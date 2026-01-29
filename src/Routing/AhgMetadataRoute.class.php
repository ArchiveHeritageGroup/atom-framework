<?php

/**
 * Extended MetadataRoute that adds GLAM sector plugins.
 * Falls back to ISAD when ahgThemeB5Plugin is not enabled.
 *
 * Uses MetadataTemplateRegistry for dynamic template lookup.
 * GLAM sector plugins should register via MetadataTemplateProviderInterface.
 */
class AhgMetadataRoute extends QubitMetadataRoute
{
    /**
     * @deprecated Use MetadataTemplateRegistry for dynamic lookup.
     *             Core templates are in parent::$METADATA_PLUGINS.
     *             GLAM templates register via MetadataTemplateRegistry.
     */
    public static $METADATA_PLUGINS = [
        // Core AtoM templates (inherited from parent)
        'isaar' => 'sfIsaarPlugin',
        'eac' => 'sfEacPlugin',
        'ead' => 'sfEadPlugin',
        'isad' => 'sfIsadPlugin',
        'dc' => 'sfDcPlugin',
        'skos' => 'sfSkosPlugin',
        'rad' => 'sfRadPlugin',
        'mods' => 'sfModsPlugin',
        'dacs' => 'arDacsPlugin',
        'isdf' => 'sfIsdfPlugin',
        // GLAM sector templates
        'museum' => 'museum',
        'dam' => 'dam',
        'gallery' => 'gallery',
        'library' => 'library',
    ];

    protected static $IO_ALLOWED_VALUES = [
        'isad', 'dc', 'mods', 'rad', 'ead', 'dacs',
        'museum', 'dam', 'gallery', 'library',
    ];

    // GLAM sector codes that require ahgThemeB5Plugin
    protected static $GLAM_CODES = ['museum', 'dam', 'gallery', 'library'];

