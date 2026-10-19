<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BlockedUserForAppointment;
use App\Models\Customer;
use App\Models\Notifications;
use App\Models\ReportUserByAgent;
use App\Models\User;
use App\Models\Usertokens;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminAppointmentReportController extends Controller
{
    /**
     * Display appointment reports index page
     */
    public function index()
    {
        if (! has_permissions('read', 'appointment_reports')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('admin.appointment.reports.index');
    }

    /**
     * Get appointment reports list for bootstrap table
     */
    public function getReportsList(Request $request)
    {
        if (! has_permissions('read', 'appointment_reports')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $query = ReportUserByAgent::select('report_user_by_agents.*')->with([
            'agent:id,name,email,mobile',
            'user:id,name,email,mobile',
            'admin:id,name',
        ]);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('report_user_by_agents.id', 'LIKE', "%$search%")
                    ->orWhere('report_user_by_agents.reason', 'LIKE', "%$search%")
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('name', 'LIKE', "%$search%")
                            ->orWhere('email', 'LIKE', "%$search%");
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%$search%")
                            ->orWhere('email', 'LIKE', "%$search%");
                    });
            });
        }

        $total = $query->count();

        // Handle sorting
        if ($sort == 'agent_name') {
            $query->leftJoin('customers as agents', 'report_user_by_agents.agent_id', '=', 'agents.id')
                ->orderBy('agents.name', $order);
        } elseif ($sort == 'user_name') {
            $query->leftJoin('customers as users', 'report_user_by_agents.user_id', '=', 'users.id')
                ->orderBy('users.name', $order);
        } elseif ($sort == 'raw_status_badge') {
            $query->orderBy('report_user_by_agents.status', $order);
        } elseif ($sort == 'reported_at_formatted') {
            $query->orderBy('report_user_by_agents.created_at', $order);
        } else {
            $query->orderBy('report_user_by_agents.'.$sort, $order);
        }

        $reports = $query->skip($offset)
            ->take($limit)
            ->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        foreach ($reports as $report) {
            $tempRow = $report->toArray();

            // Add action buttons
            $operate = '';
            if (has_permissions('update', 'appointment_reports')) {
                if ($report->status === 'pending') {
                    $operate .= '<button type="button" class="btn btn-success btn-sm approve-report" data-id="'.$report->id.'" title="'.__('Approve').'"><i class="bi bi-check-circle"></i></button> ';
                    $operate .= '<button type="button" class="btn btn-danger btn-sm reject-report" data-id="'.$report->id.'" title="'.__('Reject').'"><i class="bi bi-x-circle"></i></button> ';
                }
                $operate .= '<button type="button" class="btn btn-warning btn-sm block-appointment-user" data-id="'.$report->id.'" title="'.__('Block User').'"><i class="bi bi-person-x"></i></button>';
            }

            $tempRow['operate'] = $operate;
            $tempRow['agent_name'] = $report->agent ? $report->agent->name : 'N/A';
            $tempRow['user_name'] = $report->user ? $report->user->name : 'N/A';
            $tempRow['admin_name'] = $report->admin ? $report->admin->name : 'N/A';
            $tempRow['reported_at_formatted'] = $report->created_at ? $report->created_at->format('Y-m-d H:i:s') : 'N/A';
            $tempRow['raw_status_badge'] = $this->getStatusBadge($report->status);

            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Update report status (approve/reject)
     */
    public function updateReportStatus(Request $request)
    {
        if (! has_permissions('update', 'appointment_reports')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'report_id' => 'required|exists:report_user_by_agents,id',
            'status' => 'required|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return ResponseService::errorResponse($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $report = ReportUserByAgent::findOrFail($request->report_id);
            $report->status = $request->status;
            $report->save();

            DB::commit();

            return ResponseService::successResponse('Report status updated successfully');

        } catch (Exception $e) {
            DB::rollBack();

            return ResponseService::errorResponse();
        }
    }

    /**
     * Block user for appointments
     */
    public function blockUser(Request $request)
    {
        if (! has_permissions('update', 'appointment_reports')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'report_id' => 'required|exists:report_user_by_agents,id',
            'block_type' => 'required|in:agent_specific,global',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ResponseService::errorResponse($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $report = ReportUserByAgent::with(['agent', 'user'])->findOrFail($request->report_id);
            $adminId = Auth::id();

            // Check if user is already blocked
            $existingBlock = BlockedUserForAppointment::where('user_id', $report->user_id)
                ->where('status', 'active');

            if ($request->block_type === 'agent_specific') {
                $existingBlock->where('agent_id', $report->agent_id);
            } else {
                $existingBlock->where('block_type', 'global');
            }

            if ($existingBlock->exists()) {
                return ResponseService::errorResponse('User is already blocked for this scope');
            }

            // Create block record
            $block = BlockedUserForAppointment::create([
                'user_id' => $report->user_id,
                'blocked_by_admin_id' => $adminId,
                'report_id' => $report->id,
                'block_type' => $request->block_type,
                'agent_id' => $request->block_type === 'agent_specific' ? $report->agent_id : null,
                'reason' => $request->reason ?: $report->reason,
                'status' => 'active',
                'blocked_at' => now(),
            ]);

            // Send notification to agents
            $this->sendBlockingNotification($block);

            // Make all Appointments of this user cancelled
            Appointment::where('user_id', $report->user_id)->update(['status' => 'cancelled']);

            DB::commit();

            return ResponseService::successResponse('User blocked successfully');

        } catch (Exception $e) {
            DB::rollBack();

            return ResponseService::errorResponse();
        }
    }

    /**
     * Display blocked users management page
     */
    public function blockedUsersIndex()
    {
        if (! has_permissions('read', 'appointment_reports')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('admin.appointment.reports.blocked-users');
    }

    /**
     * Get blocked users list for bootstrap table
     */
    public function getBlockedUsersList(Request $request)
    {
        if (! has_permissions('read', 'appointment_reports')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $query = BlockedUserForAppointment::select('blocked_users_for_appointments.*')->with([
            'user:id,name,email,mobile',
            'agent:id,name,email,mobile',
            'blockedByAdmin:id,name',
            'report',
        ])->active();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('blocked_users_for_appointments.id', 'LIKE', "%$search%")
                    ->orWhere('blocked_users_for_appointments.reason', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%$search%")
                            ->orWhere('email', 'LIKE', "%$search%");
                    })
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('name', 'LIKE', "%$search%");
                    });
            });
        }

        $total = $query->count();

        // Handle sorting
        if ($sort == 'user_name') {
            $query->leftJoin('customers as users', 'blocked_users_for_appointments.user_id', '=', 'users.id')
                ->orderBy('users.name', $order);
        } elseif ($sort == 'agent_name') {
            $query->leftJoin('customers as agents', 'blocked_users_for_appointments.agent_id', '=', 'agents.id')
                ->orderBy('agents.name', $order);
        } elseif ($sort == 'raw_block_type_badge') {
            $query->orderBy('blocked_users_for_appointments.block_type', $order);
        } elseif ($sort == 'blocked_by_admin') {
            $query->leftJoin('users as admins', 'blocked_users_for_appointments.blocked_by_admin_id', '=', 'admins.id')
                ->orderBy('admins.name', $order);
        } elseif ($sort == 'blocked_at_formatted') {
            $query->orderBy('blocked_users_for_appointments.blocked_at', $order);
        } else {
            $query->orderBy('blocked_users_for_appointments.'.$sort, $order);
        }

        $blockedUsers = $query->skip($offset)
            ->take($limit)
            ->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        foreach ($blockedUsers as $block) {
            $tempRow = $block->toArray();

            // Add action buttons
            $operate = '';
            if (has_permissions('update', 'appointment_reports')) {
                $operate .= '<button type="button" class="btn btn-success btn-sm unblock-appointment-user" data-id="'.$block->id.'"><i class="bi bi-unlock"></i> '.__('Unblock').'</button>';
            }

            $tempRow['operate'] = $operate;
            $tempRow['user_name'] = $block->user ? $block->user->name : 'N/A';
            $tempRow['agent_name'] = $block->agent ? $block->agent->name : 'All Agents';
            $tempRow['blocked_by_admin'] = $block->blockedByAdmin ? $block->blockedByAdmin->name : 'N/A';
            $tempRow['raw_block_type_badge'] = $this->getBlockTypeBadge($block->block_type);
            $tempRow['blocked_at_formatted'] = $block->blocked_at ? $block->blocked_at->format('Y-m-d H:i:s') : 'N/A';

            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Unblock user
     */
    public function unblockUser(Request $request)
    {
        if (! has_permissions('update', 'appointment_reports')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'block_id' => 'required|exists:blocked_users_for_appointments,id',
        ]);

        if ($validator->fails()) {
            return ResponseService::errorResponse($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $block = BlockedUserForAppointment::findOrFail($request->block_id);
            $block->status = 'inactive';
            $block->unblocked_at = now();
            $block->save();

            // Send unblocking notification to agents
            $this->sendUnblockingNotification($block);

            DB::commit();

            return ResponseService::successResponse('User unblocked successfully');

        } catch (Exception $e) {
            DB::rollBack();

            return ResponseService::errorResponse();
        }
    }

    /**
     * Send blocking notification to agents
     */
    private function sendBlockingNotification(BlockedUserForAppointment $block)
    {
        try {
            $agents = collect();

            if ($block->block_type === 'global') {
                // Get all agents
                $agents = Customer::where('is_agent', true)->get();
            } else {
                // Get specific agent
                $agent = Customer::find($block->agent_id);
                if ($agent) {
                    $agents = collect([$agent]);
                }
            }

            foreach ($agents as $agent) {
                // Create notification
                Notifications::create([
                    'title' => 'User Blocked for Appointments',
                    'message' => "User {$block->user->name} has been blocked from making appointments. Reason: {$block->reason}",
                    'image' => '',
                    'type' => '1', // Custom type for blocking notifications
                    'send_type' => '1', // Individual notification
                    'customers_id' => $agent->id,
                    'propertys_id' => 0,
                ]);

                // Send push notification if FCM token exists
                $fcmTokens = Usertokens::where('customer_id', $agent->id)->pluck('fcm_id')->toArray();
                if (! empty($fcmTokens)) {
                    $fcmMsg = [
                        'title' => 'User Blocked for Appointments',
                        'body' => 'User :name has been blocked from making appointments',
                        'data' => [
                            'type' => 'user_blocked',
                            'user_id' => $block->user_id,
                            'block_type' => $block->block_type,
                        ],
                        'replace' => [
                            'name' => $block->user->name,
                        ],
                    ];
                    send_push_notification($fcmTokens, $fcmMsg);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to send blocking notification: '.$e->getMessage());
        }
    }

    /**
     * Send unblocking notification to agents
     */
    private function sendUnblockingNotification(BlockedUserForAppointment $block)
    {
        try {
            $agents = collect();

            if ($block->block_type === 'global') {
                // Get all agents
                $agents = Customer::where('is_agent', true)->get();
            } else {
                // Get specific agent
                $agent = Customer::find($block->agent_id);
                if ($agent) {
                    $agents = collect([$agent]);
                }
            }

            foreach ($agents as $agent) {
                // Create notification
                Notifications::create([
                    'title' => 'User Unblocked for Appointments',
                    'message' => "User {$block->user->name} has been unblocked and can now make appointments again.",
                    'image' => '',
                    'type' => '1', // Custom type for unblocking notifications
                    'send_type' => '1', // Individual notification
                    'customers_id' => $agent->id,
                    'propertys_id' => 0,
                ]);

                // Send push notification if FCM token exists
                $fcmTokens = Usertokens::where('customer_id', $agent->id)->pluck('fcm_id')->toArray();
                if (! empty($fcmTokens)) {
                    $fcmMsg = [
                        'title' => 'User Unblocked for Appointments',
                        'body' => 'User :name has been unblocked and can now make appointments again',
                        'data' => [
                            'type' => 'user_unblocked',
                            'user_id' => $block->user_id,
                            'block_type' => $block->block_type,
                        ],
                        'replace' => [
                            'name' => $block->user->name,
                        ],
                    ];
                    send_push_notification($fcmTokens, $fcmMsg);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to send unblocking notification: '.$e->getMessage());
        }
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">'.__('Pending').'</span>',
            'approved' => '<span class="badge bg-success">'.__('Approved').'</span>',
            'rejected' => '<span class="badge bg-danger">'.__('Rejected').'</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">'.__('Unknown').'</span>';
    }

    /**
     * Get block type badge HTML
     */
    private function getBlockTypeBadge($blockType)
    {
        $badges = [
            'agent_specific' => '<span class="badge bg-info">'.__('Agent Specific').'</span>',
            'global' => '<span class="badge bg-danger">'.__('Global').'</span>',
        ];

        return $badges[$blockType] ?? '<span class="badge bg-secondary">'.__('Unknown').'</span>';
    }
}
