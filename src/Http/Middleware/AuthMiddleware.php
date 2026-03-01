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
 *   6. Prevent session fixation (regenerate session ID on login/logout transitions)
 */
class AuthMiddleware
{
    /** @var int Default session timeout in seconds (30 minutes) */
    private const DEFAULT_TIMEOUT = 1800;

    /** Session key tracking which user ID the session was regenerated for */
    private const SESSION_AUTH_KEY = '_security_auth_id';

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
                $this->regenerateSession(null);

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
                    $this->regenerateSession(null);

                    return $this->addAuthCookie($next($request), false);
                }

                // Cache user object on adapter
                $sfUser->user = $user;

                // Set user on AclService for permission checks
                AclService::setUser($user);

                // Session fixation prevention: regenerate session ID on login transition
                $this->regenerateSessionOnLogin($userId);
            }

            // Update last request timestamp
            $sfUser->updateLastRequest();
        } else {
            // User is not authenticated — if they WERE authenticated, session was invalidated
            $prevAuthId = $_SESSION[self::SESSION_AUTH_KEY] ?? null;
            if ($prevAuthId !== null) {
                $this->regenerateSession(null);
            }
        }

        $response = $next($request);

        // Set atom_authenticated cookie for reverse proxy cache bypass
        $isAuth = $sfUser->isAuthenticated();

        return $this->addAuthCookie($response, $isAuth);
    }

    /**
     * Regenerate session ID on login transition (session fixation prevention).
     *
     * Detects when the authenticated user ID differs from the stored session
     * auth ID, indicating a login occurred (via Symfony or Laravel). Regenerates
     * the session ID so any pre-authentication session ID becomes invalid.
     */
    private function regenerateSessionOnLogin(int $userId): void
    {
        $prevAuthId = $_SESSION[self::SESSION_AUTH_KEY] ?? null;

        if ($prevAuthId === null || (int) $prevAuthId !== $userId) {
            $this->regenerateSession($userId);
        }
    }

    /**
     * Regenerate the PHP session ID and update the auth tracking key.
     *
     * @param int|null $userId The authenticated user ID, or null on logout
     */
    private function regenerateSession(?int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION[self::SESSION_AUTH_KEY] = $userId;
        }
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
                    0,      // session cookie (no expiry)
                    '/',
                    '',
                    true,   // secure
                    true,   // httponly (was false — security fix)
                    false,  // raw
                    'Lax'   // samesite
                )
            );
        } else {
            $response->headers->clearCookie('atom_authenticated', '/', '');
        }

        return $response;
    }
}
