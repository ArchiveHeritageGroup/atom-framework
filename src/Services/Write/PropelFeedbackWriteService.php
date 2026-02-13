<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Feedback write operations via QubitFeedback.
 *
 * Uses Propel (QubitFeedback) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> feedback -> feedback_i18n
 */
class PropelFeedbackWriteService implements FeedbackWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitFeedback', false)
            || class_exists('QubitFeedback');
    }

    public function createFeedback(array $data, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateFeedback($data, $culture);
        }

        return $this->dbCreateFeedback($data, $culture);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateFeedback(array $data, string $culture): int
    {
        $feedback = new \QubitFeedback();

        // Direct columns on feedback table
        if (isset($data['feed_name'])) {
            $feedback->feed_name = $data['feed_name'];
        }
        if (isset($data['feed_surname'])) {
            $feedback->feed_surname = $data['feed_surname'];
        }
        if (isset($data['feed_phone'])) {
            $feedback->feed_phone = $data['feed_phone'];
        }
        if (isset($data['feed_email'])) {
            $feedback->feed_email = $data['feed_email'];
        }
        if (isset($data['feed_relationship'])) {
            $feedback->feed_relationship = $data['feed_relationship'];
        }
        if (isset($data['feed_type_id'])) {
            $feedback->feedTypeId = $data['feed_type_id'];
        }
        if (isset($data['parent_id'])) {
            $feedback->parent_id = $data['parent_id'];
        }

        // i18n columns (feedback_i18n table)
        if (isset($data['name'])) {
            $feedback->name = $data['name'];
        }
        if (isset($data['remarks'])) {
            $feedback->remarks = $data['remarks'];
        }
        if (isset($data['object_id'])) {
            $feedback->object_id = $data['object_id'];
        }
        if (isset($data['status_id'])) {
            $feedback->statusId = $data['status_id'];
        }

        $feedback->createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        $feedback->sourceCulture = $culture;

        $feedback->save();

        return $feedback->id;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateFeedback(array $data, string $culture): int
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');

        // 1. Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitFeedback',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Insert into feedback table
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

        // 3. Insert into feedback_i18n table
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
    }
}
