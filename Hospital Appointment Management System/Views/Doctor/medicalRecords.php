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
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Get the correct doctor_id and name
$user_id = $_SESSION['user']['user_id'] ?? $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare('SELECT doctor_id, name FROM doctors WHERE user_id = ?');
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';

// Fetch all medical records for patients of this doctor
$rec_stmt = $pdo->prepare('
    SELECT 
        mr.medical_record_id,
        mr.patient_id,
        mr.diagnosis,
        mr.symptoms,
        mr.notes,
        mr.follow_up_date,
        mr.created_at,
        p.full_name,
        a.reason as visit_type
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.patient_id
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.doctor_id = ?
    ORDER BY mr.created_at DESC
');
$rec_stmt->execute([$doctor_id]);
$db_records = $rec_stmt->fetchAll();

// Group records by patient
$grouped = [];
foreach ($db_records as $row) {
    $pid = $row['patient_id'];
    
    if (!isset($grouped[$pid])) {
        // Compute patient initials
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

        $grouped[$pid] = [
            'id' => $pid,
            'name' => $row['full_name'],
            'initials' => $initials,
            'color' => $bgColor,
            'visits' => 0,
            'records' => []
        ];
    }
    
    // Visit type formatting
    $v_type = $row['visit_type'] ? $row['visit_type'] : 'Consultation';
    
    // Badge colors
    $badgeBg = '#ccfbf1';
    $badgeColor = '#0d9488';
    if ($v_type === 'Consultation') {
        $badgeBg = '#eff6ff';
        $badgeColor = '#2563eb';
    } elseif ($v_type === 'Check-up') {
        $badgeBg = '#d1fae5';
        $badgeColor = '#065f46';
    } elseif ($v_type === 'Follow-up') {
        $badgeBg = '#f3e8ff';
        $badgeColor = '#6b21a8';
    }
    
$grouped[$pid]['records'][] = [
        'type' => $v_type,
        'badgeBg' => $badgeBg,
        'badgeColor' => $badgeColor,
        'title' => e($row['diagnosis']),
        'symptoms' => $row['symptoms'] ? e($row['symptoms']) : '',
        'notes' => $row['notes'] ? e($row['notes']) : '',
        'date' => date('Y-m-d', strtotime($row['created_at'])),
        'code' => $row['medical_record_id']
    ];
    $grouped[$pid]['visits']++;
}

// Convert to indexed array for Javascript
$js_overview_list = array_values($grouped);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare General Medical Records</title>
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
        <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900);">Medical Records</h1>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">Patient diagnoses, notes, and treatment history</p>
    </div>
    
    <!-- Search Bar -->
    <div class="form-group" style="margin-bottom: 24px;">
        <div class="search-input-wrapper" style="max-width: 100%;">
            <span class="search-field-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="globalRecordsSearchInput" class="search-input-field" placeholder="Search by patient, diagnosis, or type..." onkeyup="handleGlobalSearch()">
        </div>
    </div>
    
    <!-- Grouped Patient Medical Records List -->
    <div id="patientRecordGroupsContainer">
        <!-- Rendered Dynamically by JS -->
    </div>
    
    <!-- Pagination Component -->
    <div class="pagination-container" style="margin-top: 32px;">
        <div class="pagination-info" id="globalPaginationInfo">
            Showing 1-3 of 6 patients
        </div>
        
        <div class="pagination-buttons">
            <button class="pagination-btn" id="globalPrevPageBtn" onclick="goToGlobalPage(currentGlobalPage - 1)">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div id="globalPageNumbersContainer" style="display: flex; gap: 6px;">
                <!-- Page numbers generated dynamically -->
            </div>
            <button class="pagination-btn" id="globalNextPageBtn" onclick="goToGlobalPage(currentGlobalPage + 1)">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Dynamic Patient Groups Database loaded directly from SQLite/MySQL PHP array
const medicalRecordsOverview = <?= json_encode($js_overview_list) ?>;

let currentGlobalPage = 1;
const itemsPerGlobalPage = 3;
let filteredGroups = [...medicalRecordsOverview];

// Initial Render
document.addEventListener("DOMContentLoaded", () => {
    renderOverviewList();
});

