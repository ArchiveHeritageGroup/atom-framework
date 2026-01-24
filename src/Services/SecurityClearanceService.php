<?php

namespace AtomExtensions\Services;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Services\SecurityClearanceService (ahgSecurityClearancePlugin/lib/Services/SecurityClearanceService.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomExtensions\Services\SecurityClearanceService is deprecated. Use SecurityClearanceService from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

// Forward to plugin's service if it exists (Symfony autoloader loads it without namespace)
if (!class_exists('SecurityClearanceService', false)) {
    // The plugin's SecurityClearanceService is loaded by Symfony without namespace
    throw new \RuntimeException(
        'SecurityClearanceService has moved to ahgSecurityClearancePlugin/lib/Services/SecurityClearanceService.php. ' .
        'Please ensure the ahgSecurityClearancePlugin is enabled.'
    );
}
