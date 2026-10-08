<?php

declare(strict_types=1);

namespace AtomExtensions\Services\Search;

use Illuminate\Database\Capsule\Manager as DB;

class SearchAccessFilterService
{
    private static ?self $instance = null;

    /** @var callable[] fn(?int $userId): int[] - extra ids to hide, from plugins */
    private static array $sources = [];

    /**
     * Let a plugin hide further descriptions from non-administrators (for
     * example ahgMultiTenantPlugin: other tenants' records). The source gets
     * the user id and returns description ids. Every list, search, API and
     * record-page check that uses this service then applies it too.
     */
    public static function addRestrictionSource(callable $source): void
    {
        self::$sources[] = $source;
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Descriptions hidden from this user in lists and search.
     *
     * @param bool $withIcipOdrl include ICIP restrictions/notices and ODRL
     *                           prohibitions. Lists pass true. A record page
     *                           passes false: ahgICIPPlugin and ahgResearchPlugin
     *                           run their own page flows there (an ICIP notice
     *                           is lifted by acknowledging it on that page).
     */
    public function getRestrictedObjectIds(?int $userId, bool $withIcipOdrl = true): array
    {
        $userContext = $this->getUserContext($userId);

        if ($userContext['is_administrator']) {
            return [];
        }

        $today = date('Y-m-d');

        // Classification restricted
        // Each source of restriction belongs to an optional plugin. Where its
        // tables are absent that plugin cannot have restricted anything, so the
        // source contributes no ids - it must not throw and take search down (#302).
        $classRestricted = !$this->has('object_security_classification', 'security_classification') ? [] : DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->where('osc.active', 1)
            ->where('sc.level', '>', $userContext['clearance_level'])
            ->pluck('osc.object_id')
            ->toArray();

        // Donor restricted (closed items)
        $donorRestricted = !$this->has('object_rights_holder', 'donor_agreement', 'donor_agreement_restriction') ? [] : DB::table('object_rights_holder as orh')
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

        $icipRestricted = $withIcipOdrl ? $this->icipRestricted($today, $userId) : [];
        $odrlRestricted = $withIcipOdrl ? $this->odrlRestricted() : [];

        if (!$this->has('rights_embargo')) {
            return array_values(array_unique(array_merge($classRestricted, $donorRestricted, $icipRestricted, $odrlRestricted)));
        }

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
        if ($userId && $this->has('embargo_exception')) {
            // An exception can name the user or one of their groups.
            $userExceptions = DB::table('embargo_exception as ee')
                ->join('rights_embargo as re', 're.id', '=', 'ee.embargo_id')
                ->where(function ($q) use ($userId) {
                    $q->where(function ($u) use ($userId) {
                        $u->where('ee.exception_type', 'user')->where('ee.exception_id', $userId);
                    })->orWhere(function ($g) use ($userId) {
                        $g->where('ee.exception_type', 'group')
                            ->whereIn('ee.exception_id', DB::table('acl_user_group')->where('user_id', $userId)->select('group_id'));
                    });
                })
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

        $fromSources = [];
        foreach (self::$sources as $source) {
            // Not caught: like the sources above, a failure propagates, and the
            // record-page check fails closed on it rather than showing the record.
            $fromSources = array_merge($fromSources, array_map('intval', (array) $source($userId)));
        }

        return array_values(array_unique(array_merge($classRestricted, $donorRestricted, $embargoed, $icipRestricted, $odrlRestricted, $fromSources)));
    }

    /**
     * Whether a record page must be refused: classification, donor
     * restriction, full embargo. ICIP and ODRL are left to their plugins'
     * own page flows (see getRestrictedObjectIds).
     */
    public function isRestricted(int $objectId, ?int $userId): bool
    {
        return in_array($objectId, $this->getRestrictedObjectIds($userId, false), true);
    }

    /**
     * ICIP (Indigenous cultural and intellectual property, ahgICIPPlugin): an
     * active access restriction, or an active cultural notice whose type
     * blocks access, on the record or on an ancestor whose entry applies to
     * descendants. A blocking notice the user has already acknowledged no
     * longer hides the record from them.
     *
     * @return int[]
     */
    private function icipRestricted(string $today, ?int $userId): array
    {
        $ids = [];
        $sources = [];
        if ($this->has('icip_access_restriction')) {
            $sources[] = DB::table('icip_access_restriction as e')
                ->where(function ($q) use ($today) {
                    $q->whereNull('e.start_date')->orWhere('e.start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('e.end_date')->orWhere('e.end_date', '>=', $today);
                });
        }
        if ($this->has('icip_cultural_notice', 'icip_cultural_notice_type')) {
            $sources[] = DB::table('icip_cultural_notice as e')
                ->join('icip_cultural_notice_type as t', 't.id', '=', 'e.notice_type_id')
                ->where('t.is_active', 1)->where('t.blocks_access', 1)
                ->when($userId && $this->has('icip_notice_acknowledgement'), function ($q) use ($userId) {
                    $q->whereNotExists(function ($a) use ($userId) {
                        $a->select(DB::raw(1))->from('icip_notice_acknowledgement as ack')
                            ->whereColumn('ack.notice_id', 'e.id')->where('ack.user_id', $userId);
                    });
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('e.start_date')->orWhere('e.start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('e.end_date')->orWhere('e.end_date', '>=', $today);
                });
        }

        foreach ($sources as $source) {
            foreach ((clone $source)->get(['e.information_object_id', 'e.applies_to_descendants']) as $row) {
                $ids[(int) $row->information_object_id] = true;
                if ((int) $row->applies_to_descendants) {
                    $node = DB::table('information_object')->where('id', $row->information_object_id)->first(['lft', 'rgt']);
                    if ($node) {
                        foreach (DB::table('information_object')->whereBetween('lft', [$node->lft, $node->rgt])->pluck('id') as $id) {
                            $ids[(int) $id] = true;
                        }
                    }
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * ODRL "use" prohibitions (ahgResearchPlugin research_rights_policy) - the
     * rule ahgPortableExportPlugin's DisclosureGate already applies.
     *
     * @return int[]
     */
    private function odrlRestricted(): array
    {
        if (!$this->has('research_rights_policy')) {
            return [];
        }

        return array_map('intval', DB::table('research_rights_policy')
            ->whereIn('target_type', ['archival_description', 'information_object'])
            ->where('policy_type', 'prohibition')
            ->where('action_type', 'use')
            ->pluck('target_id')->all());
    }

    /** Table presence, cached for the request. */
    private function has(string ...$tables): bool
    {
        static $known = [];

        foreach ($tables as $t) {
            if (!isset($known[$t])) {
                try {
                    $known[$t] = DB::schema()->hasTable($t);
                } catch (\Throwable $e) {
                    $known[$t] = false;
                }
            }
            if (!$known[$t]) {
                return false;
            }
        }

        return true;
    }

    private function getUserContext(?int $userId): array
    {
        if (null === $userId) {
            return ['user_id' => null, 'is_administrator' => false, 'clearance_level' => 0];
        }

        $clearance = !$this->has('user_security_clearance', 'security_classification') ? 0 : DB::table('user_security_clearance as usc')
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
