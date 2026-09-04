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

$patientRepository = new PatientRepository($pdo);
$patient = $patientRepository->getPatientByUserId($_SESSION['user']['user_id']);
$patient_id = $patient['patient_id'] ?? null;
$patient_name = $patient['full_name'] ?? 'Patient';

if (!function_exists('checkAndMarkExpired')) {
    function checkAndMarkExpired($pdo, $appointment_id, $appointment_date, $current_status) {
        if (in_array($current_status, ['Completed', 'Cancelled', 'Expired'])) {
            return $current_status;
        }
        $today = new DateTime('today');
        $appt_date = new DateTime($appointment_date);
        if ($appt_date < $today) {
            require_once '../../Models/Appointment.php';
            $appointment = Appointment::load($appointment_id);
            if ($appointment) {
                $appointment->expire();
            }
            return 'Expired';
        }
        return $current_status;
    }
}

$upcoming_appointments = [];
$total_visits = 0;
$upcoming_count = 0;

if ($patient_id) {
    // First, fetch all active Scheduled appointments to update expired ones
    $raw_apts = $patientRepository->getScheduledAppointmentsRaw($patient_id);
    foreach ($raw_apts as $r_apt) {
        checkAndMarkExpired($pdo, $r_apt['appointment_id'], $r_apt['appointment_date'], $r_apt['status']);
    }

    $upcoming_appointments = $patientRepository->getUpcomingAppointments($patient_id);
    $upcoming_count = count($upcoming_appointments);

    $total_visits = $patientRepository->countCompletedAppointments($patient_id);
}

// Fetch recent medical records (up to 3)
$recent_records = [];
if ($patient_id) {
    $recent_records = $patientRepository->getRecentMedicalRecords($patient_id, 3);
}

