<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Notifications;
use App\Models\Property;
use App\Models\Usertokens;
use App\Services\FileService;
use App\Services\ResponseService;
use Dotenv\Validator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'notification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $property_list = Property::where(['status' => 1, 'request_status' => 'approved'])->select('title', 'request_status')->get();

            return view('notification.index', compact('property_list'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        file_put_contents(storage_path('logs/laravel.log'), 'Store method ENTRY at '.now()."\n", FILE_APPEND);
        if (! has_permissions('create', 'notification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            try {
                DB::beginTransaction();
                $firebaseProjectId = system_setting('firebase_project_id');
                $firebaseServiceJsonFile = system_setting('firebase_service_json_file');
                if (empty($firebaseProjectId)) {
                    ResponseService::errorRedirectResponse(route('notification.index'), 'Firebase Project ID is Missing');
                } elseif (empty($firebaseServiceJsonFile)) {
                    ResponseService::errorRedirectResponse(route('notification.index'), 'Firebase Service File is Missing');
                } else {
                    file_put_contents(storage_path('logs/laravel.log'), 'Validation started at '.now()."\n", FILE_APPEND);
                    $request->validate([
                        'file' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
                        'type' => 'required',
                        'send_type' => 'required',
                        'user_id' => 'required_if:send_type,==,0',
                        'title' => 'required',
                        'message' => 'required',
                        'include_image' => 'nullable',
                    ],
                        [
                            'user_id.*' => trans('Select User From Table'),
                            'file.max' => __('Maximum file size is 3MB.'),
                        ]);

                    $imageName = '';
                    $includeImage = $request->include_image ? 1 : 0;
                    if ($request->hasFile('file') && $includeImage == 1) {
                        $imageName = FileService::compressAndUpload($request->file('file'), config('global.NOTIFICATION_IMG_PATH'));
                    }

                    // Get Customer ids who is active and has notification activated
                    $customerQuery = Customer::where(['isActive' => '1', 'notification' => 1]);
                    $sendTarget = $request->send_type;

                    // Determine notification type
                    $type = 0;
                    $propertyId = null;
                    if (isset($request->property) && ! empty(trim($request->property))) {
                        $type = 2;
                        $propertyId = $request->property;
                        $propertyData = Property::find($propertyId);
                        if (! $propertyData) {
                            ResponseService::errorRedirectResponse(route('notification.index'), 'Property Not Found');
                        }
                    } else {
                        $type = $request->type;
                    }

                    // Build notifications based on send target
                    $notificationsToCreate = [];
                    $allFcmIds = [];

                    if ($sendTarget == 'everyone') {
                        $allCustomerIds = $customerQuery->clone()->pluck('id');
                        $allFcmIds = Usertokens::whereIn('customer_id', $allCustomerIds)->pluck('fcm_id')->toArray();
                        $notificationsToCreate[] = ['send_type' => 1, 'customers_id' => '', 'role_context' => 'general'];

                    } elseif ($sendTarget == 'all_users') {
                        $allCustomerIds = $customerQuery->clone()->pluck('id');
                        $allFcmIds = Usertokens::whereIn('customer_id', $allCustomerIds)->pluck('fcm_id')->toArray();
                        $notificationsToCreate[] = ['send_type' => 1, 'customers_id' => '', 'role_context' => 'user'];

                    } elseif ($sendTarget == 'all_agents') {
                        $agentIds = $customerQuery->clone()->where('is_agent', 1)->pluck('id');
                        $allFcmIds = Usertokens::whereIn('customer_id', $agentIds)->pluck('fcm_id')->toArray();
                        $notificationsToCreate[] = ['send_type' => 1, 'customers_id' => '', 'role_context' => 'agent'];

                    } else {
                        // Specific People — separate user and agent selections
                        $userIds = $request->user_id;
                        $agentUserIds = $request->agent_user_id;

                        // Collect all FCM tokens
                        if (! empty($userIds)) {
                            $userFcmIds = Usertokens::whereIn('customer_id', explode(',', $userIds))->pluck('fcm_id')->toArray();
                            $allFcmIds = array_merge($allFcmIds, $userFcmIds);
                            $notificationsToCreate[] = ['send_type' => 0, 'customers_id' => $userIds, 'role_context' => 'user'];
                        }
                        if (! empty($agentUserIds)) {
                            $agentFcmIds = Usertokens::whereIn('customer_id', explode(',', $agentUserIds))->pluck('fcm_id')->toArray();
                            $allFcmIds = array_merge($allFcmIds, $agentFcmIds);
                            $notificationsToCreate[] = ['send_type' => 0, 'customers_id' => $agentUserIds, 'role_context' => 'agent'];
                        }
                    }

                    // Create notification records
                    foreach ($notificationsToCreate as $notifData) {
                        Notifications::create([
                            'title' => $request->title,
                            'message' => $request->message,
                            'image' => $imageName,
                            'type' => $type,
                            'send_type' => $notifData['send_type'],
                            'customers_id' => $notifData['customers_id'],
                            'propertys_id' => $propertyId ?? 0,
                            'role_context' => $notifData['role_context'],
                        ]);
                    }

                    // Collect unique FCM IDs for push notification
                    $fcm_ids = collect(array_unique(array_filter($allFcmIds)));

                    $img = null;
                    if ($imageName != '') {
                        $img = FileService::getFileUrl(config('global.NOTIFICATION_IMG_PATH').$imageName);
                    } else {
                        if ($request->property) {
                            $img = $propertyData->title_image;
                        }
                    }

                    // START :: Send Notification To Customer
                    Log::info('NotificationController :: FCM IDs count : '.collect($fcm_ids)->count());
                    if (collect($fcm_ids)->isNotEmpty()) {

                        $registrationIDs = array_filter($fcm_ids->toArray());
                        Log::info('NotificationController :: Registration IDs : ', $registrationIDs);

                        $fcmMsg = [
                            'title' => $request->title,
                            'message' => $request->message,
                            'image' => $img,
                            'property_id' => ! empty($propertyId) ? $propertyId : null,
                            'type' => 'default',
                            'body' => $request->message,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',

                        ];
                        send_push_notification($registrationIDs, $fcmMsg);
                        // END ::  Send Notification To Customer
                        file_put_contents(storage_path('logs/laravel.log'), 'Notification sent to helper at '.now()."\n", FILE_APPEND);
                    } else {
                        file_put_contents(storage_path('logs/laravel.log'), 'No FCM IDs found at '.now()."\n", FILE_APPEND);
                    }
                    DB::commit();
                    ResponseService::successRedirectResponse('Message Send Successfully');
                }
            } catch (Exception $e) {
                DB::rollBack();
                ResponseService::errorRedirectResponse(route('notification.index'), $e->getMessage());
            }
        }
    }

    public function destroy(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                ResponseService::errorResponse(trans('This is not allowed in the Demo Version'));
            }
            if (! has_permissions('delete', 'notification')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            $id = $request->id;
            $notification = Notifications::where('id', $id)->first();
            FileService::delete(config('global.NOTIFICATION_IMG_PATH'), $notification->getRawOriginal('image'));
            $notification->delete();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Notification Delete Error', 'Something Went Wrong');
        }
    }

    public function notificationList(Request $request)
    {
        if (! has_permissions('read', 'notification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $sql = Notifications::where('id', '!=', 0);

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('message', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);

        $res = $sql->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;
        $operate = '';
        foreach ($res as $row) {
            $tempRow = $row->toArray();

            if (has_permissions('delete', 'notification')) {
                $operate = '<a data-id='.$row->id.' data-image="'.$row->image.'" class="btn icon btn-danger btn-sm rounded-pill mt-2 delete-data" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            }
            $type = '';
            if ($row->type == 0) {
                $type = trans('General Notification');
            }
            if ($row->type == 1) {
                $type = trans('Inquiry Notification');
            }
            if ($row->type == 2) {
                $type = trans('Property Notification');
            }
            $tempRow['count'] = $count;

            $tempRow['type'] = $type;
            $tempRow['title'] = $row->title;
            $tempRow['send_type'] = ($row->send_type == 0) ? 'Selected' : 'All';
            $tempRow['created_at'] = $row->created_at->diffForHumans();
            $tempRow['operate'] = $operate;
            if ($row->customerData) {
                $tempRow['customer_data'] = $row->customerData->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                });
            }
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function multiple_delete(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                ResponseService::errorResponse(trans('This is not allowed in the Demo Version'));
            }
            if (has_permissions('delete', 'notification')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            $id = $request->id;
            $notifications = Notifications::whereIn('id', explode(',', $id))->get();
            foreach ($notifications as $notification) {
                FileService::delete(config('global.NOTIFICATION_IMG_PATH'), $notification->getRawOriginal('image'));
            }
            Notifications::whereIn('id', explode(',', $id))->delete();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Notification Multiple Delete Error', 'Something Went Wrong');
        }
    }
}
