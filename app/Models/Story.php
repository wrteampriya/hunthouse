<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [
        'story_id',
        'agent_id',
        'media_type',
        'media_url',
        'thumbnail_url',
        'duration_seconds',
        'linked_entity_type',
        'linked_entity_id',
        'view_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'expires_at'  => 'datetime',
        'view_count'  => 'integer',
    ];

    public function agent()
    {
        // agent_id = 0 is reserved for admin-uploaded stories
        return $this->belongsTo(Customer::class, 'agent_id')->where('id', '>', 0);
    }

    public function isAdminStory(): bool
    {
        return $this->agent_id === 0;
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'linked_entity_id');
    }

    public function project()
    {
        return $this->belongsTo(Projects::class, 'linked_entity_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('expires_at', '>', now());
    }
}
