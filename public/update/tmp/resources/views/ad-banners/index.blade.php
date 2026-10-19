@extends('layouts.main')

@section('title')
    {{ __('Advertisement Banners') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Advertisement Banners') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="d-flex justify-content-end align-items-center">
            <a href="{{ route('ad-banners.create') }}" class="btn btn-primary">{{ __('Create New') }}</a>
        </div>
    </div>
@endsection

@section('content')
<section class="section">
    <div class="card">
        <div class="card-body">
            <div class="row" id="toolbar">
                {{-- Filter Category --}}
                <div class="col-xl-4 mt-2">
                    <select class="form-select form-control-sm" id="filter-page">
                        <option value="">{{ __('Select Page') }}</option>
                        <option value="homepage">{{ __('Homepage') }}</option>
                        <option value="property_listing">{{ __('Property Listing Page') }}</option>
                        <option value="property_detail">{{ __('Property Detail Page') }}</option>
                    </select>
                </div>
                {{-- Filter Platform --}}
                <div class="col-xl-4 mt-2">
                    <select class="form-select form-control-sm" id="filter-platform">
                        <option value="">{{ __('Select Platform') }}</option>
                        <option value="app">{{ __('App') }}</option>
                        <option value="web">{{ __('Web') }}</option>
                    </select>
                </div>
                {{-- Filter Status --}}
                <div class="col-xl-4 mt-2">
                    <select class="form-select form-control-sm" id="filter-status">
                        <option value="">{{ __('Select Status') }}</option>
                        <option value="1">{{ __('Active') }}</option>
                        <option value="0">{{ __('Inactive') }}</option>
                        <option value="expired">{{ __('Expired') }}</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <table class="table table-striped"
                        id="table_list" data-toggle="table" data-url="{{ route('ad-banners.show',1) }}"
                        data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                        data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                        data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                        data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                        data-pagination-successively-size="3" data-query-params="queryParams">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-align="center">{{ __('ID') }}</th>
                                <th scope="col" data-field="image" data-formatter="imageFormatter" data-align="center" data-sortable="false">{{ __('Banner Image') }}</th>
                                <th scope="col" data-field="page" data-sortable="true" data-align="center" data-formatter="pageFormatter">{{ __('Page') }}</th>
                                <th scope="col" data-field="platform" data-sortable="true" data-align="center" data-formatter="platformFormatter">{{ __('Platform') }}</th>
                                <th scope="col" data-field="placement" data-sortable="true" data-align="center" data-formatter="placementFormatter">{{ __('Placement') }}</th>
                                <th scope="col" data-field="type" data-sortable="true" data-align="center" data-formatter="typeFormatter">{{ __('Ad Type') }}</th>
                                <th scope="col" data-field="property.title" data-sortable="false" data-align="center">{{ __('Property') }}</th>
                                <th scope="col" data-field="external_link_url" data-sortable="false" data-align="center" data-formatter="linkFormatter">{{ __('External Link') }}</th>
                                <th scope="col" data-field="is_active" data-sortable="true" data-align="center" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                <th scope="col" data-field="duration_days" data-sortable="true" data-align="center" data-formatter="durationDaysFormatter">{{ __('Duration Days') }}</th>
                                <th scope="col" data-field="ends_at_raw" data-sortable="true" data-align="center" data-formatter="endDateFormatter">{{ __('End Date') }}</th>
                                <th scope="col" data-field="operate" data-sortable="false" data-align="center">{{ __('Action') }}</th>
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
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            limit: p.limit,
            search: p.search,
            page: $('#filter-page').val(),
            platform: $('#filter-platform').val(),
            status: $('#filter-status').val()
        };
    }

    // Format link
    function linkFormatter(value, row, index) {
        if (value && value !== '-') {
            return `<a href="${value}" target="_blank" class="text-primary">${window.trans["View Link"]}</a>`;
        }
        return '-';
    }

    // Format page
    function pageFormatter(value, row, index) {
        switch(row.pageRawValue) {
            case 'homepage':
                return `<span>${window.trans['Homepage']}</span>`;
            case 'property_listing':
                return `<span>${window.trans['Property Listing Page']}</span>`;
            case 'property_detail':
                return `<span>${window.trans['Property Detail Page']}</span>`;
            default:
                return `<span>-</span>`;
                break;
        }
    }

    // Format platform
    function platformFormatter(value, row, index) {
        if(value == 'app'){
            return `<span class="badge bg-primary">${window.trans['App']}</span>`;
        } else {
            return `<span class="badge bg-secondary">${window.trans['Web']}</span>`;
        }
    }

    // Format type
    function typeFormatter(value, row, index) {
        if(value == 'external_link'){
            return `<span>${window.trans['External Link']}</span>`;
        } else if(value == 'property'){
            return `<span>${window.trans['Property']}</span>`;
        } else if(value == 'banner_only'){
            return `<span>${window.trans['Banner Only']}</span>`;
        } else {
            return `<span>-</span>`;
        }
    }

    // Format status
    function statusFormatter(value, row, index) {
        if(row.is_expired){
            return `<span class="badge bg-danger text-dark">${window.trans['Expired']}</span>`;
        }
        return `<div class="form-check form-switch" text-center">
            <input class = "form-check-input switch1"id = "${row.id}" onclick = "chk(this);" data-url="${row.edit_status_url}" type="checkbox" role="switch" ${value == 1 ? 'checked' : ''} value="${value}">
            ${value == 1 ? `<span class="badge bg-success">${window.trans['Active']}</span>` : `<span class="badge bg-warning">${window.trans['Inactive']}</span>`}
        </div>`;
    }

    // Format placement
    function placementFormatter(value, row, index) {
        switch(row.placement) {
            case 'below_categories':
                return `<span>${window.trans['Below Categories']}</span>`;
            case 'above_all_properties':
                return `<span>${window.trans['Above All Properties']}</span>`;
            case 'above_facilities':
                return `<span>${window.trans['Above Facilities']}</span>`;
            case 'above_similar_properties':
                return `<span>${window.trans['Above Similar Properties']}</span>`;
            case 'below_slider':
                return `<span>${window.trans['Below Slider']}</span>`;
            case 'above_footer':
                return `<span>${window.trans['Above Footer']}</span>`;
            case 'sidebar_below_filters':
                return `<span>${window.trans['Sidebar Below Filters']}</span>`;
            case 'below_breadcrumb':
                return `<span>${window.trans['Below Breadcrumb']}</span>`;
            case 'sidebar_below_mortgage_loan_calculator':
                return `<span>${window.trans['Sidebar Below Mortgage Loan Calculator']}</span>`;
            case 'above_footer':
                return `<span>${window.trans['Above Footer']}</span>`;
            case 'above_breadcrumb':
                return `<span>${window.trans['Above Breadcrumb']}</span>`;
            default:
                return `<span>-</span>`;
                break;
        }
    }

    // Format duration days
    function durationDaysFormatter(value, row, index) {
        return `<span>${value} ${window.trans['Days']}</span>`;
    }

    // Format end date with remaining days badge (same style as property table)
    function endDateFormatter(value, row, index) {
        if (!value || value === '-') {
            return '-';
        }
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var expiry = new Date(value);
        expiry.setHours(0, 0, 0, 0);
        var diffTime = expiry - today;
        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        // Format display date as dd-mm-yyyy
        var day = String(expiry.getDate()).padStart(2, '0');
        var month = String(expiry.getMonth() + 1).padStart(2, '0');
        var year = expiry.getFullYear();
        var formattedDate = day + '-' + month + '-' + year;

        if (diffDays < 0) {
            return formattedDate + '<br><span class="badge bg-light-danger text-danger">' + (window.trans['Expired'] || 'Expired') + '</span>';
        } else if (diffDays === 0) {
            return formattedDate + '<br><span class="badge bg-light-warning text-warning">' + (window.trans['Expires Today'] || 'Expires Today') + '</span>';
        } else {
            return formattedDate + '<br><span class="badge bg-light-success text-success">' + diffDays + ' ' + (window.trans['Days Left'] || 'Days Left') + '</span>';
        }
    }

    $(document).ready(function() {
        // Initialize Bootstrap table
        $('#filter-page').change(function() {
            $('#table_list').bootstrapTable('refresh');
        });
        $('#filter-platform').change(function() {
            $('#table_list').bootstrapTable('refresh');
        });
        $('#filter-status').change(function() {
            $('#table_list').bootstrapTable('refresh');
        });
        $('#table_list').bootstrapTable();
    });
</script>
@endsection
