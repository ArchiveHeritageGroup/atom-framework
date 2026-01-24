<?php

namespace AtomFramework\Middleware;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Middleware\SecurityClearanceMiddleware (ahgSecurityClearancePlugin/lib/Middleware/SecurityClearanceMiddleware.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomFramework\Middleware\SecurityClearanceMiddleware is deprecated. Use AhgSecurityClearance\Middleware\SecurityClearanceMiddleware from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'SecurityClearanceMiddleware has moved to ahgSecurityClearancePlugin/lib/Middleware/SecurityClearanceMiddleware.php. ' .
    'Please update your imports to use AhgSecurityClearance\Middleware\SecurityClearanceMiddleware.'
);
