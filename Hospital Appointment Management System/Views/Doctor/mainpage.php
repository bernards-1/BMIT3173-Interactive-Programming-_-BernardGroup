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

$id = $_SESSION['user_id'];

// 1. Fetch doctor details from database
$doctor_stmt = $pdo->prepare('SELECT doctor_id, name FROM doctors WHERE user_id = ?');
$doctor_stmt->execute([$id]);
$doctor = $doctor_stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';
$doctor_name = $doctor ? $doctor['name'] : $_SESSION['user']['username'];

// Today's date
$today = date('Y-m-d');
$banner_date = date('l, F j');

// 2. Today's total appointments count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ?');
$stmt->execute([$doctor_id, $today]);
$todays_appointments_count = $stmt->fetchColumn();

// 3. Today's remaining scheduled appointments
$stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = \'Scheduled\'');
$stmt->execute([$doctor_id, $today]);
$todays_remaining_count = $stmt->fetchColumn();

// 4. Today's completed appointments
$stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = \'Completed\'');
$stmt->execute([$doctor_id, $today]);
$todays_completed_count = $stmt->fetchColumn();

// 5. Doctor leaves pending review
$stmt = $pdo->prepare('SELECT COUNT(*) FROM doctor_leaves WHERE doctor_id = ? AND status = \'Pending\'');
$stmt->execute([$doctor_id]);
$pending_leaves_count = $stmt->fetchColumn();

// 6. Total unique patients under care
$stmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?');
$stmt->execute([$doctor_id]);
$total_patients_count = $stmt->fetchColumn();

