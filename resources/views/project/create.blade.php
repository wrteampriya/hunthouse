@extends('layouts.main')

@section('title')
    {{ __('Add Project') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/map-responsive.css') }}">
    <style>
        /* Gallery Images: FilePond stacks previews in a single full-width
           column by default, so a handful of images turns into a long
           vertical scroll. Small fixed-size tiles that wrap in a row keep
           them all visible at a glance instead. */
        .filepond-gallery .filepond--list {
            display: flex;
            flex-wrap: wrap;
            gap: .5em;
        }
        .filepond-gallery .filepond--item {
            width: 100px !important;
            height: 100px !important;
            margin: .35em;
        }
    </style>
@endsection
<!-- add before </body> -->

{{-- <script src="https://unpkg.com/filepond/dist/filepond.js"></script> --}}
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
                            <a href="{{ route('project.index') }}" id="subURL">{{ __('View Project') }}</a>
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
    {!! Form::open([
        'route' => 'project.store',
        'data-parsley-validate',
        'id' => 'create-form',
        'files' => true,
        'data-success-function' => 'formSuccessFunction',
    ]) !!}
    <div class='row'>
        <div class='col-md-6'>
            <div class="card">
                <h3 class="card-header"> {{ __('Details') }}</h3>
                <hr>
                <input type="hidden" id="default-latitude" value="{{ system_setting('latitude') }}">
                <input type="hidden" id="default-longitude" value="{{ system_setting('longitude') }}">

                {{-- Category --}}
                <div class="card-body">
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('category', __('Category'), ['class' => 'form-label col-12 ']) }}
                        <select name="category_id" class="form-select form-control-sm" data-parsley-minSelect='1'
                            id="project-category" required>
                            <option value="" selected>{{ __('Choose Category') }}</option>
                            @foreach ($category as $row)
                                <option value="{{ $row->id }}" data-parametertypes='{{ $row->parameter_types }}'>
                                    {{ $row->category }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Country — drives which language tab is required below.
                         Kept in sync with the map location picker (Location
                         card): picking either one updates the other via the
                         shared "map:countryResolved" event. --}}
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('country_select', __('Country'), ['class' => 'form-label col-12 ']) }}
                        @if ($countries->isEmpty())
                            {{-- A required <select> with zero <option>s just silently blocks
                                 submission via native browser validation, with no indication
                                 why — tell the admin exactly what to do instead. --}}
                            <div class="alert alert-warning mb-0">
                                {{ __('No countries are configured yet.') }}
                                <a href="{{ route('country.index') }}">{{ __('Add a country') }}</a>
                                {{ __('before creating a project.') }}
                            </div>
                        @else
                            <select class="form-select form-control-sm" id="country_select" required>
                                <option value="" selected>{{ __('Choose Country') }}</option>
                                @foreach ($countries as $countryOption)
                                    <option value="{{ $countryOption->id }}" data-lat="{{ $countryOption->latitude }}" data-lng="{{ $countryOption->longitude }}">{{ $countryOption->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Title & Description — one tab per language, default language first.
                         Not "mandatory" itself: the default tab's fields carry that class
                         individually below, so the required "*" doesn't leak onto every
                         language via this wrapper (.mandatory is a descendant selector). --}}
                    <div class="col-md-12 col-12 form-group">
                        <ul class="nav nav-tabs" id="projectLanguageTabs" role="tablist">
                            @foreach ($languages as $index => $language)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}" id="lang-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#lang-tab-{{ $language->id }}" type="button" role="tab">
                                        {{ $language->name }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content pt-3" id="projectLanguageTabsContent">
                            @foreach ($languages as $index => $language)
                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="lang-tab-{{ $language->id }}" role="tabpanel">
                                    @if ($index === 0)
                                        {{-- Default language: base title/description fields.
                                             Not statically "mandatory"/required — required.js below
                                             marks whichever language is actually required right now. --}}
                                        <div class="form-group" data-language-id="{{ $language->id }}">
                                            {{ Form::label('title', __('Title'), ['class' => 'form-label col-12 ']) }}
                                            {{ Form::text('title', '', ['class' => 'form-control ', 'placeholder' => __('Title'), 'id' => 'title']) }}
                                        </div>
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                {{ Form::label('description', __('Description'), ['class' => 'form-label mb-0']) }}
                                                @if (isset($geminiEnabled) && $geminiEnabled)
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="generate-description-btn"
                                                        title="{{ __('Generate with AI') }}">
                                                        <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                                                    </button>
                                                @endif
                                            </div>
                                            {{ Form::textarea('description', '', ['class' => 'form-control mb-3', 'rows' => '5', 'id' => 'description', 'placeholder' => __('Description')]) }}
                                            <div id="description-loading" class="d-none text-primary">
                                                <small><i class="bi bi-hourglass-split"></i> {{ __('Generating description...') }}</small>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Additional language: optional translations[] fields --}}
                                        <div class="form-group" data-language-id="{{ $language->id }}">
                                            <label for="translation-title-{{ $language->id }}" class="form-label">{{ __('Title') }}</label>
                                            <input type="hidden" name="translations[{{ $language->id }}][title][language_id]" value="{{ $language->id }}">
                                            <input type="text" name="translations[{{ $language->id }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Title') }}">
                                        </div>
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label for="translation-project-description-{{ $language->id }}" class="form-label mb-0">{{ __('Description') }}</label>
                                                @if (isset($geminiEnabled) && $geminiEnabled)
                                                    <button type="button" class="btn btn-sm btn-outline-primary generate-translation-description-btn"
                                                        data-language-id="{{ $language->id }}"
                                                        data-language-name="{{ $language->name }}"
                                                        data-language-code="{{ $language->code ?? $language->name }}"
                                                        title="{{ __('Generate with AI') }}">
                                                        <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                                                    </button>
                                                @endif
                                            </div>
                                            <input type="hidden" name="translations[{{ $language->id }}][description][language_id]" value="{{ $language->id }}">
                                            <textarea name="translations[{{ $language->id }}][description][value]" id="translation-project-description-{{ $language->id }}" class="form-control mb-3" rows="5" placeholder="{{ __('Enter Description') }}"></textarea>
                                            <div id="translation-project-description-loading-{{ $language->id }}" class="d-none text-primary mt-2">
                                                <small><i class="bi bi-hourglass-split"></i> {{ __('Generating description...') }}</small>
                                            </div>
                                        </div>
                                        {{-- Multi-language SEO / schema markup — not handled by the
                                             website yet this version, hidden until it is. --}}
                                        @if(false)
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <label for="translation-meta-title-{{ $language->id }}" class="form-label">{{ __('SEO Title') }}</label>
                                            <input type="hidden" name="translations[{{ $language->id }}][meta_title][language_id]" value="{{ $language->id }}">
                                            <input type="text" name="translations[{{ $language->id }}][meta_title][value]" id="translation-meta-title-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter SEO Title') }}">
                                        </div>
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <label for="translation-meta-description-{{ $language->id }}" class="form-label">{{ __('SEO Description') }}</label>
                                            <input type="hidden" name="translations[{{ $language->id }}][meta_description][language_id]" value="{{ $language->id }}">
                                            <textarea name="translations[{{ $language->id }}][meta_description][value]" id="translation-meta-description-{{ $language->id }}" class="form-control" rows="3" placeholder="{{ __('Enter SEO Description') }}"></textarea>
                                        </div>
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <label for="translation-meta-keywords-{{ $language->id }}" class="form-label">{{ __('SEO Keywords') }}</label>
                                            <input type="hidden" name="translations[{{ $language->id }}][meta_keywords][language_id]" value="{{ $language->id }}">
                                            <textarea name="translations[{{ $language->id }}][meta_keywords][value]" id="translation-meta-keywords-{{ $language->id }}" class="form-control" rows="2" placeholder="{{ __('Enter SEO Keywords') }}"></textarea>
                                        </div>
                                        <div class="form-group mt-2" data-language-id="{{ $language->id }}">
                                            <label for="translation-schema-markup-{{ $language->id }}" class="form-label">{{ __('Schema Markup') }}</label>
                                            <input type="hidden" name="translations[{{ $language->id }}][schema_markup][language_id]" value="{{ $language->id }}">
                                            <textarea name="translations[{{ $language->id }}][schema_markup][value]" id="translation-schema-markup-{{ $language->id }}" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                                        </div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Slug --}}
                    <div class="col-md-12 col-12 form-group">
                        {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('slug', '', ['class' => 'form-control ', 'placeholder' => __('Slug'), 'id' => 'slug']) }}
                        <small
                            class="text-danger text-sm">{{ __('Only Small English Characters, Numbers And Hypens Allowed') }}</small>
                    </div>

                    {{-- Project Type --}}
                    <div class="col-md-12 col-12  form-group  mandatory">
                        <div class="row">
                            {{ Form::label('', __('Project Type'), ['class' => 'form-label col-12 ']) }}

                            {{-- Upcoming --}}
                            <div class="col-md-4">
                                {{ Form::radio('project_type', 'upcoming', null, ['class' => 'form-check-input', 'id' => 'upcoming', 'required' => true, 'checked' => true]) }}
                                {{ Form::label('project_type', __('Upcoming'), ['class' => 'form-check-label', 'for' => 'upcoming']) }}
                            </div>

                            {{-- Under Construction --}}
                            <div class="col-md-4">
                                {{ Form::radio('project_type', 'under_construction', null, ['class' => 'form-check-input', 'id' => 'under_construction', 'required' => true]) }}
                                {{ Form::label('project_type', __('Under Construction'), ['class' => 'form-check-label', 'for' => 'under_construction']) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6'>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('SEO Details') }}</h3>
                    @if (isset($geminiEnabled) && $geminiEnabled)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="generate-meta-btn"
                            title="{{ __('Generate Meta Details with AI') }}">
                            <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                        </button>
                    @endif
                </div>
                <hr>
                <div class="row card-body">

                    {{-- SEO Title --}}
                    <div class="col-12 form-group">
                        {{ Form::label('title', __('Title'), ['class' => 'form-label text-center']) }}
                        <textarea id="meta_title" name="meta_title" class="form-control" rows="2" style="height: 75px"
                            placeholder="{{ __('Title') }}"></textarea>
                        <span
                            class="small text-muted">{{ __('Recommended: 55-60 characters, Max size: 255 characters') }}</span>
                        <br>
                    </div>

                    {{-- SEO Image --}}
                    <div class="col-12 form-group card">
                        {{ Form::label('meta_image', __('Image'), ['class' => 'form-label']) }}
                        <input type="file" name="meta_image" id="meta_image" class="filepond from-control"
                            placeholder="{{ __('Image') }}">
                        <span class="small text-muted">{{ __('Allowed: JPG, PNG, JPEG, Max size: 5MB') }}</span>
                        <div class="img_error"></div>
                    </div>

                    {{-- SEO Description --}}
                    <div class="col-12 form-group">
                        {{ Form::label('description', __('Description'), ['class' => 'form-label text-center']) }}
                        <textarea id="meta_description" name="meta_description" class="form-control" rows="3"
                            placeholder="{{ __('Description') }}"></textarea>
                        <span
                            class="small text-muted">{{ __('Recommended: 155-160 characters, Max size: 255 characters') }}</span>
                        <br>
                    </div>

                    {{-- SEO Keywords --}}
                    <div class="col-12 form-group">
                        {{ Form::label('keywords', __('Keywords'), ['class' => 'form-label']) }}
                        <textarea name="keywords" id="keywords" class="form-control" rows="3" placeholder="{{ __('Keywords') }}"></textarea>
                        <span class="small text-muted">{{ __('Max size: 255 characters') }}</span>
                        {{ __('(add comma separated keywords)') }}
                    </div>
                    <div id="meta-loading" class="col-12 d-none text-primary">
                        <small><i class="bi bi-hourglass-split"></i> {{ __('Generating meta details...') }}</small>
                    </div>

                    {{-- SEO Schema Markup — not handled by the website yet this version, hidden until it is. --}}
                    @if(false)
                    <div class="col-12 form-group">
                        {{ Form::label('schema_markup', __('Schema Markup'), ['class' => 'form-label']) }}
                        <textarea name="schema_markup" id="schema_markup" class="form-control" rows="5" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- Location --}}
        <div class='col-md-12'>
            <div class="card">
                <h3 class="card-header">{{ __('Location') }}</h3>
                <hr>
                <div class="card-body">

                    <div class="row">
                        <div class='col-md-6'>
                            <div class="card col-md-12" id="map" style="height: 400px; min-height: 300px;">
                                <!-- Google map -->
                            </div>
                        </div>
                        <div class='col-md-6'>
                            <div class="row">
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('city', __('City'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::hidden('city', '', ['class' => 'form-control ', 'id' => 'city']) !!}
                                    <input id="searchInput" class="controls form-control" type="text" placeholder="{{ __('City') }}" required>
                                     {{-- {{ Form::text('city', '', ['class' => 'form-control ', 'placeholder' => 'City', 'id' => 'city', 'required' => true]) }}  --}}
                                </div>
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('country', __('Country'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('country', '', ['class' => 'form-control ', 'placeholder' => __('Country'), 'id' => 'country', 'required' => true]) }}
                                </div>
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('state', __('State'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('state', '', ['class' => 'form-control ', 'placeholder' => __('State'), 'id' => 'state', 'required' => true]) }}
                                </div>
                                {{-- Country is derived automatically from the picked map location --}}
                                {!! Form::hidden('country_id', '', ['id' => 'country_id']) !!}
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('latitude', __('Latitude'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::text('latitude', '', [
                                        'class' => 'form-control',
                                        'id' => 'latitude',
                                        'step' => 'any',
                                        'readonly' => true,
                                        'required' => true,
                                        'placeholder' => __('Latitude'),
                                    ]) !!}
                                </div>
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('longitude', __('Longitude'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::text('longitude', '', [
                                        'class' => 'form-control',
                                        'id' => 'longitude',
                                        'step' => 'any',
                                        'readonly' => true,
                                        'required' => true,
                                        'placeholder' => __('Longitude'),
                                    ]) !!}
                                </div>
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('address', '', [
                                        'class' => 'form-control ',
                                        'placeholder' => __('Address'),
                                        'rows' => '4',
                                        'id' => 'address',
                                        'autocomplete' => 'off',
                                        'required' => 'true',
                                    ]) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Floor Plans --}}
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Floor Plans') }}</h3>
                <hr>
                <div class="card-body projects-floor-plans">
                    {{-- Floor Section --}}
                    <div class="mt-4" data-repeater-list="floor_data">
                        <div class="row floor-section" data-repeater-item>
                            {{-- Floor Title --}}
                            <div class="form-group col-md-5">
                                <label class="form-label">{{ __('Floor') }} - <span class="floor-number">1</span> <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="title" placeholder="{{ __('Enter Floor Title') }}"
                                    class="form-control" required>
                            </div>

                            {{-- Floor Image --}}
                            <div class="form-group col-md-6">
                                {{ Form::label('floor-image', __('Floor Image'), ['class' => 'form-label']) }}
                                <input type="file" class="form-control" name="floor_image"
                                    accept="image/jpg,image/png,image/jpeg,image/webp" required>
                            </div>
                            <div class="form-group col-md-1 pl-0 mt-4">
                                <button data-repeater-delete type="button"
                                    class="btn btn-icon btn-danger remove-default-floor"
                                    title="{{ __('Remove Floor') }}">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    {{-- Add New Floor Button --}}
                    <div class="col-md-5 pl-0 mb-4">
                        <button type="button" class="btn btn-success add-new-floor" data-repeater-create
                            title="{{ __('Add New Floor') }}">
                            <span><i class="fa fa-plus"></i> {{ __('Add New Floor') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>


        {{-- Images and Documents --}}
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Images and Documents') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        {{-- Title Image --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 form-group mandatory">
                            {{ Form::label('title-image', __('Title Image'), ['class' => 'form-label']) }}
                            <input type="file" class="filepond" id="title-image" name="image"
                                accept="image/jpg,image/png,image/jpeg,image/webp" required>
                        </div>

                        {{-- Gallery Images --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3">
                            {{ Form::label('gallary-images', __('Gallery Images'), ['class' => 'form-label']) }}
                            <input type="file" class="filepond filepond-gallery" id="gallary-images" name="gallery_images[]" multiple
                                accept="image/jpg,image/png,image/jpeg,image/webp">
                        </div>

                        {{-- Documents --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3">
                            {{ Form::label('documents', __('Documents'), ['class' => 'form-label ']) }}
                            <input type="file" class="filepond" id="documents" name="documents[]" multiple
                                accept="application/pdf,application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Video --}}
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Video') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3">
                        {{ Form::label('video_type', __('Video Type'), ['class' => 'form-label col-12 ']) }}
                        <select name="video_type" class="form-select" data-parsley-minSelect='1'
                            id="project-category">
                            <option value="" selected>{{ __('Choose Video Type') }}</option>
                                @if(system_setting('show_direct_video_upload') == 1)
                                    <option value="0" name="video_type" >
                                        {{ __('Custom') }}
                                    </option>
                                @endif
                                <option value="1" name="video_type" >
                                    {{ __('Youtube') }}
                                </option>
                                <option value="2" name="video_type" >
                                    {{ __('Vimeo') }}
                                </option>
                
                        </select>
                                        </div>
                        {{-- Video Link --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3 mt-sm-0" id="video_link_div" style="display:none">
                            {{ Form::label('video_link', __('Video Link'), ['class' => 'form-label']) }}
                            {{ Form::text('video_link', '', ['class' => 'form-control ', 'placeholder' => trans('Video Link'), 'id' => 'video_link', 'autocomplete' => 'off']) }}
                        </div>

                        {{-- Custom Video --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3 mt-sm-0"  id="custom_video_div" style="display:none">
                            {{ Form::label('custom_video', __('Custom Video'), ['class' => 'form-label']) }}
                            <input type="file" class="filepond" name="custom_video" id="custom_video"
                                accept="video/mp4,video/webm,video/ogg">
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>


        <div class="card">
            <h3 class="card-header">{{ __('accessibility') }}</h3>
            <hr>
            <div class="card-body">
                <div class="col-sm-12 col-md-12 col-xs-12 d-flex">
                    <label class="form-check-label mandatory mt-3">{{ __('Is Premium?') }}</label>

                    <div class="form-check form-switch mt-3 ms-3">

                        <input type="hidden" name="is_premium" id="is_premium" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_premium_switch">

                    </div>
                </div>
            </div>
        </div>

        {{-- Translations are now handled by the language tabs in the Details card above. --}}


        <input type="hidden" name="is_draft" id="is_draft" value="0">
        {{-- Save --}}
        <div class='col-md-12 d-flex justify-content-end mb-3'>
            <input type="submit" class="btn btn-primary" value="{{ __('Save') }}"> &nbsp;&nbsp;
            <button class="btn btn-outline-primary" type="button" onclick="saveAsDraft()">{{ __('Save as Draft') }}</button> &nbsp;&nbsp;
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('create-form').reset(); document.getElementById('project-category').focus();">{{ __('Reset') }}</button>
        </div>
    </div>

    {!! Form::close() !!}
@endsection
@section('script')
    @if (system_setting('map_service_provider') === 'open_street_maps')
        <script>window.MAP_SERVICE_PROVIDER = 'open_street_maps';</script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('assets/js/osm-maps-helper.js') }}"></script>
    @else
        <script>window.MAP_SERVICE_PROVIDER = 'google_maps';</script>
        <script type="text/javascript"
            src="https://maps.googleapis.com/maps/api/js?key={{ env('MAP_API_KEY') }}&libraries=marker,places&loading=async&callback=initMap"
            async defer></script>
        <script src="{{ asset('assets/js/maps-helper.js') }}"></script>
    @endif
    <script type="text/javascript">
        jQuery(document).ready(function() {
            initMap();
        });

        // Exactly ONE language tab is required at a time: the selected country's
        // resolved language when a country is picked, otherwise the system's
        // default language (mirrors HelperService::effectiveRequiredLanguageId on
        // the backend). Marked with the standard "*" (via .mandatory) and the
        // actual required attribute, so the other language(s) — including the
        // default one when overridden — are genuinely optional.
        var currentMapOptions = null;

        (function() {
            const countryLanguageMap = @json($countries->mapWithKeys(fn ($country) => [$country->id => optional($country->resolvedLanguage())->id]));
            const defaultLanguageId = @json($languages->first()->id ?? null);

            function markRequiredLanguageTab(countryId) {
                const $groups = $('#projectLanguageTabsContent [data-language-id]');
                $groups.removeClass('mandatory');
                $groups.find('input[type="text"], textarea').removeAttr('required');

                let requiredLanguageId = defaultLanguageId;
                const countryLanguageId = countryLanguageMap[countryId || $('#country_id').val()];
                if (countryLanguageId) {
                    requiredLanguageId = countryLanguageId;
                }
                if (! requiredLanguageId) {
                    return;
                }

                const $requiredGroup = $('#projectLanguageTabsContent [data-language-id="' + requiredLanguageId + '"]');
                $requiredGroup.addClass('mandatory');
                $requiredGroup.find('input[type="text"], textarea').attr('required', 'required');

                const $tabBtn = $('#lang-tab-btn-' + requiredLanguageId);
                if ($tabBtn.length) {
                    (new bootstrap.Tab($tabBtn[0])).show();
                }
            }

            $(document).on('map:countryResolved', function(e, countryId) {
                markRequiredLanguageTab(countryId);
                if ($('#country_select').val() != countryId) {
                    $('#country_select').val(countryId);
                }
            });
            markRequiredLanguageTab();

            // Explicit country selector (Details card): picking a country here
            // sets the hidden country/country_id fields the map picker also
            // writes to, then fires the same event so the required language
            // stays in sync regardless of which control the user used.
            $(document).on('change', '#country_select', function() {
                const countryId = $(this).val();
                const countryName = $(this).find('option:selected').text();
                $('#country_id').val(countryId);
                $('#country').val(countryId ? countryName : '');
                $(document).trigger('map:countryResolved', [countryId]);

                // Re-validate any pin already on the map against the newly
                // selected country — snaps it back if it now falls outside.
                if (window.recheckPinForCountry && currentMapOptions) {
                    window.recheckPinForCountry(currentMapOptions.mapElementId || 'map', currentMapOptions);
                }
            });
        })();

        function initMap() {
            var mapOptions = {
                mapElementId: 'map',
                inputSelector: '#searchInput',
                citySelector: '#city',
                countrySelector: '#country',
                countryIdSelector: '#country_id',
                stateSelector: '#state',
                addressSelector: '#address',
                latitudeSelector: '#latitude',
                longitudeSelector: '#longitude',
                defaultLatitudeSelector: '#default-latitude',
                defaultLongitudeSelector: '#default-longitude',
                countrySelectSelector: '#country_select'
            };
            currentMapOptions = mapOptions;
            if (window.MAP_SERVICE_PROVIDER === 'open_street_maps') {
                window.initOsmPlacesMap(mapOptions);
            } else {
                window.initBackendPlacesMap(mapOptions);
            }
        }
        jQuery(document).ready(function() {
            initMap();
        });
        $(document).ready(function() {
            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize,
                FilePondPluginFileValidateType);
        });

        var slugRequestTimer = null;
        var slugRequestXhr = null;
        $("#title").on('keyup', function(e) {
            let title = $(this).val();
            let slugElement = $("#slug");
            clearTimeout(slugRequestTimer);
            if (slugRequestXhr) { slugRequestXhr.abort(); }
            if (title) {
                slugRequestTimer = setTimeout(function() {
                    slugRequestXhr = $.ajax({
                        type: 'POST',
                        url: "{{ route('project.generate-slug') }}",
                        data: {
                            '_token': $('meta[name="csrf-token"]').attr('content'),
                            title: title
                        },
                        beforeSend: function() {
                            slugElement.attr('readonly', true).val('Please wait....')
                        },
                        success: function(response) {
                            // Only apply if the title hasn't changed since this request
                            // was fired — otherwise a slower response for an earlier
                            // keystroke can overwrite a faster one for a later keystroke.
                            if (title !== $("#title").val()) { return; }
                            if (!response.error) {
                                if (response.data) {
                                    slugElement.removeAttr('readonly').val(response.data);
                                } else {
                                    slugElement.removeAttr('readonly').val("")
                                }
                            }
                        }
                    });
                }, 300);
            } else {
                slugElement.removeAttr('readonly', true).val("")
            }
        });

        function formSuccessFunction(response) {
            if (!response.error) {
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        }

        // Gemini AI Integration
        @if (isset($geminiEnabled) && $geminiEnabled)
            $(document).ready(function() {
                // Generate Description
                $('#generate-description-btn').on('click', function() {
                    const title = $('#title').val();
                    const city = $('#city').val();
                    const state = $('#state').val();
                    const country = $('#country').val();
                    const address = $('#address').val();
                    const projectType = $('input[name="project_type"]:checked').val();
                    const category_id = $('#project-category').val();

                    if (!title) {
                        showErrorToast('{{ __('Please enter a title first') }}');
                        return;
                    }

                    const btn = $(this);
                    const loadingDiv = $('#description-loading');
                    const descriptionField = $('#description');

                    btn.prop('disabled', true);
                    loadingDiv.removeClass('d-none');

                    $.ajax({
                        url: '{{ route('gemini.generate-description') }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            entity_type: 'project',
                            title: title,
                            location: address,
                            city: city,
                            state: state,
                            country: country,
                            type: projectType,
                            category_id: category_id
                        },
                        success: function(response) {
                            if (!response.error && response.data && response.data.description) {
                                descriptionField.val(response.data.description);
                                if (response.data.cached) {
                                    console.log('{{ __('Used cached result') }}');
                                }
                            } else {
                                showErrorToast(response.message ||
                                    '{{ __('Failed to generate description') }}');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message ||
                                '{{ __('An error occurred') }}';
                            showErrorToast(errorMsg);
                        },
                        complete: function() {
                            btn.prop('disabled', false);
                            loadingDiv.addClass('d-none');
                        }
                    });
                });

                // Generate Meta Details
                $('#generate-meta-btn').on('click', function() {
                    const title = $('#title').val();
                    const city = $('#city').val();
                    const address = $('#address').val();

                    if (!title) {
                        showErrorToast('{{ __('Please enter a title first') }}');
                        return;
                    }

                    const btn = $(this);
                    const loadingDiv = $('#meta-loading');
                    const metaTitleField = $('#meta_title');
                    const metaDescriptionField = $('#meta_description');
                    const metaKeywordsField = $('#keywords');

                    btn.prop('disabled', true);
                    loadingDiv.removeClass('d-none');

                    $.ajax({
                        url: '{{ route('gemini.generate-meta') }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            entity_type: 'project',
                            title: title,
                            location: address,
                            city: city
                        },
                        success: function(response) {
                            if (!response.error && response.data) {
                                if (response.data.meta_title) {
                                    metaTitleField.val(response.data.meta_title);
                                }
                                if (response.data.meta_description) {
                                    metaDescriptionField.val(response.data.meta_description);
                                }
                                if (response.data.meta_keywords) {
                                    metaKeywordsField.val(response.data.meta_keywords);
                                }
                                if (response.data.cached) {
                                    console.log('{{ __('Used cached result') }}');
                                }
                            } else {
                                showErrorToast(response.message ||
                                    '{{ __('Failed to generate meta details') }}');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message ||
                                '{{ __('An error occurred') }}';
                            showErrorToast(errorMsg);
                        },
                        complete: function() {
                            btn.prop('disabled', false);
                            loadingDiv.addClass('d-none');
                        }
                    });
                });

                // Generate Translation Description
                $('.generate-translation-description-btn').on('click', function() {
                    const languageId = $(this).data('language-id');
                    const languageName = $(this).data('language-name');
                    const languageCode = $(this).data('language-code');
                    const title = $('#title').val();
                    const city = $('#city').val();
                    const state = $('#state').val();
                    const country = $('#country').val();
                    const address = $('#address').val();
                    const projectType = $('input[name="project_type"]:checked').val();
                    const category_id = $('#project-category').val();
                    const descriptionField = $('#translation-project-description-' + languageId);
                    const loadingDiv = $('#translation-project-description-loading-' + languageId);
                    const btn = $(this);

                    if (!title) {
                        showErrorToast('{{ __('Please enter a title first') }}');
                        return;
                    }

                    btn.prop('disabled', true);
                    loadingDiv.removeClass('d-none');

                    $.ajax({
                        url: '{{ route('gemini.generate-description') }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            entity_type: 'project',
                            title: title,
                            location: address,
                            city: city,
                            state: state,
                            country: country,
                            type: projectType,
                            category_id: category_id,
                            language_id: languageId,
                            language_name: languageName,
                            language_code: languageCode
                        },
                        success: function(response) {
                            if (!response.error && response.data && response.data.description) {
                                // const editorId = 'translation-description-' + languageId;
                                // // Check if TinyMCE editor exists for this field
                                // if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                                //     // Use TinyMCE API to set content
                                //     tinymce.get(editorId).setContent(response.data.description);
                                // } else {
                                // Fallback to regular textarea
                                descriptionField.val(response.data.description);
                                // }
                            } else {
                                showErrorToast(response.message ||
                                    '{{ __('Failed to generate description') }}');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message ||
                                '{{ __('An error occurred') }}';
                            showErrorToast(errorMsg);
                        },
                        complete: function() {
                            btn.prop('disabled', false);
                            loadingDiv.addClass('d-none');
                        }
                    });
                });
            });
        @endif

        document.getElementById('is_premium_switch').addEventListener('change', function() {
            document.getElementById('is_premium').value = this.checked ? 1 : 0;
        });

        $(document).ready(function() {
            // Video Type Change Handler
            $('select[name="video_type"]').on('change', function() {
                var videoType = $(this).val();
                
                // Reset fields
                $('#video_link_div').hide();
                $('#custom_video_div').hide();
                $('#video_link').prop('required', false);
                $('#custom_video').prop('required', false);

                if (videoType == '0') { // Custom
                    $('#custom_video_div').show();
                    // For FilePond, we don't need to manually set required prop on the input if it's already there
                    // or if we do, we might need to check if FilePond instance exists.
                    // However, simplified approach:
                    $('#custom_video').attr('required', true); 
                } else if (videoType == '1' || videoType == '2') { // Youtube or Vimeo
                    $('#video_link_div').show();
                    $('#video_link').prop('required', true);
                    $('#custom_video').removeAttr('required');
                }
            });

            // Trigger change on load to set initial state if value exists
            $('select[name="video_type"]').trigger('change');
        });

        // Save as Draft: nothing on this form is required — strip every
        // required constraint (native, Parsley, FilePond) before submitting
        // so a draft can be saved with any subset of fields filled in.
        function saveAsDraft() {
            document.getElementById('is_draft').value = '1';
            var form = document.getElementById('create-form');
            form.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            // FilePond manages its own `required` state internally and
            // re-applies it on every render, so removeAttribute('required')
            // on the original input doesn't reliably stick — use its API.
            if (window.FilePond) {
                form.querySelectorAll('.filepond').forEach(function(el) {
                    var pond = FilePond.find(el);
                    if (pond) {
                        pond.required = false;
                    }
                });
            }
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    </script>
@endsection
