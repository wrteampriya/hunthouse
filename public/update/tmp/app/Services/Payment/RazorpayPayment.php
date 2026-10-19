<?php

namespace App\Services\Payment;

use App\Services\HelperService;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use RuntimeException;
use Throwable;

class RazorpayPayment implements PaymentInterface
{
    private Api $api;

    private string $currencyCode;

    /**
     * RazorpayPayment constructor.
     *
     * @param  $secretKey
     * @param  $publicKey
     * @param  $currencyCode
     */
    public function __construct($paymentData)
    {
        $publicKey = $paymentData['razor_key'];
        $secretKey = $paymentData['razor_secret'];
        $currencyCode = HelperService::getSettingData('currency_code');
        // Call Razorpay Class and Create Payment Intent
        $this->api = new Api($publicKey, $secretKey);
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return mixed
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);

            $paymentLink = $this->api->paymentLink->create([
                'amount' => $amount,
                'currency' => $this->currencyCode,
                'description' => $customMetaData['description'] ?? 'Razorpay Payment',
                'customer' => [
                    'email' => $customMetaData['email'] ?? null,
                ],
                'notify' => [
                    'sms' => true,
                    'email' => true,
                ],
                'callback_url' => $customMetaData['platform_type'] == 'app' ? route('payment.success') : route('payment.success.web', ['gateway' => 'razorpay']),
                'callback_method' => 'get',
                'notes' => $customMetaData,
                'expire_by' => now()->addMinutes(16)->timestamp, //  Add this line for expiry
            ]);

            return $paymentLink;
        } catch (Throwable $e) {
            Log::error('Razorpay createCheckoutSession failed: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $response = $this->createPaymentIntent($amount, $customMetaData);

        return $this->format($response);
    }

    /**
     * @throws Throwable
     */
    public function retrievePaymentIntent($paymentId): array
    {
        try {
            return $this->api->order->fetch($paymentId);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * @return float|int
     */
    public function minimumAmountValidation($currency, $amount)
    {
        return match ($currency) {
            'BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND' => $amount * 1000,
            'AED', 'ALL', 'AMD', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTN', 'BWP', 'BZD', 'CAD', 'CHF',
            'CNY', 'COP', 'CRC', 'CUP', 'CVE', 'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'GBP', 'GHS', 'GIP', 'GMD', 'GTQ', 'GYD', 'HKD', 'HNL',
            'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'KES', 'KGS', 'KHR', 'KYD', 'KZT', 'LAK', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT',
            'MOP', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RSD',
            'RUB', 'SAR', 'SCR', 'SEK', 'SGD', 'SLL', 'SOS', 'SSP', 'SVC', 'SZL', 'THB', 'TTD', 'TWD', 'TZS', 'UAH', 'USD', 'UYU', 'UZS', 'XCD', 'YER', 'ZAR', 'ZMW' => $amount * 100,
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF', 'HRK' => $amount,

        };
    }

    /**
     * @return array
     */
    private function format($paymentIntent)
    {
        return $this->formatPaymentIntent($paymentIntent->id, $paymentIntent->amount, $paymentIntent->currency, $paymentIntent->status, $paymentIntent->notes->toArray(), $paymentIntent);
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentIntent->short_url,
        ];
    }
}
