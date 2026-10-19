<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;

class CheckLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        if (auth()->user()->status != 0) {
            return $next($request);
        } else {
            if (auth()->user()->status == 0) {
                return back()->with('inactive', 'Credentials not match');
            } else {
                return back()->with('inactive', 'credentials Not match');
            }

        }
        // return redirect('/');

    }
}
