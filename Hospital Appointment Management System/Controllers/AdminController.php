<?php
// Controllers/AdminController.php
require_once __DIR__ . '/../core/SecuritySession.php';
require_once __DIR__ . '/../Facades/AdminFacade.php';
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/Patient.php';
require_once __DIR__ . '/../Models/Appointment.php';
require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/../Models/User.php';

class AdminController {
    private $facade;

    public function __construct(AdminFacade $facade = null) {
        global $pdo;
        $this->facade = $facade ?: new AdminFacade($pdo);
    }
    
    private function checkAdminAuth() {
        SecuritySession::checkAdminAuth();
    }

    public function dashboard() {
        $this->checkAdminAuth();
        
        // Client-to-Facade delegation: The controller simply delegates to the Facade
        return $this->facade->getDashboardOverview();
    }

    public function doctors() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'delete_doctor' && isset($_POST['doctor_id'])) {
                Doctor::delete($_POST['doctor_id']);
                header("Location: doctors.php?success=" . urlencode("Doctor deleted successfully."));
                exit;
            } elseif ($_POST['action'] === 'add_doctor') {
                $username = strtolower(str_replace(' ', '', trim($_POST['name'] ?? '')));
                $username = preg_replace('/[^a-z0-9]/', '', $username);
                if (empty($username)) {
                    $username = 'doctor' . time();
                }

                $usernameCount = User::count('username', $username);
                if ($usernameCount > 0) {
                    $username .= rand(10, 99);
                }

                $data = [
                    'username' => $username,
                    'email' => trim($_POST['email'] ?? ''),
                    'password' => $_POST['password'] ?? 'doctor123',
                    'ic' => trim($_POST['ic'] ?? ''),
                    'name' => trim($_POST['name'] ?? ''),
                    'specialization' => trim($_POST['specialization'] ?? ''),
                    'qualification' => trim($_POST['qualification'] ?? 'MD'),
                    'consultation_fee' => floatval($_POST['consultation_fee'] ?? 50.00),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'color' => $_POST['color'] ?? '#3b82f6'
                ];

                // Validate uniqueness of email in users table via ORM
                if (User::count('email', $data['email']) > 0) {
                    header("Location: doctors.php?error=" . urlencode("Email is already registered."));
                    exit;
                }

                $success = Doctor::add($data);
                if ($success) {
                    header("Location: doctors.php?success=" . urlencode("Doctor added successfully."));
                } else {
                    header("Location: doctors.php?error=" . urlencode("Failed to add doctor."));
                }
                exit;
            }
        }

        $doctors = Doctor::getAll();
        
        // Count statuses if needed
        $availableCount = count($doctors); // Mocking status logic
        
        return [
            'doctors' => $doctors,
            'availableCount' => $availableCount
        ];
    }

    public function patients() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'delete_patient' && isset($_POST['patient_id'])) {
                Patient::delete($_POST['patient_id']);
            }
            header("Location: patients.php");
            exit;
        }

        $patients = Patient::getAll();
        
        return [
            'patients' => $patients
        ];
    }

    public function pharmacists() {
        $this->checkAdminAuth();
        require_once __DIR__ . '/../Models/Pharmacist.php';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'delete_pharmacist' && isset($_POST['pharmacist_id'])) {
                Pharmacist::delete($_POST['pharmacist_id']);
            }
            header("Location: pharmacists.php");
            exit;
        }

        $pharmacists = Pharmacist::getAll();
        
        return [
            'pharmacists' => $pharmacists
        ];
    }

    public function appointments() {
        $this->checkAdminAuth();
        global $pdo;

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
                header('Location: appointments.php?error=' . urlencode('Your session has expired. Please try again.'));
                exit;
            }

            $action = $_POST['action'] ?? '';
            $patientId = trim($_POST['patient_id'] ?? '');
            $doctorId = trim($_POST['doctor_id'] ?? '');
            $date = trim($_POST['appointment_date'] ?? '');
            $time = trim($_POST['appointment_time'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $appointmentId = trim($_POST['appointment_id'] ?? '');
            $allowedStatuses = ['Scheduled', 'Completed', 'Cancelled', 'Expired'];
            $status = $_POST['status'] ?? 'Scheduled';

            $dateObject = DateTime::createFromFormat('Y-m-d', $date);
            $timeObject = DateTime::createFromFormat('H:i', $time);
            $isDateValid = $dateObject && $dateObject->format('Y-m-d') === $date;
            $isTimeValid = $timeObject && $timeObject->format('H:i') === $time;

            if ($patientId === '' || $doctorId === '' || $reason === '' || !$isDateValid || !$isTimeValid || ($action === 'update_appointment' && !in_array($status, $allowedStatuses, true))) {
                header('Location: appointments.php?error=' . urlencode('Please provide valid appointment details.'));
                exit;
            }

            // Existence checks via ORM
            if (!Patient::count('patient_id', $patientId) || !Doctor::count('doctor_id', $doctorId)) {
                header('Location: appointments.php?error=' . urlencode('The selected patient or doctor no longer exists.'));
                exit;
            }

            require_once __DIR__ . '/../Models/DoctorLeave.php';
            if (DoctorLeave::isDoctorOnLeave($doctorId, $date)) {
                header('Location: appointments.php?error=' . urlencode('The selected doctor is on approved leave on this date.'));
                exit;
            }

            $excludeId = $action === 'update_appointment' ? $appointmentId : '';
            if (Appointment::doctorHasConflict($doctorId, $date, $time . ':00', $excludeId)
                || Appointment::patientHasConflict($patientId, $date, $time . ':00', $excludeId)) {
                header('Location: appointments.php?error=' . urlencode('The doctor or patient already has an appointment at that time.'));
                exit;
            }

            try {
                if ($action === 'create_appointment') {
                    $appointmentId = Appointment::generateNextId();
                    $newAppointment = new Appointment([
                        'appointment_id'   => $appointmentId,
                        'patient_id'       => $patientId,
                        'doctor_id'        => $doctorId,
                        'appointment_date' => $date,
                        'appointment_time' => $time . ':00',
                        'reason'           => $reason,
                        'status'           => 'Scheduled',
                    ], false);
                    $newAppointment->save(); // ORM insert
                    $message = 'Appointment scheduled successfully.';
                } elseif ($action === 'update_appointment' && $appointmentId !== '') {
                    $existingAppointment = Appointment::find($appointmentId);
                    if (!$existingAppointment) {
                        throw new RuntimeException('The appointment no longer exists.');
                    }
                    $existingAppointment->appointment_date = $date;
                    $existingAppointment->appointment_time = $time . ':00';
                    $existingAppointment->reason = $reason;
                    $existingAppointment->status = $status;
                    $existingAppointment->save(); // ORM update
                    $message = 'Appointment updated successfully.';
                } else {
                    throw new RuntimeException('Unsupported appointment action.');
                }
                header('Location: appointments.php?success=' . urlencode($message));
                exit;
            } catch (Throwable $exception) {
                header('Location: appointments.php?error=' . urlencode($exception->getMessage()));
                exit;
            }
        }
        
        $appointments = Appointment::getAllAppointments();
        $patients = $pdo->query('SELECT patient_id, full_name FROM patients ORDER BY full_name')->fetchAll();
        $doctors = $pdo->query('SELECT doctor_id, name, specialization FROM doctors ORDER BY name')->fetchAll();
        
        $stats = [
            'today' => Appointment::getTodayCount(),
            'scheduled' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'expired' => 0
        ];
        
        foreach ($appointments as $apt) {
            if ($apt['status'] === 'Scheduled') {
                $stats['scheduled']++;
            } elseif ($apt['status'] === 'Completed') {
                $stats['completed']++;
            } elseif ($apt['status'] === 'Cancelled') {
                $stats['cancelled']++;
            } elseif ($apt['status'] === 'Expired') {
                $stats['expired']++;
            }
        }
        
        return [
            'appointments' => $appointments,
            'stats' => $stats,
            'patients' => $patients,
            'doctors' => $doctors,
            'csrfToken' => $_SESSION['csrf_token']
        ];
    }

    public function billing() {
        $this->checkAdminAuth();
        require_once __DIR__ . '/../Models/Payment.php';
        
        $payments = Payment::getAllPayments();
        
        $totalRevenue = 0;
        $paid = 0;
        $pending = 0;
        $overdue = 0;
        
        foreach ($payments as $pay) {
            if ($pay['payment_status'] === 'Paid') {
                $paid += $pay['amount'];
                $totalRevenue += $pay['amount'];
            } elseif ($pay['payment_status'] === 'Unpaid') {
                $pending += $pay['amount']; // Can add logic for overdue based on date
            }
        }
        
        return [
            'payments' => $payments,
            'stats' => [
                'totalRevenue' => $totalRevenue,
                'paid' => $paid,
                'pending' => $pending,
                'overdue' => $overdue
            ]
        ];
    }

    public function reports() {
        $this->checkAdminAuth();
        require_once __DIR__ . '/../Models/Pharmacist.php';

        // Total Revenue (paid payments) — aggregate SUM, now inside Payment model
        $totalRevenue = Payment::getTotalRevenue();

        // Simple per-table / per-status counts via ORM
        $totalAppointments = Appointment::count();
        $activePatients = Patient::count();
        $completedAppointments = Appointment::count('status', 'Completed');
        $cancelledAppointments = Appointment::count('status', 'Cancelled');
        $scheduledAppointments = Appointment::count('status', 'Scheduled');
        $totalDoctors = Doctor::count();
        $totalPharmacists = Pharmacist::count();

        // Revenue by Department — JOIN + GROUP BY, now inside Payment model
        $revenueByDept = Payment::getRevenueByDepartment(5);

        // Recent 6 months payments (for trend chart) — aggregate, now inside Payment model
        $monthlyRevenue = Payment::getMonthlyRevenueTrend(6);

        return [
            'totalRevenue'          => $totalRevenue,
            'totalAppointments'     => $totalAppointments,
            'activePatients'        => $activePatients,
            'completedAppointments' => $completedAppointments,
            'cancelledAppointments' => $cancelledAppointments,
            'scheduledAppointments' => $scheduledAppointments,
            'totalDoctors'          => $totalDoctors,
            'totalPharmacists'      => $totalPharmacists,
            'revenueByDept'         => $revenueByDept,
            'monthlyRevenue'        => $monthlyRevenue,
            'satisfaction'          => 94
        ];
    }

    public function settings() {
        $this->checkAdminAuth();
        require_once __DIR__ . '/../Models/Setting.php';
        $settingModel = new Setting();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                $settingModel->updateSettings($data);
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }

        return $settingModel->getAllSettings();
    }

    public function leaveRequests() {
        $this->checkAdminAuth();

        // Handle approve / reject actions via Facade delegation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'approve_leave' && !empty($_POST['leave_id'])) {
                $this->facade->processLeaveRequest($_POST['leave_id'], 'Approved');
                header('Location: leave_requests.php?success=Approved');
                exit;
            }
            if ($_POST['action'] === 'reject_leave' && !empty($_POST['leave_id'])) {
                $this->facade->processLeaveRequest($_POST['leave_id'], 'Rejected', trim($_POST['reject_reason']));
                header('Location: leave_requests.php?success=Rejected');
                exit;
            }
        }

        // Fetch leaves via Facade delegation
        $leaves = $this->facade->getLeaveApplications();

        // Stats
        $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($leaves as $l) {
            if ($l['status'] === 'Pending')  $stats['pending']++;
            if ($l['status'] === 'Approved') $stats['approved']++;
            if ($l['status'] === 'Rejected') $stats['rejected']++;
        }

        return ['leaves' => $leaves, 'stats' => $stats];
    }
}
