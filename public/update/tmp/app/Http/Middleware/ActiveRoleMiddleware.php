<?php

namespace App\Http\Middleware;

use App\Services\ActiveRoleService;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveRoleMiddleware
{
    protected ActiveRoleService $activeRoleService;

    public function __construct(ActiveRoleService $activeRoleService)
    {
        $this->activeRoleService = $activeRoleService;
    }

    public function handle(Request $request, Closure $next)
    {
        $rawRole = $request->header('X-Active-Role');

        // If header is missing or empty, default to 'user' silently
        if ($rawRole === null || trim($rawRole) === '') {
            $this->activeRoleService->setRole('user');
            // Merge into request as 'user_active_role' so callers receive consistent key
            $request->merge(['user_active_role' => 'user']);

            return $next($request);
        }

        $role = strtolower(trim($rawRole));

        // Reject invalid values
        if (! in_array($role, ['user', 'agent'])) {
            return ApiResponseService::errorResponse(
                'Invalid X-Active-Role value. Must be user or agent.',
                null,
                400
            );
        }

        // If requesting agent mode, verify the user is actually an agent
        if ($role === 'agent') {
            if (Auth::guard('sanctum')->check() && Auth::guard('sanctum')->user()->is_agent) {
                $request->merge(['user_active_role' => 'agent']);
                // $this->activeRoleService->setRole('agent');
            } else {
                return ApiResponseService::errorResponse(
                    'Unauthorized. You must be an agent to use agent mode.',
                    null,
                    403
                );
            }
        } else {
            $request->merge(['user_active_role' => 'user']);
            // $this->activeRoleService->setRole('user');
        }

        // Merge active role into request for easy access
        // $request->merge(['user_active_role' => $this->activeRoleService->getRole()]);

        return $next($request);
    }
}
