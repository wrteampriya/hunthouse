<?php

namespace App\Http\Controllers;

use App\Models\HomepageSection;
use App\Models\Projects;
use App\Models\Property;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HomepageSectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            if (! has_permissions('read', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $sectionTypes = HelperService::getHomepageSectionTypes();
            $languages = HelperService::getActiveLanguages(null, true);

            return view('homepage-sections.index', compact('sectionTypes', 'languages'));
        } catch (Exception $e) {
            ResponseService::logErrorRedirectResponse($e);
        }
    }

    /**
     * Display homepage sections order management.
     */
    public function changeOrder()
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            $sectionTypes = HelperService::getHomepageSectionTypes();
            $sections = HomepageSection::orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();

            return view('homepage-sections.change-order', compact('sectionTypes', 'sections'));
        } catch (Exception $e) {
            dd($e);
            ResponseService::logErrorRedirectResponse($e);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            if (! has_permissions('create', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $sectionTypes = HelperService::getHomepageSectionTypes();
            $languages = HelperService::getActiveLanguages(null, true);

            return view('homepage-sections.create', compact('sectionTypes', 'languages'));
        } catch (Exception $e) {
            ResponseService::logErrorRedirectResponse($e);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $section = HomepageSection::with([
                'translations',
                'properties:id,title,title_image,status,request_status,expiry_date',
                'projects:id,title,image,status,request_status,expiry_date',
            ])->findOrFail($id);
            $section->properties->each(function ($property) {
                $property->status_badge = $this->listingStatusBadge($property);
            });
            $section->projects->each(function ($project) {
                $project->status_badge = $this->listingStatusBadge($project);
            });
            $sectionTypes = HelperService::getHomepageSectionTypes();
            $languages = HelperService::getActiveLanguages(null, true);

            return view('homepage-sections.edit', compact('section', 'sectionTypes', 'languages'));
        } catch (Exception $e) {
            ResponseService::logErrorRedirectResponse($e);
        }
    }

    /**
     * A curated pick can go stale after being selected (expire, get
     * deactivated, or drop out of "approved") — this surfaces that state as
     * a badge in the edit picker instead of the admin only finding out
     * because the listing quietly stopped showing on the frontend.
     */
    private function listingStatusBadge($listing): string
    {
        $isExpired = ! empty($listing->expiry_date) && $listing->expiry_date < now()->startOfDay();

        if ($listing->status == 1 && $listing->request_status === 'approved' && ! $isExpired) {
            return '<span class="badge bg-success">'.trans('Active').'</span>';
        }
        if ($isExpired) {
            return '<span class="badge bg-danger">'.trans('Expired').'</span>';
        }
        if ($listing->request_status === 'pending') {
            return '<span class="badge bg-warning text-dark">'.trans('Pending Approval').'</span>';
        }
        if ($listing->request_status === 'rejected') {
            return '<span class="badge bg-danger">'.trans('Rejected').'</span>';
        }
        if ($listing->request_status === 'draft') {
            return '<span class="badge bg-secondary">'.trans('Draft').'</span>';
        }

        return '<span class="badge bg-danger">'.trans('Inactive').'</span>';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if (! has_permissions('create', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'section_type' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            if ($curatedError = $this->validateCuratedSelection($request)) {
                ResponseService::validationError($curatedError);
            }
            DB::beginTransaction();
            // Check if section already exists
            $section = HomepageSection::where('section_type', $request->section_type)->first();
            if ($section) {
                ResponseService::errorResponse(trans('Section already exists'));
            }
            // Create new section
            $data = $validator->validated();
            $data['app_title'] = $request->app_title;
            $homepageSection = HomepageSection::create($data);

            $this->syncCuratedPicks($homepageSection, $request);

            // Add Translations (web title + app title)
            $translationData = [];
            if (isset($request->translations) && ! empty($request->translations)) {
                foreach ($request->translations as $translation) {
                    $translationData[] = [
                        'translatable_id' => $homepageSection->id,
                        'translatable_type' => 'App\Models\HomepageSection',
                        'key' => 'title',
                        'value' => $translation['value'],
                        'language_id' => $translation['language_id'],
                    ];
                }
            }
            if (isset($request->app_translations) && ! empty($request->app_translations)) {
                foreach ($request->app_translations as $translation) {
                    $translationData[] = [
                        'translatable_id' => $homepageSection->id,
                        'translatable_type' => 'App\Models\HomepageSection',
                        'key' => 'app_title',
                        'value' => $translation['value'],
                        'language_id' => $translation['language_id'],
                    ];
                }
            }
            if (! empty($translationData)) {
                HelperService::storeTranslations($translationData);
            }
            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            if (! has_permissions('read', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $offset = request('offset', 0);
            $limit = request('limit', 10);
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $search = request('search');

            $sql = HomepageSection::with(['translations', 'properties:id,title,title_image', 'projects:id,title,image'])->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('title', 'LIKE', "%$search%")
                        ->orWhere('section_type', 'LIKE', "%$search%");
                });
            });

            $total = $sql->count();

            $sql->orderBy($sort, $order)->skip($offset)->take($limit);
            $res = $sql->get();
            $bulkData = [];
            $bulkData['total'] = $total;
            $rows = [];
            $no = 1;
            foreach ($res as $row) {
                $row = (object) $row;
                $operate = '';
                if (has_permissions('update', 'homepage-sections')) {
                    $operate .= BootstrapTableService::editButton(route('homepage-sections.edit', $row->id), false);
                }
                if (has_permissions('delete', 'homepage-sections')) {
                    $operate .= BootstrapTableService::deleteAjaxButton(route('homepage-sections.destroy', $row->id));
                }

                $tempRow = $row->toArray();
                if (has_permissions('update', 'homepage-sections')) {
                    $tempRow['edit_status_url'] = route('homepage-sections.status-update');
                } else {
                    $tempRow['edit_status_url'] = null;
                }
                $tempRow['operate'] = $operate;
                $rows[] = $tempRow;
            }

            $bulkData['rows'] = $rows;

            return response()->json($bulkData);
        } catch (Exception $e) {
            
            ResponseService::logErrorResponse($e);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'section_type' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            if ($curatedError = $this->validateCuratedSelection($request)) {
                ResponseService::validationError($curatedError);
            }

            DB::beginTransaction();
            // Check if section already exists
            $section = HomepageSection::where('section_type', $request->section_type)->where('id', '!=', $id)->first();
            if ($section) {
                ResponseService::errorResponse(trans('Section already exists'));
            }
            $data = $request->except('_token', '_method', 'edit_id', 'translations', 'app_translations', 'property_ids', 'project_ids');
            $section = HomepageSection::find($id);
            $section->update($data);

            $this->syncCuratedPicks($section, $request);

            // Add Translations (web title + app title)
            $translationData = [];
            if (isset($request->translations) && ! empty($request->translations)) {
                foreach ($request->translations as $translation) {
                    $translationData[] = [
                        'id' => $translation['id'] ?? null,
                        'translatable_id' => $id,
                        'translatable_type' => 'App\Models\HomepageSection',
                        'key' => 'title',
                        'value' => $translation['value'],
                        'language_id' => $translation['language_id'],
                    ];
                }
            }
            if (isset($request->app_translations) && ! empty($request->app_translations)) {
                foreach ($request->app_translations as $translation) {
                    $translationData[] = [
                        'id' => $translation['id'] ?? null,
                        'translatable_id' => $id,
                        'translatable_type' => 'App\Models\HomepageSection',
                        'key' => 'app_title',
                        'value' => $translation['value'],
                        'language_id' => $translation['language_id'],
                    ];
                }
            }
            if (! empty($translationData)) {
                HelperService::storeTranslations($translationData);
            }
            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            if (! has_permissions('delete', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            HomepageSection::where('id', $id)->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e);
        }
    }

    /**
     * Update the status of the specified resource in storage.
     */
    public function statusUpdate(Request $request)
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }
            $section = HomepageSection::find($request->id);
            $section->is_active = $request->status == 1 ? 1 : 0;
            $section->save();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e);
        }
    }

    /**
     * Update the order of homepage sections.
     */
    public function updateOrder(Request $request)
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'sections' => 'required|array',
                'sections.*.id' => 'required|exists:homepage_sections,id',
                'sections.*.sort_order' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();

            // Update each section's sort_order
            foreach ($request->sections as $section) {
                HomepageSection::where('id', $section['id'])
                    ->update(['sort_order' => $section['sort_order']]);
            }

            DB::commit();

            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
        }
    }

    public function updateToggles(Request $request)
    {
        try {
            if (! has_permissions('update', 'homepage-sections')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            $settings = ['slider_section', 'search_section', 'all_properties_section'];
            foreach ($settings as $setting) {
                $value = $request->input($setting, 0);
                \App\Models\Setting::updateOrCreate(['type' => $setting], ['data' => $value]);
            }

            return redirect()->back()->with('success', trans('Data Updated Successfully'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', trans('Something Went Wrong'));
        }
    }

    /**
     * A curated section with zero picks is meaningless (nothing to show),
     * and previously fell through to a generic "Error Occurred" from
     * whatever downstream failure that produced — validate it explicitly
     * up front instead, with a message the admin can actually act on.
     */
    private function validateCuratedSelection(Request $request): ?string
    {
        $curatedPropertiesType = config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROPERTIES_SECTION.TYPE');
        $curatedProjectsType = config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROJECTS_SECTION.TYPE');

        if ($request->section_type === $curatedPropertiesType && empty(array_filter((array) $request->input('property_ids', [])))) {
            return trans('Please select at least one property for this section.');
        }
        if ($request->section_type === $curatedProjectsType && empty(array_filter((array) $request->input('project_ids', [])))) {
            return trans('Please select at least one project for this section.');
        }

        return null;
    }

    /**
     * Persist the admin's hand-picked properties/projects for a curated
     * section. Only touches the pivot relevant to the section's own type —
     * a section can never hold both kinds of picks.
     */
    private function syncCuratedPicks(HomepageSection $section, Request $request): void
    {
        $curatedPropertiesType = config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROPERTIES_SECTION.TYPE');
        $curatedProjectsType = config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROJECTS_SECTION.TYPE');

        if ($section->section_type === $curatedPropertiesType) {
            $ids = array_filter((array) $request->input('property_ids', []));
            $sync = [];
            foreach (array_values($ids) as $order => $id) {
                $sync[$id] = ['sort_order' => $order];
            }
            $section->properties()->sync($sync);
        } elseif ($section->section_type === $curatedProjectsType) {
            $ids = array_filter((array) $request->input('project_ids', []));
            $sync = [];
            foreach (array_values($ids) as $order => $id) {
                $sync[$id] = ['sort_order' => $order];
            }
            $section->projects()->sync($sync);
        }
    }

    /**
     * Select2 AJAX search — active properties, for the curated-section picker.
     */
    public function selectProperties(Request $request)
    {
        if (! has_permissions('read', 'homepage-sections')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $term = $request->input('q');
        $page = (int) $request->input('page', 1);
        $perPage = 20;

        $query = Property::onlyActive()->when($term, function ($q) use ($term) {
            $q->where(function ($q2) use ($term) {
                $q2->where('title', 'like', "%$term%")->orWhere('id', 'like', "%$term%");
            });
        });

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')->skip(($page - 1) * $perPage)->take($perPage)->get(['id', 'title', 'title_image']);

        return response()->json([
            'results' => $items->map(fn ($p) => [
                'id' => $p->id,
                'text' => $p->title.' (#'.$p->id.')',
                'image' => $p->title_image
            ]),
            'pagination' => ['more' => ($page * $perPage) < $total],
        ]);
    }

    /**
     * Select2 AJAX search — active projects, for the curated-section picker.
     */
    public function selectProjects(Request $request)
    {
        if (! has_permissions('read', 'homepage-sections')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $term = $request->input('q');
        $page = (int) $request->input('page', 1);
        $perPage = 20;

        $query = Projects::onlyActive()->when($term, function ($q) use ($term) {
            $q->where(function ($q2) use ($term) {
                $q2->where('title', 'like', "%$term%")->orWhere('id', 'like', "%$term%");
            });
        });

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')->skip(($page - 1) * $perPage)->take($perPage)->get(['id', 'title', 'image']);

        return response()->json([
            'results' => $items->map(fn ($p) => [
                'id' => $p->id,
                'text' => $p->title.' (#'.$p->id.')',
                'image' => $p->image
            ]),
            'pagination' => ['more' => ($page * $perPage) < $total],
        ]);
    }
}
