<!DOCTYPE html>
<html>
<head>
    <title>Opening {{ $settingsData['schema_for_deeplink'] ?? 'App' }}...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const slug = @json($slug);
            const schema = @json($settingsData['schema_for_deeplink'] ?? 'ebroker');
            const androidStore = @json($settingsData['playstore_id']);
            const iosStore = @json($settingsData['appstore_id']);
            const appName = schema.charAt(0).toUpperCase() + schema.slice(1);

            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            const isAndroid = /android/i.test(userAgent);
            const isIOS = /iPhone|iPad|iPod/.test(userAgent) && !window.MSStream;
            const hostname = window.location.hostname;

            const appUrl = `${schema}://${hostname}/property-details/${slug}`;
            const fallbackStore = isAndroid ? androidStore : isIOS ? iosStore : androidStore;

            let didRedirect = false;

            if (isIOS) {
                // iOS: iframe method
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = appUrl;
                document.body.appendChild(iframe);

                setTimeout(() => {
                    if (!document.hidden && !didRedirect) {
                        showFallback();
                    }
                }, 2000);
            } else if (isAndroid) {
                // Android: regular redirect
                window.location.href = appUrl;

                setTimeout(() => {
                    if (!document.hidden && !didRedirect) {
                        showFallback();
                    }
                }, 2000);
            } else {
                // Fallback: redirect to web if on desktop
                window.location.href = `https://play.google.com/store`;
            }

            function showFallback() {
                didRedirect = true;
                const fallback = fallbackStore || (isAndroid
                    ? `https://play.google.com/store/search?q=${appName}&c=apps`
                    : `https://apps.apple.com/search?term=${appName}`);

                window.location.href = fallback;
            }
        });
    </script>
</head>
<body>
    <p style="text-align:center;padding:2rem;">Opening {{ $settingsData['schema_for_deeplink'] ?? 'the app' }}...</p>
</body>
</html>
