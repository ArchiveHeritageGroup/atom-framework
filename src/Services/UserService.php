<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;
use AtomFramework\Core\Security\PasswordService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * User Service - Replaces QubitUser.
 *
 * Provides user management using Laravel Query Builder.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class UserService
{
    /**
     * Get user by ID.
     *
     * Replaces: QubitUser::getById($id)
     */
    public static function getById(int $id): ?object
    {
        return DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->where('u.id', $id)
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'u.salt',
                'u.password_hash',
                'ai.authorized_form_of_name as name',
                's.slug'
            )
            ->first();
    }

    /**
     * Get user by slug.
     *
     * Replaces: QubitUser::getBySlug($slug)
     */
    public static function getBySlug(string $slug): ?object
    {
        return DB::table('user as u')
            ->join('slug as s', 'u.id', '=', 's.object_id')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->where('s.slug', $slug)
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'ai.authorized_form_of_name as name',
                's.slug'
            )
            ->first();
    }

    /**
     * Get user by username.
     */
    public static function getByUsername(string $username): ?object
    {
        return DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->where('u.username', $username)
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'u.salt',
                'u.password_hash',
                'ai.authorized_form_of_name as name',
                's.slug'
            )
            ->first();
    }

    /**
     * Get user by email.
     */
    public static function getByEmail(string $email): ?object
    {
        return DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->where('u.email', $email)
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'u.salt',
                'u.password_hash',
                'ai.authorized_form_of_name as name',
                's.slug'
            )
            ->first();
    }

    /**
     * Get all users.
     */
    public static function getAll(): Collection
    {
        return DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'u.active',
                'ai.authorized_form_of_name as name',
                's.slug'
            )
            ->get();
    }

    /**
     * Get user's groups.
     */
    public static function getGroups(int $userId): Collection
    {
        return DB::table('acl_user_group as ug')
            ->join('acl_group as g', 'ug.group_id', '=', 'g.id')
            ->leftJoin('acl_group_i18n as gi', function ($join) {
                $join->on('g.id', '=', 'gi.id')
                     ->where('gi.culture', '=', CultureHelper::getCulture());
            })
            ->where('ug.user_id', $userId)
            ->select(
                'g.id',
                'gi.name',
                'gi.description'
            )
            ->get();
    }

    /**
     * Check if user is active.
     */
    public static function isActive(int $userId): bool
    {
        $user = self::getById($userId);

        return $user && $user->active == 1;
    }

    /**
     * Authenticate user.
     */
    public static function authenticate(string $emailOrUsername, string $password): ?object
    {
        // Try email first, then username (matching QubitUser::checkCredentials)
        $user = self::getByEmail($emailOrUsername);
        if (!$user) {
            $user = self::getByUsername($emailOrUsername);
        }

        if (!$user || !$user->active) {
            return null;
        }

        // Verify supporting BOTH schemes; transparent rehash on success.
        // See PasswordService — password-hashing migration 2026-06-15.
        if (!PasswordService::verify($password, (string) $user->password_hash, $user->salt ?? '')) {
            return null;
        }

        if (PasswordService::needsUpgrade((string) $user->password_hash, $user->salt ?? '')) {
            try {
                DB::table('user')->where('id', $user->id)->update(PasswordService::hash($password));
            } catch (\Throwable $e) {
                // non-fatal: retried on next login
            }
        }

        return $user;
    }

    /**
     * Update password.
     */
    public static function updatePassword(int $userId, string $newPassword): bool
    {
        // Argon2id over plaintext, empty salt (migration 2026-06-15).
        return DB::table('user')
            ->where('id', $userId)
            ->update(PasswordService::hash($newPassword)) > 0;
    }
}
