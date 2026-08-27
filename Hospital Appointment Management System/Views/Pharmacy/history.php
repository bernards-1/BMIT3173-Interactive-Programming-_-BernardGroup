<?php
// Views/Pharmacy/history.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pharmacist') {
    header('Location: ../Login/login.php');
    exit;
}
require_once '../../db.php';
require_once '../../Models/Pharmacy.php';

$history    = Pharmacy::getDispensingHistory('all');
$stats      = Pharmacy::getHistoryStats();
$dailyRevs  = Pharmacy::getDailyRevenueLast7Days();

// Build 7-day chart data (fill gaps with 0)
$chartDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartDays[$d] = ['label' => date('M d', strtotime($d)), 'revenue' => 0];
}
foreach ($dailyRevs as $row) {
    if (isset($chartDays[$row['date']])) {
        $chartDays[$row['date']]['revenue'] = (float)$row['revenue'];
    }
}
$chartDays  = array_values($chartDays);
$maxRevenue = max(array_column($chartDays, 'revenue') ?: [1]);

$avatarColors = ['#3b82f6','#a855f7','#10b981','#ef4444','#f59e0b','#06b6d4','#ec4899','#64748b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispensing History - MediCare Pharmacy</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Pharmacy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--slate-50);">

<?php include '../Layout/Pharmacy/navigation.php'; ?>

<div class="dashboard-container">

    <!-- Header -->
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 24px; font-weight: 700; color: var(--slate-900);">Dispensing History</h2>
        <p class="queue-header-subtitle">Record of all dispensed prescriptions</p>
    </div>

    <!-- Status Cards -->
    <div class="history-status-cards">
        <div class="history-status-card dispensed">
            <div class="history-status-val dispensed"><?php echo $stats['total_dispensed']; ?></div>
            <div class="history-status-lbl">Total Dispensed</div>
        </div>
        <div class="history-status-card cancelled">
            <div class="history-status-val cancelled"><?php echo $stats['cancelled']; ?></div>
            <div class="history-status-lbl">Cancelled</div>
        </div>
        <div class="history-status-card revenue-today">
            <div class="history-status-val revenue-today">$<?php echo number_format($stats['today_revenue'], 2); ?></div>
            <div class="history-status-lbl">Today's Revenue</div>
        </div>
        <div class="history-status-card revenue-total">
            <div class="history-status-val revenue-total">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
            <div class="history-status-lbl">Total Revenue</div>
        </div>
    </div>

    <!-- Daily Revenue Chart -->
    <div class="section-card">
        <div class="card-header-flex">
            <div class="card-title-group">
                <h3>Daily Revenue</h3>
                <p>Last 7 days</p>
            </div>
        </div>

        <div class="chart-container-css" style="display: flex;">
            <div class="chart-y-axis" style="width: 60px;">
                <?php
                $yMax  = max($maxRevenue, 100);
                $yStep = ceil($yMax / 4 / 100) * 100;
                for ($i = 4; $i >= 0; $i--) {
                    echo '<span>$' . number_format($i * $yStep) . '</span>';
                }
                ?>
            </div>
            <div class="chart-main-area">
                <div class="chart-grid-lines">
                    <?php for ($i = 0; $i < 5; $i++) echo '<div class="chart-grid-line"></div>'; ?>
                </div>
                <div class="chart-bars">
                    <?php foreach ($chartDays as $day):
                        $pct = $yMax > 0 ? round(($day['revenue'] / $yMax) * 100) : 0;
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar" style="height: <?php echo max($pct, $day['revenue'] > 0 ? 2 : 0); ?>%;">
                            <div class="chart-bar-tooltip">$<?php echo number_format($day['revenue'], 0); ?> Revenue</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="chart-x-axis" style="padding-left: 60px;">
            <?php foreach ($chartDays as $day): ?>
            <div class="chart-x-label"><?php echo e($day['label']); ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-filter-box-flex">
        <div class="search-input-wrapper" style="flex-grow: 1; max-width: 500px;">
            <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="historySearch" oninput="filterHistory()" class="search-input-control" placeholder="Search by patient or doctor...">
        </div>
        <div class="filter-btn-group">
            <button class="filter-btn active" id="filter-all"       onclick="setFilter('all')">All</button>
            <button class="filter-btn"        id="filter-today"     onclick="setFilter('today')">Today</button>
            <button class="filter-btn"        id="filter-yesterday" onclick="setFilter('yesterday')">Yesterday</button>
        </div>
    </div>

    <!-- History Table -->
    <div class="history-table-card">
        <div class="table-wrapper">
            <table class="custom-table" id="historyTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Record ID</th>
                        <th>Date &amp; Time</th>
                        <th>Prescribing Doctor</th>
                        <th>Medication</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: 40px; color: var(--slate-400);">
                            <i class="fa-solid fa-folder-open" style="font-size: 36px; margin-bottom: 10px; display: block;"></i>
                            No dispensing records yet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($history as $idx => $row):
                        $color    = $avatarColors[$idx % count($avatarColors)];
                        $initials = strtoupper(substr(str_replace(' ', '', $row['patient_name']), 0, 2));
                        $dispensedAt = $row['dispensed_at'] ? strtotime($row['dispensed_at']) : null;
                        $dateLabel   = $dispensedAt ? date('M d, h:i A', $dispensedAt) : '—';

                        // Determine date bucket for filter
                        $today     = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $rowDate   = $dispensedAt ? date('Y-m-d', $dispensedAt) : '';
                        if ($rowDate === $today) $bucket = 'today';
                        elseif ($rowDate === $yesterday) $bucket = 'yesterday';
                        else $bucket = 'older';
                    ?>
                    <tr class="history-row"
                        data-date="<?php echo e($bucket); ?>"
                        data-search="<?php echo e(strtolower($row['patient_name'] . ' ' . $row['record_id'] . ' ' . $row['doctor_name'])); ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="patient-avatar-circle" style="background-color: <?php echo e($color); ?>; width: 30px; height: 30px; font-size: 11px;"><?php echo e($initials); ?></div>
                                <span style="font-weight: 600;"><?php echo e($row['patient_name']); ?></span>
                            </div>
                        </td>
                        <td><span style="font-weight: 500; font-family: monospace;"><?php echo e($row['record_id']); ?></span></td>
                        <td><?php echo e($dateLabel); ?></td>
                        <td><?php echo e($row['doctor_name']); ?></td>
                        <td style="max-width: 200px; white-space: normal; font-size: 13px;"><?php echo e($row['medications']); ?></td>
                        <td style="font-weight: 600; color: var(--slate-900);">$<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td><span class="badge success">Dispensed</span></td>
                        <td style="text-align: right;">
                            <button class="action-icon-btn"
                                title="View Details"
                                onclick="openHistoryDetail({
                                    record_id:     '<?php echo e($row['record_id']); ?>',
                                    patient_name:  '<?php echo e(addslashes($row['patient_name'])); ?>',
                                    doctor_name:   '<?php echo e(addslashes($row['doctor_name'])); ?>',
                                    medications:   '<?php echo e(addslashes($row['medications'])); ?>',
                                    total_amount:  '<?php echo number_format((float)$row['total_amount'], 2); ?>',
                                    dispensed_at:  '<?php echo e($dateLabel); ?>',
                                    notes:         '<?php echo e(addslashes($row['dispense_notes'] ?? '')); ?>'
                                })">
                                <i class="fa-solid fa-receipt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="noHistory" style="display: none; text-align: center; padding: 40px 20px;">
                <i class="fa-solid fa-folder-open" style="font-size: 40px; color: var(--slate-400); margin-bottom: 12px;"></i>
                <h4 style="font-weight: 700; color: var(--slate-700); font-size: 15px;">No records found</h4>
                <p style="font-size: 13px; color: var(--slate-400);">No history rows match your search/filter criteria.</p>
            </div>
        </div>
    </div>
