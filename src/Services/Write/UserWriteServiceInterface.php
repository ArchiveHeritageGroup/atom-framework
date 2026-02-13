<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for user write operations.
 *
 * Covers: creating users (registration), updating passwords,
 * saving password reset tokens, and factory methods for
 * unsaved user objects.
 *
 * The PropelAdapter wraps QubitUser for Symfony mode.
 * Falls back to Laravel Query Builder for standalone mode.
 */
interface UserWriteServiceInterface
{
    /**
     * Create a new user (register). Returns the new user ID.
     *
     * Data may include: email, username, password (hashed), active, groups, etc.
     * In Propel mode, creates the full object/actor/user chain via QubitUser.
     * In standalone mode, INSERTs into object, actor, and user tables.
     *
     * @param array  $data    User attributes
     * @param string $culture Culture code
     *
     * @return int The new user ID
     */
    public function createUser(array $data, string $culture = 'en'): int;

    /**
     * Update user password (password reset confirm).
     *
     * In Propel mode, loads the QubitUser, calls setPassword(), and saves.
     * In standalone mode, updates the password_hash column directly.
     *
     * @param int    $userId       User ID
     * @param string $passwordHash The hashed password
     */
    public function updatePassword(int $userId, string $passwordHash): void;

    /**
     * Save a password reset token for a user.
     *
     * Stores the token and expiry on the user record.
     * In Propel mode, sets properties and saves. In standalone mode,
     * uses Laravel DB to update the user row.
     *
     * @param int    $userId User ID
     * @param string $token  Reset token string
     * @param string $expiry Expiry datetime string (Y-m-d H:i:s)
     */
    public function savePasswordResetToken(int $userId, string $token, string $expiry): void;

    /**
     * Clear the password reset token for a user.
     *
     * Called after a successful password reset to invalidate the token.
     *
     * @param int $userId User ID
     */
    public function clearPasswordResetToken(int $userId): void;

    /**
     * Get a new unsaved QubitUser object (for form binding in Propel mode).
     *
     * In Propel mode, returns a new QubitUser instance.
     * In standalone mode, returns a stdClass.
     *
     * @return object Unsaved QubitUser or stdClass
     */
    public function newUser(): object;
}
