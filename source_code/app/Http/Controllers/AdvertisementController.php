<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Category;
use App\Models\Notifications;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Usertokens;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'advertisement')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::where('status', 1)->get();

        return view('advertisement.index', compact('category'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $status = $request->input('status', null);
        $for = $request->input('for', null);
        $visibility = $request->input('visibility', null);
        $category = $request->input('category', null);

        $sql = Advertisement::with('customer', 'property:id,title,title_image,category_id', 'project:id,title,image,category_id', 'property.category:id,category', 'project.category:id,category')
            ->when($status != null && ($status == 0 || $status == 1 || $status == 2 || $status == 3), function ($query) use ($status) {
                $query->where('status', $status);
            })->when(! empty($for) && ($for == 'property' || $for == 'project'), function ($query) use ($for) {
                $query->where('for', $for);
            })->when($visibility != null && ($visibility == 1 || $visibility == 0), function ($query) use ($visibility) {
                $query->where('is_enable', $visibility);
            })->when(! empty($category), function ($query) use ($category) {
                $query->where(function ($q) use ($category) {
                    $q->whereHas('property.category', function ($subQ) use ($category) {
                        $subQ->where('id', $category);
                    })->orWhereHas('project.category', function ($subQ) use ($category) {
                        $subQ->where('id', $category);
                    });
                });
            });

        // Filter by user/agent role
        if (isset($_GET['role_context_filter']) && $_GET['role_context_filter'] !== '') {
            $addedAsFilter = $_GET['role_context_filter'];
            $sql = $sql->whereHas('customer', function ($q) use ($addedAsFilter) {
                if ($addedAsFilter === 'agent') {
                    $q->where('is_agent', 1);
                } else {
                    $q->where('is_agent', 0);
                }
            });
        }

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%$search%");
                    })->orWhereHas('property', function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%$search%");
                    })->orWhereHas('project', function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%$search%");
                    })->orWhereHas('property.category', function ($q) use ($search) {
                        $q->where('category', 'LIKE', "%$search%");
                    })->orWhereHas('project.category', function ($q) use ($search) {
                        $q->where('category', 'LIKE', "%$search%");
                    });
            });
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }
        $res = $sql->orderBy($sort, $order)->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;
        $status = '';

        $operate = '';
        foreach ($res as $row) {
            $operate = BootstrapTableService::editButton('', true, '#editModal', null, $row->id, 'setValue(this.id)', $row->id);
            $tempRow = $row->toArray();

            $image = null;
            if ($row->for == 'property') {
                if ($row->property) {
                    $image = $row->property->title_image;
                }
            } else {
                if ($row->project) {
                    $image = $row->project->image;
                }
            }
            $tempRow['image'] = $image;

            $tempRow['edit_status_url'] = route('featured_properties.update-advertisement-status');

            if ($row->status == 0) {
                $status = trans('Approved');
            }
            if ($row->status == 1) {
                $status = trans('Pending');
            }
            if ($row->status == 2) {
                $status = trans('Rejected');
            }
            if ($row->status == 3) {
                $status = trans('Expired');
            }
            $tempRow['category'] = $row->for == 'property' ? $row->property->category->category : $row->project->category->category ?? null;
            $tempRow['listing_title'] = $row->for == 'property' ? $row->property->title : $row->project->title ?? null;
            $tempRow['status'] = $status;
            $tempRow['start_date'] = Carbon::parse($row->start_date)->format('d-m-Y');
            $tempRow['end_date'] = $row->end_date ? Carbon::parse($row->end_date)->toIso8601String() : null;

            $tempRow['created_by_role'] = $row->role_context ?? 'user';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {
        if (! has_permissions('update', 'advertisement')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            Advertisement::find($request->id)->update(['status' => $request->edit_adv_status]);

            $adv = Advertisement::with('customer')->find($request->id);
            $status = $adv->status;
            if ($status == '0') {
                $status_text = 'Approved';
            } elseif ($status == '1') {
                $status_text = 'Pending';
            } elseif ($status == '2') {
                $status_text = 'Rejected';
            }

            // Send mail for property feature status
            try {
                $advertisementData = Advertisement::with('customer:id,name,email', 'property:id,title')->select('id', 'customer_id', 'property_id')->find($request->id);
                if ($advertisementData->customer->email) {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('property_ads_status');

                    // Email Template
                    $userStatusTemplateData = system_setting($emailTypeData['type']);
                    $appName = env('APP_NAME') ?? 'eBroker';
                    $variables = [
                        'app_name' => $appName,
                        'user_name' => $advertisementData->customer->name,
                        'property_name' => $advertisementData->property->title,
                        'advertisement_status' => $status_text,
                        'email' => $advertisementData->customer->email,
                    ];
                    if (empty($userStatusTemplateData)) {
                        $userStatusTemplateData = 'Your Property :- '.$variables['propertyName']."'s feature status ".$variables['status'];
                    }
                    $userStatusTemplate = HelperService::replaceEmailVariables($userStatusTemplateData, $variables);

                    $data = [
                        'email_template' => $userStatusTemplate,
                        'email' => $advertisementData->customer->email,
                        'title' => $emailTypeData['title'],
                    ];
                    HelperService::sendMail($data);

                }

            } catch (Exception $e) {
                Log::error('Something Went Wrong in Feature Mail Sending');
            }

            /** Notification */
            if ($adv->customer->notification == 1) {
                $user_token = Usertokens::where('customer_id', $adv->customer->id)->pluck('fcm_id')->toArray();
                // START :: Send Notification To Customer
                $fcm_ids = [];
                $fcm_ids = $user_token;
                if (! empty($fcm_ids)) {
                    $registrationIDs = $fcm_ids;
                    $advRoleContext = 'user';
                    if ($adv->property_id) {
                        $advRoleContext = Property::where('id', $adv->property_id)->value('role_context') ?? 'user';
                    } elseif ($adv->project_id) {
                        $advRoleContext = Projects::where('id', $adv->project_id)->value('role_context') ?? 'user';
                    }
                    $fcmMsg = [
                        'title' => 'Advertisement Request',
                        'message' => 'Advertisement Request Is :status_text',
                        'type' => 'advertisement_request',
                        'body' => 'Advertisement Request Is :status_text',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string) $adv->id,
                        'role_context' => $advRoleContext,
                        'replace' => [
                            'status_text' => $status_text,
                        ],
                    ];
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                // END ::  Send Notification To Customer

                Notifications::create([
                    'title' => 'Property Inquiry Updated',
                    'message' => 'Your Advertisement Request is '.$status_text,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $adv->customer->id,
                    'propertys_id' => $adv->id,
                ]);
            }

            ResponseService::successRedirectResponse('Advertisement status update Successfully');
        }
    }

    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Advertisement::where('id', $request->id)->update(['is_enable' => $request->status]);
            ResponseService::successResponse($request->status ? 'Advertisement Activated Successfully' : 'Advertisement Deactivated Successfully');
        }
    }
}
