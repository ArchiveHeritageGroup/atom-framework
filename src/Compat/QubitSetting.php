<?php

/**
 * QubitSetting Compatibility Layer
 * 
 * Only activates when core QubitSetting is not available.
 * In normal AtoM context, the core class is used.
 */

// Don't define if we're in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}

if (!class_exists('QubitSetting', false)) {
    class QubitSetting
    {
        public $id;
        public $name;
        public $scope;
        public $value;
        public $sourceCulture;
        
        // Constants from core
        const NAME = 'name';
        const SCOPE = 'scope';
        const SOURCE_CULTURE = 'source_culture';
        
        public static function getByName(string $name, array $options = []): ?object
        {
            $culture = $options['culture'] ?? null;
            return \AtomExtensions\Services\SettingService::getByName($name, $culture);
        }
        
        public static function getByNameAndScope(string $name, string $scope): ?object
        {
            return \AtomExtensions\Services\SettingService::getByNameAndScope($name, $scope);
        }
        
        public static function getByScope(string $scope): \Illuminate\Support\Collection
        {
            return \AtomExtensions\Services\SettingService::getByScope($scope);
        }
        
        public static function getById(int $id): ?object
        {
            return \AtomExtensions\Services\SettingService::getById($id);
        }
        
        public static function findAndSave(string $name, $value, array $options = []): object
        {
            return \AtomExtensions\Services\SettingService::findAndSave($name, $value, $options);
        }
        
        public static function createNewSetting(string $name, $value, array $options = []): object
        {
            return \AtomExtensions\Services\SettingService::createNewSetting($name, $value, $options);
        }
        
        public function save(): self
        {
            if ($this->id) {
                \AtomExtensions\Services\SettingService::findAndSave($this->name, $this->value, [
                    'scope' => $this->scope,
                ]);
            } else {
                $result = \AtomExtensions\Services\SettingService::createNewSetting($this->name, $this->value, [
                    'scope' => $this->scope,
                ]);
                $this->id = $result->id;
            }
            return $this;
        }
        
        public function setValue($value): self
        {
            $this->value = $value;
            return $this;
        }
        
        public function getValue(array $options = []): ?string
        {
            return $this->value;
        }
        
        public function delete(): bool
        {
            if ($this->id) {
                \Illuminate\Database\Capsule\Manager::table('setting_i18n')->where('id', $this->id)->delete();
                \Illuminate\Database\Capsule\Manager::table('setting')->where('id', $this->id)->delete();
                return true;
            }
            return false;
        }
    }
}
