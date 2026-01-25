<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Contributions;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Contribution Service.
 *
 * Manages contributions from public users.
 */
class ContributionService
{
    private ContributorService $contributorService;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->contributorService = new ContributorService();
        $this->culture = $culture;
    }

    /**
     * Get contribution opportunities for an item.
     */
    public function getOpportunities(int $informationObjectId): array
    {
        // Get item info
        $item = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
            ->where('io.id', $informationObjectId)
            ->select([
                'io.id',
                'ioi.title',
                'ioi.scope_and_content',
                'do.id as digital_object_id',
                'do.mime_type',
            ])
            ->first();

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        // Get all active contribution types
        $types = DB::table('heritage_contribution_type')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->get();

        // Check existing contributions for this item
        $existingByType = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->where('c.information_object_id', $informationObjectId)
            ->groupBy('t.code')
            ->select(['t.code', DB::raw('COUNT(*) as count'), DB::raw('SUM(CASE WHEN c.status = "approved" THEN 1 ELSE 0 END) as approved_count')])
            ->pluck('count', 'code')
            ->toArray();

        $opportunities = [];
        foreach ($types as $type) {
            $opportunity = [
                'type_id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'icon' => $type->icon,
                'color' => $type->color,
                'points_value' => $type->points_value,
                'existing_count' => $existingByType[$type->code] ?? 0,
                'available' => true,
                'reason' => null,
            ];

            // Check type-specific availability
            switch ($type->code) {
                case 'transcription':
                    // Only available if item has a digital object (document/image)
                    if (!$item->digital_object_id) {
                        $opportunity['available'] = false;
                        $opportunity['reason'] = 'No digital object to transcribe';
                    }
                    break;

                case 'identification':
                    // Only for images
                    if (!$item->mime_type || !str_starts_with($item->mime_type, 'image/')) {
                        $opportunity['available'] = false;
                        $opportunity['reason'] = 'Only available for photographs and images';
                    }
                    break;

                case 'translation':
                    // Need existing content to translate
                    if (empty($item->scope_and_content) && empty($item->title)) {
                        $opportunity['available'] = false;
                        $opportunity['reason'] = 'No content to translate';
                    }
                    break;
            }

            $opportunities[] = $opportunity;
        }

        return [
            'success' => true,
            'data' => [
                'item' => [
                    'id' => $item->id,
                    'title' => $item->title ?? 'Untitled',
                    'has_digital_object' => !empty($item->digital_object_id),
                    'mime_type' => $item->mime_type,
                ],
                'opportunities' => $opportunities,
            ],
        ];
    }

    /**
     * Create a new contribution.
     */
    public function create(int $contributorId, int $itemId, string $typeCode, array $content): array
    {
        // Validate contributor
        $contributor = DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->where('is_active', 1)
            ->first();

        if (!$contributor) {
            return ['success' => false, 'error' => 'Contributor not found'];
        }

        // Get contribution type
        $type = DB::table('heritage_contribution_type')
            ->where('code', $typeCode)
            ->where('is_active', 1)
            ->first();

        if (!$type) {
            return ['success' => false, 'error' => 'Invalid contribution type'];
        }

        // Check trust level requirement
        $trustLevels = ['new' => 0, 'contributor' => 1, 'trusted' => 2, 'expert' => 3];
        $contributorLevel = $trustLevels[$contributor->trust_level] ?? 0;
        $requiredLevel = $trustLevels[$type->min_trust_level] ?? 0;

        if ($contributorLevel < $requiredLevel) {
            return ['success' => false, 'error' => 'Your trust level is not sufficient for this contribution type'];
        }

        // Validate item exists
        $item = DB::table('information_object')
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        // Check for existing pending contribution of same type from same user
        $existing = DB::table('heritage_contribution')
            ->where('contributor_id', $contributorId)
            ->where('information_object_id', $itemId)
            ->where('contribution_type_id', $type->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'You already have a pending contribution of this type for this item'];
        }

        // Validate content based on type
        $validation = $this->validateContent($typeCode, $content);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Determine initial status
        $status = $type->requires_validation ? 'pending' : 'approved';

        // Create contribution
        $id = DB::table('heritage_contribution')->insertGetId([
            'contributor_id' => $contributorId,
            'information_object_id' => $itemId,
            'contribution_type_id' => $type->id,
            'content' => json_encode($content),
            'status' => $status,
            'version_number' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create initial version
        DB::table('heritage_contribution_version')->insert([
            'contribution_id' => $id,
            'version_number' => 1,
            'content' => json_encode($content),
            'created_by' => $contributorId,
            'change_summary' => 'Initial submission',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update contributor stats
        DB::table('heritage_contributor')
            ->where('id', $contributorId)
            ->update([
                'total_contributions' => DB::raw('total_contributions + 1'),
                'last_contribution_at' => date('Y-m-d H:i:s'),
            ]);

        // If auto-approved, award points
        if ($status === 'approved') {
            $this->awardPoints($id);
        }

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => $status,
                'type' => $typeCode,
            ],
        ];
    }

    /**
     * Update an existing contribution.
     */
    public function update(int $contributionId, array $content, int $contributorId, ?string $changeSummary = null): array
    {
        $contribution = DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->first();

        if (!$contribution) {
            return ['success' => false, 'error' => 'Contribution not found'];
        }

        // Only original contributor can edit
        if ($contribution->contributor_id !== $contributorId) {
            return ['success' => false, 'error' => 'You can only edit your own contributions'];
        }

        // Can't edit approved or rejected contributions
        if (in_array($contribution->status, ['approved', 'rejected'])) {
            return ['success' => false, 'error' => 'Cannot edit a contribution that has been reviewed'];
        }

        // Get type for validation
        $type = DB::table('heritage_contribution_type')
            ->where('id', $contribution->contribution_type_id)
            ->first();

        // Validate content
        $validation = $this->validateContent($type->code, $content);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $newVersion = $contribution->version_number + 1;

        // Update contribution
        DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->update([
                'content' => json_encode($content),
                'version_number' => $newVersion,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        // Create version record
        DB::table('heritage_contribution_version')->insert([
            'contribution_id' => $contributionId,
            'version_number' => $newVersion,
            'content' => json_encode($content),
            'created_by' => $contributorId,
            'change_summary' => $changeSummary ?? 'Updated content',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => true,
            'data' => [
                'version_number' => $newVersion,
            ],
        ];
    }

    /**
     * Get contributions for an item.
     */
    public function getByItem(int $informationObjectId, ?string $status = 'approved', int $page = 1, int $limit = 20): array
    {
        $query = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->join('heritage_contributor as u', 'c.contributor_id', '=', 'u.id')
            ->where('c.information_object_id', $informationObjectId);

        if ($status) {
            $query->where('c.status', $status);
        }

        $total = $query->count();

        $contributions = $query
            ->orderBy('c.created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->select([
                'c.id',
                'c.content',
                'c.status',
                'c.created_at',
                'c.is_featured',
                't.code as type_code',
                't.name as type_name',
                't.icon as type_icon',
                't.color as type_color',
                'u.id as contributor_id',
                'u.display_name as contributor_name',
                'u.avatar_url as contributor_avatar',
                'u.trust_level as contributor_trust',
            ])
            ->get();

        $result = [];
        foreach ($contributions as $c) {
            $result[] = [
                'id' => $c->id,
                'content' => json_decode($c->content, true),
                'status' => $c->status,
                'created_at' => $c->created_at,
                'is_featured' => (bool) $c->is_featured,
                'type' => [
                    'code' => $c->type_code,
                    'name' => $c->type_name,
                    'icon' => $c->type_icon,
                    'color' => $c->type_color,
                ],
                'contributor' => [
                    'id' => $c->contributor_id,
                    'display_name' => $c->contributor_name,
                    'avatar_url' => $c->contributor_avatar,
                    'trust_level' => $c->contributor_trust,
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'contributions' => $result,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get contributions by a contributor.
     */
    public function getByContributor(int $contributorId, ?string $status = null, int $page = 1, int $limit = 20): array
    {
        $query = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.contributor_id', $contributorId);

        if ($status) {
            $query->where('c.status', $status);
        }

        $total = $query->count();

        $contributions = $query
            ->orderBy('c.created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->select([
                'c.id',
                'c.content',
                'c.status',
                'c.created_at',
                'c.reviewed_at',
                'c.review_notes',
                'c.points_awarded',
                't.code as type_code',
                't.name as type_name',
                't.icon as type_icon',
                't.color as type_color',
                'ioi.title as item_title',
                's.slug as item_slug',
            ])
            ->get();

        $result = [];
        foreach ($contributions as $c) {
            $result[] = [
                'id' => $c->id,
                'content' => json_decode($c->content, true),
                'status' => $c->status,
                'created_at' => $c->created_at,
                'reviewed_at' => $c->reviewed_at,
                'review_notes' => $c->review_notes,
                'points_awarded' => $c->points_awarded,
                'type' => [
                    'code' => $c->type_code,
                    'name' => $c->type_name,
                    'icon' => $c->type_icon,
                    'color' => $c->type_color,
                ],
                'item' => [
                    'title' => $c->item_title ?? 'Untitled',
                    'slug' => $c->item_slug,
                ],
            ];
        }

        // Get stats
        $stats = DB::table('heritage_contribution')
            ->where('contributor_id', $contributorId)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(points_awarded) as total_points'),
            ])
            ->first();

        return [
            'success' => true,
            'data' => [
                'contributions' => $result,
                'stats' => [
                    'total' => (int) $stats->total,
                    'pending' => (int) $stats->pending,
                    'approved' => (int) $stats->approved,
                    'rejected' => (int) $stats->rejected,
                    'total_points' => (int) $stats->total_points,
                ],
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get pending contributions for review.
     */
    public function getPendingReview(int $page = 1, ?string $typeFilter = null, int $limit = 20): array
    {
        $query = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->join('heritage_contributor as u', 'c.contributor_id', '=', 'u.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.status', 'pending');

        if ($typeFilter) {
            $query->where('t.code', $typeFilter);
        }

        $total = $query->count();

        $contributions = $query
            ->orderBy('c.created_at', 'asc') // Oldest first
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->select([
                'c.id',
                'c.information_object_id',
                'c.content',
                'c.status',
                'c.created_at',
                'c.version_number',
                't.id as type_id',
                't.code as type_code',
                't.name as type_name',
                't.icon as type_icon',
                't.color as type_color',
                't.points_value',
                'u.id as contributor_id',
                'u.display_name as contributor_name',
                'u.avatar_url as contributor_avatar',
                'u.trust_level as contributor_trust',
                'u.approved_contributions as contributor_approved',
                'ioi.title as item_title',
                's.slug as item_slug',
            ])
            ->get();

        $result = [];
        foreach ($contributions as $c) {
            $result[] = [
                'id' => $c->id,
                'content' => json_decode($c->content, true),
                'created_at' => $c->created_at,
                'version_number' => $c->version_number,
                'type' => [
                    'id' => $c->type_id,
                    'code' => $c->type_code,
                    'name' => $c->type_name,
                    'icon' => $c->type_icon,
                    'color' => $c->type_color,
                    'points_value' => $c->points_value,
                ],
                'contributor' => [
                    'id' => $c->contributor_id,
                    'display_name' => $c->contributor_name,
                    'avatar_url' => $c->contributor_avatar,
                    'trust_level' => $c->contributor_trust,
                    'approved_count' => $c->contributor_approved,
                ],
                'item' => [
                    'id' => $c->information_object_id,
                    'title' => $c->item_title ?? 'Untitled',
                    'slug' => $c->item_slug,
                ],
            ];
        }

        // Get counts by type
        $countsByType = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->where('c.status', 'pending')
            ->groupBy('t.code', 't.name', 't.icon')
            ->select(['t.code', 't.name', 't.icon', DB::raw('COUNT(*) as count')])
            ->get()
            ->toArray();

        return [
            'success' => true,
            'data' => [
                'contributions' => $result,
                'counts_by_type' => $countsByType,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get a single contribution for review.
     */
    public function getForReview(int $contributionId): array
    {
        $contribution = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->join('heritage_contributor as u', 'c.contributor_id', '=', 'u.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->leftJoin('digital_object as do', 'c.information_object_id', '=', 'do.object_id')
            ->where('c.id', $contributionId)
            ->select([
                'c.*',
                't.code as type_code',
                't.name as type_name',
                't.icon as type_icon',
                't.color as type_color',
                't.points_value',
                'u.id as contributor_id',
                'u.display_name as contributor_name',
                'u.avatar_url as contributor_avatar',
                'u.trust_level as contributor_trust',
                'u.approved_contributions as contributor_approved',
                'u.total_contributions as contributor_total',
                'ioi.title as item_title',
                'ioi.scope_and_content as item_description',
                's.slug as item_slug',
                'do.path as item_thumbnail_path',
                'do.name as item_thumbnail_name',
            ])
            ->first();

        if (!$contribution) {
            return ['success' => false, 'error' => 'Contribution not found'];
        }

        // Get version history
        $versions = DB::table('heritage_contribution_version as v')
            ->join('heritage_contributor as u', 'v.created_by', '=', 'u.id')
            ->where('v.contribution_id', $contributionId)
            ->orderBy('v.version_number', 'desc')
            ->select([
                'v.version_number',
                'v.content',
                'v.change_summary',
                'v.created_at',
                'u.display_name as created_by_name',
            ])
            ->get();

        // Build thumbnail URL
        $thumbnail = null;
        if ($contribution->item_thumbnail_path && $contribution->item_thumbnail_name) {
            $path = rtrim($contribution->item_thumbnail_path, '/');
            $basename = pathinfo($contribution->item_thumbnail_name, PATHINFO_FILENAME);
            $thumbnail = $path . '/' . $basename . '_142.jpg';
        }

        return [
            'success' => true,
            'data' => [
                'id' => $contribution->id,
                'content' => json_decode($contribution->content, true),
                'status' => $contribution->status,
                'created_at' => $contribution->created_at,
                'reviewed_at' => $contribution->reviewed_at,
                'review_notes' => $contribution->review_notes,
                'version_number' => $contribution->version_number,
                'type' => [
                    'code' => $contribution->type_code,
                    'name' => $contribution->type_name,
                    'icon' => $contribution->type_icon,
                    'color' => $contribution->type_color,
                    'points_value' => $contribution->points_value,
                ],
                'contributor' => [
                    'id' => $contribution->contributor_id,
                    'display_name' => $contribution->contributor_name,
                    'avatar_url' => $contribution->contributor_avatar,
                    'trust_level' => $contribution->contributor_trust,
                    'approved_count' => $contribution->contributor_approved,
                    'total_count' => $contribution->contributor_total,
                    'approval_rate' => $contribution->contributor_total > 0
                        ? round(($contribution->contributor_approved / $contribution->contributor_total) * 100, 1)
                        : 0,
                ],
                'item' => [
                    'id' => $contribution->information_object_id,
                    'title' => $contribution->item_title ?? 'Untitled',
                    'description' => $contribution->item_description,
                    'slug' => $contribution->item_slug,
                    'thumbnail' => $thumbnail,
                ],
                'versions' => $versions->map(fn ($v) => [
                    'version_number' => $v->version_number,
                    'content' => json_decode($v->content, true),
                    'change_summary' => $v->change_summary,
                    'created_at' => $v->created_at,
                    'created_by' => $v->created_by_name,
                ])->toArray(),
            ],
        ];
    }

    /**
     * Approve a contribution.
     */
    public function approve(int $contributionId, int $reviewerId, ?string $notes = null): array
    {
        $contribution = DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->first();

        if (!$contribution) {
            return ['success' => false, 'error' => 'Contribution not found'];
        }

        if ($contribution->status !== 'pending') {
            return ['success' => false, 'error' => 'Contribution has already been reviewed'];
        }

        DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $notes,
            ]);

        // Award points
        $this->awardPoints($contributionId);

        // Update contributor stats
        DB::table('heritage_contributor')
            ->where('id', $contribution->contributor_id)
            ->increment('approved_contributions');

        // Check and award badges
        $this->contributorService->calculateTrustLevel($contribution->contributor_id);
        $this->contributorService->checkAndAwardBadges($contribution->contributor_id);

        return ['success' => true];
    }

    /**
     * Reject a contribution.
     */
    public function reject(int $contributionId, int $reviewerId, ?string $notes = null): array
    {
        $contribution = DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->first();

        if (!$contribution) {
            return ['success' => false, 'error' => 'Contribution not found'];
        }

        if ($contribution->status !== 'pending') {
            return ['success' => false, 'error' => 'Contribution has already been reviewed'];
        }

        DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $notes,
            ]);

        // Update contributor stats
        DB::table('heritage_contributor')
            ->where('id', $contribution->contributor_id)
            ->increment('rejected_contributions');

        return ['success' => true];
    }

    /**
     * Get contribution statistics.
     */
    public function getStats(): array
    {
        $totals = DB::table('heritage_contribution')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
            ])
            ->first();

        $byType = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->groupBy('t.code', 't.name', 't.icon')
            ->select([
                't.code',
                't.name',
                't.icon',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN c.status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN c.status = "approved" THEN 1 ELSE 0 END) as approved'),
            ])
            ->get()
            ->toArray();

        $thisWeek = DB::table('heritage_contribution')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 week')))
            ->count();

        $thisMonth = DB::table('heritage_contribution')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 month')))
            ->count();

        return [
            'success' => true,
            'data' => [
                'total' => (int) $totals->total,
                'pending' => (int) $totals->pending,
                'approved' => (int) $totals->approved,
                'rejected' => (int) $totals->rejected,
                'by_type' => $byType,
                'this_week' => $thisWeek,
                'this_month' => $thisMonth,
            ],
        ];
    }

    /**
     * Get contribution types.
     */
    public function getTypes(): array
    {
        $types = DB::table('heritage_contribution_type')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->get();

        return ['success' => true, 'data' => $types->toArray()];
    }

    /**
     * Validate contribution content.
     */
    private function validateContent(string $typeCode, array $content): array
    {
        switch ($typeCode) {
            case 'transcription':
                if (empty($content['text']) || strlen(trim($content['text'])) < 10) {
                    return ['valid' => false, 'error' => 'Transcription must be at least 10 characters'];
                }
                break;

            case 'identification':
                if (empty($content['name']) || strlen(trim($content['name'])) < 2) {
                    return ['valid' => false, 'error' => 'Name is required for identification'];
                }
                break;

            case 'context':
                if (empty($content['text']) || strlen(trim($content['text'])) < 20) {
                    return ['valid' => false, 'error' => 'Context must be at least 20 characters'];
                }
                break;

            case 'correction':
                if (empty($content['field']) || empty($content['suggestion'])) {
                    return ['valid' => false, 'error' => 'Field and suggestion are required for corrections'];
                }
                break;

            case 'translation':
                if (empty($content['text']) || empty($content['target_language'])) {
                    return ['valid' => false, 'error' => 'Translation text and target language are required'];
                }
                break;

            case 'tag':
                if (empty($content['tags']) || !is_array($content['tags']) || count($content['tags']) === 0) {
                    return ['valid' => false, 'error' => 'At least one tag is required'];
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Award points for an approved contribution.
     */
    private function awardPoints(int $contributionId): void
    {
        $contribution = DB::table('heritage_contribution as c')
            ->join('heritage_contribution_type as t', 'c.contribution_type_id', '=', 't.id')
            ->where('c.id', $contributionId)
            ->select(['c.contributor_id', 't.points_value'])
            ->first();

        if (!$contribution) {
            return;
        }

        $points = $contribution->points_value;

        // Update contribution
        DB::table('heritage_contribution')
            ->where('id', $contributionId)
            ->update(['points_awarded' => $points]);

        // Update contributor
        DB::table('heritage_contributor')
            ->where('id', $contribution->contributor_id)
            ->increment('points', $points);
    }
}
