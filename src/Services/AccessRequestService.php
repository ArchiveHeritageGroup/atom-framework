<?php

namespace AtomExtensions\Services;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Services\AccessRequestService (ahgSecurityClearancePlugin/lib/Services/AccessRequestService.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomExtensions\Services\AccessRequestService is deprecated. Use AhgSecurityClearance\Services\AccessRequestService from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'AccessRequestService has moved to ahgSecurityClearancePlugin/lib/Services/AccessRequestService.php. ' .
    'Please update your imports to use AhgSecurityClearance\Services\AccessRequestService.'
);
