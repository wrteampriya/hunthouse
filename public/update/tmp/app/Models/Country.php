<?php

namespace App\Models;

use App\Services\CachingService;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'code',
        'default_language_id',
        'currency_id',
        'latitude',
        'longitude',
        'status',
    ];

    public function defaultLanguage()
    {
        return $this->belongsTo(Language::class, 'default_language_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The language to apply when this country is selected: its own mapped
     * language, or the system-wide default language if none was assigned.
     */
    public function resolvedLanguage(): ?Language
    {
        return $this->defaultLanguage ?? (new CachingService)->getDefaultLanguage();
    }

    /**
     * The currency to apply when this country is selected: its own mapped
     * currency, or the system-wide default currency if none was assigned.
     */
    public function resolvedCurrency(): ?Currency
    {
        return $this->currency ?? (new CachingService)->getDefaultCurrency();
    }

    /**
     * The language a listing MUST have content in, given its country — null
     * when no country is selected or the country's resolved language isn't
     * known (falls back to the system default language, see
     * HelperService::effectiveRequiredLanguageId).
     */
    public static function requiredLanguageIdFor(?int $countryId): ?int
    {
        if (empty($countryId)) {
            return null;
        }

        $country = self::find($countryId);
        if (! $country) {
            return null;
        }

        return optional($country->resolvedLanguage())->id;
    }

    /**
     * Find an active country by its ISO 3166-1 alpha-2 code (case-insensitive).
     */
    public static function matchByCode(?string $isoCode): ?self
    {
        if (empty($isoCode)) {
            return null;
        }

        return self::where('status', 1)->where('code', strtoupper($isoCode))->first();
    }

    /**
     * Resolve a listing's country_id from its coordinates, so API clients
     * never need to compute/send country_id themselves.
     */
    public static function resolveIdForPoint($latitude, $longitude): ?int
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return optional(self::nearestTo((float) $latitude, (float) $longitude))->id;
    }

    /**
     * The active, geo-located country nearest to a given point, used when a
     * picked map location falls outside every admin-added country.
     */
    public static function nearestTo(float $latitude, float $longitude): ?self
    {
        return self::where('status', 1)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->sortBy(function ($country) use ($latitude, $longitude) {
                return self::haversineKm($latitude, $longitude, (float) $country->latitude, (float) $country->longitude);
            })
            ->first();
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
