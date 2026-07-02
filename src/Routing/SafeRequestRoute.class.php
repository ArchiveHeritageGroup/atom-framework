<?php

/**
 * A method-aware route (get()/post()) that is safe to probe during URL
 * generation from a model object.
 *
 * Base sfRequestRoute::matchesParameters() runs `isset($params['sf_method'])`.
 * During generate(null, $object) — e.g. building an accession's URL — $params is
 * a Qubit model whose ArrayAccess isset() calls Qubit __isset(), which THROWS
 * "Unknown record property sf_method" for any non-column key. That 500s pages
 * like /accession/add whose forms generate URLs from an unsaved resource.
 *
 * sf_method only ever applies to real request parameters (arrays), never to a
 * model object, so we only run the sf_method enforcement for array params and
 * otherwise fall straight through to plain route matching.
 */
class SafeRequestRoute extends sfRequestRoute
{
    public function matchesParameters($params, $context = array())
    {
        if (is_array($params)) {
            return parent::matchesParameters($params, $context);
        }

        // Object params (URL generation from a model): sf_method cannot apply.
        return sfRoute::matchesParameters($params, $context);
    }
}
