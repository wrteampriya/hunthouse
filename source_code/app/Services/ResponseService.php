<?php

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResponseService
{
    /**
     * @return Application|RedirectResponse|Redirector|true
     */
    public static function noPermissionThenRedirect($permission)
    {
        if (! Auth::user()->can($permission)) {
            return redirect(route('home'))->withErrors([
                'message' => trans("You Don't have enough permissions"),
            ])->send();
        }

        return true;
    }

    /**
     * @return true
     */
    public static function noPermissionThenSendJson($permission)
    {
        if (! Auth::user()->can($permission)) {
            self::errorResponse("You Don't have enough permissions");
        }

        return true;
    }

    /**
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector|true
     */
    // Check user role
    public static function noRoleThenRedirect($role)
    {
        if (! Auth::user()->hasRole($role)) {
            return redirect(route('home'))->withErrors([
                'message' => trans("You Don't have enough permissions"),
            ])->send();
        }

        return true;
    }

    /**
     * @return bool|Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
    public static function noAnyRoleThenRedirect(array $role)
    {
        if (! Auth::user()->hasAnyRole($role)) {
            return redirect(route('home'))->withErrors([
                'message' => trans("You Don't have enough permissions"),
            ])->send();
        }

        return true;
    }

    //    /**
    //     * @param $role
    //     * @return true
    //     */
    //    public static function noRoleThenSendJson($role)
    //    {
    //        if (!Auth::user()->hasRole($role)) {
    //            self::errorResponse("You Don't have enough permissions");
    //        }
    //        return true;
    //    }

    /**
     * If User don't have any of the permission that is specified in Array then Redirect will happen
     *
     * @return RedirectResponse|true
     */
    public static function noAnyPermissionThenRedirect(array $permissions)
    {
        if (! Auth::user()->canany($permissions)) {
            return redirect()->back()->withErrors([
                'message' => trans("You Don't have enough permissions"),
            ])->send();
        }

        return true;
    }

    /**
     * If User don't have any of the permission that is specified in Array then Json Response will be sent
     *
     * @return true
     */
    public static function noAnyPermissionThenSendJson(array $permissions)
    {
        if (! Auth::user()->canany($permissions)) {
            self::errorResponse("You Don't have enough permissions");
        }

        return true;
    }

    /**
     * @return void
     */
    public static function successResponse(string $message = 'Success', $data = null, array $customData = [], $code = null)
    {
        response()->json(array_merge([
            'error' => false,
            'message' => trans($message),
            'data' => $data,
            'code' => $code ?? config('constants.RESPONSE_CODE.SUCCESS'),
        ], $customData), $code ?? config('constants.RESPONSE_CODE.SUCCESS'))->send();
        exit();
    }

    /**
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
    public static function successRedirectResponse(string $message = 'success', $url = null)
    {
        return isset($url) ? redirect($url)->with([
            'success' => trans($message),
        ])->send() : redirect()->back()->with([
            'success' => trans($message),
        ])->send();
    }

    /**
     * @param  string  $message  - Pass the Translatable Field
     * @param  null  $data
     * @param  null  $code
     * @param  null  $e
     * @return void
     */
    public static function errorResponse(string $message = 'Error Occurred', $data = null, $code = null, $e = null)
    {
        $details = $message;
        if (! empty($e) && is_object($e)) {
            if (method_exists($e, 'getMessages')) {
                $details .= ' | Validation Errors: '.json_encode($e->getMessages());
            } else {
                $details .= ' | Exception: '.$e->getMessage().' --> '.$e->getFile().' At Line : '.$e->getLine();
            }
        }

        response()->json([
            'error' => true,
            'message' => trans($message),
            'data' => $data,
            'code' => $code ?? config('constants.RESPONSE_CODE.EXCEPTION_ERROR'),
            'details' => $details,
        ], $code ?? config('constants.RESPONSE_CODE.EXCEPTION_ERROR'))->send();
        exit();
    }

    /**
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
    public static function errorRedirectResponse($url = null, string $message = 'Error Occurred')
    {
        return isset($url) ? redirect($url)->with([
            'error' => trans($message),
        ])->send() : redirect()->back()->with([
            'error' => trans($message),
        ])->send();
    }

    /**
     * @param  null  $data
     * @param  null  $code
     * @return void
     */
    public static function warningResponse(string $message = 'Error Occurred', $data = null, $code = null)
    {
        response()->json([
            'error' => false,
            'warning' => true,
            'code' => $code,
            'message' => trans($message),
            'data' => $data,
        ], $code ?? config('constants.RESPONSE_CODE.SUCCESS'))->send();
        exit();
    }

    /**
     * @param  null  $data
     * @return void
     */
    public static function validationError(string $message = 'Error Occurred', $data = null)
    {
        self::errorResponse($message, $data, config('constants.RESPONSE_CODE.VALIDATION_ERROR'));
    }

    /**
     * @param  null  $data
     * @return void
     */
    public static function validationErrorRedirect($url = null, string $message = 'Error Occurred')
    {
        return (($url != null) ? redirect($url) : redirect()->back())->with([
            'error' => trans($message),
        ])->send();
    }

    /**
     * @return void
     */
    public static function logErrorResponse($e, string $logMessage = ' ', string $responseMessage = 'Error Occurred', bool $jsonResponse = true)
    {
        Log::error($logMessage.' '.$e->getMessage().'---> '.$e->getFile().' At Line : '.$e->getLine());
        if ($jsonResponse && config('app.debug')) {
            self::errorResponse($responseMessage, null, null, $e);
        }
    }

    /**
     * @return void
     */
    public static function logErrorRedirectResponse($e, string $logMessage = ' ', string $responseMessage = 'Error Occurred')
    {
        Log::error($logMessage.' '.$e->getMessage().'---> '.$e->getFile().' At Line : '.$e->getLine());
        if (config('app.debug')) {
            self::errorRedirectResponse(null, $responseMessage);
        }

    }
}
