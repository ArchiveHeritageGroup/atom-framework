<?php

namespace App\Services\Rights;

use Illuminate\Database\Capsule\Manager as DB;

class ExtendedRightsService
{
    protected $culture;

    public function __construct($culture = 'en')
    {
        $this->culture = $culture;
    }

    public function getRightsStatements()
    {
        return DB::table('rights_statement')
            ->leftJoin('rights_statement_i18n', function ($join) {
                $join->on(DB::raw('rights_statement_i18n.rights_statement_id'), '=', DB::raw('rights_statement.id'))
                     ->where(DB::raw('rights_statement_i18n.culture'), '=', $this->culture);
            })
            ->where(DB::raw('rights_statement.is_active'), '=', 1)
            ->orderBy(DB::raw('rights_statement.category'))
            ->orderBy(DB::raw('rights_statement.sort_order'))
            ->select([
                DB::raw('rights_statement.id'),
                DB::raw('rights_statement.code'),
                DB::raw('rights_statement.uri'),
                DB::raw('rights_statement.category'),
                DB::raw('rights_statement.icon_url'),
                DB::raw('rights_statement_i18n.name'),
                DB::raw('rights_statement_i18n.definition as description')
            ])
            ->get();
    }

    public function getCreativeCommonsLicenses()
    {
        return DB::table('creative_commons_license')
            ->leftJoin('creative_commons_license_i18n', function ($join) {
                $join->on(DB::raw('creative_commons_license_i18n.creative_commons_license_id'), '=', DB::raw('creative_commons_license.id'))
                     ->where(DB::raw('creative_commons_license_i18n.culture'), '=', $this->culture);
            })
            ->where(DB::raw('creative_commons_license.is_active'), '=', 1)
            ->orderBy(DB::raw('creative_commons_license.sort_order'))
            ->select([
                DB::raw('creative_commons_license.id'),
                DB::raw('creative_commons_license.code'),
                DB::raw('creative_commons_license.uri'),
                DB::raw('creative_commons_license.icon_url'),
                DB::raw('creative_commons_license_i18n.name'),
                DB::raw('creative_commons_license_i18n.description')
            ])
            ->get();
    }

    public function getTkLabels()
    {
        return DB::table('tk_label')
            ->leftJoin('tk_label_i18n', function ($join) {
                $join->on(DB::raw('tk_label_i18n.tk_label_id'), '=', DB::raw('tk_label.id'))
                     ->where(DB::raw('tk_label_i18n.culture'), '=', $this->culture);
            })
            ->leftJoin('tk_label_category', DB::raw('tk_label_category.id'), '=', DB::raw('tk_label.tk_label_category_id'))
            ->leftJoin('tk_label_category_i18n', function ($join) {
                $join->on(DB::raw('tk_label_category_i18n.tk_label_category_id'), '=', DB::raw('tk_label_category.id'))
                     ->where(DB::raw('tk_label_category_i18n.culture'), '=', $this->culture);
            })
            ->where(DB::raw('tk_label.is_active'), '=', 1)
            ->orderBy(DB::raw('tk_label_category.sort_order'))
            ->orderBy(DB::raw('tk_label.sort_order'))
            ->select([
                DB::raw('tk_label.id'),
                DB::raw('tk_label.code'),
                DB::raw('tk_label.uri'),
                DB::raw('tk_label.icon_url'),
                DB::raw('tk_label_category.code as category_code'),
                DB::raw('tk_label_category_i18n.name as category_name'),
                DB::raw('tk_label_i18n.name'),
                DB::raw('tk_label_i18n.description')
            ])
            ->get();
    }

