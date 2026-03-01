<?php

namespace AtomFramework\Http\Middleware;

use AtomExtensions\Services\AclService;
use AtomExtensions\Services\UserService;
use AtomFramework\Core\Security\PasswordPolicyService;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Database\Capsule\Manager as DB;
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
                $isNewLogin = $this->regenerateSessionOnLogin($userId);

                // On fresh login, check password expiry and notify user
                if ($isNewLogin) {
                    $this->checkPasswordExpiry($sfUser, $userId);
                }
            }

            // Force password change redirect (if enabled and password expired)
            if (!empty($_SESSION['_security_force_password_change'])) {
                $path = $request->getPathInfo();
                // Allow access to password change page and logout
                if (!preg_match('#/user/password#i', $path)
                    && !preg_match('#/user/logout#i', $path)
                    && !preg_match('#\.(css|js|png|jpg|gif|svg|ico|woff2?)$#i', $path)
                ) {
                    unset($_SESSION['_security_force_password_change']);
                    $changeUrl = \sfContext::getInstance()->getRouting()->generate(
                        null,
                        ['module' => 'user', 'action' => 'passwordEdit']
                    );

                    return new \Symfony\Component\HttpFoundation\RedirectResponse($changeUrl);
                }
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
     *
     * @return bool True if this was a new login (session was regenerated)
     */
    private function regenerateSessionOnLogin(int $userId): bool
    {
        $prevAuthId = $_SESSION[self::SESSION_AUTH_KEY] ?? null;

        if ($prevAuthId === null || (int) $prevAuthId !== $userId) {
            $this->regenerateSession($userId);

            return true;
        }

        return false;
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
     * Check password expiry on login and set flash notifications.
     *
     * Uses PasswordPolicyService to check if the user's password is expired
     * or expiring soon, and sets appropriate flash messages via Symfony's
     * sfUser flash system (displayed by the theme layout).
     */
    private function checkPasswordExpiry($sfUser, int $userId): void
    {
        try {
            // Check if notifications are enabled
            $notifyEnabled = true;

            try {
                $val = DB::table('ahg_settings')
                    ->where('setting_key', 'security_password_expiry_notify')
                    ->value('setting_value');
                if ($val === 'false') {
                    $notifyEnabled = false;
                }
            } catch (\Throwable $e) {
                // Settings table may not exist — default to enabled
            }

            if (!$notifyEnabled) {
                return;
            }

            $daysRemaining = PasswordPolicyService::daysUntilExpiry($userId);

            if ($daysRemaining === -1) {
                return; // Expiry disabled
            }

            if ($daysRemaining === 0) {
                // Password has expired
                $sfUser->setFlash('error', 'Your password has expired. Please change your password immediately.');

                // Check if forced redirect is enabled
                try {
                    $forceChange = DB::table('ahg_settings')
                        ->where('setting_key', 'security_force_password_change')
                        ->value('setting_value');
                    if ($forceChange === 'true') {
                        $_SESSION['_security_force_password_change'] = true;
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }
            } else {
                // Check warning threshold
                $warnDays = 14;

                try {
                    $val = DB::table('ahg_settings')
                        ->where('setting_key', 'security_password_expiry_warn_days')
                        ->value('setting_value');
                    if ($val !== null) {
                        $warnDays = (int) $val;
                    }
                } catch (\Throwable $e) {
                    // Use default
                }

                if ($warnDays > 0 && $daysRemaining <= $warnDays) {
                    $sfUser->setFlash('notice', sprintf(
                        'Your password will expire in %d day%s. Please change it soon.',
                        $daysRemaining,
                        $daysRemaining === 1 ? '' : 's'
                    ));
                }
            }
        } catch (\Throwable $e) {
            // Never block login due to policy check errors
            error_log('[AuthMiddleware] Password expiry check failed: ' . $e->getMessage());
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
