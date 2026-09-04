<?php
// Views/Pharmacy/queue.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
    header('Location: ../Login/login.php');
    exit;
}
require_once '../../db.php';
require_once '../../Models/Pharmacy.php';

$pharmacist     = Pharmacy::getPharmacistByUserId($_SESSION['user']['user_id']);
$pharmacistName = $pharmacist ? $pharmacist['full_name'] : $_SESSION['user']['username'];

// Consuming Doctor Module API via cURL RESTful Service (Section 6.3)
$ch = curl_init();
$apiUrl = "http://localhost/Hospital%20Appointment%20Management%20System/api/get_prescriptions.php?status=pending&timeStamp=" . urlencode(date('Y-m-d H:i:s'));
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1); // 1.0s timeout guard
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    $queue  = $result['data'] ?? [];
} else {
    // Controlled Error Handling (Preserves Module Ownership Boundary)
    $queue = [];
    $errorMessage = "Clinical Prescription Service is temporarily unavailable. Please retry shortly.";
}

$counts         = Pharmacy::getQueueStatusCounts();
$dispensedToday = Pharmacy::countDispensedToday();

$avatarColors = ['#3b82f6','#a855f7','#10b981','#ef4444','#f59e0b','#06b6d4','#ec4899','#64748b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Queue - MediCare Pharmacy</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Pharmacy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<?php include '../Layout/Pharmacy/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Header Block -->
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 24px; font-weight: 700; color: var(--slate-900);">Prescription Queue</h2>
        <p class="queue-header-subtitle">Review and dispense doctor-issued prescriptions</p>
    </div>

    <!-- Status cards row -->
    <div class="queue-status-cards">
        <div class="queue-status-card pending">
            <span class="queue-status-number pending" id="countPending"><?php echo $counts['pending']; ?></span>
            <span class="queue-status-label pending">Pending</span>
        </div>
        <div class="queue-status-card processing">
            <span class="queue-status-number processing" id="countProcessing">0</span>
            <span class="queue-status-label processing">Processing</span>
        </div>
        <div class="queue-status-card dispensed">
            <span class="queue-status-number dispensed" id="countDispensed"><?php echo $dispensedToday; ?></span>
            <span class="queue-status-label dispensed">Dispensed Today</span>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-filter-box">
        <div class="search-input-wrapper">
            <span class="search-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="queueSearch" oninput="filterQueue()" class="search-input-control" placeholder="Search by patient, RX ID, or doctor...">
        </div>
    </div>

    <!-- Queue List -->
    <div class="queue-list-wrapper" id="queueContainer">

        <?php if (isset($errorMessage)): ?>
        <div id="serviceUnavailableAlert" style="background-color: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 18px 24px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 14px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 26px; color: #dc2626;"></i>
            <div>
                <div style="font-weight: 700; font-size: 15px;">Clinical Prescription Service Unavailable</div>
                <div style="font-size: 13px; opacity: 0.95; margin-top: 2px;"><?php echo e($errorMessage); ?></div>
            </div>
        </div>
        <?php elseif (empty($queue)): ?>
        <div id="emptyState" style="text-align: center; padding: 60px 20px; background-color: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
            <i class="fa-solid fa-circle-check" style="font-size: 48px; color: var(--success); margin-bottom: 16px;"></i>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--slate-700);">All Prescriptions Dispensed!</h4>
            <p style="font-size: 13px; color: var(--slate-400); margin-top: 4px;">Excellent work. The queue is currently empty.</p>
        </div>
        <?php else: ?>

        <?php foreach ($queue as $idx => $item):
            $color    = $avatarColors[$idx % count($avatarColors)];
            $initials = strtoupper(substr(str_replace(' ', '', $item['patient_name']), 0, 2));
            $time     = date('h:i A', strtotime($item['appointment_time']));
            $rowId    = 'q-db-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $item['record_id']));
        ?>
        <div class="queue-item-card"
             id="<?php echo e($rowId); ?>"
             data-patient="<?php echo e(strtolower($item['patient_name'])); ?>"
             data-rx="<?php echo e(strtolower($item['record_id'])); ?>"
             data-doctor="<?php echo e(strtolower($item['doctor_name'])); ?>">
            <div class="queue-item-left">
                <div class="queue-patient-initials" style="background-color: <?php echo e($color); ?>;">
                    <?php echo e($initials); ?>
                </div>
                <div class="queue-details-column">
                    <div class="queue-patient-header">
                        <span class="queue-patient-name"><?php echo e($item['patient_name']); ?></span>
                        <span class="queue-patient-id"><?php echo e($item['patient_id']); ?></span>
                    </div>
                    <div class="queue-doctor-time">
                        <?php echo e($item['doctor_name']); ?> &middot; Issued <?php echo e($time); ?>
                    </div>
                    <div class="queue-med-tags">
                        <?php foreach ($item['medicines'] as $med): ?>
                        <span class="queue-med-tag">
                            <i class="fa-solid fa-link"></i> <?php echo e($med['brand_name'] . ' ' . $med['dosage']); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="queue-item-right">
                <button class="btn-dispense"
                    data-patient-name="<?php echo e($item['patient_name']); ?>"
                    data-initials="<?php echo e($initials); ?>"
                    data-doctor-name="<?php echo e($item['doctor_name']); ?>"
                    data-meds="<?php echo e(json_encode($item['medicines'])); ?>"
                    data-consult-fee="<?php echo e($item['consultation_fee']); ?>"
                    data-meds-subtotal="<?php echo e($item['medicines_subtotal']); ?>"
                    data-total-amount="<?php echo e($item['total_amount']); ?>"
                    data-time="<?php echo e($time); ?>"
                    data-row-id="<?php echo e($rowId); ?>"
                    data-record-id="<?php echo e($item['record_id']); ?>"
                    onclick="openDispenseModal(this)">Dispense &amp; Pay</button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Empty State (shown when queue is fully dispensed or no search matches) -->
        <div id="emptyState" style="display: none; text-align: center; padding: 40px 20px; background-color: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
            <i class="fa-solid fa-capsules" style="font-size: 48px; color: var(--slate-400); margin-bottom: 16px;"></i>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--slate-700);">No prescriptions match your search</h4>
            <p style="font-size: 13px; color: var(--slate-400); margin-top: 4px;">Try searching for a different patient, ID or doctor name.</p>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- Dispense & Payment Modal Overlay -->
