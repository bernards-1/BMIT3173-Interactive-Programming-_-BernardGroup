<?php
// services/PrescriptionConsumerService.php

class PrescriptionConsumerService
{
    private $baseUrl;

    public function __construct($baseUrl = null)
    {
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $base_dir = '/Hospital Appointment Management System';
            if (strpos($script, '/Hospital Appointment Management System') !== false) {
                $base_dir = '/Hospital Appointment Management System';
            } else {
                $parts = explode('/', trim($script, '/'));
                if (!empty($parts) && !empty($parts[0])) {
                    $base_dir = '/' . $parts[0];
                }
            }
            $this->baseUrl = "{$protocol}://{$host}" . str_replace(' ', '%20', $base_dir);
        }
    }

    private function buildUrl(string $path, array $queryParams = []): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        return $url;
    }

    /**
     * Consume Clinical Prescriptions Web Service via cURL RESTful API
     * with graceful fallback to Pharmacy Model.
     */
    public function getPendingPrescriptions(): array
    {
        $requestId = 'REQ_PHARM_QUEUE_' . bin2hex(random_bytes(3));
        $timestamp = date('Y-m-d H:i:s');

        $url = $this->buildUrl(
            '/api/get_prescriptions.php',
            [
                'status'    => 'pending',
                'requestID' => $requestId,
                'timeStamp' => $timestamp
            ]
        );

        // Close session write to avoid session lock deadlocks during loopback cURL on Windows Apache
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);
        if ($sessionActive) {
            session_write_close();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Reopen session if it was active before cURL
        if ($sessionActive && session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (($data['status'] ?? null) === 'S' && isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }
        }

        // Graceful Fallback to Pharmacy ORM / Model if Web Service is offline, times out, or loopback fails
        try {
            require_once __DIR__ . '/../db.php';
            global $pdo;
            if (!$pdo && class_exists('Database')) {
                $pdo = Database::getInstance()->getConnection();
            }
            require_once __DIR__ . '/../Models/Pharmacy.php';
            if (class_exists('Pharmacy')) {
                return Pharmacy::getPendingQueue();
            }
        } catch (\Throwable $e) {
            error_log('PrescriptionConsumerService fallback error: ' . $e->getMessage());
        }

        return [];
    }
}