// 7. Today's appointments list with patient details
$schedule_stmt = $pdo->prepare('
    SELECT 
        a.appointment_id, 
        a.appointment_time, 
        a.reason, 
        a.status, 
        p.patient_id, 
        p.full_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE a.doctor_id = ? AND a.appointment_date = ? 
    ORDER BY a.appointment_time ASC
');
$schedule_stmt->execute([$doctor_id, $today]);
$todays_schedule = $schedule_stmt->fetchAll();
// 8. Fetch doctor's 3 most recent prescriptions from database
$recent_pres_stmt = $pdo->prepare('
    SELECT 
        pr.dosage,
        pr.frequency,
        pr.created_at,
        m.brand_name,
        p.full_name as patient_name
    FROM prescriptions pr
    JOIN medical_records mr ON pr.record_id = mr.medical_record_id
    JOIN patients p ON mr.patient_id = p.patient_id
    JOIN medicines m ON pr.medicine_id = m.medicine_id
    WHERE mr.doctor_id = ?
    ORDER BY pr.created_at DESC, pr.prescription_id DESC
    LIMIT 3
');
$recent_pres_stmt->execute([$doctor_id]);
$recent_prescriptions = $recent_pres_stmt->fetchAll();

$temp_has_active = false;
$active_count = 0;
foreach ($todays_schedule as $appt) {
    if ($appt['status'] === 'Scheduled' && !$temp_has_active) {
        $temp_has_active = true;
        $active_count = 1;
    }
}

// Fetch medicines list via Web Service Consumption
$medicines_list = [];
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$base_dir = '/Hospital Appointment Management System';
if (strpos($script, '/Hospital Appointment Management System') !== false) {
    $base_dir = '/Hospital Appointment Management System';
} else {
    $parts = explode('/', trim($script, '/'));
    if (!empty($parts)) {
        $base_dir = '/' . $parts[0];
    }
}
$api_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $host . $base_dir . '/api/medicines.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
$api_response = curl_exec($ch);
curl_close($ch);

if ($api_response) {
    $api_data = json_decode($api_response, true);
    if (isset($api_data['status']) && $api_data['status'] === 'success') {
        $medicines_list = $api_data['data'];
    }
}

// Fallback to direct DB query if Web Service is offline or times out
if (empty($medicines_list)) {
    $med_stmt = $pdo->query('SELECT medicine_id, brand_name, generic_name FROM medicines ORDER BY brand_name');
    $medicines_list = $med_stmt->fetchAll();
}

// Handle form submission to Complete Consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_consultation') {
    $appt_id = $_POST['appointment_id'];
    $symptoms = isset($_POST['symptoms']) ? trim($_POST['symptoms']) : '';
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $follow_up_time = !empty($_POST['follow_up_time']) ? $_POST['follow_up_time'] : null;
    
    // 1. Get the patient_id and doctor_id from the appointment
    $appt_stmt = $pdo->prepare('SELECT patient_id, doctor_id FROM appointments WHERE appointment_id = ?');
    $appt_stmt->execute([$appt_id]);
    $appt_info = $appt_stmt->fetch();
    
    if ($appt_info) {
        $patient_id = $appt_info['patient_id'];
        $doctor_id = $appt_info['doctor_id'];
        
        // Generate a new medical record ID (e.g. MR002)
        $count_stmt = $pdo->query('SELECT COUNT(*) FROM medical_records');
        $mr_count = $count_stmt->fetchColumn() + 1;
        $medical_record_id = 'MR' . str_pad($mr_count, 3, '0', STR_PAD_LEFT);
        
        // 2. Insert into medical_records
        $ins_mr = $pdo->prepare('
            INSERT INTO medical_records (medical_record_id, patient_id, doctor_id, appointment_id, diagnosis, symptoms, notes, follow_up_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $ins_mr->execute([$medical_record_id, $patient_id, $doctor_id, $appt_id, $diagnosis, $symptoms, $notes, $follow_up_date]);
        
        // 3. Update appointment status to 'Completed' using State Pattern
        require_once '../../Models/Appointment.php';
        $appointment = Appointment::load($appt_id);
        if ($appointment) {
            $appointment->complete();
        }
        
        // 4. If prescription toggle is on, insert into prescriptions (supports multiple medicines!)
        if (isset($_POST['issue_prescription']) && $_POST['issue_prescription'] === '1' && !empty($_POST['medicine_id'])) {
            $med_ids = $_POST['medicine_id']; // Array
            $dosages = $_POST['dosage']; // Array
            $frequencies = $_POST['frequency']; // Array
            $durations = $_POST['duration']; // Array
            $instructions_list = $_POST['instructions']; // Array
            $quantities = $_POST['quantity']; // Array
            
            for ($i = 0; $i < count($med_ids); $i++) {
                if (empty($med_ids[$i])) continue;
                
                $med_id = $med_ids[$i];
                $dosage = isset($dosages[$i]) ? trim($dosages[$i]) : '';
                $frequency = isset($frequencies[$i]) ? trim($frequencies[$i]) : '';
                $duration = isset($durations[$i]) ? trim($durations[$i]) : '';
                $instructions = isset($instructions_list[$i]) ? trim($instructions_list[$i]) : '';
                $quantity = isset($quantities[$i]) ? (int)$quantities[$i] : 30;
                
                // Generate prescription ID (e.g. PR002)
                $count_pr_stmt = $pdo->query('SELECT COUNT(*) FROM prescriptions');
                $pr_count = $count_pr_stmt->fetchColumn() + 1;
                $prescription_id = 'PR' . str_pad($pr_count, 3, '0', STR_PAD_LEFT);
                
                $ins_pr = $pdo->prepare('
                    INSERT INTO prescriptions (prescription_id, record_id, medicine_id, dosage, frequency, duration, instructions, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $ins_pr->execute([$prescription_id, $medical_record_id, $med_id, $dosage, $frequency, $duration, $instructions, $quantity]);
            }
        }
        
        // 5. If follow up date is set, automatically make a new appointment for the patient!
        if ($follow_up_date) {
            $appt_time = $follow_up_time;
            if (!$appt_time) {
                // Get original appointment time to reuse for follow-up
                $orig_time_stmt = $pdo->prepare('SELECT appointment_time FROM appointments WHERE appointment_id = ?');
                $orig_time_stmt->execute([$appt_id]);
                $orig_time = $orig_time_stmt->fetchColumn();
                $appt_time = $orig_time ? $orig_time : '10:00:00';
            }
            
            // Generate appointment ID (e.g. A006)
            $count_appt_stmt = $pdo->query('SELECT COUNT(*) FROM appointments');
            $appt_count = $count_appt_stmt->fetchColumn() + 1;
            $new_appt_id = 'A' . str_pad($appt_count, 3, '0', STR_PAD_LEFT);
            
            $ins_appt = $pdo->prepare('
                INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, status) 
                VALUES (?, ?, ?, ?, ?, \'Follow-up\', \'Scheduled\')
            ');
            $ins_appt->execute([$new_appt_id, $patient_id, $doctor_id, $follow_up_date, $appt_time]);
        }
        
        // Success redirect
        header('Location: mainpage.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Doctor Dashboard</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Doctor/style.css?v=<?= time() ?>">
    <style>
    /* Inline Fallback Styles for Consultation Modal */
    .consultation-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.6);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .consultation-modal-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }
    .consultation-modal-container {
        background-color: var(--white);
        border-radius: 16px;
        width: 90%;
        max-width: 550px;
        max-height: 90%;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: translateY(20px);
        transition: transform 0.3s ease;
    }
    .consultation-modal-overlay.active .consultation-modal-container {
        transform: translateY(0);
    }
    .consultation-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--slate-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .consultation-modal-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--slate-900);
    }
    .consultation-modal-subtitle {
        font-size: 12px;
        color: var(--slate-400);
        margin-top: 2px;
    }
    .consultation-modal-close {
        font-size: 20px;
        color: var(--slate-400);
        cursor: pointer;
        background: none;
        border: none;
        transition: color 0.2s ease;
    }
    .consultation-modal-close:hover {
        color: var(--slate-600);
    }
    .consultation-modal-body {
        padding: 24px;
    }
    .modal-patient-card {
        background-color: #f8fafc;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .modal-patient-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .modal-patient-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--slate-900);
    }
    .modal-patient-sub {
        font-size: 12px;
        color: var(--slate-500);
        margin-top: 2px;
    }
    .prescription-toggle-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        background-color: #f8fafc;
        border: 1px solid var(--slate-100);
        border-radius: 12px;
        margin-bottom: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .prescription-toggle-row.active {
        background-color: #faf5ff;
        border-color: #e9d5ff;
    }
    .prescription-toggle-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: var(--slate-700);
        transition: color 0.2s ease;
    }
    .prescription-toggle-row.active .prescription-toggle-label {
        color: #7c3aed;
    }
    .switch-container {
        position: relative;
        width: 44px;
        height: 24px;
    }
    .switch-input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 24px;
    }
    .switch-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    .switch-input:checked + .switch-slider {
        background-color: #a855f7;
    }
    .switch-input:checked + .switch-slider:before {
        transform: translateX(20px);
    }
    .prescription-form-area {
        display: none;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        background-color: #fafafa;
        margin-bottom: 20px;
    }
    .prescription-section-title {
        font-size: 12px;
        font-weight: 700;
        color: var(--slate-400);
        letter-spacing: 0.05em;
        margin-bottom: 12px;
        text-transform: uppercase;
    }
    .modal-actions-row {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    .btn-modal-cancel {
        flex: 1;
        padding: 12px;
        background-color: var(--white);
        border: 1px solid var(--slate-200);
        color: var(--slate-600);
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-modal-cancel:hover {
        background-color: var(--slate-50);
    }
    .btn-modal-submit {
        flex: 1.2;
        padding: 12px;
        background-color: #10b981;
        border: none;
        color: var(--white);
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .btn-modal-submit:hover {
        background-color: #059669;
    }
    
    /* Searchable Dropdown Custom Select styles */
    .searchable-select-container {
        position: relative;
    }
    .searchable-select-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid var(--slate-200);
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    }
    .searchable-select-option {
        padding: 10px 12px;
        font-size: 13px;
        color: var(--slate-700);
        cursor: pointer;
        transition: background 0.15s ease;
        text-align: left;
    }
    .searchable-select-option:hover {
        background-color: var(--slate-50);
        color: var(--primary-blue);
    }
    </style>
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Doctor/navigation.php'; ?>

<!-- Dashboard Core Container -->
<div class="dashboard-container">
    
    <!-- Welcome Blue Banner Card -->
    <div class="welcome-banner-blue">
        <div style="font-size: 13px; font-weight: 500; opacity: 0.8; text-transform: capitalize; margin-bottom: 2px;">Good evening</div>
        <h2><?= e($doctor_name) ?></h2>
        <p>You have <span style="font-weight:700;"><?= $todays_appointments_count ?> appointments</span> scheduled today · <?= $banner_date ?></p>
        
        <div class="banner-buttons">
            <a href="schedule.php" class="btn-banner-outline">
                <i class="fa-regular fa-calendar"></i> View Schedule
            </a>
            <a href="medicalRecords.php" class="btn-banner-outline">
                <i class="fa-solid fa-capsules"></i> Prescriptions
            </a>
        </div>
    </div>
    
    <!-- Stats Indicator Grid -->
    <div class="stats-grid">
        <!-- Stat 1 -->
        <div class="stat-card">
            <div class="stat-card-info">
                <div class="stat-card-icon blue" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-card-value" style="font-size: 32px; line-height: 1;"><?= $todays_appointments_count ?></div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Today's Patients</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;"><?= $todays_remaining_count ?> remaining</div>
            </div>
        </div>
        
        <!-- Stat 2 -->
        <div class="stat-card">
            <div class="stat-card-info">
                <div class="stat-card-icon teal" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-card-value" style="font-size: 32px; line-height: 1;"><?= $todays_completed_count ?></div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Completed</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Out of <?= $todays_appointments_count ?> today</div>
            </div>
        </div>
        
        <!-- Stat 3 -->
        <div class="stat-card">
            <div class="stat-card-info">
                <div class="stat-card-icon purple" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px; background-color: var(--warning-light); color: var(--warning);">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div class="stat-card-value" style="font-size: 32px; line-height: 1;"><?= $pending_leaves_count ?></div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Pending Leaves</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Requests pending</div>
            </div>
        </div>
        
        <!-- Stat 4 -->
        <div class="stat-card">
            <div class="stat-card-info">
                <div class="stat-card-icon purple" style="width: 32px; height: 32px; font-size: 14px; margin-bottom: 12px; border-radius: 6px;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="stat-card-value" style="font-size: 32px; line-height: 1;"><?= $total_patients_count ?></div>
                <div style="font-size: 13px; font-weight: 600; color: var(--slate-700); margin-top: 6px;">Total Patients</div>
                <div style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Unique patients</div>
            </div>
        </div>
    </div>
    
    <!-- Split Layout Grid -->
    <div class="dashboard-grid" style="grid-template-columns: 2.2fr 1.1fr;">
        
        <!-- Today's Schedule Card -->
        <div class="schedule-list-card">
            <div class="schedule-list-header">
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900);">Today's Schedule</h3>
                    <p style="font-size: 12px; color: var(--slate-400); margin-top: 2px;">
                        <?= $todays_completed_count ?> of <?= count($todays_schedule) ?> completed
                    </p>
                </div>
                <div class="schedule-badge-row">
                    <span class="badge success" style="background-color: #d1fae5; color: #065f46; border-radius: 9999px; padding: 4px 12px; text-transform: none;">
                        <?= $todays_completed_count ?> done
                    </span>
                    <span class="badge success" style="background-color: #dbeafe; color: #1e40af; border-radius: 9999px; padding: 4px 12px; text-transform: none;">
                        <?= count($todays_schedule) - $todays_completed_count ?> pending
                    </span>
                </div>
            </div>
            
            <div class="schedule-items-wrapper">
                <?php if (empty($todays_schedule)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--slate-400); font-size: 14px;">
                        <i class="fa-regular fa-calendar-xmark" style="font-size: 28px; margin-bottom: 12px; display: block; color: var(--slate-300);"></i>
                        No appointments scheduled for today.
                    </div>
                <?php else: ?>
                    <?php 
                    $has_active = false;
                    foreach ($todays_schedule as $appt): 
                        // Generate patient initials
                        $names = explode(' ', $appt['full_name']);
                        $initials = '';
                        foreach ($names as $n) {
                            $initials .= strtoupper(substr($n, 0, 1));
                        }
                        $initials = substr($initials, 0, 2);

                        // Format time to 12-hour format
                        $time_formatted = date('h:i A', strtotime($appt['appointment_time']));

                        // Generate background color dynamically based on initials
                        $colors = ['#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
                        $color_index = abs(crc32($appt['full_name'])) % count($colors);
                        $bg_color = $colors[$color_index];
                    ?>
                        <div class="schedule-row-item">
                            <div class="schedule-patient-info">
                                <div class="patient-initial-circle" style="background-color: <?= $bg_color ?>;"><?= e($initials) ?></div>
                                <div>
                                    <div class="patient-name-text"><?= e($appt['full_name']) ?></div>
                                    <div class="patient-type-text"><?= e($appt['reason']) ?></div>
                                </div>
                            </div>
                            <div class="schedule-time-status" style="display: flex; align-items: center; gap: 12px;">
                                <div class="schedule-time-text"><?= $time_formatted ?></div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($appt['status'] === 'Completed'): ?>
                                        <span class="badge success" style="background-color: #d1fae5; color: #065f46; border-radius: 6px; text-transform: none; font-weight: 600; font-size: 11px; padding: 5px 10px; display: inline-block;">Completed</span>
                                    <?php else: 
                                        $appt_time_timestamp = strtotime(date('Y-m-d') . ' ' . $appt['appointment_time']);
                                        $is_overdue = ($appt_time_timestamp < time());
                                        if ($is_overdue):
                                        ?>
                                            <span style="font-size: 12px; color: #f59e0b; font-weight: 600; margin-right: 4px;">Overdue</span>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--slate-400); font-weight: 500; margin-right: 4px;">Upcoming</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <a href="Patient_medicalRecords.php?id=<?= $appt['patient_id'] ?>" class="btn-row-action secondary" style="background-color: var(--slate-100); color: var(--slate-700); text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--slate-200); cursor: pointer;">
                                        <i class="fa-regular fa-eye"></i> View
                                    </a>
                                    
                                    <?php if ($appt['status'] !== 'Completed'): ?>
                                        <button class="btn-row-action" onclick="openConsultationModal('<?= $appt['appointment_id'] ?>', '<?= e(addslashes($appt['full_name'])) ?>', '<?= $time_formatted ?>', '<?= e(addslashes($appt['reason'])) ?>', '<?= $bg_color ?>', '<?= e($initials) ?>')" style="background-color: var(--primary-blue); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; outline: none;">
                                            <i class="fa-solid fa-plus"></i> Add
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column Cards -->
        <div class="card-list">
            
            <!-- Recent Prescriptions Card -->
            <div class="table-card" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900);">Recent Prescriptions</h3>
                    <a href="#" style="font-size: 13px; font-weight: 600; color: var(--primary-blue); text-decoration: none;">All <i class="fa-solid fa-arrow-trend-up" style="transform: rotate(45deg); font-size: 10px; margin-left: 2px;"></i></a>
                </div>
                
                <div class="prescriptions-wrapper">
                    <?php if (empty($recent_prescriptions)): ?>
                        <div style="font-size: 13px; color: var(--slate-400); text-align: center; padding: 20px 0;">
                            No prescriptions issued yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_prescriptions as $pres): 
                            $date_formatted = date('M j', strtotime($pres['created_at']));
                        ?>
                            <div class="prescription-list-item">
                                <div class="prescription-meta">
                                    <span class="prescription-patient"><?= e($pres['patient_name']) ?></span>
                                    <span class="prescription-date"><?= $date_formatted ?></span>
                                </div>
                                <div class="prescription-details"><?= e($pres['brand_name']) ?> <?= e($pres['dosage']) ?></div>
                                <div class="prescription-dosage"><?= e($pres['frequency']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions Card -->
            <div class="table-card" style="padding: 24px;">
                <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900); margin-bottom: 20px;">Quick Actions</h3>
                
                <div style="display: flex; flex-direction: column;">
                    <a href="schedule.php" class="quick-action-link blue-action">
                        <i class="fa-regular fa-calendar-days"></i> Manage Schedule
                    </a>
                    <a href="patients.php" class="quick-action-link teal-action">
                        <i class="fa-solid fa-address-book"></i> Patient Directory
                    </a>
                    <a href="medicalRecords.php" class="quick-action-link purple-action">
                        <i class="fa-solid fa-folder-open"></i> Medical Records
                    </a>
                    <a href="appointments.php" class="quick-action-link orange-action">
                        <i class="fa-solid fa-file-signature"></i> New Prescription
                    </a>
                </div>
            </div>
            
        </div>
        
    </div>
    
</div>

<!-- Start Consultation Modal -->
<div class="consultation-modal-overlay" id="consultationModalOverlay">
    <div class="consultation-modal-container">
        <div class="consultation-modal-header">
            <div>
                <h3 class="consultation-modal-title">Start Consultation</h3>
                <div class="consultation-modal-subtitle" id="modalTimeReason">11:00 AM · Check-up</div>
            </div>
            <button class="consultation-modal-close" onclick="closeConsultationModal()">&times;</button>
        </div>
        
        <form action="mainpage.php" method="POST" id="consultationForm">
            <input type="hidden" name="action" value="complete_consultation">
            <input type="hidden" name="appointment_id" id="modalApptId">
            
            <div class="consultation-modal-body">
                <!-- Patient Banner Card -->
                <div class="modal-patient-card">
                    <div class="modal-patient-info">
                        <div class="patient-initial-circle" id="modalPatientInitials" style="width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-weight: 700; font-size: 14px;">RD</div>
                        <div>
                            <div class="modal-patient-name" id="modalPatientName">Robert Davis</div>
                            <div class="modal-patient-sub" id="modalPatientSub">Age 58 · Type 2 Diabetes</div>
                        </div>
                    </div>
                    <span class="badge success" id="modalBadgeReason" style="background-color: #dbeafe; color: #1e40af; border-radius: 9999px; text-transform: none; font-weight: 600; padding: 4px 12px; font-size: 12px;">Check-up</span>
                </div>
                
                <!-- Presenting Symptoms -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; color: var(--slate-700); margin-bottom: 6px; display: block;">Presenting Symptoms <span style="color: #ef4444;">*</span></label>
                    <textarea name="symptoms" class="form-textarea" placeholder="e.g. BP 145/92, mild headache, occasional dizziness for past 2 weeks..." required style="height: 90px; resize: none;"></textarea>
                </div>
                
                <!-- Diagnosis -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; color: var(--slate-700); margin-bottom: 6px; display: block;">Diagnosis <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="diagnosis" class="form-select" placeholder="e.g. Hypertension Stage 1" required>
                </div>
                
                <!-- Clinical Notes -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; color: var(--slate-700); margin-bottom: 6px; display: block;">Clinical Notes</label>
                    <textarea name="notes" class="form-textarea" placeholder="Treatment plan, observations, patient education, referrals..." style="height: 90px; resize: none;"></textarea>
                </div>
                
                <!-- Schedule Follow-up Toggle -->
                <div class="prescription-toggle-row" id="followUpToggleRow" onclick="toggleFollowUpSwitch()" style="margin-bottom: 16px;">
                    <div class="prescription-toggle-label">
                        <i class="fa-regular fa-calendar-plus" style="font-size: 13px;"></i> Schedule Follow-up
                    </div>
                    <div class="switch-container">
                        <input type="checkbox" id="scheduleFollowUpCheck" name="schedule_follow_up" value="1" class="switch-input" onchange="handleFollowUpToggle(this)">
                        <span class="switch-slider"></span>
                    </div>
                </div>

                <!-- Schedule Follow-up Fields -->
                <div id="followUpFieldsArea" style="display: none; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: var(--slate-700); margin-bottom: 6px; display: block;">Follow-up Date</label>
                        <input type="date" name="follow_up_date" id="followUpDateInput" class="form-date-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: var(--slate-700); margin-bottom: 6px; display: block;">Follow-up Time</label>
                        <select name="follow_up_time" class="form-select" id="followUpTime">
                            <option value="">-- Select Time --</option>
                            <option value="08:00:00">08:00 AM</option>
                            <option value="08:30:00">08:30 AM</option>
                            <option value="09:00:00">09:00 AM</option>
                            <option value="09:30:00">09:30 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="10:30:00">10:30 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="11:30:00">11:30 AM</option>
                            <option value="12:00:00">12:00 PM</option>
                            <option value="12:30:00">12:30 PM</option>
                            <option value="13:00:00">01:00 PM</option>
                            <option value="13:30:00">01:30 PM</option>
                            <option value="14:00:00">02:00 PM</option>
                            <option value="14:30:00">02:30 PM</option>
                            <option value="15:00:00">03:00 PM</option>
                            <option value="15:30:00">03:30 PM</option>
                            <option value="16:00:00">04:00 PM</option>
                            <option value="16:30:00">04:30 PM</option>
                            <option value="17:00:00">05:00 PM</option>
                            <option value="17:30:00">05:30 PM</option>
                            <option value="18:00:00">06:00 PM</option>
                        </select>
                    </div>
                </div>
                
                <!-- Issue Prescription Toggle -->
                <div class="prescription-toggle-row" id="prescriptionToggleRow" onclick="togglePrescriptionSwitch()">
                    <div class="prescription-toggle-label">
                        <i class="fa-solid fa-link" style="font-size: 13px;"></i> Issue Prescription
                    </div>
                    <div class="switch-container">
                        <input type="checkbox" id="issuePrescriptionCheck" name="issue_prescription" value="1" class="switch-input" onchange="handlePrescriptionToggle(this)">
                        <span class="switch-slider"></span>
                    </div>
                </div>
                
                <!-- Prescription Form Area -->
                <div class="prescription-form-area" id="prescriptionFormArea">
                    <div id="prescriptionItemsContainer">
                        <!-- Medicine 1 (Default Row) -->
                        <div class="prescription-item-row" style="border-bottom: 1px dashed var(--slate-200); padding-bottom: 12px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 12px; font-weight: 700; color: var(--primary-blue);">Medicine #1</span>
                            </div>
                            
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Drug Name <span style="color: #ef4444;">*</span></label>
                                    <div class="searchable-select-container">
                                        <input type="text" class="form-select searchable-select-input" placeholder="Search drug..." onfocus="showSelectDropdown(this)" oninput="filterSelectOptions(this)">
                                        <input type="hidden" name="medicine_id[]" class="searchable-select-value" id="prescriptionMedId">
                                        <div class="searchable-select-dropdown">
                                            <div class="searchable-select-option" data-value="" onclick="selectSearchableOption(this, '', '-- Select Drug --')">-- Select Drug --</div>
                                            <?php foreach ($medicines_list as $med): ?>
                                                <div class="searchable-select-option" data-value="<?= $med['medicine_id'] ?>" onclick="selectSearchableOption(this, '<?= $med['medicine_id'] ?>', '<?= e($med['brand_name']) ?> (<?= e($med['generic_name']) ?>)')">
                                                    <?= e($med['brand_name']) ?> (<?= e($med['generic_name']) ?>)
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Dosage <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="dosage[]" class="form-select prescription-dosage-input" id="prescriptionDosage" placeholder="e.g. 500mg">
                                </div>
                            </div>
                            
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Frequency</label>
                                    <input type="text" name="frequency[]" class="form-select" placeholder="e.g. 2x / day">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Duration</label>
                                    <input type="text" name="duration[]" class="form-select" placeholder="e.g. 7 Days">
                                </div>
                            </div>
                            
                            <div class="form-grid" style="display: grid; grid-template-columns: 1.5fr 0.5fr; gap: 12px;">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Instructions</label>
                                    <input type="text" name="instructions[]" class="form-select" placeholder="e.g. Take after meals">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Qty</label>
                                    <input type="number" name="quantity[]" class="form-select" value="30" min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="form-select" id="addMedicineBtn" style="margin-top: 14px; border-style: dashed; border-color: #cbd5e1; background: none; color: var(--slate-600); font-weight: 600; display: flex; justify-content: center; align-items: center; gap: 6px; cursor: pointer;">
                        <i class="fa-solid fa-plus"></i> Add Another Medicine
                    </button>
                </div>
                
                <!-- Modal Actions Row -->
                <div class="modal-actions-row">
                    <button type="button" class="btn-modal-cancel" onclick="closeConsultationModal()">Close</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="fa-solid fa-circle-check"></i> Complete Consultation
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic Dialog / Modal JavaScript -->
<script>
console.log("Medicare consultation script loaded.");

// Searchable Select Dropdown functions
function showSelectDropdown(input) {
    document.querySelectorAll(".searchable-select-dropdown").forEach(d => {
        d.style.display = "none";
    });
    const container = input.closest(".searchable-select-container");
    const dropdown = container.querySelector(".searchable-select-dropdown");
    dropdown.style.display = "block";
    filterSelectOptions(input);
}

function filterSelectOptions(input) {
    const filter = input.value.toLowerCase();
    const container = input.closest(".searchable-select-container");
    const dropdown = container.querySelector(".searchable-select-dropdown");
    const options = dropdown.querySelectorAll(".searchable-select-option");
    
    options.forEach(opt => {
        const text = opt.textContent.toLowerCase();
        if (text.includes(filter)) {
            opt.style.display = "block";
        } else {
            opt.style.display = "none";
        }
    });
}

function selectSearchableOption(opt, value, text) {
    const container = opt.closest(".searchable-select-container");
    const input = container.querySelector(".searchable-select-input");
    const hidden = container.querySelector(".searchable-select-value");
    const dropdown = container.querySelector(".searchable-select-dropdown");
    
    input.value = text;
    hidden.value = value;
    dropdown.style.display = "none";
}

document.addEventListener("click", function(e) {
    if (!e.target.closest(".searchable-select-container")) {
        document.querySelectorAll(".searchable-select-dropdown").forEach(d => {
            d.style.display = "none";
        });
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const addBtn = document.getElementById("addMedicineBtn");
    const container = document.getElementById("prescriptionItemsContainer");
    
    if (addBtn && container) {
        addBtn.addEventListener("click", () => {
            const rowCount = container.querySelectorAll(".prescription-item-row").length + 1;
            const newRow = document.createElement("div");
            newRow.className = "prescription-item-row";
            newRow.style.borderBottom = "1px dashed var(--slate-200)";
            newRow.style.paddingBottom = "12px";
            newRow.style.marginBottom = "12px";
            
            const firstRow = container.querySelector(".prescription-item-row");
            const selectOptions = firstRow.querySelector(".searchable-select-dropdown").innerHTML;
            
            newRow.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 12px; font-weight: 700; color: var(--primary-blue);">Medicine #${rowCount}</span>
                    <button type="button" class="btn-remove-row" style="background: none; border: none; color: #ef4444; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;"><i class="fa-regular fa-trash-can"></i> Remove</button>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Drug Name <span style="color: #ef4444;">*</span></label>
                        <div class="searchable-select-container">
                            <input type="text" class="form-select searchable-select-input" placeholder="Search drug..." onfocus="showSelectDropdown(this)" oninput="filterSelectOptions(this)" required>
                            <input type="hidden" name="medicine_id[]" class="searchable-select-value">
                            <div class="searchable-select-dropdown">
                                ${selectOptions}
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Dosage <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="dosage[]" class="form-select prescription-dosage-input" placeholder="e.g. 500mg" required>
                    </div>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Frequency</label>
                        <input type="text" name="frequency[]" class="form-select" placeholder="e.g. 2x / day">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Duration</label>
                        <input type="text" name="duration[]" class="form-select" placeholder="e.g. 7 Days">
                    </div>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1.5fr 0.5fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Instructions</label>
                        <input type="text" name="instructions[]" class="form-select" placeholder="e.g. Take after meals">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--slate-600); margin-bottom: 4px; display: block;">Qty</label>
                        <input type="number" name="quantity[]" class="form-select" value="30" min="1">
                    </div>
                </div>
            `;
            
            newRow.querySelector(".btn-remove-row").addEventListener("click", () => {
                newRow.remove();
                const rows = container.querySelectorAll(".prescription-item-row");
                rows.forEach((row, index) => {
                    row.querySelector("span").textContent = `Medicine #${index + 1}`;
                });
            });
            
            container.appendChild(newRow);
        });
    }
});

