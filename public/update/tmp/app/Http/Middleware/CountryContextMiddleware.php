<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Reads the selected country from the X-Country-Id header (the frontend's
 * preferred transport) and merges it into the request as `country_id`, so
 * every existing `$request->country_id` / `$request->filled('country_id')`
 * call site across the API keeps working unchanged. Mirrors the
 * X-Active-Role -> user_active_role pattern in ActiveRoleMiddleware.
 *
 * An explicit `country_id` already present in the request body/query wins —
 * the header is only a browsing-context fallback for requests that don't
 * specify one. This matters on write endpoints (post_property, post_project,
 * update_post_property, ...) where the body's country_id is the actual
 * country the listing belongs to, which must not be overridden by whatever
 * country the client happens to be browsing in.
 * Invalid header values (non-numeric) are ignored rather than erroring — country_id
 * is optional everywhere it's used, so a bad header shouldn't fail the request.
 */
class CountryContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->filled('country_id')) {
            return $next($request);
        }

        $headerCountryId = $request->header('X-Country-Id');

        if ($headerCountryId !== null && trim($headerCountryId) !== '' && ctype_digit(trim($headerCountryId))) {
            $request->merge(['country_id' => (int) trim($headerCountryId)]);
        }

        return $next($request);
    }
}
