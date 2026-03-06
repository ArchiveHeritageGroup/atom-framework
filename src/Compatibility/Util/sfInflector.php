<?php

/**
 * sfInflector — Compatibility shim.
 *
 * String transformation utility from vendor/symfony/lib/util/sfInflector.class.php.
 * Pure string functions — no external dependencies.
 */

if (!class_exists('sfInflector', false)) {
    class sfInflector
    {
        /**
         * Convert an underscored or dashed string to CamelCase.
         *
         * @param  string $lower_case_and_underscored_word
         *
         * @return string
         */
        public static function camelize($lower_case_and_underscored_word)
        {
            $result = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $lower_case_and_underscored_word)));

            // Handle module separator (/) -> ::
            $result = str_replace('/', '::', $result);

            return $result;
        }

        /**
         * Convert a CamelCase string to underscored.
         *
         * @param  string $camel_cased_word
         *
         * @return string
         */
        public static function underscore($camel_cased_word)
        {
            $tmp = $camel_cased_word;
            $tmp = str_replace('::', '/', $tmp);
            $tmp = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $tmp);
            $tmp = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $tmp);

            return strtolower(str_replace('-', '_', $tmp));
        }

        /**
         * Convert a word to its table name equivalent.
         *
         * @param  string $class_name
         *
         * @return string
         */
        public static function tableize($class_name)
        {
            return self::underscore($class_name);
        }

        /**
         * Convert a table name to its class name equivalent.
         *
         * @param  string $table_name
         *
         * @return string
         */
        public static function classify($table_name)
        {
            return self::camelize($table_name);
        }

        /**
         * Convert an underscored string to human-readable form.
         *
         * @param  string $lower_case_and_underscored_word
         *
         * @return string
         */
        public static function humanize($lower_case_and_underscored_word)
        {
            if ('_id' === substr($lower_case_and_underscored_word, -3)) {
                $lower_case_and_underscored_word = substr($lower_case_and_underscored_word, 0, -3);
            }

            return ucfirst(str_replace('_', ' ', $lower_case_and_underscored_word));
        }

        /**
         * Strip module/namespace prefix from a class name.
         *
         * @param  string $class_name_in_module
         *
         * @return string
         */
        public static function demodulize($class_name_in_module)
        {
            $pos = strrpos($class_name_in_module, '::');
            if (false !== $pos) {
                return substr($class_name_in_module, $pos + 2);
            }

            return $class_name_in_module;
        }

        /**
         * Convert a class name to a foreign key name.
         *
         * @param  string $class_name
         * @param  bool   $separate_class_name_and_id_with_underscore
         *
         * @return string
         */
        public static function foreign_key($class_name, $separate_class_name_and_id_with_underscore = true)
        {
            return self::underscore(self::demodulize($class_name))
                . ($separate_class_name_and_id_with_underscore ? '_id' : 'id');
        }
    }
}
