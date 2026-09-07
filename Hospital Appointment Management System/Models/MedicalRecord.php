<?php
// Models/MedicalRecord.php

require_once __DIR__ . '/../core/Model.php';

class MedicalRecord extends Model {
    protected static $table = 'medical_records';
    protected static $primaryKey = 'medical_record_id';

    public function getPatient(): ?object {
        require_once __DIR__ . '/Patient.php';
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function getDoctor(): ?object {
        require_once __DIR__ . '/Doctor.php';
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }

    public function getAppointment(): ?object {
        require_once __DIR__ . '/Appointment.php';
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function getPrescriptions(): array {
        require_once __DIR__ . '/Prescription.php';
        return $this->hasMany(Prescription::class, 'record_id');
    }

    /**
     * All medical records for a given doctor's patients, with patient name
     * and the visit type (appointment reason). Cross-table JOIN — kept
     * inside the Model.
     */
    public static function getForDoctor(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                mr.medical_record_id,
                mr.patient_id,
                mr.diagnosis,
                mr.symptoms,
                mr.notes,
                mr.follow_up_date,
                mr.created_at,
                p.full_name,
                a.reason as visit_type
            FROM medical_records mr
            JOIN patients p ON mr.patient_id = p.patient_id
            LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
            WHERE mr.doctor_id = ?
            ORDER BY mr.created_at DESC
        ');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count of distinct appointments for a doctor that have at least one
     * prescription attached to their medical record. Cross-table JOIN +
     * DISTINCT COUNT — kept inside the Model.
     */
    public static function getPrescribedApptCountForDoctor(string $doctorId): int {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT mr.appointment_id) 
            FROM medical_records mr 
            JOIN prescriptions pr ON mr.medical_record_id = pr.record_id
            WHERE mr.doctor_id = ?
        ');
        $stmt->execute([$doctorId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Most recent medical-record date for a patient (MAX aggregate).
     */
    public static function getLastVisitDate(string $patientId): ?string {
        $db = static::getDb();
        $stmt = $db->prepare('SELECT MAX(created_at) FROM medical_records WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * All visit-history records for a patient, with visit type & appointment
     * status attached. Cross-table JOIN — kept inside the Model.
     */
    public static function getHistoryForPatient(string $patientId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                mr.medical_record_id,
                mr.diagnosis,
                mr.symptoms,
                mr.notes,
                mr.follow_up_date,
                mr.created_at,
                a.reason as visit_type,
                a.status as appointment_status
            FROM medical_records mr
            LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
            WHERE mr.patient_id = ?
            ORDER BY mr.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a completed-consultation medical record with an auto-generated ID
     * (e.g. MR004), used by the "Complete Consultation" flow.
     */
    public static function createForConsultation(
        string $patientId,
        string $doctorId,
        string $appointmentId,
        string $diagnosis,
        string $symptoms,
        string $notes,
        ?string $followUpDate
    ): self {
        $db = static::getDb();
        $count = (int) $db->query("SELECT COUNT(*) FROM medical_records")->fetchColumn() + 1;
        $recordId = 'MR' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        $record = new self([
            'medical_record_id' => $recordId,
            'patient_id'        => $patientId,
            'doctor_id'         => $doctorId,
            'appointment_id'    => $appointmentId,
            'diagnosis'         => $diagnosis,
            'symptoms'          => $symptoms,
            'notes'             => $notes,
            'follow_up_date'    => $followUpDate,
        ], false);
        $record->save();
        return $record;
    }
}
