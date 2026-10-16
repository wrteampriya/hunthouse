<!DOCTYPE html>
<html lang="{{ isset($language) && isset($language->code) ? $language->code : app()->getLocale() }}" dir="{{ isset($language) && isset($language->rtl) && $language->rtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="{{ url('assets/images/logo/' . ($settings['favicon_icon'] ?? null)) }}" type="image/x-icon">
    <title>{{ __('Login') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    @if(isset($language) && isset($language->rtl) && $language->rtl)
    <link rel="stylesheet" href="{{ url('assets/css/main/rtl.css') }}">
    @else
    <link rel="stylesheet" href="{{ url('assets/css/main/app.css') }}">
    @endif
    <link rel="stylesheet" href=" {{ url('assets/css/pages/auth.css') }}">
    <link href="{{ url('assets/extensions/toastify-js/src/toastify.css') }}" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="{{ url('assets/extensions/toastify-js/src/toastify.js') }}"></script>
</head>

<body>
    <div id="auth" class="login_bg">

        <div class="row justify-content-end login-box">
            <div class="col-lg-4 col-12 card login-card">
                <div id="auth-center">
                    <div class="auth-logo mb-5">
                        <a href="{{ url('') }}"><img src="{{ url('assets/images/logo/' . ($settings['company_logo'] ?? null)) }}" alt="Logo" srcset=""></a>
                    </div>
                    <div class="center mtop-120">
                        <div class='login_heading'>
                            <h3>
                                {{ __('Hi, Welcome Back!') }}
                            </h3>
                            <p>
                                {{ __('Enter your details sign in to your account') }}
                            </p>
                        </div>

                        <div class="pt-4">
                            <form method="POST" action="{{ route('login') }}">
                                {{-- {{ csrf_field() }} --}}
                                @csrf

                                <div class="form-group position-relative form-floating mb-4"> <input id="floatingInput" type="email" placeholder="{{ __('Email') }}" class="form-control form-input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                                    <label for="floatingInput">{{ __('Email address') }}</label>
                                </div>

                                <div class="form-group position-relative form-floating has-icon-right mb-4" id="pwd">
                                    <input id="floatingInput" type="password" placeholder="{{ __('Password') }}" class="form-control form-input @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                                    <label for="floatingInput">{{ __('Password') }}</label>
                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror

                                    <div class="form-control-icon icon-right">
                                        <i class="bi bi-eye" id='toggle_pass'></i>
                                    </div>
                                </div>
                                <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-block btn-sm shadow-lg mt-3 login_btn">
                                    <span class="btn-text">{{ __('Log in') }}</span>
                                    <span class="btn-loader" style="display: none;">
                                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                        {{ __('Logging in...') }}
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <script>
        // Set the CSS custom property using JavaScript
        var primarycolor = "{{ env('PRIMARY_COLOR') }}";
        document.documentElement.style.setProperty('--bs-primary', primarycolor);

        var login_bg = "{{ url('assets/images/bg/' . ($settings['login_image'] ?? 'Login_BG.jpg')) }}";
        if(login_bg){
            document.documentElement.style.setProperty('--login-bg', 'url(' + login_bg + ')');
        }

        var rgbaprimarycolor = "{{ env('PRIMARY_RGBA_COLOR') }}";

        document.documentElement.style.setProperty('--primary-rgba', rgbaprimarycolor);
    </script>
    <script>
        $('#pwd').click(function() {
            console.log('click');
            $('#password').focus();
        });
        $("#toggle_pass").click(function() {

            $(this).toggleClass("bi bi-eye bi-eye-slash");
            var input = $('[name="password"]');
            if (input.attr("type") == "password") {
                input.attr("type", "text");

            } else {
                input.attr("type", "password");
            }
        });

        // Handle login form submission with loader
        $('form').on('submit', function(e) {
            var $submitBtn = $('#loginSubmitBtn');
            var $btnText = $submitBtn.find('.btn-text');
            var $btnLoader = $submitBtn.find('.btn-loader');

            // Show loader and disable button
            $btnText.hide();
            $btnLoader.show();
            $submitBtn.prop('disabled', true);
        });

        // Remove loader if errors are displayed (on page load with errors)
        $(document).ready(function() {
            @if ($errors->any() || Session::has('error'))
                var $submitBtn = $('#loginSubmitBtn');
                var $btnText = $submitBtn.find('.btn-text');
                var $btnLoader = $submitBtn.find('.btn-loader');

                // Hide loader and enable button
                $btnText.show();
                $btnLoader.hide();
                $submitBtn.prop('disabled', false);
            @endif
        });
    </script>

    @if (Session::has('success'))
        <script type="text/javascript">
            Toastify({
                text: '{{ Session::get('success') }}',
                duration: 6000,
                close: !0,
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
            }).showToast()
        </script>
    @endif

    @if (Session::has('error'))
        <script type="text/javascript">
            Toastify({
                text: '{{ Session::get('error') }}',
                duration: 6000,
                close: !0,
                backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
            }).showToast()
        </script>
    @endif
    @if ($errors->any())
        <script type="text/javascript">
            Toastify({
                text: "{{ implode(', ', $errors->all()) }}",
                duration: 6000,
                close: true,
                backgroundColor: '#dc3545'
            }).showToast();
        </script>
    @endif
</body>

</html>
