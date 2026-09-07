<?php
// api/get_prescriptions.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Models/Pharmacy.php';

header('Content-Type: application/json; charset=utf-8');

$status = $_GET['status'] ?? 'pending';
$requestID = $_GET['requestID'] ?? 'REQ-' . bin2hex(random_bytes(4));
$timeStamp = $_GET['timeStamp'] ?? ($_GET['timestamp'] ?? date('Y-m-d H:i:s'));

try {
    $data = Pharmacy::getPendingQueue();
    echo json_encode([
        'status'     => 'S',
        'code'       => 200,
        'count'      => count($data),
        'requestID'  => $requestID,
        'data'       => $data,
        'timestamp'  => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'     => 'E',
        'code'       => 500,
        'requestID'  => $requestID,
        'message'    => 'Failed to fetch doctor prescriptions: ' . $e->getMessage(),
        'timestamp'  => date('Y-m-d H:i:s')
    ]);
}
