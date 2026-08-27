<!-- Top Header Navigation (Shared Admin/Doctor Layout) -->
<nav class="patient-nav">
    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span class="brand-title-text" style="font-weight: 700; font-size: 20px; color: var(--primary-blue);">MediCare</span>
                <span class="role-tag">PATIENT</span>
            </div>
        </div>
    </div>
    
    <div class="nav-tabs">
        <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
        <a href="mainpage.php" class="nav-tab <?php echo $current_page === 'mainpage.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="book_appointment.php" class="nav-tab <?php echo $current_page === 'book_appointment.php' ? 'active' : ''; ?>">Book Appointment</a>
        <a href="my_appointment.php" class="nav-tab <?php echo $current_page === 'my_appointment.php' ? 'active' : ''; ?>">My Appointments</a>
        <a href="medical_records.php" class="nav-tab <?php echo $current_page === 'medical_records.php' ? 'active' : ''; ?>">Medical Records</a>
        <a href="prescriptions.php" class="nav-tab <?php echo $current_page === 'prescriptions.php' ? 'active' : ''; ?>">Prescriptions</a>
    </div>
    
    <div class="nav-user">
        <div class="user-profile-container" style="position: relative;">
            <div class="user-profile" onclick="toggleProfileDropdown(event)" style="cursor:pointer; display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.backgroundColor='var(--slate-100)'" onmouseout="this.style.backgroundColor='transparent'">
                <div class="user-avatar" style="background-color: var(--purple); color: var(--white); margin-right: 0;">
                    <?= isset($_SESSION['user']['username']) ? e(strtoupper(substr($_SESSION['user']['username'], 0, 1))) : 'J' ?>
                </div>
                <span class="user-name">
                    <?= isset($_SESSION['user']['username']) ? e($_SESSION['user']['username']) : 'John Doe' ?>
                </span>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--slate-400);"></i>
            </div>

            <div class="profile-dropdown-menu" id="profileDropdownMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border: 1px solid var(--slate-200); border-radius: 8px; width: 160px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); z-index: 1000; overflow: hidden;">
                <a href="patientProfile.php" style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 13px; color: var(--slate-700); text-decoration: none; transition: background 0.15s;" onmouseover="this.style.backgroundColor='var(--slate-50)'" onmouseout="this.style.backgroundColor='transparent'">
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