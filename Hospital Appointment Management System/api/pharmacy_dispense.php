<?php
// api/pharmacy_dispense.php
require_once '../db.php';
require_once '../Models/Pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 1. Authorize pharmacist session
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// 2. Parse JSON request body
$input = json_decode(file_get_contents('php://input'), true);
$record_id = $input['record_id'] ?? null;
$notes = $input['notes'] ?? '';
$prescription_ids = $input['prescription_ids'] ?? [];
$payment_method = $input['payment_method'] ?? 'Cash';

if (!$record_id) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing medical record ID'
    ]);
    exit;
}

try {
    // 3. Resolve pharmacist_id
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'];
    $pharmacist = Pharmacy::getPharmacistByUserId($user_id);
    $pharmacist_id = $pharmacist ? $pharmacist['pharmacist_id'] : 'PH001';

    // 4. Dispense prescription record & process payment
    $result = Pharmacy::dispenseRecord($record_id, $pharmacist_id, $notes, $prescription_ids, $payment_method);

    if ($result) {
        $receipt = is_array($result) ? $result : null;
        echo json_encode([
            'status' => 'success',
            'message' => 'Prescription dispensed and payment collected successfully!',
            'receipt' => $receipt
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Prescription already dispensed or not found.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
