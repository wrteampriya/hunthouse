@extends('layouts.main')

@section('title')
    {{ __('Payment Settings') }}
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
        {!! Form::open(['route' => 'store-payment-gateway-settings', 'data-parsley-validate', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction",'enctype' => 'multipart/form-data']) !!}

            {{-- Paypal Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-paypal" name="paypal"></a>
                    {{-- Paypal Settings --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Paypal Setting') }}</h6>
                    </div>
                    <div class="form-group row">

                        {{-- Paypal Client ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paypal Client ID') }}</label>
                            <input name="paypal_client_id" type="text" class="form-control" placeholder="{{ __('Paypal Client ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paypal_client_id']) && $paymentSettings['paypal_client_id'] != '' ? $paymentSettings['paypal_client_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['paypal_client_id']) && $paymentSettings['paypal_client_id'] != '' ? $paymentSettings['paypal_client_id'] : '' ))}}">
                        </div>

                        {{-- Paypal Client Secret --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paypal Client Secret') }}</label>
                            <input name="paypal_client_secret" type="text" class="form-control" placeholder="{{ __('Paypal Client Secret') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paypal_client_secret']) && $paymentSettings['paypal_client_secret'] != '' ? $paymentSettings['paypal_client_secret'] : '' ) : '****************************' ) : ( isset($paymentSettings['paypal_client_secret']) && $paymentSettings['paypal_client_secret'] != '' ? $paymentSettings['paypal_client_secret'] : '' ))}}">
                        </div>

                        {{-- Paypal Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paypal Webhook URL') }}</label>
                            <input name="paypal_webhook_url" type="text" class="form-control" placeholder="{{ __('Paypal Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paypal_webhook_url']) && $paymentSettings['paypal_webhook_url'] != '' ? $paymentSettings['paypal_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['paypal_webhook_url']) && $paymentSettings['paypal_webhook_url'] != '' ? $paymentSettings['paypal_webhook_url'] : url('/webhook/paypal') ))}}" readonly>
                        </div>

                        {{-- Paypal Webhook ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paypal Webhook ID') }}</label>
                            <input name="paypal_webhook_id" type="text" class="form-control" placeholder="{{ __('Paypal Webhook ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paypal_webhook_id']) && $paymentSettings['paypal_webhook_id'] != '' ? $paymentSettings['paypal_webhook_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['paypal_webhook_id']) && $paymentSettings['paypal_webhook_id'] != '' ? $paymentSettings['paypal_webhook_id'] : '' ))}}">
                        </div>

                        {{-- Paypal Currency Symbol --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Paypal Currency Symbol') }}</label>
                            <select name="paypal_currency" id="paypal_currency" class="choosen-select form-select form-control-sm">
                                @foreach ($paypalCurrencies as $key => $value)
                                    <option value={{ $key }} {{ isset($paymentSettings['paypal_currency']) && $paymentSettings['paypal_currency'] == $key ? 'selected' : '' }}> {{ $key }} - {{ $value }}</option>
                                @endforeach
                            </select>
                        </div>


                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_paypal'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="paypal_gateway" id="paypal_gateway" value="{{ isset($paymentSettings['paypal_gateway']) && $paymentSettings['paypal_gateway'] != '' ? $paymentSettings['paypal_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['paypal_gateway']) && $paymentSettings['paypal_gateway'] == '1' ? 'checked' : '' }} id="switch_paypal_gateway">
                                    <label class="form-check-label" for="switch_paypal_gateway"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Sandbox Mode') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="sandbox_mode" id="sandbox_mode" value="{{ isset($paymentSettings['sandbox_mode']) && $paymentSettings['sandbox_mode'] != '' ? $paymentSettings['sandbox_mode'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ isset($paymentSettings['sandbox_mode']) && $paymentSettings['sandbox_mode'] == '1' ? 'checked' : '' }} id="switch_sandbox_mode">
                                    <label class="form-check-label" for="switch_sandbox_mode"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Razorpay Settings --}}
            <div class="card">
                <div class="card-body">

                    <a id="search-anchor-razorpay" name="razorpay"></a>
                    {{-- Razorpay Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Razorpay Setting') }}</h6>
                    </div>

                    <div class="form-group row">

                        {{-- Razorpay key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Razorpay key') }}</label>
                            <input name="razor_key" type="text" class="form-control" placeholder="{{ __('Razorpay Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['razor_key']) && $paymentSettings['razor_key'] != '' ? $paymentSettings['razor_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['razor_key']) && $paymentSettings['razor_key'] != '' ? $paymentSettings['razor_key'] : '' ))}}">
                        </div>

                        {{-- Razorpay Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Razorpay Webhook URL') }}</label>
                            <input name="razorpay_webhook_url" type="text" class="form-control" placeholder="{{ __('Razorpay Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['razorpay_webhook_url']) && $paymentSettings['razorpay_webhook_url'] != '' ? $paymentSettings['razorpay_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['razorpay_webhook_url']) && $paymentSettings['razorpay_webhook_url'] != '' ? $paymentSettings['razorpay_webhook_url'] : url('/webhook/razorpay') ))}}" readonly>
                        </div>

                        {{-- Razorpay Secret --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Razorpay Secret') }}</label>
                            <input name="razor_secret" type="text" class="form-control" placeholder="{{ __('Razorpay Secret') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['razor_secret']) && $paymentSettings['razor_secret'] != '' ? $paymentSettings['razor_secret'] : '' ) : '****************************' ) : ( isset($paymentSettings['razor_secret']) && $paymentSettings['razor_secret'] != '' ? $paymentSettings['razor_secret'] : '' ))}}">
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_razorpay'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="razorpay_gateway" id="razorpay_gateway" value="{{ isset($paymentSettings['razorpay_gateway']) && $paymentSettings['razorpay_gateway'] != '' ? $paymentSettings['razorpay_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['razorpay_gateway']) && $paymentSettings['razorpay_gateway'] == '1' ? 'checked' : '' }} id="switch_razorpay_gateway">
                                    <label class="form-check-label" for="switch_razorpay_gateway"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Razorpay Webhook Secret --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Razorpay Webhook Secret') }}</label>
                            <input name="razor_webhook_secret" type="text" class="form-control" placeholder="{{ __('Razorpay Webhook Secret') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['razor_webhook_secret']) && $paymentSettings['razor_webhook_secret'] != '' ? $paymentSettings['razor_webhook_secret'] : '' ) : '****************************' ) : ( isset($paymentSettings['razor_webhook_secret']) && $paymentSettings['razor_webhook_secret'] != '' ? $paymentSettings['razor_webhook_secret'] : '' ))}}">
                        </div>

                    </div>
                </div>
            </div>

            {{-- Paystack Settings --}}
            <div class="card">
                <div class="card-body">

                    <a id="search-anchor-paystack" name="paystack"></a>
                    {{-- Paystack Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Paystack Setting') }}</h6>
                    </div>

                    <div class="form-group row">

                        {{-- Paystack Secret key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paystack Secret key') }}</label>
                            <input name="paystack_secret_key" type="text" class="form-control" placeholder="{{ __('Paystack Secret Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paystack_secret_key']) && $paymentSettings['paystack_secret_key'] != '' ? $paymentSettings['paystack_secret_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['paystack_secret_key']) && $paymentSettings['paystack_secret_key'] != '' ? $paymentSettings['paystack_secret_key'] : '' ))}}">
                        </div>

                        {{-- Paystack Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paystack Webhook URL') }}</label>
                            <input name="paystack_webhook_url" type="text" class="form-control" placeholder="{{ __('Paystack Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paystack_webhook_url']) && $paymentSettings['paystack_webhook_url'] != '' ? $paymentSettings['paystack_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['paystack_webhook_url']) && $paymentSettings['paystack_webhook_url'] != '' ? $paymentSettings['paystack_webhook_url'] : url('/webhook/paystack') ))}}" readonly>
                        </div>

                        {{-- Paystack Currency Symbol --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paystack Currency Symbol') }}</label>
                            <select name="paystack_currency" id="paystack_currency" class="choosen-select form-select form-control-sm">
                                <option value="GHS" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'GHS' ? 'selected' : '' }}> GHS - Ghanaian Cedi</option>
                                <option value="KES" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'KES' ? 'selected' : '' }}> KES - Kenyan Shilling</option>
                                <option value="NGN" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'NGN' ? 'selected' : '' }}> NGN - Nigerian Naira</option>
                                <option value="USD" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'USD' ? 'selected' : '' }}> USD - United States Dollar</option>
                                <option value="XOF" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'XOF' ? 'selected' : '' }}> XOF - West African CFA Franc</option>
                                <option value="ZAR" {{ isset($paymentSettings['paystack_currency']) && $paymentSettings['paystack_currency'] == 'ZAR' ? 'selected' : '' }}> ZAR - South African Rand</option>
                            </select>
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_paystack'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="paystack_gateway" id="paystack_gateway" value="{{ isset($paymentSettings['paystack_gateway']) && $paymentSettings['paystack_gateway'] != '' ? $paymentSettings['paystack_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['paystack_gateway']) && $paymentSettings['paystack_gateway'] == '1' ? 'checked' : '' }} id="switch_paystack_gateway">
                                </div>
                            </div>
                        </div>

                        {{-- Paystack Public key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Paystack Public key') }}</label>
                            <input name="paystack_public_key" type="text" class="form-control" placeholder="{{ __('Paystack Public Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['paystack_public_key']) && $paymentSettings['paystack_public_key'] != '' ? $paymentSettings['paystack_public_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['paystack_public_key']) && $paymentSettings['paystack_public_key'] != '' ? $paymentSettings['paystack_public_key'] : '' ))}}">
                        </div>

                    </div>
                </div>
            </div>

            {{-- Stripe Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-stripe" name="stripe"></a>
                    {{-- Stripe Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Stripe Setting') }}</h6>
                    </div>

                    <div class="form-group row">
                        {{-- Stripe publishable key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Stripe publishable key') }}</label>
                            <input name="stripe_publishable_key" type="text" class="form-control" placeholder="{{ __('Stripe publishable key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['stripe_publishable_key']) && $paymentSettings['stripe_publishable_key'] != '' ? $paymentSettings['stripe_publishable_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['stripe_publishable_key']) && $paymentSettings['stripe_publishable_key'] != '' ? $paymentSettings['stripe_publishable_key'] : '' ))}}">
                        </div>

                        {{-- Stripe Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Stripe Webhook URL') }}</label>
                            <input name="stripe_webhook_url" type="text" class="form-control" placeholder="{{ __('Stripe Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['stripe_webhook_url']) && $paymentSettings['stripe_webhook_url'] != '' ? $paymentSettings['stripe_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['stripe_webhook_url']) && $paymentSettings['stripe_webhook_url'] != '' ? $paymentSettings['stripe_webhook_url'] : url('/webhook/stripe') ))}}" readonly>
                        </div>

                        {{-- Stripe Currency Symbol --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Stripe Currency Symbol') }}</label>
                            <select name="stripe_currency" id="stripe_currency" class="choosen-select form-select form-control-sm">
                                @foreach ($stripe_currencies as $value)
                                <option value={{ $value }}
                                {{ isset($paymentSettings['stripe_currency']) && $paymentSettings['stripe_currency'] == $value ? 'selected' : '' }}>
                                {{ $value }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_stripe'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch ">
                                    <input type="hidden" name="stripe_gateway" id="stripe_gateway" value="{{ isset($paymentSettings['stripe_gateway']) && $paymentSettings['stripe_gateway'] != '' ? $paymentSettings['stripe_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['stripe_gateway']) && $paymentSettings['stripe_gateway'] == '1' ? 'checked' : '' }} id="switch_stripe_gateway">
                                </div>
                            </div>
                        </div>

                        {{-- Stripe Secret key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label-mandatory">{{ __('Stripe Secret key') }}</label>
                            <input name="stripe_secret_key" type="text" class="form-control" placeholder="{{ __('Stripe Secret key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['stripe_secret_key']) && $paymentSettings['stripe_secret_key'] != '' ? $paymentSettings['stripe_secret_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['stripe_secret_key']) && $paymentSettings['stripe_secret_key'] != '' ? $paymentSettings['stripe_secret_key'] : '' ))}}">
                        </div>

                        {{-- Stripe Webhook Secret key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label-mandatory">{{ __('Stripe Webhook Secret key') }}</label>
                            <input name="stripe_webhook_secret_key" type="text" class="form-control" placeholder="{{ __('Stripe Webhook Secret key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['stripe_webhook_secret_key']) && $paymentSettings['stripe_webhook_secret_key'] != '' ? $paymentSettings['stripe_webhook_secret_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['stripe_webhook_secret_key']) && $paymentSettings['stripe_webhook_secret_key'] != '' ? $paymentSettings['stripe_webhook_secret_key'] : '' ))}}">
                        </div>

                    </div>
                </div>
            </div>

            {{-- Flutterwave Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-flutterwave" name="flutterwave"></a>
                    {{-- Flutterwave Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Flutterwave Setting') }}</h6>
                    </div>
                    <div class="form-group row">

                        {{-- Public Key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Public Key') }}</label>
                            <input name="flutterwave_public_key" type="text" class="form-control" placeholder="{{ __('Public Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['flutterwave_public_key']) && $paymentSettings['flutterwave_public_key'] != '' ? $paymentSettings['flutterwave_public_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['flutterwave_public_key']) && $paymentSettings['flutterwave_public_key'] != '' ? $paymentSettings['flutterwave_public_key'] : '' ))}}">
                        </div>

                        {{-- Secret Key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Secret Key') }}</label>
                            <input name="flutterwave_secret_key" type="text" class="form-control" placeholder="{{ __('Secret Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['flutterwave_secret_key']) && $paymentSettings['flutterwave_secret_key'] != '' ? $paymentSettings['flutterwave_secret_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['flutterwave_secret_key']) && $paymentSettings['flutterwave_secret_key'] != '' ? $paymentSettings['flutterwave_secret_key'] : '' ))}}">
                        </div>

                        {{-- Encryption key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Encryption Key') }}</label>
                            <input name="flutterwave_encryption_key" type="text" class="form-control" placeholder="{{ __('Encryption Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['flutterwave_encryption_key']) && $paymentSettings['flutterwave_encryption_key'] != '' ? $paymentSettings['flutterwave_encryption_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['flutterwave_encryption_key']) && $paymentSettings['flutterwave_encryption_key'] != '' ? $paymentSettings['flutterwave_encryption_key'] : '' ))}}">
                        </div>

                        {{-- Flutterwave Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Flutterwave Webhook URL') }}</label>
                                <input name="flutterwave_webhook_url" type="text" class="form-control" placeholder="{{ __('Flutterwave Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['flutterwave_webhook_url']) && $paymentSettings['flutterwave_webhook_url'] != '' ? $paymentSettings['flutterwave_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['flutterwave_webhook_url']) && $paymentSettings['flutterwave_webhook_url'] != '' ? $paymentSettings['flutterwave_webhook_url'] : url('/webhook/flutterwave') ))}}" readonly>
                        </div>

                        {{-- Flutterwave Currency Symbol --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Flutterwave Currency Symbol') }}</label>
                            <select name="flutterwave_currency" id="flutterwave_currency" class="choosen-select form-select form-control-sm">
                                <option value="GBP" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'GBP' ? 'selected' : '' }}> British Pound Sterling (GBP)</option>
                                <option value="CAD" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'CAD' ? 'selected' : '' }}> Canadian Dollar (CAD)</option>
                                <option value="XAF" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'XAF' ? 'selected' : '' }}> Central African CFA Franc (XAF)</option>
                                <option value="CLP" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'CLP' ? 'selected' : '' }}> Chilean Peso (CLP)</option>
                                <option value="COP" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'COP' ? 'selected' : '' }}> Colombian Peso (COP)</option>
                                <option value="EGP" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'EGP' ? 'selected' : '' }}> Egyptian Pound (EGP)</option>
                                <option value="EUR" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'EUR' ? 'selected' : '' }}> SEPA (EUR)</option>
                                <option value="GHS" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'GHS' ? 'selected' : '' }}> Ghanaian Cedi (GHS)</option>
                                <option value="GNF" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'GNF' ? 'selected' : '' }}> Guinean Franc (GNF)</option>
                                <option value="KES" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'KES' ? 'selected' : '' }}> Kenyan Shilling (KES)</option>
                                <option value="MWK" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'MWK' ? 'selected' : '' }}> Malawian Kwacha (MWK)</option>
                                <option value="MAD" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'MAD' ? 'selected' : '' }}> Moroccan Dirham (MAD)</option>
                                <option value="NGN" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'NGN' ? 'selected' : '' }}> Nigerian Naira (NGN)</option>
                                <option value="RWF" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'RWF' ? 'selected' : '' }}> Rwandan Franc (RWF)</option>
                                <option value="SLL" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'SLL' ? 'selected' : '' }}> Sierra Leonean Leone (SLL)</option>
                                <option value="STD" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'STD' ? 'selected' : '' }}> São Tomé and Príncipe dobra (STD)</option>
                                <option value="ZAR" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'ZAR' ? 'selected' : '' }}> South African Rand (ZAR)</option>
                                <option value="TZS" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'TZS' ? 'selected' : '' }}> Tanzanian Shilling (TZS)</option>
                                <option value="UGX" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'UGX' ? 'selected' : '' }}> Ugandan Shilling (UGX)</option>
                                <option value="USD" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'USD' ? 'selected' : '' }}> United States Dollar (USD)</option>
                                <option value="XOF" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'XOF' ? 'selected' : '' }}> West African CFA Franc BCEAO (XOF)</option>
                                <option value="ZMW" {{ isset($paymentSettings['flutterwave_currency']) && $paymentSettings['flutterwave_currency'] == 'ZMW' ? 'selected' : '' }}> Zambian Kwacha (ZMW)</option>
                            </select>
                        </div>


                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_flutterwave'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="flutterwave_status" id="flutterwave_status" value="{{ isset($paymentSettings['flutterwave_status']) && $paymentSettings['flutterwave_status'] != '' ? $paymentSettings['flutterwave_status'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['flutterwave_status']) && $paymentSettings['flutterwave_status'] == '1' ? 'checked' : '' }} id="switch_flutterwave_status">
                                    <label class="form-check-label" for="switch_flutterwave_status"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cashfree Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-cashfree" name="cashfree"></a>
                    {{-- Cashfree Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Cashfree Setting') }}</h6>
                    </div>
                    <div class="form-group row">

                        {{-- Cashfree App ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Cashfree App ID') }}</label>
                            <input name="cashfree_app_id" type="text" class="form-control" placeholder="{{ __('Cashfree App ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['cashfree_app_id']) && $paymentSettings['cashfree_app_id'] != '' ? $paymentSettings['cashfree_app_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['cashfree_app_id']) && $paymentSettings['cashfree_app_id'] != '' ? $paymentSettings['cashfree_app_id'] : '' ))}}">
                        </div>

                        {{-- Cashfree Secret Key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Cashfree Secret Key') }}</label>
                            <input name="cashfree_secret_key" type="text" class="form-control" placeholder="{{ __('Cashfree Secret Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['cashfree_secret_key']) && $paymentSettings['cashfree_secret_key'] != '' ? $paymentSettings['cashfree_secret_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['cashfree_secret_key']) && $paymentSettings['cashfree_secret_key'] != '' ? $paymentSettings['cashfree_secret_key'] : '' ))}}">
                        </div>

                        {{-- Cashfree Currency Symbol --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Cashfree Currency Symbol') }}</label>
                            <select name="cashfree_currency" id="cashfree_currency" class="choosen-select form-select form-control-sm">
                                @foreach ($cashfreeCurrencies as $key => $value)
                                    <option value={{ $key }} {{ isset($paymentSettings['cashfree_currency']) && $paymentSettings['cashfree_currency'] == $key ? 'selected' : '' }}> {{ $key.' - '.$value['name'].' ('.$value['symbol'].')' }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_cashfree'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="cashfree_gateway" id="cashfree_gateway" value="{{ isset($paymentSettings['cashfree_gateway']) && $paymentSettings['cashfree_gateway'] != '' ? $paymentSettings['cashfree_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['cashfree_gateway']) && $paymentSettings['cashfree_gateway'] == '1' ? 'checked' : '' }} id="switch_cashfree_gateway">
                                    <label class="form-check-label" for="switch_cashfree_gateway"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Sandbox Mode') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="cashfree_sandbox_mode" id="cashfree_sandbox_mode" value="{{ isset($paymentSettings['cashfree_sandbox_mode']) && $paymentSettings['cashfree_sandbox_mode'] != '' ? $paymentSettings['cashfree_sandbox_mode'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ isset($paymentSettings['cashfree_sandbox_mode']) && $paymentSettings['cashfree_sandbox_mode'] == '1' ? 'checked' : '' }} id="switch_cashfree_sandbox_mode">
                                    <label class="form-check-label" for="switch_cashfree_sandbox_mode"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PhonePe Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-phonepe" name="phonepe"></a>
                    {{-- PhonePe Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('PhonePe Setting') }}</h6>
                    </div>
                    <div class="form-group row">

                        {{-- PhonePe Client ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('PhonePe Client ID') }}</label>
                            <input name="phonepe_client_id" type="text" class="form-control" placeholder="{{ __('PhonePe Client ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_client_id']) && $paymentSettings['phonepe_client_id'] != '' ? $paymentSettings['phonepe_client_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_client_id']) && $paymentSettings['phonepe_client_id'] != '' ? $paymentSettings['phonepe_client_id'] : '' ))}}">
                        </div>

                        {{-- PhonePe Client Secret --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('PhonePe Client Secret') }}</label>
                            <input name="phonepe_client_secret" type="text" class="form-control" placeholder="{{ __('PhonePe Client Secret') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_client_secret']) && $paymentSettings['phonepe_client_secret'] != '' ? $paymentSettings['phonepe_client_secret'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_client_secret']) && $paymentSettings['phonepe_client_secret'] != '' ? $paymentSettings['phonepe_client_secret'] : '' ))}}">
                        </div>

                        {{-- Client Version --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Client Version') }}</label>
                            <input name="phonepe_client_version" type="text" class="form-control" placeholder="{{ __('Client Version') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_client_version']) && $paymentSettings['phonepe_client_version'] != '' ? $paymentSettings['phonepe_client_version'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_client_version']) && $paymentSettings['phonepe_client_version'] != '' ? $paymentSettings['phonepe_client_version'] : '' ))}}">
                        </div>

                        {{-- Merchant ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Merchant ID') }}</label>
                            <input name="phonepe_merchant_id" type="text" class="form-control" placeholder="{{ __('Merchant ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_merchant_id']) && $paymentSettings['phonepe_merchant_id'] != '' ? $paymentSettings['phonepe_merchant_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_merchant_id']) && $paymentSettings['phonepe_merchant_id'] != '' ? $paymentSettings['phonepe_merchant_id'] : '' ))}}">
                        </div>

                        {{-- PhonePe Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('PhonePe Webhook URL') }}</label>
                            <input name="phonepe_webhook_url" type="text" class="form-control" placeholder="{{ __('PhonePe Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_webhook_url']) && $paymentSettings['phonepe_webhook_url'] != '' ? $paymentSettings['phonepe_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_webhook_url']) && $paymentSettings['phonepe_webhook_url'] != '' ? $paymentSettings['phonepe_webhook_url'] : url('/webhook/phonepe') ))}}" readonly>
                        </div>

                        {{-- PhonePe Webhook Username --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('PhonePe Webhook Username') }}</label>
                            <input name="phonepe_webhook_username" type="text" class="form-control" placeholder="{{ __('PhonePe Webhook Username') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_webhook_username']) && $paymentSettings['phonepe_webhook_username'] != '' ? $paymentSettings['phonepe_webhook_username'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_webhook_username']) && $paymentSettings['phonepe_webhook_username'] != '' ? $paymentSettings['phonepe_webhook_username'] : '' ))}}">
                        </div>

                        {{-- PhonePe Webhook Password --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('PhonePe Webhook Password') }}</label>
                            <input name="phonepe_webhook_password" type="text" class="form-control" placeholder="{{ __('PhonePe Webhook Password') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['phonepe_webhook_password']) && $paymentSettings['phonepe_webhook_password'] != '' ? $paymentSettings['phonepe_webhook_password'] : '' ) : '****************************' ) : ( isset($paymentSettings['phonepe_webhook_password']) && $paymentSettings['phonepe_webhook_password'] != '' ? $paymentSettings['phonepe_webhook_password'] : '' ))}}">
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_phonepe'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="phonepe_gateway" id="phonepe_gateway" value="{{ isset($paymentSettings['phonepe_gateway']) && $paymentSettings['phonepe_gateway'] != '' ? $paymentSettings['phonepe_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['phonepe_gateway']) && $paymentSettings['phonepe_gateway'] == '1' ? 'checked' : '' }} id="switch_phonepe_gateway">
                                    <label class="form-check-label" for="switch_phonepe_gateway"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Sandbox Mode') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="phonepe_sandbox_mode" id="phonepe_sandbox_mode" value="{{ isset($paymentSettings['phonepe_sandbox_mode']) && $paymentSettings['phonepe_sandbox_mode'] != '' ? $paymentSettings['phonepe_sandbox_mode'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ isset($paymentSettings['phonepe_sandbox_mode']) && $paymentSettings['phonepe_sandbox_mode'] == '1' ? 'checked' : '' }} id="switch_phonepe_sandbox_mode">
                                    <label class="form-check-label" for="switch_phonepe_sandbox_mode"></label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Midtrans Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-midtrans" name="midtrans"></a>
                    {{-- Midtrans Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Midtrans Setting') }}</h6>
                    </div>
                    <div class="form-group row">

                        {{-- Midtrans Server Key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Midtrans Server Key') }}</label>
                            <input name="midtrans_server_key" type="text" class="form-control" placeholder="{{ __('Midtrans Server Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['midtrans_server_key']) && $paymentSettings['midtrans_server_key'] != '' ? $paymentSettings['midtrans_server_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['midtrans_server_key']) && $paymentSettings['midtrans_server_key'] != '' ? $paymentSettings['midtrans_server_key'] : '' ))}}">
                        </div>

                        {{-- Midtrans Client Key --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Midtrans Client Key') }}</label>
                            <input name="midtrans_client_key" type="text" class="form-control" placeholder="{{ __('Midtrans Client Key') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['midtrans_client_key']) && $paymentSettings['midtrans_client_key'] != '' ? $paymentSettings['midtrans_client_key'] : '' ) : '****************************' ) : ( isset($paymentSettings['midtrans_client_key']) && $paymentSettings['midtrans_client_key'] != '' ? $paymentSettings['midtrans_client_key'] : '' ))}}">
                        </div>

                        {{-- Midtrans Merchant ID --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Midtrans Merchant ID') }}</label>
                            <input name="midtrans_merchant_id" type="text" class="form-control" placeholder="{{ __('Midtrans Merchant ID') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['midtrans_merchant_id']) && $paymentSettings['midtrans_merchant_id'] != '' ? $paymentSettings['midtrans_merchant_id'] : '' ) : '****************************' ) : ( isset($paymentSettings['midtrans_merchant_id']) && $paymentSettings['midtrans_merchant_id'] != '' ? $paymentSettings['midtrans_merchant_id'] : '' ))}}">
                        </div>

                        {{-- Midtrans Webhook URL --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-label">{{ __('Midtrans Webhook URL') }}</label>
                            <input name="midtrans_webhook_url" type="text" class="form-control" placeholder="{{ __('Midtrans Webhook URL') }}" value="{{ (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( isset($paymentSettings['midtrans_webhook_url']) && $paymentSettings['midtrans_webhook_url'] != '' ? $paymentSettings['midtrans_webhook_url'] : '' ) : '****************************' ) : ( isset($paymentSettings['midtrans_webhook_url']) && $paymentSettings['midtrans_webhook_url'] != '' ? $paymentSettings['midtrans_webhook_url'] : url('/webhook/midtrans') ))}}" readonly>
                        </div>

                        {{-- Status --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label" id='lbl_midtrans'>{{ __('Enable') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="midtrans_gateway" id="midtrans_gateway" value="{{ isset($paymentSettings['midtrans_gateway']) && $paymentSettings['midtrans_gateway'] != '' ? $paymentSettings['midtrans_gateway'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" class="switch-input" name='op' {{ isset($paymentSettings['midtrans_gateway']) && $paymentSettings['midtrans_gateway'] == '1' ? 'checked' : '' }} id="switch_midtrans_gateway">
                                    <label class="form-check-label" for="switch_midtrans_gateway"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Sandbox Mode') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="midtrans_sandbox_mode" id="midtrans_sandbox_mode" value="{{ isset($paymentSettings['midtrans_sandbox_mode']) && $paymentSettings['midtrans_sandbox_mode'] != '' ? $paymentSettings['midtrans_sandbox_mode'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ isset($paymentSettings['midtrans_sandbox_mode']) && $paymentSettings['midtrans_sandbox_mode'] == '1' ? 'checked' : '' }} id="switch_midtrans_sandbox_mode">
                                    <label class="form-check-label" for="switch_midtrans_sandbox_mode"></label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Bank Details Settings --}}
            <div class="card">
                <div class="card-body">
                    <a id="search-anchor-bank-details" name="bank-details"></a>
                    {{-- Bank Details Setting --}}
                    <div class="divider pt-3 mt-3">
                        <h6 class="divider-text">{{ __('Bank Details Setting') }}</h6>
                    </div>
                    <div class="form-group row">
                        {{-- Enable Bank Details --}}
                        <div class="col-sm-12 col-md-6 mt-2">
                            <label class="form-check-label">{{ __('Enable Bank Details') }}</label>
                            <div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="bank_transfer_status" id="bank_details_enabled" value="{{ isset($paymentSettings['bank_transfer_status']) && $paymentSettings['bank_transfer_status'] != '' ? $paymentSettings['bank_transfer_status'] : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ isset($paymentSettings['bank_transfer_status']) && $paymentSettings['bank_transfer_status'] == '1' ? 'checked' : '' }} id="switch_bank_details_enabled">
                                </div>
                            </div>
                        </div>

                        {{-- Bank Details Fields --}}
                        <div class="col-12 mt-3 bank-details-fields">
                            <label class="form-label">{{ __('Bank Details Fields') }}</label>
                            <div class="bank-details-repeater">
                                <div data-repeater-list="bank_details_fields">

                                    <div data-repeater-item>

                                        <div class="row mb-2 bg-light p-2">
                                            {{-- Delete --}}
                                            <div class="col-12 d-flex justify-content-end">
                                                <button type="button" class="btn btn-danger" data-repeater-delete>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>

                                            {{-- Title --}}
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">{{ __('Title') }}</label>
                                                <input type="text" class="form-control" name="title" placeholder="{{ __('Title') }}">
                                            </div>

                                            {{-- Value --}}
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">{{ __('Value') }}</label>
                                                <input type="text" class="form-control" name="value" placeholder="{{ __('Value') }}">
                                            </div>

                                            @if(isset($languages) && $languages->count() > 0)
                                                {{-- Translations Div --}}
                                                <div class="translation-div mt-2 row">
                                                    <div class="col-12">
                                                        <div class="divider">
                                                            <div class="divider-text">
                                                                <h5>{{ __('Translations for Bank Details Title') }}</h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {{-- Fields for Translations --}}
                                                    @foreach($languages as $key => $language)
                                                        <div class="col-md-6 col-xl-4">
                                                            <div class="form-group">
                                                                <label for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                                                <input type="hidden" name="translation_language_id_{{ $language->id }}" value="{{ $language->id }}">
                                                                <input type="text" name="translation_value_{{ $language->id }}" id="translation-{{ $key }}-title-{{ $language->id }}" class="form-control" placeholder="{{ __('Enter Title') }}">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                        </div>
                                    </div>
                                </div>

                                {{-- Add Field --}}
                                <button type="button" class="btn btn-success mt-2" data-repeater-create>
                                    {{ __('Add Field') }}
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" name="btnAdd" value="btnAdd" class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
            </div>
        {!! Form::close() !!}

    </section>
@endsection

@section('script')
<script type="text/javascript">
        $(document).ready(function () {
            // Initialize bank details visibility based on saved state
            if($("#bank_details_enabled").val() == "1") {
                $(".bank-details-fields").show();

                // After DOM is fully rendered, check if we need to add a default item
                setTimeout(() => {
                    const repeaterItems = $('[data-repeater-list="bank_details_fields"] [data-repeater-item]').length;
                    if (repeaterItems === 0) {
                        // Add one default item if none exist
                        $('[data-repeater-create]').trigger('click');
                    }
                }, 100);
            } else {
                $(".bank-details-fields").hide();
            }


            setTimeout(() => {
                bankDetailsRepeater.setList([
                    @foreach($bankDetailsFields as $key => $bankDetail)
                        {
                            title: "{{$bankDetail['title']}}",
                            value: "{{$bankDetail['value']}}",
                            @if(isset($bankDetail['translations']) && !empty($bankDetail['translations']))
                                @foreach($bankDetail['translations'] as $key => $translation)
                                    translation_language_id_{{ $translation['language_id'] }}: "{{$translation['language_id']}}",
                                    translation_value_{{ $translation['language_id'] }}: "{{$translation['title']}}",
                                @endforeach
                            @endif
                        },
                    @endforeach
                ]);
            }, 100);
        });

        // Paypal
        $("#switch_sandbox_mode").on('change', function() {
            $("#switch_sandbox_mode").is(':checked') ? $("#sandbox_mode").val(1) : $("#sandbox_mode") .val(0);
        });
        $("#switch_paypal_gateway").on('change', function() {
            $("#switch_paypal_gateway").is(':checked') ? $("#paypal_gateway").val(1) : $("#paypal_gateway") .val(0);
        });

        // Razorpay
        $("#switch_razorpay_gateway").on('change', function() {
            $("#switch_razorpay_gateway").is(':checked') ? $("#razorpay_gateway").val(1) : $("#razorpay_gateway") .val(0);
        });

        // Stripe
        $("#switch_stripe_gateway").on('change', function() {
            $("#switch_stripe_gateway").is(':checked') ? $("#stripe_gateway").val(1) : $("#stripe_gateway") .val(0);
        });

        // Paystack
        $("#switch_paystack_gateway").on('change', function() {
            $("#switch_paystack_gateway").is(':checked') ? $("#paystack_gateway").val(1) : $("#paystack_gateway") .val(0);
        });

        // Flutterwave
        $("#switch_flutterwave_status").on('change', function() {
            $("#switch_flutterwave_status").is(':checked') ? $("#flutterwave_status").val(1) : $("#flutterwave_status") .val(0);
        });

        // Cashfree
        $("#switch_cashfree_gateway").on('change', function() {
            $("#switch_cashfree_gateway").is(':checked') ? $("#cashfree_gateway").val(1) : $("#cashfree_gateway") .val(0);
        });
        $("#switch_cashfree_sandbox_mode").on('change', function() {
            $("#switch_cashfree_sandbox_mode").is(':checked') ? $("#cashfree_sandbox_mode").val(1) : $("#cashfree_sandbox_mode") .val(0);
        });

        // Phonepe
        $("#switch_phonepe_gateway").on('change', function() {
            $("#switch_phonepe_gateway").is(':checked') ? $("#phonepe_gateway").val(1) : $("#phonepe_gateway") .val(0);
        });
        $("#switch_phonepe_sandbox_mode").on('change', function() {
            $("#switch_phonepe_sandbox_mode").is(':checked') ? $("#phonepe_sandbox_mode").val(1) : $("#phonepe_sandbox_mode") .val(0);
        });

        // Midtrans
        $("#switch_midtrans_gateway").on('change', function () {
            $("#switch_midtrans_gateway").is(':checked') ? $("#midtrans_gateway").val(1) : $("#midtrans_gateway").val(0);
        });
        $("#switch_midtrans_sandbox_mode").on('change', function () {
            $("#switch_midtrans_sandbox_mode").is(':checked') ? $("#midtrans_sandbox_mode").val(1) : $("#midtrans_sandbox_mode").val(0);
        });

        $("#switch_bank_details_enabled").on('change', function() {
            // Update hidden input value based on switch state
            if($(this).is(':checked')) {
                $("#bank_details_enabled").val(1);
                $(".bank-details-fields").show();

                // Check if there are no items in the repeater
                const repeaterItems = $('[data-repeater-list="bank_details_fields"] [data-repeater-item]').length;
                if (repeaterItems === 0) {
                    // Add one default item if none exist
                    $('[data-repeater-create]').trigger('click');
                }
            } else {
                $("#bank_details_enabled").val(0);
                $(".bank-details-fields").hide();
            }
        });

        function formSuccessFunction(){
            window.location.reload();
        }
    </script>
@endsection

