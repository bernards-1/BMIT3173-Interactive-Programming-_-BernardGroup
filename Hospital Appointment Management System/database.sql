-- Hospital Appointment Management System Database Schema
DROP DATABASE IF EXISTS `hospital_appointment_system`;
CREATE DATABASE `hospital_appointment_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_appointment_system`;

-- Drop tables in correct dependency order to avoid foreign key errors
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `doctor_leaves`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `pharmacists`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` VARCHAR(10) PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('doctor', 'patient', 'admin', 'pharmacist') NOT NULL,
    `is_active` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Doctors Table
CREATE TABLE IF NOT EXISTS `doctors` (
    `doctor_id` VARCHAR(10) PRIMARY KEY,
    `user_id` VARCHAR(10) NOT NULL UNIQUE,
    `ic` VARCHAR(20) DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `specialization` VARCHAR(100) NOT NULL,
    `qualification` VARCHAR(100) NOT NULL,
    `consultation_fee` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `initials` VARCHAR(5) NOT NULL,
    `color` VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Patients Table
CREATE TABLE IF NOT EXISTS `patients` (
    `patient_id` VARCHAR(10) PRIMARY KEY,
    `user_id` VARCHAR(10) NOT NULL UNIQUE,
    `ic` VARCHAR(20) DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `gender` ENUM('Male','Female') NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `blood_type` VARCHAR(5),
    `address` TEXT,
    `emergency_contact_name` VARCHAR(100),
    `emergency_contact_phone` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `admin_id` VARCHAR(10) PRIMARY KEY,
    `user_id` VARCHAR(10) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `position` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Pharmacists Table
CREATE TABLE IF NOT EXISTS `pharmacists` (
    `pharmacist_id` VARCHAR(10) PRIMARY KEY,
    `user_id` VARCHAR(10) NOT NULL UNIQUE,
    `ic` VARCHAR(20) DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `license_number` VARCHAR(50),
    `qualification` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
    `appointment_id` VARCHAR(10) PRIMARY KEY,
    `patient_id` VARCHAR(10) NOT NULL,
    `doctor_id` VARCHAR(10) NOT NULL,
    `schedule_id` VARCHAR(10) DEFAULT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `reason` TEXT,
    `status` ENUM('Scheduled', 'Completed', 'Cancelled', 'Expired') NOT NULL DEFAULT 'Scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Doctor Leaves Table
CREATE TABLE IF NOT EXISTS `doctor_leaves` (
    `leave_id` VARCHAR(10) PRIMARY KEY,
    `doctor_id` VARCHAR(10) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `reject_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Medical Records Table
