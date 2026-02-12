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
    public const INHERIT = AclService::INHERIT;

    public static function check(?object $resource, $action, ?object $user = null): bool
    {
        return AclService::check($resource, $action, $user);
    }

    public static function getRepositoryAccess(string $action): array
    {
        return AclService::getRepositoryAccess($action);
    }

    public static function forwardUnauthorized(bool $return = false)
    {
        return AclService::forwardUnauthorized($return);
    }

    public static function forwardToSecureAction(): void
    {
        AclService::forwardUnauthorized();
    }

    public static function forwardToLoginAction(): void
    {
        AclService::forwardToLoginAction();
    }

    public static function addFilterDraftsCriteria($query)
    {
        return AclService::addFilterDraftsCriteria($query);
    }
}
