<?php

namespace App\Services\Rights;

use Illuminate\Database\Capsule\Manager as DB;

class ExtendedRightsBatchService
{
    protected $culture;

    public function __construct($culture = 'en')
    {
        $this->culture = $culture;
    }

    public function batchAssignRights(array $objectIds, array $rightsData, $userId)
    {
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        foreach ($objectIds as $objectId) {
            try {
                $existing = DB::table('extended_rights')->where('object_id', $objectId)->first();

                if ($existing && !($rightsData['overwrite'] ?? false)) {
                    $results['skipped'][] = ['id' => $objectId, 'reason' => 'Exists'];
                    continue;
                }

                if ($existing) {
                    DB::table('extended_rights_tk_label')->where('extended_rights_id', $existing->id)->delete();
                    DB::table('extended_rights_i18n')->where('extended_rights_id', $existing->id)->delete();
                    DB::table('extended_rights')->where('id', $existing->id)->delete();
                }

                $rightsId = DB::table('extended_rights')->insertGetId([
                    'object_id'           => $objectId,
                    'rights_statement_id' => $rightsData['rights_statement_id'] ?? null,
                    'creative_commons_id' => $rightsData['creative_commons_id'] ?? null,
                    'rights_holder'       => $rightsData['rights_holder'] ?? null,
                    'rights_holder_uri'   => $rightsData['rights_holder_uri'] ?? null,
                    'copyright_status'    => $rightsData['copyright_status'] ?? null,
                    'created_by_user_id'  => $userId,
                    'created_at'          => date('Y-m-d H:i:s'),
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);

                if (!empty($rightsData['i18n'])) {
                    foreach ($rightsData['i18n'] as $culture => $i18nData) {
                        DB::table('extended_rights_i18n')->insert([
                            'extended_rights_id' => $rightsId,
                            'culture'            => $culture,
                            'copyright_notice'   => $i18nData['copyright_notice'] ?? null,
                            'rights_note'        => $i18nData['rights_note'] ?? null,
                        ]);
                    }
                }

                if (!empty($rightsData['tk_label_ids'])) {
                    foreach ($rightsData['tk_label_ids'] as $tkId) {
                        DB::table('extended_rights_tk_label')->insert([
                            'extended_rights_id' => $rightsId,
                            'tk_label_id'        => $tkId,
                        ]);
                    }
                }

                $results['success'][] = $objectId;

            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $objectId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function batchCreateEmbargo(array $objectIds, array $embargoData, $userId)
    {
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        foreach ($objectIds as $objectId) {
            try {
                $existing = DB::table('embargo')
                    ->where('object_id', $objectId)
                    ->where('is_active', 1)
                    ->first();

                if ($existing && !($embargoData['overwrite'] ?? false)) {
                    $results['skipped'][] = ['id' => $objectId, 'reason' => 'Active embargo exists'];
                    continue;
                }

                if ($existing) {
                    DB::table('embargo')->where('id', $existing->id)->update([
                        'is_active' => 0,
                        'status' => 'lifted',
                        'lifted_at' => date('Y-m-d H:i:s'),
                        'lifted_by' => $userId,
                    ]);
                }

                DB::table('embargo')->insert([
                    'object_id'          => $objectId,
                    'embargo_type'       => $embargoData['embargo_type'] ?? 'full',
                    'start_date'         => $embargoData['start_date'] ?? date('Y-m-d'),
                    'end_date'           => $embargoData['end_date'] ?? null,
                    'is_perpetual'       => $embargoData['is_perpetual'] ?? false,
                    'status'             => 'active',
                    'is_active'          => 1,
                    'created_by'         => $userId,
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ]);

                $results['success'][] = $objectId;

            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $objectId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function batchClearRights(array $objectIds, $userId)
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($objectIds as $objectId) {
            try {
                $rights = DB::table('extended_rights')->where('object_id', $objectId)->first();
                
                if ($rights) {
                    DB::table('extended_rights_tk_label')->where('extended_rights_id', $rights->id)->delete();
                    DB::table('extended_rights_i18n')->where('extended_rights_id', $rights->id)->delete();
                    DB::table('extended_rights')->where('id', $rights->id)->delete();
                    $results['success'][] = $objectId;
                } else {
                    $results['failed'][] = ['id' => $objectId, 'error' => 'No rights found'];
                }
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $objectId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function batchLiftEmbargo(array $objectIds, $reason, $userId)
    {
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        foreach ($objectIds as $objectId) {
            try {
                $embargo = DB::table('embargo')
                    ->where('object_id', $objectId)
                    ->where('is_active', 1)
                    ->first();

                if (!$embargo) {
                    $results['skipped'][] = ['id' => $objectId, 'reason' => 'No active embargo'];
                    continue;
                }

                DB::table('embargo')->where('id', $embargo->id)->update([
                    'is_active'   => 0,
                    'status'      => 'lifted',
                    'lifted_at'   => date('Y-m-d H:i:s'),
                    'lifted_by'   => $userId,
                    'lift_reason' => $reason,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);

                $results['success'][] = $objectId;

            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $objectId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
