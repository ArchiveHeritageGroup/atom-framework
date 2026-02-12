<?php

namespace AtomFramework\Http\Middleware;

use AtomExtensions\Services\AclService;
use AtomExtensions\Services\UserService;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Per-request authentication validation.
 *
 * Replicates myUser::initialize() behavior:
 *   1. Check session timeout (30 min default)
 *   2. Validate authenticated user still exists and is active
 *   3. Cache user object on SfUserAdapter
 *   4. Set atom_authenticated cookie for reverse proxy cache bypass
 *   5. Set user on AclService for permission checks
 */
class AuthMiddleware
{
    /** @var int Default session timeout in seconds (30 minutes) */
    private const DEFAULT_TIMEOUT = 1800;

    public function handle(Request $request, Closure $next)
    {
        if (!SfContextAdapter::hasInstance()) {
            return $next($request);
        }

        $sfUser = SfContextAdapter::getInstance()->getUser();

        // Check for reverse proxy culture override
        $cultureHeader = $request->header('X-Atom-Culture');
        if ($cultureHeader && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $cultureHeader)) {
            $sfUser->setCulture($cultureHeader);
        }

        if ($sfUser->isAuthenticated()) {
            // Check timeout
            $timeout = (int) ConfigService::get('app_session_timeout', self::DEFAULT_TIMEOUT);
            if ($sfUser->isTimedOut($timeout)) {
                $sfUser->signOut();

                return $this->addAuthCookie($next($request), false);
            }

            // Validate user still exists and is active
            $userId = $sfUser->getUserID();
            if ($userId) {
                try {
                    $user = UserService::getById($userId);
                } catch (\Exception $e) {
                    $user = null;
                }

                if (!$user || !$user->active) {
                    $sfUser->signOut();

                    return $this->addAuthCookie($next($request), false);
                }

                // Cache user object on adapter
                $sfUser->user = $user;

                // Set user on AclService for permission checks
                AclService::setUser($user);
            }

            // Update last request timestamp
            $sfUser->updateLastRequest();
        }

        $response = $next($request);

        // Set atom_authenticated cookie for reverse proxy cache bypass
        $isAuth = $sfUser->isAuthenticated();

        return $this->addAuthCookie($response, $isAuth);
    }

    /**
     * Add or remove the atom_authenticated cookie on the response.
     */
    private function addAuthCookie($response, bool $authenticated)
    {
        if ($authenticated) {
            $response->headers->setCookie(
                new \Symfony\Component\HttpFoundation\Cookie(
                    'atom_authenticated',
                    '1',
                    0,
                    '/',
                    '',
                    true,
                    false,
                    false,
                    'Lax'
                )
            );
        } else {
            $response->headers->clearCookie('atom_authenticated', '/', '');
        }

        return $response;
    }
}
