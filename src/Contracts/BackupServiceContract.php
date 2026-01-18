<?php

namespace AtomFramework\Contracts;

interface BackupServiceContract
{
    public function createBackup(array $options = []): array;
    public function restoreBackup(string $backupId): bool;
    public function listBackups(): array;
    public function deleteBackup(string $backupId): bool;
    public function getBackupDetails(string $backupId): ?array;
}
