<?php
/**
 * =========================================================================
 * tests/test_orm_verification.php
 * =========================================================================
 * BMIT3173 Integrative Programming - Doctor & Appointment ORM Verification
 * 
 * Verifies:
 * 1. ActiveRecord ORM query & findOrFail() functionality
 * 2. Automated persistence via $model->save() without manual SQL UPDATE queries
 * 3. State Pattern integration with ORM ($appointment->complete() -> $this->save())
 * 4. Object References between Doctor and User ($doctor->getUser())
 * 5. Negative Test (findOrFail throwing Exception on invalid ID)
 * =========================================================================
 */

$usingMemoryDb = false;

try {
    require_once __DIR__ . '/../db.php';
} catch (PDOException $e) {
    // If MySQL server is currently offline, fallback to SQLite in-memory database
    $usingMemoryDb = true;
    $sqlitePdo = new PDO('sqlite::memory:');
    $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlitePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Setup mock schema
    $sqlitePdo->exec("CREATE TABLE users (user_id TEXT PRIMARY KEY, username TEXT, email TEXT, password TEXT, role TEXT, is_active INTEGER);");
    $sqlitePdo->exec("CREATE TABLE doctors (doctor_id TEXT PRIMARY KEY, user_id TEXT, ic TEXT, name TEXT, specialization TEXT, qualification TEXT, consultation_fee REAL, phone TEXT, email TEXT, initials TEXT, color TEXT);");
    $sqlitePdo->exec("CREATE TABLE appointments (appointment_id TEXT PRIMARY KEY, patient_id TEXT, doctor_id TEXT, appointment_date TEXT, appointment_time TEXT, reason TEXT, status TEXT);");

    // Seed mock data
    $sqlitePdo->exec("INSERT INTO users VALUES ('U001', 'sarah_johnson', 'sarah@hospital.com', 'hash', 'doctor', 1);");
    $sqlitePdo->exec("INSERT INTO doctors VALUES ('D001', 'U001', '900101-14-5566', 'Dr. Sarah Johnson', 'Cardiology', 'MD', 50.00, '+60123456789', 'sarah@hospital.com', 'SJ', '#059669');");
    $sqlitePdo->exec("INSERT INTO appointments VALUES ('A001', 'P001', 'D001', '2026-09-06', '10:00:00', 'General Checkup', 'Scheduled');");

    global $pdo;
    $pdo = $sqlitePdo;

    require_once __DIR__ . '/../core/Model.php';
    Model::setDb($sqlitePdo);
}

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../Models/Appointment.php';
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/User.php';

echo "====================================================================\n";
echo "       BMIT3173 DOCTOR & APPOINTMENT ORM VERIFICATION SUITE         \n";
if ($usingMemoryDb) {
    echo "       (Running with SQLite Memory DB - MySQL offline)              \n";
} else {
    echo "       (Connected to Live MySQL Database)                           \n";
}
echo "====================================================================\n\n";

$passed = 0;
$total = 5;

