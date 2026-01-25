<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Contributions;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Contributor Service.
 *
 * Manages public contributor accounts (separate from AtoM users).
 */
class ContributorService
{
    private const SESSION_DURATION_DAYS = 30;
    private const VERIFY_TOKEN_HOURS = 48;
    private const RESET_TOKEN_HOURS = 24;

    /**
     * Register a new contributor.
     */
    public function register(string $email, string $displayName, string $password): array
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        // Check email uniqueness
        $existing = DB::table('heritage_contributor')
            ->where('email', strtolower($email))
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Email already registered'];
        }

        // Validate display name
        if (strlen($displayName) < 2 || strlen($displayName) > 100) {
            return ['success' => false, 'error' => 'Display name must be between 2 and 100 characters'];
        }

        // Validate password
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        // Generate verification token
        $verifyToken = bin2hex(random_bytes(32));

        $id = DB::table('heritage_contributor')->insertGetId([
            'email' => strtolower($email),
            'display_name' => $displayName,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email_verify_token' => $verifyToken,
            'email_verify_expires' => date('Y-m-d H:i:s', strtotime('+' . self::VERIFY_TOKEN_HOURS . ' hours')),
            'trust_level' => 'new',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'email' => strtolower($email),
                'display_name' => $displayName,
                'verify_token' => $verifyToken,
            ],
        ];
    }

    /**
     * Login a contributor.
     */
    public function login(string $email, string $password): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('email', strtolower($email))
            ->where('is_active', 1)
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        if (!password_verify($password, $contributor->password_hash)) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        if (!$contributor->email_verified) {
            return ['success' => false, 'error' => 'Please verify your email address first'];
        }

        // Generate session token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::SESSION_DURATION_DAYS . ' days'));

        DB::table('heritage_contributor_session')->insert([
            'contributor_id' => $contributor->id,
            'token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update last login
        DB::table('heritage_contributor')
            ->where('id', $contributor->id)
            ->update(['last_login_at' => date('Y-m-d H:i:s')]);

        return [
            'success' => true,
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'contributor' => $this->formatContributor($contributor),
            ],
        ];
    }

    /**
     * Logout a contributor (invalidate session).
     */
    public function logout(string $token): array
    {
        $deleted = DB::table('heritage_contributor_session')
            ->where('token', $token)
            ->delete();

        return ['success' => $deleted > 0];
    }

    /**
     * Validate session token and return contributor.
     */
    public function validateSession(string $token): ?object
    {
        $session = DB::table('heritage_contributor_session')
            ->where('token', $token)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$session) {
            return null;
        }

        return DB::table('heritage_contributor')
            ->where('id', $session->contributor_id)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(string $token): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('email_verify_token', $token)
            ->where('email_verify_expires', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Invalid or expired verification token'];
        }

        DB::table('heritage_contributor')
            ->where('id', $contributor->id)
            ->update([
                'email_verified' => 1,
                'email_verify_token' => null,
                'email_verify_expires' => null,
            ]);

        return ['success' => true, 'data' => ['email' => $contributor->email]];
    }

    /**
     * Request password reset.
     */
    public function requestPasswordReset(string $email): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('email', strtolower($email))
            ->where('is_active', 1)
            ->first();

        if (!$contributor) {
            // Don't reveal if email exists
            return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
        }

        $token = bin2hex(random_bytes(32));

        DB::table('heritage_contributor')
            ->where('id', $contributor->id)
            ->update([
                'password_reset_token' => $token,
                'password_reset_expires' => date('Y-m-d H:i:s', strtotime('+' . self::RESET_TOKEN_HOURS . ' hours')),
            ]);

        return [
            'success' => true,
            'data' => [
                'token' => $token,
                'email' => $contributor->email,
            ],
        ];
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        $contributor = DB::table('heritage_contributor')
            ->where('password_reset_token', $token)
            ->where('password_reset_expires', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }

        DB::table('heritage_contributor')
            ->where('id', $contributor->id)
            ->update([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'password_reset_token' => null,
                'password_reset_expires' => null,
            ]);

        // Invalidate all sessions
        DB::table('heritage_contributor_session')
            ->where('contributor_id', $contributor->id)
            ->delete();

        return ['success' => true];
    }

    /**
     * Get contributor profile.
     */
    public function getProfile(int $contributorId): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Contributor not found'];
        }

        // Get badges
        $badges = DB::table('heritage_contributor_badge_award as a')
            ->join('heritage_contributor_badge as b', 'a.badge_id', '=', 'b.id')
            ->where('a.contributor_id', $contributorId)
            ->select(['b.code', 'b.name', 'b.description', 'b.icon', 'b.color', 'a.awarded_at'])
            ->orderBy('a.awarded_at', 'desc')
            ->get()
            ->toArray();

        // Get recent approved contributions
        $recentContributions = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.contributor_id', $contributorId)
            ->where('c.status', 'approved')
            ->orderBy('c.created_at', 'desc')
            ->limit(10)
            ->select([
                'c.id',
                'c.content',
                'c.created_at',
                't.code as type_code',
                't.name as type_name',
                't.icon as type_icon',
                'ioi.title as item_title',
                's.slug as item_slug',
            ])
            ->get()
            ->toArray();

        // Get contribution stats by type
        $statsByType = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->where('c.contributor_id', $contributorId)
            ->where('c.status', 'approved')
            ->groupBy('t.code', 't.name', 't.icon')
            ->select([
                't.code',
                't.name',
                't.icon',
                DB::raw('COUNT(*) as count'),
            ])
            ->get()
            ->toArray();

        return [
            'success' => true,
            'data' => [
                'contributor' => $this->formatContributor($contributor, true),
                'badges' => $badges,
                'recent_contributions' => $recentContributions,
                'stats_by_type' => $statsByType,
            ],
        ];
    }

    /**
     * Update contributor profile.
     */
    public function updateProfile(int $contributorId, array $data): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Contributor not found'];
        }

        $updates = [];

        if (isset($data['display_name'])) {
            if (strlen($data['display_name']) < 2 || strlen($data['display_name']) > 100) {
                return ['success' => false, 'error' => 'Display name must be between 2 and 100 characters'];
            }
            $updates['display_name'] = $data['display_name'];
        }

        if (isset($data['bio'])) {
            $updates['bio'] = substr($data['bio'], 0, 2000);
        }

        if (isset($data['avatar_url'])) {
            $updates['avatar_url'] = $data['avatar_url'];
        }

        if (!empty($updates)) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            DB::table('heritage_contributor')
                ->where('id', $contributorId)
                ->update($updates);
        }

        return ['success' => true];
    }

    /**
     * Calculate and update trust level based on contributions.
     */
    public function calculateTrustLevel(int $contributorId): string
    {
        $contributor = DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->first();

        if (!$contributor) {
            return 'new';
        }

        $approvedCount = $contributor->approved_contributions;
        $totalCount = $contributor->total_contributions;

        // Calculate approval rate
        $approvalRate = $totalCount > 0 ? ($approvedCount / $totalCount) * 100 : 0;

        // Determine trust level
        $newLevel = 'new';

        if ($approvedCount >= 100 && $approvalRate >= 90) {
            $newLevel = 'expert';
        } elseif ($approvedCount >= 25 && $approvalRate >= 80) {
            $newLevel = 'trusted';
        } elseif ($approvedCount >= 5) {
            $newLevel = 'contributor';
        }

        // Update if changed
        if ($newLevel !== $contributor->trust_level) {
            DB::table('heritage_contributor')
                ->where('id', $contributorId)
                ->update(['trust_level' => $newLevel]);
        }

        return $newLevel;
    }

    /**
     * Get leaderboard.
     */
    public function getLeaderboard(int $limit = 20, ?string $period = null): array
    {
        $query = DB::table('heritage_contributor')
            ->where('is_active', 1)
            ->where('approved_contributions', '>', 0);

        // Filter by period
        if ($period === 'week') {
            $query->where('last_contribution_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')));
        } elseif ($period === 'month') {
            $query->where('last_contribution_at', '>=', date('Y-m-d H:i:s', strtotime('-1 month')));
        }

        $contributors = $query
            ->orderBy('points', 'desc')
            ->limit($limit)
            ->select([
                'id',
                'display_name',
                'avatar_url',
                'trust_level',
                'points',
                'approved_contributions',
                'badges',
            ])
            ->get();

        $result = [];
        $rank = 1;
        foreach ($contributors as $c) {
            $result[] = [
                'rank' => $rank++,
                'id' => $c->id,
                'display_name' => $c->display_name,
                'avatar_url' => $c->avatar_url,
                'trust_level' => $c->trust_level,
                'points' => $c->points,
                'approved_contributions' => $c->approved_contributions,
                'badge_count' => count(json_decode($c->badges ?? '[]', true)),
            ];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Award badges to contributor based on achievements.
     */
    public function checkAndAwardBadges(int $contributorId): array
    {
        $contributor = DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->first();

        if (!$contributor) {
            return [];
        }

        $awarded = [];

        // Get all active badges not yet awarded
        $eligibleBadges = DB::table('heritage_contributor_badge as b')
            ->leftJoin('heritage_contributor_badge_award as a', function ($join) use ($contributorId) {
                $join->on('b.id', '=', 'a.badge_id')
                    ->where('a.contributor_id', '=', $contributorId);
            })
            ->where('b.is_active', 1)
            ->whereNull('a.id')
            ->select(['b.*'])
            ->get();

        foreach ($eligibleBadges as $badge) {
            $earned = false;

            switch ($badge->criteria_type) {
                case 'contribution_count':
                    $earned = $contributor->approved_contributions >= $badge->criteria_value;
                    break;

                case 'approval_rate':
                    if ($contributor->total_contributions >= 20) {
                        $rate = ($contributor->approved_contributions / $contributor->total_contributions) * 100;
                        $earned = $rate >= $badge->criteria_value;
                    }
                    break;

                case 'points':
                    $earned = $contributor->points >= $badge->criteria_value;
                    break;

                case 'type_specific':
                    $config = json_decode($badge->criteria_config ?? '{}', true);
                    $typeCode = $config['type'] ?? null;
                    if ($typeCode) {
                        $count = DB::table('heritage_contribution as c')
                            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
                            ->where('c.contributor_id', $contributorId)
                            ->where('c.status', 'approved')
                            ->where('t.code', $typeCode)
                            ->count();
                        $earned = $count >= $badge->criteria_value;
                    }
                    break;
            }

            if ($earned) {
                DB::table('heritage_contributor_badge_award')->insert([
                    'contributor_id' => $contributorId,
                    'badge_id' => $badge->id,
                    'awarded_at' => date('Y-m-d H:i:s'),
                ]);

                // Award bonus points
                if ($badge->points_bonus > 0) {
                    DB::table('heritage_contributor')
                        ->where('id', $contributorId)
                        ->increment('points', $badge->points_bonus);
                }

                $awarded[] = [
                    'code' => $badge->code,
                    'name' => $badge->name,
                    'icon' => $badge->icon,
                ];
            }
        }

        return $awarded;
    }

    /**
     * Get contributor statistics.
     */
    public function getStats(): array
    {
        $totalContributors = DB::table('heritage_contributor')
            ->where('is_active', 1)
            ->count();

        $verifiedContributors = DB::table('heritage_contributor')
            ->where('is_active', 1)
            ->where('email_verified', 1)
            ->count();

        $newThisWeek = DB::table('heritage_contributor')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')))
            ->count();

        $activeThisWeek = DB::table('heritage_contributor')
            ->where('last_contribution_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')))
            ->count();

        $byTrustLevel = DB::table('heritage_contributor')
            ->where('is_active', 1)
            ->groupBy('trust_level')
            ->select(['trust_level', DB::raw('COUNT(*) as count')])
            ->pluck('count', 'trust_level')
            ->toArray();

        return [
            'success' => true,
            'data' => [
                'total' => $totalContributors,
                'verified' => $verifiedContributors,
                'new_this_week' => $newThisWeek,
                'active_this_week' => $activeThisWeek,
                'by_trust_level' => $byTrustLevel,
            ],
        ];
    }

    /**
     * Format contributor for response.
     */
    private function formatContributor(object $contributor, bool $includeStats = false): array
    {
        $data = [
            'id' => $contributor->id,
            'email' => $contributor->email,
            'display_name' => $contributor->display_name,
            'avatar_url' => $contributor->avatar_url,
            'bio' => $contributor->bio,
            'trust_level' => $contributor->trust_level,
            'email_verified' => (bool) $contributor->email_verified,
            'created_at' => $contributor->created_at,
        ];

        if ($includeStats) {
            $data['total_contributions'] = $contributor->total_contributions;
            $data['approved_contributions'] = $contributor->approved_contributions;
            $data['rejected_contributions'] = $contributor->rejected_contributions;
            $data['points'] = $contributor->points;
            $data['last_contribution_at'] = $contributor->last_contribution_at;
        }

        return $data;
    }
}
