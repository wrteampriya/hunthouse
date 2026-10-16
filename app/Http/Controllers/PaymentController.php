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
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\PDF\PaymentReceiptService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'payment')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('payments.index');
    }

    public function paymentList(Request $request)
    {
        if (! has_permissions('read', 'payment')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $search = $request->input('search');
        $manualPaymentTypeOnly = $request->input('manual_payment_type_only', 0);

        $roleFilter = $request->input('role_filter', null);

        $sql = PaymentTransaction::with('package:id,name', 'customer:id,name,is_agent', 'bank_receipt_files')
            ->when($manualPaymentTypeOnly == 1, function ($query) {
                $query->where('payment_type', 'manual');
            })
            ->when(! empty($roleFilter), function ($query) use ($roleFilter) {
                $query->where('role_context', $roleFilter);
            })
            ->when($request->has('search') && ! empty($search), function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('id', 'LIKE', "%$search%")
                        ->orWhere('transaction_id', 'LIKE', "%$search%")
                        ->orWhere('payment_gateway', 'LIKE', "%$search%")
                        ->orWhere('amount', 'LIKE', "%$search%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('package', function ($q1) use ($search) {
                            $q1->where('name', 'LIKE', "%$search%");
                        });
                });
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get()->map(function ($item) {
            $item->bank_receipt_files = $item->bank_receipt_files->map(function ($file) {
                $file->file_name = $file->getRawOriginal('file');

                return $file;
            });

            return $item;
        });

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;
        $priceSymbol = HelperService::getSettingData('currency_symbol') ?? '$';
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            if ($row->payment_status == 'review' && $row->payment_type == 'bank transfer') {
                if (collect($row->bank_receipt_files)->isNotEmpty() && count($row->bank_receipt_files) > 1) {
                    $tempRow['payment_status'] = 're-uploaded';
                }
            }
            $tempRow['payment_gateway'] = $row->payment_gateway ? $row->payment_gateway : null;
            $tempRow['created_at'] = $row->created_at->format('d-m-Y H:i:s');
            $tempRow['payment_type'] = trans(Str::title($row->payment_type));
            $tempRow['updated_at'] = $row->updated_at->format('d-m-Y H:i:s');

            // Add action buttons for review status
            $tempRow['operate'] = null;
            if ($row->payment_status === 'review' && $manualPaymentTypeOnly == 0) {
                if (has_permissions('update', 'payment')) {
                    $operate = null;

                    // Success button
                    $successButtonClasses = ['btn', 'icon', 'btn-success', 'btn-sm', 'rounded-pill', 'accept-payment', 'payment-status-btn'];
                    $successButtonAttributes = [
                        'id' => $row->id,
                        'title' => trans('Accept Payment'),
                        'data-id' => $row->id,
                        'data-url' => route('payment.status'),
                        'data-status' => 'success',
                    ];
                    $operate .= BootstrapTableService::button('bi bi-check-circle', '', $successButtonClasses, $successButtonAttributes);

                    // Reject button
                    $rejectButtonClasses = ['btn', 'icon', 'btn-warning', 'btn-sm', 'rounded-pill', 'reject-payment', 'payment-status-btn'];
                    $rejectButtonAttributes = [
                        'id' => $row->id,
                        'title' => trans('Reject Payment'),
                        'data-id' => $row->id,
                        'data-url' => route('payment.status'),
                        'data-status' => 'rejected',
                    ];
                    $operate .= ' '.BootstrapTableService::button('bi bi-x-circle', '', $rejectButtonClasses, $rejectButtonAttributes);

                    // Cancel button
                    $cancelButtonClasses = ['btn', 'icon', 'btn-danger', 'btn-sm', 'rounded-pill', 'cancel-payment', 'payment-status-btn'];
                    $cancelButtonAttributes = [
                        'id' => $row->id,
                        'title' => trans('Cancel Payment'),
                        'data-id' => $row->id,
                        'data-url' => route('payment.status'),
                        'data-status' => 'failed',
                    ];
                    $operate .= ' '.BootstrapTableService::button('bi bi-slash-circle', '', $cancelButtonClasses, $cancelButtonAttributes);

                    $tempRow['operate'] = $operate;
                }

                // Add view files button if files exist
                if ($row->bank_receipt_files->count() > 0) {
                    $viewFilesButtonClasses = ['btn', 'icon', 'btn-info', 'btn-sm', 'rounded-pill', 'view-files'];
                    $viewFilesButtonCustomAttributes = ['id' => $row->id, 'title' => trans('View Files'), 'data-toggle' => 'modal', 'data-bs-target' => '#viewFilesModal', 'data-bs-toggle' => 'modal'];
                    $tempRow['operate'] .= ' '.BootstrapTableService::button('bi bi-file-earmark-text', '', $viewFilesButtonClasses, $viewFilesButtonCustomAttributes);
                }
            } elseif ($row->payment_status === 'success') {
                // Add View receipt button for successful payments
                if (has_permissions('read', 'payment')) {
                    $receiptButtonClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill'];
                    $receiptButtonAttributes = [
                        'id' => $row->id,
                        'title' => trans('View Receipt'),
                        'onclick' => "window.open('".route('payment.receipt.view', $row->id)."', '_blank')",
                    ];
                    $tempRow['operate'] = BootstrapTableService::button('bi bi-receipt', '', $receiptButtonClasses, $receiptButtonAttributes);
                }
            }
            $tempRow['customer_role'] = $row->role_context ?? 'user';
            $tempRow['price_symbol'] = $priceSymbol;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function updateStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'status' => 'required|in:success,rejected,failed',
                'reject_reason' => 'required_if:status,rejected,failed|string|max:255',
            ]);

            if ($validator->fails()) {
                return ResponseService::errorResponse($validator->errors()->first());
            }

            DB::beginTransaction();
            $payment = PaymentTransaction::find($request->id);
            if (! $payment) {
                return ResponseService::errorResponse(trans('Payment not found'));
            } elseif ($payment->payment_status != 'review') {
                return ResponseService::errorResponse(trans('Payment is not in review status'));
            }
            $payment->payment_status = $request->status;
            $payment->transaction_id = Str::uuid();
            $payment->reject_reason = $request->status == 'rejected' || $request->status == 'failed' ? $request->reject_reason : null;
            $payment->save();

            // Assign package to user
            $packageId = $payment->package_id;
            $payAsYouGoId = $payment->pay_as_you_go_id;
            $userId = $payment->user_id;

            if ($request->status == 'success') {
                if ($payAsYouGoId) {
                    UserPayAsYouGoCredit::create([
                        'user_id' => $userId,
                        'pay_as_you_go_id' => $payAsYouGoId,
                        'payment_transaction_id' => $payment->id,
                        'used' => 0,
                    ]);
                } elseif ($packageId) {
                    $package = Package::find($packageId);
                    if ($package) {
                        $userPackage = UserPackage::create([
                            'package_id' => $packageId,
                            'user_id' => $userId,
                            'start_date' => Carbon::now(),
                            'end_date' => $package->package_type == 'unlimited' ? null : Carbon::now()->addHours($package->duration),
                            'role_context' => $package->user_type,
                        ]);
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
            }

            $statusText = $request->status == 'success' ? 'Accepted' : ($request->status == 'rejected' ? 'Rejected' : 'Failed');

            // Send Notification To Customer
            $user_token = Usertokens::where('customer_id', $payment->user_id)->pluck('fcm_id')->toArray();
            $fcm_ids = [];
            $fcm_ids = $user_token;
            if (! empty($fcm_ids)) {
                $registrationIDs = $fcm_ids;
                $title = 'Payment Status';
                $body = 'Payment Status Is :status_text amount is :amount';
                $fcmMsg = [
                    'title' => $title,
                    'message' => $body,
                    'type' => 'payment',
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'id' => (string) $payment->id,
                    'role_context' => $payment->role_context ?? 'user',
                    'replace' => [
                        'status_text' => trans($statusText),
                        'amount' => $payment->amount,
                    ],
                ];
                send_push_notification($registrationIDs, $fcmMsg);
            }
            // Send Notification To Customer

            Notifications::create([
                'title' => 'Payment Status',
                'message' => 'Payment Status Is '.$statusText,
                'image' => '',
                'type' => '1',
                'send_type' => '0',
                'customers_id' => $payment->customer_id,
                'role_context' => $payment->role_context ?? 'user',
            ]);
            DB::commit();

            return ResponseService::successResponse(trans('Payment status updated successfully'));
        } catch (Exception $e) {
            DB::rollBack();

            return ResponseService::errorResponse(trans('Payment status update failed'));
        }
    }

    /**
     * View a payment receipt in PDF format
     *
     * @param  int  $id
     * @return Response
     */
    public function viewReceipt($id)
    {
        if (! has_permissions('read', 'payment')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        try {
            $payment = PaymentTransaction::with('package', 'customer')->findOrFail($id);

            // Only allow viewing receipts for successful payments
            if ($payment->payment_status !== 'success') {
                return redirect()->back()->with('error', trans('Receipt is only available for successful payments'));
            }

            $receiptService = new PaymentReceiptService;

            return $receiptService->streamPDF($payment);
        } catch (Exception $e) {
            return redirect()->back()->with('error', trans('Error generating receipt:').$e->getMessage());
        }
    }

    // public function paymentSuccess(){
    //     ResponseService::successResponse("Payment done successfully.");
    // }

    public function paymentSuccess()
    {
        ResponseService::successResponse('Payment done successfully.');
    }

    public function paymentCancel(Request $request)
    {
        try {

            $paymentTransactionId = $request->payment_transaction_id ?? null;

            // If transaction ID is available, update the payment status
            if ($paymentTransactionId) {
                PaymentTransaction::where('id', $paymentTransactionId)->update(['payment_status' => 'failed']);
            }
            ResponseService::errorResponse('Payment cancelled.');
        } catch (Exception $e) {
            return ResponseService::errorResponse('Payment cancelled.');
        }
    }

    public function paymentCancelWeb(Request $request)
    {
        try {
            $webUrl = config('app.url');
            $paymentTransactionId = $request->query('txn_id') ?? null;

            // If transaction ID is available, update the payment status
            if ($paymentTransactionId) {
                PaymentTransaction::where('id', $paymentTransactionId)->update(['payment_status' => 'failed']);
            }

            return view('payments.status', [
                'gateway' => $request->query('gateway') ?? 'unknown',
                'status' => 'failed',
                'txnRefId' => $paymentTransactionId ?? 'unknown',
                'webUrl' => $webUrl,
            ]);
        } catch (Exception $e) {
            return ResponseService::errorResponse('Payment cancelled.');
        }
    }

    public function paymentSuccessWeb(Request $request)
    {
        $webUrl = config('app.url');

        return view('payments.responses.paystack', [
            'gateway' => $request->query('gateway') ?? 'unknown',
            'status' => 'success',
            'txnRefId' => $request->query('payment_intent') ?? $request->query('razorpay_payment_id') ?? $request->query('transactionId') ?? 'success',
            'webUrl' => $webUrl,
        ]);
    }

    public function handleGatewayStatus(Request $request, $gateway)
    {
        $status = 'failed';
        $txnRefId = '';

        switch (strtolower($gateway)) {
            case 'paypal':
                $status = $request->has('token') ? 'success' : 'failed';
                $txnRefId = $request->query('token');
                break;

            case 'stripe':
                $status = $request->query('redirect_status') === 'succeeded' ? 'success' : 'failed';
                $txnRefId = $request->query('payment_intent');
                break;

            case 'paystack':
                $status = ($request->has('reference') || $request->has('trxref')) ? 'success' : 'failed';
                $txnRefId = $request->query('reference') ?? $request->query('trxref');
                break;

            case 'razorpay':
                $status = $request->has('razorpay_payment_id') ? 'success' : 'failed';
                $txnRefId = $request->query('razorpay_payment_id');
                break;

            case 'flutterwave':
                $status = ($request->query('status') === 'successful' || $request->query('status') === 'success') ? 'success' : 'failed';
                $txnRefId = $request->query('transaction_id') ?? $request->query('tx_ref');
                break;

            case 'cashfree':
                $status = $request->query('txStatus') === 'SUCCESS' ? 'success' : 'failed';
                $txnRefId = $request->query('referenceId');
                break;

            case 'phonepe':
                $status = $request->query('status') === 'COMPLETED' ? 'success' : 'failed';
                $txnRefId = $request->query('transactionId');
                break;

            case 'midtrans':
                $status = ($request->query('status_code') == '200' || $request->query('transaction_status') == 'settlement' || $request->query('transaction_status') == 'capture') ? 'success' : 'failed';
                $txnRefId = $request->query('order_id');
                break;

            default:
                $status = 'failed';
                $txnRefId = 'unknown';
        }

        Log::info('Payment Gateway Status Redirect', [
            'gateway' => $gateway,
            'params' => $request->all(),
            'normalized_status' => $status,
        ]);

        $webUrl = config('app.url');

        return view('payments.status', [
            'gateway' => $gateway,
            'status' => $status,
            'txnRefId' => $txnRefId,
            'webUrl' => $webUrl,
        ]);
    }

    public function flutterwavePaymentStatus(Request $request)
    {
        try {
            $flutterwavePaymentInfo = $request->all();
            if (isset($flutterwavePaymentInfo) && ! empty($flutterwavePaymentInfo) && isset($flutterwavePaymentInfo['status']) && ! empty($flutterwavePaymentInfo['status'])) {
                if ($flutterwavePaymentInfo['status'] == 'successful') {
                    $response['error'] = false;
                    $response['message'] = trans('Your Purchase Package Activate Within 10 Minutes');
                    $response['data'] = $flutterwavePaymentInfo;
                } else {
                    $trxRef = $flutterwavePaymentInfo['tx_ref'];
                    $paymentTransactionQuery = PaymentTransaction::where(['order_id' => $trxRef, 'payment_gateway' => 'Flutterwave', 'payment_status' => 'pending']);
                    $paymentTransaction = $paymentTransactionQuery->first();
                    if ($paymentTransaction) {
                        $paymentTransaction->update(['payment_status' => 'failed']);
                    }
                    $response['error'] = true;
                    $response['message'] = trans('Payment Cancelled / Declined');
                    $response['data'] = ! empty($flutterwavePaymentInfo) ? $flutterwavePaymentInfo : '';
                }
            } else {
                $response['error'] = true;
                $response['message'] = trans('Payment Cancelled / Declined');
            }

            return response()->json($response);

        } catch (Exception $e) {
            return ResponseService::errorResponse('Issue in flutterwave payment status.');
        }
    }

    public function flutterwavePaymentStatusWeb(Request $request)
    {
        try {
            $webUrl = config('app.url');
            $status = 'failed';
            $txnRefId = 'unknown';

            $flutterwavePaymentInfo = $request->all();
            if (isset($flutterwavePaymentInfo) && ! empty($flutterwavePaymentInfo) && isset($flutterwavePaymentInfo['status']) && ! empty($flutterwavePaymentInfo['status'])) {
                if ($flutterwavePaymentInfo['status'] == 'successful') {
                    $status = 'success';
                    $txnRefId = $flutterwavePaymentInfo['transaction_id'] ?? $flutterwavePaymentInfo['tx_ref'] ?? 'unknown';
                } else {
                    $trxRef = $flutterwavePaymentInfo['tx_ref'];
                    PaymentTransaction::where('order_id', $trxRef)->update(['payment_status' => 'failed']);
                    $status = 'failed';
                    $txnRefId = $trxRef;
                }
            }

            return view('payments.status', [
                'gateway' => 'flutterwave',
                'status' => $status,
                'txnRefId' => $txnRefId,
                'webUrl' => $webUrl,
            ]);

        } catch (Exception $e) {
            return ResponseService::errorResponse('Issue in flutterwave payment status web.');
        }

    }
}
