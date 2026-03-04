<?php

/**
 * Task System Compatibility Autoloader.
 *
 * Load order:
 *   1. Command classes (sfCommandOption, sfCommandArgument, sfFormatter, sfEventDispatcher)
 *   2. sfBaseTask (depends on command classes)
 *   3. arBaseTask (extends sfBaseTask)
 */

$taskDir = __DIR__;

// Ensure sfConfig is available (needed by tasks via sfConfig::add/get/set)
if (!class_exists('sfConfig', false)) {
    if (class_exists(\AtomFramework\Http\Compatibility\SfConfigShim::class)) {
        \AtomFramework\Http\Compatibility\SfConfigShim::register();
    }
}

$files = [
    'sfCommandClasses.php',
    'sfBaseTask.php',
    'arBaseTask.php',
];

foreach ($files as $file) {
    $path = $taskDir . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}
