<?php

declare(strict_types=1);

namespace AtomFramework;

use AtomFramework\Contracts\PiiRedactionProviderInterface;
use AtomFramework\Contracts\Model3DProviderInterface;
use AtomFramework\Contracts\IiifProviderInterface;

/**
 * Service provider registry for plugin capabilities.
 *
 * Plugins register their implementations of framework contracts here.
 * This enables loose coupling - the framework can use plugin features
 * without hardcoded require_once statements.
 *
 * Usage in plugins:
 *   AtomFramework\Providers::register('pii_redaction', new MyRedactionService());
 *
 * Usage in framework:
 *   $provider = AtomFramework\Providers::get('pii_redaction');
 *   if ($provider && $provider->hasRedaction($id)) { ... }
 */
class Providers
{
    /**
     * Registered service providers.
     *
     * @var array<string, object>
     */
    private static array $providers = [];

    /**
     * Provider interface mappings for type checking.
     *
     * @var array<string, string>
     */
    private static array $interfaces = [
        'pii_redaction' => PiiRedactionProviderInterface::class,
        'model_3d' => Model3DProviderInterface::class,
        'iiif' => IiifProviderInterface::class,
    ];

    /**
     * Register a service provider.
     *
     * @param string $name Provider name (e.g., 'pii_redaction', 'model_3d')
     * @param object $implementation Provider implementation
     * @throws \InvalidArgumentException If implementation doesn't match expected interface
     */
    public static function register(string $name, object $implementation): void
    {
        if (isset(self::$interfaces[$name])) {
            $interface = self::$interfaces[$name];
            if (!$implementation instanceof $interface) {
                throw new \InvalidArgumentException(
                    sprintf('Provider "%s" must implement %s', $name, $interface)
                );
            }
        }

        self::$providers[$name] = $implementation;
    }

    /**
     * Get a registered provider.
     *
     * @param string $name Provider name
     * @return object|null The provider or null if not registered
     */
    public static function get(string $name): ?object
    {
        return self::$providers[$name] ?? null;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $name Provider name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset(self::$providers[$name]);
    }

    /**
     * Get PII redaction provider.
     *
     * @return PiiRedactionProviderInterface|null
     */
    public static function piiRedaction(): ?PiiRedactionProviderInterface
    {
        $provider = self::get('pii_redaction');
        return $provider instanceof PiiRedactionProviderInterface ? $provider : null;
    }

    /**
     * Get 3D model provider.
     *
     * @return Model3DProviderInterface|null
     */
    public static function model3d(): ?Model3DProviderInterface
    {
        $provider = self::get('model_3d');
        return $provider instanceof Model3DProviderInterface ? $provider : null;
    }

    /**
     * Get IIIF provider.
     *
     * @return IiifProviderInterface|null
     */
    public static function iiif(): ?IiifProviderInterface
    {
        $provider = self::get('iiif');
        return $provider instanceof IiifProviderInterface ? $provider : null;
    }

    /**
     * Get all registered provider names.
     *
     * @return array<string>
     */
    public static function registered(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Clear all registered providers (for testing).
     */
    public static function clear(): void
    {
        self::$providers = [];
    }

    /**
     * Register a custom interface mapping.
     *
     * Allows plugins to define their own provider types.
     *
     * @param string $name Provider name
     * @param string $interface Fully qualified interface name
     */
    public static function defineInterface(string $name, string $interface): void
    {
        self::$interfaces[$name] = $interface;
    }
}
