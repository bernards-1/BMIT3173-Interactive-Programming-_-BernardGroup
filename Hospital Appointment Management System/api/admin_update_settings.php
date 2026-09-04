<?php
// api/admin_update_settings.php
/**
 * Dedicated Controller Route for Admin Settings.
 * Requires Admin session authorization and validates CSRF Token.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/SecuritySession.php';
require_once __DIR__ . '/../Models/Setting.php';

header('Content-Type: application/json; charset=UTF-8');

// 1. Enforce Admin Session Authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'Unauthorized: Admin authentication required.'
    ]);
    exit;
}

// 2. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 405, 'message' => 'Method Not Allowed']);
    exit;
}

// 3. Verify CSRF Token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

$input = json_decode(file_get_contents('php://input'), true);

if (!$csrfToken && isset($input['csrf_token'])) {
    $csrfToken = $input['csrf_token'];
}

if (!SecuritySession::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'code' => 403,
        'message' => 'Forbidden: Invalid or missing CSRF token.'
    ]);
    exit;
}

// 4. Validate and update settings
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'Invalid JSON payload.']);
    exit;
}

unset($input['csrf_token']); // Remove token from database keys

$settingModel = new Setting();
$updated = $settingModel->updateSettings($input);

if ($updated) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'message' => 'System settings updated successfully.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database failure while updating settings.'
    ]);
}
exit;