<div class="modal-overlay" id="dispenseModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header" style="background-color: var(--teal);">
            <div class="modal-title"><i class="fa-solid fa-receipt" style="margin-right: 6px;"></i> Dispense &amp; Settle Payment</div>
            <button class="modal-close" onclick="closeDispenseModal()">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
                <div class="patient-avatar-circle" id="modalPatientAvatar" style="width: 44px; height: 44px; font-size: 15px;"></div>
                <div>
                    <h4 id="modalPatientName" style="font-size: 16px; font-weight: 700; color: var(--slate-900);"></h4>
                    <p style="font-size: 12px; color: var(--slate-500); margin-top: 2px;">Prescribed by <span id="modalDoctorName" style="font-weight: 600;"></span></p>
                </div>
            </div>

            <form id="dispenseForm" onsubmit="submitDispense(event)">
                <input type="hidden" id="targetRowId">
                <input type="hidden" id="targetRecordId">
                
                <!-- Prescribed Medications List -->
                <div class="modal-form-group">
                    <label style="font-weight: 600; color: var(--slate-700); font-size: 13px;">Prescribed Medications</label>
                    <div id="medicationsCheckboxList" style="display: flex; flex-direction: column; gap: 6px; margin-top: 6px;">
                        <!-- Medicine list injected dynamically -->
                    </div>
                </div>

                <!-- Billing & Payment Breakdown Box -->
                <div style="background: var(--slate-50); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--slate-600); margin-bottom: 6px;">
                        <span><i class="fa-solid fa-user-doctor" style="color: var(--teal); width: 18px;"></i> Consultation Fee:</span>
                        <span id="modalConsultFee" style="font-weight: 600;">$0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--slate-600); margin-bottom: 8px;">
                        <span><i class="fa-solid fa-pills" style="color: var(--teal); width: 18px;"></i> Medications Subtotal:</span>
                        <span id="modalMedsSubtotal" style="font-weight: 600;">$0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; color: var(--slate-900); padding-top: 8px; border-top: 1px dashed #cbd5e1;">
                        <span>Total Payable Amount:</span>
                        <span id="modalTotalAmount" style="color: #059669; font-size: 17px;">$0.00</span>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="modal-form-group" style="margin-top: 14px;">
                    <label style="font-weight: 600; color: var(--slate-700); font-size: 13px;"><i class="fa-solid fa-credit-card" style="margin-right: 4px;"></i> Select Payment Method</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 6px;">
                        <div class="pay-method-card active" onclick="selectPayMethod('Cash', this)" style="border: 2px solid var(--teal); background: #f0fdf4; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: all 0.2s;">
                            <i class="fa-solid fa-money-bill-wave" style="font-size: 20px; color: #16a34a; margin-bottom: 4px;"></i>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Cash</div>
                        </div>
                        <div class="pay-method-card" onclick="selectPayMethod('Touch \'n Go', this)" style="border: 1px solid var(--border-color); background: white; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: all 0.2s;">
                            <i class="fa-solid fa-mobile-screen-button" style="font-size: 20px; color: #0284c7; margin-bottom: 4px;"></i>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Touch 'n Go</div>
                        </div>
                        <div class="pay-method-card" onclick="selectPayMethod('Credit Card', this)" style="border: 1px solid var(--border-color); background: white; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: all 0.2s;">
                            <i class="fa-solid fa-credit-card" style="font-size: 20px; color: #6366f1; margin-bottom: 4px;"></i>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Credit / Card</div>
                        </div>
                    </div>
                    <input type="hidden" id="selectedPaymentMethod" value="Cash">
                </div>

                <div class="modal-form-group" style="margin-top: 14px;">
                    <label for="pharmacistNotes">Instructions / Notes for Patient</label>
                    <textarea class="textarea-control" id="pharmacistNotes" placeholder="Take 1 tablet after meals, twice a day..." required></textarea>
                </div>

                <div class="modal-form-group">
                    <label for="dispensingPharmacist">Dispensing Pharmacist</label>
                    <input type="text" id="dispensingPharmacist" value="<?php echo e($pharmacistName); ?>" disabled class="select-control" style="background-color: var(--slate-50);">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDispenseModal()">Cancel</button>
            <button class="btn-dispense" id="confirmDispenseBtn" style="padding: 8px 20px; background-color: var(--teal);" onclick="document.getElementById('dispenseForm').requestSubmit()"><i class="fa-solid fa-check-circle" style="margin-right: 4px;"></i> Confirm &amp; Collect Payment</button>
        </div>
    </div>
