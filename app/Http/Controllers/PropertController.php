<?php

namespace App\Http\Controllers;

use App\Models\AssignedOutdoorFacilities;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\CityImage;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Notifications;
use App\Models\OutdoorFacilities;
use App\Models\parameter;
use App\Models\PropertiesDocument;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\RejectReason;
use App\Models\Setting;
use App\Models\UserPackage;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PropertController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $customerID = $_GET['customer'] ?? null;
            $category = Category::all();

            return view('property.index', compact('category', 'customerID'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! has_permissions('create', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $category = Category::where('status', '1')->get();
            // translated_name/translated_option_value resolve against the
            // admin's own current UI language (same as the rest of the admin
            // panel), so the Facilities/Near By Places section shows names in
            // whatever language translations were added for.
            $parameters = parameter::with('translations')->get()->map(function ($p) {
                $p->translated_name = $p->translated_name;
                $p->translated_option_value = $p->translated_option_value;

                return $p;
            });
            $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
            $facility = OutdoorFacilities::with('translations')->get()->map(function ($f) {
                $f->translated_name = $f->translated_name;

                return $f;
            });
            $distanceValueDB = system_setting('distance_option');
            $distanceValue = isset($distanceValueDB) && ! empty($distanceValueDB) ? $distanceValueDB : 'km';
            // withEnglish=true: the default language now gets its own tab
            // (Title/Description) alongside the other languages, instead of
            // being handled separately via the plain title/description fields.
            $languages = HelperService::getActiveLanguages(null, true);
            $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
            $countries = Country::with(['defaultLanguage', 'currency'])->where('status', 1)->get();
            $currencies = Currency::where('status', 1)->get();

            $sellerId = request('seller_id') ?? request('customer_id') ?? request('seller') ?? request('customer');
            $selectedSeller = $sellerId ? Customer::find($sellerId) : null;

            return view('property.create', compact('category', 'parameters', 'currency_symbol', 'facility', 'distanceValue', 'languages', 'geminiEnabled', 'countries', 'currencies', 'selectedSeller'));
        }
    }

    /**
     * Search sellers (customers) for property creation.
     */
    public function searchSellers(Request $request)
    {
        if (! has_permissions('create', 'property') && ! has_permissions('read', 'property')) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $term = $request->input('q', '');
        $page = (int) $request->input('page', 1);
        $perPage = 20;

        $query = Customer::where('isActive', 1);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('mobile', 'like', "%{$term}%")
                    ->orWhere('id', $term);
            });
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'name', 'email', 'mobile', 'address', 'is_agent']);

        $results = $items->map(function ($c) {
            $name = $c->name ?? '';
            $contact = $c->mobile ?: ($c->email ?? '');
            $type = $c->is_agent ? trans('Agent') : trans('User');
            $text = trim($name.($contact ? " ({$contact})" : '')." [{$type}] (#{$c->id})");

            return [
                'id' => $c->id,
                'text' => $text,
                'name' => $c->name,
                'role' => $type,
                'contact' => $contact,
                'address' => $c->address ?? '',
            ];
        })->toArray();

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => ($page * $perPage) < $total],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $arr = [];
        if (! has_permissions('create', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            // Saving as a draft: nothing is required — the listing can be
            // completed later. Only format/type constraints still apply to
            // whatever fields are actually sent. Mirrors post_property()'s
            // is_draft handling (PropertyApiController) for consistency.
            $isDraft = $request->boolean('is_draft');

            $request->validate([
                'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:propertys,slug_id',
                'gallery_images.*' => 'image|mimes:jpg,png,jpeg,webp|max:5120',
                'documents.*' => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
                'title_image' => $isDraft ? 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120' : 'required|image|mimes:jpg,png,jpeg,webp|max:5120',
                'meta_title' => 'nullable|max:255',
                'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
                'meta_description' => 'nullable|max:255',
                'meta_keywords' => 'nullable|max:255',
                'schema_markup' => 'nullable|string',
                'price' => $isDraft ? 'nullable|numeric|max:9223372036854775807' : 'required|numeric|min:1|max:9223372036854775807',
                'video_type' => 'nullable|required_with:video_link,custom_video|in:0,1,2',
                'video_link' => [
                    'nullable',
                    $isDraft ? 'nullable' : 'required_if:video_type,1,2',
                    new VideoUrlRule($request->video_type),
                ],
                'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480',
                'is_draft' => 'nullable|in:0,1',
                'seller_id' => 'nullable|exists:customers,id',
                'customer_id' => 'nullable|exists:customers,id',

            ], [], [
                'documents.*' => 'document :position',
            ]);

            try {
                DB::beginTransaction();

                $sellerId = $request->input('seller_id') ?: $request->input('customer_id');
                if ($request->input('property_owner_type') === 'admin') {
                    $sellerId = null;
                }
                $seller = ! empty($sellerId) ? Customer::find($sellerId) : null;

                $saveProperty = new Property;
                // NOT NULL columns with no sensible placeholder and no FK
                // (unlike projects.category_id) — default to '' when left
                // blank (Save as Draft).
                $saveProperty->category_id = $request->category ?: '';
                $saveProperty->address = $request->address ?? '';
                $saveProperty->client_address = $request->client_address ?? '';
                // NOT NULL column — default to 0 (Sell) when left blank (Save as Draft).
                $saveProperty->propery_type = $request->property_type ?? 0;
                // NOT NULL column — default to 0 when left blank (Save as Draft).
                $saveProperty->price = $request->price ?? 0;
                $saveProperty->request_status = $isDraft ? 'draft' : 'approved';
                $saveProperty->status = $isDraft ? 0 : 1;
                $saveProperty->city = (isset($request->city)) ? $request->city : '';
                $saveProperty->country = (isset($request->country)) ? $request->country : '';
                $saveProperty->state = (isset($request->state)) ? $request->state : '';
                $saveProperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
                $saveProperty->longitude = (isset($request->longitude)) ? $request->longitude : '';

                if ($seller) {
                    $saveProperty->added_by = $seller->id;
                    $saveProperty->post_type = 1;
                    $saveProperty->role_context = $seller->is_agent ? 'agent' : 'user';

                    $activePackage = UserPackage::where('user_id', $seller->id)->onlyActive()->first();
                    $saveProperty->package_id = $activePackage ? $activePackage->package_id : 0;
                    if (! $isDraft) {
                        $expirationDate = HelperService::calculateExpirationDate($seller->id);
                        $saveProperty->expiry_date = $expirationDate ?: Carbon::now()->addDays(30);
                    } else {
                        $saveProperty->expiry_date = null;
                    }
                } else {
                    $saveProperty->added_by = 0; // Admin Added
                    $saveProperty->post_type = 0;
                    $saveProperty->role_context = 'agent';
                    $saveProperty->package_id = 0;
                    $saveProperty->expiry_date = null;
                }

                $watermarkAgentId = ($seller && $seller->is_agent) ? $seller->id : null;

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
                // NOT NULL columns — default to '' when left blank (Save as Draft).
                $saveProperty->title = $baseContent['title'] ?? '';
                $saveProperty->slug_id = $request->slug ?? generateUniqueSlug($baseContent['title'] ?? '', 1);
                $saveProperty->description = $baseContent['description'] ?? '';

                $saveProperty->country_id = $request->country_id ?: null;
                $saveProperty->currency_id = $request->currency_id ?: null;
                // $project->video_link = $request->video_link;
                $saveProperty->video_type = $request->video_type;
                if ($request->video_type == 0 && $request->hasFile('custom_video')) {
                    $path = config('global.PROPERTY_VIDEO_PATH');
                    $saveProperty->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
                } else {
                    $saveProperty->video_link = $request->video_link;
                }
                $saveProperty->meta_title = isset($request->meta_title) ? $request->meta_title : $request->title;
                $saveProperty->meta_description = $request->meta_description;
                $saveProperty->meta_keywords = $request->keywords;
                $saveProperty->schema_markup = $request->schema_markup;
                $saveProperty->rentduration = $request->price_duration;
                $saveProperty->is_premium = $request->is_premium ?? 0;

                if ($request->hasFile('title_image')) {
                    $path = config('global.PROPERTY_TITLE_IMG_PATH');
                    $requestFile = $request->file('title_image');
                    $saveProperty->title_image = FileService::compressAndUpload($requestFile, $path, true, $watermarkAgentId);
                } else {
                    $saveProperty->title_image = '';
                }

                if ($request->hasFile('3d_image')) {
                    $path = config('global.3D_IMG_PATH');
                    $requestFile = $request->file('3d_image');
                    $saveProperty->three_d_image = FileService::compressAndUpload($requestFile, $path);
                } else {
                    $saveProperty->three_d_image = '';
                }

                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROPERTY_SEO_IMG_PATH');
                    $requestFile = $request->file('meta_image');
                    $saveProperty->meta_image = FileService::compressAndUpload($requestFile, $path);
                }

                $saveProperty->save();

                $facility = OutdoorFacilities::all();
                foreach ($facility as $key => $value) {
                    if ($request->has('facility'.$value->id) && $request->input('facility'.$value->id) != '') {
                        $facilities = new AssignedOutdoorFacilities;
                        $facilities->facility_id = $value->id;
                        $facilities->property_id = $saveProperty->id;
                        $facilities->distance = $request->input('facility'.$value->id);
                        $facilities->save();
                    }
                }
                $parameters = parameter::all();
                foreach ($parameters as $par) {
                    if ($request->has('par_'.$par->id)) {
                        $assign_parameter = new AssignParameters;
                        $assign_parameter->parameter_id = $par->id;
                        if (($request->hasFile('par_'.$par->id))) {
                            $allowedMimeTypes = [
                                'image/jpeg',
                                'image/png',
                                'image/jpg',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/plain',
                            ];
                            $mimeType = $request->file('par_'.$par->id)->getMimeType();
                            if (! in_array($mimeType, $allowedMimeTypes)) {
                                return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                            }
                            $path = config('global.PARAMETER_IMG_PATH');
                            $requestFile = $request->file('par_'.$par->id);
                            $assign_parameter->value = FileService::compressAndUpload($requestFile, $path);
                        } else {
                            $inputValue = $request->input('par_'.$par->id);
                            if (is_array($inputValue)) {
                                $encodedArray = array_map(function ($item) {
                                    return is_array($item) ? array_map('htmlspecialchars', $item) : htmlspecialchars($item ?? '', ENT_QUOTES, 'UTF-8');
                                }, $inputValue);
                                $assign_parameter->value = json_encode($encodedArray, JSON_FORCE_OBJECT);
                            } else {
                                $assign_parameter->value = (! empty($inputValue)) ? htmlspecialchars($inputValue, ENT_QUOTES, 'UTF-8') : null;
                            }
                        }
                        $assign_parameter->modal()->associate($saveProperty);
                        $assign_parameter->save();
                        $arr = $arr + [$par->id => $request->input('par_'.$par->id)];
                    }
                }

                // / START :: UPLOAD GALLERY IMAGE
                if ($request->hasfile('gallery_images')) {
                    $galleryImagesData = [];
                    foreach ($request->file('gallery_images') as $file) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH').$saveProperty->id.'/';
                        $requestFile = $file;
                        $imageName = FileService::compressAndUpload($requestFile, $path, true, $watermarkAgentId);
                        $galleryImagesData[] = [
                            'propertys_id' => $saveProperty->id,
                            'image' => $imageName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($galleryImagesData)) {
                        PropertyImages::insert($galleryImagesData);
                    }
                }
                // / END :: UPLOAD GALLERY IMAGE

                // / START :: UPLOAD DOCUMENT
                if ($request->hasFile('documents')) {
                    $documentsData = [];
                    foreach ($request->file('documents') as $file) {
                        $path = config('global.PROPERTY_DOCUMENT_PATH').$saveProperty->id.'/';
                        $requestFile = $file;
                        $documentName = FileService::compressAndUpload($requestFile, $path);
                        $documentsData[] = [
                            'property_id' => $saveProperty->id,
                            'name' => $documentName,
                            'type' => $file->extension(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($documentsData)) {
                        PropertiesDocument::insert($documentsData);
                    }
                }
                // / END :: UPLOAD DOCUMENT

                // START :: ADD CITY DATA
                if (isset($request->city) && ! empty($request->city)) {
                    CityImage::updateOrCreate(['city' => $request->city]);
                }
                // END :: ADD CITY DATA

                // START ::Add Translations
                if (isset($request->translations) && ! empty($request->translations)) {
                    $translationData = [];
                    foreach ($request->translations as $translation) {
                        foreach ($translation as $key => $value) {
                            $translationData[] = [
                                'translatable_id' => $saveProperty->id,
                                'translatable_type' => 'App\Models\Property',
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
                $ownerLabel = $seller ? "seller '{$seller->name}' (#{$seller->id})" : 'admin';
                AuditLogService::log('property', $saveProperty->id, $saveProperty->title, 'created', "Property '{$saveProperty->title}' created for {$ownerLabel} by admin");
                ResponseService::successResponse('Data Created Successfully');
            } catch (Exception $e) {
                DB::rollBack();
                ResponseService::logErrorResponse($e, 'Create Property Issue');
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        if (! has_permissions('update', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $category = Category::all()->where('status', '1')->mapWithKeys(function ($item, $key) {
                return [$item['id'] => $item['category']];
            });
            $category = Category::where('status', '1')->get();
            $list = Property::with(['assignParameter' => function ($q) {
                $q->with('parameter:id,name,type_of_parameter,type_values,is_required,image')->select('id', 'modal_type', 'modal_id', 'property_id', 'parameter_id', 'value');
            }, 'translations'])->where('id', $id)->get()->first();
            if (! $list) {
                return redirect()->back()->with('error', trans('Property not found'));
            }

            $categoryData = Category::find($list->category_id);
            if ($categoryData) {
                $categoryParameterTypeIds = explode(',', $categoryData['parameter_types']);
            } else {
                return redirect()->back()->with('error', trans('Category not found'));
            }

            // translated_name/translated_option_value resolve against the
            // admin's own current UI language (same as the rest of the admin
            // panel), so the Facilities/Near By Places section shows names in
            // whatever language translations were added for.
            $parameters = parameter::with('translations')->get()->map(function ($p) {
                $p->translated_name = $p->translated_name;
                $p->translated_option_value = $p->translated_option_value;

                return $p;
            });
            $edit_parameters = parameter::with(['assigned_parameter' => function ($q) use ($id) {
                $q->where('modal_id', $id)->select('id', 'modal_type', 'modal_id', 'property_id', 'parameter_id', 'value');
            }, 'translations'])->whereIn('id', $categoryParameterTypeIds)->get();

            // Sort the collection by the order of IDs in $categoryParameterTypeIds.
            $edit_parameters = $edit_parameters->sortBy(function ($parameter) use ($categoryParameterTypeIds) {
                return array_search($parameter->id, $categoryParameterTypeIds);
            });

            // Reset the keys on the sorted collection.
            $edit_parameters = $edit_parameters->values()->map(function ($p) {
                $p->translated_name = $p->translated_name;
                $p->translated_option_value = $p->translated_option_value;

                return $p;
            });

            $facility = OutdoorFacilities::with(['assign_facilities' => function ($q) use ($id) {
                $q->where('property_id', $id)->select('id', 'property_id', 'facility_id', 'distance');
            }, 'translations'])->get()->map(function ($f) {
                $f->translated_name = $f->translated_name;

                return $f;
            });

            $assignFacility = AssignedOutdoorFacilities::where('property_id', $id)->get();

            $arr = json_decode($list->carpet_area);
            $par_arr = [];
            $par_id = [];
            $type_arr = [];
            foreach ($list->assignParameter as $par) {
                if (! $par->parameter) {
                    continue;
                }
                $par_arr = $par_arr + [$par->parameter->name => $par->value];
                $par_id  = $par_id  + [$par->parameter->name => $par->value];
            }
            $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
            $distanceValueDB = system_setting('distance_option');
            $distanceValue = isset($distanceValueDB) && ! empty($distanceValueDB) ? $distanceValueDB : 'km';
            // withEnglish=true: the default language now gets its own tab
            // (Title/Description) alongside the other languages.
            $languages = HelperService::getActiveLanguages(null, true);
            $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
            $countries = Country::with(['defaultLanguage', 'currency'])->where('status', 1)->get();
            $currencies = Currency::where('status', 1)->get();
            return view('property.edit', compact('category', 'facility', 'assignFacility', 'edit_parameters', 'list', 'id', 'par_arr', 'parameters', 'par_id', 'currency_symbol', 'distanceValue', 'languages', 'geminiEnabled', 'countries', 'currencies'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! has_permissions('update', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            // Save as Draft (admin-owned listings only, see below) — nothing
            // is required while still a draft. Mirrors store()'s is_draft handling.
            $isDraft = $request->boolean('is_draft');

            $request->validate([
                'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:propertys,slug_id,'.$id.',id',
                'gallery_images.*' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
                'documents.*' => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
                'title_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
                'edit_meta_title' => 'nullable|max:255',
                'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
                'edit_meta_description' => 'nullable|max:255',
                'Keywords' => 'nullable|max:255',
                'schema_markup' => 'nullable|string',
                'price' => $isDraft ? 'nullable|numeric|max:9223372036854775807' : 'required|numeric|min:1|max:9223372036854775807',
                'edit_reason' => 'nullable|max:255',
                'video_type' => 'nullable|in:0,1,2',
                'video_link' => [
                    'nullable',
                    $isDraft ? 'nullable' : 'required_if:video_type,1,2',
                    new VideoUrlRule($request->video_type),
                ],
                'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480',
                'is_draft' => 'nullable|in:0,1',

            ], [], [
                'documents.*' => 'document :position',
            ]);

            try {

                DB::beginTransaction();
                $UpdateProperty = Property::with('assignparameter.parameter')->find($id);
                $watermarkAgentId = ($UpdateProperty->added_by != 0 && Customer::where('id', $UpdateProperty->added_by)->where('is_agent', true)->exists())
                    ? $UpdateProperty->added_by
                    : null;
                $destinationPath = public_path('images').config('global.PROPERTY_TITLE_IMG_PATH');
                if (! is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                // NOT NULL columns with no sensible placeholder and no FK
                // (unlike projects.category_id) — default to '' when left
                // blank (Save as Draft).
                $UpdateProperty->category_id = $request->category ?: '';
                $UpdateProperty->address = $request->address ?? '';
                $UpdateProperty->client_address = $request->client_address ?? '';
                // NOT NULL column — default to 0 (Sell) when left blank (Save as Draft).
                $UpdateProperty->propery_type = $request->property_type ?? 0;
                $UpdateProperty->price = $request->price ?? 0;
                $UpdateProperty->state = (isset($request->state)) ? $request->state : '';
                $UpdateProperty->country = (isset($request->country)) ? $request->country : '';
                $UpdateProperty->city = (isset($request->city)) ? $request->city : '';
                $UpdateProperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
                $UpdateProperty->longitude = (isset($request->longitude)) ? $request->longitude : '';

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
                // NOT NULL columns — fall back to whatever the draft already
                // had saved (so re-saving without retouching title/description
                // doesn't wipe it), then to '' if this is genuinely still blank.
                $UpdateProperty->title = $baseContent['title'] ?? $UpdateProperty->getRawOriginal('title') ?? '';
                $UpdateProperty->slug_id = $request->slug ?? generateUniqueSlug($baseContent['title'] ?? '', 1, null, $id);
                $UpdateProperty->description = $baseContent['description'] ?? $UpdateProperty->getRawOriginal('description') ?? '';

                $UpdateProperty->country_id = $request->country_id ?: null;
                $UpdateProperty->currency_id = $request->currency_id ?: null;
                if ($request->has('remove_video') && $request->remove_video == 1) {
                    if ($UpdateProperty->video_type == Property::VIDEO_CUSTOM && ! empty($UpdateProperty->getRawOriginal('video_link'))) {
                        FileService::delete(config('global.PROPERTY_VIDEO_PATH'), $UpdateProperty->getRawOriginal('video_link'));
                    }
                    $UpdateProperty->video_type = null;
                    $UpdateProperty->video_link = null;
                } else {
                    $UpdateProperty->video_type = $request->video_type;
                    if ($request->video_type == 0 && $request->hasFile('custom_video')) {
                        $path = config('global.PROPERTY_VIDEO_PATH');
                        $UpdateProperty->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
                    } else {
                        $UpdateProperty->video_link = $request->video_link;
                    }
                }
                $UpdateProperty->is_premium = $request->is_premium ?? 0;
                $UpdateProperty->meta_title = (isset($request->edit_meta_title)) ? $request->edit_meta_title : '';
                $UpdateProperty->meta_description = (isset($request->edit_meta_description)) ? $request->edit_meta_description : '';
                $UpdateProperty->meta_keywords = (isset($request->Keywords)) ? $request->Keywords : '';
                $UpdateProperty->schema_markup = $request->schema_markup;
                if ($UpdateProperty->added_by != 0) {
                    if (isset($request->edit_reason) && ! empty($request->edit_reason)) {
                        $UpdateProperty->edit_reason = $request->edit_reason;
                    } else {
                        ResponseService::validationError('Edit Reason is required');
                    }
                }

                $UpdateProperty->rentduration = $request->price_duration;

                // Auto-approve logic on edit (only for customer-uploaded listings)
                if ($UpdateProperty->added_by != 0) {
                    $autoApprove = HelperService::getAutoApproveStatus($UpdateProperty->added_by, 'user');
                    if ($autoApprove) {
                        $UpdateProperty->request_status = 'approved';
                        $UpdateProperty->status         = 1;
                    } else {
                        $UpdateProperty->request_status = 'pending';
                        $UpdateProperty->status         = 0;
                    }
                } else {
                    // Admin-owned listing — Save as Draft keeps/returns it to
                    // draft; a normal Save finalizes it (also covers turning
                    // an existing draft into a real listing).
                    $UpdateProperty->request_status = $isDraft ? 'draft' : 'approved';
                    $UpdateProperty->status = $isDraft ? 0 : 1;
                }

                if ($request->hasFile('title_image')) {
                    $path = config('global.PROPERTY_TITLE_IMG_PATH');
                    $requestFile = $request->file('title_image');
                    $rawImage = $UpdateProperty->getRawOriginal('title_image');
                    FileService::clearCachedBlurImageUrl('blur_property_title_image_'.$UpdateProperty->id);
                    $UpdateProperty->title_image = FileService::compressAndReplace($requestFile, $path, $rawImage, true, $watermarkAgentId);
                }

                if ($request->hasFile('3d_image')) {
                    $path = config('global.3D_IMG_PATH');
                    $requestFile = $request->file('3d_image');
                    $rawImage = $UpdateProperty->getRawOriginal('three_d_image');
                    $UpdateProperty->three_d_image = FileService::compressAndReplace($requestFile, $path, $rawImage);
                }

                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROPERTY_SEO_IMG_PATH');
                    $requestFile = $request->file('meta_image');
                    $rawImage = $UpdateProperty->getRawOriginal('meta_image');
                    $UpdateProperty->meta_image = FileService::compressAndReplace($requestFile, $path, $rawImage);
                }

                $UpdateProperty->update();
                AssignedOutdoorFacilities::where('property_id', $UpdateProperty->id)->delete();
                $facility = OutdoorFacilities::all();
                foreach ($facility as $key => $value) {
                    if ($request->has('facility'.$value->id) && $request->input('facility'.$value->id) != '') {
                        $facilities = new AssignedOutdoorFacilities;
                        $facilities->facility_id = $value->id;
                        $facilities->property_id = $UpdateProperty->id;
                        $facilities->distance = $request->input('facility'.$value->id);
                        $facilities->save();
                        Cache::forget("property_assign_facilities_{$UpdateProperty->id}");
                    }
                }
                $parameters = parameter::all();

                AssignParameters::where('modal_id', $id)->delete();
                foreach ($parameters as $par) {
                    if ($request->has('par_'.$par->id)) {
                        $update_parameter = new AssignParameters;
                        $update_parameter->parameter_id = $par->id;
                        if (($request->hasFile('par_'.$par->id))) {
                            $allowedMimeTypes = [
                                'image/jpeg',
                                'image/png',
                                'image/jpg',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/plain',
                            ];
                            $mimeType = $request->file('par_'.$par->id)->getMimeType();
                            if (! in_array($mimeType, $allowedMimeTypes)) {
                                return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                            }
                            $path = config('global.PARAMETER_IMG_PATH');
                            $requestFile = $request->file('par_'.$par->id);
                            $update_parameter->value = FileService::compressAndUpload($requestFile, $path);
                        } else {
                            $inputValue = $request->input('par_'.$par->id);
                            if (is_array($inputValue)) {
                                $encodedArray = array_map(function ($item) {
                                    return is_array($item) ? array_map('htmlspecialchars', $item) : htmlspecialchars($item ?? '', ENT_QUOTES, 'UTF-8');
                                }, $inputValue);
                                $update_parameter->value = json_encode($encodedArray, JSON_FORCE_OBJECT);
                            } else {
                                $update_parameter->value = (! empty($inputValue)) ? htmlspecialchars($inputValue, ENT_QUOTES, 'UTF-8') : null;
                            }
                        }
                        $update_parameter->modal()->associate($UpdateProperty);
                        $update_parameter->save();
                        Cache::forget("property_parameters_{$UpdateProperty->id}");
                    }
                }

                // / START :: UPLOAD GALLERY IMAGE
                if ($request->hasfile('gallery_images')) {
                    $galleryImagesData = [];
                    foreach ($request->file('gallery_images') as $file) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH').$UpdateProperty->id.'/';
                        $requestFile = $file;
                        $imageName = FileService::compressAndUpload($requestFile, $path, true, $watermarkAgentId);
                        $galleryImagesData[] = [
                            'propertys_id' => $UpdateProperty->id,
                            'image' => $imageName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($galleryImagesData)) {
                        PropertyImages::insert($galleryImagesData);
                    }
                }
                // / END :: UPLOAD GALLERY IMAGE

                // / START :: UPLOAD DOCUMENT
                if ($request->hasFile('documents')) {
                    $documentsData = [];
                    foreach ($request->file('documents') as $file) {
                        $path = config('global.PROPERTY_DOCUMENT_PATH');
                        $requestFile = $file;
                        $documentName = FileService::compressAndUpload($requestFile, $path);
                        $documentsData[] = [
                            'property_id' => $UpdateProperty->id,
                            'name' => $documentName,
                            'type' => $file->extension(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($documentsData)) {
                        PropertiesDocument::insert($documentsData);
                    }
                }
                // / END :: UPLOAD DOCUMENT

                // START :: ADD CITY DATA
                if (isset($request->city) && ! empty($request->city)) {
                    CityImage::updateOrCreate(['city' => $request->city]);
                }
                // END :: ADD CITY DATA

                // START ::Add Translations
                if (isset($request->translations) && ! empty($request->translations)) {
                    $translationData = [];
                    foreach ($request->translations as $translation) {
                        foreach ($translation as $key => $value) {
                            if (is_array($value)) {
                                $translationData[] = [
                                    'id' => $value['id'] ?? null,
                                    'translatable_id' => $UpdateProperty->id,
                                    'translatable_type' => 'App\Models\Property',
                                    'language_id' => $value['language_id'],
                                    'key' => $key,
                                    'value' => $value['value'],
                                ];
                            }
                        }
                    }
                    if (! empty($translationData)) {
                        HelperService::storeTranslations($translationData);
                    }
                }

                DB::commit();

                // Notify property owner when admin edits their property
                if ($UpdateProperty->added_by != 0) {
                    try {
                        $UpdateProperty->load('customer:id,name,isActive,notification');
                        $customer = $UpdateProperty->customer;
                        if ($customer && $customer->isActive == 1 && $customer->notification == 1) {
                            $tokens = Usertokens::where('customer_id', $customer->id)->pluck('fcm_id')->toArray();
                            if (! empty($tokens)) {
                                $fcmMsg = [
                                    'title'        => 'Property updated :- :property_name',
                                    'message'      => trans('Your property has been edited by administrator'),
                                    'type'         => 'property_inquiry',
                                    'body'         => trans('Your property has been edited by administrator'),
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'sound'        => 'default',
                                    'id'           => (string) $UpdateProperty->id,
                                    'role_context' => $UpdateProperty->role_context ?? 'user',
                                    'replace'      => ['property_name' => $UpdateProperty->title],
                                ];
                                send_push_notification($tokens, $fcmMsg);
                            }
                        }
                        Notifications::create([
                            'title'        => 'Property Updated :- ' . $UpdateProperty->title,
                            'message'      => trans('Your property has been edited by administrator'),
                            'image'        => '',
                            'type'         => '1',
                            'send_type'    => '0',
                            'customers_id' => $UpdateProperty->customer->id,
                            'propertys_id' => $UpdateProperty->id,
                            'role_context' => $UpdateProperty->role_context ?? 'user',
                        ]);
                    } catch (Exception $e) {
                        Log::error('Admin edit property notification failed: ' . $e->getMessage());
                    }
                }

                AuditLogService::log('property', $UpdateProperty->id, $UpdateProperty->title, 'updated', "Property '{$UpdateProperty->title}' updated by admin");
                ResponseService::successResponse('Data Updated Successfully');
            } catch (Exception $e) {
                DB::rollBack();
                ResponseService::logErrorResponse($e, 'Update Property Issue');
            }
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
            return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
        }
        if (! has_permissions('delete', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            DB::beginTransaction();
            $property = Property::find($id);
            if (! $property) {
                DB::rollBack();

                return ResponseService::errorRedirectResponse('Data not found');
            }
            $propertyTitle = $property->title;

            if ($property->delete()) {
                DB::commit();
                AuditLogService::log('property', $id, $propertyTitle, 'deleted', "Property '{$propertyTitle}' deleted by admin");
                ResponseService::successRedirectResponse('Data Deleted Successfully');
            } else {
                DB::rollBack();
                ResponseService::errorRedirectResponse('Something Wrong');
            }
        }
    }

    public function getPropertyList(Request $request)
    {

        $offset = (int) $request->input('offset', 0); // Ensure integer for pagination
        $limit = (int) $request->input('limit', 10);   // Ensure integer for pagination
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $customerID = $request->input('customerID', null);

        $sql = Property::with('category')
            ->with('customer:id,name,mobile')
            ->with('customer.agent_profile:customer_id,agent_name')
            ->with('assignParameter.parameter')
            ->with('interested_users')
            ->with('advertisement')
            ->orderBy($sort, $order);

        $searchQuery = null;
        $propertyType = null;
        $status = null;
        $categoryId = null;
        $propertyAddedBy = null;

        // Extract and validate filters
        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $searchQuery = trim($_GET['search']);  // Trim whitespace
        }

        if (isset($_GET['property_type']) && $_GET['property_type'] !== '') {
            $propertyType = $_GET['property_type'];
        }

        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $status = $_GET['status'];
        }

        if (isset($_GET['category']) && $_GET['category'] !== '') {
            $categoryId = (int) $_GET['category']; // Ensure integer for category ID
        }

        if (isset($_GET['property_added_by']) && $_GET['property_added_by'] !== '') {
            $propertyAddedBy = $_GET['property_added_by'];
        }
        if (isset($_GET['property_accessibility']) && $_GET['property_accessibility'] !== '') {
            $propertyAccessibility = $_GET['property_accessibility'];
        }
        if (isset($_GET['verification_status']) && $_GET['verification_status'] !== '') {
            $verificationStatus = $_GET['verification_status']; // Ensure string for verification status
        }

        // Apply filters with proper escaping for security
        if ($searchQuery !== null) {
            $sql = $sql->where(function ($query) use ($searchQuery) {
                $query->where('id', 'LIKE', "%$searchQuery%")->orwhere('title', 'LIKE', "%$searchQuery%")->orwhere('address', 'LIKE', "%$searchQuery%");
                $query->orWhereHas('category', function ($query) use ($searchQuery) {
                    $query->where('category', 'LIKE', "%$searchQuery%");
                })->orWhereHas('customer', function ($query) use ($searchQuery) {
                    $query->where('name', 'LIKE', "%$searchQuery%")->orwhere('email', 'LIKE', "%$searchQuery%");
                });
            });
        }

        if ($propertyType !== null) {
            $sql = $sql->where('propery_type', $propertyType);
        }

        if (! empty($customerID)) {
            $sql = $sql->where('added_by', $customerID);
        }

        if ($status !== null) {
            $sql = $sql->where('status', $status);
        }

        if ($categoryId !== null) {
            $sql = $sql->where('category_id', $categoryId);
        }

        if ($propertyAddedBy !== null) {
            if ($propertyAddedBy == 0) {
                $sql = $sql->where('added_by', 0);
            } else {
                $sql = $sql->whereNot('added_by', 0);
            }
        }
        if (isset($propertyAccessibility) && $propertyAccessibility !== null) {
            if ($propertyAccessibility == 1) {
                $sql = $sql->where('is_premium', 1);
            } else {
                $sql = $sql->where('is_premium', 0);
            }
        }

        if (isset($verificationStatus) && $verificationStatus !== null) {
            $sql = $sql->where('request_status', $verificationStatus);
        }

        // Filter by role_context (All/Admin/User/Agent tabs)
        if (isset($_GET['role_context_filter']) && $_GET['role_context_filter'] !== '') {
            $addedAsFilter = $_GET['role_context_filter'];
            if ($addedAsFilter === 'admin') {
                $sql = $sql->where('added_by', 0);
            } elseif ($addedAsFilter === 'user') {
                $sql = $sql->where(function ($q) {
                    $q->where('role_context', 'user')->orWhereNull('role_context');
                })->where('added_by', '!=', 0);
            } elseif ($addedAsFilter === 'agent') {
                $sql = $sql->where('role_context', 'agent')->where('added_by', '!=', 0);
            }
        }

        $total = $sql->count();

        if (isset($limit)) {
            $sql = $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;

        $operate = '';
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        $adminName = \App\Models\User::where('type', 0)->value('name') ?? trans('Admin');

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['property_type'] = $row->getRawOriginal('propery_type');
            if ($row->added_by == 0) {
                $tempRow['customer_name'] = $adminName;
            } elseif ($row->role_context === 'agent') {
                $tempRow['customer_name'] = $row->customer?->agent_profile?->agent_name ?? $row->customer?->name ?? '-';
            } else {
                $tempRow['customer_name'] = $row->customer?->name ?? '-';
            }
            $tempRow['added_as_tag'] = $row->added_by == 0 ? 'admin' : ($row->role_context ?? 'user');

            if ($row->added_by != 0) {
                if ($row->getRawOriginal('request_status') === 'draft' || $row->request_status === 'draft') {
                    $operate = '<span class="badge bg-secondary rounded-pill me-1">'.trans('Draft').'</span>';
                } else {
                    $requestStatusButtonCustomClasses = ['btn', 'icon', 'text-warning', 'btn-light-warning', 'btn-sm', 'rounded-pill', 'request-status-btn', 'border', 'border-warning'];
                    $requestStatusButtonCustomAttributes = ['id' => $row->id, 'title' => trans('Change Status'), 'data-toggle' => 'modal', 'data-bs-target' => '#changeRequestStatusModal', 'data-bs-toggle' => 'modal'];
                    $operate = BootstrapTableService::button('fa fa-exclamation-circle', '', $requestStatusButtonCustomClasses, $requestStatusButtonCustomAttributes);
                }
            } elseif ($row->getRawOriginal('request_status') === 'draft' || $row->request_status === 'draft') {
                $operate = '<span class="badge bg-secondary rounded-pill me-1">'.trans('Draft').'</span>';
            } else {
                $operate = '';
            }
            // Edit stays hidden for an agent/user's own in-progress draft (admin
            // shouldn't finish/publish someone else's unfinished draft for them),
            // but admin's own draft listings (added_by == 0) need to stay editable
            // so admin can resume and finish them via Save as Draft/Save.
            if (has_permissions('update', 'property') && ($row->request_status !== 'draft' || $row->added_by == 0)) {
                $operate .= BootstrapTableService::editButton(route('property.edit', $row->id), false);
            }
            if (has_permissions('delete', 'property')) {
                $operate .= BootstrapTableService::deleteButton(route('property.destroy', $row->id));
            }

            $interested_users = [];
            foreach ($row->interested_users as $interested_user) {
                if ($interested_user->property_id == $row->id) {
                    array_push($interested_users, $interested_user->customer_id);
                }
            }

            $price = null;
            if (! empty($row->propery_type) && $row->getRawOriginal('propery_type') == 1) {
                $price = ! empty($row->rentduration) ? $currency_symbol.''.$row->price.'/'.$row->rentduration : $row->price;
            } else {
                $price = $currency_symbol.''.$row->price;
            }

            $tempRow['total_interested_users'] = count($interested_users);
            $tempRow['promoted'] = $row->is_promoted;
            if ($row->added_by == 0 && $row->request_status == 'approved') {
                $tempRow['edit_status'] = $row->status;
            } else {
                $tempRow['edit_status'] = null;
            }
            if (has_permissions('update', 'property')) {
                $tempRow['edit_status_url'] = $row->added_by == 0 && $row->request_status == 'approved' ? 'updatepropertystatus' : null;
            } else {
                $tempRow['edit_status_url'] = null;
            }
            $tempRow['price'] = $price;
            $featured = count($row->advertisement) ? '<div class="featured_tag"><div class="featured_lable">'.trans('Featured').'</div></div>' : '';
            $tempRow['Property_name'] = '<div class="propetrty_name d-flex"><img class="property_image" alt="" src="'.$row->title_image.'"><div class="property_detail"><div class="property_title">'.$row->title.'</div>'.$featured.'</div></div></div>';

            if ($row->added_by != 0) {
                $tempRow['added_by'] = $row->customer?->name ?? '-';
                $tempRow['mobile'] = (env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ($row->customer?->mobile ?? '') : '****************************') : ($row->customer?->mobile ?? ''));
            }
            if ($row->added_by == 0) {
                $mobile = Setting::where('type', 'company_tel1')->pluck('data');
                $tempRow['added_by'] = $adminName;
                $tempRow['mobile'] = $mobile[0];
            }
            $tempRow['customer_ids'] = $interested_users;

            // Interested Users
            $count = '  '.count($interested_users);
            $interestedUserButton = BootstrapTableService::editButton('', true, null, 'text-secondary interested_users_btn', $row->id, null, '', 'bi bi-eye-fill edit_icon', $count);
            $tempRow['raw_interested_users'] = $interestedUserButton;
            foreach ($row->interested_users as $interested_user) {
                if ($interested_user->property_id == $row->id) {
                    $tempRow['interested_users_details'] = Customer::Where('id', $interested_user->customer_id)->get()->toArray();
                }
            }

            // Gallery Images
            $galleryButtonCustomClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill', 'gallery-image-btn'];
            $galleryButtonCustomAttributes = ['id' => $row->id, 'title' => trans('Gallery Images'), 'data-toggle' => 'modal', 'data-bs-target' => '#galleryImagesModal', 'data-bs-toggle' => 'modal'];
            $galleryImagesCount = count($row->gallery);
            $galleryImagesButton = BootstrapTableService::button('bi bi-eye-fill ml-2', '', $galleryButtonCustomClasses, $galleryButtonCustomAttributes, $galleryImagesCount);
            $tempRow['raw_gallery_images_btn'] = $galleryImagesButton;

            // Documents
            $documentsButtonCustomClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill', 'documents-btn'];
            $documentsButtonCustomAttributes = ['id' => $row->id, 'title' => trans('Documents'), 'data-toggle' => 'modal', 'data-bs-target' => '#documentsModal', 'data-bs-toggle' => 'modal'];
            $documentsCount = count($row->documents);
            $documentsButton = BootstrapTableService::button('bi bi-eye-fill', '', $documentsButtonCustomClasses, $documentsButtonCustomAttributes, $documentsCount);
            $tempRow['raw_documents_btn'] = $documentsButton;

            // $tempRow['expiry_date'] = $row->expiry_date ? \Carbon\Carbon::parse($row->expiry_date)->toIso8601String() : null;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }
        // $cities =  json_decode(file_get_contents(public_path('json') . "/cities.json"), true);

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function updateStatus(Request $request)
    {

        // dd($request->status);
        if (! has_permissions('update', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Property::where('id', $request->id)->update(['status' => $request->status]);
            $Property = Property::with('customer')->find($request->id);

            if ($request->status == 1) {
                HelperService::AlertUserForNewListing($Property->id);
            }

            if (! empty($Property->customer)) {
                if ($Property->customer->isActive == 1 && $Property->customer->notification == 1) {

                    $fcm_ids = [];
                    $user_token = Usertokens::where('customer_id', $Property->customer->id)->pluck('fcm_id')->toArray();
                    $fcm_ids[] = ! empty($user_token) ? $user_token : [];

                    $msg = '';
                    if (! empty($fcm_ids)) {
                        $title = 'Property updated :- :property_name';
                        $msg = $Property->status == 1 ? 'Your property post activated by administrator' : 'Your property post deactivated by administrator';
                        $registrationIDs = $fcm_ids[0];

                        $fcmMsg = [
                            'title' => $title,
                            'message' => $msg,
                            'type' => 'property_inquiry',
                            'body' => $msg,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => (string) $Property->id,
                            'role_context' => $Property->role_context ?? 'user',
                            'replace' => [
                                'property_name' => $Property->name,
                            ],

                        ];
                        send_push_notification($registrationIDs, $fcmMsg);
                    }
                    // END ::  Send Notification To Customer

                    $notificationMsg = $Property->status == 1 ? 'Your property post activated by administrator' : 'Your property post deactivated by administrator';
                    Notifications::create([
                        'title' => 'Property Updated :- '.$Property->name,
                        'message' => $notificationMsg,
                        'image' => '',
                        'type' => '1',
                        'send_type' => '0',
                        'customers_id' => $Property->customer->id,
                        'propertys_id' => $Property->id,
                        'role_context' => $Property->role_context ?? 'user',
                    ]);
                }
            }
            $statusLabel = $request->status ? 'Activated' : 'Deactivated';
            AuditLogService::log('property', $request->id, $Property->title ?? null, 'status_changed', "Property status changed to {$statusLabel} by admin");
            ResponseService::successResponse($request->status ? 'Property Activated Successfully' : 'Property Deactivated Successfully');
        }
    }

    public function removeGalleryImage(Request $request)
    {

        if (! has_permissions('delete', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getImage = PropertyImages::where('id', $id)->first();

            $image = $getImage->image;
            $propertys_id = $getImage->propertys_id;

            if (PropertyImages::where('id', $id)->delete()) {
                if (file_exists(public_path('images').config('global.PROPERTY_GALLERY_IMG_PATH').$propertys_id.'/'.$image)) {
                    unlink(public_path('images').config('global.PROPERTY_GALLERY_IMG_PATH').$propertys_id.'/'.$image);
                }
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            $countImage = PropertyImages::where('propertys_id', $propertys_id)->get();
            if ($countImage->count() == 0) {
                rmdir(public_path('images').config('global.PROPERTY_GALLERY_IMG_PATH').$propertys_id);
            }

            return response()->json($response);
        }
    }

    public function getFeaturedPropertyList()
    {

        $offset = 0;
        $limit = 4;
        $sort = 'id';
        $order = 'DESC';

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }

        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }

        if (isset($_GET['sort'])) {
            $sort = $_GET['sort'];
        }

        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        $sql = Property::with('category')->with('customer')->with('advertisement')->whereHas('advertisement')->orderBy($sort, $order);

        $sql->skip($offset)->take($limit);

        $res = $sql->get();

        $bulkData = [];

        $rows = [];
        $tempRow = [];
        $count = 1;

        $operate = '';

        foreach ($res as $row) {

            if (count($row->advertisement)) {
                if (has_permissions('update', 'property') && $row->added_by == 0) {
                    $operate = '<a  href="'.route('property.edit', $row->id).'"  class="btn icon btn-primary btn-sm rounded-pill mt-2" id="edit_btn" title="Edit"><i class="fa fa-edit edit_icon"></i></a>';
                } else {
                    $operate = '-';
                }
                $tempRow = $row->toArray();
                $tempRow['type'] = ucfirst($row->propery_type);
                if ($row->added_by == 0 && $row->request_status == 'approved') {
                    $tempRow['status'] = $row->status;
                } else {
                    $tempRow['status'] = null;
                }
                $tempRow['edit_status_url'] = 'updatepropertystatus';
                $tempRow['promoted'] = $row->is_promoted;
                $tempRow['operate'] = $operate;
                $rows[] = $tempRow;
                $count++;
            }
        }
        $total = $sql->count();
        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function updateaccessability(Request $request)
    {
        if (! has_permissions('update', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Property::where('id', $request->id)->update(['is_premium' => $request->status]);
            ResponseService::successResponse('Data Updated Successfully');
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
                $slug = generateUniqueSlug($title, 1, null, $id);
            } else {
                $slug = generateUniqueSlug($title, 1);
            }
            ResponseService::successResponse('', $slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Property Slug Generation Error', 'Something Went Wrong');
        }
    }

    public function removeDocument(Request $request)
    {

        if (! has_permissions('delete', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;
            $getDocument = PropertiesDocument::where('id', $id)->first();
            if ($getDocument) {
                $file = $getDocument->getRawOriginal('name');
                $propertyId = $getDocument->property_id;

                if (PropertiesDocument::where('id', $id)->delete()) {
                    if (file_exists(public_path('images').config('global.PROPERTY_DOCUMENT_PATH').$propertyId.'/'.$file)) {
                        unlink(public_path('images').config('global.PROPERTY_DOCUMENT_PATH').$propertyId.'/'.$file);
                    }
                    $response['error'] = false;
                } else {
                    $response['error'] = true;
                }

                $countImage = PropertiesDocument::where('property_id', $propertyId)->get();
                if ($countImage->count() == 0) {
                    rmdir(public_path('images').config('global.PROPERTY_DOCUMENT_PATH').$propertyId);
                }

                return response()->json($response);
            }
        }
    }

    public function removeThreeDImage($id, Request $request)
    {
        if (! has_permissions('delete', 'property')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            try {
                $propertyData = Property::findOrFail($id);
                unlink_image($propertyData->three_d_image);
                $propertyData->three_d_image = null;
                $propertyData->save();
                ResponseService::successResponse('Data Deleted Successfully');
            } catch (Exception $e) {
                ResponseService::logErrorResponse($e, 'Remove ThreeD Image Error', 'Something Went Wrong');
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
            if (! has_permissions('update', 'property')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            } else {
                $notifyNewListing = false;
                if ($request->request_status == 'rejected') {
                    RejectReason::create([
                        'property_id' => $request->id,
                        'reason' => $request->reject_reason,
                    ]);
                    $status = 0;
                } else {
                    $status = 1;
                    $notifyNewListing = true;
                }

                if ($request->request_status == 'approved') {
                    $propertyData = Property::find($request->id);
                    if ($propertyData->request_status != 'approved') {
                        if ($propertyData->expiry_date && $propertyData->updated_at) {
                            $durationDays = Carbon::parse($propertyData->updated_at)->startOfDay()->diffInDays(Carbon::parse($propertyData->expiry_date)->startOfDay());
                            $expirationDate = Carbon::now()->addDays($durationDays > 0 ? $durationDays : 30);
                        } else {
                            $expirationDate = HelperService::calculateExpirationDate($propertyData->added_by);
                        }
                        Property::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status, 'expiry_date' => $expirationDate]);
                    } else {
                        Property::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status]);
                    }
                } else {
                    Property::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => $status]);
                }
                DB::commit();
                $actionLabel = $request->request_status === 'approved' ? 'approved' : 'rejected';
                $desc = $request->request_status === 'rejected'
                    ? "Property request rejected. Reason: {$request->reject_reason}"
                    : 'Property request approved by admin';
                AuditLogService::log('property', $request->id, null, $actionLabel, $desc);

                // Send mail for property status
                try {
                    $propertyData = Property::where('id', $request->id)->select('id', 'title', 'request_status', 'added_by')->with('customer:id,name,email')->firstOrFail();
                    if (! empty($propertyData->customer->email)) {
                        // Get Data of email type
                        $emailTypeData = HelperService::getEmailTemplatesTypes('property_status');

                        // Email Template
                        $propertyStatusTemplateData = system_setting($emailTypeData['type']);
                        $appName = env('APP_NAME') ?? 'eBroker';
                        $variables = [
                            'app_name' => $appName,
                            'user_name' => $propertyData->customer->name,
                            'property_name' => $propertyData->title,
                            'status' => $request->request_status,
                            'reject_reason' => $request->request_status == 'rejected' ? $request->reject_reason : null,
                            'email' => $propertyData->customer->email,
                        ];
                        if (empty($propertyStatusTemplateData)) {
                            $propertyStatusTemplateData = 'Property Status have been changed';
                        }
                        $propertyStatusTemplate = HelperService::replaceEmailVariables($propertyStatusTemplateData, $variables);

                        $data = [
                            'email_template' => $propertyStatusTemplate,
                            'email' => $propertyData->customer->email,
                            'title' => $emailTypeData['title'],
                        ];
                        HelperService::sendMail($data);
                    }
                } catch (Exception $e) {
                    Log::error('Something Went Wrong in Property Status Update Mail Sending');
                }

                // Send Notification
                $property = Property::with('customer:id,name,isActive,notification')->select('id', 'title', 'request_status', 'added_by', 'role_context')->find($request->id);

                if ($property->customer) {
                    $fcm_ids = [];
                    if ($property->customer->isActive == 1 && $property->customer->notification == 1) {
                        $user_token = Usertokens::where('customer_id', $property->customer->id)->pluck('fcm_id')->toArray();
                    }

                    $fcm_ids[] = $user_token ?? [];

                    $msg = '';
                    if (! empty($fcm_ids)) {
                        $title = 'Property updated :- :property_name';
                        $msg = $property->request_status == 'approved' ? 'Your property post approved by administrator' : 'Your property post rejected by administrator';
                        $registrationIDs = $fcm_ids[0];

                        $fcmMsg = [
                            'title' => $title,
                            'message' => $msg,
                            'type' => 'property_inquiry',
                            'body' => $msg,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => (string) $property->id,
                            'role_context' => $property->role_context ?? 'user',
                            'replace' => [
                                'property_name' => $property->title,
                            ],
                        ];
                        send_push_notification($registrationIDs, $fcmMsg);
                    }
                    // END ::  Send Notification To Customer

                    $notificationMsg = $property->request_status == 'approved' ? 'Your property post approved by administrator' : 'Your property post rejected by administrator';
                    Notifications::create([
                        'title' => 'Property Updated :- '.$property->title,
                        'message' => $notificationMsg,
                        'image' => '',
                        'type' => '1',
                        'send_type' => '0',
                        'customers_id' => $property->customer->id,
                        'propertys_id' => $property->id,
                        'role_context' => $property->role_context ?? 'user',
                    ]);
                }

                if ($notifyNewListing) {
                    HelperService::AlertUserForNewListing($request->id);
                }
                ResponseService::successResponse('Data Updated Successfully');
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'Update Request Status in Property', 'Something Went Wrong');
        }
    }
}
