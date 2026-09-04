<?php
// Subsystems/DoctorSubsystem.php
require_once __DIR__ . '/../Models/Doctor.php';

/**
 * DoctorSubsystem handles all low-level business logic, queries, and state
 * changes relating to Doctors and Doctor Leaves.
 * Responsibilities strictly remain here (Single Responsibility Principle).
 */
class DoctorSubsystem {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    /**
     * Get total count of registered doctors.
     */
    public function getActiveDoctorsCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM doctors");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch all doctors.
     */
    public function getAllDoctors() {
        return Doctor::getAll();
    }

    /**
     * Add new doctor with account creation.
     */
    public function registerDoctor(array $data) {
        return Doctor::add($data);
    }

    /**
     * Delete doctor record.
     */
    public function removeDoctor($doctorId) {
        return Doctor::delete($doctorId);
    }

    /**
     * Fetch all leave requests with doctor information.
     */
    public function getLeaveRequests() {
        $stmt = $this->pdo->query("
            SELECT dl.*, d.name AS doctor_name
            FROM doctor_leaves dl
            JOIN doctors d ON dl.doctor_id = d.doctor_id
            ORDER BY 
                CASE dl.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
                dl.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Process leave request approval or rejection.
     */
    public function updateLeaveStatus($leaveId, $status, $rejectReason = null) {
        if ($status === 'Approved') {
            $stmt = $this->pdo->prepare("UPDATE doctor_leaves SET status = 'Approved' WHERE leave_id = ?");
            return $stmt->execute([$leaveId]);
        } elseif ($status === 'Rejected') {
            $stmt = $this->pdo->prepare("UPDATE doctor_leaves SET status = 'Rejected', reject_reason = ? WHERE leave_id = ?");
            return $stmt->execute([$rejectReason, $leaveId]);
        }
        return false;
    }

    /**
     * Query doctor duty & availability for inter-module integration.
     */
    public function getDoctorAvailability($doctorId, $date = null) {
        $stmt = $this->pdo->prepare("SELECT doctor_id, name, specialization, phone, email, consultation_fee FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            return null;
        }

        $checkDate = $date ?: date('Y-m-d');
        $leaveStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM doctor_leaves 
            WHERE doctor_id = ? AND status = 'Approved' 
            AND ? BETWEEN start_date AND end_date
        ");
        $leaveStmt->execute([$doctorId, $checkDate]);
        $isOnLeave = $leaveStmt->fetchColumn() > 0;

        return [
            'doctor' => $doctor,
            'checkDate' => $checkDate,
            'isAvailable' => !$isOnLeave,
            'status' => $isOnLeave ? 'On Leave' : 'Available'
        ];
    }
}
