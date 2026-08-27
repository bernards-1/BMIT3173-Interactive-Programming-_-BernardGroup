<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$data = $controller->pharmacists();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Management - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-primary { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        
        /* === 新增的顶部布局区域 === */
        .tab-switcher { display: inline-flex; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; margin-bottom: 24px; }
        .tab-btn { padding: 8px 16px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: #64748b; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { background: #f8fafc; color: #0f172a; }
        .badge-orange { background: #f59e0b; color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 11px; display: flex; align-items: center; justify-content: center; }
        
        .status-summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; width: 100%; }
        .summary-card { padding: 32px 24px; border-radius: 12px; text-align: center; border: 1px solid; }
        .card-green { background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; }
        .card-yellow { background: #fffbeb; border-color: #fde68a; color: #f59e0b; }
        .card-blue { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
        .summary-num { font-size: 36px; font-weight: 700; margin-bottom: 8px; line-height: 1; }
        .summary-label { font-size: 14px; font-weight: 500; }

        .search-bar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 24px; display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
        .search-bar input { border: none; outline: none; font-size: 15px; width: 100%; color: #0f172a;}
        .search-bar input::placeholder { color: #94a3b8; }
        .search-bar i { color: #94a3b8; font-size: 18px; }
        /* ======================== */

        .staff-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .staff-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        .staff-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .staff-profile { display: flex; gap: 16px; align-items: center; }
        .staff-avatar { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: white; }
        .staff-name { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .staff-id { font-size: 13px; color: #94a3b8; }
        
        .status-pill { padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #065f46; }

        .staff-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 8px; }
        .stat-row { display: flex; justify-content: space-between; font-size: 13px; }
        .stat-label { color: #64748b; }
        .stat-val { font-weight: 600; color: #0f172a; }

        .staff-contact { border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; padding: 16px 0; margin-bottom: 16px; display: flex; flex-direction: column; gap: 8px; }
        .contact-item { display: flex; align-items: center; gap: 12px; font-size: 13px; color: #64748b; }
        
        .staff-actions { display: flex; gap: 12px; }
        .btn-view { flex-grow: 1; padding: 10px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .btn-delete { padding: 10px 14px; background: #fff5f5; border: 1px solid #ffe4e6; color: #ef4444; border-radius: 8px; cursor: pointer; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-card { background: white; border-radius: 12px; width: 600px; max-width: 90%; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 18px; font-weight: 600; }
        .close-modal { background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer; }
        .modal-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full { grid-column: span 2; }
        .form-label { font-size: 14px; font-weight: 500; color: #0f172a; }
        .form-control { padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>

<?php include '../Layout/Admin/navigation.php'; ?>

<div class="dashboard-container">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Pharmacist Management</h1>
        <p style="font-size: 14px; color: #64748b;">Manage pharmacy staff profiles</p>
    </div>

    <!-- 顶层卡片与搜索 -->

    <div id="pharmacistsSection">
        <div class="status-summary-cards">
            <div class="summary-card card-green">
                <div class="summary-num"><?= count($data['pharmacists']) ?></div>
                <div class="summary-label">On Duty</div>
            </div>
            <div class="summary-card card-yellow">
                <div class="summary-num">1</div>
                <div class="summary-label">On Leave</div>
            </div>
            <div class="summary-card card-blue">
                <div class="summary-num">0</div>
                <div class="summary-label">Off Duty</div>
            </div>
        </div>

        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="pharmaSearch" placeholder="Search pharmacists by name, ID, or shift...">
        </div>
        <!-- /顶层卡片与搜索 -->

        <!-- Pharmacists Grid -->
        <div class="staff-grid" id="pharmaGrid">
            <?php foreach ($data['pharmacists'] as $pharma): ?>
            <div class="staff-card" data-name="<?= strtolower(e($pharma['full_name'])) ?>" data-id="<?= strtolower(e($pharma['pharmacist_id'])) ?>" data-ic="<?= strtolower(e($pharma['ic'] ?? '')) ?>">
                <div class="staff-header">
                    <div class="staff-profile">
                        <div class="staff-avatar" style="background: #a855f7;"><?= strtoupper(substr($pharma['full_name'], 0, 2)) ?></div>
                        <div>
                            <div class="staff-name"><?= e($pharma['full_name']) ?></div>
                            <div class="staff-id"><?= e($pharma['pharmacist_id']) ?></div>
                        </div>
                    </div>
                    <span class="status-pill">On Duty</span>
                </div>
                
                <div class="staff-stats">
                    <div class="stat-row full"><span class="stat-label">IC No</span><span class="stat-val" style="font-family:monospace;"><?= e($pharma['ic'] ?? 'N/A') ?></span></div>
                    <div class="stat-row"><span class="stat-label">Role</span><span class="stat-val"><?= e($pharma['qualification']) ?></span></div>
                    <div class="stat-row"><span class="stat-label">License</span><span class="stat-val"><?= e($pharma['license_number']) ?></span></div>
                </div>
                
                <div class="staff-contact">
                    <div class="contact-item"><i class="fa-solid fa-phone"></i> <?= e($pharma['phone']) ?></div>
                    <div class="contact-item"><i class="fa-regular fa-envelope"></i> <?= e($pharma['user_email']) ?></div>
                </div>
                
                <div class="staff-actions">
                    <button class="btn-view" onclick='viewProfile(<?= json_encode([
                        "name" => e($pharma["full_name"]),
                        "id" => e($pharma["pharmacist_id"]),
                        "ic" => e($pharma["ic"] ?? "N/A"),
                        "qualification" => e($pharma["qualification"]),
                        "license" => e($pharma["license_number"]),
                        "phone" => e($pharma["phone"]),
                        "email" => e($pharma["user_email"]),
                        "initials" => strtoupper(substr($pharma["full_name"], 0, 2)),
                        "color" => "#a855f7"
                    ]) ?>)'><i class="fa-regular fa-eye"></i> View Profile</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this pharmacist?');">
                        <input type="hidden" name="action" value="delete_pharmacist">
                        <input type="hidden" name="pharmacist_id" value="<?= e($pharma['pharmacist_id']) ?>">
                        <button type="submit" class="btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($data['pharmacists'])): ?>
                <div style="grid-column: span 2; text-align: center; color: #64748b; padding: 40px;">No pharmacists found.</div>
            <?php endif; ?>
        </div>
    </div>


<!-- Profile Modal -->
<div id="profileModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Pharmacist Profile</h2>
            <button class="close-modal" onclick="document.getElementById('profileModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px;">
                <div id="modalAvatar" style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 600; color: white;"></div>
                <div>
                    <h3 id="modalName" style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 4px;"></h3>
                    <p id="modalId" style="font-size: 14px; color: #64748b;"></p>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <div id="modalRole" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">License Number</label>
                    <div id="modalLicense" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">IC Number</label>
                    <div id="modalIc" class="form-control" style="background: #f8fafc; font-family: monospace;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Phone') ?></label>
                    <div id="modalPhone" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group full">
                    <label class="form-label"><?= __('Email') ?></label>
                    <div id="modalEmail" class="form-control" style="background: #f8fafc;"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" onclick="document.getElementById('profileModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
    // Search Filtering
    const searchInput = document.getElementById('pharmaSearch');
    const staffCards = document.querySelectorAll('.staff-card');

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        staffCards.forEach(card => {
            const name = card.getAttribute('data-name');
            const id = card.getAttribute('data-id');
            const ic = card.getAttribute('data-ic');

            if (name.includes(term) || id.includes(term) || ic.includes(term)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // View Profile Modal
    function viewProfile(pharma) {
        document.getElementById('modalAvatar').style.background = pharma.color;
        document.getElementById('modalAvatar').textContent = pharma.initials;
        document.getElementById('modalName').textContent = pharma.name;
        document.getElementById('modalId').textContent = pharma.id;
        document.getElementById('modalRole').textContent = pharma.qualification;
        document.getElementById('modalLicense').textContent = pharma.license;
        document.getElementById('modalIc').textContent = pharma.ic;
        document.getElementById('modalPhone').textContent = pharma.phone;
        document.getElementById('modalEmail').textContent = pharma.email;

        document.getElementById('profileModal').style.display = 'flex';
    }

    document.getElementById('profileModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
</script>

</body>
</html>