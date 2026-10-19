@extends('layouts.main')

@section('title')
    {{ __('Gemini AI Settings') }}
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
        {!! Form::open(['route' => 'gemini-settings.update', 'method' => 'POST', 'class' => 'create-form', 'data-parsley-validate','data-success-function'=> "formSuccessFunction"]) !!}

        <div class="form-group row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('API Configuration') }}</h6>
                        </div>

                        <div class="card-body">
                            {{-- Enable Gemini AI --}}
                            <div class="row">
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_ai_enabled">{{ __('Enable Gemini AI') }}</label>
                                    <div class="form-check form-switch mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="gemini_ai_enabled"
                                            name="gemini_ai_enabled"
                                            value="1"
                                            {{ isset($settings['gemini_ai_enabled']) && $settings['gemini_ai_enabled'] == '1' ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="gemini_ai_enabled">
                                            {{ __('Enable Gemini AI features') }}
                                        </label>
                                    </div>
                                    <small class="text-muted">{{ __('Toggle to enable or disable all Gemini AI functionality') }}</small>
                                </div>
                            </div>
                            {{-- Gemini API Key --}}
                            <div class="row mt-3">
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_api_key">{{ __('Gemini API Key') }}</label>
                                    <input
                                        name="gemini_api_key"
                                        type="text"
                                        class="form-control"
                                        id="gemini_api_key"
                                        placeholder="{{ __('Enter Gemini API Key') }}"
                                        value="{{ (env('DEMO_MODE',false) == false) ? isset($settings['gemini_api_key']) && $settings['gemini_api_key'] != '' ? $settings['gemini_api_key'] : '' : '****************************' }}"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('Rate Limits Globally') }}</h6>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                {{-- Description Generation Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_description_limit_global">
                                        {{ __('Description Generation Limit (per day)') }}
                                    </label>
                                    <input
                                        name="gemini_description_limit_global"
                                        type="number"
                                        class="form-control"
                                        id="gemini_description_limit_global"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_description_limit_global']) && $settings['gemini_description_limit_global'] != '' ? $settings['gemini_description_limit_global'] : 10 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of description generations globally per day (0 = unlimited)') }}</small>
                                </div>

                                {{-- Meta Details Generation Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_meta_limit_global">
                                        {{ __('Meta Details Generation Limit (per day)') }}
                                    </label>
                                    <input
                                        name="gemini_meta_limit_global"
                                        type="number"
                                        class="form-control"
                                        id="gemini_meta_limit_global"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_meta_limit_global']) && $settings['gemini_meta_limit_global'] != '' ? $settings['gemini_meta_limit_global'] : 10 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of meta details generations globally per day (0 = unlimited)') }}</small>
                                </div>

                                {{-- Search Limit (per hour) --}}
                                {{-- <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_search_limit_global per day">
                                        {{ __('Search Limit (globally per day (0 = unlimited)') }}
                                    </label>
                                    <input
                                        name="gemini_search_limit_global"
                                        type="number"
                                        class="form-control"
                                        id="gemini_search_limit_global"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_search_limit_global']) && $settings['gemini_search_limit_global'] != '' ? $settings['gemini_search_limit_global'] : 50 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of AI-powered searches per user per hour') }}</small>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('Rate Limits Per User') }}</h6>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                {{-- Description Generation Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_description_limit">
                                        {{ __('Description Generation Limit (per day)') }}
                                    </label>
                                    <input
                                        name="gemini_description_limit"
                                        type="number"
                                        class="form-control"
                                        id="gemini_description_limit"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_description_limit']) && $settings['gemini_description_limit'] != '' ? $settings['gemini_description_limit'] : 10 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of description generations per user per day (0 = unlimited)') }}</small>
                                </div>

                                {{-- Meta Details Generation Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_meta_limit">
                                        {{ __('Meta Details Generation Limit (per day)') }}
                                    </label>
                                    <input
                                        name="gemini_meta_limit"
                                        type="number"
                                        class="form-control"
                                        id="gemini_meta_limit"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_meta_limit']) && $settings['gemini_meta_limit'] != '' ? $settings['gemini_meta_limit'] : 10 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of meta details generations per user per day (0 = unlimited)') }}</small>
                                </div>

                                {{-- Search Limit (per hour) --}}
                                {{-- <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="gemini_search_limit_user">
                                        {{ __('Search Limit (per user per day (0 = unlimited)') }}
                                    </label>
                                    <input
                                        name="gemini_search_limit_user"
                                        type="number"
                                        class="form-control"
                                        id="gemini_search_limit_user"
                                        min="0"
                                        max="1000"
                                        required
                                        value="{{ isset($settings['gemini_search_limit_user']) && $settings['gemini_search_limit_user'] != '' ? $settings['gemini_search_limit_user'] : 50 }}"
                                    >
                                    <small class="text-muted">{{ __('Maximum number of AI-powered searches per user per day (0 = unlimited)') }}</small>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('Cache Management') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <p class="text-muted">{{ __('Clear all cached AI-generated content. This will force regeneration of descriptions and meta details on next request.') }}</p>
                                    <button type="button" class="btn btn-warning" id="clear-cache-btn">
                                        <i class="bi bi-trash"></i> {{ __('Clear AI Cache') }}
                                    </button>
                                    <div id="cache-clear-loading" class="d-none text-primary mt-2">
                                        <small><i class="bi bi-hourglass-split"></i> {{ __('Clearing cache...') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ __('Save Settings') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('script')
    <script>
        function formSuccessFunction(response) {
            if(!response.error){
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        }

        // Clear AI Cache
        $(document).ready(function() {
            $('#clear-cache-btn').on('click', function() {
                if (!confirm('{{ __("Are you sure you want to clear all AI cache? This will force regeneration of all cached AI content.") }}')) {
                    return;
                }

                const btn = $(this);
                const loadingDiv = $('#cache-clear-loading');

                btn.prop('disabled', true);
                loadingDiv.removeClass('d-none');

                $.ajax({
                    url: '{{ route("gemini-settings.clear-cache") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (!response.error) {
                            Toastify({
                                text: response.message || '{{ __("Cache cleared successfully") }}',
                                duration: 3000,
                                close: true,
                                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                            }).showToast();
                        } else {
                            Toastify({
                                text: response.message || '{{ __("Failed to clear cache") }}',
                                duration: 3000,
                                close: true,
                                backgroundColor: '#dc3545'
                            }).showToast();
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || '{{ __("An error occurred") }}';
                        Toastify({
                            text: errorMsg,
                            duration: 3000,
                            close: true,
                            backgroundColor: '#dc3545'
                        }).showToast();
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        loadingDiv.addClass('d-none');
                    }
                });
            });
        });
    </script>
@endsection
