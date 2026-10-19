@extends('layouts.main')

@section('title')
    {{ __('Near By Places') }}
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
        @if (has_permissions('create', 'near_by_places'))
        <div class="card">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Add Place') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            {!! Form::open(['files' => true, 'data-parsley-validate', 'url' => route('outdoor_facilities.store')]) !!}
                            <div class="row">

                                {{-- Place Name --}}
                                <div class="col-sm-12 col-md-4 form-group mandatory">
                                    {{ Form::label('type', __('Place Name'), ['class' => 'form-label text-center']) }}
                                    {{ Form::text('facility', '', ['class' => 'form-control', 'placeholder' => trans('Place Name'), 'data-parsley-required' => 'true']) }}
                                </div>

                                {{-- Image --}}
                                <div class="col-md-4 form-group mandatory">
                                    {{ Form::label('image', __('Image'), ['class' => ' form-label text-center']) }}
                                    <input type="file" class="filepond" id="image" name="image" accept="image/svg+xml" required>
                                </div>

                                @if(isset($languages) && $languages->count() > 0)
                                    {{-- Translations Div --}}
                                    <div class="translation-div mt-2">
                                        <div class="col-12">
                                            <div class="divider">
                                                <div class="divider-text">
                                                    <h5>{{ __('Translations for Place') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <ul class="nav nav-tabs" id="facilityTranslationTabs" role="tablist">
                                                @foreach($languages as $key => $language)
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                            {{ $language->name }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <div class="tab-content p-3" id="facilityTranslationTabsContent">
                                                @foreach($languages as $key =>$language)
                                                    <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                        <div class="form-group">
                                                            <label for="translation-{{ $language->id }}">{{ __('Place') }}</label>
                                                            <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                            <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Place') }}">
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Save --}}
                                <div class="col-sm-12 col-md-12 text-end" style="margin-top:2%;">
                                    {{ Form::submit(trans('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                                </div>


                            </div>

                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">

                        <table class="table table-striped" id="table_list"
                            data-toggle="table" data-url="{{ url('facility-list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>

                                    <th scope="col" data-field="image" data-formatter="imageFormatter"
                                        data-sortable="false">{{ __('Image') }}</th>

                                    @if (has_permissions('update', 'near_by_places'))
                                        <th scope="col" data-field="operate" data-sortable="false"
                                            data-events="actionEvents">{{ __('Action') }}
                                        </th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Near By Place') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ url('facility-update') }}" class="form-horizontal" enctype="multipart/form-data"
                        method="POST" data-parsley-validate>
                        {{ csrf_field() }}

                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="row">
                            <div class="col-md-12 col-12 ">
                                <div class="form-group mandatory">
                                    <label for="edit_name" class="form-label col-12">{{ __('Name') }}</label>
                                    <input type="text" id="edit_name" class="form-control col-12" placeholder=""
                                        name="edit_name" data-parsley-required="true">
                                </div>
                            </div>
                        </div>
                        <div class="row">

                            {{ Form::label('image', __('Image'), ['class' => 'col-sm-12 col-form-label']) }}
                            <div class="col-md-12 col-12">

                                <input type="button" class="input-btn1-ghost-dashed bottomleft h-100" value="+">
                                <input accept='.svg' name='image' type='file' id="edit_image" style="display: none"/>

                                <img id="blah" height="100" width="110" style="margin-left: 2%" />

                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div mt-2">
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations for Place') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <ul class="nav nav-tabs" id="facilityEditTranslationTabs" role="tablist">
                                        @foreach($languages as $key => $language)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="edit-translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#edit-translation-tab-{{ $language->id }}" type="button" role="tab">
                                                    {{ $language->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content p-3" id="facilityEditTranslationTabsContent">
                                        @foreach($languages as $key =>$language)
                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="edit-translation-tab-{{ $language->id }}" role="tabpanel">
                                                <div class="form-group">
                                                    <label for="translation-{{ $language->id }}">{{ __('Place') }}</label>
                                                    <input type="hidden" name="translations[{{ $key }}][id]" value="" id="edit-translation-id-{{ $language->id }}" class="edit-translations">
                                                    <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $key }}][value]" id="edit-translation-{{ $language->id }}" class="form-control edit-translations" value="" placeholder="{{ __('Enter Place') }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect"
                        data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light"
                        id="btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
    </div>
    <!-- EDIT MODEL -->
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }
        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit_id").val(row.id);
                $("#edit_name").val(row.name);
                $('#blah').attr('src', row.image);
                $(".edit-translations").val("");
                if(row.translations){
                    $.each(row.translations, function(key, value) {
                        $("#edit-translation-id-" + value.language_id).val(value.id);
                        $("#edit-translation-" + value.language_id).val(value.value);
                    });
                }

            }
        }




        $('.bottomleft').click(function() {
            $('#edit_image').click();

        });
        edit_image.onchange = evt => {
            const [file] = edit_image.files
            if (file) {
                blah.src = URL.createObjectURL(file)

            }
        }
    </script>
@endsection

