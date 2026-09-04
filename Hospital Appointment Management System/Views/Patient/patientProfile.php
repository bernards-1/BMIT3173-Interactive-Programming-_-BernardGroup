<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/PatientRepository.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as patient
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../Login/login.php');
    exit;
}

// Helper to escape output
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$id = $_SESSION['user']['user_id'];

$patientRepository = new PatientRepository($pdo);

// Fetch patient details
$patient = $patientRepository->getPatientByUserId($id);

if (!$patient) {
    die("Patient profile not found.");
}

$patient_id = $patient['patient_id'];

// Handle POST submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $ic = trim($_POST['ic'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $blood_type = trim($_POST['blood_type'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');

        if (empty($full_name) || empty($date_of_birth) || empty($gender) || empty($phone) || empty($email)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $profileData = [
                    'full_name' => $full_name,
                    'ic' => $ic,
                    'date_of_birth' => $date_of_birth,
                    'gender' => $gender,
                    'phone' => $phone,
                    'email' => $email,
                    'blood_type' => $blood_type,
                    'address' => $address,
                    'emergency_contact_name' => $emergency_contact_name,
                    'emergency_contact_phone' => $emergency_contact_phone,
                ];
                $patientRepository->updatePatientProfile($id, $patient_id, $profileData);

                // Update session values
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['username'] = $full_name;

                // Refresh data
                $patient = $patientRepository->getPatientByUserId($id);

                $success = 'Profile details updated successfully!';
            } catch (Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $error = 'New password and confirm password do not match.';
        } else {
            // Fetch current password hash from users
            $hash = $patientRepository->getPasswordHash($id);

            if ($hash && password_verify($current_pwd, $hash)) {
                $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $patientRepository->updatePassword($id, $new_hash);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Incorrect current password.';
            }
        }
    }
}

// Fetch some statistics for patient
$total_appointments = 0;
$total_visits = 0;
$total_records = 0;

if ($patient_id) {
    $total_appointments = $patientRepository->countAppointments($patient_id);
    $total_visits = $patientRepository->countCompletedAppointments($patient_id);
    $total_records = $patientRepository->countMedicalRecords($patient_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediCare Patient</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Patient/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Patient/patient_profile.css?v=<?= time() ?>">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="patient-profile-page">

<!-- Include Navigation Header -->
<?php include '../Layout/Patient/navigation.php'; ?>

<!-- Dashboard Core Container -->
<div class="dashboard-container">

    <!-- Welcome Header -->
    <div>
        <h1 class="profile-page-title">My Profile</h1>
        <p class="profile-page-subtitle">Manage your personal details, contact info, and security settings</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="profile-alert profile-alert--error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="profile-alert profile-alert--success">
            <i class="fa-solid fa-circle-check"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Grid Layout -->
    <div class="profile-container">

        <!-- Left Sidebar Column -->
        <div class="profile-sidebar-card">
            <div class="patient-profile-avatar">
                <?= e(strtoupper(substr($patient['full_name'], 0, 1))) ?>
            </div>
            <h3 class="profile-sidebar-name"><?= e($patient['full_name']) ?></h3>
            <p class="profile-sidebar-email"><?= e($patient['user_email']) ?></p>
            <span class="profile-tag"><?= e($patient_id) ?></span>

            <div class="stat-mini-row">
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_appointments ?></div>
                    <div class="stat-mini-lbl">Appointments</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_visits ?></div>
                    <div class="stat-mini-lbl">Visits</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_records ?></div>
                    <div class="stat-mini-lbl">Records</div>
                </div>
            </div>
        </div>

        <!-- Right Main Form Column -->
        <div class="profile-main-card">
            <div class="profile-tab-nav">
                <button class="tab-button-profile active" onclick="switchProfileTab('edit-details', this)">Edit Details</button>
                <button class="tab-button-profile" onclick="switchProfileTab('security', this)">Security & Password</button>
            </div>

            <!-- Tab 1: Edit Details Form -->
            <div id="tab-edit-details" class="profile-tab-content">
                <form action="patientProfile.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-select" required value="<?= e($patient['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-select" required value="<?= e($patient['user_email']) ?>">
                        </div>
                    </div>

                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label">IC Number</label>
                            <input type="text" name="ic" class="form-select" placeholder="e.g. 880812-14-5543" value="<?= e($patient['ic'] ?? '') ?>" oninput="formatIC(this)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-select" required value="<?= e($patient['phone']) ?>" oninput="formatPhone(this)">
                        </div>
                    </div>

                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-select" required value="<?= e($patient['date_of_birth']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-select">
                                <?php $blood_types = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']; ?>
                                <?php foreach ($blood_types as $bt): ?>
                                    <option value="<?= e($bt) ?>" <?= ($patient['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt === '' ? 'Unknown' : e($bt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-select" value="<?= e($patient['address'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-select" value="<?= e($patient['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-select" value="<?= e($patient['emergency_contact_phone'] ?? '') ?>" oninput="formatPhone(this)">
                        </div>
                    </div>

                    <button type="submit" class="btn-row-action">
                        <i class="fa-solid fa-floppy-disk"></i> Save Details
                    </button>
                </form>
            </div>

            <!-- Tab 2: Security & Password -->
            <div id="tab-security" class="profile-tab-content is-hidden">
                <form action="patientProfile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group profile-form-group--compact">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-select" required placeholder="••••••••">
                    </div>

                    <div class="form-group profile-form-group--compact">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-select" required placeholder="••••••••">
                    </div>

                    <div class="form-group profile-form-group--compact">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-select" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn-row-action">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </form>
            </div>

        </div>

    </div>
</div>

<script>
function switchProfileTab(tabName, btn) {
    document.querySelectorAll(".profile-tab-content").forEach(content => {
        content.classList.add("is-hidden");
    });

    document.getElementById("tab-" + tabName).classList.remove("is-hidden");

    document.querySelectorAll(".tab-button-profile").forEach(b => {
        b.classList.remove("active");
    });
    btn.classList.add("active");
}

function formatIC(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 12) {
        val = val.substring(0, 12);
    }
    let formatted = '';
    if (val.length > 0) formatted += val.substring(0, 6);
    if (val.length > 6) formatted += '-' + val.substring(6, 8);
    if (val.length > 8) formatted += '-' + val.substring(8, 12);
    input.value = formatted;
}

function formatPhone(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 11) {
        val = val.substring(0, 11);
    }
    let formatted = '';
    if (val.length > 0) formatted += val.substring(0, 3);
    if (val.length > 3) formatted += '-' + val.substring(3, 11);
    input.value = formatted;
}
</script>

</body>
</html>