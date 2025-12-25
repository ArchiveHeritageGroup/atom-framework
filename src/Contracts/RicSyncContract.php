<?php

declare(strict_types=1);

namespace AtomFramework\Contracts;

interface RicSyncContract
{
    // Sync Operations
    public function syncRecord(string $entityType, int $entityId, string $operation): bool;
    public function bulkSync(array $records): array;
    public function fullResync(?string $entityType = null, ?callable $progressCallback = null): array;

    // Deletion Handling
    public function handleDeletion(string $entityType, int $entityId, bool $cascade = true): int;
    public function handleBatchDeletion(array $entities): array;
    public function previewDeletion(string $entityType, int $entityId): array;

    // Move/Hierarchy Handling
    public function handleMove(string $entityType, int $entityId, ?int $oldParentId, ?int $newParentId): bool;
    public function updateHierarchy(string $entityType, int $entityId): int;

    // Integrity & Maintenance
    public function findOrphanedTriples(?string $entityType = null): array;
    public function findMissingRecords(?string $entityType = null): array;
    public function findInconsistencies(?string $entityType = null): array;
    public function runIntegrityCheck(): array;
    public function cleanupOrphanedTriples(bool $dryRun = false): array;
    public function repairInconsistencies(array $inconsistencies): array;

    // Audit & Logging
    public function logOperation(string $operation, string $entityType, ?int $entityId, string $status, ?string $details = null): void;
    public function getSyncHistory(string $entityType, int $entityId): array;
    public function getSyncStats(?string $since = null): array;
}
