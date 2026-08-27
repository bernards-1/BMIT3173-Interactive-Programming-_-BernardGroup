<?php
// Controllers/AdminController.php
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/Patient.php';
require_once __DIR__ . '/../Models/Appointment.php';
require_once __DIR__ . '/../Models/Payment.php';

class AdminController {
    
    private function checkAdminAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            header('Location: ../Login/login.php');
            exit;
        }
    }

    public function dashboard() {
        $this->checkAdminAuth();
        
        $totalPatients = Patient::getTotalCount();
        $activeDoctors = Doctor::getTotalCount();
        $todayAppointments = Appointment::getTodayCount();
        $monthlyRevenue = Payment::getMonthlyRevenue();
        
        $recentAppointments = Appointment::getRecentAppointments(5);
        
        return [
            'totalPatients' => $totalPatients,
            'activeDoctors' => $activeDoctors,
            'todayAppointments' => $todayAppointments,
            'monthlyRevenue' => $monthlyRevenue,
            'recentAppointments' => $recentAppointments
        ];
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

                global $pdo;
                $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetchColumn() > 0) {
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

                // Validate uniqueness of email in users table
                $chkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $chkEmail->execute([$data['email']]);
                if ($chkEmail->fetchColumn() > 0) {
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

            $patientStmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE patient_id = ?');
            $patientStmt->execute([$patientId]);
            $doctorStmt = $pdo->prepare('SELECT COUNT(*) FROM doctors WHERE doctor_id = ?');
            $doctorStmt->execute([$doctorId]);
            if (!$patientStmt->fetchColumn() || !$doctorStmt->fetchColumn()) {
                header('Location: appointments.php?error=' . urlencode('The selected patient or doctor no longer exists.'));
                exit;
            }

            $excludeId = $action === 'update_appointment' ? $appointmentId : '';
            $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled', 'Expired') AND appointment_id <> ?");
            $conflictStmt->execute([$doctorId, $date, $time . ':00', $excludeId]);
            $patientConflictStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled', 'Expired') AND appointment_id <> ?");
            $patientConflictStmt->execute([$patientId, $date, $time . ':00', $excludeId]);
            if ($conflictStmt->fetchColumn() || $patientConflictStmt->fetchColumn()) {
                header('Location: appointments.php?error=' . urlencode('The doctor or patient already has an appointment at that time.'));
                exit;
            }

            try {
                if ($action === 'create_appointment') {
                    $idStmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(appointment_id, 2) AS UNSIGNED)) FROM appointments WHERE appointment_id REGEXP '^A[0-9]+$'");
                    $nextId = ((int) $idStmt->fetchColumn()) + 1;
                    $appointmentId = 'A' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
                    $stmt = $pdo->prepare('INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, \'Scheduled\')');
                    $stmt->execute([$appointmentId, $patientId, $doctorId, $date, $time . ':00', $reason]);
                    $message = 'Appointment scheduled successfully.';
                } elseif ($action === 'update_appointment' && $appointmentId !== '') {
                    $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE appointment_id = ?');
                    $existsStmt->execute([$appointmentId]);
                    if (!$existsStmt->fetchColumn()) {
                        throw new RuntimeException('The appointment no longer exists.');
                    }
                    $stmt = $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ?, reason = ?, status = ? WHERE appointment_id = ?');
                    $stmt->execute([$date, $time . ':00', $reason, $status, $appointmentId]);
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
        global $pdo;

        // Total Revenue (paid payments)
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'");
        $totalRevenue = $stmt->fetchColumn();

        // Total Appointments
        $stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
        $totalAppointments = $stmt->fetchColumn();

        // Active Patients
        $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
        $activePatients = $stmt->fetchColumn();

        // Completed appointments
        $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed'");
        $completedAppointments = $stmt->fetchColumn();

        // Cancelled appointments
        $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled'");
        $cancelledAppointments = $stmt->fetchColumn();

        // Pending (Scheduled) appointments
        $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Scheduled'");
        $scheduledAppointments = $stmt->fetchColumn();

        // Total Doctors
        $stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
        $totalDoctors = $stmt->fetchColumn();

        // Total Pharmacists
        $stmt = $pdo->query("SELECT COUNT(*) FROM pharmacists");
        $totalPharmacists = $stmt->fetchColumn();

        // Revenue by Department
        $stmt = $pdo->query("
            SELECT d.specialization, COALESCE(SUM(p.amount), 0) as revenue
            FROM doctors d
            LEFT JOIN appointments a ON a.doctor_id = d.doctor_id
            LEFT JOIN payments p ON p.appointment_id = a.appointment_id AND p.payment_status = 'Paid'
            GROUP BY d.specialization
            ORDER BY revenue DESC
            LIMIT 5
        ");
        $revenueByDept = $stmt->fetchAll();

        // Recent 6 months payments (for trend chart)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(payment_date, '%b') as month, COALESCE(SUM(amount), 0) as total
            FROM payments
            WHERE payment_status = 'Paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
        ");
        $monthlyRevenue = $stmt->fetchAll();

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
        global $pdo;

        // Handle approve / reject actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'approve_leave' && !empty($_POST['leave_id'])) {
                $stmt = $pdo->prepare("UPDATE doctor_leaves SET status = 'Approved' WHERE leave_id = ?");
                $stmt->execute([$_POST['leave_id']]);
                header('Location: leave_requests.php?success=Approved');
                exit;
            }
            if ($_POST['action'] === 'reject_leave' && !empty($_POST['leave_id'])) {
                $stmt = $pdo->prepare("UPDATE doctor_leaves SET status = 'Rejected', reject_reason = ? WHERE leave_id = ?");
                $stmt->execute([trim($_POST['reject_reason']), $_POST['leave_id']]);
                header('Location: leave_requests.php?success=Rejected');
                exit;
            }
        }

        // Fetch all leaves with doctor name
        $stmt = $pdo->query("
            SELECT dl.*, d.name AS doctor_name
            FROM doctor_leaves dl
            JOIN doctors d ON dl.doctor_id = d.doctor_id
            ORDER BY 
                CASE dl.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
                dl.created_at DESC
        ");
        $leaves = $stmt->fetchAll();

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
