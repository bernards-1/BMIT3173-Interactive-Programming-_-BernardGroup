<?php
// services/PharmacyConsumerService.php

class PharmacyConsumerService {
    private $baseUrl;

    public function __construct($baseUrl = null) {
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $base_dir = '/Hospital Appointment Management System';
            $this->baseUrl = "{$protocol}://{$host}" . str_replace(' ', '%20', $base_dir) . "/api";
        }
    }

    /**
     * Consume external Pharmacy inventory alert service.
     * Returns structured result including HTTP code and captured response.
     */
    public function getInventoryAlerts($threshold = 10) {
        $requestID = 'REQ-' . bin2hex(random_bytes(4));
        $timestamp = date('Y-m-d H:i:s');
        $endpoint = $this->baseUrl . '/pharmacy_inventory.php?action=get_alerts&threshold=' . urlencode($threshold) . '&requestID=' . urlencode($requestID) . '&timestamp=' . urlencode($timestamp);

        $headers = [
            'Accept: application/json',
            'X-Consumer-Module: AdminModule',
            'X-Request-ID: ' . $requestID,
            'X-Timestamp: ' . $timestamp
        ];

        // Pass current Admin session cookie to simulate authenticated inter-module API call
        if (session_status() === PHP_SESSION_ACTIVE && !empty(session_id())) {
            $headers[] = 'Cookie: ' . session_name() . '=' . session_id();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'status'    => 'error',
                'httpCode'  => 504,
                'requestID' => $requestID,
                'message'   => 'Gateway Timeout: Unable to reach external Pharmacy Module service: ' . $curlError,
                'data'      => null
            ];
        }

        $decoded = json_decode($responseBody, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300) 
            && isset($decoded['status']) 
            && in_array($decoded['status'], ['S', 'success'], true);

        return [
            'status'    => $isSuccess ? 'success' : 'error',
            'httpCode'  => $httpCode,
            'requestID' => $requestID,
            'raw'       => $responseBody,
            'data'      => $decoded
        ];
    }
}
