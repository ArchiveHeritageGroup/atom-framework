<?php

declare(strict_types=1);

namespace AtomExtensions\Services\Access;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * AccessFilterService - Comprehensive Access Control
 *
 * @package    AtoM
 * @subpackage Services
 * @author     The Archive and Heritage Group (Pty) Ltd
 */
class AccessFilterService
{
    private static ?self $instance = null;

    public const DENIED_CLASSIFICATION = 'classification';
    public const DENIED_DONOR = 'donor_restriction';
    public const DENIED_EMBARGO = 'embargo';

    public const ACCESS_FULL = 'full';
    public const ACCESS_METADATA_ONLY = 'metadata_only';
    public const ACCESS_RESTRICTED = 'restricted';
    public const ACCESS_DENIED = 'denied';

    private const RESTRICTION_MAP = [
        'closure' => self::ACCESS_DENIED,
        'partial_closure' => self::ACCESS_RESTRICTED,
        'redaction' => self::ACCESS_RESTRICTED,
        'permission_only' => self::ACCESS_DENIED,
        'researcher_only' => self::ACCESS_RESTRICTED,
        'onsite_only' => self::ACCESS_RESTRICTED,
        'no_copying' => self::ACCESS_RESTRICTED,
        'no_publication' => self::ACCESS_RESTRICTED,
        'anonymization' => self::ACCESS_RESTRICTED,
        'time_embargo' => self::ACCESS_DENIED,
        'review_required' => self::ACCESS_RESTRICTED,
        'security_clearance' => self::ACCESS_DENIED,
        'popia_restricted' => self::ACCESS_DENIED,
        'legal_hold' => self::ACCESS_DENIED,
        'cultural_protocol' => self::ACCESS_RESTRICTED,
    ];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Check if user can access a specific object
     */
    public function checkAccess(int $objectId, ?int $userId, string $action = 'view'): array
    {
        $result = [
            'granted' => true,
            'level' => self::ACCESS_FULL,
            'reasons' => [],
            'restrictions' => [],
            'classification' => null,
            'donor_restrictions' => [],
            'embargo' => null,
        ];

        $userContext = $this->getUserContext($userId);

        // 1. Check Security Classification
        $classificationCheck = $this->checkSecurityClassification($objectId, $userContext);
        if (!$classificationCheck['granted']) {
            $result['granted'] = false;
            $result['level'] = self::ACCESS_DENIED;
            $result['reasons'][] = self::DENIED_CLASSIFICATION;
            $result['classification'] = $classificationCheck['classification'];
            return $result;
        }
        $result['classification'] = $classificationCheck['classification'];

        // 2. Check Donor Restrictions
        $donorCheck = $this->checkDonorRestrictions($objectId, $userContext, $action);
        if (!$donorCheck['granted']) {
            $result['granted'] = false;
            $result['level'] = $donorCheck['level'];
            $result['reasons'][] = self::DENIED_DONOR;
            $result['donor_restrictions'] = $donorCheck['restrictions'];
            $result['restrictions'] = array_merge($result['restrictions'], $donorCheck['restrictions']);
            
            if (in_array($donorCheck['level'], [self::ACCESS_METADATA_ONLY, self::ACCESS_RESTRICTED])) {
                $result['granted'] = true;
            }
        }
        $result['donor_restrictions'] = $donorCheck['restrictions'];

        // 3. Check Embargo Status (extended_rights.expiry_date)
        $embargoCheck = $this->checkEmbargo($objectId);
        if (!$embargoCheck['granted']) {
            $result['granted'] = false;
            $result['level'] = self::ACCESS_DENIED;
            $result['reasons'][] = self::DENIED_EMBARGO;
            $result['embargo'] = $embargoCheck['embargo'];
            return $result;
        }
        $result['embargo'] = $embargoCheck['embargo'];

        return $result;
    }

    /**
     * Get user context
     */
    public function getUserContext(?int $userId): array
    {
        if (null === $userId) {
            return [
                'user_id' => null,
                'is_authenticated' => false,
                'is_administrator' => false,
                'clearance_level' => 0,
                'clearance_code' => 'PUBLIC',
                'groups' => [],
            ];
        }

        $now = $this->now();

        $clearance = DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'sc.id', '=', 'usc.classification_id')
            ->where('usc.user_id', $userId)
            ->where(function ($q) use ($now) {
                $q->whereNull('usc.expires_at')
                  ->orWhere('usc.expires_at', '>', $now);
            })
            ->select('sc.id', 'sc.code', 'sc.level', 'sc.name')
            ->first();

        $groups = DB::table('acl_user_group as aug')
            ->join('acl_group as ag', 'ag.id', '=', 'aug.group_id')
            ->where('aug.user_id', $userId)
            ->pluck('ag.id')
            ->toArray();

