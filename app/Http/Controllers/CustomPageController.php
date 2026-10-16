<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomPageController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'custom_page')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('custom_pages.index');
    }

    public function create()
    {
        if (! has_permissions('create', 'custom_page')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();

        return view('custom_pages.create', compact('languages'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'custom_page')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        // dd($request->all());
        $request->validate([
            'title' => 'required',
            'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:custom_pages,slug_id',
            'content' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $page = new CustomPage;
            $page->title = $request->title;
            $page->slug_id = $request->slug ?? generateUniqueSlug($request->title, 7);
            $page->content = $request->content;
            $page->status = $request->status ?? 1;

            $page->save();

            $translations = $request->input('translations', []);
            if (! empty($translations) && is_array($translations)) {
                $translationData = [];
                foreach ($translations as $translation) {
                    if (! is_array($translation)) {
                        continue;
                    }
                    foreach ($translation as $key => $value) {
                        if (! is_array($value)) {
                            continue;
                        }
                        $translationData[] = [
                            'translatable_id' => $page->id,
                            'translatable_type' => 'App\Models\CustomPage',
                            'language_id' => $value['language_id'] ?? null,
                            'key' => $key,
                            'value' => $value['value'] ?? '',
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            DB::commit();

            return back()->with('success', trans('Data Created Successfully'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', trans('Something Went Wrong'));
        }
    }

    public function show(Request $request)
    {
        if (! has_permissions('read', 'custom_page')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = CustomPage::when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")
                    ->orWhere('title', 'LIKE', "%$search%")
                    ->orWhere('slug_id', 'LIKE', "%$search%");
            });
        });

        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = ['total' => $total];
        $rows = [];

        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('update', 'custom_page')) {
                $operate .= BootstrapTableService::editButton(route('custom-page.edit', $row->id), false);
            }
            if (has_permissions('delete', 'custom_page')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('custom-page.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['edit_status'] = $row->status;
            $tempRow['edit_status_url'] = 'update-custom-page-status';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function edit($id)
    {
        if (! has_permissions('update', 'custom_page')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $page = CustomPage::with('translations')->findOrFail($id);
        $languages = HelperService::getActiveLanguages();

        return view('custom_pages.edit', compact('page', 'languages'));
    }

    public function update(Request $request, $id)
    {
        if (! has_permissions('update', 'custom_page')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $request->validate([
            'title' => 'required',
            'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:custom_pages,slug_id,'.$id.',id',
            'content' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $page = CustomPage::findOrFail($id);
            $page->title = $request->title;
            $page->slug_id = $request->slug ?? generateUniqueSlug($request->title, 7, null, $id);
            $page->content = $request->content;
            $page->status = $request->has('status') ? $request->status : $page->status;

            $page->save();

            $translations = $request->input('translations', []);
            if (! empty($translations) && is_array($translations)) {
                $translationData = [];
                foreach ($translations as $translation) {
                    if (! is_array($translation)) {
                        continue;
                    }
                    foreach ($translation as $key => $value) {
                        if (! is_array($value)) {
                            continue;
                        }
                        $translationData[] = [
                            'id' => $value['id'] ?? null,
                            'translatable_id' => $page->id,
                            'translatable_type' => 'App\Models\CustomPage',
                            'language_id' => $value['language_id'] ?? null,
                            'key' => $key,
                            'value' => $value['value'] ?? '',
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            DB::commit();
            ResponseService::successRedirectResponse(trans('Data Updated Successfully'));
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::errorResponse(trans('Something Went Wrong'));
        }
    }

    public function destroy($id)
    {
        try {
            if (! has_permissions('delete', 'custom_page')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            $page = CustomPage::findOrFail($id);
            if ($page->getRawOriginal('icon')) {
                FileService::delete(config('global.CUSTOM_PAGE_ICON_PATH'), $page->getRawOriginal('icon'));
            }
            $page->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Custom Page Delete Error', 'Something Went Wrong');
        }
    }

    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'custom_page')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        CustomPage::where('id', $request->id)->update(['status' => $request->status]);
        ResponseService::successResponse(trans('Status Updated Successfully'));
    }

    public function generateAndCheckSlug(Request $request)
    {
        $validator = Validator::make($request->all(), ['title' => 'required']);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $title = $request->title;
            $id = $request->has('id') && ! empty($request->id) ? $request->id : null;
            $slug = $id
                ? generateUniqueSlug($title, 7, null, $id)
                : generateUniqueSlug($title, 7);
            ResponseService::successResponse('', $slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Custom Page Slug Generation Error', 'Something Went Wrong');
        }
    }

    public function renderPage($slug)
    {
        $page = CustomPage::where('slug_id', $slug)->where('status', 1)->firstOrFail();

        return view('custom_pages.show_page', compact('page'));
    }
}
