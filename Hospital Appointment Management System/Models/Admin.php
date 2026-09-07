<?php
// Models/Admin.php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/User.php';

class Admin extends Model {
    protected static $table = 'admins';
    protected static $primaryKey = 'admin_id';

    private ?User $userInstance = null;

    public function getUser(): ?User {
        if ($this->userInstance === null && !empty($this->user_id)) {
            $this->userInstance = User::find($this->user_id);
        }
        return $this->userInstance;
    }

    /**
     * Find the admin row for a given user_id (ORM lookup by non-PK column).
     */
    public static function findByUserId(string $userId): ?self {
        $results = static::where('user_id', $userId);
        return $results[0] ?? null;
    }

    /**
     * Admin profile joined with account email/username — cross-table, kept raw.
     */
    public static function profileByUserId(string $userId): ?array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT a.admin_id, a.full_name, a.phone, a.position, u.email, u.username
            FROM admins a
            JOIN users u ON u.user_id = a.user_id
            WHERE a.user_id = ?
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
