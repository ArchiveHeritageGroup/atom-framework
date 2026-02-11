<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Force HTTPS for authenticated and login routes.
 *
 * Replaces QubitSslRequirementFilter. Redirects to HTTPS when
 * the app_require_ssl_admin setting is enabled and the user is
 * authenticated or accessing the login page.
 */
class ForceHttpsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Skip if already secure or SSL not required
        if ($request->isSecure()
            || !ConfigService::getBool('require_ssl_admin', false)) {
            return $next($request);
        }

        $path = trim($request->getPathInfo(), '/');
        $isAuthenticated = $request->session()->get('authenticated', false);
        $isLoginRoute = ('user/login' === $path);

        if ($isAuthenticated || $isLoginRoute) {
            $secureUrl = str_replace('http://', 'https://', $request->fullUrl());

            return redirect($secureUrl, 301);
        }

        return $next($request);
    }
}
