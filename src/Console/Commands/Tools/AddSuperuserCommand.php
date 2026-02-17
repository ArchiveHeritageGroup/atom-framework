<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Add a new superuser (administrator) to AtoM.
 *
 * Creates the full entity chain: object -> actor -> user -> acl_user_group.
 */
class AddSuperuserCommand extends BaseCommand
{
    protected string $name = 'tools:add-superuser';
    protected string $description = 'Add a new superuser (administrator) account';

    /** @var int Admin group ID in AtoM */
    private const ADMIN_GROUP_ID = 100;

    protected function configure(): void
    {
        $this->addArgument('username', 'The username to create', true);
        $this->addOption('email', 'e', 'Email address');
        $this->addOption('password', 'p', 'Password');
        $this->addOption('demo', null, 'Use default demo values (demo@example.com / demo123)');
    }

    protected function handle(): int
    {
        $username = $this->argument('username');

        // Check if username already exists
        $existing = DB::table('user')->where('username', $username)->first();
        if ($existing) {
            $this->error("User '{$username}' already exists.");
            return 1;
        }

        // Resolve email and password
        if ($this->option('demo')) {
            $email = 'demo@example.com';
            $password = 'demo123';
            $this->info("Using demo credentials: {$email} / demo123");
        } else {
            $email = $this->option('email');
            $password = $this->option('password');

            if (!$email) {
                $email = $this->ask('Email address');
            }
            if (!$password) {
                $password = $this->ask('Password');
            }
        }

        if (empty($email) || empty($password)) {
            $this->error('Email and password are required.');
            return 1;
        }

        // Generate salt and password hash
        $salt = bin2hex(random_bytes(32));
        $passwordHash = sha1($salt . $password);

        $now = date('Y-m-d H:i:s');

        try {
            DB::connection()->getPdo()->beginTransaction();

            // Step 1: Insert into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitUser',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);

            // Step 2: Insert into actor table (user extends actor)
            DB::table('actor')->insert([
                'id' => $objectId,
                'entity_type_id' => null,
                'description_status_id' => null,
                'description_detail_id' => null,
                'description_identifier' => null,
                'source_standard' => null,
                'corporate_body_identifiers' => null,
            ]);

            // Step 3: Insert into actor_i18n for the display name
            DB::table('actor_i18n')->insert([
                'id' => $objectId,
                'culture' => \AtomExtensions\Helpers\CultureHelper::getCulture(),
                'authorized_form_of_name' => $username,
            ]);

            // Step 4: Insert into user table
            DB::table('user')->insert([
                'id' => $objectId,
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'salt' => $salt,
                'active' => 1,
            ]);

            // Step 5: Grant admin group
            DB::table('acl_user_group')->insert([
                'user_id' => $objectId,
                'group_id' => self::ADMIN_GROUP_ID,
            ]);

            DB::connection()->getPdo()->commit();

            $this->success("Superuser '{$username}' created successfully (ID: {$objectId}).");
            $this->info("  Email: {$email}");
            $this->info("  Group: Administrator (ID: " . self::ADMIN_GROUP_ID . ')');

            return 0;
        } catch (\Exception $e) {
            DB::connection()->getPdo()->rollBack();
            $this->error('Failed to create superuser: ' . $e->getMessage());
            if ($this->verbose) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
