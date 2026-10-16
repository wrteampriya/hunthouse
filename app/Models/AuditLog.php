<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'source',
        'entity_type',
        'entity_id',
        'entity_title',
        'action',
        'description',
    ];
}
