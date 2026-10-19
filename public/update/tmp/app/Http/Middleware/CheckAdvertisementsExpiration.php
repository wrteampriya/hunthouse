<?php

namespace App\Http\Middleware;

use App\Models\Advertisement;
use Carbon\Carbon;
use Closure;
use dacoto\EnvSet\Facades\EnvSet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckAdvertisementsExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            // Check Advertisement and make expire to those advertisements whose expiry date is passed by today's date
            $today = Carbon::today();
            if (! Cache::has('ads_expired_today')) {
                // Check if the request URL contains "/install"
                if (strpos($request->url(), '/install') !== false) {
                    // If it does, skip the middleware
                    return $next($request);
                }

                // Change Cache Driver to file
                if (EnvSet::keyExists('CACHE_DRIVER') && env('CACHE_DRIVER') != 'file') {
                    EnvSet::setKey('CACHE_DRIVER', 'file');
                    EnvSet::save();
                }

                // Check DB connection
                DB::connection()->getPdo();

                // Expire advertisements
                Advertisement::where('end_date', '<', $today)->where('status', '!=', '3')->update(['status' => '3', 'is_enable' => 0]);

                // Set cache to avoid repetitive updates
                Cache::put('ads_expired_today', true, Carbon::now()->endOfDay());
            }
        } catch (Exception $e) {
            Log::error('CheckAdvertisementExpiration Middleware issue');

            return $next($request);
        }

        return $next($request);
    }
}
