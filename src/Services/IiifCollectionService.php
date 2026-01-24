<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * @deprecated This class has been moved to AhgIiif\Services\IiifCollectionService
 * 
 * This stub remains for backward compatibility. Please update your code to use:
 * use AhgIiif\Services\IiifCollectionService;
 * 
 * This file will be removed in a future version.
 */
class_alias(\AhgIiif\Services\IiifCollectionService::class, IiifCollectionService::class);

// Trigger deprecation notice when this file is included directly
trigger_error(
    'AtomFramework\Services\IiifCollectionService is deprecated. Use AhgIiif\Services\IiifCollectionService instead.',
    E_USER_DEPRECATED
);