    /**
     * Get allowed IO template values dynamically.
     *
     * Combines core values with registered GLAM templates.
     */
    protected static function getAllowedIOValues(): array
    {
        $values = self::$IO_ALLOWED_VALUES;

        // Add GLAM codes if ahgThemeB5Plugin is enabled
        if (self::isAhgThemeEnabled()) {
            // Check MetadataTemplateRegistry for GLAM templates
            if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
                $registeredCodes = \AtomExtensions\Services\MetadataTemplateRegistry::getTemplateCodes();
                foreach ($registeredCodes as $code) {
                    if (!in_array($code, $values)) {
                        $values[] = $code;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Check if a code is a GLAM sector code.
     *
     * GLAM codes are registered by sector plugins or in $GLAM_CODES.
     */
    protected static function isGlamCode(string $code): bool
    {
        // Check static list first
        if (in_array($code, self::$GLAM_CODES)) {
            return true;
        }

        // Check if it's a registered template that's not a core template
        if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
            $plugin = \AtomExtensions\Services\MetadataTemplateRegistry::getPluginForTemplate($code);
            // If plugin name starts with 'ahg', it's a GLAM plugin
            if ($plugin && strpos($plugin, 'ahg') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if ahgThemeB5Plugin is enabled
     * Uses setting_i18n (plugins array) as source of truth - same as theme selection
     */
    protected static function isAhgThemeEnabled()
    {
        try {
            // Check setting_i18n which reflects Admin > Themes selection
            $sql = "SELECT value FROM setting_i18n WHERE id = (SELECT id FROM setting WHERE name = 'plugins' LIMIT 1)";
            $result = QubitPdo::fetchColumn($sql);
            if ($result) {
                $plugins = @unserialize($result);
                if (is_array($plugins)) {
                    return in_array('ahgThemeB5Plugin', $plugins);
                }
            }
        } catch (Exception $e) {
        }
        return false;
    }

    public function matchesUrl($url, $context = [])
    {
        if (false === $parameters = sfRoute::matchesUrl($url, $context)) {
            return false;
        }

        if (in_array($parameters['action'], ['add', 'copy'])) {
            $parameters['action'] = 'edit';
        }

        if (isset($parameters['slug'])) {
            $criteria = new Criteria();
            $criteria->add(QubitSlug::SLUG, $parameters['slug']);
            $criteria->addJoin(QubitSlug::OBJECT_ID, QubitObject::ID);

            if (null === $this->resource = QubitObject::get($criteria)->__get(0)) {
                return false;
            }

            switch (true) {
                case $this->resource instanceof QubitRepository:
                    $parameters['module'] = 'sfIsdiahPlugin';
                    break;
                case $this->resource instanceof QubitRelation:
                    $parameters['module'] = 'relation';
                    break;
                case $this->resource instanceof QubitDonor:
                    $parameters['module'] = 'donor';
                    break;
                case $this->resource instanceof QubitRights:
                    $parameters['module'] = 'right';
                    break;
                case $this->resource instanceof QubitRightsHolder:
                    $parameters['module'] = 'rightsholder';
                    break;
                case $this->resource instanceof QubitUser:
                    $parameters['module'] = 'user';
                    break;
                case $this->resource instanceof QubitActor:
                    $parameters['module'] = $this->getActionParameter(['isaar', 'eac'], $this->getDefaultTemplate('actor'), $parameters);
                    break;
                case $this->resource instanceof QubitFunctionObject:
                    $parameters['module'] = 'sfIsdfPlugin';
                    break;
                case $this->resource instanceof QubitDigitalObject:
                    $parameters['module'] = 'digitalobject';
                    break;
                case $this->resource instanceof QubitInformationObject:
                    $default = $this->getDefaultTemplate('informationobject');
                    $sql = 'SELECT code FROM information_object JOIN term ON information_object.display_standard_id = term.id WHERE information_object.id = ? AND taxonomy_id = ?';
                    if (false !== $defaultSetting = QubitPdo::fetchColumn($sql, [$this->resource->id, QubitTaxonomy::INFORMATION_OBJECT_TEMPLATE_ID])) {
                        $default = $defaultSetting;
                    }

                    // If GLAM code but ahgThemeB5Plugin not enabled, fall back to ISAD
                    if (self::isGlamCode($default) && !self::isAhgThemeEnabled()) {
                        $default = 'isad';
                    }

                    $parameters['module'] = $this->getActionParameter(self::getAllowedIOValues(), $default, $parameters);
                    break;
                case $this->resource instanceof QubitAccession:
                    $parameters['module'] = 'accession';
                    break;
                case $this->resource instanceof QubitDeaccession:
                    $parameters['module'] = 'deaccession';
                    break;
                case $this->resource instanceof QubitTerm:
                    $parameters['module'] = isset($parameters['template']) && 'skos' == $parameters['template'] ? 'sfSkosPlugin' : 'term';
                    break;
                case $this->resource instanceof QubitTaxonomy:
                    $parameters['module'] = 'taxonomy';
                    break;
                case $this->resource instanceof QubitStaticPage:
                    $parameters['module'] = 'staticpage';
                    break;
                case $this->resource instanceof QubitPhysicalObject:
                    $parameters['module'] = 'physicalobject';
                    break;
                case $this->resource instanceof QubitEvent:
                    $parameters['module'] = 'event';
                    break;
                default:
                    return false;
            }
        } elseif (isset($parameters['module'])) {
            switch ($parameters['module']) {
                case 'informationobject':
                    if (false !== $code = $this->getDefaultTemplate($parameters['module'])) {
                        // If GLAM code but ahgThemeB5Plugin not enabled, fall back to ISAD
                        if (self::isGlamCode($code) && !self::isAhgThemeEnabled()) {
                            $code = 'isad';
                        }
                        $parameters['module'] = self::getPluginForTemplate($code);
                    }
                    break;
                case 'actor':
                case 'repository':
                case 'function':
                    $module = $parameters['module'];
                    $parameters['module'] = parent::$DEFAULT_MODULES[$module];
                    break;
            }
        }

        return $parameters;
    }

    public function matchesParameters($params, $context = [])
    {
        $params = $this->parseParameters($params);

        // Must have slug
        if (!isset($params['slug'])) {
            if (isset($params['module']) && !isset(parent::$DEFAULT_MODULES[$params['module']])) {
                return false;
            }
        }

        return parent::matchesParameters($params, $context);
    }

    public function generate($params, $context = [], $absolute = false)
    {
        $params = $this->parseParameters($params);
        return parent::generate($params, $context, $absolute);
    }

    protected function parseParameters($params)
    {
        if (!is_array($params)) {
            $params = [$params];
        }

        if (isset($params[0]) && is_object($params[0])) {
            try {
                $params['slug'] = $params[0]->slug;
            } catch (Exception $e) {
            }
            unset($params[0]);
        }

        if (isset($params['slug'])) {
            if (isset($params['module'])) {
                $key = self::getTemplateForPlugin($params['module']);
                if ($key !== null) {
                    $params['template'] = $key;
                }
                unset($params['module']);
            }
        }

        return $params;
    }

    protected function getActionParameter($allowedValues, $default, $parameters)
    {
        $code = $default;
        if (isset($parameters['template'])) {
            $code = $parameters['template'];
        }

        // If GLAM code but ahgThemeB5Plugin not enabled, fall back to ISAD
        if (self::isGlamCode($code) && !self::isAhgThemeEnabled()) {
            $code = 'isad';
        }

        if (!in_array($code, $allowedValues)) {
            throw new sfConfigurationException(sprintf('The metadata code "%s" is not valid.', $code));
        }

        return self::getModuleForTemplate($code);
    }

    /**
     * Get the module name for a template code.
     *
     * For GLAM/DAM templates, the module name differs from the plugin name.
     * E.g., ahgMuseumPlugin has module 'museum'.
     */
    protected static function getModuleForTemplate(string $code): ?string
    {
        // Check registry first (has separate module info)
        if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
            $module = \AtomExtensions\Services\MetadataTemplateRegistry::getModuleForTemplate($code);
            if ($module !== null) {
                return $module;
            }
        }

        // Fallback to legacy array (plugin name = module name for core plugins)
        return self::$METADATA_PLUGINS[$code] ?? null;
    }

    /**
     * Get the plugin name for a template code.
     *
     * Checks MetadataTemplateRegistry first (for dynamically registered plugins),
     * then falls back to legacy $METADATA_PLUGINS array.
     */
    protected static function getPluginForTemplate(string $code): ?string
    {
        // Check registry first (dynamic registration)
        if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
            $plugin = \AtomExtensions\Services\MetadataTemplateRegistry::getPluginForTemplate($code);
            if ($plugin !== null) {
                return $plugin;
            }
        }

        // Fallback to legacy array
        return self::$METADATA_PLUGINS[$code] ?? null;
    }

    /**
     * Get the template code for a plugin name (reverse lookup).
     *
     * Checks MetadataTemplateRegistry first, then falls back to legacy array.
     */
    protected static function getTemplateForPlugin(string $plugin): ?string
    {
        // Check registry first
        if (class_exists('\\AtomExtensions\\Services\\MetadataTemplateRegistry')) {
            $map = \AtomExtensions\Services\MetadataTemplateRegistry::getTemplatePluginMap();
            $key = array_search($plugin, $map, true);
            if ($key !== false) {
                return $key;
            }
        }

        // Fallback to legacy array
        $key = array_search($plugin, self::$METADATA_PLUGINS, true);

        return $key !== false ? $key : null;
    }
}
