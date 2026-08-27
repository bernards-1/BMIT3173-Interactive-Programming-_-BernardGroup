<?php
// api/patient_summary.php
// RESTful Web Service exposed by Patient Module to provide patient health & appointment summary
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$timestamp = date('c');

try {
    $userId = $_GET['userId'] ?? null;
    $requestTimestamp = $_GET['timestamp'] ?? null;

    if (!$userId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'F',
            'message' => 'Missing mandatory parameter: userId',
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // Lookup patient
    $stmt = $pdo->prepare("SELECT patient_id, full_name, date_of_birth, gender, blood_type, phone FROM patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(404);
        echo json_encode([
            'status' => 'F',
            'message' => 'Patient record not found',
            'timestamp' => $timestamp
        ]);
        exit;
    }

    $patientId = $patient['patient_id'];

    // Appointments summary
    $aptStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE patient_id = ? GROUP BY status");
    $aptStmt->execute([$patientId]);
    $appointmentSummary = $aptStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Recent medical records
    $recStmt = $pdo->prepare("
        SELECT mr.medical_record_id, mr.diagnosis, mr.created_at, d.name as doctor_name
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.patient_id = ?
        ORDER BY mr.created_at DESC LIMIT 3
    ");
    $recStmt->execute([$patientId]);
    $recentRecords = $recStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'S',
        'message' => 'Patient summary retrieved successfully',
        'patientDetails' => [
            'patientId' => $patient['patient_id'],
            'fullName' => $patient['full_name'],
            'gender' => $patient['gender'],
            'bloodType' => $patient['blood_type'],
            'appointmentCounts' => $appointmentSummary,
            'recentRecords' => $recentRecords
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