$next_apt_text = "No upcoming appointments scheduled.";
if ($upcoming_count > 0) {
    $first_apt = $upcoming_appointments[0];
    $date_formatted = date("M j", strtotime($first_apt['appointment_date']));
    $next_apt_text = "Your next appointment is <span style=\"font-weight:700;\">" . e($date_formatted) . " with " . e($first_apt['doctor_name']) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Dashboard</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Patient/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Patient/navigation.php'; ?>

<!-- Dashboard Core Container -->
<div class="dashboard-container">
    
    <!-- Welcome Blue Banner Card -->
    <div class="welcome-banner-blue">
        <div style="font-size: 13px; font-weight: 500; opacity: 0.8; text-transform: capitalize; margin-bottom: 2px;">Welcome</div>
        <h2>Welcome back, <?= e($patient_name) ?>!</h2>
        <p><?= $next_apt_text ?></p>
        
        <div class="banner-buttons">
            <a href="medical_records.php" class="btn-banner-outline">
                <i class="fa-regular fa-circle-check" style="color: #59FF1A;"></i> <?= (int)$total_visits ?> Total Visits
            </a>
            <a href="my_appointment.php" class="btn-banner-outline">
                <i class="fa-regular fa-calendar"></i> <span id="bannerUpcomingCount"><?= (int)$upcoming_count ?></span> Upcoming
            </a>
            <a href="prescriptions.php" class="btn-banner-outline">
                <i class="fa-solid fa-capsules"></i> Prescriptions
            </a>
        </div>
    </div>
    
     <!-- Stats Indicator Grid -->
    <div class="stats-grid">
        <!-- Stat 1 -->
        <a href="book_appointment.php" class="stat-card" style="text-decoration: none;">
            <div class="stat-card-info">
                <div class="stat-card-icon blue" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Book Appointment</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Schedule a visit</div>
            </div>
        </a>
        
        <!-- Stat 2 -->
        <a href="medical_records.php" class="stat-card" style="text-decoration: none;">
            <div class="stat-card-info">
                <div class="stat-card-icon teal" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                    <i class="fa-regular fa-file"></i>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Medical Records</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">View your history</div>
            </div>
        </a>
        
        <!-- Stat 3 -->
        <a href="prescriptions.php" class="stat-card" style="text-decoration: none;">
            <div class="stat-card-info">
                <div class="stat-card-icon purple" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                   <i class="fa-solid fa-capsules"></i>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Prescriptions</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Active medications</div>
            </div>
        </a>
        
        <!-- Stat 4 -->
        <a href="patientProfile.php" class="stat-card" style="text-decoration: none;">
            <div class="stat-card-info">
                <div class="stat-card-icon purple" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px; background-color: var(--warning-light); color: var(--warning);">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Patient Profile</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Personal info & security</div>
            </div>
        </a>
    </div>

     <!-- Split Layout Grid -->
    <div class="dashboard-grid" style="grid-template-columns: 2.2fr 1.1fr;">
        
        <!-- Today's Schedule Card -->
<div class="schedule-list-card">

    <div class="schedule-list-header">
        <div>
            <h2 class="schedule-list-title">Upcoming Appointments</h2>
            <p class="schedule-list-subtitle"><span id="sectionUpcomingCount"><?= (int)$upcoming_count ?> scheduled</span></p>
        </div>
        <a href="my_appointment.php" class="view-all-link">
            View all
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </a>
    </div>

    <div class="schedule-items-wrapper">
        <?php if (!empty($upcoming_appointments)): ?>
            <?php foreach ($upcoming_appointments as $apt): ?>
                <?php 
                $date_display = date("M j, Y", strtotime($apt['appointment_date']));
                $time_display = date("g:i A", strtotime($apt['appointment_time']));
                ?>
                <div class="schedule-row-item" id="apt-item-<?= e($apt['appointment_id']) ?>">
                    <div class="schedule-doctor-info">
                        <div class="doctor-initial-circle" style="background-color: <?= e($apt['color']) ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= e($apt['initials']) ?>
                        </div>
                        <div>
                            <div class="doctor-name-text"><?= e($apt['doctor_name']) ?></div>
                            <div class="doctor-type-text"><?= e($apt['specialization']) ?> · <?= e($apt['reason']) ?></div>
                            <div class="doctor-datetime">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg><?= e($date_display) ?>
                                </span>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= e($time_display) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn reschedule" onclick="openRescheduleModal('<?= e($apt['appointment_id']) ?>', '<?= e(addslashes($apt['doctor_name'])) ?>', '<?= e($apt['appointment_date']) ?>', '<?= e($apt['appointment_time']) ?>', '<?= e($date_display) ?>', '<?= e($time_display) ?>')">Reschedule</button>
                        <button type="button" class="btn cancel" onclick="openCancelModal('<?= e($apt['appointment_id']) ?>', '<?= e(addslashes($apt['doctor_name'])) ?>', '<?= e($date_display) ?>', '<?= e($time_display) ?>')">Cancel</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 24px; color: var(--slate-500); text-align: center;">No upcoming appointments.</p>
        <?php endif; ?>
    </div>
</div>

 <!-- Right Column Cards -->
        <div class="card-list">

            <!-- Recent Medical Records Card -->
            <div class="table-card" style="padding: 24px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:700; color:var(--slate-900); margin-bottom:2px;">Recent Records</h3>
                        <p style="font-size:12px; color:var(--slate-400); margin:0;">Latest medical history</p>
                    </div>
                    <a href="medical_records.php" style="font-size:12px; font-weight:600; color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:4px;">
                        View all
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </a>
                </div>

                <?php if (!empty($recent_records)): ?>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($recent_records as $rec): ?>
                        <?php
                            $rec_date = date('M j, Y', strtotime($rec['created_at']));
                            $followup = $rec['follow_up_date'] ? date('M j', strtotime($rec['follow_up_date'])) : null;
                        ?>
                        <div style="background:var(--slate-50); border:1px solid var(--slate-100); border-radius:10px; padding:12px 14px;">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                                <div style="width:32px; height:32px; border-radius:50%; background-color:<?= e($rec['color']) ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0;">
                                    <?= e($rec['initials']) ?>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-size:12px; font-weight:600; color:var(--slate-700); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($rec['doctor_name']) ?></div>
                                    <div style="font-size:11px; color:var(--slate-400);"><?= e($rec['specialization']) ?> &middot; <?= $rec_date ?></div>
                                </div>
                            </div>
                            <?php if (!empty($rec['diagnosis'])): ?>
                                <div style="font-size:12px; font-weight:600; color:var(--slate-800); margin-bottom:3px;">
                                    <i class="fa-solid fa-stethoscope" style="color:var(--primary); margin-right:4px; font-size:11px;"></i>
                                    <?= e($rec['diagnosis']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rec['symptoms'])): ?>
                                <div style="font-size:11px; color:var(--slate-500); margin-bottom:4px;"><?= e(mb_strimwidth($rec['symptoms'], 0, 60, '...')) ?></div>
                            <?php endif; ?>
                            <?php if ($followup): ?>
                                <div style="display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#3b82f6; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px; margin-top:2px;">
                                    <i class="fa-regular fa-calendar"></i> Follow-up: <?= $followup ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:30px 0;">
                        <i class="fa-regular fa-folder-open" style="font-size:32px; color:var(--slate-300); display:block; margin-bottom:8px;"></i>
                        <p style="font-size:13px; color:var(--slate-400); margin:0;">No medical records yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

<?php include 'appointment_modals.php'; ?>
</body>
</html>
                
