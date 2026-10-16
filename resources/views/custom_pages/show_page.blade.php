<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        .page-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 20px;
        }
        .page-icon {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            object-fit: cover;
        }
        h1 {
            margin: 0;
            font-size: 2rem;
            color: #111;
        }
        .page-content {
            line-height: 1.8;
        }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-header">
            @if($page->getRawOriginal('icon'))
                <img src="{{ $page->icon }}" alt="{{ $page->title }}" class="page-icon">
            @endif
            <h1>{{ $page->title }}</h1>
        </div>
        <div class="page-content">
            {!! $page->content !!}
        </div>
    </div>
</body>
</html>
