<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/Patient.php';

$error = '';
$success = false;
$activation_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $full_name = trim($_POST['full_name'] ?? '');
    $ic = trim($_POST['ic'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? 'Male';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: null;
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '') ?: null;
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '') ?: null;
    
    if (empty($email) || empty($password) || empty($confirm_password) || empty($full_name) || empty($ic) || empty($date_of_birth) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Keep username exactly as full name (including spaces)
        $username = $full_name;
        
        // Check if username already exists via ORM and append a suffix if it does
        if (User::count('username', $username) > 0) {
            $username .= ' ' . rand(100, 999);
        }

        // Check if email already exists via ORM
        if (User::count('email', $email) > 0) {
            $error = 'Email is already registered.';
        } else {
            $pdo->beginTransaction();
            try {
                // Generate next User ID (uniqueness check via ORM)
                $count_user = User::count();
                $new_user_id = 'U' . str_pad($count_user + 1, 3, '0', STR_PAD_LEFT);
                while (User::count('user_id', $new_user_id) > 0) {
                    $count_user++;
                    $new_user_id = 'U' . str_pad($count_user + 1, 3, '0', STR_PAD_LEFT);
                }
                
                // Generate next Patient ID (uniqueness check via ORM)
                $count_patient = Patient::count();
                $new_patient_id = 'P' . str_pad($count_patient + 1, 3, '0', STR_PAD_LEFT);
                while (Patient::count('patient_id', $new_patient_id) > 0) {
                    $count_patient++;
                    $new_patient_id = 'P' . str_pad($count_patient + 1, 3, '0', STR_PAD_LEFT);
                }
                
                // 1. Insert into users table via ORM (is_active = 1 for immediate access)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $newUser = new User([
                    'user_id'   => $new_user_id,
                    'username'  => $username,
                    'email'     => $email,
                    'password'  => $hashed_password,
                    'role'      => 'patient',
                    'is_active' => 1,
                ], false);
                $newUser->save();
                
                // 2. Insert into patients table via ORM (blood_type is omitted, setting to null)
                $newPatient = new Patient([
                    'patient_id'              => $new_patient_id,
                    'user_id'                 => $new_user_id,
                    'ic'                      => $ic,
                    'full_name'               => $full_name,
                    'date_of_birth'           => $date_of_birth,
                    'gender'                  => $gender,
                    'phone'                   => $phone,
                    'blood_type'              => null,
                    'address'                 => $address,
                    'emergency_contact_name'  => $emergency_contact_name,
                    'emergency_contact_phone' => $emergency_contact_phone,
                ], false);
                $newPatient->save();
                
                $pdo->commit();
                
                // Redirect immediately to login with success message
                header("Location: login.php?success=" . urlencode("Account created successfully! You can now sign in with your email and password."));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error during registration: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - MediCare</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Login/style.css">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .login-right {
        overflow-y: auto !important;
        justify-content: flex-start !important;
        padding-top: 40px;
        padding-bottom: 40px;
    }
    .login-right-content {
        max-width: 520px !important;
        margin: auto !important;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 12px;
    }
    @media (max-width: 576px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
    }
    </style>
</head>
<body style="background-color: var(--white); overflow-x: hidden;">

