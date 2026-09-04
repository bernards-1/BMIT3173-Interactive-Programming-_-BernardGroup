<?php
// api/admin_doctor_status.php
/**
 * =========================================================================
 * WEB SERVICE (PROVIDER): Admin Module Doctor Duty & Status Service
 * =========================================================================
 * Provider: Admin Module
 * Consumers: Patient Module (during booking), Doctor Module (schedule sync)
 * Base URL: http://localhost:80/Hospital%20Appointment%20Management%20System/api/admin_doctor_status.php
 * 
 * Contract Specification:
 * - Method: POST
 * - Content-Type: application/json
 * - Mandatory Fields:
 *    1. requestID   (string, alphanumeric + hyphen, e.g. "REQ-9a8b7c")
 *    2. timestamp   (string, format: YYYY-MM-DD HH:MM:SS)
 *    3. doctorId    (string, alphanumeric, e.g. "D001")
 *    4. checkDate   (string, format: YYYY-MM-DD)
 * =========================================================================
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Facades/AdminFacade.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Request-ID');

$responseTimestamp = date('Y-m-d H:i:s');

// 1. Method verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'    => 'E',
        'code'      => 405,
        'message'   => 'Method Not Allowed. Only POST is accepted.',
        'requestID' => 'N/A',
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
    exit;
}

// 2. Parse JSON Body
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'status'    => 'E',
        'code'      => 400,
        'message'   => 'Malformed JSON payload.',
        'requestID' => 'N/A',
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
    exit;
}

$requestID = $input['requestID'] ?? null;
$requestTimestamp = $input['timestamp'] ?? null;
$doctorId = $input['doctorId'] ?? null;
$checkDate = $input['checkDate'] ?? null;

// 3. Mandatory Field Validations
$missing = [];
if (empty($requestID))        $missing[] = 'requestID';
if (empty($requestTimestamp)) $missing[] = 'timestamp';
if (empty($doctorId))         $missing[] = 'doctorId';
if (empty($checkDate))        $missing[] = 'checkDate';

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        'status'                   => 'E',
        'code'                     => 400,
        'message'                  => 'Missing mandatory request fields.',
        'missing_mandatory_fields' => $missing,
        'requestID'                => $requestID ?: 'N/A',
        'timestamp'                => $responseTimestamp,
        'data'                     => null
    ]);
    exit;
}

// Format Rule: doctorId must be alphanumeric
if (!preg_match('/^[a-zA-Z0-9]+$/', $doctorId)) {
    http_response_code(400);
    echo json_encode([
        'status'    => 'E',
        'code'      => 400,
        'message'   => 'Invalid format for doctorId. Must be alphanumeric.',
        'requestID' => $requestID,
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
    exit;
}

// Format Rule: timestamp (YYYY-MM-DD HH:MM:SS)
$d = DateTime::createFromFormat('Y-m-d H:i:s', $requestTimestamp);
if (!$d || $d->format('Y-m-d H:i:s') !== $requestTimestamp) {
    http_response_code(400);
    echo json_encode([
        'status'    => 'E',
        'code'      => 400,
        'message'   => 'Invalid timestamp format. Expected YYYY-MM-DD HH:MM:SS.',
        'requestID' => $requestID,
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
    exit;
}

// Format Rule: checkDate (YYYY-MM-DD)
$cd = DateTime::createFromFormat('Y-m-d', $checkDate);
if (!$cd || $cd->format('Y-m-d') !== $checkDate) {
    http_response_code(400);
    echo json_encode([
        'status'    => 'E',
        'code'      => 400,
        'message'   => 'Invalid checkDate format. Expected YYYY-MM-DD.',
        'requestID' => $requestID,
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
    exit;
}

// 4. Delegate to AdminFacade
try {
    global $pdo;
    $facade = new AdminFacade($pdo);
    $result = $facade->verifyDoctorDutyAvailability($doctorId, $checkDate);

    if (!$result) {
        http_response_code(404);
        echo json_encode([
            'status'    => 'F',
            'code'      => 404,
            'message'   => 'Doctor not found in administrative records.',
            'requestID' => $requestID,
            'timestamp' => $responseTimestamp,
            'data'      => null
        ]);
        exit;
    }

    // 5. Success 200 OK
    http_response_code(200);
    echo json_encode([
        'status'           => 'S',
        'code'             => 200,
        'message'          => 'Doctor duty availability successfully verified by Admin Provider.',
        'requestID'        => $requestID,
        'timestamp'        => $responseTimestamp,
        'providerModule'   => 'AdminModule',
        'data'             => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'    => 'E',
        'code'      => 500,
        'message'   => 'Internal server error: ' . $e->getMessage(),
        'requestID' => $requestID,
        'timestamp' => $responseTimestamp,
        'data'      => null
    ]);
}
