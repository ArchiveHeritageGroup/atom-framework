<?php

declare(strict_types=1);

namespace TheAHG\Archive\Controllers;

use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheAHG\Archive\Services\SecurityClearanceService;

/**
 * User Security Clearance Controller.
 *
 * Handles user clearance management - mimics AtoM 2.10 user administration
 */
class UserSecurityController
{
    private $view;

    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * List all user clearances.
     * Route: GET /admin/security/clearances
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $search = $params['search'] ?? null;

        $result = SecurityClearanceService::getAllUserClearances($page, 25, $search);
        $classifications = SecurityClearanceService::getAllClassifications();
        $stats = SecurityClearanceService::getSecurityStatistics();

        return $this->view->render($response, 'security/clearances/index.blade.php', [
            'clearances' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 25,
            'search' => $search,
            'classifications' => $classifications,
            'stats' => $stats,
            'pageTitle' => 'User Security Clearances',
        ]);
    }

    /**
     * Show grant clearance form.
     * Route: GET /admin/security/clearances/grant
     */
    public function create(Request $request, Response $response): Response
    {
        $classifications = SecurityClearanceService::getClassificationsDropdown();
        $usersWithoutClearance = SecurityClearanceService::getUsersWithoutClearance();

        return $this->view->render($response, 'security/clearances/grant.blade.php', [
            'classifications' => $classifications,
            'users' => $usersWithoutClearance,
            'pageTitle' => 'Grant Security Clearance',
        ]);
    }

    /**
     * Grant clearance to user.
     * Route: POST /admin/security/clearances/grant
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $userId = (int) ($data['user_id'] ?? 0);
        $classificationId = (int) ($data['classification_id'] ?? 0);
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
        $notes = $data['notes'] ?? null;

        if (!$userId || !$classificationId) {
            return $this->redirect($response, '/admin/security/clearances/grant?error=invalid');
        }

        $success = SecurityClearanceService::grantUserClearance(
            $userId,
            $classificationId,
            $currentUser['id'],
            $expiresAt,
            $notes
        );

        if ($success) {
            return $this->redirect($response, '/admin/security/clearances?success=granted');
        }

        return $this->redirect($response, '/admin/security/clearances/grant?error=failed');
    }

    /**
     * Show edit clearance form.
     * Route: GET /admin/security/clearances/{userId}/edit
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $clearance = SecurityClearanceService::getUserClearance($userId);

        if (!$clearance) {
            return $this->redirect($response, '/admin/security/clearances?error=notfound');
        }

        $classifications = SecurityClearanceService::getClassificationsDropdown();
        $history = SecurityClearanceService::getUserClearanceHistory($userId);

        // Get user info
        $user = DB::table('user')->where('id', $userId)->first();

        return $this->view->render($response, 'security/clearances/edit.blade.php', [
            'clearance' => $clearance,
            'classifications' => $classifications,
            'history' => $history,
            'user' => $user,
            'pageTitle' => 'Edit Security Clearance - '.$clearance->username,
        ]);
    }

    /**
     * Update user clearance.
     * Route: PUT /admin/security/clearances/{userId}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $classificationId = (int) ($data['classification_id'] ?? 0);
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
        $notes = $data['notes'] ?? null;

        if (!$classificationId) {
            return $this->redirect($response, "/admin/security/clearances/{$userId}/edit?error=invalid");
        }

        $success = SecurityClearanceService::grantUserClearance(
            $userId,
            $classificationId,
            $currentUser['id'],
            $expiresAt,
            $notes
        );

        if ($success) {
            return $this->redirect($response, '/admin/security/clearances?success=updated');
        }

        return $this->redirect($response, "/admin/security/clearances/{$userId}/edit?error=failed");
    }

    /**
     * Revoke user clearance.
     * Route: DELETE /admin/security/clearances/{userId}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $reason = $data['reason'] ?? 'Clearance revoked';

        $success = SecurityClearanceService::revokeUserClearance($userId, $currentUser['id'], $reason);

        if ($success) {
            return $this->redirect($response, '/admin/security/clearances?success=revoked');
        }

        return $this->redirect($response, '/admin/security/clearances?error=revoke_failed');
    }

    /**
     * Show user's clearance in user edit form.
     * Route: GET /admin/users/{userId}/security
     */
    public function userSecurity(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $clearance = SecurityClearanceService::getUserClearance($userId);
        $classifications = SecurityClearanceService::getClassificationsDropdown();
        $history = SecurityClearanceService::getUserClearanceHistory($userId);

        // Get user info
        $user = DB::table('user')
            ->leftJoin('user_i18n', 'user.id', '=', 'user_i18n.id')
            ->where('user.id', $userId)
            ->select(['user.*', 'user_i18n.name as display_name'])
            ->first();

        if (!$user) {
            return $this->redirect($response, '/admin/users?error=notfound');
        }

        return $this->view->render($response, 'user/security.blade.php', [
            'user' => $user,
            'clearance' => $clearance,
            'classifications' => $classifications,
            'history' => $history,
            'pageTitle' => 'Security Clearance - '.$user->username,
        ]);
    }

    /**
     * Update user's security clearance from user edit.
     * Route: POST /admin/users/{userId}/security
     */
    public function updateUserSecurity(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $action = $data['action'] ?? 'update';

        if ('revoke' === $action) {
            $reason = $data['reason'] ?? 'Clearance revoked';
            SecurityClearanceService::revokeUserClearance($userId, $currentUser['id'], $reason);

            return $this->redirect($response, "/admin/users/{$userId}/security?success=revoked");
        }

        $classificationId = (int) ($data['classification_id'] ?? 0);
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
        $notes = $data['notes'] ?? null;

        if ($classificationId > 0) {
            SecurityClearanceService::grantUserClearance(
                $userId,
                $classificationId,
                $currentUser['id'],
                $expiresAt,
                $notes
            );

            return $this->redirect($response, "/admin/users/{$userId}/security?success=updated");
        }

        return $this->redirect($response, "/admin/users/{$userId}/security?error=invalid");
    }

    /**
     * Redirect helper.
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
