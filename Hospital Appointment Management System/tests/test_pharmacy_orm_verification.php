<?php
/**
 * =========================================================================
 * tests/test_pharmacy_orm_verification.php
 * =========================================================================
 * BMIT3173 Integrative Programming - Pharmacy ORM & Object References Test
 * 
 * Verifies:
 * 1. Pharmacist ORM mapping & User object reference ($pharmacist->getUser())
 * 2. Medicine ORM entity mapping, domain methods & automated $medicine->save()
 * 3. Prescription ORM entity mapping & Medicine object reference ($prescription->getMedicine())
 * 4. Observer Pattern integration with ORM (LowStockAlertNotifier)
 * 5. Negative Test (findOrFail throwing Exception on invalid ID)
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
    $sqlitePdo->exec("CREATE TABLE pharmacists (pharmacist_id TEXT PRIMARY KEY, user_id TEXT, ic TEXT, full_name TEXT, phone TEXT, license_number TEXT, qualification TEXT);");
    $sqlitePdo->exec("CREATE TABLE medicines (medicine_id TEXT PRIMARY KEY, brand_name TEXT, generic_name TEXT, dosage TEXT, category TEXT, unit_type TEXT, manufacturer TEXT, stock_quantity INTEGER, minimum_stock INTEGER, unit_price REAL, expiry_date TEXT, description TEXT);");
    $sqlitePdo->exec("CREATE TABLE prescriptions (prescription_id TEXT PRIMARY KEY, record_id TEXT, medicine_id TEXT, dosage TEXT, frequency TEXT, duration TEXT, instructions TEXT, quantity INTEGER, is_dispensed INTEGER, dispensed_at TEXT, dispensed_by TEXT, dispense_notes TEXT);");

    // Seed mock data
    $sqlitePdo->exec("INSERT INTO users VALUES ('U003', 'jane_smith', 'jane@clinic.com', 'hash', 'pharmacist', 1);");
    $sqlitePdo->exec("INSERT INTO pharmacists VALUES ('PH001', 'U003', '920202-14-1122', 'Jane Smith', '+60129998877', 'LIC-1001', 'B.Pharm');");
    $sqlitePdo->exec("INSERT INTO medicines VALUES ('M001', 'Amoxicillin', 'Amoxicillin Trihydrate', '500mg', 'Antibiotic', 'Capsule', 'PharmaCorp', 50, 15, 12.50, '2027-12-31', 'Antibiotic');");
    $sqlitePdo->exec("INSERT INTO prescriptions VALUES ('PR001', 'MR001', 'M001', '500mg', 'Twice daily', '7 days', 'After food', 14, 0, NULL, NULL, NULL);");

    global $pdo;
    $pdo = $sqlitePdo;

    require_once __DIR__ . '/../core/Model.php';
    Model::setDb($sqlitePdo);
}

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../Models/Pharmacist.php';
require_once __DIR__ . '/../Models/Medicine.php';
require_once __DIR__ . '/../Models/Prescription.php';
require_once __DIR__ . '/../Models/StockObserver.php';

echo "====================================================================\n";
echo "       BMIT3173 PHARMACY ORM & ENTITY CLASS VERIFICATION TEST       \n";
if ($usingMemoryDb) {
    echo "       (Running with SQLite Memory DB - MySQL offline)              \n";
} else {
    echo "       (Connected to Live MySQL Database)                           \n";
}
echo "====================================================================\n\n";

$passed = 0;
$total = 5;

// ── Test 1: Pharmacist ORM & User Object Reference ────────────────
echo "Test 1: Pharmacist ORM mapping & \$pharmacist->getUser() Object Reference\n";
try {
    $pharmacist = Pharmacist::findOrFail('PH001');
    $user = $pharmacist->getUser();

    if ($pharmacist instanceof Pharmacist && $user instanceof User && $user->username === 'jane_smith') {
        echo "✅ PASS: Pharmacist ORM entity mapped successfully!\n";
        echo "   - Pharmacist Name: {$pharmacist->full_name} (ID: {$pharmacist->pharmacist_id})\n";
        echo "   - Object Reference: \$pharmacist->getUser() returned User [ID: {$user->user_id}, Username: {$user->username}, Role: {$user->role}]\n";
        echo "   - Requirement Met: Object references used instead of foreign keys!\n";
        $passed++;
    } else {
        echo "❌ FAIL: Failed to map Pharmacist entity or resolve User object reference.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 2: Medicine ORM & Automated save() ───────────────────────
echo "Test 2: Medicine ORM mapping & automated \$medicine->save()\n";
try {
    $med = Medicine::findOrFail('M001');
    $originalStock = (int)$med->stock_quantity;
    
    // Modify attribute using OOP
    $med->stock_quantity = $originalStock - 5;
    $saveSuccess = $med->save();

    // Reload from DB to verify persistence without manual SQL
    $reloaded = Medicine::findOrFail('M001');
    if ($saveSuccess && (int)$reloaded->stock_quantity === ($originalStock - 5)) {
        echo "✅ PASS: Automated \$medicine->save() persisted stock update without raw SQL!\n";
        echo "   - Medicine: {$med->brand_name} ({$med->dosage})\n";
        echo "   - Previous Stock: {$originalStock} -> Updated Stock via ORM: {$reloaded->stock_quantity}\n";
        $passed++;

        // Restore stock
        $med->stock_quantity = $originalStock;
        $med->save();
    } else {
        echo "❌ FAIL: \$medicine->save() failed to persist to database.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 3: Prescription ORM & Medicine Object Reference ──────────
echo "Test 3: Prescription ORM mapping & \$prescription->getMedicine() Object Reference\n";
try {
    $presc = Prescription::findOrFail('PR001');
    $medicine = $presc->getMedicine();

    if ($presc instanceof Prescription && $medicine instanceof Medicine && $medicine->brand_name === 'Amoxicillin') {
        echo "✅ PASS: Prescription ORM entity mapped and resolved Medicine object reference!\n";
        echo "   - Prescription ID: {$presc->prescription_id} (Dosage: {$presc->dosage}, Qty: {$presc->quantity})\n";
        echo "   - Object Reference: \$prescription->getMedicine() -> [Brand: {$medicine->brand_name}, Category: {$medicine->category}, Unit Price: \${$medicine->unit_price}]\n";
        echo "   - Encapsulation: Avoids manual SQL JOIN; uses clean entity relationship!\n";
        $passed++;
    } else {
        echo "❌ FAIL: Failed to resolve Prescription -> Medicine relationship.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 4: Observer Pattern Integration with Stock Deduction ─────
echo "Test 4: Observer Pattern (LowStockAlertNotifier) verification\n";
try {
    $handler = MedicineStockHandler::getInstance();
    $handler->clearObservers();
    StockWarningRegistry::clearWarnings();

    $observer = new LowStockAlertNotifier();
    $handler->attach($observer);

    // Trigger low stock warning
    $handler->notify('Amoxicillin (ID: M001)', 5, 15);

    $warnings = StockWarningRegistry::getWarnings();
    if (!empty($warnings) && count($warnings) === 1 && $warnings[0]['current_stock'] === 5) {
        echo "✅ PASS: Observer Pattern triggered successfully!\n";
        echo "   - StockSubject: MedicineStockHandler\n";
        echo "   - StockObserver: LowStockAlertNotifier\n";
        echo "   - Warning Captured in Registry: {$warnings[0]['medicine']} (Stock: {$warnings[0]['current_stock']} <= Min: {$warnings[0]['min_stock']})\n";
        $passed++;
    } else {
        echo "❌ FAIL: Observer failed to capture low stock warning.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 5: Negative Test: findOrFail on invalid Medicine ─────────
echo "Test 5: Negative Test: Medicine::findOrFail('M_NON_EXISTENT') throws Exception\n";
try {
    Medicine::findOrFail('M_NON_EXISTENT_999');
    echo "❌ FAIL: Exception was not thrown for non-existent medicine.\n";
} catch (Exception $e) {
    echo "✅ PASS: Expected Exception correctly thrown for non-existent record:\n";
    echo "   - Exception Message: \"" . $e->getMessage() . "\"\n";
    $passed++;
}
echo "\n";

echo "====================================================================\n";
echo "SUMMARY: {$passed}/{$total} TESTS PASSED (" . round(($passed / $total) * 100) . "%)\n";
echo "====================================================================\n";
