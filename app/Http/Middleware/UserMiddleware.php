<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class userMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {

        if ($request->user_active_role !== 'user') {
            return ApiResponseService::errorResponse('Unauthorized. Active role must be user.' , null , null , null , null, [] , config('constants.API_RESPONSE_KEY.REQUIRED_USER_ROLE'));
        }


        return $next($request);
    }
}
