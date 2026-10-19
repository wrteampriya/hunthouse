<?php

namespace App\Http\Controllers;

use App\Models\AssignedOutdoorFacilities;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Feature;
use App\Models\OutdoorFacilities;
use App\Models\PackageFeature;
use App\Models\ProjectDocuments;
use App\Models\Projects;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\UserPackage;
use App\Models\UserPackageLimit;
use App\Models\parameter as Parameter;
use App\Jobs\AddWatermarkJob;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class BulkImportController extends Controller
{
    // -----------------------------------------------------------------------
    //  Pages
    // -----------------------------------------------------------------------

    /** Show the property bulk-import page. */
    public function propertyIndex()
    {
        if (! has_permissions('create', 'property')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('bulk-import.property', [
            'languages' => $this->activeLanguagesForImport(),
            'countries' => Country::where('status', 1)->get(),
        ]);
    }

    /** Show the project bulk-import page. */
    public function projectIndex()
    {
        if (! has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('bulk-import.project', [
            'languages' => $this->activeLanguagesForImport(),
            'countries' => Country::where('status', 1)->get(),
        ]);
    }

    /**
     * Active languages for the "Content Language" bulk-import picker, with
     * the system default language (if active) listed first.
     */
    private function activeLanguagesForImport()
    {
        $defaultCode = HelperService::getSettingData('default_language');

        return Language::where('status', 1)
            ->orderByRaw('code = ? DESC', [$defaultCode])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    // -----------------------------------------------------------------------
    //  Example CSV downloads
    // -----------------------------------------------------------------------

    /** Stream the property example CSV to the browser. */
    public function downloadPropertyCsv()
    {
        $path = storage_path('app/example-csvs/property_example.csv');

        return response()->download($path, 'property_example.csv', [
            'Content-Type'        => 'text/csv',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /** Stream a categories reference CSV (id, name) to the browser. */
    public function downloadCategoriesCsv()
    {
        $categories = \App\Models\Category::orderBy('id')->get(['id', 'category']);

        $rows   = [];
        $rows[] = implode(',', ['id', 'category']);
        foreach ($categories as $cat) {
            $rows[] = $cat->id . ',' . '"' . str_replace('"', '""', $cat->category) . '"';
        }

        return response(implode("\n", $rows), 200, [
            'Content-Type'              => 'text/csv',
            'Content-Disposition'       => 'attachment; filename="categories.csv"',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'no-cache',
            'Expires'                   => '0',
        ]);
    }

    /** Stream the project example CSV to the browser. */
    public function downloadProjectCsv()
    {
        $path = storage_path('app/example-csvs/project_example.csv');

        return response()->download($path, 'project_example.csv', [
            'Content-Type'        => 'text/csv',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    // -----------------------------------------------------------------------
    //  Customer search (Select2 AJAX)
    // -----------------------------------------------------------------------

    /**
     * Search customers for the Select2 dropdown.
     * Requires create permission on property OR project (not assign_package).
     */
    public function searchCustomers(Request $request)
    {
        if (! has_permissions('create', 'property') && ! has_permissions('create', 'project')) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $term    = $request->input('q', '');
        $page    = (int) $request->input('page', 1);
        $perPage = 20;
        $role    = $request->input('role', 'user');

        $query = Customer::where('isActive', 1);

        // For agent mode, restrict to agents only. For user mode, show all customers.
        if ($role === 'agent') {
            $query->where('is_agent', true);
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('id', 'like', "%{$term}%");
            });
        }

        $total   = $query->count();
        $items   = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'name', 'email']);

        $results = $items->map(function ($c) {
            $label = trim($c->getRawOriginal('name').' <'.($c->email ?? '').'>');

            return ['id' => $c->id, 'text' => rtrim($label, ' <>').' (#'.$c->id.')'];
        })->toArray();

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => ($page * $perPage) < $total],
        ]);
    }

    // -----------------------------------------------------------------------
    //  Customer info (AJAX)
    // -----------------------------------------------------------------------

    /**
     * Return active package + quota info for a customer.
     * Used by the bulk-import UI to show remaining slots before upload.
     *
     * Response shape:
     *   { customer: {id, name, email}, has_package: bool,
     *     package: {id, name, end_date} | null,
     *     quota: {property: int|'unlimited', project: int|'unlimited'} }
     */
    public function getCustomerInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'role'        => 'nullable|in:user,agent',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $role     = $request->input('role', 'user');
        $customer = Customer::findOrFail($request->customer_id);

        $userPackage = UserPackage::where('user_id', $customer->id)
            ->where('role_context', $role)
            ->onlyActive()
            ->with(['package', 'user_package_limits.package_feature.feature'])
            ->orderBy('end_date', 'asc')
            ->first();

        if (! $userPackage) {
            return response()->json([
                'customer'    => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email],
                'has_package' => false,
                'package'     => null,
                'quota'       => ['property' => 0, 'project' => 0],
            ]);
        }

        // Feature IDs: 1 = Property List, 2 = Project List
        $propertyFeatureId = Feature::where('name', 'Property List')->value('id') ?? 1;
        $projectFeatureId  = Feature::where('name', 'Project List')->value('id') ?? 2;

        $propertyQuota = $this->resolveQuota($userPackage, $propertyFeatureId);
        $projectQuota  = $this->resolveQuota($userPackage, $projectFeatureId);

        return response()->json([
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
            ],
            'has_package' => true,
            'package' => [
                'id'       => $userPackage->package_id,
                'name'     => $userPackage->package->getRawOriginal('name'),
                'end_date' => $userPackage->end_date?->format('d M Y') ?? 'Unlimited',
            ],
            'quota' => [
                'property' => $propertyQuota,
                'project'  => $projectQuota,
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    //  CSV processing
    // -----------------------------------------------------------------------

    /**
     * Parse and insert properties from the uploaded CSV.
     *
     * Returns JSON: { total, imported, skipped: [{row, reason}] }
     */
    public function processPropertyCsv(Request $request)
    {
        if (! has_permissions('create', 'property')) {
            return response()->json(['error' => trans(PERMISSION_ERROR_MSG)], 403);
        }

        $validator = Validator::make($request->all(), [
            'csv_file'          => 'required|file|extensions:csv,txt|max:10240',
            'upload_mode'       => 'required|in:admin,customer',
            'customer_id'       => 'required_if:upload_mode,customer|nullable|integer|exists:customers,id',
            'customer_role'     => 'required_if:upload_mode,customer|nullable|in:user,agent',
            'country_id'        => 'required|integer|exists:countries,id',
            'content_language_id' => 'required|integer|exists:languages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $countryId = (int) $request->country_id;
        $languageError = $this->validateBatchLanguageForCountry($countryId, (int) $request->content_language_id);
        if ($languageError) {
            return response()->json(['error' => $languageError], 422);
        }

        // Resolve customer context
        $customerContext = null;
        if ($request->upload_mode === 'customer') {
            $role = $request->input('customer_role', 'user');
            $customerContext = $this->resolveCustomerContext($request->customer_id, 'property', $role);
            if (isset($customerContext['error'])) {
                return response()->json(['error' => $customerContext['error']], 422);
            }
        }

        try {
            $rows = $this->parseCsv($request->file('csv_file'));

            if (empty($rows)) {
                return response()->json(['error' => trans('The CSV file is empty. Please add at least one data row and try again.')], 422);
            }

            $results = $this->importProperties($rows, $customerContext, (int) $request->content_language_id, $countryId);

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Property bulk import failed: '.$e->getMessage());

            return response()->json(['error' => trans('Failed to process CSV: :error', ['error' => $e->getMessage()])], 500);
        }
    }

    /**
     * Parse and insert projects from the uploaded CSV.
     *
     * Returns JSON: { total, imported, skipped: [{row, reason}] }
     */
    public function processProjectCsv(Request $request)
    {
        if (! has_permissions('create', 'project')) {
            return response()->json(['error' => trans(PERMISSION_ERROR_MSG)], 403);
        }

        $validator = Validator::make($request->all(), [
            'csv_file'          => 'required|file|mimes:csv,txt|max:10240',
            'upload_mode'       => 'required|in:admin,customer',
            'customer_id'       => 'required_if:upload_mode,customer|nullable|integer|exists:customers,id',
            'customer_role'     => 'required_if:upload_mode,customer|nullable|in:user,agent',
            'country_id'        => 'required|integer|exists:countries,id',
            'content_language_id' => 'required|integer|exists:languages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $countryId = (int) $request->country_id;
        $languageError = $this->validateBatchLanguageForCountry($countryId, (int) $request->content_language_id);
        if ($languageError) {
            return response()->json(['error' => $languageError], 422);
        }

        $customerContext = null;
        if ($request->upload_mode === 'customer') {
            $role = $request->input('customer_role', 'user');
            $customerContext = $this->resolveCustomerContext($request->customer_id, 'project', $role);
            if (isset($customerContext['error'])) {
                return response()->json(['error' => $customerContext['error']], 422);
            }
        }

        try {
            $rows = $this->parseCsv($request->file('csv_file'));

            if (empty($rows)) {
                return response()->json(['error' => trans('The CSV file is empty. Please add at least one data row and try again.')], 422);
            }

            $results = $this->importProjects($rows, $customerContext, (int) $request->content_language_id, $countryId);

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Project bulk import failed: '.$e->getMessage());

            return response()->json(['error' => trans('Failed to process CSV: :error', ['error' => $e->getMessage()])], 500);
        }
    }

    // -----------------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------------

    /**
     * The whole batch shares one country and one content language (both
     * picked once in the UI). If that country's required language doesn't
     * match the declared content language, no row in the batch could ever
     * satisfy HelperService::validateRequiredLanguageContent() — reject the
     * whole upload up front instead of skipping every row with the same reason.
     */
    private function validateBatchLanguageForCountry(int $countryId, int $contentLanguageId): ?string
    {
        $requiredLanguageId = Country::requiredLanguageIdFor($countryId) ?? HelperService::currentContentLanguageId();
        if ($requiredLanguageId && (int) $requiredLanguageId !== $contentLanguageId) {
            $requiredLanguageName = Language::find($requiredLanguageId)->name ?? '';

            return trans('The selected country requires content in :language. Please choose that language.', ['language' => $requiredLanguageName]);
        }

        return null;
    }

    /**
     * Build the customer context array needed by the import methods.
     * Returns an ['error' => '...'] array if the customer has no active package.
     *
     * @param  string  $type  'property' | 'project'
     */
    private function resolveCustomerContext(int $customerId, string $type, string $role = 'user'): array
    {
        $featureName      = $type === 'property' ? 'Property List' : 'Project List';
        $featureId        = Feature::where('name', $featureName)->value('id') ?? ($type === 'property' ? 1 : 2);

        $userPackage = UserPackage::where('user_id', $customerId)
            ->where('role_context', $role)
            ->onlyActive()
            ->with(['package', 'user_package_limits' => function ($q) use ($featureId) {
                $q->whereHas('package_feature', fn ($q2) => $q2->where('feature_id', $featureId));
            }])
            ->orderBy('end_date', 'asc')
            ->first();

        if (! $userPackage) {
            return ['error' => trans('Selected customer does not have an active package. Please assign a package first.')];
        }

        $packageFeature = PackageFeature::where('package_id', $userPackage->package_id)
            ->where('feature_id', $featureId)
            ->first();

        // If the package doesn't include this feature at all
        if (! $packageFeature) {
            return ['error' => trans("Customer's package does not include the :feature feature.", ['feature' => $featureName])];
        }

        $limitRecord = null;
        $remaining   = null; // null = unlimited

        if ($packageFeature->limit_type === 'limited') {
            $limitRecord = UserPackageLimit::where('user_package_id', $userPackage->id)
                ->where('package_feature_id', $packageFeature->id)
                ->first();

            if (! $limitRecord) {
                return ['error' => trans('Package limit record not found for this customer.')];
            }

            $remaining = $limitRecord->total_limit - $limitRecord->used_limit;

            if ($remaining <= 0) {
                return ['error' => trans("Customer's package quota for :feature is exhausted (0 slots remaining).", ['feature' => $featureName])];
            }
        }

        return [
            'customer_id'     => $customerId,
            'role_context'    => $role,
            'user_package'    => $userPackage,
            'package_feature' => $packageFeature,
            'limit_record'    => $limitRecord,  // null if unlimited
            'remaining'       => $remaining,    // null if unlimited, int otherwise
        ];
    }

    /**
     * Parse an uploaded CSV and return all rows as an array.
     *
     * Normalises encoding before parsing so files saved by Excel on any OS
     * (UTF-16 LE/BE with BOM, UTF-8 with BOM, plain UTF-8) all work without
     * the user having to alter the file. Rows are eagerly collected so the
     * League\Csv Reader and its in-memory stream stay in scope for the full
     * read — preventing the "header record does not exist" error.
     */
    private function parseCsv($file): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($content === false || trim($content) === '') {
            throw new \RuntimeException('CSV file is empty or could not be read.');
        }

        // UTF-16 LE BOM — Excel on Mac / "Save as CSV UTF-16"
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }
        // UTF-16 BE BOM
        elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }
        // UTF-8 BOM — Windows Excel "Save as CSV UTF-8 (BOM)"
        elseif (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Drop any leading blank lines so the header is always at offset 0
        $content = ltrim($content, "\r\n");

        $csv = Reader::createFromString($content);
        $csv->setHeaderOffset(0);

        // Force eager read while $csv (and its stream) is still in scope
        $rows = iterator_to_array($csv->getRecords());

        // Filter out rows where every column value is empty (blank lines in CSV)
        return array_values(array_filter($rows, function ($row) {
            return array_filter(array_map('trim', $row)) !== [];
        }));
    }

    /**
     * Import property rows from the parsed CSV.
     *
     * @param  array|null  $ctx  Customer context from resolveCustomerContext(), or null for admin mode
     */
    private function importProperties(array $rows, ?array $ctx, int $contentLanguageId, int $countryId): array
    {
        $total    = 0;
        $imported = 0;
        $skipped  = [];

        $validCategoryIds = Category::pluck('id')->flip();
        $isCustomerMode   = ! is_null($ctx);

        // Country is fixed for the whole batch (selected once in the UI, same
        // as content language) — resolved once here instead of guessed per
        // row from coordinates, which was unreliable near international
        // borders (nearest-admin-country-by-centroid could pick a
        // neighboring country even for a point well inside the selected one).
        $country = Country::find($countryId);
        $resolvedCurrencyId = optional($country->resolvedCurrency())->id;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $total++;

            // Check quota before attempting each row
            if ($isCustomerMode && $ctx['remaining'] !== null && $ctx['remaining'] <= 0) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Package quota exhausted — no remaining property slots.')];
                continue;
            }

            $row = array_combine(
                array_map('trim', array_keys($row)),
                array_map('trim', array_values($row))
            );

            // Normalize case-insensitive enum fields before validation
            if (isset($row['property_type'])) {
                $row['property_type'] = strtolower(trim($row['property_type']));
            }

            if ($this->containsMaliciousContent($row)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Row contains potentially unsafe content (scripts or HTML code) and was rejected.')];
                continue;
            }

            $validator = Validator::make($row, [
                'title'         => 'required|string|max:255',
                'category_id'   => 'required|integer',
                'price'         => ['required', 'numeric', 'min:1', 'max:9223372036854775807'],
                'property_type' => 'required|in:0,1',
                'address'       => 'required|string',
                'latitude'      => 'required|numeric|between:-90,90',
                'longitude'     => 'required|numeric|between:-180,180',
            ], [
                'latitude.required'  => trans('Latitude is required.'),
                'latitude.numeric'   => trans('Latitude must be a number.'),
                'latitude.between'   => trans('Latitude must be between -90 and 90.'),
                'longitude.required' => trans('Longitude is required.'),
                'longitude.numeric'  => trans('Longitude must be a number.'),
                'longitude.between'  => trans('Longitude must be between -180 and 180.'),
            ]);

            if ($validator->fails()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $validator->errors()->first()];
                continue;
            }

            $latVal = isset($row['latitude'])  ? trim((string) $row['latitude'])  : '';
            $lonVal = isset($row['longitude']) ? trim((string) $row['longitude']) : '';
            if ($latVal !== '' && ! is_numeric($latVal)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Latitude must be a number.')];
                continue;
            }
            if ($lonVal !== '' && ! is_numeric($lonVal)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Longitude must be a number.')];
                continue;
            }
            if ($latVal !== '' && ((float) $latVal < -90 || (float) $latVal > 90)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Latitude must be between -90 and 90.')];
                continue;
            }
            if ($lonVal !== '' && ((float) $lonVal < -180 || (float) $lonVal > 180)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Longitude must be between -180 and 180.')];
                continue;
            }

            if (! isset($validCategoryIds[$row['category_id']])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Category ID :id does not exist.', ['id' => $row['category_id']])];
                continue;
            }

            if (isset($row['status']) && $row['status'] !== '' && ! in_array($row['status'], ['0', '1'])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Status must be 0 (Inactive) or 1 (Active).')];
                continue;
            }

            $rentdurationRaw = trim($row['rentduration'] ?? '');
            if ($row['property_type'] == 1 && $rentdurationRaw !== '') {
                $allowedDurations = ['Daily', 'Monthly', 'Yearly', 'Quarterly'];
                $normalized = ucfirst(strtolower($rentdurationRaw));
                if (! in_array($normalized, $allowedDurations)) {
                    $skipped[] = ['row' => $rowNumber, 'reason' => trans('Invalid rentduration ":value". Allowed values: Daily, Monthly, Yearly, Quarterly.', ['value' => $rentdurationRaw])];
                    continue;
                }
            }

            if (empty(trim($row['title_image'] ?? ''))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Title image is required.')];
                continue;
            }

            if (preg_match('/^https?:\/\//i', trim($row['title_image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('External image URLs are not allowed. Upload the image to the gallery and use the copied path instead.')];
                continue;
            }

            if (! preg_match('/^bulk-import-gallery\/[^\\/]+\.(jpg|jpeg|png|webp)$/i', trim($row['title_image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Invalid image path format. Expected: bulk-import-gallery/filename.jpg')];
                continue;
            }

            if (! Storage::disk('public')->exists(trim($row['title_image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Image file not found in gallery: :file', ['file' => trim($row['title_image'])])];
                continue;
            }

            $galleryImagePaths = $this->validateGalleryImagesColumn($row['gallery_images'] ?? '');
            if (isset($galleryImagePaths['error'])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $galleryImagePaths['error']];
                continue;
            }

            // Re-uploading the same (or an overlapping) CSV after a partial
            // failure would otherwise duplicate the rows that already
            // succeeded — the importer has no other memory of past runs.
            $addedBy = $isCustomerMode ? $ctx['customer_id'] : 0;
            if (Property::where('added_by', $addedBy)->where('title', $row['title'])->where('address', $row['address'])->exists()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('A property with the same title and address already exists for this account — possible duplicate, skipped.')];
                continue;
            }

            try {
                DB::beginTransaction();

                $property = new Property;
                $property->title        = $row['title'];
                $property->slug_id      = generateUniqueSlug($row['title'], 1);
                $property->category_id  = $row['category_id'];
                $property->description  = $row['description'] ?? '';
                $property->address      = $row['address'] ?? '';
                $property->propery_type = $row['property_type'];
                $rentduration = ucfirst(strtolower($row['rentduration'] ?? ''));
                $property->rentduration = ($row['property_type'] == 1 && empty($rentduration)) ? 'Monthly' : $rentduration;
                $property->price        = $row['price'];
                $property->city         = $row['city'] ?? '';
                $property->state        = $row['state'] ?? '';
                $property->country      = $country->name;
                $property->latitude     = $row['latitude'] ?? '';
                $property->longitude    = $row['longitude'] ?? '';
                $property->country_id   = $countryId;
                $property->currency_id  = $resolvedCurrencyId;
                $videoLink = trim($row['video_link'] ?? '');
                if (preg_match('/youtube\.com|youtu\.be/i', $videoLink)) {
                    $property->video_type = Property::VIDEO_YOUTUBE;
                    $property->video_link = $videoLink;
                } elseif (preg_match('/vimeo\.com/i', $videoLink)) {
                    $property->video_type = Property::VIDEO_VIMEO;
                    $property->video_link = $videoLink;
                } elseif (preg_match('/^bulk-import-gallery\/videos\//i', $videoLink)) {
                    $copiedVideo = $this->copyGalleryVideoTo($videoLink, config('global.PROPERTY_VIDEO_PATH'));
                    $property->video_type = Property::VIDEO_CUSTOM;
                    $property->video_link = $copiedVideo ?? '';
                } else {
                    $property->video_type = Property::VIDEO_CUSTOM;
                    $property->video_link = '';
                }
                $property->status       = isset($row['status']) && $row['status'] !== '' ? (int) $row['status'] : 1;
                $property->is_premium   = isset($row['is_premium']) && $row['is_premium'] === '1' ? 1 : 0;
                $property->meta_title       = $row['meta_title'] ?? $row['title'];
                $property->meta_description = $row['meta_description'] ?? '';
                $property->meta_keywords    = $row['meta_keywords'] ?? '';

                if ($isCustomerMode) {
                    $property->added_by       = $ctx['customer_id'];
                    $property->package_id     = $ctx['user_package']->package_id;
                    $property->post_type      = 1;
                    $property->request_status = 'approved';
                    $property->role_context   = $ctx['role_context'];
                    $property->expiry_date    = HelperService::calculateExpirationDate($ctx['customer_id']);
                } else {
                    $property->added_by       = 0;
                    $property->package_id     = 0;
                    $property->post_type      = 0;
                    $property->request_status = 'approved';
                    $property->role_context   = 'agent';
                }

                if (! empty($row['title_image'])) {
                    $filename = $this->copyGalleryImageTo($row['title_image'], config('global.PROPERTY_TITLE_IMG_PATH'));
                    if ($filename) {
                        $property->title_image = $filename;
                        $this->dispatchWatermark(
                            config('global.PROPERTY_TITLE_IMG_PATH').$filename,
                            $isCustomerMode ? $ctx['customer_id'] : null
                        );
                    }
                }

                $property->save();

                if (! empty($galleryImagePaths['paths'])) {
                    $this->savePropertyGalleryImages(
                        $property->id,
                        $galleryImagePaths['paths'],
                        $isCustomerMode ? $ctx['customer_id'] : null
                    );
                }

                // Save parameters (format: "param_id:value|param_id:value")
                if (! empty($row['parameters'])) {
                    $this->savePropertyParameters($property->id, $row['parameters']);
                }

                // Save nearby places (format: "facility_id:distance|facility_id:distance")
                if (! empty($row['nearby_places'])) {
                    $this->savePropertyFacilities($property->id, $row['nearby_places']);
                }

                // Deduct from customer's package quota
                if ($isCustomerMode && $ctx['limit_record'] !== null) {
                    $ctx['limit_record']->increment('used_limit');
                    $ctx['remaining']--;
                }

                DB::commit();
                $imported++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Property bulk import row {$rowNumber} failed: ".$e->getMessage());
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Database error: :error', ['error' => $e->getMessage()])];
            }
        }

        return compact('total', 'imported', 'skipped');
    }

    /**
     * Import project rows from the parsed CSV.
     *
     * @param  array|null  $ctx  Customer context from resolveCustomerContext(), or null for admin mode
     */
    private function importProjects(array $rows, ?array $ctx, int $contentLanguageId, int $countryId): array
    {
        $total    = 0;
        $imported = 0;
        $skipped  = [];

        $validCategoryIds = Category::pluck('id')->flip();
        $isCustomerMode   = ! is_null($ctx);

        // Country is fixed for the whole batch — see importProperties() for why.
        $country = Country::find($countryId);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $total++;

            if ($isCustomerMode && $ctx['remaining'] !== null && $ctx['remaining'] <= 0) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Package quota exhausted — no remaining project slots.')];
                continue;
            }

            $row = array_combine(
                array_map('trim', array_keys($row)),
                array_map('trim', array_values($row))
            );

            // Normalize case-insensitive enum fields before validation
            if (isset($row['project_type'])) {
                $row['project_type'] = strtolower(trim($row['project_type']));
            }

            if ($this->containsMaliciousContent($row)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Row contains potentially unsafe content (scripts or HTML code) and was rejected.')];
                continue;
            }

            $validator = Validator::make($row, [
                'title'        => 'required|string|max:255',
                'category_id'  => 'required|integer',
                'project_type' => 'required|in:under_construction,upcoming',
                'city'         => 'required|string',
                'state'        => 'required|string',
                'latitude'     => 'required|numeric|between:-90,90',
                'longitude'    => 'required|numeric|between:-180,180',
            ], [
                'latitude.required' => trans('Latitude is required.'),
                'latitude.numeric'  => trans('Latitude must be a number.'),
                'latitude.between'  => trans('Latitude must be between -90 and 90.'),
                'longitude.required' => trans('Longitude is required.'),
                'longitude.numeric' => trans('Longitude must be a number.'),
                'longitude.between' => trans('Longitude must be between -180 and 180.'),
            ]);

            if ($validator->fails()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $validator->errors()->first()];
                continue;
            }

            $latVal = isset($row['latitude'])  ? trim((string) $row['latitude'])  : '';
            $lonVal = isset($row['longitude']) ? trim((string) $row['longitude']) : '';
            if ($latVal !== '' && ! is_numeric($latVal)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Latitude must be a number.')];
                continue;
            }
            if ($lonVal !== '' && ! is_numeric($lonVal)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Longitude must be a number.')];
                continue;
            }
            if ($latVal !== '' && ((float) $latVal < -90 || (float) $latVal > 90)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Latitude must be between -90 and 90.')];
                continue;
            }
            if ($lonVal !== '' && ((float) $lonVal < -180 || (float) $lonVal > 180)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Longitude must be between -180 and 180.')];
                continue;
            }

            if (! isset($validCategoryIds[$row['category_id']])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Category ID :id does not exist.', ['id' => $row['category_id']])];
                continue;
            }

            if (isset($row['status']) && $row['status'] !== '' && ! in_array($row['status'], ['0', '1'])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Status must be 0 (Inactive) or 1 (Active).')];
                continue;
            }

            if (empty(trim($row['image'] ?? ''))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Title image is required.')];
                continue;
            }

            if (preg_match('/^https?:\/\//i', trim($row['image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('External image URLs are not allowed. Upload the image to the gallery and use the copied path instead.')];
                continue;
            }

            if (! preg_match('/^bulk-import-gallery\/[^\\/]+\.(jpg|jpeg|png|webp)$/i', trim($row['image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Invalid image path format. Expected: bulk-import-gallery/filename.jpg')];
                continue;
            }

            if (! Storage::disk('public')->exists(trim($row['image']))) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Image file not found in gallery: :file', ['file' => trim($row['image'])])];
                continue;
            }

            $galleryImagePaths = $this->validateGalleryImagesColumn($row['gallery_images'] ?? '');
            if (isset($galleryImagePaths['error'])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $galleryImagePaths['error']];
                continue;
            }

            // Re-uploading the same (or an overlapping) CSV after a partial
            // failure would otherwise duplicate the rows that already
            // succeeded — the importer has no other memory of past runs.
            $addedBy = $isCustomerMode ? $ctx['customer_id'] : null;
            if (Projects::where('added_by', $addedBy)->where('title', $row['title'])->where('location', $row['location'] ?? '')->exists()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('A project with the same title and location already exists for this account — possible duplicate, skipped.')];
                continue;
            }

            try {
                DB::beginTransaction();

                $project = new Projects;
                $project->title            = $row['title'];
                $project->slug_id          = generateUniqueSlug($row['title'], 4);
                $project->category_id      = $row['category_id'];
                $project->description      = $row['description'] ?? '';
                $project->location         = $row['location'] ?? '';
                $project->type             = $row['project_type'];
                $project->city             = $row['city'] ?? '';
                $project->state            = $row['state'] ?? '';
                $project->country          = $country->name;
                $project->latitude         = $row['latitude'] ?? '';
                $project->longitude        = $row['longitude'] ?? '';
                $project->country_id       = $countryId;
                $videoLink = trim($row['video_link'] ?? '');
                if (preg_match('/youtube\.com|youtu\.be/i', $videoLink)) {
                    $project->video_type = Projects::VIDEO_YOUTUBE;
                    $project->video_link = $videoLink;
                } elseif (preg_match('/vimeo\.com/i', $videoLink)) {
                    $project->video_type = Projects::VIDEO_VIMEO;
                    $project->video_link = $videoLink;
                } elseif (preg_match('/^bulk-import-gallery\/videos\//i', $videoLink)) {
                    $copiedVideo = $this->copyGalleryVideoTo($videoLink, config('global.PROJECT_VIDEO_PATH'));
                    $project->video_type = Projects::VIDEO_CUSTOM;
                    $project->video_link = $copiedVideo ?? '';
                } else {
                    $project->video_type = Projects::VIDEO_CUSTOM;
                    $project->video_link = '';
                }
                $project->status           = isset($row['status']) && $row['status'] !== '' ? (int) $row['status'] : 1;
                $project->is_premium       = isset($row['is_premium']) && $row['is_premium'] === '1' ? 1 : 0;
                $project->meta_title       = $row['meta_title'] ?? $row['title'];
                $project->meta_description = $row['meta_description'] ?? '';
                $project->meta_keywords    = $row['meta_keywords'] ?? '';

                if ($isCustomerMode) {
                    $project->added_by         = $ctx['customer_id'];
                    $project->is_admin_listing = false;
                    $project->request_status   = 'approved';
                    $project->role_context     = $ctx['role_context'];
                    $project->expiry_date      = HelperService::calculateExpirationDate($ctx['customer_id']);
                } else {
                    $project->added_by         = null;
                    $project->is_admin_listing = true;
                    $project->request_status   = 'approved';
                    $project->role_context     = 'agent';
                }

                if (! empty($row['image'])) {
                    $filename = $this->copyGalleryImageTo($row['image'], config('global.PROJECT_TITLE_IMG_PATH'));
                    if ($filename) {
                        $project->image = $filename;
                        $this->dispatchWatermark(
                            config('global.PROJECT_TITLE_IMG_PATH').$filename,
                            $isCustomerMode ? $ctx['customer_id'] : null
                        );
                    }
                }

                $project->save();

                if (! empty($galleryImagePaths['paths'])) {
                    $this->saveProjectGalleryImages(
                        $project->id,
                        $galleryImagePaths['paths'],
                        $isCustomerMode ? $ctx['customer_id'] : null
                    );
                }

                if ($isCustomerMode && $ctx['limit_record'] !== null) {
                    $ctx['limit_record']->increment('used_limit');
                    $ctx['remaining']--;
                }

                DB::commit();
                $imported++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Project bulk import row {$rowNumber} failed: ".$e->getMessage());
                $skipped[] = ['row' => $rowNumber, 'reason' => trans('Database error: :error', ['error' => $e->getMessage()])];
            }
        }

        return compact('total', 'imported', 'skipped');
    }

    /**
     * Resolve remaining quota for a given feature from a UserPackage.
     * Returns 'unlimited' string or remaining int.
     */
    private function resolveQuota(UserPackage $userPackage, int $featureId): string|int
    {
        $packageFeature = PackageFeature::where('package_id', $userPackage->package_id)
            ->where('feature_id', $featureId)
            ->first();

        if (! $packageFeature) {
            return 0;
        }

        if ($packageFeature->limit_type === 'unlimited') {
            return 'unlimited';
        }

        $limitRecord = UserPackageLimit::where('user_package_id', $userPackage->id)
            ->where('package_feature_id', $packageFeature->id)
            ->first();

        if (! $limitRecord) {
            return 0;
        }

        return max(0, $limitRecord->total_limit - $limitRecord->used_limit);
    }

    /**
     * Parse and save property parameters from the CSV value.
     * Format: "parameter_id:value|parameter_id:value"
     * Only number, textbox, and textarea types are supported via CSV.
     */
    private function savePropertyParameters(int $propertyId, string $raw): void
    {
        $validIds = Parameter::whereIn('type_of_parameter', ['number', 'textbox', 'textarea'])
            ->pluck('id')
            ->flip();

        foreach (explode('|', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            [$paramId, $value] = array_pad(explode(':', $pair, 2), 2, '');
            $paramId = (int) trim($paramId);
            $value   = trim($value);

            if ($paramId <= 0 || $value === '' || ! isset($validIds[$paramId])) {
                continue;
            }

            AssignParameters::create([
                'modal_type'   => Property::class,
                'modal_id'     => $propertyId,
                'property_id'  => 0,
                'parameter_id' => $paramId,
                'value'        => $value,
            ]);
        }
    }

    /**
     * Parse and save outdoor facility assignments from the CSV value.
     * Format: "facility_id:distance|facility_id:distance"
     */
    private function savePropertyFacilities(int $propertyId, string $raw): void
    {
        $validIds = OutdoorFacilities::pluck('id')->flip();

        foreach (explode('|', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            [$facilityId, $distance] = array_pad(explode(':', $pair, 2), 2, '');
            $facilityId = (int) trim($facilityId);
            $distance   = trim($distance);

            if ($facilityId <= 0 || ! isset($validIds[$facilityId])) {
                continue;
            }

            AssignedOutdoorFacilities::create([
                'facility_id' => $facilityId,
                'property_id' => $propertyId,
                'distance'    => $distance !== '' ? $distance : null,
            ]);
        }
    }

    /**
     * Validate the optional "gallery_images" CSV column: a pipe-separated
     * list of staged bulk-import-gallery paths, same format/rules as the
     * title_image column. Empty column is valid (no gallery images for
     * that row) — only present entries are checked.
     *
     * @return array{paths: string[]}|array{error: string}
     */
    private function validateGalleryImagesColumn(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['paths' => []];
        }

        $paths = array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($p) => $p !== ''));

        if (count($paths) > 10) {
            return ['error' => trans('A maximum of 10 gallery images is allowed per row.')];
        }

        foreach ($paths as $path) {
            if (preg_match('/^https?:\/\//i', $path)) {
                return ['error' => trans('External image URLs are not allowed. Upload the image to the gallery and use the copied path instead.')];
            }
            if (! preg_match('/^bulk-import-gallery\/[^\\/]+\.(jpg|jpeg|png|webp)$/i', $path)) {
                return ['error' => trans('Invalid gallery image path format. Expected: bulk-import-gallery/filename.jpg')];
            }
            if (! Storage::disk('public')->exists($path)) {
                return ['error' => trans('Gallery image file not found in gallery: :file', ['file' => $path])];
            }
        }

        return ['paths' => $paths];
    }

    /**
     * Copy each staged gallery image into the property's gallery folder and
     * bulk-insert the property_images rows — mirrors post_property()'s
     * gallery_images handling (PropertyApiController.php).
     *
     * @param  string[]  $paths
     */
    private function savePropertyGalleryImages(int $propertyId, array $paths, ?int $agentId): void
    {
        $destFolder = rtrim(config('global.PROPERTY_GALLERY_IMG_PATH'), '/').'/'.$propertyId.'/';
        $galleryImagesData = [];

        foreach ($paths as $path) {
            $filename = $this->copyGalleryImageTo($path, $destFolder);
            if (! $filename) {
                continue;
            }
            $galleryImagesData[] = [
                'propertys_id' => $propertyId,
                'image' => $filename,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $this->dispatchWatermark($destFolder.$filename, $agentId);
        }

        if (! empty($galleryImagesData)) {
            PropertyImages::insert($galleryImagesData);
        }
    }

    /**
     * Copy each staged gallery image into the project document folder and
     * bulk-insert the project_documents rows (type: image) — mirrors
     * ProjectApiController.php's gallery_images handling.
     *
     * @param  string[]  $paths
     */
    private function saveProjectGalleryImages(int $projectId, array $paths, ?int $agentId): void
    {
        $destFolder = config('global.PROJECT_DOCUMENT_PATH');
        $galleryImagesData = [];

        foreach ($paths as $path) {
            $filename = $this->copyGalleryImageTo($path, $destFolder);
            if (! $filename) {
                continue;
            }
            $galleryImagesData[] = [
                'project_id' => $projectId,
                'name' => $filename,
                'type' => 'image',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $this->dispatchWatermark($destFolder.$filename, $agentId);
        }

        if (! empty($galleryImagesData)) {
            ProjectDocuments::insert($galleryImagesData);
        }
    }

    /**
     * Copy an image from the bulk-import gallery to the target storage folder.
     * Returns the bare filename to store in the DB, or null if source not found.
     *
     * @param  string  $csvPath    e.g. "bulk-import-gallery/file.jpg"
     * @param  string  $destFolder e.g. "property_title_img/"
     */
    /**
     * Detect XSS / script injection in any string field of a CSV row.
     * Returns true if suspicious content is found.
     */
    private function containsMaliciousContent(array $row): bool
    {
        $patterns = [
            '/<script[\s\S]*?>/i',
            '/javascript\s*:/i',
            '/on\w+\s*=/i',          // onclick=, onload=, onerror=, etc.
            '/<\s*iframe/i',
            '/<\s*object/i',
            '/<\s*embed/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/vbscript\s*:/i',
            '/data\s*:\s*text\/html/i',
        ];

        foreach ($row as $value) {
            if (! is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function dispatchWatermark(string $relativeStoragePath, ?int $agentId): void
    {
        $watermarkConfig = HelperService::resolveListingWatermarkConfig($agentId);
        if (empty($watermarkConfig)) {
            return;
        }

        $absolutePath = storage_path('app/public/'.ltrim($relativeStoragePath, '/'));
        $extension    = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        AddWatermarkJob::dispatch($absolutePath, $extension, $watermarkConfig)->delay(now()->addSeconds(5));
    }

    private function copyGalleryImageTo(string $csvPath, string $destFolder): ?string
    {
        $filename = basename(ltrim($csvPath, '/'));
        $srcPath  = 'bulk-import-gallery/'.$filename;

        if (! Storage::disk('public')->exists($srcPath)) {
            return null;
        }

        $destPath = rtrim($destFolder, '/').'/'.$filename;

        if (! Storage::disk('public')->exists($destPath)) {
            Storage::disk('public')->copy($srcPath, $destPath);
        }

        return $filename;
    }

    private function copyGalleryVideoTo(string $csvPath, string $destFolder): ?string
    {
        $filename = basename($csvPath);
        $srcPath  = 'bulk-import-gallery/videos/'.$filename;

        if (! Storage::disk('public')->exists($srcPath)) {
            return null;
        }

        $destPath = rtrim($destFolder, '/').'/'.$filename;

        if (! Storage::disk('public')->exists($destPath)) {
            Storage::disk('public')->copy($srcPath, $destPath);
        }

        return $filename;
    }
}
