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

// Helper function for HTML escaping
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Get the correct doctor_id and name
$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'];
$stmt = $pdo->prepare('SELECT doctor_id, name FROM doctors WHERE user_id = ?');
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';

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

// Handle form submission to Complete Consultation (same as mainpage.php!)
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
            // Get original appointment time to reuse for follow-up
            $orig_time_stmt = $pdo->prepare('SELECT appointment_time FROM appointments WHERE appointment_id = ?');
            $orig_time_stmt->execute([$appt_id]);
            $orig_time = $orig_time_stmt->fetchColumn();
            if (!$orig_time) {
                $orig_time = '10:00:00';
            }
            
            // Generate appointment ID (e.g. A006)
            $count_appt_stmt = $pdo->query('SELECT COUNT(*) FROM appointments');
            $appt_count = $count_appt_stmt->fetchColumn() + 1;
            $new_appt_id = 'A' . str_pad($appt_count, 3, '0', STR_PAD_LEFT);
            
            $ins_appt = $pdo->prepare('
                INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, status) 
                VALUES (?, ?, ?, ?, ?, \'Follow-up\', \'Scheduled\')
            ');
            $ins_appt->execute([$new_appt_id, $patient_id, $doctor_id, $follow_up_date, $orig_time]);
        }
        
        // Success redirect to schedule page
        header('Location: schedule.php?success=1');
        exit;
    }
}

