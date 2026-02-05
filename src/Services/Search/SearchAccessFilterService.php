<?php

declare(strict_types=1);

namespace AtomExtensions\Services\Search;

use Illuminate\Database\Capsule\Manager as DB;

class SearchAccessFilterService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getRestrictedObjectIds(?int $userId): array
    {
        $userContext = $this->getUserContext($userId);

        if ($userContext['is_administrator']) {
            return [];
        }

        $today = date('Y-m-d');

        // Classification restricted
        $classRestricted = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->where('osc.active', 1)
            ->where('sc.level', '>', $userContext['clearance_level'])
            ->pluck('osc.object_id')
            ->toArray();

        // Donor restricted (closed items)
        $donorRestricted = DB::table('object_rights_holder as orh')
            ->join('donor_agreement as da', 'da.donor_id', '=', 'orh.donor_id')
            ->join('donor_agreement_restriction as dar', 'dar.donor_agreement_id', '=', 'da.id')
            ->whereIn('dar.restriction_type', ['closure', 'permission_only', 'time_embargo', 'popia_restricted', 'legal_hold'])
            ->where(function ($q) use ($today) {
                $q->whereNull('dar.start_date')->orWhere('dar.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('dar.end_date')->orWhere('dar.end_date', '>=', $today);
            })
            ->pluck('orh.object_id')
            ->toArray();

        // Embargoed - query rights_embargo table for full embargoes
        // Only full embargoes should hide from search; other types allow metadata viewing
        $embargoedQuery = DB::table('rights_embargo')
            ->where('status', 'active')
            ->where('embargo_type', 'full')
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date') // Perpetual embargo
                    ->orWhere('end_date', '>=', $today);
            });

        // If user is authenticated, check for embargo exceptions
        if ($userId) {
            $userExceptions = DB::table('embargo_exception as ee')
                ->join('rights_embargo as re', 're.id', '=', 'ee.embargo_id')
                ->where('ee.exception_type', 'user')
                ->where('ee.exception_id', $userId)
                ->where(function ($q) {
                    $now = date('Y-m-d');
                    $q->whereNull('ee.valid_from')->orWhere('ee.valid_from', '<=', $now);
                })
                ->where(function ($q) {
                    $now = date('Y-m-d');
                    $q->whereNull('ee.valid_until')->orWhere('ee.valid_until', '>=', $now);
                })
                ->pluck('re.object_id')
                ->toArray();

            // Exclude objects where user has an exception
            if (!empty($userExceptions)) {
                $embargoedQuery->whereNotIn('object_id', $userExceptions);
            }
        }

        $embargoed = $embargoedQuery->pluck('object_id')->toArray();

        return array_unique(array_merge($classRestricted, $donorRestricted, $embargoed));
    }

    private function getUserContext(?int $userId): array
    {
        if (null === $userId) {
            return ['user_id' => null, 'is_administrator' => false, 'clearance_level' => 0];
        }

        $clearance = DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'sc.id', '=', 'usc.classification_id')
            ->where('usc.user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('usc.expires_at')->orWhere('usc.expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->value('sc.level') ?? 0;

        $isAdmin = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', 100)
            ->exists();

        return ['user_id' => $userId, 'is_administrator' => $isAdmin, 'clearance_level' => $clearance];
    }
}
