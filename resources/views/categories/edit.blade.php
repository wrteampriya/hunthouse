@extends('layouts.main')

@section('title')
    {{ __('Edit Category') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
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
                        <h4>{{ __('Edit Category') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <form action="{{ url('categories-update') }}" method="POST" enctype="multipart/form-data" data-parsley-validate>
                        {{ csrf_field() }}
                        <input type="hidden" name="edit_id" value="{{ $category->id }}">
                        <input type="hidden" value="{{ system_setting('svg_clr') }}" id="svg_clr">

                        <div class="row">
                            {{-- Category --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('edit_category', __('Category'), ['class' => 'form-label text-center']) }}
                                <input type="text" id="edit_category" class="form-control" placeholder="{{ __('Category') }}" name="edit_category" value="{{ $category->category }}" data-parsley-required="true">
                            </div>

                            {{-- Slug --}}
                            <div class="col-md-6 col-12 form-group">
                                {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12']) }}
                                <input type="text" name="slug" class="form-control" id="edit-slug" placeholder="{{ __('Slug') }}" value="{{ $category->slug_id }}">
                                <small class="text-danger text-sm">{{ __("Only Small English Characters, Numbers And Hypens Allowed") }}</small>
                            </div>

                            {{-- Facilities --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('type', __('Facilities'), ['class' => 'form-label text-center']) }}
                                @php
                                    $selectedParams = !empty($category->parameter_types) ? explode(',', $category->parameter_types) : [];
                                @endphp
                                <select data-placeholder="{{ __('Choose Facilities') }}" name="edit_parameter_type[]" id="edit_parameter_type" multiple class="form-select form-control" data-parsley-required="true">
                                    @foreach ($parameters as $parameter)
                                        <option value="{{ $parameter->id }}" {{ in_array($parameter->id, $selectedParams) ? 'selected' : '' }}>{{ $parameter->name }} {{ $parameter->is_required == 1 ? "*" : "" }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Image --}}
                            <div class="col-md-6 col-sm-12 form-group">
                                {{ Form::label('image', __('Image'), ['class' => 'form-label text-center']) }}
                                <input type="file" name="edit_image" id="edit_image" class="filepond" accept="image/svg+xml">
                                <div class="mt-2">
                                    <img src="{{ $category->image }}" height="100" width="110" id="current_image" />
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            {{-- Sequence --}}
                            <div class="col-12 form-group">
                                {{ Form::label('Sequence', __('Sequence'), ['class' => 'form-label']) }}
                                <div id="par" class="d-flex row"></div>
                                <input type="hidden" name="update_seq" id="update_seq">
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
                                            <input type="text" name="edit_meta_title" class="form-control" id="edit_meta_title" placeholder="{{ __('Meta Title') }}" value="{{ $category->meta_title }}">
                                        </div>

                                        {{-- Meta Keywords --}}
                                        <div class="col-xl-4 col-md-6 col-sm-12 form-group">
                                            {{ Form::label('keywords', __('Meta Keywords'), ['class' => 'form-label text-center']) }}
                                            <input type="text" name="edit_keywords" class="form-control" id="edit_keywords" placeholder="{{ __('Meta Keywords') }}" value="{{ $category->meta_keywords }}">
                                        </div>

                                        {{-- Meta Description --}}
                                        <div class="col-xl-4 col-md-6 col-sm-12 form-group">
                                            {{ Form::label('description', __('Meta Description'), ['class' => 'form-label text-center']) }}
                                            <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Meta description should be around 395 pixels in length') }}"></i>
                                            <textarea id="edit_meta_description" name="edit_meta_description" class="form-control" placeholder="{{ __('Meta Description') }}">{{ $category->meta_description }}</textarea>
                                        </div>

                                        {{-- Schema Markup — not handled by the website yet this version, hidden until it is. --}}
                                        @if(false)
                                        <div class="col-12 form-group">
                                            {{ Form::label('schema_markup', __('Schema Markup'), ['class' => 'form-label text-center']) }}
                                            <textarea id="schema_markup" name="schema_markup" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}">{{ $category->schema_markup }}</textarea>
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
                                                @php
                                                    $translation = $category->translations->where('language_id', $language->id)->where('key', 'category')->first();
                                                    $tMetaTitle = $category->translations->where('language_id', $language->id)->where('key', 'meta_title')->first();
                                                    $tMetaDescription = $category->translations->where('language_id', $language->id)->where('key', 'meta_description')->first();
                                                    $tMetaKeywords = $category->translations->where('language_id', $language->id)->where('key', 'meta_keywords')->first();
                                                    $tSchemaMarkup = $category->translations->where('language_id', $language->id)->where('key', 'schema_markup')->first();
                                                @endphp
                                                <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $language->id }}][category][id]" value="{{ $translation->id ?? '' }}">
                                                        <label for="edit-translation-{{ $language->id }}">{{ __('Category') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][category][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $language->id }}][category][value]" id="edit-translation-{{ $language->id }}" class="form-control" value="{{ $translation->value ?? '' }}" placeholder="{{ __('Enter Category') }}">
                                                    </div>
                                                    {{-- Multi-language SEO / schema markup — not handled by
                                                         the website yet this version, hidden until it is. --}}
                                                    @if(false)
                                                    <div class="form-group mt-2">
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_title][id]" value="{{ $tMetaTitle->id ?? '' }}">
                                                        <label for="edit-translation-meta-title-{{ $language->id }}">{{ __('SEO Title') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_title][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $language->id }}][meta_title][value]" id="edit-translation-meta-title-{{ $language->id }}" class="form-control" value="{{ $tMetaTitle->value ?? '' }}" placeholder="{{ __('Enter SEO Title') }}">
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_description][id]" value="{{ $tMetaDescription->id ?? '' }}">
                                                        <label for="edit-translation-meta-description-{{ $language->id }}">{{ __('SEO Description') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_description][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][meta_description][value]" id="edit-translation-meta-description-{{ $language->id }}" class="form-control" rows="3" placeholder="{{ __('Enter SEO Description') }}">{{ $tMetaDescription->value ?? '' }}</textarea>
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_keywords][id]" value="{{ $tMetaKeywords->id ?? '' }}">
                                                        <label for="edit-translation-meta-keywords-{{ $language->id }}">{{ __('SEO Keywords') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][meta_keywords][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][meta_keywords][value]" id="edit-translation-meta-keywords-{{ $language->id }}" class="form-control" rows="2" placeholder="{{ __('Enter SEO Keywords') }}">{{ $tMetaKeywords->value ?? '' }}</textarea>
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <input type="hidden" name="translations[{{ $language->id }}][schema_markup][id]" value="{{ $tSchemaMarkup->id ?? '' }}">
                                                        <label for="edit-translation-schema-markup-{{ $language->id }}">{{ __('Schema Markup') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][schema_markup][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][schema_markup][value]" id="edit-translation-schema-markup-{{ $language->id }}" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}">{!! $tSchemaMarkup ? $tSchemaMarkup->getRawOriginal('value') : '' !!}</textarea>
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
                                <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script src="https://bevacqua.github.io/dragula/dist/dragula.js"></script>
    <script>
        $(document).ready(function() {
            $('#edit_parameter_type').chosen();
            initSequence();
            initDragula();
        });

        // Build sequence badges from current parameter_types order
        function initSequence() {
            var type = "{{ $category->parameter_types }}";
            if (!type) return;

            var type_arr = type.split(',');
            $('#par').empty();

            $.each(type_arr, function(k, v) {
                var text = $('#edit_parameter_type option[value="' + v + '"]').text();
                if (v && text) {
                    $('#par').append(
                        '<div class="col-md-3">' +
                        '<div class="seq" id="' + v + '"><span class="badge rounded-pill" style="background:var(--bs-primary);margin-left:2px;cursor:grab;">' +
                        text + '</span></div></div>'
                    );
                }
            });
            updateSequenceValue();
        }

        function updateSequenceValue() {
            var sequence = [];
            var existingIDs = {};
            $('.seq').each(function() {
                var id = $(this).attr('id');
                if (!existingIDs[id]) {
                    existingIDs[id] = true;
                    sequence.push(id);
                }
            });
            $('#update_seq').val(sequence.join(','));
        }

        function initDragula() {
            var containers = [document.getElementById('par')];
            dragula(containers).on('drop', function() {
                updateSequenceValue();
            });
        }

        // Update sequence when facilities selection changes
        $('#edit_parameter_type').on('change', function(e) {
            e.preventDefault();

            // Remove badges for deselected options
            $('#edit_parameter_type option:not(:selected)').each(function() {
                $('#div_' + this.value).remove();
            });

            var type_arr = $(this).val() || [];
            $('#par').empty();

            $.each(type_arr, function(k, v) {
                var text = $('#edit_parameter_type option[value="' + v + '"]').text();
                if (v && text) {
                    $('#par').append(
                        '<div class="col-md-3">' +
                        '<div class="seq" id="' + v + '"><span class="badge rounded-pill" style="background:var(--bs-primary);margin-left:2px;cursor:grab;">' +
                        text + '</span></div></div>'
                    );
                }
            });
            updateSequenceValue();
        });

        // Auto-generate slug from category name
        $('#edit_category').on('input', function() {
            var categoryName = $(this).val();
            if (categoryName.length > 0) {
                $.ajax({
                    url: "{{ route('category.generate-slug') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        category: categoryName,
                        id: '{{ $category->id }}'
                    },
                    success: function(response) {
                        if (!response.error) {
                            $('#edit-slug').val(response.data);
                        }
                    }
                });
            }
        });
    </script>
@endsection