CREATE TABLE IF NOT EXISTS `medical_records` (
    `medical_record_id` VARCHAR(10) PRIMARY KEY,
    `patient_id` VARCHAR(10) NOT NULL,
    `doctor_id` VARCHAR(10) NOT NULL,
    `appointment_id` VARCHAR(10) DEFAULT NULL,
    `diagnosis` TEXT NOT NULL,
    `symptoms` TEXT,
    `notes` TEXT,
    `follow_up_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`appointment_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Medicines Table
CREATE TABLE IF NOT EXISTS `medicines` (
    `medicine_id` VARCHAR(10) PRIMARY KEY,
    `brand_name` VARCHAR(100) NOT NULL,
    `generic_name` VARCHAR(100) NOT NULL,
    `dosage` VARCHAR(50) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `unit_type` VARCHAR(50) NOT NULL,
    `manufacturer` VARCHAR(100),
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `minimum_stock` INT NOT NULL DEFAULT 10,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `expiry_date` DATE DEFAULT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Prescriptions Table
CREATE TABLE IF NOT EXISTS `prescriptions` (
    `prescription_id` VARCHAR(10) PRIMARY KEY,
    `record_id` VARCHAR(10) NOT NULL,
    `medicine_id` VARCHAR(10) NOT NULL,
    `dosage` VARCHAR(50) NOT NULL,
    `frequency` VARCHAR(50) NOT NULL,
    `duration` VARCHAR(50) NOT NULL,
    `instructions` TEXT,
    `quantity` INT NOT NULL DEFAULT 1,
    `is_dispensed` TINYINT(1) NOT NULL DEFAULT 0,
    `dispensed_at` DATETIME NULL DEFAULT NULL,
    `dispensed_by` VARCHAR(10) NULL DEFAULT NULL,
    `dispense_notes` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`record_id`) REFERENCES `medical_records`(`medical_record_id`) ON DELETE CASCADE,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
    `payment_id` VARCHAR(10) PRIMARY KEY,
    `appointment_id` VARCHAR(10) NOT NULL,
    `patient_id` VARCHAR(10) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `payment_status` ENUM('Paid', 'Unpaid', 'Refunded') NOT NULL DEFAULT 'Unpaid',
    `payment_date` TIMESTAMP NULL DEFAULT NULL,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`appointment_id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ====================================================
-- Insert Mock Data
-- Password for all mock accounts is: password123
-- hashed using password_hash('password123', PASSWORD_BCRYPT) => '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe'
-- ====================================================

-- 1. Users Mock
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `is_active`) VALUES
('U001', 'sarah_johnson', 'sarah@hospital.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'doctor', 1),
('U002', 'admin_user', 'admin@hospital.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'admin', 1),
('U003', 'john_doe', 'john@patient.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'patient', 1),
('U004', 'jane_smith', 'jane@clinic.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'pharmacist', 1),
('U005', 'emily_davis', 'emily@patient.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'patient', 1),
('U006', 'michael_brown', 'michael@patient.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'patient', 1),
('U007', 'sophia_wilson', 'sophia@patient.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'patient', 1),
('U008', 'david_lee', 'david@patient.com', '$2y$10$aV2sqW/yEXee.DRfSqN06OJq3TUgromXxTp9wewQzUcaUAhc/6qpe', 'patient', 1);

-- 2. Doctors Mock
INSERT INTO `doctors` (`doctor_id`, `user_id`, `name`, `specialization`, `qualification`, `consultation_fee`, `phone`, `email`, `initials`, `color`) VALUES
('D001', 'U001', 'Dr. Sarah Johnson', 'Cardiologist', 'MBBS, MD', 50.00, '+1 (555) 019-2834', 'sarah@hospital.com', 'SJ', '#059669');

-- 3. Patients Mock
INSERT INTO `patients` (`patient_id`, `user_id`, `full_name`, `date_of_birth`, `gender`, `phone`, `blood_type`, `address`, `emergency_contact_name`, `emergency_contact_phone`) VALUES
('P001', 'U003', 'John Doe', '1995-05-15', 'Male', '+1 (555) 019-8833', 'O+', '123 Pine St, Cityville', 'Jane Doe', '+1 (555) 019-8844'),
('P002', 'U005', 'Emily Davis', '1992-08-20', 'Female', '+1 (555) 019-4411', 'A+', '456 Oak St, Cityville', 'Robert Davis', '+1 (555) 019-4422'),
('P003', 'U006', 'Michael Brown', '1988-11-05', 'Male', '+1 (555) 019-5522', 'B+', '789 Maple Ave, Townsville', 'Sarah Brown', '+1 (555) 019-5533'),
('P004', 'U007', 'Sophia Wilson', '1998-03-12', 'Female', '+1 (555) 019-6633', 'O-', '321 Elm Dr, Metroville', 'David Wilson', '+1 (555) 019-6644'),
('P005', 'U008', 'David Lee', '1985-07-24', 'Male', '+1 (555) 019-7744', 'AB+', '654 Birch Rd, Hilltown', 'Linda Lee', '+1 (555) 019-7755');

-- 4. Admins Mock
INSERT INTO `admins` (`admin_id`, `user_id`, `full_name`, `phone`, `position`) VALUES
('A001', 'U002', 'Admin User', '+1 (555) 012-3456', 'Clinic Manager');

-- 5. Pharmacists Mock
INSERT INTO `pharmacists` (`pharmacist_id`, `user_id`, `full_name`, `phone`, `license_number`, `qualification`) VALUES
('PH001', 'U004', 'Jane Smith', '+1 (555) 019-9944', 'LIC998877', 'Bachelor of Pharmacy');

-- 6. Appointments Mock (using CURDATE() so it always populates "Today's Schedule" on any test day!)
INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `schedule_id`, `appointment_date`, `appointment_time`, `reason`, `status`) VALUES
('A001', 'P001', 'D001', NULL, CURDATE(), '09:00:00', 'Consultation', 'Completed'),
('A002', 'P001', 'D001', NULL, CURDATE(), '10:00:00', 'Follow-up', 'Scheduled'),
('A003', 'P001', 'D001', NULL, CURDATE(), '11:00:00', 'Check-up', 'Scheduled'),
('A004', 'P001', 'D001', NULL, CURDATE(), '14:00:00', 'Consultation', 'Scheduled'),
('A005', 'P001', 'D001', NULL, CURDATE(), '15:30:00', 'Follow-up', 'Scheduled'),
('A006', 'P002', 'D001', NULL, CURDATE(), '09:30:00', 'Bacterial Sinusitis', 'Completed'),
('A007', 'P003', 'D001', NULL, CURDATE(), '10:45:00', 'Diabetes Follow-up', 'Completed'),
('A008', 'P004', 'D001', NULL, CURDATE(), '11:30:00', 'Asthma Check-up', 'Completed'),
('A009', 'P005', 'D001', NULL, CURDATE(), '14:15:00', 'Acid Reflux Consultation', 'Completed'),
('A010', 'P001', 'D001', NULL, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'General Consultation', 'Completed');

-- 7. Doctor Leaves Mock
INSERT INTO `doctor_leaves` (`leave_id`, `doctor_id`, `start_date`, `end_date`, `reason`, `status`) VALUES
('DL001', 'D001', '2026-07-10', '2026-07-12', 'Annual leave', 'Pending');

-- 8. Medical Records Mock
INSERT INTO `medical_records` (`medical_record_id`, `patient_id`, `doctor_id`, `appointment_id`, `diagnosis`, `symptoms`, `notes`, `follow_up_date`) VALUES
('MR001', 'P001', 'D001', 'A001', 'Hypertension stage 1', 'Occasional headache, high BP', 'Advised lower sodium diet and daily walking.', '2026-07-20'),
('MR002', 'P002', 'D001', 'A006', 'Acute Bacterial Sinusitis', 'Severe nasal congestion, facial pressure and headache', 'Prescribed antibiotics and pain reliever.', '2026-09-10'),
('MR003', 'P003', 'D001', 'A007', 'Type 2 Diabetes Mellitus', 'Fasting blood sugar 145 mg/dL', 'Adjusted Metformin dosage. Monitor diet.', '2026-09-15'),
('MR004', 'P004', 'D001', 'A008', 'Asthma Exacerbation', 'Wheezing, shortness of breath on exertion', 'Prescribed inhaler for quick relief.', '2026-09-05'),
('MR005', 'P005', 'D001', 'A009', 'Gastroesophageal Reflux Disease (GERD)', 'Frequent heartburn and epigastric discomfort', 'Avoid spicy food and take PPI before meals.', '2026-09-20'),
('MR006', 'P001', 'D001', 'A010', 'Allergic Rhinitis', 'Sneezing, watery eyes, runny nose', 'Antihistamines prescribed.', NULL),
('MR007', 'P003', 'D001', 'A011', 'Diabetic Peripheral Neuropathy', 'Burning sensation and numbness in feet', 'Prescribed Lyrica and pain reliever.', '2026-09-25'),
('MR008', 'P004', 'D001', 'A012', 'Refractory GERD', 'Persistent heartburn resistant to OTC antacids', 'Prescribed Nexium 40mg daily.', '2026-10-01'),
('MR009', 'P005', 'D001', 'A013', 'Dermatophytosis', 'Itchy localized skin rash', 'Prescribed oral antifungal.', '2026-09-18'),
('MR010', 'P009', 'D001', 'A014', 'Type 2 Diabetes & Hyperlipidemia', 'Elevated HbA1c and LDL cholesterol', 'Prescribed Januvia and Crestor.', '2026-10-15'),
('MR011', 'P010', 'D001', 'A015', 'Acute Knee Tendonitis', 'Swelling and sharp knee pain after sports', 'Apply topical gel and rest leg.', '2026-09-12');

-- 9. Medicines Mock
INSERT INTO `medicines` (`medicine_id`, `brand_name`, `generic_name`, `dosage`, `category`, `unit_type`, `manufacturer`, `stock_quantity`, `minimum_stock`, `unit_price`, `expiry_date`, `description`) VALUES
('M001', 'Lipitor', 'Atorvastatin', '20mg', 'Cardiovascular', 'Tablet', 'Pfizer', 100, 20, 1.50, '2027-12-31', 'Statin for lowering cholesterol'),
('M002', 'Amoxil', 'Amoxicillin', '500mg', 'Antibiotic', 'Capsule', 'GSK', 250, 50, 0.80, '2027-06-30', 'Penicillin antibiotic'),
('M003', 'Panadol Extra', 'Paracetamol', '500mg', 'Analgesic', 'Tablet', 'GSK', 12, 30, 0.50, '2028-01-15', 'Relief of pain and fever'),
('M004', 'Ventolin Evohaler', 'Salbutamol', '100mcg', 'Respiratory', 'Inhaler', 'GSK', 0, 10, 12.50, '2027-09-20', 'Fast-acting bronchodilator inhaler'),
('M005', 'Glucophage', 'Metformin', '500mg', 'Antidiabetic', 'Tablet', 'Merck', 180, 30, 0.65, '2027-11-30', 'First-line medication for type 2 diabetes'),
('M006', 'Losec', 'Omeprazole', '20mg', 'Gastrointestinal', 'Capsule', 'AstraZeneca', 8, 15, 1.20, '2027-08-10', 'Proton-pump inhibitor for acid reflux'),
('M007', 'Augmentin', 'Amoxicillin/Clavulanate', '625mg', 'Antibiotic', 'Tablet', 'GSK', 120, 25, 2.40, '2027-05-25', 'Broad-spectrum antibiotic formulation'),
('M008', 'Zyrtec', 'Cetirizine', '10mg', 'Antihistamine', 'Tablet', 'Johnson & Johnson', 90, 20, 0.75, '2028-03-31', 'Non-drowsy antihistamine for allergies'),
('M009', 'Nurofen', 'Ibuprofen', '400mg', 'Analgesic', 'Tablet', 'Reckitt', 5, 20, 0.90, '2027-10-15', 'Nonsteroidal anti-inflammatory drug'),
('M010', 'Crestor', 'Rosuvastatin', '10mg', 'Cardiovascular', 'Tablet', 'AstraZeneca', 60, 15, 2.10, '2028-02-28', 'Potent statin medication for lipid control'),
('M011', 'Nexium', 'Esomeprazole', '40mg', 'Gastrointestinal', 'Capsule', 'AstraZeneca', 150, 25, 2.10, '2028-04-30', 'Proton pump inhibitor for severe heartburn'),
('M012', 'Lyrica', 'Pregabalin', '75mg', 'Neurology', 'Capsule', 'Pfizer', 45, 15, 3.20, '2027-11-15', 'For neuropathic pain and nerve damage'),
('M013', 'Diflucan', 'Fluconazole', '150mg', 'Antifungal', 'Tablet', 'Pfizer', 0, 10, 5.50, '2027-09-30', 'Antifungal medication'),
('M014', 'Symbicort Turbuhaler', 'Budesonide/Formoterol', '160/4.5mcg', 'Respiratory', 'Inhaler', 'AstraZeneca', 22, 10, 24.00, '2028-01-20', 'Maintenance asthma and COPD treatment'),
('M015', 'Januvia', 'Sitagliptin', '100mg', 'Antidiabetic', 'Tablet', 'MSD', 110, 20, 2.80, '2028-06-15', 'DPP-4 inhibitor for type 2 diabetes'),
('M016', 'Voltaren Emulgel', 'Diclofenac Sodium', '1%', 'Dermatology', 'Cream', 'GSK', 8, 15, 6.50, '2027-08-31', 'Topical NSAID gel for joint and muscle pain'),
('M017', 'Xalatan', 'Latanoprost', '0.005%', 'Ophthalmology', 'Drop', 'Pfizer', 14, 10, 18.50, '2027-12-10', 'Eye drops for glaucoma and intraocular pressure'),
('M018', 'Xanax', 'Alprazolam', '0.5mg', 'Psychiatric', 'Tablet', 'Viatris', 35, 10, 1.10, '2028-03-15', 'Short-acting anxiolytic medication');

-- 10. Prescriptions Mock
INSERT INTO `prescriptions` (`prescription_id`, `record_id`, `medicine_id`, `dosage`, `frequency`, `duration`, `instructions`, `quantity`, `is_dispensed`, `dispensed_at`, `dispensed_by`, `dispense_notes`) VALUES
('PR001', 'MR001', 'M001', '20mg', 'Once daily', '30 Days', 'Take in evening', 30, 1, NOW(), 'PH001', 'Take 1 tablet every evening after dinner.'),
('PR002', 'MR002', 'M007', '625mg', 'Twice daily', '7 Days', 'Take 1 tablet after food twice daily', 14, 0, NULL, NULL, NULL),
('PR003', 'MR002', 'M003', '500mg', 'Every 6 hours', '5 Days', 'Take 2 tablets for severe pain/fever as needed', 20, 0, NULL, NULL, NULL),
('PR004', 'MR003', 'M005', '500mg', 'Twice daily', '30 Days', 'Take 1 tablet twice daily with meals', 60, 0, NULL, NULL, NULL),
('PR005', 'MR004', 'M004', '100mcg', 'As needed', '30 Days', 'Inhale 2 puffs for breathlessness', 1, 0, NULL, NULL, NULL),
('PR006', 'MR005', 'M006', '20mg', 'Once daily', '30 Days', 'Take 1 capsule 30 min before breakfast', 30, 0, NULL, NULL, NULL),
('PR007', 'MR006', 'M008', '10mg', 'Once daily', '14 Days', 'Take 1 tablet daily at bedtime', 14, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'PH001', 'May cause mild drowsiness.'),
('PR008', 'MR006', 'M010', '10mg', 'Once daily', '30 Days', 'Take 1 tablet in evening', 30, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'PH001', 'Regular lipid profile check advised.'),
('PR009', 'MR007', 'M012', '75mg', 'Twice daily', '30 Days', 'Take 1 capsule morning and night', 60, 0, NULL, NULL, NULL),
('PR010', 'MR007', 'M003', '500mg', 'Every 6 hours', '5 Days', 'Take 2 tablets for nerve pain flare-ups', 20, 0, NULL, NULL, NULL),
('PR011', 'MR008', 'M011', '40mg', 'Once daily', '30 Days', 'Take 1 capsule 30 min before meal', 30, 0, NULL, NULL, NULL),
('PR012', 'MR008', 'M006', '20mg', 'Once daily', '14 Days', 'Take 1 capsule before dinner', 14, 0, NULL, NULL, NULL),
('PR013', 'MR009', 'M013', '150mg', 'Single dose', '1 Day', 'Take 1 tablet after food', 1, 0, NULL, NULL, NULL),
('PR014', 'MR010', 'M015', '100mg', 'Once daily', '30 Days', 'Take 1 tablet daily in the morning', 30, 0, NULL, NULL, NULL),
('PR015', 'MR010', 'M010', '10mg', 'Once daily', '30 Days', 'Take 1 tablet at night for cholesterol', 30, 0, NULL, NULL, NULL),
('PR016', 'MR011', 'M016', '1%', 'Three times daily', '14 Days', 'Apply gel gently to affected knee joint', 1, 0, NULL, NULL, NULL),
('PR017', 'MR011', 'M009', '400mg', 'Every 8 hours', '7 Days', 'Take 1 tablet after food for inflammation', 21, 0, NULL, NULL, NULL);


-- 11. Payments Mock
INSERT INTO `payments` (`payment_id`, `appointment_id`, `patient_id`, `amount`, `payment_method`, `payment_status`, `payment_date`, `invoice_no`) VALUES
('PA001', 'A001', 'P001', 50.00, 'Cash', 'Paid', '2026-07-06 09:15:00', 'INV-2026-0001');

-- 12. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('hospital_name', 'Central Medical Hospital'),
('hospital_code', 'CMH-2024'),
('phone_number', '+1 234-567-8900'),
('email_address', 'info@centralhospital.com'),
('address', '123 Healthcare Avenue, Medical District, NY 10001'),
('theme_mode', 'Light'),
('primary_color', '#2563eb');