</div>

<!-- History Detail Modal -->
<div class="modal-overlay" id="historyDetailModal">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-header" style="background-color: var(--teal);">
            <div class="modal-title">Dispensing Record Detail</div>
            <button class="modal-close" onclick="closeHistoryDetail()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tbody id="historyDetailBody"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeHistoryDetail()">Close</button>
        </div>
    </div>
</div>

<script>
let currentFilter = 'all';

function setFilter(filterType) {
    currentFilter = filterType;
    ['all','today','yesterday'].forEach(f => document.getElementById('filter-' + f).classList.remove('active'));
    document.getElementById('filter-' + filterType).classList.add('active');
    applyFilters();
}

function filterHistory() { applyFilters(); }

function applyFilters() {
    const query = document.getElementById('historySearch').value.toLowerCase();
    const rows  = document.getElementsByClassName('history-row');
    let visible = 0;

    for (let row of rows) {
        const text   = row.getAttribute('data-search') || '';
        const bucket = row.getAttribute('data-date')   || '';
        const matchSearch = text.includes(query) || !query;
        const matchDate   = currentFilter === 'all' || bucket === currentFilter;

        if (matchSearch && matchDate) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    }
    document.getElementById('noHistory').style.display = visible === 0 ? 'block' : 'none';
}

function openHistoryDetail(d) {
    const rows = [
        ['Record ID',  d.record_id],
        ['Patient',    d.patient_name],
        ['Doctor',     d.doctor_name],
        ['Medication', d.medications],
        ['Amount',     '$' + d.total_amount],
        ['Dispensed',  d.dispensed_at],
        ['Notes',      d.notes || 'N/A'],
    ];
    const tbody = document.getElementById('historyDetailBody');
    tbody.innerHTML = rows.map(([label, val]) =>
        `<tr>
            <td style="padding: 8px 6px; font-weight: 600; color: var(--slate-600); white-space: nowrap; width: 120px;">${label}</td>
            <td style="padding: 8px 6px; color: var(--slate-900);">${val}</td>
         </tr>`
    ).join('');
    document.getElementById('historyDetailModal').classList.add('active');
}
function closeHistoryDetail() {
    document.getElementById('historyDetailModal').classList.remove('active');
}
</script>

</body>
</html>
