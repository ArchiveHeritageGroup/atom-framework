<?php

/**
 * Action Stack Autoloader.
 *
 * Loads the Symfony 1.x action/component class hierarchy in dependency order.
 * All classes are guarded with class_exists() — they never override real Symfony classes.
 *
 * Load order:
 *   1. sfParameterHolder (used by sfComponent)
 *   2. sfException → sfStopException, sfError404Exception
 *   3. sfComponent (base)
 *   4. sfAction (extends sfComponent)
 *   5. sfActions (extends sfAction)
 *   6. sfComponents (extends sfComponent)
 */

$actionDir = __DIR__;

// 1. sfParameterHolder — dependency for sfComponent
if (!class_exists('sfParameterHolder', false)) {
    require_once $actionDir . '/sfParameterHolder.php';
}

// 2. Exception hierarchy
if (!class_exists('sfException', false)) {
    require_once $actionDir . '/sfException.php';
}
if (!class_exists('sfStopException', false)) {
    require_once $actionDir . '/sfStopException.php';
}
if (!class_exists('sfError404Exception', false)) {
    require_once $actionDir . '/sfError404Exception.php';
}

// 3. sfComponent — base for all actions/components
if (!class_exists('sfComponent', false)) {
    require_once $actionDir . '/sfComponent.php';
}

// 4. sfAction — extends sfComponent
if (!class_exists('sfAction', false)) {
    require_once $actionDir . '/sfAction.php';
}

// 5. sfActions — extends sfAction (multi-action dispatcher)
if (!class_exists('sfActions', false)) {
    require_once $actionDir . '/sfActions.php';
}

// 6. sfComponents — extends sfComponent (multi-component dispatcher)
if (!class_exists('sfComponents', false)) {
    require_once $actionDir . '/sfComponents.php';
}
