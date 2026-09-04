<?php
// tests/run_sql_injection_test.php

require_once __DIR__ . '/../Models/PatientRepository.php';

echo "========================================================\n";
echo "       RUNNING SQL INJECTION DEFENSE VERIFICATION      \n";
echo "========================================================\n\n";

try {
    // 使用 SQLite 内存数据库模拟测试环境
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 建表模拟数据
    $pdo->exec("CREATE TABLE users (user_id TEXT PRIMARY KEY, email TEXT);");
    $pdo->exec("CREATE TABLE patients (
        patient_id TEXT PRIMARY KEY, 
        full_name TEXT, ic TEXT, date_of_birth TEXT, gender TEXT, phone TEXT, 
        blood_type TEXT, address TEXT, emergency_contact_name TEXT, emergency_contact_phone TEXT
    );");

    $pdo->exec("INSERT INTO users VALUES ('U001', 'original@example.com');");
    $pdo->exec("INSERT INTO patients VALUES ('P001', 'Original Name', '900101015555', '1990-01-01', 'Male', '0123456789', 'O+', 'Address', 'Emergency Contact', '0198765432');");

    $repo = new PatientRepository($pdo);

    // 恶意 SQL 注入 Payload
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

    // 执行更新
    $repo->updatePatientProfile('U001', 'P001', $payloadData);

    // 查询数据库，验证写入的结果
    $updatedPatient = $repo->getPatientById('P001');

    // 验证逻辑：如果 injectionPayload 被作为“纯文本”精确存入，证明 SQL 语句结构未被破坏（注入失败）
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