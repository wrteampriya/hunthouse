<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LogLevel;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            $details = '';

            if (method_exists($exception, 'getMessages')) {
                // For validation exceptions
                $details = json_encode($exception->getMessages());
            } else {
                $details = $exception->getMessage();
            }

            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
                'details' => $details,
                'code' => 500,
            ], 500);
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => 'User is not authenticated',
                'details' => 'Authentication required',
                'code' => 401,
            ], 401);
        }

        return $request->expectsJson()
            ? response()->json(['error' => true, 'message' => 'User is not authenticated'], 401)
            : redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}
