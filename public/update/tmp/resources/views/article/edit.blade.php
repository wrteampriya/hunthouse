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

                            {{-- Country --}}
                            <div class="col-md-12 col-12 form-group">
                                {{ Form::label('country_id', __('Country'), ['class' => 'form-label col-12']) }}
                                <select name="country_id" class="choosen-select form-select form-control-sm">
                                    <option value="">{{ __('Global (all countries)') }}</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}" {{ $country->id == $list->country_id ? 'selected' : '' }}>{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Description --}}
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('description', __('Description'), ['class' => 'form-label col-12']) }}
                                {{ Form::textarea('description', $list->description, [ 'class' => 'form-control tinymce_editor', 'data-parsley-required' => 'true', ]) }}
                            </div>

                            {{-- SEO Details (default language) --}}
                            <div class="col-md-12 col-sm-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="mb-0">{{ __('SEO Details') }} ({{ isset($languages) && $languages->isNotEmpty() ? $languages->first()->name : __('Default') }})</h3>
                                    </div>
                                    <hr>
                                    <div class="row card-body">
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

                                        {{-- Meta Image (OG Image) — not handled by the website yet this version, hidden until it is. --}}
                                        @if(false)
                                        <div class="col-md-12 col-sm-12 form-group">
                                            {{ Form::label('meta_image', __('Meta Image (OG Image)'), ['class' => 'form-label text-center']) }}
                                            <input accept="image/jpg,image/png,image/jpeg,image/webp" name="meta_image" type="file" class="form-control" id="meta_image">
                                            @if($list->meta_image)
                                                <img src="{{ $list->meta_image }}" alt="" height="80" class="mt-2">
                                            @endif
                                        </div>
                                        @endif

                                        {{-- Schema Markup — not handled by the website yet this version, hidden until it is. --}}
                                        @if(false)
                                        <div class="col-md-12 col-sm-12 form-group">
                                            {{ Form::label('schema_markup', __('Schema Markup (JSON-LD)'), ['class' => 'form-label text-center']) }}
                                            <textarea id="schema_markup" name="schema_markup" class="form-control" rows="6">{{ $list->schema_markup ?? '' }}</textarea>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div">
                                <div class="card">
                                    <h3 class="card-header">{{ __('Translations for Article') }}</h3>
                                    <hr>
                                    <div class="card-body p-0">
                                        <ul class="nav nav-tabs" id="articleTranslationTabs" role="tablist">
                                            @foreach($languages as $key => $language)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                        {{ $language->name }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content p-3" id="articleTranslationTabsContent">
                                            @foreach($languages as $key => $language)
                                                @php
                                                    $tTitle = $list->translations->where('language_id', $language->id)->where('key', 'title')->first();
                                                    $tDesc  = $list->translations->where('language_id', $language->id)->where('key', 'description')->first();
                                                    $tMetaTitle = $list->translations->where('language_id', $language->id)->where('key', 'meta_title')->first();
                                                    $tMetaDesc  = $list->translations->where('language_id', $language->id)->where('key', 'meta_description')->first();
                                                    $tMetaKeywords = $list->translations->where('language_id', $language->id)->where('key', 'meta_keywords')->first();
                                                    $tSchemaMarkup = $list->translations->where('language_id', $language->id)->where('key', 'schema_markup')->first();
                                                @endphp
                                                <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
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
                                                    {{-- Multi-language SEO / schema markup — not handled by
                                                         the website yet this version, hidden until it is. --}}
                                                    @if(false)
                                                    <label for="translation-meta_title-{{ $language->id }}">{{ __('Meta Title') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_title][id]" value="{{ $tMetaTitle->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_title][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $key }}][meta_title][value]" id="translation-meta_title-{{ $language->id }}" class="form-control" value="{{ $tMetaTitle->value ?? '' }}" placeholder="{{ __('Enter Meta Title') }}">
                                                    </div>
                                                    <label for="translation-meta_description-{{ $language->id }}">{{ __('Meta Description') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_description][id]" value="{{ $tMetaDesc->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_description][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $key }}][meta_description][value]" id="translation-meta_description-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Meta Description') }}">{{ $tMetaDesc->value ?? '' }}</textarea>
                                                    </div>
                                                    <label for="translation-meta_keywords-{{ $language->id }}">{{ __('Meta Keywords') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_keywords][id]" value="{{ $tMetaKeywords->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][meta_keywords][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $key }}][meta_keywords][value]" id="translation-meta_keywords-{{ $language->id }}" class="form-control" value="{{ $tMetaKeywords->value ?? '' }}" placeholder="{{ __('Enter Meta Keywords') }}">
                                                    </div>
                                                    <label for="translation-schema_markup-{{ $language->id }}">{{ __('Schema Markup (JSON-LD)') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][schema_markup][id]" value="{{ $tSchemaMarkup->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][schema_markup][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $key }}][schema_markup][value]" id="translation-schema_markup-{{ $language->id }}" class="form-control" rows="6" placeholder="{{ __('Enter Schema Markup') }}">{{ $tSchemaMarkup->value ?? '' }}</textarea>
                                                    </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
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
