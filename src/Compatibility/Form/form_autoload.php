<?php

/**
 * Form System Compatibility Autoloader.
 *
 * Loads sfForm, sfWidget, sfValidator stubs in the correct dependency order.
 * All classes are guarded with class_exists() — they never conflict with
 * real Symfony form classes when loaded via sfCoreAutoload.
 *
 * Load order:
 *   1. Validators (standalone, no deps)
 *   2. Validator schema + errors (depends on validators)
 *   3. Widgets (base widgets, no deps on validators)
 *   4. Widget schema + formatters (depends on widgets)
 *   5. Form + FormField (depends on widgets + validators)
 *   6. Qubit form stubs (depends on base form classes)
 *   7. AtoM theme form stubs (depends on base form classes)
 */

$formDir = __DIR__;

$files = [
    'sfValidators.php',
    'sfValidatorSchema.php',
    'sfWidgetForm.php',
    'sfWidgetFormSchema.php',
    'sfForm.php',
    'sfFormField.php',
    'QubitFormStubs.php',
    'arFormStubs.php',
];

foreach ($files as $file) {
    $path = $formDir . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}
