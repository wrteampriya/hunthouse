<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPackageLimit extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'id',
        'user_package_id',
        'package_feature_id',
        'total_limit',
        'used_limit',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user that owns the UserPackageLimit
     */
    public function user_package()
    {
        return $this->belongsTo(UserPackage::class, 'user_package_id');
    }

    public function package_feature()
    {
        return $this->belongsTo(PackageFeature::class, 'package_feature_id');
    }

    public function getIsLimitOverAttribute()
    {
        if ($this->package_feature->limit_type == 'unlimited') {
            return false;
        }
        if ($this->total_limit <= $this->used_limit) {
            return true;
        }

        return false;
    }
}
