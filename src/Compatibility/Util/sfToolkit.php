<?php

/**
 * sfToolkit — Compatibility shim.
 *
 * Key utility methods from vendor/symfony/lib/util/sfToolkit.class.php.
 * Only the methods actually used by AHG plugins are included.
 */

if (!class_exists('sfToolkit', false)) {
    class sfToolkit
    {
        /**
         * Generate a URL-friendly slug from a string.
         *
         * @param  string $text
         * @param  string $separator
         *
         * @return string
         */
        public static function slugify($text, $separator = '-')
        {
            // Transliterate
            if (function_exists('transliterator_transliterate')) {
                $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            } else {
                $text = strtolower($text);
            }

            // Replace non-alphanumeric with separator
            $text = preg_replace('/[^a-z0-9]+/', $separator, $text);

            // Trim separators
            $text = trim($text, $separator);

            return $text ?: 'n-a';
        }

        /**
         * Recursively create a directory.
         *
         * @param  string $path
         * @param  int    $mode
         *
         * @return bool
         */
        public static function mkdir($path, $mode = 0777)
        {
            if (is_dir($path)) {
                return true;
            }

            return @mkdir($path, $mode, true);
        }

        /**
         * Clear all files in a directory.
         *
         * @param  string $directory
         */
        public static function clearDirectory($directory)
        {
            if (!is_dir($directory)) {
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
        }

        /**
         * Get a value from a nested array using path notation.
         *
         * @param  array       $values  The array to search
         * @param  string      $name    Path (e.g., 'foo[bar][baz]')
         * @param  mixed       $default Default if not found
         *
         * @return mixed
         */
        public static function getArrayValueForPath($values, $name, $default = null)
        {
            if (false === ($offset = strpos($name, '['))) {
                return $values[$name] ?? $default;
            }

            $key = substr($name, 0, $offset);

            if (!isset($values[$key])) {
                return $default;
            }

            $rest = substr($name, $offset + 1);
            $rest = str_replace(']', '', $rest);
            $parts = explode('[', $rest);

            $current = $values[$key];
            foreach ($parts as $part) {
                if ('' === $part) {
                    continue;
                }
                if (!is_array($current) || !isset($current[$part])) {
                    return $default;
                }
                $current = $current[$part];
            }

            return $current;
        }

        /**
         * Set a value in a nested array using path notation.
         *
         * @param  array  &$values  The array to modify
         * @param  string  $name    Path (e.g., 'foo[bar][baz]')
         * @param  mixed   $value   Value to set
         */
        public static function setArrayValueForPath(&$values, $name, $value)
        {
            if (false === ($offset = strpos($name, '['))) {
                $values[$name] = $value;

                return;
            }

            $key = substr($name, 0, $offset);
            $rest = substr($name, $offset + 1);
            $rest = str_replace(']', '', $rest);
            $parts = explode('[', $rest);

            if (!isset($values[$key]) || !is_array($values[$key])) {
                $values[$key] = [];
            }

            $current = &$values[$key];
            foreach ($parts as $part) {
                if ('' === $part) {
                    continue;
                }
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }

            $current = $value;
        }

        /**
         * Converts PHP-like attribute strings to arrays.
         *
         * @param  string $string  e.g., 'class="foo" id="bar"'
         *
         * @return array
         */
        public static function stringToArray($string)
        {
            $result = [];

            preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $string, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $result[$match[1]] = $match[2];
            }

            return $result;
        }

        /**
         * Literalize a string value.
         *
         * Converts: null, true, false, integers, floats, and defined constants.
         *
         * @param  string $value
         *
         * @return mixed
         */
        public static function literalize($value)
        {
            if (!is_string($value)) {
                return $value;
            }

            $lower = strtolower($value);

            switch (true) {
                case 'null' === $lower:
                    return null;
                case 'true' === $lower:
                case 'on' === $lower:
                case 'yes' === $lower:
                    return true;
                case 'false' === $lower:
                case 'off' === $lower:
                case 'no' === $lower:
                    return false;
                case ctype_digit(ltrim($value, '-')):
                    return (int) $value;
                case is_numeric($value):
                    return (float) $value;
            }

            // Check for constants
            if (defined($value)) {
                return constant($value);
            }

            return $value;
        }

        /**
         * Replace constants in a string (e.g., %SF_DATA_DIR%).
         *
         * @param  string $value
         *
         * @return string
         */
        public static function replaceConstants($value)
        {
            if (!is_string($value)) {
                return $value;
            }

            return preg_replace_callback(
                '/%(.+?)%/',
                function ($matches) {
                    return defined($matches[1]) ? constant($matches[1]) : $matches[0];
                },
                $value
            );
        }

        /**
         * Multiple regex replacement.
         *
         * @param  string $search   Subject
         * @param  array  $replacePairs  pattern => replacement
         *
         * @return string
         */
        public static function pregtr($search, $replacePairs)
        {
            foreach ($replacePairs as $pattern => $replacement) {
                $search = preg_replace($pattern, $replacement, $search);
            }

            return $search;
        }

        /**
         * Check if a path is absolute.
         *
         * @param  string $path
         *
         * @return bool
         */
        public static function isPathAbsolute($path)
        {
            if ('/' === $path[0] || '\\' === $path[0]
                || (strlen($path) > 3 && ctype_alpha($path[0]) && ':' === $path[1]
                    && ('\\' === $path[2] || '/' === $path[2]))
                || null !== parse_url($path, PHP_URL_SCHEME)
            ) {
                return true;
            }

            return false;
        }

        /**
         * Check if a string is valid UTF-8.
         *
         * @param  string $string
         *
         * @return bool
         */
        public static function isUTF8($string)
        {
            return mb_check_encoding($string, 'UTF-8');
        }

        /**
         * Check if array values are empty.
         *
         * @param  array $array
         *
         * @return bool
         */
        public static function isArrayValuesEmpty($array)
        {
            if (!is_array($array)) {
                return true;
            }

            foreach ($array as $value) {
                if (is_array($value)) {
                    if (!self::isArrayValuesEmpty($value)) {
                        return false;
                    }
                } elseif ('' !== $value && null !== $value) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Find the PHP CLI executable.
         *
         * @return string
         */
        public static function getPhpCli()
        {
            if (defined('PHP_BINARY')) {
                return PHP_BINARY;
            }

            return 'php';
        }
    }
}
