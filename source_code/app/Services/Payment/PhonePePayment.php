<?php

namespace App\Services\Payment;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PhonePePayment implements PaymentInterface
{
    private string $clientId;

    private string $clientSecret;

    private string $clientVersion;

    private string $merchantId;

    private string $currencyCode;

    private bool $isSandbox;

    private string $baseUrl;

    private string $authUrl;

    public function __construct($paymentData)
    {
        $this->clientId = $paymentData['phonepe_client_id'] ?? '';
        $this->clientSecret = $paymentData['phonepe_client_secret'] ?? '';
        $this->clientVersion = $paymentData['phonepe_client_version'] ?? '';
        $this->merchantId = $paymentData['phonepe_merchant_id'] ?? '';
        $this->currencyCode = $paymentData['phonepe_currency'] ?? 'INR';
        $this->isSandbox = ($paymentData['phonepe_sandbox_mode'] ?? 0) == 1;

        if ($this->isSandbox) {
            // ✅ Sandbox (UAT)
            $this->baseUrl = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
            $this->authUrl = 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token';
        } else {
            // ✅ Production (Live)
            $this->baseUrl = 'https://api.phonepe.com/apis/pg';
            $this->authUrl = 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token';
        }

    }

    /**
     * Get OAuth Access Token using Client Credentials
     */
    private function getAuthToken(): string
    {
        try {
            $response = Http::asForm()
                ->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'client_version' => $this->clientVersion,
                ]);

            if (! $response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                Log::error('PhonePe Auth Token failed', [
                    'status_code' => $statusCode,
                    'response' => $errorBody,
                ]);
            }

            $data = $response->json();

            return $data['access_token'];

        } catch (Exception $e) {
            Log::error('PhonePe Auth Exception: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Create PhonePe payment intent
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            // Validate credentials
            if (empty($this->clientId) || empty($this->clientSecret) || empty($this->merchantId)) {
                throw new RuntimeException('PhonePe credentials are missing. Please check phonepe_client_id, phonepe_client_secret, and phonepe_merchant_id.');
            }

            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $returnURL = $customMetaData['platform_type'] == 'app' ? route('payment.success') : route('payment.success.web', ['gateway' => 'phonepe']);

            $merchantTransactionId = 'TXN_'.($customMetaData['payment_transaction_id']);

            // Amount in paise (smallest currency unit)
            $amountInPaise = (int) round($amount * 100);

            // Prepare payment data
            $paymentData = [
                'merchantOrderId' => $merchantTransactionId,
                'amount' => (int) $amountInPaise,
                'paymentFlow' => [
                    'type' => 'PG_CHECKOUT',
                    'merchantUrls' => [
                        'redirectUrl' => $returnURL,
                        'callbackUrl' => route('webhook.phonepe'),
                    ],
                ],
            ];

            // Get auth token
            $authToken = $this->getAuthToken();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'O-Bearer '.$authToken,
            ])->post($this->baseUrl.'/checkout/v2/pay', $paymentData);

            if (! $response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                Log::error('PhonePe createPaymentIntent failed', [
                    'status_code' => $statusCode,
                    'response' => $errorBody,
                    'headers' => $response->headers(),
                ]);
            }

            $responseData = $response->json();

            // Extract the payment URL
            $paymentUrl = $responseData['redirectUrl'] ?? null;

            if (! $paymentUrl) {
                Log::error('PhonePe createPaymentIntent missing payment URL: '.$response->body());
            }

            return [
                'merchant_transaction_id' => $merchantTransactionId,
                'transaction_id' => $responseData['data']['transactionId'] ?? null,
                'payment_url' => $paymentUrl,
                'amount' => round($amount, 2),
                'currency' => $this->currencyCode,
                'status' => 'PENDING',
                'success' => $responseData['success'] ?? false,
                'code' => $responseData['code'] ?? null,
                'message' => $responseData['message'] ?? null,
                'data' => $responseData,
            ];

        } catch (Exception $e) {
            Log::error('PhonePe createPaymentIntent failed: '.$e->getMessage());
        }
    }

    /**
     * Required by PaymentInterface
     */
    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $paymentIntent = $this->createPaymentIntent($amount, $customMetaData);
        if (! $paymentIntent) {
            return [];
        }

        return $this->format($paymentIntent, $amount, $this->currencyCode, $customMetaData);
    }

    /**
     * Required by PaymentInterface
     */
    public function retrievePaymentIntent($paymentId): array
    {
        return [];
    }

    /**
     * Required by PaymentInterface
     */
    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent, $paymentUrl = ''): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Helper to format response
     */
    private function format($paymentIntent, $amount, $currencyCode, $metadata): array
    {
        $id = $paymentIntent['merchant_transaction_id'] ?? $paymentIntent['transaction_id'] ?? '';
        $status = $paymentIntent['status'] ?? 'PENDING';
        $paymentUrl = $paymentIntent['payment_url'] ?? '';

        return $this->formatPaymentIntent($id, $amount, $currencyCode, $status, $metadata, $paymentIntent, $paymentUrl);
    }

    /**
     * Minimum amount validation
     */
    public function minimumAmountValidation($currency, $amount)
    {
        $minimumAmount = match (strtoupper($currency)) {
            'INR' => 1.00,
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'NZD', 'SGD', 'HKD' => 0.01,
            'JPY' => 1,
            'KRW' => 10,
            'PKR', 'BDT' => 1,
            default => 0.01,
        };

        return max($amount, $minimumAmount);
    }
}
