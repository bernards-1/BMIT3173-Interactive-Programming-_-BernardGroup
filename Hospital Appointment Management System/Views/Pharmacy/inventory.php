<?php
// Views/Pharmacy/inventory.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
    header('Location: ../Login/login.php');
    exit;
}
require_once '../../db.php';
require_once '../../Models/Pharmacy.php';

$medicines  = Pharmacy::getAllMedicines();
$stats      = Pharmacy::getInventoryStats();
$categories = Pharmacy::getCategories();
$pharmacist = Pharmacy::getPharmacistByUserId($_SESSION['user']['user_id']);
$pharmacistName = $pharmacist ? $pharmacist['full_name'] : $_SESSION['user']['username'];

// Helper: determine stock status
function getStockStatus($qty, $min) {
    if ($qty == 0) return ['label' => 'Out', 'class' => 'out', 'bar' => 'red', 'pct' => 0];
    if ($qty <= $min) {
        $pct = min(50, round(($qty / max($min,1)) * 50));
        return ['label' => 'Low', 'class' => 'low', 'bar' => 'yellow', 'pct' => $pct];
    }
    $cap = max($qty, $min * 3);
    $pct = min(100, round(($qty / $cap) * 100));
    return ['label' => 'Normal', 'class' => 'success', 'bar' => 'green', 'pct' => $pct];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - MediCare Pharmacy</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Pharmacy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<?php include '../Layout/Pharmacy/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 24px; font-weight: 700; color: var(--slate-900);">Inventory Management</h2>
            <p class="queue-header-subtitle">Track stock levels, reorder drugs, and manage medicine details</p>
        </div>
        <button class="btn-dispense" style="display: flex; align-items: center; gap: 8px;" onclick="openAddMedicineModal()">
            <i class="fa-solid fa-plus"></i> Add New Medicine
        </button>
    </div>

    <!-- Status Cards -->
    <div class="history-status-cards">
        <div class="history-status-card revenue-total">
            <div class="history-status-val revenue-total" id="statTotalMeds"><?php echo $stats['total']; ?></div>
            <div class="history-status-lbl">Total Medicines</div>
        </div>
        <div class="history-status-card dispensed" style="background-color: #fffbeb; border-color: #fde68a;">
            <div class="history-status-val" style="color: #d97706;" id="statLowStock"><?php echo $stats['low_stock']; ?></div>
            <div class="history-status-lbl">Low Stock</div>
        </div>
        <div class="history-status-card cancelled">
            <div class="history-status-val cancelled" id="statOutOfStock"><?php echo $stats['out_stock']; ?></div>
            <div class="history-status-lbl">Out of Stock</div>
        </div>
        <div class="history-status-card revenue-today">
            <div class="history-status-val revenue-today"><?php echo $stats['categories']; ?></div>
            <div class="history-status-lbl">Categories</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="search-filter-box-flex">
        <div class="search-input-wrapper" style="flex-grow: 2; max-width: 500px;">
            <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="inventorySearch" oninput="filterInventory()" class="search-input-control" placeholder="Search by ID or medicine name...">
        </div>
        <div style="display: flex; gap: 12px; flex-grow: 1; justify-content: flex-end; flex-wrap: wrap;">
            <select id="filterCategory" onchange="filterInventory()" class="select-control" style="max-width: 160px; height: 42px;">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo e(strtolower($cat)); ?>"><?php echo e($cat); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterStock" onchange="filterInventory()" class="select-control" style="max-width: 160px; height: 42px;">
                <option value="all">All Stock Status</option>
                <option value="normal">Normal Stock</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
            </select>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="history-table-card">
        <div class="table-wrapper">
            <table class="custom-table" id="inventoryTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Medicine Name</th>
                        <th>Category</th>
                        <th>Stock Level</th>
                        <th>Unit Price</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $med):
                        $s = getStockStatus($med['stock_quantity'], $med['minimum_stock']);
                        $capacity = max($med['stock_quantity'], $med['minimum_stock'] * 3);
                        $pctDisplay = $capacity > 0 ? round(($med['stock_quantity'] / $capacity) * 100) : 0;
                        $colorVar = $s['bar'] === 'green' ? 'var(--success)' : ($s['bar'] === 'yellow' ? 'var(--warning)' : 'var(--danger)');
                    ?>
                    <tr class="inventory-row"
                        data-id="<?php echo e($med['medicine_id']); ?>"
                        data-category="<?php echo e(strtolower($med['category'])); ?>"
                        data-status="<?php echo e(strtolower($s['label'])); ?>"
                        data-search="<?php echo e(strtolower($med['medicine_id'] . ' ' . $med['brand_name'] . ' ' . $med['generic_name'] . ' ' . $med['category'])); ?>">
                        <td><span style="font-family: monospace; font-weight: 600; color: var(--slate-600);"><?php echo e($med['medicine_id']); ?></span></td>
                        <td>
                            <div style="font-weight: 700; color: var(--slate-900);"><?php echo e($med['brand_name']); ?> <?php echo e($med['dosage']); ?></div>
                            <div style="font-size: 11px; color: var(--slate-500);"><?php echo e($med['generic_name']); ?></div>
                        </td>
                        <td><?php echo e($med['category']); ?></td>
                        <td style="width: 220px;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 2px;">
                                <span><?php echo e($med['stock_quantity']); ?> / <?php echo e($capacity); ?> <?php echo e($med['unit_type']); ?></span>
                                <span style="color: <?php echo $colorVar; ?>"><?php echo $pctDisplay; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill <?php echo e($s['bar']); ?>" style="width: <?php echo $s['pct']; ?>%;"></div>
                            </div>
                        </td>
                        <td style="font-weight: 600;"><?php echo formatCurrency($med['unit_price']); ?></td>
                        <td style="font-size: 12px; color: var(--slate-600);">
                            <?php echo $med['expiry_date'] ? date('M Y', strtotime($med['expiry_date'])) : '—'; ?>
                        </td>
                        <td><span class="stock-alert-badge <?php echo e($s['class']); ?>"><?php echo e($s['label']); ?></span></td>
                        <td style="text-align: right;">
                            <div style="display: flex; flex-direction: row; align-items: center; justify-content: flex-end; gap: 6px;">
                                <button class="action-icon-btn reorder-btn" title="Restock"
                                    data-med-name="<?php echo e($med['brand_name'] . ' ' . $med['dosage']); ?>"
                                    data-suggest-qty="<?php echo (int)$med['minimum_stock'] * 2; ?>"
                                    data-medicine-id="<?php echo e($med['medicine_id']); ?>"
                                    onclick="openReorderModal(this)"><i class="fa-solid fa-cart-shopping"></i></button>
                                <button class="action-icon-btn" title="Edit"
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($med), ENT_QUOTES); ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i></button>
                                <button class="action-icon-btn" title="Delete"
                                    style="color: var(--danger); background-color: #fee2e2;"
                                    data-medicine-id="<?php echo e($med['medicine_id']); ?>"
                                    data-med-name="<?php echo e($med['brand_name'] . ' ' . $med['dosage']); ?>"
                                    onclick="openDeleteConfirm(this)">
                                    <i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="noInventory" style="display: none; text-align: center; padding: 40px 20px;">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 40px; color: var(--slate-400); margin-bottom: 12px;"></i>
                <h4 style="font-weight: 700; color: var(--slate-700); font-size: 15px;">No inventory items found</h4>
                <p style="font-size: 13px; color: var(--slate-400);">Try adjusting your search query or filters.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Medicine Modal -->
