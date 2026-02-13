<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;

/**
 * Standalone component renderer for when Symfony is not loaded.
 *
 * Replaces include_component() / get_component() in standalone mode.
 * Locates the component class, executes it, renders the template,
 * and returns HTML.
 *
 * Usage:
 *   $html = ComponentRenderer::render('display', 'sidebar', ['resource' => $r]);
 *   $html = ComponentRenderer::renderPartial('module', '_partial', ['items' => $items]);
 */
class ComponentRenderer
{
    /**
     * Render a component: execute its class and render its template.
     *
     * @param string $module    Module name
     * @param string $component Component name (e.g., 'sidebar')
     * @param array  $vars      Variables to pass to the component
     */
    public static function render(string $module, string $component, array $vars = []): string
    {
        $rootDir = self::getRootDir();
        $pluginsDir = $rootDir . '/plugins';

        // Find component class file
        $classFile = self::findComponentFile($pluginsDir, $module);
        $templateDir = null;

        if (null !== $classFile) {
            $templateDir = dirname($classFile, 2) . '/templates';

            // Require and execute the component class
            require_once $classFile;
            $className = self::resolveComponentClassName($classFile, $module);

            if (null !== $className && class_exists($className, false)) {
                $instance = new $className();

                // Set initial vars on the instance
                foreach ($vars as $key => $value) {
                    $instance->$key = $value;
                }

                // Execute the component method
                $method = 'execute' . ucfirst($component);
                if (method_exists($instance, $method)) {
                    $request = null;
                    if (SfContextAdapter::hasInstance()) {
                        $request = SfContextAdapter::getInstance()->getRequest();
                    }

                    $instance->$method($request);

                    // Merge component's template vars back
                    if (method_exists($instance, 'getTemplateVars')) {
                        $vars = array_merge($vars, $instance->getTemplateVars());
                    }
                }
            }
        }

        // Render the template
        return self::renderComponentTemplate($module, $component, $vars, $templateDir);
    }

    /**
     * Render a partial template (no component class execution).
     *
     * @param string $module  Module name
     * @param string $partial Partial name (with leading underscore, e.g., '_item')
     * @param array  $vars    Variables to pass
     */
    public static function renderPartial(string $module, string $partial, array $vars = []): string
    {
        // In standalone mode, intercept theme layout partials and render
        // via Heratio's Blade partials instead of Symfony's PHP partials.
        if (!class_exists('sfActions', false)) {
            $standaloneResult = self::renderStandalonePartial($partial, $vars);
            if (null !== $standaloneResult) {
                return $standaloneResult;
            }
        }

        $rootDir = self::getRootDir();
        $pluginsDir = $rootDir . '/plugins';

        // Find template directories for this module
        $dirs = glob($pluginsDir . '/*/modules/' . $module . '/templates');

        foreach ($dirs as $dir) {
            // Try Blade first
            $bladeFile = $dir . '/' . $partial . '.blade.php';
            if (file_exists($bladeFile)) {
                $renderer = BladeRenderer::getInstance();
                $renderer->addPath($dir);

                return $renderer->render($partial, $vars);
            }

            // Try PHP
            $phpFile = $dir . '/' . $partial . '.php';
            if (file_exists($phpFile)) {
                return self::renderPhpFile($phpFile, $vars);
            }
        }

        return '';
    }