</div>

<!-- Toast Success Notification -->
<div class="toast-notification" id="successToast">
    <i class="fa-solid fa-circle-check toast-icon"></i>
    <div>
        <div style="font-weight: 700; font-size: 14px;">Prescription &amp; Payment Processed!</div>
        <div style="font-size: 12px; opacity: 0.9;" id="toastDetails"></div>
    </div>
</div>

<!-- Official Payment Receipt Modal Overlay -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" style="max-width: 540px; background: white;" id="printableReceipt">
        <div style="text-align: center; padding: 20px 20px 12px; border-bottom: 2px dashed #cbd5e1;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 4px;">
                <i class="fa-solid fa-hospital-user" style="font-size: 26px; color: var(--teal);"></i>
                <span style="font-size: 20px; font-weight: 800; color: var(--slate-900); letter-spacing: -0.5px;">MediCare Health Centre</span>
            </div>
            <p style="font-size: 12px; color: var(--slate-500); margin: 0;">123 Healthcare Blvd, Suite 400 &middot; Tel: +60 3-8888 9999</p>
            <div style="display: inline-block; margin-top: 10px; padding: 4px 14px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-weight: 700; font-size: 12px; border-radius: 20px;">
                OFFICIAL PAYMENT RECEIPT
            </div>
        </div>

        <div class="modal-body" style="padding: 16px 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--slate-600); margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--slate-100);">
                <div>
                    <div><strong>Invoice No:</strong> <span id="recInvoiceNo" style="font-weight: 700; color: var(--slate-800);">INV-2026-0001</span></div>
                    <div><strong>Date &amp; Time:</strong> <span id="recDate">-</span></div>
                </div>
                <div style="text-align: right;">
                    <div><strong>Payment Method:</strong> <span id="recPayMethod" style="color: var(--teal); font-weight: 700;">Cash</span></div>
                    <div><strong>Status:</strong> <span style="color: #16a34a; font-weight: 700;">PAID</span></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; background: var(--slate-50); padding: 10px 12px; border-radius: 6px; margin-bottom: 14px;">
                <div><span style="color: var(--slate-400);">Patient Name:</span> <div id="recPatientName" style="font-weight: 700; color: var(--slate-800); font-size: 13px;">-</div></div>
                <div><span style="color: var(--slate-400);">Attending Doctor:</span> <div id="recDoctorName" style="font-weight: 600; color: var(--slate-800);">-</div></div>
            </div>

            <table style="width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 14px;">
                <thead>
                    <tr style="border-bottom: 1.5px solid var(--slate-200); text-align: left; color: var(--slate-500);">
                        <th style="padding: 6px 0;">Item Description</th>
                        <th style="padding: 6px 0; text-align: center;">Qty</th>
                        <th style="padding: 6px 0; text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody id="recItemsList">
                    <!-- Injected item rows -->
                </tbody>
            </table>

            <div style="border-top: 2px dashed #cbd5e1; padding-top: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; color: var(--slate-600); margin-bottom: 4px;">
                    <span>Doctor Consultation Fee:</span>
                    <span id="recConsultFee" style="font-weight: 600;">$0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; color: var(--slate-600); margin-bottom: 6px;">
                    <span>Medications Subtotal:</span>
                    <span id="recMedsSubtotal" style="font-weight: 600;">$0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; color: var(--slate-900); padding-top: 8px; border-top: 1px solid var(--slate-200);">
                    <span>TOTAL PAID:</span>
                    <span id="recTotalAmount" style="color: #059669; font-size: 18px;">$0.00</span>
                </div>
            </div>

            <div style="text-align: center; margin-top: 16px; font-size: 11px; color: var(--slate-400);">
                Thank you for visiting MediCare Health Centre. Wish you good health!
            </div>
        </div>

        <div class="modal-footer print-hide" style="padding: 12px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between;">
            <button class="btn-secondary" onclick="closeReceiptModal()"><i class="fa-solid fa-xmark"></i> Close &amp; Finish</button>
            <button class="btn-dispense" onclick="printReceipt()" style="background-color: #0284c7; padding: 8px 20px;"><i class="fa-solid fa-print" style="margin-right: 4px;"></i> Print Receipt</button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printableReceipt, #printableReceipt * {
        visibility: visible;
    }
    #printableReceipt {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100% !important;
        box-shadow: none !important;
        border: none !important;
    }
    .print-hide {
        display: none !important;
    }
}
</style>

