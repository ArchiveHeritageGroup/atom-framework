<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone request-to-publish write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> request_to_publish -> request_to_publish_i18n
 */
class StandaloneRequestToPublishWriteService implements RequestToPublishWriteServiceInterface
{
    public function createRequest(array $data, string $culture = 'en'): int
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');

        return DB::transaction(function () use ($data, $culture, $now) {
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitRequestToPublish',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('request_to_publish')->insert([
                'id' => $objectId,
                'parent_id' => $data['parent_id'] ?? null,
                'rtp_type_id' => $data['rtp_type_id'] ?? null,
                'lft' => 0,
                'rgt' => 0,
                'source_culture' => $culture,
            ]);

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
        });
    }

    public function updateRequest(int $id, array $data): void
    {
        $directUpdates = [];
        if (array_key_exists('parent_id', $data)) {
            $directUpdates['parent_id'] = $data['parent_id'];
        }
        if (!empty($directUpdates)) {
            DB::table('request_to_publish')->where('id', $id)->update($directUpdates);
        }

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

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
