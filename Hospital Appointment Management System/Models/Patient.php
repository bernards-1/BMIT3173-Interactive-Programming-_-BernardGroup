<?php
// Models/Patient.php

class Patient {
    public static function getTotalCount() {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
        return $stmt->fetchColumn();
    }

    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("
            SELECT p.*, u.email as user_email, u.is_active 
            FROM patients p 
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

            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'patient')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            $patientId = 'P' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($pdo->query("SELECT patient_id FROM patients WHERE patient_id = '$patientId'")->fetch()) {
                $patientId = 'P' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("INSERT INTO patients (patient_id, user_id, ic, full_name, date_of_birth, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, 
                $userId, 
                $data['ic'], 
                $data['full_name'], 
                $data['date_of_birth'], 
                $data['gender'], 
                $data['phone']
            ]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function delete($patientId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            return $stmt->execute([$userId]);
        }
        return false;
    }

    public static function update($patientId, $data) {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $stmt->execute([$patientId]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                // Update users table (email and is_active status)
                $stmt = $pdo->prepare("UPDATE users SET email = ?, is_active = ? WHERE user_id = ?");
                $stmt->execute([$data['email'], $data['is_active'], $userId]);

                // Update patients table
                $stmt = $pdo->prepare("UPDATE patients SET ic = ?, full_name = ?, date_of_birth = ?, gender = ?, phone = ? WHERE patient_id = ?");
                $stmt->execute([
                    $data['ic'], 
                    $data['full_name'], 
                    $data['date_of_birth'], 
                    $data['gender'], 
                    $data['phone'],
                    $patientId
                ]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