<script>
let pendingVal = <?php echo $counts['pending']; ?>;
let dispensedVal = <?php echo $dispensedToday; ?>;

function filterQueue() {
    const query = document.getElementById('queueSearch').value.toLowerCase();
    const cards = document.getElementsByClassName('queue-item-card');
    let hasMatches = false;

    for (let card of cards) {
        const patient = card.getAttribute('data-patient') || '';
        const rx      = card.getAttribute('data-rx')      || '';
        const doctor  = card.getAttribute('data-doctor')  || '';

        if (patient.includes(query) || rx.includes(query) || doctor.includes(query)) {
            card.style.display = 'flex';
            hasMatches = true;
        } else {
            card.style.display = 'none';
        }
    }

    const emptyState = document.getElementById('emptyState');
    if (emptyState) {
        emptyState.style.display = (!hasMatches && query !== '') ? 'block' : 'none';
    }
}

function selectPayMethod(method, el) {
    document.querySelectorAll('.pay-method-card').forEach(card => {
        card.style.border = '1px solid var(--border-color)';
        card.style.background = 'white';
        card.classList.remove('active');
    });
    el.style.border = '2px solid var(--teal)';
    el.style.background = '#f0fdf4';
    el.classList.add('active');
    document.getElementById('selectedPaymentMethod').value = method;
}

