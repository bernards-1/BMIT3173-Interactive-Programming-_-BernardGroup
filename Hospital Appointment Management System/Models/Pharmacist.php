<?php
/**
 * Pharmacist Model
 */

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/User.php';

class Pharmacist extends Model {
    protected static $table = 'pharmacists';
    protected static $primaryKey = 'pharmacist_id';

    /**
     * @var User|null In-memory object reference to the associated User entity
     */
    private ?User $userInstance = null;

    /**
     * Object Reference Getter: retrieves associated User entity instance.
     * Fulfills report Section 3: Use object references instead of foreign keys.
     */
    public function getUser(): ?User {
        if ($this->userInstance === null && !empty($this->user_id)) {
            $this->userInstance = User::find($this->user_id);
        }
        return $this->userInstance;
    }

    /**
     * Object Reference Setter: binds associated User entity instance.
     */
    public function setUser(User $user): void {
        $this->userInstance = $user;
        $this->user_id = $user->user_id;
    }

    // ── Legacy / Static helpers for Admin & Subsystems ───────────

    public static function getAll() {
        $db = static::getDb();
        $stmt = $db->query("
            SELECT p.*, u.email as user_email, u.is_active 
            FROM pharmacists p 
            JOIN users u ON p.user_id = u.user_id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add($data) {
        $db = static::getDb();
        try {
            $db->beginTransaction();

            $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($db->query("SELECT user_id FROM users WHERE user_id = '$userId'")->fetch()) {
                $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $db->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'pharmacist')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            $pharmacistId = 'PH' . str_pad(rand(1, 99), 3, '0', STR_PAD_LEFT);
            while ($db->query("SELECT pharmacist_id FROM pharmacists WHERE pharmacist_id = '$pharmacistId'")->fetch()) {
                $pharmacistId = 'PH' . str_pad(rand(1, 99), 3, '0', STR_PAD_LEFT);
            }

            $pharmacist = new self([
                'pharmacist_id'  => $pharmacistId,
                'user_id'        => $userId,
                'ic'             => $data['ic'],
                'full_name'      => $data['full_name'],
                'phone'          => $data['phone'],
                'license_number' => $data['license_number'] ?? 'LIC-1234',
                'qualification'  => $data['qualification']
            ], false);

            $pharmacist->save();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function delete($pharmacistId = null): bool {
        if (!$pharmacistId) {
            return false;
        }
        $db = static::getDb();
        $stmt = $db->prepare("SELECT user_id FROM pharmacists WHERE pharmacist_id = ?");
        $stmt->execute([$pharmacistId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $delStmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            return $delStmt->execute([$userId]);
        }
        return false;
    }
}
