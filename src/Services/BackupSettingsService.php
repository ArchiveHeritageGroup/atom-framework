<?php

namespace AtomExtensions\Services;

/**
 * @deprecated Since version 1.0. Use AhgBackup\Services\BackupSettingsService instead.
 * This class has been moved to ahgBackupPlugin/lib/Services/BackupSettingsService.php
 *
 * This stub exists for backward compatibility only.
 */
class BackupSettingsService extends \AhgBackup\Services\BackupSettingsService
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Services\BackupSettingsService is deprecated. ' .
            'Use AhgBackup\Services\BackupSettingsService instead.',
            E_USER_DEPRECATED
        );
        parent::__construct();
    }
}
