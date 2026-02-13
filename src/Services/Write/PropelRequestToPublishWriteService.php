<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Request-to-publish write operations via QubitRequestToPublish.
 *
 * Uses Propel (QubitRequestToPublish) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> request_to_publish -> request_to_publish_i18n
 */
class PropelRequestToPublishWriteService implements RequestToPublishWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitRequestToPublish', false)
            || class_exists('QubitRequestToPublish');
    }

    public function createRequest(array $data, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateRequest($data, $culture);
        }

        return $this->dbCreateRequest($data, $culture);
    }

    public function updateRequest(int $id, array $data): void
    {
        if ($this->hasPropel) {
            $this->propelUpdateRequest($id, $data);

            return;
        }

        $this->dbUpdateRequest($id, $data);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateRequest(array $data, string $culture): int
    {
        $rtp = new \QubitRequestToPublish();

        // Direct columns on request_to_publish table
        if (isset($data['parent_id'])) {
            $rtp->parent_id = $data['parent_id'];
        }

        // i18n columns (request_to_publish_i18n table)
        if (isset($data['rtp_name'])) {
            $rtp->rtp_name = $data['rtp_name'];
        }
        if (isset($data['rtp_surname'])) {
            $rtp->rtp_surname = $data['rtp_surname'];
        }
        if (isset($data['rtp_phone'])) {
            $rtp->rtp_phone = $data['rtp_phone'];
        }
        if (isset($data['rtp_email'])) {
            $rtp->rtp_email = $data['rtp_email'];
        }
        if (isset($data['rtp_institution'])) {
            $rtp->rtp_institution = $data['rtp_institution'];
        }
        if (isset($data['rtp_motivation'])) {
            $rtp->rtp_motivation = $data['rtp_motivation'];
        }
        if (isset($data['rtp_planned_use'])) {
            $rtp->rtp_planned_use = $data['rtp_planned_use'];
        }
        if (isset($data['rtp_need_image_by'])) {
            $rtp->rtp_need_image_by = $data['rtp_need_image_by'];
        }
        if (isset($data['unique_identifier'])) {
            $rtp->unique_identifier = $data['unique_identifier'];
        }
        if (isset($data['object_id'])) {
            $rtp->object_id = $data['object_id'];
        }
        if (isset($data['status_id'])) {
            $rtp->statusId = $data['status_id'];
        }

        $rtp->createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        $rtp->sourceCulture = $culture;

        $rtp->save();

        return $rtp->id;
    }

    private function propelUpdateRequest(int $id, array $data): void
    {
        $rtp = \QubitRequestToPublish::getById($id);
        if (null === $rtp) {
            return;
        }

        // Direct columns on request_to_publish table
        if (array_key_exists('parent_id', $data)) {
            $rtp->parent_id = $data['parent_id'];
        }

        // i18n columns
        $i18nFields = [
            'rtp_name', 'rtp_surname', 'rtp_phone', 'rtp_email',
            'rtp_institution', 'rtp_motivation', 'rtp_planned_use',
            'rtp_need_image_by', 'unique_identifier', 'object_id',
        ];

        foreach ($i18nFields as $field) {
            if (array_key_exists($field, $data)) {
                $rtp->{$field} = $data[$field];
            }
        }

        if (array_key_exists('status_id', $data)) {
            $rtp->statusId = $data['status_id'];
        }
        if (array_key_exists('completed_at', $data)) {
            $rtp->completedAt = $data['completed_at'];
        }

        $rtp->save();
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateRequest(array $data, string $culture): int
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');

        // 1. Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRequestToPublish',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Insert into request_to_publish table
        DB::table('request_to_publish')->insert([
            'id' => $objectId,
            'parent_id' => $data['parent_id'] ?? null,
            'rtp_type_id' => $data['rtp_type_id'] ?? null,
            'lft' => 0,
            'rgt' => 0,
            'source_culture' => $culture,
        ]);

        // 3. Insert into request_to_publish_i18n table
        DB::table('request_to_publish_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'rtp_name' => $data['rtp_name'] ?? null,
            'rtp_surname' => $data['rtp_surname'] ?? null,
            'rtp_phone' => $data['rtp_phone'] ?? null,
            'rtp_email' => $data['rtp_email'] ?? null,
            'rtp_institution' => $data['rtp_institution'] ?? null,
            'rtp_motivation' => $data['rtp_motivation'] ?? null,
            'rtp_planned_use' => $data['rtp_planned_use'] ?? null,
            'rtp_need_image_by' => $data['rtp_need_image_by'] ?? null,
            'unique_identifier' => $data['unique_identifier'] ?? null,
            'object_id' => isset($data['object_id']) ? (string) $data['object_id'] : null,
            'status_id' => $data['status_id'] ?? 0,
            'created_at' => $now,
        ]);

        return $objectId;
    }

    private function dbUpdateRequest(int $id, array $data): void
    {
        // Direct columns on request_to_publish table
        $directUpdates = [];
        if (array_key_exists('parent_id', $data)) {
            $directUpdates['parent_id'] = $data['parent_id'];
        }
        if (!empty($directUpdates)) {
            DB::table('request_to_publish')->where('id', $id)->update($directUpdates);
        }

        // i18n columns on request_to_publish_i18n table
        $i18nUpdates = [];
        $i18nFields = [
            'rtp_name', 'rtp_surname', 'rtp_phone', 'rtp_email',
            'rtp_institution', 'rtp_motivation', 'rtp_planned_use',
            'rtp_need_image_by', 'unique_identifier', 'object_id',
            'status_id', 'completed_at',
        ];

        foreach ($i18nFields as $field) {
            if (array_key_exists($field, $data)) {
                $i18nUpdates[$field] = $data[$field];
            }
        }

        if (!empty($i18nUpdates)) {
            $culture = $data['culture'] ?? 'en';
            DB::table('request_to_publish_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        // Update object timestamp
        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
