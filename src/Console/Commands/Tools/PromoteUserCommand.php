<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Promote a user to administrator.
 *
 * Adds the user to the admin group (group_id=100) if not already a member.
 */
class PromoteUserCommand extends BaseCommand
{
    protected string $name = 'tools:promote-user';
    protected string $description = 'Promote a user to administrator';

    /** @var int Admin group ID in AtoM */
    private const ADMIN_GROUP_ID = 100;

    protected function configure(): void
    {
        $this->addArgument('username', 'The username to promote', true);
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

        // Check if already admin
        $existing = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', self::ADMIN_GROUP_ID)
            ->first();

        if ($existing) {
            $this->warning("User '{$username}' is already an administrator.");
            return 0;
        }

        // Add to admin group
        DB::table('acl_user_group')->insert([
            'user_id' => $userId,
            'group_id' => self::ADMIN_GROUP_ID,
        ]);

        $this->success("User '{$username}' (ID: {$userId}) promoted to administrator.");

        return 0;
    }
}
