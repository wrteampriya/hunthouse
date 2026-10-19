<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // if (Auth::guard('sanctum')->check() && Auth::guard('sanctum')->user()->is_agent) {
        //     return $next($request);
        // }

        // return ApiResponseService::errorResponse('Unauthorized. You must be an agent to access this action.');

        $user = Auth::guard('sanctum')->user();

        if (! $user || ! $user->is_agent) {
            return ApiResponseService::errorResponse('Unauthorized. You must be an agent.');
        }

        if ($request->user_active_role !== 'agent') {
            return ApiResponseService::errorResponse('Unauthorized. Active role must be agent.', null, null, null, null, [], config('constants.API_RESPONSE_KEY.REQUIRED_AGENT_ROLE'));
        }

        return $next($request);

    }
}
