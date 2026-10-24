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
                $ph = \AtomFramework\Core\Security\PasswordService::hash((string) $data['password']); // SECURITY: was raw unsalted sha1
                $userRow['password_hash'] = $ph['password_hash'];
                $userRow['salt'] = $ph['salt'];
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
        // Throws on failure: a token that was not stored must not be emailed.
        DB::table('ahg_password_reset')->updateOrInsert(
            ['user_id' => $userId],
            ['token_hash' => hash('sha256', $token), 'expires_at' => $expiry, 'created_at' => date('Y-m-d H:i:s')]
        );
    }

    public function findPasswordResetUserId(string $token): ?int
    {
        if ('' === $token) {
            return null;
        }
        $id = DB::table('ahg_password_reset')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->value('user_id');

        return null === $id ? null : (int) $id;
    }

    public function clearPasswordResetToken(int $userId): void
    {
        DB::table('ahg_password_reset')->where('user_id', $userId)->delete();
    }

    public function newUser(): object
    {
        return new \stdClass();
    }
}
