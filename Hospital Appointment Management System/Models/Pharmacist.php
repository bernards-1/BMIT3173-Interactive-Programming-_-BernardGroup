<?php
// Models/Pharmacist.php

class Pharmacist {
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("
            SELECT p.*, u.email as user_email, u.is_active 
            FROM pharmacists p 
            JOIN users u ON p.user_id = u.user_id
        ");
        return $stmt->fetchAll();
    }

    public static function add($data) {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($pdo->query("SELECT user_id FROM users WHERE user_id = '$userId'")->fetch()) {
                $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'pharmacist')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            $pharmacistId = 'PH' . str_pad(rand(1, 99), 3, '0', STR_PAD_LEFT);
            while ($pdo->query("SELECT pharmacist_id FROM pharmacists WHERE pharmacist_id = '$pharmacistId'")->fetch()) {
                $pharmacistId = 'PH' . str_pad(rand(1, 99), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("INSERT INTO pharmacists (pharmacist_id, user_id, ic, full_name, phone, license_number, qualification) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $pharmacistId, 
                $userId, 
                $data['ic'], 
                $data['full_name'], 
                $data['phone'], 
                $data['license_number'] ?? 'LIC-1234',
                $data['qualification']
            ]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function delete($pharmacistId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT user_id FROM pharmacists WHERE pharmacist_id = ?");
        $stmt->execute([$pharmacistId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            return $stmt->execute([$userId]);
        }
        return false;
    }
}
