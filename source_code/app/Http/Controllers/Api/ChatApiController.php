<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\BlockedChatUser;
use App\Models\Chats;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use App\Models\Usertokens;
use App\Services\ApiResponseService;
use App\Services\FileService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatApiController extends Controller
{
    public function send_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_id' => 'required',
            'receiver_id' => 'required',
            'property_id' => 'required',
            'file' => 'nullable|mimes:png,jpg,jpeg,webp,pdf,doc,docx|max:2024',
            'audio' => 'nullable|mimes:mpeg,m4a,mp3,mp4|max:5024',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::select('id', 'name', 'profile', 'is_agent')->with(['usertokens' => function ($q) {
            $q->select('fcm_id', 'id', 'customer_id');
        }])->find($request->receiver_id);
        if (collect($customer)->isNotEmpty()) {
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'user_id' => $request->receiver_id])->count();
            if ($senderBlockedReciever) {
                ApiResponseService::validationError('You have blocked user');
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_user_id' => $request->receiver_id, 'user_id' => $request->sender_id])->count();
            if ($recieverBlockedSender) {
                ApiResponseService::validationError('You are blocked by user');
            }
        } else {
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'admin' => 1])->count();
            if ($senderBlockedReciever) {
                ApiResponseService::validationError('You have blocked admin');
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_admin' => 1, 'user_id' => $request->sender_id])->count();
            if ($recieverBlockedSender) {
                ApiResponseService::validationError('You are blocked by admin');
            }
        }

        // check if customer is agent and chat is first time for this perticular property then he can not initiate chat with other customer and admin
        $sender = Customer::find($request->sender_id);
        $isFirstChat = Chats::Where(['property_id' => $request->property_id])->count() == 0;
        if ($isFirstChat && $request->user_active_role == 'agent') {
            ApiResponseService::validationError('Agents cannot initiate chats with other customers or admin');
        }

        if ($request->sender_id == $request->receiver_id) {
            ApiResponseService::validationError('You cannot send message to yourself');
        }

        $fcm_id = [];
        $chat = new Chats;
        $chat->sender_id = $request->sender_id;
        $chat->receiver_id = $request->receiver_id;
        $chat->property_id = $request->property_id;
        $chat->message = $request->message;
        $chat->sender_role_context = $request->user_active_role;
        // check if receiver is property owner
        $receiverProperty = Property::where(['id' => $request->property_id, 'added_by' => $request->receiver_id])->first();
        $isReceiverPropertyOwner = $receiverProperty ? true : false;
        if ($isReceiverPropertyOwner) {
            $chat->receiver_role_context = $receiverProperty->role_context;
        } elseif ($request->receiver_id == 0) {
            $chat->receiver_role_context = 'admin';
        } else {
            $chat->receiver_role_context = 'user';
        }

        //    dd($receiverProperty->added_by, $request->receiver_id);

        if (! is_null($receiverProperty) && $receiverProperty->added_by != $request->receiver_id) {
            return ApiResponseService::validationError('Receiver must be property owner');
        }

        if ($request->hasFile('file')) {
            $path = config('global.CHAT_FILE');
            $file = $request->file('file');
            $chat->file = FileService::compressAndUpload($file, $path);
        }

        if ($request->hasFile('audio')) {
            $path = config('global.CHAT_AUDIO');
            $file = $request->file('audio');
            $chat->audio = FileService::compressAndUpload($file, $path);
        }
        $chat->save();

        if ($customer) {
            foreach ($customer->usertokens as $usertokens) {
                array_push($fcm_id, $usertokens->fcm_id);
            }
            $username = $customer->name;
        } else {
            $user_data = User::select('fcm_id', 'name')->get();
            $username = 'Admin';
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }
        }
        $senderUser = Customer::select('fcm_id', 'name', 'profile')->find($request->sender_id);
        if ($senderUser) {
            $profile = $senderUser->profile;
        } else {
            $profile = '';
        }

        $Property = Property::find($request->property_id);

        $chat_message_type = '';
        if (! empty($request->file('audio'))) {
            $chat_message_type = 'audio';
        } elseif (! empty($request->file('file')) && $request->message == '') {
            $chat_message_type = 'file';
        } elseif (! empty($request->file('file')) && $request->message != '') {
            $chat_message_type = 'file_and_text';
        } elseif (empty($request->file('file')) && $request->message != '' && empty($request->file('audio'))) {
            $chat_message_type = 'text';
        } else {
            $chat_message_type = 'text';
        }

        $unreadMessagesCount = Chats::where([
            'property_id' => $request->property_id,
            'receiver_id' => $request->sender_id,
            'is_read' => false,
            'receiver_role_context' => $request->user_active_role,
        ])->count();

        $fcmMsg = [
            'title' => 'Message',
            'message' => $request->message,
            'type' => 'chat',
            'body' => $request->message,
            'sender_id' => $request->sender_id,
            'sender_name' => $senderUser->name ?? 'User',
            'sender_profile' => $senderUser->profile ?? '',
            'receiver_id' => $request->receiver_id,
            'file' => $chat->file,
            'username' => $username,
            'user_profile' => $profile,
            'audio' => $chat->audio,
            'date' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'sound' => 'default',
            'time_ago' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'property_id' => (string) $Property->id,
            'property_title_image' => $Property->title_image,
            'title' => $Property->title,
            'chat_message_type' => $chat_message_type,
            'created_at' => Carbon::parse($chat->created_at)->toIso8601ZuluString(),
            'unread_messages_count' => (string) $unreadMessagesCount,
            'role_context' => $chat->receiver_role_context,
        ];

        file_put_contents(storage_path('logs/debug_notification.log'), 'ChatApiController: send_message called at '.now()."\n", FILE_APPEND);
        $send = send_push_notification($fcm_id, $fcmMsg);
        file_put_contents(storage_path('logs/debug_notification.log'), 'ChatApiController: send_message notification sent at '.now()."\n", FILE_APPEND);
        $response['error'] = false;
        $response['message'] = 'Data Store Successfully';
        $response['id'] = $chat->id;

        return response()->json($response);
    }

    // public function get_messages(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'property_id' => 'required',
    //         'user_id' => 'required'
    //     ]);
    //     if (!$validator->fails()) {
    //         $currentUser = Auth::user();
    //         $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();
    //         $userId = $request->user_id;
    //         $reciever = Customer::find($request->user_id);

    //         Chats::where(['property_id' => $request->property_id, 'receiver_id' => $currentUser->id, 'is_read' => false, 'role_context' => $request->user_active_role])->update(['is_read' => true]);

    //         $perPage = $request->per_page ? $request->per_page : 15;
    //         $page = $request->page ?? 1;
    //         $chat = Chats::where(['property_id' => $request->property_id, 'role_context' => $request->user_active_role])
    //             ->where(function ($query) use ($currentUser, $userId) {
    //                 $query->where(function ($query) use ($currentUser, $userId) {
    //                     $query->where(['sender_id' => $currentUser->id, 'receiver_id' => $userId]);
    //                 })->orWhere(function ($query) use ($currentUser, $userId) {
    //                     $query->where(['sender_id' => $userId, 'receiver_id' => $currentUser->id]);
    //                 });
    //             })
    //             ->orderBy('created_at', 'DESC')
    //             ->paginate($perPage, ['*'], 'page', $page);

    //         $isChatWithAdmin = false;
    //         $isChatWithCustomer = false;
    //         $chat_message_type = "";
    //         if ($chat) {
    //             $chat->map(function ($chat) use ($chat_message_type, $currentUser, $adminData, &$isChatWithAdmin, &$isChatWithCustomer) {
    //                 if (!empty($chat->audio)) {
    //                     $chat_message_type = "audio";
    //                 } else if (!empty($chat->file) && $chat->message == "") {
    //                     $chat_message_type = "file";
    //                 } else if (!empty($chat->file) && $chat->message != "") {
    //                     $chat_message_type = "file_and_text";
    //                 } else if (empty($chat->file) && !empty($chat->message) && empty($chat->audio)) {
    //                     $chat_message_type = "text";
    //                 } else {
    //                     $chat_message_type = "text";
    //                 }
    //                 $chat['chat_message_type'] = $chat_message_type;
    //                 $chat['user_profile'] = $currentUser->profile;
    //                 $chat['time_ago'] = $chat->created_at->diffForHumans();
    //                 if ($chat->sender_id == $adminData->id || $chat->receiver_id == $adminData->id) {
    //                     $isChatWithAdmin = true;
    //                 } else {
    //                     $isChatWithCustomer = true;
    //                 }
    //             });

    //             $isBlockedByMe = false;
    //             $isBlockedByUser = false;
    //             if ($isChatWithAdmin == true) {
    //                 $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)->where('admin', 1)->exists();
    //                 $isBlockedByUser = BlockedChatUser::where('by_admin', 1)->where('user_id', $currentUser->id)->exists();
    //             } else {
    //                 $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)->where('user_id', $userId)->exists();
    //                 $isBlockedByUser = BlockedChatUser::where('by_user_id', $userId)->where('user_id', $currentUser->id)->exists();
    //             }
    //             // dd($reciever->id);

    //             $response['error'] = false;
    //             $response['message'] = trans("Data Fetched Successfully");
    //             $response['total_page'] = $chat->lastPage();
    //             $response['data'] = array_merge($chat->toArray(), [
    //                 'is_blocked_by_me' => $isBlockedByMe,
    //                 'is_blocked_by_user' => $isBlockedByUser,
    //                 'is_agent' => $reciever->is_agent ?? false,
    //                 'is_agent_verified' => $reciever->is_agent_verified ?? false,
    //                 'is_user_verified' => $reciever->is_user_verified ?? false,
    //                 'is_admin' => $reciever?->id == 0 ? true : false
    //             ]);

    //         } else {
    //             $response['error'] = false;
    //             $response['message'] = trans("No Data Found");
    //             $response['data'] = [];
    //         }
    //     } else {
    //         $response['error'] = true;
    //         $response['message'] = trans("Please fill all data and Submit");
    //     }

    //     // $response['is_agent'] = Auth::user()["is_agent"] ?? null;
    //     // $response['is_agent_verified'] = Auth::user()["is_agent_verified"] ?? null;
    //     // $response['is_user_verified'] = Auth::user()["is_user_verified"] ?? null;
    //     // $response['is_admin'] = Customer::find($request->user_id)->type == 0 ? true : false;

    //     return response()->json($response);
    // }

    public function get_messages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'user_id' => 'required',
        ]);

        if (! $validator->fails()) {

            $currentUser = Auth::user();
            $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();
            $userId = $request->user_id;
            $reciever = Customer::find($request->user_id);

            Chats::where([
                'property_id' => $request->property_id,
                'receiver_id' => $currentUser->id,
                'sender_id' => $userId,
                'receiver_role_context' => $request->user_active_role,
            ])
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $perPage = $request->per_page ? $request->per_page : 15;
            $page = $request->page ?? 1;

            $chat = Chats::where('property_id', $request->property_id)
                ->where(function ($query) use ($currentUser, $userId) {
                    $query->where(function ($query) use ($currentUser, $userId) {
                        $query->where([
                            'sender_id' => $currentUser->id,
                            'receiver_id' => $userId,
                        ]);
                    })->orWhere(function ($query) use ($currentUser, $userId) {
                        $query->where([
                            'sender_id' => $userId,
                            'receiver_id' => $currentUser->id,
                        ]);
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage, ['*'], 'page', $page);

            $isChatWithAdmin = false;
            $isChatWithCustomer = false;
            $chat_message_type = '';

            if ($chat) {

                $chat->map(function ($chat) use ($chat_message_type, $currentUser, $adminData, &$isChatWithAdmin, &$isChatWithCustomer) {

                    if (! empty($chat->audio)) {
                        $chat_message_type = 'audio';
                    } elseif (! empty($chat->file) && $chat->message == '') {
                        $chat_message_type = 'file';
                    } elseif (! empty($chat->file) && $chat->message != '') {
                        $chat_message_type = 'file_and_text';
                    } else {
                        $chat_message_type = 'text';
                    }

                    $chat['chat_message_type'] = $chat_message_type;
                    $chat['user_profile'] = $currentUser->profile;
                    $chat['time_ago'] = $chat->created_at->diffForHumans();

                    if ($adminData && ($chat->sender_id == $adminData->id || $chat->receiver_id == $adminData->id)) {
                        $isChatWithAdmin = true;
                    } else {
                        $isChatWithCustomer = true;
                    }
                });

                $isBlockedByMe = false;
                $isBlockedByUser = false;

                if ($isChatWithAdmin == true) {
                    $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)
                        ->where('admin', 1)
                        ->exists();

                    $isBlockedByUser = BlockedChatUser::where('by_admin', 1)
                        ->where('user_id', $currentUser->id)
                        ->exists();
                } else {
                    $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)
                        ->where('user_id', $userId)
                        ->exists();

                    $isBlockedByUser = BlockedChatUser::where('by_user_id', $userId)
                        ->where('user_id', $currentUser->id)
                        ->exists();
                }

                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['total_page'] = $chat->lastPage();
                $response['data'] = array_merge($chat->toArray(), [
                    'is_blocked_by_me' => $isBlockedByMe,
                    'is_blocked_by_user' => $isBlockedByUser,
                    'is_agent' => $reciever->is_agent ?? false,
                    'is_agent_verified' => $reciever->is_agent_verified ?? false,
                    'is_user_verified' => $reciever->is_user_verified ?? false,
                    'is_admin' => $reciever?->id == 0 ? true : false,
                ]);

            } else {
                $response['error'] = false;
                $response['message'] = trans('No Data Found');
                $response['data'] = [];
            }

        } else {
            $response['error'] = true;
            $response['message'] = trans('Please fill all data and Submit');
        }

        return response()->json($response);
    }

    // public function get_chats(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'per_page' => 'nullable|integer|min:1|max:200',
    //         'page' => 'nullable|integer|min:1',
    //     ]);
    //     if ($validator->fails()) {
    //         ApiResponseService::validationError($validator->errors()->first());
    //     }
    //     $current_user = Auth::user()->id;
    //     $perPage = $request->per_page ? $request->per_page : 15;
    //     $page = $request->page ?? 1;
    //     $role_context = $request->user_active_role;

    //     $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();

    //     $chat = Chats::with(['sender', 'receiver'])->with('property.translations')
    //         ->select(
    //             'id', 'sender_id', 'receiver_id', 'property_id', 'created_at',
    //             DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
    //             DB::raw('GREATEST(sender_id, receiver_id) as user2_id'),
    //             DB::raw('COUNT(CASE WHEN receiver_id = ' . $current_user . ' AND is_read = 0 THEN 1 END) AS unread_count')
    //         )
    //         ->where(function ($query) use ($current_user, $role_context) {
    //             // $query->where('role_context', $role_context)->where('sender_id', $current_user)->orWhere('receiver_id', $current_user);

    //             if ($role_context == 'agent') {

    //          $query->where(function($q) use
    //             ($current_user) {
    //              $q->where('sender_id',
    //                 $current_user)->where('role_context', 'agent');
    //             })->orWhere(function($q) use
    //             ($current_user) {
    //              $q->where('receiver_id',
    //             $current_user)->where('role_context', 'agent');
    //             });
    //         } else {

    //             $query->where(function($q) use
    //                 ($current_user) {
    //                 $q->where('sender_id',$current_user)->where('role_context', 'user');
    //             })->orWhere(function($q) use
    //             ($current_user) {
    //                 $q->where('receiver_id',$current_user)->where('role_context', 'user');
    //             });
    //         }
    //         })
    //         ->orderBy('id', 'desc')
    //         ->groupBy('user1_id', 'user2_id', 'property_id')
    //         ->paginate($perPage, ['*'], 'page', $page);

    //     if (!$chat->isEmpty()) {
    //         $rows = array();
    //         $count = 1;
    //         $response['total_page'] = $chat->lastPage();

    //         foreach ($chat as $key => $row) {
    //             $tempRow = array();
    //             $tempRow['property_id'] = $row->property_id;
    //             $tempRow['title'] = $row->property->title;
    //             $tempRow['translated_title'] = $row->property->translated_title;
    //             $tempRow['title_image'] = $row->property->title_image;
    //             $tempRow['date'] = $row->created_at;
    //             $tempRow['property_id'] = $row->property_id;
    //             $tempRow['unread_count'] = $row->unread_count;
    //             if (!$row->receiver || !$row->sender) {
    //                 $user = Customer::where('id', $row->sender_id)->orWhere('id', $row->receiver_id)->select('id')->first();
    //                 $blockedByMe = BlockedChatUser::where('by_user_id', $user->id)->where('admin', 1)->exists();
    //                 $blockedByAdmin = BlockedChatUser::where('by_admin', 1)->where('user_id', $user->id)->exists();
    //                 $tempRow['is_blocked_by_me'] = $blockedByMe;
    //                 $tempRow['is_blocked_by_user'] = $blockedByAdmin;
    //                 $tempRow['user_id'] = 0;
    //                 $tempRow['name'] = "Admin";
    //                 $tempRow['profile'] = !empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg');
    //                 $tempRow['is_agent'] = true;
    //                 $tempRow['is_agent_verified'] = true;
    //                 $tempRow['is_user_verified'] = true;
    //                 $tempRow['is_admin'] = true;
    //             } else {
    //                 $isBlockedByMe = false;
    //                 $isBlockedByUser = false;
    //                 if ($row->sender->id == $current_user) {
    //                     $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)->where('user_id', $row->receiver->id)->exists();
    //                     $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->receiver->id)->where('user_id', $current_user)->exists();
    //                     $tempRow['is_blocked_by_me'] = $isBlockedByMe;
    //                     $tempRow['is_blocked_by_user'] = $isBlockedByUser;
    //                     $tempRow['user_id'] = $row->receiver->id;
    //                     $tempRow['name'] = $row->receiver->name;
    //                     $tempRow['profile'] = $row->receiver->profile;
    //                     $tempRow['fcm_id'] = $row->receiver->fcm_id;
    //                     $tempRow['is_agent'] = $row->receiver->is_agent ?? false;
    //                     $tempRow['is_agent_verified'] = $row->receiver->is_agent_verified ?? false;
    //                     $tempRow['is_user_verified'] = $row->receiver->is_user_verified ?? false;
    //                     $tempRow['is_admin'] = $row->receiver->id == 0 ? true : false;
    //                 }
    //                 if ($row->receiver->id == $current_user) {
    //                     // dd($row->receiver);
    //                     // $reciev = Customer::where('id', $request->receiver_id)->first();
    //                     $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)->where('user_id', $row->sender->id)->exists();
    //                     $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->sender->id)->where('user_id', $current_user)->exists();
    //                     $tempRow['is_blocked_by_me'] = $isBlockedByMe;
    //                     $tempRow['is_blocked_by_user'] = $isBlockedByUser;
    //                     $tempRow['user_id'] = $row->sender->id;
    //                     $tempRow['name'] = $row->sender->name;
    //                     $tempRow['profile'] = $row->sender->profile;
    //                     $tempRow['fcm_id'] = $row->sender->fcm_id;
    //                     $tempRow['is_agent'] = $row->sender->is_agent ?? false;
    //                     $tempRow['is_agent_verified'] = $row->sender->is_agent_verified ?? false;
    //                     $tempRow['is_user_verified'] = $row->sender->is_user_verified ?? false;
    //                     $tempRow['is_admin'] = $row->sender->id == 0 ? true : false;
    //                 }
    //             }
    //             $rows[] = $tempRow;
    //             $count++;
    //         }

    //         // $rows = new CustomerResource($rows, ['is_agent','is_agent_verified','is_user_verified']);

    //         $response['error'] = false;
    //         $response['message'] = trans("Data Fetched Successfully");
    //         $response['data'] = $rows;
    //     } else {
    //         $response['error'] = false;
    //         $response['message'] = trans("No Data Found");
    //         $response['data'] = [];
    //     }
    //     return response()->json($response);
    // }

    //     public function get_chats(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'per_page' => 'nullable|integer|min:1|max:200',
    //         'page' => 'nullable|integer|min:1',
    //     ]);

    //     if ($validator->fails()) {
    //         return ApiResponseService::validationError($validator->errors()->first());
    //     }

    //     $current_user = Auth::user()->id;
    //     $perPage = $request->per_page ?? 15;
    //     $page = $request->page ?? 1;
    //     $role_context = $request->user_active_role; // 'agent' or 'user'

    //     $adminData = User::where('type', 0)
    //         ->select('id', 'name', 'profile')
    //         ->first();

    //     $chat = Chats::with(['sender', 'receiver', 'property.translations'])
    //         ->select(
    //             'id',
    //             'sender_id',
    //             'receiver_id',
    //             'property_id',
    //             'created_at',
    //             DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
    //             DB::raw('GREATEST(sender_id, receiver_id) as user2_id'),
    //             DB::raw("COUNT(CASE WHEN receiver_id = {$current_user} AND is_read = 0 THEN 1 END) AS unread_count")
    //         )

    //         // ✅ FIXED FILTER (core bug solved)
    //         // ->where(function ($query) use ($current_user, $role_context) {
    //         //     $query->where('role_context', $role_context)
    //         //         ->where(function ($q) use ($current_user) {
    //         //             $q->where('sender_id', $current_user)
    //         //               ->orWhere('receiver_id', $current_user);
    //         //         });
    //         // })

    //         ->where(function ($query) use ($current_user, $role_context) {

    //     $query->where(function ($q) use ($current_user) {
    //         $q->where('sender_id', $current_user)
    //           ->orWhere('receiver_id', $current_user);
    //     });

    //     if ($role_context == 'agent') {
    //         $query->whereHas('property', function ($q) use ($current_user) {
    //             $q->where('added_by', $current_user); // agent owns property
    //         });
    //     } else {
    //         $query->whereHas('property', function ($q) use ($current_user) {
    //             $q->where('added_by', '!=', $current_user); // user side
    //         });
    //     }
    // })

    //         ->groupBy('user1_id', 'user2_id', 'property_id', 'role_context') // ✅ important fix
    //         ->orderBy('id', 'desc')
    //         ->paginate($perPage, ['*'], 'page', $page);

    //     if ($chat->isEmpty()) {
    //         return response()->json([
    //             'error' => false,
    //             'message' => trans("No Data Found"),
    //             'data' => []
    //         ]);
    //     }

    //     $rows = [];
    //     $response['total_page'] = $chat->lastPage();

    //     foreach ($chat as $row) {

    //         $tempRow = [];

    //         $tempRow['property_id'] = $row->property_id;
    //         $tempRow['title'] = optional($row->property)->title;
    //         $tempRow['translated_title'] = optional($row->property)->translated_title;
    //         $tempRow['title_image'] = optional($row->property)->title_image;
    //         $tempRow['date'] = $row->created_at;
    //         $tempRow['unread_count'] = $row->unread_count;

    //         // 🔥 Identify other user
    //         $otherUser = ($row->sender_id == $current_user)
    //             ? $row->receiver
    //             : $row->sender;

    //         // ✅ ADMIN CASE
    //         if (!$row->sender || !$row->receiver) {

    //             $user = Customer::where('id', $row->sender_id)
    //                 ->orWhere('id', $row->receiver_id)
    //                 ->select('id')
    //                 ->first();

    //             $tempRow['is_blocked_by_me'] = BlockedChatUser::where('by_user_id', $user->id)->where('admin', 1)->exists();
    //             $tempRow['is_blocked_by_user'] = BlockedChatUser::where('by_admin', 1)->where('user_id', $user->id)->exists();

    //             $tempRow['user_id'] = 0;
    //             $tempRow['name'] = "Admin";
    //             $tempRow['profile'] = !empty($adminData->getRawOriginal('profile'))
    //                 ? $adminData->profile
    //                 : url('assets/images/faces/2.jpg');

    //             $tempRow['is_agent'] = true;
    //             $tempRow['is_agent_verified'] = true;
    //             $tempRow['is_user_verified'] = true;
    //             $tempRow['is_admin'] = true;

    //         } else {

    //             // ✅ BLOCK CHECKS
    //             $tempRow['is_blocked_by_me'] = BlockedChatUser::where('by_user_id', $current_user)
    //                 ->where('user_id', $otherUser->id)
    //                 ->exists();

    //             $tempRow['is_blocked_by_user'] = BlockedChatUser::where('by_user_id', $otherUser->id)
    //                 ->where('user_id', $current_user)
    //                 ->exists();

    //             // ✅ USER DATA
    //             $tempRow['user_id'] = $otherUser->id;
    //             $tempRow['name'] = $otherUser->name;
    //             $tempRow['profile'] = $otherUser->profile;
    //             $tempRow['fcm_id'] = $otherUser->fcm_id;

    //             $tempRow['is_agent'] = $otherUser->is_agent ?? false;
    //             $tempRow['is_agent_verified'] = $otherUser->is_agent_verified ?? false;
    //             $tempRow['is_user_verified'] = $otherUser->is_user_verified ?? false;
    //             $tempRow['is_admin'] = $otherUser->id == 0;
    //         }

    //         $rows[] = $tempRow;
    //     }

    //     return response()->json([
    //         'error' => false,
    //         'message' => trans("Data Fetched Successfully"),
    //         'total_page' => $response['total_page'],
    //         'data' => $rows
    //     ]);
    // }

    public function get_chats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        $current_user = Auth::user()->id;
        $perPage = $request->per_page ?? 15;
        $page = $request->page ?? 1;
        $role_context = $request->user_active_role;

        $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();

        $chats = Chats::with(['sender', 'receiver', 'property.translations'])
            ->where(function ($query) use ($current_user, $role_context) {
                $query->where(function ($q) use ($current_user, $role_context) {
                    $q->where('sender_id', $current_user)->where('sender_role_context', $role_context);
                })->orWhere(function ($q) use ($current_user, $role_context) {
                    $q->where('receiver_id', $current_user)->where('receiver_role_context', $role_context);
                });
            })
            ->orderBy('id', 'DESC')
            ->get();

        $grouped = $chats->groupBy(function ($item) {
            return min($item->sender_id, $item->receiver_id).'_'
                 .max($item->sender_id, $item->receiver_id).'_'
                 .$item->property_id;
        });

        $rows = [];

        foreach ($grouped as $group) {

            $row = $group->first(); // latest message

            $tempRow = [];
            $tempRow['property_id'] = $row->property_id;
            $tempRow['title'] = $row->property->title ?? '';
            $tempRow['translated_title'] = $row->property->translated_title ?? '';
            $tempRow['title_image'] = $row->property->title_image ?? '';
            $tempRow['date'] = $row->created_at;

            $tempRow['unread_count'] = $group->where('receiver_id', $current_user)
                ->where('receiver_role_context', $role_context)
                ->where('is_read', 0)
                ->count();

            if (! $row->receiver || ! $row->sender) {

                $user = Customer::where('id', $row->sender_id)
                    ->orWhere('id', $row->receiver_id)
                    ->select('id')
                    ->first();

                $blockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                    ->where('admin', 1)
                    ->exists();

                $blockedByAdmin = BlockedChatUser::where('by_admin', 1)
                    ->where('user_id', $current_user)
                    ->exists();

                $tempRow['is_blocked_by_me'] = $blockedByMe;
                $tempRow['is_blocked_by_user'] = $blockedByAdmin;

                $tempRow['user_id'] = 0;
                $tempRow['name'] = 'Admin';
                $tempRow['profile'] = ! empty($adminData->getRawOriginal('profile'))
                    ? $adminData->profile
                    : url('assets/images/faces/2.jpg');

                $tempRow['is_agent'] = true;
                $tempRow['is_agent_verified'] = true;
                $tempRow['is_user_verified'] = true;
                $tempRow['is_admin'] = true;

            } else {

                if ($row->sender->id == $current_user) {

                    $other = $row->receiver;

                    $tempRow['is_blocked_by_me'] = BlockedChatUser::where('by_user_id', $current_user)
                        ->where('user_id', $other->id)
                        ->exists();

                    $tempRow['is_blocked_by_user'] = BlockedChatUser::where('by_user_id', $other->id)
                        ->where('user_id', $current_user)
                        ->exists();

                } else {

                    $other = $row->sender;

                    $tempRow['is_blocked_by_me'] = BlockedChatUser::where('by_user_id', $current_user)
                        ->where('user_id', $other->id)
                        ->exists();

                    $tempRow['is_blocked_by_user'] = BlockedChatUser::where('by_user_id', $other->id)
                        ->where('user_id', $current_user)
                        ->exists();
                }

                $tempRow['user_id'] = $other->id;
                $tempRow['name'] = $other->name;
                $tempRow['profile'] = $other->profile;
                $tempRow['fcm_id'] = $other->fcm_id;
                $tempRow['is_agent'] = $other->is_agent ?? false;
                $tempRow['is_agent_verified'] = $other->is_agent_verified ?? false;
                $tempRow['is_user_verified'] = $other->is_user_verified ?? false;
                $tempRow['is_admin'] = $other->id == 0 ? true : false;
            }

            $rows[] = $tempRow;
        }

        $collection = collect($rows);
        $paginated = new LengthAwarePaginator(
            $collection->forPage($page, $perPage),
            $collection->count(),
            $perPage,
            $page
        );

        if (count($rows)) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['total_page'] = $paginated->lastPage();
            $response['data'] = $paginated->values();
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function delete_chat_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required',
        ]);
        // dd($request->all());
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $fcmId = Usertokens::select('fcm_id')->where('customer_id', $request->receiver_id)->pluck('fcm_id')->toArray();

            if (isset($request->message_id)) {
                $chat = Chats::where('id', $request->message_id)->first();
                if ($chat !== null) {
                    if (! empty($fcmId)) {
                        $registrationIDs = array_filter($fcmId);
                        $fcmMsg = [
                            'title' => 'Delete Chat Message',
                            'message' => 'Message Deleted Successfully',
                            'image' => '',
                            'type' => 'delete_message',
                            'message_id' => (string) $request->message_id,
                            'body' => 'Message Deleted Successfully',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'receiver_id' => (string) $chat->receiver_id,
                            'user_id' => (string) Auth::user()->id,
                            'property_id' => (string) $chat->property_id,
                        ];
                        send_push_notification($registrationIDs, $fcmMsg);
                    }
                    $chat->delete();
                    ApiResponseService::successResponse('Message Deleted Successfully');
                } else {
                    ApiResponseService::successResponse('No Data Found');
                }
            } elseif (isset($request->sender_id) && isset($request->receiver_id) && isset($request->property_id)) {
                $userChat = Chats::where('property_id', $request->property_id)->where(function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->where('sender_id', $request->sender_id)->where('receiver_id', $request->receiver_id);
                    })->orWhere(function ($query) use ($request) {
                        $query->where('sender_id', $request->receiver_id)->where('receiver_id', $request->sender_id);
                    });
                });

                $firstChat = $userChat->clone()->first();

                if ($firstChat) {

                    $userChat->delete();
                    $registrationIDs = array_filter($fcmId);
                    $fcmMsg = [
                        'title' => 'Chat Messages Deleted',
                        'message' => 'Chat Messages Deleted Successfully',
                        'image' => '',
                        'type' => 'chat_message_deleted',
                        'body' => 'Chat Messages Deleted Successfully',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'receiver_id' => (string) $firstChat->receiver_id,
                        'user_id' => (string) Auth::user()->id,
                        'property_id' => (string) $firstChat->property_id,
                    ];
                    send_push_notification($registrationIDs, $fcmMsg);
                    ApiResponseService::successResponse('Chat Deleted Successfully');
                } else {
                    ApiResponseService::successResponse('No Data Found');
                }
            } else {
                ApiResponseService::validationError('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function blockChatUser(Request $request)
    {
        $userId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot block yourself.');
                    }
                },
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $blockUserData = [
                'by_user_id' => $userId,
                'reason' => $request->reason ?? null,
            ];
            if ($request->has('to_user_id') && ! empty($request->to_user_id)) {
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id])->count();
                if ($ifExtryExists) {
                    ApiResponseService::validationError('User Already Blocked');
                }
                $blockUserData['user_id'] = $request->to_user_id;
            } elseif ($request->has('to_admin') && $request->to_admin == 1) {
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId, 'admin' => 1])->count();
                if ($ifExtryExists) {
                    ApiResponseService::validationError('Admin Already Blocked');
                }
                $blockUserData['admin'] = 1;
            } else {
                ApiResponseService::errorResponse();
            }

            BlockedChatUser::create($blockUserData);
            ApiResponseService::successResponse('User Blocked Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function unBlockChatUser(Request $request)
    {
        $userId = Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot unblock yourself.');
                    }
                },
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            if ($request->has('to_user_id') && ! empty($request->to_user_id)) {
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->clone()->count();
                if (! $ifExtryExists) {
                    ApiResponseService::validationError('No Blocked User Found');
                }
                $blockedUserQuery->delete();
            } elseif ($request->has('to_admin') && $request->to_admin == 1) {
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->count();
                if (! $ifExtryExists) {
                    ApiResponseService::validationError('No Blocked User Found');
                }
                $blockedUserQuery->delete();
            } else {
                ApiResponseService::errorResponse();
            }
            ApiResponseService::successResponse('User Unblocked Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
}
