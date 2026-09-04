<?php
// api/get_prescriptions.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Models/Pharmacy.php';

header('Content-Type: application/json; charset=utf-8');

$status = $_GET['status'] ?? 'pending';
$timeStamp = $_GET['timeStamp'] ?? date('Y-m-d H:i:s');

try {
    $data = Pharmacy::getPendingQueue();
    echo json_encode([
        'status'    => 'success',
        'code'      => 200,
        'count'     => count($data),
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'    => 'error',
        'code'      => 500,
        'message'   => 'Failed to fetch doctor prescriptions: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
