<?php
// Models/Patient.php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/User.php';

class Patient extends Model {
    protected static $table = 'patients';
    protected static $primaryKey = 'patient_id';

    /**
     * @var User|null In-memory object reference to the associated User entity
     */
    private ?User $userInstance = null;

    /**
     * Object Reference Getter: retrieves associated User entity instance.
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

    // ── Static helpers & Business Operations ─────────────────────

    public static function getTotalCount() {
        $db = static::getDb();
        return (int) $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    }

    /**
     * All patients under a given doctor's care, with per-patient visit
     * summaries (latest diagnosis, visit count, last/next visit dates).
     * Cross-table JOIN + correlated aggregate subqueries — kept inside the Model.
     */
    public static function getVisitSummariesForDoctor(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                p.patient_id,
                p.full_name,
                p.gender,
                p.date_of_birth,
                p.phone,
                u.email,
                (
                    SELECT mr.diagnosis 
                    FROM medical_records mr 
                    WHERE mr.patient_id = p.patient_id 
                    ORDER BY mr.created_at DESC LIMIT 1
                ) as condition_name,
                (
                    SELECT COUNT(*) 
                    FROM appointments a 
                    WHERE a.patient_id = p.patient_id AND a.doctor_id = ?
                ) as visits_count,
                (
                    SELECT MAX(appointment_date) 
                    FROM appointments a 
                    WHERE a.patient_id = p.patient_id AND a.doctor_id = ? AND a.appointment_date <= CURDATE()
                ) as last_visit_date,
                (
                    SELECT MIN(appointment_date) 
                    FROM appointments a 
                    WHERE a.patient_id = p.patient_id AND a.doctor_id = ? AND a.appointment_date >= CURDATE() AND a.status = \'Scheduled\'
                ) as next_visit_date
            FROM patients p
            JOIN users u ON p.user_id = u.user_id
            ORDER BY p.full_name ASC
        ');
        $stmt->execute([$doctorId, $doctorId, $doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cross-table listing (patients JOIN users) — kept as raw SQL since
     * the lightweight ActiveRecord layer does not model joins.
     */
    public static function getAll() {
        $db = static::getDb();
        $stmt = $db->query("
            SELECT p.*, u.email as user_email, u.is_active 
            FROM patients p 
            JOIN users u ON p.user_id = u.user_id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function generateUniqueId(string $prefix, string $table, string $column): string {
        $db = static::getDb();
        do {
            $candidate = $prefix . str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT);
            $check = $db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
            $check->execute([$candidate]);
        } while ($check->fetch());
        return $candidate;
    }

    public static function add($data) {
        $db = static::getDb();
        try {
            $db->beginTransaction();

            $userId = self::generateUniqueId('U', 'users', 'user_id');

            $stmt = $db->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'patient')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            $patientId = self::generateUniqueId('P', 'patients', 'patient_id');

            // ORM insert instead of hand-rolled INSERT
            $patient = new self([
                'patient_id'    => $patientId,
                'user_id'       => $userId,
                'ic'            => $data['ic'],
                'full_name'     => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender'        => $data['gender'],
                'phone'         => $data['phone'],
            ], false);
            $patient->save();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Delete cascades to the linked users row, matching Doctor::delete().
     */
    public static function delete($patientId = null): bool {
        if (!$patientId) {
            return false;
        }
        $patient = self::find($patientId);
        if (!$patient) {
            return false;
        }
        $db = static::getDb();
        return $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$patient->user_id]);
    }

    /**
     * Update profile across users + patients using find() + save().
     */
    public static function update($patientId, $data) {
        $db = static::getDb();
        try {
            $db->beginTransaction();

            $patient = self::find($patientId);
            if (!$patient) {
                $db->rollBack();
                return false;
            }

            $db->prepare("UPDATE users SET email = ?, is_active = ? WHERE user_id = ?")
               ->execute([$data['email'], $data['is_active'], $patient->user_id]);

            $patient->ic = $data['ic'];
            $patient->full_name = $data['full_name'];
            $patient->date_of_birth = $data['date_of_birth'];
            $patient->gender = $data['gender'];
            $patient->phone = $data['phone'];
            $patient->save(); // ORM update

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }
}
