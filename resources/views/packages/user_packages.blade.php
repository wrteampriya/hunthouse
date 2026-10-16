@extends('layouts.main')

@section('title')
    {{ __('Subscriptions') }}
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
        <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <div class="btn-group" role="group" id="user-type-filter">
                                    <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                                    <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                                    <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                                </div>
                            </div>
                            <table class="table table-striped"
                                id="table_list" data-toggle="table" data-url="{{ route('user-packages.list') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                data-search-align="right" data-show-columns="true"
                                data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3"
                                data-query-params="queryParams">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}</th>
                                        <th scope="col" data-field="customer.name" data-align="false" data-sortable="false"> {{ __('Customer Name') }} </th>
                                        <th scope="col" data-field="package_user_type" data-align="center" data-sortable="false" data-formatter="activeRoleFormatter"> {{ __('Type') }} </th>
                                        <th scope="col" data-field="package.name" data-sortable="false"> {{ __('Package Name') }} </th>
                                        <th scope="col" data-field="start_date" data-align="center" data-sortable="true"> {{ __('Start Date') }} </th>
                                        <th scope="col" data-field="end_date" data-align="center" data-sortable="true" data-formatter="expiryDateFormatter"> {{ __('End Date') }} </th>
                                        <th scope="col" data-field="subscription_status" data-align="center" data-sortable="false" data-formatter="yesNoStatusFormatter"> {{ __('Subscription') }} </th>
                                    </tr>
                                </thead>
                            </table>
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
            @if(!empty($type))
                $('#user-type-filter button').removeClass('active');
                $('#user-type-filter button[data-value="{{ $type }}"]').addClass('active');
            @endif
        });

        $('#user-type-filter button').on('click', function() {
            $('#user-type-filter button').removeClass('active');
            $(this).addClass('active');
            $('#table_list').bootstrapTable('refresh');
        });

        function queryParams(p) {
            var userType = $('#user-type-filter button.active').data('value');
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                user_type: userType !== undefined ? userType : ''
            };
        }
</script>
@endsection
