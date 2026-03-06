<?php

/**
 * sfCultureInfo — Compatibility shim.
 *
 * Minimal stub for i18n culture data. Returns basic info for the current culture.
 */

if (!class_exists('sfCultureInfo', false)) {
    class sfCultureInfo
    {
        protected $culture;

        protected static $instances = [];

        /**
         * @param  string $culture  Culture code (e.g., 'en', 'en_US')
         */
        public function __construct($culture = 'en')
        {
            $this->culture = $culture;
        }

        /**
         * Get a singleton instance for the given culture.
         *
         * @param  string|null $culture
         *
         * @return static
         */
        public static function getInstance($culture = null)
        {
            if (null === $culture) {
                $culture = class_exists('sfConfig', false)
                    ? \sfConfig::get('sf_default_culture', 'en')
                    : 'en';
            }

            if (!isset(self::$instances[$culture])) {
                self::$instances[$culture] = new static($culture);
            }

            return self::$instances[$culture];
        }

        /**
         * @return string
         */
        public function getCulture()
        {
            return $this->culture;
        }

        /**
         * Get the display name for this culture.
         *
         * @return string
         */
        public function getName()
        {
            if (class_exists('Locale', false) || extension_loaded('intl')) {
                return \Locale::getDisplayName($this->culture) ?: $this->culture;
            }

            return $this->culture;
        }

        /**
         * Get the language component of the culture.
         *
         * @return string
         */
        public function getLanguage()
        {
            $parts = explode('_', $this->culture);

            return $parts[0] ?? 'en';
        }

        /**
         * Get the region/country component of the culture.
         *
         * @return string|null
         */
        public function getRegion()
        {
            $parts = explode('_', $this->culture);

            return $parts[1] ?? null;
        }

        /**
         * Get language names for the current culture.
         *
         * @return array  Language code => language name
         */
        public function getLanguages()
        {
            // Return common languages as fallback
            return [
                'en' => 'English',
                'fr' => 'French',
                'es' => 'Spanish',
                'de' => 'German',
                'pt' => 'Portuguese',
                'nl' => 'Dutch',
                'af' => 'Afrikaans',
                'zu' => 'Zulu',
                'xh' => 'Xhosa',
                'st' => 'Sesotho',
            ];
        }

        /**
         * Get country names for the current culture.
         *
         * @return array  Country code => country name
         */
        public function getCountries()
        {
            return [
                'ZA' => 'South Africa',
                'ZW' => 'Zimbabwe',
                'US' => 'United States',
                'GB' => 'United Kingdom',
                'CA' => 'Canada',
                'AU' => 'Australia',
            ];
        }

        /**
         * Get the date/time format info.
         *
         * @return sfDateTimeFormatInfo|null
         */
        public function getDateTimeFormat()
        {
            return null;
        }

        /**
         * Get the number format info.
         *
         * @return sfNumberFormatInfo|null
         */
        public function getNumberFormat()
        {
            return null;
        }

        /**
         * @return string
         */
        public function __toString()
        {
            return $this->culture;
        }
    }
}
