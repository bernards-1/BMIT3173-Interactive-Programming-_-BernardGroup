<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/PatientRepository.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as patient
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../Login/login.php');
    exit;
}

// Helper to escape output
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$patientRepository = new PatientRepository($pdo);

// Get patient_id from session
$user_id = $_SESSION['user']['user_id'] ?? null;
$patient = $user_id ? $patientRepository->getPatientByUserId($user_id) : null;
$patient_id = $patient ? $patient['patient_id'] : null;

// Fetch all medical records created by doctors for this patient
$records = [];
if ($patient_id) {
    $records = $patientRepository->getMedicalRecordsByPatientId($patient_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - MediCare</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Patient/style.css">
    <link rel="stylesheet" href="../Layout/Patient/medical_records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<?php include '../Layout/Patient/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Page Header -->
    <div class="mr-page-header">
        <h1>Medical Records</h1>
        <p>Complete medical history and health information</p>
    </div>

    <div class="mr-layout">

        <!-- Main Content -->
        <div style="width: 100%;">
            <!-- Tabs: Timeline -->
            <div class="mr-tabs">
                <a class="mr-tab active" data-tab="timeline">Timeline</a>
            </div>

            <!-- ── TIMELINE PANEL ── -->
            <div class="tab-panel active" id="panel-timeline">
                <div class="timeline-list">
                    <?php if (empty($records)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: #888;">
                        <i class="fa-solid fa-inbox" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p>No medical records yet</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($records as $index => $record): ?>
                        <!-- Medical Record Card -->
                        <div class="timeline-card">
                            <div class="timeline-icon-col">
                                <div class="timeline-icon teal">
                                    <i class="fa-solid fa-stethoscope"></i>
                                </div>
                                <?php if ($index < count($records) - 1): ?>
                                <div class="timeline-line"></div>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-body">
                                <div class="timeline-card-header">
                                    <div>
                                        <div class="timeline-card-title"><?php echo e($record['visit_type'] ?? 'Consultation'); ?></div>
                                        <div class="timeline-card-doctor">
                                            <?php 
                                            $doc_name = $record['doctor_name'];
                                            $has_prefix = stripos(trim($doc_name), 'dr.') === 0;
                                            echo e($has_prefix ? $doc_name : 'Dr. ' . $doc_name); 
                                            ?> - <?php echo e($record['specialization']); ?>
                                        </div>
                                    </div>
                                    <div class="timeline-date-badge"><?php echo date('Y-m-d', strtotime($record['created_at'])); ?></div>
                                </div>

                                <div class="timeline-field-label">Diagnosis:</div>
                                <div class="timeline-field-value"><?php echo e($record['diagnosis']); ?></div>

                                <?php if (!empty($record['symptoms'])): ?>
                                <div class="timeline-field-label">Symptoms:</div>
                                <div class="timeline-field-value"><?php echo e($record['symptoms']); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($record['notes'])): ?>
                                <div class="timeline-field-label">Notes:</div>
                                <div class="timeline-field-value"><?php echo e($record['notes']); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($record['follow_up_date'])): ?>
                                <div class="timeline-field-label">Follow-up Date:</div>
                                <div class="timeline-field-value"><?php echo date('Y-m-d', strtotime($record['follow_up_date'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Simple tab switcher — no state, no localStorage
document.querySelectorAll('.mr-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.mr-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
    });
});
</script>

</body>
</html>