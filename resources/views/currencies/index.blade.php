@extends('layouts.main')

@section('title')
    {{ __('Currencies') }}
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
                @if (has_permissions('create', 'currency'))
                    <div class="col-md-12">
                        <div class="card">
                            <form action="{{ route('currency.store') }}" class="needs-validation" method="post" data-parsley-validate>
                                {{ csrf_field() }}
                                <div class="card-body">
                                    <div class="form-group row">
                                        <div class="col-md-3">
                                            <label class="form-label">{{ __('Currency') }}</label> <span class="text-danger">*</span>
                                            <select id="currency_code_select" name="code" class="form-select form-control-sm" required>
                                                <option value="">{{ __('Select Currency') }}</option>
                                                @foreach ($listOfCurrencies as $data)
                                                    <option value="{{ $data['currency_code'] }}" data-name="{{ $data['currency_name'] }}">
                                                        {{ $data['currency_name'] }} ({{ $data['currency_code'] }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">{{ __('Name') }}</label> <span class="text-danger">*</span>
                                            <input type="text" name="name" id="currency_name_input" class="form-control" placeholder="{{ __('e.g. Indian Rupee') }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Symbol') }}</label> <span class="text-danger">*</span>
                                            <input type="text" name="symbol" id="currency_symbol_input" maxlength="16" class="form-control" placeholder="₹" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-flex justify-content-end mt-2">
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
                                        id="table_list" data-toggle="table" data-url="{{ url('currency-list') }}"
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
                                                <th scope="col" data-field="symbol">{{ __('Symbol') }}</th>
                                                @if (has_permissions('update', 'currency'))
                                                    <th scope="col" data-field="status" data-align="center" data-formatter="currencyEnableDisableSwitchFormatter">{{ __('Status') }}</th>
                                                @else
                                                    <th scope="col" data-field="status" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                                @endif
                                                @if (has_permissions('update', 'currency') || has_permissions('delete', 'currency'))
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

            <div id="editCurrencyModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Edit Currency') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="{{ url('currency-update') }}" method="POST" data-parsley-validate>
                            {{ csrf_field() }}
                            <input type="hidden" id="edit_id" name="edit_id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Currency') }}</label>
                                            <select id="edit_currency_code_select" name="edit_code" class="form-select form-control-sm" required>
                                                <option value="">{{ __('Select Currency') }}</option>
                                                @foreach ($listOfCurrencies as $data)
                                                    <option value="{{ $data['currency_code'] }}" data-name="{{ $data['currency_name'] }}">
                                                        {{ $data['currency_name'] }} ({{ $data['currency_code'] }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Name') }}</label>
                                            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('Symbol') }}</label>
                                            <input type="text" name="edit_symbol" id="edit_symbol" maxlength="16" class="form-control" required>
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
            'click .edit-currency': function(e, value, row, index) {
                $("#edit_id").val(row.id);
                $("#edit_name").val(row.name);
                $("#edit_currency_code_select").val(row.code);
                $("#edit_symbol").val(row.symbol);
            }
        };

        // Fill Name/Symbol automatically from the picked currency, mirroring
        // the same behaviour on the system settings form.
        function wireCurrencyCodeSelect(codeSelector, nameSelector, symbolSelector) {
            $(codeSelector).on('change', function() {
                const code = $(this).val();
                if (!code) {
                    return;
                }
                $(nameSelector).val($(this).find(':selected').data('name') || '');
                $.ajax({
                    url: '{{ route('get-currency-symbol') }}',
                    data: { country_code: code }
                }).done(function(response) {
                    if (response && response.error === false) {
                        $(symbolSelector).val(response.data);
                    }
                });
            });
        }
        wireCurrencyCodeSelect('#currency_code_select', '#currency_name_input', '#currency_symbol_input');
        wireCurrencyCodeSelect('#edit_currency_code_select', '#edit_name', '#edit_symbol');

        function statusFormatter(value) {
            return value == 1 ?
                '<span class="badge bg-success">{{ __('Active') }}</span>' :
                '<span class="badge bg-danger">{{ __('Inactive') }}</span>';
        }

        // Add/edit no longer carry a status control (new currencies always start
        // inactive, edit never touches status) — this switch in the list is now
        // the only way to activate/deactivate a currency.
        function currencyEnableDisableSwitchFormatter(value, row) {
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
