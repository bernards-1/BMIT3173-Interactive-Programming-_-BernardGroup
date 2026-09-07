<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/Pharmacy.php';
require_once '../../Models/Pharmacist.php';
require_once '../../Models/Prescription.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as pharmacist
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
    header('Location: ../Login/login.php');
    exit;
}

// Helper to escape output safely
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$id = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'];

// Fetch pharmacist details via ORM (username/email come from the object reference)
function loadPharmacistProfileArray($id) {
    $pharmacistMatches = Pharmacist::where('user_id', $id);
    $pharmacistModel = $pharmacistMatches[0] ?? null;
    if (!$pharmacistModel) {
        return null;
    }
    $data = $pharmacistModel->toArray();
    $user = $pharmacistModel->getUser();
    $data['username'] = $user->username ?? null;
    $data['email'] = $user->email ?? null;
    return $data;
}

$pharmacist = loadPharmacistProfileArray($id);

if (!$pharmacist) {
    die("Pharmacist profile not found.");
}

$pharmacist_id = $pharmacist['pharmacist_id'];

// Handle POST submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $ic             = trim($_POST['ic'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $qualification  = trim($_POST['qualification'] ?? '');
        
        if (empty($full_name) || empty($email) || empty($phone) || empty($license_number) || empty($qualification)) {
            $error = 'Please fill in all required fields.';
        } else {
            $pdo->beginTransaction();
            try {
                $username = $full_name;
                
                // Check if username already exists for another user
                if (User::usernameTakenByOther($username, $id)) {
                    $username .= ' ' . rand(100, 999);
                }

                // Check email uniqueness
                if (User::emailTakenByOther($email, $id)) {
                    throw new Exception('Email address is already in use by another account.');
                }

                // Update users table via ORM
                $userAccount = User::find($id);
                $userAccount->email = $email;
                $userAccount->username = $username;
                $userAccount->save();
                
                // Update pharmacists table via ORM
                $pharmacistModel = Pharmacist::find($pharmacist_id);
                $pharmacistModel->full_name = $full_name;
                $pharmacistModel->ic = $ic;
                $pharmacistModel->phone = $phone;
                $pharmacistModel->license_number = $license_number;
                $pharmacistModel->qualification = $qualification;
                $pharmacistModel->save();
                
                $pdo->commit();
                
                // Update Session values
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['username'] = $username;
                
                // Refresh data via ORM
                $pharmacist = loadPharmacistProfileArray($id);
                
                $success = 'Pharmacist profile details updated successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd     = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        
        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $error = 'New password and confirm password do not match.';
        } elseif (strlen($new_pwd) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            // Fetch current password hash via ORM
            $userAccount = User::find($id);
            $hash = $userAccount ? $userAccount->password : null;
            
            if ($hash && password_verify($current_pwd, $hash)) {
                $userAccount->password = password_hash($new_pwd, PASSWORD_DEFAULT);
                $userAccount->save();
                $success = 'Password changed successfully!';
            } else {
                $error = 'Incorrect current password.';
            }
        }
    }
}

// Fetch stats for side card
$dispensedCount = Pharmacy::countDispensedToday();

// Single-column aggregate via ORM helper
$totalDispensedAllTime = Prescription::countDistinct('record_id', 'is_dispensed', 1);

$pendingCount = Pharmacy::countPendingPrescriptions();

$todayRevenue = Pharmacy::getTodayRevenue();

$initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $pharmacist['full_name']), 0, 2));
if (empty($initials)) {
    $initials = 'PH';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediCare Pharmacy</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Pharmacy/style.css?v=<?= time() ?>">
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
            box-shadow: var(--shadow-sm);
        }
        .profile-main-card {
            background-color: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }
        .pharmacist-profile-avatar {
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
            background-color: var(--teal);
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
        }
        .profile-tag {
            background-color: var(--teal-light);
            color: var(--teal);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            text-transform: uppercase;
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
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: var(--slate-400);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-button-profile.active {
            border-bottom-color: var(--teal);
            color: var(--teal);
        }
        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            .form-grid-profile {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Pharmacy/navigation.php'; ?>

<!-- Dashboard Core Container -->
<div class="dashboard-container">
    
    <!-- Welcome Header -->
    <div>
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">My Profile</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">Manage your personal credentials, pharmacy license details, and account security</p>
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
            <div class="pharmacist-profile-avatar">
                <?= e($initials) ?>
            </div>
            <h3 style="font-size: 18px; font-weight: 700; color: var(--slate-900);"><?= e($pharmacist['full_name']) ?></h3>
            <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;"><?= e($pharmacist['qualification'] ?: 'Licensed Pharmacist') ?></p>
            <span class="profile-tag"><i class="fa-solid fa-id-card" style="margin-right: 4px;"></i> <?= e($pharmacist_id) ?></span>
            
            <div class="stat-mini-row">
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $totalDispensedAllTime ?></div>
                    <div class="stat-mini-lbl">Dispensed</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val"><?= $pendingCount ?></div>
                    <div class="stat-mini-lbl">Pending</div>
                </div>
                <div class="stat-mini-item">
                    <div class="stat-mini-val">$<?= number_format($todayRevenue, 0) ?></div>
                    <div class="stat-mini-lbl">Today Rev</div>
                </div>
            </div>
        </div>
        
        <!-- Right Main Form Column -->
        <div class="profile-main-card">
            <div style="display: flex; gap: 16px; border-bottom: 1px solid var(--slate-100); margin-bottom: 24px;">
                <button class="tab-button-profile active" onclick="switchProfileTab('edit-details', this)">
                    <i class="fa-regular fa-user" style="margin-right: 6px;"></i> Edit Details
                </button>
                <button class="tab-button-profile" onclick="switchProfileTab('security', this)">
                    <i class="fa-solid fa-shield-halved" style="margin-right: 6px;"></i> Security & Password
                </button>
            </div>
            
            <!-- Tab 1: Edit Details Form -->
            <div id="tab-edit-details" class="profile-tab-content">
                <form action="pharmacistProfile.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Full Name</label>
                            <input type="text" name="full_name" class="search-input-control" required value="<?= e($pharmacist['full_name']) ?>" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Email Address</label>
                            <input type="email" name="email" class="search-input-control" required value="<?= e($pharmacist['email']) ?>" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                    </div>
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">IC Number</label>
                            <input type="text" name="ic" class="search-input-control" placeholder="e.g. 900512-14-5231" value="<?= e($pharmacist['ic'] ?? '') ?>" oninput="formatIC(this)" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Phone Number</label>
                            <input type="text" name="phone" class="search-input-control" required value="<?= e($pharmacist['phone'] ?? '') ?>" oninput="formatPhone(this)" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                    </div>
                    
                    <div class="form-grid-profile">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">License Number</label>
                            <input type="text" name="license_number" class="search-input-control" required value="<?= e($pharmacist['license_number'] ?? '') ?>" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Qualification</label>
                            <input type="text" name="qualification" class="search-input-control" required value="<?= e($pharmacist['qualification'] ?? '') ?>" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-dispense" style="background-color: var(--teal); color: white; border: none; font-size: 14px; font-weight: 600; padding: 10px 22px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Details
                    </button>
                </form>
            </div>
            
            <!-- Tab 2: Security & Password -->
            <div id="tab-security" class="profile-tab-content" style="display: none;">
                <form action="pharmacistProfile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group" style="margin-bottom: 20px; max-width: 450px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Current Password</label>
                        <input type="password" name="current_password" class="search-input-control" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px; max-width: 450px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">New Password</label>
                        <input type="password" name="new_password" class="search-input-control" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px; max-width: 450px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px; color: var(--slate-700);">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="search-input-control" required placeholder="••••••••" style="background-color: var(--white); color: var(--slate-900); padding-left: 14px;">
                    </div>
                    
                    <button type="submit" class="btn-dispense" style="background-color: var(--teal); color: white; border: none; font-size: 14px; font-weight: 600; padding: 10px 22px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </form>
            </div>
            
        </div>
        
    </div>
</div>

<script>
function switchProfileTab(tabName, btn) {
    // Hide all tab contents
    document.querySelectorAll(".profile-tab-content").forEach(content => {
        content.style.display = "none";
    });
    
    // Show selected tab content
    document.getElementById("tab-" + tabName).style.display = "block";
    
    // Toggle active state on tab buttons
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
