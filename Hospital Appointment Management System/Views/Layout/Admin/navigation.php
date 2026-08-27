<!-- Views/Layout/Admin/navigation.php -->
<?php
require_once __DIR__ . '/../../../Models/Setting.php';
$settingModel = new Setting();
$sysSettings = $settingModel->getAllSettings();
$themeMode = $sysSettings['theme_mode'] ?? 'Light';
$primaryColor = $sysSettings['primary_color'] ?? '#2563eb';

$bgBody = $themeMode === 'Dark' ? '#0f172a' : '#f8fafc';
$bgCard = $themeMode === 'Dark' ? '#1e293b' : '#ffffff';
$textMain = $themeMode === 'Dark' ? '#f8fafc' : '#0f172a';
$textMuted = $themeMode === 'Dark' ? '#94a3b8' : '#64748b';
$border = $themeMode === 'Dark' ? '#334155' : '#e2e8f0';

echo "<style>
:root {
    --primary: {$primaryColor};
    --bg-body: {$bgBody};
    --bg-card: {$bgCard};
    --text-main: {$textMain};
    --text-muted: {$textMuted};
    --border: {$border};
}
</style>";
?>
<nav class="top-nav">
    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <span>MediCare</span>
        <span class="admin-badge">ADMIN</span>
    </div>

    <div class="nav-links">
        <?php 
            $current_page = strtolower(basename($_SERVER['PHP_SELF'])); 
        ?>
        <a href="Admin.php" class="nav-link <?= ($current_page == 'admin.php') ? 'active' : '' ?>"><?= __('Dashboard') ?></a>
        <a href="patients.php" class="nav-link <?= ($current_page == 'patients.php') ? 'active' : '' ?>"><?= __('Patients') ?></a>
        <a href="doctors.php" class="nav-link <?= ($current_page == 'doctors.php') ? 'active' : '' ?>"><?= __('Doctors') ?></a>
        <a href="pharmacists.php" class="nav-link <?= ($current_page == 'pharmacists.php') ? 'active' : '' ?>"><?= __('Pharmacists') ?></a>
        <a href="appointments.php" class="nav-link <?= ($current_page == 'appointments.php') ? 'active' : '' ?>"><?= __('Appointments') ?></a>
        <a href="billing.php" class="nav-link <?= ($current_page == 'billing.php') ? 'active' : '' ?>"><?= __('Billing') ?></a>
        <a href="reports.php" class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>"><?= __('Reports') ?></a>
        <a href="leave_requests.php" class="nav-link <?= ($current_page == 'leave_requests.php') ? 'active' : '' ?>" style="position:relative;">
            Leave Requests
            <?php
            // Show badge if there are pending leave requests
            global $pdo;
            $pending_count = $pdo->query("SELECT COUNT(*) FROM doctor_leaves WHERE status = 'Pending'")->fetchColumn();
            if ($pending_count > 0): ?>
                <span style="position:absolute;top:-6px;right:-10px;background:#ef4444;color:white;font-size:10px;font-weight:700;padding:2px 5px;border-radius:999px;line-height:1;"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
        <a href="settings.php" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>"><?= __('Settings') ?></a>
    </div>

    <div class="nav-actions">
        <div class="user-profile-container" style="position:relative;">
            <div class="user-profile" onclick="toggleAdminProfileDropdown(event)" style="padding:6px 12px;border-radius:8px;transition:background .2s;" onmouseover="this.style.backgroundColor='var(--bg-body)'" onmouseout="this.style.backgroundColor='transparent'">
                <div class="user-avatar"><?= isset($_SESSION['user']['username']) ? e(strtoupper(substr($_SESSION['user']['username'], 0, 1))) : 'A' ?></div>
                <div class="user-info">
                    <?= isset($_SESSION['user']['username']) ? e($_SESSION['user']['username']) : 'Admin User' ?>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div id="adminProfileDropdownMenu" style="display:none;position:absolute;top:100%;right:0;margin-top:8px;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;width:160px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);z-index:1000;overflow:hidden;">
                <a href="adminProfile.php" style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:var(--text-main);text-decoration:none;" onmouseover="this.style.backgroundColor='var(--bg-body)'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fa-regular fa-user" style="width:14px;"></i> Profile
                </a>
                <a href="../Login/login.php?logout=1" onclick="return confirm('Are you sure you want to sign out?')" style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:#ef4444;text-decoration:none;border-top:1px solid var(--border);" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fa-solid fa-right-from-bracket" style="width:14px;"></i> Logout
                </a>
            </div>
        </div>
        
    </div>
</nav>

<script>
function toggleAdminProfileDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('adminProfileDropdownMenu');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('click', function (event) {
    const dropdown = document.getElementById('adminProfileDropdownMenu');
    if (dropdown && !event.target.closest('.user-profile-container')) dropdown.style.display = 'none';
});
</script>
