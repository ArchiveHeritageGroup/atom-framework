<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Access Request Service
 * 
 * Handles access request operations including approver checks
 */
class AccessRequestService
{
    /**
     * Check if a user is an approver for access requests
     *
     * @param int $userId
     * @return bool
     */
    public static function isApprover(int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        try {
            // Check if approvers table exists and user is in it
            $exists = DB::table('access_request_approver')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->exists();
            
            return $exists;
        } catch (\Exception $e) {
            // Table may not exist yet, or plugin not installed
            return false;
        }
    }

    /**
     * Get count of pending access requests
     *
     * @return int
     */
    public static function getPendingCount(): int
    {
        try {
            return DB::table('access_request')
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get pending requests for a specific approver
     *
     * @param int $userId
     * @return \Illuminate\Support\Collection
     */
    public static function getPendingForApprover(int $userId)
    {
        try {
            return DB::table('access_request')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Check if user has any pending requests
     *
     * @param int $userId
     * @return bool
     */
    public static function userHasPendingRequests(int $userId): bool
    {
        try {
            return DB::table('access_request')
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}
