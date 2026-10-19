<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PaymentTransaction;
use App\Models\UserPackage;
use App\Models\UserPackageLimit;
use App\Models\UserPayAsYouGoCredit;
use App\Models\Usertokens;
use App\Services\HelperService;
use App\Services\Payment\PayPalPayment;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Razorpay\Api\Api;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;

class WebhookController extends Controller
{
    public function paystack()
    {
        try {
            // only a post with paystack signature header gets our attention
            if (! array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER) || (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST')) {
                echo 'Signature not found';
                http_response_code(400);
                exit(0);
            }
            $inputJSON = @file_get_contents('php://input');
            $input = json_decode($inputJSON, true, 512, JSON_THROW_ON_ERROR);

            // Calculate HMAC
            $paystackSecretKey = HelperService::getSettingData('paystack_secret_key');
            $headerSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
            $calculatedHMAC = hash_hmac('sha512', $inputJSON, $paystackSecretKey);
            if (! hash_equals($headerSignature, $calculatedHMAC)) {
                echo 'Signature does not match';
                http_response_code(400);
                exit(0);
            }
            Log::info('Paystack Webhook Signature Verified Successfully');

            $transactionId = $input['data']['id'];
            $paymentTransactionId = $input['data']['metadata']['payment_transaction_id'];
            switch ($input['event']) {
                case 'charge.success':
                    $response = $this->assignPackage($paymentTransactionId, $transactionId);
                    if ($response['error']) {
                        Log::error('Paystack Webhook : ', [$response['message']]);
                    }
                    http_response_code(200);
                    break;
                case 'charge.failed':
                    $response = $this->failedTransaction($paymentTransactionId);
                    if ($response['error']) {
                        Log::error('Paystack Webhook : ', [$response['message']]);
                    }
                    http_response_code(200);
                    break;
            }
        } catch (Throwable $e) {
            Log::error('Paystack Webhook : Error occurred', [$e->getMessage().' --> '.$e->getFile().' At Line : '.$e->getLine()]);
            http_response_code(400);
            exit();
        }
    }