<div class="modal-overlay" id="addMedicineModal">
    <div class="modal-box">
        <div class="modal-header" style="background-color: var(--teal);">
            <div class="modal-title">Add New Medicine</div>
            <button class="modal-close" onclick="closeAddMedicineModal()">&times;</button>
        </div>
        <form id="addMedicineForm" onsubmit="submitAddMedicine(event)">
            <div class="modal-body">
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label for="medBrandName">Brand Name</label>
                        <input type="text" id="medBrandName" placeholder="e.g. Lipitor" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="medGenericName">Generic Name</label>
                        <input type="text" id="medGenericName" placeholder="e.g. Atorvastatin" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label for="medDosage">Dosage</label>
                        <input type="text" id="medDosage" placeholder="e.g. 20mg" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="medCategory">Category</label>
                        <input type="text" id="medCategory" placeholder="e.g. Cardiovascular" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label for="medUnitType">Unit Type</label>
                        <select id="medUnitType" required>
                            <option>Tablet</option>
                            <option>Capsule</option>
                            <option>Inhaler</option>
                            <option>Syrup</option>
                            <option>Injection</option>
                            <option>Cream</option>
                            <option>Drop</option>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="medManufacturer">Manufacturer</label>
                        <input type="text" id="medManufacturer" placeholder="e.g. Pfizer">
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label for="medPrice">Unit Price ($)</label>
                        <input type="number" id="medPrice" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="medStock">Initial Stock Qty</label>
                        <input type="number" id="medStock" min="0" placeholder="100" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label for="medMinStock">Min Stock (Alert threshold)</label>
                        <input type="number" id="medMinStock" min="1" value="20" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="medExpiry">Expiry Date</label>
                        <input type="date" id="medExpiry">
                    </div>
                </div>
                <div class="modal-form-group">
                    <label for="medDescription">Description</label>
                    <textarea class="textarea-control" id="medDescription" placeholder="Short description of this medicine..." style="height: 70px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddMedicineModal()">Cancel</button>
                <button type="submit" id="addMedBtn" class="btn-dispense" style="padding: 8px 20px;">Save Medicine</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal-overlay" id="editMedicineModal">
    <div class="modal-box">
        <div class="modal-header" style="background-color: var(--teal);">
            <div class="modal-title">Edit Medicine</div>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editMedicineForm" onsubmit="submitEditMedicine(event)">
            <div class="modal-body">
                <input type="hidden" id="editMedicineId">
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label>Brand Name</label>
                        <input type="text" id="editBrandName" required>
                    </div>
                    <div class="modal-form-group">
                        <label>Generic Name</label>
                        <input type="text" id="editGenericName" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label>Dosage</label>
                        <input type="text" id="editDosage" required>
                    </div>
                    <div class="modal-form-group">
                        <label>Category</label>
                        <input type="text" id="editCategory" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label>Unit Type</label>
                        <input type="text" id="editUnitType" required>
                    </div>
                    <div class="modal-form-group">
                        <label>Manufacturer</label>
                        <input type="text" id="editManufacturer">
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label>Unit Price ($)</label>
                        <input type="number" id="editPrice" step="0.01" min="0" required>
                    </div>
                    <div class="modal-form-group">
                        <label>Stock Quantity</label>
                        <input type="number" id="editStock" min="0" required>
                    </div>
                </div>
                <div class="dashboard-form-row">
                    <div class="modal-form-group">
                        <label>Min Stock</label>
                        <input type="number" id="editMinStock" min="1" required>
                    </div>
                    <div class="modal-form-group">
                        <label>Expiry Date</label>
                        <input type="date" id="editExpiry">
                    </div>
                </div>
                <div class="modal-form-group">
                    <label>Description</label>
                    <textarea class="textarea-control" id="editDescription" style="height: 70px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" id="editMedBtn" class="btn-dispense" style="padding: 8px 20px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal-overlay" id="reorderModal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header" style="background-color: var(--teal);">
            <div class="modal-title">Restock Medicine</div>
            <button class="modal-close" onclick="closeReorderModal()">&times;</button>
        </div>
        <form id="reorderForm" onsubmit="submitReorder(event)">
            <div class="modal-body">
                <input type="hidden" id="reorderMedicineId">
                <p style="font-size: 14px; color: var(--slate-700); margin-bottom: 16px;">
                    Restock: <strong id="reorderMedName"></strong>
                </p>
                <div class="modal-form-group">
                    <label for="reorderQty">Quantity to Add</label>
                    <input type="number" id="reorderQty" min="1" value="100" required>
                </div>
                <div class="modal-form-group">
                    <label for="supplierSelect">Preferred Supplier</label>
                    <select id="supplierSelect">
                        <option>AstraZeneca Pharmacies</option>
                        <option>Pfizer Pharma Dist.</option>
                        <option>GlaxoSmithKline Wholesale</option>
                        <option>MediCare Central Supply</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeReorderModal()">Cancel</button>
                <button type="submit" id="reorderBtn" class="btn-dispense" style="padding: 8px 20px;">Confirm Restock</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteConfirmModal">
    <div class="modal-box" style="max-width: 400px;">
        <div class="modal-header" style="background-color: var(--danger);">
            <div class="modal-title">Delete Medicine</div>
            <button class="modal-close" onclick="closeDeleteConfirm()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 30px 24px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 42px; color: var(--danger); margin-bottom: 14px;"></i>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">Are you sure?</h4>
            <p style="font-size: 13px; color: var(--slate-500);">You are about to permanently delete</p>
            <p style="font-size: 14px; font-weight: 700; color: var(--slate-800); margin: 6px 0 16px;" id="deleteMedLabel"></p>
            <p style="font-size: 12px; color: var(--slate-400);">This action cannot be undone. Medicines with linked prescriptions cannot be deleted.</p>
            <input type="hidden" id="deleteMedicineId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteConfirm()">Cancel</button>
            <button id="confirmDeleteBtn" style="padding: 8px 20px; background-color: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer;" onclick="deleteConfirmed()">Yes, Delete</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast-notification" id="inventoryToast">
    <i class="fa-solid fa-circle-check toast-icon"></i>
    <div>
        <div style="font-weight: 700; font-size: 14px;" id="toastTitle">Done!</div>
        <div style="font-size: 12px; opacity: 0.9;" id="toastDetails"></div>
    </div>
