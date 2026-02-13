<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for settings persistence.
 *
 * All settings handlers write QubitSetting objects. This interface
 * decouples controllers from Propel so the same handler code works
 * in both Symfony mode (PropelAdapter) and standalone Heratio mode
 * (future LaravelAdapter using DB::table('setting')).
 */
interface SettingsWriteServiceInterface
{
    /**
     * Save a setting value (non-localized).
     *
     * Creates the setting if it doesn't exist, updates if it does.
     *
     * @param string      $name   Setting name (e.g., 'hits_per_page')
     * @param mixed       $value  Setting value
     * @param string|null $scope  Optional scope (e.g., 'ldap', 'oai')
     */
    public function save(string $name, $value, ?string $scope = null): void;

    /**
     * Save a localized (i18n) setting value.
     *
     * @param string      $name    Setting name
     * @param mixed       $value   Setting value
     * @param string      $culture Culture code (e.g., 'en', 'af')
     * @param string|null $scope   Optional scope
     */
    public function saveLocalized(string $name, $value, string $culture, ?string $scope = null): void;

    /**
     * Save a serialized array value as a setting.
     *
     * @param string      $name  Setting name
     * @param array       $value Array to serialize
     * @param string|null $scope Optional scope
     */
    public function saveSerialized(string $name, array $value, ?string $scope = null): void;

    /**
     * Delete a setting by name and optional scope.
     *
     * @return bool True if the setting existed and was deleted
     */
    public function delete(string $name, ?string $scope = null): bool;

    /**
     * Bulk-save multiple settings at once.
     *
     * @param array       $settings Associative array of name => value
     * @param string|null $scope    Optional scope applied to all
     */
    public function saveMany(array $settings, ?string $scope = null): void;
}
