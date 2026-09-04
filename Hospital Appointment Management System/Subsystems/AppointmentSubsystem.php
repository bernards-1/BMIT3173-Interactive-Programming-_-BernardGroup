<?php
// Subsystems/AppointmentSubsystem.php
require_once __DIR__ . '/../Models/Appointment.php';

/**
 * AppointmentSubsystem encapsulates all appointment-specific database 
 * operations, metrics, scheduling, and validation.
 */
class AppointmentSubsystem {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    public function getTodayCount() {
        return Appointment::getTodayCount();
    }

    public function getRecentAppointments($limit = 5) {
        return Appointment::getRecentAppointments($limit);
    }

    public function getAllAppointments() {
        return Appointment::getAllAppointments();
    }

    public function scheduleAppointment($appointmentId, $patientId, $doctorId, $date, $time, $reason) {
        $stmt = $this->pdo->prepare("INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')");
        return $stmt->execute([$appointmentId, $patientId, $doctorId, $date, $time, $reason]);
    }

    public function updateAppointmentStatus($appointmentId, $status) {
        $stmt = $this->pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        return $stmt->execute([$status, $appointmentId]);
    }
}
