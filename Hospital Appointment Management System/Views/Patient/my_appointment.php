<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/PatientRepository.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as patient
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../Login/login.php');
    exit;
}

// Debug: Check current session
error_log('Patient session: ' . json_encode($_SESSION['user']));

$patientRepository = new PatientRepository($pdo);
$patient = $patientRepository->getPatientByUserId($_SESSION['user']['user_id']);
$patient_id = $patient['patient_id'] ?? null;
$patient_name = $patient['full_name'] ?? 'Patient';

// Debug: Check patient lookup
error_log('Patient lookup result: ' . json_encode($patient));

$appointments = [];
if ($patient_id) {
    $appointments = $patientRepository->getAppointmentsByPatientId($patient_id);
    
    // Debug: Check appointment query
    error_log('Appointments found for ' . $patient_id . ': ' . count($appointments));
    error_log('Appointments data: ' . json_encode($appointments));
} else {
    error_log('No patient_id found for user_id: ' . $_SESSION['user']['user_id']);
}

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

// Sort appointments: Scheduled (Upcoming) first, then Completed, Expired, Cancelled
usort($appointments, function($a, $b) {
    $order = ['Scheduled' => 1, 'Completed' => 2, 'Expired' => 3, 'Cancelled' => 4];
    $orderA = $order[$a['status']] ?? 5;
    $orderB = $order[$b['status']] ?? 5;
    if ($orderA !== $orderB) {
        return $orderA - $orderB;
    }
    if ($a['appointment_date'] !== $b['appointment_date']) {
        return strcmp($a['appointment_date'], $b['appointment_date']);
    }
    return strcmp($a['appointment_time'], $b['appointment_time']);
});

$total_count = count($appointments);
$scheduled_count = 0;
$completed_count = 0;
$cancelled_count = 0;
$expired_count = 0;

