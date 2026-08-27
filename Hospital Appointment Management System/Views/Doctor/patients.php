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

// Get the correct doctor_id and name
$user_id = $_SESSION['user']['user_id'] ?? $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare('SELECT doctor_id, name FROM doctors WHERE user_id = ?');
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';

// Fetch all patients with their visit summaries
$pat_stmt = $pdo->prepare('
    SELECT 
        p.patient_id,
        p.full_name,
        p.gender,
        p.date_of_birth,
        p.phone,
        u.email,
        (
            SELECT mr.diagnosis 
            FROM medical_records mr 
            WHERE mr.patient_id = p.patient_id 
            ORDER BY mr.created_at DESC LIMIT 1
        ) as condition_name,
        (
            SELECT COUNT(*) 
            FROM appointments a 
            WHERE a.patient_id = p.patient_id AND a.doctor_id = ?
        ) as visits_count,
        (
            SELECT MAX(appointment_date) 
            FROM appointments a 
            WHERE a.patient_id = p.patient_id AND a.doctor_id = ? AND a.appointment_date <= CURDATE()
        ) as last_visit_date,
        (
            SELECT MIN(appointment_date) 
            FROM appointments a 
            WHERE a.patient_id = p.patient_id AND a.doctor_id = ? AND a.appointment_date >= CURDATE() AND a.status = \'Scheduled\'
        ) as next_visit_date
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.full_name ASC
');
$pat_stmt->execute([$doctor_id, $doctor_id, $doctor_id]);
$db_patients = $pat_stmt->fetchAll();

// Construct the JS patients array
$js_patients = [];
foreach ($db_patients as $row) {
    // Calculate patient initials
    $names = explode(' ', $row['full_name']);
    $initials = '';
    foreach ($names as $n) {
        if (!empty($n)) $initials .= strtoupper($n[0]);
    }
    $initials = substr($initials, 0, 2);

    // Color hash for initials circle background
    $colors = ['#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#ef4444', '#0d9488', '#6366f1'];
    $charCodeSum = 0;
    for ($i = 0; $i < strlen($row['full_name']); $i++) {
        $charCodeSum += ord($row['full_name'][$i]);
    }
    $bgColor = $colors[$charCodeSum % count($colors)];

    // Age
    $age = '—';
    if (!empty($row['date_of_birth'])) {
        $birthdate = new DateTime($row['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthdate->diff($today)->y;
    }

    $js_patients[] = [
        'id' => $row['patient_id'],
        'name' => $row['full_name'],
        'gender' => $row['gender'] ? $row['gender'] : 'Unspecified',
        'age' => $age,
        'initials' => $initials,
        'color' => $bgColor,
        'condition' => $row['condition_name'] ? $row['condition_name'] : 'No records',
        'lastVisit' => $row['last_visit_date'] ? date('Y-m-d', strtotime($row['last_visit_date'])) : 'None',
        'nextVisit' => $row['next_visit_date'] ? date('Y-m-d', strtotime($row['next_visit_date'])) : 'None',
        'visits' => (int)$row['visits_count'],
        'phone' => $row['phone'] ? $row['phone'] : '—',
        'email' => $row['email'] ? $row['email'] : '—'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Doctor Patients</title>
    <link rel="stylesheet" href="../Layout/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../Layout/Doctor/style.css?v=<?= time() ?>">
    <!-- FontAwesome 6 for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<!-- Include Navigation Header -->
<?php include '../Layout/Doctor/navigation.php'; ?>

<!-- Main Container -->
<div class="dashboard-container">
    
    <!-- Page Header -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">My Patients</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;" id="patientCountHeader">0 patients under your care</p>
    </div>
    
    <!-- Dynamic Master-Detail Layout Wrapper -->
    <div class="patient-page-layout" id="patientPageLayout">
        
        <!-- Left Column: Patient List (Search + Grid + Pagination) -->
        <div class="patient-list-section">
            <!-- Search Bar -->
            <div class="form-group" style="margin-bottom: 24px;">
                <div class="search-input-wrapper" style="max-width: 100%;">
                    <span class="search-field-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" id="patientSearchInput" class="search-input-field" placeholder="Search by name, condition, or patient ID..." onkeyup="handleSearch()">
                </div>
            </div>
            
            <!-- Patient Cards Grid -->
            <div class="patient-cards-grid" id="patientCardsGrid">
                <!-- Rendered Dynamically by JS -->
            </div>
            
            <!-- Pagination Component -->
            <div class="pagination-container">
                <div class="pagination-info" id="paginationInfo">
                    Showing 1-8 of 16 patients
                </div>
                
                <div class="pagination-buttons">
                    <button class="pagination-btn" id="prevPageBtn" onclick="goToPage(currentPage - 1)">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <div id="pageNumbersContainer" style="display: flex; gap: 6px;">
                        <!-- Page numbers generated dynamically -->
                    </div>
                    <button class="pagination-btn" id="nextPageBtn" onclick="goToPage(currentPage + 1)">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Patient Profile Sidebar (Slides in next to grid) -->
        <div class="patient-profile-sidebar" id="patientProfileSidebar">
            <!-- Rendered dynamically when a patient is clicked -->
        </div>
        
    </div>
</div>

<script>
// Dynamic patients list loaded from SQLite/MySQL database via PHP
const patients = <?= json_encode($js_patients) ?>;

// Color options for conditions based on hashing
const condColors = [
    { bg: "#fee2e2", text: "#ef4444" },
    { bg: "#f3e8ff", text: "#9061f9" },
    { bg: "#fef3c7", text: "#d97706" },
    { bg: "#e0f2fe", text: "#0284c7" },
    { bg: "#ccfbf1", text: "#0d9488" },
    { bg: "#d1fae5", text: "#065f46" }
];

function getConditionColors(condition) {
    if (condition === 'No records') {
        return { bg: "#f1f5f9", text: "#475569" };
    }
    let sum = 0;
    for (let i = 0; i < condition.length; i++) {
        sum += condition.charCodeAt(i);
    }
    return condColors[sum % condColors.length];
}

let filteredPatients = [...patients];
let currentPage = 1;
const itemsPerPage = 8;
let activePatientId = null;

// Initial render
document.addEventListener("DOMContentLoaded", () => {
    renderGrid();
});

function renderGrid() {
    const grid = document.getElementById("patientCardsGrid");
    const countHeader = document.getElementById("patientCountHeader");
    const info = document.getElementById("paginationInfo");
    
    // Clear grid
    grid.innerHTML = "";
    
    // Calculate total pages
    const totalPages = Math.ceil(filteredPatients.length / itemsPerPage);
    if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
    
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredPatients.length);
    
    // Render count header
    countHeader.innerText = `${filteredPatients.length} patients under your care`;
    
    // Render pagination info
    if (filteredPatients.length === 0) {
        info.innerText = "Showing 0 patients";
        grid.innerHTML = `
            <div style="grid-column: span 2; text-align: center; padding: 40px; color: var(--slate-400); font-size: 15px;">
                <i class="fa-solid fa-user-slash" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--slate-300);"></i>
                No matching patients found.
            </div>
        `;
        document.getElementById("prevPageBtn").disabled = true;
        document.getElementById("nextPageBtn").disabled = true;
        document.getElementById("pageNumbersContainer").innerHTML = "";
        return;
    }
    
    info.innerText = `Showing ${startIndex + 1}-${endIndex} of ${filteredPatients.length} patients`;
    
    // Loop and render cards
    for (let i = startIndex; i < endIndex; i++) {
        const p = filteredPatients[i];
        const col = getConditionColors(p.condition);
        
        const card = document.createElement("a");
        card.className = `patient-card ${p.id === activePatientId ? 'active-card' : ''}`;
        card.href = "#";
        card.onclick = (e) => {
            e.preventDefault();
            selectPatient(p);
        };
        
        card.innerHTML = `
            <div class="patient-card-left">
                <div class="patient-initial-circle" style="background-color: ${p.color};">${p.initials}</div>
                <div class="patient-card-details">
                    <span class="patient-card-name">${p.name}</span>
                    <span class="patient-card-info">${p.id} · ${p.gender} · Age ${p.age}</span>
                    
                    <div class="patient-condition-wrapper">
                        <span class="badge" style="background-color: ${col.bg}; color: ${col.text}; border-radius: 9999px; text-transform: none; font-weight: 600; padding: 3px 10px; font-size:11px;">
                            ${p.condition}
                        </span>
                    </div>
                    
                    <span class="patient-card-footer">
                        Last: ${p.lastVisit} &nbsp;&nbsp;<span class="patient-visits-link">${p.visits} visits</span>
                    </span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right patient-card-arrow"></i>
        `;
        
        grid.appendChild(card);
    }
    
    // Render buttons states
    document.getElementById("prevPageBtn").disabled = (currentPage === 1);
    document.getElementById("nextPageBtn").disabled = (currentPage === totalPages);
    
    // Render Page Numbers
    const numbersContainer = document.getElementById("pageNumbersContainer");
    numbersContainer.innerHTML = "";
    for (let page = 1; page <= totalPages; page++) {
        const btn = document.createElement("button");
        btn.className = `pagination-btn ${page === currentPage ? 'active-page' : ''}`;
        btn.innerText = page;
        btn.onclick = () => goToPage(page);
        numbersContainer.appendChild(btn);
    }
}

function selectPatient(p) {
    activePatientId = p.id;
    const col = getConditionColors(p.condition);
    
    // Add profile-open class to layout
    const layout = document.getElementById("patientPageLayout");
    layout.classList.add("profile-open");
    
    // Fill sidebar content
    const sidebar = document.getElementById("patientProfileSidebar");
    sidebar.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--slate-800);">Patient Profile</h3>
            <i class="fa-solid fa-xmark" style="color: var(--slate-400); cursor: pointer; font-size: 18px;" onclick="closeProfile()"></i>
        </div>
        
        <!-- Avatar details -->
        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 24px;">
            <div class="avatar-large" style="background-color: ${p.color};">${p.initials}</div>
            <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900); margin-bottom: 4px;">${p.name}</h2>
            <p style="font-size: 13px; color: var(--slate-400); margin-bottom: 10px;">${p.id} · ${p.gender} · Age ${p.age}</p>
            <span class="badge" style="background-color: ${col.bg}; color: ${col.text}; border-radius: 9999px; text-transform: none; font-weight: 600; padding: 4px 12px; font-size: 11px;">
                ${p.condition}
            </span>
        </div>
        
        <!-- Contact details -->
        <div style="margin-bottom: 24px; border-top: 1px solid var(--border-color); padding-top: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: var(--slate-400); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 12px;">Contact</div>
            <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--slate-700); margin-bottom: 10px;">
                <i class="fa-solid fa-phone" style="color: var(--slate-400); width: 16px;"></i>
                <span>${p.phone}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--slate-700);">
                <i class="fa-regular fa-envelope" style="color: var(--slate-400); width: 16px;"></i>
                <span>${p.email}</span>
            </div>
        </div>
        
        <!-- Visit Metrics -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
            <div class="patient-detail-box" style="background-color: #f8fafc;">
                <div style="font-size: 11px; color: var(--slate-400); font-weight: 600; margin-bottom: 4px;">Last Visit</div>
                <div style="font-size: 13px; font-weight: 700; color: var(--slate-800);">${p.lastVisit}</div>
            </div>
            
            <div class="patient-detail-box" style="background-color: #eff6ff;">
                <div style="font-size: 11px; color: #2563eb; font-weight: 600; margin-bottom: 4px;">Next Visit</div>
                <div style="font-size: 13px; font-weight: 700; color: #2563eb;">${p.nextVisit}</div>
            </div>
            
            <div class="patient-detail-box" style="background-color: #f0fdf4; grid-column: span 2;">
                <div style="font-size: 11px; color: #16a34a; font-weight: 600; margin-bottom: 4px;">Total Visits</div>
                <div style="font-size: 13px; font-weight: 700; color: #15803d;">${p.visits} appointments</div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <button class="patient-btn-primary" onclick="window.location.href = 'schedule.php'">
            <i class="fa-regular fa-calendar-plus"></i> Schedule Appointment
        </button>
        <button class="patient-btn-secondary" onclick="window.location.href = 'Patient_medicalRecords.php?id=${p.id}'">
            <i class="fa-regular fa-file-lines"></i> View Medical Records
        </button>
    `;
    
    // Re-render grid to highlight selected card
    renderGrid();
}

function closeProfile() {
    activePatientId = null;
    
    // Remove profile-open class
    const layout = document.getElementById("patientPageLayout");
    layout.classList.remove("profile-open");
    
    // Re-render grid to remove highlights
    renderGrid();
}

function goToPage(page) {
    currentPage = page;
    renderGrid();
}

function handleSearch() {
    const query = document.getElementById("patientSearchInput").value.toLowerCase();
    
    filteredPatients = patients.filter(p => {
        return p.name.toLowerCase().includes(query) || 
               p.condition.toLowerCase().includes(query) || 
               p.id.toLowerCase().includes(query);
    });
    
    currentPage = 1; // Reset to page 1 on search
    renderGrid();
}
</script>

</body>
</html>
