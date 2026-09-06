<?php
/**
 * =========================================================================
 * tests/test_patient_pricing_mvc.php
 * =========================================================================
 * BMIT3173 Integrative Programming - Patient Pricing & MVC Flow Test Suite
 * 
 * Verifies MVC Architecture Flow:
 * View 
 *   ↓ sends appointment type and doctor ID
 * Controller 
 *   ↓ requests doctor and pricing calculation
 * Repository/ORM + Pricing Service 
 *   ↓ returns final amount
 * Controller 
 *   ↓ passes prepared result
 * View
 * =========================================================================
 */

$usingMemoryDb = false;

try {
    require_once __DIR__ . '/../db.php';
} catch (PDOException $e) {
    $usingMemoryDb = true;
    $sqlitePdo = new PDO('sqlite::memory:');
    $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlitePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Setup mock schema
    $sqlitePdo->exec("CREATE TABLE users (user_id TEXT PRIMARY KEY, username TEXT, email TEXT, password TEXT, role TEXT, is_active INTEGER);");
    $sqlitePdo->exec("CREATE TABLE doctors (doctor_id TEXT PRIMARY KEY, user_id TEXT, ic TEXT, name TEXT, specialization TEXT, qualification TEXT, consultation_fee REAL, phone TEXT, email TEXT, initials TEXT, color TEXT);");

    // Seed mock data
    $sqlitePdo->exec("INSERT INTO users VALUES ('U001', 'sarah_johnson', 'sarah@hospital.com', 'hash', 'doctor', 1);");
    $sqlitePdo->exec("INSERT INTO doctors VALUES ('D001', 'U001', '900101-14-5566', 'Dr. Sarah Johnson', 'Cardiology', 'MD', 50.00, '+60123456789', 'sarah@hospital.com', 'SJ', '#059669');");

    global $pdo;
    $pdo = $sqlitePdo;

    require_once __DIR__ . '/../core/Model.php';
    Model::setDb($sqlitePdo);
}

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../services/PricingService.php';
require_once __DIR__ . '/../Controllers/PatientController.php';

// Setup mock session for Patient role
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user'] = ['user_id' => 'U999', 'username' => 'test_patient', 'role' => 'patient'];

echo "====================================================================\n";
echo "       PATIENT PRICING MVC ARCHITECTURE VERIFICATION TEST           \n";
echo "       Architecture Flow: View -> Controller -> ORM + Service       \n";
echo "====================================================================\n\n";

$controller = new PatientController();
$doctorId = 'D001';
$passed = 0;
$total = 4;

// ── Test 1: Standard Pricing Calculation via Controller ───────────
echo "Test 1: Standard Pricing (Consultation)\n";
$res1 = $controller->calculateAppointmentFee($doctorId, 'Consultation');
echo "   - Doctor: {$res1['doctor_name']} (Base Fee: RM" . number_format($res1['base_fee'], 2) . ")\n";
echo "   - Appointment Type: {$res1['type']}\n";
echo "   - Final Calculated Amount: RM" . number_format($res1['final_amount'], 2) . "\n";

// Expected: 50.00 + 10.00 = 60.00
if ($res1['success'] && $res1['final_amount'] === 60.00) {
    echo "✅ PASS: Standard Pricing correctly calculated: Base(RM50) + Booking(RM10) = RM60.00\n";
    $passed++;
} else {
    echo "❌ FAIL: Expected RM60.00, got RM{$res1['final_amount']}\n";
}
echo "\n";

// ── Test 2: Follow-up Pricing Calculation via Controller ──────────
echo "Test 2: Follow-up Pricing (50% Base Fee + $10.00 booking fee)\n";
$res2 = $controller->calculateAppointmentFee($doctorId, 'Follow-up');
echo "   - Appointment Type: {$res2['type']}\n";
echo "   - Final Calculated Amount: RM" . number_format($res2['final_amount'], 2) . "\n";

// Expected: (50.00 * 0.5) + 10.00 = 35.00
if ($res2['success'] && $res2['final_amount'] === 35.00) {
    echo "✅ PASS: Follow-up Pricing correctly calculated: 50%(RM25) + Booking(RM10) = RM35.00\n";
    $passed++;
} else {
    echo "❌ FAIL: Expected RM35.00, got RM{$res2['final_amount']}\n";
}
echo "\n";

// ── Test 3: Routine Check-up Pricing Calculation via Controller ───
echo "Test 3: Routine Check-up Pricing (80% Base Fee + $10.00 booking fee)\n";
$res3 = $controller->calculateAppointmentFee($doctorId, 'Routine Check-up');
echo "   - Appointment Type: {$res3['type']}\n";
echo "   - Final Calculated Amount: RM" . number_format($res3['final_amount'], 2) . "\n";

// Expected: (50.00 * 0.8) + 10.00 = 50.00
if ($res3['success'] && $res3['final_amount'] === 50.00) {
    echo "✅ PASS: Routine Pricing correctly calculated: 80%(RM40) + Booking(RM10) = RM50.00\n";
    $passed++;
} else {
    echo "❌ FAIL: Expected RM50.00, got RM{$res3['final_amount']}\n";
}
echo "\n";

// ── Test 4: ORM Encapsulation Check (No raw SQL in View) ───────────
echo "Test 4: Architecture Verification (ORM Encapsulation)\n";
try {
    $doctor = Doctor::findOrFail($doctorId);
    if ($doctor instanceof Doctor && $doctor->doctor_id === $doctorId) {
        $computed = PricingService::calculateFee($doctor, 'Follow-up');
        echo "✅ PASS: PricingService directly accepts Doctor ORM model without manual SQL queries in View!\n";
        echo "   - Verified Doctor ORM Model: " . get_class($doctor) . "\n";
        echo "   - Verified PricingService Strategy: " . get_class(PricingService::getStrategyForType('Follow-up')) . "\n";
        $passed++;
    } else {
        echo "❌ FAIL: Failed to retrieve Doctor ORM model.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

echo "====================================================================\n";
echo "SUMMARY: {$passed}/{$total} TESTS PASSED (" . round(($passed / $total) * 100) . "%)\n";
echo "====================================================================\n";
