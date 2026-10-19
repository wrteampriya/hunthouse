@extends('layouts.main')

@section('title')
    {{ __('Countries') }}
@endsection

@section('css')
    <style>
        /* Select2's default width: 'resolve' reads the hidden <select>'s computed
           width at init time — inside this modal (display:none until shown), that
           resolves to ~0 and the widget renders truncated. Force full width here
           only, without touching the shared select2() init used site-wide. */
        #editCountryModal .select2-container {
            width: 100% !important;
        }
    </style>
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <section class="section">
            <div class="row">
                @if (has_permissions('create', 'country'))
                    <div class="col-md-12">
                        <div class="card">
                            <form action="{{ route('country.store') }}" class="needs-validation" method="post" data-parsley-validate>
                                {{ csrf_field() }}
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-12 col-sm-6 col-xl">
                                            <label class="form-label">{{ __('Country') }}</label> <span class="text-danger">*</span>
                                            <select id="iso_country_select" class="form-control select2" required>
                                                <option value="">{{ __('Select Country') }}</option>
                                                @foreach ($isoCountries as $isoCountry)
                                                    <option value="{{ $isoCountry['code'] }}" data-name="{{ $isoCountry['name'] }}">{{ $isoCountry['name'] }} ({{ $isoCountry['code'] }})</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="code" id="code" required>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl">
                                            <label class="form-label">{{ __('Display Name') }}</label> <span class="text-danger">*</span>
                                            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('e.g. India') }}" required>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl">
                                            <label class="form-label">{{ __('Default Language') }}</label>
                                            <select name="default_language_id" class="form-control select2">
                                                @foreach ($languages as $language)
                                                    @php $isGlobalDefault = $language->id == $globalDefaultLanguageId; @endphp
                                                    <option value="{{ $isGlobalDefault ? '' : $language->id }}">{{ $language->name }}{{ $isGlobalDefault ? ' ('.__('Global Default').')' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl">
                                            <label class="form-label">{{ __('Currency') }} <span class="text-danger">*</span></label>
                                            <select name="currency_id" class="form-control select2" required>
                                                <option value="">{{ __('Select Currency') }}</option>
                                                @foreach ($currencies as $currency)
                                                    <option value="{{ $currency->id }}">{{ $currency->name }} ({{ $currency->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-3">
                                        <button class="btn btn-primary" type="submit">{{ __('Submit') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <table class="table-light" aria-describedby="mydesc" class='table-striped'
                                        id="table_list" data-toggle="table" data-url="{{ url('country-list') }}"
                                        data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                                        data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                                        data-search="true" data-toolbar="#toolbar" data-show-columns="true"
                                        data-show-refresh="true" data-trim-on-search="false" data-sort-name="id"
                                        data-sort-order="desc" data-pagination-successively-size="3"
                                        data-query-params="queryParams">
                                        <thead>
                                            <tr>
                                                <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                                <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                                <th scope="col" data-field="code" data-sortable="true">{{ __('Code') }}</th>
                                                <th scope="col" data-field="default_language_name">{{ __('Default Language') }}</th>
                                                <th scope="col" data-field="currency_name">{{ __('Currency') }}</th>
                                                @if (has_permissions('update', 'country'))
                                                    <th scope="col" data-field="status" data-align="center" data-formatter="countryEnableDisableSwitchFormatter">{{ __('Status') }}</th>
                                                @else
                                                    <th scope="col" data-field="status" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                                @endif
                                                @if (has_permissions('update', 'country') || has_permissions('delete', 'country'))
                                                    <th scope="col" data-field="operate" data-events="actionEvents">{{ __('Action') }}</th>
                                                @endif
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="editCountryModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Edit Country') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="{{ url('country-update') }}" method="POST" data-parsley-validate>
                            {{ csrf_field() }}
                            <input type="hidden" id="edit_id" name="edit_id">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Country') }}</label>
                                            <select id="edit_iso_country_select" class="form-control select2" required>
                                                <option value="">{{ __('Select Country') }}</option>
                                                @foreach ($isoCountries as $isoCountry)
                                                    <option value="{{ $isoCountry['code'] }}" data-name="{{ $isoCountry['name'] }}">{{ $isoCountry['name'] }} ({{ $isoCountry['code'] }})</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="edit_code" id="edit_code" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Display Name') }}</label>
                                            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Default Language') }}</label>
                                            <select name="edit_default_language_id" id="edit_default_language_id" class="form-control select2">
                                                @foreach ($languages as $language)
                                                    @php $isGlobalDefault = $language->id == $globalDefaultLanguageId; @endphp
                                                    <option value="{{ $isGlobalDefault ? '' : $language->id }}">{{ $language->name }}{{ $isGlobalDefault ? ' ('.__('Global Default').')' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Currency') }} <span class="text-danger">*</span></label>
                                            <select name="edit_currency_id" id="edit_currency_id" class="form-control select2" required>
                                                <option value="">{{ __('Select Currency') }}</option>
                                                @foreach ($currencies as $currency)
                                                    <option value="{{ $currency->id }}">{{ $currency->name }} ({{ $currency->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                                <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('script')
    <script type="text/javascript">
        window.actionEvents = {
            'click .edit-country': function(e, value, row, index) {
                $("#edit_id").val(row.id);
                $("#edit_iso_country_select").val(row.code).trigger('change');
                // Restore the saved display name — it may differ from the
                // official ISO name (e.g. "UAE" instead of "United Arab Emirates").
                $("#edit_code").val(row.code);
                $("#edit_name").val(row.name);
                $("#edit_default_language_id").val(row.default_language_id || '').trigger('change');
                $("#edit_currency_id").val(row.currency_id || '').trigger('change');
            }
        };

        // Auto-fill code (locked) + name (starting point, still editable) from
        // the selected ISO country, on both the create form and edit modal.
        $('#iso_country_select').on('change', function() {
            var selected = $(this).find('option:selected');
            $('#code').val($(this).val());
            $('#name').val(selected.data('name') || '');
        });
        $('#edit_iso_country_select').on('change', function() {
            var selected = $(this).find('option:selected');
            $('#edit_code').val($(this).val());
            $('#edit_name').val(selected.data('name') || '');
        });

        function statusFormatter(value) {
            return value == 1 ?
                '<span class="badge bg-success">{{ __('Active') }}</span>' :
                '<span class="badge bg-danger">{{ __('Inactive') }}</span>';
        }

        // Add/edit no longer carry a status control (new countries always start
        // inactive, edit never touches status) — this switch in the list is now
        // the only way to activate/deactivate a country.
        function countryEnableDisableSwitchFormatter(value, row) {
            var status = (value == 1) ? 'checked' : '';

            return '<div class="form-check form-switch" style="display: flex; justify-content: center;">' +
                '<input class="form-check-input" id="' + row.id + '" onclick="chk(this);" data-url="' + row.edit_status_url + '" type="checkbox" role="switch" ' + status + ' value="' + value + '">' +
                '</div>';
        }

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }
    </script>
@endsection
