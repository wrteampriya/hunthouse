@extends('layouts.main')

@section('title')
    {{ $type == 'agent' ? __('Agent Feature Translations') : __('Package Feature Translations') }}
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
                            <a href="{{ $type == 'agent' ? route('agent-package-features.index') : route('package-features.index') }}" class="btn btn-primary">{{ __('Back') }}</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <label for="feature-name">{{ __('Feature Name') }}</label>
                                <input type="text" id="feature-name" class="form-control" value="{{ $feature->name }}" disabled>
                            </div>
                            <form action="{{ $type == 'agent' ? route('agent-package-features.update-translated-names') : route('package-features.update-translated-names') }}" class="create-form" method="post" data-success-function="successFunction">
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
                                        <div class="col-12">
                                            <ul class="nav nav-tabs" id="featureTranslationTabs" role="tablist">
                                                @foreach($languages as $key => $language)
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                            {{ $language->name }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <div class="tab-content p-3" id="featureTranslationTabsContent">
                                                @foreach($languages as $key => $language)
                                                    @php $featureTranslationData = $featureTranslations->where('language_id', $language->id)->first(); @endphp
                                                    <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                        @if($featureTranslationData)
                                                            <input type="hidden" name="translations[{{ $key }}][id]" value="{{ $featureTranslationData->id }}">
                                                        @endif
                                                        <div class="form-group">
                                                            <label for="translation-{{ $language->id }}">{{ __('Feature Name') }}</label>
                                                            <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                            <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="{{ $featureTranslationData['value'] ?? '' }}" placeholder="{{ __('Enter Feature Name') }}">
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
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