    public function getTopLevelRecords($limit = 500)
    {
        return DB::table('information_object')
            ->join('slug', DB::raw('slug.object_id'), '=', DB::raw('information_object.id'))
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on(DB::raw('information_object_i18n.id'), '=', DB::raw('information_object.id'))
                     ->where(DB::raw('information_object_i18n.culture'), '=', $this->culture);
            })
            ->leftJoin('term_i18n', function ($join) {
                $join->on(DB::raw('term_i18n.id'), '=', DB::raw('information_object.level_of_description_id'))
                     ->where(DB::raw('term_i18n.culture'), '=', $this->culture);
            })
            ->where(DB::raw('information_object.parent_id'), '=', 1)
            ->whereNotNull(DB::raw('information_object_i18n.title'))
            ->orderBy(DB::raw('information_object_i18n.title'), 'asc')
            ->limit($limit)
            ->select([
                DB::raw('information_object.id'),
                DB::raw('slug.slug'),
                DB::raw('information_object.identifier'),
                DB::raw('information_object_i18n.title'),
                DB::raw('term_i18n.name as level')
            ])
            ->get();
    }

    public function getActiveEmbargoes()
    {
        return DB::table('embargo')
            ->join('slug', DB::raw('slug.object_id'), '=', DB::raw('embargo.object_id'))
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on(DB::raw('information_object_i18n.id'), '=', DB::raw('embargo.object_id'))
                     ->where(DB::raw('information_object_i18n.culture'), '=', $this->culture);
            })
            ->where(DB::raw('embargo.is_active'), '=', 1)
            ->orderBy(DB::raw('embargo.end_date'), 'asc')
            ->select([
                DB::raw('embargo.id'),
                DB::raw('embargo.object_id'),
                DB::raw('embargo.embargo_type'),
                DB::raw('embargo.start_date'),
                DB::raw('embargo.end_date'),
                DB::raw('information_object_i18n.title'),
                DB::raw('slug.slug')
            ])
            ->get();
    }

    public function getRightsStatistics()
    {
        return (object) [
            'total_objects' => DB::table('information_object')->count(),
            'with_rights_statement' => DB::table('object_rights_statement')->distinct()->count('object_id'),
            'with_creative_commons' => DB::table('object_creative_commons')->distinct()->count('object_id'),
            'with_tk_labels' => DB::table('object_tk_label')->distinct()->count('object_id'),
            'active_embargoes' => DB::table('embargo')->where('is_active', '=', 1)->count(),
            'expiring_soon' => DB::table('embargo')
                ->where('is_active', '=', 1)
                ->where('end_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->where('end_date', '>=', date('Y-m-d'))
                ->count(),
        ];
    }

    public function assignRightsStatement($objectId, $rightsStatementId, $userId = null)
    {
        DB::table('object_rights_statement')->where('object_id', '=', $objectId)->delete();
        return DB::table('object_rights_statement')->insert([
            'object_id' => $objectId,
            'rights_statement_id' => $rightsStatementId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function assignCreativeCommons($objectId, $licenseId, $userId = null)
    {
        DB::table('object_creative_commons')->where('object_id', '=', $objectId)->delete();
        return DB::table('object_creative_commons')->insert([
            'object_id' => $objectId,
            'creative_commons_license_id' => $licenseId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function assignTkLabel($objectId, $tkLabelId, $userId = null)
    {
        $exists = DB::table('object_tk_label')
            ->where('object_id', '=', $objectId)
            ->where('tk_label_id', '=', $tkLabelId)
            ->exists();

        if (!$exists) {
            return DB::table('object_tk_label')->insert([
                'object_id' => $objectId,
                'tk_label_id' => $tkLabelId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

    public function clearRights($objectId, $type = null)
    {
        if ($type === null || $type === 'rights_statement') {
            DB::table('object_rights_statement')->where('object_id', '=', $objectId)->delete();
        }
        if ($type === null || $type === 'creative_commons') {
            DB::table('object_creative_commons')->where('object_id', '=', $objectId)->delete();
        }
        if ($type === null || $type === 'tk_label') {
            DB::table('object_tk_label')->where('object_id', '=', $objectId)->delete();
        }
        return true;
    }

    public function createEmbargo($objectId, $embargoType, $startDate, $endDate = null)
    {
        return DB::table('embargo')->insert([
            'object_id' => $objectId,
            'embargo_type' => $embargoType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function liftEmbargo($embargoId)
    {
        return DB::table('embargo')
            ->where('id', '=', $embargoId)
            ->update([
                'is_active' => 0,
                'lifted_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function getDonors($limit = 500)
    {
        // Donors are in the donor table, which extends actor
        return DB::table('donor')
            ->join('slug', DB::raw('slug.object_id'), '=', DB::raw('donor.id'))
            ->leftJoin('actor_i18n', function ($join) {
                $join->on(DB::raw('actor_i18n.id'), '=', DB::raw('donor.id'))
                     ->where(DB::raw('actor_i18n.culture'), '=', $this->culture);
            })
            ->whereNotNull(DB::raw('actor_i18n.authorized_form_of_name'))
            ->orderBy(DB::raw('actor_i18n.authorized_form_of_name'), 'asc')
            ->limit($limit)
            ->select([
                DB::raw('donor.id'),
                DB::raw('slug.slug'),
                DB::raw('actor_i18n.authorized_form_of_name as name')
            ])
            ->get();
    }

    public function assignRightsHolder($objectId, $donorId)
    {
        DB::table('object_rights_holder')->where('object_id', '=', $objectId)->delete();
        return DB::table('object_rights_holder')->insert([
            'object_id' => $objectId,
            'donor_id' => $donorId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

}