<?php

/**
 * Route class for "add" actions (/term/add, /accession/add, etc.)
 *
 * Rejects URL generation when an existing resource is referenced. The "add"
 * route is for creating NEW resources only. When the caller passes a resource
 * object ($params[0]) or an explicit slug, they want to view/edit an existing
 * resource — not create a new one. Without this guard, /term/add matches any
 * params with module=term because extra params become query strings, stealing
 * URLs from the catch-all /:slug route and /:slug/edit route.
 */
class AddActionRoute extends sfRoute
{
    public function matchesParameters($params, $context = [])
    {
        if (is_array($params)) {
            // Reject when an existing resource object is passed —
            // add routes are for NEW resources, not existing ones
            if (isset($params[0]) && is_object($params[0])) {
                return false;
            }

            // Also reject explicit slug parameter
            if (isset($params['slug'])) {
                return false;
            }
        }

        return parent::matchesParameters($params, $context);
    }
}
