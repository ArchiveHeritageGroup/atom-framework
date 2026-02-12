<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Standalone authentication controller for Heratio.
 *
 * Provides login/logout/me endpoints that work independently of Symfony.
 * Session state is shared with Symfony via the SfUserAdapter which reads
 * and writes $_SESSION using Symfony's exact key format.
 *
 * Endpoints:
 *   POST /auth/login   — Authenticate and create session
 *   GET|POST /auth/logout — Destroy session
 *   GET /auth/me       — Return current user info
 */
class AuthController
{
    /**
     * POST /auth/login
     *
     * Authenticate via email/username + password.
     * On success: signs in via SfUserAdapter, sets atom_authenticated cookie.
     * Accepts JSON body or form-encoded POST data.
     * Returns JSON or redirects based on Accept header / login_route attribute.
     */
    public function login(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $email = $request->input('email', $request->input('username', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            return $this->respondToLogin($request, false, 'Email/username and password are required.');
        }

        $user = AuthService::authenticate($email, $password);

        if (!$user) {
            return $this->respondToLogin($request, false, 'Invalid credentials.');
        }

        // Sign in via SfUserAdapter (sets Symfony session state)
        $sfUser = SfContextAdapter::getInstance()->getUser();
        $sfUser->signIn($user);

        return $this->respondToLogin($request, true, null, $user);
    }

    /**
     * GET|POST /auth/logout
     *
     * Sign out and destroy session state.
     */
    public function logout(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (SfContextAdapter::hasInstance()) {
            $sfUser = SfContextAdapter::getInstance()->getUser();
            $sfUser->signOut();
        }

        // JSON clients get a JSON response
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'authenticated' => false,
                'message' => 'Logged out.',
            ]);
        }

        // Browser clients get redirected to home
        return new \Illuminate\Http\RedirectResponse('/');
    }

    /**
     * GET /auth/me
     *
     * Return current user info as JSON (for API clients).
     */
    public function me(Request $request): JsonResponse
    {
        if (!SfContextAdapter::hasInstance()) {
            return new JsonResponse([
                'authenticated' => false,
            ]);
        }

        $sfUser = SfContextAdapter::getInstance()->getUser();

        if (!$sfUser->isAuthenticated()) {
            return new JsonResponse([
                'authenticated' => false,
            ]);
        }

        $userId = $sfUser->getUserID();
        $groups = [];
        if ($userId) {
            $groups = AuthService::getGroupNames($userId);
        }

        return new JsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => $userId,
                'name' => $sfUser->getAttribute('user_name'),
                'slug' => $sfUser->getAttribute('user_slug'),
                'culture' => $sfUser->getCulture(),
                'credentials' => $sfUser->getCredentials(),
                'groups' => $groups,
                'is_administrator' => $sfUser->isAdministrator(),
            ],
        ]);
    }

    /**
     * Build appropriate login response based on request type.
     */
    private function respondToLogin(Request $request, bool $success, ?string $error = null, ?object $user = null): \Symfony\Component\HttpFoundation\Response
    {
        // JSON clients always get JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            if ($success) {
                return new JsonResponse([
                    'authenticated' => true,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name ?? $user->email,
                        'slug' => $user->slug ?? '',
                    ],
                ]);
            }

            return new JsonResponse([
                'authenticated' => false,
                'error' => $error,
            ], 401);
        }

        // Browser clients: redirect on success, back with error on failure
        if ($success) {
            // Check for a stored login_route
            $sfUser = SfContextAdapter::getInstance()->getUser();
            $loginRoute = $sfUser->getAttribute('login_route');
            $redirectTo = $loginRoute ?: '/';

            // Clear the stored login route
            if ($loginRoute) {
                $sfUser->removeAttribute('login_route');
            }

            return new \Illuminate\Http\RedirectResponse($redirectTo);
        }

        // On failure, redirect back to login with error flash
        if (SfContextAdapter::hasInstance()) {
            SfContextAdapter::getInstance()->getUser()->setFlash('error', $error ?? 'Login failed.');
        }

        $referer = $request->header('Referer', '/user/login');

        return new \Illuminate\Http\RedirectResponse($referer);
    }
}
