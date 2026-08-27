<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$data = $controller->reports();

$deptColors = ['#2563eb','#10b981','#f59e0b','#a855f7','#ef4444','#94a3b8'];
$totalDeptRevenue = array_sum(array_column($data['revenueByDept'], 'revenue'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header-title h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .header-title p { font-size: 14px; color: #64748b; }
        .action-bar { display: flex; align-items: center; gap: 12px; }
        .dropdown { padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: white; font-size: 14px; color: #475569; }
        .btn-export { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #475569; cursor: pointer; display: flex; align-items: center; gap: 8px; }

        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
        .metric-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; display: flex; flex-direction: column; }
        .metric-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .metric-icon { font-size: 20px; }
        .metric-value { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .metric-title { font-size: 14px; color: #475569; font-weight: 500; }
        .metric-trend { display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; }
        .trend-positive { color: #065f46; }
        .trend-icon { font-size: 10px; }

        .reports-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; margin-bottom: 24px; }
        .report-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        .report-card h2 { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 24px; }

        /* Appointment Status Summary */
        .appt-summary { display: flex; gap: 16px; margin-bottom: 24px; }
        .appt-stat { flex: 1; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; }
        .appt-stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
        .appt-stat-label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .appt-stat.completed .appt-stat-value { color: #10b981; }
        .appt-stat.scheduled .appt-stat-value { color: #2563eb; }
        .appt-stat.cancelled .appt-stat-value { color: #ef4444; }

        /* Bar chart */
        .bar-chart-container { display: flex; flex-direction: column; gap: 14px; }
        .bar-row { display: flex; align-items: center; gap: 12px; font-size: 13px; }
        .bar-label { width: 110px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
        .bar-track { flex: 1; height: 10px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 99px; transition: width 0.5s ease; }
        .bar-amount { width: 80px; text-align: right; font-weight: 600; color: #0f172a; }

        /* Staff grid */
        .staff-overview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .staff-stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; }
        .staff-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .staff-stat-icon.blue { background: #eff6ff; color: #2563eb; }
        .staff-stat-icon.purple { background: #faf5ff; color: #a855f7; }
        .staff-stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
        .staff-stat-label { font-size: 14px; color: #64748b; }

        .section-title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 24px 0 16px; }
    </style>
</head>
<body>

<?php include '../Layout/Admin/navigation.php'; ?>

<div class="dashboard-container">
    <div class="header-actions">
        <div class="header-title">
            <h1>Reports & Analytics</h1>
            <p>Comprehensive insights and performance metrics</p>
        </div>
        <div class="action-bar">
            <button class="btn-export" onclick="exportReportCSV()"><i class="fa-solid fa-download"></i> Export Report</button>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Total Revenue</span>
                <div class="metric-icon" style="color: #10b981;"><i class="fa-solid fa-dollar-sign"></i></div>
            </div>
            <div class="metric-value"><?= formatCurrency($data['totalRevenue']) ?></div>
            <div class="metric-trend trend-positive"><i class="fa-solid fa-arrow-trend-up trend-icon"></i> From paid invoices</div>
        </div>
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title"><?= __('Total Appointments') ?></span>
                <div class="metric-icon" style="color: #2563eb;"><i class="fa-regular fa-calendar-check"></i></div>
            </div>
            <div class="metric-value"><?= number_format($data['totalAppointments']) ?></div>
            <div class="metric-trend trend-positive"><i class="fa-solid fa-arrow-trend-up trend-icon"></i> All time</div>
        </div>
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title"><?= __('Active Patients') ?></span>
                <div class="metric-icon" style="color: #a855f7;"><i class="fa-solid fa-user-group"></i></div>
            </div>
            <div class="metric-value"><?= number_format($data['activePatients']) ?></div>
            <div class="metric-trend trend-positive"><i class="fa-solid fa-arrow-trend-up trend-icon"></i> Registered</div>
        </div>
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title"><?= __('Patient Satisfaction') ?></span>
                <div class="metric-icon" style="color: #f59e0b;"><i class="fa-solid fa-face-smile"></i></div>
            </div>
            <div class="metric-value"><?= $data['satisfaction'] ?>%</div>
            <div class="metric-trend trend-positive"><i class="fa-solid fa-arrow-trend-up trend-icon"></i> Estimated</div>
        </div>
    </div>

    <!-- Appointment Status Breakdown -->
    <div class="section-title">Appointment Status</div>
    <div class="appt-summary">
        <div class="appt-stat completed">
            <div class="appt-stat-value"><?= $data['completedAppointments'] ?></div>
            <div class="appt-stat-label"><?= __('Completed') ?></div>
        </div>
        <div class="appt-stat scheduled">
            <div class="appt-stat-value"><?= $data['scheduledAppointments'] ?></div>
            <div class="appt-stat-label"><?= __('Scheduled') ?></div>
        </div>
        <div class="appt-stat cancelled">
            <div class="appt-stat-value"><?= $data['cancelledAppointments'] ?></div>
            <div class="appt-stat-label"><?= __('Cancelled') ?></div>
        </div>
        <div class="appt-stat">
            <div class="appt-stat-value"><?= $data['totalAppointments'] > 0 ? round(($data['completedAppointments'] / $data['totalAppointments']) * 100) : 0 ?>%</div>
            <div class="appt-stat-label"><?= __('Completion Rate') ?></div>
        </div>
    </div>

    <!-- Revenue & Department -->
    <div class="reports-grid">
        <div class="report-card">
            <h2><?= __('Revenue by Department') ?></h2>
            <?php if (empty($data['revenueByDept'])): ?>
                <p style="color:#94a3b8; text-align:center; padding: 40px 0;">No payment data available yet.</p>
            <?php else: ?>
                <div class="bar-chart-container">
                    <?php foreach ($data['revenueByDept'] as $i => $dept): ?>
                        <?php
                            $pct = $totalDeptRevenue > 0 ? round(($dept['revenue'] / $totalDeptRevenue) * 100) : 0;
                            $color = $deptColors[$i % count($deptColors)];
                        ?>
                        <div class="bar-row">
                            <div class="bar-label"><?= e($dept['specialization']) ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
                            </div>
                            <div class="bar-amount"><?= formatCurrency($dept['revenue'], 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <h2>Monthly Revenue (Last 6 Months)</h2>
            <?php if (empty($data['monthlyRevenue'])): ?>
                <p style="color:#94a3b8; text-align:center; padding: 40px 0;">No payment data available yet.</p>
            <?php else: ?>
                <?php
                    $maxMonthly = max(array_column($data['monthlyRevenue'], 'total')) ?: 1;
                ?>
                <div class="bar-chart-container">
                    <?php foreach ($data['monthlyRevenue'] as $m): ?>
                        <?php $pct = round(($m['total'] / $maxMonthly) * 100); ?>
                        <div class="bar-row">
                            <div class="bar-label"><?= e($m['month']) ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?= $pct ?>%; background: #2563eb;"></div>
                            </div>
                            <div class="bar-amount"><?= formatCurrency($m['total'], 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff Overview -->
    <div class="section-title"><?= __('Staff Overview') ?></div>
    <div class="staff-overview-grid">
        <div class="staff-stat-card">
            <div class="staff-stat-icon blue"><i class="fa-solid fa-user-doctor"></i></div>
            <div>
                <div class="staff-stat-value"><?= $data['totalDoctors'] ?></div>
                <div class="staff-stat-label">Total Doctors</div>
            </div>
        </div>
        <div class="staff-stat-card">
            <div class="staff-stat-icon purple"><i class="fa-solid fa-pills"></i></div>
            <div>
                <div class="staff-stat-value"><?= $data['totalPharmacists'] ?></div>
                <div class="staff-stat-label">Total Pharmacists</div>
            </div>
        </div>
    </div>

</div>

<script>
    function exportReportCSV() {
        const data = [
            ["Metric", "Value"],
            ["Total Revenue", "<?= formatCurrency($data['totalRevenue']) ?>"],
            ["Total Appointments", "<?= $data['totalAppointments'] ?>"],
            ["Completed Appointments", "<?= $data['completedAppointments'] ?>"],
            ["Scheduled Appointments", "<?= $data['scheduledAppointments'] ?>"],
            ["Cancelled Appointments", "<?= $data['cancelledAppointments'] ?>"],
            ["Active Patients", "<?= $data['activePatients'] ?>"],
            ["Patient Satisfaction", "<?= $data['satisfaction'] ?>%"],
            ["Total Doctors", "<?= $data['totalDoctors'] ?>"],
            ["Total Pharmacists", "<?= $data['totalPharmacists'] ?>"],
            ["", ""], // empty row
            ["Department", "Revenue"]
        ];

        // Add Revenue by Department
        <?php foreach ($data['revenueByDept'] as $dept): ?>
            data.push(["<?= addslashes(e($dept['specialization'])) ?>", "<?= formatCurrency($dept['revenue']) ?>"]);
        <?php endforeach; ?>

        data.push(["", ""]); // empty row
        data.push(["Month", "Revenue"]);

        // Add Monthly Revenue
        <?php foreach ($data['monthlyRevenue'] as $m): ?>
            data.push(["<?= addslashes(e($m['month'])) ?>", "<?= formatCurrency($m['total']) ?>"]);
        <?php endforeach; ?>

        let csvContent = "data:text/csv;charset=utf-8,";
        data.forEach(function(rowArray) {
            let row = rowArray.map(item => '"' + item + '"').join(",");
            csvContent += row + "\r\n";
        });

        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "medicare_hospital_report_<?= date('Y-m-d') ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>