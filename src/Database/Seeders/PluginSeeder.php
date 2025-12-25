<?php

declare(strict_types=1);

namespace Atom\Framework\Database\Seeders;

use Atom\Framework\Repositories\PluginRepository;

class PluginSeeder
{
    protected PluginRepository $repository;

    protected const DEPENDENCIES = [
        'sfPluginAdminPlugin' => ['sfPropelPlugin'],
        'qtAccessionPlugin' => ['sfPropelPlugin'],
        'arElasticSearchPlugin' => ['sfPropelPlugin'],
        'sfHistoryPlugin' => ['sfPropelPlugin'],
        'sfTranslatePlugin' => ['sfPropelPlugin'],
        'arSecurityClearancePlugin' => ['qbAclPlugin', 'arElasticSearchPlugin'],
        'arAccessRequestPlugin' => ['sfPropelPlugin', 'qbAclPlugin'],
        'arAuditTrailPlugin' => ['sfPropelPlugin'],
        'arIiifCollectionPlugin' => ['sfPropelPlugin'],
        'arOAISPlugin' => ['sfPropelPlugin'],
        'arSpectrumPlugin' => ['sfPropelPlugin'],
        'arConditionPlugin' => ['sfPropelPlugin'],
        'arGrapPlugin' => ['sfPropelPlugin'],
        'arResearchPlugin' => ['sfPropelPlugin', 'qbAclPlugin'],
        'arGalleryPlugin' => ['sfPropelPlugin'],
        'arDAMPlugin' => ['sfPropelPlugin'],
        'arLibraryPlugin' => ['sfPropelPlugin'],
        'arDisplayPlugin' => ['sfPropelPlugin'],
        'ar3DModelPlugin' => ['sfPropelPlugin'],
        'arRicExplorerPlugin' => ['sfPropelPlugin'],
        'sfMuseumPlugin' => ['sfPropelPlugin'],
    ];

    protected const CATEGORIES = [
        'qbAclPlugin' => 'core',
        'sfPropelPlugin' => 'core',
        'arElasticSearchPlugin' => 'core',
        'sfPluginAdminPlugin' => 'core',
        'sfDrupalPlugin' => 'core',
        'sfHistoryPlugin' => 'core',
        'sfThumbnailPlugin' => 'core',
        'sfTranslatePlugin' => 'core',
        'sfWebBrowserPlugin' => 'core',
        'qtAccessionPlugin' => 'workflow',
        'arSecurityClearancePlugin' => 'security',
        'arAccessRequestPlugin' => 'workflow',
        'arAuditTrailPlugin' => 'security',
        'arIiifCollectionPlugin' => 'integration',
        'arOAISPlugin' => 'metadata',
        'arSpectrumPlugin' => 'metadata',
        'arConditionPlugin' => 'workflow',
        'arGrapPlugin' => 'metadata',
        'arResearchPlugin' => 'workflow',
        'arGalleryPlugin' => 'metadata',
        'arDAMPlugin' => 'integration',
        'arLibraryPlugin' => 'metadata',
        'arDisplayPlugin' => 'integration',
        'ar3DModelPlugin' => 'integration',
        'arRicExplorerPlugin' => 'integration',
        'sfMuseumPlugin' => 'metadata',
        'arOidcPlugin' => 'security',
    ];

    protected const CORE_PLUGINS = [
        'qbAclPlugin',
        'sfPropelPlugin',
        'arElasticSearchPlugin',
        'sfPluginAdminPlugin',
    ];

