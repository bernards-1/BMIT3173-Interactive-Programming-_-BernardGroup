<?php
// Facades/AdminFacade.php
require_once __DIR__ . '/../Subsystems/DoctorSubsystem.php';
require_once __DIR__ . '/../Subsystems/AppointmentSubsystem.php';
require_once __DIR__ . '/../Subsystems/PatientSubsystem.php';
require_once __DIR__ . '/../Subsystems/BillingSubsystem.php';

/**
 * AdminFacade acts as a unified structural interface (Facade Pattern) for the Admin client.
 * It provides simplified methods that delegate responsibilities to individual subsystem classes.
 * 
 * Notice: Responsibilities remain encapsulated within the respective subsystems 
 * (Single Responsibility Principle & Law of Demeter).
 */
class AdminFacade {
    private $doctorSubsystem;
    private $appointmentSubsystem;
    private $patientSubsystem;
    private $billingSubsystem;

    public function __construct(
        $pdo = null,
        DoctorSubsystem $doctorSubsystem = null,
        AppointmentSubsystem $appointmentSubsystem = null,
        PatientSubsystem $patientSubsystem = null,
        BillingSubsystem $billingSubsystem = null
    ) {
        $this->doctorSubsystem = $doctorSubsystem ?: new DoctorSubsystem($pdo);
        $this->appointmentSubsystem = $appointmentSubsystem ?: new AppointmentSubsystem($pdo);
        $this->patientSubsystem = $patientSubsystem ?: new PatientSubsystem($pdo);
        $this->billingSubsystem = $billingSubsystem ?: new BillingSubsystem($pdo);
    }

    /**
     * Facade Delegation: Aggregates dashboard operational metrics from subsystems.
     */
    public function getDashboardOverview() {
        return [
            'totalPatients'      => $this->patientSubsystem->getTotalCount(),
            'activeDoctors'      => $this->doctorSubsystem->getActiveDoctorsCount(),
            'todayAppointments'  => $this->appointmentSubsystem->getTodayCount(),
            'monthlyRevenue'     => $this->billingSubsystem->getMonthlyRevenue(),
            'recentAppointments' => $this->appointmentSubsystem->getRecentAppointments(5)
        ];
    }

    /**
     * Facade Delegation: Fetches doctor management overview.
     */
    public function getDoctorManagementData() {
        return [
            'doctors'        => $this->doctorSubsystem->getAllDoctors(),
            'availableCount' => $this->doctorSubsystem->getActiveDoctorsCount()
        ];
    }

    /**
     * Facade Delegation: Adds a new doctor.
     */
    public function registerDoctor(array $doctorData) {
        return $this->doctorSubsystem->registerDoctor($doctorData);
    }

    /**
     * Facade Delegation: Removes a doctor.
     */
    public function removeDoctor($doctorId) {
        return $this->doctorSubsystem->removeDoctor($doctorId);
    }

    /**
     * Facade Delegation: Fetches patient list and counts.
     */
    public function getPatientManagementData() {
        return [
            'patients'   => $this->patientSubsystem->getAllPatients(),
            'totalCount' => $this->patientSubsystem->getTotalCount()
        ];
    }

    /**
     * Facade Delegation: Removes patient record.
     */
    public function removePatient($patientId) {
        return $this->patientSubsystem->removePatient($patientId);
    }

    /**
     * Facade Delegation: Processes doctor leave requests.
     */
    public function processLeaveRequest($leaveId, $status, $rejectReason = null) {
        return $this->doctorSubsystem->updateLeaveStatus($leaveId, $status, $rejectReason);
    }

    /**
     * Facade Delegation: Gets all leave applications.
     */
    public function getLeaveApplications() {
        return $this->doctorSubsystem->getLeaveRequests();
    }

    /**
     * Facade Delegation: Queries doctor duty & availability (used by web service).
     */
    public function verifyDoctorDutyAvailability($doctorId, $date = null) {
        return $this->doctorSubsystem->getDoctorAvailability($doctorId, $date);
    }
}
