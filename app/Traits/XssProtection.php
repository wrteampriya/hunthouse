<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;


trait XssProtection
{
    /**
     * Fields that should be protected from XSS attacks
     * This property must be defined in the model that uses this trait
     */
    // protected $xssProtectedFields = []; // Removed to avoid conflicts

    /**
     * Boot the trait and set up model events
     */
    protected static function bootXssProtection()
    {
        // Encode data before creating
        static::creating(function ($model) {
            $model->encodeXssFields();
        });

        // Encode before updating
        static::updating(function ($model) {
            $model->encodeXssFields();
        });

        /**
         * Decode XSS protected fields after retrieving
         */
        static::retrieved(function ($model) {
            foreach ($model->xssProtectedFields as $field) {
                if(isset($model->attributes[$field]) && !empty($model->attributes[$field])){
                    $model->attributes[$field] = $model->decodeXss($model->attributes[$field]);
                }
            }
        });
    }

    /**
     * Encode XSS vulnerable fields before saving
     */
    public function encodeXssFields()
    {
        if (!isset($this->xssProtectedFields) || empty($this->xssProtectedFields)) {
            return;
        }

        foreach ($this->xssProtectedFields as $field) {
            if (isset($this->attributes[$field]) && !empty($this->attributes[$field])) {
                $this->attributes[$field] = $this->encodeXss($this->attributes[$field]);
            }
        }
    }

    /**
     * Decode XSS protected fields after retrieving
     */
    public function decodeXssFields()
    {
        if (!isset($this->xssProtectedFields) || empty($this->xssProtectedFields)) {
            return;
        }

        foreach ($this->xssProtectedFields as $field) {
            if (isset($this->attributes[$field]) && !empty($this->attributes[$field])) {
                $this->attributes[$field] = $this->decodeXss($this->attributes[$field]);
            }
        }
    }

    /**
     * Encode string to prevent XSS attacks
     */
    protected function encodeXss($value)
    {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $value;
    }

    /**
     * Decode string for safe display
     */
    protected function decodeXss($value)
    {
        if (is_string($value)) {
            return htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
        }
        return $value;
    }

    /**
     * Get original value without XSS protection (for admin purposes)
     */
    public function getRawXssValue($field)
    {
        if (isset($this->xssProtectedFields) && in_array($field, $this->xssProtectedFields)) {
            return htmlspecialchars_decode($this->getRawOriginal($field), ENT_QUOTES | ENT_HTML5);
        }
        return $this->getRawOriginal($field);
    }

    /**
     * Manually encode a field value
     */
    public function encodeField($field, $value)
    {
        if (isset($this->xssProtectedFields) && in_array($field, $this->xssProtectedFields)) {
            return $this->encodeXss($value);
        }
        return $value;
    }

    /**
     * Manually decode a field value
     */
    public function decodeField($field, $value)
    {
        if (isset($this->xssProtectedFields) && in_array($field, $this->xssProtectedFields)) {
            return $this->decodeXss($value);
        }
        return $value;
    }

    /**
     * Get a field value safely decoded for display (use with caution)
     * This should only be used when you need to display the content safely
     */
    public function getSafeField($field)
    {
        if (isset($this->attributes[$field])) {
            if (isset($this->xssProtectedFields) && in_array($field, $this->xssProtectedFields)) {
                return $this->decodeXss($this->attributes[$field]);
            }
            return $this->attributes[$field];
        }
        return null;
    }

    /**
     * Get all XSS protected fields safely decoded (use with caution)
     * This should only be used when you need to display the content safely
     */
    public function getSafeFields()
    {
        $safeData = [];
        if (isset($this->xssProtectedFields)) {
            foreach ($this->xssProtectedFields as $field) {
                if (isset($this->attributes[$field])) {
                    $safeData[$field] = $this->decodeXss($this->attributes[$field]);
                }
            }
        }
        return $safeData;
    }
}   
