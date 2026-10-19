<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentAvailability;
use App\Models\AgentBookingPreference;
use App\Models\AgentExtraTimeSlot;
use App\Models\AgentUnavailability;
use App\Models\AgentVerification;
use App\Models\Appointment;
use App\Models\AppointmentCancellation;
use App\Models\AppointmentReschedule;
use App\Models\BlockedUserForAppointment;
use App\Models\Customer;
use App\Models\Property;
use App\Models\ReportUserByAgent;
use App\Models\User;
use App\Models\VerifyCustomer;
use App\Services\ApiResponseService;
use App\Services\AppointmentNotificationService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppointmentApiController extends Controller
{
    public function storeBookingPreferences(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_duration_minutes' => 'required|integer',
                'lead_time_minutes' => 'required|integer',
                'buffer_time_minutes' => 'required|integer',
                'auto_confirm' => 'nullable|in:0,1',
                'cancel_reschedule_buffer_minutes' => 'nullable|integer',
                'auto_cancel_after_minutes' => 'required|integer',
                'auto_cancel_message' => 'nullable|string',
                'daily_booking_limit' => 'nullable|integer',
                'availability_types' => 'nullable|string',
                'anti_spam_enabled' => 'nullable|boolean',
                'timezone' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();

            // Check if the user is an agent
            if (! $isAgent) {
                return ApiResponseService::validationError('You are not authorized to store booking preferences');
            }

            // Check if the provided timezone is valid
            if ($request->timezone && ! empty($request->timezone)) {
                $timezone = $request->timezone;
                if (! in_array($timezone, DateTimeZone::listIdentifiers())) {
                    return ApiResponseService::validationError(trans('Invalid timezone provided.'));
                }
            }

            // Check if the availability types are valid
            if ($request->has('availability_types') && ! empty($request->availability_types)) {
                $availabilityTypes = explode(',', $request->availability_types);
                $availabilityTypes = array_map('trim', $availabilityTypes);
                $availabilityValidator = Validator::make($availabilityTypes, [
                    '*' => 'in:phone,virtual,in_person',
                ], [
                    '*.in' => trans('Invalid availability type provided.'),
                ]);
                if ($availabilityValidator->fails()) {
                    return ApiResponseService::validationError($availabilityValidator->errors()->first());
                }
            }

            // Create booking preferences
            $bookingPreferencesData = [
                'agent_id' => $loggedInUser->id,
                'meeting_duration_minutes' => $request->meeting_duration_minutes,
                'lead_time_minutes' => $request->lead_time_minutes,
                'buffer_time_minutes' => $request->buffer_time_minutes,
                'auto_confirm' => $request->auto_confirm,
                'cancel_reschedule_buffer_minutes' => $request->cancel_reschedule_buffer_minutes,
                'auto_cancel_after_minutes' => $request->auto_cancel_after_minutes,
                'auto_cancel_message' => $request->auto_cancel_message,
                'daily_booking_limit' => $request->daily_booking_limit,
                'availability_types' => $request->availability_types,
                'anti_spam_enabled' => $request->anti_spam_enabled,
                'timezone' => $request->timezone,
            ];
            AgentBookingPreference::upsert($bookingPreferencesData, ['agent_id'], ['agent_id', 'meeting_duration_minutes', 'lead_time_minutes', 'buffer_time_minutes', 'auto_confirm', 'cancel_reschedule_buffer_minutes', 'auto_cancel_after_minutes', 'auto_cancel_message', 'daily_booking_limit', 'availability_types', 'anti_spam_enabled', 'timezone']);
            $bookingPreferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();

            return ApiResponseService::successResponse('Booking preferences stored successfully', $bookingPreferences);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function setAgentTimeSchedule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'schedule' => 'required_without:deleted_ids|nullable|array',
                'schedule.*.id' => 'nullable|integer|exists:agent_availabilities,id',
                'schedule.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedule.*.start_time' => 'required|date_format:H:i',
                'schedule.*.end_time' => 'required|date_format:H:i',
                'deleted_ids' => 'nullable|array',
                'deleted_ids.*' => 'nullable|integer|exists:agent_availabilities,id',
            ], [
                'schedule.*.id.integer' => trans('ID must be a number'),
                'schedule.*.id.exists' => trans('ID is not valid'),
                'schedule.*.day.required' => trans('Day is required'),
                'schedule.*.day.string' => trans('Day must be a string'),
                'schedule.*.day.in' => trans('Invalid day provided'),
                'schedule.*.start_time.required' => trans('Start time is required'),
                'schedule.*.start_time.date_format' => trans('Invalid start time format'),
                'schedule.*.end_time.required' => trans('End time is required'),
                'schedule.*.end_time.date_format' => trans('Invalid end time format'),
                'deleted_ids.array' => trans('Deleted slots IDs must be an array'),
                'deleted_ids.*.integer' => trans('Deleted slots IDs must be a number'),
                'deleted_ids.*.exists' => trans('Deleted slots IDs is not valid'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $loggedInUser = Auth::user();

            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();

            $agentTimeZone = $loggedInUser->getTimezone(true);

            // Check if the user is an agent
            if (! $isAgent) {
                return ApiResponseService::validationError('You are not authorized to set agent time schedule');
            }

            if ($request->has('schedule') && ! empty($request->schedule)) {
                // Check if the schedule is valid
                $schedule = $request->schedule;

                // Validate for overlapping time slots within the same request
                HelperService::validateTimeSlotOverlaps($schedule);

                // Validate against existing time slots in database
                HelperService::validateExistingTimeSlotOverlaps($schedule, $loggedInUser, $request->deleted_ids ?? []);

                $schedule = array_map(function ($item) use ($loggedInUser, $agentTimeZone) {
                    return [
                        'agent_id' => $loggedInUser->id,
                        'id' => $item['id'] ?? null,
                        'day_of_week' => $item['day'],
                        'start_time' => Carbon::parse($item['start_time'], $agentTimeZone)->setTimezone('UTC')->toDateTimeString(),
                        'end_time' => Carbon::parse($item['end_time'], $agentTimeZone)->setTimezone('UTC')->toDateTimeString(),
                        'is_active' => 1,
                    ];
                }, $schedule);

                // Update or create schedule
                AgentAvailability::upsert($schedule, ['id'], ['agent_id', 'day_of_week', 'start_time', 'end_time', 'is_active']);
            }

            // Remove availabilities of agents by ids
            if ($request->has('deleted_ids') && ! empty($request->deleted_ids)) {
                AgentAvailability::whereIn('id', $request->deleted_ids)->delete();
            }

            // Get Updated Agent schedule
            $agentSchedule = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->get()
                ->groupBy('day_of_week')
                ->map(function ($items) use ($agentTimeZone) {
                    return $items->map(function ($item) use ($agentTimeZone) {
                        return [
                            'id' => $item->id,
                            'start_time' => Carbon::parse($item->start_time, 'UTC')->setTimezone($agentTimeZone)->format('H:i'),
                            'end_time' => Carbon::parse($item->end_time, 'UTC')->setTimezone($agentTimeZone)->format('H:i'),
                        ];
                    });
                });

            DB::commit();

            return ApiResponseService::successResponse('Agent time schedule set successfully', $agentSchedule);
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getMonthlyTimeSlots(Request $request)
    {
        try {
            $currentYear = date('Y');
            $validator = Validator::make($request->all(), [
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:'.$currentYear,
                'agent_id' => 'nullable|integer',
            ], [
                'month.integer' => trans('Month must be a number'),
                'month.min' => trans('Month must be between 1 and 12'),
                'month.max' => trans('Month must be between 1 and 12'),
                'year.integer' => trans('Year must be a number'),
                'year.min' => trans('Year must be greater than or equal to '.$currentYear),
                'agent_id.integer' => trans('Agent ID must be a number'),
                'agent_id.exists' => trans('Agent ID is not valid'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            if ($request->has('month') && $request->has('year')) {
                $requestMonth = (int) $request->month;
                $requestYear = (int) $request->year;
                $currentMonth = (int) date('m');
                $currentYear = (int) date('Y');

                if ($requestYear >= $currentYear && $requestMonth < $currentMonth) {
                    return ApiResponseService::validationError('Month cannot be greater than current month for the current year.');
                }
            }

            $agentId = $request->agent_id;
            if ($agentId == 0) {
                $agentData = User::where('type', 0)->first();
                if (Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1])->count() == 0) {
                    return ApiResponseService::validationError('Agent has no properties');
                }
            } else {
                $agentData = Customer::where('id', $agentId)->first();
                if ($agentData->isActive == 0) {
                    return ApiResponseService::validationError('Agent is not active');
                }
                if ($agentData->property()->whereIn('propery_type', [0, 1])->onlyActive()->count() == 0) {
                    return ApiResponseService::validationError('Agent has no properties');
                }
            }
            if (! $agentData) {
                return ApiResponseService::validationError('Agent not found');
            }
            if (isset($agentData->is_agent) && $agentData->is_agent == false) {
                return ApiResponseService::validationError('Agent not found');
            }

            $month = (int) $request->month;
            $year = (int) $request->year;

            // Preferences (buffer + timezone)
            if ($agentId == 0) {
                $bookingPref = AgentBookingPreference::where(['admin_id' => $agentData->id, 'is_admin_data' => 1])->first();
            } else {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
            }
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            $availableMeetingTypes = $bookingPref->availability_types;
            $bufferMinutes = (int) ($bookingPref->buffer_time_minutes ?? 0);
            $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));

            // Validate meeting duration and buffer values
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError('Meeting duration must be greater than 0 minutes.');
            }
            if ($bufferMinutes < 0) {
                $bufferMinutes = 0;
            }

            // Admin timezone for display
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';

            // Month bounds in agent timezone
            $monthStartTz = Carbon::create($year, $month, 1, 0, 0, 0, $agentTimezone)->startOfDay();
            $monthEndTz = (clone $monthStartTz)->endOfMonth()->endOfDay();

            // Admin timezone month bounds for keys
            $adminMonthStart = Carbon::create($year, $month, 1, 0, 0, 0, $adminTimezone)->startOfDay();
            $adminMonthEnd = (clone $adminMonthStart)->endOfMonth()->endOfDay();

            if ($agentId == 0) {
                $weeklyAvailability = AgentAvailability::where('admin_id', $agentData->id)
                    ->where('is_active', 1)
                    ->where('is_admin_data', 1)
                    ->get()
                    ->groupBy('day_of_week');
            } else {
                // Load weekly availability (grouped by day_of_week)
                $weeklyAvailability = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->get()
                    ->groupBy('day_of_week');
            }

            // Load extra time slots for the month (grouped by date)
            $monthStartDate = $monthStartTz->toDateString();
            $monthEndDate = $monthEndTz->toDateString();
            if ($agentId == 0) {
                $extraSlotsByDate = AgentExtraTimeSlot::where('admin_id', $agentData->id)
                    ->where('is_admin_data', 1)
                    ->whereBetween('date', [$monthStartDate, $monthEndDate])
                    ->get()
                    ->groupBy('date');
            } else {
                $extraSlotsByDate = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereBetween('date', [$monthStartDate, $monthEndDate])
                    ->get()
                    ->groupBy('date');
            }

            $daysSlots = [];
            // Pre-initialize all admin dates in the month to ensure presence of empty arrays
            $adminCursor = (clone $adminMonthStart);
            while ($adminCursor <= $adminMonthEnd) {
                $daysSlots[$adminCursor->toDateString()] = [];
                $adminCursor->addDay();
            }
            // Do not include past dates: start from today or monthStartTz, whichever is later
            if ($agentId == 0) {
                $todayAgentTz = Carbon::now($adminTimezone)->startOfDay();
            } else {
                $todayAgentTz = Carbon::now($agentTimezone)->startOfDay();
            }
            $cursor = $monthStartTz->greaterThan($todayAgentTz) ? (clone $monthStartTz) : $todayAgentTz;
            // Get the current time in agent timezone for comparison
            if ($agentId == 0) {
                $nowAgentTz = Carbon::now($adminTimezone);
            } else {
                $nowAgentTz = Carbon::now($agentTimezone);
            }
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);

            while ($cursor <= $monthEndTz) {
                $dateKey = $cursor->toDateString();
                $dayName = strtolower($cursor->englishDayOfWeek); // monday..sunday

                $windows = $weeklyAvailability->get($dayName, collect());

                // Build slots from weekly availability windows (if any)
                foreach ($windows as $w) {
                    if ($agentId == 0) {
                        $winStart = Carbon::parse($dateKey.' '.$w->start_time, 'UTC')->setTimezone($adminTimezone);
                        $winEnd = Carbon::parse($dateKey.' '.$w->end_time, 'UTC')->setTimezone($adminTimezone);
                    } else {
                        $winStart = Carbon::parse($dateKey.' '.$w->start_time, 'UTC')->setTimezone($agentTimezone);
                        $winEnd = Carbon::parse($dateKey.' '.$w->end_time, 'UTC')->setTimezone($agentTimezone);
                    }
                    if ($winEnd <= $winStart) {
                        continue;
                    }

                    $slotStart = (clone $winStart);
                    while (true) {
                        $slotEnd = (clone $slotStart)->addMinutes($meetingDurationMinutes);
                        if ($slotEnd > $winEnd) {
                            break;
                        }
                        // Skip slots that are in the past (end time must be after now)
                        if ($slotEnd <= $nowAgentTz) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);

                            continue;
                        }
                        // Enforce lead time: slot must start after now + lead time
                        if ($slotStart < $minStartAgent) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);

                            continue;
                        }
                        $slotStartAdmin = (clone $slotStart)->setTimezone($adminTimezone);
                        $slotEndAdmin = (clone $slotEnd)->setTimezone($adminTimezone);
                        $targetKey = $slotStartAdmin->toDateString();
                        if (! isset($daysSlots[$targetKey])) {
                            $daysSlots[$targetKey] = [];
                        }
                        $daysSlots[$targetKey][] = [
                            'start_time' => $slotStartAdmin->format('H:i'),
                            'end_time' => $slotEndAdmin->format('H:i'),
                            'start_at' => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at' => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];
                        $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                    }
                }

                // Build slots from extra time windows (date-specific)
                $extraWindows = $extraSlotsByDate->get($dateKey, collect());
                foreach ($extraWindows as $ew) {
                    if ($agentId == 0) {
                        $ewStart = Carbon::parse($dateKey.' '.$ew->start_time, 'UTC')->setTimezone($adminTimezone);
                        $ewEnd = Carbon::parse($dateKey.' '.$ew->end_time, 'UTC')->setTimezone($adminTimezone);
                    } else {
                        $ewStart = Carbon::parse($dateKey.' '.$ew->start_time, 'UTC')->setTimezone($agentTimezone);
                        $ewEnd = Carbon::parse($dateKey.' '.$ew->end_time, 'UTC')->setTimezone($agentTimezone);
                    }
                    if ($ewEnd <= $ewStart) {
                        continue;
                    }

                    $slotStart = (clone $ewStart);
                    while (true) {
                        $slotEnd = (clone $slotStart)->addMinutes($meetingDurationMinutes);
                        if ($slotEnd > $ewEnd) {
                            break;
                        }
                        // Skip slots that are in the past (end time must be after now)
                        if ($slotEnd <= $nowAgentTz) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);

                            continue;
                        }
                        // Enforce lead time: slot must start after now + lead time
                        if ($slotStart < $minStartAgent) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);

                            continue;
                        }
                        $slotStartAdmin = (clone $slotStart)->setTimezone($adminTimezone);
                        $slotEndAdmin = (clone $slotEnd)->setTimezone($adminTimezone);
                        $targetKey = $slotStartAdmin->toDateString();
                        if (! isset($daysSlots[$targetKey])) {
                            $daysSlots[$targetKey] = [];
                        }
                        $daysSlots[$targetKey][] = [
                            'start_time' => $slotStartAdmin->format('H:i'),
                            'end_time' => $slotEndAdmin->format('H:i'),
                            'start_at' => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at' => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];
                        $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                    }
                }
                $cursor->addDay();
            }

            // Remove slots that overlap existing appointments (with buffer applied)
            $monthStartUtc = (clone $monthStartTz)->setTimezone('UTC')->toDateTimeString();
            $monthEndUtc = (clone $monthEndTz)->setTimezone('UTC')->toDateTimeString();
            if ($agentId == 0) {
                $appointments = Appointment::where(['is_admin_appointment' => 1, 'admin_id' => $agentData->id])
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $monthEndUtc)
                    ->where('end_at', '>', $monthStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where('agent_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $monthEndUtc)
                    ->where('end_at', '>', $monthStartUtc)
                    ->get();
            }

            foreach ($daysSlots as $k => $list) {
                $filtered = [];
                foreach ($list as $s) {
                    // Convert slot (admin tz) to UTC and apply buffer window
                    $slotStartUtcWithBuffer = Carbon::parse($s['start_at'], $adminTimezone)->setTimezone('UTC')->subMinutes($bufferMinutes)->toDateTimeString();
                    $slotEndUtcWithBuffer = Carbon::parse($s['end_at'], $adminTimezone)->setTimezone('UTC')->addMinutes($bufferMinutes)->toDateTimeString();
                    $overlaps = false;
                    foreach ($appointments as $a) {
                        if ($a->start_at < $slotEndUtcWithBuffer && $a->end_at > $slotStartUtcWithBuffer) {
                            $overlaps = true;
                            break;
                        }
                    }
                    if (! $overlaps) {
                        $filtered[] = $s;
                    }
                }
                // Sort each day's slots
                usort($filtered, function ($a, $b) {
                    return strcmp($a['start_time'], $b['start_time']);
                });
                $daysSlots[$k] = $filtered;
            }

            $response = [
                'agent_id' => $agentId,
                'month' => $month,
                'year' => $year,
                'availability_types' => $availableMeetingTypes,
                'timezone' => $adminTimezone,
                'agent_timezone' => $agentTimezone,
                'days' => $daysSlots,
            ];

            return ApiResponseService::successResponse('Time slots fetched successfully', $response);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function checkAgentTimeAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_id' => 'nullable|integer',
                'date' => 'required|date_format:Y-m-d',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
            ], [
                'date.required' => trans('Date is required'),
                'date.date_format' => trans('Invalid date format, expected Y-m-d'),
                'start_time.required' => trans('Start time is required'),
                'start_time.date_format' => trans('Invalid start time format, expected H:i'),
                'end_time.required' => trans('End time is required'),
                'end_time.date_format' => trans('Invalid end time format, expected H:i'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC'; // Admin/site timezone for input interpretation if needed
            $loggedInUser = Auth::user();
            $requestedAgentId = $request->agent_id;
            if ($requestedAgentId == 0) {
                $agentId = User::where('type', 0)->first()->id ?? 0;
                $isAdminAppointment = true;
            } else {
                $isAdminAppointment = false;
                $agentId = $requestedAgentId;
            }

            if (! $isAdminAppointment) {
                $agentData = Customer::where('id', $agentId)->first();
                if (isset($agentData->is_agent) && $agentData->is_agent) {
                    $agentId = $agentData->id;
                } else {
                    return ApiResponseService::validationError(trans('Agent id passed is not valid'));
                }
            }

            // Booking preferences (meeting duration is required to compute slots)
            if (! $isAdminAppointment) {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
                $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            } else {
                $bookingPref = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $agentId])->first();
                $agentTimezone = $adminTimezone;
            }
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError(trans('Meeting duration must be configured for the agent.'));
            }
            $bufferMinutes = max(0, (int) ($bookingPref->buffer_time_minutes ?? 0));
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));

            $dateKey = $request->date; // Y-m-d
            $startTimeStr = $request->start_time; // H:i
            $endTimeStr = $request->end_time; // H:i

            // Build requested interval in agent TZ
            $slotStartAgent = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
            $slotEndAgent = Carbon::parse($dateKey.' '.$endTimeStr, $adminTimezone)->setTimezone($agentTimezone);

            if ($slotEndAgent <= $slotStartAgent) {
                return ApiResponseService::validationError(trans('End time must be greater than start time'));
            }
            // Ensure requested interval equals exactly one meeting duration
            if ($slotStartAgent->diffInMinutes($slotEndAgent) !== $meetingDurationMinutes) {
                return ApiResponseService::validationError(trans('Invalid time duration'));
            }

            $dayName = strtolower($slotStartAgent->englishDayOfWeek);
            // Lead time barrier at query time
            $nowAgentTz = Carbon::now($agentTimezone);
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);

            // Load weekly availability windows for the day
            if (! $isAdminAppointment) {
                $windows = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            } else {
                $windows = AgentAvailability::where(['is_admin_data' => 1, 'admin_id' => $agentId])
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            }

            // Build slots for the date from availability windows
            $availbleSlots = [];
            foreach ($windows as $w) {
                $winStart = Carbon::parse($dateKey.' '.$w->start_time, 'UTC')->setTimezone($agentTimezone);
                $winEnd = Carbon::parse($dateKey.' '.$w->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($winEnd <= $winStart) {
                    continue;
                }

                $cursor = (clone $winStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $winEnd) {
                        break;
                    }

                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);

                    $availbleSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent' => (clone $candidateEnd),
                        'start_time' => $slotStartAdmin->format('H:i'),
                        'end_time' => $slotEndAdmin->format('H:i'),
                        'start_at' => $slotStartAdmin->format('Y-m-d H:i:s'),
                        'end_at' => $slotEndAdmin->format('Y-m-d H:i:s'),
                    ];

                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }

            // Also include date-specific extra time windows for the agent.
            // Pull from requested date plus adjacent days to handle timezone spillover.
            $prevDate = Carbon::parse($dateKey, $adminTimezone)->subDay()->toDateString();
            $nextDate = Carbon::parse($dateKey, $adminTimezone)->addDay()->toDateString();
            $extraSlots = [];

            if ($requestedAgentId != 0) {
                $extraWindows = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            } else {
                $extraWindows = AgentExtraTimeSlot::where(['is_admin_data' => 1, 'admin_id' => $agentId])
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            }
            if (! empty($extraWindows)) {
                foreach ($extraWindows as $ew) {
                    // Build using the window's own stored date in agent TZ
                    $ewDate = $ew->date;
                    $ewStart = Carbon::parse($ewDate.' '.$ew->start_time, 'UTC')->setTimezone($agentTimezone);
                    $ewEnd = Carbon::parse($ewDate.' '.$ew->end_time, 'UTC')->setTimezone($agentTimezone);
                    if ($ewEnd <= $ewStart) {
                        continue;
                    }

                    $cursor = (clone $ewStart);
                    while (true) {
                        $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                        if ($candidateEnd > $ewEnd) {
                            break;
                        }

                        $slotStartAdmin = (clone $cursor);
                        $slotEndAdmin = (clone $candidateEnd);
                        // Constrain to requested admin date to avoid cross-day leakage
                        if ($slotStartAdmin->toDateString() !== $dateKey) {
                            $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);

                            continue;
                        }

                        $extraSlots[] = [
                            'start_agent' => (clone $cursor),
                            'end_agent' => (clone $candidateEnd),
                            'start_time' => $slotStartAdmin->format('H:i'),
                            'end_time' => $slotEndAdmin->format('H:i'),
                            'start_at' => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at' => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];

                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                    }
                }
            }
            $slots = array_merge($availbleSlots, $extraSlots);
            // Filter slots according to start time in ascending order
            usort($slots, function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });
            // If no slots produced at all, outside schedule
            if (empty($slots)) {
                return ApiResponseService::successResponse('Unavailable', [
                    'available' => false,
                    'reason' => trans('No Slots available for selected date'),
                    'available_slots' => [],
                ]);
            }

            // Fetch unavailability and appointments for filtering
            // $unavailabilities = \App\Models\AgentUnavailability::where('agent_id', $requestedAgentId)
            //     ->where('date', $dateKey)
            //     ->get();

            $dayStartAgent = Carbon::parse($dateKey.' 00:00:00', $agentTimezone);
            $dayEndAgent = Carbon::parse($dateKey.' 23:59:59', $agentTimezone);
            $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
            $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();

            if ($requestedAgentId != 0) {
                $appointments = Appointment::where('agent_id', $requestedAgentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where(['is_admin_appointment' => 1, 'admin_id' => $agentId])
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            }

            // Filter slots by unavailability and appointments (with buffer margins)
            $availableSlots = [];
            foreach ($slots as $s) {
                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // Enforce lead time: slot must start after now + lead time
                if ($sStartAgent < $minStartAgent) {
                    continue;
                }

                // Unavailability filter
                $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                if ($blockedByUnavailability) {
                    continue;
                }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $availableSlots[] = $s;
            }

            // Determine if requested interval equals one of the available slots (compare admin-time HH:MM)
            $requestedStartAdmin = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone);
            $requestedEndAdmin = Carbon::parse($dateKey.' '.$endTimeStr, $adminTimezone);
            $matched = false;
            foreach ($availableSlots as $s) {
                if ($s['start_time'] === $requestedStartAdmin->format('H:i') && $s['end_time'] === $requestedEndAdmin->format('H:i')) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                ApiResponseService::successResponse('Available', [
                    'available' => true,
                    'is_admin_agent' => $requestedAgentId == 0 ? true : false,
                    'agent_id' => $agentId,
                    'date' => $dateKey,
                    'start_time' => $startTimeStr,
                    'end_time' => $endTimeStr,
                    'duration_minutes' => $meetingDurationMinutes,
                    'buffer_minutes' => $bufferMinutes,
                    'agent_timezone' => $agentTimezone,
                    'admin_timezone' => $adminTimezone,
                    'slot_start_at_agent' => $slotStartAgent->format('Y-m-d H:i:s'),
                    'slot_end_at_agent' => $slotEndAgent->format('Y-m-d H:i:s'),
                ]);
            }
            // Not matched
            ApiResponseService::successResponse('Unavailable', [
                'available' => false,
                'reason' => trans('Please select a different time slot'),
                'available_slots' => array_map(function ($s) {
                    return ['start_time' => $s['start_time'], 'end_time' => $s['end_time']];
                }, $availableSlots),
            ]);

            // (old direct overlap logic replaced by slot-based check above)
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function addAgentUnavailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
                'type_of_unavailability' => 'required|in:full_day,partial_day',
                'start_time' => 'nullable|required_if:type_of_unavailability,partial_day|date_format:H:i',
                'end_time' => 'nullable|required_if:type_of_unavailability,partial_day|date_format:H:i|after:start_time',
                'reason' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();

            // Check if the user is an agent
            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to add unavailability'));
            }
            // Resolve agent timezone from preferences (default UTC)
            $preferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();
            $agentTimezone = $preferences?->timezone ?: 'UTC';

            $date = $request->input('date');
            $type = $request->input('type_of_unavailability');
            $reason = $request->input('reason');

            if ($type === 'partial_day') {
                $startTimeStr = $request->input('start_time');
                $endTimeStr = $request->input('end_time');
                $startAgent = Carbon::parse($date.' '.$startTimeStr, $agentTimezone);
                $endAgent = Carbon::parse($date.' '.$endTimeStr, $agentTimezone);
                if ($endAgent <= $startAgent) {
                    return ApiResponseService::validationError(trans('End time must be greater than start time'));
                }
                $unavailabilityType = 'specific_time';
            } else {
                // full_day
                $startAgent = Carbon::parse($date.' 00:00:00', $agentTimezone);
                $endAgent = Carbon::parse($date.' 23:59:59', $agentTimezone);
                $unavailabilityType = 'full_day';
            }

            // Unavailability must be within the agent's active schedule for that weekday
            $weekday = strtolower(Carbon::parse($date)->format('l'));
            $hasSchedule = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->where('day_of_week', $weekday)
                ->where('is_active', true)
                ->exists();
            if (! $hasSchedule) {
                return ApiResponseService::validationError(trans('No active schedule defined for this day'));
            }
            if ($unavailabilityType === 'full_day') {
                return ApiResponseService::validationError(trans('Unavailability must be within your scheduled hours'));
            }
            // For specific time, ensure it fits entirely inside a single schedule window
            $startHms = $startAgent->format('H:i:s');
            $endHms = $endAgent->format('H:i:s');
            $fitsInside = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->where('day_of_week', $weekday)
                ->where('is_active', true)
                ->where('start_time', '<=', $startHms)
                ->where('end_time', '>=', $endHms)
                ->exists();
            if (! $fitsInside) {
                return ApiResponseService::validationError(trans('Unavailability must be within your schedule window'));
            }

            // Prevent duplicate/overlapping unavailability on the same date
            if ($unavailabilityType === 'full_day') {
                $exists = AgentUnavailability::where('agent_id', $loggedInUser->id)
                    ->where('date', $date)
                    ->exists();
                if ($exists) {
                    return ApiResponseService::validationError(trans('Unavailability already exists for this date'));
                }
            } else {
                $startHms = $startAgent->format('H:i:s');
                $endHms = $endAgent->format('H:i:s');
                $overlapExists = AgentUnavailability::where('agent_id', $loggedInUser->id)
                    ->where('date', $date)
                    ->where(function ($q) use ($startHms, $endHms) {
                        $q->where('unavailability_type', 'full_day')
                            ->orWhere(function ($qq) use ($startHms, $endHms) {
                                $qq->where('unavailability_type', 'specific_time')
                                    ->where('start_time', '<', $endHms)
                                    ->where('end_time', '>', $startHms);
                            });
                    })
                    ->exists();
                if ($overlapExists) {
                    return ApiResponseService::validationError(trans('Unavailability overlaps with an existing entry'));
                }
            }

            // Store unavailability
            $createdUnavailability = AgentUnavailability::create([
                'agent_id' => $loggedInUser->id,
                'date' => $date,
                'unavailability_type' => $unavailabilityType,
                'start_time' => ($unavailabilityType === 'specific_time') ? $startAgent->format('H:i:s') : null,
                'end_time' => ($unavailabilityType === 'specific_time') ? $endAgent->format('H:i:s') : null,
                'reason' => $reason,
            ]);

            // Build UTC window for overlap query
            $windowStartUtc = (clone $startAgent)->setTimezone('UTC')->toDateTimeString();
            $windowEndUtc = (clone $endAgent)->setTimezone('UTC')->toDateTimeString();

            // Determine default cancel reason from preferences
            $defaultCancelReason = $preferences?->auto_cancel_message ?: ($reason ?: trans('Agent unavailable'));

            // Find overlapping appointments to cancel
            $appointments = Appointment::where('agent_id', $loggedInUser->id)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where('start_at', '<', $windowEndUtc)
                ->where('end_at', '>', $windowStartUtc)
                ->get();

            $cancelledCount = 0;
            DB::beginTransaction();

            $cancelData = [];
            $appointmentIds = [];

            foreach ($appointments as $appt) {
                $cancelData[] = [
                    'appointment_id' => $appt->id,
                    'cancelled_by' => 'agent',
                    'reason' => $defaultCancelReason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $appointmentIds[] = $appt->id;
            }

            if (! empty($cancelData)) {
                // Bulk insert cancellations
                AppointmentCancellation::insert($cancelData);

                // Bulk update appointments to cancelled
                Appointment::whereIn('id', $appointmentIds)->update(['status' => 'cancelled']);
            }

            $cancelledCount = count($appointmentIds);

            DB::commit();

            $createdUnavailability->cancelled_appointments = $cancelledCount;

            ApiResponseService::successResponse(
                trans('Unavailability added successfully'),
                $createdUnavailability
            );
        } catch (Exception $e) {
            DB::rollBack();
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function deleteUnavailabilityData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'unavailability_id' => 'required|exists:agent_unavailabilities,id',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $unavailabilityId = $request->unavailability_id;
            $unavailability = AgentUnavailability::where('id', $unavailabilityId)->first();
            if (! $unavailability) {
                return ApiResponseService::validationError(trans('Unavailability not found'));
            }
            $unavailability->delete();

            return ApiResponseService::successResponse(trans('Unavailability deleted successfully'));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentBookingPreferences()
    {
        try {
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            if (! AgentVerification::where('customer_id', $loggedInUser->id)->where('form_type', 'become_agent')->where('status', 'approved')->exists()) {
                return ApiResponseService::validationError(trans('You are not authorized to get agent booking preferences'));
            }
            $agentBookingPreferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();

            return ApiResponseService::successResponse(trans('Data fetched successfully'), $agentBookingPreferences);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentTimeSchedules()
    {
        try {
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            if (! VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'approved')->exists() && ! AgentVerification::where('customer_id', $loggedInUser->id)->where('form_type', 'become_agent')->where('status', 'approved')->exists()) {
                return ApiResponseService::validationError(trans('You are not authorized to get agent time schedules'));
            }

            $agentId = $loggedInUser->id;
            $agentTimezone = $loggedInUser->getTimezone(true);

            // Get time schedules with appointment counts
            $agentTimeSchedules = AgentAvailability::where('agent_id', $agentId)->get()->map(function ($schedule) use ($agentId, $agentTimezone) {
                // Get appointments for this day of week and time slot
                $appointmentCount = HelperService::getAppointmentCountForTimeSlot($agentId, $schedule->day_of_week, $schedule->start_time, $schedule->end_time, $agentTimezone);
                $schedule->start_time = Carbon::parse($schedule->start_time, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $schedule->end_time = Carbon::parse($schedule->end_time, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $schedule->appointment_count = $appointmentCount;

                return $schedule;
            });

            // Get extra time slots with appointment counts (only future and today's slots)
            $today = now($agentTimezone)->format('Y-m-d');
            $extraTimeSlots = AgentExtraTimeSlot::where('agent_id', $agentId)
                ->where('date', '>=', $today)
                ->get()
                ->map(function ($slot) use ($agentId, $agentTimezone) {
                    // Get appointments for this specific date and time slot
                    $appointmentCount = HelperService::getAppointmentCountForExtraTimeSlot($agentId, $slot->date, $slot->start_time, $slot->end_time, $agentTimezone);
                    $slot->appointment_count = $appointmentCount;

                    return $slot;
                });

            $data = [
                'time_schedules' => $agentTimeSchedules,
                'extra_slots' => $extraTimeSlots,
            ];

            return ApiResponseService::successResponse(trans('Data fetched successfully'), $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getUnavailabilityData()
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'approved')->exists();
            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to get unavailability data'));
            }
            $unavailabilityData = AgentUnavailability::where('agent_id', $loggedInUser->id)->get();

            return ApiResponseService::successResponse(trans('Data fetched successfully'), $unavailabilityData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function manageAgentExtraTimeSlots(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            if (isset($loggedInUser->is_agent) && $loggedInUser->is_agent == false) {
                return ApiResponseService::validationError(trans('You are not authorized to manage extra time slots'));
            }

            $validator = Validator::make($request->all(), [
                'extra_time_slots' => 'required|array',
                'extra_time_slots.*.id' => 'nullable|integer|exists:agent_extra_time_slots,id',
                'extra_time_slots.*.date' => 'required|date_format:Y-m-d|after_or_equal:today',
                'extra_time_slots.*.start_time' => 'required|date_format:H:i',
                'extra_time_slots.*.end_time' => 'required|date_format:H:i|after:extra_time_slots.*.start_time',
                'extra_time_slots.*.reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $extraTimeSlots = $request->extra_time_slots;
            $data = [];
            $errors = [];

            // First, validate all slots for conflicts within the batch
            for ($i = 0; $i < count($extraTimeSlots); $i++) {
                for ($j = $i + 1; $j < count($extraTimeSlots); $j++) {
                    $slot1 = $extraTimeSlots[$i];
                    $slot2 = $extraTimeSlots[$j];

                    if ($slot1['date'] === $slot2['date']) {
                        // Check if slots overlap (excluding the same slot if updating)
                        if (($slot1['start_time'] < $slot2['end_time']) && ($slot1['end_time'] > $slot2['start_time'])) {
                            // If both slots have IDs and they're the same, skip overlap check
                            if (! (isset($slot1['id']) && isset($slot2['id']) && $slot1['id'] == $slot2['id'])) {
                                $errors[] = 'Slots '.($i + 1).' and '.($j + 1).' overlap on '.$slot1['date'];
                            }
                        }
                    }
                }
            }

            if (! empty($errors)) {
                return ApiResponseService::validationError('Batch validation failed: '.implode(', ', $errors));
            }

            // Process each slot
            foreach ($extraTimeSlots as $index => $slot) {
                try {
                    $slotId = $slot['id'] ?? null;
                    $date = $slot['date'];
                    $startTime = $slot['start_time'];
                    $endTime = $slot['end_time'];
                    $reason = $slot['reason'] ?? null;

                    // Validate time is not in the past
                    $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$startTime);
                    if ($startDateTime->lt(Carbon::now())) {
                        $errors[] = 'Slot '.($index + 1).': '.trans('You cannot add/update a time slot to a past time');

                        continue;
                    }

                    if ($slotId) {
                        // UPDATE LOGIC
                        $existingSlot = AgentExtraTimeSlot::where('id', $slotId)
                            ->where('agent_id', $loggedInUser->id)
                            ->first();

                        if (! $existingSlot) {
                            $errors[] = 'Slot '.($index + 1).': '.trans('Extra time slot not found or not authorized');

                            continue;
                        }

                        // Check for overlapping with existing extra time slots (excluding current slot)
                        $overlaps = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)
                            ->where('date', $date)
                            ->where('id', '!=', $slotId)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($overlaps) {
                            $errors[] = 'Slot '.($index + 1).': '.trans('Time slot overlaps with an existing slot');

                            continue;
                        }

                        // Check for overlapping with agent's base schedule
                        $weekday = strtolower(Carbon::parse($date)->format('l'));
                        $scheduleOverlap = AgentAvailability::where('agent_id', $loggedInUser->id)
                            ->where('day_of_week', $weekday)
                            ->where('is_active', true)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($scheduleOverlap) {
                            $errors[] = 'Slot '.($index + 1).': '.trans('Time slot overlaps with your schedule');

                            continue;
                        }

                        // Update the slot
                        $existingSlot->update([
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'reason' => $reason,
                        ]);
                        $data[] = $existingSlot;
                    } else {
                        // CREATE LOGIC
                        // Check for overlapping with existing extra time slots
                        $overlaps = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)
                            ->where('date', $date)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($overlaps) {
                            $errors[] = 'Slot '.($index + 1).': '.trans('Time slot overlaps with an existing slot');

                            continue;
                        }

                        // Check for overlapping with agent's base schedule
                        $weekday = strtolower(Carbon::parse($date)->format('l'));
                        $scheduleOverlap = AgentAvailability::where('agent_id', $loggedInUser->id)
                            ->where('day_of_week', $weekday)
                            ->where('is_active', true)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($scheduleOverlap) {
                            $errors[] = 'Slot '.($index + 1).': '.trans('Time slot overlaps with your schedule');

                            continue;
                        }

                        // Create the slot
                        $agentExtraTimeSlot = AgentExtraTimeSlot::create([
                            'agent_id' => $loggedInUser->id,
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'reason' => $reason,
                        ]);
                        $data[] = $agentExtraTimeSlot;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Slot '.($index + 1).': '.$e->getMessage();
                }
            }

            DB::commit();
            $totalProcessed = count($data);
            if ($totalProcessed == 0) {
                return ApiResponseService::validationError('No slots were processed. Issues: '.implode(', ', $errors));
            } elseif (! empty($errors)) {
                $message = trans('Some slots were processed successfully. Issues: ').implode(', ', $errors);

                return ApiResponseService::successResponse($message, $data);
            } else {
                return ApiResponseService::successResponse('All extra time slots processed successfully', $data);
            }
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function deleteMultipleAgentExtraTimeSlots(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();
            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to delete extra time slots'));
            }

            $validator = Validator::make($request->all(), [
                'slot_ids' => 'required|array',
                'slot_ids.*' => 'required|integer|exists:agent_extra_time_slots,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            // Agent timezone and default cancel reason
            $preferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();
            $agentTimezone = $preferences?->timezone ?: 'UTC';
            $defaultCancelReason = $preferences?->auto_cancel_message ?: trans('Extra time slot removed by agent');

            DB::beginTransaction();

            foreach ($request->slot_ids as $slotId) {
                try {
                    $slot = AgentExtraTimeSlot::where('id', $slotId)
                        ->where('agent_id', $loggedInUser->id)
                        ->first();

                    // Build slot window in UTC
                    $slotStartAgent = Carbon::parse($slot->date.' '.$slot->start_time, $agentTimezone);
                    $slotEndAgent = Carbon::parse($slot->date.' '.$slot->end_time, $agentTimezone);
                    $windowStartUtc = (clone $slotStartAgent)->setTimezone('UTC')->toDateTimeString();
                    $windowEndUtc = (clone $slotEndAgent)->setTimezone('UTC')->toDateTimeString();

                    // Find overlapping appointments to cancel
                    $appointments = Appointment::where('agent_id', $loggedInUser->id)
                        ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                        ->where('start_at', '<', $windowEndUtc)
                        ->where('end_at', '>', $windowStartUtc)
                        ->get();

                    $appointmentIds = [];
                    foreach ($appointments as $appointment) {
                        $appointmentIds[] = $appointment->id;

                        // Cancel the appointment
                        $appointment->update([
                            'status' => 'cancelled',
                            'last_status_updated_by' => 'agent',
                        ]);

                        // Record cancellation
                        AppointmentCancellation::create([
                            'appointment_id' => $appointment->id,
                            'cancelled_by' => 'agent',
                            'reason' => $defaultCancelReason,
                        ]);
                    }

                    // Delete the slot
                    $slot->delete();
                } catch (Exception $e) {
                    $failedDeletions[] = "Slot ID {$slotId}: ".$e->getMessage();
                }
            }

            DB::commit();
            ResponseService::successResponse(trans('Extra time slots deleted successfully'));
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentExtraTimeSlots()
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'approved')->exists();
            if (! $isAgent) {
                $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('form_type', 'become_agent')->where('status', 'approved')->exists();
            }
            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to get extra time slots'));
            }
            $extraTimeSlots = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)->get();

            return ApiResponseService::successResponse(trans('Data fetched successfully'), $extraTimeSlots);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function createAppointment(Request $request)
    {
        try {
            // Admin/site timezone for inputs
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            $currentDate = Carbon::now()->setTimezone($adminTimezone)->toDateString();
            $currentTime = Carbon::now()->setTimezone($adminTimezone)->format('H:i');
            $validator = Validator::make($request->all(), [
                'property_id' => 'required|integer|exists:propertys,id',
                'meeting_type' => 'required|in:phone,virtual,in_person',
                'date' => 'required|date_format:Y-m-d|after_or_equal:'.$currentDate,
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'notes' => 'nullable|string',
            ], [
                'property_id.required' => trans('Property is required'),
                'property_id.integer' => trans('Invalid property id'),
                'property_id.exists' => trans('Property not found'),
                'meeting_type.required' => trans('Meeting type is required'),
                'meeting_type.in' => trans('Invalid meeting type'),
                'date.required' => trans('Date is required'),
                'date.date_format' => trans('Invalid date format, expected Y-m-d'),
                'date.after_or_equal' => trans('Date must be greater than or equal to current date'),
                'start_time.required' => trans('Start time is required'),
                'start_time.date_format' => trans('Invalid start time format, expected H:i'),
                'start_time.after_or_equal' => trans('Start time must be greater than or equal to current time'),
                'end_time.required' => trans('End time is required'),
                'end_time.date_format' => trans('Invalid end time format, expected H:i'),
                'end_time.after' => trans('End time must be greater than start time'),
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();

            if ($request->date == $currentDate) {
                $validator->after(function ($validator) use ($request, $adminTimezone, $currentTime) {
                    $inputDate = $request->input('date');
                    $startTime = $request->input('start_time');
                    if (! $inputDate || ! $startTime) {
                        return;
                    }
                    $start = Carbon::createFromFormat('H:i', $startTime, $adminTimezone);
                    $nowTime = Carbon::createFromFormat('H:i', $currentTime, $adminTimezone);
                    if ($start->lt($nowTime)) {
                        $validator->errors()->add('start_time', trans('Start time must be greater than or equal to current time'));
                    }
                });
            }

            $loggedInUser = Auth::user();
            $userId = $loggedInUser->id;
            $dateKey = $request->date;
            $startTimeStr = $request->start_time;
            $endTimeStr = $request->end_time;

            // Resolve property and agent
            $property = Property::where('id', $request->property_id)->first();
            if (! $property) {
                return ApiResponseService::validationError(trans('Property not found'));
            }

            // Check if property is expired
            if ($property->expiry_date && Carbon::parse($property->expiry_date)->lt(Carbon::now())) {
                return ApiResponseService::validationError(trans('This property has expired. You cannot book an appointment for an expired listing.'));
            }
            $isAdminAgent = $property->added_by == 0 ? true : false;
            if ($isAdminAgent) {
                $adminId = User::where('type', 0)->first()->id ?? 0;
                $agentId = $adminId;
            } else {
                $agentId = $property->added_by;
            }

            // Booking preferences (timezone)
            if ($isAdminAgent) {
                $bookingPref = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $agentId])->first();
                $agentTimezone = $adminTimezone;
            } else {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
                $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            }
            $dailyLimit = $bookingPref && $bookingPref->daily_booking_limit ? $bookingPref->daily_booking_limit : 0;

            // Build requested interval in agent TZ
            $slotStartAgent = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
            $slotEndAgent = Carbon::parse($dateKey.' '.$endTimeStr, $adminTimezone)->setTimezone($agentTimezone);

            // Create appointment in UTC
            $startUtc = (clone $slotStartAgent)->setTimezone('UTC')->toDateTimeString();
            $endUtc = (clone $slotEndAgent)->setTimezone('UTC')->toDateTimeString();

            // Check if agent is trying to create appointment for himself
            if (! $isAdminAgent && ($agentId == $loggedInUser->id)) {
                return ApiResponseService::validationError(trans('You are not allowed to create appointment for yourself'));
            }

            // Resolve agent
            if ($isAdminAgent) {
                $agent = User::where('id', $agentId)->first();
            } else {
                $agent = Customer::where('id', $agentId)->first();
            }
            if (! $agent) {
                return ApiResponseService::validationError(trans('Agent not found'));
            }

            // Check if user is blocked for appointments
            if ($isAdminAgent) {
                if (BlockedUserForAppointment::isUserBlocked($userId, null)) {
                    return ApiResponseService::validationError(trans('You are blocked from making appointments with this agent'));
                }
            } else {
                if (BlockedUserForAppointment::isUserBlocked($userId, $agentId)) {
                    return ApiResponseService::validationError(trans('You are blocked from making appointments with this agent'));
                }
            }

            // Check if user has reached daily limit
            if ($dailyLimit > 0) {
                $startOfDay = Carbon::parse($dateKey.' '.'00:00:00', $agentTimezone)->setTimezone('UTC')->toDateTimeString();
                $endOfDay = Carbon::parse($dateKey.' '.'23:59:59', $agentTimezone)->setTimezone('UTC')->toDateTimeString();
                DB::enableQueryLog();
                $dailyAppointmentCount = Appointment::whereBetween('start_at', [$startOfDay, $endOfDay])
                    ->when($isAdminAgent, function ($query) use ($agentId, $isAdminAgent) {
                        $query->where('admin_id', $agentId)
                            ->where('is_admin_appointment', $isAdminAgent);
                    }, function ($query) use ($agentId) {
                        $query->where('agent_id', $agentId);
                    })
                    ->whereNotIn('status', ['cancelled', 'auto_cancelled', 'pending'])
                    ->count();

                // If daily appointment count is greater than or equal to daily limit, return error
                if ($dailyAppointmentCount >= $dailyLimit) {
                    return ApiResponseService::validationError(
                        trans('Agent cannot accept more appointments for today')
                    );
                }
            }

            // Check for existing appointments
            if ($isAdminAgent) {
                // AppointmentQuery
                $appointmentQuery = Appointment::where([
                    'user_id' => $userId,
                    'is_admin_appointment' => 1,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc,
                ])->whereNot('status', 'cancelled', 'auto_cancelled');

                // Check if user has appointment with same admin at same time
                $existingAppointmentWithSameAdmin = $appointmentQuery->clone()->where('admin_id', $agentId)->first();
                if ($existingAppointmentWithSameAdmin) {
                    return ApiResponseService::validationError(trans('Appointment already booked with this admin'));
                }

                // Check if user has appointment with any other admin at same time
                $existingAppointmentWithOtherAdmin = $appointmentQuery->clone()->where('admin_id', '!=', $agentId)->first();
                if ($existingAppointmentWithOtherAdmin) {
                    return ApiResponseService::validationError(trans('Appointment cannot be created as you already have an appointment with another admin at this time'));
                }
            } else {
                // Appointment Query
                $appointmentQuery = Appointment::where([
                    'is_admin_appointment' => 0,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc,
                    'user_id' => $userId,
                ])->whereNot('status', 'cancelled');

                // Check if user has appointment with same agent at same time
                $existingAppointmentWithSameAgent = $appointmentQuery->clone()->where('agent_id', $agentId)->first();
                if ($existingAppointmentWithSameAgent) {
                    return ApiResponseService::validationError(trans('Appointment already booked with this agent'));
                }

                // Check if user has appointment with any other agent at same time
                $existingAppointmentWithOtherAgent = Appointment::where([
                    'is_admin_appointment' => 0,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc,
                ])->whereNot('status', 'cancelled')
                    ->where(function ($query) use ($userId) {
                        $query->where('user_id', $userId)
                            ->orWhere('agent_id', $userId);
                    })
                    ->where('agent_id', '!=', $agentId)
                    ->where('user_id', '!=', $agentId)
                    ->first();

                if ($existingAppointmentWithOtherAgent) {
                    return ApiResponseService::validationError(trans('Appointment cannot be created as you already have an appointment with another agent at this time'));
                }
            }

            // Booking preferences (meeting duration/auto_confirm/timezone)
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError(trans('Agent has not configured meeting duration'));
            }
            $bufferMinutes = max(0, (int) ($bookingPref->buffer_time_minutes ?? 0));
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));
            $autoConfirm = (bool) ($bookingPref->auto_confirm ?? false);

            // Validate duration
            if ($slotEndAgent <= $slotStartAgent) {
                return ApiResponseService::validationError(trans('End time must be greater than start time'));
            }
            if ($slotStartAgent->diffInMinutes($slotEndAgent) !== $meetingDurationMinutes) {
                return ApiResponseService::validationError(trans('Invalid time duration'));
            }

            // Enforce lead time at booking time
            $nowAgentTz = Carbon::now($agentTimezone);
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);
            if ($slotStartAgent < $minStartAgent) {
                return ApiResponseService::validationError(trans('Selected time is too soon. Please choose a later time.'));
            }

            // Reuse availability algorithm from checkAgentTimeAvailability
            $availabilityRequest = new Request([
                'agent_id' => $agentId,
                'date' => $dateKey,
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
            ]);

            // Manually invoke availability logic
            $availabilityValidator = Validator::make($availabilityRequest->all(), [
                'agent_id' => 'nullable|integer|exists:'.($isAdminAgent ? 'users' : 'customers').',id',
                'date' => 'required|date_format:Y-m-d',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
            ]);
            if ($availabilityValidator->fails()) {
                return ApiResponseService::validationError($availabilityValidator->errors()->first());
            }

            // Build all candidate slots similar to checkAgentTimeAvailability
            $dayName = strtolower($slotStartAgent->englishDayOfWeek);

            if ($isAdminAgent) {
                $windows = AgentAvailability::where('admin_id', $agentId)
                    ->where('is_active', 1)
                    ->where('is_admin_data', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            } else {
                $windows = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            }

            $availbleAgentSlots = [];
            foreach ($windows as $w) {
                $winStart = Carbon::parse($dateKey.' '.$w->start_time, 'UTC')->setTimezone($agentTimezone);
                $winEnd = Carbon::parse($dateKey.' '.$w->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($winEnd <= $winStart) {
                    continue;
                }

                $cursor = (clone $winStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $winEnd) {
                        break;
                    }

                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);
                    $availbleAgentSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent' => (clone $candidateEnd),
                        'start_time' => $slotStartAdmin->format('H:i'),
                        'end_time' => $slotEndAdmin->format('H:i'),
                    ];
                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }

            // Include extra time windows limited to the specific admin date
            $prevDate = Carbon::parse($dateKey, $adminTimezone)->setTimezone('UTC')->subDay()->toDateString();
            $nextDate = Carbon::parse($dateKey, $adminTimezone)->setTimezone('UTC')->addDay()->toDateString();
            if ($isAdminAgent) {
                $extraWindows = AgentExtraTimeSlot::where('admin_id', $agentId)
                    ->where('is_admin_data', 1)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            } else {
                $extraWindows = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            }
            $extraSlots = [];

            foreach ($extraWindows as $ew) {
                $ewDate = $ew->date;
                $ewStart = Carbon::parse($ewDate.' '.$ew->start_time, 'UTC')->setTimezone($agentTimezone);
                $ewEnd = Carbon::parse($ewDate.' '.$ew->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($ewEnd <= $ewStart) {
                    continue;
                }
                $cursor = (clone $ewStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $ewEnd) {
                        break;
                    }
                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    if ($slotStartAdmin->toDateString() !== $dateKey) {
                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);

                        continue;
                    }
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);
                    $extraSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent' => (clone $candidateEnd),
                        'start_time' => $slotStartAdmin->format('H:i'),
                        'end_time' => $slotEndAdmin->format('H:i'),
                    ];
                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }
            $slots = array_merge($availbleAgentSlots, $extraSlots);
            // Filter slots according to start time in ascending order
            usort($slots, function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });

            if (empty($slots)) {
                return ApiResponseService::validationError(trans('Selected time is outside agent availability'), $slots);
            }

            // // Filter by unavailability and existing appointments
            // $unavailabilities = AgentUnavailability::where('agent_id', $agentId)
            //     ->where('date', $dateKey)
            //     ->get();

            $dayStartAgent = Carbon::parse($dateKey.' 00:00:00', $agentTimezone);
            $dayEndAgent = Carbon::parse($dateKey.' 23:59:59', $agentTimezone);
            $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
            $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();
            if ($isAdminAgent) {
                $appointments = Appointment::where('admin_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where('agent_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            }

            // Build list of available slots after applying unavailability and existing appointments
            $availableSlots = [];
            foreach ($slots as $s) {
                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // Unavailability filter
                // $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                // if ($blockedByUnavailability) { continue; }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $availableSlots[] = [
                    'start_agent' => $sStartAgent,
                    'end_agent' => $sEndAgent,
                    'start_time' => $s['start_time'],
                    'end_time' => $s['end_time'],
                ];
            }

            $available = false;
            $requestedStartAdmin = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone);
            $requestedEndAdmin = Carbon::parse($dateKey.' '.$endTimeStr, $adminTimezone);

            foreach ($slots as $s) {
                if ($s['start_time'] !== $requestedStartAdmin->format('H:i') || $s['end_time'] !== $requestedEndAdmin->format('H:i')) {
                    continue;
                }

                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // // Unavailability filter
                // $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                // if ($blockedByUnavailability) { continue; }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $available = true;
                break;
            }

            if (! $available) {
                return ApiResponseService::validationError(trans('Selected time is not available'), $availableSlots);
            }

            $appointment = Appointment::create([
                'is_admin_appointment' => $isAdminAgent ? 1 : 0,
                'admin_id' => $isAdminAgent ? $agentId : null,
                'agent_id' => $isAdminAgent ? null : $agentId,
                'user_id' => $userId,
                'property_id' => $property->id,
                'meeting_type' => $request->meeting_type,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'status' => $autoConfirm ? 'confirmed' : 'pending',
                'is_auto_confirmed' => $autoConfirm,
                'last_status_updated_by' => $autoConfirm ? 'system' : 'user',
                'notes' => $request->notes,
            ]);
            $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');
            $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');

            // Send notifications using the appointment notification service
            AppointmentNotificationService::sendNewAppointmentRequestNotification(
                $appointment,
                $isAdminAgent ? null : $agent,
                $loggedInUser,
                $property,
                $autoConfirm,
                $isAdminAgent ? $agent : null
            );

            DB::commit();

            return ApiResponseService::successResponse(trans('Appointment created successfully'), $appointment);
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function updateAppointmentStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|exists:appointments,id',
                'status' => 'required|in:confirmed,cancelled,rescheduled',
                'reason' => 'nullable|required_if:status,cancelled,rescheduled|string',
                'date' => 'nullable|required_if:status,rescheduled|date_format:Y-m-d',
                'start_time' => 'nullable|required_if:status,rescheduled|date_format:H:i',
                'end_time' => 'nullable|required_if:status,rescheduled|date_format:H:i|after:start_time',
                'meeting_type' => 'nullable|in:phone,virtual,in_person',
            ], [
                'date.required_if' => trans('Date is required for rescheduling'),
                'start_time.required_if' => trans('Start time is required for rescheduling'),
                'end_time.required_if' => trans('End time is required for rescheduling'),
                'end_time.after' => trans('End time must be greater than start time'),
                'meeting_type.in' => trans('Invalid meeting type'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $appointmentId = $request->appointment_id;
            $requestedStatus = $request->status;
            $reason = $request->input('reason');
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC'; // Admin Timezone
            $appointment = Appointment::where('id', $appointmentId)->first(); // Appointment Data
            $isAdminAppointment = false;

            // Appointment Data Validation
            if (! $appointment) {
                return ApiResponseService::validationError(trans('Appointment not found'));
            }

            // Admin Appointment Data Validation
            if ($appointment->is_admin_appointment) {
                $isAdminAppointment = true;
            }

            // Booking Preference Data Validation
            if ($isAdminAppointment) {
                $preferences = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $appointment->admin_id])->first(); // Agent Booking Preference
                $agentTimezone = $adminTimezone;
            } else {
                $preferences = AgentBookingPreference::where('agent_id', $appointment->agent_id)->first(); // Admin Booking Preference
                $agentTimezone = $preferences?->timezone ?: (config('app.timezone') ?? 'UTC'); // Agent Timezone
            }
            $dailyLimit = $preferences?->daily_booking_limit ?: 0;
            $isAgent = false;
            if (! $isAdminAppointment) {
                $isAgent = (bool) ($loggedInUser->is_agent ?? false);
                $isAgent = $appointment->agent_id === $loggedInUser->id;
            }

            // Authorization: agent must be the appointment's agent; user must be the appointment's user
            if ($isAdminAppointment) {
                $authorized = false;
                if ($appointment->user_id == $loggedInUser->id) {
                    $authorized = true;
                }
            } else {
                if ($isAgent) {
                    $property = Property::select('id', 'added_by')->where('id', $appointment->property_id)->first();
                    $authorized = $property && $property->added_by == $loggedInUser->id;
                } else {
                    $authorized = $appointment->user_id === $loggedInUser->id;
                }
            }
            if (! $authorized) {
                return ApiResponseService::validationError(trans('You are not authorized to update this appointment'));
            }

            // Allowed actions by role
            if ($isAgent) {
                $allowedForRole = in_array($requestedStatus, ['confirmed', 'cancelled', 'rescheduled']);
            } else {
                $allowedForRole = in_array($requestedStatus, ['cancelled', 'rescheduled']);
            }
            if (! $allowedForRole) {
                return ApiResponseService::validationError(trans('You are not allowed to set this status'));
            }

            // Prevent invalid state transitions
            if (in_array($appointment->status, ['completed'])) {
                return ApiResponseService::validationError(trans('This appointment can no longer be updated'));
            }

            // If update status is reschedule or cancel, check agent preference cancel_reschedule_buffer_minutes
            if (in_array($requestedStatus, ['rescheduled', 'cancelled'])) {
                $cancelRescheduleBuffer = (int) ($preferences->cancel_reschedule_buffer_minutes ?? 0);
                if ($cancelRescheduleBuffer > 0) {
                    // Appointment start time in agent timezone
                    $appointmentStart = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
                    $nowAgent = Carbon::now($agentTimezone);
                    $appointmentDate = Carbon::parse($appointment->start_at, 'UTC');
                    if ($nowAgent >= $appointmentDate) {
                        $diffInMinutes = $appointmentStart->diffInMinutes($nowAgent, false);
                    } else {
                        $diffInMinutes = $nowAgent->diffInMinutes($appointmentStart, false);
                    }
                    if ($diffInMinutes <= $cancelRescheduleBuffer) {
                        return ApiResponseService::validationError(
                            trans('You cannot '.($requestedStatus === 'rescheduled' ? 'reschedule' : 'cancel').' this appointment within :minutes minutes of its start time.', ['minutes' => $cancelRescheduleBuffer])
                        );
                    }
                }
            }

            // Idempotency: if same status (and for reschedule same time), return success without changes
            if ($requestedStatus !== 'rescheduled' && $appointment->status === $requestedStatus) {
                if ($isAgent) {
                    $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
                    $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone);
                } else {
                    $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone);
                    $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone);
                }

                return ApiResponseService::successResponse(trans('No changes'), $appointment);
            }

            $meetingDurationMinutes = (int) ($preferences->meeting_duration_minutes ?? 0);
            $bufferMinutes = max(0, (int) ($preferences->buffer_time_minutes ?? 0));

            DB::beginTransaction();

            $changedBy = null;
            // Handle reschedule (validate availability and update times)
            if ($requestedStatus === 'rescheduled') {
                $dateKey = $request->input('date');
                $startTimeStr = $request->input('start_time');
                $endTimeStr = $request->input('end_time');

                // Check if agent has reached daily limit
                if ($dailyLimit > 0) {
                    if ($isAdminAppointment) {
                        $startOfDay = Carbon::parse($dateKey.' '.'00:00:00', $adminTimezone)->setTimezone('UTC');
                        $endOfDay = Carbon::parse($dateKey.' '.'23:59:59', $adminTimezone)->setTimezone('UTC');
                    } else {
                        $startOfDay = Carbon::parse($dateKey.' '.'00:00:00', $agentTimezone)->setTimezone('UTC');
                        $endOfDay = Carbon::parse($dateKey.' '.'23:59:59', $agentTimezone)->setTimezone('UTC');
                    }
                    $dailyAppointmentCount = Appointment::whereBetween('start_at', [$startOfDay, $endOfDay])
                        ->whereNotIn('status', ['cancelled', 'auto_cancelled', 'pending'])
                        ->when($isAdminAppointment, function ($query) use ($appointment) {
                            $query->where('admin_id', $appointment->admin_id)
                                ->where('is_admin_appointment', 1);
                        }, function ($query) use ($appointment) {
                            $query->where('agent_id', $appointment->agent_id);
                        })
                        ->count();
                    // If daily appointment count is greater than or equal to daily limit, return error
                    if ($dailyAppointmentCount >= $dailyLimit) {
                        return ApiResponseService::validationError(
                            trans('Agent cannot accept more appointments on provided date.')
                        );
                    }
                }

                // Idempotent check: same as existing
                $newStartAgent = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
                $newEndAgent = Carbon::parse($dateKey.' '.$endTimeStr, $adminTimezone)->setTimezone($agentTimezone);
                $newStartUtc = (clone $newStartAgent)->setTimezone('UTC')->toDateTimeString();
                $newEndUtc = (clone $newEndAgent)->setTimezone('UTC')->toDateTimeString();
                if ($appointment->start_at === $newStartUtc && $appointment->end_at === $newEndUtc) {
                    DB::rollBack();
                    if ($isAgent) {
                        $startAt = $appointment->start_at;
                        unset($appointment->start_at);
                        $endAt = $appointment->end_at;
                        unset($appointment->end_at);
                        $appointment->start_at = Carbon::parse($startAt, 'UTC')->setTimezone($agentTimezone);
                        $appointment->end_at = Carbon::parse($endAt, 'UTC')->setTimezone($agentTimezone);
                    } else {
                        $startAt = $appointment->start_at;
                        unset($appointment->start_at);
                        $endAt = $appointment->end_at;
                        unset($appointment->end_at);
                        $appointment->start_at = Carbon::parse($startAt, 'UTC')->setTimezone($adminTimezone);
                        $appointment->end_at = Carbon::parse($endAt, 'UTC')->setTimezone($adminTimezone);
                    }

                    return ApiResponseService::successResponse(trans('No changes'), $appointment);
                }

                // Past date/time checks per role
                if ($isAgent) {
                    $nowAgent = Carbon::now($agentTimezone);
                    if ($newStartAgent->lt($nowAgent)) {
                        DB::rollBack();

                        return ApiResponseService::validationError(trans('You cannot select a past date/time'));
                    }
                } else {
                    $newStartAdmin = Carbon::parse($dateKey.' '.$startTimeStr, $adminTimezone);
                    $nowAdmin = Carbon::now($adminTimezone);
                    if ($newStartAdmin->lt($nowAdmin)) {
                        DB::rollBack();

                        return ApiResponseService::validationError(trans('You cannot select a past date/time'));
                    }
                }

                if ($newEndAgent <= $newStartAgent) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('End time must be greater than start time'));
                }
                if ($meetingDurationMinutes > 0 && $newStartAgent->diffInMinutes($newEndAgent) !== $meetingDurationMinutes) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('Invalid time duration'));
                }

                // Check availability similar to createAppointment
                $dayName = strtolower($newStartAgent->englishDayOfWeek);
                if ($isAdminAppointment) {
                    $windows = AgentAvailability::where('admin_id', $appointment->admin_id)
                        ->where('is_active', 1)
                        ->where('is_admin_data', 1)
                        ->where('day_of_week', $dayName)
                        ->get();
                    $extraWindows = AgentExtraTimeSlot::where(['admin_id' => $appointment->admin_id, 'is_admin_data' => 1])
                        ->where('date', $dateKey)
                        ->get();
                } else {
                    $windows = AgentAvailability::where('agent_id', $appointment->agent_id)
                        ->where('is_active', 1)
                        ->where('day_of_week', $dayName)
                        ->get();
                    $extraWindows = AgentExtraTimeSlot::where(['agent_id' => $appointment->agent_id])
                        ->where('date', $dateKey)
                        ->get();
                }
                $windows = $windows->merge($extraWindows);

                // Build slots
                $slots = [];
                foreach ($windows as $w) {
                    $winStart = Carbon::parse($dateKey.' '.$w->start_time, 'UTC')->setTimezone($agentTimezone);
                    $winEnd = Carbon::parse($dateKey.' '.$w->end_time, 'UTC')->setTimezone($agentTimezone);
                    if ($winEnd <= $winStart) {
                        continue;
                    }
                    $cursor = (clone $winStart);
                    while (true) {
                        $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                        if ($candidateEnd > $winEnd) {
                            break;
                        }
                        $slots[] = ['start' => (clone $cursor), 'end' => (clone $candidateEnd)];
                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                    }
                }
                if (empty($slots)) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('Selected time is outside agent availability'));
                }

                // Find exact requested slot in slots and ensure it's not blocked
                $slotValid = false;
                foreach ($slots as $s) {
                    $sStartAgent = $s['start'];
                    $sEndAgent = $s['end'];
                    if ($sStartAgent->format('H:i') !== $newStartAgent->format('H:i') || $sEndAgent->format('H:i') !== $newEndAgent->format('H:i')) {
                        continue;
                    }
                    $slotValid = true;
                    break;
                }
                if (! $slotValid) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('Selected time is not available'));
                }

                // Record reschedule and update appointment
                AppointmentReschedule::create([
                    'appointment_id' => $appointment->id,
                    'old_start_at' => $appointment->start_at,
                    'old_end_at' => $appointment->end_at,
                    'new_start_at' => $newStartUtc,
                    'new_end_at' => $newEndUtc,
                    'reason' => $reason,
                    'rescheduled_by' => $isAgent ? 'agent' : 'user',
                ]);

                $appointment->start_at = $newStartUtc;
                $appointment->end_at = $newEndUtc;
                $appointment->status = 'rescheduled';
                $appointment->last_status_updated_by = $isAgent ? 'agent' : 'user';
                $appointment->meeting_type = $request->meeting_type;
                $appointment->save();
            }

            // Handle confirm
            if ($requestedStatus === 'confirmed') {
                if (! $isAgent) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('Only agents can confirm appointments'));
                }
                if (in_array($appointment->status, ['cancelled'])) {
                    DB::rollBack();

                    return ApiResponseService::validationError(trans('Cancelled appointment cannot be confirmed'));
                }
                $appointment->status = 'confirmed';
                $appointment->last_status_updated_by = 'agent';
                $appointment->save();
            }

            // Handle cancel
            if ($requestedStatus === 'cancelled') {
                if ($appointment->status === 'cancelled') {
                    DB::rollBack();

                    return ApiResponseService::successResponse(trans('No changes'), $appointment);
                }
                $appointment->status = 'cancelled';
                $appointment->last_status_updated_by = $isAgent ? 'agent' : 'user';
                $appointment->save();
                AppointmentCancellation::create([
                    'appointment_id' => $appointment->id,
                    'reason' => $reason,
                    'cancelled_by' => $isAgent ? 'agent' : 'user',
                ]);
            }

            $changedBy = $isAgent ? 'agent' : 'user';

            AppointmentNotificationService::sendStatusNotification(
                $appointment,
                $appointment->status,
                $reason,
                $changedBy
            );

            $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone);
            $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone);

            DB::commit();

            return ApiResponseService::successResponse(trans('Appointment status updated successfully'), $appointment);
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse();
        }
    }

    public function updateAppointmentMeetingType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|exists:appointments,id',
                'meeting_type' => 'required|in:phone,virtual,in_person',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $loggedInUser = Auth::user();
            $appointment = Appointment::where('id', $request->appointment_id)->where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            })->first();
            if (! $appointment) {
                ApiResponseService::validationError(trans('You are not authorized to update this appointment'));
            }
            if ($appointment->status === 'cancelled') {
                ApiResponseService::validationError(trans('Appointment is cancelled'));
            }
            // Store old meeting type for notification
            $oldMeetingType = $appointment->meeting_type;
            $newMeetingType = $request->meeting_type;

            // Check if meeting type actually changed
            if ($oldMeetingType === $newMeetingType) {
                ApiResponseService::successResponse(trans('No changes made'), $appointment);
            }

            // Update meeting type
            Appointment::where('id', $request->appointment_id)->update(['meeting_type' => $newMeetingType]);

            // Determine who made the change for notification targeting
            $updatedBy = null;
            if ($appointment->agent_id == $loggedInUser->id) {
                $updatedBy = 'agent';
            } elseif ($appointment->user_id == $loggedInUser->id) {
                $updatedBy = 'user';
            }

            // Send notifications using the appointment notification service
            AppointmentNotificationService::sendMeetingTypeChangeNotification(
                $appointment,
                $oldMeetingType,
                $newMeetingType,
                $updatedBy
            );

            ApiResponseService::successResponse(trans('Appointment meeting type updated successfully'), $appointment);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAgentAppointments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
                'meeting_type' => 'nullable|string|in:phone,virtual,in_person',
                'status' => 'nullable|in:pending,confirmed,completed,cancelled,rescheduled,auto_cancelled',
                'date_filter' => 'nullable|string|in:upcoming,previous',
            ], [
                'offset.integer' => trans('Offset must be an integer'),
                'limit.integer' => trans('Limit must be an integer'),
                'meeting_type.in' => trans('Meeting type must be phone, virtual, or in_person'),
                'status.in' => trans('Status must be pending, confirmed, completed, cancelled, rescheduled, or auto_cancelled'),
                'date_filter.in' => trans('Date filter must be upcoming, or previous'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();
            // dd($isAgent);
            $agentTimezone = $loggedInUser->getTimezone(true);
            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to get agent appointments'));
            }
            $appointmentQuery = Appointment::where('agent_id', $loggedInUser->id)->with('user:id,name,profile', 'property:id,title,title_image')
                ->when($request->has('date_filter') && ! empty($request->date_filter), function ($query) use ($request, $agentTimezone) {
                    switch ($request->date_filter) {
                        case 'upcoming':
                            $status = ['confirmed', 'rescheduled', 'pending', 'cancelled', 'auto_cancelled'];
                            $date = now()->setTimezone($agentTimezone)->toDateString();
                            $query->whereIn('status', $status)->whereDate('start_at', '>=', $date);
                            break;
                        case 'previous':
                            $status = ['completed', 'rejected', 'cancelled', 'auto_cancelled'];
                            $date = now()->setTimezone($agentTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '<', $date);
                            break;
                    }
                })
                ->when($request->has('meeting_type') && ! empty($request->meeting_type), function ($query) use ($request) {
                    $query->where('meeting_type', $request->meeting_type);
                })->when($request->has('status') && ! empty($request->status), function ($query) use ($request) {
                    $query->where('status', $request->status);
                });
            // Total
            $totalAppointments = $appointmentQuery->clone()->count();

            $appointments = $appointmentQuery
                ->with(['user:id,name,profile,email', 'property' => function ($propertyQuery) {
                    $propertyQuery->select('id', 'title', 'title_image', 'price', 'propery_type', 'address', 'state', 'country', 'city', 'rentduration', 'category_id')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
                }, 'agent.agent_booking_preferences:id,agent_id,availability_types', 'cancellations:id,appointment_id,reason,cancelled_by'])
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            $appointments = $appointments->map(function ($appointment) use ($agentTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('Y-m-d H:i:s');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone)->format('Y-m-d H:i:s');
                $appointment->property->translated_title = $appointment->property->translated_title;
                $appointment->property->property_type = $appointment->property->propery_type;
                $appointment->property->is_premium = $appointment->property->is_premium;
                $appointment->property->is_promoted = $appointment->property->is_promoted;
                $appointment->property->parameters = $appointment->property->parameters;
                $appointment->property->category->translated_name = $appointment->property->category->translated_name;
                $appointment->agent->is_user_verified = $appointment->agent->is_user_verified;
                $appointment->availability_types = $appointment->agent->agent_booking_preferences->availability_types;
                $appointment->reason = $appointment->status === 'cancelled' ? optional($appointment->cancellations->last())->reason : null;
                unset($appointment->agent);

                return $appointment;
            });

            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointments, ['total' => $totalAppointments]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse(' '.$e->getMessage());
        }
    }

    public function getUserAppointments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
                'date_filter' => 'nullable|string|in:upcoming,previous',
                'meeting_type' => 'nullable|string|in:phone,virtual,in_person',
                'status' => 'nullable|in:pending,confirmed,completed,cancelled,rescheduled,auto_cancelled',
            ], [
                'offset.integer' => trans('Offset must be an integer'),
                'limit.integer' => trans('Limit must be an integer'),
                'meeting_type.in' => trans('Meeting type must be phone, virtual, or in_person'),
                'status.in' => trans('Status must be pending, confirmed, completed, cancelled, rescheduled, or auto_cancelled'),
                'date_filter.in' => trans('Date filter must be upcoming, or previous'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            $userTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            $appointmentQuery = Appointment::where('user_id', $loggedInUser->id)
                ->when($request->has('date_filter') && ! empty($request->date_filter), function ($query) use ($request, $userTimezone) {
                    switch ($request->date_filter) {
                        case 'upcoming':
                            $status = ['confirmed', 'rescheduled', 'pending', 'cancelled', 'auto_cancelled'];
                            $date = now()->setTimezone($userTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '>=', $date);
                            break;
                        case 'previous':
                            $status = ['completed', 'rejected', 'cancelled', 'auto_cancelled'];
                            $date = now()->setTimezone($userTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '<', $date);
                            break;
                    }
                })
                ->when($request->has('meeting_type') && ! empty($request->meeting_type), function ($query) use ($request) {
                    $query->where('meeting_type', $request->meeting_type);
                })->when($request->has('status') && ! empty($request->status), function ($query) use ($request) {
                    $query->where('status', $request->status);
                });

            // Total
            $totalAppointments = $appointmentQuery->count();

            $appointments = $appointmentQuery->clone()
                ->with(['agent' => function ($agentQuery) {
                    $agentQuery->with('agent_booking_preferences:id,agent_id,availability_types')->select('id', 'name', 'profile', 'email');
                }, 'admin' => function ($adminQuery) {
                    $adminQuery->with('agent_booking_preferences:id,admin_id,availability_types')->select('id', 'name', 'profile', 'email');
                }, 'property' => function ($propertyQuery) {
                    $propertyQuery->select('id', 'title', 'title_image', 'price', 'propery_type', 'address', 'state', 'country', 'city', 'rentduration', 'category_id')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
                }, 'cancellations:id,appointment_id,reason,cancelled_by'])
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            $appointments = $appointments->map(function ($appointment) use ($userTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($userTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s');
                $appointment->property->translated_title = $appointment->property->translated_title;
                $appointment->property->property_type = $appointment->property->propery_type;
                $appointment->property->is_premium = $appointment->property->is_premium;
                $appointment->property->is_promoted = $appointment->property->is_promoted;
                $appointment->property->parameters = $appointment->property->parameters;
                $appointment->property->category->translated_name = $appointment->property->category->translated_name;
                if ($appointment->status == 'cancelled') {
                    $appointment->reason = $appointment->cancellations->last()->reason;
                } else {
                    $appointment->reason = null;
                }
                if ($appointment->is_admin_appointment) {
                    $appointment->admin->is_user_verified = true;
                } else {
                    $appointment->agent->is_user_verified = $appointment->agent->is_user_verified;
                }
                $appointment->availability_types = $appointment->is_admin_appointment ? $appointment->admin->agent_booking_preferences->availability_types : $appointment->agent->agent_booking_preferences->availability_types;
                if ($appointment->is_admin_appointment) {
                    unset($appointment->agent);
                    $appointment->admin->name = trans('Admin');
                } else {
                    unset($appointment->admin);
                }

                return $appointment;
            });

            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointments, ['total' => $totalAppointments]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function reportUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:customers,id',
                'reason' => 'required|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();
            $loggedInUser = Auth::user();
            $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('status', 'approved')->where('form_type', 'become_agent')->exists();
            if (! $isAgent) {
                $isAgent = AgentVerification::where('customer_id', $loggedInUser->id)->where('form_type', 'become_agent')->where('status', 'approved')->exists();
            }

            if (! $isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to report user'));
            }
            $agentId = $loggedInUser->id;
            $appointment = Appointment::where('agent_id', $agentId)->where('user_id', $request->user_id)->first();
            if (! $appointment) {
                return ApiResponseService::validationError(trans('Appointment not found'));
            }
            $userId = $request->user_id;
            $reason = $request->reason;
            $reportUser = ReportUserByAgent::updateOrCreate([
                'agent_id' => $agentId,
                'user_id' => $userId,
            ], [
                'reason' => $reason,
                'status' => 'pending',
            ]);
            $appointments = Appointment::where(['agent_id' => $agentId, 'user_id' => $userId])->with('property:id,title')->get();
            if (collect($appointments)->isNotEmpty()) {
                $appointmentIds = $appointments->pluck('id');
                // Cancel appointments
                Appointment::whereIn('id', $appointmentIds)->update(['status' => 'cancelled']);
                // Create appointment cancellations with reason
                $appointmentCancellationData = [];
                foreach ($appointmentIds as $appointmentId) {
                    $appointmentCancellationData[] = [
                        'appointment_id' => $appointmentId,
                        'reason' => $reason,
                        'cancelled_by' => 'system',
                    ];
                }
                AppointmentCancellation::insert($appointmentCancellationData);

                $user = Customer::where('id', $userId)->select('id', 'name', 'email')->first();
                foreach ($appointments as $appointment) {
                    // Send notification and email to user about cancellation due to being reported
                    if ($user) {
                        AppointmentNotificationService::sendCancellationByReportNotification($appointment, $reason);
                    }
                }
            }
            DB::commit();

            return ApiResponseService::successResponse(trans('Report user submitted successfully'), $reportUser);
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getUserReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            // $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'approved')->exists();
            // if (!$isAgent) {
            //     $isAgent = \App\Models\AgentVerification::where('customer_id', $loggedInUser->id)->where('form_type', 'become_agent')->where('status', 'approved')->exists();
            // }
            // if (!$isAgent) {
            //     return ApiResponseService::validationError(trans("You are not authorized to get user reports"));
            // }
            $userReports = ReportUserByAgent::where('agent_id', $loggedInUser->id)->with('user:id,name,profile')->latest()->offset($offset)->limit($limit)->get();

            return ApiResponseService::successResponse(trans('User reports fetched successfully'), $userReports);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }
}
