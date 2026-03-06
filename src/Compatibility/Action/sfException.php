<?php

/**
 * sfException — Compatibility shim.
 *
 * Base exception for Symfony 1.x. Provides printStackTrace() and context access.
 */

if (!class_exists('sfException', false)) {
    class sfException extends \Exception
    {
        protected static $lastException = null;

        /**
         * Wrap another exception.
         */
        public static function createFromException(\Throwable $e)
        {
            $exception = new static($e->getMessage(), $e->getCode(), $e);
            self::$lastException = $exception;

            return $exception;
        }

        /**
         * Get the last exception.
         */
        public static function getLastException()
        {
            return self::$lastException;
        }

        /**
         * Print the stack trace (HTML or text).
         */
        public function printStackTrace()
        {
            // In standalone mode, just let the exception propagate
            if (class_exists('sfConfig', false) && \sfConfig::get('sf_debug', false)) {
                error_log(sprintf(
                    "sfException: %s in %s on line %d\n%s",
                    $this->getMessage(),
                    $this->getFile(),
                    $this->getLine(),
                    $this->getTraceAsString()
                ));
            }

            throw $this;
        }

        /**
         * Get the name of the exception.
         */
        public function getName()
        {
            return 'sfException';
        }
    }
}

// ── sfInitializationException ────────────────────────────────────────

if (!class_exists('sfInitializationException', false)) {
    class sfInitializationException extends sfException
    {
        public function getName()
        {
            return 'sfInitializationException';
        }
    }
}

// ── sfConfigurationException ─────────────────────────────────────────

if (!class_exists('sfConfigurationException', false)) {
    class sfConfigurationException extends sfException
    {
        public function getName()
        {
            return 'sfConfigurationException';
        }
    }
}

// ── sfParseException ─────────────────────────────────────────────────

if (!class_exists('sfParseException', false)) {
    class sfParseException extends sfException
    {
        public function getName()
        {
            return 'sfParseException';
        }
    }
}
