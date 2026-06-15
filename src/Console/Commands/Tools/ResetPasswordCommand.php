<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Reset a user's password.
 *
 * Generates a new salt, hashes the password with SHA1, and updates the user record.
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

        // Argon2id over plaintext, empty salt (migration 2026-06-15; fixes prior
        // raw-sha1 storage that AuthService could not verify).
        $ph = \AtomFramework\Core\Security\PasswordService::hash($password);

        $updates = [
            'password_hash' => $ph['password_hash'],
            'salt' => $ph['salt'],
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