// ── Test 1: Appointment::findOrFail($id) ORM retrieval ──────────
echo "Test 1: Appointment::findOrFail(\$id) ORM retrieval\n";
try {
    global $pdo;
    $db = $pdo ?? Database::getInstance()->getConnection();
    $firstId = $db->query("SELECT appointment_id FROM appointments LIMIT 1")->fetchColumn();
    
    if (!$firstId) {
        $firstId = 'A001';
        $db->exec("INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, status) 
                   VALUES ('A001', 'P001', 'D001', '2026-09-06', '10:00:00', 'General Checkup', 'Scheduled')");
    }

    $appointment = Appointment::findOrFail($firstId);

    if ($appointment instanceof Appointment && $appointment->appointment_id === $firstId) {
        echo "✅ PASS: Appointment::findOrFail('{$firstId}') successfully returned an Appointment ORM instance.\n";
        echo "   - Table: " . Appointment::getTable() . "\n";
        echo "   - Primary Key: " . Appointment::getPrimaryKey() . " = {$appointment->appointment_id}\n";
        echo "   - Current Status: {$appointment->status}\n";
        $passed++;
    } else {
        echo "❌ FAIL: Failed to return valid Appointment instance.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 2: Automated save() without manual SQL ──
echo "Test 2: Attribute update & automated \$appointment->save()\n";
try {
    $appointment = Appointment::findOrFail($firstId);
    $originalReason = $appointment->reason;
    $testReason = 'ORM Verification Reason ' . rand(100, 999);

    // Object-oriented attribute assignment
    $appointment->reason = $testReason;
    $saveResult = $appointment->save();

    // Verify in database via raw query
    $stmt = $db->prepare("SELECT reason FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$firstId]);
    $savedReason = $stmt->fetchColumn();

    if ($saveResult && $savedReason === $testReason) {
        echo "✅ PASS: Automated \$appointment->save() successfully updated database row without raw SQL!\n";
        echo "   - Original Reason: {$originalReason}\n";
        echo "   - Updated Reason via ORM: {$savedReason}\n";
        $passed++;

        // Restore original reason
        $appointment->reason = $originalReason;
        $appointment->save();
    } else {
        echo "❌ FAIL: Database was not updated by \$appointment->save().\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 3: State Pattern + ORM persistence ($appointment->complete()) ──
echo "Test 3: State Pattern integration with ORM persistence\n";
try {
    // Reset appointment to Scheduled for test
    $db->prepare("UPDATE appointments SET status = 'Scheduled' WHERE appointment_id = ?")->execute([$firstId]);

    $appointment = Appointment::load($firstId);
    echo "   - Initial Status: " . $appointment->getStatus() . "\n";

    // Trigger complete() consultation
    $appointment->complete();

    // Re-query from DB to verify ORM persisted the transition
    $persistedStatus = $db->query("SELECT status FROM appointments WHERE appointment_id = '{$firstId}'")->fetchColumn();

    if ($persistedStatus === 'Completed' && $appointment->getStatus() === 'Completed') {
        echo "✅ PASS: \$appointment->complete() transitioned status to 'Completed' and persisted via ORM!\n";
        echo "   - Persisted Status in Database: {$persistedStatus}\n";
        $passed++;

        // Reset back to Scheduled
        $db->prepare("UPDATE appointments SET status = 'Scheduled' WHERE appointment_id = ?")->execute([$firstId]);
    } else {
        echo "❌ FAIL: Expected status 'Completed' but got '{$persistedStatus}'.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 4: Doctor Entity Object Reference ($doctor->getUser()) ──
echo "Test 4: Object Reference (\$doctor->getUser() instead of foreign key)\n";
try {
    $doctor = Doctor::find('D001');
    if (!$doctor) {
        $docId = $db->query("SELECT doctor_id FROM doctors LIMIT 1")->fetchColumn();
        $doctor = Doctor::find($docId);
    }

    if ($doctor) {
        $user = $doctor->getUser();
        if ($user instanceof User && !empty($user->username)) {
            echo "✅ PASS: Object Reference verified! \$doctor->getUser() returned associated User entity object.\n";
            echo "   - Doctor Name: {$doctor->name} (Doctor ID: {$doctor->doctor_id})\n";
            echo "   - Associated User Object: [User ID: {$user->user_id}, Username: {$user->username}, Role: {$user->role}]\n";
            echo "   - Encapsulation: Uses object reference instead of raw foreign key string.\n";
            $passed++;
        } else {
            echo "❌ FAIL: \$doctor->getUser() did not return a valid User object.\n";
        }
    } else {
        echo "❌ FAIL: No doctor record found in database.\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
echo "\n";

// ── Test 5: Negative Test: findOrFail on invalid record ───────────
echo "Test 5: Negative Test: Appointment::findOrFail('INVALID_ID') throws Exception\n";
try {
    Appointment::findOrFail('INVALID_NON_EXISTENT_ID_99999');
    echo "❌ FAIL: Exception was not thrown for non-existent record.\n";
} catch (Exception $e) {
    echo "✅ PASS: Non-existent record correctly threw expected Exception:\n";
    echo "   - Exception Message: \"" . $e->getMessage() . "\"\n";
    $passed++;
}
echo "\n";

echo "====================================================================\n";
echo "SUMMARY: {$passed}/{$total} TESTS PASSED (" . round(($passed / $total) * 100) . "%)\n";
echo "====================================================================\n";
