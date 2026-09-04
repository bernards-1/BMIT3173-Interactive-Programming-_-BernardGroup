<?php
require_once '../db.php';
require_once '../Models/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get patient_id
$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient record not found.']);
    exit;
}

$patient_id = $patient['patient_id'];

// Get input data (支持 GET 和 POST)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_REQUEST;
}

$action = $input['action'] ?? '';
$appointment_id = $input['appointment_id'] ?? '';

if (empty($action) || empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Check ownership of appointment
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?");
$stmt->execute([$appointment_id, $patient_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or permission denied.']);
    exit;
}

$doctor_id = $appointment['doctor_id'];

// Action 1: Get booked time slots and leave status for a specific date
if ($action === 'get_booked_slots') {
    $target_date = $input['date'] ?? '';
    if (empty($target_date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required.']);
        exit;
    }
    
    // Check doctor leave
    $leave_stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_leaves WHERE doctor_id = ? AND start_date <= ? AND end_date >= ? AND status = 'Approved'");
    $leave_stmt->execute([$doctor_id, $target_date, $target_date]);
    $is_on_leave = $leave_stmt->fetchColumn() > 0;
    
    // Check booked slots on that date (excluding current appointment)
    $booked_stmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_id != ? AND status = 'Scheduled'");
    $booked_stmt->execute([$doctor_id, $target_date, $appointment_id]);
    $booked_slots = $booked_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'on_leave' => $is_on_leave,
        'booked_slots' => $booked_slots
    ]);
    exit;
}

// Action 2: Cancel appointment
if ($action === 'cancel') {
    require_once '../Models/Appointment.php';
    $apptObj = Appointment::load($appointment_id);
    if (!$apptObj) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }
    try {
        $apptObj->cancel();
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Action 3: Reschedule appointment with strict availability validation
if ($action === 'reschedule') {
    $new_date = $input['appointment_date'] ?? '';
    $new_time = $input['appointment_time'] ?? '';
    
    if (empty($new_date) || empty($new_time)) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid date and time.']);
        exit;
    }
    
    $today = date('Y-m-d');
    if ($new_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
        exit;
    }
    if ($new_date === $today && $new_time <= date('H:i:s')) {
        echo json_encode(['success' => false, 'message' => 'Appointment time cannot be in the past.']);
        exit;
    }
    
    // 1. Validate doctor leave
    $leave_stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_leaves WHERE doctor_id = ? AND start_date <= ? AND end_date >= ? AND status = 'Approved'");
    $leave_stmt->execute([$doctor_id, $new_date, $new_date]);
    if ($leave_stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'The doctor is on leave on this date. Please select another date.']);
        exit;
    }
    
    // 2. Validate time slot conflict
    $conflict_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND appointment_id != ? AND status = 'Scheduled'");
    $conflict_stmt->execute([$doctor_id, $new_date, $new_time, $appointment_id]);
    if ($conflict_stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked for this doctor. Please choose another time slot.']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Scheduled' WHERE appointment_id = ? AND patient_id = ?");
    $stmt->execute([$new_date, $new_time, $appointment_id, $patient_id]);
    
    echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
exit;
