<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Metadata route handler for AtoM.
 *
 * Uses MetadataTemplateRegistry for dynamic template lookup.
 * Plugins can register their templates via MetadataTemplateProviderInterface.
 */
class QubitMetadataRoute extends QubitRoute
{
    /**
     * Legacy metadata plugins mapping.
     *
     * @deprecated Use MetadataTemplateRegistry instead. Plugins should implement
     *             MetadataTemplateProviderInterface and register via
     *             MetadataTemplateRegistry::register().
     *
     * Core AtoM templates are handled by MetadataTemplateRegistry::$coreTemplates.
     * AHG plugin templates should register dynamically in their configuration.
     */
    public static $METADATA_PLUGINS = [
        // Core AtoM templates
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
    ];
    public static $DEFAULT_MODULES = [
        'informationobject' => false,
        'term' => 'term',
        'actor' => 'sfIsaarPlugin',
        'repository' => 'sfIsdiahPlugin',
        'function' => 'sfIsdfPlugin',
    ];
    public $resource;

    /**
     * Returns an array of parameters if the $url matches this route by looking up
     * the slug in the database, otherwise returns FALSE. These parameters are
     * modified according to the type of resource in order to route the request to
     * its corresponding Symfony module.
     *
     * Case 1: e.g. uri "/peanut-12345" (QubitInformationObject)
     *     -> Add module 'sfIsadPlugin' since that is the module that corresponds
     *        with that description (it looks at the record settings and the
     *        default application template).
     * Case 2: e.g. uri "/repository/add"
     *     -> Replace module "repository" with "sfIsdiahPlugin". The relation
     *        is descrbied in self::$DEFAULT_MODULES
     *
     * @see sfRoute
     *
     * @param mixed $url
     * @param mixed $context
     */
    public function matchesUrl($url, $context = [])
    {
        // Delegate basic matching to sfRoute
        if (false === $parameters = parent::matchesUrl($url, $context)) {
            return false;
        }

        // Rewrite action add/copy to edit
        if (in_array($parameters['action'], ['add', 'copy'])) {
            $parameters['action'] = 'edit';
        }

        // At this point, it's likely that we are dealing with a permalink so let's
        // hit the database to see whether the resource exists or not.
        if (isset($parameters['slug'])) {
            $criteria = new Criteria();
            $criteria->add(QubitSlug::SLUG, $parameters['slug']);
            $criteria->addJoin(QubitSlug::OBJECT_ID, QubitObject::ID);

            if (null === $this->resource = QubitObject::get($criteria)->__get(0)) {
                return false;
            }

            // Find the Symfony module to be used based in the object class.
            // In some cases, the metadata template passed as part of the URL will be
            // considered. Additionally, QubitInformationObject will be analyzed
            // differently, since every object may have a specific metadata template
            // assigned.
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

                    // See if there is a template assigned in the database for this record
                    $sql = 'SELECT code
                        FROM information_object JOIN term ON information_object.display_standard_id = term.id
                        WHERE information_object.id = ? AND taxonomy_id = ?';

                    if (false !== $defaultSetting = QubitPdo::fetchColumn($sql, [$this->resource->id, QubitTaxonomy::INFORMATION_OBJECT_TEMPLATE_ID])) {
                        $default = $defaultSetting;
                    }

                    $parameters['module'] = $this->getActionParameter(['isad', 'dc', 'mods', 'rad', 'ead', 'dacs', 'museum', 'dam', 'gallery', 'library'], $default, $parameters);

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
        }

        // Given the parent module (e.g. informationobject), find the best metadata
        // to be used
        elseif (isset($parameters['module'])) {
            switch ($parameters['module']) {
                case 'informationobject':
                    if (false !== $code = $this->getDefaultTemplate($parameters['module'])) {
                        $parameters['module'] = self::getPluginForTemplate($code);
                    }

                    break;

                case 'actor':
                case 'repository':
                case 'function':
                    $module = $parameters['module'];
                    $parameters['module'] = self::$DEFAULT_MODULES[$module];

                    break;
            }
        }

        return $parameters;
    }

    /**
     * @see sfRoute
     *
     * @param mixed $params
     * @param mixed $context
     */
    public function matchesParameters($params, $context = [])
    {
        $params = $this->parseParameters($params);

        if (!isset($params['slug']) && isset($params['module'])) {
            $module = $params['module'];
            if (!isset(self::$DEFAULT_MODULES[$module])) {
                return false;
            }
        }

        return parent::matchesParameters($params, $context);
    }

    /**
     * @see sfRoute
     *
     * @param mixed $params
     * @param mixed $context
     * @param mixed $absolute
     */
    public function generate($params, $context = [], $absolute = false)
    {
        $params = $this->parseParameters($params);

        return parent::generate($params, $context, $absolute);
    }

    protected function parseParameters($params)
    {
        // Fill in missing parameters with attributes of $params[0]
        if (!is_array($params)) {
            $params = [$params];
        }

        // Look for the slug property if an object is passed
        if (isset($params[0]) && is_object($params[0])) {
            // Extract slug if exists (some objects don't have a slug, like
            // QubitContactInformation
            try {
                $params['slug'] = $params[0]->slug;
            } catch (Exception $e) {
            }

            // Unset the object
            unset($params[0]);
        }

        if (isset($params['slug'])) {
            // Set the metadata template
            if (isset($params['module'])) {
                $key = self::getTemplateForPlugin($params['module']);
                if ($key !== null) {
                    $params['template'] = $key;
                }

                // Hide the module because it is hip!
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

        if (!in_array($code, $allowedValues)) {
            throw new sfConfigurationException(sprintf('The metadata code "%s" is not valid.', $code));
        }

        return self::getPluginForTemplate($code);
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

    /**
     * Fetch the default template for a given module from the database/settings
     * TODO: this should be cached somewhere.
     *
     * @param mixed $module
     */
    protected function getDefaultTemplate($module)
    {
        $sql = 'SELECT value
            FROM setting JOIN setting_i18n ON setting.id = setting_i18n.id
            WHERE scope = "default_template" AND name = ?';

        return QubitPdo::fetchColumn($sql, [$module]);
    }
}
