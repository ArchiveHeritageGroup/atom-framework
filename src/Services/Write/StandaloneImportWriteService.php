<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone import write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Supports CSV/bulk import operations including find-or-create patterns
 * for actors and terms.
 */
class StandaloneImportWriteService implements ImportWriteServiceInterface
{
    use EntityWriteTrait;

    private const IO_I18N_FIELDS = [
        'title', 'alternate_title', 'scope_and_content', 'arrangement',
        'archival_history', 'acquisition', 'appraisal', 'accruals',
        'physical_characteristics', 'finding_aids', 'access_conditions',
        'reproduction_conditions', 'location_of_originals', 'location_of_copies',
        'related_units_of_description', 'rules', 'sources', 'revision_history',
    ];

    public function createInformationObject(array $attributes, array $i18nAttributes, string $culture = 'en'): int
    {
        $ioData = [];
        foreach ($attributes as $key => $value) {
            $ioData[$this->toSnakeCase($key)] = $value;
        }

        if (!isset($ioData['parent_id'])) {
            $ioData['parent_id'] = \QubitInformationObject::ROOT_ID;
        }

        $i18nData = [];
        foreach ($i18nAttributes as $key => $value) {
            $i18nData[$this->toSnakeCase($key)] = $value;
        }

        $objectId = $this->insertEntity(
            'QubitInformationObject',
            'information_object',
            $ioData,
            'information_object_i18n',
            $i18nData,
            $culture
        );

        $this->autoSlug($objectId, $i18nData, 'title');

        return $objectId;
    }

    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): int
    {
        return $this->createEventRecord($objectId, $attributes, $culture);
    }

    public function createOrFindActor(string $name, string $culture = 'en'): int
    {
        $existing = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.authorized_form_of_name', $name)
            ->where('actor_i18n.culture', $culture)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $objectId = $this->insertEntity(
            'QubitActor',
            'actor',
            ['parent_id' => \QubitActor::ROOT_ID],
            'actor_i18n',
            ['authorized_form_of_name' => $name],
            $culture
        );

        $this->autoSlug($objectId, ['name' => $name]);

        return $objectId;
    }

    public function createOrFindTerm(int $taxonomyId, string $name, string $culture = 'en'): int
    {
        $existing = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', $name)
            ->where('term_i18n.culture', $culture)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $objectId = $this->insertEntity(
            'QubitTerm',
            'term',
            [
                'taxonomy_id' => $taxonomyId,
                'parent_id' => \QubitTerm::ROOT_ID,
            ],
            'term_i18n',
            ['name' => $name],
            $culture
        );

        $this->autoSlug($objectId, ['name' => $name]);

        return $objectId;
    }

    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int
    {
        return $this->createNoteRecord($objectId, $typeId, $content, $culture);
    }

    public function createProperty(int $objectId, string $name, string $value, string $culture = 'en'): int
    {
        return $this->createPropertyRecord($objectId, $name, $value, $culture);
    }

    public function createSlug(int $objectId, string $slug): void
    {
        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): int
    {
        return $this->createRelationRecord($subjectId, $objectId, $typeId);
    }
}
