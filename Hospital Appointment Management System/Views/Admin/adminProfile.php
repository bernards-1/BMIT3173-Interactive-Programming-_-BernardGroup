<?php
require_once '../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../Login/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$profileStmt = $pdo->prepare('
    SELECT a.admin_id, a.full_name, a.phone, a.position, u.email, u.username
    FROM admins a
    JOIN users u ON u.user_id = a.user_id
    WHERE a.user_id = ?
');
$profileStmt->execute([$userId]);
$admin = $profileStmt->fetch();

if (!$admin) {
    http_response_code(404);
    exit('Admin profile not found.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a name and a valid email address.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE user_id = ?')->execute([$name, $email, $userId]);
                $pdo->prepare('UPDATE admins SET full_name = ?, phone = ?, position = ? WHERE user_id = ?')->execute([$name, $phone, $position, $userId]);
                $pdo->commit();
                $_SESSION['user']['username'] = $name;
                $_SESSION['user']['email'] = $email;
                $profileStmt->execute([$userId]);
                $admin = $profileStmt->fetch();
                $success = 'Profile details updated successfully!';
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'That name or email address is already in use.';
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Your new password must contain at least 8 characters.';
        } else {
            $passwordStmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ?');
            $passwordStmt->execute([$userId]);
            if (password_verify($currentPassword, (string) $passwordStmt->fetchColumn())) {
                $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Your current password is incorrect.';
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
    <title>My Profile - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .profile-layout{display:grid;grid-template-columns:minmax(250px,1fr) minmax(0,2.5fr);gap:24px;margin-top:24px}.profile-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:28px}.profile-summary{text-align:center;height:fit-content}.profile-avatar{width:90px;height:90px;margin:0 auto 16px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700}.profile-tag{display:inline-block;margin-top:10px;padding:4px 10px;border-radius:999px;background:var(--primary-light);color:var(--primary);font-size:11px;font-weight:700}.profile-tabs{display:flex;gap:12px;border-bottom:1px solid var(--border);margin-bottom:24px}.profile-tab{background:none;border:0;border-bottom:2px solid transparent;padding:8px 16px;color:var(--text-muted);font-size:14px;font-weight:600;cursor:pointer}.profile-tab.active{border-bottom-color:var(--primary);color:var(--primary)}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}.form-group{display:flex;flex-direction:column;gap:7px}.form-group.full{grid-column:1/-1}.form-group label{font-size:13px;font-weight:600;color:var(--text-main)}.form-group input{padding:10px 12px;border:1px solid var(--border);border-radius:8px;font:inherit;color:var(--text-main);background:var(--bg-card)}.form-group input:focus{outline:none;border-color:var(--primary)}.btn-primary{border:0;border-radius:8px;padding:10px 18px;background:var(--primary);color:white;font-weight:600;cursor:pointer}.alert{margin-top:20px;padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#d1fae5;color:#065f46}@media(max-width:800px){.profile-layout,.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:auto}}
    </style>
</head>
<body>
<?php include '../Layout/Admin/navigation.php'; ?>
<main class="dashboard-container">
    <h1 style="font-size:26px;margin-bottom:4px;">My Profile</h1>
    <p style="color:var(--text-muted);font-size:14px;">Manage your administrator details and account security.</p>
    <?php if ($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div><?php endif; ?>
    <div class="profile-layout">
        <aside class="profile-card profile-summary">
            <div class="profile-avatar"><?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?></div>
            <h2 style="font-size:18px;"> <?= e($admin['full_name']) ?></h2>
            <p style="color:var(--text-muted);font-size:13px;margin-top:5px;"> <?= e($admin['position'] ?: 'Administrator') ?></p>
            <span class="profile-tag"><?= e($admin['admin_id']) ?></span>
        </aside>
        <section class="profile-card">
            <div class="profile-tabs">
                <button type="button" class="profile-tab active" onclick="showProfileTab('details', this)">Edit Details</button>
                <button type="button" class="profile-tab" onclick="showProfileTab('security', this)">Security &amp; Password</button>
            </div>
            <div id="details-tab">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group"><label for="full_name">Full Name</label><input id="full_name" name="full_name" required value="<?= e($admin['full_name']) ?>"></div>
                        <div class="form-group"><label for="email">Email Address</label><input id="email" name="email" type="email" required value="<?= e($admin['email']) ?>"></div>
                        <div class="form-group"><label for="phone">Phone Number</label><input id="phone" name="phone" value="<?= e($admin['phone']) ?>"></div>
                        <div class="form-group"><label for="position">Position</label><input id="position" name="position" value="<?= e($admin['position']) ?>" placeholder="e.g. Hospital Administrator"></div>
                    </div>
                    <button class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Details</button>
                </form>
            </div>
            <div id="security-tab" style="display:none;">
                <form method="POST" style="max-width:420px;">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group" style="margin-bottom:18px;"><label for="current_password">Current Password</label><input id="current_password" name="current_password" type="password" required></div>
                    <div class="form-group" style="margin-bottom:18px;"><label for="new_password">New Password</label><input id="new_password" name="new_password" type="password" minlength="8" required></div>
                    <div class="form-group" style="margin-bottom:22px;"><label for="confirm_password">Confirm New Password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" required></div>
                    <button class="btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
                </form>
            </div>
        </section>
    </div>
</main>
<script>
function showProfileTab(tab, button) {
    document.getElementById('details-tab').style.display = tab === 'details' ? 'block' : 'none';
    document.getElementById('security-tab').style.display = tab === 'security' ? 'block' : 'none';
    document.querySelectorAll('.profile-tab').forEach(item => item.classList.remove('active'));
    button.classList.add('active');
}
</script>
</body>
</html>
