<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar','he','fa','ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ __('Payment Receipt') }}</title>
    
    <!-- Arabic Font Support with proper ligatures -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100;200;300;400;500;600;700;800;900&family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Scheherazade+New:wght@400;500;600;700&family=Cairo:wght@200;300;400;500;600;700;800;900&family=Tajawal:wght@200;300;400;500;700;800;900&family=IBM+Plex+Sans+Arabic:wght@100;200;300;400;500;600;700&family=Rubik:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        body {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }
        
        /* Arabic font support with proper ligatures */
        html[dir='rtl'] *,
        body[dir='rtl'] * {
            font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', 'Noto Sans Arabic', 'Amiri', 'Scheherazade New', 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        
        html[dir='rtl'] body,
        body[dir='rtl'] {
            font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', 'Noto Sans Arabic', 'Amiri', 'Scheherazade New', 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        
        /* Force Arabic letter connection - more aggressive approach */
        html[dir='rtl'] *,
        body[dir='rtl'] * {
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: "liga" 1, "calt" 1, "kern" 1, "dlig" 1;
            font-variant-ligatures: common-ligatures contextual discretionary-ligatures;
            -webkit-font-feature-settings: "liga" 1, "calt" 1, "kern" 1, "dlig" 1;
            -moz-font-feature-settings: "liga" 1, "calt" 1, "kern" 1, "dlig" 1;
            -ms-font-feature-settings: "liga" 1, "calt" 1, "kern" 1, "dlig" 1;
            unicode-bidi: bidi-override;
            direction: rtl;
            font-kerning: normal;
            font-variant-numeric: normal;
        }
        
        /* Specific Arabic text connection fixes */
        html[dir='rtl'] .info-row,
        body[dir='rtl'] .info-row,
        html[dir='rtl'] .info-section h3,
        body[dir='rtl'] .info-section h3,
        html[dir='rtl'] .receipt-title,
        body[dir='rtl'] .receipt-title {
            font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', 'Noto Sans Arabic', 'Amiri', 'Scheherazade New', 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif !important;
            font-feature-settings: "liga" 1, "calt" 1, "kern" 1, "dlig" 1 !important;
            font-variant-ligatures: common-ligatures contextual discretionary-ligatures !important;
            text-rendering: optimizeLegibility !important;
        }
        .container {
            padding-left: 10px;
            padding-right: 10px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 20px;
        }
        .logo {
            height: 80px;
            max-width: 200px;
            margin: 0 auto 10px;
            display: block;
        }
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .receipt-number {
            font-size: 16px;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-row {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f8f8f8;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            margin-bottom: 5px;
        }
        .total-amount {
            font-size: 18px;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }
        /* RTL overrides */
        html[dir='rtl'] body { direction: rtl; }
        html[dir='rtl'] .table th, html[dir='rtl'] .table td { text-align: right; }
        html[dir='rtl'] .receipt-number { direction: rtl; }
        html[dir='rtl'] .info-row { text-align: right; }
        html[dir='rtl'] .info-section h3 { text-align: right; }
        html[dir='rtl'] .header { text-align: center; }
        html[dir='rtl'] .receipt-title { text-align: center; }
        html[dir='rtl'] .total-section { text-align: left; }
        html[dir='rtl'] .footer { text-align: center; }
        
        /* Alternative RTL selectors using body */
        body[dir='rtl'] { direction: rtl; }
        body[dir='rtl'] .table th, body[dir='rtl'] .table td { text-align: right; }
        body[dir='rtl'] .receipt-number { direction: rtl; }
        body[dir='rtl'] .info-row { text-align: right; }
        body[dir='rtl'] .info-section h3 { text-align: right; }
        body[dir='rtl'] .header { text-align: center; }
        body[dir='rtl'] .receipt-title { text-align: center; }
        body[dir='rtl'] .total-section { text-align: left; }
        body[dir='rtl'] .footer { text-align: center; }
        
        /* Force RTL for testing - remove this after confirming it works */
        .rtl-test .info-row { text-align: right !important; }
        .rtl-test .info-section h3 { text-align: right !important; }
        .rtl-test .table th, .rtl-test .table td { text-align: right !important; }
    </style>
</head>
<body dir="{{ in_array(app()->getLocale(), ['ar','he','fa','ur']) ? 'rtl' : 'ltr' }}">
    <div class="container {{ in_array(app()->getLocale(), ['ar','he','fa','ur']) ? 'rtl-test' : '' }}">
        <div class="header">
            @if(isset($settings['logo']) && !empty($settings['logo']))
                <img src="{{ $settings['logo'] }}" alt="{{ __('Company Logo') }}" class="logo">
            @endif
            <div class="receipt-title">{{ __('Payment Receipt') }}</div>
            <div class="receipt-number">{{ __('Receipt #') }}: {{ $payment->transaction_id }}</div>
        </div>

        <div class="info-section">
            <h3>{{ __('Customer Information') }}</h3>
            <div class="info-row">
                <strong>{{ __('Name') }}:</strong> {{ $payment->customer->name }}
            </div>
            <div class="info-row">
                <strong>{{ __('Email') }}:</strong> {{ $payment->customer->email }}
            </div>
            <div class="info-row">
                <strong>{{ __('Mobile') }}:</strong> {{ $payment->customer->mobile }}
            </div>
        </div>

        <div class="info-section">
            <h3>{{ __('Payment Information') }}</h3>
            <div class="info-row">
                <strong>{{ __('Payment Date') }}:</strong> {{ $payment->created_at->translatedFormat('d M Y, h:i A') }}
            </div>
            <div class="info-row">
                <strong>{{ __('Transaction ID') }}:</strong> {{ $payment->transaction_id }}
            </div>
            <div class="info-row">
                <strong>{{ __('Payment Type') }}:</strong> {{ ucfirst($payment->payment_type) }}
            </div>
            @if($payment->payment_type == 'online payment')
                <div class="info-row">
                    <strong>{{ __('Payment Gateway') }}:</strong> {{ ucfirst($payment->payment_gateway) }}
                </div>
            @endif
        </div>

        @if($payment->package_id)
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Package') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $payment->package->name }}</td>
                        <td>{{ $payment->package->duration / 24 }} {{ $payment->package->package_type == 'unlimited' ? __('Unlimited') : __('Days') }}</td>
                        <td>{{ $settings['currency_symbol'] ?? '$' }} {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody> 
                    <tr>
                        <td>{{ __('Paid via pay as go') }}</td> 
                        <td>{{ $settings['currency_symbol'] ?? '$' }} {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        <div class="total-section">
            <div class="total-row">
                <span class="total-label">{{ __('Total Amount') }}:</span>
                <span class="total-amount">{{ $settings['currency_symbol'] ?? '$' }} {{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>{{ __('Thank you for your purchase!') }}</p>
            <p>{{ $settings['company_name'] ?? __('Company Name') }} | {{ $settings['company_address'] ?? __('Company Address') }}</p>
            @php
                // if company_tel1 is not null, then use it, otherwise use company_tel2
                $settings['company_tel'] = $settings['company_tel1'] ?? $settings['company_tel2'] ?? 'xxxxxxxxxxxxxxx';
            @endphp
            <p>{{ $settings['company_email'] ?? 'support@example.com' }} | {{ $settings['company_tel'] }}</p>
            <p>{{ __('Receipt generated on') }} {{ now()->translatedFormat('d M Y, h:i A') }}</p>
        </div>
    </div>
    
    <!-- Force Arabic text rendering -->
    <script>
        // Force Arabic text to render with proper connections
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're in RTL mode
            if (document.documentElement.dir === 'rtl' || document.body.dir === 'rtl') {
                // Force re-render of all text elements
                const elements = document.querySelectorAll('*');
                elements.forEach(function(element) {
                    if (element.textContent && /[\u0600-\u06FF]/.test(element.textContent)) {
                        // Force browser to re-render Arabic text
                        element.style.fontFeatureSettings = '"liga" 1, "calt" 1, "kern" 1, "dlig" 1';
                        element.style.fontVariantLigatures = 'common-ligatures contextual discretionary-ligatures';
                        element.style.textRendering = 'optimizeLegibility';
                        
                        // Trigger reflow
                        element.style.display = 'none';
                        element.offsetHeight; // Trigger reflow
                        element.style.display = '';
                    }
                });
            }
        });
    </script>
</body>
</html>

