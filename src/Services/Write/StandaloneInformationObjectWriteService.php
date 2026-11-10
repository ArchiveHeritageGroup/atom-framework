<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone information object write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 *
 * Information objects have 18 i18n fields.
 */
class StandaloneInformationObjectWriteService implements InformationObjectWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = [
        'title', 'alternate_title', 'edition', 'extent_and_medium',
        'scope_and_content', 'arrangement', 'archival_history', 'acquisition',
        'appraisal', 'accruals', 'physical_characteristics', 'finding_aids',
        'access_conditions', 'reproduction_conditions', 'location_of_originals',
        'location_of_copies', 'related_units_of_description',
        'institution_responsible_identifier', 'rules', 'sources', 'revision_history',
    ];

    public function newInformationObject(): object
    {
        $io = new \stdClass();
        $io->id = null;
        $io->parentId = null;
        $io->identifier = null;
        $io->title = null;
        $io->levelOfDescriptionId = null;
        $io->sourceStandard = null;
        $io->sourceCulture = 'en';
        $io->displayStandardId = null;

        return $io;
    }

    public function createInformationObject(array $data, string $culture = 'en'): int
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);

        // Default parent to root
        if (!isset($core['parent_id'])) {
            $parentId = $data['parentId'] ?? null;
            $core['parent_id'] = $parentId ?? \QubitInformationObject::ROOT_ID;
        }

        $objectId = $this->insertEntity(
            'QubitInformationObject',
            'information_object',
            $core,
            'information_object_i18n',
            $i18n,
            $culture
        );

        $this->autoSlug($objectId, $i18n, 'title');

        return $objectId;
    }

    /**
     * Give a new description its place in the hierarchy and a publication status.
     *
     * createInformationObject() inserts the row only: lft/rgt stay NULL and there is
     * no publication status, and the record's page then fails ("Could not build SQL
     * for expression: information_object.LFT > NULL", "No publication status set").
     * Call this after creating. It is idempotent - a record that already has a
     * place or a status is left as it is - so callers that place records themselves
     * (ahgArchaeologyPlugin) are unaffected.
     *
     * ponytail: not called from createInformationObject() itself, because
     * ArchaeologyService::placeInNestedSet() places unconditionally and would place
     * a record twice. Once that checks lft first, call this from create.
     */
    public function placeInHierarchy(int $id, int $publicationStatusId = 159): void
    {
        $node = DB::table('information_object')->where('id', $id)->first(['lft', 'parent_id']);
        if (!$node) {
            return;
        }
        if (null === $node->lft) {
            DB::transaction(function () use ($id, $node) {
                $parentId = (int) ($node->parent_id ?: \QubitInformationObject::ROOT_ID);
                $edge = DB::table('information_object')->where('id', $parentId)->lockForUpdate()->value('rgt');
                if (null === $edge) {
                    return; // parent outside the tree: placing would corrupt the set
                }
                DB::table('information_object')->where('rgt', '>=', $edge)->increment('rgt', 2);
                DB::table('information_object')->where('lft', '>', $edge)->increment('lft', 2);
                DB::table('information_object')->where('id', $id)->update(['lft' => $edge, 'rgt' => $edge + 1]);
            });
        }
        $hasStatus = DB::table('status')->where('object_id', $id)->where('type_id', 158)->exists();
        if (!$hasStatus) {
            DB::table('status')->insert(['object_id' => $id, 'type_id' => 158, 'status_id' => $publicationStatusId]);
        }
    }

    public function updateInformationObject(int $id, array $data, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);
        $this->updateEntity($id, 'information_object', $core, 'information_object_i18n', $i18n, $culture);
    }

    public function saveInformationObject(object $informationObject): int
    {
        $data = [];
        foreach (get_object_vars($informationObject) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        if (!empty($informationObject->id)) {
            $this->updateInformationObject($informationObject->id, $data, $culture);

            return $informationObject->id;
        }

        return $this->createInformationObject($data, $culture);
    }
}
