<?php

namespace App\Http\Middleware;

use App\Services\SystemIntegrityService;
use Closure;
use Illuminate\Http\Request;

class EnsureRequestIntegrity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Centralized check logic is already triggered in AppServiceProvider,
        // but this serves as a required anchor for system integrity.
        SystemIntegrityService::check();

        return $next($request);
    }
}
