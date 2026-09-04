<?php
require_once "../../db.php";
require_once "../../Controllers/AdminController.php";

$controller = new AdminController();
$data = $controller->patients();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .btn-primary { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #1d4ed8; }
        .controls-bar { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #ffffff; border-bottom: 1px solid #e2e8f0; }
        .search-box { position: relative; width: 400px; }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; outline: none; box-sizing: border-box; }
        .search-box input:focus { border-color: #2563eb; }
        .action-btns { display: flex; gap: 12px; align-items: center; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #475569; cursor: pointer; }
        .table-container { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 16px 24px; font-size: 13px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 24px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #475569; vertical-align: middle; }
        tbody tr:hover { background-color: #f8fafc; }
        .patient-name { font-weight: 600; color: #0f172a; display: block; margin-bottom: 2px; }
        .patient-email { font-size: 13px; color: #64748b; }
        .ic-text { font-family: monospace; color: #64748b; font-size: 13px; }
        .action-icons { display: flex; gap: 16px; color: #64748b; align-items: center; }
        .action-icons i { cursor: pointer; font-size: 15px; }
        .action-icons i:hover { color: #2563eb; }
        .delete-btn { background: none; border: none; color: #64748b; cursor: pointer; padding: 0; font-size: 15px; display: flex; }
        .delete-btn:hover { color: #ef4444; }
        .filter-select { padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #475569; background: #f8fafc; outline: none; cursor: pointer; }
        .filter-select:focus { border-color: #2563eb; }
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-card { background: white; border-radius: 16px; width: 560px; max-width: 92%; box-shadow: 0 24px 48px -12px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; }
        .close-modal { background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; line-height: 1; }
        .close-modal:hover { color: #475569; }
        .modal-body { padding: 24px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .detail-group { display: flex; flex-direction: column; gap: 4px; }
        .detail-group.full { grid-column: span 2; }
        .detail-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; }
        .detail-value { font-size: 15px; color: #0f172a; font-weight: 500; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; }
    </style>
</head>
<body>

<?php include "../Layout/Admin/navigation.php"; ?>

<div class="dashboard-container">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Patient Management</h1>
        <p style="font-size: 14px; color: #64748b;">Manage and view all patient records</p>
    </div>

    <div class="table-container">
        <div class="controls-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="patientSearch" placeholder="Search by name, IC, or email...">
            </div>
            <div class="action-btns">
                <select id="filterGender" class="filter-select">
                    <option value="all">All Genders</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>IC No</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data["patients"] as $pat): ?>
                <tr class="patient-row">
                    <td><?= e($pat["patient_id"]) ?></td>
                    <td>
                        <span class="patient-name"><?= e($pat["full_name"]) ?></span>
                        <span class="patient-email"><?= e($pat["user_email"]) ?></span>
                    </td>
                    <td class="ic-text"><?= e($pat["ic"] ?? "N/A") ?></td>
                    <td>
                        <?php
                        $dob = new DateTime($pat["date_of_birth"]);
                        $now = new DateTime();
                        echo $now->diff($dob)->y;
                        ?>
                    </td>
                    <td class="patient-gender"><?= e($pat["gender"]) ?></td>
                    <td><?= e($pat["phone"]) ?></td>
                    <td class="action-icons">
                        <i class="fa-regular fa-eye" title="View"
                           onclick="openPatientModal(<?= htmlspecialchars(json_encode($pat), ENT_QUOTES, 'UTF-8') ?>)"></i>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Are you sure you want to delete this patient?');">
                            <input type="hidden" name="action" value="delete_patient">
                            <input type="hidden" name="patient_id" value="<?= e($pat["patient_id"]) ?>">
                            <button type="submit" class="delete-btn" title="Delete">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($data["patients"])): ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No patients found.</td></tr>
                <?php endif; ?>
                <tr id="noMatchRow" style="display:none;">
                    <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No patients match your search criteria.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- View Patient Modal -->
<div id="patientModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Patient Details</h2>
            <button type="button" class="close-modal"
                    onclick="document.getElementById('patientModal').style.display='none'">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-group full">
                    <span class="detail-label">Full Name</span>
                    <span class="detail-value" id="v_full_name">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Email</span>
                    <span class="detail-value" id="v_email">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">IC Number</span>
                    <span class="detail-value" id="v_ic">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Date of Birth</span>
                    <span class="detail-value" id="v_dob">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value" id="v_phone">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Gender</span>
                    <span class="detail-value" id="v_gender">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Patient ID</span>
                    <span class="detail-value" id="v_patient_id">—</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Blood Type</span>
                    <span class="detail-value" id="v_blood_type">—</span>
                </div>
                <div class="detail-group full">
                    <span class="detail-label">Appointment Summary</span>
                    <span class="detail-value" id="v_appt_summary">—</span>
                </div>
                <div class="detail-group full">
                    <span class="detail-label">Recent Medical Records</span>
                    <span class="detail-value" id="v_recent_records">—</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline"
                    onclick="document.getElementById('patientModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
    const searchInput  = document.getElementById('patientSearch');
    const filterGender = document.getElementById('filterGender');
    const patientRows  = document.querySelectorAll('.patient-row');
    const noMatchRow   = document.getElementById('noMatchRow');

    function applyFilter() {
        const term   = searchInput.value.toLowerCase();
        const gender = filterGender.value;
        let visible  = 0;

        patientRows.forEach(row => {
            const name   = row.querySelector('.patient-name').textContent.toLowerCase();
            const email  = row.querySelector('.patient-email').textContent.toLowerCase();
            const ic     = row.querySelector('.ic-text').textContent.toLowerCase();
            const g      = row.querySelector('.patient-gender').textContent.trim();

            const ok = (name.includes(term) || email.includes(term) || ic.includes(term))
                    && (gender === 'all' || g === gender);

            row.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        noMatchRow.style.display = (visible === 0 && patientRows.length > 0) ? '' : 'none';
    }

    searchInput.addEventListener('input', applyFilter);
    filterGender.addEventListener('change', applyFilter);

    function openPatientModal(p) {
        document.getElementById('v_full_name').textContent  = p.full_name    || '—';
        document.getElementById('v_email').textContent      = p.user_email   || '—';
        document.getElementById('v_ic').textContent         = p.ic           || '—';
        document.getElementById('v_dob').textContent        = p.date_of_birth|| '—';
        document.getElementById('v_phone').textContent      = p.phone        || '—';
        document.getElementById('v_gender').textContent     = p.gender       || '—';
        document.getElementById('v_patient_id').textContent = p.patient_id   || '—';
        document.getElementById('patientModal').style.display = 'flex';

        loadPatientSummary(p.user_id);
    }

    function loadPatientSummary(userId) {
        const bloodEl   = document.getElementById('v_blood_type');
        const summaryEl = document.getElementById('v_appt_summary');
        const recordsEl = document.getElementById('v_recent_records');
        bloodEl.textContent = 'Loading...';
        summaryEl.textContent = 'Loading...';
        recordsEl.textContent = 'Loading...';

        if (!userId) {
            bloodEl.textContent = '—';
            summaryEl.textContent = 'Unavailable — missing user reference.';
            recordsEl.textContent = '—';
            return;
        }

        const requestID = 'REQ-' + Date.now().toString(16);
        const requestTimestamp = new Date().toISOString();
        const url = `../../api/patient_summary.php?userId=${encodeURIComponent(userId)}`
                  + `&requestID=${encodeURIComponent(requestID)}`
                  + `&timestamp=${encodeURIComponent(requestTimestamp)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'S') {
                    bloodEl.textContent = data.patientDetails.bloodType || 'Not on file';

                    const counts = data.patientDetails.appointmentCounts || {};
                    const countEntries = Object.entries(counts).filter(([, v]) => v > 0);
                    summaryEl.textContent = countEntries.length
                        ? countEntries.map(([k, v]) => `${k}: ${v}`).join(', ')
                        : 'No appointments recorded yet.';

                    const recs = data.patientDetails.recentRecords || [];
                    recordsEl.textContent = recs.length
                        ? recs.map(r => `${r.diagnosis} (${r.created_at})`).join('; ')
                        : 'No medical records yet.';
                } else {
                    // F or E status — surfaces the web service's own error message
                    bloodEl.textContent = '—';
                    summaryEl.textContent = data.message || 'Patient summary unavailable.';
                    recordsEl.textContent = '—';
                }
            })
            .catch(() => {
                bloodEl.textContent = '—';
                summaryEl.textContent = 'Patient Module service unavailable. Please try again later.';
                recordsEl.textContent = '—';
            });
    }

    document.getElementById('patientModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
</script>

</body>
</html>
