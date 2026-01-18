<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Support\Collection;

/**
 * Extended Rights Service
 * Handles RightsStatements.org, Creative Commons, TK Labels, and Embargoes
 */
class ExtendedRightsService
{
    protected DB $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // RIGHTS STATEMENTS
    // =========================================================================

    public function getRightsStatements(bool $activeOnly = true): Collection
    {
        $query = $this->db->table('rights_statement as rs')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rsi.rights_statement_id', '=', 'rs.id')
                    ->where('rsi.culture', '=', CultureHelper::getCulture());
            })
            ->orderBy('rs.category')
            ->orderBy('rs.sort_order')
            ->select([
                'rs.id',
                'rs.code',
                'rs.uri',
                'rs.category',
                'rs.icon_url',
                'rs.icon_filename',
                'rs.is_active',
                'rsi.name',
                'rsi.description',
                'rsi.definition',
            ]);

        if ($activeOnly) {
            $query->where('rs.is_active', 1);
        }

        return $query->get();
    }

    public function getRightsStatementByCode(string $code): ?object
    {
        return $this->db->table('rights_statement as rs')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rsi.rights_statement_id', '=', 'rs.id')
                    ->where('rsi.culture', '=', CultureHelper::getCulture());
            })
            ->where('rs.code', $code)
            ->select([
                'rs.id',
                'rs.code',
                'rs.uri',
                'rs.category',
                'rs.icon_url',
                'rs.icon_filename',
                'rsi.name',
                'rsi.description',
            ])
            ->first();
    }

    // =========================================================================
    // CREATIVE COMMONS LICENSES
    // =========================================================================

    public function getCreativeCommonsLicenses(bool $activeOnly = true): Collection
    {
        $query = $this->db->table('creative_commons_license as ccl')
            ->leftJoin('creative_commons_license_i18n as ccli', function ($join) {
                $join->on('ccli.creative_commons_license_id', '=', 'ccl.id')
                    ->where('ccli.culture', '=', CultureHelper::getCulture());
            })
            ->orderBy('ccl.sort_order')
            ->select([
                'ccl.id',
                'ccl.code',
                'ccl.uri',
                'ccl.version',
                'ccl.allows_adaptation',
                'ccl.allows_commercial',
                'ccl.requires_attribution',
                'ccl.requires_sharealike',
                'ccl.icon_url',
                'ccl.icon_filename',
                'ccl.is_active',
                'ccli.name',
                'ccli.description',
            ]);

        if ($activeOnly) {
            $query->where('ccl.is_active', 1);
        }

        return $query->get();
    }

    public function getCreativeCommonsLicenseByCode(string $code): ?object
    {
        return $this->db->table('creative_commons_license as ccl')
            ->leftJoin('creative_commons_license_i18n as ccli', function ($join) {
                $join->on('ccli.creative_commons_license_id', '=', 'ccl.id')
                    ->where('ccli.culture', '=', CultureHelper::getCulture());
            })
            ->where('ccl.code', $code)
            ->select([
                'ccl.id',
                'ccl.code',
                'ccl.uri',
                'ccl.version',
                'ccl.icon_url',
                'ccli.name',
                'ccli.description',
            ])
            ->first();
    }

    // =========================================================================
    // TK LABELS (Traditional Knowledge)
    // =========================================================================

    public function getTkLabelCategories(): Collection
    {
        return $this->db->table('tk_label_category as cat')
            ->leftJoin('tk_label_category_i18n as cati', function ($join) {
                $join->on('cati.tk_label_category_id', '=', 'cat.id')
                    ->where('cati.culture', '=', CultureHelper::getCulture());
            })
            ->orderBy('cat.sort_order')
            ->select([
                'cat.id',
                'cat.code',
                'cat.color',
                'cati.name',
                'cati.description',
            ])
            ->get();
    }

    public function getTkLabels(bool $activeOnly = true): Collection
    {
        $query = $this->db->table('tk_label as tk')
            ->leftJoin('tk_label_i18n as tki', function ($join) {
                $join->on('tki.tk_label_id', '=', 'tk.id')
                    ->where('tki.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('tk_label_category as cat', 'cat.id', '=', 'tk.tk_label_category_id')
            ->leftJoin('tk_label_category_i18n as cati', function ($join) {
                $join->on('cati.tk_label_category_id', '=', 'cat.id')
                    ->where('cati.culture', '=', CultureHelper::getCulture());
            })
            ->orderBy('cat.sort_order')
            ->orderBy('tk.sort_order')
            ->select([
                'tk.id',
                'tk.code',
                'tk.uri',
                'tk.icon_url',
                'tk.icon_filename',
                'tk.is_active',
                'cat.code as category_code',
                'cat.color as category_color',
                'cati.name as category_name',
                'tki.name',
                'tki.description',
                'tki.usage_guide',
            ]);

        if ($activeOnly) {
            $query->where('tk.is_active', 1);
        }

        return $query->get();
    }

    public function getTkLabelByCode(string $code): ?object
    {
        return $this->db->table('tk_label as tk')
            ->leftJoin('tk_label_i18n as tki', function ($join) {
                $join->on('tki.tk_label_id', '=', 'tk.id')
                    ->where('tki.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('tk_label_category as cat', 'cat.id', '=', 'tk.tk_label_category_id')
            ->leftJoin('tk_label_category_i18n as cati', function ($join) {
                $join->on('cati.tk_label_category_id', '=', 'cat.id')
                    ->where('cati.culture', '=', CultureHelper::getCulture());
            })
            ->where('tk.code', $code)
            ->select([
                'tk.id',
                'tk.code',
                'tk.uri',
                'tk.icon_url',
                'tk.icon_filename',
                'cat.code as category_code',
                'cati.name as category_name',
                'tki.name',
                'tki.description',
                'tki.usage_guide',
            ])
            ->first();
    }

    public function getTkLabelsByCategory(int $categoryId): Collection
    {
        return $this->db->table('tk_label as tk')
            ->leftJoin('tk_label_i18n as tki', function ($join) {
                $join->on('tki.tk_label_id', '=', 'tk.id')
                    ->where('tki.culture', '=', CultureHelper::getCulture());
            })
            ->where('tk.tk_label_category_id', $categoryId)
            ->where('tk.is_active', 1)
            ->orderBy('tk.sort_order')
            ->select([
                'tk.id',
                'tk.code',
                'tk.uri',
                'tk.icon_url',
                'tk.icon_filename',
                'tki.name',
                'tki.description',
            ])
            ->get();
    }

    // =========================================================================
    // EMBARGO MANAGEMENT
    // =========================================================================

    public function getActiveEmbargoes(): Collection
    {
        return $this->db->table('embargo as e')
            ->join('object as o', 'e.object_id', '=', 'o.id')
            ->join('slug as s', function ($join) {
                $join->on('s.object_id', '=', 'o.id');
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'o.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->where('e.is_active', 1)
            ->where(function ($query) {
                $query->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>', now());
            })
            ->orderBy('e.end_date')
            ->select([
                'e.id',
                'e.object_id',
                'e.embargo_type',
                'e.start_date',
                'e.end_date',
                'e.reason',
                'e.created_at',
                'ioi.title as object_title',
                's.slug',
            ])
            ->get();
    }

    public function getEmbargoForObject(int $objectId): ?object
    {
        return $this->db->table('embargo')
            ->where('object_id', $objectId)
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->first();
    }

    public function createEmbargo(array $data): int
    {
        return $this->db->table('embargo')->insertGetId([
            'object_id' => $data['object_id'],
            'object_type' => $data['object_type'] ?? 'informationobject',
            'embargo_type' => $data['embargo_type'] ?? 'full',
            'start_date' => $data['start_date'] ?? now(),
            'end_date' => $data['end_date'] ?? null,
            'reason' => $data['reason'] ?? null,
            'created_by_user_id' => $data['user_id'] ?? null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function liftEmbargo(int $embargoId, ?int $userId = null): bool
    {
        return $this->db->table('embargo')
            ->where('id', $embargoId)
            ->update([
                'is_active' => 0,
                'lifted_by_user_id' => $userId,
                'lifted_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function extendEmbargo(int $embargoId, string $newEndDate, ?string $reason = null): bool
    {
        $updateData = [
            'end_date' => $newEndDate,
            'updated_at' => now(),
        ];

        if ($reason) {
            $updateData['reason'] = $reason;
        }

        return $this->db->table('embargo')
            ->where('id', $embargoId)
            ->update($updateData) > 0;
    }

    // =========================================================================
    // OBJECT RIGHTS ASSIGNMENTS
    // =========================================================================

    public function getObjectRights(int $objectId): object
    {
        $rightsStatement = $this->db->table('object_rights_statement as ors')
            ->join('rights_statement as rs', 'rs.id', '=', 'ors.rights_statement_id')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rsi.rights_statement_id', '=', 'rs.id')
                    ->where('rsi.culture', '=', CultureHelper::getCulture());
            })
            ->where('ors.object_id', $objectId)
            ->select([
                'rs.id',
                'rs.code',
                'rs.uri',
                'rs.icon_url',
                'rsi.name',
                'rsi.description',
                'ors.notes',
            ])
            ->first();

        $ccLicense = $this->db->table('object_creative_commons as occ')
            ->join('creative_commons_license as ccl', 'ccl.id', '=', 'occ.creative_commons_license_id')
            ->leftJoin('creative_commons_license_i18n as ccli', function ($join) {
                $join->on('ccli.creative_commons_license_id', '=', 'ccl.id')
                    ->where('ccli.culture', '=', CultureHelper::getCulture());
            })
            ->where('occ.object_id', $objectId)
            ->select([
                'ccl.id',
                'ccl.code',
                'ccl.uri',
                'ccl.icon_url',
                'ccli.name',
                'ccli.description',
                'occ.notes',
            ])
            ->first();

        $tkLabels = $this->db->table('object_tk_label as otl')
            ->join('tk_label as tk', 'tk.id', '=', 'otl.tk_label_id')
            ->leftJoin('tk_label_i18n as tki', function ($join) {
                $join->on('tki.tk_label_id', '=', 'tk.id')
                    ->where('tki.culture', '=', CultureHelper::getCulture());
            })
            ->where('otl.object_id', $objectId)
            ->select([
                'tk.id',
                'tk.code',
                'tk.uri',
                'tk.icon_url',
                'tki.name',
                'tki.description',
                'otl.community_name',
                'otl.community_contact',
                'otl.custom_text',
            ])
            ->get();

        $embargo = $this->getEmbargoForObject($objectId);

        return (object) [
            'rights_statement' => $rightsStatement,
            'creative_commons' => $ccLicense,
            'tk_labels' => $tkLabels,
            'embargo' => $embargo,
        ];
    }

    public function assignRightsStatement(int $objectId, int $rightsStatementId, ?string $notes = null): bool
    {
        // Remove existing
        $this->db->table('object_rights_statement')
            ->where('object_id', $objectId)
            ->delete();

        // Insert new
        return $this->db->table('object_rights_statement')->insert([
            'object_id' => $objectId,
            'rights_statement_id' => $rightsStatementId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function assignCreativeCommons(int $objectId, int $licenseId, ?string $notes = null): bool
    {
        // Remove existing
        $this->db->table('object_creative_commons')
            ->where('object_id', $objectId)
            ->delete();

        // Insert new
        return $this->db->table('object_creative_commons')->insert([
            'object_id' => $objectId,
            'creative_commons_license_id' => $licenseId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function assignTkLabel(int $objectId, int $labelId, array $data = []): bool
    {
        // Check if already assigned
        $exists = $this->db->table('object_tk_label')
            ->where('object_id', $objectId)
            ->where('tk_label_id', $labelId)
            ->exists();

        if ($exists) {
            return $this->db->table('object_tk_label')
                ->where('object_id', $objectId)
                ->where('tk_label_id', $labelId)
                ->update([
                    'community_name' => $data['community_name'] ?? null,
                    'community_contact' => $data['community_contact'] ?? null,
                    'custom_text' => $data['custom_text'] ?? null,
                    'updated_at' => now(),
                ]) > 0;
        }

        return $this->db->table('object_tk_label')->insert([
            'object_id' => $objectId,
            'tk_label_id' => $labelId,
            'community_name' => $data['community_name'] ?? null,
            'community_contact' => $data['community_contact'] ?? null,
            'custom_text' => $data['custom_text'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function removeTkLabel(int $objectId, int $labelId): bool
    {
        return $this->db->table('object_tk_label')
            ->where('object_id', $objectId)
            ->where('tk_label_id', $labelId)
            ->delete() > 0;
    }

    // =========================================================================
    // BATCH OPERATIONS
    // =========================================================================

    public function batchAssignRights(array $objectIds, string $type, int $valueId, ?string $notes = null): int
    {
        $count = 0;

        foreach ($objectIds as $objectId) {
            switch ($type) {
                case 'rights_statement':
                    if ($this->assignRightsStatement($objectId, $valueId, $notes)) {
                        ++$count;
                    }
                    break;
                case 'creative_commons':
                    if ($this->assignCreativeCommons($objectId, $valueId, $notes)) {
                        ++$count;
                    }
                    break;
                case 'tk_label':
                    if ($this->assignTkLabel($objectId, $valueId)) {
                        ++$count;
                    }
                    break;
            }
        }

        return $count;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    public function getRightsStatistics(): object
    {
        $totalObjects = $this->db->table('information_object')->count();

        $withRightsStatement = $this->db->table('object_rights_statement')
            ->distinct('object_id')
            ->count('object_id');

        $withCreativeCommons = $this->db->table('object_creative_commons')
            ->distinct('object_id')
            ->count('object_id');

        $withTkLabels = $this->db->table('object_tk_label')
            ->distinct('object_id')
            ->count('object_id');

        $activeEmbargoes = $this->db->table('embargo')
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->count();

        return (object) [
            'total_objects' => $totalObjects,
            'with_rights_statement' => $withRightsStatement,
            'with_creative_commons' => $withCreativeCommons,
            'with_tk_labels' => $withTkLabels,
            'active_embargoes' => $activeEmbargoes,
            'without_rights' => $totalObjects - max($withRightsStatement, $withCreativeCommons),
        ];
    }
}
