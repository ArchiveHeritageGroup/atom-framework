<?php

/**
 * QubitAcl Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\AclService directly
 */

use AtomExtensions\Services\AclService;

class QubitAcl
{
    public const GRANT = AclService::GRANT;
    public const DENY = AclService::DENY;

    public static function check(?object $resource, string $action): bool
    {
        return AclService::check($resource, $action);
    }

    public static function getRepositoryAccess(string $action): array
    {
        return AclService::getRepositoryAccess($action);
    }

    public static function forwardToSecureAction(): void
    {
        AclService::forwardUnauthorized();
    }
}
