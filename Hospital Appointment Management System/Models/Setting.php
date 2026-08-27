<?php
require_once __DIR__ . '/../db.php';

class Setting {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAllSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (PDOException $e) {
            // Return empty array if table doesn't exist yet
            return [];
        }
    }

    public function updateSettings($data) {
        $stmt = $this->pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($data as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        return true;
    }
}
