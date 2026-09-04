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
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$patientRepository = new PatientRepository($pdo);

// Get patient_id from session
$user_id = $_SESSION['user']['user_id'] ?? null;
$patient = $user_id ? $patientRepository->getPatientByUserId($user_id) : null;
$patient_id = $patient ? $patient['patient_id'] : null;

// Fetch all prescriptions for this patient
$prescriptions = [];
$total_prescriptions = 0;

if ($patient_id) {
    $prescriptions = $patientRepository->getPrescriptionsByPatientId($patient_id);
    $total_prescriptions = count($prescriptions);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - MediCare</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Patient/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .prescription-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .prescription-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: var(--slate-300);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            background: #eff6ff;
            color: #2563eb;
        }
        .stat-banner {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
</head>
<body style="background-color: var(--slate-50);">

<?php include '../Layout/Patient/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Page Header -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: var(--slate-900);">My Prescriptions</h1>
        <p style="font-size: 14px; color: var(--slate-500); margin-top: 4px;">View active medications and prescription history issued by doctors</p>
    </div>

    <!-- Summary Stats -->
    <div class="stat-banner">
        <div class="stat-item">
            <div class="stat-icon-wrapper" style="background: #eff6ff; color: #3b82f6;">
                <i class="fa-solid fa-capsules"></i>
            </div>
            <div>
                <div style="font-size: 20px; font-weight: 700; color: var(--slate-800);"><?= $total_prescriptions ?></div>
                <div style="font-size: 12px; color: var(--slate-400);">Total Prescriptions</div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon-wrapper" style="background: #e0e7ff; color: #4338ca;">
                <i class="fa-solid fa-file-medical"></i>
            </div>
            <div>
                <div style="font-size: 20px; font-weight: 700; color: var(--slate-800);"><?= $total_prescriptions > 0 ? 'Active' : 'None' ?></div>
                <div style="font-size: 12px; color: var(--slate-400);">Prescription Status</div>
            </div>
        </div>
    </div>

    <!-- Prescription List -->
    <div>
        <?php if (empty($prescriptions)): ?>
            <div style="background: white; border: 1px solid var(--slate-200); border-radius: 12px; padding: 48px 20px; text-align: center;">
                <i class="fa-solid fa-capsules" style="font-size: 48px; color: var(--slate-300); margin-bottom: 16px; display: block;"></i>
                <h3 style="font-size: 16px; font-weight: 600; color: var(--slate-700); margin-bottom: 4px;">No Prescriptions Found</h3>
                <p style="font-size: 13px; color: var(--slate-400);">You have not been issued any prescriptions yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($prescriptions as $p): ?>
                <?php 
                    $created_date = !empty($p['created_at']) ? date('M j, Y', strtotime($p['created_at'])) : 'N/A';
                ?>
                <div class="prescription-card">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 42px; height: 42px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                                <i class="fa-solid fa-pills"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 16px; font-weight: 700; color: var(--slate-900); margin: 0;">
                                    <?= e($p['brand_name'] ?: ($p['generic_name'] ?: 'Prescription Item')) ?>
                                </h3>
                                <?php if (!empty($p['generic_name'])): ?>
                                    <div style="font-size: 12px; color: var(--slate-400); margin-top: 2px;">Generic: <?= e($p['generic_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge">
                                <i class="fa-solid fa-prescription"></i> Active Prescription
                            </span>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; background: var(--slate-50); border: 1px solid var(--slate-100); border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                        <div>
                            <div style="font-size: 11px; color: var(--slate-400); text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">Dosage & Frequency</div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--slate-700);">
                                <?= e($p['dosage'] ?: 'N/A') ?> <?= !empty($p['frequency']) ? '· ' . e($p['frequency']) : '' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--slate-400); text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">Duration</div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--slate-700);"><?= e($p['duration'] ?: 'N/A') ?></div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--slate-400); text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">Quantity</div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--slate-700);"><?= e($p['quantity'] ?: '1') ?> unit(s)</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--slate-400); text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">Prescribed Date</div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--slate-700);"><?= $created_date ?></div>
                        </div>
                    </div>

                    <?php if (!empty($p['instructions'])): ?>
                        <div style="font-size: 13px; color: var(--slate-600); margin-bottom: 12px; display: flex; align-items: flex-start; gap: 6px;">
                            <i class="fa-solid fa-circle-info" style="color: #3b82f6; margin-top: 2px;"></i>
                            <span><strong>Instructions:</strong> <?= e($p['instructions']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--slate-100); padding-top: 12px; margin-top: 8px; font-size: 12px; color: var(--slate-400);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: <?= e($p['color'] ?: '#3b82f6') ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">
                                <?= e($p['initials'] ?: 'Dr') ?>
                            </div>
                            <span>Prescribed by <strong>Dr. <?= e($p['doctor_name'] ?: 'Unknown') ?></strong> (<?= e($p['specialization'] ?: 'General') ?>)</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
