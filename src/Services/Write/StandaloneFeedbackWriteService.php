<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone feedback write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> feedback -> feedback_i18n
 */
class StandaloneFeedbackWriteService implements FeedbackWriteServiceInterface
{
    public function createFeedback(array $data, string $culture = 'en'): int
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');

        return DB::transaction(function () use ($data, $culture, $now) {
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitFeedback',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('feedback')->insert([
                'id' => $objectId,
                'feed_name' => $data['feed_name'] ?? null,
                'feed_surname' => $data['feed_surname'] ?? null,
                'feed_phone' => $data['feed_phone'] ?? null,
                'feed_email' => $data['feed_email'] ?? null,
                'feed_relationship' => $data['feed_relationship'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'feed_type_id' => $data['feed_type_id'] ?? null,
                'lft' => 0,
                'rgt' => 0,
                'source_culture' => $culture,
            ]);

            DB::table('feedback_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'name' => $data['name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'object_id' => isset($data['object_id']) ? (string) $data['object_id'] : null,
                'status_id' => $data['status_id'] ?? 0,
                'created_at' => $now,
            ]);

            return $objectId;
        });
    }
}
