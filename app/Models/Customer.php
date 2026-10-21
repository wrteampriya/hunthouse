<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

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
        'country_id',
        'is_agent',
        'is_agent_verified',
        'auto_approve',
        'user_auto_approve',
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
        'auto_approve' => 'boolean',
        'user_auto_approve' => 'boolean',
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
                AgentFollow::where('user_id', $userId)->orWhere('agent_id', $userId)->delete();
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

    public function following()
    {
        return $this->hasMany(AgentFollow::class, 'user_id');
    }

    public function followers()
    {
        return $this->hasMany(AgentFollow::class, 'agent_id');
    }

    public function getIsFollowingAttribute()
    {
        $authUser = Auth::user();
        if (! $authUser) {
            return false;
        }

        return AgentFollow::where('user_id', $authUser->id)->where('agent_id', $this->id)->exists();
    }

    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }

    public function projects()
    {
        return $this->hasMany(Projects::class, 'added_by');
    }

    public function stories()
    {
        return $this->hasMany(Story::class, 'agent_id');
    }

    public function scopeWithStoryStatus(Builder $query): Builder
    {
        return $query->withExists(['stories as has_active_story' => fn ($q) => $q->active()]);
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
                $authUser = Auth::user();
                if ($authUser && ($authUser->email == 'superadmin@gmail.com' || $authUser->id == $this->attributes['id'])) {
                    return $mobile;
                } else {
                    return '****************************';
                }
            }
        }

        return $mobile;
    }

    public function setMobileAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['mobile'] = null;

            return;
        }

        // Always store as string to prevent integer casting from dropping leading zeros.
        $value = (string) $value;

        // If the value already looks like a full E.164 number (e.g. "+2250123456789"),
        // extract just the national significant number and set country_code from it.
        if (str_starts_with($value, '+')) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $proto = $phoneUtil->parse($value, null);
                if ($phoneUtil->isValidNumber($proto)) {
                    $this->attributes['country_code'] = (string) $proto->getCountryCode();
                    $this->attributes['mobile'] = $phoneUtil->getNationalSignificantNumber($proto);

                    return;
                }
            } catch (NumberParseException) {
                // fall through and store as-is
            }
        }

        // Plain national number — store as-is (string, preserving leading zeros).
        $this->attributes['mobile'] = $value;
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

    public function agent_social_media_values()
    {
        return $this->hasMany(AgentSocialMediaValue::class, 'customer_id');
    }

    /**
     * Every active social media link definition, with this customer's own
     * url (if they've set one) — null otherwise. Admin-created platforms
     * appear here automatically, same as the original 5.
     */
    public function getSocialMediaLinksAttribute(): array
    {
        $values = $this->agent_social_media_values->keyBy('social_media_link_id');

        return SocialMediaLink::where('status', 'active')
            ->orderBy('sequence')
            ->get()
            ->map(function ($link) use ($values) {
                return [
                    'id' => $link->id,
                    'name' => $link->translated_name,
                    'icon' => $link->icon,
                    'url' => $values->get($link->id)?->url,
                ];
            })
            ->toArray();
    }

    /**
     * Service Areas / Languages Spoken / Years of Experience / Working Hours are Default-section
     * fields in the Become Agent form (AgentVerificationValue) — reused here rather than
     * duplicated as agent_profiles columns, so editing from either screen stays in sync.
     */
    public function getAgentProfileExtrasAttribute(): array
    {
        $fieldNames = ['Service Areas', 'Languages Spoken', 'Years of Experience', 'Working Hours - Start', 'Working Hours - End'];

        $verification = AgentVerification::where('customer_id', $this->id)
            ->where('form_type', 'become_agent')
            ->first();

        $values = $verification
            ? AgentVerificationValue::where('agent_verification_id', $verification->id)
                ->whereHas('verify_form', function ($query) use ($fieldNames) {
                    $query->whereIn('name', $fieldNames);
                })
                ->with('verify_form:id,name,field_type')
                ->get()
                ->keyBy(function ($value) {
                    return $value->verify_form->name;
                })
            : collect();

        $workingHoursStart = $values->get('Working Hours - Start')?->value;
        $workingHoursEnd = $values->get('Working Hours - End')?->value;

        return [
            'service_areas' => $values->get('Service Areas')?->value ?? [],
            'languages_spoken' => $values->get('Languages Spoken')?->value ?? [],
            'years_of_experience' => $values->get('Years of Experience')?->value,
            'working_hours_start' => $workingHoursStart,
            'working_hours_end' => $workingHoursEnd,
            'working_hours_total_per_day' => $this->calculateWorkingHoursTotal($workingHoursStart, $workingHoursEnd),
        ];
    }

    private function calculateWorkingHoursTotal($start, $end): ?float
    {
        if (empty($start) || empty($end)) {
            return null;
        }

        $startTime = Carbon::createFromFormat('H:i', $start);
        $endTime = Carbon::createFromFormat('H:i', $end);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $endTime->addDay();
        }

        return round($startTime->diffInMinutes($endTime) / 60, 2);
    }

    public function customerCountry()
    {
        return $this->belongsTo(Country::class, 'country_id');
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
            'agent_banner' => $profile?->agent_banner ?? null,
            'watermark_enabled' => $profile?->watermark_enabled ?? false,
            'watermark_image' => $profile?->watermark_image ?? null,
            'watermark_opacity' => $profile?->watermark_opacity ?? 25,
            'watermark_size' => $profile?->watermark_size ?? 10,
            'watermark_style' => $profile?->watermark_style ?? 'tile',
            'watermark_position' => $profile?->watermark_position ?? 'center',
            'watermark_rotation' => $profile?->watermark_rotation ?? 30,
            'created_at' => $profile?->created_at,
            'updated_at' => $profile?->updated_at,
        ];
    }

    /**
     * Replace the agent_profile relation with the resolved array so it
     * survives serialization (loaded relations override same-named attributes
     * in toArray()).
     */
    public function applyResolvedAgentProfile(): array
    {
        $resolved = $this->resolved_agent_profile;
        $this->unsetRelation('agent_profile');
        $this->setAttribute('agent_profile', $resolved);

        return $resolved;
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

    /**
     * Whether this customer (as an agent) can be booked for an appointment.
     * Delegates to the existing is_agent/property/availability accessor used
     * everywhere else (AgentApiController, AuthApiController, ...) so this
     * doesn't drift into a second, inconsistent definition of "bookable".
     */
    public function isAppointmentAvailable(): bool
    {
        return (bool) $this->is_appointment_available;
    }

    /**
     * For LIST queries: adds two lightweight EXISTS subqueries mirroring
     * getIsAppointmentAvailableAttribute()'s property/availability conditions,
     * without loading full property/agent_availabilities rows into memory or
     * the response. Pair with resolveAppointmentAvailability() per row, then
     * discard the two intermediate columns before returning — callers should
     * never expose them directly.
     */
    public function scopeWithAppointmentAvailabilityChecks($query)
    {
        return $query->withExists([
            'property as has_qualifying_property_for_appointment' => function ($q) {
                $q->where('status', 1)->where('request_status', 'approved')->whereIn('propery_type', [0, 1]);
            },
            'agent_availabilities as has_active_availability_for_appointment' => function ($q) {
                $q->where('is_active', 1);
            },
        ]);
    }

    /**
     * Resolves the boolean from the two withAppointmentAvailabilityChecks()
     * columns when present, otherwise falls back to isAppointmentAvailable()
     * (fresh queries or eager-loaded relations, whichever applies).
     */
    public function resolveAppointmentAvailability(): bool
    {
        if (! $this->is_agent) {
            return false;
        }

        if (array_key_exists('has_qualifying_property_for_appointment', $this->attributes)
            && array_key_exists('has_active_availability_for_appointment', $this->attributes)) {
            return (bool) $this->getRawOriginal('has_qualifying_property_for_appointment')
                && (bool) $this->getRawOriginal('has_active_availability_for_appointment');
        }

        return $this->isAppointmentAvailable();
    }

    /**
     * Verification status fields for a given chat role_context ('user',
     * 'agent', or 'admin'), matching the field naming already used by
     * PropertyApiController::getPropertiesOnMap for consistency.
     */
    public function getVerificationStatusesForRole(?string $roleContext): array
    {
        if ($roleContext === 'admin') {
            return [
                'is_agent' => false,
                'is_agent_verified' => true,
                'is_user_verified' => true,
                'become_agent_status' => 'approved',
                'agent_verification_status' => 'approved',
                'user_verification_status' => 'approved',
            ];
        }

        return [
            'is_agent' => (bool) $this->is_agent,
            'is_agent_verified' => (bool) $this->is_agent_verified,
            'is_user_verified' => (bool) $this->is_user_verified,
            'become_agent_status' => $this->becomeAgent->status ?? 'not_applied',
            'agent_verification_status' => $this->verifyAgent->status ?? 'not_applied',
            'user_verification_status' => $this->verifyCustomer->status ?? 'not_applied',
        ];
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

    public function getIsUserVerifiedAttribute(): bool
    {
        $verification = $this->verifyCustomer;

        // 1. Permanent manual/admin approval
        if ($verification && $verification->status === 'approved' && in_array($verification->source, ['admin', 'manual'])) {
            return true;
        }

        // 2. Subscription-driven verification: requires an active subscription
        if ($verification && $verification->status === 'approved' && $verification->source === 'subscription') {
            return $this->user_purchased_package()->onlyActive()->exists();
        }

        // 3. Fallback: Check if user currently has an active subscription
        return $this->user_purchased_package()->onlyActive()->exists();
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
        if (! $this->is_agent) {
            return false;
        }

        $propertyExists = $this->relationLoaded('property')
            ? $this->property->contains(fn ($property) => $property->status == 1 && $property->request_status === 'approved' && in_array($property->getRawOriginal('propery_type'), [0, 1]))
            : $this->property()->where(['status' => 1, 'request_status' => 'approved'])->whereIn('propery_type', [0, 1])->exists();

        $appointmentScheduleExists = $this->relationLoaded('agent_availabilities')
            ? $this->agent_availabilities->contains(fn ($availability) => $availability->is_active)
            : $this->agent_availabilities()->where('is_active', 1)->exists();

        return $propertyExists && $appointmentScheduleExists;
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
