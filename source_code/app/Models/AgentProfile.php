<?php

namespace App\Models;

use App\Services\FileService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'agent_name',
        'agent_email',
        'agent_profile_photo',
        'agent_address',
        'agent_mobile',
        'agent_country_code',
        'about_me',
        'facebook_id',
        'twitter_id',
        'youtube_id',
        'instagram_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getAgentProfilePhotoAttribute($image)
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $path = $image ? config('global.AGENT_PROFILE_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }
}
