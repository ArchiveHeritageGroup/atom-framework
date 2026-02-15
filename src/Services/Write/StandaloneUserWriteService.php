<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone user write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the full AtoM user inheritance chain:
 *   object -> actor -> actor_i18n -> user
 *
 * User extends Actor, which extends Object. All three tables plus
 * actor_i18n must be populated when creating a user.
 */
class StandaloneUserWriteService implements UserWriteServiceInterface
{
    use EntityWriteTrait;

    public function createUser(array $data, string $culture = 'en'): int
    {
        return DB::transaction(function () use ($data, $culture) {
            $now = date('Y-m-d H:i:s');

            // Step 1: Insert into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitUser',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Step 2: Insert into actor table (User extends Actor)
            DB::table('actor')->insert([
                'id' => $objectId,
                'source_culture' => $culture,
            ]);

            // Step 3: Actor i18n for authorized_form_of_name (username)
            if (!empty($data['username'])) {
                DB::table('actor_i18n')->insert([
                    'id' => $objectId,
                    'culture' => $culture,
                    'authorized_form_of_name' => $data['username'],
                ]);
            }

            // Step 4: Insert into user table
            $userRow = [
                'id' => $objectId,
                'username' => $data['username'] ?? null,
                'email' => $data['email'] ?? null,
                'active' => $data['active'] ?? 1,
            ];

            if (!empty($data['password'])) {
                $userRow['password_hash'] = sha1($data['password']);
                $userRow['salt'] = null;
            } elseif (!empty($data['passwordHash'])) {
                $userRow['password_hash'] = $data['passwordHash'];
            }

            DB::table('user')->insert($userRow);

            // Step 5: Generate slug
            if (!empty($data['username'])) {
                $this->autoSlug($objectId, ['name' => $data['username']]);
            }

            return $objectId;
        });
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        DB::table('user')->where('id', $userId)->update([
            'password_hash' => $passwordHash,
        ]);

        DB::table('object')->where('id', $userId)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function savePasswordResetToken(int $userId, string $token, string $expiry): void
    {
        try {
            DB::table('user')->where('id', $userId)->update([
                'reset_token' => $token,
                'reset_token_expiry' => $expiry,
            ]);
        } catch (\Exception $e) {
            error_log('StandaloneUserWriteService: Could not save reset token: ' . $e->getMessage());
        }
    }

    public function clearPasswordResetToken(int $userId): void
    {
        try {
            DB::table('user')->where('id', $userId)->update([
                'reset_token' => null,
                'reset_token_expiry' => null,
            ]);
        } catch (\Exception $e) {
            error_log('StandaloneUserWriteService: Could not clear reset token: ' . $e->getMessage());
        }
    }

    public function newUser(): object
    {
        return new \stdClass();
    }
}
