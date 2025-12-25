<?php

/**
 * QubitUser Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\UserService directly
 */

use AtomExtensions\Services\UserService;

class QubitUser
{
    public $id;
    public $username;
    public $email;
    public $slug;

    public static function getBySlug(string $slug): ?self
    {
        $result = UserService::getBySlug($slug);
        if (!$result) return null;
        
        $user = new self();
        $user->id = $result->id;
        $user->username = $result->username;
        $user->email = $result->email;
        $user->slug = $result->slug;
        return $user;
    }

    public static function getById(int $id): ?self
    {
        $result = UserService::getById($id);
        if (!$result) return null;
        
        $user = new self();
        $user->id = $result->id;
        $user->username = $result->username;
        $user->email = $result->email;
        $user->slug = $result->slug;
        return $user;
    }
}
