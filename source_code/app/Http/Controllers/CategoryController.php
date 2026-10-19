<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\parameter;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Slider;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('categories.index');
    }

    public function create()
    {
        if (! has_permissions('create', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $parameters = parameter::all();
        $languages = HelperService::getActiveLanguages();

        return view('categories.create', compact('parameters', 'languages'));
    }

    public function edit($id)
    {
        if (! has_permissions('update', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::with('translations')->findOrFail($id);
        $parameters = parameter::all();
        $languages = HelperService::getActiveLanguages();

        return view('categories.edit', compact('category', 'parameters', 'languages'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        if (! has_permissions('create', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            try {
                DB::beginTransaction();
                $validator = Validator::make($request->all(), [
                    'category' => 'required',
                    'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:categories,slug_id',
                    'image' => 'required|image|mimes:svg|max:2048',
                    'parameter_type' => 'required',
                ], [
                    'category.required' => trans('The category field is required.'),
                    'slug.required' => trans('The slug field is required.'),
                    'image.required' => trans('The image field is required.'),
                    'image.image' => trans('The uploaded file must be an image.'),
                    'image.mimes' => trans('The image must be a SVG file.'),
                    'image.max' => trans('The image size should not exceed 2MB.'),
                    'parameter_type.required' => trans('The parameter field is required.'),
                ]);
                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $saveCategories = new Category;

                if ($request->hasFile('image')) {
                    $path = config('global.CATEGORY_IMG_PATH');
                    $requestFile = $request->file('image');
                    $saveCategories->image = FileService::compressAndUpload($requestFile, $path);
                } else {
                    $saveCategories->image = '';
                }

                $saveCategories->category = ($request->category) ? $request->category : '';
                $saveCategories->parameter_types = ($request->parameter_type) ? implode(',', $request->parameter_type) : '';
                $saveCategories->slug_id = $request->slug ?? generateUniqueSlug($request->title, 3);
                $saveCategories->meta_title = $request->meta_title;
                $saveCategories->meta_description = $request->meta_description;
                $saveCategories->meta_keywords = $request->meta_keywords;
                $saveCategories->status = 1;
                $saveCategories->save();

                // Add Translations
                if (isset($request->translations) && ! empty($request->translations)) {
                    $translationData = [];
                    foreach ($request->translations as $translation) {
                        $translationData[] = [
                            'translatable_id' => $saveCategories->id,
                            'translatable_type' => 'App\Models\Category',
                            'key' => 'category',
                            'value' => $translation['value'],
                            'language_id' => $translation['language_id'],
                        ];
                    }
                    if (! empty($translationData)) {
                        HelperService::storeTranslations($translationData);
                    }
                }
                DB::commit();
                ResponseService::successRedirectResponse(trans('Data Created Successfully'));
            } catch (Exception $e) {
                DB::rollBack();
                ResponseService::logErrorResponse($e, 'Category Creation Error', 'Something Went Wrong');
            }
        }
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

            if (! has_permissions('update', 'categories')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            } else {
                DB::beginTransaction();

                // Support both old modal field names and new edit page field names
                $categoryName = $request->edit_category ?? $request->category;
                $categoryId = $request->edit_id ?? $request->id;
                $updateSeq = $request->update_seq;

                $validator = Validator::make($request->all(), [
                    'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:categories,slug_id,'.$categoryId.',id',
                    'update_seq' => 'required',
                ], [
                    'update_seq.required' => trans('The parameter field is required.'),
                ]);

                if (! $categoryName) {
                    return redirect()->back()->withErrors(['category' => trans('The category field is required.')])->withInput();
                }

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }
                $Category = Category::find($categoryId);
                $destinationPath = public_path('images').config('global.CATEGORY_IMG_PATH');
                if (! is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // image upload - support both field names
                $imageFile = $request->file('edit_image') ?? $request->file('image');
                if ($imageFile) {
                    $path = config('global.CATEGORY_IMG_PATH');
                    $rawImage = $Category->getRawOriginal('image');
                    $Category->image = FileService::compressAndReplace($imageFile, $path, $rawImage);
                }

                $Category->category = $categoryName;
                $Category->slug_id = $request->slug ?? generateUniqueSlug($categoryName, 3, null, $categoryId);
                $Category->meta_title = $request->edit_meta_title ?? $request->meta_title;
                $Category->meta_description = $request->edit_meta_description ?? $request->meta_description;
                $Category->meta_keywords = $request->edit_keywords ?? $request->meta_keywords;

                $Category->sequence = ($request->sequence) ? $request->sequence : 0;
                $Category->parameter_types = $updateSeq;

                $Category->update();

                // Synchronize Parameters with Properties - Remove parameters that are no longer in category
                $currentParameterIds = ! empty($updateSeq) ? explode(',', $updateSeq) : [];

                foreach ($Category->properties as $property) {
                    foreach ($property->assignParameter as $propertyParameter) {
                        // If this parameter is not in the current category's parameter types, detach it
                        if (! in_array($propertyParameter->parameter_id, $currentParameterIds)) {
                            $property->parameters()->detach($propertyParameter->parameter_id);
                        }
                    }
                }

                if (isset($request->translations) && ! empty($request->translations)) {
                    $translationData = [];
                    foreach ($request->translations as $translation) {
                        $translationData[] = [
                            'id' => $translation['id'] ?? null,
                            'translatable_id' => $Category->id,
                            'translatable_type' => 'App\Models\Category',
                            'key' => 'category',
                            'value' => $translation['value'],
                            'language_id' => $translation['language_id'],
                        ];
                    }
                    if (! empty($translationData)) {
                        HelperService::storeTranslations($translationData);
                    }
                }

                DB::commit();

                return redirect()->route('categories.index')->with('success', trans('Data Updated Successfully'));
            }
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'Category Update Error', 'Something Went Wrong');
        }
    }

    public function categoryList(Request $request)
    {
        if (! has_permissions('read', 'categories')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');

        $sql = Category::orderBy($sort, $order)->with('translations');
        // dd($sql->toArray());
        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('category', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }
        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;

        $operate = '';
        $tempRow['type'] = '';

        foreach ($res as $row) {

            $tempRow = $row->toArray();
            $tempRow['edit_status_url'] = 'categorystatus';
            $parameter_type_arr = explode(',', $row->parameter_types);
            $arr = [];
            $rawArr = [];

            if ($row->parameter_types) {
                foreach ($parameter_type_arr as $p) {
                    $par = parameter::find($p);
                    if ($par) {
                        $arr = array_merge($arr, [$par->name]);
                        $rawArr = array_merge($rawArr, [$par->getRawOriginal('name')]);
                    }
                }
            }
            $tempRow['type'] = implode(',', $arr);

            $ids = isset($row->parameter_types) ? $row->parameter_types : '';

            // Check if category is being used
            $isUsed = $this->isCategoryUsed($row->id);
            $tempRow['is_used'] = $isUsed;

            $operate = null;
            if (has_permissions('update', 'categories')) {
                $operate = BootstrapTableService::editButton(route('categories.edit', $row->id), false);
                $operate .= BootstrapTableService::button('bi bi-arrow-down-up', route('categories.reorder-facilities', $row->id), ['btn', 'icon', 'btn-sm', 'rounded-pill', 'text-info', 'bg-light-info', 'reorder-button', 'border', 'border-info'], ['title' => trans('Reorder Facilities')]);
            }

            // Add delete button only if category is not used and user has delete permission
            if (! $isUsed && has_permissions('delete', 'categories')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('categories.destroy', $row->id));
            }

            $tempRow['operate'] = $operate;

            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function updateCategory(Request $request)
    {
        if (! has_permissions('update', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            Category::where('id', $request->id)->update(['status' => $request->status]);
            ResponseService::successResponse($request->status ? trans('Category Activated Successfully') : trans('Category Deactivated Successfully'));
        }
    }

    public function generateAndCheckSlug(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'category' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Generate the slug or throw exception
        try {
            $category = $request->category;
            $id = $request->has('id') && ! empty($request->id) ? $request->id : null;
            if ($id) {
                $slug = generateUniqueSlug($category, 3, null, $id);
            } else {
                $slug = generateUniqueSlug($category, 3);
            }
            ResponseService::successResponse('', $slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Category Slug Generation Error', 'Something Went Wrong');
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
        if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
            ResponseService::validationError(__('This is not allowed in the Demo Version'));
        }

        if (! has_permissions('delete', 'categories')) {
            ResponseService::validationError(PERMISSION_ERROR_MSG);
        }

        try {
            DB::beginTransaction();

            $category = Category::find($id);
            if (! $category) {
                ResponseService::validationError('Category not found');
            }

            // Double-check if category is being used (security validation)
            $usageCount = 0;
            $usedIn = [];

            // Check in Properties
            $propertyCount = Property::where('category_id', $id)->count();
            if ($propertyCount > 0) {
                $usageCount += $propertyCount;
                $usedIn[] = "Properties ($propertyCount)";
            }

            // Check in Projects
            $projectCount = Projects::where('category_id', $id)->count();
            if ($projectCount > 0) {
                $usageCount += $projectCount;
                $usedIn[] = "Projects ($projectCount)";
            }

            // Check in Articles
            $articleCount = Article::where('category_id', $id)->count();
            if ($articleCount > 0) {
                $usageCount += $articleCount;
                $usedIn[] = "Articles ($articleCount)";
            }

            // Check in Sliders
            $sliderCount = Slider::where('category_id', $id)->count();
            if ($sliderCount > 0) {
                $usageCount += $sliderCount;
                $usedIn[] = "Sliders ($sliderCount)";
            }

            if ($usageCount > 0) {
                $message = 'Cannot delete this category. It is being used in: '.implode(', ', $usedIn);
                ResponseService::validationError($message);
            }

            // Safe to delete
            if ($category->delete()) {
                // Delete the category image if exists
                if ($category->getRawOriginal('image') != '') {
                    $path = config('global.CATEGORY_IMG_PATH');
                    $rawImage = $category->getRawOriginal('image');
                    FileService::delete($path, $rawImage);
                }

            }

            DB::commit();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'Category Delete Error', 'Something Went Wrong');
        }
    }

    public function reorderFacilities($id)
    {
        if (! has_permissions('update', 'categories')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::findOrFail($id);

        // Load parameters in the exact order stored in parameter_types
        $parameterIds = array_filter(array_map('intval', explode(',', $category->parameter_types)));

        if (! empty($parameterIds)) {
            $parameterMap = parameter::whereIn('id', $parameterIds)->get()->keyBy('id');
            // Preserve the order defined in parameter_types
            $parameters = collect($parameterIds)
                ->map(fn ($pid) => $parameterMap->get($pid))
                ->filter()
                ->values();
        } else {
            $parameters = collect();
        }

        return view('categories.reorder-facilities', compact('category', 'parameters'));
    }

    public function saveFacilityOrder(Request $request)
    {
        if (! has_permissions('update', 'categories')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:parameters,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            // Sort by sort_order and extract IDs
            $orderedIds = collect($request->fields)
                ->sortBy('sort_order')
                ->pluck('id')
                ->implode(',');

            Category::where('id', $request->category_id)
                ->update(['parameter_types' => $orderedIds]);

            DB::commit();
            ResponseService::successResponse(trans('Parameter Order Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    /**
     * Check if category is being used in any related tables
     *
     * @param  int  $categoryId
     * @return bool
     */
    private function isCategoryUsed($categoryId)
    {
        // Check in Properties
        $propertyCount = Property::where('category_id', $categoryId)->count();
        if ($propertyCount > 0) {
            return true;
        }

        // Check in Projects
        $projectCount = Projects::where('category_id', $categoryId)->count();
        if ($projectCount > 0) {
            return true;
        }

        // Check in Articles
        $articleCount = Article::where('category_id', $categoryId)->count();
        if ($articleCount > 0) {
            return true;
        }

        // Check in Sliders
        $sliderCount = Slider::where('category_id', $categoryId)->count();
        if ($sliderCount > 0) {
            return true;
        }

        return false;
    }
}
