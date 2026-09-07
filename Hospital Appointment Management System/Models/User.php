<?php
// Models/User.php

require_once __DIR__ . '/../core/Model.php';

class User extends Model {
    protected static $table = 'users';
    protected static $primaryKey = 'user_id';

    /**
     * Find a user by their email address (ORM lookup by non-primary-key column).
     *
     * @param string $email
     * @return User|null
     */
    public static function findByEmail($email): ?self {
        $results = static::where('email', $email);
        return $results[0] ?? null;
    }

    /**
     * Whether the given username is already used by a different user
     * (excludes the given user id). Aggregate COUNT — kept inside the Model.
     */
    public static function usernameTakenByOther(string $username, string $excludeUserId): bool {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $excludeUserId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Whether the given email is already used by a different user
     * (excludes the given user id). Aggregate COUNT — kept inside the Model.
     */
    public static function emailTakenByOther(string $email, string $excludeUserId): bool {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $excludeUserId]);
        return $stmt->fetchColumn() > 0;
    }
}
