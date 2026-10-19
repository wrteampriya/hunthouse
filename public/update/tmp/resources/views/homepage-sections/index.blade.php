
@extends('layouts.main')

@section('title')
    {{ __('Homepage Sections') }}
@endsection

@section('css')
<style>
    .gu-mirror {
        position: fixed !important;
        margin: 0 !important;
        z-index: 9999 !important;
        opacity: 0.9 !important;
        background-color: white !important;
        border: 2px solid var(--bs-primary) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        border-radius: 0.5rem !important;
    }
    .gu-transit {
        opacity: 0.4 !important;
        border: 2px dashed #aaa !important;
        background-color: #f8f9fa !important;
    }
    /* Required by dragula's internal hit-testing (getElementBehindPoint):
       it hides the mirror via this class before calling elementFromPoint()
       to find the sibling under the cursor. Without it, drop-target
       detection silently fails and reordering never commits. */
    .gu-hide {
        display: none !important;
    }
    .gu-unselectable {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }
</style>
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
            <div class="card-header">
                <h4>{{ __("Static Sections Settings") }}</h4>
            </div>
            <div class="card-body">
                {!! Form::open(["url" => route("homepage-sections.update-toggles"), "method" => "POST"]) !!}
                <div class="row my-3">
                    @php
                        $sliderStatus = system_setting("slider_section");
                        $searchStatus = system_setting("search_section");
                        $allPropertiesStatus = system_setting("all_properties_section");
                        
                        $sliderStatus = $sliderStatus !== null ? $sliderStatus : 1;
                        $searchStatus = $searchStatus !== null ? $searchStatus : 1;
                        $allPropertiesStatus = $allPropertiesStatus !== null ? $allPropertiesStatus : 1;
                    @endphp
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100" style="border: 2px solid var(--bs-primary);">
                            <div class="card-body d-flex justify-content-between align-items-center p-3">
                                <label class="form-check-label h6 mb-0" for="slider_section">{{ __("Slider Section") }}</label>
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="slider_section" value="0">
                                    <input class="form-check-input" type="checkbox" id="slider_section" name="slider_section" value="1" {{ $sliderStatus == 1 ? "checked" : "" }} style="width: 2.5em; height: 1.25em;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100" style="border: 2px solid var(--bs-primary);">
                            <div class="card-body d-flex justify-content-between align-items-center p-3">
                                <label class="form-check-label h6 mb-0" for="search_section">{{ __("Search Section") }}</label>
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="search_section" value="0">
                                    <input class="form-check-input" type="checkbox" id="search_section" name="search_section" value="1" {{ $searchStatus == 1 ? "checked" : "" }} style="width: 2.5em; height: 1.25em;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100" style="border: 2px solid var(--bs-primary);">

                        <div class="card-body d-flex justify-content-between align-items-center p-3">
                                <label class="form-check-label h6 mb-0" for="all_properties_section">{{ __("All Properties Section") }}</label>
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="all_properties_section" value="0">
                                    <input class="form-check-input" type="checkbox" id="all_properties_section" name="all_properties_section" value="1" {{ $allPropertiesStatus == 1 ? "checked" : "" }} style="width: 2.5em; height: 1.25em;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">{{ __("Save") }}</button>
                    </div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </section>


    <section class="section">

        @if(has_permissions('create', 'homepage-sections') || has_permissions('update', 'homepage-sections'))
            <div class="col-md-12 text-end">
                @if(has_permissions('update', 'homepage-sections'))
                    <a href="{{ route('homepage-sections.change-order') }}" class="btn mb-3 btn-secondary">
                        <i class="bi bi-list-ol"></i>
                        {{ __('Change Order') }}
                    </a>
                @endif
                @if(has_permissions('create', 'homepage-sections'))
                <a href="{{ route('homepage-sections.create') }}" class="btn mb-3 btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-plus-circle-fill" viewBox="0 0 16 16">
                        <path
                            d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3v-3z">
                        </path>
                    </svg>
                    {{ __('Add Homepage Section') }}
                </a>
                @endif
            </div>
        @endif

    </section>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ route('homepage-sections.show',1) }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="sort_order" data-sort-order="asc"
                            data-pagination-successively-size="3" data-query-params="queryParams"
                            data-use-row-attr-func="true">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="title" data-sortable="true">{{ __('Web Title') }}</th>
                                    <th scope="col" data-field="app_title" data-sortable="true">{{ __('App Title') }}</th>
                                    <th scope="col" data-field="section_type" data-sortable="true" data-formatter="homepageSectionTypeFormatter">{{ __('Section Type') }}</th>
                                    <th scope="col" data-field="sort_order" data-sortable="true" data-align="center" data-width="5%"> {{ __('Order') }}</th>
                                    @if(has_permissions('update', 'homepage-sections'))
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-width="5%" data-formatter="enableDisableSwitchFormatter"> {{ __('Enable/Disable') }}</th>
                                    @else
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-width="5%" data-formatter="statusFormatter"> {{ __('Status') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'homepage-sections') || has_permissions('delete', 'homepage-sections'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center"> {{ __('Action') }}</th>
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
    @include('homepage-sections._picker-scripts')
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
    </script>
@endsection
