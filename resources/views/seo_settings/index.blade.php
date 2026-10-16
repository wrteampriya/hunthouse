@extends('layouts.main')

@section('title')
    {{ __('SEO Settings') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-4">
                <div class="card">

                    <div class="card-header">
                        <div class="divider">
                            <div class="divider-text">
                                <h4>{{ __('SEO Settings') }}</h4>
                            </div>
                        </div>
                    </div>

                    <div class="card-content">
                        <div class="card-body">
                            {!! Form::open(['url' => route('seo_settings.store'), 'data-parsley-validate', 'files' => true, 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction"]) !!}
                            <div class=" row">

                                {{-- Pages --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('page', __('Page'), ['class' => 'form-label text-center']) }}
                                    <select name="page" id="" class="from-select form-control">
                                        @foreach ($pages as $page)
                                            @if (in_array($page, $seo_pages->toArray()))
                                                <option value="{{ $page }}" disabled>{{ $page }}</option>
                                            @else
                                                <option value="{{ $page }}">{{ $page }}</option>
                                            @endif
                                        @endforeach
                                    </select>

                                </div>

                                {{-- Title --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('title', __('Title'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Title should not exceed 580 pixels in width (~60 characters). Titles longer than this may get cut off in search results') }}"></i>
                                    <input type="text" name="meta_title" class="form-control" id="meta_title" placeholder="{{ __('Title') }}" required>
                                </div>

                                {{-- Description --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('description', __('Description'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Description should be around 395 pixels in length') }}"></i>
                                    <textarea id="meta_description" name="meta_description" class="form-control" required placeholder="{{ __('Description') }}"></textarea>
                                </div>

                                {{-- Schema Markup --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('schema_markup', __('Schema Markup'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Schema markup helps search engines understand your content') }}"></i>
                                    <textarea name="schema_markup" id="schema_markup" class="form-control" placeholder="{{ __('Schema Markup') }}" required></textarea>
                                    <span class="text-muted text-sm">{{ __("Generate markup using this ") }} <a href="https://www.rankranger.com/schema-markup-generator" target="_blank">{{ __("tool") }}</a></span>
                                </div>

                                {{-- Keywords --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('keywords', __('Keywords'), ['class' => 'form-label text-center']) }}
                                    <textarea name="keywords" id="" class="form-control" required placeholder="{{ __('Keywords') }}"></textarea>
                                </div>

                                {{-- Image --}}
                                <div class="col-md-12 col-sm-12 form-group mandatory">
                                    {{ Form::label('image', __('Image'), ['class' => 'form-label text-center']) }}
                                    {{ Form::file('image', ['class' => 'filepond form-control', 'required' => 'true', 'accept' => 'image/*']) }}
                                </div>

                                {{-- Multi-language SEO / schema markup — not handled by the
                                     website yet this version, hidden until it is. --}}
                                @if (false && isset($languages) && $languages->count() > 0)
                                    <div class="col-12">
                                        <div class="divider">
                                            <div class="divider-text">
                                                <h5>{{ __('Translations') }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <ul class="nav nav-tabs" id="seoTranslationTabs" role="tablist">
                                            @foreach ($languages as $key => $language)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                        {{ $language->name }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content p-3" id="seoTranslationTabsContent">
                                            @foreach ($languages as $key => $language)
                                                <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                    <div class="form-group">
                                                        <label for="translation-title-{{ $language->id }}">{{ __('Title') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][title][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $language->id }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Title') }}">
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <label for="translation-description-{{ $language->id }}">{{ __('Description') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][description][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][description][value]" id="translation-description-{{ $language->id }}" class="form-control" rows="3" placeholder="{{ __('Enter Description') }}"></textarea>
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <label for="translation-keywords-{{ $language->id }}">{{ __('Keywords') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][keywords][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][keywords][value]" id="translation-keywords-{{ $language->id }}" class="form-control" rows="2" placeholder="{{ __('Enter Keywords') }}"></textarea>
                                                    </div>
                                                    <div class="form-group mt-2">
                                                        <label for="translation-schema-markup-{{ $language->id }}">{{ __('Schema Markup') }}</label>
                                                        <input type="hidden" name="translations[{{ $language->id }}][schema_markup][language_id]" value="{{ $language->id }}">
                                                        <textarea name="translations[{{ $language->id }}][schema_markup][value]" id="translation-schema-markup-{{ $language->id }}" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Save --}}
                                <div class="col-sm-2 justify-content-end" style="margin-top:2%;">
                                    {{ Form::submit(trans('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                                </div>
                            </div>

                            {!! Form::close() !!}

                        </div>
                    </div>

                </div>

            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ route('seo_settings.show', 1) }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-toolbar="#toolbar" data-fixed-number="1" data-fixed-right-number="1"
                            data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams" data-show-refresh="true">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}</th>
                                    <th scope="col" data-field="image" data-sortable="false"  data-formatter="imageFormatter" data-align="center"> {{ __('Image') }} </th>
                                    <th scope="col" data-field="page" data-sortable="true" data-align="center"> {{ __('Page') }} </th>
                                    <th scope="col" data-field="title" data-sortable="true" data-align="center"> {{ __('Title') }}</th>
                                    <th scope="col" data-field="description" data-sortable="false" data-align="center"> {{ __('Description') }} </th>
                                    <th scope="col" data-field="schema_markup" data-sortable="false" data-align="center"> {{ __('Schema Markup') }} </th>
                                    <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-events="actionEvents"> {{ __('Action') }}</th>
                                </tr>
                            </thead>
                        </table>

                    </div>
                </div>
            </div>
        </div>
        <!-- EDIT MODEL MODEL -->
        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="myModalLabel1">{{ __('Edit SEO Settings') }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    {!! Form::open(['url' => url('seo_settings'), 'data-parsley-validate', 'files' => true, 'class' => 'edit-form']) !!}
                    @method('PUT')
                    <div class="modal-body">

                        <div class=" row">

                            {{-- Edit Page --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('page', __('Page'), ['class' => 'form-label text-center']) }}
                                {{ Form::text('edit_page', '', [
                                    'class' => 'form-control',
                                    'placeholder' => __('Page'),
                                    'id' => 'edit_page_show',
                                    'disabled' => true,
                                    'data-parsley-required' => 'true',
                                ]) }}
                                {!! Form::hidden('edit_page', '', ['id' => 'edit_page']) !!}
                            </div>

                            {{-- Edit Title --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('title', __('Title'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Title should not exceed 580 pixels in width (~60 characters). Titles longer than this may get cut off in search results') }}"></i>
                                <input type="text" name="edit_meta_title" class="form-control" id="edit_meta_title" placeholder="{{ __('Title') }}" required>
                            </div>

                            {{-- Edit Keywords --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('keywords', __('Keywords'), ['class' => 'form-label text-center']) }}
                                <textarea name="edit_keywords" id="edit_keywords" class="form-control" placeholder="{{ __('Keywords') }}"></textarea>
                            </div>

                            {{-- Edit Description --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('description', __('Description'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ trans('Description should be around 395 pixels in length') }}"></i>
                                <textarea id="edit_meta_description" name="edit_meta_description" class="form-control" placeholder="{{ __('Description') }}"></textarea>
                            </div>
                            
                            {{-- Schema Markup --}}
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('schema_markup', __('Schema Markup'), ['class' => 'form-label text-center']) }} <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Schema markup helps search engines understand your content') }}"></i>
                                <textarea name="edit_schema_markup" id="edit_schema_markup" class="form-control" placeholder="{{ __('Schema Markup') }}" required></textarea>
                                <span class="text-muted text-sm">{{ __("Generate markup using this ") }} <a href="https://www.rankranger.com/schema-markup-generator" target="_blank">{{ __("tool") }}</a></span>
                            </div>

                            {{-- Edit Image --}}
                            <div class="col-md-6 col-sm-12 form-group mandatory">
                                {{ Form::label('image', __('Image'), ['class' => 'form-label text-center']) }}
                                {{ Form::file('edit_image', ['class' => 'form-control filepond', 'data-parsley-required' => 'true', 'accept' => 'image/*']) }}
                            </div>
                            <div class="col-md-6 col-sm-12 mt-4">
                                <img src="" alt="OG_Image" class="mt-2" id="meta_img" height="100px" width="100px">
                            </div>

                            {{-- Multi-language SEO / schema markup — not handled by the
                                 website yet this version, hidden until it is. --}}
                            @if (false && isset($languages) && $languages->count() > 0)
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <ul class="nav nav-tabs" id="editSeoTranslationTabs" role="tablist">
                                        @foreach ($languages as $key => $language)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="edit-translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#edit-translation-tab-{{ $language->id }}" type="button" role="tab">
                                                    {{ $language->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content p-3" id="editSeoTranslationTabsContent">
                                        @foreach ($languages as $key => $language)
                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="edit-translation-tab-{{ $language->id }}" role="tabpanel">
                                                <div class="form-group">
                                                    <input type="hidden" name="translations[{{ $language->id }}][title][id]" class="edit-translation-id" data-lang="{{ $language->id }}" data-key="title">
                                                    <label for="edit-translation-title-{{ $language->id }}">{{ __('Title') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][title][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $language->id }}][title][value]" id="edit-translation-title-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Title') }}">
                                                </div>
                                                <div class="form-group mt-2">
                                                    <input type="hidden" name="translations[{{ $language->id }}][description][id]" class="edit-translation-id" data-lang="{{ $language->id }}" data-key="description">
                                                    <label for="edit-translation-description-{{ $language->id }}">{{ __('Description') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][description][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][description][value]" id="edit-translation-description-{{ $language->id }}" class="form-control" rows="3" placeholder="{{ __('Enter Description') }}"></textarea>
                                                </div>
                                                <div class="form-group mt-2">
                                                    <input type="hidden" name="translations[{{ $language->id }}][keywords][id]" class="edit-translation-id" data-lang="{{ $language->id }}" data-key="keywords">
                                                    <label for="edit-translation-keywords-{{ $language->id }}">{{ __('Keywords') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][keywords][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][keywords][value]" id="edit-translation-keywords-{{ $language->id }}" class="form-control" rows="2" placeholder="{{ __('Enter Keywords') }}"></textarea>
                                                </div>
                                                <div class="form-group mt-2">
                                                    <input type="hidden" name="translations[{{ $language->id }}][schema_markup][id]" class="edit-translation-id" data-lang="{{ $language->id }}" data-key="schema_markup">
                                                    <label for="edit-translation-schema-markup-{{ $language->id }}">{{ __('Schema Markup') }}</label>
                                                    <input type="hidden" name="translations[{{ $language->id }}][schema_markup][language_id]" value="{{ $language->id }}">
                                                    <textarea name="translations[{{ $language->id }}][schema_markup][value]" id="edit-translation-schema-markup-{{ $language->id }}" class="form-control" rows="4" placeholder="{{ __('Paste JSON-LD schema markup') }}"></textarea>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Edit ID --}}
                        <input type="hidden" name="edit_id" id="edit_id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                    </div>
                    {!! Form::close() !!}
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
        <!-- EDIT MODEL -->
    </section>
@endsection
@section('script')
    <script>
        $(document).ready(function() {
            getWordCount("meta_title", "meta_title_count", "19.9px arial");
            getWordCount("meta_description", "meta_description_count", "12.9px arial");
            getWordCount("edit_meta_title", "edit_meta_title_count", "19.9px arial");
            getWordCount(
                "edit_meta_description",
                "edit_meta_description_count",
                "12.9px arial"
            );
        });


        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $('#edit_id').val(row.id);
                $('#edit_page_show').val(row.page);
                $('#edit_page').val(row.page);
                $('#edit_meta_title').val(row.title);
                $('#edit_meta_description').val(row.description);
                $('#edit_schema_markup').val(row.schema_markup);
                $('#edit_keywords').val(row.keywords);
                $('#meta_img').attr('src', row.image);

                getWordCount('edit_meta_description', 'edit_meta_description_count', '12.9px arial');
                getWordCount('edit_meta_title', 'edit_meta_title_count', '19.9px arial');

                // Reset translation fields, then fill in whatever this row has
                $('#editSeoTranslationTabsContent textarea, #editSeoTranslationTabsContent input[type="text"]').val('');
                $('.edit-translation-id').val('');
                (row.translations || []).forEach(function(translation) {
                    $('#edit-translation-' + translation.key.replace(/_/g, '-') + '-' + translation.language_id).val(translation.value);
                    $('.edit-translation-id[data-lang="' + translation.language_id + '"][data-key="' + translation.key + '"]').val(translation.id);
                });
            }
        }

        function queryParams(p) {

            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,

            };
        }

        function formSuccessFunction(response) {
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        }
    </script>
@endsection
