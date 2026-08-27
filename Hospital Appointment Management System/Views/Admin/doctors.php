<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$data = $controller->doctors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-primary { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        
        .status-summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; width: 100%; }
        .summary-card { padding: 32px 24px; border-radius: 12px; text-align: center; border: 1px solid; }
        .card-green { background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; }
        .card-yellow { background: #fffbeb; border-color: #fde68a; color: #f59e0b; }
        .card-blue { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
        .summary-num { font-size: 36px; font-weight: 700; margin-bottom: 8px; line-height: 1; }
        .summary-label { font-size: 14px; font-weight: 500; }

        .search-bar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 24px; display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
        .search-bar input { border: none; outline: none; font-size: 15px; width: 100%; color: #0f172a; }
        .search-bar input::placeholder { color: #94a3b8; }
        .search-bar i { color: #94a3b8; font-size: 18px; }
        /* ======================== */

        .doctors-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .doc-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .doc-profile { display: flex; gap: 16px; align-items: center; }
        .doc-avatar { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: white; }
        .doc-name { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .doc-id { font-size: 13px; color: #94a3b8; }
        
        .status-pill { padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #065f46; }

        .doc-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 8px; }
        .stat-row { display: flex; justify-content: space-between; font-size: 13px; }
        .stat-label { color: #64748b; }
        .stat-val { font-weight: 600; color: #0f172a; }

        .doc-contact { border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; padding: 16px 0; margin-bottom: 16px; display: flex; flex-direction: column; gap: 8px; }
        .contact-item { display: flex; align-items: center; gap: 12px; font-size: 13px; color: #64748b; }
        
        .doc-actions { display: flex; gap: 12px; }
        .btn-view { flex-grow: 1; padding: 10px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .btn-delete { padding: 10px 14px; background: #fff5f5; border: 1px solid #ffe4e6; color: #ef4444; border-radius: 8px; cursor: pointer; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-card { background: white; border-radius: 12px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
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
    <div class="header-actions">
        <div style="display:flex; flex-direction:column;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Doctor Management</h1>
            <p style="font-size: 14px; color: #64748b;">Manage hospital doctor profiles and schedules</p>
        </div>
        <button class="btn-primary" onclick="openAddDoctorModal()"><i class="fa-solid fa-user-plus"></i> Add Doctor</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 500;">
            <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div style="background-color: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 500;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div id="doctorsSection">
        <div class="status-summary-cards">
            <div class="summary-card card-green">
                <div class="summary-num"><?= $data['availableCount'] ?? 0 ?></div>
                <div class="summary-label">Available</div>
            </div>
            <div class="summary-card card-yellow">
                <div class="summary-num">0</div>
                <div class="summary-label">On Leave</div>
            </div>
            <div class="summary-card card-blue">
                <div class="summary-num">0</div>
                <div class="summary-label">Busy</div>
            </div>
        </div>

        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="doctorSearch" placeholder="Search by name, specialty, or ID...">
        </div>
        
        <!-- Doctors Grid -->
        <div class="doctors-grid" id="doctorsGrid">
            <?php foreach ($data['doctors'] as $doc): ?>
            <div class="doc-card" data-name="<?= strtolower(e($doc['name'])) ?>" data-specialty="<?= strtolower(e($doc['specialization'])) ?>" data-id="<?= strtolower(e($doc['doctor_id'])) ?>">
                <div class="doc-header">
                    <div class="doc-profile">
                        <div class="doc-avatar" style="background: <?= e($doc['color']) ?>;"><?= e($doc['initials']) ?></div>
                        <div>
                            <div class="doc-name"><?= e($doc['name']) ?></div>
                            <div class="doc-id"><?= e($doc['doctor_id']) ?></div>
                        </div>
                    </div>
                    <span class="status-pill">Available</span>
                </div>
                
                <div class="doc-stats">
                    <div class="stat-row full"><span class="stat-label">IC No</span><span class="stat-val" style="font-family:monospace;"><?= e($doc['ic'] ?? 'N/A') ?></span></div>
                    <div class="stat-row"><span class="stat-label">Specialty</span><span class="stat-val"><?= e($doc['specialization']) ?></span></div>
                    <div class="stat-row"><span class="stat-label">Experience</span><span class="stat-val"><?= e($doc['qualification']) ?></span></div>
                </div>
                
                <div class="doc-contact">
                    <div class="contact-item"><i class="fa-solid fa-phone"></i> <?= e($doc['phone']) ?></div>
                    <div class="contact-item"><i class="fa-regular fa-envelope"></i> <?= e($doc['email']) ?></div>
                </div>
                
                <div class="doc-actions">
                    <button class="btn-view" onclick='viewProfile(<?= json_encode([
                        "name" => e($doc["name"]),
                        "id" => e($doc["doctor_id"]),
                        "ic" => e($doc["ic"] ?? "N/A"),
                        "specialty" => e($doc["specialization"]),
                        "qualification" => e($doc["qualification"]),
                        "phone" => e($doc["phone"]),
                        "email" => e($doc["email"]),
                        "initials" => e($doc["initials"]),
                        "color" => e($doc["color"])
                    ]) ?>)'><i class="fa-regular fa-eye"></i> View Profile</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this doctor?');">
                        <input type="hidden" name="action" value="delete_doctor">
                        <input type="hidden" name="doctor_id" value="<?= e($doc['doctor_id']) ?>">
                        <button type="submit" class="btn-delete"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($data['doctors'])): ?>
                <div style="grid-column: span 2; text-align: center; color: #64748b; padding: 40px;">No doctors found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div id="profileModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Doctor Profile</h2>
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
                    <label class="form-label">Specialty</label>
                    <div id="modalSpecialty" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Qualification / Experience</label>
                    <div id="modalQualification" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">IC Number</label>
                    <div id="modalIc" class="form-control" style="background: #f8fafc; font-family: monospace;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <div id="modalPhone" class="form-control" style="background: #f8fafc;"></div>
                </div>
                <div class="form-group full">
                    <label class="form-label">Email Address</label>
                    <div id="modalEmail" class="form-control" style="background: #f8fafc;"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" onclick="document.getElementById('profileModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Add Doctor Modal -->
<div id="addDoctorModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Add New Doctor</h2>
            <button class="close-modal" onclick="closeAddDoctorModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="addDoctorForm" action="doctors.php" method="POST">
            <input type="hidden" name="action" value="add_doctor">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Dr. Jane Miller" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="jane@medicare.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">IC Number *</label>
                        <input type="text" name="ic" class="form-control" placeholder="e.g. 910214-10-5542" oninput="formatIC(this)" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 012-3456789" oninput="formatPhone(this)" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Specialization *</label>
                        <input type="text" name="specialization" class="form-control" placeholder="e.g. Cardiology" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qualification *</label>
                        <input type="text" name="qualification" class="form-control" placeholder="e.g. MD, FACC" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consultation Fee (RM) *</label>
                        <input type="number" step="0.01" name="consultation_fee" class="form-control" placeholder="50.00" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Theme Color *</label>
                        <select name="color" class="form-control" required>
                            <option value="#3b82f6">Blue</option>
                            <option value="#10b981">Emerald Green</option>
                            <option value="#a855f7">Purple</option>
                            <option value="#f59e0b">Amber Orange</option>
                            <option value="#ef4444">Crimson Red</option>
                            <option value="#06b6d4">Cyan Blue</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeAddDoctorModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add Doctor</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Search Filtering
    const searchInput = document.getElementById('doctorSearch');
    const doctorCards = document.querySelectorAll('.doc-card');

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        doctorCards.forEach(card => {
            const name = card.getAttribute('data-name');
            const specialty = card.getAttribute('data-specialty');
            const id = card.getAttribute('data-id');
            
            if (name.includes(term) || specialty.includes(term) || id.includes(term)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // View Profile Modal
    function viewProfile(doctor) {
        document.getElementById('modalAvatar').style.background = doctor.color;
        document.getElementById('modalAvatar').textContent = doctor.initials;
        document.getElementById('modalName').textContent = doctor.name;
        document.getElementById('modalId').textContent = doctor.id;
        document.getElementById('modalSpecialty').textContent = doctor.specialty;
        document.getElementById('modalQualification').textContent = doctor.qualification;
        document.getElementById('modalIc').textContent = doctor.ic;
        document.getElementById('modalPhone').textContent = doctor.phone;
        document.getElementById('modalEmail').textContent = doctor.email;
        
        document.getElementById('profileModal').style.display = 'flex';
    }

    // Add Doctor Modal Controls
    function openAddDoctorModal() {
        document.getElementById('addDoctorModal').style.display = 'flex';
    }
    function closeAddDoctorModal() {
        document.getElementById('addDoctorModal').style.display = 'none';
        document.getElementById('addDoctorForm').reset();
    }
    
    // Auto Formatters
    function formatIC(input) {
        let val = input.value.replace(/\D/g, '');
        if (val.length > 12) val = val.substring(0, 12);
        let formatted = '';
        if (val.length > 0) formatted += val.substring(0, 6);
        if (val.length > 6) formatted += '-' + val.substring(6, 8);
        if (val.length > 8) formatted += '-' + val.substring(8, 12);
        input.value = formatted;
    }
    function formatPhone(input) {
        let val = input.value.replace(/\D/g, '');
        if (val.length > 11) val = val.substring(0, 11);
        let formatted = '';
        if (val.length > 0) formatted += val.substring(0, 3);
        if (val.length > 3) formatted += '-' + val.substring(3, 11);
        input.value = formatted;
    }
</script>

</body>
</html>
