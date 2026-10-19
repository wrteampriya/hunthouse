<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Notifications;
use App\Models\ProjectDocuments;
use App\Models\ProjectPlans;
use App\Models\Projects;
use App\Models\RejectReason;
use App\Models\Setting;
use App\Models\Usertokens;
use App\Rules\VideoUrlRule;
use App\Services\AuditLogService;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::all();

        return view('project.index', compact('category'));
    }

    public function create()
    {
        if (! has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::where('status', '1')->get();
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        // withEnglish=true: the default language gets its own tab (Title/Description)
        // alongside the other languages, matching the property form's pattern.
        $languages = HelperService::getActiveLanguages(null, true);
        $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
        $countries = Country::with(['defaultLanguage', 'currency'])->where('status', 1)->get();
        return view('project.create', compact('category', 'currency_symbol', 'languages', 'geminiEnabled', 'countries'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        // Save as Draft — nothing is required while still a draft. Mirrors
        // post_project()'s is_draft handling (ProjectApiController).
        $isDraft = $request->boolean('is_draft');

        // dd($request->all());
        $validator = Validator::make($request->all(), [
            // title/description are validated below against the listing's
            // required language(s) instead of being unconditionally required here.
            'title' => 'nullable',
            'description' => 'nullable',
            'image' => $isDraft ? 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp' : 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
            'meta_title' => 'nullable|max:255',
            'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description' => 'nullable|max:255',
            'meta_keywords' => 'nullable|max:255',
            'schema_markup' => 'nullable|string',
            'category_id' => $isDraft ? 'nullable' : 'required',
            'city' => 'nullable',
            'state' => $isDraft ? 'nullable' : 'required',
            'country' => $isDraft ? 'nullable' : 'required',
            // 'video_link'        => ['nullable', 'url', function ($attribute, $value, $fail) {
            //     // Regular expression to validate YouTube URLs
            //     $youtubePattern = '/^(https?\:\/\/)?(www\.youtube\.com|youtu\.be)\/.+$/';

            //     if (!preg_match($youtubePattern, $value)) {
            //         return $fail("The Video Link must be a valid YouTube URL.");
            //     }

            'video_type' => 'nullable|in:0,1,2',
            'video_link' => [
                'nullable',
                $isDraft ? 'nullable' : 'required_if:video_type,1,2',
                new VideoUrlRule($request->video_type),
            ],
            'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480'.($isDraft ? '' : '|required_if:video_type,0'),
            'is_draft' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $project = new Projects;
            // FK to categories — now nullable (see 1_8_0 migration) so a
            // draft can leave it blank without violating the constraint.
            // Empty string must become null too, not just missing/PHP-null:
            // '' would coerce to 0, which isn't a valid category id.
            $project->category_id = $request->category_id ?: null;
            // NOT NULL column with no sensible placeholder — default to ''
            // when left blank (Save as Draft).
            $project->location = $request->address ?? '';
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->keywords ?? null;
            $project->schema_markup = $request->schema_markup ?? null;
            $project->added_by = null;
            $project->is_admin_listing = true;
            $project->request_status = $isDraft ? 'draft' : 'approved';
            $project->status = $isDraft ? 0 : 1;
            // NOT NULL columns with no sensible placeholder — default to ''
            // when left blank (Save as Draft). An explicit null here (as
            // opposed to just never touching the attribute) always fails
            // the NOT NULL constraint, regardless of sql_mode.
            $project->country = $request->country ?? '';
            $project->state = $request->state ?? '';
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;

            $requiredLanguageId = Country::requiredLanguageIdFor($request->country_id);
            $filledLanguageIds = HelperService::filledTranslationLanguageIds($request);
            // Save as Draft: the selected country's required language content
            // is allowed to be missing too, same as every other field.
            $mappingError = $isDraft ? null : HelperService::validateRequiredLanguageContent($request->country_id, $filledLanguageIds);
            if ($mappingError) {
                DB::rollBack();
                ResponseService::errorResponse($mappingError);
            }

            // Base title/description columns are read directly all over the app
            // (listing cards, search, SEO fallback) — backfill them from whichever
            // language was actually filled when the base fields are left blank.
            $baseContent = HelperService::resolveBaseContent($request, $requiredLanguageId);
            $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $baseContent['title'];
            // NOT NULL columns — default to '' when left blank (Save as Draft).
            $project->title = $baseContent['title'] ?? '';
            $project->slug_id = generateUniqueSlug($slugData, 4);
            $project->description = $baseContent['description'] ?? '';

            // country_id is an FK to countries — a stale/invalid value (from a
            // half-filled map picker on a draft) must not hard-fail the save,
            // so fall back to null (already nullable) if it doesn't resolve.
            $project->country_id = $request->country_id && Country::where('id', $request->country_id)->exists()
                ? $request->country_id
                : null;
            // $project->video_link = $request->video_link;
            $project->video_type = $request->video_type;
            $project->role_context = 'agent';
            if ($request->video_type == Projects::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                $path = config('global.PROJECT_VIDEO_PATH');
                $project->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
            } else {
                $project->video_link = $request->video_link;
            }

            // NOT NULL column with no sensible placeholder — default to ''
            // when left blank (Save as Draft).
            $project->type = $request->project_type ?? '';
            $project->is_premium = $request->is_premium ? 1 : 0;

            if ($request->hasFile('image')) {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                $project->image = FileService::compressAndUpload($request->file('image'), $path, true);
            }
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROJECT_SEO_IMG_PATH');
                $project->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $galleryImages = [];
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('gallery_images') as $file) {
                    $image = FileService::compressAndUpload($file, $path, true);
                    $galleryImages[] = [
                        'project_id' => $project->id,
                        'name' => $image,
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($galleryImages)) {
                    ProjectDocuments::insert($galleryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $projectDocuments = [];
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('documents') as $file) {
                    $document = FileService::compressAndUpload($file, $path);
                    $projectDocuments[] = [
                        'project_id' => $project->id,
                        'name' => $document,
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($projectDocuments)) {
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $projectPlan = [];
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object) $planArray;
                    $document = FileService::compressAndUpload($plan->floor_image, $path, true);
                    $projectPlan[] = [
                        'title' => $plan->title,
                        'project_id' => $project->id,
                        'document' => $document,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($projectPlan)) {
                    ProjectPlans::insert($projectPlan);
                }
            }

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'translatable_id' => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            // END ::Add Translations

            DB::commit();
            AuditLogService::log('project', $project->id, $project->title, 'created', "Project '{$project->title}' created by admin");
            ResponseService::successResponse('Data Created Successfully');
        } catch (Exception $e) {
            return ResponseService::errorResponse($e->getMessage());
            DB::rollback();
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');

        $sql = Projects::with('category')->with('gallary_images')->with('documents')->with('plans')->with('customer')->with('customer.agent_profile:customer_id,agent_name')->orderBy($sort, $order);

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('title', 'LIKE', "%$search%")
                    ->orWhere('location', 'LIKE', "%$search%")
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q->where('category', 'LIKE', "%$search%");
                    })
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%$search%")
                            ->orWhere('email', 'LIKE', "%$search%");
                    });
            });
        }

        if (isset($_GET['status']) && $_GET['status'] != '') {
            $status = $_GET['status'];
            $sql = $sql->where('status', $status);
        }

        if (isset($_GET['category']) && $_GET['category'] != '') {
            $category_id = $_GET['category'];
            $sql = $sql->where('category_id', $category_id);
        }

        if (isset($_GET['owner']) && $_GET['owner'] != '') {
            $owner = $_GET['owner'];
            if ($owner == 0) {
                $sql = $sql->where('is_admin_listing', 1);
            } else {
                $sql = $sql->whereNot('is_admin_listing', 0);
            }
        }

        // Filter by role_context (All/Admin/User/Agent tabs)
        if (isset($_GET['role_context_filter']) && $_GET['role_context_filter'] !== '') {
            $addedAsFilter = $_GET['role_context_filter'];
            if ($addedAsFilter === 'admin') {
                $sql = $sql->where('is_admin_listing', 1);
            } elseif ($addedAsFilter === 'user') {
                $sql = $sql->where(function ($q) {
                    $q->where('role_context', 'user')->orWhereNull('role_context');
                })->where('is_admin_listing', 0);
            } elseif ($addedAsFilter === 'agent') {
                $sql = $sql->where('role_context', 'agent')->where('is_admin_listing', 0);
            }
        }

        // Filter by verification status
        if (isset($_GET['verification_status']) && $_GET['verification_status'] !== '') {
            $sql = $sql->where('request_status', $_GET['verification_status']);
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
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        $adminName = \App\Models\User::where('type', 0)->value('name') ?? trans('Admin');

        // dd($res);

        foreach ($res as $row) {
            $documentsButtonCustomClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill', 'documents-btn'];
            $documentsButtonCustomAttributes = ['id' => $row->id, 'title' => trans('Documents'), 'data-toggle' => 'modal', 'data-bs-target' => '#documentsModal', 'data-bs-toggle' => 'modal'];
            $documentAction = BootstrapTableService::button('bi bi-eye-fill', '', $documentsButtonCustomClasses, $documentsButtonCustomAttributes);

            $operate = '';
            if ($row->is_admin_listing == false) {
                if ($row->getRawOriginal('request_status') === 'draft' || $row->request_status === 'draft') {
                    $operate .= '<span class="badge bg-secondary rounded-pill me-1">'.trans('Draft').'</span>';
                } elseif (has_permissions('update', 'project')) {
                    $requestStatusButtonCustomClasses = ['btn', 'icon', 'text-warning', 'btn-light-warning', 'btn-sm', 'rounded-pill', 'request-status-btn', 'border', 'border-warning'];
                    $requestStatusButtonCustomAttributes = ['id' => $row->id, 'title' => trans('Change Status'), 'data-toggle' => 'modal', 'data-bs-target' => '#changeRequestStatusModal', 'data-bs-toggle' => 'modal'];
                    $operate .= BootstrapTableService::button('fa fa-exclamation-circle', '', $requestStatusButtonCustomClasses, $requestStatusButtonCustomAttributes);
                }
            } elseif ($row->getRawOriginal('request_status') === 'draft' || $row->request_status === 'draft') {
                $operate .= '<span class="badge bg-secondary rounded-pill me-1">'.trans('Draft').'</span>';
            }
            // Edit stays hidden for an agent/user's own in-progress draft, but
            // admin's own draft listings (is_admin_listing) need to stay
            // editable so admin can resume and finish them.
            if (has_permissions('update', 'project') && ($row->request_status !== 'draft' || $row->is_admin_listing == true)) {
                $operate .= BootstrapTableService::editButton(route('project.edit', $row->id), false);
            }
            if (has_permissions('delete', 'project')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('project.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            if ($row->is_admin_listing == true) {
                $tempRow['owner_name'] = $adminName;
            } elseif ($row->role_context === 'agent') {
                $tempRow['owner_name'] = $row->customer?->agent_profile?->agent_name ?? $row->customer?->name ?? '-';
            } else {
                $tempRow['owner_name'] = $row->customer?->name ?? '-';
            }
            $tempRow['added_as_tag'] = $row->is_admin_listing == true ? 'admin' : ($row->role_context ?? 'user');
            if ($row->is_admin_listing == true && $row->request_status == 'approved') {
                $tempRow['edit_status'] = $row->status;
                $tempRow['edit_status_url'] = 'updateProjectStatus';
            } else {
                $tempRow['edit_status'] = null;
                $tempRow['edit_status_url'] = null;
            }

            $tempRow['price'] = $currency_symbol.''.$row->price.'/'.(! empty($row->rentduration) ? $row->rentduration : 'Month');
            $tempRow['expiry_date'] = $row->expiry_date ? Carbon::parse($row->expiry_date)->toIso8601String() : null;
            $tempRow['raw_document_action'] = $documentAction;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function edit($id)
    {
        if (! has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $project = Projects::where('id', $id)->with([
            'plans:id,title,project_id,document',
            'gallary_images' => function ($query) {
                $query->select('id', 'project_id', 'name', 'type');
            },
            'documents' => function ($query) {
                $query->select('id', 'project_id', 'name', 'type');
            },
            'translations',
        ])->first();
        $category = Category::where('status', '1')->get();
        // withEnglish=true: the default language gets its own tab (Title/Description)
        // alongside the other languages, matching the property form's pattern.
        $languages = HelperService::getActiveLanguages(null, true);
        $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
        $countries = Country::with(['defaultLanguage', 'currency'])->where('status', 1)->get();
        return view('project.edit', compact('project', 'category', 'languages', 'geminiEnabled', 'countries'));
    }

    public function update($id, Request $request)
    {
        if (! has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        // Save as Draft (admin-owned listings only, see below).
        $isDraft = $request->boolean('is_draft');

        $validator = Validator::make($request->all(), [
            // title/description are validated below against the listing's
            // required language(s) instead of being unconditionally required here.
            'title' => 'nullable',
            'description' => 'nullable',
            'image' => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
            'category_id' => $isDraft ? 'nullable' : 'required',
            'meta_title' => 'nullable|max:255',
            'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description' => 'nullable|max:255',
            'meta_keywords' => 'nullable|max:255',
            'schema_markup' => 'nullable|string',
            'city' => 'nullable',
            'state' => $isDraft ? 'nullable' : 'required',
            'country' => $isDraft ? 'nullable' : 'required',
            'video_type' => 'nullable|in:0,1,2',
            'video_link' => [
                'nullable',
                $isDraft ? 'nullable' : 'required_if:video_type,1,2',
                new VideoUrlRule($request->video_type),
            ],
            'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480', // Nullable on update
            'is_draft' => 'nullable|in:0,1',
        ], [
            'custom_video.file' => __('The custom video field should be a valid mp4, webm, or ogg file.'),
            'custom_video.max' => __('File size exceeds the :max limit. Please upload a smaller video.'),
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $project = Projects::find($id);
            $watermarkAgentId = (! $project->is_admin_listing && ! empty($project->added_by) && Customer::where('id', $project->added_by)->where('is_agent', true)->exists())
                ? $project->added_by
                : null;
            // FK to categories — now nullable (see 1_8_0 migration) so a
            // draft can leave it blank without violating the constraint.
            // Empty string must become null too, not just missing/PHP-null:
            // '' would coerce to 0, which isn't a valid category id.
            $project->category_id = $request->category_id ?: null;
            $project->location = $request->address ?? '';
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->keywords ?? null;
            $project->schema_markup = $request->schema_markup ?? null;
            // NOT NULL columns with no sensible placeholder — default to ''
            // when left blank (Save as Draft). An explicit null here (as
            // opposed to just never touching the attribute) always fails
            // the NOT NULL constraint, regardless of sql_mode.
            $project->country = $request->country ?? '';
            $project->state = $request->state ?? '';
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;

            $requiredLanguageId = Country::requiredLanguageIdFor($request->country_id);
            $filledLanguageIds = HelperService::filledTranslationLanguageIds($request);
            // Save as Draft: the selected country's required language content
            // is allowed to be missing too, same as every other field.
            $mappingError = $isDraft ? null : HelperService::validateRequiredLanguageContent($request->country_id, $filledLanguageIds);
            if ($mappingError) {
                DB::rollBack();
                ResponseService::errorResponse($mappingError);
            }

            // Base title/description columns are read directly all over the app
            // (listing cards, search, SEO fallback) — backfill them from whichever
            // language was actually filled when the base fields are left blank.
            $baseContent = HelperService::resolveBaseContent($request, $requiredLanguageId);
            $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $baseContent['title'];
            // NOT NULL columns — fall back to whatever the draft already had
            // saved (so re-saving without retouching title/description
            // doesn't wipe it), then to '' if this is genuinely still blank.
            $project->title = $baseContent['title'] ?? $project->getRawOriginal('title') ?? '';
            $project->slug_id = generateUniqueSlug($slugData, 4, null, $id);
            $project->description = $baseContent['description'] ?? $project->getRawOriginal('description') ?? '';

            // country_id is an FK to countries — a stale/invalid value (from a
            // half-filled map picker on a draft) must not hard-fail the save,
            // so fall back to null (already nullable) if it doesn't resolve.
            $project->country_id = $request->country_id && Country::where('id', $request->country_id)->exists()
                ? $request->country_id
                : null;
            // $project->video_link = $request->video_link;

            // Video Logic
            if ($request->has('remove_video') && $request->remove_video == 1) {
                if ($project->video_type == Projects::VIDEO_CUSTOM && ! empty($project->getRawOriginal('video_link'))) {
                    FileService::delete(config('global.PROJECT_VIDEO_PATH'), $project->getRawOriginal('video_link'));
                }
                $project->video_type = null;
                $project->video_link = null;
            } else {
                $oldVideoType = $project->video_type;
                $project->video_type = $request->video_type;

                if ($request->video_type == Projects::VIDEO_CUSTOM) {
                    if ($request->hasFile('custom_video')) {
                        $path = config('global.PROJECT_VIDEO_PATH');
                        if ($oldVideoType == Projects::VIDEO_CUSTOM) {
                            $project->video_link = FileService::compressAndReplace($request->file('custom_video'), $path, $project->getRawOriginal('video_link'));
                        } else {
                            $project->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
                        }
                    } elseif ($oldVideoType != Projects::VIDEO_CUSTOM && empty($project->video_link)) {
                        ResponseService::validationError('Custom video file is required.');

                        return;
                    }
                } else {
                    $project->video_link = $request->video_link;
                }
            }
            $project->type = $request->project_type ?? '';
            $project->is_premium = $request->is_premium ? 1 : 0;
            if ($project->is_admin_listing == false) {
                if (isset($request->edit_reason) && ! empty($request->edit_reason)) {
                    $project->edit_reason = $request->edit_reason;
                } else {
                    ResponseService::validationError('Edit Reason is required');
                }

                // Auto-approve logic on edit (only for customer-uploaded projects)
                $autoApprove = HelperService::getAutoApproveStatus($project->added_by, 'user');
                if ($autoApprove) {
                    $project->request_status = 'approved';
                    $project->status         = 1;
                } else {
                    $project->request_status = 'pending';
                    $project->status         = 0;
                }
            } else {
                // Admin-owned listing — Save as Draft keeps/returns it to
                // draft; a normal Save finalizes it (also covers turning an
                // existing draft into a real listing).
                $project->request_status = $isDraft ? 'draft' : 'approved';
                $project->status = $isDraft ? 0 : 1;
            }
            if ($request->hasFile('image')) {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                $rawImage = $project->getRawOriginal('image');
                $project->image = FileService::compressAndReplace($request->file('image'), $path, $rawImage, true, $watermarkAgentId);
            }
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROJECT_SEO_IMG_PATH');
                $rawImage = $project->getRawOriginal('meta_image');
                $project->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $rawImage);
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $galleryImages = [];
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('gallery_images') as $file) {
                    $image = FileService::compressAndUpload($file, $path, true, $watermarkAgentId);
                    $galleryImages[] = [
                        'project_id' => $project->id,
                        'name' => $image,
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($galleryImages)) {
                    ProjectDocuments::insert($galleryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $path = config('global.PROJECT_DOCUMENT_PATH');
                $projectDocuments = [];
                foreach ($request->file('documents') as $file) {
                    $document = FileService::compressAndUpload($file, $path);
                    $projectDocuments[] = [
                        'project_id' => $project->id,
                        'name' => $document,
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($projectDocuments)) {
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object) $planArray;
                    if (! empty($plan->floor_image)) {
                        $document = FileService::compressAndUpload($plan->floor_image, $path, true, $watermarkAgentId);
                        ProjectPlans::updateOrCreate(['id' => $plan->id], ['title' => $plan->title, 'project_id' => $project->id, 'document' => $document]);
                    } else {
                        ProjectPlans::updateOrCreate(['id' => $plan->id], ['title' => $plan->title, 'project_id' => $project->id]);
                    }
                }
            }

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'id' => $value['id'] ?? null,
                            'translatable_id' => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            // END ::Add Translations

            DB::commit();

            // Notify project owner when admin edits their project
            if ($project->is_admin_listing == false) {
                try {
                    $project->load('customer:id,name,isActive,notification');
                    $customer = $project->customer;
                    if ($customer && $customer->isActive == 1 && $customer->notification == 1) {
                        $tokens = Usertokens::where('customer_id', $customer->id)->pluck('fcm_id')->toArray();
                        if (! empty($tokens)) {
                            $fcmMsg = [
                                'title'        => 'Project updated :- :project_name',
                                'message'      => trans('Your project has been edited by administrator'),
                                'type'         => 'project_inquiry',
                                'body'         => trans('Your project has been edited by administrator'),
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                'sound'        => 'default',
                                'id'           => (string) $project->id,
                                'role_context' => $project->role_context ?? 'user',
                                'replace'      => ['project_name' => $project->title],
                            ];
                            send_push_notification($tokens, $fcmMsg);
                        }
                    }
                    Notifications::create([
                        'title'        => 'Project Updated :- ' . $project->title,
                        'message'      => trans('Your project has been edited by administrator'),
                        'image'        => '',
                        'type'         => '1',
                        'send_type'    => '0',
                        'customers_id' => $project->customer->id,
                        'role_context' => $project->role_context ?? 'user',
                    ]);
                } catch (Exception $e) {
                    Log::error('Admin edit project notification failed: ' . $e->getMessage());
                }
            }

            AuditLogService::log('project', $project->id, $project->title, 'updated', "Project '{$project->title}' updated by admin");
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function destroy($id)
    {
        if (! has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            DB::beginTransaction();
            $project = Projects::find($id);

            $projectTitle = $project->title;
            DB::commit();
            if ($project->delete()) {
                AuditLogService::log('project', $id, $projectTitle, 'deleted', "Project '{$projectTitle}' deleted by admin");
                ResponseService::successResponse('Data Deleted Successfully');
            } else {
                ResponseService::errorResponse('Something Went Wrong');
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Projects::where('id', $request->id)->update(['status' => $request->status]);
            $project = Projects::with('customer')->find($request->id);

            if ($project->customer) {
                // Send mail for project status
                try {
                    $projectData = Projects::where('id', $request->id)->select('id', 'title', 'status', 'added_by')->with('customer:id,name,email')->where('is_admin_listing', false)->firstOrFail();

                    if (! empty($projectData->customer->email)) {
                        // Get Data of email type
                        $emailTypeData = HelperService::getEmailTemplatesTypes('project_status');

                        // Email Template
                        $projectStatusTemplateData = system_setting($emailTypeData['type']);
                        $appName = env('APP_NAME') ?? 'eBroker';
                        $variables = [
                            'app_name' => $appName,
                            'user_name' => $projectData->customer->name,
                            'project_name' => $projectData->title,
                            'status' => $request->status == 1 ? 'Enabled' : 'Disabled',
                            'email' => $projectData->customer->email,
                        ];
                        if (empty($projectStatusTemplateData)) {
                            $projectStatusTemplateData = 'Your Project :- '.$variables['projectName'].' is '.$variables['status'];
                        }
                        $projectStatusTemplate = HelperService::replaceEmailVariables($projectStatusTemplateData, $variables);

                        $data = [
                            'email_template' => $projectStatusTemplate,
                            'email' => $projectData->customer->email,
                            'title' => $emailTypeData['title'],
                        ];
                        HelperService::sendMail($data);
                    }

                } catch (Exception $e) {
                    Log::error('Something Went Wrong in Project Status Update Mail Sending');
                }
            }

            /** Send Notification */
            $fcm_ids = [];
            if ($project->customer->isActive == 1 && $project->customer->notification == 1) {
                $user_token = Usertokens::where('customer_id', $project->customer->id)->pluck('fcm_id')->toArray();
            }

            $fcm_ids[] = $user_token;

            $msg = '';
            if (! empty($fcm_ids)) {
                $msg = $project->status == 1 ? 'Activate now by Administrator ' : 'Deactivated now by Administrator ';
                $registrationIDs = $fcm_ids[0];

                $fcmMsg = [
                    'title' => $project->title.'Project Updated',
                    'message' => 'Your Project Post '.$msg,
                    'type' => 'project_inquiry',
                    'body' => 'Your Project Post '.$msg,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'id' => (string) $project->id,
                ];
                send_push_notification($registrationIDs, $fcmMsg);
            }
            // END ::  Send Notification To Customer

            Notifications::create([
                'title' => $project->title.'Project Updated',
                'message' => 'Your Project Post '.$msg,
                'image' => '',
                'type' => '1',
                'send_type' => '0',
                'customers_id' => $project->customer->id,
                'projects_id' => $project->id,
                'role_context' => $project->role_context ?? 'user',
            ]);

            $statusLabel = $request->status ? 'Activated' : 'Deactivated';
            AuditLogService::log('project', $request->id, $project->title ?? null, 'status_changed', "Project status changed to {$statusLabel} by admin");
            // $response['error'] = false;
            ResponseService::successResponse($request->status ? 'Project Activated Successfully' : 'Project Deactivated Successfully');
        }
    }

    public function generateAndCheckSlug(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Generate the slug or throw exception
        try {
            $title = $request->title;
            $id = $request->has('id') && ! empty($request->id) ? $request->id : null;
            if ($id) {
                $slug = generateUniqueSlug($title, 4, null, $id);
            } else {
                $slug = generateUniqueSlug($title, 4);
            }
            ResponseService::successResponse('', $slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Project Slug Generation Error', 'Something Went Wrong');
        }
    }

    public function removeGalleryImage(Request $request)
    {

        if (! has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getImage = ProjectDocuments::where('id', $id)->first();

            $image = $getImage->getRawOriginal('name');
            $path = config('global.PROJECT_DOCUMENT_PATH');
            if (ProjectDocuments::where('id', $id)->delete()) {
                FileService::delete($path, $image);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            return response()->json($response);
        }
    }

    public function removeDocument(Request $request)
    {

        if (! has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getDocument = ProjectDocuments::where('id', $id)->first();

            $file = $getDocument->getRawOriginal('name');
            $path = config('global.PROJECT_DOCUMENT_PATH');
            if (ProjectDocuments::where('id', $id)->delete()) {
                FileService::delete($path, $file);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            return response()->json($response);
        }
    }

    public function removeFloorPlan($id)
    {
        if (! has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            try {
                $getDocument = ProjectPlans::where('id', $id)->first();

                $file = $getDocument->getRawOriginal('document');
                $path = config('global.PROJECT_DOCUMENT_PATH');
                if (ProjectPlans::where('id', $id)->delete()) {
                    FileService::delete($path, $file);
                    ResponseService::successResponse('Data Deleted Sucessfully');
                } else {
                    ResponseService::errorResponse('Something Went Wrong');
                }
            } catch (Exception $e) {
                ResponseService::errorResponse('Something Went Wrong');
            }
        }
    }

    public function updateRequestStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:request_status,rejected|max:300',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            if (! has_permissions('update', 'project')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            } else {
                if ($request->request_status == 'rejected') {
                    RejectReason::create([
                        'project_id' => $request->id,
                        'reason' => $request->reject_reason,
                    ]);
                    $status = 0;
                } else {
                    $status = 1;
                }
                if ($request->request_status == 'approved') {
                    $projectData = Projects::find($request->id);
                    if ($projectData->request_status != 'approved') {
                        if ($projectData->expiry_date && $projectData->updated_at) {
                            $durationDays = Carbon::parse($projectData->updated_at)->startOfDay()->diffInDays(Carbon::parse($projectData->expiry_date)->startOfDay());
                            $expirationDate = Carbon::now()->addDays($durationDays > 0 ? $durationDays : 30);
                        } else {
                            $expirationDate = HelperService::calculateExpirationDate($projectData->added_by);
                        }
                        Projects::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status, 'expiry_date' => $expirationDate]);
                    } else {
                        Projects::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status]);
                    }
                } else {
                    Projects::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status]);
                }
                DB::commit();
                $actionLabel = $request->request_status === 'approved' ? 'approved' : 'rejected';
                $desc = $request->request_status === 'rejected'
                    ? "Project request rejected. Reason: {$request->reject_reason}"
                    : 'Project request approved by admin';
                AuditLogService::log('project', $request->id, null, $actionLabel, $desc);

                // Send mail for project status
                try {
                    $projectData = Projects::where('id', $request->id)->select('id', 'title', 'request_status', 'added_by')->with('customer:id,name,email')->firstOrFail();
                    if (! empty($projectData->customer->email)) {
                        // Get Data of email type
                        $emailTypeData = HelperService::getEmailTemplatesTypes('project_status');

                        // Email Template
                        $projectStatusTemplateData = system_setting($emailTypeData['type']);
                        $appName = env('APP_NAME') ?? 'eBroker';
                        $variables = [
                            'app_name' => $appName,
                            'user_name' => $projectData->customer->name,
                            'project_name' => $projectData->title,
                            'status' => $request->request_status,
                            'reject_reason' => $request->request_status == 'rejected' ? $request->reject_reason : null,
                            'email' => $projectData->customer->email,
                        ];
                        if (empty($projectStatusTemplateData)) {
                            $projectStatusTemplateData = 'Project Status have been changed';
                        }
                        $projectStatusTemplate = HelperService::replaceEmailVariables($projectStatusTemplateData, $variables);

                        $data = [
                            'email_template' => $projectStatusTemplate,
                            'email' => $projectData->customer->email,
                            'title' => $emailTypeData['title'],
                        ];
                        HelperService::sendMail($data);
                    }
                } catch (Exception $e) {
                    Log::error('Something Went Wrong in project Status Update Mail Sending');
                }

                // Send Notification
                $project = Projects::with('customer:id,name,isActive,notification')->select('id', 'title', 'request_status', 'added_by', 'role_context')->find($request->id);
                $fcm_ids = [];
                if ($project->customer->isActive == 1 && $project->customer->notification == 1) {
                    $user_token = Usertokens::where('customer_id', $project->customer->id)->pluck('fcm_id')->toArray();
                }

                $fcm_ids[] = $user_token ?? [];

                $msg = '';
                if (! empty($fcm_ids)) {
                    $title = 'Project updated :- :project_name';
                    $msg = $project->request_status == 'approved' ? 'Your project post approved by administrator' : 'Your project post rejected by administrator';
                    $registrationIDs = $fcm_ids[0];

                    $fcmMsg = [
                        'title' => $title,
                        'message' => $msg,
                        'type' => 'project_inquiry',
                        'body' => $msg,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string) $project->id,
                        'role_context' => $project->role_context ?? 'user',
                        'replace' => [
                            'project_name' => $project->title,
                        ],

                    ];
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                // END ::  Send Notification To Customer

                $notificationMsg = $project->request_status == 'approved' ? 'Your project post approved by administrator' : 'Your project post rejected by administrator';
                Notifications::create([
                    'title' => 'Project Updated :- '.$project->title,
                    'message' => $notificationMsg,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $project->customer->id,
                    'projects_id' => $project->id,
                    'role_context' => $project->role_context ?? 'user',
                ]);
                ResponseService::successResponse('Data Updated Successfully');
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'Update Request Status in Project', 'Something Went Wrong');
        }
    }
}