<div class="login-container">
    <!-- Left Section: Branding & Slogans -->
    <div class="login-left">
        <div>
            <div class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div>
                    <div>MediCare</div>
                    <div class="brand-subtitle">Health Management</div>
                </div>
            </div>
            
            <div class="login-left-content" style="margin-top: 40px;">
                <h1>Patient Portal.</h1>
                <p class="subtitle">Join MediCare to schedule appointments, consult with top doctors, manage medical records, and track treatments online.</p>
                
                <ul class="value-props">
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <span>Easy online appointment booking</span>
                    </li>
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>
                        <span>Direct access to medical history & notes</span>
                    </li>
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <span>Secure, compliant health data storage</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="login-left-footer">
            <p>Ready to manage your health securely</p>
        </div>
    </div>
    
    <!-- Right Section: Registration Form -->
    <div class="login-right">
        <div class="login-right-content">
            
            <?php if ($success): ?>
                <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 24px; color: #065f46;">
                    <div style="font-size: 40px; color: #10b981; margin-bottom: 12px; text-align: center;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 style="font-size: 20px; font-weight: 700; color: #065f46; text-align: center; margin-bottom: 8px;">Registration Successful!</h2>
                    <p style="font-size: 14px; line-height: 1.6; color: #047857; text-align: center; margin-bottom: 20px; font-weight: 500;">
                        Your account has been created. Please check your email and click the link to activate your account.<br>
                    </p>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" style="font-size: 13px; font-weight: 600; color: #065f46; text-decoration: underline;">Return to Login</a>
                    </div>
                </div>
            <?php else: ?>
                
                <h2>Create account</h2>
                <p class="form-desc" style="margin-bottom: 20px;">Fill in your details to register as a new patient</p>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 500;">
                        <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form id="registerForm" action="patientRegister.php" method="POST">
                    
                    <!-- Full Name & Email -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Full Name <span style="color:red;">*</span></label>
                            <input type="text" name="full_name" class="input-control" required style="padding-left: 14px;" placeholder="e.g. John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Email Address <span style="color:red;">*</span></label>
                            <input type="email" name="email" class="input-control" required style="padding-left: 14px;" placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Password & Confirm Password -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Password <span style="color:red;">*</span></label>
                            <input type="password" name="password" class="input-control" required style="padding-left: 14px;" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Confirm Password <span style="color:red;">*</span></label>
                            <input type="password" name="confirm_password" class="input-control" required style="padding-left: 14px;" placeholder="••••••••">
                        </div>
                    </div>

                    <!-- IC & Date of Birth -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">IC Number <span style="color:red;">*</span></label>
                            <input type="text" name="ic" class="input-control" required style="padding-left: 14px;" placeholder="e.g. 111111-33-3333" oninput="formatIC(this)" value="<?= htmlspecialchars($_POST['ic'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Date of Birth <span style="color:red;">*</span></label>
                            <input type="date" name="date_of_birth" class="input-control" required style="padding-left: 14px;" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Gender & Phone -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Gender <span style="color:red;">*</span></label>
                            <select name="gender" class="input-control" required style="padding-left: 14px; height: 46px;">
                                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Phone Number <span style="color:red;">*</span></label>
                            <input type="text" name="phone" class="input-control" required style="padding-left: 14px;" placeholder="e.g. 012-3456789" oninput="formatPhone(this)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Emergency Contact Name & Emergency Contact Phone -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="input-control" style="padding-left: 14px;" placeholder="e.g. Spouse or Parent" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="input-control" style="padding-left: 14px;" placeholder="e.g. 012-9876543" oninput="formatPhone(this)" value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Address (Full Width Textarea) -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="display:block; margin-bottom: 4px; font-size: 13px; font-weight: 600;">Address</label>
                        <textarea name="address" class="input-control" style="padding-left: 14px; height: 80px; resize: none;" placeholder="e.g. 123 Main St, KL"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="margin-top: 10px;">
                        Create Account <i class="fa-solid fa-user-plus" style="font-size: 12px; margin-left: 4px;"></i>
                    </button>
                </form>
                
                <div class="login-signup-link" style="margin-top: 20px;">
                    Already have an account? <a href="login.php">Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function formatIC(input) {
    // Remove all non-digits
    let val = input.value.replace(/\D/g, '');
    
    // Limit to 12 digits
    if (val.length > 12) {
        val = val.substring(0, 12);
    }
    
    // Format to XXXXXX-XX-XXXX
    let formatted = '';
    if (val.length > 0) {
        formatted += val.substring(0, 6);
    }
    if (val.length > 6) {
        formatted += '-' + val.substring(6, 8);
    }
    if (val.length > 8) {
        formatted += '-' + val.substring(8, 12);
    }
    
    input.value = formatted;
}

function formatPhone(input) {
    // Remove all non-digits
    let val = input.value.replace(/\D/g, '');
    
    // Limit to 11 digits (3 prefix + 8 suffix)
    if (val.length > 11) {
        val = val.substring(0, 11);
    }
    
    // Format to XXX-XXXXXXX or XXX-XXXXXXXX
    let formatted = '';
    if (val.length > 0) {
        formatted += val.substring(0, 3);
    }
    if (val.length > 3) {
        formatted += '-' + val.substring(3, 11);
    }
    
    input.value = formatted;
}
</script>

</body>
</html>
