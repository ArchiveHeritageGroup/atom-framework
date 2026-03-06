<?php

/**
 * sfFilterChain — Compatibility shim.
 *
 * Minimal filter chain that executes registered filters in sequence.
 */

if (!class_exists('sfFilterChain', false)) {
    class sfFilterChain
    {
        protected $chain = [];
        protected $index = 0;

        /**
         * Register a filter in the chain.
         *
         * @param  sfFilter $filter
         */
        public function register($filter)
        {
            $this->chain[] = $filter;
        }

        /**
         * Execute the next filter in the chain.
         */
        public function execute()
        {
            if (isset($this->chain[$this->index])) {
                $filter = $this->chain[$this->index];
                ++$this->index;
                $filter->execute($this);
            }
        }

        /**
         * @return bool
         */
        public function hasFilter($class)
        {
            foreach ($this->chain as $filter) {
                if ($filter instanceof $class) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @return int
         */
        public function count()
        {
            return count($this->chain);
        }
    }
}
