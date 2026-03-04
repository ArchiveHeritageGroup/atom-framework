<?php

/**
 * QubitUser Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\UserService directly
 */

if (!class_exists('QubitUser', false)) {
    class QubitUser
    {
        // Field name constants used in queries and forms
        public const EMAIL = 'email';
        public const USERNAME = 'username';
        public const RESET_TOKEN = 'reset_token';
        public const ID = 'id';

        public $id;
        public $username;
        public $email;
        public $slug;

        public static function getBySlug(string $slug): ?self
        {
            $result = \AtomExtensions\Services\UserService::getBySlug($slug);
            if (!$result) {
                return null;
            }

            return self::fromResult($result);
        }

        public static function getById(int $id): ?self
        {
            $result = \AtomExtensions\Services\UserService::getById($id);
            if (!$result) {
                return null;
            }

            return self::fromResult($result);
        }

        /**
         * Get a single user matching criteria.
         *
         * @param array $criteria Column => value pairs
         *
         * @return static|null
         */
        public static function getOne($criteria): ?self
        {
            try {
                $query = \Illuminate\Database\Capsule\Manager::table('user');
                foreach ($criteria as $col => $val) {
                    $query->where($col, $val);
                }
                $result = $query->first();

                return $result ? self::fromResult($result) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get users matching criteria.
         *
         * @param array $criteria Column => value pairs
         *
         * @return array
         */
        public static function get($criteria): array
        {
            try {
                $query = \Illuminate\Database\Capsule\Manager::table('user');
                foreach ($criteria as $col => $val) {
                    $query->where($col, $val);
                }
                $results = $query->get();

                $users = [];
                foreach ($results as $row) {
                    $users[] = self::fromResult($row);
                }

                return $users;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get the system admin user (first superuser).
         *
         * @return static|null
         */
        public static function getSystemAdmin(): ?self
        {
            try {
                $result = \Illuminate\Database\Capsule\Manager::table('user')
                    ->orderBy('id')
                    ->first();

                return $result ? self::fromResult($result) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Create a QubitUser from a database result object.
         */
        private static function fromResult(object $result): self
        {
            $user = new self();
            $user->id = $result->id ?? null;
            $user->username = $result->username ?? null;
            $user->email = $result->email ?? null;
            $user->slug = $result->slug ?? null;

            return $user;
        }
    }
}
