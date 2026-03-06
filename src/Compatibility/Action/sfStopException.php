<?php

/**
 * sfStopException — Compatibility shim.
 *
 * Thrown by forward() and redirect() to stop action execution.
 * In Symfony 1.x, this exception is caught by the filter chain and
 * triggers response output. The empty printStackTrace() prevents error display.
 */

if (!class_exists('sfStopException', false)) {
    class sfStopException extends sfException
    {
        /**
         * Override: do nothing — this exception is a flow control signal, not an error.
         */
        public function printStackTrace()
        {
            // Intentionally empty — caught by controller/filter chain
        }

        public function getName()
        {
            return 'sfStopException';
        }
    }
}
