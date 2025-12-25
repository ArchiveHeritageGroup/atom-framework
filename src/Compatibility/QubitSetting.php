<?php

/**
 * QubitSetting Compatibility Layer.
 *
 * Provides backward compatibility for code using QubitSetting.
 * Delegates all calls to SettingService.
 *
 * USAGE: Include this file to enable transparent migration.
 * Existing code using QubitSetting will work without modification.
 *
 * @deprecated Use AtomExtensions\Services\SettingService directly
 */

use AtomExtensions\Services\SettingService;

class QubitSetting
{
    public $id;
    public $name;
    public $scope;
    public $value;
    public $editable = true;
    public $deleteable = true;
    public $source_culture = 'en';

    /**
     * Get setting by name.
     */
    public static function getByName(string $name): ?self
    {
        $result = SettingService::getByName($name);
        return $result ? self::fromObject($result) : null;
    }

    /**
     * Get setting by name and scope.
     */
    public static function getByNameAndScope(string $name, string $scope): ?self
    {
        $result = SettingService::getByNameAndScope($name, $scope);
        return $result ? self::fromObject($result) : null;
    }

    /**
     * Get settings by scope.
     */
    public static function getByScope(string $scope): array
    {
        $results = SettingService::getByScope($scope);
        return $results->map(fn($r) => self::fromObject($r))->toArray();
    }

    /**
     * Get setting by ID.
     */
    public static function getById(int $id): ?self
    {
        $result = SettingService::getById($id);
        return $result ? self::fromObject($result) : null;
    }

    /**
     * Find and save setting.
     */
    public static function findAndSave(string $name, ?string $value, array $options = []): self
    {
        $result = SettingService::findAndSave($name, $value, $options);
        return self::fromObject($result);
    }

    /**
     * Create new setting.
     */
    public static function createNewSetting(string $name, ?string $value, array $options = []): self
    {
        $result = SettingService::createNewSetting($name, $value, $options);
        return self::fromObject($result);
    }

    /**
     * Get value with options.
     */
    public function getValue(array $options = []): ?string
    {
        if (isset($options['sourceCulture']) && $options['sourceCulture']) {
            return SettingService::getValue($this->name, ['sourceCulture' => true]);
        }
        return $this->value;
    }

    /**
     * Set value.
     */
    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Save setting.
     */
    public function save(): bool
    {
        if ($this->id) {
            return SettingService::save($this->id, $this->value);
        }

        $result = SettingService::createNewSetting($this->name, $this->value, [
            'scope' => $this->scope,
            'editable' => $this->editable,
            'deleteable' => $this->deleteable,
        ]);

        $this->id = $result->id;
        return true;
    }

    /**
     * Delete setting.
     */
    public function delete(): bool
    {
        if ($this->id) {
            return SettingService::delete($this->id);
        }
        return false;
    }

    /**
     * Create from object.
     */
    private static function fromObject(object $obj): self
    {
        $setting = new self();
        $setting->id = $obj->id ?? null;
        $setting->name = $obj->name ?? null;
        $setting->scope = $obj->scope ?? null;
        $setting->value = $obj->value ?? null;
        $setting->editable = $obj->editable ?? true;
        $setting->deleteable = $obj->deleteable ?? true;
        $setting->source_culture = $obj->source_culture ?? 'en';
        return $setting;
    }
}
