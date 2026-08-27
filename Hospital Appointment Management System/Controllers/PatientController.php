<?php
// Controllers/PatientController.php

class PatientController {
    
    private function checkPatientAuth() {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public function dashboard() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/mainpage.php';
    }

    public function bookAppointment() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/book_appointment.php';
    }

    public function myAppointments() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/my_appointment.php';
    }

    public function medicalRecords() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/medical_records.php';
    }

    public function profile() {
        $this->checkPatientAuth();
        require_once 'Views/Patient/patientProfile.php';
    }
}