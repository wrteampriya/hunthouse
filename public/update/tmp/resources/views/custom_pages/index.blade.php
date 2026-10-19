@extends('layouts.main')

@section('title')
    {{ __('Custom Pages') }}
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
            @if (has_permissions('create', 'custom_page'))
                <div class="card-header">
                    <div class="col-12 d-flex justify-content-end">
                        <a href="{{ route('custom-page.create') }}" class="btn btn-primary">{{ __('Add Custom Page') }}</a>
                    </div>
                </div>
            @endif
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list" data-toggle="table"
                            data-url="{{ route('custom-page.show', 1) }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                            data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3"
                            data-query-params="queryParams" data-response-handler="globalTableResponseHandler">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="title" data-sortable="true">{{ __('Title') }}</th>
                                    <th scope="col" data-field="slug_id" data-sortable="true">{{ __('Slug') }}</th>
                                    @if (has_permissions('update', 'custom_page'))
                                        <th scope="col" data-field="edit_status" data-sortable="false" data-align="center"
                                            data-formatter="enableDisableSwitchFormatter">{{ __('Status') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'custom_page') || has_permissions('delete', 'custom_page'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center">{{ __('Action') }}</th>
                                    @endif
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
        };
    }
</script>
@endsection
