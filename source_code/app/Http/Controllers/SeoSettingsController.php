<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Models\SeoSettings;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SeoSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
 public function index()
{
    if (!has_permissions('read', 'seo_settings')) {
        return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
    }

    // Static pages (translated)
    $pages = [
        __('homepage'),
        __('all-categories'),
        __('about-us'),
        __('articles'),
        __('chat'),
        __('contact-us'),
        __('featured-properties'),
        __('properties-on-map'),
        __('most-viewed-properties'),
        __('most-favorite-properties'),
        __('privacy-policy'),
        __('all-properties'),
        __('properties-nearby-city'),
        __('search'),
        __('subscription-plan'),
        __('terms-and-condition'),
        __('profile'),
        __('user-register'),
        __('all-agents'),
        __('agent-details'),
        __('faqs'),
    ];

    // Custom pages (optimized with eager loading)
    $customPages = CustomPage::with('translations')
        ->where('status', 1)
        ->get()
        ->map(function ($page) {
            return $page->translated_title;
        })
        ->toArray();

    // Merge both
    $pages = array_merge($pages, $customPages);

    $seo_pages = SeoSettings::pluck('page');

    return view('seo_settings.index', compact('seo_pages', 'pages'));
}
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                ResponseService::validationError(__('This is not allowed in the Demo Version'));
            }
            if (! has_permissions('create', 'seo_settings')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            } else {
                $validator = Validator::make($request->all(), [
                    'meta_title' => 'required',
                    'meta_description' => 'required',
                    'keywords' => 'required',
                    'image' => 'required|image|mimes:jpg,png,jpeg,svg|max:2048',
                    'schema_markup' => 'required',
                ]);
                if ($validator->fails()) {
                    ResponseService::validationError($validator->errors()->first());
                }

                $seo_setting = new SeoSettings;
                $seo_setting->page = $request->page;
                $seo_setting->title = $request->meta_title;
                $seo_setting->description = $request->meta_description;
                $seo_setting->keywords = $request->keywords;
                $seo_setting->schema_markup = $request->schema_markup;

                if ($request->hasFile('image')) {
                    $seo_setting->image = FileService::compressAndUpload($request->file('image'), config('global.SEO_IMG_PATH'));
                } else {
                    $seo_setting->image = '';
                }

                $seo_setting->save();
                ResponseService::successResponse('Data Created Successfully');
            }
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error Creating Seo Settings', 'Error Creating Seo Settings', false);
            ResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'seo_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $sql = SeoSettings::orderBy($sort, $order);

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('page', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%");
        }
        $total = $sql->count();

        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;

        $operate = '';
        $tempRow['type'] = '';
        foreach ($res as $row) {
            $tempRow = $row->toArray();

            $operate = '';
            if (has_permissions('update', 'seo_settings')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, $row->id);
            }
            if (has_permissions('delete', 'seo_settings')) {
                $operate .= BootstrapTableService::deleteButton(route('seo_settings.destroy', $row->id), $row->id);
            }

            $tempRow['operate'] = $operate;

            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                ResponseService::validationError(__('This is not allowed in the Demo Version'));
            }
            if (! has_permissions('update', 'seo_settings')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            } else {
                $validator = Validator::make($request->all(), [
                    'edit_meta_title' => 'required',
                    'edit_meta_description' => 'required',
                    'edit_keywords' => 'required',
                    'edit_schema_markup' => 'required',
                ]);
                if ($validator->fails()) {
                    ResponseService::validationError($validator->errors()->first());
                }

                $seo_setting = SeoSettings::find($request->edit_id);
                $seo_setting->page = $request->edit_page;
                $seo_setting->title = $request->edit_meta_title;
                $seo_setting->description = $request->edit_meta_description;
                $seo_setting->keywords = $request->edit_keywords;
                $seo_setting->schema_markup = $request->edit_schema_markup;

                if ($request->hasFile('edit_image')) {
                    $rawImage = $seo_setting->getRawOriginal('image');
                    $seo_setting->image = FileService::compressAndReplace($request->file('edit_image'), config('global.SEO_IMG_PATH'), $rawImage);
                }

                $seo_setting->save();
                ResponseService::successResponse('Data Updated Successfully');
            }
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error Updating Seo Settings', 'Error Updating Seo Settings', false);
            ResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
            }
            if (! has_permissions('delete', 'seo_settings')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            $seo_setting = SeoSettings::find($id);
            FileService::delete(config('global.SEO_IMG_PATH'), $seo_setting->getRawOriginal('image'));
            $seo_setting->delete();
            ResponseService::successRedirectResponse('Data Deleted Successfully');
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error Deleting Seo Settings', 'Error Deleting Seo Settings', false);
        }
    }
}
