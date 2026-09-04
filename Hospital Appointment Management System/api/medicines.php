<?php
// api/medicines.php
// RESTful Web Service to provide list of active medicines
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$responseTimestamp = date('Y-m-d H:i:s');

try {
    // 1. Extract request parameters
    $requestID = $_GET['requestID'] ?? null;
    $requestTimestamp = $_GET['timestamp'] ?? null;

    // 2. Validation Rules
    // Rule A: requestID is mandatory
    if (empty($requestID)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'code' => 400,
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
            'code' => 400,
            'message' => 'Missing mandatory parameter: timestamp',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // Rule C: Validate timestamp format (YYYY-MM-DD HH:MM:SS)
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $requestTimestamp);
    if (!$d || $d->format('Y-m-d H:i:s') !== $requestTimestamp) {
        http_response_code(400);
        echo json_encode([
            'status' => 'E',
            'code' => 400,
            'message' => 'Invalid timestamp format. Use YYYY-MM-DD HH:MM:SS.',
            'requestID' => e($requestID),
            'timestamp' => $responseTimestamp,
            'data' => null
        ]);
        exit;
    }

    // 3. Query Database for Medicines
    $stmt = $pdo->query('SELECT medicine_id, brand_name, generic_name, dosage, category, unit_price FROM medicines ORDER BY brand_name');
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Success response
    echo json_encode([
        'status' => 'S',
        'code' => 200,
        'count' => count($medicines),
        'requestID' => e($requestID),
        'timestamp' => $responseTimestamp,
        'data' => $medicines
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'E',
        'code' => 500,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'requestID' => isset($requestID) ? e($requestID) : 'N/A',
        'timestamp' => $responseTimestamp,
        'data' => null
    ]);
}
?>
