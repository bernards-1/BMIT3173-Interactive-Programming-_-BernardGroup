<?php
// Models/User.php

class User {
    public $user_id;
    public $username;
    public $email;
    public $password;
    public $role;
    public $is_active;

    /**
     * Find a user by their email address using secure prepared statements.
     * 
     * @param string $email
     * @return User|null
     */
    public static function findByEmail($email) {
        global $pdo;
        
        $stmt = $pdo->prepare('SELECT user_id, username, email, password, role, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        
        if ($row) {
            $user = new User();
            $user->user_id = $row['user_id'];
            $user->username = $row['username'];
            $user->email = $row['email'];
            $user->password = $row['password'];
            $user->role = $row['role'];
            $user->is_active = $row['is_active'];
            return $user;
        }
        
        return null;
    }

    /**
     * Find a user by their user_id.
     * 
     * @param string $user_id
     * @return User|null
     */
    public static function find($user_id) {
        global $pdo;
        
        $stmt = $pdo->prepare('SELECT user_id, username, email, password, role, is_active FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        
        if ($row) {
            $user = new User();
            $user->user_id = $row['user_id'];
            $user->username = $row['username'];
            $user->email = $row['email'];
            $user->password = $row['password'];
            $user->role = $row['role'];
            $user->is_active = $row['is_active'];
            return $user;
        }
        
        return null;
    }
}
