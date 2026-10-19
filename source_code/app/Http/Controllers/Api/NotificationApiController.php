<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifications;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationApiController extends Controller
{
    public function get_notification_list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $loggedInUserData = Auth::user();
        $loggedInUserId = $loggedInUserData->id;
        $loggedInCreatedAt = $loggedInUserData->created_at;
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $notificationQuery = Notifications::where(function ($query) use ($loggedInUserId, $loggedInCreatedAt) {
            $query->where(['customers_id' => $loggedInUserId, 'send_type' => 0])
                ->orWhere(function ($query) use ($loggedInCreatedAt) {
                    $query->where('send_type', 1)
                        ->where('created_at', '>=', $loggedInCreatedAt);
                });
        })
            ->where('role_context', $request->user_active_role)
            ->with('property:id,title_image')
            ->select('id', 'title', 'message', 'image', 'type', 'send_type', 'customers_id', 'propertys_id', 'role_context', 'created_at')
            ->orderBy('id', 'DESC');

        $total = $notificationQuery->count();

        $result = $notificationQuery->clone()
            ->skip($offset)
            ->take($limit)
            ->get();

        if (! $result->isEmpty()) {
            $result = $result->map(function ($notification) {
                $notification->created = $notification->created_at->diffForHumans();
                $notification->notification_image = ! empty($notification->image) ? $notification->image : (! empty($notification->propertys_id) && ! empty($notification->property) ? $notification->property->title_image : '');
                unset($notification->image);

                return $notification;
            });

            $response = [
                'error' => false,
                'total' => $total,
                'data' => $result->toArray(),
            ];
        } else {
            $response = [
                'error' => false,
                'message' => trans('No Data Found'),
                'data' => [],
            ];
        }

        return response()->json($response);
    }
}
