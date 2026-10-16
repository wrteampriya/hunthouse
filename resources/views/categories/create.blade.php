@extends('layouts.main')

@section('title')
    {{ __('Add Category') }}
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
                            <a href="{{ route('categories.index') }}">{{ __('Categories') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Add') }}</li>
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
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Create Category') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    {!! Form::open(['url' => route('categories.store'), 'data-parsley-validate', 'files' => true]) !!}
                    <div class="row">

                        {{-- Category --}}
                        <div class="col-md-6 col-sm-12 form-group mandatory">
                            {{ Form::label('category', __('Category'), ['class' => 'form-label text-center']) }}
                            {{ Form::text('category', '', ['class' => 'form-control', 'placeholder' => trans('Category'), 'data-parsley-required' => 'true', 'id' => 'category']) }}
                        </div>

                        {{-- Slug --}}
                        <div class="col-md-6 col-12 form-group">
                            {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12']) }}
                            {{ Form::text('slug', '', ['class' => 'form-control', 'placeholder' => __('Slug'), 'id' => 'slug']) }}
                            <small class="text-danger text-sm">{{ __("Only Small English Characters, Numbers And Hypens Allowed") }}</small>
                        </div>

                        {{-- Facilities --}}
                        <div class="col-md-6 col-sm-12 form-group mandatory">
                            {{ Form::label('type', __('Facilities'), ['class' => 'form-label text-center']) }}
                            <select data-placeholder="{{ __('Choose Facilities') }}" name="parameter_type[]" class="form-control form-select chosen-select" id="select_parameter_type" multiple data-parsley-required="true" data-parsley-minSelect='1'>
                                @foreach ($parameters as $parameter)
                                    <option value="{{ $parameter->id }}">{{ $parameter->name }} {{ $parameter->is_required == 1 ? "*" : "" }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Image --}}
                        <div class="col-md-6 col-sm-12 form-group mandatory">
                            {{ Form::label('image', __('Image'), ['class' => 'form-label text-center']) }}
                            <input type="file" class="filepond" id="image" name="image" accept="image/svg+xml" required>
                        </div>

                    </div>
                    <div class="row">

                        {{-- SEO Details (default language) --}}
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="mb-0">{{ __('SEO Details') }} ({{ isset($languages) && $languages->isNotEmpty() ? $languages->first()->name : __('Default') }})</h3>
                                </div>
                                <hr>
                                <div class="row card-body">
                                    {{-- Meta Title --}}
                                    <div class="col-xl-4 col-md-6 col-sm-12 form-group">
                                        {{ Form::label('title', __('Meta Title'), ['class' => 'form-label text-center']) }}
                                        <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Meta title should not exceed 580 pixels in width (~60 characters). Titles longer than this may get cut off in search results') }}"></i>
                                        <input type="text" name="meta_title" class="form-control" id="meta_title" placeholder="{{ __('Meta Title') }}">
                                    </div>

                                    {{-- Meta Keywords --}}
                                    <div class="col-xl-4 col-md-6 col-sm-12 form-group">
                                        {{ Form::label('title', __('Meta Keywords'), ['class' => 'form-label text-center']) }}
                                        <input type="text" name="meta_keywords" class="form-control" id="meta_keywords" placeholder="{{ __('Meta Keywords') }}">
                                    </div>

                                    {{-- Meta Description --}}
                                    <div class="col-xl-4 col-md-6 col-sm-12 form-group">
                                        {{ Form::label('description', __('Meta Description'), ['class' => 'form-label text-center']) }}
                                        <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Meta description should be around 395 pixels in length') }}"></i>
                                        <textarea id="meta_description" name="meta_description" class="form-control" placeholder="{{ __('Meta Description') }}"></textarea>
                                    </div>

                                    {{-- Schema Markup — not handled by the website yet this version, hidden until it is. --}}
                                    @if(false)
                                    <div class="col-12 form-group">
                                        {{ Form::label('schema_markup', __('Schema Markup'), ['class' => 'form-label text-center']) }}
                                        <textarea id="schema_markup" name="schema_markup" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            <div class="translation-div">
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations for Category') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <ul class="nav nav-tabs" id="categoryTranslationTabs" role="tablist">
                                        @foreach($languages as $key => $language)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                    {{ $language->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content p-3" id="categoryTranslationTabsContent">
                                        @foreach($languages as $key => $language)
                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                <div class="form-group">
                                                    <label for="translation-{{ $language->id }}">{{ __('Category') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][category][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $language->id }}][category][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Category') }}">
                                                </div>
                                                {{-- Multi-language SEO / schema markup — not handled by
                                                     the website yet this version, hidden until it is. --}}
                                                @if(false)
                                                <div class="form-group mt-2">
                                                    <label for="translation-meta-title-{{ $language->id }}">{{ __('SEO Title') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][meta_title][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $language->id }}][meta_title][value]" id="translation-meta-title-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter SEO Title') }}">
                                                </div>
                                                <div class="form-group mt-2">
                                                    <label for="translation-meta-description-{{ $language->id }}">{{ __('SEO Description') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][meta_description][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][meta_description][value]" id="translation-meta-description-{{ $language->id }}" class="form-control" rows="3" placeholder="{{ __('Enter SEO Description') }}"></textarea>
                                                </div>
                                                <div class="form-group mt-2">
                                                    <label for="translation-meta-keywords-{{ $language->id }}">{{ __('SEO Keywords') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][meta_keywords][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][meta_keywords][value]" id="translation-meta-keywords-{{ $language->id }}" class="form-control" rows="2" placeholder="{{ __('Enter SEO Keywords') }}"></textarea>
                                                </div>
                                                <div class="form-group mt-2">
                                                    <label for="translation-schema-markup-{{ $language->id }}">{{ __('Schema Markup') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][schema_markup][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][schema_markup][value]" id="translation-schema-markup-{{ $language->id }}" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                                                </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-sm-12 col-md-12 text-end" style="margin-top:2%;">
                            <a href="{{ route('categories.index') }}" class="btn btn-secondary me-1 mb-1">{{ __('Cancel') }}</a>
                            {{ Form::submit(trans('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
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
        $(document).ready(function() {
            $('#select_parameter_type').chosen();
        });

        // Auto-generate slug from category name
        $('#category').on('input', function() {
            var categoryName = $(this).val();
            if (categoryName.length > 0) {
                $.ajax({
                    url: "{{ route('category.generate-slug') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        category: categoryName
                    },
                    success: function(response) {
                        if (!response.error) {
                            $('#slug').val(response.data);
                        }
                    }
                });
            }
        });
    </script>
@endsection
