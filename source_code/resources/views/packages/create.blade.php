@extends('layouts.main')

@section('title')
    {{ __('Create Package') }}
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
                        <li class="breadcrumb-item">
                            <a href="{{ $type == 'agent' ? route('agent-packages.index', ['user_type' => 'agent']) : route('package.index') }}" id="subURL">{{ __('View Packages') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Add') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4>{{ __('Create Package') }}</h4>
            </div>
            <div class="card">
                {!! Form::open([
                    'url' => $type == 'agent' ? route('agent-packages.store') : route('package.store'),
                    'data-parsley-validate',
                    'class' => 'create-form',
                    'data-success-function' => 'formSuccessFunction',
                ]) !!}
                <div class="card-body">
                    <div class="row ">
                        {{-- Target Audience --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory">
                            {{ Form::label('user_type', __('Target Audience'), ['class' => 'form-label']) }}
                            <select name="user_type" id="user_type" class="form-select" data-parsley-required="true">
                                <option value="user" {{ $type == 'user' ? 'selected' : '' }}>{{ __('User') }}</option>
                                <option value="agent" {{ $type == 'agent' ? 'selected' : '' }}>{{ __('Agent') }}</option>
                            </select>
                        </div>
                        {{-- Package Name --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory">
                            {{ Form::label('name', __('Package Name'), ['class' => 'form-label']) }}
                            {{ Form::text('name', '', [
                                'class' => 'form-control ',
                                'placeholder' => trans('Package Name'),
                                'data-parsley-required' => 'true',
                                'id' => 'name',
                            ]) }}
                        </div>

                        {{-- Duration --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory">
                            {{ Form::label('duration', __('Duration (In Days)'), ['class' => 'form-label']) }}
                            {{ Form::number('duration', '', [
                                'class' => 'form-control ',
                                'placeholder' => trans('Duration (In Days)'),
                                'data-parsley-required' => 'true',
                                'id' => 'duration',
                                'min' => '1',
                                'max' => '730',
                            ]) }}
                        </div>

                        {{-- Package Type --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory">
                            {{ Form::label('', __('Package Type'), ['class' => 'form-label col-12 ']) }}

                            {{-- Paid --}}
                            {{ Form::radio('package_type', 'paid', null, ['class' => 'form-check-input package-type', 'data-parsley-required' => 'true', 'id' => 'package-type-paid', 'checked' => true]) }}
                            {{ Form::label('package-type-paid', __('Paid'), ['class' => 'form-check-label']) }}

                            {{-- Free --}}
                            {{ Form::radio('package_type', 'free', null, ['class' => 'form-check-input package-type', 'data-parsley-required' => 'true', 'id' => 'package-type-free']) }}
                            {{ Form::label('package-type-free', __('Free'), ['class' => 'form-check-label']) }}
                        </div>

                        {{-- Purchase Type --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory">
                            {{ Form::label('', __('Purchase Type'), ['class' => 'form-label col-12 ']) }}

                            {{-- Unlimited --}}
                            {{ Form::radio('purchase_type', 'unlimited', null, ['class' => 'form-check-input purchase-type', 'data-parsley-required' => 'true', 'id' => 'purchase-type-unlimited', 'checked' => true]) }}
                            {{ Form::label('purchase-type-unlimited', __('Unlimited'), ['class' => 'form-check-label']) }}

                            {{-- One Time --}}
                            {{ Form::radio('purchase_type', 'one_time', null, ['class' => 'form-check-input purchase-type', 'data-parsley-required' => 'true', 'id' => 'purchase-type-one-time']) }}
                            {{ Form::label('purchase-type-one-time', __('One Time'), ['class' => 'form-check-label']) }}
                        </div>


                        {{-- Price --}}
                        <div class="col-md-6 col-lg-4 form-group mandatory price-div">
                            {{ Form::label('price', __('Price') . '(' . $currency_symbol . ')', ['class' => 'form-label']) }}
                            {{ Form::number('price', '', [
                                'class' => 'form-control ',
                                'placeholder' => trans('Price'),
                                'id' => 'price',
                                'data-parsley-required' => 'true',
                                'min' => '0.01',
                                'step' => '0.01',
                            ]) }}
                        </div>

                        {{-- IOS Product ID --}}
                        <div class="col-md-6 col-lg-4 form-group" id="ios-product-id-div">
                            {{ Form::label('ios-product-id', __('IOS Product ID'), ['class' => 'form-label']) }}
                            {{ Form::text('ios_product_id', '', [
                                'class' => 'form-control ',
                                'placeholder' => trans('IOS Product ID'),
                                'id' => 'ios-product-id',
                            ]) }}
                        </div>
                    </div>


                    {{-- List duration Type --}}
                    <div class="col-md-6 col-lg-4 form-group">
                        {{ Form::label('list_duration_type', __('List Duration Type'), ['class' => 'form-label col-12']) }}

                        {{-- Standard --}}
                        {{ Form::radio('list_duration_type', 'Standard', null, ['class' => 'form-check-input', 'data-parsley-required' => 'true', 'id' => 'list_duration_type_standard', 'checked' => true]) }}
                        {{ Form::label('list_duration_type_standard', __('Standard (30 Days)'), ['class' => 'form-check-label']) }}

                        {{-- Package --}}
                        {{ Form::radio('list_duration_type', 'Package', null, ['class' => 'form-check-input', 'data-parsley-required' => 'true', 'id' => 'list_duration_type_package']) }}
                        {{ Form::label('list_duration_type_package', __('Package'), ['class' => 'form-check-label']) }}

                        {{-- Custom --}}
                        {{ Form::radio('list_duration_type', 'Custom', null, ['class' => 'form-check-input', 'data-parsley-required' => 'true', 'id' => 'list_duration_type_custom']) }}
                        {{ Form::label('list_duration_type_custom', __('Custom'), ['class' => 'form-check-label']) }}

                        <div class="form-group col-md-12 mt-2" id="custom-duration-div" style="display: none;">
                            {{ Form::label('custom_duration', __('Duration (In Days)'), ['class' => 'form-label']) }}
                            {{ Form::number('custom_duration', '', ['class' => 'form-control', 'placeholder' => trans('Duration (In Days)'), 'id' => 'custom_duration']) }}
                        </div>
                    </div>

                    @if (isset($languages) && $languages->count() > 0)
                        {{-- Translations Div --}}
                        <div class="translation-div mt-4">
                            <div class="col-12">
                                <div class="divider">
                                    <div class="divider-text">
                                        <h5>{{ __('Translations for Package Name') }}</h5>
                                    </div>
                                </div>
                            </div>
                            {{-- Fields for Translations --}}
                            @foreach ($languages as $key => $language)
                                <div class="col-md-6 col-xl-4">
                                    <div class="form-group">
                                        <label for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                        <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                        <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Package Name') }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <hr>
                    {{-- Feature Section --}}
                    <div class="feature-sections">
                        <div class="mt-4" data-repeater-list="feature_data">
                            <div class="col-md-5 pl-0 mb-4">
                                <button type="button" class="btn btn-success add-new-feature" data-repeater-create title="Add new row">
                                    <span><i class="fa fa-plus"></i> {{ __('Add New Feature') }}</span>
                                </button>
                            </div>
                            <div class="row feature-section" data-repeater-item>
                                {{-- Select Feature --}}
                                <div class="form-group mandatory col-md-6 col-lg-4">
                                    <label>{{ __('Select Feature') }} <span class="text-danger">*</span></label>
                                    <select name="feature_id" class="form-control form-select features" required>
                                        <option value="">{{ trans('Select Option') }}</option>
                                        @foreach ($featuresList as $feature)
                                            <option value="{{ $feature->id }}" data-user-type="{{ $feature->user_type }}"> {{ __($feature->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Type --}}
                                <div class="form-group mandatory col-md-6 col-lg-3 package-types">
                                    {{ Form::label('', __('Type'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::radio('type', 'unlimited', null, ['class' => 'form-check-input feature-type feature-type-unlimited', 'required' => true]) }}
                                    {{ Form::label('', __('Unlimited'), ['class' => 'form-check-label feature-type-unlimited-label']) }}
                                    {{ Form::radio('type', 'limited', null, ['class' => 'form-check-input feature-type feature-type-limited', 'required' => true]) }}
                                    {{ Form::label('', __('Limited'), ['class' => 'form-check-label feature-type-limited-label']) }}
                                </div>

                                {{-- Limited Value --}}
                                <div class="col-md-5 col-lg-4 form-group mandatory limit-div" style="display: none">
                                    {{ Form::label('', __('Limited'), ['class' => 'form-label']) }}
                                    {!! Form::text('limit', '', [
                                        'min' => 1,
                                        'class' => 'form-control limit',
                                        'placeholder' => trans('Enter your limit'),
                                    ]) !!}
                                </div>

                                {{-- Remove Option --}}
                                <div class="form-group col-md-1 pl-0 d-flex align-items-end">
                                    <button data-repeater-delete type="button" class="btn btn-icon btn-danger remove-default-option" title="{{ __('Remove Option') }}">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>

                    <div class="col-12 form-group mt-2">
                        {{ Form::submit(trans('Add Package'), ['class' => 'center btn btn-primary']) }}
                    </div>

                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        function filterFeatures() {
            let userType = $('#user_type').val();
            $('.features option').each(function() {
                if ($(this).val() !== "") {
                    let featureType = $(this).data('user-type');
                    // Hide options that do not match the selected user_type
                    if (featureType == userType || featureType == 'all' || !featureType) {
                        $(this).show().prop('disabled', false);
                    } else {
                        $(this).hide().prop('disabled', true);
                        if ($(this).is(':selected')) {
                            $(this).parent().val(''); // Reset selection if it's hidden
                        }
                    }
                }
            });
        }

        $(document).ready(function() {
            // Initial filter based on default selected target audience
            filterFeatures();

            $('#user_type').on('change', function() {
                filterFeatures();
                // Optionally check duplicate features again or clear invalid selected options
                checkDuplicateFeatures('.features', '.add-new-feature');
            });

            $('.package-type').on('click', function(e) {
                $("#price").val("");
                $("#ios-product-id").val("");
                if ($(this).val() == 'paid') {
                    $('.price-div').show();
                    $("#price").attr('data-parsley-required', true);
                    $("#ios-product-id-div").show();
                } else {
                    $("#price").removeAttr('data-parsley-required');
                    $('.price-div').hide();
                    $("#ios-product-id-div").hide();
                }
            })

            // On add new feature, make sure the new select box is also filtered
            $(document).on('click', '.add-new-feature', function() {
                setTimeout(() => {
                    filterFeatures();
                }, 50); // small delay to allow repeater to clone first
            });

            $(".add-new-feature").trigger('click');
            checkDuplicateFeatures('.features', '.add-new-feature');
        });

        $('input[name="list_duration_type"]').on('change', function() {
            if ($(this).val() == 'Custom') {
                $('#custom-duration-div').show();
                $('#custom_duration').attr('data-parsley-required', 'true');
            } else {
                $('#custom-duration-div').hide();
                $('#custom_duration').removeAttr('data-parsley-required');
                $('#custom_duration').val('');
            }
        });

        $(document).on('change', '.features', function(e) {
            let value = $(this).val();
            checkDuplicateFeatures('.features', '.add-new-feature');
            let mortgageCalculatorText = "{{ $featureMapData[config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.NAME')] ?? '' }}";
            let premiumPropertiesText = "{{ $featureMapData[config('constants.FEATURES.PREMIUM_PROPERTIES.NAME')] ?? '' }}";
            let premiumProjectsText = "{{ $featureMapData[config('constants.FEATURES.PREMIUM_PROJECTS.NAME')] ?? '' }}";
            let limitRadioElement = $(this).parent().parent().find('.package-types').find('.feature-type-limited');
            let limitRadioLabelElement = $(this).parent().parent().find('.package-types').find('.feature-type-limited-label');
            let unlimitedRadioElement = $(this).parent().parent().find('.package-types').find('.feature-type-unlimited');
            unlimitedRadioElement.click()
            if (value == mortgageCalculatorText || value == premiumPropertiesText || value == premiumProjectsText) {
                limitRadioElement.removeAttr('required').hide()
                limitRadioLabelElement.hide()
            } else {
                limitRadioElement.attr('required', true).show()
                limitRadioLabelElement.show()
            }
        });

        $(document).on('change', '.feature-type', function(e) {
            let value = $(this).val();
            if (value == 'limited') {
                $(this).parent().parent().find('.limit-div').show()
                $(this).parent().parent().find('.limit-div').find('.limit').attr('data-parsley-required', true)
            } else {
                $(this).parent().parent().find('.limit-div').find('.limit').removeAttr('data-parsley-required')
                $(this).parent().parent().find('.limit-div').hide()
            }
        })

        function formSuccessFunction(response) {
            if (!response.error) {
                setTimeout(() => {
                    window.location.href = "{{ $type == 'agent' ? route('agent-packages.index', ['user_type' => 'agent']) : route('package.index') }}";
                }, 1000);
            }
        }
    </script>
@endsection