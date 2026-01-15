<?php

/**
 * Extended MetadataRoute that adds GLAM sector plugins.
 * Falls back to ISAD when ahgThemeB5Plugin is not enabled.
 */
class AhgMetadataRoute extends QubitMetadataRoute
{
    public static $METADATA_PLUGINS = [
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
        'museum' => 'ahgMuseumPlugin',
        'dam' => 'ahgDAMPlugin',
        'gallery' => 'ahgGalleryPlugin',
        'library' => 'ahgLibraryPlugin',
    ];

    protected static $IO_ALLOWED_VALUES = [
        'isad', 'dc', 'mods', 'rad', 'ead', 'dacs',
        'museum', 'dam', 'gallery', 'library'
    ];

    // GLAM sector codes that require ahgThemeB5Plugin
    protected static $GLAM_CODES = ['museum', 'dam', 'gallery', 'library'];

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
                    if (in_array($default, self::$GLAM_CODES) && !self::isAhgThemeEnabled()) {
                        $default = 'isad';
                    }
                    
                    $parameters['module'] = $this->getActionParameter(self::$IO_ALLOWED_VALUES, $default, $parameters);
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
                        if (in_array($code, self::$GLAM_CODES) && !self::isAhgThemeEnabled()) {
                            $code = 'isad';
                        }
                        $parameters['module'] = static::$METADATA_PLUGINS[$code];
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
                if (false !== $key = array_search($params['module'], static::$METADATA_PLUGINS)) {
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
        if (in_array($code, self::$GLAM_CODES) && !self::isAhgThemeEnabled()) {
            $code = 'isad';
        }
        
        if (!in_array($code, $allowedValues)) {
            throw new sfConfigurationException(sprintf('The metadata code "%s" is not valid.', $code));
        }
        return static::$METADATA_PLUGINS[$code];
    }
}
