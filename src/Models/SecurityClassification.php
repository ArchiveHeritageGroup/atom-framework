<?php

namespace AtomFramework\Models;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Models\SecurityClassification (ahgSecurityClearancePlugin/lib/Models/SecurityClassification.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomFramework\Models\SecurityClassification is deprecated. Use AhgSecurityClearance\Models\SecurityClassification from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'SecurityClassification has moved to ahgSecurityClearancePlugin/lib/Models/SecurityClassification.php. ' .
    'Please update your imports to use AhgSecurityClearance\Models\SecurityClassification.'
);
