<?php

namespace App\Traits;

use App\Services\HelperService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

trait HasAppTimezone
{
    // Define date fields that should be converted
    protected $dateFields = [
        'created_at',
        'updated_at',
        'deleted_at',
        'date',
        'time',
        'datetime',
        'timestamp',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'start_datetime',
        'end_datetime',
        'start_timestamp',
        'end_timestamp',
        'start_at',
        'end_at',
        'blocked_at',
        'unblocked_at',
    ];

    /**
     * Boot the trait.
     * This is called automatically by Laravel when the model using this trait is being booted.
     */
    public static function bootHasAppTimezone()
    {
        static::retrieved(function ($model) {
            $model->convertDatesToAppTimezone();
        });

        // Convert timestamps back to UTC before saving
        static::saving(function ($model) {
            $model->convertDatesToUTC();
        });

        // Convert dates to UTC before updating
        static::updating(function ($model) {
            $model->convertDatesToUTC();
        });
    }

    /**
     * Convert dates to application timezone
     */
    protected function convertDatesToAppTimezone()
    {
        foreach ($this->dates as $field) {
            if (in_array($field, $this->dateFields)) {
                try {
                    if (isset($this->attributes[$field])) {
                        $value = $this->getAttributeValue($field);
                        $value = HelperService::toAppTimezone(new Carbon($value));
                        $this->attributes[$field] = $value;
                    }
                } catch (Exception $e) {
                    // Log the error instead of silently returning true
                    Log::error('Error converting date to app timezone: '.$e->getMessage(), [
                        'field' => $field,
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                    ]);
                }
            }
        }
    }

    /**
     * Convert dates back to UTC before saving to database
     */
    protected function convertDatesToUTC()
    {
        foreach ($this->dates as $field) {
            if (in_array($field, $this->dateFields)) {
                try {
                    // For updates, never update created_at - remove it from dirty attributes
                    if ($field === 'created_at' && $this->exists) {
                        // Remove created_at from dirty attributes to prevent it from being updated
                        unset($this->attributes[$field]);

                        continue;
                    }

                    // Convert timestamps back to UTC if they exist in attributes
                    if (isset($this->attributes[$field])) {
                        $value = $this->getAttributeValue($field);

                        // If it's a Carbon instance or string, convert to UTC
                        if ($value instanceof Carbon) {
                            $this->attributes[$field] = $value->setTimezone('UTC')->format('Y-m-d H:i:s');
                        } elseif (is_string($value)) {
                            // Parse the value assuming it's in app timezone and convert to UTC
                            $timezone = HelperService::toAppTimezoneValue();
                            $carbon = Carbon::parse($value, $timezone);
                            $this->attributes[$field] = $carbon->setTimezone('UTC')->format('Y-m-d H:i:s');
                        }
                    }
                } catch (Exception $e) {
                    // Log the error
                    Log::error('Error converting date to UTC: '.$e->getMessage(), [
                        'field' => $field,
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                    ]);
                }
            }
        }
    }
}
