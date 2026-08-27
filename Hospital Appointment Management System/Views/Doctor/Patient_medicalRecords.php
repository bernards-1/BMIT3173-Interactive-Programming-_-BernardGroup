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

// Get patient ID from query parameter
$patient_id = isset($_GET['id']) ? trim($_GET['id']) : 'P001';

// Fetch patient info from database
$pat_stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$pat_stmt->execute([$patient_id]);
$patient = $pat_stmt->fetch();

if (!$patient) {
    echo "Patient not found.";
    exit;
}

// Helper to escape output
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Helper to calculate age
function getAge($dob) {
    if (empty($dob)) return '—';
    $birthdate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthdate->diff($today)->y;
}

// Get the correct doctor_id and name
$user_id = $_SESSION['user']['user_id'] ?? $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = ?');
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['doctor_id'] : 'D001';

// Get visits count for this patient
$visits_stmt = $pdo->prepare('SELECT COUNT(*) FROM medical_records WHERE patient_id = ?');
$visits_stmt->execute([$patient_id]);
$visits_count = $visits_stmt->fetchColumn();

// Get last visit date
$last_stmt = $pdo->prepare('SELECT MAX(created_at) FROM medical_records WHERE patient_id = ?');
$last_stmt->execute([$patient_id]);
$last_visit_raw = $last_stmt->fetchColumn();
$last_visit = $last_visit_raw ? date('Y-m-d', strtotime($last_visit_raw)) : 'None';

