<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';
require_once '../../services/PharmacyConsumerService.php';

$controller = new AdminController();
$data = $controller->dashboard();

// Inter-Module Consumption: Admin consumes Pharmacy Module inventory alerts
$pharmacyService = new PharmacyConsumerService();
$pharmacyAlertResult = $pharmacyService->getInventoryAlerts(10);
$lowStockAlerts = ($pharmacyAlertResult['status'] === 'success' && isset($pharmacyAlertResult['data']['data'])) 
    ? $pharmacyAlertResult['data']['data'] 
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MediCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    
    <style>
        /* ==========================================
           图表加载动画 (CSS Chart Animations)
           ========================================== */
        
        /* 1. 柱状图平滑过渡动画 (Morphing Animation) */
        .bar {
            transform-origin: bottom; 
            /* 使用带有弹性的 cubic-bezier 曲线优化动画效果 */
            transition: height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s;
        }

        /* 柱子 Hover 悬停放大微交互 */
        .bar-group:hover .bar {
            filter: brightness(1.1);
            transition: filter 0.2s;
        }

        /* 2. 饼图弹出旋转动画 */
        @keyframes piePop {
            0% {
                transform: scale(0.3) rotate(-90deg);
                opacity: 0;
            }
            70% {
                transform: scale(1.05) rotate(5deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .pie-chart {
            animation: piePop 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            transition: transform 0.3s ease;
        }

        .pie-chart:hover {
            transform: scale(1.03); /* 鼠标悬停微放大 */
        }
    </style>
</head>
<body>

<?php include '../Layout/Admin/navigation.php'; ?>

<div class="dashboard-container">
    
    <div class="page-header">
        <div>
            <h1><?= __('Admin Dashboard') ?></h1>
            <p><?= date('l, F j, Y') ?></p>
        </div>
        <div class="system-status">
            <i class="fa-solid fa-wave-square"></i> System Live 
            <div class="status-dot"></div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon icon-blue"><i class="fa-solid fa-user-group"></i></div>
                <div class="trend-badge"><i class="fa-solid fa-arrow-trend-up"></i> +12.5%</div>
            </div>
            <div class="stat-value"><?= number_format($data['totalPatients']) ?></div>
            <div class="stat-title"><?= __('Total Patients') ?></div>
            <div class="stat-subtitle">All registered patients</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon icon-green"><i class="fa-solid fa-user-doctor"></i></div>
                <div class="trend-badge"><i class="fa-solid fa-arrow-trend-up"></i> +5.2%</div>
            </div>
            <div class="stat-value"><?= number_format($data['activeDoctors']) ?></div>
            <div class="stat-title"><?= __('Active Doctors') ?></div>
            <div class="stat-subtitle">Currently active</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon icon-purple"><i class="fa-regular fa-calendar-check"></i></div>
                <div class="trend-badge"><i class="fa-solid fa-arrow-trend-up"></i> +8.3%</div>
            </div>
            <div class="stat-value"><?= number_format($data['todayAppointments']) ?></div>
            <div class="stat-title"><?= __('Today\'s Appointments') ?></div>
            <div class="stat-subtitle">Scheduled for today</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon icon-yellow"><i class="fa-solid fa-dollar-sign"></i></div>
                <div class="trend-badge"><i class="fa-solid fa-arrow-trend-up"></i> +18.7%</div>
            </div>
            <div class="stat-value"><?= formatCurrency($data['monthlyRevenue']) ?></div>
            <div class="stat-title"><?= __('Monthly Revenue') ?></div>
            <div class="stat-subtitle">Paid this month</div>
        </div>
    </div>
    
    <!-- Charts Section (带动画) -->
    <div class="dashboard-grid">
        <!-- 动态柱状图 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <h2>Performance Overview</h2>
                    <p>Jan – Jun <?= date('Y') ?></p>
                </div>
                <div class="toggle-group" id="chartToggle">
                    <button class="toggle-btn active" data-type="appointments">Appointments</button>
                    <button class="toggle-btn" data-type="revenue">Revenue</button>
                </div>
            </div>
            <div class="bar-chart">
                <div class="y-axis" id="yAxisLabels">
                    <span>600</span><span>450</span><span>300</span><span>150</span><span>0</span>
                </div>
                <div class="chart-area" id="chartArea">
                    <div class="grid-line" style="bottom: 100%"></div>
                    <div class="grid-line" style="bottom: 75%"></div>
                    <div class="grid-line" style="bottom: 50%"></div>
                    <div class="grid-line" style="bottom: 25%"></div>
                    <div class="grid-line" style="bottom: 0%"></div>
                    <!-- JS injects bar-groups here -->
                </div>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartData = {
        appointments: {
            yLabels: [600, 450, 300, 150, 0],
            data: [
                { label: 'Jan', dark: 35, light: 25 },
                { label: 'Feb', dark: 50, light: 40 },
                { label: 'Mar', dark: 65, light: 55 },
                { label: 'Apr', dark: 70, light: 65 },
                { label: 'May', dark: 80, light: 75 },
                { label: 'Jun', dark: 90, light: 85 }
            ]
        },
        revenue: {
            yLabels: ['$12k', '$9k', '$6k', '$3k', '$0'],
            data: [
                { label: 'Jan', dark: 40, light: 30 },
                { label: 'Feb', dark: 60, light: 45 },
                { label: 'Mar', dark: 55, light: 40 },
                { label: 'Apr', dark: 80, light: 60 },
                { label: 'May', dark: 75, light: 50 },
                { label: 'Jun', dark: 95, light: 70 }
            ]
        }
    };

    const chartArea = document.getElementById('chartArea');
    const yAxisLabels = document.getElementById('yAxisLabels');
    const toggleBtns = document.querySelectorAll('#chartToggle .toggle-btn');
    
    // 初始化柱子结构，高度为 0%
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    months.forEach((month) => {
        const group = document.createElement('div');
        group.className = 'bar-group';
        
        const barDark = document.createElement('div');
        barDark.className = 'bar bar-dark';
        barDark.style.height = '0%';
        
        const barLight = document.createElement('div');
        barLight.className = 'bar bar-light';
        barLight.style.height = '0%';
        
        const label = document.createElement('span');
        label.className = 'x-label';
        label.innerText = month;
        
        group.appendChild(barDark);
        group.appendChild(barLight);
        group.appendChild(label);
        
        chartArea.appendChild(group);
    });

    function updateChart(type) {
        const dataset = chartData[type];
        
        // Update Y axis labels
        yAxisLabels.innerHTML = '';
        dataset.yLabels.forEach(val => {
            const span = document.createElement('span');
            span.innerText = val;
            yAxisLabels.appendChild(span);
        });

        // Update bar heights with staggered transition
        const groups = chartArea.querySelectorAll('.bar-group');
        dataset.data.forEach((item, index) => {
            const group = groups[index];
            const dark = group.querySelector('.bar-dark');
            const light = group.querySelector('.bar-light');
            
            // 产生一点交错进入的视觉效果
            setTimeout(() => {
                dark.style.height = item.dark + '%';
                light.style.height = item.light + '%';
            }, index * 40);
        });
    }

    // 事件监听
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            toggleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateChart(btn.dataset.type);
        });
    });

    // 初次加载动画
    setTimeout(() => updateChart('appointments'), 100);
});
</script>

        <!-- 动态饼图 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <h2>Departments</h2>
                    <p>Appointment distribution</p>
                </div>
            </div>
            <div class="pie-chart-wrapper">
                <div class="pie-chart"></div>
            </div>
            <div class="legend-grid">
                <div class="legend-item">
                    <div class="legend-name"><div class="dot" style="background:#2563eb"></div> Cardiology</div>
                    <div class="legend-val">30%</div>
                </div>
                <div class="legend-item">
                    <div class="legend-name"><div class="dot" style="background:#10b981"></div> Neurology</div>
                    <div class="legend-val">25%</div>
                </div>
                <div class="legend-item">
                    <div class="legend-name"><div class="dot" style="background:#f59e0b"></div> Pediatrics</div>
                    <div class="legend-val">20%</div>
                </div>
                <div class="legend-item">
                    <div class="legend-name"><div class="dot" style="background:#a855f7"></div> Orthopedics</div>
                    <div class="legend-val">15%</div>
                </div>
                <div class="legend-item">
                    <div class="legend-name"><div class="dot" style="background:#94a3b8"></div> Other</div>
                    <div class="legend-val">10%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lists Section -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <h2><?= __('Recent Appointments') ?></h2>
                    <p><?= count($data['recentAppointments']) ?> recent scheduled</p>
                </div>
                <a href="appointments.php" class="view-all">View all <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left:4px;"></i></a>
            </div>
            
            <?php foreach ($data['recentAppointments'] as $apt): ?>
            <div class="list-row">
                <div class="person">
                    <div class="avatar avatar-blue"><?= substr($apt['patient_name'], 0, 2) ?></div>
                    <div>
                        <div class="name"><?= e($apt['patient_name']) ?></div>
                        <div class="desc"><?= e($apt['doctor_name']) ?></div>
                    </div>
                </div>
                <div class="status-col">
                    <div class="time"><i class="fa-regular fa-clock"></i> <?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                    <?php if ($apt['status'] === 'Completed'): ?>
                        <span class="badge badge-confirmed"><?= __('Completed') ?></span>
                    <?php elseif ($apt['status'] === 'Scheduled'): ?>
                        <span class="badge badge-pending"><?= __('Scheduled') ?></span>
                    <?php else: ?>
                        <span class="badge" style="background:#f1f5f9; color:#64748b;"><?= e($apt['status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($data['recentAppointments'])): ?>
                <div style="padding: 20px; text-align: center; color: #64748b;"><?= __('No recent appointments found.') ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <h2><?= __('System Status') ?></h2>
                    <p><?= __('Quick links and actions') ?></p>
                </div>
            </div>
            
            <div class="list-row" style="padding: 16px 0;">
                <div class="person">
                    <div class="avatar avatar-green"><i class="fa-solid fa-user-doctor"></i></div>
                    <div>
                        <div class="name">Manage Doctors</div>
                        <div class="desc">Add or remove doctors</div>
                    </div>
                </div>
                <div class="stat-right">
                    <a href="doctors.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">Manage</a>
                </div>
            </div>
            
            <div class="list-row" style="padding: 16px 0;">
                <div class="person">
                    <div class="avatar avatar-purple"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="name">Manage Patients</div>
                        <div class="desc">View patient directory</div>
                    </div>
                </div>
                <div class="stat-right">
                    <a href="patients.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">Manage</a>
                </div>
            </div>

            <!-- Inter-Module Service Consumer: Pharmacy Stock Alert Widget -->
            <div style="border-top:1px solid var(--border);margin-top:14px;padding-top:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-main);display:flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-pills" style="color:#ef4444;"></i>
                        <span>Pharmacy Low Stock (Inter-Module Feed)</span>
                    </div>
                    <span style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:6px;color:#64748b;">
                        HTTP <?= $pharmacyAlertResult['httpCode'] ?? 200 ?>
                    </span>
                </div>
                <?php if (!empty($lowStockAlerts)): ?>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <?php foreach (array_slice($lowStockAlerts, 0, 3) as $med): ?>
                        <div style="display:flex;justify-content:space-between;font-size:12px;background:#fff5f5;padding:6px 10px;border-radius:6px;border:1px solid #fee2e2;">
                            <span style="font-weight:500;color:#991b1b;"><?= e($med['brand_name'] ?? 'Medicine') ?></span>
                            <span style="font-weight:700;color:#ef4444;">Stock: <?= (int)($med['stock_quantity'] ?? 0) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="font-size:12px;color:#10b981;background:#f0fdf4;padding:6px 10px;border-radius:6px;border:1px solid #dcfce7;">
                        <i class="fa-solid fa-check"></i> All pharmacy stock levels normal (> 10 units)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>