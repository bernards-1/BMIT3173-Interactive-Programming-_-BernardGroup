<?php
// Models/Prescription.php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Medicine.php';
require_once __DIR__ . '/Pharmacist.php';

class Prescription extends Model {
    protected static $table = 'prescriptions';
    protected static $primaryKey = 'prescription_id';

    /**
     * @var Medicine|null In-memory object reference
     */
    private ?Medicine $medicineInstance = null;

    /**
     * @var Pharmacist|null In-memory object reference
     */
    private ?Pharmacist $pharmacistInstance = null;

    /**
     * Object Reference: Get associated Medicine entity instance.
     */
    public function getMedicine(): ?Medicine {
        if ($this->medicineInstance === null && !empty($this->medicine_id)) {
            $this->medicineInstance = Medicine::find($this->medicine_id);
        }
        return $this->medicineInstance;
    }

    /**
     * Object Reference: Set associated Medicine entity instance.
     */
    public function setMedicine(Medicine $medicine): void {
        $this->medicineInstance = $medicine;
        $this->medicine_id = $medicine->medicine_id;
    }

    /**
     * Object Reference: Get Pharmacist entity instance who dispensed this prescription.
     */
    public function getPharmacist(): ?Pharmacist {
        if ($this->pharmacistInstance === null && !empty($this->dispensed_by)) {
            $this->pharmacistInstance = Pharmacist::find($this->dispensed_by);
        }
        return $this->pharmacistInstance;
    }

    /**
     * A doctor's most recent prescriptions across all patients, with medicine
     * and patient name attached. Cross-table JOIN — kept inside the Model.
     */
    public static function getRecentForDoctor(string $doctorId, int $limit = 3): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                pr.dosage,
                pr.frequency,
                pr.created_at,
                m.brand_name,
                p.full_name as patient_name
            FROM prescriptions pr
            JOIN medical_records mr ON pr.record_id = mr.medical_record_id
            JOIN patients p ON mr.patient_id = p.patient_id
            JOIN medicines m ON pr.medicine_id = m.medicine_id
            WHERE mr.doctor_id = ?
            ORDER BY pr.created_at DESC, pr.prescription_id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $doctorId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count of all prescriptions ever issued to a given patient.
     * Cross-table JOIN + COUNT aggregate — kept inside the Model.
     */
    public static function getCountForPatient(string $patientId): int {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT COUNT(*) 
            FROM prescriptions pr
            JOIN medical_records mr ON pr.record_id = mr.medical_record_id
            WHERE mr.patient_id = ?
        ');
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Medicines prescribed for a specific medical record — dosage, frequency,
     * and medicine names. Cross-table JOIN — kept inside the Model.
     */
    public static function getMedicinesForRecord(string $recordId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT pr.dosage, pr.frequency, pr.duration, pr.instructions, pr.quantity, m.brand_name, m.generic_name 
            FROM prescriptions pr
            JOIN medicines m ON pr.medicine_id = m.medicine_id
            WHERE pr.record_id = ?
        ');
        $stmt->execute([$recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark this prescription as dispensed and persist via ORM.
     */
    public function markAsDispensed(string $pharmacistId, string $notes = ''): bool {
        $this->is_dispensed = 1;
        $this->dispensed_at = date('Y-m-d H:i:s');
        $this->dispensed_by = $pharmacistId;
        $this->dispense_notes = $notes;
        return $this->save();
    }
}
