<?php

namespace App\Models;

use Carbon\Carbon;
use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use App\Services\HelperService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\VerifyCustomer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPackage extends Model
{
    use HasFactory, HasAppTimezone, HasRoleContext;
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'start_date', 'end_date'];
    protected $hidden = array('created_at','updated_at','deleted_at');
    protected $fillable = array(
        'id',
        'user_id',
        'package_id',
        'start_date',
        'end_date',
        'role_context',
    );

    protected static function booted()
    {
        static::created(function ($userPackage) {
            $existing = VerifyCustomer::where('user_id', $userPackage->user_id)->first();
            // If already approved manually by admin, preserve admin source
            if ($existing && $existing->status === 'approved' && in_array($existing->source, ['admin', 'manual'])) {
                return;
            }

            VerifyCustomer::updateOrCreate(
                ['user_id' => $userPackage->user_id],
                [
                    'status' => 'approved',
                    'source' => 'subscription',
                ]
            );
        });
    }

    /**
     * Get all of the user_package_limit for the UserPackage
     */
    public function user_package_limits()
    {
        return $this->hasMany(UserPackageLimit::class, 'user_package_id', 'id');
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


    // Scope
    public function scopeOnlyActive($query) {
        $currentDate = Carbon::now();
        return $query->where(function ($q) use($currentDate){
            $q->whereDate('end_date', '>', $currentDate)->orWhereNull('end_date');
        })->orderBy('end_date', 'asc');
    }


    // If same package purchased before then return true else false
    public function getIsRenewedAttribute(){
        $userPackage = UserPackage::where(['user_id' => $this->user_id, 'package_id' => $this->package_id])->first();
        if (!empty($userPackage) && $userPackage->id != $this->id) {   
            return true;
        }
        return false;
    }

    // If Same purchased then get last package as renewed allowed true or else false
    public function getIsRenewAllowedAttribute(){
        if($this->package->purchase_type == 'one_time'){
            return false;
        }
        $userPackage = UserPackage::where(['user_id' => $this->user_id, 'package_id' => $this->package_id])->orderBy('id', 'desc')->first();
        if (!empty($userPackage) && $userPackage->id == $this->id) {
            return true;
        }
        return false;
    }
}