function openConsultationModal(apptId, patientName, time, reason, bgColor, initials) {
    console.log("openConsultationModal triggered for apptId:", apptId);
    try {
        const modalApptId = document.getElementById('modalApptId');
        const modalPatientName = document.getElementById('modalPatientName');
        const modalPatientInitials = document.getElementById('modalPatientInitials');
        const modalTimeReason = document.getElementById('modalTimeReason');
        const modalBadgeReason = document.getElementById('modalBadgeReason');
        
        if (modalApptId) modalApptId.value = apptId;
        if (modalPatientName) modalPatientName.textContent = patientName;
        if (modalPatientInitials) {
            modalPatientInitials.textContent = initials;
            modalPatientInitials.style.backgroundColor = bgColor;
        }
        if (modalTimeReason) modalTimeReason.textContent = time + ' · ' + reason;
        if (modalBadgeReason) modalBadgeReason.textContent = reason;
        
        const subtext = document.getElementById('modalPatientSub');
        if (subtext) {
            if (patientName.includes('John Doe') || patientName.includes('John Smith')) {
                subtext.textContent = 'Age 38 · Routine Checkup';
            } else if (patientName.includes('Emma Wilson')) {
                subtext.textContent = 'Age 29 · Follow-up';
            } else {
                subtext.textContent = 'Age 58 · Type 2 Diabetes';
            }
        }
        
        const overlay = document.getElementById('consultationModalOverlay');
        if (overlay) {
            overlay.classList.add('active');
            console.log("Modal overlay class active added.");
        } else {
            console.error("consultationModalOverlay not found!");
        }
        document.body.style.overflow = 'hidden';
    } catch (e) {
        console.error("Error opening modal:", e);
    }
}

