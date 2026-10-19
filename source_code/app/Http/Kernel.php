<?php

namespace App\Http;

use App\Http\Middleware\ActiveRoleMiddleware;
use App\Http\Middleware\AgentMiddleware;
use App\Http\Middleware\ApiLocalizationMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckAdvertisementsExpiration;
use App\Http\Middleware\CheckLogin;
use App\Http\Middleware\CheckLoginApi;
use App\Http\Middleware\DemoMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureRequestIntegrity;
use App\Http\Middleware\LanguageManager;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\UserMiddleware;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    protected $middleware = [
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        CheckAdvertisementsExpiration::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            EnsureRequestIntegrity::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            PreventBackHistory::class,
            DemoMiddleware::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
            ApiLocalizationMiddleware::class,
            DemoMiddleware::class,
            CheckLoginApi::class,
            ActiveRoleMiddleware::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'checkLogin' => CheckLogin::class,
        'language' => LanguageManager::class,
        'api.localization' => ApiLocalizationMiddleware::class,
        'user' => UserMiddleware::class,
        'agent' => AgentMiddleware::class,
        'active-role' => ActiveRoleMiddleware::class,
        // 'checkAuth' => \App\Http\Middleware\CheckAuth::class,
    ];
}
