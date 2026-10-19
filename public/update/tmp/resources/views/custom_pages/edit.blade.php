@extends('layouts.main')

@section('title')
    {{ __('Edit Custom Page') }}
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
                            <a href="{{ route('custom-page.index') }}">{{ __('Custom Pages') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        {{ __('Edit Custom Page') }}
                    </div>
                    <hr>
                    {!! Form::open(['route' => ['custom-page.update', $page->id], 'data-parsley-validate', 'files' => true, 'method' => 'PATCH']) !!}
                    <div class="card-body">
                        <div class="row">
                            {{-- Title --}}
                            <div class="col-sm-12 col-md-6 form-group mandatory">
                                {{ Form::label('title', __('Title'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('title', $page->title, ['class' => 'form-control', 'placeholder' => __('Title'), 'data-parsley-required' => 'true', 'id' => 'title']) }}
                            </div>

                            {{-- Slug --}}
                            <div class="col-sm-12 col-md-6 form-group">
                                {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('slug', $page->slug_id, ['class' => 'form-control', 'placeholder' => __('Slug'), 'id' => 'slug']) }}
                                <small class="text-danger text-sm">{{ __('Only Small English Characters, Numbers And Hypens Allowed') }}</small>
                            </div>

                            {{-- Status --}}
                            <div class="col-sm-12 col-md-6 form-group">
                                {{ Form::label('status', __('Status'), ['class' => 'form-label col-12']) }}
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="status" id="status" value="1" {{ $page->status ? 'checked' : '' }}>
                                    <label class="form-check-label" for="status">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="row mt-3">
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('content', __('Content'), ['class' => 'form-label col-12']) }}
                                {{ Form::textarea('content', $page->content, ['class' => 'form-control tinymce_editor', 'id' => 'content', 'data-parsley-required' => 'true']) }}
                            </div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations --}}
                            <div class="translation-div mt-3">
                                <div class="card">
                                    <h3 class="card-header">{{ __('Translations') }}</h3>
                                    <hr>
                                    <div class="card-body p-0">
                                        <ul class="nav nav-tabs" id="customPageTranslationTabs" role="tablist">
                                            @foreach($languages as $key => $language)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                        {{ $language->name }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content p-3" id="customPageTranslationTabsContent">
                                            @foreach($languages as $key => $language)
                                                @php
                                                    $tTitle   = $page->translations->where('language_id', $language->id)->where('key', 'title')->first();
                                                    $tContent = $page->translations->where('language_id', $language->id)->where('key', 'content')->first();
                                                @endphp
                                                <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                    {{-- Translation Title --}}
                                                    <label for="translation-title-{{ $language->id }}">{{ __('Title') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][title][id]" value="{{ $tTitle->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][title][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $key }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" value="{{ $tTitle->value ?? '' }}" placeholder="{{ __('Enter Title') }}">
                                                    </div>

                                                    {{-- Translation Content --}}
                                                    <label for="translation-content-{{ $language->id }}">{{ __('Content') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][content][id]" value="{{ $tContent->id ?? '' }}">
                                                        <input type="hidden" name="translations[{{ $key }}][content][language_id]" value="{{ $language->id }}">
                                                        {{ Form::textarea('translations[' . $key . '][content][value]', $tContent->value ?? '', ['class' => 'form-control tinymce_editor', 'id' => 'translation-content-' . $language->id, 'placeholder' => __('Enter Content')]) }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Save Button --}}
                        <div class="card-footer mt-3">
                            <div class="col-12 d-flex justify-content-end">
                                {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                            </div>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
<script>
    $("#title").on('keyup', function () {
        let title = $(this).val();
        let slugElement = $("#slug");
        if (title) {
            $.ajax({
                type: 'POST',
                url: "{{ route('custom-page.generate-slug') }}",
                data: {
                    '_token': $('meta[name="csrf-token"]').attr('content'),
                    title: title,
                    id: {{ $page->id }}
                },
                beforeSend: function () {
                    slugElement.attr('readonly', true).val('{{ __("Please wait....") }}');
                },
                success: function (response) {
                    if (!response.error) {
                        slugElement.removeAttr('readonly').val(response.data || '');
                    }
                }
            });
        }
    });
</script>
@endsection
