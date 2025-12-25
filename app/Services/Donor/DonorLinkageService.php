<?php

namespace App\Services\Donor;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Rights\ExtendedRightsService;
use App\Services\Rights\EmbargoService;

class DonorLinkageService
{
    protected ExtendedRightsService $rightsService;

    public function __construct()
    {
        $this->rightsService = new ExtendedRightsService();
    }

    // =========================================================================
    // AGREEMENT RECORD LINKAGE (using donor_agreement_record)
    // =========================================================================

    /**
     * Link an agreement to an information object
     */
    public function linkAgreementToRecord(
        int $agreementId,
        int $informationObjectId,
        string $relationshipType = 'covers',
        ?string $notes = null
    ): int {
        return DB::table('donor_agreement_record')->insertGetId([
            'agreement_id' => $agreementId,
            'information_object_id' => $informationObjectId,
            'relationship_type' => $relationshipType,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Unlink an agreement from a record
     */
    public function unlinkAgreementFromRecord(int $linkId): bool
    {
        return DB::table('donor_agreement_record')->where('id', $linkId)->delete() > 0;
    }

    /**
     * Get all records linked to an agreement
     */
    public function getAgreementRecords(int $agreementId): Collection
    {
        return DB::table('donor_agreement_record as dar')
            ->join('information_object as io', 'io.id', '=', 'dar.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->where('dar.agreement_id', $agreementId)
            ->select([
                'dar.id as link_id',
                'dar.relationship_type',
                'dar.notes as link_notes',
                'dar.created_at as linked_at',
                'io.id as object_id',
                'io.slug',
                'io.identifier',
                'ioi.title'
            ])
            ->orderBy('ioi.title')
            ->get();
    }

    /**
     * Get all agreements linked to a record
     */
    public function getRecordAgreements(int $informationObjectId): Collection
    {
        return DB::table('donor_agreement_record as dar')
            ->join('donor_agreement as da', 'da.id', '=', 'dar.agreement_id')
            ->leftJoin('donor_agreement_i18n as dai', function ($join) {
                $join->on('dai.id', '=', 'da.id')
                    ->where('dai.culture', '=', 'en');
            })
            ->join('donor as d', 'd.id', '=', 'da.donor_id')
            ->leftJoin('donor_i18n as di', function ($join) {
                $join->on('di.donor_id', '=', 'd.id')
                    ->where('di.culture', '=', 'en');
            })
            ->where('dar.information_object_id', $informationObjectId)
            ->select([
                'dar.id as link_id',
                'dar.relationship_type',
                'dar.notes as link_notes',
                'da.id as agreement_id',
                'da.agreement_number',
                'da.agreement_date',
                'da.slug as agreement_slug',
                'dai.title as agreement_title',
                'd.id as donor_id',
                'd.slug as donor_slug',
                'di.authorized_form_of_name as donor_name'
            ])
            ->orderByDesc('da.agreement_date')
            ->get();
    }

    // =========================================================================
    // PROVENANCE TRACKING
    // =========================================================================

    /**
     * Create a provenance record
     */
    public function createProvenance(
        int $donorId,
        int $informationObjectId,
        ?int $agreementId = null,
        string $relationshipType = 'donated',
        ?int $userId = null
    ): int {
        return DB::table('donor_provenance')->insertGetId([
            'donor_id' => $donorId,
            'information_object_id' => $informationObjectId,
            'donor_agreement_id' => $agreementId,
            'relationship_type' => $relationshipType,
            'provenance_date' => now()->toDateString(),
            'is_current_owner' => false,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get provenance history for an information object
     */
    public function getProvenanceHistory(int $informationObjectId): Collection
    {
        return DB::table('donor_provenance as dp')
            ->join('donor as d', 'd.id', '=', 'dp.donor_id')
            ->leftJoin('donor_i18n as di', function ($join) {
                $join->on('di.donor_id', '=', 'd.id')
                    ->where('di.culture', '=', 'en');
            })
            ->leftJoin('donor_agreement as da', 'da.id', '=', 'dp.donor_agreement_id')
            ->where('dp.information_object_id', $informationObjectId)
            ->orderByDesc('dp.provenance_date')
            ->orderBy('dp.sequence_number')
            ->select([
                'dp.*',
                'd.slug as donor_slug',
                'di.authorized_form_of_name as donor_name',
                'da.agreement_number',
                'da.agreement_date',
                'da.slug as agreement_slug'
            ])
            ->get();
    }

    /**
     * Get all records from a donor
     */
    public function getRecordsByDonor(int $donorId): Collection
    {
        return DB::table('donor_provenance as dp')
            ->join('information_object as io', 'io.id', '=', 'dp.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->where('dp.donor_id', $donorId)
            ->orderByDesc('dp.provenance_date')
            ->select([
                'dp.*',
                'io.slug',
                'io.identifier',
                'ioi.title'
            ])
            ->get();
    }

    // =========================================================================
    // RIGHTS APPLICATION
    // =========================================================================

    /**
     * Get rights configured for an agreement
     */
    public function getAgreementRightsConfig(int $agreementId): Collection
    {
        return DB::table('donor_agreement_rights as dar')
            ->leftJoin('extended_rights as er', 'er.id', '=', 'dar.extended_rights_id')
            ->leftJoin('rights_statement as rs', 'rs.id', '=', 'er.rights_statement_id')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rsi.rights_statement_id', '=', 'rs.id')
                    ->where('rsi.culture', '=', 'en');
            })
            ->leftJoin('embargo as emb', 'emb.id', '=', 'dar.embargo_id')
            ->where('dar.donor_agreement_id', $agreementId)
            ->select([
                'dar.*',
                'rs.code as rs_code',
                'rsi.name as rs_name',
                'emb.embargo_type',
                'emb.start_date as embargo_start',
                'emb.end_date as embargo_end',
                'emb.is_perpetual'
            ])
            ->get();
    }

    /**
     * Apply agreement rights to a linked record
     */
    public function applyAgreementRightsToRecord(
        int $agreementId,
        int $informationObjectId,
        ?int $userId = null
    ): array {
        $results = ['rights_applied' => 0, 'embargoes_applied' => 0];
        
        $configs = $this->getAgreementRightsConfig($agreementId);
        
        foreach ($configs as $config) {
            if (!$config->auto_apply) {
                continue;
            }

            // Apply extended rights
            if ($config->extended_rights_id) {
                $this->copyRightsToObject($config->extended_rights_id, $informationObjectId, $userId);
                $this->logRightsApplication($agreementId, $informationObjectId, 'extended_rights', $userId);
                $results['rights_applied']++;
            }

            // Apply embargo
            if ($config->embargo_id) {
                $this->copyEmbargoToObject($config->embargo_id, $informationObjectId, $userId);
                $this->logRightsApplication($agreementId, $informationObjectId, 'embargo', $userId);
                $results['embargoes_applied']++;
            }
        }

        return $results;
    }

    /**
     * Copy rights from template to object
     */
    protected function copyRightsToObject(int $templateRightsId, int $objectId, ?int $userId): void
    {
        $template = DB::table('extended_rights')->where('id', $templateRightsId)->first();
        if (!$template) return;

        // Check if already has this rights statement
        $existing = DB::table('extended_rights')
            ->where('object_id', $objectId)
            ->where('rights_statement_id', $template->rights_statement_id)
            ->first();

        if ($existing) return;

        $this->rightsService->assignRights($objectId, [
            'rights_statement_id' => $template->rights_statement_id,
            'creative_commons_license_id' => $template->creative_commons_license_id,
            'rights_holder' => $template->rights_holder,
            'rights_holder_uri' => $template->rights_holder_uri,
            'is_primary' => 1,
        ], $userId);
    }

    /**
     * Copy embargo from template to object
     */
    protected function copyEmbargoToObject(int $templateEmbargoId, int $objectId, ?int $userId): void
    {
        $template = DB::table('embargo')->where('id', $templateEmbargoId)->first();
        if (!$template) return;

        // Check if already has active embargo
        $existing = DB::table('embargo')
            ->where('object_id', $objectId)
            ->where('status', 'active')
            ->first();

        if ($existing) return;

        DB::table('embargo')->insert([
            'object_id' => $objectId,
            'embargo_type' => $template->embargo_type,
            'start_date' => $template->start_date,
            'end_date' => $template->end_date,
            'is_perpetual' => $template->is_perpetual,
            'status' => 'active',
            'created_by' => $userId,
            'notify_on_expiry' => $template->notify_on_expiry,
            'notify_days_before' => $template->notify_days_before,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log rights application
     */
    protected function logRightsApplication(
        int $agreementId,
        int $objectId,
        string $rightsType,
        ?int $userId
    ): void {
        DB::table('donor_rights_application_log')->insert([
            'donor_agreement_id' => $agreementId,
            'information_object_id' => $objectId,
            'rights_type' => $rightsType,
            'action' => 'applied',
            'applied_by' => $userId,
            'applied_at' => now(),
        ]);
    }

    /**
     * Apply rights to all linked records
     */
    public function applyRightsToAllLinkedRecords(int $agreementId, ?int $userId = null): array
    {
        $results = ['total' => 0, 'rights_applied' => 0, 'embargoes_applied' => 0];

        $records = $this->getAgreementRecords($agreementId);

        foreach ($records as $record) {
            $res = $this->applyAgreementRightsToRecord($agreementId, $record->object_id, $userId);
            $results['total']++;
            $results['rights_applied'] += $res['rights_applied'];
            $results['embargoes_applied'] += $res['embargoes_applied'];
        }

        return $results;
    }

    // =========================================================================
    // SUMMARY & REPORTING
    // =========================================================================

    /**
     * Get agreement linkage summary
     */
    public function getAgreementSummary(int $agreementId): array
    {
        return [
            'linked_records' => DB::table('donor_agreement_record')
                ->where('agreement_id', $agreementId)->count(),
            'rights_configs' => DB::table('donor_agreement_rights')
                ->where('donor_agreement_id', $agreementId)->count(),
            'rights_applied' => DB::table('donor_rights_application_log')
                ->where('donor_agreement_id', $agreementId)
                ->where('action', 'applied')->count(),
        ];
    }
}
