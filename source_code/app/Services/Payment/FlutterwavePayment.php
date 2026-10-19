<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use RuntimeException;
use Throwable;

class FlutterwavePayment implements PaymentInterface
{
    private string $currencyCode;

    private string $publicKey;

    private string $secretKey;

    /**
     * FlutterwavePayment constructor.
     */
    public function __construct($paymentData)
    {
        $this->publicKey = $paymentData['flutterwave_public_key'];
        $this->secretKey = $paymentData['flutterwave_secret_key'];
        $this->currencyCode = $paymentData['flutterwave_currency'] ?? 'NGN';
    }

    /**
     * @return array
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);

            // Note: Flutterwave Laravel package may handle currency conversion automatically
            // Amount is passed as-is to match existing implementation behavior

            $reference = Flutterwave::generateReference();

            // Determine redirect URL based on platform type
            if ($customMetaData['platform_type'] == 'app') {
                $redirectUrl = URL::to('api/flutterwave-payment-status');
            } else {
                $redirectUrl = route('payment.success.web', ['gateway' => 'flutterwave']);
            }

            $data = [
                'payment_options' => 'card,banktransfer',
                'amount' => $amount,
                'email' => $customMetaData['email'] ?? null,
                'tx_ref' => $reference,
                'currency' => $this->currencyCode,
                'redirect_url' => $redirectUrl,
                'customer' => [
                    'email' => $customMetaData['email'] ?? null,
                    'name' => $customMetaData['user_name'] ?? null,
                ],
                'meta' => $customMetaData,
            ];

            $payment = Flutterwave::initializePayment($data);

            if (empty($payment) || $payment['status'] !== 'success') {
                throw new RuntimeException('Failed to initialize Flutterwave payment');
            }

            // Return payment data with reference
            return [
                'id' => $reference,
                'status' => 'success',
                'data' => [
                    'link' => $payment['data']['link'],
                    'reference' => $reference,
                ],
                'payment_gateway_response' => $payment,
            ];

        } catch (Throwable $e) {
            Log::error('Flutterwave createPaymentIntent failed: '.$e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $response = $this->createPaymentIntent($amount, $customMetaData);

        return $this->format($response, $amount, $this->currencyCode, $customMetaData);
    }

    /**
     * @throws Throwable
     */
    public function retrievePaymentIntent($paymentId): array
    {
        try {
            $verificationData = Flutterwave::verifyTransaction($paymentId);
            if ($verificationData['status'] === 'success') {
                $data = $verificationData['data'];
                // Amount from Flutterwave is already in base currency (package handles conversion)
                $amount = $data['amount'];
                $metadata = $data['meta'] ?? [];

                return $this->format($verificationData, $amount, $data['currency'], $metadata);
            }
            throw new RuntimeException('Payment verification failed');
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @return float|int
     */
    public function minimumAmountValidation($currency, $amount)
    {
        // Flutterwave minimum amounts (in base currency, not smallest unit)
        $minimumAmount = match ($currency) {
            'NGN' => 100, // 100 Naira
            'USD', 'EUR', 'GBP' => 1.00,
            'KES' => 10,
            'ZAR' => 5.00,
            'GHS' => 1.00,
            default => 1.00
        };

        if ($amount < $minimumAmount) {
            return $minimumAmount;
        }

        return $amount;
    }

    /**
     * @return array
     */
    public function format($paymentIntent, $amount, $currencyCode, $metadata)
    {
        $id = $paymentIntent['id'] ?? $paymentIntent['data']['reference'] ?? null;
        $status = $paymentIntent['status'] ?? 'pending';
        $paymentUrl = $paymentIntent['data']['link'] ?? null;

        return $this->formatPaymentIntent($id, $amount, $currencyCode, $status, $metadata, $paymentIntent);
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentIntent['data']['link'] ?? null,
        ];
    }
}
