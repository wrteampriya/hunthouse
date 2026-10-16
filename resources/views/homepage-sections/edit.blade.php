@extends('layouts.main')

@section('title')
    {{ __('Update Homepage Section') }}
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
                        <li class="breadcrumb-item">
                            <a href="{{ route('homepage-sections.index') }}">{{ __('Homepage Sections') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Update') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $titleTranslations = $section->translations->where('key', 'title')->keyBy('language_id');
        $appTitleTranslations = $section->translations->where('key', 'app_title')->keyBy('language_id');
    @endphp
    {!! Form::open(['route' => ['homepage-sections.update', $section->id], 'method' => 'PUT', 'data-parsley-validate', 'class' => 'create-form', 'data-success-function' => 'successFunction']) !!}
    <section class="section">
        <div class="card">
            <h3 class="card-header">{{ __('Details') }}</h3>
            <hr>
            <div class="card-body">
                <div class="row">
                    {{-- Web Title / App Title — one tab per language, default language first --}}
                    <div class="col-12 form-group mt-3">
                        <ul class="nav nav-tabs" id="homepageSectionLanguageTabs" role="tablist">
                            @foreach ($languages as $index => $language)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $index === 0 ? 'active' : '' }}" id="lang-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#lang-tab-{{ $language->id }}" type="button" role="tab">
                                    {{ $language->name }}
                                </button>
                            </li>
                            @endforeach
                        </ul>
                        <div class="tab-content pt-3" id="homepageSectionLanguageTabsContent">
                            @foreach ($languages as $index => $language)
                            <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="lang-tab-{{ $language->id }}" role="tabpanel">
                                @if ($index === 0)
                                <div class="row">
                                    <div class="col-md-6 form-group mandatory">
                                        {{ Form::label('title', __('Web Title'), ['class' => 'form-label']) }}
                                        {{ Form::text('title', $section->title, ['class' => 'form-control', 'placeholder' => trans('Web Title'), 'data-parsley-required' => 'true', 'id' => 'title']) }}
                                    </div>
                                    <div class="col-md-6 form-group">
                                        {{ Form::label('app_title', __('App Title'), ['class' => 'form-label']) }}
                                        {{ Form::text('app_title', $section->app_title, ['class' => 'form-control', 'placeholder' => trans('App Title'), 'id' => 'app_title']) }}
                                    </div>
                                </div>
                                @else
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label for="translation-{{ $language->id }}" class="form-label">{{ __('Web Title') }}</label>
                                        <input type="hidden" name="translations[{{ $language->id }}][id]" value="{{ $titleTranslations[$language->id]->id ?? '' }}">
                                        <input type="hidden" name="translations[{{ $language->id }}][language_id]" value="{{ $language->id }}">
                                        <input type="text" name="translations[{{ $language->id }}][value]" id="translation-{{ $language->id }}" class="form-control" value="{{ $titleTranslations[$language->id]->value ?? '' }}" placeholder="{{ __('Enter Web Title') }}">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="app-translation-{{ $language->id }}" class="form-label">{{ __('App Title') }}</label>
                                        <input type="hidden" name="app_translations[{{ $language->id }}][id]" value="{{ $appTitleTranslations[$language->id]->id ?? '' }}">
                                        <input type="hidden" name="app_translations[{{ $language->id }}][language_id]" value="{{ $language->id }}">
                                        <input type="text" name="app_translations[{{ $language->id }}][value]" id="app-translation-{{ $language->id }}" class="form-control" value="{{ $appTitleTranslations[$language->id]->value ?? '' }}" placeholder="{{ __('Enter App Title') }}">
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-12 col-xl-6 form-group mandatory">
                        {{ Form::label('section_type', __('Section Type'), ['class' => 'form-label text-center']) }}
                        {{ Form::select('section_type', $sectionTypes, $section->section_type, ['class' => 'form-control form-select', 'placeholder' => trans('Section Type'), 'data-parsley-required' => 'true', 'id' => 'section_type']) }}
                    </div>

                    {{-- Curated Properties Picker --}}
                    <div class="col-lg-12 form-group curated-properties-picker mt-3" style="display:none;">
                        {{ Form::label('property_ids_select', __('Select Properties'), ['class' => 'form-label text-center']) }}
                        <select id="property_ids" class="form-control select2-search-only"></select>
                        <div id="sortable-properties" class="mt-3 border rounded p-3 bg-light sortable-grid row g-2" style="max-height: 400px; overflow-y: auto;"></div>
                    </div>

                    {{-- Curated Projects Picker --}}
                    <div class="col-lg-12 form-group curated-projects-picker mt-3" style="display:none;">
                        {{ Form::label('project_ids_select', __('Select Projects'), ['class' => 'form-label text-center']) }}
                        <select id="project_ids" class="form-control select2-search-only"></select>
                        <div id="sortable-projects" class="mt-3 border rounded p-3 bg-light sortable-grid row g-2" style="max-height: 400px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="col-sm-12 col-md-12 text-end">
            {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
            <a href="{{ route('homepage-sections.index') }}" class="btn btn-secondary mb-1">{{ __('Cancel') }}</a>
        </div>
    </section>
    {!! Form::close() !!}
@endsection

@section('css')
<style>
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
</style>
@endsection

@section('script')
    @include('homepage-sections._picker-scripts')
    <script>
        function successFunction() {
            window.location.href = "{{ route('homepage-sections.index') }}";
        }

        $(document).ready(function() {
            initListingSelect2('#property_ids', '{{ route('homepage-sections.select2.properties') }}', '#sortable-properties', 'property_ids[]');
            initListingSelect2('#project_ids', '{{ route('homepage-sections.select2.projects') }}', '#sortable-projects', 'project_ids[]');

            @foreach ($section->properties as $property)
                addSortableItem('#sortable-properties', {{ $property->id }}, {!! json_encode($property->title.' (#'.$property->id.')') !!}, {!! json_encode($property->title_image) !!}, 'property_ids[]', {!! json_encode($property->status_badge) !!});
            @endforeach
            @foreach ($section->projects as $project)
                addSortableItem('#sortable-projects', {{ $project->id }}, {!! json_encode($project->title.' (#'.$project->id.')') !!}, {!! json_encode($project->image) !!}, 'project_ids[]', {!! json_encode($project->status_badge) !!});
            @endforeach

            var dragulaContainers = [];
            ['#sortable-properties', '#sortable-projects'].forEach(function(id) {
                var el = document.querySelector(id);
                if (el) {
                    dragulaContainers.push(el);
                    toggleEmptyState(id);
                }
            });
            dragula(dragulaContainers, {
                direction: 'vertical',
                moves: function (el, container, handle) {
                    return handle.classList.contains('drag-handle') || handle.closest('.drag-handle') !== null;
                }
            });

            togglePickerFor({!! json_encode($section->section_type) !!}, '.curated-properties-picker', '.curated-projects-picker');
            $('#section_type').on('change', function() {
                togglePickerFor($(this).val(), '.curated-properties-picker', '.curated-projects-picker');
            });
        });
    </script>
@endsection
