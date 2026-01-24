<?php

namespace AtomFramework\Models;

/**
 * @deprecated This class has been moved to ahgSecurityClearancePlugin.
 * @see \AhgSecurityClearance\Models\ObjectSecurityClassification (ahgSecurityClearancePlugin/lib/Models/ObjectSecurityClassification.php)
 * 
 * This stub is provided for backward compatibility only.
 * Please update your code to use the new location.
 */

trigger_error(
    'AtomFramework\Models\ObjectSecurityClassification is deprecated. Use AhgSecurityClearance\Models\ObjectSecurityClassification from ahgSecurityClearancePlugin instead.',
    E_USER_DEPRECATED
);

throw new \RuntimeException(
    'ObjectSecurityClassification has moved to ahgSecurityClearancePlugin/lib/Models/ObjectSecurityClassification.php. ' .
    'Please update your imports to use AhgSecurityClearance\Models\ObjectSecurityClassification.'
);
