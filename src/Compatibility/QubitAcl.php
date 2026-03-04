<?php

/**
 * QubitAcl Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\AclService directly
 */

if (!class_exists('QubitAcl', false)) {
    class QubitAcl
    {
        public const GRANT = \AtomExtensions\Services\AclService::GRANT;
        public const DENY = \AtomExtensions\Services\AclService::DENY;
        public const INHERIT = \AtomExtensions\Services\AclService::INHERIT;

        public static function check(?object $resource, $action, ?object $user = null): bool
        {
            return \AtomExtensions\Services\AclService::check($resource, $action, $user);
        }

        public static function getRepositoryAccess(string $action): array
        {
            return \AtomExtensions\Services\AclService::getRepositoryAccess($action);
        }

        public static function forwardUnauthorized(bool $return = false)
        {
            return \AtomExtensions\Services\AclService::forwardUnauthorized($return);
        }

        public static function forwardToSecureAction(): void
        {
            \AtomExtensions\Services\AclService::forwardUnauthorized();
        }

        public static function forwardToLoginAction(): void
        {
            \AtomExtensions\Services\AclService::forwardToLoginAction();
        }

        public static function addFilterDraftsCriteria($query)
        {
            return \AtomExtensions\Services\AclService::addFilterDraftsCriteria($query);
        }
    }
}
