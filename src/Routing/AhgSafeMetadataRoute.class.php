<?php

/**
 * Metadata route that degrades instead of returning 500 when a record's
 * display standard has no renderer on this instance.
 *
 * A description carries its descriptive standard in
 * information_object.display_standard_id, and the metadata route turns that
 * code into the symfony module that renders it. Base AtoM resolves the code
 * against a hardcoded whitelist and throws sfConfigurationException - an
 * uncaught 500 on the record's own permalink - the moment the code is not on
 * that list. Two ordinary situations reach it:
 *
 *   1. The instance runs stock base routing (no framework patch applied), so
 *      the GLAM codes museum / dam / gallery / library are not whitelisted,
 *      even where the sector plugin is installed and could render them.
 *      Signature: 'The metadata code "dam" is not valid.'
 *
 *   2. The code is whitelisted but the plugin that owns the module is not
 *      enabled here, so symfony resolves the template directory to "".
 *      Signature: 'The template "indexSuccess.php" does not exist or is
 *      unreadable in "".'
 *
 * Both are reachable from the UI: the display-standard picker on the edit form
 * lists every term in the template taxonomy regardless of what is installed, so
 * an editor can pick a standard this instance cannot render and the record is
 * then unreachable. Measured on archaeology 2026-08-24 - /<slug>;dam, ;museum
 * and ;library all 500, reported from the Wits instance by Stefan du Toit.
 *
 * A record that cannot be rendered in its own standard should still be
 * readable in another, so this resolves the first standard that this instance
 * actually has a module for: the one requested, then the instance default,
 * then the allowed codes in declaration order. Only if none resolves does it
 * hand back to the parent, so behaviour is unchanged where nothing is missing.
 */
class AhgSafeMetadataRoute extends QubitMetadataRoute
{
    /**
     * @param array $allowedValues
     * @param mixed $default
     * @param array $parameters
     *
     * @return string the module name to route to
     */
    protected function getActionParameter($allowedValues, $default, $parameters)
    {
        $requested = isset($parameters['template']) ? $parameters['template'] : $default;

        $candidates = [];

        foreach (array_merge([$requested, $default], (array) $allowedValues) as $code) {
            if (is_string($code) && '' !== $code && !in_array($code, $candidates, true)) {
                $candidates[] = $code;
            }
        }

        foreach ($candidates as $code) {
            $module = self::moduleForTemplateCode($code);

            if (null !== $module && self::moduleIsInstalled($module)) {
                return $module;
            }
        }

        // Nothing on this instance can render any of them. Hand back to the
        // parent rather than inventing a module: its exception is at least
        // logged with the offending code in the message.
        return parent::getActionParameter($allowedValues, $default, $parameters);
    }

    /**
     * Can this instance render a record in the given descriptive standard?
     *
     * Public so the edit form can ask the same question the route will ask
     * later, and leave a standard off the picker rather than let an editor
     * choose one that makes the record unreachable at its own permalink.
     *
     * @param string $code
     *
     * @return bool
     */
    public static function canRender($code)
    {
        if (!is_string($code) || '' === $code) {
            return false;
        }

        $module = self::moduleForTemplateCode($code);

        return null !== $module && self::moduleIsInstalled($module);
    }

    /**
     * Map a template code to the module that renders it.
     *
     * Note static:: rather than self:: on the legacy map. The parent reads its
     * own $METADATA_PLUGINS with self::, which binds to the class the method is
     * defined in, so every subclass map - including the GLAM codes in
     * AhgMetadataRoute - is silently ignored there.
     *
     * @param string $code
     *
     * @return null|string
     */
    private static function moduleForTemplateCode($code)
    {
        if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
            $module = \AtomExtensions\Services\MetadataTemplateRegistry::getModuleForTemplate($code);

            if (null !== $module) {
                return $module;
            }
        }

        return isset(static::$METADATA_PLUGINS[$code]) ? static::$METADATA_PLUGINS[$code] : null;
    }

    /**
     * Is the module present in an enabled plugin, or in the application itself?
     *
     * getPluginPaths() lists enabled plugins only, which is the distinction
     * that matters: ahgDAMPlugin's modules/dam is on disk on every install that
     * carries the plugin tree, enabled or not.
     *
     * Fails open. If the configuration cannot be reached the module is treated
     * as present, which leaves the request exactly where it is today rather
     * than rerouting on a guess.
     *
     * @param string $module
     *
     * @return bool
     */
    private static function moduleIsInstalled($module)
    {
        static $known = [];

        if (isset($known[$module])) {
            return $known[$module];
        }

        $configuration = class_exists('sfProjectConfiguration', false)
            ? sfProjectConfiguration::getActive()
            : null;

        if (!$configuration) {
            return $known[$module] = true;
        }

        $directories = [];

        if ($appModuleDir = sfConfig::get('sf_app_module_dir')) {
            $directories[] = $appModuleDir.'/'.$module;
        }

        try {
            foreach ($configuration->getPluginPaths() as $pluginPath) {
                $directories[] = $pluginPath.'/modules/'.$module;
            }
        } catch (Exception $e) {
            return $known[$module] = true;
        }

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                return $known[$module] = true;
            }
        }

        return $known[$module] = false;
    }
}
