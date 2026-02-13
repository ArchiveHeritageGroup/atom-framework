<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Import write operations.
 *
 * Uses Propel (QubitInformationObject, QubitActor, QubitTerm, QubitEvent,
 * QubitNote, QubitProperty, QubitRelation) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 *   object -> actor -> actor_i18n
 *   object -> term -> term_i18n
 */
class PropelImportWriteService implements ImportWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitInformationObject', false)
            || class_exists('QubitInformationObject');
    }

    public function createInformationObject(array $attributes, array $i18nAttributes, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateInformationObject($attributes, $i18nAttributes, $culture);
        }

        return $this->dbCreateInformationObject($attributes, $i18nAttributes, $culture);
    }

    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateEvent($objectId, $attributes, $culture);
        }

        return $this->dbCreateEvent($objectId, $attributes, $culture);
    }

    public function createOrFindActor(string $name, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateOrFindActor($name, $culture);
        }

        return $this->dbCreateOrFindActor($name, $culture);
    }

    public function createOrFindTerm(int $taxonomyId, string $name, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateOrFindTerm($taxonomyId, $name, $culture);
        }

        return $this->dbCreateOrFindTerm($taxonomyId, $name, $culture);
    }

    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateNote($objectId, $typeId, $content, $culture);
        }

        return $this->dbCreateNote($objectId, $typeId, $content, $culture);
    }

    public function createProperty(int $objectId, string $name, string $value, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateProperty($objectId, $name, $value, $culture);
        }

        return $this->dbCreateProperty($objectId, $name, $value, $culture);
    }

    public function createSlug(int $objectId, string $slug): void
    {
        // Slugs are simple inserts — same logic for both modes
        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateRelation($subjectId, $objectId, $typeId);
        }

        return $this->dbCreateRelation($subjectId, $objectId, $typeId);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateInformationObject(array $attributes, array $i18nAttributes, string $culture): int
    {
        $io = new \QubitInformationObject();
        $io->sourceCulture = $culture;

        // Set core attributes
        foreach ($attributes as $key => $value) {
            $io->{$key} = $value;
        }

        // Set i18n attributes
        foreach ($i18nAttributes as $key => $value) {
            $io->{$key} = $value;
        }

        // Default parent to root if not set
        if (!isset($io->parentId)) {
            $io->parentId = \QubitInformationObject::ROOT_ID;
        }

        $io->save();

        return $io->id;
    }

    private function propelCreateEvent(int $objectId, array $attributes, string $culture): int
    {
        $event = new \QubitEvent();
        $event->objectId = $objectId;
        $event->sourceCulture = $culture;

        foreach ($attributes as $key => $value) {
            $event->{$key} = $value;
        }

        $event->save();

        return $event->id;
    }

    private function propelCreateOrFindActor(string $name, string $culture): int
    {
        // Try to find existing actor
        $actor = \QubitActor::getByAuthorizedFormOfName($name);
        if (null !== $actor) {
            return $actor->id;
        }

        // Create new actor
        $actor = new \QubitActor();
        $actor->parentId = \QubitActor::ROOT_ID;
        $actor->sourceCulture = $culture;
        $actor->setAuthorizedFormOfName($name, ['culture' => $culture]);
        $actor->save();

        return $actor->id;
    }

    private function propelCreateOrFindTerm(int $taxonomyId, string $name, string $culture): int
    {
        // Try to find existing term
        $criteria = new \Criteria();
        $criteria->add(\QubitTerm::TAXONOMY_ID, $taxonomyId);
        $criteria->addJoin(\QubitTerm::ID, \QubitTermI18n::ID);
        $criteria->add(\QubitTermI18n::CULTURE, $culture);
        $criteria->add(\QubitTermI18n::NAME, $name);

        $term = \QubitTerm::getOne($criteria);
        if (null !== $term) {
            return $term->id;
        }

        // Create new term
        $term = new \QubitTerm();
        $term->taxonomyId = $taxonomyId;
        $term->parentId = \QubitTerm::ROOT_ID;
        $term->sourceCulture = $culture;
        $term->setName($name, ['culture' => $culture]);
        $term->save();

        return $term->id;
    }

    private function propelCreateNote(int $objectId, int $typeId, string $content, string $culture): int
    {
        $note = new \QubitNote();
        $note->objectId = $objectId;
        $note->typeId = $typeId;
        $note->sourceCulture = $culture;
        $note->setContent($content, ['culture' => $culture]);
        $note->save();

        return $note->id;
    }

    private function propelCreateProperty(int $objectId, string $name, string $value, string $culture): int
    {
        $property = new \QubitProperty();
        $property->objectId = $objectId;
        $property->name = $name;
        $property->sourceCulture = $culture;
        $property->setValue($value, ['culture' => $culture]);
        $property->save();

        return $property->id;
    }

    private function propelCreateRelation(int $subjectId, int $objectId, int $typeId): int
    {
        $relation = new \QubitRelation();
        $relation->subjectId = $subjectId;
        $relation->objectId = $objectId;
        $relation->typeId = $typeId;
        $relation->save();

        return $relation->id;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateInformationObject(array $attributes, array $i18nAttributes, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Build IO core data
        $ioData = ['id' => $objectId, 'source_culture' => $culture];
        foreach ($attributes as $key => $value) {
            $ioData[$this->toSnakeCase($key)] = $value;
        }

        // Default parent to root
        if (!isset($ioData['parent_id'])) {
            $ioData['parent_id'] = \QubitInformationObject::ROOT_ID;
        }

        DB::table('information_object')->insert($ioData);

        // Insert i18n
        $i18nData = ['id' => $objectId, 'culture' => $culture];
        foreach ($i18nAttributes as $key => $value) {
            $i18nData[$this->toSnakeCase($key)] = $value;
        }

        DB::table('information_object_i18n')->insert($i18nData);

        return $objectId;
    }

    private function dbCreateEvent(int $objectId, array $attributes, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        $eventObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventData = ['id' => $eventObjectId, 'object_id' => $objectId, 'source_culture' => $culture];
        $i18nData = ['id' => $eventObjectId, 'culture' => $culture];

        $i18nFields = ['date', 'description'];

        foreach ($attributes as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nData[$snakeKey] = $value;
            } else {
                $eventData[$snakeKey] = $value;
            }
        }

        DB::table('event')->insert($eventData);
        DB::table('event_i18n')->insert($i18nData);

        return $eventObjectId;
    }

    private function dbCreateOrFindActor(string $name, string $culture): int
    {
        // Try to find existing actor by name
        $existing = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.authorized_form_of_name', $name)
            ->where('actor_i18n.culture', $culture)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $now = date('Y-m-d H:i:s');

        // Create: object -> actor -> actor_i18n
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('actor')->insert([
            'id' => $objectId,
            'parent_id' => \QubitActor::ROOT_ID,
            'source_culture' => $culture,
        ]);

        DB::table('actor_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'authorized_form_of_name' => $name,
        ]);

        return $objectId;
    }

    private function dbCreateOrFindTerm(int $taxonomyId, string $name, string $culture): int
    {
        // Try to find existing term
        $existing = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', $name)
            ->where('term_i18n.culture', $culture)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $now = date('Y-m-d H:i:s');

        // Create: object -> term -> term_i18n
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('term')->insert([
            'id' => $objectId,
            'taxonomy_id' => $taxonomyId,
            'parent_id' => \QubitTerm::ROOT_ID,
            'source_culture' => $culture,
        ]);

        DB::table('term_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'name' => $name,
        ]);

        return $objectId;
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
            'source_culture' => $culture,
        ]);

        DB::table('note_i18n')->insert([
            'id' => $noteObjectId,
            'culture' => $culture,
            'content' => $content,
        ]);

        return $noteObjectId;
    }

    private function dbCreateProperty(int $objectId, string $name, string $value, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        $propObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitProperty',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('property')->insert([
            'id' => $propObjectId,
            'object_id' => $objectId,
            'name' => $name,
            'source_culture' => $culture,
        ]);

        DB::table('property_i18n')->insert([
            'id' => $propObjectId,
            'culture' => $culture,
            'value' => $value,
        ]);

        return $propObjectId;
    }

    private function dbCreateRelation(int $subjectId, int $objectId, int $typeId): int
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

        return $relObjectId;
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
