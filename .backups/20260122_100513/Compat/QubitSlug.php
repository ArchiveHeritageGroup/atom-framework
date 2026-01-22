<?php

// Dont define if were in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}

/**
 * QubitSlug Compatibility Layer
 */
if (!class_exists('QubitSlug', false)) {
    class QubitSlug
    {
        const SLUG_BASIS_TITLE = 0;
        const SLUG_BASIS_IDENTIFIER = 1;
        const SLUG_BASIS_REFERENCE_CODE = 2;
        const SLUG_RESTRICTIVE = 0;
        const SLUG_PERMISSIVE = 1;
    }
}