</div>

<script>
// ── Filter ──────────────────────────────────────────────────────────────────
function filterInventory() {
    const query    = document.getElementById('inventorySearch').value.toLowerCase();
    const category = document.getElementById('filterCategory').value;
    const stock    = document.getElementById('filterStock').value;
    const rows     = document.getElementsByClassName('inventory-row');
    let visible    = 0;

    for (let row of rows) {
        const text = row.getAttribute('data-search');
        const cat  = row.getAttribute('data-category');
        const st   = row.getAttribute('data-status');

        const ok = (text.includes(query) || !query)
                && (category === 'all' || cat === category)
                && (stock    === 'all' || st  === stock.toLowerCase());

        row.style.display = ok ? '' : 'none';
        if (ok) visible++;
    }
    document.getElementById('noInventory').style.display = visible === 0 ? 'block' : 'none';
}

// ── Add Medicine ─────────────────────────────────────────────────────────────
function openAddMedicineModal() {
    document.getElementById('addMedicineForm').reset();
    document.getElementById('addMedicineModal').classList.add('active');
}
function closeAddMedicineModal() {
    document.getElementById('addMedicineModal').classList.remove('active');
}
function submitAddMedicine(event) {
    event.preventDefault();
    const btn = document.getElementById('addMedBtn');
    btn.disabled = true; btn.innerText = 'Saving...';

    const payload = {
        brand_name:     document.getElementById('medBrandName').value,
        generic_name:   document.getElementById('medGenericName').value,
        dosage:         document.getElementById('medDosage').value,
        category:       document.getElementById('medCategory').value,
        unit_type:      document.getElementById('medUnitType').value,
        manufacturer:   document.getElementById('medManufacturer').value,
        unit_price:     document.getElementById('medPrice').value,
        stock_quantity: document.getElementById('medStock').value,
        minimum_stock:  document.getElementById('medMinStock').value,
        expiry_date:    document.getElementById('medExpiry').value,
        description:    document.getElementById('medDescription').value,
    };

    fetch('../../api/pharmacy_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeAddMedicineModal();
            showToast('Medicine Added!', `${payload.brand_name} ${payload.dosage} added to inventory.`);
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error.'))
    .finally(() => { btn.disabled = false; btn.innerText = 'Save Medicine'; });
}

// ── Edit Medicine ────────────────────────────────────────────────────────────
function openEditModal(med) {
    document.getElementById('editMedicineId').value  = med.medicine_id;
    document.getElementById('editBrandName').value   = med.brand_name;
    document.getElementById('editGenericName').value = med.generic_name;
    document.getElementById('editDosage').value      = med.dosage;
    document.getElementById('editCategory').value    = med.category;
    document.getElementById('editUnitType').value    = med.unit_type;
    document.getElementById('editManufacturer').value= med.manufacturer || '';
    document.getElementById('editPrice').value       = med.unit_price;
    document.getElementById('editStock').value       = med.stock_quantity;
    document.getElementById('editMinStock').value    = med.minimum_stock;
    document.getElementById('editExpiry').value      = med.expiry_date || '';
    document.getElementById('editDescription').value = med.description || '';
    document.getElementById('editMedicineModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editMedicineModal').classList.remove('active');
}
function submitEditMedicine(event) {
    event.preventDefault();
    const btn = document.getElementById('editMedBtn');
    btn.disabled = true; btn.innerText = 'Saving...';

    const payload = {
        medicine_id:    document.getElementById('editMedicineId').value,
        brand_name:     document.getElementById('editBrandName').value,
        generic_name:   document.getElementById('editGenericName').value,
        dosage:         document.getElementById('editDosage').value,
        category:       document.getElementById('editCategory').value,
        unit_type:      document.getElementById('editUnitType').value,
        manufacturer:   document.getElementById('editManufacturer').value,
        unit_price:     document.getElementById('editPrice').value,
        stock_quantity: document.getElementById('editStock').value,
        minimum_stock:  document.getElementById('editMinStock').value,
        expiry_date:    document.getElementById('editExpiry').value,
        description:    document.getElementById('editDescription').value,
    };

    fetch('../../api/pharmacy_inventory.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeEditModal();
            showToast('Medicine Updated!', `${payload.brand_name} details saved.`);
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error.'))
    .finally(() => { btn.disabled = false; btn.innerText = 'Save Changes'; });
}

// ── Restock ──────────────────────────────────────────────────────────────────
function openReorderModal(btn) {
    const medName    = btn.dataset.medName;
    const suggestQty = btn.dataset.suggestQty;
    const medicineId = btn.dataset.medicineId;
    document.getElementById('reorderMedName').innerText     = medName;
    document.getElementById('reorderQty').value             = suggestQty;
    document.getElementById('reorderMedicineId').value      = medicineId;
    document.getElementById('reorderModal').classList.add('active');
}
function closeReorderModal() {
    document.getElementById('reorderModal').classList.remove('active');
}
function submitReorder(event) {
    event.preventDefault();
    const btn = document.getElementById('reorderBtn');
    btn.disabled = true; btn.innerText = 'Processing...';

    const payload = {
        medicine_id: document.getElementById('reorderMedicineId').value,
        qty_to_add:  parseInt(document.getElementById('reorderQty').value),
    };

    fetch('../../api/pharmacy_inventory.php', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeReorderModal();
            showToast('Restocked!', data.message + ` New stock: ${data.new_stock_quantity}`);
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error.'))
    .finally(() => { btn.disabled = false; btn.innerText = 'Confirm Restock'; });
}

// ── Delete Medicine ──────────────────────────────────────────────────────────
function openDeleteConfirm(btn) {
    document.getElementById('deleteMedicineId').value = btn.dataset.medicineId;
    document.getElementById('deleteMedLabel').innerText = btn.dataset.medName;
    document.getElementById('deleteConfirmModal').classList.add('active');
}
function closeDeleteConfirm() {
    document.getElementById('deleteConfirmModal').classList.remove('active');
}
function deleteConfirmed() {
    const btn = document.getElementById('confirmDeleteBtn');
    const medicineId = document.getElementById('deleteMedicineId').value;
    const medName = document.getElementById('deleteMedLabel').innerText;
    btn.disabled = true; btn.innerText = 'Deleting...';

    fetch('../../api/pharmacy_inventory.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ medicine_id: medicineId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeDeleteConfirm();
            showToast('Medicine Deleted!', `${medName} has been removed from inventory.`);
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => { btn.disabled = false; btn.innerText = 'Yes, Delete'; });
}

// ── Toast ────────────────────────────────────────────────────────────────────
function showToast(title, details) {
    document.getElementById('toastTitle').innerText   = title;
    document.getElementById('toastDetails').innerText = details;
    const toast = document.getElementById('inventoryToast');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4500);
}
</script>

</body>
</html>
