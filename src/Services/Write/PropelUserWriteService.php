<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: User write operations.
 *
 * Uses Propel (QubitUser) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode.
 *
 * AtoM entity inheritance chain for User:
 *   object -> actor -> user
 * In Propel mode, QubitUser handles this automatically.
 * In standalone mode, we must INSERT into all three tables.
 */
class PropelUserWriteService implements UserWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitUser', false)
            || class_exists('QubitUser');
    }

    public function createUser(array $data, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateUser($data, $culture);
        }

        return $this->dbCreateUser($data, $culture);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        if ($this->hasPropel) {
            $this->propelUpdatePassword($userId, $passwordHash);

            return;
        }

        $this->dbUpdatePassword($userId, $passwordHash);
    }

    public function savePasswordResetToken(int $userId, string $token, string $expiry): void
    {
        if ($this->hasPropel) {
            $this->propelSaveResetToken($userId, $token, $expiry);

            return;
        }

        $this->dbSaveResetToken($userId, $token, $expiry);
    }

    public function clearPasswordResetToken(int $userId): void
    {
        if ($this->hasPropel) {
            $this->propelClearResetToken($userId);

            return;
        }

        $this->dbClearResetToken($userId);
    }

    public function newUser(): object
    {
        if ($this->hasPropel) {
            return new \QubitUser();
        }

        return new \stdClass();
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateUser(array $data, string $culture): int
    {
        $user = new \QubitUser();
        $user->sourceCulture = $culture;

        foreach ($data as $key => $value) {
            if ('password' === $key) {
                $user->setPassword($value);
            } else {
                $user->{$key} = $value;
            }
        }

        $user->save();

        return (int) $user->id;
    }

    private function propelUpdatePassword(int $userId, string $passwordHash): void
    {
        $user = \QubitUser::getById($userId);
        if (null === $user) {
            return;
        }

        // Set the hash directly (already hashed by caller)
        $user->passwordHash = $passwordHash;
        $user->save();
    }

    private function propelSaveResetToken(int $userId, string $token, string $expiry): void
    {
        $user = \QubitUser::getById($userId);
        if (null === $user) {
            return;
        }

        $user->resetToken = $token;
        $user->resetTokenExpiry = $expiry;
        $user->save();
    }

    private function propelClearResetToken(int $userId): void
    {
        $user = \QubitUser::getById($userId);
        if (null === $user) {
            return;
        }

        $user->resetToken = null;
        $user->resetTokenExpiry = null;
        $user->save();
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateUser(array $data, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        // Step 1: Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Step 2: Insert into actor table (User extends Actor)
        $actorData = [
            'id' => $objectId,
            'source_culture' => $culture,
        ];
        DB::table('actor')->insert($actorData);

        // Actor i18n for authorized_form_of_name (username)
        if (!empty($data['username'])) {
            DB::table('actor_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'authorized_form_of_name' => $data['username'],
            ]);
        }

        // Step 3: Insert into user table
        $userRow = [
            'id' => $objectId,
            'username' => $data['username'] ?? null,
            'email' => $data['email'] ?? null,
            'active' => $data['active'] ?? 1,
        ];

        // Handle password hashing
        if (!empty($data['password'])) {
            $userRow['password_hash'] = sha1($data['password']);
            $userRow['salt'] = null;
        } elseif (!empty($data['passwordHash'])) {
            $userRow['password_hash'] = $data['passwordHash'];
        }

        DB::table('user')->insert($userRow);

        return $objectId;
    }

    private function dbUpdatePassword(int $userId, string $passwordHash): void
    {
        DB::table('user')->where('id', $userId)->update([
            'password_hash' => $passwordHash,
        ]);

        DB::table('object')->where('id', $userId)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function dbSaveResetToken(int $userId, string $token, string $expiry): void
    {
        $updates = [
            'reset_token' => $token,
            'reset_token_expiry' => $expiry,
        ];

        // Only update columns that exist
        try {
            DB::table('user')->where('id', $userId)->update($updates);
        } catch (\Exception $e) {
            // Columns may not exist in standalone mode; log and continue
            error_log('UserWriteService: Could not save reset token: ' . $e->getMessage());
        }
    }

    private function dbClearResetToken(int $userId): void
    {
        try {
            DB::table('user')->where('id', $userId)->update([
                'reset_token' => null,
                'reset_token_expiry' => null,
            ]);
        } catch (\Exception $e) {
            error_log('UserWriteService: Could not clear reset token: ' . $e->getMessage());
        }
    }
}
