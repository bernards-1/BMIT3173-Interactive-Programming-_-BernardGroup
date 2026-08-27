<?php
// Controllers/PharmacyController.php
require_once __DIR__ . '/../Models/Pharmacy.php';

class PharmacyController {

    /**
     * Ensure only authenticated pharmacists can access these pages.
     */
    private function checkPharmacistAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
            header('Location: ../../Views/Login/login.php');
            exit;
        }
    }

    /**
     * Dashboard page — stats, pending prescriptions preview, low stock alerts.
     */
    public function dashboard() {
        $this->checkPharmacistAuth();
        require_once 'Views/Pharmacy/dashboard.php';
    }

    /**
     * Prescription Queue page — all pending prescriptions.
     */
    public function queue() {
        $this->checkPharmacistAuth();
        require_once 'Views/Pharmacy/queue.php';
    }

    /**
     * Dispensing History page.
     */
    public function history() {
        $this->checkPharmacistAuth();
        require_once 'Views/Pharmacy/history.php';
    }

    /**
     * Inventory Management page.
     */
    public function inventory() {
        $this->checkPharmacistAuth();
        require_once 'Views/Pharmacy/inventory.php';
    }

    /**
     * Pharmacist Profile page.
     */
    public function profile() {
        $this->checkPharmacistAuth();
        require_once 'Views/Pharmacy/pharmacistProfile.php';
    }
}
