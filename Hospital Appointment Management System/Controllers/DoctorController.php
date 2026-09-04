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

    public function updateProfile($userId, $doctorId, $data) {
        $this->checkDoctorAuth();
        if (empty($data['name']) || empty($data['specialization']) || empty($data['qualification']) || empty($data['phone']) || empty($data['email']) || empty($data['initials']) || empty($data['color'])) {
            throw new Exception('Please fill in all required fields.');
        }
        require_once __DIR__ . '/../Models/Doctor.php';
        return Doctor::updateProfile($userId, $doctorId, $data);
    }
}

