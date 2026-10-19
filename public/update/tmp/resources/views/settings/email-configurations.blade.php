@extends('layouts.main')

@section('title')
    {{ __('Email Configurations') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>{{ __('Email Configurations') }}</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {!! Form::open(['url' => route('email-configurations-store'), 'data-parsley-validate', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction"]) !!}
            <div class="row">
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 order-md-1">
                                            <h5>{{ __('Update Configurations') }}</h5>
                                        </div>
                                    </div>
                                    <div class="row mt-4">
                                        {{-- Mailer --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-mailer">{{__('Mailer')}}</label>
                                            <select required name="mail_mailer" id="mail-mailer" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                                <option value="">{{ __("Select Mailer") }}</option>
                                                <option {{env('MAIL_MAILER')=='smtp' ?'selected':''}} value="smtp">SMTP</option>
                                                <option {{env('MAIL_MAILER')=='sendmail' ?'selected':''}} value="sendmail">sendmail</option>
                                                <option {{env('MAIL_MAILER')=='amazon_ses' ?'selected':''}} value="amazon_ses">Amazon SES</option>
                                                <option {{env('MAIL_MAILER')=='godaddy' ?'selected':''}} value="godaddy">Godaddy</option>
                                            </select>
                                            <div id="godaddy-warning" class="text-danger mt-2" style="display: none;">
                                                <small>{{ __('This will works only if hosting server and mail server both on godaddy') }}</small>
                                            </div>
                                        </div>

                                        {{-- Mail Host --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-host">{{__('Mail Host')}}</label>
                                            <input name="mail_host" id="mail-host" value="{{env('MAIL_HOST')}}" type="text" required placeholder="{{__('Mail Host')}}" class="form-control"/>
                                        </div>

                                        {{-- Mail Port --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-port">{{__('Mail Port')}}</label>
                                            <input name="mail_port" id="mail-port" value="{{env('MAIL_PORT')}}" type="text" required placeholder="{{__('Mail Port')}}" class="form-control"/>
                                        </div>

                                        {{-- Mail Username --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-username">{{__('Mail Username')}}</label>
                                            <input name="mail_username" id="mail-username" value="{{env('MAIL_USERNAME')}}" type="text" required placeholder="{{__('Mail Username')}}" class="form-control"/>
                                        </div>

                                        {{-- Mail Password --}}
                                        <div class="form-group position-relative mb-4 col-md-4" id="pwd">
                                            <label for="mail-password">{{ __('Mail Password') }}</label>
                                            <input id="mail-password" type="password" value="{{env('MAIL_PASSWORD')}}" name="mail_password" placeholder="{{ __('Password') }}" class="form-control form-input" required>
                                            <div class="form-control-icon eye-icon">
                                                <i class="bi bi-eye" id='toggle_pass'></i>
                                            </div>
                                        </div>


                                        {{-- Mail Encryption --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-encryption">{{__('Mail Encryption')}}</label>
                                            {{-- <input name="mail_encryption" id="mail-encryption" value="{{env('MAIL_ENCRYPTION')}}" type="text" required placeholder="{{__('Mail Encryption')}}" class="form-control"/> --}}                                            
                                            <select required name="mail_encryption" id="mail-encryption" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true" required>
                                                <option value="">{{ __("Select Encryption") }}</option>
                                                <option {{env('MAIL_ENCRYPTION') == 'ssl' ?'selected':''}} value="ssl">SSL</option>
                                                <option {{env('MAIL_ENCRYPTION') == 'tls' ?'selected':''}} value="tls">TLs</option>
                                                <option {{env('MAIL_ENCRYPTION') == 'none' ?'selected':''}} value="none">None</option>
                                            </select>
                                        </div>

                                        {{-- Mail Send From --}}
                                        <div class="form-group col-md-4 col-sm-12">
                                            <label for="mail-send-from">{{__('Mail Send From')}}</label>
                                            <input name="mail_send_from" id="mail-send-from" value="{{env('MAIL_FROM_ADDRESS')}}" type="text" required placeholder="{{__('Mail Send From')}}" class="form-control"/>
                                        </div>
                                    </div>

                                    {{-- Save --}}
                                    <div class="col-12 d-flex mt-4">
                                        <button type="submit" name="btnAdd1" value="btnAdd" id="btnAdd1" class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {!! Form::close() !!}
    </section>

    {{-- Email Configuration Verification --}}
    <section class="section">
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 order-md-1">
                                <h5>{{ __('Email Configuration Verification') }}</h5>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <form class="verify_email create-form" action="{{route('verify-email-config')}}" method="POST" data-success-function="formSuccessFunction">
                                @csrf
                                {{-- Verify Email --}}
                                <div class="form-group col-md-6 col-lg-4">
                                    <label>{{__('Email')}}</label>
                                    <input name="verify_email" type="email" required placeholder="{{__('Email')}}" class="form-control" />
                                </div>
                                {{-- Verify --}}
                                <div class="form-group col-md-4">
                                    <input class="btn btn-primary" type="submit" value="{{ trans('Verify') }}">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Cron Job Instructions --}}
    <section class="section">
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 order-md-1">
                                <h5>{{ __('Cron Job Setup Instructions') }}</h5>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">{{ __('Important: Queue Processing Setup') }}</h6>
                                    <p class="mb-2">{{ __('To ensure emails are processed properly, you need to set up a cron job on your server.') }}</p>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ __('Steps to Add Cron Job:') }}</h6>
                                        <ol class="mb-3">
                                            <li>{{ __('Access your server via SSH or Server Panel Cron Jobs') }}</li>
                                            <li>{{ __('Add the following cron job command:') }}</li>
                                        </ol>
                                        
                                                                <div class="bg-dark p-3 rounded">
                            <p>{{ __('If CURL is installed') }}</p>
                            <code>
                                * * * * * curl -s "{{env('APP_URL')}}/run-scheduler" > /dev/null 2>&1
                            </code>
                        </div>
                        <div class="bg-dark p-3 rounded mt-3">
                            <p>{{ __('If CURL is not installed') }}</p>
                            <code>
                                * * * * * wget -q "{{env('APP_URL')}}/run-scheduler" > /dev/null 2>&1
                            </code>
                        </div>
                        <div class="bg-dark p-3 rounded mt-3">
                            <p>{{ __('If URL scheduler is not working, use PHP command') }}</p>
                            <code>
                                * * * * * cd {{base_path()}} && php artisan schedule:run >> /dev/null 2>&1
                            </code>
                        </div>
                                        
                        <div class="mt-3">
                            <p class="text-muted mb-2"><strong>{{ __('Note:') }}</strong></p>
                                <ul class="text-muted">
                                    <li>{{ __('Check if') }} "{{env('APP_URL')}}" {{ __('is correct') }}</li>
                                    <li> {{ __('***** is a wildcard for the minute, hour, day of month, month, and day of week') }}</li>
                                    <li> {{ __('curl or wget is a command-line tool for transferring data with URLs') }}</li>
                                    <li> {{ __('> /dev/null 2>&1 is a command to redirect output to /dev/null (discard it) and show errors in the terminal') }}</li>
                                    <li> {{ __('If URL-based scheduler fails, use the PHP command method as it directly runs Laravel scheduler') }}</li>
                                    <li> {{ __('Replace') }} "{{base_path()}}" {{ __('with your actual project path if needed') }}</li>
                                    <li> {{ __('In your server, you may need to adjust the cron job command based on your server configuration') }}</li>
                                    <li>{{ __('This cron job runs every minute to process email queues') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('script')
<script>
    $('#pwd').click(function() {
        $('#password').focus();
    });

    $("#toggle_pass").click(function() {
        $(this).toggleClass("bi bi-eye bi-eye-slash");
        var input = $('[name="mail_password"]');
        if (input.attr("type") == "password") {
            input.attr("type", "text");
        } else {
            input.attr("type", "password");
        }
    });

    $(document).ready(function() {
        function checkMailer() {
            if ($('#mail-mailer').val() == 'godaddy') {
                $('#godaddy-warning').show();
                $('#mail-host').closest('.form-group').hide();
                $('#mail-port').closest('.form-group').hide();
                $('#mail-username').closest('.form-group').hide();
                $('#pwd').hide();
                $('#mail-encryption').closest('.form-group').hide();

                // Remove required attributes for validation
                $('#mail-host, #mail-port, #mail-username, #mail-password, #mail-encryption').removeAttr('required');
            } else {
                $('#godaddy-warning').hide();
                $('#mail-host').closest('.form-group').show();
                $('#mail-port').closest('.form-group').show();
                $('#mail-username').closest('.form-group').show();
                $('#pwd').show();
                $('#mail-encryption').closest('.form-group').show();

                // Restore required attributes
                $('#mail-host, #mail-port, #mail-username, #mail-password, #mail-encryption').attr('required', true);
            }
        }

        $('#mail-mailer').on('change', function() {
            checkMailer();
        });

        // Check on page load
        checkMailer();
    });

    function formSuccessFunction(response) {
        if(!response.error && !response.warning){
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    }
</script>
@endsection
