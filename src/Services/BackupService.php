<?php

namespace AtomExtensions\Services;

/**
 * @deprecated Since version 1.0. Use AhgBackup\Services\BackupService instead.
 * This class has been moved to ahgBackupPlugin/lib/Services/BackupService.php
 *
 * This stub exists for backward compatibility only.
 */
class BackupService extends \AhgBackup\Services\BackupService
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Services\BackupService is deprecated. ' .
            'Use AhgBackup\Services\BackupService instead.',
            E_USER_DEPRECATED
        );
        parent::__construct();
    }
}
