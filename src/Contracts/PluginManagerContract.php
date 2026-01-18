<?php

declare(strict_types=1);

namespace AtomFramework\Contracts;

interface PluginManagerContract
{
    public function getAllPlugins(array $filters = []): array;
    public function getEnabledPlugins(): array;
    public function getPlugin(string $name): ?array;
    public function registerPlugin(array $pluginData): int;
    public function enablePlugin(string $name, ?int $userId = null, ?string $reason = null): bool;
    public function disablePlugin(string $name, ?int $userId = null, ?string $reason = null, bool $force = false): bool;
    public function canEnable(string $name): array;
    public function canDisable(string $name): array;
    public function getDependencies(string $name): array;
    public function getDependents(string $name): array;
    public function resolveDependencyTree(string $name): array;
    public function updateSettings(string $name, array $settings): bool;
    public function syncPluginsFromFilesystem(string $pluginsPath): array;
    public function clearCaches(): bool;
    public function getAuditLog(?string $pluginName = null, int $limit = 50): array;
    public function isEnabled(string $name): bool;
    public function validatePluginConfig(string $pluginPath): array;
}
