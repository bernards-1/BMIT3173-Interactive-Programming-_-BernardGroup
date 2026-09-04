<?php
// api/doctor_details.php
// RESTful Web Service to provide doctor details & active approved leave schedules
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$responseTimestamp = date('Y-m-d H:i:s');

try {
    // 1. Extract request parameters
    $doctorId = $_GET['doctorId'] ?? null;
    $requestID = $_GET['requestID'] ?? null;
    $requestTimestamp = $_GET['timestamp'] ?? null;

    // 2. Validation Rules
    // Rule A: requestID is mandatory
    if (empty($requestID)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'message' => 'Missing mandatory parameter: requestID',
            'requestID' => 'N/A',
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // Rule B: timestamp is mandatory
    if (empty($requestTimestamp)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'message' => 'Missing mandatory parameter: timestamp',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // Rule C: doctorId is mandatory and must be alphanumeric
    if (empty($doctorId)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'message' => 'Missing mandatory parameter: doctorId',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9]+$/', $doctorId)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'message' => 'Invalid format for doctorId. Must be alphanumeric.',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // Rule D: Validate timestamp format (YYYY-MM-DD HH:MM:SS)
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $requestTimestamp);
    if (!$d || $d->format('Y-m-d H:i:s') !== $requestTimestamp) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'message' => 'Invalid timestamp format. Use YYYY-MM-DD HH:MM:SS.',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // 3. Query Database for Doctor Details
    $stmt = $pdo->prepare("SELECT doctor_id, name, specialization, qualification, phone, email, consultation_fee, initials, color FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    // No-Result response
    if (!$doctor) {
        http_response_code(404);
        echo json_encode([
            'status' => 'F',
            'message' => 'Doctor record not found',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // 4. Query Database for Approved Leave Dates
    $leaveStmt = $pdo->prepare("SELECT start_date, end_date, reason FROM doctor_leaves WHERE doctor_id = ? AND status = 'Approved' AND end_date >= CURDATE() ORDER BY start_date ASC");
    $leaveStmt->execute([$doctorId]);
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

    // Success response (Data type is Object containing doctorInfo and approvedLeaves)
    echo json_encode([
        'status' => 'S',
        'message' => 'Doctor details retrieved successfully',
        'requestID' => e($requestID),
        'timestamp' => $responseTimestamp,
        'data' => (object)[
            'doctorInfo' => $doctor,
            'approvedLeaves' => $leaves
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'E',
        'message' => 'Internal server error: ' . $e->getMessage(),
        'requestID' => isset($requestID) ? e($requestID) : 'N/A',
        'timestamp' => $responseTimestamp,
        'data' => null
    ]);
}
?>
