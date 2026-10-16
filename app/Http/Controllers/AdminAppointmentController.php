<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Usertokens;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Services\HelperService;
use App\Models\AgentAvailability;
use App\Services\ResponseService;
use App\Models\AgentExtraTimeSlot;
use Illuminate\Support\Facades\DB;
use App\Models\AgentUnavailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\AppointmentReschedule;
use App\Models\AgentBookingPreference;
use App\Models\AppointmentCancellation;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;
use App\Services\AppointmentNotificationService;

class AdminAppointmentController extends Controller
{
    /**
     * Display main appointment settings index page
     */
    public function index()
    {
        if (!has_permissions('read', 'admin_appointment_preferences')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('admin.appointment.index');
    }

    /**
     * Display appointment preferences settings page
     */
    public function preferencesIndex()
    {
        if (!has_permissions('read', 'admin_appointment_preferences')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $adminId = User::where('type', 0)->firstOrFail()->id;
        $preferences = AgentBookingPreference::where(['admin_id' => $adminId, 'is_admin_data' => 1])->first();

        return view('admin.appointment.preferences', compact('preferences'));
    }

    /**
     * Store appointment preferences
     */
    public function storePreferences(Request $request)
    {
        if (!has_permissions('update', 'admin_appointment_preferences')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'meeting_duration_minutes'          => 'required|integer|min:15|max:480',
                'lead_time_minutes'                 => 'required|integer|min:0|max:10080',
                'buffer_time_minutes'               => 'required|integer|min:0|max:120',
                'auto_confirm'                      => 'nullable|boolean',
                'cancel_reschedule_buffer_minutes'  => 'nullable|integer|min:0|max:1440',
                'auto_cancel_after_minutes'         => 'required|integer|min:0|max:10080',
                'auto_cancel_message'               => 'nullable|string|max:500',
                'daily_booking_limit'               => 'nullable|integer|min:1|max:100',
                'availability_types'                => 'nullable|array',
                'availability_types.*'              => 'in:phone,virtual,in_person',
                'anti_spam_enabled'                 => 'nullable|boolean',
                'timezone'                          => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adminId = User::where('type', 0)->firstOrFail()->id;
            $data = $request->all();

            // Convert availability types array to JSON string
            if (isset($data['availability_types']) && is_array($data['availability_types'])) {
                $data['availability_types'] = implode(',', $data['availability_types']);
            }

            // Convert boolean fields
            $data['auto_confirm'] = $request->has('auto_confirm') ? 1 : 0;
            $data['anti_spam_enabled'] = $request->has('anti_spam_enabled') ? 1 : 0;

            AgentBookingPreference::updateOrCreate(
                ['is_admin_data' => 1, 'admin_id' => $adminId],
                $data
            );

            ResponseService::successResponse(trans('Appointment preferences updated successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Display time schedule settings page
     */
    public function timeScheduleIndex()
    {
        if (!has_permissions('read', 'admin_appointment_schedules')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $adminId = User::where('type', 0)->firstOrFail()->id;
        $adminTimezone = HelperService::getSettingData('timezone') ?? config('app.timezone');

        // Get all schedules (both active and inactive) to show the toggle state correctly
        $schedules = AgentAvailability::where(['admin_id' => $adminId, 'is_admin_data' => 1])
            ->orderBy('day_of_week')
            ->get()
            ->map(function($item) use ($adminTimezone) {
                $item->start_time = Carbon::parse($item->start_time, 'UTC')->setTimezone($adminTimezone)->format('H:i');
                $item->end_time = Carbon::parse($item->end_time, 'UTC')->setTimezone($adminTimezone)->format('H:i');
                return $item;
            })
            ->groupBy('day_of_week');

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return view('admin.appointment.time-schedule', compact('schedules', 'days'));
    }

    /**
     * Store time schedule
     */
    public function storeTimeSchedule(Request $request)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'schedule' => 'required|array',
                'schedule.*.day' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedule.*.slots' => 'nullable|array',
                'schedule.*.slots.*.id' => 'nullable|integer',
                'schedule.*.slots.*.start_time' => 'nullable|date_format:H:i',
                'schedule.*.slots.*.end_time' => 'nullable|date_format:H:i',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();

            $adminId = User::where('type', 0)->firstOrFail()->id;
            $scheduleData = [];
            $processedDays = [];

            $adminTimezone = HelperService::getSettingData('timezone') ?? config('app.timezone');

            // Process only the days that have slots (active days)
            foreach ($request->schedule as $dayItem) {
                $day = $dayItem['day'] ?? null;
                if (!$day) { continue; }

                $processedDays[] = $day;
                $slots = $dayItem['slots'] ?? [];

                foreach ($slots as $slot) {
                    $start = $slot['start_time'] ?? null;
                    $end = $slot['end_time'] ?? null;
                    if (!$start || !$end) { continue; }
                    if (strtotime($end) <= strtotime($start)) {
                        ResponseService::validationError('End time must be greater than start time for '.ucfirst($day));
                    }
                    $id = $slot['id'] ?? null;
                    $startUtc = Carbon::parse($start, $adminTimezone)->setTimezone('UTC');
                    $endUtc = Carbon::parse($end, $adminTimezone)->setTimezone('UTC');
                    $scheduleData[] = [
                        'id' => $id,
                        'admin_id' => $adminId,
                        'is_admin_data' => 1,
                        'day_of_week' => $day,
                        'start_time' => $startUtc,
                        'end_time' => $endUtc,
                        'is_active' => 1
                    ];
                }
            }

            // Validate duplicates and conflicts per day (server-side safety)
            $byDay = [];
            foreach ($scheduleData as $row) {
                $d = $row['day_of_week'];
                $byDay[$d] = $byDay[$d] ?? [];
                $byDay[$d][] = $row;
            }
            foreach ($byDay as $day => $rows) {
                // Sort by start time
                usort($rows, function ($a, $b) { return strcmp($a['start_time'], $b['start_time']); });

                $seen = [];
                $prevEnd = null;
                foreach ($rows as $r) {
                    $key = $r['start_time'].'-'.$r['end_time'];
                    if (isset($seen[$key])) {
                        ResponseService::validationError('Duplicate time slot on '.ucfirst($day).' ('.$r['start_time'].'-'.$r['end_time'].')');
                    }
                    $seen[$key] = true;

                    if ($prevEnd !== null && strcmp($r['start_time'], $prevEnd) < 0) {
                        ResponseService::validationError('Overlapping time slots on '.ucfirst($day));
                    }
                    $prevEnd = $r['end_time'];
                }
            }

            // Update or create slots for active days
            if (!empty($scheduleData)) {
                AgentAvailability::upsert($scheduleData, ['id'], ['day_of_week','start_time','end_time','is_active']);
            }

            // For days not in the processed list, we don't touch their existing slots
            // The toggle functionality handles activating/deactivating existing slots
            // This ensures that existing slots are preserved when days are toggled off/on

            DB::commit();
            ResponseService::successResponse('Time schedule updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse('Something went wrong: ' . $e->getMessage());
        }
    }

    public function removeTimeSchedule($id)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $validator = Validator::make(['id' => $id], [
                'id' => 'required|integer|exists:agent_availabilities,id',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adminId = User::where('type', 0)->firstOrFail()->id;
            $slot = AgentAvailability::where('id', $id)
                ->where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->first();

            if (!$slot) {
                ResponseService::errorResponse('Time slot not found');
            }

            $slotDay = strtolower($slot->day_of_week);
            $slotStart = $slot->start_time; // H:i[:s]
            $slotEnd = $slot->end_time;     // H:i[:s]
            $cancelReason = 'Time slot removed by admin';

            // Fetch candidate future appointments once to minimize queries
            $candidateAppointments = Appointment::where(['is_admin_appointment' => 1, 'admin_id' => $adminId])
                ->whereIn('status', ['pending','confirmed','rescheduled'])
                ->where('start_at', '>=', $slotStart)
                ->where('end_at', '<=', $slotEnd)
                ->get();

            foreach ($candidateAppointments as $appointment) {
                // Convert appointment times to admin timezone for day/time comparison
                $apptDay = strtolower($appointment->start_at->format('l')); // e.g., monday
                if ($apptDay !== $slotDay) { continue; }

                // Overlap check: start < slotEnd AND end > slotStart
                if ($appointment->start_at < $slotEnd && $appointment->end_at > $slotStart) {
                    // Idempotency: skip if already cancelled
                    if ($appointment->status === 'cancelled') { continue; }

                    // Record cancellation
                    AppointmentCancellation::create([
                        'appointment_id' => $appointment->id,
                        'reason' => $cancelReason,
                        'cancelled_by' => 'admin',
                    ]);

                    // Update appointment status
                    $appointment->update([
                        'status' => 'cancelled',
                        'last_status_updated_by' => 'admin',
                    ]);

                    // Notify concerned parties
                    try {
                        AppointmentNotificationService::sendStatusNotification(
                            $appointment,
                            'cancelled',
                            $cancelReason,
                            'admin'
                        );
                    } catch (\Exception $e) {
                        // Do not interrupt the flow on notification errors
                        Log::error('Failed to send cancellation notification on slot removal', [
                            'appointment_id' => $appointment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $slot->delete();
            ResponseService::successResponse(trans('Time slot removed successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }

    }

    /**
     * Display extra time slots page
     */
    public function extraTimeSlotsIndex()
    {
        if (!has_permissions('read', 'admin_appointment_schedules')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        return view('admin.appointment.extra-time-slots');
    }

    /**
     * Store extra time slot
     */
    public function storeExtraTimeSlot(Request $request)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adminUser = User::where('type', 0)->firstOrFail();
            $adminId = $adminUser->id;
            $adminTimezone = $adminUser->getTimezone();
            $startTimeWithDate = $request->date.' '.$request->start_time;
            $endTimeWithDate = $request->date.' '.$request->end_time;
            $startUTC = Carbon::parse($startTimeWithDate, $adminTimezone)->setTimezone('UTC');
            $endUTC = Carbon::parse($endTimeWithDate, $adminTimezone)->setTimezone('UTC');
            $dateUTC = $startUTC->toDateString();

            $dayName = strtolower($startUTC->englishDayOfWeek);

            $adminAvailability = AgentAvailability::where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->where('day_of_week', $dayName)
                ->where('is_active', 1)
                ->where(function($q) use ($startUTC, $endUTC) {
                    $q->where('start_time', '<', $endUTC)
                      ->where('end_time', '>', $startUTC);
                })
                ->exists();
            if ($adminAvailability) {
                ResponseService::errorResponse(trans('Time slot overlaps with your schedule'));
            }

            // Check for overlapping slots
            $overlaps = AgentExtraTimeSlot::where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->where('date', $request->date)
                ->where(function($q) use ($startUTC, $endUTC) {
                    $q->where('start_time', '<', $endUTC)
                      ->where('end_time', '>', $startUTC);
                })
                ->exists();

            if ($overlaps) {
                ResponseService::errorResponse(trans('Time slot overlaps with an existing slot'));
            }

            AgentExtraTimeSlot::create([
                'admin_id' => $adminId,
                'is_admin_data' => 1,
                'date' => $dateUTC,
                'start_time' => $startUTC,
                'end_time' => $endUTC,
                'reason' => $request->reason,
            ]);

            ResponseService::successResponse(trans('Extra time slot added successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Delete extra time slot
     */
    public function deleteExtraTimeSlot($id)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $adminId = User::where('type', 0)->firstOrFail()->id;
            $slot = AgentExtraTimeSlot::where('id', $id)
                ->where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->first();

            if (!$slot) {
                ResponseService::errorResponse(trans('Time slot not found'));
            }

            $slot->delete();
            ResponseService::successResponse(trans('Time slot deleted successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Toggle active status for an existing day's schedules (admin data only)
     */
    public function toggleDayActive(Request $request)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'active' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adminId = User::where('type', 0)->firstOrFail()->id;
            $day = strtolower($request->input('day'));
            $active = (int) $request->input('active') === 1 ? 1 : 0;

            // Only update if there is existing data for the day
            $exists = AgentAvailability::where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->where('day_of_week', $day)
                ->exists();

            if (!$exists) {
                ResponseService::successResponse(trans('No schedules found for this day. Nothing to update.'));
            }

            AgentAvailability::where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->where('day_of_week', $day)
                ->update(['is_active' => $active]);

            ResponseService::successResponse(trans('Day status updated successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Get extra time slots list for bootstrap table
     */
    public function getExtraTimeSlotsList(Request $request)
    {
        if (!has_permissions('read', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'date');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $adminId = User::where('type', 0)->firstOrFail()->id;

        $sql = AgentExtraTimeSlot::where(['admin_id' => $adminId, 'is_admin_data' => 1])
            ->when($request->has('search') && !empty($search), function ($query) use ($search) {
                $query->where('date', 'LIKE', "%$search%")
                    ->orWhere('start_time', 'LIKE', "%$search%")
                    ->orWhere('end_time', 'LIKE', "%$search%")
                    ->orWhere('reason', 'LIKE', "%$search%");
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        $adminTimezone = HelperService::getSettingData('timezone') ?? 'UTC';
        foreach ($res as $row) {
            $durationMinutes = 0;
            $start = Carbon::parse($row->start_time, 'UTC')->setTimezone($adminTimezone);
            $end = Carbon::parse($row->end_time, 'UTC')->setTimezone($adminTimezone);
            try {
                $durationMinutes = $start->diffInMinutes($end);
            } catch (\Exception $e) {
                $durationMinutes = 0;
            }

            $operate = '';
            if (has_permissions('delete', 'admin_appointment_schedules')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('admin.appointment.extra-time-slots.delete', $row->id));
            }

            $rows[] = [
                'id' => $row->id,
                'date' => Carbon::parse($row->date)->format('d-m-Y'),
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'duration' => $durationMinutes . ' ' . trans('minutes'),
                'operate' => $operate,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Display unavailability settings page
     */
    public function unavailabilityIndex()
    {
        if (!has_permissions('read', 'admin_appointment_schedules')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $adminId = User::where('type', 0)->firstOrFail()->id;
        $unavailabilities = AgentUnavailability::where(['admin_id' => $adminId, 'is_admin_data' => 1])
            ->orderBy('date', 'desc')
            ->paginate(20);

        // Currently not required
        // return view('admin.appointment.unavailability', compact('unavailabilities'));
        return redirect()->to(route('home'));
    }

    /**
     * Store unavailability
     */
    public function storeUnavailability(Request $request)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today',
                'unavailability_type' => 'required|in:full_day,specific_time',
                'start_time' => 'nullable|required_if:unavailability_type,specific_time|date_format:H:i',
                'end_time' => 'nullable|required_if:unavailability_type,specific_time|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adminId = User::where('type', 0)->firstOrFail()->id;

            AgentUnavailability::create([
                'admin_id' => $adminId,
                'is_admin_data' => 1,
                'date' => $request->date,
                'unavailability_type' => $request->unavailability_type,
                'start_time' => $request->unavailability_type === 'full_day' ? null : $request->start_time,
                'end_time' => $request->unavailability_type === 'full_day' ? null : $request->end_time,
                'reason' => $request->reason,
            ]);

            ResponseService::successResponse(trans('Unavailability added successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Delete unavailability
     */
    public function deleteUnavailability($id)
    {
        if (!has_permissions('update', 'admin_appointment_schedules')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $adminId = User::where('type', 0)->firstOrFail()->id;
            $unavailability = AgentUnavailability::where('id', $id)
                ->where(['admin_id' => $adminId, 'is_admin_data' => 1])
                ->first();

            if (!$unavailability) {
                ResponseService::errorResponse(trans('Unavailability not found'));
            }

            $unavailability->delete();
            ResponseService::successResponse(trans('Unavailability deleted successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Display a listing of appointments
     */
    public function appointmentManagementIndex()
    {
        if (!has_permissions('read', 'appointment_management')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('admin.appointment.management');
    }

    /**
     * Get appointments list for bootstrap table
     */
    public function getAppointmentsList(Request $request)
    {
        if (!has_permissions('read', 'appointment_management')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');
        $filter = $request->input('filter', 'all'); // all, admin, other

        if($sort == 'appointment_type'){
            $sort = 'is_admin_appointment';
        }
        if($sort == 'start_at_formatted'){
            $sort = 'start_at';
        }
        if($sort == 'end_at_formatted'){
            $sort = 'end_at';
        }


        $sql = Appointment::with(['property', 'agent', 'user', 'cancellations', 'reschedules', 'admin'])
            ->when($filter === 'admin', function($query) {
                $query->where('is_admin_appointment', 1);
            })
            ->when($filter === 'other', function($query) {
                $query->where('is_admin_appointment', 0);
            })
            ->when($request->has('search') && !empty($search), function($query) use($search){
                $query->where(function($query) use ($search){
                        $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('meeting_type', 'LIKE', "%$search%")
                        ->orWhere('status', 'LIKE', "%$search%")
                        ->orWhere('notes', 'LIKE', "%$search%")
                        ->orWhereHas('property', function ($q) use ($search) {
                            $q->where('title', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('agent', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                    });
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        $adminTimezone = HelperService::getSettingData('timezone') ?? 'UTC';

        foreach ($res as $row) {
            $tempRow = $row->toArray();

            $operate = '';
            if (has_permissions('update', 'appointment_management')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, $row->id);
            }
            if (has_permissions('delete', 'appointment_management')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('appointment-management.destroy', $row->id));
            }

            if($row->status == 'cancelled' || $row->status == 'auto_cancelled'){
                $tempRow['reason'] = $row->cancellations->last()->reason;
            }else if($row->status == 'rescheduled'){
                $tempRow['reason'] = $row->reschedules->last()->reason;
            }else{
                $tempRow['reason'] = null;
            }
            $tempRow['operate'] = $operate;
            $tempRow['appointment_type'] = $row->is_admin_appointment ? trans('Admin') : trans('Agent');
            $tempRow['date'] = $row->start_at ? Carbon::parse($row->start_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d') : '-';
            $tempRow['start_at_formatted'] = $row->start_at ? Carbon::parse($row->start_at, 'UTC')->setTimezone($adminTimezone)->format('d-m-Y H:i') : '-';
            $tempRow['end_at_formatted'] = $row->end_at ? Carbon::parse($row->end_at, 'UTC')->setTimezone($adminTimezone)->format('d-m-Y H:i') : '-';
            $tempRow['property_title'] = $row->property ? $row->property->title : '-';
            $tempRow['agent_name'] = $row->agent ? $row->agent->name : '-';
            $tempRow['agent_timezone'] = $row->agent ? $row->agent->getTimezone(true) : '-';
            $tempRow['user_name'] = $row->user ? $row->user->name : '-';
            $agentMeetingTypes = $row->is_admin_appointment ? ($row->admin ? $row->admin->agent_booking_preferences->availability_types : '-') : ($row->agent ? $row->agent->agent_booking_preferences->availability_types : '-');
            $tempRow['agent_meeting_types'] = $agentMeetingTypes ? explode(',', $agentMeetingTypes) : '-';

            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update appointment status
     */
    public function appointmentManagementUpdateStatus(Request $request)
    {
        if (!has_permissions('update', 'appointment_management')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:appointments,id',
                'status' => 'required|in:pending,confirmed,cancelled,completed,rescheduled',
                'reason' => 'nullable|required_if:status,cancelled,rescheduled|string|max:500',
                'new_date' => 'nullable|required_if:status,rescheduled|date_format:Y-m-d',
                'new_start_time' => 'nullable|required_if:status,rescheduled|date_format:H:i',
                'new_end_time' => 'nullable|required_if:status,rescheduled|date_format:H:i|after:new_start_time',
                'meeting_type' => 'nullable|in:phone,virtual,in_person',
            ], [
                'new_date.required_if' => 'Date is required for rescheduling',
                'new_start_time.required_if' => 'Start time is required for rescheduling',
                'new_end_time.required_if' => 'End time is required for rescheduling',
                'new_end_time.after' => 'End time must be greater than start time',
                'meeting_type.in' => 'Invalid meeting type',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $appointment = Appointment::find($request->id);
            if (!$appointment) {
                ResponseService::errorResponse(trans('Appointment not found'));
            }

            $oldStatus = $appointment->status;
            $newStatus = $request->status;
            $isAdminAppointment = $appointment->is_admin_appointment ? true : false;
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            if($isAdminAppointment){
                $preferences = AgentBookingPreference::where(['is_admin_data' => 1,'admin_id' => Auth::id()])->first();
                $agentTimezone = $adminTimezone;
            }else{
                $preferences = AgentBookingPreference::where('agent_id', $appointment->agent_id)->first();
                $agentTimezone = $preferences?->timezone ?: (config('app.timezone') ?? 'UTC');
            }
            $dailyLimit = $preferences?->daily_booking_limit ?: 0;
            $reason = $request->input('reason');

            // Prevent invalid state transitions
            if (in_array($oldStatus, ['completed'])) {
                ResponseService::errorResponse(trans('This appointment can no longer be updated'));
            }

            // Idempotency check
            if ($oldStatus === $newStatus && $newStatus !== 'rescheduled') {
                ResponseService::successResponse('No changes made');
            }

            // If update status is reschedule or cancel, check agent preference cancel_reschedule_buffer_minutes
            if (in_array($newStatus, ['rescheduled', 'cancelled'])) {
                $cancelRescheduleBuffer = (int) ($preferences->cancel_reschedule_buffer_minutes ?? 0);
                if ($cancelRescheduleBuffer > 0) {
                    // Appointment start time in agent timezone
                    $appointmentStart = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
                    $nowAgent = Carbon::now($agentTimezone);
                    $appointmentDate = Carbon::parse($appointment->start_at, 'UTC');
                    if($nowAgent >= $appointmentDate){
                        $diffInMinutes = $appointmentStart->diffInMinutes($nowAgent, false);
                    }else{
                        $diffInMinutes = $nowAgent->diffInMinutes($appointmentStart, false);
                    }
                    if ($diffInMinutes <= $cancelRescheduleBuffer) {
                        ResponseService::validationError(
                            'You cannot ' . ($newStatus === 'rescheduled' ? 'reschedule' : 'cancel') . ' this appointment within ' . $cancelRescheduleBuffer . ' minutes of its start time.'
                        );
                    }
                }
            }

            DB::beginTransaction();

            try {
                // Handle reschedule
                if ($newStatus === 'rescheduled') {
                    // Validate required fields for reschedule
                    if (!$request->has('new_date') || !$request->has('new_start_time') || !$request->has('new_end_time')) {
                        DB::rollBack();
                        ResponseService::validationError(trans('Date, start time, and end time are required for rescheduling'));
                    }

                    $newDate = $request->input('new_date');
                    $newStartTime = $request->input('new_start_time');
                    $newEndTime = $request->input('new_end_time');

                    // Check if agent has reached daily limit
                    if($dailyLimit > 0){
                        $startOfDay = Carbon::parse($newDate.' '.'00:00:00', $adminTimezone)->setTimezone('UTC');
                        $endOfDay = Carbon::parse($newDate.' '.'23:59:59', $adminTimezone)->setTimezone('UTC');
                        $dailyAppointmentCount = Appointment::where('id', '!=', $appointment->id)->whereBetween('start_at', [$startOfDay, $endOfDay])
                        ->when($isAdminAppointment, function ($query) use ($appointment) {
                            $query->where('admin_id', $appointment->admin_id)
                            ->where('is_admin_appointment', 1)
                            ->whereNotIn('status', ['auto_cancelled','cancelled','pending','completed']);
                        }, function ($query) use ($appointment) {
                            $query->where('agent_id', $appointment->agent_id);
                        })
                        ->count();
                        // If daily appointment count is greater than or equal to daily limit, return error
                        if ($dailyAppointmentCount >= $dailyLimit) {
                            DB::rollBack();
                            ResponseService::validationError(
                                trans('Agent cannot accept more appointments on provided date.')
                            );
                        }
                    }

                    // Create new datetime objects with proper timezone handling
                    $newStartAdmin = Carbon::parse($newDate . ' ' . $newStartTime, $adminTimezone);
                    $newEndAdmin = Carbon::parse($newDate . ' ' . $newEndTime, $adminTimezone);
                    $newStartAgent = (clone $newStartAdmin)->setTimezone('UTC');
                    $newEndAgent = (clone $newEndAdmin)->setTimezone('UTC');
                    $newStartUtc = (clone $newStartAdmin)->setTimezone('UTC');
                    $newEndUtc = (clone $newEndAdmin)->setTimezone('UTC');

                    // Check if the new time is in the past
                    if ($newStartAdmin->isPast()) {
                        DB::rollBack();
                        ResponseService::validationError(trans('Cannot reschedule to a past date/time'));
                    }

                    // Check if it's the same time (idempotency)
                    if ($appointment->start_at === $newStartUtc->format('Y-m-d H:i:s') &&
                        $appointment->end_at === $newEndUtc->format('Y-m-d H:i:s')) {
                        DB::rollBack();
                        ResponseService::successResponse(trans('No changes made'));
                    }

                    // Validate end time is after start time
                    if ($newEndAgent <= $newStartAgent) {
                        DB::rollBack();
                        ResponseService::validationError(trans('End time must be greater than start time'));
                    }

                    // Validate meeting duration if set
                    $meetingDurationMinutes = (int) ($preferences->meeting_duration_minutes ?? 0);
                    if ($meetingDurationMinutes > 0 && $newStartAgent->diffInMinutes($newEndAgent) !== $meetingDurationMinutes) {
                        DB::rollBack();
                        ResponseService::validationError(trans('Invalid time duration'));
                    }

                    // Check agent availability
                    $dayName = strtolower($newStartAgent->englishDayOfWeek);
                    if($isAdminAppointment){
                        $windows = AgentAvailability::where('admin_id', $appointment->admin_id)
                            ->where('is_active', 1)
                            ->where('is_admin_data', 1)
                            ->where('day_of_week', $dayName)
                            ->get();
                        $extraWindows = AgentExtraTimeSlot::where(['admin_id' => $appointment->admin_id])
                            ->where('date', $newDate)
                            ->where('is_admin_data', 1)
                            ->get();
                    }else{
                        $windows = AgentAvailability::where('agent_id', $appointment->agent_id)
                            ->where('is_active', 1)
                            ->where('day_of_week', $dayName)
                            ->get();
                        $extraWindows = AgentExtraTimeSlot::where(['agent_id' => $appointment->agent_id])
                            ->where('date', $newDate)
                            ->get();
                    }
                    $windows = $windows->merge($extraWindows);

                    // Build available slots
                    $bufferMinutes = max(0, (int) ($preferences->buffer_time_minutes ?? 0));
                    $slots = [];
                    foreach ($windows as $w) {
                        $winStart = Carbon::parse($newDate . ' ' . $w->start_time);
                        $winEnd = Carbon::parse($newDate . ' ' . $w->end_time);
                        if ($winEnd <= $winStart) { continue; }
                        $cursor = (clone $winStart);
                        while (true) {
                            $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                            if ($candidateEnd > $winEnd) { break; }
                            $slots[] = [ 'start' => (clone $cursor), 'end' => (clone $candidateEnd) ];
                            $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                        }
                    }

                    if (empty($slots)) {
                        DB::rollBack();
                        ResponseService::validationError(trans('Selected time is outside agent availability'));
                    }

                    // // Check for agent unavailability
                    // $unavailabilities = AgentUnavailability::where('agent_id', $appointment->agent_id)
                    //     ->where('date', $newDate)
                    //     ->get();

                    // Check for existing appointments with same agent (excluding current appointment)
                    $dayStartAgent = Carbon::parse($newDate . ' 00:00:00', $agentTimezone);
                    $dayEndAgent = Carbon::parse($newDate . ' 23:59:59', $agentTimezone);
                    $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
                    $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();

                    if($isAdminAppointment){
                        $sameDayAppointments = Appointment::where('admin_id', $appointment->admin_id)
                            ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                            ->where('id', '!=', $appointment->id)
                            ->where('start_at', '<', $dayEndUtc)
                            ->where('end_at', '>', $dayStartUtc)
                            ->get();
                    }else{
                        $sameDayAppointments = Appointment::where('agent_id', $appointment->agent_id)
                            ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                            ->where('id', '!=', $appointment->id)
                            ->where('start_at', '<', $dayEndUtc)
                            ->where('end_at', '>', $dayStartUtc)
                            ->get();
                    }

                    // Find exact requested slot in slots and ensure it's not blocked
                    $slotValid = false;
                    foreach ($slots as $s) {
                        $sStartAgent = $s['start'];
                        $sEndAgent = $s['end'];
                        if ($sStartAgent->format('H:i') !== $newStartAgent->format('H:i') || $sEndAgent->format('H:i') !== $newEndAgent->format('H:i')) {
                            continue;
                        }

                        // // Check unavailability overlap
                        // $blockedByUnavailability = false;
                        // foreach ($unavailabilities as $u) {
                        //     if ($u->unavailability_type === 'full_day') {
                        //         $blockedByUnavailability = true;
                        //         break;
                        //     }
                        //     if ($u->start_time && $u->end_time) {
                        //         $uStart = Carbon::parse($newDate . ' ' . $u->start_time, $agentTimezone);
                        //         $uEnd = Carbon::parse($newDate . ' ' . $u->end_time, $agentTimezone);
                        //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) {
                        //             $blockedByUnavailability = true;
                        //             break;
                        //         }
                        //     }
                        // }
                        // if ($blockedByUnavailability) { continue; }

                        // Check appointment overlap with buffer
                        $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->toDateTimeString();
                        $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->toDateTimeString();
                        $blockedByAppointment = false;
                        foreach ($sameDayAppointments as $a) {
                            if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                                $blockedByAppointment = true;
                                break;
                            }
                        }
                        if ($blockedByAppointment) { continue; }

                        $slotValid = true;
                        break;
                    }

                    if (!$slotValid) {
                        DB::rollBack();
                        ResponseService::validationError(trans('Selected time is not available'));
                    }

                    // Record reschedule
                    AppointmentReschedule::create([
                        'appointment_id' => $appointment->id,
                        'old_start_at' => $appointment->start_at,
                        'old_end_at' => $appointment->end_at,
                        'new_start_at' => $newStartUtc,
                        'new_end_at' => $newEndUtc,
                        'reason' => $reason,
                        'rescheduled_by' => 'admin',
                    ]);

                    $newMeetingType = $request->input('meeting_type');
                    // Update appointment
                    $updateData = [
                        'status' => 'rescheduled',
                        'start_at' => $newStartUtc,
                        'end_at' => $newEndUtc,
                        'last_status_updated_by' => 'admin',
                        'meeting_type' => $newMeetingType,
                    ];

                    // Check if meeting type is being changed
                    $oldMeetingType = $appointment->meeting_type;
                    $meetingTypeChanged = false;

                    // Update meeting type if provided
                    if ($oldMeetingType !== $newMeetingType) {
                        $meetingTypeChanged = true;
                    }

                    $appointment->update($updateData);

                    // Send meeting type change notification if meeting type was changed
                    if ($meetingTypeChanged) {
                        AppointmentNotificationService::sendMeetingTypeChangeNotification(
                            $appointment,
                            $oldMeetingType,
                            $newMeetingType,
                            'admin'
                        );
                    }
                }
                // Handle cancellation
                elseif ($newStatus === 'cancelled') {
                    // Check if already cancelled (idempotency)
                    if ($oldStatus === 'cancelled') {
                        DB::rollBack();
                        ResponseService::successResponse(trans('No changes made'));
                    }

                    // Record cancellation
                    AppointmentCancellation::create([
                        'appointment_id' => $appointment->id,
                        'reason' => $reason,
                        'cancelled_by' => 'admin',
                    ]);

                    // Update appointment
                    $appointment->update([
                        'status' => 'cancelled',
                        'last_status_updated_by' => 'admin',
                    ]);
                }
                // Handle other status updates
                else {
                    $updateData = [
                        'status' => $newStatus,
                        'last_status_updated_by' => 'admin',
                    ];
                    $appointment->update($updateData);
                    // if($oldStatus === $newStatus){
                    //     $updateData = [
                    //         'status' => $newStatus,
                    //         'last_status_updated_by' => 'admin',
                    //     ];

                    //     // Check if meeting type is being changed
                    //     $oldMeetingType = $appointment->meeting_type;
                    //     $newMeetingType = $request->input('meeting_type');
                    //     $meetingTypeChanged = false;

                    //     // Update meeting type if provided
                    //     if ($request->has('meeting_type') && $oldMeetingType !== $newMeetingType) {
                    //         $updateData['meeting_type'] = $newMeetingType;
                    //         $meetingTypeChanged = true;
                    //     }

                    //     $appointment->update($updateData);

                    //     // Send meeting type change notification if meeting type was changed
                    //     if ($meetingTypeChanged) {
                    //         AppointmentNotificationService::sendMeetingTypeChangeNotification(
                    //             $appointment,
                    //             $oldMeetingType,
                    //             $newMeetingType,
                    //             'admin'
                    //         );
                    //     }
                    // }
                }

                DB::commit();

                // Send notifications for status changes
                $this->sendAppointmentStatusNotifications($appointment, $newStatus, $reason, $oldStatus);

                ResponseService::successResponse(trans('Appointment status updated successfully'));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Get available slots for a specific date and agent
     */
    public function getAvailableSlots(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|exists:appointments,id',
                'date' => 'required|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            $appointment = Appointment::find($request->appointment_id);
            if (!$appointment) {
                return ResponseService::errorResponse(trans('Appointment not found'));
            }

            $dateKey = $request->input('date');
            $agentId = $appointment->agent_id;
            $isAdminAppointment = $appointment->is_admin_appointment;

            // Get agent preferences and timezone
            if($isAdminAppointment){
                $preferences = AgentBookingPreference::where(['is_admin_data' => 1,'admin_id' => Auth::id()])->first();
                $agentTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            }else{
                $preferences = AgentBookingPreference::where('agent_id', $agentId)->first();
                $agentTimezone = $preferences?->timezone ?: (config('app.timezone') ?? 'UTC');
            }

            $meetingDurationMinutes = (int) ($preferences->meeting_duration_minutes ?? 60);
            $bufferMinutes = max(0, (int) ($preferences->buffer_time_minutes ?? 0));

            // Handle past dates and same-day past times in agent's timezone
            $agentNow = Carbon::now($agentTimezone);
            $selectedDateAgent = Carbon::parse($dateKey . ' 00:00:00', $agentTimezone);
            $todayAgentStart = (clone $agentNow)->startOfDay();
            if ($selectedDateAgent->lt($todayAgentStart)) {
                return ResponseService::successResponse(trans('No slots available'), [
                    'available_slots' => [],
                    'message' => trans('No available slots for the selected date')
                ]);
            }

            // Get agent availability for the day
            $dayName = strtolower(Carbon::parse($dateKey)->englishDayOfWeek);
            $agentAvailabilityQuery = AgentAvailability::where(['is_active' => 1, 'day_of_week' => $dayName]);
            $agentExtraTimeSlotQuery = AgentExtraTimeSlot::where('date', $dateKey);
            if($isAdminAppointment){
                $windows = $agentAvailabilityQuery->where(['admin_id' => Auth::id(),'is_admin_data' => 1])->get();
                $extraWindows = $agentExtraTimeSlotQuery->where(['admin_id' => Auth::id(), 'is_admin_data' => 1])->get();
            }else{
                $windows = $agentAvailabilityQuery->where(['agent_id' => $agentId])->get();
                $extraWindows = $agentExtraTimeSlotQuery->where(['agent_id' => $agentId])->get();
            }
            $windows = $windows->merge($extraWindows);

            // Build available slots
            $slots = [];
            foreach ($windows as $w) {
                $winStart = Carbon::parse($dateKey . ' ' . $w->start_time);
                $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time);
                if ($winEnd <= $winStart) { continue; }
                $cursor = (clone $winStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $winEnd) { break; }
                    $slots[] = [ 'start' => (clone $cursor), 'end' => (clone $candidateEnd) ];
                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }

            if (empty($slots)) {
                return ResponseService::successResponse(trans('No slots available'), [
                    'available_slots' => [],
                    'message' => trans('No available slots for the selected date')
                ]);
            }

            // // Check for agent unavailability
            // $unavailabilities = AgentUnavailability::where('agent_id', $agentId)
            //     ->where('date', $dateKey)
            //     ->get();

            // Check for existing appointments (excluding current appointment)
            $dayStartAgent = Carbon::parse($dateKey . ' 00:00:00', $agentTimezone);
            $dayEndAgent = Carbon::parse($dateKey . ' 23:59:59', $agentTimezone);
            $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
            $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();

            $appointmentsQuery = Appointment::whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where('id', '!=', $appointment->id)->where(function($query) use ($dayEndUtc, $dayStartUtc){
                    $query->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc);
                });

            if ($isAdminAppointment) {
                $appointmentsQuery->where(['admin_id' => Auth::id(), 'is_admin_appointment' => 1]);
            } else {
                $appointmentsQuery->where('agent_id', $agentId);
            }

            $appointments = $appointmentsQuery->get();

            // Filter slots by unavailability and appointments
            $availableSlots = [];
	        foreach ($slots as $s) {
                $sStartAgent = $s['start'];
                $sEndAgent = $s['end'];

	                // Skip past slots when the selected date is today (agent timezone)
	                if ($selectedDateAgent->equalTo($todayAgentStart)) {
	                    $slotStartInAgentTz = (clone $sStartAgent)->setTimezone($agentTimezone);
	                    if ($slotStartInAgentTz->lte($agentNow)) { continue; }
	                }

                // // Check unavailability overlap
                // $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u) {
                //     if ($u->unavailability_type === 'full_day') {
                //         $blockedByUnavailability = true;
                //         break;
                //     }
                //     if ($u->start_time && $u->end_time) {
                //         $uStart = Carbon::parse($dateKey . ' ' . $u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey . ' ' . $u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) {
                //             $blockedByUnavailability = true;
                //             break;
                //         }
                //     }
                // }
                // if ($blockedByUnavailability) { continue; }

                // Check appointment overlap with buffer
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) { continue; }

                // Convert to admin timezone for display
                $startAdmin = (clone $sStartAgent)->setTimezone($agentTimezone);
                $endAdmin = (clone $sEndAgent)->setTimezone($agentTimezone);

                $availableSlots[] = [
                    'start_time' => $startAdmin->format('H:i'),
                    'end_time' => $endAdmin->format('H:i'),
                    'start_at' => $startAdmin->toIso8601String(),
                    'end_at' => $endAdmin->toIso8601String(),
                    'display' => $startAdmin->format('H:i') . ' - ' . $endAdmin->format('H:i')
                ];
            }

            return ResponseService::successResponse(trans('Available slots retrieved'), [
                'available_slots' => $availableSlots,
                'date' => $dateKey,
                'agent_timezone' => $agentTimezone,
                'admin_timezone' => $agentTimezone
            ]);

        } catch (Exception $e) {
            return ResponseService::errorResponse();
        }
    }

    /**
     * Delete appointment
     */
    public function appointmentManagementDestroy($id)
    {
        if (!has_permissions('delete', 'appointment_management')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $appointment = Appointment::find($id);
            if (!$appointment) {
                ResponseService::errorResponse(trans('Appointment not found'));
            }

            $appointment->delete();
            ResponseService::successResponse(trans('Appointment deleted successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse();
        }
    }

    /**
     * Send appointment status notifications to relevant parties
     */
    private function sendAppointmentStatusNotifications(Appointment $appointment, string $newStatus, string $reason = null, string $oldStatus = null)
    {
        try {
            // Only send notifications for meaningful status changes
            if ($oldStatus === $newStatus) {
                return;
            }

            // Send notifications to both agent and user when admin makes changes
            $agent = $appointment->agent;
            $user = $appointment->user;

            // Send notification to agent (if exists and different from user)
            if ($agent && (!$user || $agent->id !== $user->id)) {
                $this->sendNotificationToParty($appointment, $agent, $newStatus, $reason, 'admin');
            }

            // Send notification to user (if exists and different from agent)
            if ($user && (!$agent || $user->id !== $agent->id)) {
                $this->sendNotificationToParty($appointment, $user, $newStatus, $reason, 'admin');
            }

        } catch (Exception $e) {
            // Log the error but don't fail the main operation
            Log::error("Failed to send appointment status notifications", [
                'appointment_id' => $appointment->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to a specific party (agent or user)
     */
    private function sendNotificationToParty(Appointment $appointment, $targetParty, string $newStatus, string $reason = null, string $changedBy = 'admin')
    {
        try {
            // Get the other party for context
            $otherParty = null;
            if ($targetParty->id === $appointment->agent_id) {
                $otherParty = $appointment->user;
            } else {
                $otherParty = $appointment->agent;
            }

            // Send email notification
            $this->sendEmailNotificationToParty($appointment, $targetParty, $otherParty, $newStatus, $reason);

            // Send push notification
            $this->sendPushNotificationToParty($appointment, $targetParty, $newStatus, $reason);

            // Store notification in database
            $this->storeNotificationToParty($appointment, $targetParty, $newStatus, $reason);

        } catch (Exception $e) {
            Log::error("Failed to send notification to party", [
                'appointment_id' => $appointment->id,
                'target_party_id' => $targetParty->id,
                'status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email notification to a specific party
     */
    private function sendEmailNotificationToParty(Appointment $appointment, $targetParty, $otherParty, string $status, string $reason = null)
    {
        try {
            if (empty($targetParty->email)) {
                return false;
            }

            $emailTypeData = HelperService::getEmailTemplatesTypes('appointment_status');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            // Get timezone for the target party
            $targetTimezone = $targetParty->timezone ?? config('app.timezone');
            $startAt = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($targetTimezone);
            $endAt = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($targetTimezone);

            // Get the actual agent and customer from the appointment
            $actualAgent = $appointment->agent;
            $actualCustomer = $appointment->user;

            $variables = [
                'app_name' => $appName,
                'user_name' => $targetParty->name,
                'property_name' => $appointment->property?->title ?? 'N/A',
                'agent_name' => $actualAgent?->name ?? 'N/A',
                'customer_name' => $actualCustomer?->name ?? 'N/A',
                'meeting_status' => $status,
                'meeting_type' => $appointment->meeting_type,
                'start_time' => $startAt->format('H:i'),
                'end_time' => $endAt->format('H:i'),
                'date' => $startAt->format('d M Y'),
                'reason' => $reason,
                'email' => $targetParty->email,
            ];

            if (empty($templateRaw)) {
                $templateRaw = $this->getDefaultEmailTemplate($status);
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $targetParty->email,
                'title' => $this->getEmailTitle($status),
            ];

            HelperService::sendMail($data);
            return true;

        } catch (Exception $e) {
            Log::error("Failed to send appointment email notification", [
                'appointment_id' => $appointment->id,
                'target_email' => $targetParty->email ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send push notification to a specific party
     */
    private function sendPushNotificationToParty(Appointment $appointment, $targetParty, string $status, string $reason = null)
    {
        try {
            $fcmTokens = Usertokens::where('customer_id', $targetParty->id)
                ->pluck('fcm_id')
                ->toArray();

            if (empty($fcmTokens)) {
                return false;
            }

            $title = $this->getNotificationTitle($status);
            $body = $this->getNotificationBody($status, $reason);

            $fcmMsg = [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'appointment_status',
                    'appointment_id' => $appointment->id,
                    'status' => $status,
                    'reason' => $reason,
                ],
            ];

            send_push_notification($fcmTokens, $fcmMsg);
            return true;

        } catch (Exception $e) {
            Log::error("Failed to send appointment push notification", [
                'appointment_id' => $appointment->id,
                'target_id' => $targetParty->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Store notification in database for a specific party
     */
    private function storeNotificationToParty(Appointment $appointment, $targetParty, string $status, string $reason = null)
    {
        try {
            $title = $this->getNotificationTitle($status);
            $body = $this->getNotificationBody($status, $reason);

            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2', // Appointment notification type
                'send_type' => '0', // Push notification
                'customers_id' => $targetParty->id,
                'propertys_id' => $appointment->property?->id ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to store appointment notification", [
                'appointment_id' => $appointment->id,
                'target_id' => $targetParty->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get default email template based on status
     */
    private function getDefaultEmailTemplate(string $status): string
    {
        $templates = [
            'confirmed' => 'Your appointment has been confirmed for {property_name} on {date} between {start_time}-{end_time}.',
            'cancelled' => 'Your appointment for {property_name} on {date} has been cancelled. {start_reason}Reason: {reason}{end_reason}',
            'rescheduled' => 'Your appointment for {property_name} has been rescheduled to {date} between {start_time}-{end_time}.',
            'pending' => 'Your appointment request for {property_name} on {date} is pending confirmation.',
            'completed' => 'Your appointment for {property_name} on {date} has been marked as completed.',
        ];

        return $templates[$status] ?? 'Your appointment status has been updated to {meeting_status}.';
    }

    /**
     * Get email title based on status
     */
    private function getEmailTitle(string $status): string
    {
        $titles = [
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'rescheduled' => 'Appointment Rescheduled',
            'pending' => 'Appointment Pending',
            'completed' => 'Appointment Completed',
        ];

        return $titles[$status] ?? 'Appointment Status Updated';
    }

    /**
     * Get notification title based on status
     */
    private function getNotificationTitle(string $status): string
    {
        $titles = [
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'rescheduled' => 'Appointment Rescheduled',
            'pending' => 'Appointment Pending',
            'completed' => 'Appointment Completed',
        ];

        return $titles[$status] ?? 'Appointment Status Updated';
    }

    /**
     * Get notification body based on status
     */
    private function getNotificationBody(string $status, string $reason = null): string
    {
        $baseMessages = [
            'confirmed' => 'Your appointment has been confirmed',
            'cancelled' => 'Your appointment has been cancelled',
            'rescheduled' => 'Your appointment has been rescheduled',
            'pending' => 'Your appointment request is pending confirmation',
            'completed' => 'Your appointment has been marked as completed',
        ];

        $message = $baseMessages[$status] ?? 'Your appointment status has been updated';

        if ($reason && in_array($status, ['cancelled', 'rescheduled'])) {
            $message .= '. Reason: ' . $reason;
        }

        return $message;
    }
}
