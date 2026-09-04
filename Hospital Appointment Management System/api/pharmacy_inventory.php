<?php
// api/pharmacy_inventory.php
require_once '../db.php';
require_once '../Models/Pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 1. Authorize pharmacist session OR Admin consumer header/session
$isPharmacist = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'pharmacist';
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$consumerHeader = $_SERVER['HTTP_X_CONSUMER_MODULE'] ?? '';
$isAuthorizedConsumer = ($isAdmin || $consumerHeader === 'AdminModule');

if (!$isPharmacist && !$isAuthorizedConsumer) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'Unauthorized access: Pharmacist or AdminModule authorization required'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET for Admin Consumer alerts
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_alerts') {
    $threshold = (int)($_GET['threshold'] ?? 10);
    $lowStock = Pharmacy::getLowStockMedicines();
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'provider' => 'PharmacyModule',
        'threshold' => $threshold,
        'count' => count($lowStock),
        'data' => $lowStock
    ]);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);

try {
    if ($method === 'POST') {
        // Add new medicine
        if (empty($input['brand_name']) || empty($input['generic_name']) || empty($input['dosage']) || empty($input['category'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }

        $success = Pharmacy::addMedicine($input);
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Medicine added successfully!']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to add medicine to database.']);
        }

    } elseif ($method === 'PUT') {
        // Edit existing medicine
        $medicine_id = $input['medicine_id'] ?? null;
        if (!$medicine_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Medicine ID required']);
            exit;
        }

        $success = Pharmacy::updateMedicine($medicine_id, $input);
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Medicine updated successfully!']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update medicine.']);
        }

    } elseif ($method === 'PATCH') {
        // Restock medicine stock
        $medicine_id = $input['medicine_id'] ?? null;
        $qty_to_add = (int)($input['qty_to_add'] ?? 0);

        if (!$medicine_id || $qty_to_add <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid medicine ID or quantity']);
            exit;
        }

        $success = Pharmacy::restockMedicine($medicine_id, $qty_to_add);
        if ($success) {
            $updatedMed = Pharmacy::getMedicineById($medicine_id);
            echo json_encode([
                'status' => 'success',
                'message' => 'Stock updated!',
                'new_stock_quantity' => $updatedMed['stock_quantity'] ?? 0
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to restock medicine.']);
        }

    } elseif ($method === 'DELETE') {
        // Delete medicine
        $medicine_id = $input['medicine_id'] ?? null;
        if (!$medicine_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Medicine ID required']);
            exit;
        }

        $success = Pharmacy::deleteMedicine($medicine_id);
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Medicine deleted successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete medicine because it has existing prescription records.']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
