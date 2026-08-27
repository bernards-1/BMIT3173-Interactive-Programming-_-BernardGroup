<?php
// Models/Appointment.php
require_once __DIR__ . '/AppointmentState.php';

class Appointment implements AppointmentContext {
    private $appointment_id;
    private $statusState;
    private $pdo;

    public function __construct($appointment_id, $status = 'Scheduled') {
        global $pdo;
        $this->pdo = $pdo;
        $this->appointment_id = $appointment_id;
        $this->setStatus($status);
    }

    public function setStatus($status) {
        switch ($status) {
            case 'Completed':
                $this->statusState = new CompletedState();
                break;
            case 'Cancelled':
                $this->statusState = new CancelledState();
                break;
            case 'Expired':
                $this->statusState = new ExpiredState();
                break;
            case 'Scheduled':
            default:
                $this->statusState = new ScheduledState();
                break;
        }
    }

    public function getStatus(): string {
        return $this->statusState->getStatusName();
    }

    public function transitionTo(AppointmentState $state): void {
        $this->statusState = $state;
        // Persist to database
        $stmt = $this->pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $stmt->execute([$state->getStatusName(), $this->appointment_id]);
    }

    public function complete(): void {
        $this->statusState->complete($this);
    }

    public function cancel(): void {
        $this->statusState->cancel($this);
    }

    public function expire(): void {
        $this->statusState->expire($this);
    }

    // Static helper to load an appointment context
    public static function load($appointment_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        $status = $stmt->fetchColumn();
        if ($status) {
            return new self($appointment_id, $status);
        }
        return null;
    }

    // Existing static methods below
    public static function getTodayCount() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public static function getRecentAppointments($limit = 5) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT a.*, p.full_name as patient_name, d.name as doctor_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getAllAppointments() {
        global $pdo;
        $stmt = $pdo->query("
            SELECT a.*, p.full_name as patient_name, d.name as doctor_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        return $stmt->fetchAll();
    }
}
