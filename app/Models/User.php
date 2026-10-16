<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'profile',
        'email',
        'password',
        'type', // 0: admin, 1: system users
        'status',
        'permissions',
        'slug_id',
        'type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    public function isActive()
    {
        if ($this->status == 1) {
            return true;
        }
        return false;
    }
    public function getProfileAttribute($image)
    {
        $path = $image ? config('global.ADMIN_PROFILE_IMG_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function agent_availabilities(){
        return $this->hasMany(AgentAvailability::class, 'admin_id')->where('is_admin_data',1);
    }

    public function agent_booking_preferences()
    {
        return $this->hasOne(AgentBookingPreference::class, 'admin_id')->where('is_admin_data',1);
    }

    public function getIsAgentAttribute(){
        $propertyExists = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->whereIn('propery_type',[0,1])->exists();
        $projectExists = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->exists();
        return $propertyExists || $projectExists ? true : false;
    }

    public function getIsAppointmentAvailableAttribute(){
        $status = false;
        if($this->type == 0){
            $propertyExists = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->whereIn('propery_type',[0,1])->exists();
            $appointmentScheduleExists = $this->agent_availabilities()->where('is_active',1)->exists();
            $status = $propertyExists && $appointmentScheduleExists ? true : false;
        }
        return $status;
    }

    public function getTimezone(){
        $adminTimezone = HelperService::getSettingData('timezone') ?? config('app.timezone');
        return $adminTimezone;
    }
}
