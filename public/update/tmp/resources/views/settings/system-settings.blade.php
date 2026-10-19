@extends('layouts.main')

@section('title')
    {{ __('System Settings') }}
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
        {{-- <form class="form" id="myForm" action="{{ url('set_settings') }}" data-parsley-validate method="POST" id="setting_form" enctype="multipart/form-data"> --}}
        {!! Form::open([
            'route' => 'store-settings',
            'data-parsley-validate',
            'class' => 'create-form',
            'data-success-function' => 'formSuccessFunction',
            'enctype' => 'multipart/form-data',
        ]) !!}

        {{ csrf_field() }}
        <div class="form-group row">
            <div class="col-12">
                <div class="card" style="height: 95%">

                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('Company Details') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                {{-- Company Name --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label center" for="company_name">{{ __('Company Name') }}</label>
                                    <input name="company_name" type="text" class="form-control" id="company_name"
                                        placeholder="{{ __('Company Name') }}"
                                        value="{{ isset($systemSettings['company_name']) && $systemSettings['company_name'] != '' ? $systemSettings['company_name'] : 'eBroker' }}">
                                </div>

                                {{-- Email --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="email">{{ __('Email') }}</label>
                                    <input name="company_email" type="email" id="email" class="form-control"
                                        placeholder="{{ __('Email') }}"
                                        value="{{ isset($systemSettings['company_email']) && $systemSettings['company_email'] != '' ? $systemSettings['company_email'] : '' }}">
                                </div>

                                {{-- Contact Number 1 --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="company-tel1">{{ __('Contact Number 1') }}</label>
                                    <input name="company_tel1" type="text" id="company-tel1" class="form-control"
                                        placeholder="{{ __('Contact Number 1') }}"
                                        value="{{ isset($systemSettings['company_tel1']) && $systemSettings['company_tel1'] != '' ? $systemSettings['company_tel1'] : '' }}">
                                </div>

                                {{-- Contact Number 2 --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label mt-1" for="company-tel2">{{ __('Contact Number 2') }}</label>
                                    <input name="company_tel2" type="text" id="company-tel2" class="form-control"
                                        placeholder="{{ __('Contact Number 2') }}"
                                        value="{{ isset($systemSettings['company_tel2']) && $systemSettings['company_tel2'] != '' ? $systemSettings['company_tel2'] : '' }}">
                                </div>

                                {{-- Latitude --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label" for="latitude">{{ __('Latitude') }}</label>
                                    <input name="latitude" type="text" id="latitude" class="form-control"
                                        placeholder="{{ __('Latitude') }}"
                                        value="{{ isset($systemSettings['latitude']) && $systemSettings['latitude'] != '' ? $systemSettings['latitude'] : '' }}">
                                </div>

                                {{-- Longitude --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label mt-1" for="longitude">{{ __('Longitude') }}</label>
                                    <input name="longitude" type="text" id="longitude" class="form-control"
                                        placeholder="{{ __('Longitude') }}"
                                        value="{{ isset($systemSettings['longitude']) && $systemSettings['longitude'] != '' ? $systemSettings['longitude'] : '' }}">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <label class="form-label-mandatory"
                                    for="company-address">{{ __('Company Address') }}</label>
                                <div class="col-sm-12">
                                    <textarea name="company_address" class="form-control" id="company_address" rows="3"
                                        placeholder="{{ __('Company Address') }}">{{ isset($systemSettings['company_address']) && $systemSettings['company_address'] != '' ? $systemSettings['company_address'] : '' }}</textarea>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ __('More Settings') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                {{-- Countries --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 form-label"
                                        for="currency-code">{{ __('Currency Name') }}</label>
                                    <select id="currency-code" class="form-select form-control-sm select2"
                                        name="currency_code" required>
                                        <option value="">{{ __('Select Currency') }}</option>
                                        @if (!empty($listOfCurrencies))
                                            @foreach ($listOfCurrencies as $data)
                                                <option value="{{ $data['currency_code'] }}">{{ $data['currency_name'] }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <input type="hidden" id="url-for-currency-symbol"
                                        value="{{ route('get-currency-symbol') }}">
                                </div>

                                {{-- Currency Symbol --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 form-label "
                                        for="curreny-symbol">{{ __('Currency Symbol') }}</label>
                                    <input name="currency_symbol" type="text" id="currency-symbol" class="form-control"
                                        placeholder="{{ __('Currency Symbol') }}" required maxlength="5"
                                        value="{{ isset($systemSettings['currency_symbol']) && $systemSettings['currency_symbol'] != '' ? $systemSettings['currency_symbol'] : '' }}">
                                </div>

                                {{-- Default Language --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 form-label mt-1"
                                        for="default_language">{{ __('Default Language') }}</label>
                                    <select name="default_language" id="default_language"
                                        class="choosen-select form-select form-control-sm" required>
                                        @foreach ($languagesWithEnglish as $row)
                                            {{ $row }}
                                            <option value="{{ $row->getRawOriginal('code') }}"
                                                {{ isset($systemSettings['default_language']) && $systemSettings['default_language'] == $row->getRawOriginal('code') ? 'selected' : '' }}>
                                                {{ $row->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Timezone --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 form-label mt-1" for="timezone">{{ __('Timezone') }}</label>
                                    <select name="timezone" id="timezone" class="form-select form-control-sm select2"
                                        required>
                                        @php
                                            $utc = new DateTimeZone('UTC');
                                            $now = new DateTime('now', $utc);
                                        @endphp
                                        @foreach (DateTimeZone::listIdentifiers() as $timezone)
                                            @php
                                                $tz = new DateTimeZone($timezone);
                                                $offset = $tz->getOffset($now);
                                                $offsetHours = abs(floor($offset / 3600));
                                                $offsetMinutes = abs(floor(($offset % 3600) / 60));
                                                $offsetString =
                                                    ($offset < 0 ? '-' : '+') .
                                                    str_pad($offsetHours, 2, '0', STR_PAD_LEFT) .
                                                    ':' .
                                                    str_pad($offsetMinutes, 2, '0', STR_PAD_LEFT);
                                            @endphp
                                            <option value="{{ $timezone }}"
                                                {{ isset($systemSettings['timezone']) && $systemSettings['timezone'] == $timezone ? 'selected' : '' }}>
                                                {{ $timezone }} (UTC {{ $offsetString }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Min Radius Range --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 mt-1 form-label"
                                        for="min-radius-range">{{ __('Min Radius Range') }} <i class="fa fa-info-circle"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ trans('Minimum Radius Range for Homepage Location Data') }}"></i></label>
                                    <input name="min_radius_range" type="number" min="0" id="min-radius-range"
                                        class="form-control" placeholder="{{ __('Min Radius Range') }}"
                                        value="{{ isset($systemSettings['min_radius_range']) && $systemSettings['min_radius_range'] != '' ? $systemSettings['min_radius_range'] : '' }}"
                                        required>
                                </div>

                                {{-- Max Radius Range --}}
                                <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                    <label class="col-sm-12 mt-1 form-label"
                                        for="max-radius-range">{{ __('Max Radius Range') }} <i class="fa fa-info-circle"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ trans('Maximum Radius Range for Homepage Location Data') }}"></i></label>
                                    <input name="max_radius_range" type="number" min="0" id="max-radius-range"
                                        class="form-control" placeholder="{{ __('Max Radius Range') }}"
                                        value="{{ isset($systemSettings['max_radius_range']) && $systemSettings['max_radius_range'] != '' ? $systemSettings['max_radius_range'] : '' }}"
                                        required>
                                </div>

                                <hr class="mt-4" style="">

                                {{-- Unsplash API Key --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-12 form-label"
                                        for="unsplash-api-key">{{ __('Unsplash API Key') }}</label>
                                    <input name="unsplash_api_key" type="text" id="unsplash-api-key"
                                        class="form-control" placeholder="{{ __('Unsplash API Key') }}"
                                        value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['unsplash_api_key']) && $systemSettings['unsplash_api_key'] != '' ? $systemSettings['unsplash_api_key'] : '') : '****************************') : (isset($systemSettings['unsplash_api_key']) && $systemSettings['unsplash_api_key'] != '' ? $systemSettings['unsplash_api_key'] : '') }}">
                                </div>

                                {{-- Playstore App link --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-12 form-label">{{ __('Playstore Id') }}</label>
                                    <input name="playstore_id" type="text" class="form-control"
                                        placeholder="{{ __('Playstore Id') }}"
                                        value="{{ isset($systemSettings['playstore_id']) && $systemSettings['playstore_id'] != '' ? $systemSettings['playstore_id'] : '' }}">
                                </div>

                                {{-- Appstore App link --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-12 form-label">{{ __('Appstore Id') }}</label>
                                    <input name="appstore_id" type="text" class="form-control"
                                        placeholder="{{ __('Appstore Id') }}"
                                        value="{{ isset($systemSettings['appstore_id']) && $systemSettings['appstore_id'] != '' ? $systemSettings['appstore_id'] : '' }}">
                                </div>

                                {{-- Number With Suffix --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-4 form-check-label"
                                        for="switch_number_with_suffix">{{ __('Number With Suffix') }}</label>
                                    <div class="col-sm-1 col-md-1 col-xs-12 ">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="number_with_suffix" id="number_with_suffix"
                                                value="{{ isset($systemSettings['number_with_suffix']) && $systemSettings['number_with_suffix'] != '' ? $systemSettings['number_with_suffix'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['number_with_suffix']) && $systemSettings['number_with_suffix'] == '1' ? 'checked' : '' }}
                                                id="switch_number_with_suffix">
                                        </div>
                                    </div>
                                </div>

                                {{-- Change Icon Colors to theme Color --}}
                                {{-- <div class="col-sm-12 col-md-6 mt-2 form-group">
                                        <label class="col-sm-5 form-check-label" for="switch_svg_clr">{{ __('Change Icon Colors to theme Color ?') }}</label>
                                        <div class="col-sm-1">
                                            <div class="form-check form-switch ">
                                                <input type="hidden" name="svg_clr" id="svg_clr" value="{{ isset($systemSettings['svg_clr']) && $systemSettings['svg_clr'] != '' ? $systemSettings['svg_clr'] : 0 }}">
                                                <input class="form-check-input" type="checkbox" role="switch" {{ isset($systemSettings['svg_clr']) && $systemSettings['svg_clr'] == '1' ? 'checked' : '' }} id="switch_svg_clr">
                                                <label class="form-check-label" for="switch_svg_clr"></label>
                                            </div>
                                        </div>
                                    </div> --}}
                                {{-- Distance Options --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group mandatory">
                                    <label class="col-sm-12 form-label mt-3"
                                        for="distance-option">{{ __('Distance Options') }}</label>
                                    <select name="distance_option" id="distance-option"
                                        class="form-select form-control-sm" required>
                                        <option
                                            {{ isset($systemSettings['distance_option']) && $systemSettings['distance_option'] == 'km' ? 'selected' : '' }}
                                            value="km">{{ __('Kilometers') }}</option>
                                        <option
                                            {{ isset($systemSettings['distance_option']) && $systemSettings['distance_option'] == 'm' ? 'selected' : '' }}
                                            value="m">{{ __('Meters') }}</option>
                                        <option
                                            {{ isset($systemSettings['distance_option']) && $systemSettings['distance_option'] == 'mi' ? 'selected' : '' }}
                                            value="mi">{{ __('Miles') }}</option>
                                        <option
                                            {{ isset($systemSettings['distance_option']) && $systemSettings['distance_option'] == 'yd' ? 'selected' : '' }}
                                            value="yd">{{ __('Yards') }}</option>
                                    </select>
                                </div>

                                {{-- System Color --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-12 form-label mt-3">{{ __('System Color') }}</label>
                                    <input name="system_color" type="color" class="form-control"
                                        placeholder="{{ __('System Color') }}"
                                        value="{{ isset($systemSettings['system_color']) && $systemSettings['system_color'] != '' ? $systemSettings['system_color'] : '#087C7C' }}"
                                        id="systemColor">
                                    <input type="hidden" id="hiddenRGBA" name="rgb_color">
                                </div>

                                {{-- Web URL --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="form-label mt-3">{{ __('Web URL') }}</label>
                                    <input name="web_url" id="web-url" type="text" class="form-control"
                                        placeholder="{{ __('Web URL') }}"
                                        value="{{ isset($systemSettings['web_url']) && $systemSettings['web_url'] != '' ? $systemSettings['web_url'] : '' }}">
                                </div>


                                {{-- Auto Approve Edited Listings --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Auto Approve Edited Listings') }} <i
                                            class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ trans('Edited Listings will be automatically approved after editing') }}"></i></label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="auto_approve_edited_listings"
                                                id="auto_approve_edited_listings"
                                                value="{{ isset($systemSettings['auto_approve_edited_listings']) && $systemSettings['auto_approve_edited_listings'] != '' ? $systemSettings['auto_approve_edited_listings'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['auto_approve_edited_listings']) && $systemSettings['auto_approve_edited_listings'] == '1' ? 'checked' : '' }}
                                                id="switch_auto_approve_edited_listings">
                                            <label class="form-check-label"
                                                for="switch_auto_approve_edited_listings"></label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Toggle to show location alert --}}
                                <div class="col-sm-12 col-md-6 mt-2 form-group">
                                    <label class="col-sm-5 form-check-label"
                                        for="switch-homepage-location-alert">{{ __('Show Location Alert on Homepage') }}</label>
                                    <div class="col-sm-1">
                                        <div class="form-check form-switch ">
                                            <input type="hidden" name="homepage_location_alert_status"
                                                id="homepage-location-alert-status"
                                                value="{{ isset($systemSettings['homepage_location_alert_status']) && $systemSettings['homepage_location_alert_status'] != '' ? $systemSettings['homepage_location_alert_status'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['homepage_location_alert_status']) && $systemSettings['homepage_location_alert_status'] == '1' ? 'checked' : '' }}
                                                id="switch-homepage-location-alert">
                                            <label class="form-check-label" for="switch-homepage-location-alert"></label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Notify User for Subscription Expiry --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label
                                        class="form-check-label">{{ __('Notify User for Subscription Expiry') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="notify_user_for_subscription_expiry"
                                                id="notify_user_for_subscription_expiry"
                                                value="{{ isset($systemSettings['notify_user_for_subscription_expiry']) && $systemSettings['notify_user_for_subscription_expiry'] != '' ? $systemSettings['notify_user_for_subscription_expiry'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['notify_user_for_subscription_expiry']) && $systemSettings['notify_user_for_subscription_expiry'] == '1' ? 'checked' : '' }}
                                                id="switch_notify_user_for_subscription_expiry">
                                            <label class="form-check-label"
                                                for="switch_notify_user_for_subscription_expiry"></label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Days Before Subscription Expiry --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label">{{ __('Days Before Subscription Expiry') }}</label>
                                    <input name="days_before_subscription_expiry" type="number" class="form-control"
                                        placeholder="{{ __('Days Before Subscription Expiry') }}"
                                        value="{{ isset($systemSettings['days_before_subscription_expiry']) && $systemSettings['days_before_subscription_expiry'] != '' ? $systemSettings['days_before_subscription_expiry'] : '' }}"
                                        min="1" max="31">
                                </div>

                                {{-- Show Direct Video upload --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Show Direct Video upload') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="show_direct_video_upload"
                                                id="show_direct_video_upload"
                                                value="{{ isset($systemSettings['show_direct_video_upload']) && $systemSettings['show_direct_video_upload'] != '' ? $systemSettings['show_direct_video_upload'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['show_direct_video_upload']) && $systemSettings['show_direct_video_upload'] == '1' ? 'checked' : '' }}
                                                id="switch_show_direct_video_upload">
                                            <label class="form-check-label" for="switch_show_direct_video_upload"></label>
                                        </div>
                                    </div>
                                </div>
                                {{-- Story Duration Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label">{{ __('Story Duration Limit (Seconds)') }}</label>
                                    <input name="story_max_duration" type="number" class="form-control"
                                        placeholder="{{ __('Story Duration Limit (Seconds)') }}"
                                        value="{{ isset($systemSettings['story_max_duration']) && $systemSettings['story_max_duration'] != '' ? $systemSettings['story_max_duration'] : 60 }}"
                                        min="1" max="180">
                                </div>

                                {{-- Story Video Size Limit --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-label">{{ __('Story Video Max Size (MB)') }}</label>
                                    <input name="story_video_max_size" type="number" class="form-control"
                                        placeholder="{{ __('Story Video Max Size (MB)') }}"
                                        value="{{ isset($systemSettings['story_video_max_size']) && $systemSettings['story_video_max_size'] != '' ? $systemSettings['story_video_max_size'] : 50 }}"
                                        min="1" max="500">
                                </div>

                                {{-- show whatapp button --}}
                                  <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Show WhatsApp Button') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="show_whatsapp_button"
                                                id="show_whatsapp_button"
                                                value="{{ isset($systemSettings['show_whatsapp_button']) && $systemSettings['show_whatsapp_button'] != '' ? $systemSettings['show_whatsapp_button'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['show_whatsapp_button']) && $systemSettings['show_whatsapp_button'] == '1' ? 'checked' : '' }}
                                                id="switch_show_whatsapp_button">
                                            <label class="form-check-label" for="switch_show_whatsapp_button"></label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Audit Log Enabled --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Enable Audit Logs') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="audit_log_enabled" id="audit_log_enabled"
                                                value="{{ isset($systemSettings['audit_log_enabled']) && $systemSettings['audit_log_enabled'] != '' ? $systemSettings['audit_log_enabled'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['audit_log_enabled']) && $systemSettings['audit_log_enabled'] == '1' ? 'checked' : '' }}
                                                id="switch_audit_log_enabled">
                                            <label class="form-check-label" for="switch_audit_log_enabled"></label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Show Premium Toggle --}}
                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Show Premium Toggle') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="show_premium_toggle" id="show_premium_toggle"
                                                value="{{ isset($systemSettings['show_premium_toggle']) && $systemSettings['show_premium_toggle'] != '' ? $systemSettings['show_premium_toggle'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['show_premium_toggle']) && $systemSettings['show_premium_toggle'] == '1' ? 'checked' : '' }}
                                                id="switch_show_premium_toggle">
                                            <label class="form-check-label" for="switch_show_premium_toggle"></label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-12 col-md-6 mt-2">
                                    <label class="form-check-label">{{ __('Show exact location of property/project') }}</label>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="show_exact_location" id="show_exact_location"
                                                value="{{ isset($systemSettings['show_exact_location']) && $systemSettings['show_exact_location'] != '' ? $systemSettings['show_exact_location'] : 0 }}">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                {{ isset($systemSettings['show_exact_location']) && $systemSettings['show_exact_location'] == '1' ? 'checked' : '' }}
                                                id="switch_show_exact_location">
                                            <label class="form-check-label" for="switch_show_exact_location"></label>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Map Service Provider Settings --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="divider pt-3">
                        <h6 class="divider-text">{{ __('Map Service Provider Settings') }} <i class="fa fa-info-circle"
                                data-bs-toggle="tooltip" data-bs-placement="top"
                                title="{{ trans('Can use same api key for map and place api key if there is no restrictions in api key') }}"></i>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">

                            {{-- Map Service Provider --}}

                            <div class="col-sm-12 col-md-6 mt-2 form-group mandatory">
                                <label class="col-sm-12 form-label" for="map-service-provider">{{ __('Map Service Provider') }}</label>
                                <select name="map_service_provider" id="map-service-provider"
                                    class="choosen-select form-select form-control-sm">
                                    <option
                                        {{ isset($systemSettings['map_service_provider']) && $systemSettings['map_service_provider'] == 'google_maps' ? 'selected' : '' }}
                                        value="google_maps">{{ __('Google Maps') }}</option>
                                    <option
                                        {{ isset($systemSettings['map_service_provider']) && $systemSettings['map_service_provider'] == 'open_street_maps' ? 'selected' : '' }}
                                        value="open_street_maps">{{ __('Open Street Maps (Free)') }}</option>
                                </select>
                                <small id="open-street-maps-note" class="form-text text-muted mt-1" style="display: none">
                                    <i class="fa fa-info-circle"></i>
                                    {{ __('Note: Search results and place detail accuracy may be lower with OpenStreetMap compared to Google Maps.') }}
                                </small>
                            </div>

                            {{-- Geonames Username --}} 
                            <div class="col-sm-12 col-md-6 mt-2 form-group mandatory" id="geonames-username-div"
                                style="display: none">
                                <label class="col-sm-12 form-label" for="geonames-username">{{ __('Geonames Username') }} <i
                                        class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ trans('Geonames username for Open Street Maps') }}"></i></label>
                                <input name="geonames_username" type="text" id="geonames-username" class="form-control"
                                    placeholder="{{ __('Geonames Username') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['geonames_username']) && $systemSettings['geonames_username'] != '' ? $systemSettings['geonames_username'] : '') : '****************************') : (isset($systemSettings['geonames_username']) && $systemSettings['geonames_username'] != '' ? $systemSettings['geonames_username'] : '') }}">

                                    <div class="mt-2">
                                    <button type="button" id="clear-open-street-maps-cache"
                                        class="btn btn-outline-secondary btn-sm">
                                        {{ __('Clear Open Street Maps Cache') }}
                                        <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ __('Clears cached Open Street Maps responses') }}"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Map API Key --}}
                            <div class="col-sm-12 col-md-6 mt-2 form-group">
                                <label class="col-sm-12 form-label" for="map-api-key">{{ __('Map API Key') }} <i
                                        class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ trans('Map API key used for Google Maps and its restricted by domains or can be open for all') }}"></i></label>
                                <input name="map_api_key" type="text" id="map-api-key" class="form-control"
                                    placeholder="{{ __('Map API Key') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['map_api_key']) && $systemSettings['map_api_key'] != '' ? $systemSettings['map_api_key'] : '') : '****************************') : (isset($systemSettings['map_api_key']) && $systemSettings['map_api_key'] != '' ? $systemSettings['map_api_key'] : '') }}">
                            </div>

                            {{-- Place API Key --}}
                            <div class="col-sm-12 col-md-6 mt-2 form-group">
                                <label class="col-sm-12 form-label" for="place-api-key">{{ __('Place API Key') }} <i
                                        class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ trans('Place API key used for places and its restricted by ip or can be open for all') }}"></i></label>
                                <input name="place_api_key" type="text" id="place-api-key" class="form-control"
                                    placeholder="{{ __('Place API Key') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['place_api_key']) && $systemSettings['place_api_key'] != '' ? $systemSettings['place_api_key'] : '') : '****************************') : (isset($systemSettings['place_api_key']) && $systemSettings['place_api_key'] != '' ? $systemSettings['place_api_key'] : '') }}">
                                <div class="mt-2">
                                    <button type="button" id="clear-gmaps-cache"
                                        class="btn btn-outline-secondary btn-sm">
                                        {{ __('Clear Google Maps Cache') }}
                                        <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ __('Clears cached Google Places responses') }}"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Login Methods --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="divider pt-3">
                        <h6 class="divider-text">{{ __('Login Methods') }}</h6>
                    </div>
                    <div class="card-body row">

                        {{-- Number with OTP Login Toggle --}}
                        <div class="col-sm-12 col-md-6 mt-2 form-group mandatory">
                            <label class="form-check-label"
                                for="number-with-otp-login-toggle">{{ __('Number with OTP Login') }}</label>
                            <div class="col-sm-1">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="number_with_otp_login" id="number-with-otp-login"
                                        value="{{ isset($systemSettings['number_with_otp_login']) && $systemSettings['number_with_otp_login'] == 1 ? 1 : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        {{ isset($systemSettings['number_with_otp_login']) && $systemSettings['number_with_otp_login'] == '1' ? 'checked' : '' }}
                                        id="number-with-otp-login-toggle">
                                    <label class="form-check-label" for="number-with-otp-login-toggle"></label>
                                </div>
                            </div>
                        </div>

                        {{-- OTP Services Provider --}}
                        <div class="col-sm-12 col-md-6 mt-2 form-group mandatory" id="otp-services-provider-div"
                            style="display: none">
                            <label class="col-sm-12 form-label-mandatory"
                                for="otp-services-provider">{{ __('OTP Services Provider') }}</label>
                            <select name="otp_service_provider" id="otp-services-provider"
                                class="choosen-select form-select form-control-sm">
                                <option
                                    {{ isset($systemSettings['otp_service_provider']) && $systemSettings['otp_service_provider'] == 'firebase' ? 'selected' : '' }}
                                    value="firebase">{{ __('Firebase') }}</option>
                                <option
                                    {{ isset($systemSettings['otp_service_provider']) && $systemSettings['otp_service_provider'] == 'twilio' ? 'selected' : '' }}
                                    value="twilio">{{ __('Twilio') }}</option>
                            </select>
                        </div>

                        <div class="col-12 mt-2 p-4 row bg-light rounded" id="twilio-sms-settings-div"
                            style="display: none">
                            {{-- TWILIO --}}
                            <h5>{{ __('Twilio SMS Settings') }}</h5>

                            {{-- Account SID --}}
                            <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                <label class="col-sm-12 form-label"
                                    for="twilio-account-sid">{{ __('Account SID') }}</label>
                                <input name="twilio_account_sid" type="text"
                                    class="form-control twilio-account-settings" id="twilio-account-sid"
                                    placeholder="{{ __('Account SID') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['twilio_account_sid']) && $systemSettings['twilio_account_sid'] != '' ? $systemSettings['twilio_account_sid'] : '') : '****************************') : (isset($systemSettings['twilio_account_sid']) && $systemSettings['twilio_account_sid'] != '' ? $systemSettings['twilio_account_sid'] : '') }}">
                            </div>

                            {{-- Auth Token --}}
                            <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                <label class="col-sm-12 form-label"
                                    for="twilio-auth-token">{{ __('Auth Token') }}</label>
                                <input name="twilio_auth_token" type="text"
                                    class="form-control twilio-account-settings" id="twilio-auth-token"
                                    placeholder="{{ __('Auth Token') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['twilio_auth_token']) && $systemSettings['twilio_auth_token'] != '' ? $systemSettings['twilio_auth_token'] : '') : '****************************') : (isset($systemSettings['twilio_auth_token']) && $systemSettings['twilio_auth_token'] != '' ? $systemSettings['twilio_auth_token'] : '') }}">
                            </div>

                            {{-- My Twilio Phone Number --}}
                            <div class="col-sm-12 col-md-6 col-lg-4 mt-2 form-group mandatory">
                                <label class="col-sm-12 form-label"
                                    for="twilio-my-phone-number">{{ __('My Twilio Phone Number') }}</label>
                                <input name="twilio_my_phone_number" type="text"
                                    class="form-control twilio-account-settings" id="twilio-my-phone-number"
                                    placeholder="{{ __('My Twilio Phone Number') }}"
                                    value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['twilio_my_phone_number']) && $systemSettings['twilio_my_phone_number'] != '' ? $systemSettings['twilio_my_phone_number'] : '') : '****************************') : (isset($systemSettings['twilio_my_phone_number']) && $systemSettings['twilio_my_phone_number'] != '' ? $systemSettings['twilio_my_phone_number'] : '') }}">
                            </div>
                        </div>

                        {{-- Social Login Toggle --}}
                        <div class="col-sm-12 col-md-6 mt-2 form-group mandatory">
                            <label class="form-check-label"
                                for="social-login-toggle">{{ __('Social Login (Google & Apple)') }}</label>
                            <div class="col-sm-1">
                                <div class="form-check form-switch ">
                                    <input type="hidden" name="social_login" id="social-login"
                                        value="{{ isset($systemSettings['social_login']) && $systemSettings['social_login'] == 1 ? 1 : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        {{ isset($systemSettings['social_login']) && $systemSettings['social_login'] == '1' ? 'checked' : '' }}
                                        id="social-login-toggle">
                                    <label class="form-check-label mandatory" for="social-login-toggle"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Email & Password Login Toggle --}}
                        <div class="col-sm-12 col-md-6 mt-2 form-group mandatory">
                            <label class="form-check-label"
                                for="email-password-login-toggle">{{ __('Email & Password Login') }}</label>
                            <div class="col-sm-1">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="email_password_login" id="email-password-login"
                                        value="{{ isset($systemSettings['email_password_login']) && $systemSettings['email_password_login'] == 1 ? 1 : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        {{ isset($systemSettings['email_password_login']) && $systemSettings['email_password_login'] == '1' ? 'checked' : '' }}
                                        id="email-password-login-toggle">
                                    <label class="form-check-label mandatory" for="email-password-login-toggle"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="card">
            <div class="card-body">
                <div class="form-group row">
                    {{-- Deep Link Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Deep Link Settings') }}</h6>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="schema" class="form-label">{{ __('Schema') }}</label>
                            <input type="text" class=" form-control" name="schema_for_deeplink" id="schema"
                                value="{{ env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (isset($systemSettings['schema_for_deeplink']) && $systemSettings['schema_for_deeplink'] != '' ? $systemSettings['schema_for_deeplink'] : '') : '') : (isset($systemSettings['schema_for_deeplink']) && $systemSettings['schema_for_deeplink'] != '' ? $systemSettings['schema_for_deeplink'] : '') }}"
                                placeholder="{{ __('Your Schema') }}">
                            <small
                                class="text-grey">{{ __('Note: Please add your scheme here using a single word in lowercase (e.g., ebroker).') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="divider pt-3">
                        <h6 class="divider-text">{{ __('Images') }}</h6>
                    </div>

                    <div class="row">
                        {{-- Favicon --}}
                        <div class="col-md-6 col-lg-4 mt-3">
                            <div class="col-12 form-group mandatory card title_card">
                                {{ Form::label('favicon_icon', __('Favicon Icon'), ['class' => 'form-label col-12 ']) }}
                                <input type="file" class="filepond" id="favicon_icon" name="favicon_icon"
                                    {{ isset($systemSettings['favicon_icon']) && $systemSettings['favicon_icon'] == '' ? 'required' : '' }}
                                    accept="image/png,image/jpg,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon,.ico">
                                @if (isset($systemSettings['favicon_icon']) && $systemSettings['favicon_icon'] != '')
                                    <div class="title_img mt-2">
                                        <img src="{{ url('assets/images/logo/' . $systemSettings['favicon_icon']) }}"
                                            alt="Image" class="img-fluid" width="100" height="100">
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Company Logo --}}
                        <div class="col-md-6 col-lg-4 mt-3">
                            <div class="col-12 form-group mandatory card title_card">
                                {{ Form::label('company_logo', __('Company Logo'), ['class' => 'form-label col-12 ']) }}
                                <input type="file" class="filepond" id="company_logo" name="company_logo"
                                    {{ isset($systemSettings['company_logo']) && $systemSettings['company_logo'] == '' ? 'required' : '' }}
                                    accept="image/png,image/jpg,image/jpeg">
                                @if (isset($systemSettings['company_logo']) && $systemSettings['company_logo'] != '')
                                    <div class="title_img mt-2">
                                        <img src="{{ url('assets/images/logo/' . $systemSettings['company_logo']) }}"
                                            alt="Image" class="img-fluid" width="100" height="100">
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Login Page Image --}}
                        <div class="col-md-6 col-lg-4 mt-3">
                            <div class="col-12 form-group mandatory card title_card">
                                {{ Form::label('login_image', __('Login Page Image'), ['class' => 'form-label col-12 ']) }}
                                <input type="file" class="filepond" id="login_image" name="login_image"
                                    {{ isset($systemSettings['login_image']) && $systemSettings['login_image'] == '' ? 'required' : '' }}
                                    accept="image/png,image/jpg,image/jpeg">
                                @if (isset($systemSettings['login_image']) && $systemSettings['login_image'] != '')
                                    <div class="title_img mt-2">
                                        <img src="{{ url('assets/images/bg/' . $systemSettings['login_image']) }}"
                                            alt="Image" class="img-fluid" width="100" height="100">
                                    </div>
                                @else
                                    <div class="title_img mt-2">
                                        <img src="{{ url('assets/images/bg/Login_BG.jpg') }}" alt="Image"
                                            class="img-fluid" width="100" height="100">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" name="btnAdd" value="btnAdd"
                class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
        </div>
        {!! Form::close() !!}

    </section>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            let countryValue =
                "{{ isset($systemSettings['currency_code']) && $systemSettings['currency_code'] != '' ? $systemSettings['currency_code'] : '' }}";
            $("#currency-code").val(countryValue).trigger("change").promise().done(function() {
                let currencySymbol =
                    "{{ isset($systemSettings['currency_symbol']) && $systemSettings['currency_symbol'] != '' ? $systemSettings['currency_symbol'] : '' }}";
                setTimeout(() => {
                    $("#currency-symbol").val(currencySymbol);
                }, 100);
            });

        });


        $(document).on('click', '#favicon_icon', function(e) {

            $('.favicon_icon').hide();

        });
        $(document).on('click', '#company_logo', function(e) {

            $('.company_logo').hide();

        });

        $(document).on('click', '#login_image', function(e) {
            $('.login_image').hide();
        });

        // const checkboxes = document.querySelectorAll('input[type=checkbox][role=switch][name=op]', );
        // checkboxes.forEach((checkbox) => {
        //     checkbox.addEventListener('change', (event) => {
        //         if (event.target.checked) {
        //             checkboxes.forEach((checkbox) => {
        //                 if (checkbox !== event.target) {
        //                     checkbox.checked = false;
        //                     $("#switch_paypal_gateway").is(':checked') ? $("#paypal_gateway").val(1) : $("#paypal_gateway") .val(0);
        //                     $("#switch_razorpay_gateway").is(':checked') ? $("#razorpay_gateway").val(1) : $("#razorpay_gateway") .val(0);
        //                     $("#switch_paystack_gateway").is(':checked') ? $("#paystack_gateway").val(1) : $("#paystack_gateway") .val(0);
        //                     $("#switch_stripe_gateway").is(':checked') ? $("#stripe_gateway").val(1) : $("#stripe_gateway") .val(0);
        //                     $("#switch_flutterwave_status").is(':checked') ? $("#flutterwave_status").val(1) : $("#flutterwave_status") .val(0);
        //                 }
        //             });
        //         }
        //     });
        // });


        $("#switch_svg_clr").on('change', function() {
            $("#switch_svg_clr").is(':checked') ? $("#svg_clr").val(1) : $("#svg_clr").val(0);
        });

        $("#switch-homepage-location-alert").on('change', function() {
            $("#switch-homepage-location-alert").is(':checked') ? $("#homepage-location-alert-status").val(1) : $(
                "#homepage-location-alert-status").val(0);
        });


        $("#switch_force_update").on('change', function() {
            $("#switch_force_update").is(':checked') ? $("#force_update").val(1) : $("#force_update").val(0);
        });

        $("#switch_number_with_suffix").on('change', function() {
            $("#switch_number_with_suffix").is(':checked') ? $("#number_with_suffix").val(1) : $(
                "#number_with_suffix").val(0);
        });

        // Change Event on OTP login Toggle
        $("#number-with-otp-login-toggle").on('change', function() {
            if ($("#number-with-otp-login-toggle").is(':checked')) {
                // If number with otp login is checked then make database value 1, show the otp services provider div, and trigger select option
                $("#number-with-otp-login").val(1);
                $("#otp-services-provider-div").show(100);
                $("#otp-services-provider").trigger('change');
            } else {
                // make database value 0, hide services provider div hide
                $("#number-with-otp-login").val(0);
                $("#otp-services-provider-div").hide();
                $("#twilio-sms-settings-div").hide()
                $(".twilio-account-settings").removeAttr('required');
            }

        });

        // Change event on OTP services provider selection
        $("#otp-services-provider").on('change', function() {
            // Get the value of selection
            let otpServicesProviderValue = $(this).val();
            if (otpServicesProviderValue == 'twilio') {
                // IF Twilio then show the div of twilio sms settings with all the details required attribute
                $("#twilio-sms-settings-div").show();
                $(".twilio-account-settings").attr('required', true);
            } else {
                // IF other then hide the div of twilio sms settings and remove required attribute in all twilio settings
                $(".twilio-account-settings").removeAttr('required');
                $("#twilio-sms-settings-div").hide();
            }
        })

        $("#social-login-toggle").on('change', function() {
            $("#social-login-toggle").is(':checked') ? $("#social-login").val(1) : $("#social-login").val(0);
        });

        $("#email-password-login-toggle").on('change', function() {
            $("#email-password-login-toggle").is(':checked') ? $("#email-password-login").val(1) : $(
                "#email-password-login").val(0);
        });

        $("#switch_auto_approve_edited_listings").on('change', function() {
            $("#switch_auto_approve_edited_listings").is(':checked') ? $("#auto_approve_edited_listings").val(1) :
                $("#auto_approve_edited_listings").val(0);
        });

        $("#switch_notify_user_for_subscription_expiry").on('change', function() {
            $("#switch_notify_user_for_subscription_expiry").is(':checked') ? $(
                "#notify_user_for_subscription_expiry").val(1) : $("#notify_user_for_subscription_expiry").val(
                0);
        });

        $("#switch_show_direct_video_upload").on('change', function() {
            $("#switch_show_direct_video_upload").is(':checked') ? $("#show_direct_video_upload").val(1) : $(
                "#show_direct_video_upload").val(0);
        });

        $("#switch_show_whatsapp_button").on('change', function() {
            $("#switch_show_whatsapp_button").is(':checked') ? $("#show_whatsapp_button").val(1) : $(
                "#show_whatsapp_button").val(0);
        });

        $("#switch_audit_log_enabled").on('change', function() {
            $("#switch_audit_log_enabled").is(':checked') ? $("#audit_log_enabled").val(1) : $(
                "#audit_log_enabled").val(0);
        });

        $("#switch_show_premium_toggle").on('change', function() {
            $("#switch_show_premium_toggle").is(':checked') ? $("#show_premium_toggle").val(1) : $(
                "#show_premium_toggle").val(0);
        });

        $("#switch_show_exact_location").on('change', function() {
            $("#switch_show_exact_location").is(':checked') ? $("#show_exact_location").val(1) : $(
                "#show_exact_location").val(0);
        });

        function hexToRgb(hex) {
            const bigint = parseInt(hex.slice(1), 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return `rgb(${r}, ${g}, ${b},0.15)`;
        }


        const colorForm = document.getElementById("setting_form");
        const systemColorInput = document.getElementById("systemColor");

        const hiddenRGBAInput = document.getElementById("hiddenRGBA");


        systemColorInput.addEventListener("change", function() {
            const selectedColor = systemColorInput.value;
            const alpha = 0.15; // You can adjust the alpha value as needed (1 for fully opaque)
            const rgba = hexToRgb(selectedColor);
            hiddenRGBAInput.value = rgba; // Update the hidden input with the new RGBA value
        });



        $(document).ready(function() {
            var companyname = $('#company_name').val();
            sessionStorage.setItem('comapanyname', $('#company_name').val());
            const newValue = `"${companyname}"`;
            const rgba = hexToRgb(systemColorInput.value);
            hiddenRGBAInput.value = rgba;

            // Initialize login toggles from hidden inputs
            function initLoginTogglesFromHidden() {
                $("#email-password-login-toggle").prop('checked', $("#email-password-login").val() == '1');
                $("#social-login-toggle").prop('checked', $("#social-login").val() == '1');
                $("#number-with-otp-login-toggle").prop('checked', $("#number-with-otp-login").val() == '1');
            }

            // Ensure at least one login method remains enabled
            function enforceAtLeastOneLogin() {
                var emailOn = $("#email-password-login-toggle").is(':checked');
                var socialOn = $("#social-login-toggle").is(':checked');
                var otpOn = $("#number-with-otp-login-toggle").is(':checked');

                if (!emailOn && !socialOn && !otpOn) {
                    // Priority: Email > Social > OTP
                    $("#email-password-login-toggle").prop('checked', true);
                    $("#email-password-login").val(1);
                    // Trigger change on OTP toggle to ensure related UI updates when it changes later
                }
            }

            // Bind change to login methods with enforcement
            $("#number-with-otp-login-toggle, #social-login-toggle, #email-password-login-toggle").on('change',
                function() {
                    enforceAtLeastOneLogin();
                });

            // Run initializations
            initLoginTogglesFromHidden();
            $("#number-with-otp-login-toggle").trigger('change');

            // Map service provider selection change event
            $("#map-service-provider").on('change', function() {
                let mapServiceProviderValue = $(this).val();
                if (mapServiceProviderValue == 'open_street_maps') {
                    $("#geonames-username-div").show();
                    $("#open-street-maps-note").show();
                    $("#map-api-key").closest('.form-group').hide();
                    $("#place-api-key").closest('.form-group').hide();
                } else {
                    $("#geonames-username-div").hide();
                    $("#open-street-maps-note").hide();
                    $("#map-api-key").closest('.form-group').show();
                    $("#place-api-key").closest('.form-group').show();
                }
            });
            // Apply the correct visibility on initial page load
            $("#map-service-provider").trigger('change');

            // Clear only the Google Maps cache store
            $(document).on('click', '#clear-gmaps-cache', function(e) {
                const button = $(this);
                const originalHtml = button.html();
                button.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' +
                    '{{ __('Clearing...') }}');

                $.ajax({
                    url: '{{ route('cache.gmaps.clear') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {
                        button.html('{{ __('Cleared') }}');
                        setTimeout(function() {
                            button.prop('disabled', false).html(originalHtml);
                        }, 1200);
                    },
                    error: function() {
                        alert('{{ __('Failed to clear Google Maps cache') }}');
                        button.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // Clear only the Open Street Maps cache store
            $(document).on('click', '#clear-open-street-maps-cache', function(e) {
                const button = $(this);
                const originalHtml = button.html();
                button.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' +
                    '{{ __('Clearing...') }}');

                $.ajax({
                    url: '{{ route('cache.osm.clear') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {
                        button.html('{{ __('Cleared') }}');
                        setTimeout(function() {
                            button.prop('disabled', false).html(originalHtml);
                        }, 1200);
                    },
                    error: function() {
                        alert('{{ __('Failed to clear Open Street Maps cache') }}');
                        button.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });

        $('.fav_icon_btn').click(function() {
            $('#fav_image').click();


        });
        fav_image.onchange = evt => {
            const [file] = fav_image.files
            if (file) {
                blah_fav.src = URL.createObjectURL(file)

            }
        }
        $('.btn_comapany_logo').click(function() {
            $('#company_logo').click();


        });
        company_logo.onchange = evt => {
            const [file] = company_logo.files
            if (file) {
                blah_comapany_logo.src = URL.createObjectURL(file)

            }
        }



        $('.btn_login_image').click(function() {
            $('#login_image').click();


        });
        login_image.onchange = evt => {
            const [file] = login_image.files
            if (file) {
                blah_login_image.src = URL.createObjectURL(file)

            }
        }

        function formSuccessFunction() {
            window.location.reload();
        }
    </script>
@endsection
