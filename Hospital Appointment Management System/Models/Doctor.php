<?php
// Models/Doctor.php

class Doctor {
    public static function getTotalCount() {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
        return $stmt->fetchColumn();
    }

    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("
            SELECT d.*, u.email as user_email, u.is_active 
            FROM doctors d 
            JOIN users u ON d.user_id = u.user_id
        ");
        return $stmt->fetchAll();
    }

    public static function add($data) {
        global $pdo;
        try {
            $pdo->beginTransaction();

            // Insert into users
            $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT); // simple ID generation
            // Ensure unique ID
            while ($pdo->query("SELECT user_id FROM users WHERE user_id = '$userId'")->fetch()) {
                $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'doctor')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            // Insert into doctors
            $doctorId = 'D' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($pdo->query("SELECT doctor_id FROM doctors WHERE doctor_id = '$doctorId'")->fetch()) {
                $doctorId = 'D' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $initials = strtoupper(substr($data['name'], 0, 2));
            if (strpos($data['name'], 'Dr. ') === 0) {
                $initials = strtoupper(substr($data['name'], 4, 2));
            }

            $stmt = $pdo->prepare("INSERT INTO doctors (doctor_id, user_id, ic, name, specialization, qualification, consultation_fee, phone, email, initials, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $doctorId, 
                $userId, 
                $data['ic'], 
                $data['name'], 
                $data['specialization'], 
                $data['qualification'] ?? 'MD', 
                $data['consultation_fee'] ?? 50.00, 
                $data['phone'], 
                $data['email'], 
                $initials, 
                '#059669' // Default color
            ]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function delete($doctorId) {
        global $pdo;
        // User record will be deleted due to ON DELETE CASCADE if we delete user.
        // Wait, doctor table has CASCADE on user_id. So we should delete the user.
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctorId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            return $stmt->execute([$userId]);
        }
        return false;
    }
}
