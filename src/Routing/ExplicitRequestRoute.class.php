<?php

/**
 * The method-restricted counterpart of ExplicitRoute.
 *
 * get()/post() routes need SafeRequestRoute so the sf_method requirement is
 * enforced and so probing during URL generation cannot throw on a Qubit object.
 * They need the generation guard for exactly the same reason plain routes do -
 * see ExplicitRoute for the mechanism and the measurement.
 */
class ExplicitRequestRoute extends SafeRequestRoute
{
    public function matchesParameters($params, $context = [])
    {
        if (!is_array($params) || !isset($params['module'], $params['action'])) {
            return false;
        }

        return parent::matchesParameters($params, $context);
    }
}
