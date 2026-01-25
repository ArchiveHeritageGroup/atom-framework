<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Access;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Access Decision Service.
 *
 * Main decision engine for access control. Integrates trust levels,
 * embargoes, access rules, and POPIA compliance.
 */
class AccessDecisionService
{
    private TrustLevelService $trustLevelService;
    private EmbargoService $embargoService;
    private AccessRequestService $requestService;

    public function __construct()
    {
        $this->trustLevelService = new TrustLevelService();
        $this->embargoService = new EmbargoService();
        $this->requestService = new AccessRequestService();
    }

    /**
     * Check if user can access an object.
     *
     * @return array{allowed: bool, reason: string|null, level: string}
     */
    public function checkAccess(
        int $objectId,
        ?int $userId = null,
        string $action = 'view',
        ?int $institutionId = null
    ): array {
        // Step 1: Check embargo
        if ($this->embargoService->isEmbargoed($objectId)) {
            $embargo = $this->embargoService->getEmbargo($objectId);
            if ($embargo->embargo_type === 'full') {
                return [
                    'allowed' => false,
                    'reason' => 'This item is currently under embargo',
                    'level' => 'none',
                    'embargo' => $embargo,
                ];
            }
            if ($embargo->embargo_type === 'digital_only' && in_array($action, ['download', 'download_master'])) {
                return [
                    'allowed' => false,
                    'reason' => 'Digital content is embargoed',
                    'level' => 'metadata_only',
                    'embargo' => $embargo,
                ];
            }
        }

        // Step 2: Check access rules
        $rule = $this->getApplicableRule($objectId, $userId, $action, $institutionId);
        if ($rule) {
            if ($rule->rule_type === 'deny') {
                return [
                    'allowed' => false,
                    'reason' => $rule->notes ?? 'Access denied by policy',
                    'level' => 'none',
                ];
            }
            if ($rule->rule_type === 'require_approval') {
                // Check if user has approved access
                if ($userId && $this->requestService->hasApprovedAccess($userId, $objectId)) {
                    return [
                        'allowed' => true,
                        'reason' => null,
                        'level' => 'full',
                    ];
                }

                return [
                    'allowed' => false,
                    'reason' => 'Access requires approval',
                    'level' => 'request_required',
                    'can_request' => $userId !== null,
                ];
            }
        }

        // Step 3: Check trust level requirements
        if ($userId) {
            $userTrustLevel = $this->trustLevelService->getUserTrustLevel($userId, $institutionId);
            $userLevel = $userTrustLevel ? (int) $userTrustLevel->level : 0;

            // Check if object has POPIA flags requiring higher trust
            if ($this->hasCriticalPOPIAFlags($objectId) && $userLevel < 3) {
                return [
                    'allowed' => false,
                    'reason' => 'Access to sensitive personal data requires elevated trust level',
                    'level' => 'restricted',
                ];
            }

            // Check download permissions
            if (in_array($action, ['download', 'download_master'])) {
                if (!$userTrustLevel || !$userTrustLevel->can_download) {
                    return [
                        'allowed' => false,
                        'reason' => 'Download permission not granted for your account',
                        'level' => 'view_only',
                    ];
                }
            }
        } else {
            // Anonymous user
            $anonRule = $this->getAnonymousRule($objectId, $action);
            if ($anonRule && $anonRule->rule_type === 'deny') {
                return [
                    'allowed' => false,
                    'reason' => 'Login required to access this content',
                    'level' => 'login_required',
                ];
            }
        }

        // Step 4: Default allow
        return [
            'allowed' => true,
            'reason' => null,
            'level' => 'full',
        ];
    }

    /**
     * Get applicable access rule.
     */
    private function getApplicableRule(
        int $objectId,
        ?int $userId,
        string $action,
        ?int $institutionId
    ): ?object {
        // Get object info for collection/repository lookup
        $object = DB::table('information_object')
            ->select(['parent_id', 'repository_id'])
            ->where('id', $objectId)
            ->first();

        $query = DB::table('heritage_access_rule')
            ->where('is_enabled', 1)
            ->where(function ($q) use ($action) {
                $q->where('action', $action)
                    ->orWhere('action', 'all');
            })
            ->where(function ($q) use ($objectId, $object) {
                $q->where('object_id', $objectId);

                if ($object && $object->parent_id) {
                    $q->orWhere('collection_id', $object->parent_id);
                }

                if ($object && $object->repository_id) {
                    $q->orWhere('repository_id', $object->repository_id);
                }
            });

        // Filter by applies_to
        if ($userId) {
            $userTrustLevel = $this->trustLevelService->getUserTrustLevel($userId, $institutionId);
            $userLevel = $userTrustLevel ? (int) $userTrustLevel->level : 0;

            $query->where(function ($q) use ($userLevel) {
                $q->where('applies_to', 'all')
                    ->orWhere('applies_to', 'authenticated')
                    ->orWhere(function ($q2) use ($userLevel) {
                        $q2->where('applies_to', 'trust_level')
                            ->where(function ($q3) use ($userLevel) {
                                $q3->whereNull('trust_level_id')
                                    ->orWhereExists(function ($sub) use ($userLevel) {
                                        $sub->select(DB::raw(1))
                                            ->from('heritage_trust_level')
                                            ->whereColumn('heritage_trust_level.id', 'heritage_access_rule.trust_level_id')
                                            ->where('heritage_trust_level.level', '<=', $userLevel);
                                    });
                            });
                    });
            });
        } else {
            $query->whereIn('applies_to', ['all', 'anonymous']);
        }

        return $query->orderBy('priority')
            ->first();
    }

    /**
     * Get rule for anonymous users.
     */
    private function getAnonymousRule(int $objectId, string $action): ?object
    {
        return DB::table('heritage_access_rule')
            ->where('is_enabled', 1)
            ->where('object_id', $objectId)
            ->where(function ($q) use ($action) {
                $q->where('action', $action)
                    ->orWhere('action', 'all');
            })
            ->where('applies_to', 'anonymous')
            ->orderBy('priority')
            ->first();
    }

    /**
     * Check if object has critical POPIA flags.
     */
    private function hasCriticalPOPIAFlags(int $objectId): bool
    {
        return DB::table('heritage_popia_flag')
            ->where('object_id', $objectId)
            ->where('is_resolved', 0)
            ->whereIn('severity', ['high', 'critical'])
            ->exists();
    }

    /**
     * Log access attempt.
     */
    public function logAccess(
        int $objectId,
        ?int $userId,
        string $action,
        bool $allowed,
        ?string $reason = null,
        ?string $ipAddress = null
    ): void {
        DB::table('heritage_audit_log')->insert([
            'user_id' => $userId,
            'object_id' => $objectId,
            'object_type' => 'information_object',
            'action' => "access_{$action}",
            'action_category' => 'access',
            'metadata' => json_encode([
                'allowed' => $allowed,
                'reason' => $reason,
            ]),
            'ip_address' => $ipAddress,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
