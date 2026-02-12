<?php

namespace AtomFramework\Services;

use AtomExtensions\Helpers\CultureHelper;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone authentication service for Heratio.
 *
 * Replicates QubitUser::checkCredentials() with dual-layer password
 * verification: SHA1(salt + password) -> password_verify(sha1Hash, argon2iHash).
 *
 * AtoM stores passwords as:
 *   salt: random hex string
 *   password_hash: password_hash(sha1(salt . plaintext), PASSWORD_DEFAULT)
 *
 * The inner SHA1 layer is legacy; the outer Argon2i/Bcrypt layer was added
 * in AtoM 2.x. Both layers must be checked for backward compatibility.
 */
class AuthService
{
    /**
     * Authenticate a user by email or username.
     *
     * Tries email first, then username (matching QubitUser::checkCredentials order).
     *
     * @return object|null User object on success, null on failure
     */
    public static function authenticate(string $emailOrUsername, string $password): ?object
    {
        // Try email first
        $user = self::findUser($emailOrUsername);

        if (!$user) {
            return null;
        }

        if (!$user->active) {
            return null;
        }

        // Dual-layer verification: SHA1(salt + password) -> password_verify
        $sha1Hash = sha1($user->salt . $password);

        if (!password_verify($sha1Hash, $user->password_hash)) {
            return null;
        }

        return $user;
    }

    /**
     * Find user by email or username.
     */
    private static function findUser(string $emailOrUsername): ?object
    {
        $culture = class_exists(CultureHelper::class) ? CultureHelper::getCulture() : 'en';

        // Try email first
        $user = DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->where('u.email', $emailOrUsername)
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

        if ($user) {
            return $user;
        }

        // Fall back to username
        return DB::table('user as u')
            ->join('actor as a', 'u.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'u.id', '=', 's.object_id')
            ->where('u.username', $emailOrUsername)
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
     * Get group names for a user (EN culture).
     *
     * @return string[] Array of group names
     */
    public static function getGroupNames(int $userId): array
    {
        return DB::table('acl_user_group as ug')
            ->join('acl_group_i18n as gi', function ($join) {
                $join->on('ug.group_id', '=', 'gi.id')
                    ->where('gi.culture', '=', 'en');
            })
            ->where('ug.user_id', $userId)
            ->pluck('gi.name')
            ->toArray();
    }

    /**
     * Get group IDs for a user.
     *
     * @return int[] Array of group IDs
     */
    public static function getGroupIds(int $userId): array
    {
        return DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();
    }
}
