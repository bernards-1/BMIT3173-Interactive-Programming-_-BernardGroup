<?php
// Controllers/DoctorController.php

class DoctorController {
    
    private function checkDoctorAuth() {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public function dashboard() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/mainpage.php';
    }

    public function schedule() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/schedule.php';
    }

    public function appointments() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/appointments.php';
    }

    public function patients() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/patients.php';
    }

    public function medicalRecords() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/medicalRecords.php';
    }

    public function patientMedicalRecords() {
        $this->checkDoctorAuth();
        require_once 'Views/Doctor/Patient_medicalRecords.php';
    }
}
