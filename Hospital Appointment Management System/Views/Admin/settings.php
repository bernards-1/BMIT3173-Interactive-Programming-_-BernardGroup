<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$settings = $controller->settings(); // Runs auth check and returns settings

function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-section { margin-bottom: 24px; } .header-section h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px; } .header-section p { font-size: 14px; color: #64748b; }
        .settings-tabs { display: flex; gap: 8px; margin-bottom: 24px; } .settings-tab-btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; color: #64748b; background: #f1f5f9; border: 1px solid #f1f5f9; } .settings-tab-btn.active { background: #fff; border-color: #e2e8f0; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .settings-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; overflow: auto; } .settings-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; } .settings-card-header i { font-size: 20px; color: #2563eb; } .settings-card-header h2 { font-size: 16px; font-weight: 600; color: #0f172a; } .settings-card-header p { font-size: 14px; color: #64748b; margin-top: 4px; }
        .form-grid, .email-config-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; } .form-group { display: flex; flex-direction: column; gap: 8px; } .full-width { grid-column: span 2; } .form-label { font-size: 14px; font-weight: 500; color: #0f172a; } .form-control, .select-control { padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; background: #fff; } .form-control:focus { border-color: #2563eb; }
        .btn-save { background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; float: right; margin-top: 24px; transition: background 0.3s; } .btn-save:hover { background: #1d4ed8; } .btn-save:disabled { opacity: 0.7; cursor: not-allowed; }
        .toggle-setting, .policy-item, .two-factor-auth { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #f1f5f9; } .toggle-info { display: flex; flex-direction: column; gap: 4px; } .toggle-info h3, .policy-item h3 { font-size: 14px; font-weight: 500; color: #0f172a; } .toggle-info p { font-size: 13px; color: #64748b; }
        .toggle-switch { position: relative; display: inline-block; width: 48px; height: 28px; } .toggle-switch input { opacity: 0; width: 0; height: 0; } .slider { position: absolute; cursor: pointer; inset: 0; background-color: #ccc; transition: .4s; border-radius: 28px; } .slider:before { position: absolute; content: ''; height: 20px; width: 20px; left: 4px; bottom: 4px; background: #fff; transition: .4s; border-radius: 50%; } input:checked + .slider { background: #2196f3; } input:checked + .slider:before { transform: translateX(20px); }
        .password-policy-grid { display: flex; flex-direction: column; gap: 16px; } .two-factor-auth { margin-top: 32px; padding-top: 24px; border-top: 1px solid #f1f5f9; } .color-palette { display: flex; gap: 12px; margin-top: 12px; } .color-circle { width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; } .color-circle.active { border-color: #2563eb; }
        .config-buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; } .btn-secondary { background: #fff; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; font-weight: 500; cursor: pointer; color: #0f172a; }
    </style>
</head>
<body>
<?php include '../Layout/Admin/navigation.php'; ?>
<div class="dashboard-container">
    <div class="header-section"><h1><?= __('System Settings') ?></h1><p><?= __('Manage your hospital management system configuration') ?></p></div>
    <div class="settings-tabs">
        <button class="settings-tab-btn" data-target="general-settings"><?= __('General') ?></button>
        <button class="settings-tab-btn" data-target="appearance-settings"><?= __('Appearance') ?></button>
    </div>
    
    <div id="general-settings" class="settings-section">
        <div class="settings-card">
            <div class="settings-card-header"><i class="fa-solid fa-hospital"></i><div><h2>Hospital Information</h2><p>Update your hospital's basic information</p></div></div>
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Hospital Name</label><input type="text" id="hospital_name" class="form-control" value="<?= getSetting('hospital_name', 'Central Medical Hospital') ?>"></div>
                <div class="form-group"><label class="form-label">Hospital Code</label><input type="text" id="hospital_code" class="form-control" value="<?= getSetting('hospital_code', 'CMH-2024') ?>"></div>
                <div class="form-group"><label class="form-label">Phone Number</label><input type="text" id="phone_number" class="form-control" value="<?= getSetting('phone_number', '+1 234-567-8900') ?>"></div>
                <div class="form-group"><label class="form-label">Email Address</label><input type="email" id="email_address" class="form-control" value="<?= getSetting('email_address', 'info@centralhospital.com') ?>"></div>
                <div class="form-group full-width"><label class="form-label">Address</label><input type="text" id="address" class="form-control" value="<?= getSetting('address', '123 Healthcare Avenue, Medical District, NY 10001') ?>"></div>
            </div>
            <button class="btn-save" onclick="saveSettings(['hospital_name', 'hospital_code', 'phone_number', 'email_address', 'address'], this)">Save Changes</button>
        </div>

    </div>

    
    <div id="appearance-settings" class="settings-section" style="display:none;">
        <div class="settings-card">
            <div class="settings-card-header"><i class="fa-solid fa-palette"></i><div><h2>Theme Settings</h2><p>Customize the look and feel of your system</p></div></div>
            <div class="form-group"><label class="form-label">Theme Mode</label>
                <select id="theme_mode" class="form-control">
                    <option <?= getSetting('theme_mode') == 'Light' ? 'selected' : '' ?>>Light</option>
                    <option <?= getSetting('theme_mode') == 'Dark' ? 'selected' : '' ?>>Dark</option>
                </select>
            </div>
            <div class="form-group" style="margin-top:20px;"><label class="form-label">Primary Color</label>
                <div class="color-palette">
                    <?php 
                    $selectedColor = getSetting('primary_color', '#2563eb');
                    foreach (['#2563eb','#10b981','#f59e0b','#a855f7','#ef4444'] as $color): ?>
                    <div class="color-circle <?= $selectedColor === $color ? 'active' : '' ?>" style="background:<?= $color ?>;" data-color="<?= $color ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn-save" onclick="saveAppearance(this)"><?= __('Apply Theme') ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.settings-tab-btn'), sections = document.querySelectorAll('.settings-section');
    buttons.forEach(button => button.addEventListener('click', () => { 
        buttons.forEach(btn => btn.classList.remove('active')); 
        button.classList.add('active'); 
        sections.forEach(section => section.style.display = 'none'); 
        document.getElementById(button.dataset.target).style.display = 'block'; 
    }));
    document.querySelector('.settings-tab-btn[data-target="general-settings"]').click();
    
    document.querySelectorAll('.color-circle').forEach(circle => circle.addEventListener('click', () => { 
        document.querySelectorAll('.color-circle').forEach(item => item.classList.remove('active')); 
        circle.classList.add('active'); 
    }));
});

function saveSettings(keys, btnElement, hasCheckboxes = false) {
    const data = {};
    keys.forEach(key => {
        const el = document.getElementById(key);
        if (el) {
            if (el.type === 'checkbox') {
                data[key] = el.checked ? '1' : '0';
            } else {
                data[key] = el.value;
            }
        }
    });

    sendUpdate(data, btnElement);
}

function saveAppearance(btnElement) {
    const activeColor = document.querySelector('.color-circle.active');
    const color = activeColor ? activeColor.dataset.color : '#2563eb';
    const theme = document.getElementById('theme_mode').value;
    
    sendUpdate({
        'theme_mode': theme,
        'primary_color': color
    }, btnElement);
}

function sendUpdate(data, btnElement) {
    const originalText = btnElement.innerText;
    btnElement.innerText = 'Saving...';
    btnElement.disabled = true;

    fetch('settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            btnElement.innerText = 'Saved!';
            setTimeout(() => {
                btnElement.innerText = originalText;
                btnElement.disabled = false;
            }, 2000);
        } else {
            alert('Failed to save settings.');
            btnElement.innerText = originalText;
            btnElement.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving.');
        btnElement.innerText = originalText;
        btnElement.disabled = false;
    });
}
</script>
</body>
</html>
