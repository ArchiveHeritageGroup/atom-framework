<?php

declare(strict_types=1);

namespace AtomExtensions\Helpers;

use AtomExtensions\Services\DisplayModeService;

/**
 * Helper functions for display mode switching.
 *
 * Usage in templates:
 * use AtomExtensions\Helpers\DisplayModeHelper;
 * echo DisplayModeHelper::renderToolbar('informationobject', $totalCount);
 */
class DisplayModeHelper
{
    private static ?DisplayModeService $service = null;

    /**
     * Get service instance.
     */
    private static function getService(): DisplayModeService
    {
        if (null === self::$service) {
            self::$service = new DisplayModeService();
        }

        return self::$service;
    }

    /**
     * Render the display mode toolbar.
     *
     * @param string $module     Module name
     * @param int    $totalCount Total results
     * @param array  $options    Additional options
     *
     * @return string HTML
     */
    public static function renderToolbar(string $module, int $totalCount = 0, array $options = []): string
    {
        $service = self::getService();
        $modes = $service->getModeMetas($module);

        if (count($modes) < 2) {
            return '';
        }

        ob_start();
        include sfConfig::get('sf_plugins_dir')
            . '/arAHGThemeB5Plugin/modules/sfAHGPlugin/templates/_displayModeToolbar.php';

        return ob_get_clean();
    }

    /**
     * Get current display mode for module.
     *
     * @param string $module Module name
     *
     * @return string Display mode
     */
    public static function getCurrentMode(string $module): string
    {
        return self::getService()->getCurrentMode($module);
    }

    /**
     * Get container CSS class for current mode.
     *
     * @param string $module Module name
     *
     * @return string CSS class
     */
    public static function getContainerClass(string $module): string
    {
        $mode = self::getCurrentMode($module);

        return self::getService()->getContainerClass($mode);
    }

    /**
     * Get items per page for module.
     *
     * @param string $module Module name
     *
     * @return int Items per page
     */
    public static function getItemsPerPage(string $module): int
    {
        return self::getService()->getItemsPerPage($module);
    }

    /**
     * Include the appropriate partial template for current mode.
     *
     * @param string $module Module name
     * @param array  $items  Items to display
     */
    public static function renderResults(string $module, array $items): string
    {
        $mode = self::getCurrentMode($module);
        $templatePath = sfConfig::get('sf_plugins_dir')
            . '/arAHGThemeB5Plugin/templates/displayModes/_' . $mode . '.php';

        if (!file_exists($templatePath)) {
            $templatePath = sfConfig::get('sf_plugins_dir')
                . '/arAHGThemeB5Plugin/templates/displayModes/_list.php';
        }

        ob_start();
        include $templatePath;

        return ob_get_clean();
    }

    /**
     * Transform search/browse results for display.
     *
     * @param array  $results Raw results from query/elasticsearch
     * @param string $module  Module name
     *
     * @return array Transformed items
     */
    public static function transformResults(array $results, string $module): array
    {
        $items = [];

        foreach ($results as $result) {
            $item = [];

            // Handle both array and object results
            $get = function ($key) use ($result) {
                if (is_array($result)) {
                    return $result[$key] ?? null;
                }
                if (is_object($result)) {
                    return $result->$key ?? null;
                }

                return null;
            };

            $item['id'] = $get('id');
            $item['slug'] = $get('slug');
            $item['title'] = $get('title') ?? $get('authorized_form_of_name') ?? $get('name');
            $item['reference_code'] = $get('reference_code') ?? $get('identifier');
            $item['dates'] = $get('dates') ?? $get('date');
            $item['level_of_description'] = $get('level_of_description');
            $item['scope_and_content'] = $get('scope_and_content') ?? $get('description');
            $item['thumbnail'] = $get('thumbnail_path') ?? $get('thumbnail');
            $item['thumbnail_large'] = $get('reference_path') ?? $get('thumbnail_large');
            $item['start_date'] = $get('start_date');
            $item['end_date'] = $get('end_date');
            $item['repository'] = $get('repository');
            $item['creator'] = $get('creator');

            // For hierarchical data
            $item['parent_id'] = $get('parent_id');
            $item['lft'] = $get('lft');
            $item['rgt'] = $get('rgt');
            $item['children'] = $get('children') ?? [];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Build hierarchical tree from flat results.
     *
     * @param array $items Flat items with parent_id
     *
     * @return array Hierarchical tree
     */
    public static function buildTree(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item['id']] = $item;
        }

        $tree = [];
        foreach ($indexed as $id => &$item) {
            if (!empty($item['parent_id']) && isset($indexed[$item['parent_id']])) {
                $indexed[$item['parent_id']]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }

        return $tree;
    }
}
