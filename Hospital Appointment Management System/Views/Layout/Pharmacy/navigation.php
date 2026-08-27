<?php
// Get current page filename to mark tab as active
$current_page = basename($_SERVER['PHP_SELF']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch pharmacist name if logged in
$user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? null);
$pharmacist_name = $_SESSION['user']['username'] ?? 'Pharmacist';

if (!isset($pdo)) {
    @include_once __DIR__ . '/../../db.php';
}

if (isset($pdo) && $user_id) {
    $stmt_nav = $pdo->prepare("SELECT full_name FROM pharmacists WHERE user_id = ?");
    $stmt_nav->execute([$user_id]);
    $p_data = $stmt_nav->fetch();
    if ($p_data && !empty($p_data['full_name'])) {
        $pharmacist_name = $p_data['full_name'];
    }
}
$avatar_letter = !empty($pharmacist_name) ? strtoupper(substr($pharmacist_name, 0, 1)) : 'P';
?>
<!-- Top Navigation Header (Shared Pharmacy Layout) -->
<nav class="pharmacy-nav">
    <div class="brand">
        <a href="dashboard.php" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
            <div class="brand-icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <div class="brand-text-wrapper">
                <span class="brand-title-text" style="font-weight: 700; font-size: 20px; color: var(--primary-blue);">MediCare</span>
                <span class="role-tag pharmacy-tag">PHARMACY</span>
            </div>
        </a>
    </div>
    
    <div class="nav-tabs">
        <a href="dashboard.php" class="nav-tab <?php echo ($current_page == 'dashboard.php' || $current_page == 'mainpage.php' || $current_page == '') ? 'active' : ''; ?>">Dashboard</a>
        <a href="queue.php" class="nav-tab <?php echo ($current_page == 'queue.php') ? 'active' : ''; ?>">Rx Queue</a>
        <a href="history.php" class="nav-tab <?php echo ($current_page == 'history.php') ? 'active' : ''; ?>">History</a>
        <a href="inventory.php" class="nav-tab <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">Inventory</a>
    </div>
    
    <div class="nav-user">
        <div class="user-profile-container" style="position: relative;">
            <div class="user-profile" onclick="toggleProfileDropdown(event)" style="cursor:pointer; display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.backgroundColor='var(--slate-100)'" onmouseout="this.style.backgroundColor='transparent'">
                <div class="user-avatar" style="background-color: var(--teal); color: var(--white); font-weight: 700;">
                    <?= htmlspecialchars($avatar_letter, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <span class="user-name">
                    <?= htmlspecialchars($pharmacist_name, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--slate-400);"></i>
            </div>
            
            <div class="profile-dropdown-menu" id="profileDropdownMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border: 1px solid var(--border-color); border-radius: 8px; width: 160px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); z-index: 1000; overflow: hidden;">
                <a href="pharmacistProfile.php" style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; color: var(--slate-700); text-decoration: none; transition: background 0.15s;" onmouseover="this.style.backgroundColor='var(--slate-50)'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fa-regular fa-user" style="width: 14px;"></i> Profile
                </a>
                <a href="../Login/login.php?logout=1" onclick="return confirm('Are you sure you want to sign out?')" style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; color: #ef4444; text-decoration: none; border-top: 1px solid var(--slate-100); transition: background 0.15s;" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fa-solid fa-right-from-bracket" style="width: 14px;"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleProfileDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('profileDropdownMenu');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('profileDropdownMenu');
    if (dropdown && dropdown.style.display === 'block') {
        if (!event.target.closest('.user-profile-container')) {
            dropdown.style.display = 'none';
        }
    }
});
</script>
