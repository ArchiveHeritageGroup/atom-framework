<?php

/**
 * sfRoute — Compatibility shim.
 *
 * Minimal stub for Symfony 1.x route objects.
 * In standalone mode, routing is handled by RouteLoader/RouteCollector.
 * This stub exists to prevent class-not-found errors.
 */

if (!class_exists('sfRoute', false)) {
    class sfRoute
    {
        protected $pattern = '';
        protected $defaults = [];
        protected $requirements = [];
        protected $options = [];
        protected $parameters = [];

        /**
         * @param  string $pattern
         * @param  array  $defaults
         * @param  array  $requirements
         * @param  array  $options
         */
        public function __construct($pattern = '', $defaults = [], $requirements = [], $options = [])
        {
            $this->pattern = $pattern;
            $this->defaults = $defaults;
            $this->requirements = $requirements;
            $this->options = $options;
        }

        /**
         * Check if the route matches a URL.
         *
         * @param  string $url
         * @param  array  $context
         *
         * @return bool
         */
        public function matches($url, $context = [])
        {
            // Stub — in standalone mode, routing is handled externally
            return false;
        }

        /**
         * Generate a URL from route parameters.
         *
         * @param  array $params
         * @param  bool  $absolute
         *
         * @return string
         */
        public function generate($params, $absolute = false)
        {
            // Simple parameter substitution
            $url = $this->pattern;
            foreach ($params as $key => $value) {
                $url = str_replace(':' . $key, (string) $value, $url);
            }

            return $url;
        }

        /**
         * Get the route pattern.
         *
         * @return string
         */
        public function getPattern()
        {
            return $this->pattern;
        }

        /**
         * Get route defaults.
         *
         * @return array
         */
        public function getDefaults()
        {
            return $this->defaults;
        }

        /**
         * Get route requirements.
         *
         * @return array
         */
        public function getRequirements()
        {
            return $this->requirements;
        }

        /**
         * Get route options.
         *
         * @return array
         */
        public function getOptions()
        {
            return $this->options;
        }

        /**
         * Get route parameters (matched values).
         *
         * @return array
         */
        public function getParameters()
        {
            return $this->parameters;
        }

        /**
         * Get a specific route object (used by action->getRoute()->getObject()).
         *
         * @return object|null
         */
        public function getObject()
        {
            return $this->parameters['sf_subject'] ?? null;
        }
    }
}

// ── sfRequestRoute ───────────────────────────────────────────────────

if (!class_exists('sfRequestRoute', false)) {
    class sfRequestRoute extends sfRoute
    {
    }
}

// ── sfObjectRoute ────────────────────────────────────────────────────

if (!class_exists('sfObjectRoute', false)) {
    class sfObjectRoute extends sfRequestRoute
    {
        protected $object = null;

        /**
         * @return object|null
         */
        public function getObject()
        {
            return $this->object ?? parent::getObject();
        }

        /**
         * @param  object $object
         */
        public function setObject($object)
        {
            $this->object = $object;
        }
    }
}
