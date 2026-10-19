<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'notification';

    protected $fillable = [
        'title',
        'message',
        'image',
        'type',
        'send_type',
        'customers_id',
        'propertys_id',
        'role_context',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'type' => 'integer',
        'send_type' => 'integer',
        'propertys_id' => 'integer',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'propertys_id');
    }

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.NOTIFICATION_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getCustomerDataAttribute()
    {
        if ($this->customers_id) {
            $customerId = explode(',', $this->customers_id);

            return Customer::whereIn('id', $customerId)->select('id', 'name')->get();
        }

        return null;
    }
}
