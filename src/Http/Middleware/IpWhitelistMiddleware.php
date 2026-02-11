<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * IP whitelist middleware for admin access.
 *
 * Replaces QubitLimitIpFilter. Checks the app_limit_admin_ip setting
 * and restricts authenticated user access to whitelisted IP addresses/ranges.
 */
class IpWhitelistMiddleware
{
    private const LOGOUT_MODULES = ['user', 'oidc', 'cas'];

    public function handle(Request $request, Closure $next)
    {
        $limitSetting = ConfigService::get('limit_admin_ip', '');
        $limit = array_filter(explode(';', $limitSetting));

        // Bypass if no IP restriction configured
        if (empty($limit)) {
            return $next($request);
        }

        // Bypass for logout routes
        $path = trim($request->getPathInfo(), '/');
        foreach (self::LOGOUT_MODULES as $module) {
            if ($path === $module . '/logout') {
                return $next($request);
            }
        }

        // Check IP only for authenticated sessions
        $isAuthenticated = $request->session()->get('authenticated', false);
        if ($isAuthenticated && !$this->isAllowed($request->ip(), $limit)) {
            return response('Access denied', 403);
        }

        return $next($request);
    }

    /**
     * Check if the given IP address is in the whitelist.
     */
    private function isAllowed(string $address, array $limit): bool
    {
        $addressBinary = inet_pton($address);
        if (false === $addressBinary) {
            return false;
        }

        foreach ($limit as $item) {
            $parts = preg_split('/[,-]/', $item);
            $parts = array_map('trim', $parts);

            // Single IP
            if (1 === count($parts)) {
                $limitBinary = inet_pton($parts[0]);
                if ($addressBinary === $limitBinary
                    && strlen($addressBinary) === strlen($limitBinary)) {
                    return true;
                }
            }

            // IP range
            if (2 === count($parts)) {
                $firstBinary = inet_pton($parts[0]);
                $lastBinary = inet_pton($parts[1]);

                if (false !== $firstBinary
                    && false !== $lastBinary
                    && strlen($addressBinary) === strlen($firstBinary)
                    && $addressBinary >= $firstBinary
                    && $addressBinary <= $lastBinary) {
                    return true;
                }
            }
        }

        return false;
    }
}
