<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone rights holder write service using Laravel Query Builder only.
 *
 * RightsHolder extends Actor in AtoM's entity hierarchy:
 *   object -> actor -> actor_i18n -> rights_holder
 *
 * The rights_holder table only has an `id` column (FK to actor.id).
 * All name/description fields live in actor_i18n.
 */
class StandaloneRightsHolderWriteService implements RightsHolderWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = [
        'authorized_form_of_name', 'dates_of_existence', 'history',
        'places', 'legal_status', 'functions', 'mandates',
        'internal_structures', 'general_context',
        'institution_responsible_identifier',
        'rules', 'sources', 'revision_history',
    ];

    public function createRightsHolder(array $data, string $culture = 'en'): int
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);

        if (!isset($core['parent_id'])) {
            $core['parent_id'] = \QubitActor::ROOT_ID;
        }

        // Use transaction for the 4-table insert: object -> actor -> actor_i18n -> rights_holder
        $objectId = DB::transaction(function () use ($core, $i18n, $culture) {
            $now = date('Y-m-d H:i:s');

            // Step 1: INSERT into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitRightsHolder',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Step 2: INSERT into actor table
            $core['id'] = $objectId;
            if (!isset($core['source_culture'])) {
                $core['source_culture'] = $culture;
            }
            DB::table('actor')->insert($core);

            // Step 3: INSERT into actor_i18n table
            $i18n['id'] = $objectId;
            $i18n['culture'] = $culture;
            DB::table('actor_i18n')->insert($i18n);

            // Step 4: INSERT into rights_holder table
            DB::table('rights_holder')->insert(['id' => $objectId]);

            return $objectId;
        });

        $this->autoSlug($objectId, $i18n, 'authorized_form_of_name');

        return $objectId;
    }

    public function updateRightsHolder(int $id, array $data, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);
        $this->updateEntity($id, 'actor', $core, 'actor_i18n', $i18n, $culture);
    }

    public function deleteRightsHolder(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('rights_holder')->where('id', $id)->delete();
            DB::table('actor_i18n')->where('id', $id)->delete();
            DB::table('actor')->where('id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function newRightsHolder(): object
    {
        $rh = new \stdClass();
        $rh->id = null;
        $rh->authorizedFormOfName = null;
        $rh->parentId = \QubitActor::ROOT_ID;

        return $rh;
    }
}
