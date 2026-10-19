<?php

namespace App\Services\Payment;

use App\Services\ApiResponseService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Unicodeveloper\Paystack\Paystack;

class PaystackPayment extends Paystack implements PaymentInterface
{
    private Paystack $paystack;

    private string $currencyCode;

    /**
     * PaystackPayment constructor.
     *
     * @param  $currencyCode
     */
    public function __construct($paymentData)
    {
        // Call Paystack Class and Create Payment Intent
        $currency = $paymentData['paystack_currency'];
        $this->paystack = new Paystack;
        $this->currencyCode = $currency;
        parent::__construct();
    }

    /**
     * @return array
     */
    public function createPaymentIntent($amount, $customMetaData)
    {

        try {

            if (empty($customMetaData['email'])) {
                Log::error('Email cannot be empty for paystack payment');
                ApiResponseService::errorResponse('Email cannot be empty in profile');
            }

            if ($customMetaData['platform_type'] == 'app') {
                $callbackUrl = route('payment.success', ['payment_transaction_id' => $customMetaData['payment_transaction_id']]);
                $cancelUrl = route('payment.cancel', ['payment_transaction_id' => $customMetaData['payment_transaction_id']]);
            } else {
                $callbackUrl = route('payment.success.web', ['gateway' => 'paystack']);
                $cancelUrl = route('payment.success.web', ['gateway' => 'paystack']).'?status=failed';
            }

            $finalAmount = $amount * 100;
            $reference = $this->genTranxRef();

            // Add the metadata with cancel_action
            $metadata = $customMetaData;
            $metadata['cancel_action'] = $cancelUrl;

            $data = [
                'amount' => $finalAmount,
                'currency' => $this->currencyCode,
                'email' => $customMetaData['email'],
                'metadata' => $metadata,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
            ];

            return $this->paystack->getAuthorizationResponse($data);

        } catch (Throwable $e) {
            throw new RuntimeException($e);
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
            $relativeUrl = "/transaction/verify/{$paymentId}";
            $this->response = $this->client->get($this->baseUrl.$relativeUrl, []);
            $response = json_decode($this->response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return $this->format($response['data'], $response['data']['amount'], $response['data']['currency'], $response['data']['metadata']);
        } catch (Throwable $e) {
            throw new RuntimeException($e);
        }
    }

    public function minimumAmountValidation($currency, $amount)
    {
        // TODO: Implement minimumAmountValidation() method.
    }

    /**
     * @return array
     */
    public function format($paymentIntent, $amount, $currencyCode, $metadata)
    {
        return $this->formatPaymentIntent($paymentIntent['data']['reference'], $amount, $currencyCode, $paymentIntent['status'], $metadata, $paymentIntent);
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentIntent['data']['authorization_url'],
        ];
    }
}
