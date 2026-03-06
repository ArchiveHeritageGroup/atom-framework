<?php

/**
 * sfParameterHolder — Compatibility shim.
 *
 * Faithful port of vendor/symfony/lib/util/sfParameterHolder.class.php.
 * Simple key/value store used by sfComponent, sfAction, and sfWebRequest.
 */

if (!class_exists('sfParameterHolder', false)) {
    class sfParameterHolder implements \Serializable
    {
        protected $parameters = [];

        /**
         * @param  string $name     Parameter name
         * @param  mixed  $default  Default value if parameter not found
         *
         * @return mixed
         */
        public function &get($name, $default = null)
        {
            if (array_key_exists($name, $this->parameters)) {
                $value = &$this->parameters[$name];

                return $value;
            }

            return $default;
        }

        /**
         * @return array All parameters
         */
        public function &getAll()
        {
            return $this->parameters;
        }

        /**
         * @return array Parameter names
         */
        public function getNames()
        {
            return array_keys($this->parameters);
        }

        /**
         * @param  string $name
         *
         * @return bool
         */
        public function has($name)
        {
            return array_key_exists($name, $this->parameters);
        }

        /**
         * @param  string $name
         * @param  mixed  $value
         */
        public function set($name, $value)
        {
            $this->parameters[$name] = $value;
        }

        /**
         * @param  string $name
         * @param  mixed  $value
         */
        public function setByRef($name, &$value)
        {
            $this->parameters[$name] = &$value;
        }

        /**
         * @param  array $parameters  Key/value pairs to add
         */
        public function add($parameters)
        {
            if (null === $parameters) {
                return;
            }

            foreach ($parameters as $key => $value) {
                $this->parameters[$key] = $value;
            }
        }

        /**
         * @param  array $parameters  Key/value pairs to add by reference
         */
        public function addByRef(&$parameters)
        {
            foreach ($parameters as $key => &$value) {
                $this->parameters[$key] = &$value;
            }
        }

        /**
         * @param  string $name
         *
         * @return mixed  Removed value or null
         */
        public function remove($name)
        {
            $retval = null;

            if (array_key_exists($name, $this->parameters)) {
                $retval = $this->parameters[$name];
                unset($this->parameters[$name]);
            }

            return $retval;
        }

        /**
         * Clear all parameters.
         */
        public function clear()
        {
            $this->parameters = [];
        }

        // ── Serializable ────────────────────────────────────────────────

        public function serialize()
        {
            return serialize($this->__serialize());
        }

        public function unserialize($data)
        {
            $this->__unserialize(unserialize($data));
        }

        public function __serialize(): array
        {
            return $this->parameters;
        }

        public function __unserialize(array $data): void
        {
            $this->parameters = $data;
        }
    }
}
