@extends('layouts.main')

@section('title')
    {{ __('Add Custom Page') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Add') }}</li>
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
                        {{ __('New Custom Page') }}
                    </div>
                    <hr>
                    {!! Form::open(['route' => 'custom-page.store', 'data-parsley-validate', 'files' => true]) !!}
                    <div class="card-body">
                        <div class="row">
                            {{-- Title --}}
                            <div class="col-sm-12 col-md-6 form-group mandatory">
                                {{ Form::label('title', __('Title'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('title', '', ['class' => 'form-control', 'placeholder' => __('Title'), 'data-parsley-required' => 'true', 'id' => 'title']) }}
                            </div>

                            {{-- Slug --}}
                            <div class="col-sm-12 col-md-6 form-group">
                                {{ Form::label('slug', __('Slug'), ['class' => 'form-label col-12']) }}
                                {{ Form::text('slug', '', ['class' => 'form-control', 'placeholder' => __('Slug'), 'id' => 'slug']) }}
                                <small class="text-danger text-sm">{{ __('Only Small English Characters, Numbers And Hypens Allowed') }}</small>
                            </div>

                            {{-- Icon --}}
                            <div class="col-md-12 col-sm-12 form-group">
                                {{ Form::label('icon', __('Icon'), ['class' => 'col-12 form-label']) }}
                                <input accept="image/jpg,image/png,image/jpeg,image/webp" name="icon" type="file" class="form-control filepond" id="icon" />
                            </div>

                            {{-- Status --}}
                            <div class="col-sm-12 col-md-6 form-group">
                                {{ Form::label('status', __('Status'), ['class' => 'form-label col-12']) }}
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="status" id="status" value="1" checked>
                                    <label class="form-check-label" for="status">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="row mt-3">
                            <div class="col-md-12 col-sm-12 form-group mandatory">
                                {{ Form::label('content', __('Content'), ['class' => 'form-label col-12']) }}
                                {{ Form::textarea('content', '', ['class' => 'form-control tinymce_editor', 'id' => 'content', 'data-parsley-required' => 'true']) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <!-- <div class="card-body"> -->

                        @if(isset($languages) && $languages->count() > 0)
                        {{-- Translations --}}
                        <div class="translation-div">
                            <div class="card">
                                <h5 class="card-header">{{ __('Translations') }}</h3>
                                <hr>
                                <div class="card-body">
                                    @foreach($languages as $key => $language)
                                    <div class="bg-light p-3 mt-2 rounded">
                                        <h5 class="text-center">{{ $language->name }}</h5>
                                        
                                        {{-- Translation Title --}}
                                        <label for="translation-title-{{ $language->id }}">{{ __('Title') }}</label>
                                        <div class="form-group">
                                            <input type="hidden" name="translations[{{ $key }}][title][language_id]" value="{{ $language->id }}">
                                            <input type="text" name="translations[{{ $key }}][title][value]" id="translation-title-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Title') }}">
                                        </div>
                                        
                                        {{-- Translation Content --}}
                                        <label for="translation-content-{{ $language->id }}">{{ __('Content') }}</label>
                                        <div class="form-group">
                                            <input type="hidden" name="translations[{{ $key }}][content][language_id]" value="{{ $language->id }}">
                                            <textarea name="translations[{{ $key }}][content][value]" id="translation-content-{{ $language->id }}" class="form-control tinymce_editor" placeholder="{{ __('Enter Content') }}"></textarea>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    <!-- </div> -->
                </div>

                    {{-- Save Button --}}
                        <div class="card">
                <div class="card-body text-end">
                            {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
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
                    title: title
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
        } else {
            slugElement.removeAttr('readonly').val('');
        }
    });
</script>
@endsection
