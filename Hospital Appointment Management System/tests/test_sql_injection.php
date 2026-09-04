<?php
// test_sql_injection.php
// A database-free test to verify that PDO prepared statements isolate query structure from parameter values.

class MockPDOStatement {
    private $query;
    private $params = [];

    public function __construct($query) {
        $this->query = $query;
    }

    public function execute($params = null) {
        if (is_array($params)) {
            $this->params = $params;
        }
        return true;
    }

    public function bindValue($param, $value, $type = null) {
        $this->params[$param] = $value;
        return true;
    }

    public function fetch($mode = null) {
        // Return dummy data for D001 doctor details fetch
        return [
            'name' => 'Dr. Sarah Johnson',
            'email' => 'sarah@hospital.com',
            'doctor_id' => 'D001',
            'user_id' => 'U001',
            'specialization' => 'Cardiology',
            'qualification' => 'MD',
            'consultation_fee' => 50.00,
            'phone' => '123456789',
            'initials' => 'SJ',
            'color' => '#059669',
            'ic' => '12345'
        ];
    }

    public function fetchColumn($column_number = 0) {
        return 0; // Return 0 to simulate username check (no duplicate exists)
    }

    public function getExecutedQuery() {
        return $this->query;
    }

    public function getParameters() {
        return $this->params;
    }
}

class MockPDO {
    public $statements = [];
    public $inTransaction = false;

    public function prepare($query, $options = []) {
        $stmt = new MockPDOStatement($query);
        $this->statements[] = $stmt;
        return $stmt;
    }

    public function beginTransaction() {
        $this->inTransaction = true;
        return true;
    }

    public function commit() {
        $this->inTransaction = false;
        return true;
    }

    public function rollBack() {
        $this->inTransaction = false;
        return true;
    }
}

// Instantiate mock PDO
$pdo = new MockPDO();

// Require files but bypass actual db.php connection if it sets $pdo
// We define a dummy Database singleton or directly inject $pdo into global scope
class Database {
    public static function getInstance() {
        return new self();
    }
    public function getConnection() {
        global $pdo;
        return $pdo;
    }
}

// Require the Doctor model which relies on global $pdo
require_once 'Models/Doctor.php';

function runMockInjectionTest() {
    global $pdo;

    echo "==================================================\n";
    echo "SQL INJECTION PREVENTATIVE TEST SUITE (MOCK DB)\n";
    echo "==================================================\n\n";

    $testDoctorUserId = 'U001';
    $testDoctorId = 'D001';

    // Malicious payload trying to inject SQL commands into email parameter
    $maliciousEmailPayload = "hacker@malicious.com', name = 'INJECTED_HACKED_NAME' WHERE user_id = 'U001' -- ";

    $updateData = [
        'name' => 'Dr. Sarah Johnson',
        'specialization' => 'Cardiology',
        'qualification' => 'MD, FACC',
        'phone' => '+60123456789',
        'email' => $maliciousEmailPayload, // Injection payload
        'consultation_fee' => 50.00,
        'initials' => 'SJ',
        'color' => '#059669',
        'ic' => '900101-14-5566'
    ];

    echo "Submitting profile update via Doctor::updateProfile...\n";
    echo "Using Malicious Email Payload: \"" . $maliciousEmailPayload . "\"\n\n";

    Doctor::updateProfile($testDoctorUserId, $testDoctorId, $updateData);

    // Analyze the PDO calls
    echo "Inspecting prepared SQL statements & executed parameters:\n";
    foreach ($pdo->statements as $index => $stmt) {
        $sql = $stmt->getExecutedQuery();
        $params = $stmt->getParameters();
        echo "Statement " . ($index + 1) . ":\n";
        echo "   [SQL Template]: " . trim(preg_replace('/\s+/', ' ', $sql)) . "\n";
        echo "   [Bound Values]: " . json_encode($params) . "\n\n";
    }

    // Security Verification Assertion
    // Check if the malicious SQL string ever broke into the SQL statement itself
    $injectionSucceeded = false;
    foreach ($pdo->statements as $stmt) {
        $sql = $stmt->getExecutedQuery();
        if (strpos($sql, 'INJECTED_HACKED_NAME') !== false) {
            $injectionSucceeded = true;
        }
    }

    if ($injectionSucceeded) {
        echo "❌ FAILURE: SQL Injection Succeeded! Malicious commands were interpolated directly into the SQL statement structure.\n";
    } else {
        echo "✅ SUCCESS: SQL Injection Blocked! The query layout remains constant with placeholders (?). The parameters were safely isolated as bound values.\n";
        echo "   The database engine compiles the query template separate from the inputs, making SQL injection impossible.\n";
    }
}

runMockInjectionTest();
?>
