<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MidtransPayment implements PaymentInterface
{
    private string $serverKey;

    private string $currencyCode;

    private string $baseUrl;

    public function __construct($paymentData)
    {
        $this->serverKey = $paymentData['midtrans_server_key'] ?? '';
        $this->currencyCode = 'IDR';
        $isSandbox = isset($paymentData['midtrans_sandbox_mode'])
            ? filter_var($paymentData['midtrans_sandbox_mode'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $this->baseUrl = $isSandbox
            ? 'https://api.sandbox.midtrans.com/v1/payment-links'
            : 'https://api.midtrans.com/v1/payment-links';
    }

    /**
     * Create Payment Link (GoPay / Card / etc.)
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $orderId = $customMetaData['payment_transaction_id'];

            // URLs
            if (($customMetaData['platform_type'] ?? '') === 'app') {
                $successUrl = route('payment.success', ['payment_transaction_id' => $orderId]);
                $cancelUrl = route('payment.cancel', ['payment_transaction_id' => $orderId]);
            } else {
                $successUrl = route('payment.success.web', ['gateway' => 'midtrans']);
                $cancelUrl = route('payment.success.web', ['gateway' => 'midtrans']).'?status=failed';
            }
            $webhookURL = route('webhook.midtrans');

            // Build payload per Midtrans docs
            $payload = [
                'order_id' => (string) $orderId,
                'amount' => (int) $amount,
                'currency' => $this->currencyCode,
                'description' => $customMetaData['description'] ?? 'Payment for Order #'.$orderId,
                'callbacks' => [
                    'finish' => $successUrl,
                    'error' => $cancelUrl,
                    'unfinish' => $cancelUrl,
                ],
                'customer' => [
                    'first_name' => $customMetaData['user_name'] ?? 'User',
                    'email' => $customMetaData['email'] ?? null,
                    'phone' => $customMetaData['phone'] ?? '',
                ],
                'custom_field1' => $orderId,
                'payment_transaction_id' => $orderId,
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => 60,
                ],
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount,
                ],
                'enabled_payments' => ['gopay', 'credit_card'],
            ];

            // Send request to Payment Link API
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.base64_encode($this->serverKey.':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl, $payload);

            if ($response->failed()) {
                Log::error('Midtrans Payment Link Error: '.$response->body());
                throw new RuntimeException('Midtrans payment link creation failed: '.$response->body());
            }

            $result = $response->json();
            Log::info('Midtrans Payment Link Created: ', $result);

            return $result;
        } catch (Throwable $e) {
            Log::error('Midtrans Payment Link Intent Error: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $response = $this->createPaymentIntent($amount, $customMetaData);

        return $this->format($response, $amount, $this->currencyCode, $customMetaData);
    }

    public function retrievePaymentIntent($paymentId): array
    {
        try {
            $url = str_replace('/v1/payment-links', '', $this->baseUrl).'/v2/'.$paymentId.'/status';

            $response = Http::withHeaders([
                'Authorization' => 'Basic '.base64_encode($this->serverKey.':'),
                'Accept' => 'application/json',
            ])->get($url);

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Midtrans Retrieve Error: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    public function format($paymentIntent, $amount, $currencyCode, $metadata)
    {
        // Payment Link API returns 'payment_link_url'
        return $this->formatPaymentIntent(
            $paymentIntent['order_id'] ?? null,
            $amount,
            $currencyCode,
            'pending',
            $metadata,
            $paymentIntent
        );
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $extra): array
    {
        return [
            'id' => $id,
            'payment_url' => $extra['payment_url'] ?? null,
        ];
    }

    public function minimumAmountValidation($currency, $amount)
    {
        return $amount;
    }
}
