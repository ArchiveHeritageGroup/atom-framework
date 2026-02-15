<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone term/taxonomy write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain: object -> term -> term_i18n.
 * Includes slug generation for new terms.
 */
class StandaloneTermWriteService implements TermWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = ['name'];

    public function createTerm(int $taxonomyId, string $name, string $culture = 'en', ?int $parentId = null): object
    {
        $objectId = $this->insertEntity(
            'QubitTerm',
            'term',
            [
                'taxonomy_id' => $taxonomyId,
                'parent_id' => $parentId ?? \QubitTerm::ROOT_ID,
            ],
            'term_i18n',
            ['name' => $name],
            $culture
        );

        $this->autoSlug($objectId, ['name' => $name]);

        $result = new \stdClass();
        $result->id = $objectId;
        $result->taxonomyId = $taxonomyId;
        $result->name = $name;

        return $result;
    }

    public function updateTerm(int $id, array $attributes, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($attributes, self::I18N_FIELDS);
        $this->updateEntity($id, 'term', $core, 'term_i18n', $i18n, $culture);
    }

    public function deleteTerm(int $id): bool
    {
        DB::table('slug')->where('object_id', $id)->delete();

        return $this->deleteEntity($id, 'term', 'term_i18n');
    }

    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int
    {
        return $this->createNoteRecord($objectId, $typeId, $content, $culture);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): object
    {
        $relId = $this->createRelationRecord($subjectId, $objectId, $typeId);

        $result = new \stdClass();
        $result->id = $relId;
        $result->subjectId = $subjectId;
        $result->objectId = $objectId;
        $result->typeId = $typeId;

        return $result;
    }

    public function createOtherName(int $objectId, string $name, int $typeId, string $culture = 'en'): object
    {
        $onId = $this->createOtherNameRecord($objectId, $name, $typeId, $culture);

        $result = new \stdClass();
        $result->id = $onId;
        $result->objectId = $objectId;
        $result->name = $name;
        $result->typeId = $typeId;

        return $result;
    }

    public function newTerm(): object
    {
        return new \stdClass();
    }

    public function newRelation(): object
    {
        return new \stdClass();
    }

    public function newOtherName(): object
    {
        return new \stdClass();
    }
}
