<?php
/**
 * Patient Module Controller
 */

require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/PatientRepository.php';
require_once __DIR__ . '/../services/PricingService.php';

class PatientController {
    
    private function checkPatientAuth() {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public function dashboard() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/mainpage.php';
    }

    public function bookAppointment() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/book_appointment.php';
    }

    public function myAppointments() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/my_appointment.php';
    }

    public function medicalRecords() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/medical_records.php';
    }

    public function profile() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/patientProfile.php';
    }

    /**
     * Architecture Flow Step:
     * Controller receives appointment type and doctor ID,
     * requests Doctor ORM entity and PricingService,
     * returns prepared result.
     */
    public function calculateAppointmentFee(string $doctorId, string $type): array {
        $this->checkPatientAuth();
        try {
            // 1. Fetch Doctor using ORM
            $doctor = Doctor::findOrFail($doctorId);

            // 2. Request pricing calculation via PricingService (Strategy Pattern)
            $finalAmount = PricingService::calculateFee($doctor, $type);

            return [
                'success'      => true,
                'doctor_id'    => $doctorId,
                'doctor_name'  => $doctor->name,
                'base_fee'     => (float)($doctor->consultation_fee ?? 50.00),
                'type'         => $type,
                'final_amount' => $finalAmount
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Handles full appointment booking submission conforming to MVC:
     * Extracts business logic and Strategy Pattern from View.
     */
    public function handleAppointmentBooking(array $postData, string $userId, ?PatientRepository $repo = null): array {
        global $pdo;
        $patientRepository = $repo ?? new PatientRepository($pdo ?? Database::getInstance()->getConnection());

        $doctor_id        = trim($postData['doctor_id'] ?? '');
        $appointment_date = trim($postData['appointment_date'] ?? '');
        $appointment_time = trim($postData['appointment_time'] ?? '');
        $type             = trim($postData['type'] ?? 'Consultation');
        $reason           = trim($postData['reason'] ?? '');

        // 1. Basic validation
        if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($reason)) {
            return ['success' => false, 'message' => 'Please fill in all required fields.'];
        }

        // Validate date is not in the past
        if ($appointment_date < date('Y-m-d')) {
            return ['success' => false, 'message' => 'Please select a future date.'];
        }

        // 2. Validate Doctor Leave Schedule
        $approvedLeaves = $patientRepository->getApprovedDoctorLeaves($doctor_id);
        foreach ($approvedLeaves as $leave) {
            if ($appointment_date >= $leave['start_date'] && $appointment_date <= $leave['end_date']) {
                return ['success' => false, 'message' => 'The selected doctor is on leave on this date. Please choose another date.'];
            }
        }

        // 3. Validate Time Slot Conflict
        if ($patientRepository->hasAppointmentConflict($doctor_id, $appointment_date, $appointment_time)) {
            return ['success' => false, 'message' => 'This time slot is already booked for this doctor. Please choose another time slot.'];
        }

        // 4. Get patient record
        $patient = $patientRepository->getPatientByUserId($userId);
        if (!$patient) {
            return ['success' => false, 'message' => 'Patient record not found.'];
        }
        $patient_id = $patient['patient_id'];

        // 5. Generate appointment_id
        $last_id = $patientRepository->getLastAppointmentId();
        $next_id = $last_id ? ('A' . str_pad(((int)substr($last_id, 1)) + 1, 3, '0', STR_PAD_LEFT)) : 'A001';

        $full_reason = "[" . $type . "] " . $reason;

        try {
            // 6. Architectural Flow: Fetch Doctor using ORM
            $doctor = Doctor::findOrFail($doctor_id);

            // 7. Architectural Flow: Calculate Final Price via PricingService (Strategy Pattern)
            $final_amount = PricingService::calculateFee($doctor, $type);

            // 8. Generate payment_id and invoice_no
            $pay_count = $patientRepository->getPaymentCount() + 1;
            $pay_id = 'PA' . str_pad($pay_count, 3, '0', STR_PAD_LEFT);
            while ($patientRepository->paymentIdExists($pay_id)) {
                $pay_count++;
                $pay_id = 'PA' . str_pad($pay_count, 3, '0', STR_PAD_LEFT);
            }
            $invoice_no = 'INV-2026-' . str_pad($pay_count, 4, '0', STR_PAD_LEFT);

            // 9. Persist appointment and payment inside single atomic transaction
            $created = $patientRepository->createAppointmentWithPayment(
                [
                    'appointment_id'   => $next_id,
                    'patient_id'       => $patient_id,
                    'doctor_id'        => $doctor_id,
                    'appointment_date' => $appointment_date,
                    'appointment_time' => $appointment_time,
                    'reason'           => $full_reason,
                ],
                [
                    'payment_id' => $pay_id,
                    'amount'     => $final_amount,
                    'invoice_no' => $invoice_no,
                ]
            );

            if ($created) {
                return [
                    'success'        => true,
                    'message'        => 'Appointment booked successfully!',
                    'appointment_id' => $next_id,
                    'invoice_no'     => $invoice_no,
                    'amount'         => number_format($final_amount, 2),
                ];
            }

            return ['success' => false, 'message' => 'Failed to create appointment.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Booking error: ' . $e->getMessage()];
        }
    }
}