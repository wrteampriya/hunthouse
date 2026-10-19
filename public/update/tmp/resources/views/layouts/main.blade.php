<!DOCTYPE html>

@if($language)
    @if ($language->rtl)
        <html lang="en" dir="rtl">
    @else
        <html lang="en">
    @endif
@else
    <html lang="en">
@endif

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ url('assets/images/logo/' . (system_setting('favicon_icon') ?? null)) }}" type="image/x-icon">
    <title>@yield('title') || {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @include('layouts.include')
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-responsive.css') }}">
    @yield('css')
    
    <!-- Global Language Data for TinyMCE RTL Support -->
    <script>
        window.globalLanguageData = {
            current: {
                rtl: {{ $language && $language->rtl ? 'true' : 'false' }},
                code: "{{ $language ? $language->code : 'en' }}",
                name: "{{ $language ? $language->name : 'English' }}"
            },
            all: {
                @if(isset($allLanguages) && $allLanguages->count() > 0)
                    @foreach($allLanguages as $lang)
                        "{{ $lang->id }}": {
                            "rtl": {{ $lang->rtl ? 'true' : 'false' }},
                            "code": "{{ $lang->code }}",
                            "name": "{{ $lang->name }}"
                        }{{ $loop->last ? '' : ',' }}
                    @endforeach
                @endif
            }
        };
    </script>
</head>

<body>
    <div id="app">
        @include('layouts.sidebar')
        
        <!-- Mobile sidebar overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div id="main" class='layout-navbar'>
            @include('layouts.topbar')
            <div id="main-content">
                <div class="page-heading">

                    @yield('page-title')
                </div>
                @yield('content')

            </div>

        </div>
        <div class="wrapper mt-5">
            <div class="content">
                @include('layouts.footer')

                <!-- Your page content here -->
            </div>
        </div>
        {{-- <div>
            @include('layouts.footer')
        </div> --}}
    </div>

    @if (env('DEMO_MODE'))
    <!-- Buy Now Card -->
    <style>
        /* Extra bottom breathing room so the fixed Buy Now pill doesn't sit on top of the last sidebar item */
        .sidebar-wrapper {
            padding-bottom: 50px;
        }
        .buy-now-wrap {
            position: fixed;
            bottom: 25px;
            left: 25px;
            z-index: 1030;
            display: flex;
            align-items: center;
        }
        .buy-now-container {
            position: relative;
            background: linear-gradient(135deg, var(--bs-primary), #0a5f5f);
            border-radius: 50px;
            display: flex;
            align-items: stretch;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .buy-now-container:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            transform: translateY(-3px);
        }
        .buy-now-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 60%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.55), transparent);
            transform: skewX(-20deg);
            pointer-events: none;
            animation: buyNowShine 4s ease-in-out infinite;
        }
        @keyframes buyNowShine {
            0%   { left: -150%; }
            30%  { left: 150%; }
            100% { left: 150%; }
        }
        
        .buy-now-content {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px 8px 8px 10px;
            text-decoration: none;
            color: #fff;
            white-space: nowrap;
        }
        .buy-now-content:hover {
            color: #fff;
        }
        
        .buy-now-icon {
            background: #fff;
            color: var(--bs-primary);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .buy-now-text {
            display: flex;
            flex-direction: column;
            opacity: 1;
            max-width: 300px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .buy-now-text h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }
        
        .buy-now-text p {
            margin: 0;
            font-size: 12px;
            opacity: 0.85;
            margin-top: 2px;
            font-weight: 300;
        }
        
        .buy-now-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px 0 16px;
            cursor: pointer;
            color: rgba(255,255,255,0.7);
            transition: color 0.3s ease;
        }
        .buy-now-toggle:hover {
            color: #fff;
        }
        .buy-now-toggle i {
            font-size: 14px;
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        /* Collapsed State */
        .buy-now-wrap.collapsed .buy-now-text {
            max-width: 0;
            opacity: 0;
            padding: 0;
        }
        .buy-now-wrap.collapsed .buy-now-content {
            padding-left: 0;
            gap: 0;
        }
        .buy-now-wrap.collapsed .buy-now-toggle i {
            transform: rotate(180deg);
        }
        .buy-now-wrap.collapsed .buy-now-toggle {
            padding: 0 12px 0 16px;
        }
    </style>
    
    <div class="buy-now-wrap" id="buyNowWrap">
        <div class="buy-now-container">
            <div class="buy-now-toggle" onclick="toggleBuyNow(event)" title="Collapse/Expand">
                <i class="fas fa-chevron-right"></i>
            </div>
            <a href="https://www.marketplace.wrteam.in/products/ebroker-real-estate-management-software" target="_blank" class="buy-now-content">
                <div class="buy-now-text">
                    <h4>Buy Now</h4>
                    <p>Like what you see, get the full version</p>
                </div>
                <div class="buy-now-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </a>
        </div>
    </div>
    
    <script>
        function toggleBuyNow(e) {
            e.preventDefault();
            e.stopPropagation();
            var wrap = document.getElementById('buyNowWrap');
            wrap.classList.toggle('collapsed');
        }
    </script>
    @endif

    @include('layouts.footer_script')
    @yield('js')
    @yield('script')
</body>

</html>
