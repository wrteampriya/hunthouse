<?php

namespace App\Services\Payment;

use App\Models\PaymentConfiguration;
use InvalidArgumentException;

class PaymentService {
    /**
     * @param string $paymentGateway - Stripe
     * @return StripePayment
     */
    public static function create(array $paymentGateway) {
        $paymentMethod = strtolower($paymentGateway['payment_method']);
        return match ($paymentMethod) {
            'stripe'        => new StripePayment($paymentGateway),
            'paystack'      => new PaystackPayment($paymentGateway),
            'razorpay'      => new RazorpayPayment($paymentGateway),
            'flutterwave'   => new FlutterwavePayment($paymentGateway),
            'paypal'        => new PayPalPayment($paymentGateway),
            'cashfree'      => new CashfreePayment($paymentGateway),
            'phonepe'       => new PhonePePayment($paymentGateway),
            'midtrans'      => new MidtransPayment($paymentGateway),
            // any other payment processor implementations
            default => throw new InvalidArgumentException('Invalid Payment Gateway.'),
        };
    }
}
