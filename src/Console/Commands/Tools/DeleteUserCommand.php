<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Delete a user from AtoM.
 *
 * Removes the full entity chain: acl_user_group -> user -> actor_i18n -> actor -> object.
 */
class DeleteUserCommand extends BaseCommand
{
    protected string $name = 'tools:delete-user';
    protected string $description = 'Delete a user account';

    protected function configure(): void
    {
        $this->addArgument('username', 'The username to delete', true);
        $this->addOption('force', 'f', 'Skip confirmation prompt');
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

        $userId = $user->id;

        // Confirm deletion
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete user '{$username}' (ID: {$userId})?")) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        try {
            DB::connection()->getPdo()->beginTransaction();

            // Remove group memberships
            $groupCount = DB::table('acl_user_group')->where('user_id', $userId)->delete();
            if ($this->verbose) {
                $this->comment("  Removed {$groupCount} group membership(s).");
            }

            // Remove ACL permissions
            $aclCount = DB::table('acl_permission')->where('user_id', $userId)->delete();
            if ($this->verbose) {
                $this->comment("  Removed {$aclCount} ACL permission(s).");
            }

            // Remove user record
            DB::table('user')->where('id', $userId)->delete();
            if ($this->verbose) {
                $this->comment('  Removed user record.');
            }

            // Remove actor_i18n
            DB::table('actor_i18n')->where('id', $userId)->delete();
            if ($this->verbose) {
                $this->comment('  Removed actor_i18n record.');
            }

            // Remove actor record
            DB::table('actor')->where('id', $userId)->delete();
            if ($this->verbose) {
                $this->comment('  Removed actor record.');
            }

            // Remove object record
            DB::table('object')->where('id', $userId)->delete();
            if ($this->verbose) {
                $this->comment('  Removed object record.');
            }

            DB::connection()->getPdo()->commit();

            $this->success("User '{$username}' (ID: {$userId}) deleted successfully.");

            return 0;
        } catch (\Exception $e) {
            DB::connection()->getPdo()->rollBack();
            $this->error('Failed to delete user: ' . $e->getMessage());
            if ($this->verbose) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
