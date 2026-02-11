<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Set site meta information (title, description).
 *
 * Replaces QubitMeta. Stores site title and description in ConfigService
 * for use in templates. Elasticsearch errors are caught and handled.
 */
class MetaMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Store meta values in ConfigService for template access
        ConfigService::set('site_title', ConfigService::get('siteTitle', ''));
        ConfigService::set('site_description', ConfigService::get('siteDescription', ''));

        try {
            return $next($request);
        } catch (\Exception $e) {
            // Handle Elasticsearch errors gracefully
            $interfaces = class_implements($e, true);
            if (is_array($interfaces)
                && in_array('Elastica\Exception\ExceptionInterface', $interfaces)) {
                return response()->json([
                    'error' => 'Search service error',
                    'message' => $e->getMessage(),
                ], 503);
            }

            throw $e;
        }
    }
}
