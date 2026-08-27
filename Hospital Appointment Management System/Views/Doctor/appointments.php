<?php
require_once '../../db.php';
require_once '../../Models/User.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as doctor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header('Location: ../Login/login.php');
    exit;
}

// Helper to escape output
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Helper to calculate age
function getAge($dob) {
    if (empty($dob)) return '—';
    $birthdate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthdate->diff($today)->y;
}

// Get the correct doctor_id and name
$user_id = $_SESSION['user']['user_id'] ?? $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare('SELECT doctor_id, name FROM doctors WHERE user_id = ?');
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';

// Fetch stats dynamically
// Today count
$today_stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()');
$today_stmt->execute([$doctor_id]);
$today_count = $today_stmt->fetchColumn();

// Completed count
$completed_stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = \'Completed\'');
$completed_stmt->execute([$doctor_id]);
$completed_count = $completed_stmt->fetchColumn();

// Upcoming (Scheduled) count
$upcoming_stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = \'Scheduled\'');
$upcoming_stmt->execute([$doctor_id]);
$upcoming_count = $upcoming_stmt->fetchColumn();

// Cancelled count
$cancelled_stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = \'Cancelled\'');
$cancelled_stmt->execute([$doctor_id]);
$cancelled_count = $cancelled_stmt->fetchColumn();

