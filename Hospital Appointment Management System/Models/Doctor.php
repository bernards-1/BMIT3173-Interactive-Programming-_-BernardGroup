<?php
// Models/Doctor.php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/User.php';

class Doctor extends Model {
    protected static $table = 'doctors';
    protected static $primaryKey = 'doctor_id';

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

    /**
     * Object Reference: retrieves appointments associated with this Doctor.
     */
    public function getAppointments(): array {
        require_once __DIR__ . '/Appointment.php';
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    // ── Static helpers & Business Operations ─────────────────────

    public static function getTotalCount() {
        $db = static::getDb();
        $stmt = $db->query("SELECT COUNT(*) FROM doctors");
        return (int)$stmt->fetchColumn();
    }

    public static function getAll() {
        $db = static::getDb();
        $stmt = $db->query("
            SELECT d.*, u.email as user_email, u.is_active 
            FROM doctors d 
            JOIN users u ON d.user_id = u.user_id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add($data) {
        $db = static::getDb();
        try {
            $db->beginTransaction();

            // Insert into users
            $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($db->query("SELECT user_id FROM users WHERE user_id = '$userId'")->fetch()) {
                $userId = 'U' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $stmt = $db->prepare("INSERT INTO users (user_id, username, email, password, role) VALUES (?, ?, ?, ?, 'doctor')");
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->execute([$userId, $data['username'], $data['email'], $hashedPassword]);

            // Insert into doctors using ORM model
            $doctorId = 'D' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            while ($db->query("SELECT doctor_id FROM doctors WHERE doctor_id = '$doctorId'")->fetch()) {
                $doctorId = 'D' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $initials = strtoupper(substr($data['name'], 0, 2));
            if (strpos($data['name'], 'Dr. ') === 0) {
                $initials = strtoupper(substr($data['name'], 4, 2));
            }

            $doctor = new self([
                'doctor_id'        => $doctorId,
                'user_id'          => $userId,
                'ic'               => $data['ic'],
                'name'             => $data['name'],
                'specialization'   => $data['specialization'],
                'qualification'    => $data['qualification'] ?? 'MD',
                'consultation_fee' => $data['consultation_fee'] ?? 50.00,
                'phone'            => $data['phone'],
                'email'            => $data['email'],
                'initials'         => $initials,
                'color'            => '#059669'
            ], false);

            $doctor->save();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function delete($doctorId = null): bool {
        if (!$doctorId) return false;
        $db = static::getDb();
        $stmt = $db->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctorId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $delStmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            return $delStmt->execute([$userId]);
        }
        return false;
    }

    /**
     * Update doctor profile using ORM persistence ($doctor->save())
     */
    public static function updateProfile($userId, $doctorId, $data) {
        $db = static::getDb();
        try {
            $db->beginTransaction();
            
            $name = $data['name'];
            $specialization = $data['specialization'];
            $qualification = $data['qualification'];
            $phone = $data['phone'];
            $email = $data['email'];
            $consultation_fee = floatval($data['consultation_fee']);
            $initials = $data['initials'];
            $color = $data['color'];
            $ic = $data['ic'];
            
            // Keep username exactly as name
            $username = $name;
            
            // Check if username already exists for other users
            $chk_user = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $chk_user->execute([$username, $userId]);
            if ($chk_user->fetchColumn() > 0) {
                $username .= ' ' . rand(100, 999);
            }

            // Update users table
            $upd_user = $db->prepare("UPDATE users SET email = ?, username = ? WHERE user_id = ?");
            $upd_user->execute([$email, $username, $userId]);
            
            // Update doctors table using ORM find & save
            $doctor = self::find($doctorId);
            if (!$doctor) {
                $doctor = new self(['doctor_id' => $doctorId], true);
            }
            $doctor->name = $name;
            $doctor->specialization = $specialization;
            $doctor->qualification = $qualification;
            $doctor->phone = $phone;
            $doctor->email = $email;
            $doctor->consultation_fee = $consultation_fee;
            $doctor->initials = $initials;
            $doctor->color = $color;
            $doctor->ic = $ic;
            $doctor->save(); // Automated ORM save()
            
            $db->commit();
            
            return [
                'success' => true,
                'username' => $username
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
