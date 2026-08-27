<?php
require_once '../../db.php';
require_once '../../Controllers/AdminController.php';

$controller = new AdminController();
$data = $controller->billing();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Payments - MediCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Layout/Admin/style.css">
    <style>
        .header-section { margin-bottom: 24px; }
        .header-section h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .header-section p { font-size: 14px; color: #64748b; }

        .billing-summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
        .summary-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        .sum-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 18px; }
        .icon-green { background: #d1fae5; color: #10b981; }
        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-yellow { background: #fef3c7; color: #f59e0b; }
        .icon-red { background: #fee2e2; color: #ef4444; }
        .summary-card h3 { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .summary-card p { font-size: 14px; color: #475569; font-weight: 500; }

        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .table-header-title { padding: 20px 24px; font-size: 16px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        td { padding: 16px 24px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
        
        .status-badge { padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; }
        .badge-paid { background: #e0e7ff; color: #3730a3; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; }

        .action-btns { display: flex; gap: 8px; }
        .btn-download { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>

<?php include '../Layout/Admin/navigation.php'; ?>

<div class="dashboard-container">
    <div class="header-section">
        <h1><?= __('Billing & Payments') ?></h1>
        <p>Manage invoices and payment records</p>
    </div>

    <div class="billing-summary-grid">
        <div class="summary-card">
            <div class="sum-icon icon-green"><i class="fa-solid fa-dollar-sign"></i></div>
            <h3><?= formatCurrency($data['stats']['totalRevenue']) ?></h3><p>Total Revenue</p>
        </div>
        <div class="summary-card">
            <div class="sum-icon icon-blue"><i class="fa-regular fa-credit-card"></i></div>
            <h3><?= formatCurrency($data['stats']['paid']) ?></h3><p><?= __('Paid') ?></p>
        </div>
        <div class="summary-card">
            <div class="sum-icon icon-yellow"><i class="fa-regular fa-file-lines"></i></div>
            <h3><?= formatCurrency($data['stats']['pending']) ?></h3><p><?= __('Pending') ?></p>
        </div>
        <div class="summary-card">
            <div class="sum-icon icon-red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <h3><?= formatCurrency($data['stats']['overdue']) ?></h3><p><?= __('Overdue') ?></p>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header-title">Recent Invoices</div>
        <table>
            <thead>
                <tr>
                    <th>Invoice ID</th><th><?= __('Patient') ?></th><th><?= __('Service') ?></th><th><?= __('Amount') ?></th><th><?= __('Date') ?></th><th><?= __('Status') ?></th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['payments'] as $pay): ?>
                <tr>
                    <td><?= e($pay['invoice_no']) ?></td>
                    <td><?= e($pay['patient_name']) ?></td>
                    <td><?= e($pay['service_name']) ?></td>
                    <td><?= formatCurrency($pay['amount']) ?></td>
                    <td><?= e(formatDate($pay['payment_date'])) ?></td>
                    <td>
                        <?php if ($pay['payment_status'] === 'Paid'): ?>
                            <span class="status-badge badge-paid"><?= __('Paid') ?></span>
                        <?php elseif ($pay['payment_status'] === 'Unpaid'): ?>
                            <span class="status-badge badge-pending"><?= __('Pending') ?></span>
                        <?php else: ?>
                            <span class="status-badge badge-overdue"><?= e($pay['payment_status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="action-btns"><button class="btn-download" onclick="downloadBill('<?= e($pay['invoice_no']) ?>', '<?= e($pay['patient_name']) ?>', '<?= e($pay['service_name']) ?>', '<?= formatCurrency($pay['amount']) ?>', '<?= e(formatDate($pay['payment_date'])) ?>', '<?= e($pay['payment_status']) ?>')"><i class="fa-solid fa-download"></i><?= __('Download') ?></button></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($data['payments'])): ?>
                    <tr><td colspan="7" style="text-align:center;">No invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function downloadBill(invoiceNo, patient, service, amount, date, status) {
        // Create a printable receipt window
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Invoice - ${invoiceNo}</title>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 40px; color: #333; }
                    .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); font-size: 16px; line-height: 24px; }
                    .header { display: flex; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
                    .title { font-size: 32px; font-weight: bold; color: #2563eb; }
                    .info { text-align: right; }
                    .details { display: flex; justify-content: space-between; margin-bottom: 40px; }
                    .item-table { width: 100%; text-align: left; border-collapse: collapse; margin-bottom: 40px; }
                    .item-table th, .item-table td { padding: 12px; border-bottom: 1px solid #eee; }
                    .item-table th { background: #f8fafc; font-weight: bold; }
                    .total { text-align: right; font-size: 20px; font-weight: bold; margin-top: 20px; }
                    .status { display: inline-block; padding: 6px 12px; border-radius: 4px; font-weight: bold; margin-top: 10px; }
                    .status-paid { background: #d1fae5; color: #065f46; }
                    .status-unpaid { background: #fee2e2; color: #b91c1c; }
                </style>
            </head>
            <body>
                <div class="invoice-box">
                    <div class="header">
                        <div>
                            <div class="title">MediCare Hospital</div>
                            <p>123 Health Avenue<br>Medical City, MC 10012</p>
                        </div>
                        <div class="info">
                            <h2>INVOICE</h2>
                            <p><strong>Invoice #:</strong> ${invoiceNo}<br>
                            <strong>Date:</strong> ${date}</p>
                        </div>
                    </div>
                    
                    <div class="details">
                        <div>
                            <strong>Bill To:</strong><br>
                            ${patient}
                        </div>
                        <div style="text-align: right;">
                            <span class="status ${status.toLowerCase() === 'paid' ? 'status-paid' : 'status-unpaid'}">Status: ${status.toUpperCase()}</span>
                        </div>
                    </div>
                    
                    <table class="item-table">
                        <thead>
                            <tr>
                                <th>Description / Service</th>
                                <th style="text-align: right;"><?= __('Amount') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${service}</td>
                                <td style="text-align: right;">${amount}</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="total">
                        Total Amount: ${amount}
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function(){ window.close(); }, 500);
                    }
                <\/script>
            </body>
            </html>
        `;
        
        printWindow.document.write(html);
        printWindow.document.close();
    }
</script>

</body>
</html>