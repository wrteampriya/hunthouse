<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Console\View\Components\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DemoMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Exclude URLs
        $exclude_uri = [
            '/login',
            '/logout',
            '/api/user_signup',
            '/api/before-logout',
            '/api/post_property',
            '/api/update_post_property',
            '/api/post_project',
            '/api/add_favourite',
            '/api/system_settings',
            '/api/gemini/generate-meta',
            '/api/gemini/generate-description',
            '/api/apply-agent-verification',
            '/api/get-agent-verification-form',
            '/api/get-agent-verification-form-fields',
            '/api/get-agent-verification-form-values',

            // Allow demo users to approve/reject verification requests
            '/agent-verification/update-verification-status',
            '/user-verification/update-verification-status',

            //Allow demo users to update their agent profile
            // '/api/update-agent-profile',
            // '/api/update-user-profile',
        ];

        // Exclude Emails
        $excludeEmails = [
            'superadmin@gmail.com',
        ];

        /**
         * Conditions
         * 1. Demo Mode is True.
         * 2. Request is not get
         * 3. Authenticated user
         * 4. Authenticated user's email is not in excluded emails
         * 5. Request URL is not in Excluded URL
         */
        if (env('DEMO_MODE') && ! $request->isMethod('get')) {
            if (Auth::check()) {
                if (in_array(Auth::user()->email, $excludeEmails)) {
                    return $next($request);
                } elseif (Auth::user()->auth_id != '6a1Zdl2TxORQGbCazj4XDGfgBBG3' && ! in_array(Auth::user()->email, ['wrteamdemo@gmail.com', 'admin@gmail.com']) && ! (Auth::user()->country_code == '91' && Auth::user()->mobile == '1234567890')) {
                    return $next($request);
                } else {
                    if (! in_array($request->getRequestUri(), $exclude_uri)) {
                        if ($request->ajax()) {
                            $response['error'] = true;
                            $response['message'] = trans('This is not allowed in the Demo Version');
                            $response['code'] = 403;

                            return response()->json($response);
                        } elseif (request()->wantsJson() || Str::startsWith(request()->path(), 'api')) {
                            $response['error'] = true;
                            $response['message'] = trans('This is not allowed in the Demo Version');
                            $response['code'] = 403;

                            return response()->json($response);
                        } else {
                            return back()->with('error', trans('This is not allowed in the Demo Version'));
                        }
                    }
                }
            } else {
                if ($request->getRequestUri() == '/api/update-number-password') {
                    if ($request->firebase_id == '6a1Zdl2TxORQGbCazj4XDGfgBBG3' && $request->mobile == '1234567890' && $request->country_code == '91') {
                        $response['error'] = true;
                        $response['message'] = trans('This is not allowed in the Demo Version');
                        $response['code'] = 403;

                        return response()->json($response);
                    }
                }
            }
        }

        return $next($request);
    }
}
