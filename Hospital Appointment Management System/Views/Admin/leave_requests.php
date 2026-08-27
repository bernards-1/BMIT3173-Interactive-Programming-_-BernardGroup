<?php
require_once "../../db.php";
require_once "../../Controllers/AdminController.php";

$controller = new AdminController();
$data = $controller->leaveRequests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .controls-bar { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
        .search-box { position:relative; width:360px; }
        .search-box i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; }
        .search-box input { width:100%; padding:9px 14px 9px 38px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; }
        .search-box input:focus { border-color:#2563eb; }
        .filter-select { padding:9px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#475569; background:#f8fafc; outline:none; cursor:pointer; }
        .filter-select:focus { border-color:#2563eb; }
        .action-btns { display:flex; gap:10px; align-items:center; }
        .table-container { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th { padding:14px 20px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
        td { padding:16px 20px; font-size:14px; border-bottom:1px solid #f1f5f9; color:#475569; vertical-align:middle; }
        tbody tr:hover { background:#f8fafc; }
        .doctor-name { font-weight:600; color:#0f172a; }
        .doctor-id   { font-size:12px; color:#94a3b8; margin-top:2px; }
        .reason-cell { max-width:220px; }
        .reason-type { font-weight:600; color:#1e40af; font-size:12px; margin-bottom:3px; }
        .reason-text { font-size:13px; color:#64748b; word-break:break-word; }
        /* Status badges */
        .badge-pending  { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; }
        .badge-approved { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; }
        .badge-rejected { background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; }
        /* Action buttons */
        .btn-approve { background:#10b981; color:white; border:none; padding:7px 14px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; }
        .btn-approve:hover { background:#059669; }
        .btn-reject  { background:white; border:1px solid #fca5a5; color:#ef4444; padding:7px 14px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; }
        .btn-reject:hover { background:#fee2e2; }
        /* Modal */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; }
        .modal-card { background:white; border-radius:16px; width:480px; max-width:92%; box-shadow:0 24px 48px -12px rgba(0,0,0,.2); }
        .modal-header { padding:20px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h2 { font-size:17px; font-weight:700; color:#0f172a; margin:0; }
        .close-modal { background:none; border:none; font-size:18px; color:#94a3b8; cursor:pointer; }
        .close-modal:hover { color:#475569; }
        .modal-body { padding:24px; }
        .modal-info-row { display:flex; flex-direction:column; gap:4px; margin-bottom:18px; }
        .modal-info-label { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
        .modal-info-value { font-size:14px; color:#0f172a; font-weight:500; }
        .form-group { margin-bottom:16px; }
        .form-label { font-size:13px; font-weight:600; color:#475569; display:block; margin-bottom:6px; }
        .form-control { width:100%; padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box; font-family:inherit; resize:vertical; }
        .form-control:focus { border-color:#2563eb; }
        .modal-footer { padding:16px 24px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; }
        .btn-outline { background:white; border:1px solid #e2e8f0; padding:10px 18px; border-radius:8px; font-size:14px; font-weight:600; color:#475569; cursor:pointer; }
        .btn-danger  { background:#ef4444; color:white; border:none; padding:10px 18px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-danger:hover  { background:#dc2626; }
        .btn-success { background:#10b981; color:white; border:none; padding:10px 18px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-success:hover { background:#059669; }
        .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
        .empty-state i { font-size:40px; margin-bottom:14px; display:block; color:#cbd5e1; }
    </style>
</head>
<body>
<?php include "../Layout/Admin/navigation.php"; ?>

<div class="dashboard-container">
    <div style="margin-bottom:24px;">
        <h1 style="font-size:24px;font-weight:700;color:#0f172a;margin-bottom:4px;">Leave Requests</h1>
        <p style="font-size:14px;color:#64748b;">Review and manage doctor leave applications</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div style="background:#d1fae5;color:#065f46;padding:12px 20px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-circle-check"></i> Leave request has been <?= htmlspecialchars($_GET['success']) ?> successfully.
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
        <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;">
            <div style="background:#fef3c7;color:#d97706;width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div>
                <div style="font-size:24px;font-weight:700;color:#0f172a;"><?= $data['stats']['pending'] ?></div>
                <div style="font-size:13px;color:#64748b;">Pending</div>
            </div>
        </div>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;">
            <div style="background:#d1fae5;color:#059669;width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div style="font-size:24px;font-weight:700;color:#0f172a;"><?= $data['stats']['approved'] ?></div>
                <div style="font-size:13px;color:#64748b;">Approved</div>
            </div>
        </div>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;">
            <div style="background:#fee2e2;color:#ef4444;width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fa-solid fa-ban"></i>
            </div>
            <div>
                <div style="font-size:24px;font-weight:700;color:#0f172a;"><?= $data['stats']['rejected'] ?></div>
                <div style="font-size:13px;color:#64748b;">Rejected</div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="controls-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="leaveSearch" placeholder="Search by doctor name or leave type...">
            </div>
            <div class="action-btns">
                <select id="filterStatus" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Leave Type & Reason</th>
                    <th>Duration</th>
                    <th>Days</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['leaves'])): ?>
                <tr><td colspan="7" class="empty-state">
                    <i class="fa-solid fa-plane-departure"></i>
                    No leave requests found.
                </td></tr>
                <?php else: ?>
                <?php foreach ($data['leaves'] as $leave):
                    // Parse leave type from reason "[Annual Leave] reason text"
                    preg_match('/^\[(.+?)\]\s*(.*)$/s', $leave['reason'], $m);
                    $leaveType   = $m[1] ?? 'Leave';
                    $leaveReason = trim($m[2] ?? $leave['reason']);
                    // Calculate days
                    $start = new DateTime($leave['start_date']);
                    $end   = new DateTime($leave['end_date']);
                    $days  = $start->diff($end)->days + 1;
                ?>
                <tr class="leave-row" data-status="<?= e($leave['status']) ?>" data-doctor="<?= e(strtolower($leave['doctor_name'])) ?>" data-type="<?= e(strtolower($leaveType)) ?>">
                    <td>
                        <div class="doctor-name"><?= e($leave['doctor_name']) ?></div>
                        <div class="doctor-id"><?= e($leave['doctor_id']) ?></div>
                    </td>
                    <td class="reason-cell">
                        <div class="reason-type"><?= e($leaveType) ?></div>
                        <div class="reason-text"><?= e($leaveReason) ?></div>
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="font-weight:500;color:#0f172a;"><?= date('M j', strtotime($leave['start_date'])) ?></div>
                        <div style="font-size:12px;color:#94a3b8;">to <?= date('M j, Y', strtotime($leave['end_date'])) ?></div>
                    </td>
                    <td style="font-weight:700;color:#2563eb;"><?= $days ?></td>
                    <td style="font-size:13px;color:#94a3b8;"><?= date('M j, Y', strtotime($leave['created_at'])) ?></td>
                    <td>
                        <?php if ($leave['status'] === 'Pending'): ?>
                            <span class="badge-pending">Pending</span>
                        <?php elseif ($leave['status'] === 'Approved'): ?>
                            <span class="badge-approved">Approved</span>
                        <?php else: ?>
                            <span class="badge-rejected">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($leave['status'] === 'Pending'): ?>
                        <div style="display:flex;gap:8px;">
                            <form method="POST">
                                <input type="hidden" name="action"   value="approve_leave">
                                <input type="hidden" name="leave_id" value="<?= e($leave['leave_id']) ?>">
                                <button type="submit" class="btn-approve" title="Approve">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                            </form>
                            <button class="btn-reject" title="Reject"
                                onclick="openRejectModal('<?= e($leave['leave_id']) ?>', '<?= e($leave['doctor_name']) ?>')">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </div>
                        <?php elseif ($leave['status'] === 'Rejected' && !empty($leave['reject_reason'])): ?>
                            <span style="font-size:12px;color:#94a3b8;font-style:italic;"><?= e($leave['reject_reason']) ?></span>
                        <?php else: ?>
                            <span style="color:#cbd5e1;font-size:13px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <tr id="noMatchRow" style="display:none;">
                    <td colspan="7" class="empty-state">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        No requests match your filter.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Reason Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-card">
        <form method="POST">
            <input type="hidden" name="action"   value="reject_leave">
            <input type="hidden" name="leave_id" id="rejectLeaveId">
            <div class="modal-header">
                <h2>Reject Leave Request</h2>
                <button type="button" class="close-modal" onclick="document.getElementById('rejectModal').style.display='none'">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-info-row">
                    <span class="modal-info-label">Doctor</span>
                    <span class="modal-info-value" id="rejectDoctorName">—</span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="rejectReason">Reason for Rejection <span style="color:#ef4444;">*</span></label>
                    <textarea id="rejectReason" name="reject_reason" class="form-control" rows="4"
                              placeholder="Provide a clear reason so the doctor knows why their request was rejected..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="document.getElementById('rejectModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-danger"><i class="fa-solid fa-ban"></i> Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    const searchInput  = document.getElementById('leaveSearch');
    const filterStatus = document.getElementById('filterStatus');
    const rows         = document.querySelectorAll('.leave-row');
    const noMatchRow   = document.getElementById('noMatchRow');

    function applyFilter() {
        const term   = searchInput.value.toLowerCase();
        const status = filterStatus.value;
        let visible  = 0;

        rows.forEach(row => {
            const doctor = row.dataset.doctor || '';
            const type   = row.dataset.type   || '';
            const rowSt  = row.dataset.status || '';

            const matchSearch = doctor.includes(term) || type.includes(term);
            const matchStatus = (status === 'all') || (rowSt === status);

            if (matchSearch && matchStatus) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        noMatchRow.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    }

    searchInput.addEventListener('input', applyFilter);
    filterStatus.addEventListener('change', applyFilter);

    function openRejectModal(leaveId, doctorName) {
        document.getElementById('rejectLeaveId').value   = leaveId;
        document.getElementById('rejectDoctorName').textContent = doctorName;
        document.getElementById('rejectReason').value   = '';
        document.getElementById('rejectModal').style.display = 'flex';
    }

    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
</script>
</body>
</html>
