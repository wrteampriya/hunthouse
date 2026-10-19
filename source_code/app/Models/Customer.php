<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

// class Customer extends Authenticatable implements JWTSubject
class Customer extends Authenticatable
{
    use HasApiTokens, HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'auth_id',
        'mobile',
        'country_code',
        'default_language',
        'profile',
        'address',
        'fcm_id',
        'logintype',
        'is_admin_added',
        'is_email_verified',
        'isActive',
        'slug_id',
        'notification',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'is_agent',
        'is_agent_verified',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'notification' => 'boolean',
        'is_admin_added' => 'boolean',
        'is_email_verified' => 'boolean',
        'is_agent' => 'boolean',
        'is_agent_verified' => 'boolean',
    ];

    protected $appends = [
        'is_user_verified',
        'is_agent',
        'is_agent_verified',
        // 'become_agent_status',
        // 'agent_verification_status',
        // 'user_verification_status'
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($customer) {
            if (collect($customer)->isNotEmpty()) {
                // before delete() method call this
                $userId = $customer->id;

                /** Delete Directly with delete query */
                Projects::where('added_by', $userId)->delete();
                Notifications::where('customers_id', $userId)->delete();
                Advertisement::where('customer_id', $userId)->delete();
                UserPackage::where('user_id', $userId)->delete();

                /** Delete Payment Transactions */
                $paymentTransactions = PaymentTransaction::where('user_id', $userId)->get();
                foreach ($paymentTransactions as $paymentTransaction) {
                    $paymentTransaction->delete();
                }

                /** Delete with modal boot events */
                $properties = Property::where('added_by', $userId)->get();
                foreach ($properties as $property) {
                    if (! empty($property)) {
                        $property->delete(); // This will trigger the deleting and deleted events in modal
                    }
                }
                $chats = Chats::where('sender_id', $userId)->orWhere('receiver_id', $userId)->get();
                foreach ($chats as $chat) {
                    if (collect($chat)->isNotEmpty()) {
                        $chat->delete(); // This will trigger the deleting and deleted events in modal
                    }
                }
                user_reports::where('customer_id', $userId)->delete();
                Usertokens::where('customer_id', $userId)->delete();
                Favourite::where('user_id', $userId)->delete();
                InterestedUser::where('customer_id', $userId)->delete();
            }
        });
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'customer_id' => $this->id,
        ];
    }

    public function user_purchased_package()
    {
        return $this->hasMany(UserPackage::class, 'user_id');
    }

    public function getTotalPropertiesAttribute()
    {
        return Property::where('added_by', $this->id)->count();
    }

    public function getTotalProjectsAttribute()
    {
        return Projects::where('added_by', $this->id)->count();
    }

    public function favourite()
    {
        return $this->hasMany(Favourite::class, 'user_id');
    }

    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }

    public function projects()
    {
        return $this->hasMany(Projects::class, 'added_by');
    }

    public function getProfileAttribute($image)
    {
        // Check if $image is a valid URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image; // If $image is already a URL, return it as it is
        } else {
            $path = $image ? config('global.USER_IMG_PATH').$image : null;

            return ! empty($path) ? FileService::getFileUrl($path) : null;
        }
    }

    public function getMobileAttribute($mobile)
    {
        if (! empty($mobile)) {
            if (env('DEMO_MODE')) {
                if (env('DEMO_MODE') && Auth::check() != false && Auth::user()->email == 'superadmin@gmail.com') {
                    return $mobile;
                } else {
                    return '****************************';
                }
            }
        }

        return $mobile;
    }

    public function getPhoneNumberAttribute()
    {
        if (! empty($this->country_code) && ! empty($this->getRawOriginal('mobile'))) {
            return $this->country_code.$this->getRawOriginal('mobile');
        }

        return null;
    }

    public function usertokens()
    {
        return $this->hasMany(Usertokens::class, 'customer_id');
    }

    public function agent_availabilities()
    {
        return $this->hasMany(AgentAvailability::class, 'agent_id');
    }

    public function agent_booking_preferences()
    {
        return $this->hasOne(AgentBookingPreference::class, 'agent_id');
    }

    public function agent_profile()
    {
        return $this->hasOne(AgentProfile::class, 'customer_id');
    }

    public function getResolvedAgentProfileAttribute(): array
    {
        $profile = $this->agent_profile;

        return [
            'id' => $profile?->id,
            'customer_id' => $this->id,
            'agent_name' => $profile?->agent_name ?? $this->name,
            'agent_email' => $profile?->agent_email ?? $this->email,
            'agent_profile_photo' => $profile?->agent_profile_photo ?? $this->profile,
            'agent_address' => $profile?->agent_address ?? null,
            'agent_mobile' => $profile?->agent_mobile ?? null,
            'agent_country_code' => $profile?->agent_country_code ?? null,
            'about_me' => $profile?->about_me ?? null,
            'facebook_id' => $profile?->facebook_id ?? null,
            'twitter_id' => $profile?->twitter_id ?? null,
            'youtube_id' => $profile?->youtube_id ?? null,
            'instagram_id' => $profile?->instagram_id ?? null,
            'created_at' => $profile?->created_at,
            'updated_at' => $profile?->updated_at,
        ];
    }

    // public function agent_verifications()
    // {
    //     return $this->hasMany(AgentVerification::class, 'customer_id');
    // }

    public function becomeAgent()
    {
        return $this->hasOne(AgentVerification::class, 'customer_id')
            ->where('form_type', 'become_agent');
    }

    public function verifyAgent()
    {
        return $this->hasOne(AgentVerification::class, 'customer_id')
            ->where('form_type', 'verify_agent');
    }

    public function verifyCustomer()
    {
        return $this->hasOne(VerifyCustomer::class, 'user_id');
    }

    // public function become_agent_verification()
    // {
    //     return $this->hasOne(AgentVerification::class, 'customer_id')->where('form_type', 'become_agent');
    // }

    // public function agent_verification()
    // {
    //     return $this->hasOne(AgentVerification::class, 'customer_id')->where('form_type', 'verify_agent');
    // }

    // public function user_verification()
    // {
    //     return $this->hasOne(VerifyCustomer::class, 'user_id')->select('user_id', 'status');

    // }

    // public function getBecomeAgentStatusAttribute()
    // {
    //    return $this->become_agent_verification()->status;
    // }

    // public function getAgentVerificationStatusAttribute()
    // {
    //     return $this->agent_verification()->status ?? "not_applied";
    // }
    // public function getUserVerificationStatusAttribute()
    // {
    //     return $this->user_verification()->status ?? "not_applied";
    // }

    /**
     * Get the user associated with the Customer
     */
    // public function verify_customer()
    // {
    //     return $this->hasOne(VerifyCustomer::class, 'user_id');
    // }

    public function getIsUserVerifiedAttribute()
    {
        return $this->whereHas('verifyCustomer', function ($query) {
            $query->where(['user_id' => $this->id, 'status' => 'approved']);
        })->exists() ? true : false;
    }

    public function getIsDemoUserAttribute()
    {
        return env('DEMO_MODE') && $this->email == 'wrteamdemo@gmail.com' && $this->getRawOriginal('mobile') == '1234567890' && $this->country_code == '91' && $this->logintype == '1' ? true : false;
    }

    public function getIsAgentAttribute()
    {
        return (bool) $this->getRawOriginal('is_agent');
    }

    public function getIsAgentVerifiedAttribute()
    {
        return (bool) $this->getRawOriginal('is_agent_verified');
    }

    public function activateAgent(): bool
    {
        return $this->update(['is_agent' => true]);
    }

    public function deactivateAgent(): bool
    {
        return $this->update(['is_agent' => false, 'is_agent_verified' => false]);
    }

    public function getIsAppointmentAvailableAttribute()
    {
        $status = false;
        if ($this->is_user_verified) {
            $propertyExists = $this->property()->where(['status' => 1, 'request_status' => 'approved'])->whereIn('propery_type', [0, 1])->exists();
            $appointmentScheduleExists = $this->agent_availabilities()->where('is_active', 1)->exists();
            $status = $propertyExists && $appointmentScheduleExists ? true : false;
        }

        return $status;
    }

    public function getTimezone($getForAgent = false)
    {
        if ($getForAgent) {
            $agentBookingPreference = $this->agent_booking_preferences;
            if ($agentBookingPreference) {
                return $agentBookingPreference->timezone ?? 'UTC';
            }
        }
        $adminTimezone = HelperService::getSettingData('timezone') ?? config('app.timezone');

        return $adminTimezone;
    }
}
