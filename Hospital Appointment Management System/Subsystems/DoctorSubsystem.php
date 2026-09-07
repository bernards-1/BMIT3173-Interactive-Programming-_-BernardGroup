<?php
// Subsystems/DoctorSubsystem.php
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/DoctorLeave.php';

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
        return Doctor::count();
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
        return DoctorLeave::allWithDoctorName();
    }

    /**
     * Process leave request approval or rejection via ORM.
     */
    public function updateLeaveStatus($leaveId, $status, $rejectReason = null) {
        $leave = DoctorLeave::find($leaveId);
        if (!$leave) {
            return false;
        }
        if ($status === 'Approved') {
            $approved = $leave->approve();
            if ($approved) {
                // ORM + State Pattern: Cancel conflicting scheduled appointments
                require_once __DIR__ . '/../Models/Appointment.php';
                $clashingAppointments = Appointment::getScheduledBetween($leave->doctor_id, $leave->start_date, $leave->end_date);
                foreach ($clashingAppointments as $appt) {
                    $appt->cancel();
                }
            }
            return $approved;
        } elseif ($status === 'Rejected') {
            return $leave->reject($rejectReason);
        }
        return false;
    }

    /**
     * Query doctor duty & availability for inter-module integration.
     */
    public function getDoctorAvailability($doctorId, $date = null) {
        $doctorMatches = Doctor::where('doctor_id', $doctorId);
        $doctorModel = $doctorMatches[0] ?? null;

        if (!$doctorModel) {
            return null;
        }

        $checkDate = $date ?: date('Y-m-d');
        $isOnLeave = DoctorLeave::isDoctorOnLeave($doctorId, $checkDate);

        return [
            'doctor' => [
                'doctor_id'        => $doctorModel->doctor_id,
                'name'             => $doctorModel->name,
                'specialization'   => $doctorModel->specialization,
                'phone'            => $doctorModel->phone,
                'email'            => $doctorModel->email,
                'consultation_fee' => $doctorModel->consultation_fee,
            ],
            'checkDate' => $checkDate,
            'isAvailable' => !$isOnLeave,
            'status' => $isOnLeave ? 'On Leave' : 'Available'
        ];
    }
}
