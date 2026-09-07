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
        $appointment = new Appointment([
            'appointment_id'   => $appointmentId,
            'patient_id'       => $patientId,
            'doctor_id'        => $doctorId,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'reason'           => $reason,
            'status'           => 'Scheduled',
        ], false);
        return $appointment->save(); // ORM insert
    }

    public function updateAppointmentStatus($appointmentId, $status) {
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            return false;
        }
        $appointment->status = $status;
        return $appointment->save(); // ORM update
    }
}
