<?php

/**
 * sfDateFormat — Compatibility shim.
 *
 * Thin wrapper around PHP's IntlDateFormatter for locale-aware date formatting.
 */

if (!class_exists('sfDateFormat', false)) {
    class sfDateFormat
    {
        protected $culture;

        /**
         * @param  string|sfCultureInfo|null $cultureOrFormatInfo
         */
        public function __construct($cultureOrFormatInfo = null)
        {
            if ($cultureOrFormatInfo instanceof sfCultureInfo) {
                $this->culture = $cultureOrFormatInfo->getCulture();
            } elseif (is_string($cultureOrFormatInfo)) {
                $this->culture = $cultureOrFormatInfo;
            } else {
                $this->culture = class_exists('sfConfig', false)
                    ? \sfConfig::get('sf_default_culture', 'en')
                    : 'en';
            }
        }

        /**
         * Format a date/time value.
         *
         * @param  mixed  $value    Date value (timestamp, string, or DateTime)
         * @param  string $pattern  Pattern string (Symfony 1.x ICU-style or named)
         * @param  string $charset  Character set
         *
         * @return string
         */
        public function format($value, $pattern = 'd', $charset = 'UTF-8')
        {
            if (null === $value || '' === $value) {
                return '';
            }

            // Convert to timestamp
            if ($value instanceof \DateTimeInterface) {
                $timestamp = $value->getTimestamp();
            } elseif (is_numeric($value)) {
                $timestamp = (int) $value;
            } else {
                $timestamp = strtotime((string) $value);
                if (false === $timestamp) {
                    return (string) $value;
                }
            }

            // Map Symfony 1.x named patterns to PHP date formats
            $phpFormat = $this->resolvePattern($pattern);

            // Use IntlDateFormatter if available
            if (extension_loaded('intl') && class_exists('IntlDateFormatter')) {
                try {
                    $formatter = new \IntlDateFormatter(
                        $this->culture,
                        \IntlDateFormatter::MEDIUM,
                        \IntlDateFormatter::NONE
                    );
                    $formatter->setPattern($phpFormat);
                    $result = $formatter->format($timestamp);
                    if (false !== $result) {
                        return $result;
                    }
                } catch (\Exception $e) {
                    // Fall through to date()
                }
            }

            return date($phpFormat, $timestamp);
        }

        /**
         * Parse a date string.
         *
         * @param  string $value    Date string
         * @param  string $pattern  Pattern
         *
         * @return int|false  Timestamp or false
         */
        public function parse($value, $pattern = 'd')
        {
            if (null === $value || '' === $value) {
                return false;
            }

            return strtotime((string) $value);
        }

        /**
         * Resolve a Symfony 1.x named pattern to an ICU/PHP date pattern.
         *
         * @param  string $pattern
         *
         * @return string
         */
        protected function resolvePattern($pattern)
        {
            // Symfony 1.x named patterns
            $namedPatterns = [
                'F' => 'EEEE, MMMM d, yyyy HH:mm:ss',  // Full date/time
                'D' => 'MMMM d, yyyy',                    // Full date
                'P' => 'MMMM d, yyyy HH:mm',              // Full date + short time
                'd' => 'MMM d, yyyy',                      // Medium date
                'p' => 'MMM d, yyyy HH:mm',               // Medium date + short time
                'g' => 'M/d/yyyy',                         // Short date
                'G' => 'M/d/yyyy HH:mm:ss',               // Short date/time
                'T' => 'HH:mm:ss',                         // Full time
                't' => 'HH:mm',                            // Short time
                'I' => 'yyyy-MM-dd',                       // ISO date
                'i' => "yyyy-MM-dd'T'HH:mm:ss",           // ISO date/time
            ];

            return $namedPatterns[$pattern] ?? $pattern;
        }
    }
}
