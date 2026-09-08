<?php
// services/MedicineConsumerService.php

class MedicineConsumerService
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

    public function getActiveMedicines(): array
    {
        $requestId = 'REQ_DOC_' . bin2hex(random_bytes(4));
        $timestamp = date('Y-m-d H:i:s');

        $url = $this->buildUrl(
            '/api/medicines.php',
            [
                'requestID' => $requestId,
                'timestamp' => $timestamp
            ]
        );

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response !== false) {
            $data = json_decode($response, true);

            if (($data['status'] ?? null) === 'S' && !empty($data['data'])) {
                return $data['data'];
            }
        }

        // Fallback to ORM Model if Web Service is offline or times out
        try {
            require_once __DIR__ . '/../Models/Medicine.php';
            if (class_exists('Medicine')) {
                $medicines = Medicine::all();
                return array_map(function($m) {
                    return is_object($m) && method_exists($m, 'toArray') ? $m->toArray() : (array)$m;
                }, $medicines);
            }
        } catch (\Throwable $e) {
            // Silently fall back to empty list if DB connection is unavailable
        }

        return [];
    }
}
