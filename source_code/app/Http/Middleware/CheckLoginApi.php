<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLoginApi
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('sanctum')->check() && auth()->guard('sanctum')->user()->isActive == 0) {
            ApiResponseService::errorResponse('Your account has been deactivated', null, config('constants.RESPONSE_CODE.UNAUTHORIZED'), null, ['key' => config('constants.API_RESPONSE_KEY.ACCOUNT_DEACTIVATED', 'accountDeactivated')]);
        }

        return $next($request);
    }
}
