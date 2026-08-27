<?php
require_once '../../db.php';
require_once '../../Controllers/LoginController.php';

$controller = new LoginController();
$error = $controller->handleLoginRequest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MediCare - Sign In</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Login/style.css">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--white); overflow: hidden;">

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
            
            <div class="login-left-content">
                <h1>Modern healthcare,<br>managed with care.</h1>
                <p class="subtitle">A unified platform for hospitals and clinics — connecting patients, doctors, and administrators in one seamless system.</p>
                
                <ul class="value-props">
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span>End-to-end patient management</span>
                    </li>
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <span>Real-time appointment scheduling</span>
                    </li>
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-solid fa-capsules"></i>
                        </div>
                        <span>Integrated pharmacy & billing</span>
                    </li>
                    <li class="value-prop-item">
                        <div class="value-prop-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span>Analytics and clinical reports</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="login-left-footer">
            <p>Trusted by 500+ hospitals worldwide</p>
            <div class="ratings">
                <div class="stars">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                </div>
                <span class="rating-text">4.9/5 rating</span>
            </div>
        </div>
    </div>
    
    <!-- Right Section: Login Form -->
    <div class="login-right">
        <div></div> <!-- Spacer -->
        
        <div class="login-right-content">
            <h2>Welcome back</h2>
            <p class="form-desc">Sign in to your account to continue</p>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 500;">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <form id="loginForm" action="login.php" method="POST">
                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label">Email address</label>
                    </div>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" class="input-control" placeholder="you@hospital.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label">Password</label>
                        <a href="#" class="form-link">Forgot password?</a>
                    </div>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" class="input-control input-control-pwd" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                            <i id="eyeIcon" class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    Sign In <i class="fa-solid fa-chevron-right" style="font-size: 12px; margin-left: 4px;"></i>
                </button>
            </form>
            
            <div class="login-signup-link">
                New patient? <a href="patientRegister.php">Create account</a>
            </div>
        </div>
        
        <div class="login-right-footer">
            &copy; 2026 MediCare HMS - All rights reserved
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('fa-regular', 'fa-eye');
        eyeIcon.classList.add('fa-regular', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('fa-regular', 'fa-eye-slash');
        eyeIcon.classList.add('fa-regular', 'fa-eye');
    }
}
</script>

</body>
</html>
