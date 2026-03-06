<?php

/**
 * sfFilter — Compatibility shim.
 *
 * Abstract base class for Symfony 1.x request filters.
 */

if (!class_exists('sfFilter', false)) {
    abstract class sfFilter
    {
        protected $context = null;
        protected $filterConfig = [];

        protected static $filterCalled = [];

        /**
         * Initialize the filter.
         *
         * @param  sfContext $context
         * @param  array    $parameters
         */
        public function initialize($context, $parameters = [])
        {
            $this->context = $context;
            $this->filterConfig = $parameters;
        }

        /**
         * Execute the filter.
         *
         * @param  sfFilterChain $filterChain
         */
        abstract public function execute($filterChain);

        /**
         * @return sfContext
         */
        public function getContext()
        {
            return $this->context;
        }

        /**
         * Check if this filter has already been called.
         *
         * @return bool
         */
        public function isFirstCall()
        {
            $class = get_class($this);
            if (isset(self::$filterCalled[$class])) {
                return false;
            }

            self::$filterCalled[$class] = true;

            return true;
        }
    }
}
