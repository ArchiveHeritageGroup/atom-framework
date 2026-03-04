<?php

/**
 * QubitRepository Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitRepository.php.
 */

if (!class_exists('QubitRepository', false)) {
    class QubitRepository
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'repository';
        protected static string $i18nTableName = 'repository_i18n';

        public const ROOT_ID = 6;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }

        /**
         * Get country code from primary contact.
         *
         * @param array $options Options
         *
         * @return string|null
         */
        public function getCountryCode($options = [])
        {
            return $this->getFromPrimaryOrFirstValidContact('country_code');
        }

        /**
         * Get formatted country name from primary contact's country code.
         *
         * @return string|null
         */
        public function getCountry()
        {
            $code = $this->getCountryCode();
            if (!$code) {
                return null;
            }

            // Try to use Symfony's culture info if available
            if (function_exists('format_country')) {
                return format_country($code);
            }

            return $code;
        }

        /**
         * Get region from primary contact.
         *
         * @param array $options Options
         *
         * @return string|null
         */
        public function getRegion($options = [])
        {
            return $this->getFromPrimaryOrFirstValidContact('region');
        }

        /**
         * Get city from primary contact.
         *
         * @param array $options Options
         *
         * @return string|null
         */
        public function getCity($options = [])
        {
            return $this->getFromPrimaryOrFirstValidContact('city');
        }

        /**
         * Get the uploads path for this repository.
         *
         * @param bool $absolute Return absolute filesystem path if true
         *
         * @return string
         */
        public function getUploadsPath($absolute = false)
        {
            $base = $absolute
                ? (\sfConfig::get('sf_web_dir', '/usr/share/nginx/archive') . '/uploads')
                : '/uploads';

            return $base . '/r/' . $this->slug;
        }

        /**
         * Get logo image path.
         *
         * @param bool $absolute Return absolute filesystem path if true
         *
         * @return string
         */
        public function getLogoPath($absolute = false)
        {
            return $this->getUploadsPath($absolute) . '/conf/logo.png';
        }

        /**
         * Get banner image path.
         *
         * @param bool $absolute Return absolute filesystem path if true
         *
         * @return string
         */
        public function getBannerPath($absolute = false)
        {
            return $this->getUploadsPath($absolute) . '/conf/banner.png';
        }

        /**
         * Check if repository has a logo image.
         *
         * @return bool
         */
        public function existsLogo()
        {
            return is_file($this->getLogoPath(true));
        }

        /**
         * Check if repository has a banner image.
         *
         * @return bool
         */
        public function existsBanner()
        {
            return is_file($this->getBannerPath(true));
        }

        /**
         * Get a field value from the primary contact, falling back to first valid contact.
         *
         * @param string $field Column name on contact_information table
         *
         * @return mixed|null
         */
        private function getFromPrimaryOrFirstValidContact($field)
        {
            try {
                $db = \Illuminate\Database\Capsule\Manager::connection();

                // Try primary contact first
                $row = $db->table('contact_information')
                    ->where('actor_id', $this->id)
                    ->where('primary_contact', 1)
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->first();

                if ($row && !empty($row->{$field})) {
                    return $row->{$field};
                }

                // Fallback: first contact with a non-empty value
                $row = $db->table('contact_information')
                    ->where('actor_id', $this->id)
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->first();

                return $row ? $row->{$field} : null;
            } catch (\Throwable $e) {
                return null;
            }
        }
    }
}