    /**
     * Render known layout partials using Heratio's Blade partials in standalone mode.
     *
     * Returns null if the partial is not a known layout partial (caller should
     * proceed with normal module-based lookup).
     */
    private static function renderStandalonePartial(string $partial, array $vars): ?string
    {
        // Normalize: remove leading underscore
        $name = ltrim($partial, '_');

        $renderer = BladeRenderer::getInstance();

        // Map Symfony layout partials to Heratio Blade partials
        switch ($name) {
            case 'layout_start':
                $culture = \AtomExtensions\Helpers\CultureHelper::getCulture();
                $siteTitle = ConfigService::get('siteTitle', 'AtoM');
                $rootDir = ConfigService::rootDir();
                $sfUser = null;
                if (\AtomFramework\Http\Compatibility\SfContextAdapter::hasInstance()) {
                    $sfUser = \AtomFramework\Http\Compatibility\SfContextAdapter::getInstance()->getUser();
                }
                $data = array_merge($vars, [
                    'culture' => $culture,
                    'siteTitle' => $siteTitle,
                    'rootDir' => $rootDir,
                    'sf_user' => $sfUser,
                ]);

                return '<!DOCTYPE html>'
                    . '<html lang="' . htmlspecialchars($culture) . '">'
                    . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                    . '<title>' . htmlspecialchars($siteTitle) . '</title>'
                    . '<link rel="shortcut icon" href="/favicon.ico">'
                    . $renderer->render('partials.head-assets', $data) . '</head>'
                    . '<body class="d-flex flex-column min-vh-100">'
                    . $renderer->render('partials.header', $data);

            case 'layout_end':
                return $renderer->render('partials.footer', $vars)
                    . '</body></html>';

            case 'alerts':
                $sfUser = $vars['sf_user'] ?? null;
                if (!$sfUser && \AtomFramework\Http\Compatibility\SfContextAdapter::hasInstance()) {
                    $vars['sf_user'] = \AtomFramework\Http\Compatibility\SfContextAdapter::getInstance()->getUser();
                }

                return $renderer->render('partials.alerts', $vars);

            default:
                return null;
        }
    }

    /**
     * Find the component class file for a module.
     */
    private static function findComponentFile(string $pluginsDir, string $module): ?string
    {
        if (!is_dir($pluginsDir)) {
            return null;
        }

        $moduleDirs = glob($pluginsDir . '/*/modules/' . $module);
        foreach ($moduleDirs as $moduleDir) {
            // Check for components.class.php (Symfony convention)
            $file = $moduleDir . '/actions/components.class.php';
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Resolve the component class name from the file.
     */
    private static function resolveComponentClassName(string $file, string $module): ?string
    {
        // Common naming patterns
        $candidates = [
            $module . 'Components',
            ucfirst($module) . 'Components',
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate, false)) {
                return $candidate;
            }
        }

        // Parse the file
        $content = file_get_contents($file);
        if (preg_match('/class\s+(\w+)\s+extends/', $content, $matches)) {
            $detected = $matches[1];
            if (class_exists($detected, false)) {
                return $detected;
            }
        }

        return null;
    }

    /**
     * Render a component template (Blade or PHP).
     */
    private static function renderComponentTemplate(
        string $module,
        string $component,
        array $vars,
        ?string $templateDir
    ): string {
        $rootDir = self::getRootDir();
        $pluginsDir = $rootDir . '/plugins';
        $templateName = '_' . $component;

        // Collect template directories
        $dirs = [];
        if ($templateDir && is_dir($templateDir)) {
            $dirs[] = $templateDir;
        }

        $moduleDirs = glob($pluginsDir . '/*/modules/' . $module . '/templates');
        foreach ($moduleDirs as $dir) {
            if (is_dir($dir) && !in_array($dir, $dirs)) {
                $dirs[] = $dir;
            }
        }

        foreach ($dirs as $dir) {
            // Try Blade
            $bladeFile = $dir . '/' . $templateName . '.blade.php';
            if (file_exists($bladeFile)) {
                $renderer = BladeRenderer::getInstance();
                $renderer->addPath($dir);

                return $renderer->render($templateName, $vars);
            }

            // Try PHP
            $phpFile = $dir . '/' . $templateName . '.php';
            if (file_exists($phpFile)) {
                return self::renderPhpFile($phpFile, $vars);
            }
        }

        return '';
    }

    /**
     * Render a PHP template file with variables extracted into scope.
     */
    private static function renderPhpFile(string $file, array $vars): string
    {
        extract($vars);

        ob_start();
        require $file;

        return ob_get_clean();
    }

    /**
     * Get the AtoM root directory.
     */
    private static function getRootDir(): string
    {
        $rootDir = ConfigService::get('sf_root_dir', '');
        if (empty($rootDir) && class_exists('\sfConfig', false)) {
            $rootDir = \sfConfig::get('sf_root_dir', '');
        }

        return $rootDir;
    }
}
