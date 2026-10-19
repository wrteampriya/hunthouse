<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'id',
        'package_id',
        'feature_id',
        'limit_type',
        'limit',
    ];

    /** Relations */

    /**
     * Get the package that owns the PackageFeature
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id')->withTrashed();
    }

    /**
     * Get the feature that owns the PackageFeature
     */
    public function feature()
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    /**
     * Get all of the user_package_limits for the PackageFeature
     */
    public function user_package_limits()
    {
        return $this->hasMany(UserPackageLimit::class, 'package_feature_id', 'id');
    }
}
