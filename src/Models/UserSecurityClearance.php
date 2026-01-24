<?php

namespace AtomFramework\Models;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Models\UserSecurityClearance (ahgSecurityClearancePlugin/lib/Models/UserSecurityClearance.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomFramework\Models\UserSecurityClearance is deprecated. Use AhgSecurityClearance\Models\UserSecurityClearance from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'UserSecurityClearance has moved to ahgSecurityClearancePlugin/lib/Models/UserSecurityClearance.php. ' .
    'Please update your imports to use AhgSecurityClearance\Models\UserSecurityClearance.'
);