function renderOverviewList() {
    const container = document.getElementById("patientRecordGroupsContainer");
    const info = document.getElementById("globalPaginationInfo");
    container.innerHTML = "";
    
    // Calculate total pages
    const totalPages = Math.ceil(filteredGroups.length / itemsPerGlobalPage);
    if (currentGlobalPage > totalPages) currentGlobalPage = Math.max(1, totalPages);
    
    const startIndex = (currentGlobalPage - 1) * itemsPerGlobalPage;
    const endIndex = Math.min(startIndex + itemsPerGlobalPage, filteredGroups.length);
    
    if (filteredGroups.length === 0) {
        info.innerText = "Showing 0 patients";
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; background-color: var(--white); border: 1px solid var(--border-color); border-radius: 12px; color: var(--slate-400); font-size: 15px;">
                <i class="fa-regular fa-folder-open" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--slate-300);"></i>
                No matching medical records found.
            </div>
        `;
        document.getElementById("globalPrevPageBtn").disabled = true;
        document.getElementById("globalNextPageBtn").disabled = true;
        document.getElementById("globalPageNumbersContainer").innerHTML = "";
        return;
    }
    
    info.innerText = `Showing ${startIndex + 1}-${endIndex} of ${filteredGroups.length} patients`;
    
    // Loop and render sliced groups
    for (let i = startIndex; i < endIndex; i++) {
        const group = filteredGroups[i];
        
        // Build records HTML
        let recordsHtml = "";
        group.records.forEach(r => {
            recordsHtml += `
                <div class="overview-record-card" style="flex-direction: column; align-items: stretch; gap: 12px; padding: 16px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <span class="badge" style="background-color: ${r.badgeBg}; color: ${r.badgeColor}; border-radius: 6px; text-transform: none; font-weight: 700; padding: 4px 8px; font-size: 10px; min-width: 90px; text-align: center;">
                                ${r.type}
                            </span>
                            <div>
                                <h4 style="font-size: 14px; font-weight: 600; color: var(--slate-900); margin-bottom: 4px;">${r.title}</h4>
                                <span style="font-size: 11px; color: var(--slate-400); display: flex; align-items: center; gap: 4px;">
                                    <i class="fa-regular fa-calendar"></i> ${r.date} · ${r.code}
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fa-solid fa-chevron-down" id="icon-${r.code}" style="color: var(--slate-400); cursor: pointer; padding: 6px;" onclick="toggleRecordDetails('${r.code}')"></i>
                        </div>
                    </div>
                    
                    <!-- Expanded Details Panel -->
                    <div id="details-${r.code}" style="display: none; background-color: var(--slate-50); border-radius: 8px; padding: 14px; border: 1px solid var(--slate-100); margin-top: 4px;">
                        <div style="margin-bottom: 8px;">
                            <strong style="font-size: 12px; color: var(--slate-700);">Symptoms:</strong>
                            <div style="font-size: 12px; color: var(--slate-600); margin-top: 2px;">${r.symptoms ? r.symptoms : 'None recorded.'}</div>
                        </div>
                        <div>
                            <strong style="font-size: 12px; color: var(--slate-700);">Clinical Notes:</strong>
                            <div style="font-size: 12px; color: var(--slate-600); margin-top: 2px;">${r.notes ? r.notes : 'None recorded.'}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        const groupEl = document.createElement("div");
        groupEl.className = "patient-record-group";
        groupEl.innerHTML = `
            <!-- Group Header -->
            <div class="patient-record-group-header">
                <div class="patient-record-group-title">
                    <div class="patient-initial-circle" style="background-color: ${group.color}; width: 36px; height: 36px; font-size: 13px;">${group.initials}</div>
                    <span class="patient-record-group-name">${group.name}</span>
                    <span class="patient-record-group-visits">${group.visits} visits</span>
                </div>
                <a href="Patient_medicalRecords.php?id=${group.id}" class="link-view-all">
                    View all <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i>
                </a>
            </div>
            
            <!-- Cards list -->
            <div>
                ${recordsHtml}
            </div>
        `;
        
        container.appendChild(groupEl);
    }
    
    // Set buttons states
    document.getElementById("globalPrevPageBtn").disabled = (currentGlobalPage === 1);
    document.getElementById("globalNextPageBtn").disabled = (currentGlobalPage === totalPages);
    
    // Render Page Numbers
    const numbersContainer = document.getElementById("globalPageNumbersContainer");
    numbersContainer.innerHTML = "";
    for (let page = 1; page <= totalPages; page++) {
        const btn = document.createElement("button");
        btn.className = `pagination-btn ${page === currentGlobalPage ? 'active-page' : ''}`;
        btn.innerText = page;
        btn.onclick = () => goToGlobalPage(page);
        numbersContainer.appendChild(btn);
    }
}

function toggleRecordDetails(code) {
    const detailsDiv = document.getElementById(`details-${code}`);
    const icon = document.getElementById(`icon-${code}`);
    if (detailsDiv && icon) {
        if (detailsDiv.style.display === 'none') {
            detailsDiv.style.display = 'block';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            detailsDiv.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
}

function goToGlobalPage(page) {
    currentGlobalPage = page;
    renderOverviewList();
}

function handleGlobalSearch() {
    const query = document.getElementById("globalRecordsSearchInput").value.toLowerCase();
    
    // Filter the patient groups and their records
    const filtered = [];
    
    medicalRecordsOverview.forEach(group => {
        const patientNameMatches = group.name.toLowerCase().includes(query);
        
        // Filter records inside this group that match
        const matchingRecords = group.records.filter(r => {
            return r.title.toLowerCase().includes(query) || 
                   r.type.toLowerCase().includes(query) || 
                   r.code.toLowerCase().includes(query);
        });
        
        if (patientNameMatches) {
            // If patient name matches, include the whole group with all records
            filtered.push(group);
        } else if (matchingRecords.length > 0) {
            // If patient name doesn't match but records match, include group with ONLY matching records
            filtered.push({
                ...group,
                records: matchingRecords
            });
        }
    });
    
    filteredGroups = filtered;
    currentGlobalPage = 1; // Reset to page 1 on search
    renderOverviewList();
}
</script>

</body>
</html>