// Prescribed count (medical records of this doctor that have at least one prescription)
$prescribed_stmt = $pdo->prepare('
    SELECT COUNT(DISTINCT mr.appointment_id) 
    FROM medical_records mr 
    JOIN prescriptions pr ON mr.medical_record_id = pr.record_id
    WHERE mr.doctor_id = ?
');
$prescribed_stmt->execute([$doctor_id]);
$prescribed_count = $prescribed_stmt->fetchColumn();

// Fetch appointments list
$list_stmt = $pdo->prepare('
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.reason,
        a.status,
        p.patient_id,
        p.full_name,
        p.date_of_birth,
        mr.medical_record_id,
        (SELECT COUNT(*) FROM prescriptions pr WHERE pr.record_id = mr.medical_record_id) as prescription_count
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN medical_records mr ON a.appointment_id = mr.appointment_id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
');
$list_stmt->execute([$doctor_id]);
$appointments = $list_stmt->fetchAll();

// Helper function to check if appointment is expired and update status
function checkAndMarkExpired($pdo, $appointment_id, $appointment_date, $current_status) {
    // If already Completed, Cancelled, or Expired, don't change
    if (in_array($current_status, ['Completed', 'Cancelled', 'Expired'])) {
        return $current_status;
    }
    
    // Check if appointment date is in the past
    $today = new DateTime('today');
    $appt_date = new DateTime($appointment_date);
    
    if ($appt_date < $today) {
        // Mark as Expired using State Pattern
        require_once '../../Models/Appointment.php';
        $appointment = Appointment::load($appointment_id);
        if ($appointment) {
            $appointment->expire();
        }
        return 'Expired';
    }
    
    return $current_status;
}

// Check and update expired appointments
foreach ($appointments as $key => $apt) {
    $appointments[$key]['status'] = checkAndMarkExpired($pdo, $apt['appointment_id'], $apt['appointment_date'], $apt['status']);
}

// Recalculate counts with updated statuses
$today_count = 0;
$completed_count = 0;
$upcoming_count = 0;
$cancelled_count = 0;
$expired_count = 0;
$prescribed_count_calc = 0;

foreach ($appointments as $apt) {
    if ($apt['appointment_date'] == date('Y-m-d')) {
        $today_count++;
    }
    if ($apt['status'] === 'Completed') {
        $completed_count++;
    } elseif ($apt['status'] === 'Scheduled') {
        $upcoming_count++;
    } elseif ($apt['status'] === 'Cancelled') {
        $cancelled_count++;
    } elseif ($apt['status'] === 'Expired') {
        $expired_count++;
    }
    if (!empty($apt['prescription_count'])) {
        $prescribed_count_calc++;
    }
}
$prescribed_count = $prescribed_count_calc;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Doctor Appointments</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Doctor/style.css?v=<?= time() ?>">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Doctor/navigation.php'; ?>

<!-- Main Container -->
<div class="dashboard-container">
    
    <!-- Page Header -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">Appointments</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">Manage your patient appointments and issue prescriptions</p>
    </div>
    
    <!-- Stats Counter Row -->
    <div class="stats-counter-row">
        <div class="counter-card blue-counter">
            <span class="counter-card-value"><?= $today_count ?></span> Today
        </div>
        <div class="counter-card green-counter">
            <span class="counter-card-value"><?= $completed_count ?></span> Completed
        </div>
        <div class="counter-card orange-counter">
            <span class="counter-card-value"><?= $upcoming_count ?></span> Upcoming
        </div>
        <div class="counter-card purple-counter">
            <span class="counter-card-value"><?= $prescribed_count ?></span> Prescribed
        </div>
        <div class="counter-card red-counter">
            <span class="counter-card-value"><?= $expired_count ?></span> Expired
        </div>
        <div class="counter-card gray-counter">
            <span class="counter-card-value"><?= $cancelled_count ?></span> Cancelled
        </div>
    </div>
    
    <!-- Action Bar (Search & Pills) -->
    <div class="action-bar-row">
        <!-- Search Input -->
        <div class="search-input-wrapper">
            <span class="search-field-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="searchInput" class="search-input-field" placeholder="Search patient or type..." onkeyup="filterAppointments()">
        </div>
        
        <!-- Filter Pills -->
        <div class="filter-pills-row">
            <button class="filter-pill-button active-pill" onclick="setPillFilter('All', this)">All</button>
            <button class="filter-pill-button" onclick="setPillFilter('Upcoming', this)">Upcoming</button>
            <button class="filter-pill-button" onclick="setPillFilter('Completed', this)">Completed</button>
            <button class="filter-pill-button" onclick="setPillFilter('Expired', this)">Expired</button>
            <button class="filter-pill-button" onclick="setPillFilter('Cancelled', this)">Cancelled</button>
        </div>
    </div>
    
    <!-- Appointments Table Card -->
    <div class="table-card">
        <div class="table-wrapper">
            <table class="custom-table" id="appointmentsTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Prescription</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--slate-400);">
                                <i class="fa-regular fa-calendar-times" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--slate-300);"></i>
                                No appointments found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appt): 
                            // Compute patient initials
                            $names = explode(' ', $appt['full_name']);
                            $initials = '';
                            foreach ($names as $n) {
                                if (!empty($n)) $initials .= strtoupper($n[0]);
                            }
                            $initials = substr($initials, 0, 2);

                            // Color hash for initials circle background
                            $colors = ['#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#ef4444', '#0d9488', '#6366f1'];
                            $charCodeSum = 0;
                            for ($i = 0; $i < strlen($appt['full_name']); $i++) {
                                $charCodeSum += ord($appt['full_name'][$i]);
                            }
                            $bgColor = $colors[$charCodeSum % count($colors)];

                            // Status mapping and styling
                            $data_status = $appt['status'] === 'Scheduled' ? 'Upcoming' : $appt['status'];
                            
                            $status_label = $data_status;
                            $status_bg = '#f1f5f9';
                            $status_color = '#475569';
                            
                            if ($appt['status'] === 'Completed') {
                                $status_bg = '#d1fae5';
                                $status_color = '#065f46';
                            } elseif ($appt['status'] === 'Cancelled') {
                                $status_bg = '#fee2e2';
                                $status_color = '#991b1b';
                            } elseif ($appt['status'] === 'Expired') {
                                $status_bg = '#fef3c7';
                                $status_color = '#92400e';
                            }
                            
                            // Check prescription count
                            $has_prescription = $appt['prescription_count'] > 0;
                        ?>
                            <tr class="appt-row" data-status="<?= $data_status ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="patient-initial-circle" style="background-color: <?= $bgColor ?>; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: 700; font-size: 13px;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div class="patient-name-text"><?= e($appt['full_name']) ?></div>
                                            <div class="patient-age-subtext">Age <?= getAge($appt['date_of_birth']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($appt['reason']) ?></td>
                                <td>
                                    <div><?= date('Y-m-d', strtotime($appt['appointment_date'])) ?></div>
                                    <div class="time-row-subtext"><i class="fa-regular fa-clock"></i> <?= date('h:i AM', strtotime($appt['appointment_time'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge success" style="background-color: <?= $status_bg ?>; color: <?= $status_color ?>; border-radius: 9999px; text-transform: none; font-weight: 600; font-size: 11px; padding: 4px 10px; display: inline-block;">
                                        <?= $status_label ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_prescription): ?>
                                        <span class="prescription-issued-badge">
                                            <i class="fa-solid fa-link"></i> Issued
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--slate-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="Patient_medicalRecords.php?id=<?= $appt['patient_id'] ?>" class="btn-view-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid var(--slate-200); background-color: var(--white); color: var(--slate-700); cursor: pointer; transition: all 0.2s;">
                                        <i class="fa-regular fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'All';

function setPillFilter(status, buttonElement) {
    // Remove active pill class
    const buttons = document.querySelectorAll('.filter-pill-button');
    buttons.forEach(btn => btn.classList.remove('active-pill'));
    
    // Add active class to clicked button
    buttonElement.classList.add('active-pill');
    currentStatusFilter = status;
    
    filterAppointments();
}

function filterAppointments() {
    const searchVal = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.appt-row');
    
    rows.forEach(row => {
        const patientName = row.querySelector('.patient-name-text').innerText.toLowerCase();
        const type = row.cells[1].innerText.toLowerCase();
        const status = row.getAttribute('data-status');
        
        const matchesSearch = patientName.includes(searchVal) || type.includes(searchVal);
        const matchesPill = (currentStatusFilter === 'All') || (status === currentStatusFilter);
        
        if (matchesSearch && matchesPill) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

</body>
</html>
