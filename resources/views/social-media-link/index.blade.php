@extends('layouts.main')

@section('title')
    {{ __('Social Media Links') }}
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
    @if (has_permissions('create', 'social_media_link'))
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="divider">
                        <div class="divider-text">
                            <h4>{{ __('Create Social Media Link') }}</h4>
                        </div>
                    </div>
                </div>

                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                {!! Form::open(['url' => route('social-media-links.store'), 'files' => true, 'data-parsley-validate', 'class' => 'create-form', 'data-success-function' => 'formSuccessFunction']) !!}
                                    @csrf

                                    <div class="row">
                                        {{-- Name --}}
                                        <div class="col-sm-12 col-md-6 form-group mandatory">
                                            {{ Form::label('name', __('Platform Name'), ['class' => 'form-label text-center']) }}
                                            {{ Form::text('name', '', ['class' => 'form-control', 'placeholder' => trans('e.g. Pinterest'), 'data-parsley-required' => 'true']) }}
                                        </div>

                                        {{-- Icon --}}
                                        <div class="col-sm-12 col-md-6 form-group mandatory">
                                            {{ Form::label('icon', __('Icon'), ['class' => 'form-label text-center']) }}
                                            <input type="file" class="form-control" id="icon" name="icon" accept="image/*" data-parsley-required="true">
                                        </div>

                                        @if(isset($languages) && $languages->count() > 0)
                                            {{-- Translations Div --}}
                                            <div class="form-field-translation-div mt-2">
                                                <div class="col-12">
                                                    <div class="divider">
                                                        <div class="divider-text">
                                                            <h5>{{ __('Translations for Platform Name') }}</h5>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <ul class="nav nav-tabs" id="linkTranslationTabs" role="tablist">
                                                        @foreach($languages as $key => $language)
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                                    {{ $language->name }}
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    <div class="tab-content p-3" id="linkTranslationTabsContent">
                                                        @foreach($languages as $key =>$language)
                                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                                <div class="form-group">
                                                                    <label for="translation-{{ $language->id }}">{{ __('Platform Name') }}</label>
                                                                    <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                                    <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Platform Name') }}">
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Save --}}
                                        <div class="col-12 d-flex justify-content-end pt-3">
                                            {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1', 'id' => 'btn_submit']) }}
                                        </div>
                                    </div>
                                {!! Form::close() !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (has_permissions('read', 'social_media_link'))
        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <table class="table table-striped"
                                id="table_list" data-toggle="table" data-url="{{ route('social-media-links.show') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                data-responsive="true" data-sort-name="sequence" data-sort-order="asc"
                                data-pagination-successively-size="3" data-query-params="queryParams">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                        <th scope="col" data-field="icon" data-formatter="imageFormatter" data-sortable="false">{{ __('Icon') }}</th>
                                        <th scope="col" data-field="status" data-sortable="false" data-align="center" data-width="5%" data-formatter="enableDisableSwitchFormatter">{{ __('Enable/Disable') }}</th>
                                        @if (has_permissions('update', 'social_media_link') || has_permissions('delete', 'social_media_link'))
                                            <th scope="col" data-field="operate" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editSocialMediaLinkModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="editSocialMediaLinkModal">{{ __('Edit Social Media Link') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form-horizontal create-form" action="{{ route('social-media-links.update') }}" enctype="multipart/form-data" data-parsley-validate data-success-function="editFormSuccessFunction">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="edit-id" name="id">

                        <div class="row">
                            {{-- Name --}}
                            <div class="col-sm-12 form-group mandatory">
                                {{ Form::label('name', __('Platform Name'), ['class' => 'form-label text-center']) }}
                                {{ Form::text('name', '', ['class' => 'form-control', 'id' => 'edit-name', 'data-parsley-required' => 'true']) }}
                            </div>

                            {{-- Icon --}}
                            <div class="col-sm-12 form-group">
                                {{ Form::label('icon', __('Icon (leave blank to keep current)'), ['class' => 'form-label text-center']) }}
                                <input type="file" class="form-control" id="edit-icon" name="icon" accept="image/*">
                                <img id="edit-icon-preview" height="40" class="mt-2" />
                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div mt-2">
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations for Platform Name') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <ul class="nav nav-tabs" id="linkEditTranslationTabs" role="tablist">
                                        @foreach($languages as $key => $language)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="edit-translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#edit-translation-tab-{{ $language->id }}" type="button" role="tab">
                                                    {{ $language->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content p-3" id="linkEditTranslationTabsContent">
                                        @foreach($languages as $key =>$language)
                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="edit-translation-tab-{{ $language->id }}" role="tabpanel">
                                                <div class="form-group">
                                                    <label for="translation-{{ $language->id }}">{{ __('Platform Name') }}</label>
                                                    <input type="hidden" name="translations[{{ $key }}][id]" value="" id="edit-translation-id-{{ $language->id }}" class="edit-translations">
                                                    <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $key }}][value]" id="edit-translation-{{ $language->id }}" class="form-control edit-translations" value="" placeholder="{{ __('Enter Platform Name') }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary waves-effect waves-light" id="btn_submit">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- EDIT MODAL -->
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
            };
        }

        function formSuccessFunction () {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit-id").val(row.id);
                $("#edit-name").val(row.name);
                $("#edit-icon-preview").attr('src', row.icon);

                $(".edit-translations").val("");
                if(row.translations){
                    $.each(row.translations, function(key, value) {
                        $("#edit-translation-id-" + value.language_id).val(value.id);
                        $("#edit-translation-" + value.language_id).val(value.value);
                    });
                }
            }
        }

        function editFormSuccessFunction(){
            setTimeout(() => {
                $('#editModal').modal('hide');
                $('#table_list').bootstrapTable('refresh');
            }, 1000);
        }
    </script>
@endsection
