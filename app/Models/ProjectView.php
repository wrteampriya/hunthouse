<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectView extends Model
{
    use HasFactory;
    protected $table = 'project_views';
    protected $fillable = [
        'user_id',
        'project_id',
        'date',
        'views'
    ];

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }
}
