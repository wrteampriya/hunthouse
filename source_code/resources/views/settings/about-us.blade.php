@extends('layouts.main')

@section('title')
    {{ __('About Us') }}
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
        <form action="{{ url('settings') }}" method="post">
            @csrf
            <div class="card">
                {{-- About us --}}
                <div class="card-body">
                    <input name="type" value="about_us" type="hidden">
                    <div class="row">
                        <div class="col-md-12">
                            <textarea id="tinymce_editor" name="data" class="form-control col-md-7 col-xs-12">{{ $settingData?->getRawOriginal('data') ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                @if(isset($translationLanguages) && $translationLanguages->count() > 0)
                    {{-- Translations Div --}}
                    <div class="translation-div">
                        <div class="card">
                            <h5 class="card-header">{{ __('Translations for About Us') }}</h3>
                            <hr>
                            <div class="card-body">
                                {{-- Fields for Translations --}}
                                @foreach($translationLanguages as $key =>$language)
                                    @php
                                        if(collect($settingData)->isNotEmpty()){
                                            $translation = $settingData->translations->where('language_id', $language->id)->where('key', 'data')->first();
                                        }
                                    @endphp
                                    <div class="bg-light p-3 mt-2 rounded">
                                        <h5 class="text-center">{{ $language->name }}</h5>
                                        <input type="hidden" name="translations[{{ $key }}][id]" id="translations-id-{{ $language->id }}" value="{{ $translation->id ?? '' }}">
                                        <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                        <textarea name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control tinymce_editor" placeholder="{{ __('Enter About Us') }}">{!! isset($translation) && !empty($translation) ? $translation->getRawOriginal('value') : '' !!}</textarea>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card">
                {{-- About us --}}
                <div class="card-body text-end">
                <button class="btn btn-primary me-1 mb-1" type="submit" name="submit">{{ __('Save') }}</button>
            </div>
            </div>
        </form>
    </section>
@endsection
