<?php
// db.php
date_default_timezone_set('Asia/Kuala_Lumpur');
// Database configuration

/**
 * Database Singleton Class
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = 'localhost';
        $db   = 'hospital_appointment_system';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
             $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
             throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

$pdo = Database::getInstance()->getConnection();

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/Models/Setting.php';
$sysSettingModel = new Setting();
$globalSysSettings = $sysSettingModel->getAllSettings();

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $decimals = 2) {
        return '$' . number_format((float)$amount, $decimals);
    }
}

if (!function_exists('__')) {
    function __($text) {
        return $text;
    }
}

if (!function_exists('formatDate')) {
    function formatDate($dateString) {
        if (empty($dateString)) return 'N/A';
        return date('m/d/Y', strtotime($dateString));
    }
}
