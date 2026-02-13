<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Term/taxonomy write operations.
 *
 * Uses Propel (QubitTerm, QubitRelation, QubitOtherName) when available
 * (Symfony mode). Falls back to Laravel Query Builder for standalone
 * Heratio mode, handling the AtoM entity inheritance chain:
 *   object -> term -> term_i18n
 */
class PropelTermWriteService implements TermWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitTerm', false)
            || class_exists('QubitTerm');
    }

    public function createTerm(int $taxonomyId, string $name, string $culture = 'en', ?int $parentId = null): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateTerm($taxonomyId, $name, $culture, $parentId);
        }

        return $this->dbCreateTerm($taxonomyId, $name, $culture, $parentId);
    }

    public function updateTerm(int $id, array $attributes, string $culture = 'en'): void
    {
        if ($this->hasPropel) {
            $this->propelUpdateTerm($id, $attributes, $culture);

            return;
        }

        $this->dbUpdateTerm($id, $attributes, $culture);
    }

    public function deleteTerm(int $id): bool
    {
        if ($this->hasPropel) {
            return $this->propelDeleteTerm($id);
        }

        return $this->dbDeleteTerm($id);
    }

    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateNote($objectId, $typeId, $content, $culture);
        }

        return $this->dbCreateNote($objectId, $typeId, $content, $culture);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateRelation($subjectId, $objectId, $typeId);
        }

        return $this->dbCreateRelation($subjectId, $objectId, $typeId);
    }

    public function createOtherName(int $objectId, string $name, int $typeId, string $culture = 'en'): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateOtherName($objectId, $name, $typeId, $culture);
        }

        return $this->dbCreateOtherName($objectId, $name, $typeId, $culture);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateTerm(int $taxonomyId, string $name, string $culture, ?int $parentId): object
    {
        $term = new \QubitTerm();
        $term->taxonomyId = $taxonomyId;
        $term->parentId = $parentId ?? \QubitTerm::ROOT_ID;
        $term->sourceCulture = $culture;
        $term->setName($name, ['culture' => $culture]);
        $term->save();

        return $term;
    }

    private function propelUpdateTerm(int $id, array $attributes, string $culture): void
    {
        $term = \QubitTerm::getById($id);
        if (null === $term) {
            return;
        }

        // Handle i18n attributes
        $i18nFields = ['name'];
        foreach ($i18nFields as $field) {
            if (isset($attributes[$field])) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($term, $setter)) {
                    $term->{$setter}($attributes[$field], ['culture' => $culture]);
                } else {
                    $term->{$field} = $attributes[$field];
                }
                unset($attributes[$field]);
            }
        }

        // Handle direct attributes
        foreach ($attributes as $key => $value) {
            $term->{$key} = $value;
        }

        $term->save();
    }

    private function propelDeleteTerm(int $id): bool
    {
        $term = \QubitTerm::getById($id);
        if (null === $term) {
            return false;
        }

        $term->delete();

        return true;
    }

    private function propelCreateNote(int $objectId, int $typeId, string $content, string $culture): int
    {
        $note = new \QubitNote();
        $note->objectId = $objectId;
        $note->typeId = $typeId;
        $note->scope = 'QubitTerm';
        $note->setContent($content, ['culture' => $culture]);
        $note->sourceCulture = $culture;
        $note->save();

        return $note->id;
    }

    private function propelCreateRelation(int $subjectId, int $objectId, int $typeId): object
    {
        $relation = new \QubitRelation();
        $relation->subjectId = $subjectId;
        $relation->objectId = $objectId;
        $relation->typeId = $typeId;
        $relation->save();

        return $relation;
    }

    private function propelCreateOtherName(int $objectId, string $name, int $typeId, string $culture): object
    {
        $otherName = new \QubitOtherName();
        $otherName->objectId = $objectId;
        $otherName->name = $name;
        $otherName->typeId = $typeId;
        $otherName->culture = $culture;
        $otherName->save();

        return $otherName;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateTerm(int $taxonomyId, string $name, string $culture, ?int $parentId): object
    {
        $now = date('Y-m-d H:i:s');

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert into term table
        DB::table('term')->insert([
            'id' => $objectId,
            'taxonomy_id' => $taxonomyId,
            'parent_id' => $parentId ?? \QubitTerm::ROOT_ID,
            'source_culture' => $culture,
        ]);

        // Insert into term_i18n table
        DB::table('term_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'name' => $name,
        ]);

        $result = new \stdClass();
        $result->id = $objectId;
        $result->taxonomyId = $taxonomyId;
        $result->name = $name;

        return $result;
    }

    private function dbUpdateTerm(int $id, array $attributes, string $culture): void
    {
        $i18nFields = ['name'];
        $i18nUpdates = [];
        $termUpdates = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $i18nFields)) {
                $i18nUpdates[$key] = $value;
            } else {
                $termUpdates[$key] = $value;
            }
        }

        if (!empty($termUpdates)) {
            DB::table('term')->where('id', $id)->update($termUpdates);
        }

        if (!empty($i18nUpdates)) {
            DB::table('term_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function dbDeleteTerm(int $id): bool
    {
        $exists = DB::table('term')->where('id', $id)->exists();
        if (!$exists) {
            return false;
        }

        DB::table('term_i18n')->where('id', $id)->delete();
        DB::table('term')->where('id', $id)->delete();
        DB::table('object')->where('id', $id)->delete();

        return true;
    }

    private function dbCreateNote(int $objectId, int $typeId, string $content, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        $noteObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitNote',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('note')->insert([
            'id' => $noteObjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
            'scope' => 'QubitTerm',
            'source_culture' => $culture,
        ]);

        DB::table('note_i18n')->insert([
            'id' => $noteObjectId,
            'culture' => $culture,
            'content' => $content,
        ]);

        return $noteObjectId;
    }

    private function dbCreateRelation(int $subjectId, int $objectId, int $typeId): object
    {
        $now = date('Y-m-d H:i:s');

        $relObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('relation')->insert([
            'id' => $relObjectId,
            'subject_id' => $subjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
        ]);

        $result = new \stdClass();
        $result->id = $relObjectId;
        $result->subjectId = $subjectId;
        $result->objectId = $objectId;
        $result->typeId = $typeId;

        return $result;
    }

    private function dbCreateOtherName(int $objectId, string $name, int $typeId, string $culture): object
    {
        $now = date('Y-m-d H:i:s');

        $onObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitOtherName',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('other_name')->insert([
            'id' => $onObjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
            'source_culture' => $culture,
        ]);

        DB::table('other_name_i18n')->insert([
            'id' => $onObjectId,
            'culture' => $culture,
            'name' => $name,
        ]);

        $result = new \stdClass();
        $result->id = $onObjectId;
        $result->objectId = $objectId;
        $result->name = $name;
        $result->typeId = $typeId;

        return $result;
    }

    // ─── Factory Methods (unsaved objects) ──────────────────────────

    public function newTerm(): object
    {
        if ($this->hasPropel) {
            return new \QubitTerm();
        }

        return new \stdClass();
    }

    public function newRelation(): object
    {
        if ($this->hasPropel) {
            return new \QubitRelation();
        }

        return new \stdClass();
    }

    public function newOtherName(): object
    {
        if ($this->hasPropel) {
            return new \QubitOtherName();
        }

        return new \stdClass();
    }
}
