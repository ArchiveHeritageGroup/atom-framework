<?php

namespace AtomExtensions\Services;

/**
 * @deprecated Use AhgLandingPage\Services\LandingPageService instead.
 * This class has been moved to ahgLandingPagePlugin/lib/Services/LandingPageService.php
 */

// Load the new class
$pluginPath = (class_exists('sfConfig') ? \sfConfig::get('sf_plugins_dir') : dirname(__DIR__, 3) . '/plugins')
    . '/ahgLandingPagePlugin/lib/Services/LandingPageService.php';

if (file_exists($pluginPath)) {
    require_once $pluginPath;
    class_alias(\AhgLandingPage\Services\LandingPageService::class, 'AtomExtensions\Services\LandingPageService');
}

trigger_error(
    'AtomExtensions\Services\LandingPageService is deprecated. Use AhgLandingPage\Services\LandingPageService instead.',
    E_USER_DEPRECATED
);
