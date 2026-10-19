<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'user_id',
        'package_id',
        'pay_as_you_go_id',
        'property_id',
        'project_id',
        'amount',
        'payment_gateway',
        'payment_type',
        'order_id',
        'payment_status',
        'transaction_id',
        'role_context',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'package_id' => 'integer',
        'pay_as_you_go_id' => 'integer',
        'property_id' => 'integer',
        'project_id' => 'integer',
        'amount' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            $bankReceiptFiles = $model->bank_receipt_files()->get();
            foreach ($bankReceiptFiles as $bankReceiptFile) {
                $bankReceiptFile->delete();
            }
        });
    }

    /**
     * Get the customer that owns the UserPackage
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Get the package that owns the UserPackage
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id')->withTrashed();
    }

    /**
     * Get the bank receipt files for the payment transaction
     */
    public function bank_receipt_files()
    {
        return $this->hasMany(BankReceiptFile::class, 'payment_transaction_id');
    }
}
