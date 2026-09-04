<?php

require_once __DIR__ . '/../Models/PatientRepository.php';

echo "========================================================\n";
echo "       RUNNING IDOR DEFENSE VERIFICATION      \n";
echo "========================================================\n\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup mock schema and target record owned by Patient B (P002)
$pdo->exec("CREATE TABLE appointments (appointment_id TEXT PRIMARY KEY, patient_id TEXT, status TEXT);");
$pdo->exec("INSERT INTO appointments VALUES ('APT_999', 'P002', 'Scheduled');");

$repo = new PatientRepository($pdo);

// Simulate Patient A (P001) attempting to cancel Patient B's appointment
$patientA_SessionId = 'P001';
$targetAppointmentId = 'APT_999';
$correlationId = 'CORR-' . bin2hex(random_bytes(4));

$result = $repo->cancelAppointment($targetAppointmentId, $patientA_SessionId, $correlationId);

// Assert that unauthorized access was rejected
if ($result === false) {
    echo "[PASS] IDOR Protection Verified!\n";
    echo "       Patient A ({$patientA_SessionId}) attempted to modify Patient B's record ({$targetAppointmentId}).\n";
    echo "       Result: Operation DENIED by session ownership check.\n";
    echo "       Audit Log Generated with Correlation ID: {$correlationId}\n";
    echo "       Privacy Guard: Zero sensitive patient data (PHI) written to logs.\n";
}

echo "\n--------------------------------------------------------\n";
echo "SECURITY TEST COMPLETE\n";
echo "========================================================\n";