function closeConsultationModal() {
    const overlay = document.getElementById('consultationModalOverlay');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    const form = document.getElementById('consultationForm');
    if (form) form.reset();
    
    const followUpCheck = document.getElementById('scheduleFollowUpCheck');
    if (followUpCheck) {
        followUpCheck.checked = false;
        handleFollowUpToggle(followUpCheck);
    }
    
    // Clear searchable select inputs
    document.querySelectorAll(".searchable-select-input").forEach(i => i.value = "");
    document.querySelectorAll(".searchable-select-value").forEach(i => i.value = "");
    
    // Remove dynamic medicine rows, leaving only the first one
    const container = document.getElementById("prescriptionItemsContainer");
    if (container) {
        const rows = container.querySelectorAll(".prescription-item-row");
        rows.forEach((row, index) => {
            if (index > 0) row.remove();
        });
    }
    
    const formArea = document.getElementById('prescriptionFormArea');
    if (formArea) formArea.style.display = 'none';
    
    const toggleRow = document.getElementById('prescriptionToggleRow');
    if (toggleRow) toggleRow.classList.remove('active');
}

function togglePrescriptionSwitch() {
    const chk = document.getElementById('issuePrescriptionCheck');
    if (chk) {
        chk.checked = !chk.checked;
        handlePrescriptionToggle(chk);
    }
}

