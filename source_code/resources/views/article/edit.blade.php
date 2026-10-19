@extends('layouts.main')

@section('title')
    {{ __('Update Article') }}
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
                            <a href="{{ route('article.index') }}" id="subURL">{{ __('View Article') }}</a>
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
    <section class="section">
        <div class="row article-form-layout">
            <div class="col-12 col-xxl-7 article-form-main">
                <div class="card">
                    <div class="card-header add_article_header">
                        {{ __('Update Article') }}
                    </div>
                    <hr>
                    {!! Form::open([ 'route' => ['article.update', $id], 'data-parsley-validate', 'files' => true, 'method' => 'PATCH', ]) !!}
                    <div class="card-body">
                        <div class="row">

                            {{-- Title --}}
                            <div class="col-sm-12 col-md-12 col-lg-6  form-group mandatory">
                                {{ Form::label('title', __('Title'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('title', $list->title, [ 'class' => 'form-control ', 'placeholder' => trans('Title'), 'data-parsley-required' => 'true', 'id' => 'title', ]) }}
                            </div>

                            {{-- Slug --}}
                            <div class="col-sm-12 col-md-12 col-lg-6  form-group">
                                {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('slug', $list->slug_id, [ 'class' => 'form-control ', 'placeholder' => __('Slug'), 'id' => 'slug' ]) }}
                                <small class="text-danger text-sm">{{ __("Only Small English Characters, Numbers And Hypens Allowed") }}</small>
                            </div>

                            {{-- Category --}}
                            <div class="col-md-12 col-12 form-group mandatory">
                                {{ Form::label('category', __('Category'), ['class' => 'form-label col-12 ']) }}
                                <select name="category" class="form-select form-control-sm" data-parsley-minSelect='1' required>
                                    <option value="0"> General </option>
                                    @foreach ($category as $row)
                                        <option value="{{ $row->id }}" data-parametertypes='{{ $row->parameter_types }}' {{ $row->id == $list->category_id ? 'selected' : '' }}>
                                            {{ $row->category }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Image --}}
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('image', __('Image'), ['class' => 'col-12 form-label']) }}
                                <input accept="image/*" name='image' type='file' class="filepond" id="edit_image" />
                                <div class="edit_article_img">
                                    <img src="{{ $list->image }}" alt="" class="edit_img" height="300px" width="500px">
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('description', __('Description'), ['class' => 'form-label col-12']) }}
                                {{ Form::textarea('description', $list->description, [ 'class' => 'form-control tinymce_editor', 'data-parsley-required' => 'true', ]) }}
                            </div>

                            {{-- Meta title --}}
                            <div class="col-md-12 col-sm-12 form-group {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'mandatory' : '' }}"> {{ Form::label('title', __('Meta Title'), ['class' => 'form-label text-center']) }}
                                <input type="text" name="edit_meta_title" class="form-control" id="edit_meta_title" oninput="getWordCount('edit_meta_title','edit_meta_title_count','19.9px arial')" placeholder="{{ __('Meta Title') }}" value=" {{ $list->meta_title ?? '' }}" {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'required' : '' }}>
                                <span class="small text-muted">{{ __("Recommended: 55-60 characters, Max size: 255 characters") }}</span>
                            </div>

                            {{-- Meta Keywords --}}
                            <div class="col-md-12 col-sm-12 form-group {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'mandatory' : '' }}">
                                {{ Form::label('title', __('Meta Keywords'), ['class' => 'form-label text-center']) }}
                                <input type="text" name="meta_keywords" class="form-control" id="meta_keywords" placeholder="{{ __('Meta Keywords') }}" value=" {{ $list->meta_keywords ?? '' }}" {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'required' : '' }}>
                                <span class="small text-muted">{{ __("Max size: 255 characters") }}</span>
                            </div>

                            {{-- Meta Description --}}
                            <div class="col-md-12 col-sm-12 form-group {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'mandatory' : '' }}">
                                {{ Form::label('description', __('Meta Description'), ['class' => 'form-label text-center']) }}
                                <textarea id="edit_meta_description" name="edit_meta_description" class="form-control" {{ system_setting('seo_settings') != '' && system_setting('seo_settings') == 1 ? 'required' : '' }}>{{ $list->meta_description ?? '' }}</textarea>
                                <span class="small text-muted">{{ __("Recommended: 155-160 characters, Max size: 255 characters") }}</span>
                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div">
                                <div class="card">
                                    <h3 class="card-header">{{ __('Translations for Article') }}</h3>
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
                                                    <input type="hidden" name="translations[{{ $key }}][title][id]" id="translation-title-id-{{ $language->id }}" value="{{ $tTitle->id ?? '' }}">
                                                    <input type="hidden" name="translations[{{ $key }}][title][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $key }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" value="{{ $tTitle->value ?? '' }}" placeholder="{{ __('Enter Title') }}">
                                                </div>
                                                <label for="translation-description-{{ $language->id }}">{{ __('Description') }}</label>
                                                <div class="form-group">
                                                    <input type="hidden" name="translations[{{ $key }}][description][id]" id="translation-description-id-{{ $language->id }}" value="{{ $tDesc->id ?? '' }}">
                                                    <input type="hidden" name="translations[{{ $key }}][description][language_id]" value="{{ $language->id }}">
                                                    {{ Form::textarea('translations['.$key.'][description][value]', $tDesc->value ?? '', [ 'class' => 'form-control', 'id' => 'translation-description-'.$language->id, 'placeholder' => __('Enter Description')]) }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Save Button --}}
                        <div class="card-footer">
                            <div class="col-12 d-flex justify-content-end">
                                {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                            </div>
                        </div>

                        {!! Form::close() !!}
                    </div>
                </div>
            </div>

            <div class="col-12 col-xxl-5 article-form-sidebar">

                <div class="card edit_recent_articles">
                    <div class="card-header add_article_header">
                        {{ __('Recent Articles') }}
                    </div>
                    <hr>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($recent_articles as $row)
                                <div class="col-md-12 d-flex recent_articles">
                                    <img class="article_img" src="{{ $row->image != '' ? $row->image : url('assets/images/bg/Login_BG.jpg') }}" alt="">
                                    <div class="article_details">
                                        <div class="article_category" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $row->category ? $row->category->category : 'General' }}">
                                            {{ $row->category ? $row->category->category : 'General' }}
                                        </div>
                                        <div class="article_title">
                                            {{ $row->title }}
                                        </div>
                                        <div class="article_description">
                                            @php
                                                echo Str::substr(strip_tags($row->description), 0, 180) . '...';
                                            @endphp
                                        </div>
                                        <div class="article_date">
                                            {{ date('d M Y', strtotime($row->created_at)) }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // ... existing code for edit functionality ...
        });

        $("#title").on('keyup',function(e){
            // ... existing slug generation code ...
        });
    </script>
@endsection
