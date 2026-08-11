<?php

/**
 * A plugin route that will not volunteer itself for URL generation.
 *
 * THE PROBLEM THIS SOLVES
 *
 * RouteLoader prepends every plugin route, which it must: a plugin path has to be
 * matched before AtoM's catch-all /:slug, or the slug route swallows it.
 *
 * Prepending is safe for matching and dangerous for generation. When url_for() is
 * called with an array and no route name, sfPatternRouting walks the collection in
 * order and returns the first route whose matchesParameters() is true. And
 * sfRoute::matchesParameters() merges the route's own defaults over the supplied
 * parameters before comparing them:
 *
 *     $tparams = array_merge($this->defaults, $params);
 *     foreach ($this->defaults as $key => $value) {
 *         if (!isset($this->variables[$key]) && $tparams[$key] != $value) {
 *             return false;
 *         }
 *     }
 *
 * So a caller that omits module and action has those keys filled in from the
 * route's own defaults, which then trivially equal themselves. A route with no
 * variables has nothing else to fail on, and extra parameters are permitted as a
 * query string. The result: the first variable-free route in the collection
 * matches essentially any generation call.
 *
 * Measured 2026-08-11 on a clean AtoM 2.10. Enabling ahgExtendedRightsPlugin put
 * its routes at the front, and every link on the home page - all eight "Popular
 * this week" records - generated as
 *
 *     /ahg/rights/embargo/edit/?0%5BdisableNestedSetUpdating%5D=0&0%5BindexOnSave%5D=1
 *
 * the record object serialised into the query string of an unrelated static
 * route. The four routes ahead of it were unaffected only because they carry an
 * :id and so require that parameter. Nothing about this is specific to embargo:
 * whichever variable-free plugin route happens to sit at position 0 captures the
 * site's links, so the failure moves as plugins are enabled.
 *
 * THE FIX
 *
 * Require the caller to have named the module and action explicitly. Incoming
 * request matching is untouched (that goes through matchesUrl, not this method),
 * generation by route name is untouched (sfPatternRouting::generate short-circuits
 * on a name), and a deliberate url_for(['module' => 'embargo', ...]) still works.
 * Only the implicit, unnamed call - the one that had no business landing here -
 * stops matching.
 */
class ExplicitRoute extends sfRoute
{
    public function matchesParameters($params, $context = [])
    {
        if (!is_array($params) || !isset($params['module'], $params['action'])) {
            return false;
        }

        return parent::matchesParameters($params, $context);
    }
}