// Safely bind event listener
document.addEventListener("DOMContentLoaded", function() {
    const switchContainer = document.querySelector('.switch-container');
    if (switchContainer) {
        switchContainer.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

function handlePrescriptionToggle(chk) {
    const formArea = document.getElementById('prescriptionFormArea');
    const toggleRow = document.getElementById('prescriptionToggleRow');
    const drugInputs = document.querySelectorAll('.searchable-select-input');
    const dosageInput = document.getElementById('prescriptionDosage');
    
    if (chk.checked) {
        if (formArea) formArea.style.display = 'block';
        if (toggleRow) toggleRow.classList.add('active');
        drugInputs.forEach(input => input.required = true);
        if (dosageInput) dosageInput.required = true;
    } else {
        if (formArea) formArea.style.display = 'none';
        if (toggleRow) toggleRow.classList.remove('active');
        drugInputs.forEach(input => input.required = false);
        if (dosageInput) dosageInput.required = false;
    }
}

function toggleFollowUpSwitch() {
    const chk = document.getElementById('scheduleFollowUpCheck');
    if (chk) {
        chk.checked = !chk.checked;
        handleFollowUpToggle(chk);
    }
}

function handleFollowUpToggle(chk) {
    const fieldsArea = document.getElementById('followUpFieldsArea');
    const toggleRow = document.getElementById('followUpToggleRow');
    const dateInput = document.getElementById('followUpDateInput');
    const timeInput = document.getElementById('followUpTime');
    
    if (chk.checked) {
        if (fieldsArea) fieldsArea.style.display = 'grid';
        if (toggleRow) toggleRow.classList.add('active');
        if (dateInput) dateInput.required = true;
        if (timeInput) timeInput.required = true;
    } else {
        if (fieldsArea) fieldsArea.style.display = 'none';
        if (toggleRow) toggleRow.classList.remove('active');
        if (dateInput) {
            dateInput.required = false;
            dateInput.value = '';
        }
        if (timeInput) {
            timeInput.required = false;
            timeInput.value = '';
        }
    }
}
</script>

<style>
@keyframes modalFadeIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<!-- Consultation Success Modal -->
<div id="consultationSuccessModal" class="modal-overlay">
    <div class="modal-card" style="background: white; border-radius: 16px; width: 400px; max-width: 90%; text-align: center; padding: 32px 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: modalFadeIn 0.3s ease-out; border: none;">
        <div style="width: 64px; height: 64px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Consultation Completed!</h2>
        <p style="font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.5;">The patient's medical record has been securely saved and the appointment status updated.</p>
        
        <button onclick="closeSuccessModal()" style="width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s;">
            OK
        </button>
    </div>
</div>

<script>
function closeSuccessModal() {
    document.getElementById('consultationSuccessModal').classList.remove('active');
}
</script>

<?php if (isset($_GET['success'])): ?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById('consultationSuccessModal').classList.add('active');
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>

</body>
</html>
