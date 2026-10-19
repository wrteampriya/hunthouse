<?php

namespace App\Services\Payment;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePayment implements PaymentInterface
{
    private StripeClient $stripe;

    private string $currencyCode;

    private string $secretKey;

    public function __construct($paymentData)
    {
        $this->secretKey = $paymentData['stripe_secret_key'];
        $this->currencyCode = $paymentData['stripe_currency'];
        $this->stripe = new StripeClient($this->secretKey);
    }

    /**
     * Replace PaymentIntent flow with CheckoutSession for WebView
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $zeroDecimalCurrencies = [
                'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
                'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
            ];

            if (! in_array($this->currencyCode, $zeroDecimalCurrencies)) {
                $amount *= 100;
            }
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'billing_address_collection' => 'required', // Required for India export rules
                'customer_email' => $customMetaData['email'] ?? null, // optional prefill
                'line_items' => [[
                    'price_data' => [
                        'currency' => $this->currencyCode,
                        'product_data' => ['name' => $customMetaData['description'] ?? 'Payment'],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'payment_intent_data' => [
                    'metadata' => $customMetaData,
                ],
                'success_url' => $customMetaData['platform_type'] == 'app'
                    ? route('payment.success')
                    : route('payment.success.web', ['gateway' => 'stripe']),
                'cancel_url' => $customMetaData['platform_type'] == 'app'
                    ? route('payment.cancel', ['payment_transaction_id' => $customMetaData['payment_transaction_id']])
                    : route('payment.success.web', ['gateway' => 'stripe']).'?status=failed',
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            throw $e;
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $paymentIntent = $this->createPaymentIntent($amount, $customMetaData);

        return $this->format($paymentIntent);
    }

    /**
     * @throws ApiErrorException
     */
    public function retrievePaymentIntent($paymentId): array
    {
        try {
            return $this->format($this->stripe->paymentIntents->retrieve($paymentId));
        } catch (ApiErrorException $e) {
            throw $e;
        }
    }

    /**
     * @return array
     */
    public function format($paymentIntent)
    {
        return $this->formatPaymentIntent($paymentIntent->id, $paymentIntent->amount, $paymentIntent->currency, $paymentIntent->status, $paymentIntent->metadata, $paymentIntent);
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent): array
    {
        return [
            'id' => $paymentIntent->id,
            'payment_url' => $paymentIntent->url,
        ];
    }

    /**
     * @return float|int
     */
    public function minimumAmountValidation($currency, $amount)
    {
        $minimumAmount = match ($currency) {
            'USD', 'EUR', 'INR', 'NZD', 'SGD', 'BRL', 'CAD', 'AUD', 'CHF' => 0.50,
            'AED', 'PLN', 'RON' => 2.00,
            'BGN' => 1.00,
            'CZK' => 15.00,
            'DKK' => 2.50,
            'GBP' => 0.30,
            'HKD' => 4.00,
            'HUF' => 175.00,
            'JPY' => 50,
            'MXN', 'THB' => 10,
            'MYR' => 2,
            'NOK', 'SEK' => 3.00,
            'XAF' => 100
        };
        if (! empty($minimumAmount)) {
            if ($amount > $minimumAmount) {
                return $amount;
            }

            return $minimumAmount;
        }

        return $amount;
    }
}
