@extends('layouts.main')

@section('title')
    {{ __('Categories') }}
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
            @if(has_permissions('create', 'categories'))
                <div class="card-header">
                    <div class="row">
                        <div class="col-12 col-xs-12 d-flex justify-content-end">
                            <a href="{{ route('categories.create') }}" class="btn btn-primary">{{ __('Add Category') }}</a>
                        </div>
                    </div>
                </div>
            @endif
            <hr>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ url('categoriesList') }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="category" data-sortable="true" data-align="center">{{ __('Category') }}</th>
                                    <th scope="col" data-field="slug_id" data-visible="false" data-sortable="true" data-align="center">{{ __('Slug') }}</th>
                                    <th scope="col" data-field="image" data-formatter="imageFormatter" data-sortable="false" data-align="center">{{ __('Image') }}</th>
                                    <th scope="col" data-field="type" data-sortable="false" data-align="center">{{ __('Facilities') }}</th>
                                    <th scope="col" data-field="meta_title" data-sortable="true" data-align="center">{{ __('Meta Title') }}</th>
                                    <th scope="col" data-field="meta_description" data-sortable="true" data-align="center"> {{ __('Meta Description') }}</th>
                                    <th scope="col" data-field="meta_keywords" data-sortable="true" data-align="center">{{ __('Meta Keywords') }}</th>
                                    <th scope="col" data-field="status" data-sortable="false" data-formatter="enableDisableSwitchFormatter" data-align="center"> {{ __('Enable/Disable') }} </th>
                                    <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-events="actionEvents"> {{ __('Action') }}</th>
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
                search: p.search
            };
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                // Edit navigates via href, no JS needed
            }
        }
    </script>
@endsection
