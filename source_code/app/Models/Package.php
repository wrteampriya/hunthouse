<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Package extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'ios_product_id',
        'package_type',
        'purchase_type',
        'price',
        'duration',
        'status',
        'list_duration_type',
        'custom_duration',
        'user_type',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'status' => 'boolean',
        'duration' => 'integer',
        'price' => 'float',
    ];

    /** Relations */
    /**
     * Get all of the features for the Package
     */
    public function package_features()
    {
        return $this->hasMany(PackageFeature::class, 'package_id', 'id')->with('feature');
    }

    public function user_packages()
    {
        return $this->hasMany(UserPackage::class, 'package_id', 'id');
    }

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getIsActiveAttribute()
    {
        if (Auth::guard('sanctum')->user()) {
            $userId = Auth::guard('sanctum')->user()->id;
            $packageId = $this->id;

            $getActivePackages = HelperService::getActivePackage($userId, $packageId, $this->user_type);
            if (collect($getActivePackages)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function getPackagePaymentStatusAttribute()
    {
        if (Auth::guard('sanctum')->user()) {
            $userId = Auth::guard('sanctum')->user()->id;
            $packageId = $this->id;

            // Check if package is active
            $getActivePackages = HelperService::getActivePackage($userId, $packageId, $this->user_type);
            if (collect($getActivePackages)->isNotEmpty()) {
                return 'active';
            }

            // Check payment transaction status
            $paymentTransaction = PaymentTransaction::where(['user_id' => $userId, 'role_context' => $this->user_type])
                ->where('package_id', $packageId)
                ->latest()
                ->first();

            if ($paymentTransaction) {
                switch ($paymentTransaction->payment_status) {
                    case 'pending':
                        return 'pending';
                    case 'review':
                        return 'review';
                    case 'failed':
                        return 'failed';
                    case 'rejected':
                        return 'rejected';
                    case 'success':
                        // If payment is successful but package is not active, it might have expired
                        return 'expired';
                    default:
                        return 'inactive';
                }
            }
        }

        return 'inactive';
    }

    public function getPaymentTransactionIdAttribute()
    {
        if (Auth::guard('sanctum')->user()) {
            $userId = Auth::guard('sanctum')->user()->id;
            $packageId = $this->id;

            // Check payment transaction status
            $paymentTransaction = PaymentTransaction::where(['user_id' => $userId, 'package_id' => $packageId, 'role_context' => $this->user_type])
                ->latest()
                ->first();

            if (! empty($paymentTransaction)) {
                return $paymentTransaction->id;
            }
        }

        return null;
    }

    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }
}
