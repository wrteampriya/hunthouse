<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
</head>
<body>
    <div style="text-align: center; margin-top: 50px; font-family: sans-serif;">
        <h3>Processing Payment Status...</h3>
        <p>This window will close automatically.</p>
    </div>

    <script>
        (function() {
            const paymentData = {
                gateway: "{{ $gateway }}",
                status: "{{ $status }}",
                txn_ref_id: "{{ $txnRefId }}"
            };

            const targetOrigin = "{{ $webUrl }}";

            if (window.opener) {
                // POPUP FLOW: Send data to the parent window
                window.opener.postMessage(paymentData, targetOrigin);
                
                // Close the popup after a small delay
                setTimeout(() => {
                    window.close();
                }, 500);
            } else {
                // REDIRECT FLOW: Fallback if window.opener is null
                console.error("Window opener not found. Fallback to redirect.");
                
                const statusPath = (paymentData.status === 'success' ? 'success' : 'fail');
                
                // This ensures the data is visible in the URL for the frontend dev
                const fallbackUrl = `${targetOrigin}/payment/${statusPath}?gateway=${paymentData.gateway}&txn_id=${paymentData.txn_ref_id}`;
                
                window.location.href = fallbackUrl;
            }
        })();
    </script>
</body>
</html>
