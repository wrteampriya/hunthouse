@extends('layouts.main')

@section('title')
    {{ __('Bulk Upload Projects') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('project.index') }}">{{ __('View Projects') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Bulk Upload Projects') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('css')
<style>
    .studio-dropzone {
        border: 2px dashed rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        padding: 20px 14px;
        text-align: center;
        background: rgba(0, 0, 0, 0.012);
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .studio-dropzone:hover {
        border-color: var(--bs-primary, #087C7C);
        background: rgba(8, 124, 124, 0.025);
    }
    .dropzone-icon-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(8, 124, 124, 0.1);
        color: var(--bs-primary, #087C7C);
        margin-bottom: 8px;
    }
</style>
@endsection

@section('content')
    <section class="section">

        {{-- Download Files & Reference Guide row --}}
        <div class="row">
            {{-- Left: Download Files & Image Gallery --}}
            <div class="col-12 col-lg-5">
                <div class="card">
                    <h3 class="card-header">{{ __('Download Files & Image Gallery') }}</h3>
                    <div class="card-body pt-3">
                        <div class="d-flex flex-column gap-3">
                            <div class="border rounded p-3 d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="bg-success bg-opacity-10 text-success rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                                        <i class="bi bi-file-earmark-spreadsheet" style="font-size:1.35rem;line-height:1;display:block;"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h6 class="mb-1 fw-bold text-truncate">{{ __('Example CSV File') }}</h6>
                                        <small class="text-muted">{{ __('Required format template') }}</small>
                                    </div>
                                </div>
                                <a href="{{ route('bulk-import.download.project') }}?v={{ file_exists(storage_path('app/example-csvs/project_example.csv')) ? filemtime(storage_path('app/example-csvs/project_example.csv')) : '1' }}" class="btn btn-success btn-sm flex-shrink-0">
                                    <i class="bi bi-download lh-1"></i><span class="d-none d-sm-inline ms-1">{{ __('Download') }}</span>
                                </a>
                            </div>

                            <div class="border rounded p-3 d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="bg-warning bg-opacity-10 text-warning rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                                        <i class="bi bi-tags" style="font-size:1.35rem;line-height:1;display:block;"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h6 class="mb-1 fw-bold text-truncate">{{ __('Categories Reference') }}</h6>
                                        <small class="text-muted">{{ __('Category IDs & names for CSV') }}</small>
                                    </div>
                                </div>
                                <a href="{{ route('bulk-import.download.categories') }}" class="btn btn-warning btn-sm flex-shrink-0">
                                    <i class="bi bi-download lh-1"></i><span class="d-none d-sm-inline ms-1">{{ __('Download') }}</span>
                                </a>
                            </div>

                            <div class="border rounded p-3 d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="bg-info bg-opacity-10 text-info rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                                        <i class="bi bi-images" style="font-size:1.35rem;line-height:1;display:block;"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h6 class="mb-1 fw-bold text-truncate">{{ __('Image Gallery') }}</h6>
                                        <small class="text-muted">{{ __('Upload & get image paths') }}</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-info text-white btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#galleryModal">
                                    <i class="bi bi-folder lh-1"></i><span class="d-none d-sm-inline ms-1">{{ __('Open Gallery') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Reference Guide --}}
            <div class="col-12 col-lg-7">
                <div class="card">
                    <h3 class="card-header">{{ __('Reference Guide') }}</h3>
                    <div class="card-body pt-3">
                        <ul class="nav nav-tabs mb-3" id="referenceTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-req-btn" data-bs-toggle="tab" data-bs-target="#tab-req" type="button" role="tab">{{ __('Instructions') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-format-btn" data-bs-toggle="tab" data-bs-target="#tab-format" type="button" role="tab">{{ __('Data Values') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-gallery-btn" data-bs-toggle="tab" data-bs-target="#tab-gallery" type="button" role="tab">{{ __('Gallery Guide') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-tips-btn" data-bs-toggle="tab" data-bs-target="#tab-tips" type="button" role="tab">{{ __('Tips') }}</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="referenceTabsContent">
                            {{-- Tab 1: Instructions --}}
                            <div class="tab-pane fade show active" id="tab-req" role="tabpanel">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>title:</strong> {{ __('Project listing title') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>category_id:</strong> {{ __('Valid category ID from your system') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>project_type:</strong> {{ __('under_construction or upcoming') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>city / state:</strong> {{ __('Required free-text location') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>latitude / longitude:</strong> {{ __('Required numeric coordinates (e.g. 28.5355 / 77.3910)') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('For images, upload via Image Gallery and copy the path') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Rows with invalid data will be skipped and reported') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-info-circle text-primary me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>slug:</strong> {{ __('Auto-generated from title — no need to add in CSV') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-info-circle text-primary me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span><strong>{{ __('Country') }}:</strong> {{ __('Selected once below for the whole file — not a CSV column. Every row is imported under that country.') }}</span>
                                    </li>
                                    <li class="mb-0 d-flex align-items-start">
                                        <i class="bi bi-info-circle text-primary me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('You can open CSV in Excel, edit it, and save. Excel will maintain CSV format.') }}</span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Tab 2: Data Values --}}
                            <div class="tab-pane fade" id="tab-format" role="tabpanel">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-primary flex-shrink-0" style="min-width:110px;text-align:center;">project_type</span>
                                        <span class="small"><code class="small">under_construction</code> | <code class="small">upcoming</code></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-primary flex-shrink-0" style="min-width:110px;text-align:center;">status</span>
                                        <span class="small">0 = {{ __('Inactive') }}, 1 = {{ __('Active') }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge flex-shrink-0" style="background-color:#fd7e14;min-width:110px;text-align:center;">category_id</span>
                                        <span class="small">{{ __('Must be a valid category ID from your system') }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-danger flex-shrink-0" style="min-width:110px;text-align:center;">is_premium</span>
                                        <span class="small">0 = {{ __('No') }}, 1 = {{ __('Yes') }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-info text-dark flex-shrink-0" style="min-width:110px;text-align:center;">image</span>
                                        <span class="small">{{ __('Exact path from Image Gallery') }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-info text-dark flex-shrink-0" style="min-width:110px;text-align:center;">gallery_images</span>
                                        <span class="small">{{ __('Optional. Up to 10 Image Gallery paths, separated by |') }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 p-2 rounded">
                                        <span class="badge bg-secondary flex-shrink-0" style="min-width:110px;text-align:center;">video_link</span>
                                        <span class="small">{{ __('YouTube/Vimeo URL or leave blank') }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Tab 3: Gallery Guide --}}
                            <div class="tab-pane fade" id="tab-gallery" role="tabpanel">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-arrow-right-circle text-info me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __("Click 'Open Image Gallery' button to open the gallery") }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-arrow-right-circle text-info me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Drag & drop or select images to upload (JPG, PNG, WebP)') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-arrow-right-circle text-info me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __("Click 'Upload Images' button, then click 'Copy Path' on any image") }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-arrow-right-circle text-info me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __("Paste the copied path into the CSV's image column") }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-arrow-right-circle text-info me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Format:') }} <code>bulk-import-gallery/filename.jpg</code></span>
                                    </li>
                                    <li class="mb-0 d-flex align-items-start">
                                        <i class="bi bi-exclamation-circle text-danger me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span class="text-danger">{{ __('External image URLs (http/https) are not accepted — only gallery paths work.') }}</span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Tab 4: Tips --}}
                            <div class="tab-pane fade" id="tab-tips" role="tabpanel">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-lightbulb text-warning me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Download the example CSV file first to understand the correct column format.') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-lightbulb text-warning me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Open CSV in Excel or Google Sheets, fill in your data, and save as CSV format.') }}</span>
                                    </li>
                                    <li class="mb-2 d-flex align-items-start">
                                        <i class="bi bi-lightbulb text-warning me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Rows with missing required fields or invalid data will be skipped and listed in the results.') }}</span>
                                    </li>
                                    <li class="mb-0 d-flex align-items-start">
                                        <i class="bi bi-lightbulb text-warning me-2 flex-shrink-0" style="font-size:15px;margin-top:2px;"></i>
                                        <span>{{ __('Upload images to the gallery first, then copy their paths into the CSV before uploading.') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upload & Process row (full width below) --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <h3 class="card-header">{{ __('Upload & Process CSV File') }}</h3>
                    <div class="card-body pt-3">
                        <form id="projectImportForm" enctype="multipart/form-data">
                            @csrf

                            {{-- Step 1: Upload Mode --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase ls-1 mb-2">{{ __('Step 1 — Upload Mode') }}</label>
                                <div class="d-flex flex-column flex-sm-row gap-3">
                                    <label for="modeAdmin" class="upload-mode-card flex-fill border rounded p-3 d-flex align-items-center gap-3" style="cursor:pointer;">
                                        <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="upload_mode" id="modeAdmin" value="admin" checked>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;">
                                                <i class="bi bi-shield-check text-primary" style="font-size:1.2rem;line-height:1;"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold">{{ __('Admin Listing') }}</div>
                                                <small class="text-muted">{{ __('Listed under admin account') }}</small>
                                            </div>
                                        </div>
                                    </label>
                                    <label for="modeCustomer" class="upload-mode-card flex-fill border rounded p-3 d-flex align-items-center gap-3" style="cursor:pointer;">
                                        <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="upload_mode" id="modeCustomer" value="customer">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-success bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;">
                                                <i class="bi bi-person-circle text-success" style="font-size:1.2rem;line-height:1;"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold">{{ __('For Customer') }}</div>
                                                <small class="text-muted">{{ __('Listed under a customer account') }}</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Step 2: Country & Content Language --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase ls-1 mb-2">{{ __('Step 2 — Country') }}</label>
                                @if ($countries->isEmpty())
                                    {{-- A required <select> with zero <option>s just silently blocks
                                         submission via native browser validation, with no indication
                                         why — tell the admin exactly what to do instead. --}}
                                    <div class="alert alert-warning mb-0">
                                        {{ __('No countries are configured yet.') }}
                                        <a href="{{ route('country.index') }}">{{ __('Add a country') }}</a>
                                        {{ __('before importing.') }}
                                    </div>
                                @else
                                    <select id="countrySelect" name="country_id" class="form-control" required style="max-width:320px;">
                                        <option value="">{{ __('Select Country') }}</option>
                                        @foreach($countries as $countryOption)
                                            <option value="{{ $countryOption->id }}">{{ $countryOption->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block mt-1">{{ __('Every listing in this CSV is imported under this country — not a per-row column.') }}</small>

                                    <div class="mt-3">
                                        <label class="form-label fw-semibold text-muted small text-uppercase ls-1 mb-2">{{ __('Content Language') }}</label>
                                        <select id="contentLanguageSelect" name="content_language_id" class="form-control" required style="max-width:320px;" disabled>
                                            <option value="">{{ __('Select a country first') }}</option>
                                            @foreach($languages as $language)
                                                <option value="{{ $language->id }}">{{ $language->name }}</option>
                                            @endforeach
                                        </select>
                                        <small id="contentLanguageHint" class="text-muted d-block mt-1">{{ __('Auto-selected from the country — the language your CSV title/description text must be written in.') }}</small>
                                    </div>
                                @endif
                            </div>

                            <div id="customerSection" class="mb-4 d-none">
                                <label class="form-label fw-semibold text-muted small text-uppercase ls-1 mb-2">{{ __('Step 3 — Select Customer') }}</label>
                                <div class="border rounded p-3">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">{{ __('Listing Role') }} <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-3">
                                            <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                                <input type="radio" name="customer_role" value="user" checked> {{ __('User') }}
                                            </label>
                                            <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                                <input type="radio" name="customer_role" value="agent"> {{ __('Agent') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">{{ __('Search Customer') }} <span class="text-danger">*</span></label>
                                        <select id="customerSelect" name="customer_id" class="form-control select2-customer" style="width:100%"></select>
                                    </div>

                                    <div id="customerInfoCard" class="d-none mt-2">
                                        <div id="packageInfo" class="d-none">
                                            <div class="alert alert-success py-2 px-3 mb-0 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-box-seam me-1"></i>
                                                    <strong id="pkgName"></strong>
                                                    <span class="text-muted small ms-2">{{ __('expires') }} <span id="pkgExpiry"></span></span>
                                                </div>
                                                <span class="badge bg-success" id="pkgQuota"></span>
                                            </div>
                                        </div>

                                        <div id="noPackageSection" class="d-none">
                                            <div class="alert alert-warning py-2 px-3 mb-2 d-flex align-items-center gap-2">
                                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="font-size:15px;line-height:1;"></i>
                                                <span>{{ __('No active package.') }}</span>
                                            </div>
                                            <div class="border rounded p-2 bg-light">
                                                <label class="form-label small fw-bold mb-1">{{ __('Assign Package') }}</label>
                                                <select id="inlinePackageSelect" class="form-control select2-package" style="width:100%"></select>
                                                <button type="button" id="assignPackageBtn" class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1 mt-2">
                                                    <i class="bi bi-plus-circle" style="font-size:13px;line-height:1;"></i>
                                                    <span>{{ __('Assign Package') }}</span>
                                                </button>
                                                <div id="assignProgress" class="mt-2 d-none">
                                                    <div class="progress" style="height:4px">
                                                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="customerInfoSpinner" class="text-center py-2 d-none">
                                        <div class="spinner-border spinner-border-sm text-primary"></div>
                                        <span class="ms-2 small text-muted">{{ __('Loading...') }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 3: CSV File --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small text-uppercase ls-1 mb-2" id="csvStepLabel">{{ __('Step 3 — Select CSV File') }}</label>
                                <div class="studio-dropzone" id="csvDropzone">
                                    <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv">
                                    <div id="dropzoneInstructions">
                                        <div class="dropzone-icon-box">
                                            <i class="fas fa-cloud-upload-alt fs-4"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">{{ __('Click to select or drag & drop your CSV file here') }}</h6>
                                        <p class="text-muted small mb-0">{{ __('Only .csv files are accepted') }}</p>
                                    </div>
                                    <div id="dropzonePreview" class="d-none d-flex justify-content-between align-items-center bg-white border rounded p-3 text-start">
                                        <div>
                                            <i class="bi bi-file-earmark-spreadsheet text-success" style="font-size:2rem;vertical-align:middle;line-height:1;margin-right:12px;"></i>
                                            <div style="display:inline-block;vertical-align:middle;">
                                                <span id="dropzoneFileName" class="fw-bold d-block"></span>
                                                <small id="dropzoneFileSize" class="text-muted"></small>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="removeFileBtn">
                                            <i class="bi bi-trash" style="line-height:1;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" id="importBtn" class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2 px-4">
                                    <i class="bi bi-cloud-upload" style="font-size:1.1rem;line-height:1;"></i>
                                    {{ __('Upload & Process') }}
                                </button>
                            </div>
                        </form>

                        {{-- Progress Section --}}
                        <div id="progressSection" class="mt-4 d-none">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">{{ __('Processing... please wait.') }}</p>
                        </div>

                        {{-- Results Section --}}
                        <div id="resultsSection" class="mt-4 d-none">
                            <hr>
                            <h5 class="fw-bold mb-3">{{ __('Upload Results') }}</h5>
                            <div class="d-flex flex-wrap gap-2 mb-3" id="resultSummary"></div>
                            <div id="skippedSection" class="d-none">
                                <h6 class="text-danger mb-2 d-flex align-items-center">
                                    <span class="bg-danger bg-opacity-10 text-danger rounded d-inline-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 26px; height: 26px;"><i class="bi bi-x-circle lh-1" style="font-size:13px;"></i></span>
                                    <span>{{ __('Skipped Rows') }}</span>
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th style="width: 80px;">{{ __('Row #') }}</th>
                                                <th>{{ __('Reason') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="skippedTableBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </section>

    {{-- Gallery Modal --}}
    <div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="galleryModalLabel">
                        <span class="bg-primary bg-opacity-10 text-primary rounded d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-images lh-1"></i></span>
                        <span>{{ __('Media Gallery') }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="galleryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-inline-flex align-items-center" id="media-images-tab" data-bs-toggle="tab" data-bs-target="#media-images-pane" type="button" role="tab">
                                <i class="bi bi-image me-1 lh-1"></i>{{ __('Images') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-inline-flex align-items-center" id="media-videos-tab" data-bs-toggle="tab" data-bs-target="#media-videos-pane" type="button" role="tab">
                                <i class="bi bi-camera-video me-1 lh-1"></i>{{ __('Videos') }}
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        {{-- Images tab --}}
                        <div class="tab-pane fade show active" id="media-images-pane" role="tabpanel">
                            <div class="border rounded p-3 mb-3 bg-light">
                                <input type="file" id="galleryUploadInput" multiple accept="image/jpg,image/jpeg,image/png,image/webp" class="filepond">
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="button" id="galleryUploadBtn" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-upload lh-1"></i>{{ __('Upload Images') }}
                                    </button>
                                </div>
                            </div>
                            <div id="uploadProgress" class="mb-3 d-none">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:100%"></div>
                                </div>
                                <small id="uploadProgressText" class="text-muted d-block mt-1"></small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">{{ __('Available Images') }}</h6>
                                <input type="text" id="gallerySearchInput" class="form-control form-control-sm" style="max-width:250px;" placeholder="{{ __('Search filename...') }}">
                            </div>
                            <div id="galleryGrid" class="row g-3">
                                <div class="col-12 text-center text-muted py-4" id="galleryEmpty">
                                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:54px;height:54px;">
                                        <i class="bi bi-image fs-3 lh-1"></i>
                                    </div>
                                    <span class="d-block">{{ __('No images uploaded yet.') }}</span>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" id="galleryLoadMoreBtn" class="btn btn-outline-secondary btn-sm d-none">{{ __('Load More') }}</button>
                            </div>
                        </div>
                        {{-- Videos tab --}}
                        <div class="tab-pane fade" id="media-videos-pane" role="tabpanel">
                            <div class="border rounded p-3 mb-3 bg-light">
                                <p class="small text-muted mb-2">{{ __('Supported formats: MP4, MOV, WebM. Max size: 100MB.') }}</p>
                                <input type="file" id="videoUploadInput" multiple accept="video/mp4,video/quicktime,video/webm" class="filepond">
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="button" id="videoUploadBtn" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-upload lh-1"></i>{{ __('Upload Videos') }}
                                    </button>
                                </div>
                            </div>
                            <div id="videoUploadProgress" class="mb-3 d-none">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:100%"></div>
                                </div>
                                <small id="videoUploadProgressText" class="text-muted d-block mt-1"></small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">{{ __('Available Videos') }}</h6>
                                <input type="text" id="videoSearchInput" class="form-control form-control-sm" style="max-width:250px;" placeholder="{{ __('Search filename...') }}">
                            </div>
                            <div id="videoGrid" class="row g-3">
                                <div class="col-12 text-center text-muted py-4" id="videoEmpty">
                                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:54px;height:54px;">
                                        <i class="bi bi-camera-video fs-3 lh-1"></i>
                                    </div>
                                    <span class="d-block">{{ __('No videos uploaded yet.') }}</span>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" id="videoLoadMoreBtn" class="btn btn-outline-secondary btn-sm d-none">{{ __('Load More') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Video preview — separate modal (not nested inside galleryModal) so its
         own header carries a properly-positioned close button, instead of a
         SweetAlert popup floating over the video with no real modal chrome. --}}
    <div class="modal fade" id="videoPreviewModal" tabindex="-1" aria-labelledby="videoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-black">
                <div class="modal-header border-0">
                    <h5 class="modal-title d-flex align-items-center gap-2 text-white" id="videoPreviewModalLabel">
                        <span class="bg-white bg-opacity-10 text-white rounded d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-play-circle lh-1"></i></span>
                        <span id="videoPreviewName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-0">
                    <video id="videoPreviewPlayer" controls style="max-width:100%;max-height:70vh;"></video>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    'use strict';

    const GALLERY_INDEX_URL        = "{{ route('bulk-import.gallery.index') }}";
    const GALLERY_UPLOAD_URL       = "{{ route('bulk-import.gallery.upload') }}";
    const GALLERY_VIDEO_UPLOAD_URL = "{{ route('bulk-import.gallery.upload-video') }}";
    const GALLERY_DELETE_URL       = "{{ route('bulk-import.gallery.destroy') }}";
    const IMPORT_URL          = "{{ route('bulk-import.process.project') }}";
    const CUSTOMER_INFO_URL   = "{{ route('bulk-import.customer.info') }}";
    const ASSIGN_PACKAGE_URL  = "{{ route('assign-package.store') }}";
    const CUSTOMERS_URL       = "{{ route('bulk-import.customer.search') }}";
    const PACKAGES_URL        = "{{ route('select2.packages') }}";
    const CSRF                = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const THEME_COLOR         = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#087C7C';
    const THEME_DARK          = '#343a40';

    // Country -> required content language, same mapping used on the
    // project create/edit forms — picking a country auto-selects the
    // language the whole batch's title/description must be written in.
    const COUNTRY_LANGUAGE_MAP = @json($countries->mapWithKeys(fn ($country) => [$country->id => optional($country->resolvedLanguage())->id]));

    $('#countrySelect').on('change', function () {
        const countryId = $(this).val();
        const $lang = $('#contentLanguageSelect');
        const $hint = $('#contentLanguageHint');
        if (!countryId) {
            $lang.prop('disabled', true).val('');
            $lang.find('option').prop('disabled', false);
            $hint.text('{{ __("Auto-selected from the country — the language your CSV title/description text must be written in.") }}');
            return;
        }
        const languageId = COUNTRY_LANGUAGE_MAP[countryId];
        $lang.prop('disabled', false);
        if (languageId) {
            $lang.val(languageId);
            // The server only accepts this one language for the selected
            // country (validateBatchLanguageForCountry) — disable the rest so
            // the dropdown doesn't look freely choosable when it isn't.
            const languageName = $lang.find(`option[value="${languageId}"]`).text();
            $lang.find('option').each(function () {
                const optVal = $(this).val();
                $(this).prop('disabled', optVal !== '' && optVal != languageId);
            });
            $hint.text('{{ __("This country requires content in") }} ' + languageName + '.');
        } else {
            $lang.find('option').prop('disabled', false);
        }
    });

    function toast(msg, ok = true) {
        Toastify({
            text: msg,
            duration: 4000,
            close: true,
            gravity: 'top',
            position: 'right',
            backgroundColor: ok ? 'linear-gradient(to right,#00b09b,#96c93d)' : '#dc3545',
        }).showToast();
    }

    let customerHasPackage = false;

    function getSelectedRole() {
        const checked = document.querySelector('input[name="customer_role"]:checked');
        return checked ? checked.value : 'user';
    }

    function reinitCustomerSelect() {
        const role = getSelectedRole();
        const $sel = $('#customerSelect');
        if ($sel.data('select2')) $sel.select2('destroy');
        $sel.val(null).empty();
        document.getElementById('customerInfoCard').classList.add('d-none');
        customerHasPackage = false;
        $sel.select2({
            placeholder: '{{ __("Search by name, email or ID...") }}',
            allowClear: true,
            width: '100%',
            theme: 'bootstrap-5',
            ajax: {
                url: CUSTOMERS_URL,
                dataType: 'json',
                delay: 250,
                cache: false,
                data: params => ({ q: params.term || '', page: params.page || 1, per_page: 20, role: getSelectedRole() }),
                processResults: data => ({ results: data.results || [], pagination: { more: !!(data.pagination && data.pagination.more) } }),
            },
            minimumInputLength: 0,
        });
        $sel.on('change', function () {
            const customerId = $(this).val();
            customerHasPackage = false;
            if (!customerId) {
                document.getElementById('customerInfoCard').classList.add('d-none');
                return;
            }
            loadCustomerInfo(customerId);
        });
    }

    // Upload mode toggle
    document.querySelectorAll('input[name="upload_mode"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const customerSection = document.getElementById('customerSection');
            const csvStepLabel = document.getElementById('csvStepLabel');
            if (this.value === 'customer') {
                customerSection.classList.remove('d-none');
                if (csvStepLabel) csvStepLabel.textContent = '{{ __("Step 4 — Select CSV File") }}';
            } else {
                customerSection.classList.add('d-none');
                customerHasPackage = false;
                if (csvStepLabel) csvStepLabel.textContent = '{{ __("Step 3 — Select CSV File") }}';
            }
        });
    });

    document.querySelectorAll('input[name="customer_role"]').forEach(radio => {
        radio.addEventListener('change', function () {
            reinitCustomerSelect();
            resetInlinePackageSelect();
        });
    });

    // Dropzone logic
    const dropzone      = document.getElementById('csvDropzone');
    const fileInput     = document.getElementById('csv_file');
    const preview       = document.getElementById('dropzonePreview');
    const fileName      = document.getElementById('dropzoneFileName');
    const fileSize      = document.getElementById('dropzoneFileSize');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const instructions  = document.getElementById('dropzoneInstructions');

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function updateFileDisplay() {
        if (fileInput.files && fileInput.files.length > 0) {
            const file = fileInput.files[0];
            fileName.textContent = file.name;
            fileSize.textContent = formatBytes(file.size);
            instructions.classList.add('d-none');
            preview.classList.remove('d-none');
        } else {
            instructions.classList.remove('d-none');
            preview.classList.add('d-none');
        }
    }

    dropzone.addEventListener('click', (e) => {
        if (e.target.closest('#removeFileBtn') || e.target.closest('#dropzonePreview')) return;
        fileInput.click();
    });

    fileInput.addEventListener('change', updateFileDisplay);

    removeFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.value = '';
        updateFileDisplay();
    });

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '#435ebe';
        dropzone.style.backgroundColor = 'rgba(67, 94, 190, 0.04)';
    });

    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '';
        dropzone.style.backgroundColor = '';
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '';
        dropzone.style.backgroundColor = '';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            updateFileDisplay();
        }
    });

    // Select2: customer search (initialised on page load; re-initialised on role change)
    $(function () { reinitCustomerSelect(); });

    function loadCustomerInfo(customerId) {
        const card    = document.getElementById('customerInfoCard');
        const spinner = document.getElementById('customerInfoSpinner');
        const pkgInfo = document.getElementById('packageInfo');
        const noPkg   = document.getElementById('noPackageSection');

        card.classList.remove('d-none');
        spinner.classList.remove('d-none');
        pkgInfo.classList.add('d-none');
        noPkg.classList.add('d-none');

        fetch(CUSTOMER_INFO_URL + '?customer_id=' + customerId + '&role=' + getSelectedRole(), {
            headers: { 'X-CSRF-TOKEN': CSRF },
        })
        .then(r => r.json())
        .then(data => {
            spinner.classList.add('d-none');

            if (data.error) {
                toast(data.error, false);
                return;
            }

            if (data.has_package) {
                customerHasPackage = true;
                const quota = data.quota.project === 'unlimited'
                    ? '{{ __("Unlimited") }}'
                    : data.quota.project + ' {{ __("slots remaining") }}';

                document.getElementById('pkgName').textContent   = data.package.name;
                document.getElementById('pkgExpiry').textContent = data.package.end_date;
                document.getElementById('pkgQuota').textContent  = quota;
                pkgInfo.classList.remove('d-none');
            } else {
                customerHasPackage = false;
                noPkg.classList.remove('d-none');
                initInlinePackageSelect();
            }
        })
        .catch(() => {
            spinner.classList.add('d-none');
            toast('{{ __("Failed to load customer info.") }}', false);
        });
    }

    function resetInlinePackageSelect() {
        const $pkg = $('#inlinePackageSelect');
        if ($pkg.data('select2')) { $pkg.select2('destroy'); }
        $pkg.val(null).empty();
    }

    function initInlinePackageSelect() {
        const role = getSelectedRole();
        const $pkg = $('#inlinePackageSelect');
        if ($pkg.data('select2')) return;
        $pkg.select2({
            placeholder: '{{ __("Search package...") }}',
            allowClear: true,
            width: '100%',
            theme: 'bootstrap-5',
            ajax: {
                url: PACKAGES_URL,
                dataType: 'json',
                delay: 250,
                cache: false,
                data: params => ({ q: params.term || '', page: params.page || 1, per_page: 20, type: role }),
                processResults: data => ({ results: data.results || [], pagination: { more: !!(data.pagination && data.pagination.more) } }),
            },
            minimumInputLength: 0,
        });
    }

    document.getElementById('assignPackageBtn').addEventListener('click', function () {
        const customerId = $('#customerSelect').val();
        const packageId  = $('#inlinePackageSelect').val();

        if (!customerId || !packageId) {
            toast('{{ __("Please select a package.") }}', false);
            return;
        }

        const btn      = this;
        const progress = document.getElementById('assignProgress');

        function doAssign(force) {
            btn.disabled = true;
            progress.classList.remove('d-none');

            fetch(ASSIGN_PACKAGE_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_id: customerId, package_id: packageId, force_assign: force }),
            })
            .then(r => r.json())
            .then(data => {
                progress.classList.add('d-none');
                btn.disabled = false;

                if (data.status === false || data.error) {
                    toast(data.message || data.error || '{{ __("Failed to assign package.") }}', false);
                    return;
                }
                if (data.data && data.data.confirm_required) {
                    Swal.fire({
                        title: '{{ __("Already has a package") }}',
                        text: data.message || '{{ __("Customer already has an active package. Assign anyway?") }}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: THEME_COLOR,
                        cancelButtonColor: THEME_DARK,
                        confirmButtonText: '{{ __("Yes, Assign") }}',
                        cancelButtonText: '{{ __("Cancel") }}',
                        reverseButtons: true,
                    }).then(result => {
                        if (result.isConfirmed) doAssign(true);
                    });
                    return;
                }
                toast('{{ __("Package assigned successfully.") }}', true);
                loadCustomerInfo(customerId);
            })
            .catch(() => {
                progress.classList.add('d-none');
                btn.disabled = false;
                toast('{{ __("Failed to assign package.") }}', false);
            });
        }

        doAssign(false);
    });

    document.getElementById('projectImportForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const mode       = document.querySelector('input[name="upload_mode"]:checked').value;
        const customerId = $('#customerSelect').val();
        const fileInput  = document.getElementById('csv_file');

        if (!$('#countrySelect').val()) {
            toast('{{ __("Please select a country.") }}', false);
            return;
        }

        if (!fileInput.files.length) {
            toast('{{ __("Please select a CSV file.") }}', false);
            return;
        }

        if (mode === 'customer' && !customerId) {
            toast('{{ __("Please select a customer.") }}', false);
            return;
        }

        if (mode === 'customer' && !customerHasPackage) {
            toast('{{ __("Please assign a package to the customer before uploading.") }}', false);
            return;
        }

        const formData = new FormData(this);
        if (mode === 'customer') {
            formData.set('customer_id', customerId);
        }

        document.getElementById('progressSection').classList.remove('d-none');
        document.getElementById('resultsSection').classList.add('d-none');
        const submitBtn = document.getElementById('importBtn');
        const originalBtnHTML = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("Processing...") }}';

        fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: formData,
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('progressSection').classList.add('d-none');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHTML;

            if (data.error) {
                toast(data.error, false);
                return;
            }

            renderResults(data);
            const msg = data.imported + ' {{ __("projects uploaded successfully.") }}'
                      + (data.skipped.length ? ' ' + data.skipped.length + ' {{ __("skipped.") }}' : '');
            toast(msg, data.imported > 0);

            if (data.imported > 0) {
                Swal.fire({
                    icon: 'info',
                    title: '{{ __("Complete Project Details") }}',
                    html: `<p class="text-start mb-2">{{ __("The following fields were not included in the CSV. Please complete them manually for each uploaded project:") }}</p>
                           <ul class="text-start ps-3 mb-0">
                               <li><strong>{{ __("Parameters") }}</strong> — {{ __("e.g. Bedrooms, Bathrooms, Area") }}</li>
                               <li><strong>{{ __("Nearby Places") }}</strong> — {{ __("e.g. Hospital, School distances") }}</li>
                           </ul>`,
                    confirmButtonText: '{{ __("Go to Projects") }}',
                    showCancelButton: true,
                    cancelButtonText: '{{ __("Stay Here") }}',
                    confirmButtonColor: THEME_COLOR,
                    cancelButtonColor: THEME_DARK,
                }).then(result => {
                    if (result.isConfirmed) {
                        window.location.href = '{{ route("project.index") }}';
                    }
                });
            }

            if (mode === 'customer' && customerId) {
                loadCustomerInfo(customerId);
            }
        })
        .catch(() => {
            document.getElementById('progressSection').classList.add('d-none');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHTML;
            toast('{{ __("An unexpected error occurred. Please try again.") }}', false);
        });
    });

    function renderResults(data) {
        document.getElementById('resultsSection').classList.remove('d-none');
        document.getElementById('resultSummary').innerHTML = `
            <span class="badge bg-secondary fs-6 px-3 py-2">{{ __('Total') }}: ${data.total}</span>
            <span class="badge bg-success fs-6 px-3 py-2">{{ __('Uploaded') }}: ${data.imported}</span>
            <span class="badge bg-danger fs-6 px-3 py-2">{{ __('Skipped') }}: ${data.skipped.length}</span>
        `;
        const skippedSection = document.getElementById('skippedSection');
        const tbody          = document.getElementById('skippedTableBody');
        if (data.skipped.length > 0) {
            tbody.innerHTML = data.skipped.map(s =>
                `<tr>
                    <td class="text-center">${s.row}</td>
                    <td>${s.reason}</td>
                </tr>`
            ).join('');
            skippedSection.classList.remove('d-none');
        } else {
            skippedSection.classList.add('d-none');
        }
    }

    const galleryState = { page: 1, lastPage: 1, loading: false };
    const videoState   = { page: 1, lastPage: 1, loading: false };
    let gallerySearchDebounce, videoSearchDebounce;

    function fetchMedia(type, page, search) {
        const params = new URLSearchParams({ type, page, search: search || '' });
        return fetch(`${GALLERY_INDEX_URL}?${params.toString()}`).then(res => res.json());
    }

    function loadGallery() {
        galleryState.page = 1;
        videoState.page = 1;
        loadImagesPage(1, false);
        loadVideosPage(1, false);
    }

    function loadImagesPage(page, append) {
        if (galleryState.loading) return;
        galleryState.loading = true;
        const search = document.getElementById('gallerySearchInput').value || '';
        fetchMedia('images', page, search).then(data => {
            galleryState.page = data.current_page;
            galleryState.lastPage = data.last_page;
            galleryState.loading = false;
            renderGallery(data.data || [], append);
            document.getElementById('galleryLoadMoreBtn').classList.toggle('d-none', galleryState.page >= galleryState.lastPage);
        });
    }

    function loadVideosPage(page, append) {
        if (videoState.loading) return;
        videoState.loading = true;
        const search = document.getElementById('videoSearchInput').value || '';
        fetchMedia('videos', page, search).then(data => {
            videoState.page = data.current_page;
            videoState.lastPage = data.last_page;
            videoState.loading = false;
            renderVideoGallery(data.data || [], append);
            document.getElementById('videoLoadMoreBtn').classList.toggle('d-none', videoState.page >= videoState.lastPage);
        });
    }

    document.getElementById('gallerySearchInput').addEventListener('input', function () {
        clearTimeout(gallerySearchDebounce);
        gallerySearchDebounce = setTimeout(() => loadImagesPage(1, false), 300);
    });
    document.getElementById('videoSearchInput').addEventListener('input', function () {
        clearTimeout(videoSearchDebounce);
        videoSearchDebounce = setTimeout(() => loadVideosPage(1, false), 300);
    });
    document.getElementById('galleryLoadMoreBtn').addEventListener('click', function () {
        loadImagesPage(galleryState.page + 1, true);
    });
    document.getElementById('videoLoadMoreBtn').addEventListener('click', function () {
        loadVideosPage(videoState.page + 1, true);
    });

    function bindGalleryDelegation(grid, label, onDeleted) {
        grid.addEventListener('click', function (e) {
            const playThumb = e.target.closest('.video-play-thumb');
            if (playThumb) {
                const player = document.getElementById('videoPreviewPlayer');
                player.src = playThumb.dataset.url;
                document.getElementById('videoPreviewName').textContent = playThumb.dataset.name || '';

                // Two Bootstrap modals open at once fight over the same
                // backdrop/z-index layer (washed-out header, invisible
                // close button, no dimming) — hide the gallery modal first
                // so only the video modal is ever showing on its own.
                bootstrap.Modal.getInstance(document.getElementById('galleryModal'))?.hide();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('videoPreviewModal')).show();
                player.play().catch(() => {});
                return;
            }

            const copyBtn = e.target.closest('.copy-path-btn');
            if (copyBtn) {
                navigator.clipboard.writeText(copyBtn.dataset.path).then(() => {
                    const original = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check text-success me-1"></i>{{ __("Copied!") }}';
                    setTimeout(() => { copyBtn.innerHTML = original; }, 2000);
                });
                return;
            }

            const deleteBtn = e.target.closest('.delete-media-btn');
            if (deleteBtn) {
                const path = deleteBtn.dataset.path;
                const name = deleteBtn.dataset.name;
                Swal.fire({
                    title: `{{ __("Delete this") }} ${label}?`,
                    text: name,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '{{ __("Yes, Delete") }}',
                    cancelButtonText: '{{ __("Cancel") }}',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    fetch(GALLERY_DELETE_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ path }),
                    })
                    .then(res => res.json())
                    .then(() => {
                        toast(`${label} {{ __("deleted.") }}`, true);
                        onDeleted();
                    })
                    .catch(() => toast(`{{ __("Failed to delete") }} ${label}.`, false));
                });
            }
        });
    }

    bindGalleryDelegation(document.getElementById('galleryGrid'), '{{ __("image") }}', () => loadImagesPage(1, false));
    bindGalleryDelegation(document.getElementById('videoGrid'), '{{ __("video") }}', () => loadVideosPage(1, false));

    function renderGallery(images, append) {
        const grid  = document.getElementById('galleryGrid');
        const empty = document.getElementById('galleryEmpty');

        if (!append) {
            grid.querySelectorAll('.gallery-card').forEach(el => el.remove());
        }

        if (!images.length) {
            if (!append) empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');
        images.forEach(img => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-3 gallery-card';
            col.innerHTML = `
                <div class="card h-100 mb-0 border">
                    <img src="${img.url}" class="card-img-top" style="height:120px;object-fit:cover;" alt="${img.name}">
                    <div class="card-body p-2">
                        <p class="small text-truncate mb-2" title="${img.name}">${img.name}</p>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary flex-fill copy-path-btn" data-path="${img.path}">
                                <i class="bi bi-clipboard me-1 lh-1"></i>{{ __('Copy Path') }}
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-media-btn" data-path="${img.path}" data-name="${img.name}" title="{{ __('Delete') }}">
                                <i class="bi bi-trash lh-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(col);
        });
    }

    function renderVideoGallery(videos, append) {
        const grid  = document.getElementById('videoGrid');
        const empty = document.getElementById('videoEmpty');

        if (!append) {
            grid.querySelectorAll('.gallery-card').forEach(el => el.remove());
        }

        if (!videos.length) {
            if (!append) empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');
        videos.forEach(v => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-3 gallery-card';
            col.innerHTML = `
                <div class="card h-100 mb-0 border">
                    <div class="video-play-thumb d-flex align-items-center justify-content-center bg-dark bg-opacity-10" style="height:120px;cursor:pointer;" data-url="${v.url}" data-name="${v.name}" title="{{ __('Play video') }}">
                        <i class="bi bi-play-circle text-secondary" style="font-size:2.5rem;"></i>
                    </div>
                    <div class="card-body p-2">
                        <p class="small text-truncate mb-2" title="${v.name}">${v.name}</p>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary flex-fill copy-path-btn" data-path="${v.path}">
                                <i class="bi bi-clipboard me-1 lh-1"></i>{{ __('Copy Path') }}
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-media-btn" data-path="${v.path}" data-name="${v.name}" title="{{ __('Delete') }}">
                                <i class="bi bi-trash lh-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(col);
        });
    }

    // Uploads files in small sequential batches instead of one giant request,
    // so a large selection can't blow past PHP's post_max_size/upload_max_filesize
    // and a single slow/failed chunk doesn't lose progress on the chunks
    // already uploaded successfully.
    function uploadInChunks({ files, chunkSize, url, fieldName, progressEl, progressTextEl, button }) {
        const chunks = [];
        for (let i = 0; i < files.length; i += chunkSize) {
            chunks.push(files.slice(i, i + chunkSize));
        }

        progressEl.classList.remove('d-none');
        button.disabled = true;
        let uploadedCount = 0;

        function uploadNext(index) {
            if (index >= chunks.length) {
                progressEl.classList.add('d-none');
                progressTextEl.textContent = '';
                button.disabled = false;
                return Promise.resolve({ ok: true, uploadedCount });
            }

            const chunk = chunks[index];
            progressTextEl.textContent = '{{ __("Uploading") }} '
                + (uploadedCount + 1) + '-' + (uploadedCount + chunk.length) + ' / ' + files.length + '...';

            const formData = new FormData();
            chunk.forEach(file => formData.append(fieldName, file));

            return fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    progressEl.classList.add('d-none');
                    button.disabled = false;
                    return { ok: false, error: data.error, uploadedCount };
                }
                uploadedCount += chunk.length;
                return uploadNext(index + 1);
            });
        }

        return uploadNext(0);
    }

    document.getElementById('galleryUploadBtn').addEventListener('click', function () {
        const pond = FilePond.find(document.getElementById('galleryUploadInput'));
        const files = pond ? pond.getFiles().map(f => f.file) : [];
        if (!files.length) {
            toast('{{ __("Please select at least one image.") }}', false);
            return;
        }

        uploadInChunks({
            files,
            chunkSize: 10,
            url: GALLERY_UPLOAD_URL,
            fieldName: 'images[]',
            progressEl: document.getElementById('uploadProgress'),
            progressTextEl: document.getElementById('uploadProgressText'),
            button: this,
        })
        .then(result => {
            if (!result.ok) {
                toast(result.error, false);
                return;
            }
            toast('{{ __("Images uploaded successfully.") }}', true);
            const pond = FilePond.find(document.getElementById('galleryUploadInput'));
            if (pond) pond.removeFiles();
            loadGallery();
        })
        .catch(() => {
            document.getElementById('uploadProgress').classList.add('d-none');
            this.disabled = false;
            toast('{{ __("Upload failed. Please try again.") }}', false);
        });
    });

    document.getElementById('videoUploadBtn').addEventListener('click', function () {
        const pond = FilePond.find(document.getElementById('videoUploadInput'));
        const files = pond ? pond.getFiles().map(f => f.file) : [];
        if (!files.length) {
            toast('{{ __("Please select at least one video.") }}', false);
            return;
        }

        uploadInChunks({
            files,
            chunkSize: 2,
            url: GALLERY_VIDEO_UPLOAD_URL,
            fieldName: 'videos[]',
            progressEl: document.getElementById('videoUploadProgress'),
            progressTextEl: document.getElementById('videoUploadProgressText'),
            button: this,
        })
        .then(result => {
            if (!result.ok) {
                toast(result.error, false);
                return;
            }
            toast('{{ __("Videos uploaded successfully.") }}', true);
            const pond = FilePond.find(document.getElementById('videoUploadInput'));
            if (pond) pond.removeFiles();
            document.getElementById('media-videos-tab').click();
            loadGallery();
        })
        .catch(() => {
            document.getElementById('videoUploadProgress').classList.add('d-none');
            this.disabled = false;
            toast('{{ __("Upload failed. Please try again.") }}', false);
        });
    });

    document.getElementById('galleryModal').addEventListener('show.bs.modal', loadGallery);

    document.getElementById('videoPreviewModal').addEventListener('hidden.bs.modal', function () {
        const player = document.getElementById('videoPreviewPlayer');
        player.pause();
        player.src = '';
        // Restore the gallery modal we hid to open this one on its own.
        bootstrap.Modal.getOrCreateInstance(document.getElementById('galleryModal')).show();
    });

})();
</script>
@endsection
