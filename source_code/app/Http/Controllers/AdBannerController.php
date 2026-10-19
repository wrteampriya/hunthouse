<?php

namespace App\Http\Controllers;

use App\Models\AdBanner;
use App\Models\Category;
use App\Models\Property;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdBannerController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'ad-banners')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('ad-banners.index');
    }

    public function create()
    {
        if (! has_permissions('create', 'ad-banners')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        // Get categories for the property selection
        $categories = Category::where('status', 1)->get();

        return view('ad-banners.create', compact('categories'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'ad-banners')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'required|in:homepage,property_listing,property_detail',
                'platform' => 'required|in:app,web',
                'placement' => 'required|in:below_categories,above_all_properties,above_facilities,above_similar_properties,below_slider,above_footer,sidebar_below_filters,below_breadcrumb,sidebar_below_mortgage_loan_calculator,above_breadcrumb',
                'banner_image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:10240',
                'ad_type' => 'required|in:external_link,property,banner_only',
                'external_link_url' => 'nullable|url|max:255',
                'property_id' => 'nullable|integer|exists:propertys,id',
                'duration' => 'required|integer|min:1',
            ],
                [
                    'page.required' => trans('The page field is required.'),
                    'page.in' => trans('The page field must be a valid page.'),
                    'platform.required' => trans('The platform field is required.'),
                    'platform.in' => trans('The platform field must be a valid platform.'),
                    'placement.required' => trans('The placement field is required.'),
                    'placement.in' => trans('The placement field must be a valid placement.'),
                    'banner_image.required' => trans('The banner image field is required.'),
                    'banner_image.image' => trans('The banner image field must be an image.'),
                    'banner_image.mimes' => trans('The banner image field must be a valid image format.'),
                    'ad_type.required' => trans('The ad type field is required.'),
                    'ad_type.in' => trans('The ad type field must be a valid ad type.'),
                    'external_link_url.url' => trans('The external link url field must be a valid url.'),
                    'external_link_url.max' => trans('The external link url field must be less than 255 characters.'),
                    'property_id.integer' => trans('The property id field must be an integer.'),
                    'property_id.exists' => trans('The property id field must be an existing property.'),
                    'duration.required' => trans('The duration field is required.'),
                    'duration.integer' => trans('The duration field must be an integer.'),
                    'duration.min' => trans('The duration field must be at least 1.'),
                ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $image = FileService::compressAndUpload($request->file('banner_image'), config('global.ADBANNER_IMAGE_PATH'));

            $startAt = Carbon::today()->setTime(0, 0, 0);
            $endAt = Carbon::now()->addDays($request->duration)->setTime(0, 0, 0);
            AdBanner::create([
                'page' => $request->page,
                'platform' => $request->platform,
                'placement' => $request->placement,
                'image' => $image,
                'type' => $request->ad_type,
                'external_link_url' => $request->external_link_url ?? null,
                'property_id' => $request->property_id ?? null,
                'duration_days' => (int) $request->duration,
                'starts_at' => $startAt,
                'ends_at' => $endAt,
                'is_active' => true,
            ]);

            ResponseService::successResponse(trans('Advertisement banner saved successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse(trans('Error saving advertisement banner'), $e->getMessage());
        }
    }
    
    public function show(Request $request)
    {
        if (! has_permissions('read', 'ad-banners')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        // Use $_GET consistently like property controller
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : null;
        $platform = isset($_GET['platform']) && $_GET['platform'] !== '' ? $_GET['platform'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        $sql = AdBanner::with('property:id,title,address');

        // Apply search with word splitting
        if (! empty($search)) {
            $searchTerms = explode(' ', $search);
            $sql = $sql->where(function ($query) use ($search, $searchTerms) {
                // Full string match
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('page', 'LIKE', "%$search%")
                    ->orWhere('platform', 'LIKE', "%$search%")
                    ->orWhere('placement', 'LIKE', "%$search%")
                    ->orWhere('type', 'LIKE', "%$search%")
                    ->orWhereHas('property', function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%$search%");
                    });

                // Per-word match
                foreach ($searchTerms as $term) {
                    if (empty(trim($term))) {
                        continue;
                    }
                    $query->orWhere('id', 'LIKE', "%$term%")
                        ->orWhere('page', 'LIKE', "%$term%")
                        ->orWhere('platform', 'LIKE', "%$term%")
                        ->orWhere('placement', 'LIKE', "%$term%")
                        ->orWhere('type', 'LIKE', "%$term%")
                        ->orWhereHas('property', function ($q) use ($term) {
                            $q->where('title', 'LIKE', "%$term%");
                        });
                }
            });
        }

        // Apply page filter
        if ($page !== null) {
            $sql = $sql->where('page', $page);
        }

        // Apply platform filter
        if ($platform !== null) {
            $sql = $sql->where('platform', $platform);
        }

        // Apply status filter
        if ($status !== null) {
            if ($status === 'expired') {
                $sql = $sql->where('ends_at', '<', now());
            } else {
                $sql = $sql->where('is_active', $status)->where('ends_at', '>=', now());
            }
        }

        $sql = $sql->orderBy($sort, $order);

        $total = $sql->count();

        $sql = $sql->skip($offset)->take($limit);

        $res = $sql->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $operate = '';
            $tempRow['pageRawValue'] = $row->getRawOriginal('page');

            if (has_permissions('update', 'ad-banners')) {
                $operate .= BootstrapTableService::editButton(route('ad-banners.edit', $row->id), false);
            }
            if (has_permissions('delete', 'ad-banners')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('ad-banners.destroy', $row->id));
            }

            $tempRow['operate'] = $operate;
            $tempRow['edit_status_url'] = route('ad-banners.update-status', $row->id);

            $endsAt = Carbon::parse($row->ends_at);
            $tempRow['starts_at'] = date('d-m-Y', strtotime($row->starts_at));
            $tempRow['ends_at'] = $endsAt->format('d-m-Y');
            $tempRow['ends_at_raw'] = $endsAt->toDateString();
            $tempRow['is_expired'] = $row->is_expired;
            $tempRow['days_left'] = $endsAt->diffInDays(Carbon::now()) + 1;

            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function getPropertiesByCategory(Request $request)
    {
        try {
            $categoryId = $request->get('category_id');

            if (! $categoryId) {
                ResponseService::validationError(trans('Category ID is required'));
            }

            $properties = Property::where('category_id', $categoryId)
                ->where(['request_status' => 'approved', 'status' => 1])
                ->select('id', 'title', 'address', 'city', 'state')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'title' => $property->title,
                        'address' => $property->address.', '.$property->city.', '.$property->state,
                    ];
                });

            ResponseService::successResponse(trans('Properties loaded successfully'), $properties);
        } catch (Exception $e) {
            ResponseService::errorResponse(trans('Error loading properties'));
        }
    }

    public function edit($id)
    {
        if (! has_permissions('update', 'ad-banners')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $adBanner = AdBanner::with('property:id,title,address,category_id')->findOrFail($id);
        $categories = Category::where('status', 1)->get();

        return view('ad-banners.edit', compact('adBanner', 'categories'));
    }

    public function update(Request $request, $id)
    {
        if (! has_permissions('update', 'ad-banners')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            // Check if user wants to change duration
            $changeDuration = $request->has('change_duration') && $request->change_duration == '1';
            // Validation rules
            $validationRules = [
                'page' => 'required|in:homepage,property_listing,property_detail',
                'platform' => 'required|in:app,web',
                'placement' => 'required|in:below_categories,above_all_properties,above_facilities,above_similar_properties,below_slider,above_footer,sidebar_below_filters,below_breadcrumb,sidebar_below_mortgage_loan_calculator,above_breadcrumb',
                'banner_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:10240',
                'ad_type' => 'required|in:external_link,property,banner_only',
                'external_link_url' => 'nullable|url|max:255',
                'property_id' => 'nullable|integer|exists:propertys,id',
            ];

            // Add duration validation only if user wants to change it
            if ($changeDuration) {
                $validationRules['duration'] = 'required|integer|min:1';
            }

            $validator = Validator::make($request->all(), $validationRules,
                [
                    'page.required' => trans('The page field is required.'),
                    'page.in' => trans('The page field must be a valid page.'),
                    'platform.required' => trans('The platform field is required.'),
                    'platform.in' => trans('The platform field must be a valid platform.'),
                    'placement.required' => trans('The placement field is required.'),
                    'placement.in' => trans('The placement field must be a valid placement.'),
                    'banner_image.image' => trans('The banner image field must be an image.'),
                    'banner_image.mimes' => trans('The banner image field must be a valid image format.'),
                    'ad_type.required' => trans('The ad type field is required.'),
                    'ad_type.in' => trans('The ad type field must be a valid ad type.'),
                    'external_link_url.url' => trans('The external link url field must be a valid url.'),
                    'external_link_url.max' => trans('The external link url field must be less than 255 characters.'),
                    'property_id.integer' => trans('The property id field must be an integer.'),
                    'property_id.exists' => trans('The property id field must be an existing property.'),
                    'duration.required' => trans('The duration field is required.'),
                    'duration.integer' => trans('The duration field must be an integer.'),
                    'duration.min' => trans('The duration field must be at least 1.'),
                ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adBanner = AdBanner::findOrFail($id);

            $updateData = [
                'page' => $request->page,
                'platform' => $request->platform,
                'placement' => $request->placement,
                'type' => $request->ad_type,
                'external_link_url' => $request->ad_type == 'external_link' && ! empty($request->external_link_url) ? $request->external_link_url : null,
                'property_id' => $request->property_id ?? null,
            ];

            // Handle image update
            if ($request->hasFile('banner_image')) {
                $path = config('global.ADBANNER_IMAGE_PATH');
                $file = $adBanner->getRawOriginal('image');
                $image = FileService::compressAndReplace($request->file('banner_image'), $path, $file);
                $updateData['image'] = $image;
            }

            // Handle duration update only if user wants to change it
            if ($changeDuration) {
                $updateData['duration_days'] = (int) $request->duration;
                // When duration is updated, start date will be considered as today
                $updateData['starts_at'] = Carbon::today()->setTime(0, 0, 0);
                $updateData['ends_at'] = Carbon::now()->addDays($request->duration)->setTime(0, 0, 0);
            }

            $adBanner->update($updateData);

            ResponseService::successResponse(trans('Advertisement banner updated successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse(trans('Error updating advertisement banner'), $e->getMessage());
        }
    }

    public function destroy($id)
    {
        if (! has_permissions('delete', 'ad-banners')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        try {
            $adBanner = AdBanner::findOrFail($id);
            if ($adBanner->image) {
                unlink_image($adBanner->image);
            }
            $adBanner->delete();

            ResponseService::successResponse(trans('Advertisement banner deleted successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse(trans('Error deleting advertisement banner'), $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            if (! has_permissions('update', 'ad-banners')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:ad_banners,id',
                'status' => 'required|in:0,1',
            ],
                [
                    'id.required' => trans('The ID field is required.'),
                    'id.exists' => trans('The ID field must be an existing ID.'),
                    'status.required' => trans('The status field is required.'),
                    'status.in' => trans('The status field must be a valid status.'),
                ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $adBanner = AdBanner::findOrFail($request->id);
            $adBanner->update(['is_active' => $request->status]);
            ResponseService::successResponse(trans('Advertisement banner status updated successfully'));
        } catch (Exception $e) {
            ResponseService::errorResponse(trans('Error updating advertisement banner status'), $e->getMessage());
        }
    }
}
