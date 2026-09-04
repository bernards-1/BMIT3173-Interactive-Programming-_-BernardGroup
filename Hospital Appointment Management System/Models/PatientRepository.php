<?php

class PatientRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Core patient lookups ──────────────────────────────────────

    public function getPatientByUserId(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, u.email AS user_email FROM patients p JOIN users u ON p.user_id = u.user_id WHERE p.user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getPatientById(string $patientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updatePatientProfile(string $userId, string $patientId, array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $updUser = $this->pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $updUser->execute([$data['email'], $userId]);

            $updPatient = $this->pdo->prepare("
                UPDATE patients 
                SET full_name = ?, ic = ?, date_of_birth = ?, gender = ?, phone = ?, 
                    blood_type = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? 
                WHERE patient_id = ?
            ");

            $updPatient->execute([
                $data['full_name'],
                $data['ic'],
                $data['date_of_birth'],
                $data['gender'],
                $data['phone'],
                $data['blood_type'],
                $data['address'],
                $data['emergency_contact_name'],
                $data['emergency_contact_phone'],
                $patientId
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Medical records ────────────────────────────────────────────

    public function getMedicalRecordsByPatientId(string $patientId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                mr.medical_record_id,
                mr.diagnosis,
                mr.symptoms,
                mr.notes,
                mr.follow_up_date,
                mr.created_at,
                d.name as doctor_name,
                d.specialization,
                a.reason as visit_type
            FROM medical_records mr
            JOIN doctors d ON mr.doctor_id = d.doctor_id
            LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
            WHERE mr.patient_id = ?
            ORDER BY mr.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentMedicalRecords(string $patientId, int $limit = 3): array
    {
        // $limit is an internal integer set by the calling code, never raw user input,
        // so interpolating it here is safe; PDO cannot bind LIMIT as a placeholder
        // on every driver, which is why it is not passed through execute().
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare("
            SELECT mr.diagnosis, mr.symptoms, mr.follow_up_date, mr.created_at,
                   d.name AS doctor_name, d.specialization, d.initials, d.color
            FROM medical_records mr
            JOIN doctors d ON mr.doctor_id = d.doctor_id
            WHERE mr.patient_id = ?
            ORDER BY mr.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentMedicalRecordsForApi(string $patientId, int $limit = 3): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare("
            SELECT mr.medical_record_id, mr.diagnosis, mr.created_at, d.name as doctor_name
            FROM medical_records mr
            JOIN doctors d ON mr.doctor_id = d.doctor_id
            WHERE mr.patient_id = ?
            ORDER BY mr.created_at DESC LIMIT $limit
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Prescriptions ──────────────────────────────────────────────

    public function getPrescriptionsByPatientId(string $patientId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                pr.prescription_id,
                pr.record_id,
                pr.dosage,
                pr.frequency,
                pr.duration,
                pr.instructions,
                pr.quantity,
                pr.created_at,
                m.brand_name,
                m.generic_name,
                m.dosage AS med_dosage,
                mr.diagnosis,
                d.name AS doctor_name,
                d.specialization,
                d.initials,
                d.color
            FROM prescriptions pr
            LEFT JOIN medicines m ON m.medicine_id = pr.medicine_id
            LEFT JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            LEFT JOIN doctors d ON d.doctor_id = mr.doctor_id
            WHERE mr.patient_id = ?
            ORDER BY pr.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Appointments ───────────────────────────────────────────────

    public function getAppointmentsByPatientId(string $patientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.name AS doctor_name, d.specialization, d.initials, d.color, d.consultation_fee,
                   pay.amount AS payment_amount
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScheduledAppointmentsRaw(string $patientId): array
    {
        $stmt = $this->pdo->prepare("SELECT appointment_id, appointment_date, status FROM appointments WHERE patient_id = ? AND status = 'Scheduled'");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpcomingAppointments(string $patientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.name AS doctor_name, d.specialization, d.initials, d.color
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ? AND a.status = 'Scheduled'
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countCompletedAppointments(string $patientId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'Completed'");
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn();
    }

    public function getAppointmentStatusCounts(string $patientId): array
    {
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE patient_id = ? GROUP BY status");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function countAppointments(string $patientId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn();
    }

    public function countMedicalRecords(string $patientId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM medical_records WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Account / password ─────────────────────────────────────────

    public function getPasswordHash(string $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        return $hash ?: null;
    }

    public function updatePassword(string $userId, string $newHash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        return $stmt->execute([$newHash, $userId]);
    }

    // ── Booking support (doctor leave / conflict / fee lookups used only during booking) ──

    public function getApprovedDoctorLeaves(string $doctorId): array
    {
        $stmt = $this->pdo->prepare("SELECT start_date, end_date FROM doctor_leaves WHERE doctor_id = ? AND status = 'Approved' AND end_date >= CURDATE()");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasAppointmentConflict(string $doctorId, string $date, string $time): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'Scheduled'");
        $stmt->execute([$doctorId, $date, $time]);
        return $stmt->fetchColumn() > 0;
    }

    public function getLastAppointmentId(): ?string
    {
        $stmt = $this->pdo->query("SELECT appointment_id FROM appointments ORDER BY appointment_id DESC LIMIT 1");
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    public function getDoctorConsultationFee(string $doctorId): float
    {
        $stmt = $this->pdo->prepare("SELECT consultation_fee FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctorId]);
        return (float) $stmt->fetchColumn();
    }

    public function getAllDoctors(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM doctors ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM payments");
        return (int) $stmt->fetchColumn();
    }

    public function paymentIdExists(string $paymentId): bool
    {
        // Bound parameter — this replaces a previous version of this check that
        // concatenated $pay_id directly into the SQL string, which was a real
        // SQL Injection vulnerability in this file.
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE payment_id = ?");
        $stmt->execute([$paymentId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Creates an appointment and its linked payment record inside a single transaction.
     */
    public function createAppointmentWithPayment(array $appointment, array $payment): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO appointments (appointment_id, patient_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, NULL, ?, ?, ?, 'Scheduled')");
            $stmt->execute([
                $appointment['appointment_id'],
                $appointment['patient_id'],
                $appointment['doctor_id'],
                $appointment['appointment_date'],
                $appointment['appointment_time'],
                $appointment['reason'],
            ]);

            $payStmt = $this->pdo->prepare("INSERT INTO payments (payment_id, appointment_id, patient_id, amount, payment_method, payment_status, payment_date, invoice_no) VALUES (?, ?, ?, ?, 'Credit Card', 'Unpaid', NULL, ?)");
            $payStmt->execute([
                $payment['payment_id'],
                $appointment['appointment_id'],
                $appointment['patient_id'],
                $payment['amount'],
                $payment['invoice_no'],
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelAppointment(string $appointmentId, string $sessionPatientId, string $correlationId): bool
    {
        $stmt = $this->pdo->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment || $appointment['patient_id'] !== $sessionPatientId) {
            error_log(sprintf(
                "[SECURITY ALERT] [CorrelationID: %s] Unauthorized access attempt on appointment_id: %s by patient_id: %s",
                $correlationId,
                $appointmentId,
                $sessionPatientId
            ));
            return false;
        }

        $upd = $this->pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        return $upd->execute([$appointmentId]);
    }

    public function rescheduleAppointment(string $appointmentId, string $sessionPatientId, string $newDate, string $newTime, string $correlationId): bool
    {
        $stmt = $this->pdo->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment || $appointment['patient_id'] !== $sessionPatientId) {
            error_log(sprintf(
                "[SECURITY ALERT] [CorrelationID: %s] Unauthorized reschedule attempt on appointment_id: %s by patient_id: %s",
                $correlationId,
                $appointmentId,
                $sessionPatientId
            ));
            return false;
        }

        $upd = $this->pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ?");
        return $upd->execute([$newDate, $newTime, $appointmentId]);
    }
}