// Get next follow-up appointment date (Scheduled appointment in the future or next appointment)
$next_stmt = $pdo->prepare('
    SELECT MIN(appointment_date) 
    FROM appointments 
    WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = \'Scheduled\'
');
$next_stmt->execute([$patient_id]);
$next_visit_raw = $next_stmt->fetchColumn();
$next_visit = $next_visit_raw ? date('Y-m-d', strtotime($next_visit_raw)) : 'None';

// Get prescriptions count for this patient
$pres_stmt = $pdo->prepare('
    SELECT COUNT(*) 
    FROM prescriptions pr
    JOIN medical_records mr ON pr.record_id = mr.medical_record_id
    WHERE mr.patient_id = ?
');
$pres_stmt->execute([$patient_id]);
$pres_count = $pres_stmt->fetchColumn();

// Fetch visit history list for the patient
$history_stmt = $pdo->prepare('
    SELECT 
        mr.medical_record_id,
        mr.diagnosis,
        mr.symptoms,
        mr.notes,
        mr.follow_up_date,
        mr.created_at,
        a.reason as visit_type,
        a.status as appointment_status
    FROM medical_records mr
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
');
$history_stmt->execute([$patient_id]);
$history_records = $history_stmt->fetchAll();

// Group history records into JS format
$js_records = [];
$is_latest = true; // First record is the latest since it is ordered DESC
foreach ($history_records as $rec) {
    $v_type = $rec['visit_type'] ? $rec['visit_type'] : 'Consultation';
    
    // Badge colors
    $badgeBg = '#ccfbf1';
    $badgeColor = '#0d9488';
    $icon = 'fa-chart-line';
    
    if ($v_type === 'Consultation') {
        $badgeBg = '#eff6ff';
        $badgeColor = '#2563eb';
        $icon = 'fa-stethoscope';
    } elseif ($v_type === 'Check-up') {
        $badgeBg = '#d1fae5';
        $badgeColor = '#065f46';
        $icon = 'fa-chart-line';
    } elseif ($v_type === 'Follow-up') {
        $badgeBg = '#f3e8ff';
        $badgeColor = '#6b21a8';
        $icon = 'fa-user-doctor';
    }
    
    // Check if there are medicines for this record
    $meds_stmt = $pdo->prepare('
        SELECT pr.dosage, pr.frequency, pr.duration, pr.instructions, pr.quantity, m.brand_name, m.generic_name 
        FROM prescriptions pr
        JOIN medicines m ON pr.medicine_id = m.medicine_id
        WHERE pr.record_id = ?
    ');
    $meds_stmt->execute([$rec['medical_record_id']]);
    $medicines = $meds_stmt->fetchAll();
    
    $js_records[] = [
        'type' => $v_type,
        'isLatest' => $is_latest,
        'badgeBg' => $badgeBg,
        'badgeColor' => $badgeColor,
        'title' => $rec['diagnosis'],
        'symptoms' => $rec['symptoms'] ? $rec['symptoms'] : '',
        'notes' => $rec['notes'] ? $rec['notes'] : '',
        'date' => date('Y-m-d', strtotime($rec['created_at'])),
        'code' => $rec['medical_record_id'],
        'icon' => $icon,
        'iconActive' => $is_latest,
        'medicines' => $medicines
    ];
    $is_latest = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Medical Records</title>
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
    
    <!-- Back to Patients Navigation Link -->
    <div style="margin-bottom: 24px;">
        <a href="patients.php" style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--slate-500); text-decoration: none; font-weight: 600; transition: var(--transition);" onmouseover="this.style.color='var(--slate-800)'" onmouseout="this.style.color='var(--slate-500)'">
            <i class="fa-solid fa-arrow-left"></i> Back to Patients
        </a>
    </div>
    
    <!-- Patient Profile Header Card -->
    <div class="patient-header-card" id="patientHeaderCard">
        <?php
        // Calculate patient initials
        $names = explode(' ', $patient['full_name']);
        $initials = '';
        foreach ($names as $n) {
            if (!empty($n)) $initials .= strtoupper($n[0]);
        }
        $initials = substr($initials, 0, 2);

        // Color hash for initials circle background
        $colors = ['#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#ef4444', '#0d9488', '#6366f1'];
        $charCodeSum = 0;
        for ($i = 0; $i < strlen($patient['full_name']); $i++) {
            $charCodeSum += ord($patient['full_name'][$i]);
        }
        $bgColor = $colors[$charCodeSum % count($colors)];
        ?>
        <div class="patient-header-top">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="patient-initial-circle" style="background-color: <?= $bgColor ?>; width: 54px; height: 54px; font-size: 18px; color: white; display: flex; justify-content: center; align-items: center; font-weight: 700; border-radius: 50%;"><?= $initials ?></div>
                <div>
                    <h2 style="font-size: 22px; font-weight: 700; color: var(--slate-900); margin-bottom: 2px;"><?= e($patient['full_name']) ?></h2>
                    <p style="font-size: 13px; color: var(--slate-400);">Age <?= getAge($patient['date_of_birth']) ?> · Gender <?= e($patient['gender']) ?> · Blood Type <?= e($patient['blood_type']) ?></p>
                </div>
            </div>
            <a href="patients.php" class="btn-all-patients-outline" style="text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 16px; border: 1px solid var(--slate-200); border-radius: 8px; color: var(--slate-700); background-color: var(--white); display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-arrow-left"></i> All Patients
            </a>
        </div>
        
        <div class="patient-header-metrics-row">
            <div class="patient-header-metric-box gray">
                <span style="font-size: 14px; font-weight: 700; color: var(--slate-900);"><?= $visits_count ?></span> Total Visits
            </div>
            <div class="patient-header-metric-box blue">
                <span style="font-size: 14px; font-weight: 700; color: #2563eb;"><?= $last_visit ?></span> Last Visit
            </div>
            <div class="patient-header-metric-box green">
                <span style="font-size: 14px; font-weight: 700; color: #16a34a;"><?= $next_visit ?></span> Next Follow-up
            </div>
            <div class="patient-header-metric-box purple">
                <span style="font-size: 14px; font-weight: 700; color: #7c3aed;"><?= $pres_count ?></span> Prescriptions
            </div>
        </div>
    </div>
    
    <!-- Visit History Title Section -->
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">Visit History</h2>
        <p style="font-size: 13px; color: var(--slate-400); margin-top: 2px;">All consultations sorted by most recent</p>
    </div>
    
    <!-- Visit Search Bar -->
    <div class="form-group" style="margin-bottom: 24px;">
        <div class="search-input-wrapper" style="max-width: 100%;">
            <span class="search-field-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="recordsSearchInput" class="search-input-field" placeholder="Search diagnosis or type..." onkeyup="handleRecordsSearch()">
        </div>
    </div>
    
    <!-- Timeline & Visit Cards List Container -->
    <div class="timeline-container">
        <!-- Vertical Timeline Line -->
        <div class="timeline-line"></div>
        
        <!-- Timeline cards container -->
        <div id="timelineItemsContainer">
            <!-- Loaded Dynamically by JS -->
        </div>
    </div>
</div>

<script>
// Grab active patient ID from PHP
const activePatientId = "<?= $patient_id ?>";

// Load active patient's records from dynamic query
let activeRecords = <?= json_encode($js_records) ?>;

// Initialize Page
document.addEventListener("DOMContentLoaded", () => {
    renderRecordsList(activeRecords);
});

function renderRecordsList(records) {
    const container = document.getElementById("timelineItemsContainer");
    container.innerHTML = "";
    
    if (records.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--slate-400); font-size: 15px;">
                <i class="fa-regular fa-folder-open" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--slate-300);"></i>
                No matching medical records found.
            </div>
        `;
        return;
    }
    
    records.forEach(r => {
        const item = document.createElement("div");
        item.className = "timeline-item-wrapper";
        
        let medsHtml = '';
        if (r.medicines && r.medicines.length > 0) {
            medsHtml = `<div style="margin-top: 10px; border-top: 1px solid var(--slate-100); padding-top: 10px;">
                <strong style="font-size: 12px; color: var(--slate-700); display: block; margin-bottom: 6px;"><i class="fa-solid fa-capsules" style="color: #7c3aed;"></i> Prescribed Medicines:</strong>
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; color: var(--slate-600);">
                    <thead>
                        <tr style="color: var(--slate-400); border-bottom: 1px solid var(--slate-100);">
                            <th style="padding: 4px 0;">Drug</th>
                            <th style="padding: 4px 0;">Dosage</th>
                            <th style="padding: 4px 0;">Freq</th>
                            <th style="padding: 4px 0;">Duration</th>
                        </tr>
                    </thead>
                    <tbody>`;
            r.medicines.forEach(m => {
                medsHtml += `
                    <tr style="border-bottom: 1px dashed var(--slate-100);">
                        <td style="padding: 6px 0; font-weight: 600; color: var(--slate-800);">${m.brand_name} (${m.generic_name})</td>
                        <td style="padding: 6px 0;">${m.dosage}</td>
                        <td style="padding: 6px 0;">${m.frequency}</td>
                        <td style="padding: 6px 0;">${m.duration}</td>
                    </tr>
                `;
            });
            medsHtml += `</tbody></table></div>`;
        } else {
            medsHtml = `<div style="margin-top: 10px; border-top: 1px solid var(--slate-100); padding-top: 10px; font-size: 12px; color: var(--slate-400);">No prescriptions issued.</div>`;
        }
        
        item.innerHTML = `
            <!-- Timeline Icon Circle Node -->
            <div class="timeline-node-circle ${r.iconActive ? 'active-node' : ''}">
                <i class="fa-solid ${r.icon}"></i>
            </div>
            
            <!-- Record Card -->
            <div class="timeline-card" style="flex-direction: column; align-items: stretch; gap: 12px; padding: 16px 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <!-- Badges -->
                        <div style="display: flex; gap: 6px; margin-bottom: 8px;">
                            <span class="badge" style="background-color: ${r.badgeBg}; color: ${r.badgeColor}; border-radius: 6px; text-transform: none; font-weight: 700; padding: 3px 8px; font-size: 10px;">
                                ${r.type}
                            </span>
                            ${r.isLatest ? '<span class="badge" style="background-color: #2563eb; color: var(--white); border-radius: 6px; text-transform: none; font-weight: 700; padding: 3px 8px; font-size: 10px;">Latest</span>' : ''}
                        </div>
                        
                        <!-- Title -->
                        <h3 style="font-size: 15px; font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">${r.title}</h3>
                        
                        <!-- Metadata row -->
                        <div style="display: flex; gap: 16px; font-size: 12px; color: var(--slate-400);">
                            <span style="display: flex; align-items: center; gap: 4px;">
                                <i class="fa-regular fa-calendar"></i> ${r.date}
                            </span>
                            <span style="display: flex; align-items: center; gap: 4px;">
                                <i class="fa-regular fa-file-lines"></i> ${r.code}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
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
                    <div style="margin-bottom: 8px;">
                        <strong style="font-size: 12px; color: var(--slate-700);">Clinical Notes:</strong>
                        <div style="font-size: 12px; color: var(--slate-600); margin-top: 2px;">${r.notes ? r.notes : 'None recorded.'}</div>
                    </div>
                    ${medsHtml}
                </div>
            </div>
        `;
        
        container.appendChild(item);
    });
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

function handleRecordsSearch() {
    const query = document.getElementById("recordsSearchInput").value.toLowerCase();
    const filtered = activeRecords.filter(r => {
        return r.title.toLowerCase().includes(query) || 
               r.type.toLowerCase().includes(query) ||
               r.code.toLowerCase().includes(query);
    });
    renderRecordsList(filtered);
}
</script>

</body>
</html>
