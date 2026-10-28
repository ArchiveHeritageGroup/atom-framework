<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Reset a user's password.
 *
 * Generates a new salt and stores password_hash(sha1(salt.password)), the form stock AtoM verifies.
 */
class ResetPasswordCommand extends BaseCommand
{
    protected string $name = 'tools:reset-password';
    protected string $description = 'Reset a user password';

    protected function configure(): void
    {
        $this->addArgument('username', 'The username to reset', true);
        $this->addOption('password', 'p', 'New password');
        $this->addOption('activate', null, 'Also activate the user account');
    }

    protected function handle(): int
    {
        $username = $this->argument('username');

        // Look up the user
        $user = DB::table('user')->where('username', $username)->first();
        if (!$user) {
            $this->error("User '{$username}' not found.");
            return 1;
        }

        // Resolve password
        $password = $this->option('password');
        if (!$password) {
            $password = $this->ask('New password');
        }

        if (empty($password)) {
            $this->error('Password is required.');
            return 1;
        }

        // A random salt and password_hash(sha1(salt.plaintext)): the shape stock
        // AtoM's QubitUser::checkCredentials() verifies. PasswordService::hash()
        // (Argon2id, empty salt) locked users out on stock AtoM sites such as RARI
        // (28 Oct 2026). PasswordService::verify() reads this as legacy, and sign-in
        // upgrades it to Argon2id where base verifies through the framework.
        $salt = bin2hex(random_bytes(16));

        $updates = [
            'password_hash' => password_hash(sha1($salt.$password), PASSWORD_DEFAULT),
            'salt' => $salt,
        ];

        // Optionally activate the account
        if ($this->option('activate')) {
            $updates['active'] = 1;
        }

        DB::table('user')
            ->where('username', $username)
            ->update($updates);

        $this->success("Password reset for user '{$username}'.");

        if ($this->option('activate')) {
            $this->info('  Account has been activated.');
        }

        return 0;
    }
}