function openDispenseModal(btn) {
    const patientName  = btn.dataset.patientName;
    const initials     = btn.dataset.initials;
    const doctorName   = btn.dataset.doctorName;
    const medications  = JSON.parse(btn.dataset.meds);
    const rowId        = btn.dataset.rowId;
    const recordId     = btn.dataset.recordId;
    const consultFee   = parseFloat(btn.dataset.consultFee || 50.00);
    const medsSubtotal = parseFloat(btn.dataset.medsSubtotal || 0.00);
    const totalAmount  = parseFloat(btn.dataset.totalAmount || (consultFee + medsSubtotal));

    document.getElementById('modalPatientName').innerText = patientName;
    const avatar = document.getElementById('modalPatientAvatar');
    avatar.innerText = initials;
    avatar.style.backgroundColor = 'var(--teal)';
    document.getElementById('modalDoctorName').innerText = doctorName;
    document.getElementById('targetRowId').value = rowId;
    document.getElementById('targetRecordId').value = recordId;
    document.getElementById('pharmacistNotes').value = 'Take medication as directed by doctor. Drink plenty of water.';

    // Populate billing fees
    document.getElementById('modalConsultFee').innerText = '$' + consultFee.toFixed(2);
    document.getElementById('modalMedsSubtotal').innerText = '$' + medsSubtotal.toFixed(2);
    document.getElementById('modalTotalAmount').innerText = '$' + totalAmount.toFixed(2);

    // Reset payment method to Cash
    document.getElementById('selectedPaymentMethod').value = 'Cash';
    document.querySelectorAll('.pay-method-card').forEach((card, idx) => {
        if (idx === 0) {
            card.style.border = '2px solid var(--teal)';
            card.style.background = '#f0fdf4';
            card.classList.add('active');
        } else {
            card.style.border = '1px solid var(--border-color)';
            card.style.background = 'white';
            card.classList.remove('active');
        }
    });

    const listContainer = document.getElementById('medicationsCheckboxList');
    listContainer.innerHTML = '';
    medications.forEach((med) => {
        const div = document.createElement('div');
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.justifyContent = 'space-between';
        div.style.padding = '8px 12px';
        div.style.backgroundColor = 'white';
        div.style.borderRadius = 'var(--radius-sm)';
        div.style.border = '1px solid var(--border-color)';
        const medLabel = typeof med === 'string' ? med : `${med.brand_name} ${med.dosage}`;
        const itemTotal = med.item_total ? `$${parseFloat(med.item_total).toFixed(2)}` : '';
        div.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-circle-check" style="color: var(--teal); font-size: 15px;"></i>
                <span style="font-size: 13px; font-weight: 600; color: var(--slate-800);">${medLabel} x ${med.quantity || 1}</span>
            </div>
            <span style="font-size: 13px; font-weight: 700; color: var(--slate-600);">${itemTotal}</span>
        `;
        listContainer.appendChild(div);
    });

    document.getElementById('dispenseModal').classList.add('active');
}

function closeDispenseModal() {
    document.getElementById('dispenseModal').classList.remove('active');
}

function submitDispense(event) {
    event.preventDefault();
    const rowId     = document.getElementById('targetRowId').value;
    const recordId  = document.getElementById('targetRecordId').value;
    const patientName = document.getElementById('modalPatientName').innerText;
    const notes     = document.getElementById('pharmacistNotes').value;
    const payMethod = document.getElementById('selectedPaymentMethod').value;
    const totalAmt  = document.getElementById('modalTotalAmount').innerText;
    const btn       = document.getElementById('confirmDispenseBtn');

    btn.disabled = true;
    btn.innerText = 'Processing...';

    fetch('../../api/pharmacy_dispense.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ record_id: recordId, notes: notes, payment_method: payMethod })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeDispenseModal();
            showToast(patientName, payMethod, totalAmt);
            if (data.receipt) {
                showReceiptModal(data.receipt);
            } else {
                setTimeout(() => location.reload(), 1200);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle" style="margin-right: 4px;"></i> Confirm &amp; Collect Payment';
    });
}

function showToast(patientName, payMethod, totalAmt) {
    const toast = document.getElementById('successToast');
    document.getElementById('toastDetails').innerText = `${patientName}'s bill (${totalAmt}) paid via ${payMethod}. Admin billing updated!`;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4000);
}

function showReceiptModal(receipt) {
    if (!receipt) return;
    document.getElementById('recInvoiceNo').innerText = receipt.invoice_no;
    document.getElementById('recDate').innerText = receipt.payment_date;
    document.getElementById('recPayMethod').innerText = receipt.payment_method;
    document.getElementById('recPatientName').innerText = receipt.patient_name;
    document.getElementById('recDoctorName').innerText = receipt.doctor_name;
    document.getElementById('recConsultFee').innerText = '$' + parseFloat(receipt.consultation_fee).toFixed(2);
    document.getElementById('recMedsSubtotal').innerText = '$' + parseFloat(receipt.medications_subtotal).toFixed(2);
    document.getElementById('recTotalAmount').innerText = '$' + parseFloat(receipt.total_amount).toFixed(2);

    const tbody = document.getElementById('recItemsList');
    tbody.innerHTML = '';
    if (receipt.medications && receipt.medications.length > 0) {
        receipt.medications.forEach(m => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f1f5f9';
            const itemTotal = parseFloat(m.item_total || 0).toFixed(2);
            tr.innerHTML = `
                <td style="padding: 6px 0;">${m.brand_name} ${m.dosage}</td>
                <td style="padding: 6px 0; text-align: center;">${m.quantity}</td>
                <td style="padding: 6px 0; text-align: right;">$${itemTotal}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('receiptModal').classList.add('active');
}

function printReceipt() {
    window.print();
}

function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('active');
    location.reload();
}
</script>

</body>
</html>
