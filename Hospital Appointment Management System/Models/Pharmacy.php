<?php
// Models/Pharmacy.php

class Pharmacy {

    // =========================================================
    // PHARMACIST PROFILE
    // =========================================================

    /**
     * Get pharmacist details by user_id (from session).
     */
    public static function getPharmacistByUserId($user_id) {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT ph.*, u.username, u.email
            FROM pharmacists ph
            JOIN users u ON u.user_id = ph.user_id
            WHERE ph.user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    // =========================================================
    // DASHBOARD STATS
    // =========================================================

    /**
     * Count prescriptions that are "Pending" (Completed appointment with prescriptions,
     * but not yet dispensed). We use appointments that are Completed as the trigger.
     * In practice, a prescription is "pending dispensing" when the appointment is Completed
     * but no dispense record exists. Since there's no separate dispense_logs table yet,
     * we count all prescriptions created today that haven't been flagged dispensed.
     *
     * Simplified approach: count prescriptions linked to today's Completed appointments.
     */
    public static function countPendingPrescriptions() {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT pr.prescription_id) as cnt
            FROM prescriptions pr
            JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            JOIN appointments a ON a.appointment_id = mr.appointment_id
            WHERE a.appointment_date = CURDATE()
              AND a.status = "Completed"
              AND pr.is_dispensed = 0
        ');
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int)$row['cnt'] : 0;
    }

    /**
     * Count dispensed prescriptions today.
     */
    public static function countDispensedToday() {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt
            FROM prescriptions
            WHERE DATE(dispensed_at) = CURDATE()
              AND is_dispensed = 1
        ');
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int)$row['cnt'] : 0;
    }

    /**
     * Calculate today's revenue from dispensed prescriptions.
     * Revenue = sum of (unit_price * quantity) for dispensed today.
     */
    public static function getTodayRevenue() {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(m.unit_price * pr.quantity), 0) as revenue
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            WHERE DATE(pr.dispensed_at) = CURDATE()
              AND pr.is_dispensed = 1
        ');
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (float)$row['revenue'] : 0.0;
    }

    /**
     * Count medicines with stock below minimum_stock threshold.
     */
    public static function countLowStockMedicines() {
        global $pdo;
        $stmt = $pdo->query('
            SELECT COUNT(*) as cnt
            FROM medicines
            WHERE stock_quantity > 0 AND stock_quantity <= minimum_stock
        ');
        $row = $stmt->fetch();
        return $row ? (int)$row['cnt'] : 0;
    }

    /**
     * Count medicines that are out of stock.
     */
    public static function countOutOfStockMedicines() {
        global $pdo;
        $stmt = $pdo->query('
            SELECT COUNT(*) as cnt
            FROM medicines
            WHERE stock_quantity = 0
        ');
        $row = $stmt->fetch();
        return $row ? (int)$row['cnt'] : 0;
    }

    /**
     * Get weekly dispensed prescription counts (last 7 days, Mon-Sun or past 7 days).
     */
    public static function getWeeklyDispensedCounts() {
        global $pdo;
        $stmt = $pdo->query('
            SELECT DAYNAME(dispensed_at) as day_name, COUNT(*) as cnt
            FROM prescriptions
            WHERE is_dispensed = 1
              AND dispensed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(dispensed_at), DAYNAME(dispensed_at)
            ORDER BY DATE(dispensed_at) ASC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Get last 7 days daily revenue.
     */
    public static function getDailyRevenueLast7Days() {
        global $pdo;
        $stmt = $pdo->query('
            SELECT DATE(pr.dispensed_at) as date,
                   DATE_FORMAT(pr.dispensed_at, "%b %d") as label,
                   COALESCE(SUM(m.unit_price * pr.quantity), 0) as revenue
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            WHERE pr.is_dispensed = 1
              AND pr.dispensed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(pr.dispensed_at)
            ORDER BY DATE(pr.dispensed_at) ASC
        ');
        return $stmt->fetchAll();
    }

    // =========================================================
    // PRESCRIPTION QUEUE
    // =========================================================

    /**
     * Get all pending prescriptions (from Completed appointments with undispensed prescriptions).
     * Returns grouped by medical_record (appointment).
     */
    /**
     * Get all pending prescriptions (from Completed appointments with undispensed prescriptions).
     * Returns grouped by medical_record (appointment).
     */
    public static function getPendingQueue() {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT
                pr.prescription_id,
                pr.record_id,
                pr.medicine_id,
                pr.dosage,
                pr.frequency,
                pr.duration,
                pr.instructions,
                pr.quantity,
                m.brand_name,
                m.generic_name,
                m.dosage AS med_dosage,
                m.unit_price,
                mr.medical_record_id,
                mr.appointment_id,
                a.appointment_date,
                a.appointment_time,
                p.patient_id,
                p.full_name AS patient_name,
                d.name AS doctor_name,
                d.initials,
                d.color,
                d.consultation_fee
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            JOIN appointments a ON a.appointment_id = mr.appointment_id
            JOIN patients p ON p.patient_id = mr.patient_id
            JOIN doctors d ON d.doctor_id = mr.doctor_id
            WHERE a.status = "Completed"
              AND pr.is_dispensed = 0
            ORDER BY a.appointment_time ASC
        ');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Group prescriptions by medical_record_id
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['record_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'record_id'        => $row['record_id'],
                    'appointment_id'   => $row['appointment_id'],
                    'appointment_time' => $row['appointment_time'],
                    'appointment_date' => $row['appointment_date'],
                    'patient_id'       => $row['patient_id'],
                    'patient_name'     => $row['patient_name'],
                    'doctor_name'      => $row['doctor_name'],
                    'initials'         => $row['initials'],
                    'color'            => $row['color'],
                    'consultation_fee' => (float)$row['consultation_fee'],
                    'medicines_subtotal' => 0.0,
                    'total_amount'     => (float)$row['consultation_fee'],
                    'medicines'        => [],
                ];
            }
            $medPrice = (float)$row['unit_price'] * (int)$row['quantity'];
            $grouped[$key]['medicines_subtotal'] += $medPrice;
            $grouped[$key]['total_amount'] += $medPrice;

            $grouped[$key]['medicines'][] = [
                'prescription_id' => $row['prescription_id'],
                'brand_name'      => $row['brand_name'],
                'generic_name'    => $row['generic_name'],
                'dosage'          => $row['dosage'],
                'quantity'        => $row['quantity'],
                'unit_price'      => (float)$row['unit_price'],
                'item_total'      => $medPrice,
                'instructions'    => $row['instructions'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * Count prescriptions by dispense status for the queue status cards.
     */
    public static function getQueueStatusCounts() {
        global $pdo;

        // Pending = not dispensed, linked to Completed appointments
        $stmt = $pdo->query('
            SELECT COUNT(DISTINCT pr.record_id) as cnt
            FROM prescriptions pr
            JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            JOIN appointments a ON a.appointment_id = mr.appointment_id
            WHERE a.status = "Completed" AND pr.is_dispensed = 0
        ');
        $pending = (int)$stmt->fetch()['cnt'];

        // Dispensed today
        $stmt2 = $pdo->query('
            SELECT COUNT(DISTINCT pr.record_id) as cnt
            FROM prescriptions pr
            WHERE pr.is_dispensed = 1 AND DATE(pr.dispensed_at) = CURDATE()
        ');
        $dispensedToday = (int)$stmt2->fetch()['cnt'];

        return [
            'pending'   => $pending,
            'dispensed' => $dispensedToday,
        ];
    }

    // =========================================================
    // DISPENSE PRESCRIPTION & PAYMENTS
    // =========================================================

    /**
     * Dispense prescriptions in a medical record and process payment.
     *
     * @param string $record_id
     * @param string $pharmacist_id
     * @param string $notes  Pharmacist notes
     * @param array $prescription_ids Optional list of selected prescription_ids to dispense
     * @param string $payment_method  Cash, Touch 'n Go, Credit Card
     * @return bool
     */
    public static function dispenseRecord($record_id, $pharmacist_id, $notes, $prescription_ids = [], $payment_method = 'Cash') {
        global $pdo;

        try {
            // Setup Observer Pattern for Stock Alerts
            require_once __DIR__ . '/StockObserver.php';
            $stockHandler = MedicineStockHandler::getInstance();
            StockWarningRegistry::clearWarnings(); // Reset warnings for this transaction
            $stockHandler->attach(new LowStockAlertNotifier());

            $pdo->beginTransaction();

            // Fetch undispensed prescriptions for this record
            if (!empty($prescription_ids) && is_array($prescription_ids)) {
                $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
                $params = array_merge([$record_id], $prescription_ids);
                $stmt = $pdo->prepare("
                    SELECT prescription_id, medicine_id, quantity
                    FROM prescriptions
                    WHERE record_id = ? AND is_dispensed = 0 AND prescription_id IN ($placeholders)
                ");
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare('
                    SELECT prescription_id, medicine_id, quantity
                    FROM prescriptions
                    WHERE record_id = ? AND is_dispensed = 0
                ');
                $stmt->execute([$record_id]);
            }

            $prescriptions = $stmt->fetchAll();

            if (empty($prescriptions)) {
                $pdo->rollBack();
                return false;
            }

            $now = date('Y-m-d H:i:s');

            foreach ($prescriptions as $presc) {
                // Mark as dispensed
                $upd = $pdo->prepare('
                    UPDATE prescriptions
                    SET is_dispensed = 1,
                        dispensed_at = ?,
                        dispensed_by = ?,
                        dispense_notes = ?
                    WHERE prescription_id = ?
                ');
                $upd->execute([$now, $pharmacist_id, $notes, $presc['prescription_id']]);

                // Deduct stock and notify observers using Observer Pattern
                $stockHandler->deductStock($presc['medicine_id'], $presc['quantity']);
            }

            // =========================================================
            // PROCESS PAYMENT & SYNC WITH ADMIN BILLING
            // =========================================================
            // Retrieve appointment, patient & doctor consultation fee
            $metaStmt = $pdo->prepare('
                SELECT mr.appointment_id, mr.patient_id, d.consultation_fee
                FROM medical_records mr
                JOIN doctors d ON d.doctor_id = mr.doctor_id
                WHERE mr.medical_record_id = ?
            ');
            $metaStmt->execute([$record_id]);
            $meta = $metaStmt->fetch();

            if ($meta) {
                $appointmentId  = $meta['appointment_id'];
                $patientId      = $meta['patient_id'];
                $consultFee     = (float)$meta['consultation_fee'];

                // Calculate medicines total for this record
                $medTotalStmt = $pdo->prepare('
                    SELECT COALESCE(SUM(m.unit_price * pr.quantity), 0) as med_subtotal
                    FROM prescriptions pr
                    JOIN medicines m ON m.medicine_id = pr.medicine_id
                    WHERE pr.record_id = ?
                ');
                $medTotalStmt->execute([$record_id]);
                $medSubtotal = (float)$medTotalStmt->fetchColumn();

                $totalBillAmount = $consultFee + $medSubtotal;
                $allowedMethods = ['Cash', 'Touch \'n Go', 'Credit Card'];
                if (!in_array($payment_method, $allowedMethods)) {
                    $payment_method = 'Cash';
                }

                // Check existing payment entry for this appointment
                $payChk = $pdo->prepare('SELECT payment_id, invoice_no FROM payments WHERE appointment_id = ?');
                $payChk->execute([$appointmentId]);
                $existingPay = $payChk->fetch();

                if ($existingPay) {
                    $invNo = $existingPay['invoice_no'];
                    // Update existing payment record to Paid
                    $payUpd = $pdo->prepare('
                        UPDATE payments
                        SET amount = ?,
                            payment_method = ?,
                            payment_status = "Paid",
                            payment_date = ?
                        WHERE appointment_id = ?
                    ');
                    $payUpd->execute([$totalBillAmount, $payment_method, $now, $appointmentId]);
                } else {
                    // Insert new payment record
                    $payCount = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn() + 1;
                    $payId = 'PA' . str_pad($payCount, 3, '0', STR_PAD_LEFT);
                    $invNo = 'INV-' . date('Y') . '-' . str_pad($payCount, 4, '0', STR_PAD_LEFT);

                    $payIns = $pdo->prepare('
                        INSERT INTO payments (payment_id, appointment_id, patient_id, amount, payment_method, payment_status, payment_date, invoice_no)
                        VALUES (?, ?, ?, ?, ?, "Paid", ?, ?)
                    ');
                    $payIns->execute([$payId, $appointmentId, $patientId, $totalBillAmount, $payment_method, $now, $invNo]);
                }

                // Fetch names & detailed medicine lines for receipt
                $namesStmt = $pdo->prepare('
                    SELECT p.full_name as patient_name, d.name as doctor_name
                    FROM medical_records mr
                    JOIN patients p ON p.patient_id = mr.patient_id
                    JOIN doctors d ON d.doctor_id = mr.doctor_id
                    WHERE mr.medical_record_id = ?
                ');
                $namesStmt->execute([$record_id]);
                $names = $namesStmt->fetch();

                $medsStmt = $pdo->prepare('
                    SELECT m.brand_name, m.dosage, pr.quantity, m.unit_price, (m.unit_price * pr.quantity) as item_total
                    FROM prescriptions pr
                    JOIN medicines m ON m.medicine_id = pr.medicine_id
                    WHERE pr.record_id = ?
                ');
                $medsStmt->execute([$record_id]);
                $medItems = $medsStmt->fetchAll();

                $receipt = [
                    'invoice_no'           => $invNo,
                    'patient_name'         => $names['patient_name'] ?? 'Patient',
                    'doctor_name'          => $names['doctor_name'] ?? 'Doctor',
                    'payment_date'         => date('d M Y, h:i A'),
                    'consultation_fee'     => $consultFee,
                    'medications_subtotal' => $medSubtotal,
                    'total_amount'         => $totalBillAmount,
                    'payment_method'       => $payment_method,
                    'medications'          => $medItems
                ];
            }

            $pdo->commit();
            return $receipt ?: true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    // =========================================================
    // DISPENSING HISTORY
    // =========================================================

    /**
     * Get dispensing history records.
     * Returns all dispensed prescription groups with patient, doctor, and medicine info.
     */
    public static function getDispensingHistory($filter = 'all') {
        global $pdo;

        $dateCondition = '';
        if ($filter === 'today') {
            $dateCondition = 'AND DATE(pr.dispensed_at) = CURDATE()';
        } elseif ($filter === 'yesterday') {
            $dateCondition = 'AND DATE(pr.dispensed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
        }

        $sql = "
            SELECT
                pr.record_id,
                pr.dispensed_at,
                pr.dispense_notes,
                GROUP_CONCAT(m.brand_name ORDER BY m.brand_name SEPARATOR ', ') AS medications,
                SUM(m.unit_price * pr.quantity) AS total_amount,
                p.patient_id,
                p.full_name AS patient_name,
                d.name AS doctor_name,
                d.initials,
                d.color,
                mr.medical_record_id
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            JOIN patients p ON p.patient_id = mr.patient_id
            JOIN doctors d ON d.doctor_id = mr.doctor_id
            WHERE pr.is_dispensed = 1
            $dateCondition
            GROUP BY pr.record_id, pr.dispensed_at, p.patient_id, d.doctor_id, mr.medical_record_id
            ORDER BY pr.dispensed_at DESC
        ";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get summary stats for the history page.
     */
    public static function getHistoryStats() {
        global $pdo;

        // Total dispensed records
        $stmt = $pdo->query('SELECT COUNT(DISTINCT record_id) as cnt FROM prescriptions WHERE is_dispensed = 1');
        $totalDispensed = (int)$stmt->fetch()['cnt'];

        // Total cancelled (appointments cancelled - linked prescriptions not dispensed)
        $stmt2 = $pdo->query('
            SELECT COUNT(DISTINCT pr.record_id) as cnt
            FROM prescriptions pr
            JOIN medical_records mr ON mr.medical_record_id = pr.record_id
            JOIN appointments a ON a.appointment_id = mr.appointment_id
            WHERE a.status = "Cancelled" AND pr.is_dispensed = 0
        ');
        $cancelled = (int)$stmt2->fetch()['cnt'];

        // Today's revenue
        $stmt3 = $pdo->query('
            SELECT COALESCE(SUM(m.unit_price * pr.quantity), 0) as rev
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            WHERE pr.is_dispensed = 1 AND DATE(pr.dispensed_at) = CURDATE()
        ');
        $todayRevenue = (float)$stmt3->fetch()['rev'];

        // Total revenue all time
        $stmt4 = $pdo->query('
            SELECT COALESCE(SUM(m.unit_price * pr.quantity), 0) as rev
            FROM prescriptions pr
            JOIN medicines m ON m.medicine_id = pr.medicine_id
            WHERE pr.is_dispensed = 1
        ');
        $totalRevenue = (float)$stmt4->fetch()['rev'];

        return [
            'total_dispensed' => $totalDispensed,
            'cancelled'       => $cancelled,
            'today_revenue'   => $todayRevenue,
            'total_revenue'   => $totalRevenue,
        ];
    }

    // =========================================================
    // INVENTORY
    // =========================================================

    /**
     * Get all medicines from the inventory.
     */
    public static function getAllMedicines($search = '', $category = 'all', $stockStatus = 'all') {
        global $pdo;

        $conditions = ['1=1'];
        $params = [];

        if (!empty($search)) {
            $conditions[] = '(brand_name LIKE ? OR generic_name LIKE ? OR medicine_id LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($category !== 'all') {
            $conditions[] = 'category = ?';
            $params[] = $category;
        }

        if ($stockStatus === 'out') {
            $conditions[] = 'stock_quantity = 0';
        } elseif ($stockStatus === 'low') {
            $conditions[] = 'stock_quantity > 0 AND stock_quantity <= minimum_stock';
        } elseif ($stockStatus === 'normal') {
            $conditions[] = 'stock_quantity > minimum_stock';
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM medicines WHERE $where ORDER BY brand_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get inventory summary stats.
     */
    public static function getInventoryStats() {
        global $pdo;

        $stmt = $pdo->query('SELECT COUNT(*) as total FROM medicines');
        $total = (int)$stmt->fetch()['total'];

        $stmt2 = $pdo->query('SELECT COUNT(*) as cnt FROM medicines WHERE stock_quantity > 0 AND stock_quantity <= minimum_stock');
        $low = (int)$stmt2->fetch()['cnt'];

        $stmt3 = $pdo->query('SELECT COUNT(*) as cnt FROM medicines WHERE stock_quantity = 0');
        $out = (int)$stmt3->fetch()['cnt'];

        $stmt4 = $pdo->query('SELECT COUNT(DISTINCT category) as cnt FROM medicines');
        $categories = (int)$stmt4->fetch()['cnt'];

        return [
            'total'      => $total,
            'low_stock'  => $low,
            'out_stock'  => $out,
            'categories' => $categories,
        ];
    }

    /**
     * Get a single medicine by ID.
     */
    public static function getMedicineById($medicine_id) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT * FROM medicines WHERE medicine_id = ? LIMIT 1');
        $stmt->execute([$medicine_id]);
        return $stmt->fetch();
    }

    /**
     * Add a new medicine to inventory.
     * Auto-generates medicine_id as M + zero-padded number.
     */
    public static function addMedicine($data) {
        global $pdo;

        // Generate next medicine_id
        $stmt = $pdo->query("SELECT medicine_id FROM medicines ORDER BY medicine_id DESC LIMIT 1");
        $last = $stmt->fetch();
        if ($last) {
            $num = (int)substr($last['medicine_id'], 1) + 1;
        } else {
            $num = 1;
        }
        $new_id = 'M' . str_pad($num, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO medicines
                (medicine_id, brand_name, generic_name, dosage, category, unit_type, manufacturer,
                 stock_quantity, minimum_stock, unit_price, expiry_date, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        return $stmt->execute([
            $new_id,
            $data['brand_name'],
            $data['generic_name'],
            $data['dosage'],
            $data['category'],
            $data['unit_type'],
            $data['manufacturer'] ?? null,
            (int)$data['stock_quantity'],
            (int)$data['minimum_stock'],
            (float)$data['unit_price'],
            $data['expiry_date'] ?? null,
            $data['description'] ?? null,
        ]);
    }

    /**
     * Update medicine details.
     */
    public static function updateMedicine($medicine_id, $data) {
        global $pdo;
        $stmt = $pdo->prepare('
            UPDATE medicines
            SET brand_name     = ?,
                generic_name   = ?,
                dosage         = ?,
                category       = ?,
                unit_type      = ?,
                manufacturer   = ?,
                stock_quantity = ?,
                minimum_stock  = ?,
                unit_price     = ?,
                expiry_date    = ?,
                description    = ?
            WHERE medicine_id = ?
        ');
        return $stmt->execute([
            $data['brand_name'],
            $data['generic_name'],
            $data['dosage'],
            $data['category'],
            $data['unit_type'],
            $data['manufacturer'] ?? null,
            (int)$data['stock_quantity'],
            (int)$data['minimum_stock'],
            (float)$data['unit_price'],
            $data['expiry_date'] ?? null,
            $data['description'] ?? null,
            $medicine_id,
        ]);
    }

    /**
     * Restock a medicine (add to existing stock).
     */
    public static function restockMedicine($medicine_id, $qty_to_add) {
        global $pdo;
        $stmt = $pdo->prepare('
            UPDATE medicines
            SET stock_quantity = stock_quantity + ?
            WHERE medicine_id = ?
        ');
        return $stmt->execute([(int)$qty_to_add, $medicine_id]);
    }

    /**
     * Get low-stock medicines for the dashboard alert widget.
     */
    public static function getLowStockAlerts($limit = 5) {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT medicine_id, brand_name, generic_name, dosage, unit_type,
                   stock_quantity, minimum_stock,
                   CASE WHEN stock_quantity = 0 THEN "out" ELSE "low" END AS stock_status
            FROM medicines
            WHERE stock_quantity <= minimum_stock
            ORDER BY stock_quantity ASC
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get all distinct medicine categories.
     */
    public static function getCategories() {
        global $pdo;
        $stmt = $pdo->query('SELECT DISTINCT category FROM medicines ORDER BY category ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Delete a medicine by ID.
     * Returns false if the medicine has linked prescriptions (to prevent FK violation).
     */
    public static function deleteMedicine($medicine_id) {
        global $pdo;
        // Safety check: block deletion if prescriptions reference this medicine
        $check = $pdo->prepare('SELECT COUNT(*) FROM prescriptions WHERE medicine_id = ?');
        $check->execute([$medicine_id]);
        if ((int)$check->fetchColumn() > 0) {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM medicines WHERE medicine_id = ?');
        return $stmt->execute([$medicine_id]);
    }
}
