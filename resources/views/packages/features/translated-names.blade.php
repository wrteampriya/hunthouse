@extends('layouts.main')

@section('title')
    {{ __('Features') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
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
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('package-features.index') }}" class="btn btn-primary">{{ __('Back') }}</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <label for="feature-name">{{ __('Feature Name') }}</label>
                                <input type="text" id="feature-name" class="form-control" value="{{ $feature->name }}" disabled>
                            </div>
                            <form action="{{ route('package-features.update-translated-names') }}" class="create-form" method="post" data-success-function="successFunction">
                                <input type="hidden" name="feature_id" value="{{ $feature->id }}">
                                @if(isset($languages) && $languages->count() > 0)
                                    {{-- Translations Div --}}
                                    <div class="translation-div mt-4">
                                        <div class="col-12">
                                            <div class="divider">
                                                <div class="divider-text">
                                                    <h5>{{ __('Translations for Feature Name') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Fields for Translations --}}
                                        @foreach($languages as $key => $language)
                                            @php $featureTranslationData = $featureTranslations->where('language_id', $language->id)->first(); @endphp
                                            @if($featureTranslationData)
                                                <input type="hidden" name="translations[{{ $key }}][id]" value="{{ $featureTranslationData->id }}">
                                            @endif
                                            <div class="col-md-6 col-xl-4">
                                                <div class="form-group">
                                                    <label for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                                    <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="{{ $featureTranslationData['value'] ?? '' }}" placeholder="{{ __('Enter Feature Name') }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="col-12  d-flex justify-content-end pt-3">
                                    <input type="submit" value="{{ __('Save') }}" class="btn btn-primary me-1 mb-1">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection
@section('script')
    <script>
        function successFunction() {
            window.location.reload();
        }
    </script>
@endsection