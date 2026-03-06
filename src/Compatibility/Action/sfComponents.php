<?php

/**
 * sfComponents — Compatibility shim.
 *
 * Multi-component controller: dispatches to execute{ComponentName}() methods.
 * This is the base class that AhgComponents extends.
 */

if (!class_exists('sfComponents', false)) {
    class sfComponents extends sfComponent
    {
        /**
         * Dispatch to the appropriate execute method.
         *
         * @param  sfWebRequest $request
         *
         * @return string  View name
         *
         * @throws sfInitializationException If component method not found
         */
        public function execute($request)
        {
            // Build method name: execute + ComponentName (first letter uppercase)
            $actionToRun = 'execute' . ucfirst($this->getActionName());

            if (!is_callable([$this, $actionToRun])) {
                // Try lowercase as fallback
                $actionToRun = 'execute' . $this->getActionName();

                if (!is_callable([$this, $actionToRun])) {
                    throw new \sfInitializationException(sprintf(
                        'sfComponents [execute] component "%s" does not exist in "%s".',
                        $this->getActionName(),
                        get_class($this)
                    ));
                }
            }

            return $this->$actionToRun($request);
        }
    }
}
