<?php
require_once '../../db.php';

$title = 'Account Activation - MediCare';
$message = '';
$is_success = false;

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$expected_token = md5($email . 'medicare_salt');

if (empty($email) || empty($token)) {
    $message = 'Missing activation parameters.';
} elseif ($token !== $expected_token) {
    $message = 'The activation link is invalid or has expired.';
} else {
    // Check if the user exists
    $stmt = $pdo->prepare("SELECT user_id, is_active FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $message = 'Associated user account was not found.';
    } else {
        // Activate user
        $upd = $pdo->prepare("UPDATE users SET is_active = 1 WHERE email = ?");
        $upd->execute([$email]);
        
        $is_success = true;
        $message = 'Your account has been successfully verified and activated! You can now log in to access your patient portal.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Login/style.css">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .activation-card {
        background-color: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 40px;
        max-width: 480px;
        width: 90%;
        margin: 100px auto;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        text-align: center;
    }
    .activation-icon {
        font-size: 56px;
        margin-bottom: 20px;
    }
    .activation-icon.success {
        color: #10b981;
    }
    .activation-icon.error {
        color: #ef4444;
    }
    .activation-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--slate-900);
        margin-bottom: 12px;
    }
    .activation-desc {
        font-size: 14px;
        color: var(--slate-600);
        line-height: 1.6;
        margin-bottom: 28px;
    }
    </style>
</head>
<body style="background-color: var(--slate-50); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;">

<div class="activation-card">
    <div class="brand" style="justify-content: center; margin-bottom: 30px;">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <div>
            <div style="font-size: 20px;">MediCare</div>
            <div class="brand-subtitle" style="font-size: 8px;">Health Management</div>
        </div>
    </div>
    
    <?php if ($is_success): ?>
        <div class="activation-icon success">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 class="activation-title">Activation Successful!</h2>
        <p class="activation-desc"><?= htmlspecialchars($message) ?></p>
        <a href="login.php" class="btn-primary" style="text-decoration: none; display: inline-flex;">
            Sign In to Account <i class="fa-solid fa-right-to-bracket" style="font-size: 12px; margin-left: 4px;"></i>
        </a>
    <?php else: ?>
        <div class="activation-icon error">
            <i class="fa-solid fa-circle-xmark"></i>
        </div>
        <h2 class="activation-title">Activation Failed</h2>
        <p class="activation-desc"><?= htmlspecialchars($message) ?></p>
        <a href="login.php" class="btn-primary" style="text-decoration: none; display: inline-flex; background-color: var(--slate-600);">
            Return to Login <i class="fa-solid fa-rotate-left" style="font-size: 12px; margin-left: 4px;"></i>
        </a>
    <?php endif; ?>
</div>

</body>
</html>
