@extends('layouts.main')

@section('title')
    {{ __('Update Property') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/map-responsive.css') }}">
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
                            <a href="{{ route('property.index') }}" id="subURL">{{ __('View Property') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Update') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection
@section('content')
    {!! Form::open([
        'route' => ['property.update', $id],
        'method' => 'PATCH',
        'data-parsley-validate',
        'files' => true,
        'id' => 'myForm',
        'class' => 'create-form',
        'data-success-function' => 'successFunction'
    ]) !!}

    <div class='row'>
        <div class='col-md-6'>

            <div class="card">

                <h3 class="card-header">{{ __('Details') }}</h3>
                <hr>

                {{-- Category --}}
                <div class="card-body">
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('category', __('Category'), ['class' => 'form-label col-12 ']) }}
                        <select name="category" class="choosen-select form-select form-control-sm" data-parsley-minSelect='1' id="category" required='true'>
                            <option value="">{{ __('Choose Category') }}</option>
                            @foreach ($category as $row)
                                <option value="{{ $row->id }}"
                                    {{ $list->category_id == $row->id ? ' selected=selected' : '' }}
                                    data-parametertypes='{{ $row->parameter_types }}'> {{ $row->category }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Title --}}
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('title', __('Title'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('title', isset($list->title) ? $list->title : '', ['class' => 'form-control ', 'placeholder' => __('Title'), 'required' => 'true', 'id' => 'title']) }}
                    </div>

                    {{-- Slug --}}
                    <div class="col-md-12 col-12 form-group">
                        {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('slug', isset($list->slug_id) ? $list->slug_id : '', [ 'class' => 'form-control ', 'placeholder' =>  __('Slug'), 'id' => 'slug', ]) }}
                        <small class="text-danger text-sm">{{ __("Only Small English Characters, Numbers And Hypens Allowed") }}</small>
                    </div>

                    {{-- Description --}}
                    <div class="col-md-12 col-12 form-group mandatory">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            {{ Form::label('description', __('Description'), ['class' => 'form-label mb-0']) }}
                            @if(isset($geminiEnabled) && $geminiEnabled)
                            <button type="button" class="btn btn-sm btn-outline-primary" id="generate-description-btn" title="{{ __('Generate with AI') }}">
                                <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                            </button>
                            @endif
                        </div>
                        {{ Form::textarea('description', isset($list->description) ? $list->description : '', ['class' => 'form-control mb-3', 'rows' => '3', 'id' => 'description', 'required' => 'true', 'placeholder' => __('Description')]) }}
                        <div id="description-loading" class="d-none text-primary">
                            <small><i class="bi bi-hourglass-split"></i> {{ __('Generating description...') }}</small>
                        </div>
                    </div>

                    {{-- Property Type --}}
                    <div class="col-md-12 col-12  form-group  mandatory">
                        <div class="row">
                            {{ Form::label('', __('Property Type'), ['class' => 'form-label col-12 ']) }}

                            {{-- For Sell --}}
                            <div class="col-md-6">
                                {{ Form::radio('property_type', 0, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true, isset($list->propery_type) && $list->getRawOriginal('propery_type') == 0 ? 'checked' : '']) }}
                                {{ Form::label('property_type', __('For Sell'), ['class' => 'form-check-label']) }}
                            </div>

                            {{-- For Rent --}}
                            <div class="col-md-6">
                                {{ Form::radio('property_type', 1, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true, isset($list->propery_type) && $list->getRawOriginal('propery_type') == 1 ? 'checked' : '']) }}
                                {{ Form::label('property_type', __('For Rent'), ['class' => 'form-check-label']) }}
                            </div>
                        </div>
                    </div>


                    {{-- When Rent Selected Then Show Duration For Price --}}
                    <div class="col-md-12 col-12 form-group mandatory" id='duration'>
                        {{ Form::label('Duration', __('Duration For Price'), ['class' => 'form-label col-12 ']) }}
                        <select name="price_duration" id="price_duration"class="choosen-select form-select form-control-sm" data-parsley-minSelect='1'>
                            <option value="">{{ __("Select Duration") }}</option>
                            <option value="Daily"> {{ __('Daily') }} </option>
                            <option value="Monthly"> {{ __('Monthly') }} </option>
                            <option value="Yearly"> {{ __('Yearly') }} </option>
                            <option value="Quarterly"> {{ __('Quarterly') }} </option>
                        </select>
                    </div>

                    {{-- Price --}}
                    <div class="control-label col-12 form-group mandatory">
                        {{ Form::label('price', __('Price') . '(' . $currency_symbol . ')', ['class' => 'form-label col-12 ']) }}
                        {{ Form::number('price', isset($list->price) ? $list->price : '', ['class' => 'form-control ', 'placeholder' => __('Price'), 'required' => 'true', 'min' => '1', 'max' => '9223372036854775807', 'id' => 'price']) }}
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6'>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('SEO Details') }}</h3>
                    @if(isset($geminiEnabled) && $geminiEnabled)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="generate-meta-btn" title="{{ __('Generate Meta Details with AI') }}">
                        <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                    </button>
                    @endif
                </div>
                <hr>
                <div class="row card-body">

                    {{-- Meta Title --}}
                    <div class="col-12 form-group">
                        {{ Form::label('title', __('Meta Title'), ['class' => 'form-label text-center']) }}
                        <textarea id="edit_meta_title" name="edit_meta_title" class="form-control" rows="2" {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'required' : '' }} style="height: 75px" placeholder="{{ __('Meta Title') }}">{{ $list->meta_title }}</textarea>
                        <span class="small text-muted">{{ __("Recommended: 55-60 characters, Max size: 255 characters") }}</span>
                        <br>
                    </div>

                    {{-- Meta Image --}}
                    <div class="col-12 form-group card">
                        {{ Form::label('title', __('Meta Image'), ['class' => 'form-label ']) }}
                        <input type="file" name="meta_image" id="meta_image">
                        <span class="small text-muted">{{ __("Allowed: JPG, PNG, JPEG, Max size: 5MB") }}</span>
                        {{-- Meta Image Show --}}
                        @if($list->meta_image != "")
                            <div class="col-md-2 col-sm-12 text-center">
                                <img src="{{ $list->meta_image }}" alt="" height="100px" width="100px">
                            </div>
                        @endif
                    </div>

                    {{-- Meta Description --}}
                    <div class="col-12 form-group">
                        {{ Form::label('description', __('Meta Description'), ['class' => 'form-label text-center']) }}
                        <textarea id="edit_meta_description" name="edit_meta_description" class="form-control" rows="3" placeholder="{{ __('Meta Description') }}">{{ $list->meta_description }}</textarea>
                        <span class="small text-muted">{{ __("Recommended: 155-160 characters, Max size: 255 characters") }}</span>
                        <br>
                    </div>

                    {{-- Meta Keywords --}}
                    <div class="col-12 form-group">
                        {{ Form::label('keywords', __('Meta Keywords'), ['class' => 'form-label']) }}
                        <textarea name="Keywords" id="keywords" class="form-control" rows="3" placeholder="{{ __('Meta Keywords') }}">{{ $list->meta_keywords }}</textarea>
                        <span class="small text-muted">{{ __("Max size: 255 characters") }}</span>
                        ({{ __('Add Comma Separated Keywords') }})
                    </div>
                    <div id="meta-loading" class="col-12 d-none text-primary">
                        <small><i class="bi bi-hourglass-split"></i> {{ __('Generating meta details...') }}</small>
                    </div>
                </div>

            </div>
        </div>

        {{-- Outdoor Facility --}}
        <div class="col-md-12" id="outdoor_facility">
            <div class="card">
                <h3 class="card-header">{{ __('Near By Places') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        @foreach ($facility as $key => $value)
                            <div class='col-md-3  form-group'>
                                {{ Form::label('description', $value->name, ['class' => 'form-check-label']) }}
                                @if (count($value->assign_facilities))
                                    {{ Form::number('facility' . $value->id, $value->assign_facilities[0]['distance'], ['class' => 'form-control mt-3', 'placeholder' => trans('Distance').' ('.$distanceValue.')', 'id' => 'dist' . $value->id,'min' => 0, 'max' => 99999999.9,'step' => '0.1']) }}
                                @else
                                    {{ Form::number('facility' . $value->id, '', ['class' => 'form-control mt-3', 'placeholder' => trans('Distance').' ('.$distanceValue.')', 'id' => 'dist' . $value->id,'min' => 0, 'max' => 99999999.9 ,'step' => '0.1']) }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Facility --}}
        <div class="col-md-12" id="facility">
            <div class="card">
                <h3 class="card-header">{{ __('Facilities') }}</h3>
                <hr>
                {{ Form::hidden('category_count[]', $category, ['id' => 'category_count']) }}
                {{ Form::hidden('parameter_count[]', $parameters, ['id' => 'parameter_count']) }}
                {{ Form::hidden('parameter_add', '', ['id' => 'parameter_add']) }}
                <div id="parameter_type" name=parameter_type class="row card-body">
                    @foreach ($edit_parameters as $res)
                        @if($res->is_required == 1)
                            @if ($res->type_of_parameter == 'file')
                                @if (!empty($res->assigned_parameter->value))
                                @endif
                            @endif
                            <div class="col-md-3 form-group mandatory">
                        @else
                            <div class="col-md-3 form-group">
                        @endif
                            {{ Form::label($res->name, $res->name, ['class' => 'form-label col-12']) }}

                            {{-- DropDown --}}
                            @if ($res->type_of_parameter == 'dropdown')
                                <select name="{{ 'par_' . $res->id }}" class="choosen-select form-select form-control-sm" selected="false" {{ $res->is_required == 1 ? 'required' : '' }} >
                                    <option value=""></option>
                                    @foreach ($res->type_values as $key => $value)
                                        @php
                                            $paramValue = ((isset($value) && !empty($value) && is_array($value)) ? $value['value'] : $value);
                                        @endphp
                                        <option value="{{ $paramValue }}"
                                            {{ $res->assigned_parameter && $res->assigned_parameter->value == $paramValue ? ' selected=selected' : '' }}>
                                            {{ $paramValue }} </option>
                                    @endforeach
                                </select>
                            @endif

                            {{-- Radio Button --}}
                            @if ($res->type_of_parameter == 'radiobutton')
                                @foreach ($res->type_values as $key => $value)
                                    @php
                                        $paramValue = ((isset($value) && !empty($value) && is_array($value)) ? $value['value'] : $value);
                                    @endphp
                                    <input type="radio" name="{{ 'par_' . $res->id }}" id="" value={{ $paramValue }} class="form-check-input" {{ $res->assigned_parameter && $res->assigned_parameter->value == $paramValue ? 'checked' : '' }} {{ $res->is_required == 1 ? 'required' : '' }} >
                                    {{ $paramValue }}
                                @endforeach
                            @endif

                            {{-- Number --}}
                            @if ($res->type_of_parameter == 'number')
                                <input type="number" name="{{ 'par_' . $res->id }}" id="" class="form-control" value="{{ $res->assigned_parameter  && $res->assigned_parameter != 'null' ? $res->assigned_parameter->value : '' }}" {{ $res->is_required == 1 ? 'required' : '' }}>
                            @endif

                            {{-- TextBox --}}
                            @if ($res->type_of_parameter == 'textbox')
                                <input type="text" name="{{ 'par_' . $res->id }}" id="" class="form-control" value="{{ $res->assigned_parameter && $res->assigned_parameter->value != 'null' ? $res->assigned_parameter->value : '' }}" {{ $res->is_required == 1 ? 'required' : '' }}>
                            @endif

                            {{-- TextArea --}}
                            @if ($res->type_of_parameter == 'textarea')
                                <textarea name="{{ 'par_' . $res->id }}" id="" class="form-control" cols="30" rows="3" value="{{ $res->assigned_parameter && $res->assigned_parameter->value != 'null' ? $res->assigned_parameter->value : '' }}" {{ $res->is_required == 1 ? 'required' : '' }}>{{ $res->assigned_parameter && $res->assigned_parameter->value != 'null' ? $res->assigned_parameter->value : '' }}</textarea>
                            @endif

                            {{-- CheckBox --}}
                            @if ($res->type_of_parameter == 'checkbox')
                                @foreach ($res->type_values as $key => $value)
                                    @php
                                        $assignedValues = [];
                                        $paramValue = ((isset($value) && !empty($value) && is_array($value)) ? $value['value'] : $value);
                                        if (!empty($res->assigned_parameter) && !empty($res->assigned_parameter->value)) {
                                            $decoded = $res->assigned_parameter->value;
                                            if (is_array($decoded)) {
                                                $assignedValues = $decoded;
                                            }else{
                                                $assignedValues = [$decoded];
                                            }
                                        }
                                    @endphp

                                    <input type="checkbox"
                                           name="{{ 'par_' . $res->id . '[]' }}"
                                           class="form-check-input"
                                           value="{{ $paramValue }}"
                                           {{ in_array($paramValue, $assignedValues) ? 'checked' : '' }}
                                           {{ $res->is_required == 1 ? 'required' : '' }}>
                                    {{ $paramValue }}


                                @endforeach
                            @endif


                            {{-- FILE --}}
                            @if ($res->type_of_parameter == 'file')
                                @if (!empty($res->assigned_parameter->value))
                                    <a href="{{ url('') . config('global.IMG_PATH') . config('global.PARAMETER_IMG_PATH') . '/' . $res->assigned_parameter->value }}" class="text-center col-12" style="text-align: center"> Click here to View</a> OR
                                    <input type="file" class='form-control' name="{{ 'par_' . $res->id }}" id='edit_param_img'>
                                @else
                                    <input type="file" class='form-control' name="{{ 'par_' . $res->id }}" id='edit_param_img' {{ $res->is_required == 1 ? 'required' : '' }}>
                                @endif
                                <input type="hidden" name="{{ 'par_' . $res->id }}" value="{{ $res->assigned_parameter ? $res->assigned_parameter->value : '' }}">
                            @endif
                        </div>
                        {{-- @endforeach --}}
                    @endforeach
                </div>
            </div>
        </div>
        <div class='col-md-12'>

            <div class="card">
                <h3 class="card-header">{{ __('Location') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        {{-- Google Map --}}
                        <div class='col-md-6'>
                            {{-- Map View --}}
                            <div class="card col-md-12" id="map" style="height: 400px; min-height: 300px;">
                                <!-- Google map -->
                            </div>
                        </div>

                        {{-- Details of Map --}}
                        <div class='col-md-6'>
                            <div class="row">

                                {{-- City --}}
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('city', __('City'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::hidden('city', isset($list->city) ? $list->city : '', ['class' => 'form-control ', 'id' => 'city']) !!}
                                    <input id="searchInput" value="{{ isset($list->city) ? $list->city : '' }}"  class="controls form-control" type="text" placeholder="{{ __('City') }}" required>
                                    {{-- {{ Form::text('city', isset($list->city) ? $list->city : '', ['class' => 'form-control ', 'placeholder' => 'City', 'id' => 'city']) }} --}}
                                </div>

                                {{-- Country --}}
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('country', __('Country'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('country', isset($list->country) ? $list->country : '', ['class' => 'form-control ', 'placeholder' => trans('Country'), 'id' => 'country', 'required' => true]) }}
                                </div>

                                {{-- State --}}
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('state', __('State'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('state', isset($list->state) ? $list->state : '', ['class' => 'form-control ', 'placeholder' => trans('State'), 'id' => 'state', 'required' => true]) }}
                                </div>


                                {{-- Latitude --}}
                                <div class="col-md-6 form-group mandatory">
                                    {{ Form::label('latitude', __('Latitude'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::text('latitude', isset($list->latitude) ? $list->latitude : '', ['class' => 'form-control ', 'id' => 'latitude', 'step' => 'any', 'readonly' => true, 'required' => true, 'placeholder' => trans('Latitude')]) !!}
                                </div>

                                {{-- Longitude --}}
                                <div class="col-md-6 form-group  mandatory">
                                    {{ Form::label('longitude', __('Longitude'), ['class' => 'form-label col-12 ']) }}
                                    {!! Form::text('longitude', isset($list->longitude) ? $list->longitude : '', ['class' => 'form-control ', 'id' => 'longitude', 'step' => 'any', 'readonly' => true, 'required' => true, 'placeholder' => trans('Longitude')]) !!}
                                </div>

                                {{-- Client Address --}}
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Client Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('client_address', isset($list->client_address) ? $list->client_address : (system_setting('company_address') ?? ""), ['class' => 'form-control ', 'placeholder' => trans('Client Address'), 'rows' => '4', 'id' => 'client-address', 'autocomplete' => 'off', 'required' => 'true']) }}
                                </div>

                                {{-- Address --}}
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('address', isset($list->address) ? $list->address : '', ['class' => 'form-control ', 'placeholder' => trans('Address'), 'rows' => '4', 'id' => 'address', 'autocomplete' => 'off', 'required' => 'true']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Images') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        {{-- Title Image --}}
                        <div class="col-md-3 col-sm-12 form-group mandatory card title_card">
                            {{ Form::label('filepond_title', __('Title Image'), ['class' => 'form-label col-12 ']) }}
                            <input type="file" class="filepond" id="filepond_title" name="title_image" {{ $list->title_image == '' ? 'required' : '' }} accept="image/png,image/jpg,image/jpeg,image/webp">
                            @if ($list->title_image)
                                <div class="card1 title_img mt-2">
                                    <img src="{{ $list->title_image }}" alt="Image" class="card1-img">
                                </div>
                            @endif
                        </div>

                        {{-- 3D Image --}}
                        <div class="col-md-3 col-sm-12 card">
                            {{ Form::label('filepond_3d', __('3D Image'), ['class' => 'form-label col-12 ']) }}
                            <input type="file" class="filepond" id="filepond_3d" name="3d_image">
                            @if ($list->three_d_image)
                                <div class="card1 3d_img">
                                    <img src="{{ $list->three_d_image }}" alt="Image" class="card1-img" id="3d_img">
                                    <button data-id="{{ $list->id }}" data-url="{{ route('property.remove-threeD-image',$list->id) }}" class="RemoveBtn1 removeThreeDImage">x</button>
                                </div>
                            @endif
                        </div>

                        {{-- Gallary Images --}}
                        <div class="col-md-3 col-sm-12 card">
                            {{ Form::label('filepond2', __('Gallary Images'), ['class' => 'form-label col-12 ']) }}
                            <input type="file" class="filepond" accept="image/jpg,image/png,image/jpeg,image/webp" id="filepond2" name="gallery_images[]" multiple>
                            <div class="row mt-0">
                                <?php $i = 0; ?>
                                @if (!empty($list->gallery))
                                    @foreach ($list->gallery as $row)
                                        <div class="col-md-6 col-sm-12" id='{{ $row->id }}'>
                                            <div class="card1" style="height:10vh;">
                                                <img src="{{ $row->image }}"
                                                    alt="Image" class="card1-img">
                                                <button data-id="{{ $row->id }}"
                                                    class="RemoveBtn1 RemoveBtngallary">x</button>
                                            </div>
                                        </div>

                                        <?php $i++; ?>
                                    @endforeach
                                @endif
                            </div>
                        </div>



                        {{-- Documents Images --}}
                        <div class="col-md-3 col-sm-12 card">
                            {{ Form::label('edit-documents', __('Documents'), ['class' => 'form-label col-12 ']) }}
                            <input type="file" class="doc-filepond" id="edit-documents" name="documents[]" multiple>
                            <div class="row mt-0 stored-documents-div">
                                @if (!empty($list->documents))
                                    @foreach ($list->documents as $row)
                                        <div class="properties_docs_main_div">
                                            <div class="doc_icon">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="30" width="30" xmlns="http://www.w3.org/2000/svg"><path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M208 64h66.75a32 32 0 0122.62 9.37l141.26 141.26a32 32 0 019.37 22.62V432a48 48 0 01-48 48H192a48 48 0 01-48-48V304"></path><path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M288 72v120a32 32 0 0032 32h120"></path><path fill="none" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32" d="M160 80v152a23.69 23.69 0 01-24 24c-12 0-24-9.1-24-24V88c0-30.59 16.57-56 48-56s48 24.8 48 55.38v138.75c0 43-27.82 77.87-72 77.87s-72-34.86-72-77.87V144"></path></svg>
                                            </div>
                                            <div class="doc_title">
                                                <a href="{{ $row->file }}" target="_blank"><span title="{{ $row->file_name }}"> {{ $row->file_name }} </span></a>
                                            </div>
                                            <div>
                                                <button class="btn btn-danger btn-sm removeDocument" data-id={{ $row->id }}>X</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Video') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3">
                            {{ Form::label('video_type', __('Video Type'), ['class' => 'form-label col-12 ']) }}
                            <select name="video_type" class="form-select" data-parsley-minSelect='1' id="video_type">
                                <option value="" {{ $list->video_type === null ? 'selected' : '' }}>{{ __('Choose Video Type') }}</option>
                                @if(system_setting('show_direct_video_upload') == 1)
                                    <option value="0" {{ $list->video_type == 0 && $list->video_type !== null ? 'selected' : '' }}>{{ __('Custom') }}</option>
                                @endif
                                <option value="1" {{ $list->video_type == 1 ? 'selected' : '' }}>{{ __('Youtube') }}</option>
                                <option value="2" {{ $list->video_type == 2 ? 'selected' : '' }}>{{ __('Vimeo') }}</option>
                            </select>
                        </div>
                        {{-- Video Link --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3 mt-sm-0" id="video_link_div" style="display:{{ ($list->video_type == 1 || $list->video_type == 2) ? 'block' : 'none' }}">
                            {{ Form::label('video_link', __('Video Link'), ['class' => 'form-label']) }}
                            {{ Form::text('video_link', isset($list->video_link) ? $list->video_link : '', ['class' => 'form-control ', 'placeholder' => trans('Video Link'), 'id' => 'video_link', 'autocomplete' => 'off']) }}
                        </div>

                        {{-- Custom Video --}}
                        <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3 mt-sm-0" id="custom_video_div" style="display:{{ $list->video_type == 0 && $list->video_type !== null ? 'block' : 'none' }}">
                            {{ Form::label('custom_video', __('Custom Video'), ['class' => 'form-label']) }}
                            <input type="file" class="filepond" name="custom_video" id="custom_video" accept="video/mp4,video/webm,video/ogg">
                            @if ($list->video_type == 0 && $list->video_link)
                                <div class="mt-2">
                                    <a href="{{ $list->video_link }}" target="_blank" class="btn btn-sm btn-primary">{{ __('View Video') }}</a>
                                </div>
                            @endif
                        </div>

                        {{-- Remove Video --}}
                        <input type="hidden" name="remove_video" id="remove_video" value="0">
                        @if ($list->video_type !== null && $list->getRawOriginal('video_link'))
                            <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3 d-flex align-items-end" id="video-remove-container">
                                <button type="button" class="btn btn-sm btn-danger removeVideoBtn">
                                    <i class="fa fa-trash"></i> {{ __('Remove Video') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Accesibility') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="col-sm-12 col-md-12  col-xs-12 d-flex">
                        <label class="col-sm-1 form-check-label mandatory mt-3 ">{{ __('Is Premium?') }}</label>
                        <div class="form-check form-switch mt-3">
                            <input type="hidden" name="is_premium" id="is_premium" value=" {{ $list->is_premium ? 1 : 0 }}">
                            <input class="form-check-input" type="checkbox" role="switch" {{ $list->is_premium ? 'checked' : '' }} id="is_premium_switch">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Edit Reason --}}
            @if($list->added_by != 0)
                <div class="col-md-12">
                    <div class="card">
                        <h3 class="card-header">{{ __('Edit Reason') }} <span class="text-danger">*</span></h3>
                        <hr>
                        <div class="card-body">
                            <textarea name="edit_reason" id="edit_reason" class="form-control" placeholder="{{ __('Enter Edit Reason') }}" required>{{ $list->edit_reason ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            @endif

            @if(isset($languages) && $languages->count() > 0)
                {{-- Translations Div --}}
                <div class="translation-div">
                    <div class="card">
                        <h3 class="card-header">{{ __('Translations for Property') }}</h3>
                        <hr>
                        <div class="card-body">
                            {{-- Fields for Translations --}}
                            @foreach($languages as $key => $language)
                                @php
                                    $tTitle = $list->translations->where('language_id', $language->id)->where('key', 'title')->first();
                                    $tDesc  = $list->translations->where('language_id', $language->id)->where('key', 'description')->first();
                                @endphp
                                <div class="bg-light p-3 mt-2 rounded">
                                    <h5 class="text-center">{{ $language->name }}</h5>
                                    <label for="translation-title-{{ $language->id }}">{{ __('Title') }}</label>
                                    <div class="form-group">
                                        <input type="hidden" name="translations[{{ $key }}][title][id]" id="translation-id-{{ $language->id }}" value="{{ $tTitle->id ?? '' }}">
                                        <input type="hidden" name="translations[{{ $key }}][title][language_id]" value="{{ $language->id }}">
                                        <input type="text" name="translations[{{ $key }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Title') }}" value="{{ $tTitle->value ?? '' }}">
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label for="translation-property-description-{{ $language->id }}">{{ __('Description') }}</label>
                                        @if(isset($geminiEnabled) && $geminiEnabled)
                                        <button type="button" class="btn btn-sm btn-outline-primary generate-translation-description-btn" data-language-id="{{ $language->id }}" data-language-name="{{ $language->name }}" data-language-code="{{ $language->code ?? $language->name }}" title="{{ __('Generate with AI') }}">
                                            <i class="bi bi-robot"></i> {{ __('Generate with AI') }}
                                        </button>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <input type="hidden" name="translations[{{ $key }}][description][id]" id="translations-description-id-{{ $language->id }}" value="{{ $tDesc->id ?? '' }}">
                                        <input type="hidden" name="translations[{{ $key }}][description][language_id]" value="{{ $language->id }}">
                                        <textarea name="translations[{{ $key }}][description][value]" id="translation-property-description-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Description') }}">{!! $tDesc ? $tDesc->getRawOriginal('value') : '' !!}</textarea>
                                        <div id="translation-property-description-loading-{{ $language->id }}" class="d-none text-primary mt-2">
                                            <small><i class="bi bi-hourglass-split"></i> {{ __('Generating description...') }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
        <div class='col-md-12 d-flex justify-content-end mb-3'>
            <input type="submit" class="btn btn-primary" value="{{ __('Save') }}">
            &nbsp;
            &nbsp;
            <button class="btn btn-secondary reset-form" type="button">{{ __('Reset') }}</button>
        </div>
        {!! Form::close() !!}

    </div>
@endsection
@section('script')
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key={{ env('MAP_API_KEY') }}&libraries=marker,places&loading=async&callback=initMap" async defer></script>
    <script src="{{ asset('assets/js/maps-helper.js') }}"></script>
    <script>
        function initMap() {
            window.initBackendPlacesMap({
                defaultLatitudeSelector: '#latitude',
                defaultLongitudeSelector: '#longitude',
                mapElementId: 'map',
                inputSelector: '#searchInput',
                citySelector: '#city',
                countrySelector: '#country',
                stateSelector: '#state',
                addressSelector: '#address',
                latitudeSelector: '#latitude',
                longitudeSelector: '#longitude'
            });
        }

        $(document).ready(function() {
            $('.reset-form').on('click',function(e){
                e.preventDefault();
                $('#myForm')[0].reset();
            });
            if ($('input[name="property_type"]:checked').val() == 0) {
                $('#duration').hide();
                $('#price_duration').removeAttr('required');
                $('#price_duration').val('');
            } else {
                $('#duration').show();
                $('#price_duration').attr('required', 'true');
                let rentDuration = "{{ $list->rentduration }}";
                $('#price_duration').val(rentDuration).change();
            }

        });
        $('input[name="property_type"]').change(function() {
            // Get the selected value
            var selectedType = $('input[name="property_type"]:checked').val();

            // Perform actions based on the selected value

            if (selectedType == 1) {
                $('#duration').show();
                $('#price_duration').attr('required', 'true');
            } else {
                $('#duration').hide();
                $('#price_duration').removeAttr('required');
            }
        });
        
        // Trigger change on load to set initial state for property type
        $('input[name="property_type"]').trigger('change');

        // Video Type Change Handler
        $('select[name="video_type"]').on('change', function() {
            var videoType = $(this).val();

            // Reset fields
            $('#video_link_div').hide();
            $('#custom_video_div').hide();
            $('#video_link').prop('required', false);
            // $('#custom_video').prop('required', false); // FilePond

            if (videoType == '0') { // Custom
                $('#custom_video_div').show();
                // Avoid requiring if already has video? Maybe handled by backend or just don't require on edit if not changed
                // $('#custom_video').attr('required', true); 
            } else if (videoType == '1' || videoType == '2') { // Youtube or Vimeo
                $('#video_link_div').show();
                $('#video_link').prop('required', true);
                $('#custom_video').removeAttr('required');
            }
        });

        // Trigger change on load to set initial state for video type
        $('select[name="video_type"]').trigger('change');
        $(".RemoveBtngallary").click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            Swal.fire({
                title: window.trans["Are you sure"],
                text: window.trans["You want to delete it ?"],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: window.trans["Yes Delete"],
                cancelButtonText: window.trans["Cancel"],
                reverseButtons: true,
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('property.removeGalleryImage') }}",

                        type: "POST",
                        data: {
                            '_token': "{{ csrf_token() }}",
                            "id": id
                        },
                        success: function(response) {

                            if (response.error == false) {
                                Toastify({
                                    text: 'Image Delete Successful',
                                    duration: 6000,
                                    close: !0,
                                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                                }).showToast();
                                $("#" + id).html('');
                            } else if (response.error == true) {
                                Toastify({
                                    text: 'Something Wrong !!!',
                                    duration: 6000,
                                    close: !0,
                                    backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
                                }).showToast()
                            }
                        },
                        error: function(xhr) {}
                    });
                }
            })

        });
        $(document).on('click', '#filepond_3d', function(e) {

            $('.3d_img').hide();
        });
        $(document).on('click', '#filepond_title', function(e) {

            $('.title_img').hide();
        });
        jQuery(document).ready(function() {
            initMap();
        });
        $(document).ready(function() {
            $('.parsley-error filled,.parsley-required').attr("aria-hidden", "true");
            $('.parsley-error filled,.parsley-required').hide();

        });
        $(document).ready(function() {



            $("#is_premium_switch").on('change', function() {
                $("#is_premium_switch").is(':checked') ? $("#is_premium").val(1) : $(
                        "#is_premium")
                    .val(0);
            });

            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize,
                FilePondPluginFileValidateType);

            $('#meta_image').filepond({
                credits: null,
                allowFileSizeValidation: "true",
                maxFileSize: '5000KB',
                labelMaxFileSizeExceeded: 'File is too large',
                labelMaxFileSize: 'Maximum file size is {filesize}',
                allowFileTypeValidation: true,
                acceptedFileTypes: ['image/*'],
                labelFileTypeNotAllowed: 'File of invalid type',
                fileValidateTypeLabelExpectedTypes: 'Expects {allButLastType} or {lastType}',
                storeAsFile: true,

            });
        });
        $("#title").on('keyup',function(e){
            let title = $(this).val();
            let id = "{{ $id }}";
            let slugElement = $("#slug");
            if(title){
                $.ajax({
                    type: 'POST',
                    url: "{{ route('property.generate-slug') }}",
                    data: {
                        '_token': $('meta[name="csrf-token"]').attr('content'),
                        title: title,
                        id: id
                    },
                    beforeSend: function() {
                        slugElement.attr('readonly', true).val('Please wait....')
                    },
                    success: function(response) {
                        if(!response.error){
                            if(response.data){
                                slugElement.removeAttr('readonly').val(response.data);
                            }else{
                                slugElement.removeAttr('readonly').val("")
                            }
                        }
                    }
                });
            }else{
                slugElement.removeAttr('readonly', true).val("")
            }
        });




        $(".removeDocument").click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            Swal.fire({
                title: window.trans["Are you sure"],
                text: window.trans["You want to delete it ?"],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: window.trans["Yes Delete"],
                cancelButtonText: window.trans["Cancel"],
                reverseButtons: true,
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('property.remove-documents') }}",
                        type: "POST",
                        data: {
                            '_token': "{{ csrf_token() }}",
                            "id": id
                        },
                        success: function(response) {
                            if (response.error == false) {
                                Toastify({
                                    text: window.trans['Document Deleted Successfully'],
                                    duration: 1500,
                                    close: !0,
                                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                                }).showToast();

                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);

                                $("#" + id).html('');
                            } else if (response.error == true) {
                                Toastify({
                                    text: window.trans['Something Went Wrong'],
                                    duration: 5000,
                                    close: !0,
                                    backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
                                }).showToast()
                            }
                        },
                        error: function(xhr) {}
                    });
                }
            })

        });


        $(".removeThreeDImage").on('click',function(e){
            e.preventDefault();
            let url = $(this).data('url');
            showDeletePopupModal(url,{
                successCallBack: function () {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }, errorCallBack: function (response) {
                }
            })
        })

        $(".removeVideoBtn").on('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: window.trans["Are you sure"],
                text: window.trans["You want to delete it ?"],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: window.trans["Yes Delete"],
                cancelButtonText: window.trans["Cancel"],
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#remove_video').val(1);
                    $('#video-remove-container').remove();
                    $('select[name="video_type"]').val('').trigger('change');
                    Toastify({
                        text: '{{ trans("Video will be removed on save") }}',
                        duration: 3000,
                        close: true,
                        backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                    }).showToast();
                }
            });
        });

        function successFunction(){
            window.location.reload();
        }

        // Gemini AI Integration
        @if(isset($geminiEnabled) && $geminiEnabled)
        $(document).ready(function() {
            // Generate Description
            $('#generate-description-btn').on('click', function() {
                const title = $('#title').val();
                const city = $('#city').val();
                const state = $('#state').val();
                const country = $('#country').val();
                const price = $('#price').val();
                const propertyType = $('input[name="property_type"]:checked').val();
                const address = $('#address').val();
                const category_id = $('#category').val();

                if (!title) {
                    showErrorToast('{{ __("Please enter a title first") }}');
                    return;
                }

                const btn = $(this);
                const loadingDiv = $('#description-loading');
                const descriptionField = $('#description');

                btn.prop('disabled', true);
                loadingDiv.removeClass('d-none');

                $.ajax({
                    url: '{{ route("gemini.generate-description") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        entity_type: 'property',
                        entity_id: {{ $id }},
                        category_id: category_id,
                        title: title,
                        location: address,
                        city: city,
                        state: state,
                        country: country,
                        price: price,
                        property_type: propertyType == 1 ? 'rent' : 'sell'
                    },
                    success: function(response) {
                        if (!response.error && response.data && response.data.description) {
                            descriptionField.val(response.data.description);
                            if (response.data.cached) {
                                console.log('{{ __("Used cached result") }}');
                            }
                        } else {
                            showErrorToast(response.message || '{{ __("Failed to generate description") }}');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || '{{ __("An error occurred") }}';
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
                const price = $('#price').val();

                if (!title) {
                    showErrorToast('{{ __("Please enter a title first") }}');
                    return;
                }

                const btn = $(this);
                const loadingDiv = $('#meta-loading');
                const metaTitleField = $('#edit_meta_title');
                const metaDescriptionField = $('#edit_meta_description');
                const metaKeywordsField = $('#keywords');

                btn.prop('disabled', true);
                loadingDiv.removeClass('d-none');

                $.ajax({
                    url: '{{ route("gemini.generate-meta") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        entity_type: 'property',
                        entity_id: {{ $id }},
                        title: title,
                        location: address,
                        city: city,
                        price: price
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
                                console.log('{{ __("Used cached result") }}');
                            }
                        } else {
                            showErrorToast(response.message || '{{ __("Failed to generate meta details") }}');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || '{{ __("An error occurred") }}';
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
                const price = $('#price').val();
                const propertyType = $('input[name="property_type"]:checked').val();
                const address = $('#address').val();
                const category_id = $('#category').val();
                const descriptionField = $('#translation-property-description-' + languageId);
                const loadingDiv = $('#translation-property-description-loading-' + languageId);
                const btn = $(this);

                if (!title) {
                    showErrorToast('{{ __("Please enter a title first") }}');
                    return;
                }

                btn.prop('disabled', true);
                loadingDiv.removeClass('d-none');

                $.ajax({
                    url: '{{ route("gemini.generate-description") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        entity_type: 'property',
                        category_id: category_id,
                        title: title,
                        location: address,
                        city: city,
                        state: state,
                        country: country,
                        price: price,
                        property_type: propertyType == 1 ? 'rent' : 'sell',
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
                            showErrorToast(response.message || '{{ __("Failed to generate description") }}');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || '{{ __("An error occurred") }}';
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
    </script>
@endsection
