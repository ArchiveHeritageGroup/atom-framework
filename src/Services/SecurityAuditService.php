<?php

namespace AtomFramework\Services;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Services\SecurityAuditService (ahgSecurityClearancePlugin/lib/Services/SecurityAuditService.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomFramework\Services\SecurityAuditService is deprecated. Use AhgSecurityClearance\Services\SecurityAuditService from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'SecurityAuditService has moved to ahgSecurityClearancePlugin/lib/Services/SecurityAuditService.php. ' .
    'Please update your imports to use AhgSecurityClearance\Services\SecurityAuditService.'
);
