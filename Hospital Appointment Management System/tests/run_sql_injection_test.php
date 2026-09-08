<?php

require_once __DIR__ . '/../Models/PatientRepository.php';

echo "========================================================\n";
echo "       RUNNING SQL INJECTION DEFENSE VERIFICATION      \n";
echo "========================================================\n\n";

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE users (user_id TEXT PRIMARY KEY, email TEXT, username TEXT);");
    $pdo->exec("CREATE TABLE patients (
        patient_id TEXT PRIMARY KEY, 
        full_name TEXT, ic TEXT, date_of_birth TEXT, gender TEXT, phone TEXT, 
        blood_type TEXT, address TEXT, emergency_contact_name TEXT, emergency_contact_phone TEXT
    );");

    $pdo->exec("INSERT INTO users VALUES ('U001', 'original@example.com', 'original_user');");
    $pdo->exec("INSERT INTO patients VALUES ('P001', 'Original Name', '900101015555', '1990-01-01', 'Male', '0123456789', 'O+', 'Address', 'Emergency Contact', '0198765432');");

    $repo = new PatientRepository($pdo);

    $injectionPayload = "Malicious Name' OR '1'='1";

    $payloadData = [
        'email'                   => 'hacker@example.com',
        'full_name'               => $injectionPayload,
        'ic'                      => '900101015555',
        'date_of_birth'           => '1990-01-01',
        'gender'                  => 'Male',
        'phone'                   => '0123456789',
        'blood_type'              => 'O+',
        'address'                 => 'Some Address',
        'emergency_contact_name'  => 'Emergency Name',
        'emergency_contact_phone' => '0198765432'
    ];

    $repo->updatePatientProfile('U001', 'P001', $payloadData);

    $updatedPatient = $repo->getPatientById('P001');

    if ($updatedPatient['full_name'] === $injectionPayload) {
        echo "[PASS] SQL Injection Defense Verified:\n";
        echo "       Payload submitted: \"{$injectionPayload}\"\n";
        echo "       Stored Literal Value: \"{$updatedPatient['full_name']}\"\n";
        echo "       Result: Prepared statement successfully treated payload as plain text string.\n";
        echo "       SQL Injection Attack Failed as expected!\n";
    } else {
        echo "[FAIL] Unexpected Behavior: SQL structure may have been altered.\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Test Execution Failed: " . $e->getMessage() . "\n";
}

echo "\n--------------------------------------------------------\n";
echo "SECURITY TEST COMPLETE\n";
echo "========================================================\n";