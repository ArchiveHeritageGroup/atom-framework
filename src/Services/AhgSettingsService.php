<?php

namespace AtomExtensions\Services;

/**
 * @deprecated Use AhgSettings\Services\AhgSettingsService instead.
 * This class has been moved to ahgSettingsPlugin/lib/Services/AhgSettingsService.php
 */

// Load the new class
$pluginPath = (class_exists('sfConfig') ? \sfConfig::get('sf_plugins_dir') : dirname(__DIR__, 3) . '/plugins')
    . '/ahgSettingsPlugin/lib/Services/AhgSettingsService.php';

if (file_exists($pluginPath)) {
    require_once $pluginPath;
    class_alias(\AhgSettings\Services\AhgSettingsService::class, 'AtomExtensions\Services\AhgSettingsService');
}

trigger_error(
    'AtomExtensions\Services\AhgSettingsService is deprecated. Use AhgSettings\Services\AhgSettingsService instead.',
    E_USER_DEPRECATED
);
