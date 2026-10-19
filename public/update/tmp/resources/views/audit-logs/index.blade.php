@extends('layouts.main')

@section('title')
    {{ __('Audit Logs') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"> </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">

                {{-- Actor Type Filter Tabs --}}
                <div class="mb-3">
                    <div class="btn-group" role="group" id="actor-type-filter">
                        <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="admin">{{ __('Admin') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                    </div>
                </div>

                <div class="row" id="toolbar">
                    {{-- Filter Module --}}
                    <div class="col-xl-3 mt-2">
                        <select class="form-select form-control-sm" id="filter-entity-type">
                            <option value="">{{ __('Select Module') }}</option>
                            <option value="property">{{ __('Property') }}</option>
                            <option value="project">{{ __('Project') }}</option>
                        </select>
                    </div>

                    {{-- Filter Action --}}
                    <div class="col-xl-3 mt-2">
                        <select class="form-select form-control-sm" id="filter-action">
                            <option value="">{{ __('Select Action') }}</option>
                            <option value="created">{{ __('Created') }}</option>
                            <option value="updated">{{ __('Updated') }}</option>
                            <option value="deleted">{{ __('Deleted') }}</option>
                            <option value="status_changed">{{ __('Status Changed') }}</option>
                            <option value="approved">{{ __('Approved') }}</option>
                            <option value="rejected">{{ __('Rejected') }}</option>
                        </select>
                    </div>

                    {{-- Filter Source --}}
                    <div class="col-xl-3 mt-2">
                        <select class="form-select form-control-sm" id="filter-source">
                            <option value="">{{ __('Select Source') }}</option>
                            <option value="admin_panel">{{ __('Admin Panel') }}</option>
                            <option value="api">{{ __('App / Web') }}</option>
                        </select>
                    </div>

                    {{-- Filter Date From --}}
                    <div class="col-xl-3 col-md-4 col-sm-6 mt-2">
                        <input type="date" id="filter-date-from" class="form-select form-control-sm w-100">
                    </div>

                    {{-- Filter Date To --}}
                    <div class="col-xl-3 col-md-4 col-sm-6 mt-2">
                        <input type="date" id="filter-date-to" class="form-select form-control-sm w-100">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped"
                            id="audit-log-table"
                            data-toggle="table"
                            data-url="{{ url('audit-logs/list') }}"
                            data-click-to-select="true"
                            data-side-pagination="server"
                            data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true"
                            data-search-align="right"
                            data-toolbar="#toolbar"
                            data-show-columns="true"
                            data-show-refresh="true"
                            data-trim-on-search="false"
                            data-responsive="true"
                            data-sort-name="id"
                            data-sort-order="desc"
                            data-pagination-successively-size="3"
                            data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="created_at" data-sortable="true">{{ __('Date & Time') }}</th>
                                    <th scope="col" data-field="actor_type" data-align="center" data-sortable="false" data-formatter="actorTypeFormatter">{{ __('Actor Type') }}</th>
                                    <th scope="col" data-field="actor_name" data-align="center" data-sortable="false">{{ __('Actor Name') }}</th>
                                    <th scope="col" data-field="source" data-align="center" data-sortable="false">{{ __('Source') }}</th>
                                    <th scope="col" data-field="entity_type" data-align="center" data-sortable="false">{{ __('Module') }}</th>
                                    <th scope="col" data-field="entity_title" data-align="center" data-sortable="false">{{ __('Record') }}</th>
                                    <th scope="col" data-field="action" data-align="center" data-sortable="false" data-formatter="actionFormatter">{{ __('Action') }}</th>
                                    <th scope="col" data-field="description" data-sortable="false">{{ __('Description') }}</th>
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
        function queryParams(p) {
            return {
                sort:        p.sort,
                order:       p.order,
                offset:      p.offset,
                limit:       p.limit,
                search:      p.search,
                entity_type: $('#filter-entity-type').val(),
                actor_type:  $('#actor-type-filter button.active').data('value'),
                action:      $('#filter-action').val(),
                source:      $('#filter-source').val(),
                date_from:   $('#filter-date-from').val(),
                date_to:     $('#filter-date-to').val(),
            };
        }

        function actionFormatter(value) {
            const map = {
                'Created':        'success',
                'Updated':        'primary',
                'Deleted':        'danger',
                'Status Changed': 'warning',
                'Approved':       'success',
                'Rejected':       'danger',
            };
            const color = map[value] ?? 'secondary';
            return `<span class="badge bg-${color}">${value}</span>`;
        }

        function actorTypeFormatter(value) {
            const v = (value || '').toLowerCase();
            if (v === 'admin') return `<span class="badge bg-primary">${window.trans['Admin'] || value}</span>`;
            if (v === 'agent') return `<span class="badge bg-info">${window.trans['Agent'] || value}</span>`;
            return `<span class="badge bg-success">${window.trans['User'] || value}</span>`;
        }

        $('#filter-entity-type, #filter-action, #filter-source').on('change', function () {
            $('#audit-log-table').bootstrapTable('refresh');
        });

        $('#filter-date-from, #filter-date-to').on('change', function () {
            $('#audit-log-table').bootstrapTable('refresh');
        });

        $('#actor-type-filter button').on('click', function () {
            $('#actor-type-filter button').removeClass('active');
            $(this).addClass('active');
            $('#audit-log-table').bootstrapTable('refresh');
        });
    </script>
@endsection
