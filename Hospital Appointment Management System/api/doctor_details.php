<?php
// api/doctor_details.php
// RESTful Web Service to provide doctor details & active approved leave schedules
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$timestamp = date('c');

try {
    $doctorId = $_GET['doctorId'] ?? null;

    if (!$doctorId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'F',
            'message' => 'Missing mandatory parameter: doctorId',
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // 1. Retrieve doctor profile details
    $stmt = $pdo->prepare("SELECT doctor_id, name, specialization, qualification, phone, email, consultation_fee, initials, color FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        http_response_code(404);
        echo json_encode([
            'status' => 'F',
            'message' => 'Doctor record not found',
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // 2. Retrieve approved future leave dates
    $leaveStmt = $pdo->prepare("SELECT start_date, end_date, reason FROM doctor_leaves WHERE doctor_id = ? AND status = 'Approved' AND end_date >= CURDATE() ORDER BY start_date ASC");
    $leaveStmt->execute([$doctorId]);
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'S',
        'message' => 'Doctor details retrieved successfully',
        'data' => [
            'doctorInfo' => $doctor,
            'approvedLeaves' => $leaves
        ],
        'timestamp' => $timestamp
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'E',
        'message' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => $timestamp
    ]);
}
