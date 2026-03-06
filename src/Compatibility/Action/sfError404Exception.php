<?php

/**
 * sfError404Exception — Compatibility shim.
 *
 * Thrown by forward404() and forward404Unless() in actions.
 */

if (!class_exists('sfError404Exception', false)) {
    class sfError404Exception extends sfException
    {
        /**
         * @param  string $message  Error message
         */
        public function __construct($message = null, $code = 0, ?\Throwable $previous = null)
        {
            parent::__construct($message ?? 'Not Found', $code ?: 404, $previous);
        }

        /**
         * Print error or forward to 404 page.
         */
        public function printStackTrace()
        {
            $debug = class_exists('sfConfig', false) ? \sfConfig::get('sf_debug', false) : true;

            if ($debug) {
                error_log('sfError404Exception: ' . $this->getMessage());
            }

            // Set 404 status on response if available
            try {
                if (class_exists('sfContext', false) && \sfContext::hasInstance()) {
                    $response = \sfContext::getInstance()->getResponse();
                    if ($response) {
                        $response->setStatusCode(404);
                    }

                    // In production mode, forward to error404 module
                    if (!$debug) {
                        $controller = \sfContext::getInstance()->getController();
                        if ($controller) {
                            $controller->forward(
                                \sfConfig::get('sf_error_404_module', 'default'),
                                \sfConfig::get('sf_error_404_action', 'error404')
                            );

                            return;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently ignore — we're already in error handling
            }

            throw $this;
        }

        public function getName()
        {
            return 'sfError404Exception';
        }
    }
}
