<?php
require_once '../../db.php';

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
        
        // Check if username already exists and append a suffix if it does
        $chk_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $chk_username->execute([$username]);
        if ($chk_username->fetchColumn() > 0) {
            $username .= ' ' . rand(100, 999);
        }

        // Check if email already exists
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Email is already registered.';
        } else {
            $pdo->beginTransaction();
            try {
                // Generate next User ID
                $count_user = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $new_user_id = 'U' . str_pad($count_user + 1, 3, '0', STR_PAD_LEFT);
                while (true) {
                    $check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id = ?');
                    $check_stmt->execute([$new_user_id]);
                    if ($check_stmt->fetchColumn() == 0) {
                        break;
                    }
                    $count_user++;
                    $new_user_id = 'U' . str_pad($count_user + 1, 3, '0', STR_PAD_LEFT);
                }
                
                // Generate next Patient ID
                $count_patient = $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
                $new_patient_id = 'P' . str_pad($count_patient + 1, 3, '0', STR_PAD_LEFT);
                while (true) {
                    $check_stmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE patient_id = ?');
                    $check_stmt->execute([$new_patient_id]);
                    if ($check_stmt->fetchColumn() == 0) {
                        break;
                    }
                    $count_patient++;
                    $new_patient_id = 'P' . str_pad($count_patient + 1, 3, '0', STR_PAD_LEFT);
                }
                
                // 1. Insert into users table (is_active = 0)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $ins_user = $pdo->prepare("
                    INSERT INTO users (user_id, username, email, password, role, is_active) 
                    VALUES (?, ?, ?, ?, 'patient', 0)
                ");
                $ins_user->execute([$new_user_id, $username, $email, $hashed_password]);
                
                // 2. Insert into patients table (blood_type is omitted, setting to null)
                $ins_patient = $pdo->prepare("
                    INSERT INTO patients (patient_id, user_id, ic, full_name, date_of_birth, gender, phone, blood_type, address, emergency_contact_name, emergency_contact_phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)
                ");
                $ins_patient->execute([$new_patient_id, $new_user_id, $ic, $full_name, $date_of_birth, $gender, $phone, $address, $emergency_contact_name, $emergency_contact_phone]);
                
                $pdo->commit();
                
                // Generate Activation Link
                $token = md5($email . 'medicare_salt');
                $host = $_SERVER['HTTP_HOST'];
                $script = $_SERVER['SCRIPT_NAME'];
                $base_dir = '/Hospital Appointment Management System';
                if (strpos($script, '/Hospital Appointment Management System') !== false) {
                    $base_dir = '/Hospital Appointment Management System';
                } else {
                    $parts = explode('/', trim($script, '/'));
                    if (!empty($parts)) {
                        $base_dir = '/' . $parts[0];
                    }
                }
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                $activation_link = $protocol . "://" . $host . $base_dir . "/Views/Login/activate.php?email=" . urlencode($email) . "&token=" . $token;
                
                // Send verification email using PHP mail()
                $subject = "Activate Your MediCare Account";
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: no-reply@medicare.com" . "\r\n";

                $body = "
                <html>
                <head>
                    <title>Activate Your MediCare Account</title>
                </head>
                <body style='font-family: Arial, sans-serif; background-color: #f1f5f9; padding: 20px; color: #1e293b;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                        <h2 style='color: #0052cc; margin-top: 0;'>Welcome to MediCare!</h2>
                        <p>Dear {$full_name},</p>
                        <p>Your account has been successfully created. Please click the link below to verify your email and activate your account:</p>
                        <p style='margin: 30px 0;'>
                            <a href='{$activation_link}' style='background-color: #0052cc; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Activate Account Now</a>
                        </p>
                        <p>If the button doesn't work, you can copy and paste the following URL into your browser:</p>
                        <p style='color: #64748b; font-size: 13px; word-break: break-all;'>{$activation_link}</p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                        <p style='font-size: 12px; color: #94a3b8;'>This is an automated email, please do not reply.</p>
                    </div>
                </body>
                </html>
                ";
                @mail($email, $subject, $body, $headers);

                $success = true;
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
