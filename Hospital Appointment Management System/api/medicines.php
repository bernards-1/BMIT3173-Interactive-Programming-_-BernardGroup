<?php
// api/medicines.php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$timestamp = date('c');

try {
    // Expose dynamic medicine list from the database
    $stmt = $pdo->query('SELECT medicine_id, brand_name, generic_name, dosage, category, unit_price FROM medicines ORDER BY brand_name');
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'S',
        'code' => 200,
        'count' => count($medicines),
        'data' => $medicines,
        'timestamp' => $timestamp
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'E',
        'code' => 500,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => $timestamp
    ]);
}

