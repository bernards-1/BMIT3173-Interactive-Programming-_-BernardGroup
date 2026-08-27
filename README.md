# Hospital Appointment Management System

This is a simple Hospital Appointment Management System built with PHP (OOP) and MySQL. It uses the MVC (Model-View-Controller) architecture and implements several design patterns.

---

## Features

The system supports 4 roles:

### 1. Patient
- Register and login.
- Book doctor appointments.
- View medical history and prescriptions.
- Pay bills.

### 2. Doctor
- View scheduled appointments.
- Add diagnosis, symptoms, and prescribe medicines for patients.
- Submit leave requests.

### 3. Pharmacist
- Manage medicine stock and prices.
- Dispense medicine for prescriptions (automatically updates stock).
- Get warning alerts when stock is low.

### 4. Admin
- Manage users (Doctors, Patients, Pharmacists, Admins).
- Approve or reject doctor leave requests.
- View billing and payment reports.
- Manage system settings.

---

## Design Patterns Used

This project implements the following programming patterns:
- **Singleton Pattern**: Used in [db.php](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/db.php) to ensure only one database connection instance exists.
- **Strategy Pattern**: Used in [Models/PricingStrategy.php](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/Models/PricingStrategy.php) to calculate different appointment fees (Standard, Follow-up, Routine).
- **Observer Pattern**: Used in [Models/StockObserver.php](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/Models/StockObserver.php) to trigger alerts when medicine stock runs below the minimum limit.
- **State Pattern**: Used in [Models/AppointmentState.php](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/Models/AppointmentState.php) to handle appointment status transitions (Scheduled, Completed, Cancelled, Expired).

---

## Setup Instructions

1. Install **XAMPP** (or any server with Apache and MySQL).
2. Open phpMyAdmin and create a database named `hospital_appointment_system`.
3. Import the [database.sql](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/database.sql) file into the database.
4. Put the project folder into your Web Server root directory (e.g., `xampp/htdocs/`).
5. Open [db.php](file:///c:/xampp/htdocs/Hospital%20Appointment%20Management%20System/db.php) and change the database connection details (like database password) if yours is different.
6. Open your browser and visit: `http://localhost/Hospital Appointment Management System/`

---

## Test Accounts

The password for all mock accounts is **`password123`**:

| Role | Username | Email |
| **Admin** | `admin_user` | `admin@hospital.com` |
| **Doctor** | `sarah_johnson` | `sarah@hospital.com` |
| **Pharmacist** | `jane_smith` | `jane@clinic.com` |
| **Patient** | `john_doe` | `john@patient.com` |
