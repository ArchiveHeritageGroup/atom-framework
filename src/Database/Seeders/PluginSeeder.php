<?php

declare(strict_types=1);

namespace AtomFramework\Database\Seeders;

use AtomFramework\Repositories\PluginRepository;

class PluginSeeder
{
    protected PluginRepository $repository;

    protected const DEPENDENCIES = [
        'sfPluginAdminPlugin' => ['sfPropelPlugin'],
        'qtAccessionPlugin' => ['sfPropelPlugin'],
        'arElasticSearchPlugin' => ['sfPropelPlugin'],
        'sfHistoryPlugin' => ['sfPropelPlugin'],
        'sfTranslatePlugin' => ['sfPropelPlugin'],
        'ahgSecurityClearancePlugin' => ['qbAclPlugin', 'arElasticSearchPlugin'],
        'ahgAccessRequestPlugin' => ['sfPropelPlugin', 'qbAclPlugin'],
        'ahgAuditTrailPlugin' => ['sfPropelPlugin'],
        'ahgIiifCollectionPlugin' => ['sfPropelPlugin'],
        'arOAISPlugin' => ['sfPropelPlugin'],
        'ahgSpectrumPlugin' => ['sfPropelPlugin'],
        'ahgConditionPlugin' => ['sfPropelPlugin'],
        'ahgGrapPlugin' => ['sfPropelPlugin'],
        'ahgResearchPlugin' => ['sfPropelPlugin', 'qbAclPlugin'],
        'ahgGalleryPlugin' => ['sfPropelPlugin'],
        'ahgDAMPlugin' => ['sfPropelPlugin'],
        'ahgLibraryPlugin' => ['sfPropelPlugin'],
        'ahgDisplayPlugin' => ['sfPropelPlugin'],
        'ahg3DModelPlugin' => ['sfPropelPlugin'],
        'ahgRicExplorerPlugin' => ['sfPropelPlugin'],
        'ahgMuseumPlugin' => ['sfPropelPlugin'],
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
        'ahgSecurityClearancePlugin' => 'security',
        'ahgAccessRequestPlugin' => 'workflow',
        'ahgAuditTrailPlugin' => 'security',
        'ahgIiifCollectionPlugin' => 'integration',
        'arOAISPlugin' => 'metadata',
        'ahgSpectrumPlugin' => 'metadata',
        'ahgConditionPlugin' => 'workflow',
        'ahgGrapPlugin' => 'metadata',
        'ahgResearchPlugin' => 'workflow',
        'ahgGalleryPlugin' => 'metadata',
        'ahgDAMPlugin' => 'integration',
        'ahgLibraryPlugin' => 'metadata',
        'ahgDisplayPlugin' => 'integration',
        'ahg3DModelPlugin' => 'integration',
        'ahgRicExplorerPlugin' => 'integration',
        'ahgMuseumPlugin' => 'metadata',
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
        'ahgSecurityClearancePlugin' => 'Five-level security classification system',
        'ahgAccessRequestPlugin' => 'Access request workflow management',
        'ahgAuditTrailPlugin' => 'Comprehensive audit logging system',
        'ahgIiifCollectionPlugin' => 'IIIF image collection management',
        'arOAISPlugin' => 'OAIS compliance for digital preservation',
        'ahgSpectrumPlugin' => 'SPECTRUM collections management standard',
        'ahgConditionPlugin' => 'Condition reporting and assessment',
        'ahgGrapPlugin' => 'GRAP 103 heritage assets compliance',
        'ahgResearchPlugin' => 'Research request management',
        'ahgGalleryPlugin' => 'Gallery and exhibition management',
        'ahgDAMPlugin' => 'Digital Asset Management integration',
        'ahgLibraryPlugin' => 'Library management and cataloguing',
        'ahgDisplayPlugin' => 'Display and presentation layouts',
        'ahg3DModelPlugin' => '3D model viewing and management',
        'ahgRicExplorerPlugin' => 'Records in Contexts explorer',
        'ahgMuseumPlugin' => 'CCO museum cataloguing standard',
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
