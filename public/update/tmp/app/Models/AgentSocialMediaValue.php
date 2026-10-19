<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentSocialMediaValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'social_media_link_id',
        'url',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function socialMediaLink()
    {
        return $this->belongsTo(SocialMediaLink::class);
    }
}
