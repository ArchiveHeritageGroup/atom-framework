<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api;

use AtomFramework\Heritage\Access\AccessDecisionService;
use AtomFramework\Heritage\Access\AccessRequestService;
use AtomFramework\Heritage\Access\EmbargoService;
use AtomFramework\Heritage\Access\POPIAService;
use AtomFramework\Heritage\Access\TrustLevelService;

/**
 * Access Controller.
 *
 * Handles access mediation API requests.
 */
class AccessController
{
    private AccessDecisionService $decisionService;
    private TrustLevelService $trustLevelService;
    private AccessRequestService $requestService;
    private EmbargoService $embargoService;
    private POPIAService $popiaService;

    public function __construct()
    {
        $this->decisionService = new AccessDecisionService();
        $this->trustLevelService = new TrustLevelService();
        $this->requestService = new AccessRequestService();
        $this->embargoService = new EmbargoService();
        $this->popiaService = new POPIAService();
    }

    // ========================================================================
    // Access Decisions
    // ========================================================================

    /**
     * Check access for an object.
     */
    public function checkAccess(int $objectId, ?int $userId = null, string $action = 'view', ?int $institutionId = null): array
    {
        try {
            $result = $this->decisionService->checkAccess($objectId, $userId, $action, $institutionId);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Trust Levels
    // ========================================================================

    /**
     * Get all trust levels.
     */
    public function getTrustLevels(): array
    {
        try {
            $levels = $this->trustLevelService->getAllLevels();

            return [
                'success' => true,
                'data' => $levels->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user's trust level.
     */
    public function getUserTrustLevel(int $userId, ?int $institutionId = null): array
    {
        try {
            $level = $this->trustLevelService->getUserTrustLevel($userId, $institutionId);

            return [
                'success' => true,
                'data' => $level,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Access Requests
    // ========================================================================

    /**
     * Get pending access requests (admin).
     */
    public function getPendingRequests(array $params = []): array
    {
        try {
            $result = $this->requestService->getPendingRequests($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user's access requests.
     */
    public function getUserRequests(int $userId, array $params = []): array
    {
        try {
            $result = $this->requestService->getUserRequests($userId, $params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create access request.
     */
    public function createAccessRequest(array $data): array
    {
        try {
            if (empty($data['user_id']) || empty($data['object_id'])) {
                return [
                    'success' => false,
                    'error' => 'User ID and Object ID are required',
                ];
            }

            $id = $this->requestService->create($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Approve access request.
     */
    public function approveRequest(int $id, int $decisionBy, array $options = []): array
    {
        try {
            $success = $this->requestService->approve($id, $decisionBy, $options);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deny access request.
     */
    public function denyRequest(int $id, int $decisionBy, ?string $reason = null): array
    {
        try {
            $success = $this->requestService->deny($id, $decisionBy, $reason);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available purposes.
     */
    public function getPurposes(): array
    {
        try {
            $purposes = $this->requestService->getPurposes();

            return [
                'success' => true,
                'data' => $purposes->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Embargoes
    // ========================================================================

    /**
     * Get active embargoes.
     */
    public function getEmbargoes(array $params = []): array
    {
        try {
            $result = $this->embargoService->getActiveEmbargoes($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get expiring embargoes.
     */
    public function getExpiringEmbargoes(int $days = 30): array
    {
        try {
            $embargoes = $this->embargoService->getExpiringEmbargoes($days);

            return [
                'success' => true,
                'data' => $embargoes->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create embargo.
     */
    public function createEmbargo(array $data): array
    {
        try {
            if (empty($data['object_id'])) {
                return [
                    'success' => false,
                    'error' => 'Object ID is required',
                ];
            }

            $id = $this->embargoService->create($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove embargo.
     */
    public function removeEmbargo(int $id): array
    {
        try {
            $success = $this->embargoService->remove($id);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get embargo statistics.
     */
    public function getEmbargoStats(): array
    {
        try {
            $stats = $this->embargoService->getStats();

            return [
                'success' => true,
                'data' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // POPIA Flags
    // ========================================================================

    /**
     * Get POPIA flags (admin).
     */
    public function getPOPIAFlags(array $params = []): array
    {
        try {
            $result = $this->popiaService->getAllUnresolvedFlags($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create POPIA flag.
     */
    public function createPOPIAFlag(array $data): array
    {
        try {
            if (empty($data['object_id']) || empty($data['flag_type'])) {
                return [
                    'success' => false,
                    'error' => 'Object ID and flag type are required',
                ];
            }

            $id = $this->popiaService->createFlag($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve POPIA flag.
     */
    public function resolvePOPIAFlag(int $id, int $resolvedBy, ?string $notes = null): array
    {
        try {
            $success = $this->popiaService->resolveFlag($id, $resolvedBy, $notes);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get POPIA statistics.
     */
    public function getPOPIAStats(): array
    {
        try {
            $stats = $this->popiaService->getStats();

            return [
                'success' => true,
                'data' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get access request statistics.
     */
    public function getRequestStats(): array
    {
        try {
            $stats = $this->requestService->getStats();

            return [
                'success' => true,
                'data' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