        $isAdmin = in_array(100, $groups);

        return [
            'user_id' => $userId,
            'is_authenticated' => true,
            'is_administrator' => $isAdmin,
            'clearance_level' => $clearance->level ?? 0,
            'clearance_code' => $clearance->code ?? 'PUBLIC',
            'clearance_id' => $clearance->id ?? null,
            'groups' => $groups,
        ];
    }

    /**
     * Check security classification access
     */
    private function checkSecurityClassification(int $objectId, array $userContext): array
    {
        $classification = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->where('osc.object_id', $objectId)
            ->where('osc.active', 1)
            ->select('sc.id', 'sc.code', 'sc.level', 'sc.name', 'osc.review_date', 'osc.declassify_date')
            ->first();

        if (!$classification) {
            return ['granted' => true, 'classification' => null];
        }

        if ($userContext['is_administrator']) {
            return [
                'granted' => true,
                'classification' => (array) $classification,
                'bypass_reason' => 'administrator',
            ];
        }

        $granted = $userContext['clearance_level'] >= $classification->level;

        return [
            'granted' => $granted,
            'classification' => (array) $classification,
            'user_clearance' => $userContext['clearance_level'],
            'required_clearance' => $classification->level,
        ];
    }

    /**
     * Check donor/rights holder restrictions
     */
    private function checkDonorRestrictions(int $objectId, array $userContext, string $action): array
    {
        $restrictions = DB::table('object_rights_holder as orh')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'orh.donor_id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('donor_agreement as da', 'da.donor_id', '=', 'orh.donor_id')
            ->leftJoin('donor_agreement_restriction as dar', 'dar.donor_agreement_id', '=', 'da.id')
            ->where('orh.object_id', $objectId)
            ->select(
                'orh.donor_id',
                'ai.authorized_form_of_name as donor_name',
                'da.id as agreement_id',
                'da.agreement_type_id',
                'da.status as agreement_status',
                'dar.restriction_type',
                'dar.applies_to_all',
                'dar.start_date',
                'dar.end_date',
                'dar.auto_release',
                'dar.release_date',
                'dar.security_clearance_level',
                'dar.reason',
                'dar.notes'
            )
            ->get();

        if ($restrictions->isEmpty()) {
            return [
                'granted' => true,
                'level' => self::ACCESS_FULL,
                'restrictions' => [],
            ];
        }

        if ($userContext['is_administrator']) {
            return [
                'granted' => true,
                'level' => self::ACCESS_FULL,
                'restrictions' => $restrictions->toArray(),
                'bypass_reason' => 'administrator',
            ];
        }

        $activeRestrictions = [];
        $accessLevel = self::ACCESS_FULL;
        $today = $this->today();

        foreach ($restrictions as $r) {
            if (empty($r->restriction_type)) continue;
            if ($r->start_date && $r->start_date > $today) continue;
            if ($r->end_date && $r->end_date < $today) continue;
            if ($r->auto_release && $r->release_date && $r->release_date <= $today) continue;

            if ($r->restriction_type === 'security_clearance' && $r->security_clearance_level) {
                if ($userContext['clearance_level'] >= $r->security_clearance_level) {
                    continue;
                }
            }

            $restrictionLevel = self::RESTRICTION_MAP[$r->restriction_type] ?? self::ACCESS_RESTRICTED;
            
            if ($restrictionLevel === self::ACCESS_DENIED) {
                $accessLevel = self::ACCESS_DENIED;
            } elseif ($restrictionLevel === self::ACCESS_RESTRICTED && $accessLevel !== self::ACCESS_DENIED) {
                $accessLevel = self::ACCESS_RESTRICTED;
            }

            $activeRestrictions[] = [
                'type' => $r->restriction_type,
                'donor' => $r->donor_name,
                'message' => $this->getRestrictionMessage($r->restriction_type, $r->donor_name),
                'reason' => $r->reason,
                'end_date' => $r->end_date,
            ];
        }

        return [
            'granted' => $accessLevel !== self::ACCESS_DENIED,
            'level' => $accessLevel,
            'restrictions' => $activeRestrictions,
        ];
    }

    private function getRestrictionMessage(string $type, ?string $donor): string
    {
        $messages = [
            'closure' => 'Closed - access not permitted',
            'partial_closure' => 'Partially closed - some content restricted',
            'redaction' => 'Contains redacted information',
            'permission_only' => 'Access by permission only',
            'researcher_only' => 'Access for researchers only',
            'onsite_only' => 'Onsite access only',
            'no_copying' => 'Copying not permitted',
            'no_publication' => 'Publication not permitted',
            'anonymization' => 'Contains anonymized data',
            'time_embargo' => 'Under time embargo',
            'review_required' => 'Access requires review',
            'security_clearance' => 'Security clearance required',
            'popia_restricted' => 'Restricted under POPIA',
            'legal_hold' => 'Under legal hold',
            'cultural_protocol' => 'Cultural protocol restrictions apply',
        ];

        $msg = $messages[$type] ?? 'Access restricted';
        if ($donor) {
            $msg .= " (Donor: {$donor})";
        }
        return $msg;
    }

    /**
     * Check embargo status via extended_rights.expiry_date
     */
    private function checkEmbargo(int $objectId): array
    {
        $today = $this->today();
        
        // extended_rights table has: object_id, expiry_date (no object_type column)
        $embargo = DB::table('extended_rights')
            ->where('object_id', $objectId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', $today)
            ->first();

        if (!$embargo) {
            return ['granted' => true, 'embargo' => null];
        }

        return [
            'granted' => false,
            'embargo' => [
                'end_date' => $embargo->expiry_date,
                'rights_holder' => $embargo->rights_holder ?? null,
            ],
        ];
    }

    /**
     * Apply access filters to a query builder
     */
    public function applyAccessFilters(Builder $query, ?int $userId): Builder
    {
        $userContext = $this->getUserContext($userId);

        if ($userContext['is_administrator']) {
            return $query;
        }

        $query = $this->applyClassificationFilter($query, $userContext);
        $query = $this->applyDonorRestrictionFilter($query, $userContext);
        $query = $this->applyEmbargoFilter($query);

        return $query;
    }

    private function applyClassificationFilter(Builder $query, array $userContext): Builder
    {
        $userLevel = $userContext['clearance_level'];

        return $query->where(function ($q) use ($userLevel) {
            $q->whereNotExists(function ($sub) use ($userLevel) {
                $sub->select(DB::raw(1))
                    ->from('object_security_classification as osc')
                    ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
                    ->whereColumn('osc.object_id', 'information_object.id')
                    ->where('osc.active', 1)
                    ->where('sc.level', '>', $userLevel);
            });
        });
    }

    private function applyDonorRestrictionFilter(Builder $query, array $userContext): Builder
    {
        $today = $this->today();

        return $query->where(function ($q) use ($today) {
            $q->whereNotExists(function ($sub) use ($today) {
                $sub->select(DB::raw(1))
                    ->from('object_rights_holder as orh')
                    ->join('donor_agreement as da', 'da.donor_id', '=', 'orh.donor_id')
                    ->join('donor_agreement_restriction as dar', 'dar.donor_agreement_id', '=', 'da.id')
                    ->whereColumn('orh.object_id', 'information_object.id')
                    ->whereIn('dar.restriction_type', ['closure', 'permission_only', 'time_embargo', 'popia_restricted', 'legal_hold'])
                    ->where(function ($dates) use ($today) {
                        $dates->whereNull('dar.start_date')
                              ->orWhere('dar.start_date', '<=', $today);
                    })
                    ->where(function ($dates) use ($today) {
                        $dates->whereNull('dar.end_date')
                              ->orWhere('dar.end_date', '>=', $today);
                    });
            });
        });
    }

    private function applyEmbargoFilter(Builder $query): Builder
    {
        $today = $this->today();
        
        return $query->where(function ($q) use ($today) {
            $q->whereNotExists(function ($sub) use ($today) {
                $sub->select(DB::raw(1))
                    ->from('extended_rights as er')
                    ->whereColumn('er.object_id', 'information_object.id')
                    ->whereNotNull('er.expiry_date')
                    ->where('er.expiry_date', '>', $today);
            });
        });
    }

    public function getAccessibleCount(?int $userId): int
    {
        $query = DB::table('information_object');
        $query = $this->applyAccessFilters($query, $userId);
        return $query->count();
    }

    public function getRestrictedObjects(): Collection
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('object_security_classification as osc', function ($join) {
                $join->on('osc.object_id', '=', 'io.id')
                     ->where('osc.active', '=', 1);
            })
            ->leftJoin('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->leftJoin('object_rights_holder as orh', 'orh.object_id', '=', 'io.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'orh.donor_id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->where(function ($q) {
                $q->whereNotNull('osc.id')
                  ->orWhereNotNull('orh.id');
            })
            ->select(
                'io.id',
                'io.identifier',
                'ioi.title',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'ai.authorized_form_of_name as donor_name'
            )
            ->orderBy('sc.level', 'desc')
            ->get();
    }

    public function logAccess(int $objectId, ?int $userId, string $action, array $result): void
    {
        DB::table('access_audit_log')->insert([
            'object_id' => $objectId,
            'user_id' => $userId,
            'action' => $action,
            'granted' => $result['granted'] ? 1 : 0,
            'access_level' => $result['level'],
            'denial_reasons' => json_encode($result['reasons']),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => $this->now(),
        ]);
    }
}
