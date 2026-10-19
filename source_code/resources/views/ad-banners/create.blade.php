@extends('layouts.main')

@section('title')
    {{ __('Create New Advertisement') }}
@endsection

@section('css')
<style>
    .ad-stepper{display:flex;align-items:center;gap:28px;flex-wrap:wrap}
    .ad-step{display:flex;align-items:center;gap:12px;color:#6c757d}
    .ad-step .badge-round{width:44px;height:44px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:600;background:#e9ecef;color:#6c757d}
    .ad-step .label{font-weight:600}
    .ad-step.is-active .badge-round{background:var(--bs-primary);color:#fff}
    .ad-step.is-active{color:var(--bs-primary)}
    .ad-step.is-complete .badge-round{background:var(--bs-primary);color:#fff;opacity:.7}
    .ad-stepper .connector{flex:1 1 80px;height:4px;background:#e9ecef;border-radius:999px}
    .ad-stepper .connector.is-complete{background:var(--bs-primary)}
    @media (max-width: 576px){
      .ad-stepper{gap:12px;justify-content:space-between;align-items:center;width:100%}
      .ad-step{gap:8px;min-width:0}
      .ad-step .label{display:none}
      .ad-stepper .connector{min-width:40px;max-width:60px;height:4px}
    }
    .wizard-actions{display:flex;justify-content:flex-end;gap:10px;margin-block:1rem}
    .muted-help{color:#6c757d}
    .rounded-card{border-radius:14px}
    .header-actions .btn-link{color:#6c757d}
    .header-actions .btn-link:hover{color:#0d6efd}
    .ad-section-title{margin-bottom:.25rem}
    .ad-section-sub{margin-top:0;color:#6c757d}
    .size-hint{font-size:.875rem}
    .preview-section{background:#f8f9fa;border-radius:8px;padding:1.5rem;margin-top:1rem}
    .preview-item{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #dee2e6}
    .preview-item:last-child{border-bottom:none}
    .preview-label{font-weight:600;color:#495057}
    .preview-value{color:#6c757d}
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1 1 auto;
        min-width: 0;
        padding-right: 20px;
    }
    .select2-container--bootstrap-5 .select2-selection--single {
        display: flex;
        align-items: center;
    }
    .select2-container--bootstrap-5 .select2-selection__clear {
        flex-shrink: 0;
        margin-left: auto;
        margin-right: 0;
    }
</style>
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4 class="ad-section-title">@yield('title')</h4>
                <p class="ad-section-sub">{{ __('Follow the steps to create your advertisement banner') }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="header-actions d-flex justify-content-end align-items-start gap-2">
                    <a href="{{ route('ad-banners.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
<section class="section">
    <div class="card rounded-card">
        <div class="card-body">
            <div class="ad-stepper mb-4" id="adStepper">
                <div class="ad-step is-active" data-step="1">
                    <span class="badge-round">1</span>
                    <span class="label">{{ __('Page, Type & Placement') }}</span>
                </div>
                <div class="connector"></div>
                <div class="ad-step" data-step="2">
                    <span class="badge-round">2</span>
                    <span class="label">{{ __('Upload Banner') }}</span>
                </div>
                <div class="connector"></div>
                <div class="ad-step" data-step="3">
                    <span class="badge-round">3</span>
                    <span class="label">{{ __('Ad Type & Link') }}</span>
                </div>
            </div>
                {!! Form::open(['route' => 'ad-banners.store', 'id' => 'create-form', 'files' => true, 'class' => 'create-form', 'data-success-function' => 'successFunction']) !!}
                    <div class="tab-content">
                        {{-- Step 1 --}}
                        <div class="tab-pane fade show active" id="step1">
                            {{-- Page --}}
                            <div class="mb-3 form-group" id="group-page">
                                <label class="form-label">{{ __('Select Page') }}</label>
                                <select class="form-select" name="page" id="page" required>
                                    <option value="">{{ __('Choose a page') }}</option>
                                    <option value="homepage">{{ __('Homepage') }}</option>
                                    <option value="property_listing">{{ __('Property Listing Page') }}</option>
                                    <option value="property_detail">{{ __('Property Detail Page') }}</option>
                                </select>
                            </div>

                            {{-- Platform --}}
                            <div class="mb-3 form-group d-none" id="group-platform">
                                <label class="form-label">{{ __('Select Platform Type') }}</label>
                                <select class="form-select" name="platform" id="platform" required>
                                    <option value="">{{ __('Select platform') }}</option>
                                    <option value="app">{{ __('App') }}</option>
                                    <option value="web">{{ __('Web') }}</option>
                                </select>
                            </div>

                            {{-- Placement --}}
                            <div class="mb-3 form-group d-none" id="group-placement">
                                <label class="form-label">{{ __('Select Placement') }}</label>
                                <select class="form-select" name="placement" id="placement" required>
                                    <option value="">{{ __('Select Placement') }}</option>
                                </select>
                            </div>

                            {{-- Next Step Button --}}
                            <div class="wizard-actions d-none" id="group-next-step1">
                                <button type="button" class="btn btn-primary" id="next-step1" data-next="#step2" disabled>{{ __('Next') }}</button>
                            </div>
                        </div>

                        {{-- Step 2 --}}
                        <div class="tab-pane fade" id="step2">
                            {{-- Banner Image --}}
                            <div class="mb-3 form-group mandatory" id="group-image">
                                {{ Form::label('banner-image', __('Banner Image'), ['class' => 'form-label']) }}
                                <input type="file" class="filepond" id="banner-image" name="banner_image" accept="image/jpg,image/png,image/jpeg,image/webp" required>
                                <small class="muted-help size-hint d-block mt-2"></small>
                            </div>
                            {{-- Next Step Button --}}
                            <div class="wizard-actions">
                                <button type="button" class="btn btn-secondary" data-prev="#step1">{{ __('Back') }}</button>
                                <button type="button" class="btn btn-primary" id="next-step2" data-next="#step3" disabled>{{ __('Next') }}</button>
                            </div>
                        </div>

                        {{-- Step 3 --}}
                        <div class="tab-pane fade" id="step3">
                            {{-- Ad Type --}}
                            <div class="mb-3" id="group-adtype">
                                <label class="form-label">{{ __('Ad Type') }}</label>
                                <select class="form-select" name="ad_type" id="adType" required>
                                    <option value="external_link">{{ __('External Link') }}</option>
                                    <option value="property">{{ __('Property') }}</option>
                                    <option value="banner_only">{{ __('Only banner') }}</option>
                                </select>
                            </div>

                            {{-- External Link URL --}}
                            <div class="mb-3 d-none" id="externalLinkWrapper">
                                <label class="form-label">{{ __('Link URL') }}</label>
                                <input type="url" class="form-control" name="external_link_url" id="external-link-url" placeholder="https://...">
                            </div>

                            {{-- Property --}}
                            <div class="mb-3 d-none" id="propertyWrapper">
                                <div class="row">
                                    {{-- Category --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">{{ __('Select Category') }}</label>
                                        <select class="form-select" id="category-id">
                                            <option value="">{{ __('Select Category') }}</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->category }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{-- Property --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">{{ __('Select Property') }}</label>
                                        <select class="form-select" name="property_id" id="property-id" disabled>
                                            <option value="">{{ __('No Property Found') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Duration --}}
                            <div class="mb-3 d-none" id="group-duration">
                                <label class="form-label">{{ __('Ad Duration (days)') }}</label>
                                <input type="number" min="1" class="form-control" name="duration" id="duration" required>
                            </div>

                            <!-- Preview Section -->
                            <div class="preview-section" id="previewSection" style="display: none;">
                                <h6 class="mb-3">{{ __('Preview') }}</h6>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Page') }}</span>
                                    <span class="preview-value" id="previewPage">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Platform') }}</span>
                                    <span class="preview-value" id="previewPlatform">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Placement') }}</span>
                                    <span class="preview-value" id="previewPlacement">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Ad Type') }}</span>
                                    <span class="preview-value" id="previewAdType">-</span>
                                </div>
                                <div class="preview-item" id="previewLinkItem" style="display: none;">
                                    <span class="preview-label">{{ __('Link') }}</span>
                                    <span class="preview-value" id="previewLink">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Duration') }}</span>
                                    <span class="preview-value" id="previewDuration">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('Start Date') }}</span>
                                    <span class="preview-value" id="previewStartDate">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">{{ __('End Date') }}</span>
                                    <span class="preview-value" id="previewEndDate">-</span>
                                </div>
                            </div>


                            <div class="wizard-actions">
                                <button type="button" class="btn btn-secondary" data-prev="#step2">{{ __('Back') }}</button>
                                <button type="submit" class="btn btn-primary" id="submit-btn" disabled>{{ __('Save Banner') }}</button>
                            </div>
                        </div>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>
@endsection

@section('script')
<script>
function successFunction(){
    window.location.href = '{{ route("ad-banners.index") }}';
}
$(function(){
    var map = {
        'app': {
            'homepage': {
                'below_categories': {
                    'label': '{{ __("Below Categories") }}',
                    'size': '{{ __("1080 * 360 px") }}',
                },
                'above_all_properties': {
                    'label': '{{ __("Above All Properties") }}',
                    'size': '{{ __("1080 * 360 px") }}',
                }
            },
            'property_detail': {
                'above_facilities': {
                    'label': '{{ __("Above Facilities") }}',
                    'size': '{{ __("1080 * 360 px") }}',
                },
                'above_similar_properties': {
                    'label': '{{ __("Above Similar Properties") }}',
                    'size': '{{ __("1080 * 360 px") }}',
                }
            }
        },
        'web': {
            'homepage': {
                'below_slider': {
                    'label': '{{ __("Below Slider") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                },
                'above_footer': {
                    'label': '{{ __("Above Footer") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                }
            },
            'property_listing': {
                'sidebar_below_filters': {
                    'label': '{{ __("Sidebar Below Filters") }}',
                    'size': '{{ __("387 * 587 px") }}',
                },
                'below_breadcrumb': {
                    'label': '{{ __("Below Breadcrumb") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                },
                'above_footer': {
                    'label': '{{ __("Above Footer") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                }
            },
            'property_detail': {
                'above_breadcrumb': {
                    'label': '{{ __("Above Breadcrumb") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                },
                'sidebar_below_mortgage_loan_calculator': {
                    'label': '{{ __("Sidebar Below Mortgage Loan Calculator") }}',
                    'size': '{{ __("387 * 587 px") }}',
                },
                'above_footer': {
                    'label': '{{ __("Above Footer") }}',
                    'size': '{{ __("1620 * 350 px") }}',
                }
            }
        }
    };

    var $pageSel = $('#page');
    var $platformSel = $('#platform');
    var $placementSel = $('#placement');
    var $sizeHint = $('.size-hint');
    var $adType = $('#adType');
    var $externalWrap = $('#externalLinkWrapper');
    var $propertyWrap = $('#propertyWrapper');
    var $linkUrlInput = $('#external-link-url');
    var $durationInput = $('#duration');
    var $previewSection = $('#previewSection');
    var $categorySelect = $('#category-id');
    var $propertySelect = $('#property-id');

    function fillPlacements(){
        $placementSel.empty().append($('<option/>',{value:'',text:"{{ __('Select Placement') }}"}));
        $sizeHint.text('');
        var platform = $platformSel.val();
        var page = $pageSel.val();
        if(!platform || !page || !map[platform] || !map[platform][page]) return;
        $.each(map[platform][page], function(key, value){
            let label = value.label;
            let size = value.size;
            $placementSel.append($('<option/>', { value: key, text: label }).attr('data-size', size));
        });
    }

    function updatePlatformOptions(){
        var page = $pageSel.val();
        var currentPlatform = $platformSel.val();

        // Clear existing options
        $platformSel.empty().append($('<option/>',{value:'',text:"{{ __('Select platform') }}"}));

        if (page === 'property_listing') {
            // For property_listing, only show web option
            $platformSel.append($('<option/>',{value:'web',text:"{{ __('Web') }}"}));
        } else {
            // For other pages, show both app and web options
            $platformSel.append($('<option/>',{value:'app',text:"{{ __('App') }}"}));
            $platformSel.append($('<option/>',{value:'web',text:"{{ __('Web') }}"}));
        }

        // If current platform is still valid, keep it selected
        if (currentPlatform && $platformSel.find('option[value="' + currentPlatform + '"]').length > 0) {
            $platformSel.val(currentPlatform);
        } else {
            $platformSel.val('');
        }

        // Force Select2 to refresh its display after changing options
        if ($platformSel.hasClass('select2-hidden-accessible')) {
            $platformSel.trigger('change.select2');
        }
    }

    function updateSize(){
        var size = $placementSel.find('option:selected').attr('data-size') || '';
        $sizeHint.text(size ? ('{{ __('Recommended size') }}: ' + size) : '');
    }

    function toggleAdTypeFields(){
        var adType = $adType.val();
        var showExternal = adType === 'external_link';
        var showProperty = adType === 'property';

        $externalWrap.toggleClass('d-none', !showExternal);
        $propertyWrap.toggleClass('d-none', !showProperty);

        if (!showExternal) {
            $linkUrlInput.val('').prop('disabled', true);
        } else {
            $linkUrlInput.prop('disabled', false);
        }

        if (!showProperty) {
            $categorySelect.val('').trigger('change');
            $propertySelect.val('').trigger('change');
            $propertySelect.prop('disabled', true);
        } else {
            $propertySelect.prop('disabled', false);
        }
    }

    function loadPropertiesByCategory(categoryId) {

        if (!categoryId) {
            $propertySelect.empty().append('<option value="">{{ __("Select Property") }}</option>');
            $propertySelect.prop('disabled', true);
            return;
        }

        // Show loading state
        $propertySelect.empty().append('<option value="">{{ __("Loading properties...") }}</option>');
        $propertySelect.prop('disabled', true);

        $.ajax({
            url: '{{ route("properties.by-category") }}',
            method: 'GET',
            data: { category_id: categoryId },
            success: function(response) {
                if (response.error == false && response.data) {
                    // Clear and populate property select
                    $propertySelect.empty().append('<option value="">{{ __("Select Property") }}</option>');

                    $.each(response.data, function(index, property) {
                        // Use jQuery DOM methods to prevent XSS - they automatically escape HTML entities
                        var $option = $('<option></option>')
                            .attr('value', property.id)
                            .text(property.title + ' - ' + property.address);
                        $propertySelect.append($option);
                    });

                    $propertySelect.prop('disabled', false);

                    // Initialize Select2 for property dropdown
                    if ($propertySelect.hasClass('select2-hidden-accessible')) {
                        $propertySelect.select2('destroy');
                    }
                    $propertySelect.select2({
                        placeholder: '{{ __("Search and select property...") }}',
                        allowClear: true,
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                } else {
                    $propertySelect.empty().append('<option value="">{{ __("No Property Found") }}</option>');
                    $propertySelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading properties:', error);
                $propertySelect.empty().append('<option value="">{{ __("Error loading properties") }}</option>');
                $propertySelect.prop('disabled', true);
            }
        });
    }

    // Helper function to format date as dd-mm-yyyy
    function formatDateDDMMYYYY(date) {
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        return day + '-' + month + '-' + year;
    }

    function updatePreview(){
        var page = $pageSel.find('option:selected').text() || '-';
        var platform = $platformSel.find('option:selected').text() || '-';
        var placement = $placementSel.find('option:selected').text() || '-';
        var adTypeValue = $adType.find('option:selected').text() || '-';
        var duration = $durationInput.val() || '-';

        $('#previewPage').text(page);
        $('#previewPlatform').text(platform);
        $('#previewPlacement').text(placement);
        $('#previewAdType').text(adTypeValue);
        $('#previewDuration').text(duration + ' days');

        // Calculate start and end dates
        var startDate = '-';
        var endDate = '-';

        if (duration !== '-' && !isNaN(duration) && duration > 0) {
            var today = new Date();
            var endDateObj = new Date(today);
            endDateObj.setDate(today.getDate() + parseInt(duration));

            startDate = formatDateDDMMYYYY(today);
            endDate = formatDateDDMMYYYY(endDateObj);
        }

        $('#previewStartDate').text(startDate);
        $('#previewEndDate').text(endDate);

        var $linkItem = $('#previewLinkItem');
        var $linkValue = $('#previewLink');
        if (adTypeValue === 'external_link' && $linkUrlInput.val()) {
            $linkValue.text($linkUrlInput.val());
            $linkItem.css('display','flex');
        } else if (adTypeValue === 'property') {
            var propertyId = $propertySelect.val();
            var propertyText = $propertySelect.find('option:selected').text();
            if (propertyId && propertyText && propertyText !== '{{ __("Select Property") }}') {
                $linkValue.text(propertyText);
                $linkItem.css('display','flex');
            } else {
                $linkItem.hide();
            }
        } else {
            $linkItem.hide();
        }

        var hasRequired = page !== '-' && platform !== '-' && placement !== '-' && adTypeValue !== '-' && duration !== '-';
        $previewSection.toggle(hasRequired);
    }

    function validateStep1(){
        var valid = $pageSel.val() && $platformSel.val() && $placementSel.val();
        // Progressive reveal in Step 1
        $('#group-platform').toggleClass('d-none', !$pageSel.val());
        $('#group-placement').toggleClass('d-none', !($pageSel.val() && $platformSel.val()));
        $('#group-next-step1').toggleClass('d-none', !valid);
        $('#next-step1').prop('disabled', !valid);
    }
    function validateStep2(){
        var hasFile = false;
        try{
            // Try to get FilePond instance from stored data first
            var pond = $('#banner-image').data('filepond-instance');

            if (!pond) {
                // Try to get FilePond instance using the API
                pond = $('#banner-image').filepond('instance');
            }

            if(pond && pond.getFiles){
                var files = pond.getFiles();
                hasFile = files.length > 0;
            } else {
                // Fallback for regular file input
                var fileInput = $('#banner-image').get(0);
                hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            }
        }catch(e){
            // Fallback for regular file input
            var fileInput = $('#banner-image').get(0);
            hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        }
        $('#next-step2').prop('disabled', !hasFile);
    }
    function validateStep3(){
        var adType = $adType.val();
        var valid = false;

        $categorySelect.removeAttr('required');
        $propertySelect.removeAttr('required');
        if (adType === 'external_link') {
            valid = $linkUrlInput.val() && $linkUrlInput.val().trim() !== '';
        } else if (adType === 'property') {
            $categorySelect.attr('required', true);
            $propertySelect.attr('required', true);
            valid = $categorySelect.val() && $propertySelect.val();
        } else if (adType === 'banner_only') {
            valid = true;
        }

        // Progressive reveal in Step 3
        $('#group-duration').toggleClass('d-none', !valid);
        $('#submit-btn').prop('disabled', !valid);
    }

    $platformSel.on('change', function(){ fillPlacements(); updatePreview(); validateStep1(); });
    $pageSel.on('change', function(){ updatePlatformOptions(); fillPlacements(); updatePreview(); validateStep1(); });
    $placementSel.on('change', function(){ updateSize(); updatePreview(); validateStep1(); });
    $adType.on('change', function(){ toggleAdTypeFields(); updatePreview(); validateStep3(); });
    $linkUrlInput.on('input', function(){ updatePreview(); validateStep3(); });
    $durationInput.on('input', function(){ updatePreview(); validateStep3(); });
    $categorySelect.on('change', function(){
        loadPropertiesByCategory($(this).val());
        updatePreview();
        validateStep3();
    });

    // Property select change handler
    $propertySelect.on('change', function(){
        updatePreview();
        validateStep3();
    });
    // Initialize FilePond and handle file upload events
    $(document).ready(function() {
        try{
            // Check if FilePond is available and initialize
            if (typeof FilePond !== 'undefined') {
                // Wait a bit for the DOM to be ready
                setTimeout(function() {
                    var fileInput = document.querySelector('#banner-image');
                    if (fileInput) {
                        // Initialize FilePond
                        var pond = FilePond.create(fileInput, {
                            credits: null,
                            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                            maxFileSize: '10MB',
                            allowMultiple: false,
                            instantUpload: false, // Don't upload immediately
                            onaddfile: function(error, file) {
                                if (!error) {
                                    validateStep2();
                                }
                            },
                            onremovefile: function(error, file) {
                                validateStep2();
                            }
                        });

                        // Store the pond instance for later use
                        $('#banner-image').data('filepond-instance', pond);
                    }
                }, 100);
            } else {
                // Fallback for regular file input
                $('#banner-image').on('change', function(){
                    validateStep2();
                });
            }
        }catch(e){
            // Fallback for regular file input
            $('#banner-image').on('change', function(){
                validateStep2();
            });
        }
    });

    toggleAdTypeFields();

    var $stepper = $('#adStepper');
    function updateStepper(targetId){
        var mapIdx = { '#step1':1, '#step2':2, '#step3':3 };
        var activeIdx = mapIdx[targetId] || 1;
        $stepper.find('.ad-step').each(function(){
            var step = Number($(this).data('step'));
            $(this).toggleClass('is-active', step === activeIdx);
            $(this).toggleClass('is-complete', step < activeIdx);
        });
        $stepper.find('.connector').each(function(i){
            $(this).toggleClass('is-complete', (i+1) < activeIdx);
        });
    }

    $('[data-next]').on('click', function(){
        var targetSel = $(this).data('next');
        $('.tab-pane').removeClass('show active');
        $(targetSel).addClass('show active');
        updateStepper(targetSel);
        updatePreview();

        // Trigger validation when navigating to step 2
        if (targetSel === '#step2') {
            setTimeout(function() {
                validateStep2();
            }, 100);
        }
    });

    $('[data-prev]').on('click', function(){
        var targetSel = $(this).data('prev');
        $('.tab-pane').removeClass('show active');
        $(targetSel).addClass('show active');
        updateStepper(targetSel);
    });

    // Add direct event listener for file input
    $('#banner-image').on('change', function() {
        validateStep2();
    });

    // Also listen for input events (for FilePond compatibility)
    $('#banner-image').on('input', function() {
        validateStep2();
    });

    // Initialize Select2 for category dropdown
    $categorySelect.select2({
        placeholder: '{{ __("Search and select category...") }}',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Initialize Select2 for property dropdown
    $propertySelect.select2({
        placeholder: '{{ __("No Property Found") }}',
        allowClear: true,
        disabled: true,
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Initialize platform options based on current page selection
    updatePlatformOptions();

    // Hook into the existing form submission to add FilePond files
    $(document).on('submit', '#create-form', function(e) {
        var pond = $('#banner-image').data('filepond-instance');
        if (pond && pond.getFiles().length > 0) {
            var file = pond.getFiles()[0].file;

            // Create a temporary file input and add the file
            var tempInput = $('<input>').attr({
                type: 'file',
                name: 'banner_image',
                style: 'display: none;'
            });

            // Create a new FileList with the FilePond file
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            tempInput[0].files = dataTransfer.files;

            // Add the temporary input to the form
            $(this).append(tempInput);
        }
    });

    validateStep1();
    validateStep2();
    validateStep3();
    updateStepper('#step1');
});
</script>
@endsection


