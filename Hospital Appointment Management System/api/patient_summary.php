<?php
// api/patient_summary.php
// RESTful Web Service exposed by Patient Module to provide patient health & appointment summary
require_once '../db.php';
require_once '../Models/PatientRepository.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$timestamp = date('c');

try {
    $userId = $_GET['userId'] ?? null;
    $requestID = $_GET['requestID'] ?? null;
    $requestTimestamp = $_GET['timestamp'] ?? null;

    // Rule A: requestID is mandatory (request tracking)
    if (!$requestID) {
        http_response_code(400);
        echo json_encode([
            'status' => 'F',
            'message' => 'Missing mandatory parameter: requestID',
            'requestID' => 'N/A',
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // Rule B: timestamp is mandatory (request tracking)
    if (!$requestTimestamp) {
        http_response_code(400);
        echo json_encode([
            'status' => 'F',
            'message' => 'Missing mandatory parameter: timestamp',
            'requestID' => $requestID,
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // Rule C: userId is mandatory
    if (!$userId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'F',
            'message' => 'Missing mandatory parameter: userId',
            'requestID' => $requestID,
            'timestamp' => $timestamp
        ]);
        exit;
    }

    // Lookup patient
    $patientRepository = new PatientRepository($pdo);
    $patient = $patientRepository->getPatientByUserId($userId);

    // Error case: userId does not correspond to any patient record
    if (!$patient) {
        http_response_code(404);
        echo json_encode([
            'status' => 'F',
            'message' => 'Patient record not found',
            'requestID' => $requestID,
            'timestamp' => $timestamp
        ]);
        exit;
    }

    $patientId = $patient['patient_id'];

    // Appointments summary
    $appointmentSummary = $patientRepository->getAppointmentStatusCounts($patientId);

    // Recent medical records
    $recentRecords = $patientRepository->getRecentMedicalRecordsForApi($patientId, 3);

    // No-data case: patient exists but has no appointments and no medical records yet.
    // This is still a successful lookup (status S) — the query was valid, the result is simply empty.
    $hasAppointments = !empty(array_filter($appointmentSummary));
    $hasRecords = !empty($recentRecords);
    $message = ($hasAppointments || $hasRecords)
        ? 'Patient summary retrieved successfully'
        : 'Patient summary retrieved successfully — no appointment or medical record data found for this patient';

    echo json_encode([
        'status' => 'S',
        'message' => $message,
        'requestID' => $requestID,
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
        'requestID' => isset($requestID) ? $requestID : 'N/A',
        'timestamp' => $timestamp
    ]);
}
