<?php

declare(strict_types=1);

namespace AtomFramework\Services\Delete;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Unified entity delete service for standalone Heratio mode.
 *
 * Dispatches entity deletions to the appropriate CrudService::delete() method
 * based on the entity's class_name in the `object` table. Provides a non-Propel
 * delete path for all AtoM entity types using Laravel Query Builder.
 *
 * For entities without a dedicated CrudService (Term, Feedback), deletion is
 * handled inline with full referential integrity.
 *
 * All deletions are wrapped in DB::transaction() for atomicity.
 *
 * WP18: Phase 3 of the Heratio migration.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class EntityDeleteService
{
    /**
     * Map of Propel class names to [plugin, serviceClass, serviceFile] for lazy loading.
     *
     * Each entry identifies:
     *  - [0] plugin directory name (under plugins/)
     *  - [1] service class name (for documentation only; dispatch is via switch)
     *  - [2] relative path to the service file within the plugin
     */
    private static array $serviceMap = [
        'QubitActor' => [
            'ahgActorManagePlugin',
            'ActorCrudService',
            'lib/Services/ActorCrudService.php',
        ],
        'QubitRepository' => [
            'ahgRepositoryManagePlugin',
            'RepositoryCrudService',
            'lib/Services/RepositoryCrudService.php',
        ],
        'QubitDonor' => [
            'ahgDonorManagePlugin',
            'DonorCrudService',
            'lib/Services/DonorCrudService.php',
        ],
        'QubitRightsHolder' => [
            'ahgRightsHolderManagePlugin',
            'RightsHolderCrudService',
            'lib/Services/RightsHolderCrudService.php',
        ],
        'QubitAccession' => [
            'ahgAccessionManagePlugin',
            'AccessionCrudService',
            'lib/Services/AccessionCrudService.php',
        ],
        'QubitPhysicalObject' => [
            'ahgStorageManagePlugin',
            'StorageCrudService',
            'lib/Services/StorageCrudService.php',
        ],
        'QubitInformationObject' => [
            'ahgInformationObjectManagePlugin',
            'InformationObjectCrudService',
            'lib/Services/InformationObjectCrudService.php',
        ],
        'QubitUser' => [
            'ahgUserManagePlugin',
            'UserCrudService',
            'lib/Services/UserCrudService.php',
        ],
        'QubitMenu' => [
            'ahgMenuManagePlugin',
            'MenuCrudService',
            'lib/Services/MenuCrudService.php',
        ],
        'QubitStaticPage' => [
            'ahgStaticPagePlugin',
            'StaticPageCrudService',
            'lib/Services/StaticPageCrudService.php',
        ],
        'QubitFunctionObject' => [
            'ahgFunctionManagePlugin',
            'FunctionCrudService',
            'lib/Services/FunctionCrudService.php',
        ],
    ];

    /**
     * Tracks which CrudService files have been loaded to avoid redundant require_once.
     *
     * @var array<string, bool>
     */
    private static array $loaded = [];

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Delete an entity by its object ID.
     *
     * Looks up the class_name from the `object` table and dispatches to the
     * appropriate CrudService::delete() method or handles deletion inline.
     *
     * @param int $objectId The object.id of the entity to delete
     *
     * @return bool True if the entity was found and deleted
     *
     * @throws \RuntimeException  If the class_name is not supported
     * @throws \RuntimeException  If the required CrudService file cannot be found
     * @throws \Throwable         Re-thrown from DB::transaction on failure
     */
    public static function delete(int $objectId): bool
    {
        $className = DB::table('object')
            ->where('id', $objectId)
            ->value('class_name');

        if ($className === null) {
            return false;
        }

        return self::deleteByClassName($objectId, (string) $className);
    }

    /**
     * Delete an entity when the class_name is already known.
     *
     * Skips the object table lookup. Useful when the caller has already
     * resolved the entity type (e.g. from a browse list or route context).
     *
     * @param int    $id        The entity/object ID
     * @param string $className The Propel class name (e.g. 'QubitActor')
     *
     * @return bool True if deletion was dispatched successfully
     *
     * @throws \RuntimeException  If the class_name is not supported
     * @throws \RuntimeException  If the required CrudService file cannot be found
     * @throws \Throwable         Re-thrown from DB::transaction on failure
     */
    public static function deleteByClassName(int $id, string $className): bool
    {
        // Inline handlers (no CrudService)
        if ($className === 'QubitTerm') {
            self::deleteTerm($id);

            return true;
        }

        if ($className === 'QubitFeedback') {
            self::deleteFeedback($id);

            return true;
        }

        // CrudService dispatch
        if (!isset(self::$serviceMap[$className])) {
            throw new \RuntimeException(
                sprintf('EntityDeleteService: unsupported class_name "%s" for object ID %d.', $className, $id)
            );
        }

        self::loadService($className);
        self::dispatchDelete($id, $className);

        return true;
    }

    /**
     * Check whether this service supports deleting a given class_name.
     *
     * @param string $className The Propel class name
     *
     * @return bool True if the class_name can be handled
     */
    public static function supports(string $className): bool
    {
        return isset(self::$serviceMap[$className])
            || $className === 'QubitTerm'
            || $className === 'QubitFeedback';
    }

    // ------------------------------------------------------------------
    // CrudService lazy-loading and dispatch
    // ------------------------------------------------------------------

    /**
     * Lazy-load a CrudService file via require_once.
     *
     * Resolves the file path from the plugins directory using sfConfig when
     * available, falling back to the default ARCHIVE_PATH/plugins.
     *
     * @param string $className The Propel class name used as the service map key
     *
     * @throws \RuntimeException If the service file does not exist
     */
    private static function loadService(string $className): void
    {
        if (isset(self::$loaded[$className])) {
            return;
        }

        $map = self::$serviceMap[$className];
        $pluginsDir = class_exists('\sfConfig', false)
            ? \sfConfig::get('sf_plugins_dir', '/usr/share/nginx/archive/plugins')
            : '/usr/share/nginx/archive/plugins';

        $path = $pluginsDir . '/' . $map[0] . '/' . $map[2];

        if (!file_exists($path)) {
            throw new \RuntimeException(
                sprintf(
                    'EntityDeleteService: CrudService file not found for %s at %s. '
                    . 'Is the %s plugin installed?',
                    $className,
                    $path,
                    $map[0]
                )
            );
        }

        // Load the ahgCorePlugin services that CrudServices depend on
        self::loadCoreServices($pluginsDir);

        require_once $path;
        self::$loaded[$className] = true;
    }

    /**
     * Load ahgCorePlugin helper services that CrudServices depend on.
     *
     * These are loaded once and cached. Services include ObjectService,
     * RelationService, NoteService, I18nService, EventService, etc.
     *
     * @param string $pluginsDir The resolved plugins directory path
     */
    private static function loadCoreServices(string $pluginsDir): void
    {
        if (isset(self::$loaded['__core__'])) {
            return;
        }

        $coreDir = $pluginsDir . '/ahgCorePlugin/lib/Services';

        $coreFiles = [
            'ObjectService.php',
            'I18nService.php',
            'RelationService.php',
            'NoteService.php',
            'EventService.php',
            'TermRelationService.php',
            'OtherNameService.php',
            'ContactInformationService.php',
        ];

        foreach ($coreFiles as $file) {
            $fullPath = $coreDir . '/' . $file;
            if (file_exists($fullPath)) {
                require_once $fullPath;
            }
        }

        self::$loaded['__core__'] = true;
    }

    /**
     * Dispatch deletion to the appropriate CrudService::delete() static method.
     *
     * Uses a switch statement to avoid namespace resolution issues, since each
     * CrudService has its own plugin-specific namespace.
     *
     * @param int    $id        The entity/object ID to delete
     * @param string $className The Propel class name
     *
     * @throws \RuntimeException If no dispatch case exists for the class_name
     */
    private static function dispatchDelete(int $id, string $className): void
    {
        switch ($className) {
            case 'QubitActor':
                \AhgActorManage\Services\ActorCrudService::delete($id);
                break;

            case 'QubitRepository':
                \AhgRepositoryManage\Services\RepositoryCrudService::delete($id);
                break;

            case 'QubitDonor':
                \AhgDonorManage\Services\DonorCrudService::delete($id);
                break;

            case 'QubitRightsHolder':
                \AhgRightsHolderManage\Services\RightsHolderCrudService::delete($id);
                break;

            case 'QubitAccession':
                \AhgAccessionManage\Services\AccessionCrudService::delete($id);
                break;

            case 'QubitPhysicalObject':
                \AhgStorageManage\Services\StorageCrudService::delete($id);
                break;

            case 'QubitInformationObject':
                \AhgInformationObjectManage\Services\InformationObjectCrudService::delete($id);
                break;

            case 'QubitUser':
                \AhgUserManage\Services\UserCrudService::delete($id);
                break;

            case 'QubitMenu':
                \AhgMenuManage\Services\MenuCrudService::delete($id);
                break;

            case 'QubitStaticPage':
                \AhgStaticPage\Services\StaticPageCrudService::delete($id);
                break;

            case 'QubitFunctionObject':
                \AhgFunctionManage\Services\FunctionCrudService::delete($id);
                break;

            default:
                throw new \RuntimeException(
                    sprintf('EntityDeleteService: no dispatch handler for class_name "%s".', $className)
                );
        }
    }

    // ------------------------------------------------------------------
    // Inline delete: QubitTerm (MPTT nested set)
    // ------------------------------------------------------------------

    /**
     * Delete a term and all its descendants from the MPTT nested set tree.
     *
     * Handles the full cascade:
     *  1. Collect all IDs in the subtree (lft >= term.lft AND rgt <= term.rgt)
     *  2. Delete object_term_relation rows referencing those terms
     *  3. Delete term_i18n rows for the subtree
     *  4. Delete note + note_i18n rows attached to subtree terms
     *  5. Delete relation + relation_i18n rows referencing subtree terms
     *  6. Delete term rows in the subtree
     *  7. Close the MPTT gap (decrement lft/rgt for nodes after the subtree)
     *  8. Delete slug + object rows for all subtree IDs
     *
     * @param int $id The term.id to delete (root of the subtree)
     *
     * @throws \RuntimeException If the term does not exist
     */
    private static function deleteTerm(int $id): void
    {
        DB::transaction(function () use ($id) {
            // Get the term's lft/rgt boundaries
            $term = DB::table('term')
                ->where('id', $id)
                ->select('lft', 'rgt', 'taxonomy_id')
                ->first();

            if (!$term) {
                throw new \RuntimeException(
                    sprintf('EntityDeleteService: term ID %d not found.', $id)
                );
            }

            $lft = (int) $term->lft;
            $rgt = (int) $term->rgt;
            $width = $rgt - $lft + 1;

            // Collect all descendant IDs (including self)
            $subtreeIds = DB::table('term')
                ->where('lft', '>=', $lft)
                ->where('rgt', '<=', $rgt)
                ->where('taxonomy_id', $term->taxonomy_id)
                ->pluck('id')
                ->all();

            if (empty($subtreeIds)) {
                return;
            }

            // Process in chunks to avoid exceeding MySQL placeholder limits
            foreach (array_chunk($subtreeIds, 500) as $chunk) {
                // 1. Delete object_term_relation rows referencing these terms
                DB::table('object_term_relation')
                    ->whereIn('term_id', $chunk)
                    ->delete();

                // 2. Delete term_i18n rows
                DB::table('term_i18n')
                    ->whereIn('id', $chunk)
                    ->delete();

                // 3. Delete notes and note_i18n attached to these terms
                $noteIds = DB::table('note')
                    ->whereIn('object_id', $chunk)
                    ->pluck('id')
                    ->all();

                if (!empty($noteIds)) {
                    foreach (array_chunk($noteIds, 500) as $noteChunk) {
                        DB::table('note_i18n')
                            ->whereIn('id', $noteChunk)
                            ->delete();
                    }
                    DB::table('note')
                        ->whereIn('object_id', $chunk)
                        ->delete();
                }

                // 4. Delete relations (subject or object) + relation_i18n
                $relationIds = DB::table('relation')
                    ->where(function ($q) use ($chunk) {
                        $q->whereIn('subject_id', $chunk)
                            ->orWhereIn('object_id', $chunk);
                    })
                    ->pluck('id')
                    ->all();

                if (!empty($relationIds)) {
                    foreach (array_chunk($relationIds, 500) as $relChunk) {
                        DB::table('relation_i18n')
                            ->whereIn('id', $relChunk)
                            ->delete();
                    }
                    DB::table('relation')
                        ->where(function ($q) use ($chunk) {
                            $q->whereIn('subject_id', $chunk)
                                ->orWhereIn('object_id', $chunk);
                        })
                        ->delete();
                }
            }

            // 5. Delete term rows in the subtree
            DB::table('term')
                ->where('lft', '>=', $lft)
                ->where('rgt', '<=', $rgt)
                ->where('taxonomy_id', $term->taxonomy_id)
                ->delete();

            // 6. Close the MPTT gap for the same taxonomy
            //    All nodes with lft > rgt of deleted subtree: decrement lft by width
            //    All nodes with rgt > rgt of deleted subtree: decrement rgt by width
            DB::table('term')
                ->where('taxonomy_id', $term->taxonomy_id)
                ->where('lft', '>', $rgt)
                ->decrement('lft', $width);

            DB::table('term')
                ->where('taxonomy_id', $term->taxonomy_id)
                ->where('rgt', '>', $rgt)
                ->decrement('rgt', $width);

            // 7. Delete slugs and object rows for all subtree IDs
            foreach (array_chunk($subtreeIds, 500) as $chunk) {
                DB::table('slug')
                    ->whereIn('object_id', $chunk)
                    ->delete();

                DB::table('object')
                    ->whereIn('id', $chunk)
                    ->delete();
            }
        });
    }

    // ------------------------------------------------------------------
    // Inline delete: QubitFeedback (simple entity)
    // ------------------------------------------------------------------

    /**
     * Delete a feedback entry and its associated object/slug rows.
     *
     * Feedback is a simple entity with no i18n table and no child relations.
     * The feedback table has its own columns (feed_name, feed_email, etc.)
     * plus MPTT fields (lft, rgt) that are not actively used for hierarchy.
     *
     * @param int $objectId The feedback.id (= object.id) to delete
     *
     * @throws \RuntimeException If the feedback record does not exist
     */
    private static function deleteFeedback(int $objectId): void
    {
        DB::transaction(function () use ($objectId) {
            $exists = DB::table('feedback')
                ->where('id', $objectId)
                ->exists();

            if (!$exists) {
                throw new \RuntimeException(
                    sprintf('EntityDeleteService: feedback ID %d not found.', $objectId)
                );
            }

            // Delete the feedback record
            DB::table('feedback')
                ->where('id', $objectId)
                ->delete();

            // Delete slug + object via the same pattern as ObjectService::deleteObject()
            DB::table('slug')
                ->where('object_id', $objectId)
                ->delete();

            DB::table('object')
                ->where('id', $objectId)
                ->delete();
        });
    }
}
