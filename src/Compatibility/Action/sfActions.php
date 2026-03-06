<?php

/**
 * sfActions — Compatibility shim.
 *
 * Multi-action controller: dispatches to execute{ActionName}() methods.
 * This is the base class that AhgActions extends.
 */

if (!class_exists('sfActions', false)) {
    class sfActions extends sfAction
    {
        /**
         * Dispatch to the appropriate execute method.
         *
         * @param  sfWebRequest $request
         *
         * @return string  View name
         *
         * @throws sfInitializationException If action method not found
         */
        public function execute($request)
        {
            // Build method name: execute + ActionName (first letter uppercase)
            $actionToRun = 'execute' . ucfirst($this->getActionName());

            if (!is_callable([$this, $actionToRun])) {
                // Try lowercase as fallback
                $actionToRun = 'execute' . $this->getActionName();

                if (!is_callable([$this, $actionToRun])) {
                    throw new \sfInitializationException(sprintf(
                        'sfActions [execute] action "%s" does not exist in "%s".',
                        $this->getActionName(),
                        get_class($this)
                    ));
                }
            }

            return $this->$actionToRun($request);
        }
    }
}
