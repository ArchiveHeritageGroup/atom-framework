<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use Closure;
use Illuminate\Http\Request;

/**
 * Start the PHP native session using Symfony's cookie name.
 *
 * Shares the session with Symfony's index.php by using the same
 * cookie name ('symfony') and PHP native session storage. This
 * enables dual-stack operation where authentication state is
 * visible to both Heratio and Symfony request handlers.
 */
class SessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Start PHP session with Symfony's cookie name
        if (PHP_SESSION_NONE === session_status()) {
            session_name('symfony');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            ini_set('session.gc_maxlifetime', '1800');

            session_start();
        }

        // Create sfContext adapter now that session is available
        if (!SfContextAdapter::hasInstance()) {
            SfContextAdapter::create($request);
        }

        $response = $next($request);

        // Release session lock so Symfony requests aren't blocked
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        return $response;
    }
}
