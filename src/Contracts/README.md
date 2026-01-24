# Plugin Registration Contracts

This directory contains interfaces that plugins can implement to register their capabilities dynamically with the framework.

## Available Interfaces

### SectorProviderInterface

Plugins that provide GLAM sector support (museum, library, gallery, DAM) should implement this interface.

```php
// In your plugin's Configuration class:
use AtomExtensions\Contracts\SectorProviderInterface;
use AtomExtensions\Services\SectorRegistry;

class ahgMuseumPluginConfiguration extends sfPluginConfiguration
    implements SectorProviderInterface
{
    public function initialize()
    {
        parent::initialize();

        // Register with the sector registry
        SectorRegistry::register($this);
    }

    public function getSectorCode(): string
    {
        return 'museum';
    }

    public function getSectorLabel(): string
    {
        return 'Museum';
    }

    public function getDefaultLevels(): array
    {
        return ['Artifact', 'Object', 'Specimen', 'Artwork'];
    }
}
```

### MetadataTemplateProviderInterface

Plugins that provide metadata templates (display standards) should implement this interface.

```php
// In your plugin's Configuration class:
use AtomExtensions\Contracts\MetadataTemplateProviderInterface;
use AtomExtensions\Services\MetadataTemplateRegistry;

class ahgMuseumPluginConfiguration extends sfPluginConfiguration
    implements MetadataTemplateProviderInterface
{
    public function initialize()
    {
        parent::initialize();

        // Register with the metadata template registry
        MetadataTemplateRegistry::register($this);
    }

    public function getTemplateCode(): string
    {
        return 'museum';
    }

    public function getPluginName(): string
    {
        return 'ahgMuseumPlugin';
    }

    public function getModuleName(): string
    {
        return 'ahgMuseumPlugin';
    }
}
```

## Combined Implementation

A plugin can implement both interfaces:

```php
class ahgMuseumPluginConfiguration extends sfPluginConfiguration
    implements SectorProviderInterface, MetadataTemplateProviderInterface
{
    public function initialize()
    {
        parent::initialize();

        // Register with both registries
        SectorRegistry::register($this);
        MetadataTemplateRegistry::register($this);
    }

    // SectorProviderInterface methods
    public function getSectorCode(): string { return 'museum'; }
    public function getSectorLabel(): string { return 'Museum'; }
    public function getDefaultLevels(): array { return ['Artifact', 'Object']; }

    // MetadataTemplateProviderInterface methods
    public function getTemplateCode(): string { return 'museum'; }
    public function getPluginName(): string { return 'ahgMuseumPlugin'; }
    public function getModuleName(): string { return 'ahgMuseumPlugin'; }
}
```

## Benefits

1. **No Framework Modification**: Plugins register themselves without modifying framework code
2. **Dynamic Discovery**: The framework discovers available sectors and templates at runtime
3. **Backward Compatibility**: Legacy hardcoded arrays are still checked as fallback
4. **Clean Separation**: Plugin-specific code stays in plugins, not in the framework
