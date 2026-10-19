<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PayPalPayment implements PaymentInterface
{
    private string $clientId;

    private string $clientSecret;

    private string $currencyCode;

    private bool $isSandbox;

    private string $baseUrl;

    public function __construct($paymentData)
    {
        // PayPal REST API requires client_id and client_secret
        $this->clientId = $paymentData['paypal_client_id'] ?? $paymentData['paypal_business_id'] ?? '';
        $this->clientSecret = $paymentData['paypal_client_secret'] ?? '';
        $this->currencyCode = $paymentData['paypal_currency'] ?? 'USD';
        $this->isSandbox = ($paymentData['sandbox_mode'] ?? 0) == 1;
        $this->baseUrl = $this->isSandbox
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com';
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        try {
            $response = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->baseUrl.'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                Log::error('PayPal getAccessToken failed: '.$response->body());
                throw new RuntimeException('Failed to get PayPal access token: '.$response->body());
            }

            $data = $response->json();
            if (! isset($data['access_token'])) {
                throw new RuntimeException('Invalid PayPal access token response');
            }

            return $data['access_token'];
        } catch (Throwable $e) {
            Log::error('PayPal getAccessToken failed: '.$e->getMessage());
            throw new RuntimeException('PayPal authentication failed: '.$e->getMessage());
        }
    }

    /**
     * Create PayPal order (payment intent)
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $accessToken = $this->getAccessToken();

            $successUrl = $customMetaData['platform_type'] == 'app'
                ? route('payment.success')
                : route('payment.success.web', ['gateway' => 'paypal']);

            $cancelUrl = $customMetaData['platform_type'] == 'app'
                ? route('payment.cancel', ['payment_transaction_id' => $customMetaData['payment_transaction_id']])
                : route('payment.success.web', ['gateway' => 'paypal']).'?status=failed';

            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) ($customMetaData['payment_transaction_id'] ?? ''),
                    'description' => $customMetaData['description'] ?? 'Payment',
                    'custom_id' => (string) ($customMetaData['payment_transaction_id'] ?? ''),
                    'amount' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl.'/v2/checkout/orders', $orderData);

            if (! $response->successful()) {
                Log::error('PayPal createOrder failed: '.$response->body());
                throw new RuntimeException('Failed to create PayPal order: '.$response->body());
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error('PayPal createPaymentIntent failed: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Create and format payment intent
     */
    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $paymentIntent = $this->createPaymentIntent($amount, $customMetaData);

        return $this->format($paymentIntent);
    }

    /**
     * Retrieve payment intent
     */
    public function retrievePaymentIntent($paymentId): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $response = Http::withToken($accessToken)
                ->get($this->baseUrl.'/v2/checkout/orders/'.$paymentId);

            if (! $response->successful()) {
                Log::error('PayPal retrieveOrder failed: '.$response->body());
                throw new RuntimeException('Failed to retrieve PayPal order: '.$response->body());
            }

            return $this->format($response->json());
        } catch (Throwable $e) {
            Log::error('PayPal retrievePaymentIntent failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Format payment intent response
     */
    private function format($paymentIntent): array
    {
        $amount = $paymentIntent['purchase_units'][0]['amount']['value'] ?? 0;
        $currency = $paymentIntent['purchase_units'][0]['amount']['currency_code'] ?? $this->currencyCode;
        $status = $paymentIntent['status'] ?? 'UNKNOWN';
        $id = $paymentIntent['id'] ?? '';

        // Get approval URL from links
        $approvalUrl = '';
        if (isset($paymentIntent['links'])) {
            foreach ($paymentIntent['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }
        }

        // Extract metadata from purchase_units
        $metadata = [];
        if (isset($paymentIntent['purchase_units'][0]['custom_id'])) {
            $metadata['payment_transaction_id'] = $paymentIntent['purchase_units'][0]['custom_id'];
        }
        if (isset($paymentIntent['purchase_units'][0]['reference_id'])) {
            $metadata['reference_id'] = $paymentIntent['purchase_units'][0]['reference_id'];
        }

        return $this->formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent, $approvalUrl);
    }

    /**
     * Format payment intent for consistent response
     */
    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent, $approvalUrl = ''): array
    {
        return [
            'id' => $id,
            'payment_url' => $approvalUrl,
        ];
    }

    /**
     * Minimum amount validation
     */
    public function minimumAmountValidation($currency, $amount)
    {
        // PayPal minimum amounts (in major currency units)
        $minimumAmount = match (strtoupper($currency)) {
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'NZD', 'SGD', 'HKD' => 0.01,
            'JPY' => 1,
            'KRW' => 10,
            'INR', 'PKR', 'BDT' => 1,
            default => 0.01,
        };

        return max($amount, $minimumAmount);
    }

    /**
     * Verify PayPal webhook signature to ensure authenticity
     */
    public function verifyWebhookSignature(array $headers, string $payload, string $webhookId): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            if ($this->baseUrl == 'https://api.sandbox.paypal.com') {
                return true;
            }
            $verification = Http::withToken($accessToken)
                ->post($this->baseUrl.'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $headers['paypal-auth-algo'][0] ?? '',
                    'cert_url' => $headers['paypal-cert-url'][0] ?? '',
                    'transmission_id' => $headers['paypal-transmission-id'][0] ?? '',
                    'transmission_sig' => $headers['paypal-transmission-sig'][0] ?? '',
                    'transmission_time' => $headers['paypal-transmission-time'][0] ?? '',
                    'webhook_id' => $webhookId,
                    'webhook_event' => json_decode($payload, true),
                ]);

            if (! $verification->successful()) {
                Log::error('PayPal verifyWebhookSignature failed: '.$verification->body());

                return false;
            }

            return ($verification->json()['verification_status'] ?? '') === 'SUCCESS';
        } catch (Throwable $e) {
            Log::error('PayPal verifyWebhookSignature exception: '.$e->getMessage());

            return false;
        }
    }
}
