<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class report_reasons extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'report_reasons';

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($report_reasons) {
            $report_reasons->user_reports()->delete();
        });
    }

    public function user_reports()
    {
        return $this->hasMany(user_reports::class, 'reason_id', 'id');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedReasonAttribute()
    {
        return HelperService::getTranslatedData($this, $this->reason, 'reason');
    }
}
