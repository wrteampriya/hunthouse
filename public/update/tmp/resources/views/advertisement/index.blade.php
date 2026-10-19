@extends('layouts.main')

@section('title')
    {{ __('Advertisement') }}
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
        <div class="card">
            <div class="card-body">
                {{-- Added As Filter Tabs --}}
                <div class="mb-3">
                    <div class="btn-group" role="group" id="added-as-filter">
                        <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                    </div>
                </div>
                <div class="row" id="toolbar">
                    {{-- Filter Status --}}
                    <div class="col-xl-4 mt-2">
                        <select class="form-select form-control-sm" id="filter-status">
                            <option value="">{{ __('Select Status') }}</option>
                            <option value="0">{{ __('Approved') }}</option>
                            <option value="1">{{ __('Pending') }}</option>
                            <option value="2">{{ __('Rejected') }}</option>
                            <option value="3">{{ __('Expired') }}</option>
                        </select>
                    </div>
                    {{-- Filter For --}}
                    <div class="col-xl-4 mt-2">
                        <select class="form-select form-control-sm" id="filter-for">
                            <option value="">{{ __('Select For') }}</option>
                            <option value="property">{{ __('Property') }}</option>
                            <option value="project">{{ __('Project') }}</option>
                        </select>
                    </div>
                    {{-- Filter Visibility --}}
                    <div class="col-xl-4 mt-2">
                        <select class="form-select form-control-sm" id="filter-visibility">
                            <option value="">{{ __('Select Visibility') }}</option>
                            <option value="1">{{ __('Enabled') }}</option>
                            <option value="0">{{ __('Disabled') }}</option>
                        </select>
                    </div>
                    {{-- Filter Category --}}
                    <div class="col-xl-4 mt-2">
                        <select class="form-select form-control-sm" id="filter_category">
                            <option value="">{{ __('Select Category') }}</option>
                            @if (isset($category))
                                @foreach ($category as $row)
                                    <option value="{{ $row->id }}">{{ $row->category }} </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list"
                            data-toggle="table" data-url="{{ url('featured_properties_list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}</th>
                                    <th scope="col" data-field="for" data-align="center" data-sortable="true" data-formatter="advertisementTypeFormatter">{{ __('Advertisement Type') }}</th>
                                    <th scope="col" data-field="image" data-formatter="imageFormatter" data-align="center"> {{ __('Image') }} </th>
                                    <th scope="col" data-field="start_date" data-align="center" data-sortable="true">{{ __('Start Date') }}</th>
                                    <th scope="col" data-field="end_date" data-align="center" data-sortable="true" data-formatter="expiryDateFormatter">{{ __('End Date') }}</th>
                                    <th scope="col" data-field="category" data-align="center" data-sortable="false">{{ __('Category') }}</th>
                                    <th scope="col" data-field="listing_title" data-align="center" data-sortable="false">{{ __('Listing Title') }}</th>
                                    <th scope="col" data-field="customer.name" data-align="center"> {{ __('Customer Name')}}</th>
                                    <th scope="col" data-field="created_by_role" data-align="center" data-formatter="addedAsFormatter"> {{ __('Created By') }}</th>
                                    <th scope="col" data-field="customer.mobile" data-align="center" data-visible="false">{{ __('Customer Contact') }}</th>
                                    <th scope="col" data-field="customer.email" data-align="center" data-visible="false" data-sortable="false">{{ __('Customer Email') }}</th>
                                    <th scope="col" data-field="status" data-align="center" data-sortable="false"> {{ __('Status') }} </th>
                                    @if (has_permissions('update', 'advertisement'))
                                        <th scope="col" data-field="is_enable" data-formatter="enableDisableSwitchFormatter" data-sortable="false" data-align="center" data-width="5%"> {{ __('Enable/Disable') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'advertisement'))
                                        <th scope="col" data-field="operate" data-align="center" data-sortable="false"> {{ __('Action') }}</th>
                                    @endif

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
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="myModalLabel1">{{ __('Advertisement Status') }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <form action="{{ url('adv-status-update') }}" class="form-horizontal"
                            enctype="multipart/form-data" method="POST" data-parsley-validate>

                            {{ csrf_field() }}

                            <div class="row">

                                <div class="col-sm-12">

                                    <select name="edit_adv_status" id="edit_adv_status" class="chosen-select form-select"
                                        style="width: 100%">

                                        <option value='0'>{{ __('Approved') }}</option>
                                        <option value='1'>{{ __('Pending') }}</option>
                                        <option value='2'>{{ __('Rejected') }}</option>

                                    </select>
                                    <input type="hidden" name="id" id="id">

                                </div>

                            </div>
                            <div class="modal-footer" style="padding: 2% 0%">
                                <button type="button" class="btn btn-secondary waves-effect"
                                    data-bs-dismiss="modal">{{ __('Close') }}</button>

                                <button type="submit"
                                    class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
        <input type="hidden" id="customerid" value="{{ isset($_GET['customer']) ? $_GET['customer'] : '' }}">
    </section>
@endsection

@section('script')
    <script>
        $('#status').on('change', function() {
            $('#table_list').bootstrapTable('refresh');

        });

        $('#category').on('change', function() {
            $('#table_list').bootstrapTable('refresh');
        });

        $('#added-as-filter button').on('click', function() {
            $('#added-as-filter button').removeClass('active');
            $(this).addClass('active');
            $('#table_list').bootstrapTable('refresh');
        });

        $(document).ready(function() {
            var params = new window.URLSearchParams(window.location.search);
            if (params.get('status') != 'null') {
                $('#status').val(params.get('status')).trigger('change');
            }
        });



        function setValue(id) {

            $("#id").val(id);
            // $("#edit_category").val($("#" + id).parents('tr:first').find('td:nth-child(3)').text());
        }

        function queryParams(p) {

            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                status: $('#filter-status').val(),
                for: $('#filter-for').val(),
                visibility: $('#filter-visibility').val(),
                category: $('#filter_category').val(),
                role_context_filter: $('#added-as-filter button.active').data('value'),
            };
        }

        $(document).ready(function() {
            // Initialize Bootstrap table
            $('#filter-status').change(function() {
                $('#table_list').bootstrapTable('refresh');
            });
            $('#filter-for').change(function() {
                $('#table_list').bootstrapTable('refresh');
            });
            $('#filter-visibility').change(function() {
                $('#table_list').bootstrapTable('refresh');
            });
            $('#filter_category').change(function() {
                $('#table_list').bootstrapTable('refresh');
            });
            $('#table_list').bootstrapTable();
        });
    </script>
@endsection
