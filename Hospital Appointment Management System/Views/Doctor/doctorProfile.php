<?php
require_once '../../db.php';
require_once '../../Models/User.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as doctor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header('Location: ../Login/login.php');
    exit;
}

// Helper to escape output
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = $_SESSION['user_id'];

// Fetch doctor details
$doctor_stmt = $pdo->prepare('SELECT * FROM doctors WHERE user_id = ?');
$doctor_stmt->execute([$id]);
$doctor = $doctor_stmt->fetch();

if (!$doctor) {
    die("Doctor profile not found.");
}

$doctor_id = $doctor['doctor_id'];

// Handle POST submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $consultation_fee = floatval($_POST['consultation_fee'] ?? 50.00);
        $initials = trim($_POST['initials'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $ic = trim($_POST['ic'] ?? '');
        
        if (empty($name) || empty($specialization) || empty($qualification) || empty($phone) || empty($email) || empty($initials) || empty($color)) {
            $error = 'Please fill in all required fields.';
        } else {
            $pdo->beginTransaction();
            try {
                // Keep username exactly as name (including spaces)
                $username = $name;
                
                // Check if username already exists for other users
                $chk_user = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
                $chk_user->execute([$username, $id]);
                if ($chk_user->fetchColumn() > 0) {
                    $username .= ' ' . rand(100, 999);
                }

                // Update users table (email and username)
                $upd_user = $pdo->prepare("UPDATE users SET email = ?, username = ? WHERE user_id = ?");
                $upd_user->execute([$email, $username, $id]);
                
                // Update doctors table
                $upd_doc = $pdo->prepare("
                    UPDATE doctors 
                    SET name = ?, specialization = ?, qualification = ?, phone = ?, email = ?, consultation_fee = ?, initials = ?, color = ?, ic = ?
                    WHERE doctor_id = ?
                ");
                $upd_doc->execute([$name, $specialization, $qualification, $phone, $email, $consultation_fee, $initials, $color, $ic, $doctor_id]);
                
                $pdo->commit();
                
                // Update Session values
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['username'] = $username;
                
                // Refresh data
                $doctor_stmt->execute([$id]);
                $doctor = $doctor_stmt->fetch();
                
                $success = 'Profile details updated successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
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
            $user_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $user_stmt->execute([$id]);
            $hash = $user_stmt->fetchColumn();
            
            if ($hash && password_verify($current_pwd, $hash)) {
                $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $upd_pwd = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $upd_pwd->execute([$new_hash, $id]);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Incorrect current password.';
            }
        }
    }
}

// Fetch some statistics for doctor
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_records WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_records = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediCare Doctor</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Doctor/style.css?v=<?= time() ?>">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            gap: 24px;
            margin-top: 24px;
        }
        .profile-sidebar-card {
            background-color: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px 24px;
            text-align: center;
            height: fit-content;
        }
        .profile-main-card {
            background-color: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
        }
        .doctor-profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .profile-tag {
            background-color: var(--slate-100);
            color: var(--slate-700);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 8px;
            text-transform: uppercase;
        }
        .stat-mini-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            border-top: 1px solid var(--slate-100);
            margin-top: 24px;
            padding-top: 20px;
        }
        .stat-mini-item {
            text-align: center;
        }
        .stat-mini-val {
            font-size: 18px;
            font-weight: 700;
            color: var(--slate-900);
        }
        .stat-mini-lbl {
            font-size: 10px;
            color: var(--slate-400);
            margin-top: 2px;
            font-weight: 600;
        }
        .form-grid-profile {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .tab-button-profile {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--slate-400);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-button-profile.active {
            border-bottom-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Doctor/navigation.php'; ?>

<!-- Dashboard Core Container -->
<div class="dashboard-container">
    
    <!-- Welcome Header -->
    <div>
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">My Profile</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">Manage your personal credentials, consultation info, and security details</p>
    </div>

    <?php if (!empty($error)): ?>
        <div style="background-color: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-top: 20px; font-weight: 500;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-top: 20px; font-weight: 500;">
            <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>
    
    <!-- Profile Grid Layout -->
    <div class="profile-container">
        
        <!-- Left Sidebar Column -->
        <div class="profile-sidebar-card">
            <div class="doctor-profile-avatar" style="background-color: <?= e($doctor['color']) ?>;">
                <?= e(strtoupper($doctor['initials'])) ?>
            </div>
            <h3 style="font-size: 18px; font-weight: 700; color: var(--slate-900);"><?= e($doctor['name']) ?></h3>
            <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;"><?= e($doctor['specialization']) ?></p>
            <span class="profile-tag"><?= e($doctor_id) ?></span>
            
            <div class="stat-mini-row">
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_appointments ?></div>
                    <div class="stat-mini-lbl">Visits</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_patients ?></div>
                    <div class="stat-mini-lbl">Patients</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $total_records ?></div>
                    <div class="stat-mini-lbl">Records</div>
                </div>
            </div>
        </div>
        
        <!-- Right Main Form Column -->
        <div class="profile-main-card">
            <div style="display: flex; gap: 16px; border-bottom: 1px solid var(--slate-100); margin-bottom: 24px;">
                <button class="tab-button-profile active" onclick="switchProfileTab('edit-details', this)">Edit Details</button>
                <button class="tab-button-profile" onclick="switchProfileTab('security', this)">Security & Password</button>
            </div>
            
            <!-- Tab 1: Edit Details Form -->
            <div id="tab-edit-details" class="profile-tab-content">
                <form action="doctorProfile.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Full Name</label>
                            <input type="text" name="name" class="form-select" required value="<?= e($doctor['name']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Email Address</label>
                            <input type="email" name="email" class="form-select" required value="<?= e($doctor['email']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                    </div>
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">IC Number</label>
                            <input type="text" name="ic" class="form-select" placeholder="e.g. 880812-14-5543" value="<?= e($doctor['ic'] ?? '') ?>" oninput="formatIC(this)" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Phone Number</label>
                            <input type="text" name="phone" class="form-select" required value="<?= e($doctor['phone']) ?>" oninput="formatPhone(this)" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                    </div>
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Specialization</label>
                            <input type="text" name="specialization" class="form-select" required value="<?= e($doctor['specialization']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Qualifications</label>
                            <input type="text" name="qualification" class="form-select" required value="<?= e($doctor['qualification']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                    </div>
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Consultation Fee (RM)</label>
                            <input type="number" step="0.01" name="consultation_fee" class="form-select" required value="<?= e($doctor['consultation_fee']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                        </div>
                        <div class="form-group">
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
                                <div>
                                    <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Initials</label>
                                    <input type="text" name="initials" class="form-select" maxlength="3" required value="<?= e($doctor['initials']) ?>" style="background-color: var(--white); color: var(--slate-900);">
                                </div>
                                <div>
                                    <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Theme Color</label>
                                    <select name="color" class="form-select" required style="background-color: var(--white); color: var(--slate-900);">
                                        <option value="#3b82f6" <?= $doctor['color'] === '#3b82f6' ? 'selected' : '' ?>>Blue</option>
                                        <option value="#10b981" <?= $doctor['color'] === '#10b981' ? 'selected' : '' ?>>Emerald Green</option>
                                        <option value="#a855f7" <?= $doctor['color'] === '#a855f7' ? 'selected' : '' ?>>Purple</option>
                                        <option value="#f59e0b" <?= $doctor['color'] === '#f59e0b' ? 'selected' : '' ?>>Amber Orange</option>
                                        <option value="#ef4444" <?= $doctor['color'] === '#ef4444' ? 'selected' : '' ?>>Crimson Red</option>
                                        <option value="#06b6d4" <?= $doctor['color'] === '#06b6d4' ? 'selected' : '' ?>>Cyan Blue</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-row-action" style="background-color: var(--primary-blue); color: white; border: none; font-size: 14px; font-weight: 600; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Details
                    </button>
                </form>
            </div>
            
            <!-- Tab 2: Security & Password -->
            <div id="tab-security" class="profile-tab-content" style="display: none;">
                <form action="doctorProfile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group" style="margin-bottom: 20px; max-width: 400px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Current Password</label>
                        <input type="password" name="current_password" class="form-select" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900);">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px; max-width: 400px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">New Password</label>
                        <input type="password" name="new_password" class="form-select" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900);">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px; max-width: 400px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-select" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900);">
                    </div>
                    
                    <button type="submit" class="btn-row-action" style="background-color: var(--primary-blue); color: white; border: none; font-size: 14px; font-weight: 600; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </form>
            </div>
            
        </div>
        
    </div>
</div>

<script>
function switchProfileTab(tabName, btn) {
    // Hide all contents
    document.querySelectorAll(".profile-tab-content").forEach(content => {
        content.style.display = "none";
    });
    
    // Show target content
    document.getElementById("tab-" + tabName).style.display = "block";
    
    // Toggle active state on buttons
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
