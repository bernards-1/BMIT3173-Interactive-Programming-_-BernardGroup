<?php
// Subsystems/PatientSubsystem.php
require_once __DIR__ . '/../Models/Patient.php';

/**
 * PatientSubsystem handles all administrative interactions with patient records.
 */
class PatientSubsystem {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    public function getTotalCount() {
        return Patient::getTotalCount();
    }

    public function getAllPatients() {
        return Patient::getAll();
    }

    public function removePatient($patientId) {
        return Patient::delete($patientId);
    }
}
