<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Limit browse/search results per page.
 *
 * Replaces QubitLimitResults. Caps the request's 'limit' parameter
 * to the configured app_hits_per_page value.
 */
class LimitResultsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $hitsPerPage = ConfigService::getInt('hits_per_page', 10);
        $limit = $request->input('limit');

        if (null !== $limit) {
            if (!ctype_digit((string) $limit) || (int) $limit > $hitsPerPage) {
                $request->merge(['limit' => $hitsPerPage]);
            }
        }

        return $next($request);
    }
}
