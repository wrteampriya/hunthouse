@extends('layouts.main')

@section('title')
    {{ $type == 'agent' ? __('Assign Agent Package') : __('Assign Package') }}
@endsection



@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
<section class="section">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                                <form action="{{ $type == 'agent' ? route('assign-agent-package.store') : route('assign-package.store') }}" id="assign-package-form">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="assign_user_type" class="form-label">{{ __('Target Audience') }}</label>
                                        <select id="assign_user_type" name="user_type" class="form-select">
                                            <option value="user" {{ ($type ?? 'user') == 'user' ? 'selected' : '' }}>{{ __('User') }}</option>
                                            <option value="agent" {{ ($type ?? 'agent') == 'agent' ? 'selected' : '' }}>{{ __('Agent') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_id" class="form-label">{{ __('Select User') }}</label>
                                        <select id="customer_id" name="customer_id" class="form-control select2-ajax pl-5" required></select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="package_id" class="form-label">{{ __('Select Package') }}</label>
                                        <select id="package_id" name="package_id" class="form-control select2-ajax pl-5" required></select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{ __('Assign') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</section>
<section class="section">
    <div class="card">
        <div class="card-body">
            {{-- User/Agent Filter Tabs --}}
            <div class="mb-3">
                <div class="btn-group" role="group" id="payment-role-filter">
                    <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                    <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                    <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                        data-toggle="table" data-url="{{ route('payment.list') }}" data-click-to-select="true"
                        data-side-pagination="server" data-pagination="true"
                        data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                        data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                        data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                        data-pagination-successively-size="3" data-query-params="queryParams">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}</th>
                                <th scope="col" data-field="customer.name" data-align="center" data-sortable="false"> {{ __('Client Name') }}</th>
                                <th scope="col" data-field="customer_role" data-align="center" data-formatter="activeRoleFormatter"> {{ __('Role') }}</th>
                                <th scope="col" data-field="package.name" data-align="center" data-sortable="false"> {{ __('Package Name') }} </th>
                                <th scope="col" data-field="amount" data-align="center" data-sortable="true" data-formatter="paymentAmountFormatter"> {{ __('Amount') }} </th>
                                <th scope="col" data-field="payment_type" data-align="center" data-sortable="true">{{ __('Payment Type') }} </th>
                                <th scope="col" data-field="transaction_id" data-align="center" data-sortable="true">{{ __('Transaction Id') }} </th>
                                <th scope="col" data-field="payment_gateway" data-align="center" data-sortable="true">{{ __('Payment Gateway') }} </th>
                                <th scope="col" data-field="payment_status" data-align="center" data-sortable="true" data-formatter="paymentStatusFormatter"> {{ __('Status') }}</th>
                                <th scope="col" data-field="created_at" data-align="center" data-sortable="true" data-visible="false"> {{ __('Payment Date') }} </th>
                                <th scope="col" data-field="updated_at" data-align="center" data-sortable="true" data-visible="false"> {{ __('Payment Update Date') }} </th>
                                <th scope="col" data-field="operate" data-align="center" data-sortable="false"> {{ __('Action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection 

@section('script')
    <script>
        $(document).ready(function() {
            function initSelect2(selector, url, placeholder){
                $(selector).select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: 'resolve',
                    theme: 'bootstrap-5',
                    ajax: {
                        url: url,
                        dataType: 'json',
                        delay: 250,
                        cache: true,
                        data: function(params){
                            return {
                                q: params.term || '',
                                page: params.page || 1,
                                per_page: 20,
                                type : $('#assign_user_type').val()
                            };
                        },
                        processResults: function (data, params) {
                            params.page = params.page || 1;
                            return {
                                results: data.results || [],
                                pagination: {
                                    more: data.pagination && data.pagination.more ? true : false
                                }
                            };
                        }
                    },
                    minimumInputLength: 0
                });
            }

            initSelect2('#customer_id', '{{ route('select2.customers') }}', '{{ __('Search users...') }}');
            initSelect2('#package_id', '{{ route('select2.packages') }}', '{{ __('Search packages...') }}');

            $('#assign_user_type').on('change', function() {
                // Destroy and reinitialize select2 to clear cached results
                $('#customer_id').val(null).trigger('change').empty();
                $('#package_id').val(null).trigger('change').empty();

                $('#customer_id').select2('destroy');
                $('#package_id').select2('destroy');

                initSelect2('#customer_id', '{{ route('select2.customers') }}', '{{ __('Search users...') }}');
                initSelect2('#package_id', '{{ route('select2.packages') }}', '{{ __('Search packages...') }}');

                $('#table_list').bootstrapTable('refresh');
            });
        });

        $('#assign-package-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var url = form.attr('action');
            var submitBtn = form.find(':submit');
            var data = new FormData(this);

            submitBtn.val('{{ __("Please Wait...") }}').attr('disabled', true);

            $.ajax({
                type: 'POST',
                url: url,
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    submitBtn.val('{{ __("Assign") }}').attr('disabled', false);
                    if (response.error) {
                        showErrorToast(response.message);
                        return;
                    }
                    if (response.warning && response.data && response.data.confirm_required) {
                        Swal.fire({
                            title: window.trans['Are you sure'] || 'Are you sure',
                            text: response.message,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#198754',
                            cancelButtonColor: '#d33',
                            confirmButtonText: window.trans['Yes'] || 'Yes',
                            cancelButtonText: window.trans['No'] || 'No',
                            reverseButtons: true,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                data.append('force_assign', '1');
                                submitBtn.val('{{ __("Please Wait...") }}').attr('disabled', true);
                                $.ajax({
                                    type: 'POST',
                                    url: url,
                                    data: data,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',
                                    success: function(res) {
                                        submitBtn.val('{{ __("Assign") }}').attr('disabled', false);
                                        if (!res.error) {
                                            showSuccessToast(res.message);
                                            form[0].reset();
                                            $('#customer_id').val(null).trigger('change');
                                            $('#package_id').val(null).trigger('change');
                                            $('#table_list').bootstrapTable('refresh');
                                        } else {
                                            showErrorToast(res.message);
                                        }
                                    },
                                    error: function(jqXHR) {
                                        submitBtn.val('{{ __("Assign") }}').attr('disabled', false);
                                        if (jqXHR.responseJSON) showErrorToast(jqXHR.responseJSON.message);
                                    }
                                });
                            }
                        });
                        return;
                    }
                    showSuccessToast(response.message);
                    form[0].reset();
                    $('#customer_id').val(null).trigger('change');
                    $('#package_id').val(null).trigger('change');
                    $('#table_list').bootstrapTable('refresh');
                },
                error: function(jqXHR) {
                    submitBtn.val('{{ __("Assign") }}').attr('disabled', false);
                    if (jqXHR.responseJSON) showErrorToast(jqXHR.responseJSON.message);
                }
            });
        });

        $('#payment-role-filter button').on('click', function() {
            $('#payment-role-filter button').removeClass('active');
            $(this).addClass('active');
            $('#table_list').bootstrapTable('refresh');
        });

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                manual_payment_type_only: 1,
                role_filter: $('#payment-role-filter button.active').data('value'),
            };
        }
    </script>
@endsection