foreach ($appointments as $apt) {
    if ($apt['status'] === 'Scheduled') {
        $scheduled_count++;
    } elseif ($apt['status'] === 'Completed') {
        $completed_count++;
    } elseif ($apt['status'] === 'Cancelled') {
        $cancelled_count++;
    } elseif ($apt['status'] === 'Expired') {
        $expired_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - MediCare</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Patient/style.css">
    <link rel="stylesheet" href="../Layout/Patient/my_appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="patient-page-bg">

<?php include '../Layout/Patient/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Page Header -->
    <div class="booking-page-header">
        <h1>My Appointments</h1>
        <p>View and manage all your appointments</p>
    </div>

    <!-- Stats Summary Cards -->
    <div class="apt-stats-grid">
        <div class="apt-stat-card blue">
            <div class="apt-stat-number blue" id="statScheduledCount"><?= $scheduled_count ?></div>
            <div class="apt-stat-label blue">Upcoming</div>
        </div>
        <div class="apt-stat-card green">
            <div class="apt-stat-number green"><?= $completed_count ?></div>
            <div class="apt-stat-label green">Completed</div>
        </div>
        <div class="apt-stat-card orange">
            <div class="apt-stat-number orange"><?= $expired_count ?></div>
            <div class="apt-stat-label orange">Expired</div>
        </div>
        <div class="apt-stat-card red">
            <div class="apt-stat-number red" id="statCancelledCount"><?= $cancelled_count ?></div>
            <div class="apt-stat-label red">Cancelled</div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="apt-filter-card">
        <div class="apt-search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="aptSearchInput" class="doctor-search-input" placeholder="Search by doctor or specialty...">
        </div>
        <div class="apt-filter-tabs" id="aptFilterTabs">
            <button class="apt-filter-btn active" data-filter="All">All</button>
            <button class="apt-filter-btn" data-filter="Scheduled">Upcoming</button>
            <button class="apt-filter-btn" data-filter="Completed">Completed</button>
            <button class="apt-filter-btn" data-filter="Expired">Expired</button>
            <button class="apt-filter-btn" data-filter="Cancelled">Cancelled</button>
        </div>
    </div>

    <!-- Appointment List -->
    <div class="apt-list" id="aptList">
        <?php if (!empty($appointments)): ?>
            <?php foreach ($appointments as $apt): ?>
                <?php 
                $badge_class = strtolower($apt['status']);
                if ($badge_class === 'scheduled') {
                    $badge_class = 'upcoming'; // matches theme style sheet class
                }
                $date_display = date("M j, Y", strtotime($apt['appointment_date']));
                $formatted_time = date("g:i A", strtotime($apt['appointment_time']));
                ?>
                <div class="apt-row" id="apt-row-<?= e($apt['appointment_id']) ?>" data-status="<?= e($apt['status']) ?>" data-doctor="<?= e(strtolower($apt['doctor_name'])) ?>" data-specialty="<?= e(strtolower($apt['specialization'])) ?>">
                    <div class="apt-row-left">
                        <div class="doctor-initial-circle lg" style="background-color: <?= e($apt['color']) ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">
                            <?= e($apt['initials']) ?>
                        </div>
                        <div class="apt-row-info">
                            <div class="apt-doctor-name"><?= e($apt['doctor_name']) ?></div>
                            <div class="apt-doctor-sub"><?= e($apt['specialization']) ?> · <?= e($apt['reason']) ?></div>
                            <div class="apt-meta">
                                <span><i class="fa-regular fa-calendar"></i> <?= e($apt['appointment_date']) ?></span>
                                <span><i class="fa-regular fa-clock"></i> <?= e($formatted_time) ?></span>
                                <span class="apt-fee">$<?= e(isset($apt['payment_amount']) ? number_format($apt['payment_amount'], 2) : number_format($apt['consultation_fee'] + 10.00, 2)) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="apt-row-right">
                        <span class="apt-badge <?= $badge_class ?>"><?= e($apt['status'] === 'Scheduled' ? 'Upcoming' : $apt['status']) ?></span>
                        <?php if ($apt['status'] === 'Scheduled'): ?>
                            <button type="button" class="apt-action-btn reschedule" onclick="openRescheduleModal('<?= e($apt['appointment_id']) ?>', '<?= e(addslashes($apt['doctor_name'])) ?>', '<?= e($apt['appointment_date']) ?>', '<?= e($apt['appointment_time']) ?>', '<?= e($date_display) ?>', '<?= e($formatted_time) ?>')"><i class="fa-solid fa-rotate"></i> Reschedule</button>
                            <button type="button" class="apt-action-btn cancel" onclick="openCancelModal('<?= e($apt['appointment_id']) ?>', '<?= e(addslashes($apt['doctor_name'])) ?>', '<?= e($date_display) ?>', '<?= e($formatted_time) ?>')"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        <?php elseif ($apt['status'] === 'Expired'): ?>
                            <button type="button" class="apt-action-btn reschedule" onclick="openRescheduleModal('<?= e($apt['appointment_id']) ?>', '<?= e(addslashes($apt['doctor_name'])) ?>', '<?= e($apt['appointment_date']) ?>', '<?= e($apt['appointment_time']) ?>', '<?= e($date_display) ?>', '<?= e($formatted_time) ?>')"><i class="fa-solid fa-rotate"></i> Reschedule</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 24px; text-align: center; color: var(--slate-500); width: 100%;">No appointments found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("aptSearchInput");
    const filterBtns = document.querySelectorAll("#aptFilterTabs .apt-filter-btn");
    const aptRows = document.querySelectorAll("#aptList .apt-row");

    let currentFilter = "All";
    let searchQuery = "";

    function filterAppointments() {
        aptRows.forEach(row => {
            const status = row.getAttribute("data-status");
            const doctor = row.getAttribute("data-doctor");
            const specialty = row.getAttribute("data-specialty");

            const matchesFilter = (currentFilter === "All" || status === currentFilter);
            const matchesSearch = (doctor.includes(searchQuery) || specialty.includes(searchQuery));

            if (matchesFilter && matchesSearch) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // Filter tab click handlers
    filterBtns.forEach(btn => {
        btn.addEventListener("click", function() {
            filterBtns.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            currentFilter = this.getAttribute("data-filter");
            filterAppointments();
        });
    });

    // Search input handler
    searchInput.addEventListener("input", function() {
        searchQuery = this.value.toLowerCase().trim();
        filterAppointments();
    });
});
</script>
<?php include 'appointment_modals.php'; ?>
</body>
</html>