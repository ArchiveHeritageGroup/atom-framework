<?php

/**
 * QubitSlug Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\SlugService directly
 */

use AtomExtensions\Services\SlugService;

class QubitSlug
{
    public const SLUG_BASIS_TITLE = SlugService::SLUG_BASIS_TITLE;
    public const SLUG_BASIS_IDENTIFIER = SlugService::SLUG_BASIS_IDENTIFIER;
    public const SLUG_BASIS_REFERENCE_CODE = SlugService::SLUG_BASIS_REFERENCE_CODE;
    public const SLUG_RESTRICTIVE = SlugService::SLUG_RESTRICTIVE;
    public const SLUG_PERMISSIVE = SlugService::SLUG_PERMISSIVE;

    /**
     * Get valid slug character class regex.
     * Used by arRestApiPlugin for route parameter constraints.
     */
    public static function getValidSlugChars(): string
    {
        return 'a-zA-Z0-9\\-_';
    }
}