// Fetch all appointments for the doctor to group them for JS calendar dots
$appt_stmt = $pdo->prepare('
    SELECT 
        a.appointment_id, 
        a.appointment_date, 
        a.appointment_time, 
        a.reason, 
        a.status, 
        p.patient_id, 
        p.full_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE a.doctor_id = ? 
    ORDER BY a.appointment_time ASC
');
$appt_stmt->execute([$doctor_id]);
$appointments = $appt_stmt->fetchAll();

$js_appointments = [];
foreach ($appointments as $appt) {
    $date_time = strtotime($appt['appointment_date']);
    $year = date('Y', $date_time);
    $month = (int)date('n', $date_time) - 1; // 0-indexed month
    $day = (int)date('j', $date_time);
    
    $key = "{$year}-{$month}-{$day}";
    
    if (!isset($js_appointments[$key])) {
        $js_appointments[$key] = [
            'count' => 0,
            'list' => []
        ];
    }
    
    $time_formatted = date('H:i', strtotime($appt['appointment_time']));
    
    $js_appointments[$key]['list'][] = [
        'appointment_id' => $appt['appointment_id'],
        'patient_id' => $appt['patient_id'],
        'time' => $time_formatted,
        'patient' => $appt['full_name'],
        'type' => $appt['reason'],
        'status' => $appt['status']
    ];
    $js_appointments[$key]['count']++;
}

// Handle form submission to Request Leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_leave') {
    $start_date = $_POST['leave_start_date'];
    $end_date = $_POST['leave_end_date'];
    $leave_type = isset($_POST['leave_type']) ? $_POST['leave_type'] : 'Annual Leave';
    $custom_reason = isset($_POST['leave_reason']) ? trim($_POST['leave_reason']) : '';
    $reason = "[{$leave_type}] {$custom_reason}";
    
    // Generate leave ID (e.g. DL002)
    $count_l_stmt = $pdo->query('SELECT COUNT(*) FROM doctor_leaves');
    $l_count = $count_l_stmt->fetchColumn() + 1;
    $leave_id = 'DL' . str_pad($l_count, 3, '0', STR_PAD_LEFT);
    
    $ins_leave = $pdo->prepare('
        INSERT INTO doctor_leaves (leave_id, doctor_id, start_date, end_date, reason, status) 
        VALUES (?, ?, ?, ?, ?, \'Pending\')
    ');
    $ins_leave->execute([$leave_id, $doctor_id, $start_date, $end_date, $reason]);
    
    // Success redirect
    header('Location: schedule.php?leave_success=1');
    exit;
}

// Fetch leave history list for the modal
$leave_stmt = $pdo->prepare('
    SELECT start_date, end_date, reason, status, reject_reason, created_at 
    FROM doctor_leaves 
    WHERE doctor_id = ? 
    ORDER BY created_at DESC
');
$leave_stmt->execute([$doctor_id]);
$leave_history = $leave_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Doctor Schedule</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Doctor/style.css?v=<?= time() ?>">
    <style>
    /* Leave History Pagination Styles */
    .page-btn {
        padding: 5px 10px;
        border: 1px solid var(--border-color);
        background-color: var(--white);
        color: var(--slate-700);
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        transition: all 0.2s ease;
    }
    .page-btn:hover {
        background-color: var(--slate-50);
        border-color: var(--slate-300);
    }
    .page-btn.active {
        background-color: #2563eb;
        border-color: #2563eb;
        color: var(--white);
    }
    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Calendar dots styles */
    .calendar-dots {
        display: flex;
        justify-content: center;
        gap: 3px;
        position: absolute;
        bottom: 8px;
        width: 100%;
        left: 0;
    }
    .calendar-dot {
        width: 5px;
        height: 5px;
        background-color: #2563eb;
        border-radius: 50%;
        display: inline-block;
        transition: background-color 0.2s ease;
    }
    .active-cell .calendar-dot {
        background-color: #ffffff !important;
    }
    .today-cell:not(.active-cell) .calendar-dot {
        background-color: #1d4ed8;
    }
    
    /* Layout Adjustments for Calendar Cell */
    .calendar-cell {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        padding-top: 14px;
        height: 76px;
    }
    
    /* Timeline time badges */
    .timeline-time-badge {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        min-width: 65px;
        text-align: center;
    }
    
    /* Consultation Modal CSS */
    .consultation-modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(15, 23, 42, 0.6);
        display: flex; justify-content: center; align-items: center;
        z-index: 1001; opacity: 0; pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .consultation-modal-overlay.active {
        opacity: 1; pointer-events: auto;
    }
    .consultation-modal-container {
        background-color: var(--white); border-radius: 16px;
        width: 90%; max-width: 550px; max-height: 90%; overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        transform: translateY(20px); transition: transform 0.3s ease;
    }
    .consultation-modal-overlay.active .consultation-modal-container {
        transform: translateY(0);
    }
    .consultation-modal-header {
        padding: 20px 24px; border-bottom: 1px solid var(--slate-100);
        display: flex; justify-content: space-between; align-items: center;
    }
    .consultation-modal-title { font-size: 18px; font-weight: 700; color: var(--slate-900); }
    .consultation-modal-subtitle { font-size: 12px; color: var(--slate-400); margin-top: 2px; }
    .consultation-modal-close {
        font-size: 20px; color: var(--slate-400); cursor: pointer;
        background: none; border: none; transition: color 0.2s ease;
    }
    .consultation-modal-close:hover { color: var(--slate-600); }
    .consultation-modal-body { padding: 24px; }
    
    .modal-patient-card {
        background-color: #f8fafc; border-radius: 12px; padding: 16px;
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
    }
    .modal-patient-info { display: flex; align-items: center; gap: 12px; }
    .modal-patient-name { font-size: 15px; font-weight: 700; color: var(--slate-900); }
    .modal-patient-sub { font-size: 12px; color: var(--slate-500); margin-top: 2px; }
    
    .prescription-toggle-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 16px; background-color: #f8fafc; border: 1px solid var(--slate-100);
        border-radius: 12px; margin-bottom: 20px; cursor: pointer; transition: all 0.2s ease;
    }
    .prescription-toggle-row.active { background-color: #faf5ff; border-color: #e9d5ff; }
    .prescription-toggle-label {
        display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600;
        color: var(--slate-700); transition: color 0.2s ease;
    }
    .prescription-toggle-row.active .prescription-toggle-label { color: #7c3aed; }
    
    .switch-container { position: relative; width: 44px; height: 24px; }
    .switch-input { opacity: 0; width: 0; height: 0; }
    .switch-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1; transition: .3s; border-radius: 24px;
    }
    .switch-slider:before {
        position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
        background-color: white; transition: .3s; border-radius: 50%;
    }
    .switch-input:checked + .switch-slider { background-color: #a855f7; }
    .switch-input:checked + .switch-slider:before { transform: translateX(20px); }
    
    .prescription-form-area {
        display: none; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 16px; background-color: #fafafa; margin-bottom: 20px;
    }
    .prescription-section-title {
        font-size: 12px; font-weight: 700; color: var(--slate-400);
        letter-spacing: 0.05em; margin-bottom: 12px; text-transform: uppercase;
    }
    .modal-actions-row { display: flex; gap: 12px; margin-top: 24px; }
    .btn-modal-cancel {
        flex: 1; padding: 12px; background-color: var(--white); border: 1px solid var(--slate-200);
        color: var(--slate-600); border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer;
    }
    .btn-modal-cancel:hover { background-color: var(--slate-50); }
    .btn-modal-submit {
        flex: 1.2; padding: 12px; background-color: #10b981; border: none; color: var(--white);
        border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer;
        display: flex; justify-content: center; align-items: center; gap: 6px;
    }
    .btn-modal-submit:hover { background-color: #059669; }
    
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

<!-- Schedule Page Main Container -->
<div class="dashboard-container">
    
    <!-- Page Header Title -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">My Schedule</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">Manage your availability and appointments</p>
    </div>
    
    <!-- Main Content Layout Grid -->
    <div class="dashboard-grid" style="grid-template-columns: 2.2fr 1.1fr;">
        
        <!-- Left Panel: Calendar Grid & Daily Appointments List -->
        <div>
            <!-- Calendar Card -->
            <div class="calendar-card">
                <div class="calendar-header">
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900);" id="calendarMonthHeader">June 2026</h3>
                    <div style="display: flex; gap: 14px; font-size: 14px; color: var(--slate-600); cursor: pointer;">
                        <i class="fa-solid fa-chevron-left" onclick="prevMonth()"></i>
                        <i class="fa-solid fa-chevron-right" onclick="nextMonth()"></i>
                    </div>
                </div>
                
                <!-- Weekday Headers -->
                <div class="calendar-grid-header">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                
                <!-- Calendar Body Cells (Generated Dynamically) -->
                <div class="calendar-grid-body" id="calendarGridBody"></div>
            </div>
            
            <!-- Bottom Daily Timeline List -->
            <div style="background-color: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); padding: 24px;">
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900);" id="timelineDateHeader">Monday, June 24</h3>
                    <p style="font-size: 12px; color: var(--slate-400); margin-top: 2px;" id="timelineAppointmentsCount">3 appointments</p>
                </div>
                
                <div id="timelineEventsContainer">
                    <!-- Appt 1 -->
                    <div class="timeline-appointment-row">
                        <div class="timeline-left">
                            <div class="timeline-time-badge">09:00</div>
                            <div>
                                <div class="timeline-patient-name">John Smith</div>
                                <div class="timeline-appointment-type">Consultation</div>
                            </div>
                        </div>
                        <button class="btn-row-action" onclick="alert('Starting consultation for John Smith...')">Start</button>
                    </div>
                    
                    <!-- Appt 2 -->
                    <div class="timeline-appointment-row">
                        <div class="timeline-left">
                            <div class="timeline-time-badge">11:00</div>
                            <div>
                                <div class="timeline-patient-name">Emma Wilson</div>
                                <div class="timeline-appointment-type">Follow-up</div>
                            </div>
                        </div>
                        <button class="btn-row-action" onclick="alert('Starting consultation for Emma Wilson...')">Start</button>
                    </div>
                    
                    <!-- Appt 3 -->
                    <div class="timeline-appointment-row">
                        <div class="timeline-left">
                            <div class="timeline-time-badge">14:00</div>
                            <div>
                                <div class="timeline-patient-name">Robert Davis</div>
                                <div class="timeline-appointment-type">Check-up</div>
                            </div>
                        </div>
                        <button class="btn-row-action" onclick="alert('Starting consultation for Robert Davis...')">Start</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel: Availability & Leave Management -->
        <div class="card-list">
            
            <!-- Leave Management Card -->
            <div class="leave-card" style="margin-bottom: 24px;">
                <h3 class="leave-card-title">Leave Management</h3>
                <p class="leave-card-desc">You have <span style="font-weight:700;">12 days</span> of annual leave remaining for 2024.</p>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-primary" style="background-color: #2563eb; flex: 1.2; padding: 10px; border-radius: 8px; border: none; font-size: 13px; font-weight: 600; color: var(--white); cursor: pointer;" onclick="openLeaveModal()">
                        Request Leave
                    </button>
                    <button class="btn-secondary" style="background-color: var(--white); border: 1px solid var(--border-color); color: var(--slate-700); flex: 1; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;" onclick="openLeaveHistoryModal()">
                        History
                    </button>
                </div>
            </div>

            
        </div>
        
    </div>
</div>

<script>
// Current Date State (initialize to current system date)
const today = new Date();
let displayedYear = today.getFullYear();
let displayedMonth = today.getMonth(); // 0-11

// Month names list
const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

// Weekday names helper
const weekdayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

// Database-driven appointments count and list grouped by YYYY-M-D key
const mockAppointments = <?= json_encode($js_appointments) ?>;

// Render the calendar on page load
document.addEventListener("DOMContentLoaded", () => {
    renderCalendar(displayedYear, displayedMonth);
    // Select today's date by default
    selectDate(today.getDate(), today.getMonth(), today.getFullYear());
});

function renderCalendar(year, month) {
    const monthHeader = document.getElementById("calendarMonthHeader");
    const gridBody = document.getElementById("calendarGridBody");
    
    // Set Header Title: Month Year
    monthHeader.innerText = monthNames[month] + " " + year;
    
    // Get first day of the month (0 = Sunday, ..., 6 = Saturday)
    const firstDayIndex = new Date(year, month, 1).getDay();
    
    // Get total days in the month
    const totalDays = new Date(year, month + 1, 0).getDate();
    
    // Clear previous cells
    gridBody.innerHTML = "";
    
    // 1. Render Empty Padding Cells
    for (let i = 0; i < firstDayIndex; i++) {
        const cell = document.createElement("div");
        cell.className = "calendar-cell empty-cell";
        gridBody.appendChild(cell);
    }
    
    // 2. Render Day Cells
    for (let day = 1; day <= totalDays; day++) {
        const cell = document.createElement("div");
        cell.className = "calendar-cell";
        
        // Use a span to wrap the day number for cleaner innerText reading
        const dayNumberSpan = document.createElement("span");
        dayNumberSpan.className = "day-number-text";
        dayNumberSpan.innerText = day;
        cell.appendChild(dayNumberSpan);
        
        // Check if it is "Today"
        const isToday = (year === today.getFullYear() && month === today.getMonth() && day === today.getDate());
        if (isToday) {
            cell.classList.add("today-cell");
        }
        
        // Add click event
        cell.addEventListener("click", (e) => {
            // Remove active style from all cells
            const allCells = document.querySelectorAll(".calendar-cell");
            allCells.forEach(c => c.classList.remove("active-cell"));
            
            // Add active style to this cell
            cell.classList.add("active-cell");
            
            // Call select date handler
            selectDate(day, month, year);
        });
        
        // Add mock appointments dots if any
        const dateKey = `${year}-${month}-${day}`;
        if (mockAppointments[dateKey]) {
            const dotsContainer = document.createElement("div");
            dotsContainer.className = "calendar-dots";
            const dotsCount = mockAppointments[dateKey].count;
            
            // Limit to max 3 dots to prevent UI overflow
            const visibleDots = Math.min(dotsCount, 3);
            for (let d = 0; d < visibleDots; d++) {
                const dot = document.createElement("span");
                dot.className = "calendar-dot";
                dotsContainer.appendChild(dot);
            }
            cell.appendChild(dotsContainer);
        }
        
        gridBody.appendChild(cell);
    }
}

function prevMonth() {
    displayedMonth--;
    if (displayedMonth < 0) {
        displayedMonth = 11;
        displayedYear--;
    }
    renderCalendar(displayedYear, displayedMonth);
}

function nextMonth() {
    displayedMonth++;
    if (displayedMonth > 11) {
        displayedMonth = 0;
        displayedYear++;
    }
    renderCalendar(displayedYear, displayedMonth);
}

function selectDate(day, month, year) {
    const timelineHeader = document.getElementById("timelineDateHeader");
    const timelineCount = document.getElementById("timelineAppointmentsCount");
    const container = document.getElementById("timelineEventsContainer");
    
    // Format date string for header
    const dateObj = new Date(year, month, day);
    const dayOfWeek = weekdayNames[dateObj.getDay()];
    const monthName = monthNames[month];
    
    timelineHeader.innerText = `${dayOfWeek}, ${monthName} ${day}`;
    
    const dateKey = `${year}-${month}-${day}`;
    
    if (mockAppointments[dateKey]) {
        const appts = mockAppointments[dateKey].list;
        timelineCount.innerText = `${appts.length} appointments`;
        
        let html = "";
        appts.forEach(appt => {
            const isCompleted = appt.status === 'Completed';
            
            let actionHtml = '';
            if (isCompleted) {
                actionHtml = `
                    <span class="badge success" style="background-color: #d1fae5; color: #065f46; border-radius: 6px; text-transform: none; font-weight: 600; font-size: 11px; padding: 5px 10px; display: inline-block;">Completed</span>
                `;
            } else {
                const apptHour = parseInt(appt.time.split(':')[0]);
                const apptMin = parseInt(appt.time.split(':')[1]);
                const apptDateObj = new Date(year, month, day, apptHour, apptMin, 0);
                const nowObj = new Date();
                
                if (apptDateObj < nowObj) {
                    actionHtml = `
                        <span style="font-size: 12px; color: #f59e0b; font-weight: 600; margin-right: 4px;">Overdue</span>
                    `;
                } else {
                    actionHtml = `
                        <span style="font-size: 12px; color: var(--slate-400); font-weight: 500; margin-right: 4px;">Upcoming</span>
                    `;
                }
            }
            
            // View button (always visible)
            actionHtml += `
                <a href="Patient_medicalRecords.php?id=${appt.patient_id}" class="btn-row-action secondary" style="background-color: var(--slate-100); color: var(--slate-700); text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--slate-200); cursor: pointer;">
                    <i class="fa-regular fa-eye"></i> View
                </a>
            `;
            
            if (!isCompleted) {
                // Generate initials & color for modal
                const names = appt.patient.split(' ');
                let initials = '';
                names.forEach(n => {
                    if (n.length > 0) initials += n.charAt(0).toUpperCase();
                });
                initials = initials.substring(0, 2);
                const colors = ['#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
                let charCodeSum = 0;
                for (let i = 0; i < appt.patient.length; i++) charCodeSum += appt.patient.charCodeAt(i);
                const bgColor = colors[charCodeSum % colors.length];

                // Convert time to 12h formatted time for display
                const hour24 = parseInt(appt.time.split(':')[0]);
                const min = appt.time.split(':')[1];
                const ampm = hour24 >= 12 ? 'PM' : 'AM';
                const displayHour = hour24 % 12 || 12;
                const time12 = `${displayHour}:${min} ${ampm}`;

                actionHtml += `
                    <button class="btn-row-action" onclick="openConsultationModal('${appt.appointment_id}', '${appt.patient.replace(/'/g, "\\'")}', '${time12}', '${appt.type.replace(/'/g, "\\'")}', '${bgColor}', '${initials}')" style="background-color: var(--primary-blue); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; outline: none;">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                `;
            }
            
            // Check time color badge class
            let timeColorClass = '';
            if (appt.time < '10:00') timeColorClass = 'style="background-color: #eff6ff; color: #2563eb;"'; // blueish
            else if (appt.time < '13:00') timeColorClass = 'style="background-color: #ecfdf5; color: #059669;"'; // greenish
            else timeColorClass = 'style="background-color: #f3e8ff; color: #7c3aed;"'; // purpleish
            
            html += `
                <div class="timeline-appointment-row">
                    <div class="timeline-left">
                        <div class="timeline-time-badge" ${timeColorClass}>${appt.time}</div>
                        <div>
                            <div class="timeline-patient-name">${appt.patient}</div>
                            <div class="timeline-appointment-type">${appt.type}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${actionHtml}
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    } else {
        timelineCount.innerText = "0 appointments";
        container.innerHTML = `
            <div style="text-align:center; padding: 40px 20px; color: var(--slate-400); font-size:14px;">
                <i class="fa-regular fa-calendar-xmark" style="font-size:28px; margin-bottom:12px; display:block; color: var(--slate-300);"></i>
                No appointments scheduled for this day.
            </div>
        `;
    }
    
    // Find cell containing the clicked day
    const allCells = document.querySelectorAll(".calendar-cell");
    allCells.forEach(cell => {
        const textSpan = cell.querySelector('.day-number-text');
        if (textSpan && parseInt(textSpan.textContent) === day) {
            allCells.forEach(c => c.classList.remove("active-cell"));
            cell.classList.add("active-cell");
        }
    });
}

// Start Consultation Modal JS Functions
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
            document.body.style.overflow = 'hidden';
        }
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

// Request Leave Modal Functions
function openLeaveModal() {
    try {
        document.getElementById("leaveModalOverlay").style.display = "flex";
        document.body.style.overflow = "hidden"; // Prevent body scroll
        validateForm();
    } catch(err) {
        alert("openLeaveModal Error: " + err.message);
    }
}

function closeLeaveModal() {
    try {
        document.getElementById("leaveModalOverlay").style.display = "none";
        document.body.style.overflow = ""; // Re-enable body scroll
        document.getElementById("leaveRequestForm").reset();
    } catch(err) {
        alert("closeLeaveModal Error: " + err.message);
    }
}

function validateForm() {
    try {
        const type = document.getElementById("leaveType").value;
        const start = document.getElementById("leaveStartDate").value;
        const end = document.getElementById("leaveEndDate").value;
        const reason = document.getElementById("leaveReason").value.trim();
        
        const submitBtn = document.getElementById("submitLeaveBtn");
        
        if (type && start && end && reason) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    } catch(err) {
        alert("validateForm Error: " + err.message);
    }
}

// Pagination Variables for Leave History
let leaveHistoryCurrentPage = 1;
const leaveHistoryItemsPerPage = 3;

function openLeaveHistoryModal() {
    try {
        document.getElementById("leaveHistoryModalOverlay").style.display = "flex";
        document.body.style.overflow = "hidden"; // Prevent body scroll
        
        // Initialize Pagination
        initLeaveHistoryPagination();
    } catch(err) {
        console.error("openLeaveHistoryModal Error:", err);
    }
}

function initLeaveHistoryPagination() {
    const tbody = document.getElementById("leaveHistoryTableBody");
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const totalItems = rows.length;
    const totalPages = Math.ceil(totalItems / leaveHistoryItemsPerPage);
    
    // Safety check for current page boundaries
    if (leaveHistoryCurrentPage > totalPages) leaveHistoryCurrentPage = totalPages;
    if (leaveHistoryCurrentPage < 1) leaveHistoryCurrentPage = 1;
    
    // Hide/show rows based on page
    rows.forEach((row, index) => {
        const start = (leaveHistoryCurrentPage - 1) * leaveHistoryItemsPerPage;
        const end = start + leaveHistoryItemsPerPage;
        if (index >= start && index < end) {
            row.style.display = "table-row";
        } else {
            row.style.display = "none";
        }
    });
    
    // Render pagination buttons in footer
    const paginationContainer = document.getElementById("leaveHistoryPagination");
    if (!paginationContainer) return;
    
    paginationContainer.innerHTML = "";
    
    if (totalPages > 1) {
        // Prev button
        const prevBtn = document.createElement("button");
        prevBtn.className = "page-btn";
        prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size: 10px;"></i>';
        prevBtn.disabled = leaveHistoryCurrentPage === 1;
        prevBtn.onclick = () => {
            leaveHistoryCurrentPage--;
            initLeaveHistoryPagination();
        };
        paginationContainer.appendChild(prevBtn);
        
        // Page number buttons
        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement("button");
            pageBtn.className = "page-btn" + (i === leaveHistoryCurrentPage ? " active" : "");
            pageBtn.innerText = i;
            pageBtn.onclick = () => {
                leaveHistoryCurrentPage = i;
                initLeaveHistoryPagination();
            };
            paginationContainer.appendChild(pageBtn);
        }
        
        // Next button
        const nextBtn = document.createElement("button");
        nextBtn.className = "page-btn";
        nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>';
        nextBtn.disabled = leaveHistoryCurrentPage === totalPages;
        nextBtn.onclick = () => {
            leaveHistoryCurrentPage++;
            initLeaveHistoryPagination();
        };
        paginationContainer.appendChild(nextBtn);
    }
}

function closeLeaveHistoryModal() {
    try {
        document.getElementById("leaveHistoryModalOverlay").style.display = "none";
        document.body.style.overflow = ""; // Re-enable body scroll
    } catch(err) {
        console.error("closeLeaveHistoryModal Error:", err);
    }
}
</script>

<!-- Request Leave Modal Overlay (Requested Popup Modal) -->
<div id="leaveModalOverlay" class="leave-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="leave-modal-box">
        <div class="leave-modal-header">
            <div>
                <h3 class="leave-modal-title">Request Leave</h3>
                <p class="leave-modal-subtitle">Submit a leave request to admin for approval</p>
            </div>
            <i class="fa-solid fa-xmark close-modal-icon" onclick="closeLeaveModal()"></i>
        </div>
        
        <!-- Remaining Days Alert Banner -->
        <div class="leave-modal-banner-days">
            <span>Annual leave remaining</span>
            <strong style="color: #2563eb; font-size: 16px;">12 days</strong>
        </div>
        
        <!-- Form -->
        <form id="leaveRequestForm" action="schedule.php" method="POST">
            <input type="hidden" name="action" value="request_leave">
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Leave Type</label>
                <select class="form-select" name="leave_type" id="leaveType" required onchange="validateForm()">
                    <option value="" disabled selected>Select leave type...</option>
                    <option value="Annual Leave">Annual Leave</option>
                    <option value="Medical Leave">Medical Leave</option>
                    <option value="Emergency Leave">Emergency Leave</option>
                    <option value="Unpaid Leave">Unpaid Leave</option>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">Start Date <span style="color:red;">*</span></label>
                    <input type="date" class="form-date-input" name="leave_start_date" id="leaveStartDate" required onchange="validateForm()">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date <span style="color:red;">*</span></label>
                    <input type="date" class="form-date-input" name="leave_end_date" id="leaveEndDate" required onchange="validateForm()">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Reason <span style="color:red;">*</span></label>
                <textarea class="form-textarea" name="leave_reason" id="leaveReason" rows="3" placeholder="Briefly describe the reason for your leave request..." required oninput="validateForm()"></textarea>
            </div>
            
            <!-- Footer Buttons -->
            <div class="leave-modal-footer">
                <button type="button" class="leave-modal-btn-cancel" onclick="closeLeaveModal()">Cancel</button>
                <button type="submit" class="leave-modal-btn-submit" id="submitLeaveBtn" disabled>Submit Request</button>
            </div>
        </form>
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
        
        <form action="schedule.php" method="POST" id="consultationForm">
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

<!-- Leave History Modal Overlay -->
<div id="leaveHistoryModalOverlay" class="leave-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="leave-modal-box" style="max-width: 600px; width: 90%; background-color: var(--white); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); overflow: hidden; transform: translateY(0); transition: var(--transition);">
        <div class="leave-modal-header" style="background-color: var(--slate-900); color: var(--white); padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 class="leave-modal-title" style="margin: 0; font-size: 16px; font-weight: 700; color: white;">Leave Request History</h3>
                <p class="leave-modal-subtitle" style="margin: 2px 0 0 0; font-size: 12px; color: var(--slate-400);">Track your submitted leave applications</p>
            </div>
            <i class="fa-solid fa-xmark close-modal-icon" style="font-size: 20px; cursor: pointer; color: var(--slate-400);" onclick="closeLeaveHistoryModal()"></i>
        </div>
        
        <div class="modal-body" style="padding: 24px; max-height: 400px; overflow-y: auto;">
            <?php if (empty($leave_history)): ?>
                <div style="text-align: center; padding: 30px; color: var(--slate-400); font-size: 14px;">
                    <i class="fa-solid fa-plane-departure" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--slate-300);"></i>
                    No leave requests found.
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--slate-100); color: var(--slate-500); font-weight: 600;">
                            <th style="padding: 10px 8px;">Duration</th>
                            <th style="padding: 10px 8px;">Reason</th>
                            <th style="padding: 10px 8px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="leaveHistoryTableBody">
                        <?php foreach ($leave_history as $leave): 
                            $status_color = '#dbeafe'; 
                            $status_text_color = '#1e40af';
                            if ($leave['status'] === 'Approved') {
                                $status_color = '#d1fae5';
                                $status_text_color = '#065f46';
                            } elseif ($leave['status'] === 'Rejected') {
                                $status_color = '#fee2e2';
                                $status_text_color = '#991b1b';
                            }
                            
                            $start_fmt = date('M j, Y', strtotime($leave['start_date']));
                            $end_fmt = date('M j, Y', strtotime($leave['end_date']));
                        ?>
                            <tr style="border-bottom: 1px solid var(--slate-100);">
                                <td style="padding: 12px 8px; font-weight: 500; color: var(--slate-800);">
                                    <?= $start_fmt ?> - <br>
                                    <span style="font-size: 11px; color: var(--slate-400);"><?= $end_fmt ?></span>
                                </td>
                                <td style="padding: 12px 8px; color: var(--slate-600); max-width: 180px; word-break: break-word;">
                                    <?= e($leave['reason']) ?>
                                    <?php if (!empty($leave['reject_reason'])): ?>
                                        <div style="font-size: 11px; color: #b91c1c; margin-top: 4px; font-weight: 500;">
                                            <strong>Reject Reason:</strong> <?= e($leave['reject_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 8px;">
                                    <span class="badge" style="background-color: <?= $status_color ?>; color: <?= $status_text_color ?>; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 11px;">
                                        <?= e($leave['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div style="padding: 16px 24px; border-top: 1px solid var(--slate-100); display: flex; justify-content: space-between; align-items: center; background-color: var(--slate-50); border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
            <!-- Pagination Controls -->
            <div id="leaveHistoryPagination" style="display: flex; gap: 6px; align-items: center;"></div>
            
            <button type="button" class="leave-modal-btn-cancel" onclick="closeLeaveHistoryModal()" style="margin: 0; width: 100px;">Close</button>
        </div>
    </div>
</div>

<?php if (isset($_GET['leave_success'])): ?>
    <script>
        alert("Leave request submitted successfully! Status is Pending approval from admin.");
        window.history.replaceState({}, document.title, window.location.pathname);
    </script>
<?php endif; ?>

</body>
</html>
