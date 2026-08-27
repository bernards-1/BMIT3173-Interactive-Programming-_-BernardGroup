<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$data = $controller->appointments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-primary { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        
        /* 顶部四个数据卡片 */
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
        .sum-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; display: flex; justify-content: space-between; align-items: center; }
        .sum-info p { font-size: 14px; color: #64748b; font-weight: 500; margin-bottom: 8px; }
        .sum-info h3 { font-size: 28px; font-weight: 700; color: #0f172a; }
        .sum-icon { font-size: 24px; }
        
        /* 搜索和过滤栏 */
        .controls-bar { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .search-box { position: relative; width: 400px; }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; outline: none; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #475569; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        
        /* 表格区域与 Tabs */
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
        .table-header h2 { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        
        .tabs { display: flex; gap: 8px; }
        .tab { padding: 6px 16px; border-radius: 999px; font-size: 14px; font-weight: 500; cursor: pointer; border: 1px solid transparent; }
        .tab.active { background: #f8fafc; border-color: #e2e8f0; color: #0f172a; }
        .tab:not(.active) { color: #64748b; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 16px 24px; font-size: 13px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        td { padding: 16px 24px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #475569; }
        
        .patient-name { font-weight: 600; color: #0f172a; display: block; }
        .patient-id { font-size: 12px; color: #94a3b8; }
        
        .status-pill { padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; }
        .pill-confirmed { background: #d1fae5; color: #065f46; }
        .pill-pending { background: #fef3c7; color: #b45309; }
        .pill-completed { background: #e0e7ff; color: #3730a3; }
        .pill-cancelled { background: #fee2e2; color: #b91c1c; }
        .pill-expired { background: #e2e8f0; color: #475569; }
        
        .action-btns { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; font-size: 13px; cursor: pointer; color: #0f172a; }
        .btn-sm:hover { background: #f8fafc; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-card { background: white; border-radius: 12px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .close-modal { background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer; }
        .modal-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full { grid-column: span 2; }
        .form-label { font-size: 14px; font-weight: 500; color: #0f172a; }
        .form-control { padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
        .form-control:focus { border-color: #2563eb; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
    </style>
</head>
<body>

<?php include '../Layout/Admin/navigation.php'; ?>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Appointment Management</h1>
            <p style="font-size: 14px; color: #64748b;">Manage and schedule patient appointments</p>
        </div>
        <button class="btn-primary" onclick="document.getElementById('scheduleModal').style.display='flex'">
            <i class="fa-regular fa-calendar-plus"></i> Schedule Appointment
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#d1fae5;color:#065f46;font-weight:500;">
            <i class="fa-solid fa-circle-check"></i> <?= e($_GET['success']) ?>
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#b91c1c;font-weight:500;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-info"><p>Today's Appointments</p><h3><?= $data['stats']['today'] ?></h3></div>
            <div class="sum-icon" style="color: #3b82f6;"><i class="fa-regular fa-calendar"></i></div>
        </div>
        <div class="sum-card">
            <div class="sum-info"><p>Scheduled</p><h3><?= $data['stats']['scheduled'] ?></h3></div>
            <div class="sum-icon" style="color: #f59e0b;"><i class="fa-regular fa-clock"></i></div>
        </div>
        <div class="sum-card">
            <div class="sum-info"><p><?= __('Completed') ?></p><h3><?= $data['stats']['completed'] ?></h3></div>
            <div class="sum-icon" style="color: #10b981;"><i class="fa-regular fa-user"></i></div>
        </div>
        <div class="sum-card">
            <div class="sum-info"><p><?= __('Cancelled') ?></p><h3><?= $data['stats']['cancelled'] ?></h3></div>
            <div class="sum-icon" style="color: #a855f7;"><i class="fa-solid fa-stethoscope"></i></div>
        </div>
    </div>

    <div class="controls-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="appointmentSearch" placeholder="Search by patient, doctor, or appointment ID...">
        </div>
        <button class="btn-outline" onclick="document.getElementById('filterModal').style.display='flex'"><i class="fa-solid fa-filter"></i> Filters</button>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>All Appointments</h2>
            <div class="tabs">
                <div class="tab active" id="tabAll" onclick="filterTab('All')">All</div>
                <div class="tab" id="tabScheduled" onclick="filterTab('Scheduled')">Scheduled</div>
                <div class="tab" id="tabCompleted" onclick="filterTab('Completed')"><?= __('Completed') ?></div>
                <div class="tab" id="tabCancelled" onclick="filterTab('Cancelled')"><?= __('Cancelled') ?></div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th><?= __('Patient') ?></th><th><?= __('Doctor') ?></th><th><?= __('Date') ?></th><th><?= __('Time') ?></th><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="appointmentTableBody">
                <?php foreach ($data['appointments'] as $apt): 
                    $statusClass = match ($apt['status']) {
                        'Scheduled' => 'pill-confirmed',
                        'Completed' => 'pill-completed',
                        'Cancelled' => 'pill-cancelled',
                        'Expired' => 'pill-expired',
                        default => 'pill-pending'
                    };
                    $statusText = $apt['status'];
                ?>
                <tr class="appointment-row" data-status="<?= e($apt['status']) ?>" data-date="<?= e($apt['appointment_date']) ?>">
                    <td class="apt-id"><?= e($apt['appointment_id']) ?></td>
                    <td>
                        <span class="patient-name apt-patient"><?= e($apt['patient_name']) ?></span>
                        <span class="patient-id"><?= e($apt['patient_id']) ?></span>
                    </td>
                    <td class="apt-doctor"><?= e($apt['doctor_name']) ?></td>
                    <td class="apt-date"><?= e(formatDate($apt['appointment_date'])) ?></td>
                    <td class="apt-time"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></td>
                    <td class="apt-type"><?= e($apt['reason']) ?></td>
                    <td>
                        <span class="status-pill <?= $statusClass ?>"><?= $statusText ?></span>
                    </td>
                    <td class="action-btns">
                        <button class="btn-sm" onclick='openViewEditModal(<?= json_encode([
                            "id" => e($apt["appointment_id"]),
                            "patient_name" => e($apt["patient_name"]),
                            "patient_id" => e($apt["patient_id"]),
                            "doctor_id" => e($apt["doctor_id"]),
                            "doctor_name" => e($apt["doctor_name"]),
                            "date" => e($apt["appointment_date"]),
                            "time" => date("H:i", strtotime($apt["appointment_time"])),
                            "reason" => e($apt["reason"]),
                            "status" => e($apt["status"])
                        ]) ?>, true)'>View</button>
                        <button class="btn-sm" onclick='openViewEditModal(<?= json_encode([
                            "id" => e($apt["appointment_id"]),
                            "patient_name" => e($apt["patient_name"]),
                            "patient_id" => e($apt["patient_id"]),
                            "doctor_id" => e($apt["doctor_id"]),
                            "doctor_name" => e($apt["doctor_name"]),
                            "date" => e($apt["appointment_date"]),
                            "time" => date("H:i", strtotime($apt["appointment_time"])),
                            "reason" => e($apt["reason"]),
                            "status" => e($apt["status"])
                        ]) ?>, false)'><?= __('Edit') ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($data['appointments'])): ?>
                    <tr id="noDataRow"><td colspan="8" style="text-align:center;">No appointments found.</td></tr>
                <?php endif; ?>
                <tr id="noMatchRow" style="display:none;"><td colspan="8" style="text-align:center;">No appointments match your search/filter criteria.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="modal-overlay">
    <div class="modal-card">
        <form method="POST">
            <input type="hidden" name="action" value="create_appointment">
            <input type="hidden" name="csrf_token" value="<?= e($data['csrfToken']) ?>">
        <div class="modal-header">
            <h2>Schedule New Appointment</h2>
            <button class="close-modal" onclick="document.getElementById('scheduleModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="new-patient">Patient</label>
                    <select id="new-patient" name="patient_id" class="form-control" required>
                        <option value="">Select patient</option>
                        <?php foreach ($data['patients'] as $patient): ?>
                            <option value="<?= e($patient['patient_id']) ?>"><?= e($patient['full_name']) ?> (<?= e($patient['patient_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="new-doctor"><?= __('Doctor') ?></label>
                    <select id="new-doctor" name="doctor_id" class="form-control" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($data['doctors'] as $doctor): ?>
                            <option value="<?= e($doctor['doctor_id']) ?>"><?= e($doctor['name']) ?> (<?= e($doctor['specialization']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Date') ?></label>
                    <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Time') ?></label>
                    <input type="time" name="appointment_time" class="form-control" required>
                </div>
                <div class="form-group full">
                    <label class="form-label">Reason for Visit</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Follow-up consultation" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="document.getElementById('scheduleModal').style.display='none'"><?= __('Cancel') ?></button>
            <button type="submit" class="btn-primary">Schedule</button>
        </div>
        </form>
    </div>
</div>

<!-- View/Edit Modal -->
<div id="viewEditModal" class="modal-overlay">
    <div class="modal-card">
        <form method="POST">
            <input type="hidden" name="action" value="update_appointment">
            <input type="hidden" name="csrf_token" value="<?= e($data['csrfToken']) ?>">
            <input type="hidden" name="appointment_id" id="ve-appointment-id">
            <input type="hidden" name="patient_id" id="ve-patient-id">
            <input type="hidden" name="doctor_id" id="ve-doctor-id">
        <div class="modal-header">
            <h2 id="viewEditModalTitle">View Appointment</h2>
            <button class="close-modal" onclick="document.getElementById('viewEditModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Appointment ID</label>
                    <input type="text" id="ve-id" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Status') ?></label>
                    <select id="ve-status" name="status" class="form-control">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed"><?= __('Completed') ?></option>
                        <option value="Cancelled"><?= __('Cancelled') ?></option>
                        <option value="Expired">Expired</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Patient') ?></label>
                    <input type="text" id="ve-patient" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Doctor') ?></label>
                    <input type="text" id="ve-doctor" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Date') ?></label>
                    <input type="date" id="ve-date" name="appointment_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Time') ?></label>
                    <input type="time" id="ve-time" name="appointment_time" class="form-control" required>
                </div>
                <div class="form-group full">
                    <label class="form-label">Reason for Visit</label>
                    <input type="text" id="ve-reason" name="reason" class="form-control" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="document.getElementById('viewEditModal').style.display='none'">Close</button>
            <button type="submit" class="btn-primary" id="ve-saveBtn">Save Changes</button>
        </div>
        </form>
    </div>
</div>

<!-- Filter Modal -->
<div id="filterModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Filter Appointments</h2>
            <button class="close-modal" onclick="document.getElementById('filterModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label">Date Range</label>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <input type="date" id="filterDateFrom" class="form-control" style="flex: 1;">
                        <span style="color: #64748b;">to</span>
                        <input type="date" id="filterDateTo" class="form-control" style="flex: 1;">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" onclick="resetFilter()">Reset</button>
            <button class="btn-primary" onclick="applyFilter()">Apply</button>
        </div>
    </div>
</div>

<script>
    // Tab and Search Filtering
    let currentTab = 'All';
    let filterFrom = '';
    let filterTo = '';
    
    function filterTab(tab) {
        document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
        document.getElementById('tab' + tab).classList.add('active');
        currentTab = tab;
        executeSearchAndFilter();
    }

    const searchInput = document.getElementById('appointmentSearch');
    searchInput.addEventListener('input', executeSearchAndFilter);

    function applyFilter() {
        filterFrom = document.getElementById('filterDateFrom').value;
        filterTo = document.getElementById('filterDateTo').value;
        document.getElementById('filterModal').style.display = 'none';
        executeSearchAndFilter();
    }

    function resetFilter() {
        filterFrom = '';
        filterTo = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        document.getElementById('filterModal').style.display = 'none';
        executeSearchAndFilter();
    }

    function executeSearchAndFilter() {
        const term = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('.appointment-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const id = row.querySelector('.apt-id').textContent.toLowerCase();
            const patient = row.querySelector('.apt-patient').textContent.toLowerCase();
            const doctor = row.querySelector('.apt-doctor').textContent.toLowerCase();
            const date = row.dataset.date;

            const matchTab = (currentTab === 'All' || status === currentTab);
            const matchSearch = (id.includes(term) || patient.includes(term) || doctor.includes(term));
            
            let matchDate = true;
            if (filterFrom && date < filterFrom) matchDate = false;
            if (filterTo && date > filterTo) matchDate = false;

            if (matchTab && matchSearch && matchDate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noMatchRow = document.getElementById('noMatchRow');
        if (noMatchRow) {
            noMatchRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    // View/Edit Modal Logic
    function openViewEditModal(data, isReadOnly) {
        document.getElementById('viewEditModalTitle').textContent = isReadOnly ? 'View Appointment' : 'Edit Appointment';
        
        document.getElementById('ve-id').value = data.id;
        document.getElementById('ve-appointment-id').value = data.id;
        document.getElementById('ve-patient-id').value = data.patient_id;
        document.getElementById('ve-doctor-id').value = data.doctor_id;
        document.getElementById('ve-status').value = data.status;
        document.getElementById('ve-patient').value = data.patient_name + ' (' + data.patient_id + ')';
        document.getElementById('ve-doctor').value = data.doctor_name;
        document.getElementById('ve-date').value = data.date;
        document.getElementById('ve-time').value = data.time;
        document.getElementById('ve-reason').value = data.reason;

        // Toggle read-only
        const fields = ['ve-status', 've-date', 've-time', 've-reason'];
        fields.forEach(field => {
            document.getElementById(field).disabled = isReadOnly;
        });

        document.getElementById('ve-saveBtn').style.display = isReadOnly ? 'none' : 'inline-block';
        document.getElementById('viewEditModal').style.display = 'flex';
    }
</script>

</body>
</html>