    public function razorpay(Request $request)
    {
        try {
            Log::info('Razorpay Webhook Called');
            // Raw request body
            $webhookBody = file_get_contents('php://input');
            $data = json_decode($webhookBody, false, 512, JSON_THROW_ON_ERROR);

            // Get Razorpay config
            $razorPayConfigData = HelperService::getMultipleSettingData([
                'razor_key', 'razor_secret', 'razor_webhook_secret',
            ]);
            $razorPayApiKey = $razorPayConfigData['razor_key'];
            $razorPaySecretKey = $razorPayConfigData['razor_secret'];
            $webhookSecret = $razorPayConfigData['razor_webhook_secret'];

            // Validate webhook signature
            $webhookSignature = $request->header('X-Razorpay-Signature');
            $expectedSignature = hash_hmac('SHA256', $webhookBody, $webhookSecret);

            if ($expectedSignature !== $webhookSignature) {
                Log::error('Razorpay Webhook: Signature mismatch — ignoring webhook.');

                return response()->json(['error' => true, 'message' => 'Invalid signature'], 400);
            }

            // Verify signature officially
            $api = new Api($razorPayApiKey, $razorPaySecretKey);
            $api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);

            $event = $data->event ?? null;

            switch ($event) {
                // SUCCESS — Payment completed
                case 'payment_link.paid':
                    $payment = $data->payload->payment->entity ?? null;
                    if ($payment && $payment->status === 'captured') {
                        $paymentTransactionId = $payment->notes->payment_transaction_id ?? null;
                        $razorpayPaymentId = $payment->id ?? null;

                        $response = $this->assignPackage($paymentTransactionId, $razorpayPaymentId);
                        if ($response['error']) {
                            Log::error('Razorpay Webhook [payment_link.paid]: '.$response['message']);
                        }
                    } else {
                        Log::warning('Razorpay Webhook: payment_link.paid received but payment not captured.');
                    }
                    Log::info('Razorpay Webhook processed successfully and assigned package');
                    break;

                    // FAILURE — Payment link cancelled or expired
                case 'payment_link.expired':
                case 'payment_link.cancelled':
                    $paymentLink = $data->payload->payment_link->entity ?? null;
                    $notes = $paymentLink->notes ?? null;
                    $paymentTransactionId = $notes->payment_transaction_id ?? null;

                    $response = $this->failedTransaction($paymentTransactionId);
                    if ($response['error']) {
                        Log::error("Razorpay Webhook [{$event}]: ".$response['message']);
                    }
                    Log::info('Razorpay Webhook processed successfully and failed transaction');
                    break;

                default:
                    Log::info('Razorpay Webhook: Unhandled event — '.$event);
                    break;
            }

            Log::info("Razorpay Webhook processed successfully and event: {$event}");

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Razorpay Webhook Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['error' => true, 'message' => 'Webhook processing failed'], 400);
        }
    }

    public function paypal(Request $request)
    {
        try {
            Log::info('PayPal REST Webhook Received');

            // Fetch PayPal credentials
            $paypalConfigData = HelperService::getMultipleSettingData([
                'paypal_client_id', 'paypal_client_secret', 'paypal_currency', 'sandbox_mode', 'paypal_webhook_id',
            ]);

            // Initialize PayPal Payment Service
            $paypal = new PayPalPayment([
                'paypal_client_id' => $paypalConfigData['paypal_client_id'],
                'paypal_client_secret' => $paypalConfigData['paypal_client_secret'],
                'paypal_currency' => $paypalConfigData['paypal_currency'],
                'sandbox_mode' => $paypalConfigData['sandbox_mode'] ? 1 : 0,
            ]);

            // Get Webhook ID
            $webhookId = $paypalConfigData['paypal_webhook_id'];

            // Get raw body for verification
            $rawBody = file_get_contents('php://input');

            // Extract headers properly (case-insensitive)
            $paypalHeaders = [
                'paypal-auth-algo' => $request->header('paypal-auth-algo') ?: $request->header('PAYPAL-AUTH-ALGO'),
                'paypal-cert-url' => $request->header('paypal-cert-url') ?: $request->header('PAYPAL-CERT-URL'),
                'paypal-transmission-id' => $request->header('paypal-transmission-id') ?: $request->header('PAYPAL-TRANSMISSION-ID'),
                'paypal-transmission-sig' => $request->header('paypal-transmission-sig') ?: $request->header('PAYPAL-TRANSMISSION-SIG'),
                'paypal-transmission-time' => $request->header('paypal-transmission-time') ?: $request->header('PAYPAL-TRANSMISSION-TIME'),
            ];

            // Verify webhook authenticity
            $isValid = $paypal->verifyWebhookSignature($paypalHeaders, $rawBody, $webhookId);

            if (! $isValid) {
                Log::warning('Invalid PayPal Webhook Signature');

                return response()->json(['status' => 'invalid'], 200); // respond 200 to prevent PayPal retries
            }

            // Decode JSON payload
            $payload = json_decode($rawBody, true);
            $eventType = $payload['event_type'] ?? null;
            $resource = $payload['resource'] ?? [];
            $orderId = $resource['id'] ?? null;
            $customId = $resource['purchase_units'][0]['custom_id'] ?? null;

            // Log::info("PayPal Event: {$eventType}", ['order_id' => $orderId, 'custom_id' => $customId]);

            if (! $orderId && ! $customId) {
                Log::warning('Missing order/custom ID in PayPal webhook');

                return response()->json(['status' => 'missing_order'], 200);
            }

            // Match transaction by order_id or custom_id
            $transaction = PaymentTransaction::where('order_id', $orderId)
                ->orWhere('id', $customId)
                ->first();

            if (! $transaction) {
                Log::warning('Payment transaction not found', ['order_id' => $orderId, 'custom_id' => $customId]);

                return response()->json(['status' => 'missing_transaction'], 200);
            }

            $paymentTransactionId = $transaction->id;

            // Handle event types
            switch ($eventType) {
                case 'CHECKOUT.ORDER.APPROVED':
                    if ($transaction->payment_status !== 'success' && $transaction->payment_status !== 'succeed') {
                        $transactionId = $resource['id'] ?? null;
                        Log::info("PayPal Payment Approved for Transaction: {$paymentTransactionId}");
                        $response = $this->assignPackage($paymentTransactionId, $transactionId);

                        if ($response['error'] ?? false) {
                            Log::error('PayPal Webhook (Approved) Error: '.$response['message']);
                        }
                    } else {
                        Log::info('Transaction already succeeded', ['payment_transaction_id' => $paymentTransactionId]);
                    }
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.FAILED':
                    if ($transaction->payment_status !== 'failed') {
                        Log::warning("⚠️ PayPal Payment Failed for Transaction: {$paymentTransactionId}");
                        $response = $this->failedTransaction($paymentTransactionId);
                        if ($response['error'] ?? false) {
                            Log::error('PayPal Webhook (Failed): '.$response['message']);
                        }
                    } else {
                        Log::info('Transaction already marked failed', ['payment_transaction_id' => $paymentTransactionId]);
                    }
                    break;

                default:
                    Log::info("Unhandled PayPal Event Type: {$eventType}");
                    break;
            }

            // Always respond 200 OK to acknowledge receipt
            return response()->json(['status' => 'ok'], 200);

        } catch (Throwable $e) {
            Log::error("PayPal Webhook Exception: {$e->getMessage()} in {$e->getFile()} line {$e->getLine()}");

            return response()->json(['error' => true, 'message' => 'Webhook processing failed'], 200);
        }
    }

    public function stripe(Request $request)
    {
        Log::info('Stripe Webhook Called');
        // Get File Contents
        $payload = $request->getContent();
        // Get Webhook Secret From Webhook
        $secret = system_setting('stripe_webhook_secret_key');
        // Get Signature from Header
        $signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        try {
            // Create A Event
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
            // Get Transaction ID
            $transactionID = $event->data->object->id;
            // Get Payment Transaction ID
            $paymentTransactionId = $event->data->object->metadata->payment_transaction_id;
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $response = $this->assignPackage($paymentTransactionId, $transactionID);
                    if ($response['error']) {
                        Log::error('Stripe Webhook : ', [$response['message']]);
                    }
                    http_response_code(200);
                    break;
                case 'payment_intent.payment_failed':
                    $response = $this->failedTransaction($paymentTransactionId);
                    if ($response['error']) {
                        Log::error('Stripe Webhook : ', [$response['message']]);
                    }
                    http_response_code(200);
                    break;
                default:
                    Log::error('Stripe Webhook : Received unknown event type');
                    break;
            }
            Log::info('Stripe Webhook received Successfully');
        } catch (SignatureVerificationException $e) {
            // Invalid Signature Log
            return Log::error('Stripe Webhook verification failed');
        } catch (Exception $e) {
            // Other Error Exception
            return Log::error('Stripe Webhook failed');
        }
    }

    public function flutterwave(Request $request)
    {
        try {
            // This verifies the webhook is sent from Flutterwave
            $verified = Flutterwave::verifyWebhook();
            $requestData = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            // Verify the transaction
            if ($verified) {
                $verificationData = Flutterwave::verifyTransaction($requestData['data']['id']);
                if ($verificationData['status'] === 'success') {
                    $data = $verificationData['data'];
                    $transactionId = $data['id'];
                    $metaData = $data['meta'];
                    $paymentTransactionId = $metaData['payment_transaction_id'];
                    $response = $this->assignPackage($paymentTransactionId, $transactionId);
                    if ($response['error']) {
                        Log::error('Flutterwave Webhook : ', [$response['message']]);
                    }
                    http_response_code(200);

                    return true;
                } else {
                    $data = $verificationData['data'];
                    $paymentTransactionId = $data['meta']['payment_transaction_id'] ?? null;
                    if ($paymentTransactionId) {
                        $response = $this->failedTransaction($paymentTransactionId);
                        if ($response['error']) {
                            Log::error('Flutterwave Webhook : ', [$response['message']]);
                        }
                    } else {
                        Log::error('Flutterwave Webhook: Missing payment_transaction_id in metadata');
                    }
                    Log::error('Flutterwave Webhook Status Not Succeeded');
                }
            } else {
                Log::error('Flutterwave Webhook Verification Error');

                // Try to find the transaction in our database by the transaction reference
                // First extract the transaction reference from the request data
                $requestData = $request->all();
                $paymentTransactionId = null;

                if (isset($requestData['meta_data']['payment_transaction_id'])) {
                    $paymentTransactionId = $requestData['meta_data']['payment_transaction_id'];
                } elseif (isset($requestData['data']['tx_ref'])) {
                    // Get the tx_ref value
                    $txRef = $requestData['data']['tx_ref'];

                    // Try to find transaction by txRef in order_id field
                    $paymentTransaction = PaymentTransaction::where('order_id', $txRef)
                        ->where('payment_gateway', 'Flutterwave')
                        ->where('payment_status', 'pending')
                        ->first();

                    if ($paymentTransaction) {
                        $paymentTransactionId = $paymentTransaction->id;
                    }
                }

                if ($paymentTransactionId) {
                    $response = $this->failedTransaction($paymentTransactionId);
                    if ($response['error']) {
                        Log::error('Flutterwave Webhook (Failed Verification): ', [$response['message']]);
                    }
                    http_response_code(200);
                } else {
                    Log::error('Flutterwave Webhook: Could not find payment transaction to mark as failed');
                    http_response_code(400);
                }
            }
        } catch (Exception $e) {
            // Other Error Exception
            Log::error('Flutterwave Webhook failed: '.$e->getMessage());
            http_response_code(400);

            return;
        }
    }

    public function cashfree(Request $request)
    {
        try {
            $payload = $request->getContent();
            $input = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            // Verify webhook signature using client secret
            $clientSecret = HelperService::getSettingData('cashfree_secret_key') ?? '';

            if (! empty($clientSecret)) {
                $receivedSignature = $request->header('x-webhook-signature');
                $timestamp = $request->header('x-webhook-timestamp');

                if ($receivedSignature && $timestamp) {
                    $signatureData = $timestamp.$payload;
                    $calculatedSignature = base64_encode(hash_hmac('sha256', $signatureData, $clientSecret, true));

                    if (! hash_equals($calculatedSignature, $receivedSignature)) {
                        Log::error('Cashfree Webhook: Signature verification failed');

                        return response()->json(['error' => 'Invalid signature'], 401);
                    }
                }
            }

            // Extract event type and link_id from webhook payload
            $eventType = $input['type'] ?? $input['event'] ?? null;
            $linkId = $input['data']['link_id'] ?? null;

            if (! $linkId) {
                Log::error('Cashfree Webhook: Missing link_id');

                return response()->json(['error' => 'Missing link_id'], 400);
            }

            // Get payment transaction by link_id
            $paymentTransaction = PaymentTransaction::where('order_id', $linkId)
                ->where('payment_gateway', 'Cashfree')
                ->first();

            if (! $paymentTransaction) {
                Log::warning('Cashfree Webhook: Transaction not found - '.$linkId);

                return response()->json(['error' => 'Transaction not found'], 200);
            }

            $paymentTransactionId = $paymentTransaction->id;
            $transactionId = $linkId;

            // Handle webhook events
            $statusToCheck = strtoupper($eventType ?? '');

            switch ($statusToCheck) {
                case 'PAYMENT_SUCCESS_WEBHOOK':
                case 'PAYMENT_LINK_EVENT':
                    if ($paymentTransaction->payment_status !== 'success') {
                        $response = $this->assignPackage($paymentTransactionId, $transactionId);
                        if ($response['error']) {
                            Log::error('Cashfree Webhook: '.$response['message']);
                        }
                    }
                    break;

                case 'PAYMENT_FAILED_WEBHOOK':
                case 'LINK_EXPIRED':
                case 'LINK_CANCELLED':
                    if ($paymentTransaction->payment_status !== 'failed') {
                        $response = $this->failedTransaction($paymentTransactionId);
                        if ($response['error']) {
                            Log::error('Cashfree Webhook: '.$response['message']);
                        }
                    }
                    break;

                case 'PAYMENT_SUCCESS':
                case 'ORDER_PAID':
                case 'PAYMENT_COMPLETED':
                    if ($paymentTransaction->payment_status !== 'succeed' && $paymentTransaction->payment_status !== 'success') {
                        $response = $this->assignPackage($paymentTransactionId, $transactionId);
                        if ($response['error']) {
                            Log::error('Cashfree Webhook: '.$response['message']);
                        }
                    }
                    break;

                case 'PAYMENT_FAILED':
                case 'PAYMENT_DECLINED':
                case 'ORDER_FAILED':
                    if ($paymentTransaction->payment_status !== 'failed') {
                        $response = $this->failedTransaction($paymentTransactionId);
                        if ($response['error']) {
                            Log::error('Cashfree Webhook: '.$response['message']);
                        }
                    }
                    break;

                case 'PAYMENT_USER_DROPPED':
                case 'USER_DROPPED':
                    if ($paymentTransaction->payment_status !== 'failed') {
                        $response = $this->failedTransaction($paymentTransactionId);
                        if ($response['error']) {
                            Log::error('Cashfree Webhook: '.$response['message']);
                        }
                    }
                    break;
            }

            return response()->json(['success' => true], 200);

        } catch (\JsonException $e) {
            Log::error('Cashfree Webhook: Invalid JSON');

            return response()->json(['error' => 'Invalid JSON payload'], 400);

        } catch (Exception $e) {
            Log::error('Cashfree Webhook: '.$e->getMessage());

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function phonepe(Request $request)
    {
        try {
            Log::info('PhonePe Webhook Called');
            $payload = $request->getContent();
            $data = json_decode($payload, true);

            // Authorization Validation
            $authorizationHeader = $request->header('authorization');
            $isAuthorizationValid = true;

            // Get credentials
            $phonePeConfig = HelperService::getMultipleSettingData(['phonepe_merchant_id', 'phonepe_webhook_username', 'phonepe_webhook_password']);
            $userName = $phonePeConfig['phonepe_webhook_username'] ?? null;
            $password = $phonePeConfig['phonepe_webhook_password'] ?? null;
            // Verify Checksum if credentials are configured
            if (! empty($userName) && ! empty($password)) {
                if (empty($authorizationHeader)) {
                    Log::error('PhonePe Webhook: authorization header missing');
                    $isAuthorizationValid = false;
                } else {
                    $calculatedChecksum = hash('sha256', $userName.':'.$password);
                    $isAuthorizationValid = ($calculatedChecksum == $authorizationHeader);
                    if (! $isAuthorizationValid) {
                        Log::error('PhonePe Webhook: Invalid authorization webhook');

                        return response()->json(['error' => 'Invalid authorization'], 401);
                    } else {
                        Log::info('PhonePe Webhook: authorization webhook matched');
                    }
                }
            }

            if ($isAuthorizationValid && isset($data) && ! empty($data) && isset($data['payload']) && ! empty($data['payload'])) {
                $event = $data['event'];
                $payload = $data['payload'];
                switch ($event) {
                    case 'checkout.order.completed':
                        $merchantTransactionId = $payload['merchantOrderId'];
                        $transaction = PaymentTransaction::where('order_id', $merchantTransactionId)->first();
                        if ($transaction) {
                            $successfulPayment = collect($payload['paymentDetails'])->firstWhere('state', 'COMPLETED');
                            $status = $successfulPayment['state'];
                            $transactionId = $successfulPayment['transactionId'];
                            if ($status == 'COMPLETED' && ($transaction->payment_status == 'pending' || $transaction->payment_status == 'failed')) {
                                $this->assignPackage($transaction->id, $transactionId);
                            } else {
                                $this->failedTransaction($transaction->id);
                            }

                            return response()->json(['status' => 'success']);
                        } else {
                            Log::error('PhonePe Webhook: Transaction not found for '.$merchantTransactionId);
                        }
                        break;
                    case 'checkout.order.failed':
                        $merchantTransactionId = $payload['merchantOrderId'];
                        $transaction = PaymentTransaction::where('order_id', $merchantTransactionId)->first();
                        if ($transaction) {
                            $this->failedTransaction($transaction->id);
                        }
                        break;
                    default:
                        Log::error('phonepe unknown event:'.$event);
                        break;
                }
            }

            return response()->json(['status' => 'success']);

        } catch (Throwable $e) {
            Log::error('PhonePe Webhook Error: '.$e->getMessage());

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function midtrans(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('Midtrans Webhook Received');

            $serverKey = HelperService::getSettingData('midtrans_server_key');
            $signatureKey = hash(
                'sha512',
                $payload['order_id'].
                $payload['status_code'].
                $payload['gross_amount'].
                $serverKey
            );

            // Verify signature
            if (! isset($payload['signature_key']) || $payload['signature_key'] !== $signatureKey) {
                Log::warning('Invalid Midtrans Signature', ['received' => $payload['signature_key'], 'expected' => $signatureKey]);

                return response()->json(['message' => 'Invalid signature'], 403);
            }

            $transactionStatus = $payload['transaction_status'];
            $orderId = $payload['custom_field1'];
            $transactionId = $payload['transaction_id'];

            $payment = PaymentTransaction::where('order_id', $orderId)->first();
            if ($payment) {
                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $this->assignPackage($payment->id, $transactionId);
                } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'])) {
                    $this->failedTransaction($payment->id);
                } elseif ($transactionStatus === 'pending') {
                    $payment->status = 'pending';
                }
            }

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::error('Midtrans Webhook Error: '.$e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Success Business Login
     *
     * @param  $payment_transaction_id
     * @param  $user_id
     * @param  $package_id
     * @return array
     */
    private function assignPackage($paymentTransactionId, $transactionId)
    {
        try {
            $paymentTransactionData = PaymentTransaction::where('id', $paymentTransactionId)->first();
            if ($paymentTransactionData == null) {
                Log::error('Payment Transaction id not found');
                ResponseService::errorResponse('Payment Transaction id not found');
            }

            if ($paymentTransactionData->payment_status == 'succeed' || $paymentTransactionData->payment_status == 'success') {
                Log::info('Transaction Already Succeed');
                ResponseService::errorResponse('Transaction Already Succeed');
            }

            DB::beginTransaction();
            $paymentTransactionData->update(['transaction_id' => $transactionId, 'payment_status' => 'success']);

            $packageId = $paymentTransactionData->package_id;
            $payAsYouGoId = $paymentTransactionData->pay_as_you_go_id;
            $userId = $paymentTransactionData->user_id;

            if ($payAsYouGoId) {
                // Assign Pay As You Go Credit to user
                UserPayAsYouGoCredit::create([
                    'user_id' => $userId,
                    'pay_as_you_go_id' => $payAsYouGoId,
                    'payment_transaction_id' => $paymentTransactionData->id,
                    'used' => 0,
                ]);
            } elseif ($packageId) {
                $package = Package::findOrFail($packageId);

                if (! empty($package)) {
                    // Assign Package to user
                    $userPackage = UserPackage::create([
                        'package_id' => $packageId,
                        'user_id' => $userId,
                        'start_date' => Carbon::now(),
                        'end_date' => $package->package_type == 'unlimited' ? null : Carbon::now()->addHours($package->duration),
                        'role_context' => $package->user_type,
                    ]);

                    // Assign limited count feature to user with limits
                    $packageFeatures = PackageFeature::where(['package_id' => $packageId, 'limit_type' => 'limited'])->get();
                    if (collect($packageFeatures)->isNotEmpty()) {
                        $userPackageLimitData = [];
                        foreach ($packageFeatures as $key => $feature) {
                            $userPackageLimitData[] = [
                                'user_package_id' => $userPackage->id,
                                'package_feature_id' => $feature->id,
                                'total_limit' => $feature->limit,
                                'used_limit' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (! empty($userPackageLimitData)) {
                            UserPackageLimit::insert($userPackageLimitData);
                        }
                    }
                }
            }

            $userFcmTokensDB = Usertokens::where('customer_id', $userId)->pluck('fcm_id');
            if (collect($userFcmTokensDB)->isNotEmpty()) {
                $translatedTitle = 'Package Purchased';
                $translatedBody = 'Amount :- :amount';
                $registrationIDs = array_filter($userFcmTokensDB->toArray());

                $fcmMsg = [
                    'title' => $translatedTitle,
                    'message' => $translatedBody,
                    'image' => null,
                    'type' => 'default',
                    'body' => $translatedBody,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'role_context' => $paymentTransactionData->role_context ?? 'user',
                    'replace' => [
                        'amount' => $paymentTransactionData->amount,
                    ],

                ];
                send_push_notification($registrationIDs, $fcmMsg);

                $title = 'Package Purchased';
                $body = 'Amount :- '.$paymentTransactionData->amount;
                Notifications::create([
                    'title' => $title,
                    'message' => $body,
                    'image' => '',
                    'type' => '2',
                    'send_type' => '0',
                    'customers_id' => $userId,
                ]);
            }
            DB::commit();
            ResponseService::successResponse('Transaction Verified Successfully');

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage().'WebhookController -> assignPackage');
            ResponseService::errorResponse();
        }
    }

    /**
     * Failed Business Logic
     *
     * @return array
     */
    private function failedTransaction($paymentTransactionId)
    {
        try {
            $paymentTransactionData = PaymentTransaction::find($paymentTransactionId);
            if (! $paymentTransactionData) {
                Log::error('Payment Transaction id not found');

                return ResponseService::errorResponse('Payment Transaction id not found');
            }

            if ($paymentTransactionData->payment_status == 'failed') {
                Log::info('Transaction Already Failed');

                return ResponseService::errorResponse('Transaction Already Failed');
            }

            DB::beginTransaction();
            $paymentTransactionData->update(['payment_status' => 'failed']);

            $userId = $paymentTransactionData->user_id;

            $userFcmTokensDB = Usertokens::where('customer_id', $userId)->pluck('fcm_id');
            if (collect($userFcmTokensDB)->isNotEmpty()) {
                $registrationIDs = array_filter($userFcmTokensDB->toArray());
                $translatedTitle = 'Package Payment Failed';
                $translatedBody = 'Amount :- :amount';
                $fcmMsg = [
                    'title' => $translatedTitle,
                    'message' => $translatedBody,
                    'image' => null,
                    'type' => 'default',
                    'body' => $translatedBody,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'role_context' => $paymentTransactionData->role_context ?? 'user',
                    'replace' => [
                        'amount' => $paymentTransactionData->amount,
                    ],

                ];
                send_push_notification($registrationIDs, $fcmMsg);
            }

            $title = 'Package Payment Failed';
            $body = 'Amount :- '.$paymentTransactionData->amount;
            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2',
                'send_type' => '0',
                'customers_id' => $userId,
            ]);

            DB::commit();
            ResponseService::successResponse('Transaction Failed Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage().'WebhookController -> failedTransaction');
            ResponseService::errorResponse();
        }
    }
}
