<?php

namespace AtomFramework\Extensions\Contracts;

use Illuminate\Support\Collection;

interface ExtensionManagerContract
{
    /**
     * Discover all available extensions in the plugins directory
     */
    public function discover(): Collection;

    /**
     * Get all registered extensions
     */
    public function all(): Collection;

    /**
     * Get extensions by status
     */
    public function getByStatus(string $status): Collection;

    /**
     * Find extension by machine name
     */
    public function find(string $machineName): ?array;

    /**
     * Install an extension
     */
    public function install(string $machineName): bool;

    /**
     * Uninstall an extension
     */
    public function uninstall(string $machineName, bool $backup = true): bool;

    /**
     * Enable an extension
     */
    public function enable(string $machineName): bool;

    /**
     * Disable an extension
     */
    public function disable(string $machineName): bool;

    /**
     * Restore a pending deletion
     */
    public function restore(string $machineName): bool;

    /**
     * Check if extension is installed
     */
    public function isInstalled(string $machineName): bool;

    /**
     * Check if extension is enabled
     */
    public function isEnabled(string $machineName): bool;

    /**
     * Get extension setting
     */
    public function getSetting(string $key, ?int $extensionId = null, $default = null);

    /**
     * Set extension setting
     */
    public function setSetting(string $key, $value, ?int $extensionId = null): bool;
}