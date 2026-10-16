@extends('layouts.main')

@section('title')
    {{ (isset($form_type) && $form_type == 'become_agent') ? __('User Verification Form Sections') : __('Agent Verification Form Sections') }}
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
    @if (has_permissions('create', 'verify_customer_form'))
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="divider">
                        <div class="divider-text">
                            <h4>{{ __('Create Form Section') }}</h4>
                        </div>
                    </div>
                </div>

                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                {!! Form::open(['url' => route('agent-verification-form-sections.store'), 'data-parsley-validate', 'files' => true, 'class' => 'create-form','data-pre-submit-function','data-success-function'=> "formSuccessFunction"]) !!}
                                    @csrf

                                    <div class="row">
                                        {{-- Name --}}
                                        <div class="col-sm-12 col-md-4 form-group mandatory">
                                            {{ Form::label('type', __('Section Name'), ['class' => 'form-label text-center']) }}
                                            {{ Form::text('name', '', ['class' => 'form-control', 'placeholder' => trans('Name'), 'data-parsley-required' => 'true']) }}
                                        </div>

                                        {{-- Form Type --}}
                                        <div class="col-sm-12 col-md-4 form-group mandatory">
                                            {{ Form::label('form_type', __('Form Type'), ['class' => 'form-label text-center']) }}
                                            <select name="form_type" id="form_type" class="form-select form-control-sm" data-parsley-required=true>
                                                <option value="">{{ __('Select Form') }}</option>
                                                <option value="become_agent">{{ __('Become an Agent') }}</option>
                                                <option value="verify_agent">{{ __('Verify Agent') }}</option>
                                            </select>
                                        </div>

                                        {{-- Sequence Field Removed For Drag And Drop --}}

                                        @if(isset($languages) && $languages->count() > 0)
                                            {{-- Translations Div --}}
                                            <div class="form-field-translation-div mt-2">
                                                <div class="col-12">
                                                    <div class="divider">
                                                        <div class="divider-text">
                                                            <h5>{{ __('Translations for Section Name') }}</h5>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <ul class="nav nav-tabs" id="sectionTranslationTabs" role="tablist">
                                                        @foreach($languages as $key => $language)
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                                    {{ $language->name }}
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    <div class="tab-content p-3" id="sectionTranslationTabsContent">
                                                        @foreach($languages as $key =>$language)
                                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                                <div class="form-group">
                                                                    <label for="translation-{{ $language->id }}">{{ __('Section Name') }}</label>
                                                                    <input type="hidden" name="section_translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                                    <input type="text" name="section_translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Section Name') }}">
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Save --}}
                                        <div class="col-12  d-flex justify-content-end pt-3">
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

    @if (has_permissions('read', 'verify_customer_form'))
        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div id="toolbar" class="d-flex align-items-center mb-3">
                                <span class="text-danger small me-3">{{ __('NOTE: Filter by Form Type to enable drag-and-drop order updating') }}</span>
                                <select id="filter_form_type" class="form-select w-auto me-3">
                                    <option value="">{{ __('All Form Types') }}</option>
                                    <option value="become_agent">{{ __('Become an Agent') }}</option>
                                    <option value="verify_agent">{{ __('Verify Agent') }}</option>
                                </select>
                                <button id="update-order-button" class="btn btn-secondary" disabled> {{ __('Update Order') }} </button>
                            </div>
                            <table class="table table-striped"
                                id="table_list" data-toggle="table" data-url="{{ route('agent-verification-form-sections.show') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                data-responsive="true" data-sort-name="sequence" data-sort-order="asc"
                                data-pagination-successively-size="3" data-query-params="queryParams"
                                data-use-row-attr-func="true"
                                data-reorderable-rows="false" data-drag-handle=".reorder-rows-handle"
                                data-reorder-rows-on-drag-class="reorder-rows-on-drag-class">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="drag_handle" data-sortable="false" data-visible="false" data-switchable="false" data-align="center" data-width="1%" data-formatter="dragHandleFormatter"><i class="fa fa-arrows-alt"></i></th>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                        <th scope="col" data-field="form_type" data-sortable="true" data-formatter="formTypeFormatter">{{ __('Form Type') }}</th>
                                        <!-- <th scope="col" data-field="sequence" data-sortable="true">{{ __('Sequence') }}</th> -->
                                        <th scope="col" data-field="status" data-sortable="false" data-align="center" data-width="5%" data-formatter="enableDisableSwitchFormatter"> {{ __('Enable/Disable') }}</th>
                                        @if (has_permissions('update', 'verify_customer_form') || has_permissions('delete', 'verify_customer_form'))
                                            <th scope="col" data-field="operate" data-sortable="false" data-events="actionEvents">{{ __('Action') }} </th>
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


    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="verifyCustomerFormEditModal"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="verifyCustomerFormEditModal">{{ __('Edit Form Section') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form-horizontal create-form" action="{{ route('agent-verification-form-sections.update') }}" enctype="multipart/form-data" data-parsley-validate data-success-function="editFormSuccessFunction">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="edit-id" name="id">
                        
                        <div class="row">
                            {{-- Name --}}
                            <div class="col-md-4 form-group mandatory">
                                {{ Form::label('type', __('Section Name'), ['class' => 'form-label text-center']) }}
                                {{ Form::text('name', '', ['class' => 'form-control', 'id' => 'edit-name','placeholder' => trans('Name'), 'data-parsley-required' => 'true']) }}
                            </div>

                            {{-- Form Type --}}
                            <div class="col-md-4 form-group mandatory">
                                {{ Form::label('form_type', __('Form Type'), ['class' => 'form-label text-center']) }}
                                <select name="form_type" id="edit-form_type" class="form-select form-control-sm" data-parsley-required=true>
                                    <option value="">{{ __('Select Form') }}</option>
                                    <option value="become_agent">{{ __('Become an Agent') }}</option>
                                    <option value="verify_agent">{{ __('Verify Agent') }}</option>
                                </select>
                            </div>

                            {{-- Sequence Field Removed For Drag And Drop --}}
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div mt-2">
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations for Section Name') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <ul class="nav nav-tabs" id="sectionEditTranslationTabs" role="tablist">
                                        @foreach($languages as $key => $language)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="edit-translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#edit-translation-tab-{{ $language->id }}" type="button" role="tab">
                                                    {{ $language->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content p-3" id="sectionEditTranslationTabsContent">
                                        @foreach($languages as $key =>$language)
                                            <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="edit-translation-tab-{{ $language->id }}" role="tabpanel">
                                                <div class="form-group">
                                                    <label for="translation-{{ $language->id }}">{{ __('Section Name') }}</label>
                                                    <input type="hidden" name="section_translations[{{ $key }}][id]" value="" class="edit-translations">
                                                    <input type="hidden" name="section_translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="section_translations[{{ $key }}][value]" id="edit-translation-{{ $language->id }}" class="form-control edit-translations" value="" placeholder="{{ __('Enter Section Name') }}">
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
    <!-- EDIT MODEL -->

@endsection

@section('script')
    <style>
        .drag-grip:hover {
            color: #adb5bd !important;
        }

        .reorder-button:hover {
            background-color: #ebf7f7 !important;
            color: #0dcaf0 !important;
        }
    </style>
    <script>
        $(document).ready(function() {
            $('#filter_form_type').on('change', function() {
                const filterChosen = $(this).val() !== '';
                if (filterChosen) {
                    $('#table_list').bootstrapTable('showColumn', 'drag_handle');
                    $('#update-order-button').prop('disabled', false).removeClass('btn-secondary').addClass('btn-warning');
                } else {
                    $('#table_list').bootstrapTable('hideColumn', 'drag_handle');
                    $('#update-order-button').prop('disabled', true).removeClass('btn-warning').addClass('btn-secondary');
                }
                // Rows are only actually draggable once a Form Type filter is chosen — the
                // drag_handle column alone isn't enough to stop whole-row dragging. Also (re)wire
                // onAllowDrop here rather than on initial page load: data-on-allow-drop doesn't
                // resolve to a real function reference for this extension (stays a literal
                // string), and calling refreshOptions before the table's own data-toggle="table"
                // auto-init has finished can throw, silently skipping every statement after it
                // in this same ready() callback — including the Update Order button's click binding.
                $('#table_list').bootstrapTable('refreshOptions', {reorderableRows: filterChosen, onAllowDrop: onAllowDropCheck});
            });

            // The Default row has no grab handle of its own, but dragging OTHER rows past
            // it can still shift its position in the array. Snap it back to the top the
            // instant any reorder happens, so its position is effectively frozen.
            $('#table_list').on('reorder-row.bs.table', function () {
                const data = $('#table_list').bootstrapTable('getData');
                const defaultIndex = data.findIndex(row => row.is_default);
                if (defaultIndex > 0) {
                    const [defaultRow] = data.splice(defaultIndex, 1);
                    data.unshift(defaultRow);
                    $('#table_list').bootstrapTable('load', data);
                }
            });

            $('#update-order-button').click(function () {
                const updatedRows = $('#table_list').bootstrapTable('getData').map((row, index) => {
                    return {
                        id: row.id,
                        sort_order: index + 1
                    };
                });

                $.ajax({
                    url: "{{ route('agent-verification-form-sections.update-sequence') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        sections: updatedRows
                    },
                    success: function(response) {
                        if (response.error) {
                            showErrorToast(response.message);
                        } else {
                            showSuccessToast(response.message);
                            $('#table_list').bootstrapTable('refresh');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast("Error updating order");
                    }
                });
            });
        });

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                filter_form_type: $('#filter_form_type').val() || "{{ $form_type ?? '' }}"
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
                $("#edit-form_type").val(row.form_type);

                // Populate section translations
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

        function dragHandleFormatter(value, row, index) {
            if (row.is_default) {
                // The Default section's position is fixed — no drag handle, nothing to grab.
                return '<i class="bi bi-lock text-muted" title="{{ __("Position is fixed") }}"></i>';
            }
            return '<span class="reorder-rows-handle drag-grip" title="{{ __("Drag to reorder") }}"><i class="bi bi-grip-vertical"></i></span>';
        }

        // The library's real "handle" option (dragHandle) still lets any cell in a row start
        // a drag — this is the actual gate that blocks the Default row from being dragged, or
        // from being used as a drop target, no matter where in the row the mouse grabs.
        function onAllowDropCheck(rowFrom, rowTo) {
            return !rowFrom.is_default && !rowTo.is_default;
        }
    </script>
@endsection
