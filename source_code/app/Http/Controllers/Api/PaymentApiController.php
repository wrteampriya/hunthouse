<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReceiptFile;
use App\Models\Package;
use App\Models\PayAsYouGo;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\Payment\PaymentService;
use App\Services\PDF\PaymentReceiptService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class PaymentApiController extends Controller
{
    public function get_payment_settings(Request $request)
    {
        $payment_settings = Setting::select('type', 'data')->whereIn('type', ['paypal_gateway', 'razorpay_gateway', 'paystack_gateway', 'stripe_gateway', 'flutterwave_status', 'cashfree_gateway', 'bank_transfer_status', 'phonepe_gateway', 'midtrans_gateway'])->get();
        foreach ($payment_settings as $setting) {
            if ($setting->type === 'stripe_secret_key') {
                $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                $encryptedData = '';
                if (openssl_public_encrypt($setting->data, $encryptedData, $publicKey)) {
                    $setting->data = base64_encode($encryptedData);
                }
            }
        }

        if (count($payment_settings)) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['data'] = $payment_settings;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function getPaymentTransactionDetails(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_type' => 'nullable|string|in:online payment,bank transfer,free',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            // Get offset and limit
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            // Get logged in user id
            $loggedInUserId = Auth::user()->id;

            // Get payment query
            $paymentQuery = PaymentTransaction::where(['user_id' => $loggedInUserId, 'role_context' => $request->user_active_role])
                // Filter by payment type if provided
                ->when($request->payment_type, function ($query) use ($request) {
                    $query->where('payment_type', $request->payment_type);
                });
            // Get total count of filtered results
            $total = $paymentQuery->clone()->count();
            // Get paginated results
            $result = $paymentQuery->with('package:id,name,price')->orderBy('created_at', 'DESC')->skip($offset)->take($limit)->get();

            if (count($result)) {
                ApiResponseService::successResponse('Data Fetched Successfully', $result, ['total' => $total]);
            } else {
                ApiResponseService::successResponse('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required_without:pay_as_you_go_id',
            'pay_as_you_go_id' => 'required_without:package_id',
            'payment_method' => 'required|in:razorpay,paystack,stripe,flutterwave,paypal,cashfree,phonepe,midtrans',
            'platform_type' => 'required|in:app,web',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $paymentSettings = HelperService::getPaymentDetails($request->payment_method);
            if (empty($paymentSettings)) {
                ApiResponseService::validationError('None of payment method is activated');
            }

            $amount = 0;
            $itemName = '';
            $paymentTransactionData = null;

            if ($request->has('pay_as_you_go_id') && ! empty($request->pay_as_you_go_id)) {
                $payAsYouGo = PayAsYouGo::where(['id' => $request->pay_as_you_go_id, 'status' => 1])->first();
                if (empty($payAsYouGo)) {
                    ApiResponseService::validationError('No active Pay As You Go package found');
                }

                $amount = $payAsYouGo->price;
                $itemName = $payAsYouGo->name;

                // Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'amount' => $amount,
                    'payment_gateway' => Str::ucfirst($paymentSettings['payment_method']),
                    'payment_status' => 'pending',
                    'order_id' => null,
                    'payment_type' => 'online payment',
                ]);

            } else {
                $package = Package::where(['id' => $request->package_id, 'package_type' => 'paid'])->first();
                if (empty($package)) {
                    ApiResponseService::validationError('No paid package found');
                }

                // Block agent package purchase if user is not an approved agent
                if ($package->user_type === 'agent' && ! Auth::user()->is_agent) {
                    ApiResponseService::validationError('You must be an approved agent to purchase this package. Please apply for agent verification first.');
                }

                // Check if package is one_time and user already purchased it
                if ($package->purchase_type == 'one_time' && HelperService::checkUserPurchasedPackage($loggedInUserId, $package->id)) {
                    ApiResponseService::validationError('This package can only be purchased once');
                }

                // Check if user already has an active package
                $isAllFeatureLimitExits = HelperService::checkPackageLimitExists($loggedInUserId, $package->id, $request->user_active_role);
                if ($isAllFeatureLimitExits == true) {
                    ApiResponseService::validationError('same package purchase in past have all features limits available');
                }

                $amount = $package->price;
                $itemName = $package->name;

                // Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'package_id' => $package->id,
                    'amount' => $amount,
                    'payment_gateway' => Str::ucfirst($paymentSettings['payment_method']),
                    'payment_status' => 'pending',
                    'order_id' => null,
                    'payment_type' => 'online payment',
                ]);
            }

            $user = Auth::user()->fresh();
            if (empty($user->mobile) && $request->payment_method == 'cashfree') {
                ApiResponseService::validationError('Please update your phone number in your profile before making a payment.');
            }
            $phoneNumber = (! empty($user->country_code) && ! empty($user->mobile)) ? '+'.$user->country_code.$user->mobile : $user->mobile;
            // dd('hello');
            $paymentIntent = PaymentService::create($paymentSettings)->createAndFormatPaymentIntent(round($amount, 2), [
                'payment_transaction_id' => $paymentTransactionData->id,
                'package_id' => (string) ($request->package_id ?? ''),
                'pay_as_you_go_id' => (string) ($request->pay_as_you_go_id ?? ''),
                'user_type' => (string) ($package->user_type ?? 'user'),
                'user_id' => (string) $loggedInUserId,
                'email' => $user->email,
                'platform_type' => $request->platform_type,
                'description' => $request->description ?? $itemName,
                'user_name' => $user->name ?? '',
                'address_line1' => $user->address ?? '',
                'address_city' => $user->city ?? '',
                'phone' => $phoneNumber,
            ]);
            $paymentTransactionData->update(['order_id' => $paymentIntent['id'] ?? null]);

            $paymentTransactionData = PaymentTransaction::findOrFail($paymentTransactionData->id);
            // Custom Array to Show as response
            $paymentGatewayDetails = [
                ...$paymentIntent,
                'payment_transaction_id' => $paymentTransactionData->id,
            ];

            DB::commit();
            ApiResponseService::successResponse('', ['payment_intent' => $paymentGatewayDetails, 'payment_transaction' => $paymentTransactionData]);
        } catch (Throwable $e) {
            DB::rollBack();
            ApiResponseService::logErrorResponse($e);
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function makePaymentTransactionFail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_transaction_id' => 'required|exists:payment_transactions,id',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            PaymentTransaction::where('id', $request->payment_transaction_id)->update(['payment_status' => 'failed']);
            ApiResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ApiResponseService::logErrorResponse($e);
            ApiResponseService::errorResponse();
        }
    }

    public function initiateBankTransaction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required_if:pay_as_you_go_id,null|exists:packages,id',
                'pay_as_you_go_id' => 'required_without:package_id|exists:pay_as_you_gos,id',
                'file' => 'required|file|mimes:jpeg,png,jpg,pdf,doc,docx,webp|max:3072',
            ],[

            'file.max' => __('Maximum file size is 3MB.'),
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;

            if ($request->has('pay_as_you_go_id') && ! empty($request->pay_as_you_go_id)) {
                $payAsYouGo = PayAsYouGo::where(['id' => $request->pay_as_you_go_id, 'status' => 1])->first();
                if (empty($payAsYouGo)) {
                    ApiResponseService::validationError('No active Pay As You Go package found');
                }

                $amount = $payAsYouGo->price;
                $itemName = $payAsYouGo->name;

                // Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'amount' => $amount,
                    'payment_gateway' => null,
                    'payment_status' => 'review',
                    'order_id' => Str::uuid(),
                    'payment_type' => 'bank transfer',
                ]);
            } else {
                $packageData = Package::findOrFail($request->package_id);

                // Check for free packages to not allowed
                if ($packageData->package_type == 'free') {
                    ApiResponseService::validationError('No paid package found');
                }

                // Block agent package purchase if user is not an approved agent
                if ($packageData->user_type === 'agent' && ! Auth::user()->is_agent) {
                    ApiResponseService::validationError('You must be an approved agent to purchase this package. Please apply for agent verification first.');
                }

                // Check if user has already paid for this package
                $paymentTransaction = PaymentTransaction::where(['user_id' => $loggedInUserId, 'package_id' => $packageData->id, 'role_context' => $request->user_active_role])->latest()->first();
                if (! empty($paymentTransaction) && ($paymentTransaction->payment_status == 'pending' || $paymentTransaction->payment_status == 'rejected' || $paymentTransaction->payment_status == 'review')) {
                    ApiResponseService::validationError('Last Transaction is not completed');
                }

                // Check if package is one_time and user already purchased it
                if ($packageData->purchase_type == 'one_time' && HelperService::checkUserPurchasedPackage($loggedInUserId, $packageData->id)) {
                    ApiResponseService::validationError('This package can only be purchased once');
                }

                $paymentTransactionData = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'package_id' => $packageData->id,
                    'amount' => $packageData->price,
                    'payment_gateway' => null,
                    'payment_status' => 'review',
                    'order_id' => Str::uuid(),
                    'payment_type' => 'bank transfer',
                ]);

            }

            // Upload File
            $file = $request->file('file');
            $file = FileService::compressAndUpload($file, config('global.BANK_RECEIPT_FILE_PATH'));
            if (empty($file)) {
                ApiResponseService::validationError('File Upload Failed');
            }

            // Create Bank Receipt File
            $bankReceiptFile = BankReceiptFile::create([
                'payment_transaction_id' => $paymentTransactionData->id,
                'file' => $file,
            ]);
            $paymentTransactionData['bank_receipt_file'] = $bankReceiptFile->file;

            // Get Bank Details
            $bankDetailsFieldsQuery = system_setting('bank_details');
            if (isset($bankDetailsFieldsQuery) && ! empty($bankDetailsFieldsQuery)) {
                $bankDetailsFields = json_decode($bankDetailsFieldsQuery, true);
            } else {
                $bankDetailsFields = [];
            }
            DB::commit();

            ResponseService::successResponse('Transaction Initiated Successfully', $paymentTransactionData, ['bank_details' => $bankDetailsFields]);
        } catch (Exception $e) {
            DB::rollback();
            ApiResponseService::errorResponse();
        }
    }

    public function uploadBankReceiptFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_transaction_id' => 'required',
                'file' => 'required|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:3072',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // Check Payment Transaction
            $paymentTransaction = PaymentTransaction::findOrFail($request->payment_transaction_id);
            if (empty($paymentTransaction)) {
                ApiResponseService::validationError('Payment Transaction Not Found');
            }

            if ($paymentTransaction->payment_type != 'bank transfer') {
                ApiResponseService::validationError('Payment Transaction Type is not Bank Transfer');
            }

            // Check Payment Transaction Status
            if ($paymentTransaction->payment_status == 'review') {
                ApiResponseService::validationError('Your transaction is already in review');
            }

            PaymentTransaction::where('id', $request->payment_transaction_id)->update(['payment_status' => 'review']);

            // Upload File
            $file = $request->file('file');
            $file = FileService::compressAndUpload($file, config('global.BANK_RECEIPT_FILE_PATH'));

            // Create Bank Receipt File
            $bankReceiptFile = BankReceiptFile::create([
                'payment_transaction_id' => $request->payment_transaction_id,
                'file' => $file,
            ]);

            ApiResponseService::successResponse('File Uploaded Successfully', $bankReceiptFile);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getPaymentReceipt(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_transaction_id' => 'required|exists:payment_transactions,id',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUserId = Auth::user()->id;
            $payment = PaymentTransaction::with(
                'package:id,name,duration,package_type',
                'customer:id,name,email,mobile'
            )->without('customer.tokens')->findOrFail($request->payment_transaction_id);
            if ($payment->user_id != $loggedInUserId) {
                ApiResponseService::validationError('You are not authorized to view this receipt');
            }

            // Only allow viewing receipts for successful payments
            if ($payment->payment_status !== 'success') {
                ApiResponseService::validationError('Receipt is only available for successful payments');
            }
            $receiptService = new PaymentReceiptService;

            return $receiptService->generateHTML($payment);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }
}
