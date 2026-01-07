<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class AccessRequestService
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('access_request');
            self::$logger->pushHandler(
                new RotatingFileHandler('/var/log/atom/access_request.log', 30, Logger::INFO)
            );
        }
        return self::$logger;
    }

    /**
     * Create access request for clearance level upgrade
     */
    public static function createClearanceRequest(
        int $userId,
        int $requestedClassificationId,
        string $reason,
        ?string $justification = null,
        string $urgency = 'normal'
    ): ?int {
        return self::createRequest($userId, [
            'request_type' => 'clearance',
            'requested_classification_id' => $requestedClassificationId,
            'reason' => $reason,
            'justification' => $justification,
            'urgency' => $urgency,
        ]);
    }

    /**
     * Create access request for specific objects
     */
    public static function createObjectAccessRequest(
        int $userId,
        array $scopes,
        string $reason,
        ?string $justification = null,
        string $urgency = 'normal',
        string $accessLevel = 'view'
    ): ?int {
        // Determine request type from scopes
        $requestType = 'object';
        $scopeType = 'single';
        
        if (count($scopes) === 1) {
            $scope = $scopes[0];
            if ($scope['object_type'] === 'repository') {
                $requestType = 'repository';
                $scopeType = $scope['include_descendants'] ? 'repository_all' : 'single';
            } elseif ($scope['object_type'] === 'actor') {
                $requestType = 'authority';
                $scopeType = $scope['include_descendants'] ? 'with_children' : 'single';
            } else {
                $scopeType = $scope['include_descendants'] ? 'with_children' : 'single';
            }
        }

        return self::createRequest($userId, [
            'request_type' => $requestType,
            'scope_type' => $scopeType,
            'reason' => $reason,
            'justification' => $justification,
            'urgency' => $urgency,
            'access_level' => $accessLevel,
            'scopes' => $scopes,
        ]);
    }

    /**
     * Create a new access request (unified)
     */
    public static function createRequest(int $userId, array $data): ?int
    {
        try {
            $requestType = $data['request_type'] ?? 'clearance';
            
            // Get user's current clearance
            $currentClearance = DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->first();
            $currentClassificationId = $currentClearance->classification_id ?? null;

            DB::beginTransaction();

            // Create the request
            $requestId = DB::table('access_request')->insertGetId([
                'request_type' => $requestType,
                'scope_type' => $data['scope_type'] ?? 'single',
                'user_id' => $userId,
                'requested_classification_id' => $data['requested_classification_id'] ?? null,
                'current_classification_id' => $currentClassificationId,
                'reason' => $data['reason'],
                'justification' => $data['justification'] ?? null,
                'urgency' => $data['urgency'] ?? 'normal',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Add scopes if object request
            if (!empty($data['scopes'])) {
                foreach ($data['scopes'] as $scope) {
                    $objectTitle = self::getObjectTitle($scope['object_type'], $scope['object_id']);
                    
                    DB::table('access_request_scope')->insert([
                        'request_id' => $requestId,
                        'object_type' => $scope['object_type'],
                        'object_id' => $scope['object_id'],
                        'include_descendants' => $scope['include_descendants'] ? 1 : 0,
                        'object_title' => $objectTitle,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Log the creation
            self::logAction($requestId, 'created', $userId, 'Access request created');

            DB::commit();

            // Notify approvers
            self::notifyApprovers($requestId);

            self::getLogger()->info('Access request created', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'type' => $requestType
            ]);

            return $requestId;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to create access request', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return null;
        }
    }

    /**
     * Get object title based on type
     */
    public static function getObjectTitle(string $objectType, int $objectId): ?string
    {
        switch ($objectType) {
            case 'information_object':
                $obj = DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', CultureHelper::getCulture())
                    ->first();
                return $obj->title ?? null;
                
            case 'repository':
                $obj = DB::table('repository_i18n')
                    ->where('id', $objectId)
                    ->where('culture', CultureHelper::getCulture())
                    ->first();
                return $obj->authorized_form_of_name ?? null;
                
            case 'actor':
                $obj = DB::table('actor_i18n')
                    ->where('id', $objectId)
                    ->where('culture', CultureHelper::getCulture())
                    ->first();
                return $obj->authorized_form_of_name ?? null;
        }
        return null;
    }

    /**
     * Get object hierarchy path
     */
    public static function getObjectPath(string $objectType, int $objectId): array
    {
        $path = [];
        
        if ($objectType === 'information_object') {
            $current = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function($join) {
                    $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', CultureHelper::getCulture());
                })
                ->where('io.id', $objectId)
                ->select('io.id', 'io.parent_id', 'ioi.title')
                ->first();
            
            while ($current) {
                array_unshift($path, [
                    'id' => $current->id,
                    'title' => $current->title ?? 'Untitled'
                ]);
                
                if ($current->parent_id && $current->parent_id != 1) {
                    $current = DB::table('information_object as io')
                        ->leftJoin('information_object_i18n as ioi', function($join) {
                            $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', CultureHelper::getCulture());
                        })
                        ->where('io.id', $current->parent_id)
                        ->select('io.id', 'io.parent_id', 'ioi.title')
                        ->first();
                } else {
                    break;
                }
            }
        }
        
        return $path;
    }

    /**
     * Count descendants of an object
     */
    public static function countDescendants(string $objectType, int $objectId): int
    {
        if ($objectType === 'information_object') {
            $obj = DB::table('information_object')
                ->where('id', $objectId)
                ->first();
            
            if ($obj) {
                return DB::table('information_object')
                    ->where('lft', '>', $obj->lft)
                    ->where('rgt', '<', $obj->rgt)
                    ->count();
            }
        } elseif ($objectType === 'repository') {
            return DB::table('information_object')
                ->where('repository_id', $objectId)
                ->count();
        } elseif ($objectType === 'actor') {
            // Count related descriptions
            return DB::table('relation')
                ->where('subject_id', $objectId)
                ->orWhere('object_id', $objectId)
                ->count();
        }
        
        return 0;
    }

    /**
     * Approve an access request
     */
    public static function approveRequest(
        int $requestId,
        int $approverId,
        ?string $notes = null,
        ?string $expiresAt = null
    ): bool {
        try {
            $request = DB::table('access_request')->where('id', $requestId)->first();

            if (!$request || $request->status !== 'pending') {
                return false;
            }

            DB::beginTransaction();

            // Update request status
            DB::table('access_request')
                ->where('id', $requestId)
                ->update([
                    'status' => 'approved',
                    'reviewed_by' => $approverId,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'review_notes' => $notes,
                    'expires_at' => $expiresAt,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Handle based on request type
            if ($request->request_type === 'clearance') {
                // Grant clearance level
                SecurityClearanceService::grantClearance(
                    $request->user_id,
                    $request->requested_classification_id,
                    $approverId,
                    $expiresAt,
                    "Approved via access request #{$requestId}" . ($notes ? ": {$notes}" : '')
                );
            } elseif ($request->request_type === 'researcher' && $request->scope_type === 'renewal') {
                // Handle researcher renewal
                $newExpiry = $expiresAt ?: date('Y-m-d', strtotime('+1 year'));
                DB::table('research_researcher')
                    ->where('user_id', $request->user_id)
                    ->update([
                        'status' => 'approved',
                        'expires_at' => $newExpiry,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                // Grant object access
                $scopes = DB::table('access_request_scope')
                    ->where('request_id', $requestId)
                    ->get();

                foreach ($scopes as $scope) {
                    self::grantObjectAccess(
                        $request->user_id,
                        $scope->object_type,
                        $scope->object_id,
                        $scope->include_descendants,
                        $approverId,
                        $requestId,
                        $expiresAt,
                        $notes
                    );
                }
            }

            // Log the approval
            self::logAction($requestId, 'approved', $approverId, $notes);

            DB::commit();

            // Notify the user
            self::notifyUser($request->user_id, $requestId, 'approved');

            self::getLogger()->info('Access request approved', [
                'request_id' => $requestId,
                'approver_id' => $approverId
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to approve request', [
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);
            return false;
        }
    }

    /**
     * Grant object access to user
     */
    public static function grantObjectAccess(
        int $userId,
        string $objectType,
        int $objectId,
        bool $includeDescendants,
        int $grantedBy,
        ?int $requestId = null,
        ?string $expiresAt = null,
        ?string $notes = null,
        string $accessLevel = 'view'
    ): ?int {
        try {
            // Check if already granted
            $existing = DB::table('object_access_grant')
                ->where('user_id', $userId)
                ->where('object_type', $objectType)
                ->where('object_id', $objectId)
                ->where('active', 1)
                ->first();

            if ($existing) {
                // Update existing grant
                DB::table('object_access_grant')
                    ->where('id', $existing->id)
                    ->update([
                        'include_descendants' => $includeDescendants ? 1 : 0,
                        'access_level' => $accessLevel,
                        'expires_at' => $expiresAt,
                        'notes' => $notes,
                    ]);
                return $existing->id;
            }

            return DB::table('object_access_grant')->insertGetId([
                'user_id' => $userId,
                'request_id' => $requestId,
                'object_type' => $objectType,
                'object_id' => $objectId,
                'include_descendants' => $includeDescendants ? 1 : 0,
                'access_level' => $accessLevel,
                'granted_by' => $grantedBy,
                'granted_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
                'notes' => $notes,
                'active' => 1,
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to grant object access', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if user has access to an object
     */
    public static function hasObjectAccess(int $userId, string $objectType, int $objectId): bool
    {
        // Check direct grant
        $directGrant = DB::table('object_access_grant')
            ->where('user_id', $userId)
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->where('active', 1)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->exists();

        if ($directGrant) {
            return true;
        }

        // Check ancestor grants with include_descendants
        if ($objectType === 'information_object') {
            $obj = DB::table('information_object')
                ->where('id', $objectId)
                ->first();

            if ($obj) {
                // Get all ancestors
                $ancestors = DB::table('information_object')
                    ->where('lft', '<', $obj->lft)
                    ->where('rgt', '>', $obj->rgt)
                    ->pluck('id')
                    ->toArray();

                if (!empty($ancestors)) {
                    $ancestorGrant = DB::table('object_access_grant')
                        ->where('user_id', $userId)
                        ->where('object_type', 'information_object')
                        ->whereIn('object_id', $ancestors)
                        ->where('include_descendants', 1)
                        ->where('active', 1)
                        ->where(function($q) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
                        })
                        ->exists();

                    if ($ancestorGrant) {
                        return true;
                    }
                }

                // Check repository grant
                if ($obj->repository_id) {
                    $repoGrant = DB::table('object_access_grant')
                        ->where('user_id', $userId)
                        ->where('object_type', 'repository')
                        ->where('object_id', $obj->repository_id)
                        ->where('include_descendants', 1)
                        ->where('active', 1)
                        ->where(function($q) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
                        })
                        ->exists();

                    if ($repoGrant) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get user's object access grants
     */
    public static function getUserAccessGrants(int $userId): array
    {
        return DB::table('object_access_grant as oag')
            ->leftJoin('user as granter', 'oag.granted_by', '=', 'granter.id')
            ->where('oag.user_id', $userId)
            ->where('oag.active', 1)
            ->select(
                'oag.*',
                'granter.username as granted_by_name'
            )
            ->orderByDesc('oag.granted_at')
            ->get()
            ->map(function($grant) {
                $grant->object_title = self::getObjectTitle($grant->object_type, $grant->object_id);
                return $grant;
            })
            ->toArray();
    }

    /**
     * Revoke object access
     */
    public static function revokeObjectAccess(int $grantId, int $revokedBy): bool
    {
        try {
            DB::table('object_access_grant')
                ->where('id', $grantId)
                ->update([
                    'active' => 0,
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'revoked_by' => $revokedBy,
                ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Deny an access request
     */
    public static function denyRequest(
        int $requestId,
        int $approverId,
        ?string $notes = null
    ): bool {
        try {
            $request = DB::table('access_request')->where('id', $requestId)->first();

            if (!$request || $request->status !== 'pending') {
                return false;
            }

            DB::table('access_request')
                ->where('id', $requestId)
                ->update([
                    'status' => 'denied',
                    'reviewed_by' => $approverId,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'review_notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            self::logAction($requestId, 'denied', $approverId, $notes);
            self::notifyUser($request->user_id, $requestId, 'denied');

            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to deny request', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Cancel a request
     */
    public static function cancelRequest(int $requestId, int $userId): bool
    {
        try {
            $request = DB::table('access_request')->where('id', $requestId)->first();

            if (!$request || $request->status !== 'pending' || $request->user_id !== $userId) {
                return false;
            }

            DB::table('access_request')
                ->where('id', $requestId)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            self::logAction($requestId, 'cancelled', $userId, 'Request cancelled by user');
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pending requests for approver
     */
    public static function getPendingRequests(int $approverId): array
    {
        $requests = DB::table('access_request as ar')
            ->join('user as u', 'ar.user_id', '=', 'u.id')
            ->leftJoin('security_classification as sc', 'ar.requested_classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as csc', 'ar.current_classification_id', '=', 'csc.id')
            ->where('ar.status', 'pending')
            ->select(
                'ar.*',
                'u.username',
                'u.email',
                'sc.name as requested_classification',
                'sc.code as requested_code',
                'csc.name as current_classification'
            )
            ->orderByRaw("FIELD(ar.urgency, 'critical', 'high', 'normal', 'low')")
            ->orderBy('ar.created_at')
            ->get();

        // Add scope info for each request
        foreach ($requests as &$request) {
            if ($request->request_type !== 'clearance') {
                $request->scopes = DB::table('access_request_scope')
                    ->where('request_id', $request->id)
                    ->get()
                    ->toArray();
            }
        }

        return $requests->toArray();
    }

    /**
     * Get user's requests
     */
    public static function getUserRequests(int $userId): array
    {
        $requests = DB::table('access_request as ar')
            ->leftJoin('security_classification as sc', 'ar.requested_classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as csc', 'ar.current_classification_id', '=', 'csc.id')
            ->leftJoin('user as reviewer', 'ar.reviewed_by', '=', 'reviewer.id')
            ->where('ar.user_id', $userId)
            ->select(
                'ar.*',
                'sc.name as requested_classification',
                'csc.name as current_classification',
                'reviewer.username as reviewer_name'
            )
            ->orderByDesc('ar.created_at')
            ->get();

        foreach ($requests as &$request) {
            if ($request->request_type !== 'clearance') {
                $request->scopes = DB::table('access_request_scope')
                    ->where('request_id', $request->id)
                    ->get()
                    ->toArray();
            }
        }

        return $requests->toArray();
    }

    /**
     * Get single request with details
     */
    public static function getRequest(int $requestId): ?object
    {
        $request = DB::table('access_request as ar')
            ->join('user as u', 'ar.user_id', '=', 'u.id')
            ->leftJoin('security_classification as sc', 'ar.requested_classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as csc', 'ar.current_classification_id', '=', 'csc.id')
            ->leftJoin('user as reviewer', 'ar.reviewed_by', '=', 'reviewer.id')
            ->where('ar.id', $requestId)
            ->select(
                'ar.*',
                'u.username',
                'u.email',
                'sc.name as requested_classification',
                'sc.code as requested_code',
                'sc.level as requested_level',
                'csc.name as current_classification',
                'csc.code as current_code',
                'reviewer.username as reviewer_name'
            )
            ->first();

        if ($request && $request->request_type !== 'clearance') {
            $request->scopes = DB::table('access_request_scope')
                ->where('request_id', $requestId)
                ->get()
                ->toArray();
        }

        return $request;
    }

    /**
     * Check if user is an approver
     */
    public static function isApprover(int $userId): bool
    {
        // Administrators are always approvers
        $isAdmin = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', 100) // Administrator group
            ->exists();
        
        if ($isAdmin) {
            return true;
        }
        
        return DB::table('access_request_approver')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->exists();
    }

    /**
     * Check if user can approve specific classification
     */
    public static function canApprove(int $userId, ?int $classificationId): bool
    {
        if (!$classificationId) {
            return self::isApprover($userId);
        }

        $classification = DB::table('security_classification')
            ->where('id', $classificationId)
            ->first();

        if (!$classification) {
            return false;
        }

        $approver = DB::table('access_request_approver')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->first();

        if (!$approver) {
            return false;
        }

        if ($classification->level < $approver->min_classification_level ||
            $classification->level > $approver->max_classification_level) {
            return false;
        }

        $userClearance = DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->where('usc.user_id', $userId)
            ->first();

        return $userClearance && $userClearance->level >= $classification->level;
    }

    /**
     * Get approvers
     */
    public static function getApprovers(): array
    {
        return DB::table('access_request_approver as ara')
            ->join('user as u', 'ara.user_id', '=', 'u.id')
            ->leftJoin('user_security_clearance as usc', 'ara.user_id', '=', 'usc.user_id')
            ->leftJoin('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->where('ara.active', 1)
            ->select('ara.*', 'u.username', 'u.email', 'sc.name as clearance_name', 'sc.level as clearance_level')
            ->get()
            ->toArray();
    }

    /**
     * Set approver
     */
    public static function setApprover(int $userId, int $minLevel = 0, int $maxLevel = 5, bool $emailNotifications = true): bool
    {
        try {
            DB::table('access_request_approver')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'min_classification_level' => $minLevel,
                    'max_classification_level' => $maxLevel,
                    'email_notifications' => $emailNotifications ? 1 : 0,
                    'active' => 1,
                ]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove approver
     */
    public static function removeApprover(int $userId): bool
    {
        try {
            DB::table('access_request_approver')
                ->where('user_id', $userId)
                ->update(['active' => 0]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log action
     */
    private static function logAction(int $requestId, string $action, ?int $actorId, ?string $details = null): void
    {
        try {
            DB::table('access_request_log')->insert([
                'request_id' => $requestId,
                'action' => $action,
                'actor_id' => $actorId,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to log action', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get request log
     */
    public static function getRequestLog(int $requestId): array
    {
        return DB::table('access_request_log as arl')
            ->leftJoin('user as u', 'arl.actor_id', '=', 'u.id')
            ->where('arl.request_id', $requestId)
            ->select('arl.*', 'u.username as actor_name')
            ->orderByDesc('arl.created_at')
            ->get()
            ->toArray();
    }

    /**
     * Notify approvers
     */
    private static function notifyApprovers(int $requestId): void
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) return;

            $approvers = DB::table('access_request_approver as ara')
                ->join('user as u', 'ara.user_id', '=', 'u.id')
                ->where('ara.active', 1)
                ->where('ara.email_notifications', 1)
                ->select('u.email', 'u.username')
                ->get();

            foreach ($approvers as $approver) {
                self::sendEmail(
                    $approver->email,
                    "New Access Request - " . ($request->request_type === 'clearance' ? $request->requested_classification : ucfirst($request->request_type)),
                    self::buildApproverEmailBody($request, $approver->username)
                );
            }
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to notify approvers', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify user
     */
    private static function notifyUser(int $userId, int $requestId, string $status): void
    {
        try {
            $user = DB::table('user')->where('id', $userId)->first();
            $request = self::getRequest($requestId);

            if (!$user || !$request || !$user->email) return;

            $subject = $status === 'approved' ? "Access Request Approved" : "Access Request Denied";
            self::sendEmail($user->email, $subject, self::buildUserEmailBody($request, $status));
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to notify user', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send email
     */
    private static function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $siteTitle = DB::table('setting_i18n')
                ->join('setting', 'setting_i18n.id', '=', 'setting.id')
                ->where('setting.name', 'siteTitle')
                ->value('setting_i18n.value') ?? 'Archive';

            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: {$siteTitle} <noreply@theahg.co.za>",
            ];

            return mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build approver email
     */
    private static function buildApproverEmailBody(object $request, string $approverName): string
    {
        $scopeHtml = '';
        if ($request->request_type !== 'clearance' && !empty($request->scopes)) {
            $scopeHtml = '<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Requested Access:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">';
            foreach ($request->scopes as $scope) {
                $scopeHtml .= htmlspecialchars($scope->object_title ?? 'Unknown') . ' (' . ucfirst($scope->object_type) . ')';
                if ($scope->include_descendants) {
                    $scopeHtml .= ' <em>(+ all children)</em>';
                }
                $scopeHtml .= '<br>';
            }
            $scopeHtml .= '</td></tr>';
        }

        return "
        <html><body style='font-family: Arial, sans-serif;'>
            <h2>New Access Request</h2>
            <p>Dear {$approverName},</p>
            <p>A new access request requires your attention:</p>
            <table style='border-collapse: collapse; width: 100%; max-width: 600px;'>
                <tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'><strong>Requester:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$request->username}</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'><strong>Request Type:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>" . ucfirst($request->request_type) . "</td></tr>
                {$scopeHtml}
                <tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'><strong>Reason:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$request->reason}</td></tr>
            </table>
            <p><a href='https://nahlisa.theahg.co.za/index.php/security/request/{$request->id}' 
                  style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none;'>Review Request</a></p>
        </body></html>";
    }

    /**
     * Build user email
     */
    private static function buildUserEmailBody(object $request, string $status): string
    {
        $statusColor = $status === 'approved' ? '#28a745' : '#dc3545';
        return "
        <html><body style='font-family: Arial, sans-serif;'>
            <h2>Access Request <span style='color: {$statusColor};'>" . strtoupper($status) . "</span></h2>
            <p>Your access request has been {$status}.</p>
            " . ($request->review_notes ? "<p><strong>Notes:</strong> " . htmlspecialchars($request->review_notes) . "</p>" : "") . "
            <p><a href='https://nahlisa.theahg.co.za/index.php/security/my-requests'>View My Requests</a></p>
        </body></html>";
    }

    /**
     * Get stats
     */
    public static function getStats(): array
    {
        return [
            'pending' => DB::table('access_request')->where('status', 'pending')->count(),
            'approved_today' => DB::table('access_request')->where('status', 'approved')->whereDate('reviewed_at', date('Y-m-d'))->count(),
            'denied_today' => DB::table('access_request')->where('status', 'denied')->whereDate('reviewed_at', date('Y-m-d'))->count(),
            'total_this_month' => DB::table('access_request')->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count(),
        ];
    }

    /**
     * Check for pending request for same object
     */
    public static function hasPendingRequestForObject(int $userId, string $objectType, int $objectId): bool
    {
        return DB::table('access_request as ar')
            ->join('access_request_scope as ars', 'ar.id', '=', 'ars.request_id')
            ->where('ar.user_id', $userId)
            ->where('ar.status', 'pending')
            ->where('ars.object_type', $objectType)
            ->where('ars.object_id', $objectId)
            ->exists();
    }
}