    protected const DESCRIPTIONS = [
        'qbAclPlugin' => 'Access Control List management for AtoM permissions',
        'sfPropelPlugin' => 'Propel ORM integration for database operations',
        'arElasticSearchPlugin' => 'Elasticsearch integration for search functionality',
        'sfPluginAdminPlugin' => 'Plugin administration interface',
        'sfDrupalPlugin' => 'Drupal integration for user management',
        'sfHistoryPlugin' => 'Browser history and session management',
        'sfThumbnailPlugin' => 'Image thumbnail generation',
        'sfTranslatePlugin' => 'Internationalization and translation support',
        'sfWebBrowserPlugin' => 'Web browser utility functions',
        'qtAccessionPlugin' => 'Accession records management (Qubit)',
        'arSecurityClearancePlugin' => 'Five-level security classification system',
        'arAccessRequestPlugin' => 'Access request workflow management',
        'arAuditTrailPlugin' => 'Comprehensive audit logging system',
        'arIiifCollectionPlugin' => 'IIIF image collection management',
        'arOAISPlugin' => 'OAIS compliance for digital preservation',
        'arSpectrumPlugin' => 'SPECTRUM collections management standard',
        'arConditionPlugin' => 'Condition reporting and assessment',
        'arGrapPlugin' => 'GRAP 103 heritage assets compliance',
        'arResearchPlugin' => 'Research request management',
        'arGalleryPlugin' => 'Gallery and exhibition management',
        'arDAMPlugin' => 'Digital Asset Management integration',
        'arLibraryPlugin' => 'Library management and cataloguing',
        'arDisplayPlugin' => 'Display and presentation layouts',
        'ar3DModelPlugin' => '3D model viewing and management',
        'arRicExplorerPlugin' => 'Records in Contexts explorer',
        'sfMuseumPlugin' => 'CCO museum cataloguing standard',
        'arOidcPlugin' => 'OpenID Connect authentication',
    ];

    public function __construct()
    {
        $this->repository = new PluginRepository();
    }

    public function run(array $currentPlugins, string $pluginsPath): array
    {
        $results = [
            'created' => 0,
            'skipped' => 0,
            'dependencies_added' => 0,
            'errors' => [],
        ];

        $directories = glob($pluginsPath . '/*Plugin', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $pluginName = basename($dir);

            try {
                if ($this->repository->exists($pluginName)) {
                    ++$results['skipped'];
                    continue;
                }

                $isEnabled = in_array($pluginName, $currentPlugins, true);
                $isCore = in_array($pluginName, self::CORE_PLUGINS, true);

                $pluginData = [
                    'name' => $pluginName,
                    'class_name' => $pluginName,
                    'description' => self::DESCRIPTIONS[$pluginName] ?? null,
                    'category' => self::CATEGORIES[$pluginName] ?? 'general',
                    'is_enabled' => $isEnabled,
                    'is_core' => $isCore,
                    'is_locked' => $isCore,
                    'load_order' => $this->calculateLoadOrder($pluginName),
                    'plugin_path' => $dir,
                ];

                if ($isEnabled) {
                    $pluginData['enabled_at'] = date('Y-m-d H:i:s');
                }

                $pluginId = $this->repository->create($pluginData);

                if (isset(self::DEPENDENCIES[$pluginName])) {
                    foreach (self::DEPENDENCIES[$pluginName] as $requiredPlugin) {
                        $this->repository->addDependency($pluginId, [
                            'requires_plugin' => $requiredPlugin,
                            'is_optional' => false,
                        ]);
                        ++$results['dependencies_added'];
                    }
                }

                ++$results['created'];
            } catch (\Exception $e) {
                $results['errors'][] = "{$pluginName}: " . $e->getMessage();
            }
        }

        return $results;
    }

    protected function calculateLoadOrder(string $pluginName): int
    {
        if (in_array($pluginName, self::CORE_PLUGINS, true)) {
            return match ($pluginName) {
                'sfPropelPlugin' => 10,
                'qbAclPlugin' => 20,
                'arElasticSearchPlugin' => 30,
                'sfPluginAdminPlugin' => 40,
                default => 50,
            };
        }

        $category = self::CATEGORIES[$pluginName] ?? 'general';

        return match ($category) {
            'core' => 60,
            'security' => 70,
            'metadata' => 80,
            'workflow' => 90,
            default => 100,
        };
    }

    public function verifyDependencies(): array
    {
        $issues = [];
        $plugins = $this->repository->findAll(['is_enabled' => true]);

        foreach ($plugins as $plugin) {
            $plugin = (array) $plugin;
            $deps = $this->repository->getDependencies($plugin['id']);

            foreach ($deps as $dep) {
                $dep = (array) $dep;
                if ($dep['is_optional']) {
                    continue;
                }
                if (!$this->repository->isEnabled($dep['requires_plugin'])) {
                    $issues[] = [
                        'plugin' => $plugin['name'],
                        'missing_dependency' => $dep['requires_plugin'],
                    ];
                }
            }
        }

        return $issues;
    }
}
