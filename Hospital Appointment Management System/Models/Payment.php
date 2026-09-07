<?php
// Models/Payment.php

require_once __DIR__ . '/../core/Model.php';

class Payment extends Model {
    protected static $table = 'payments';
    protected static $primaryKey = 'payment_id';

    public function getAppointment(): ?object {
        require_once __DIR__ . '/Appointment.php';
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function getPatient(): ?object {
        require_once __DIR__ . '/Patient.php';
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public static function getMonthlyRevenue() {
        $db = static::getDb();
        // Sum payments for the current month — aggregate, kept as raw SQL
        $stmt = $db->query("
            SELECT SUM(amount) FROM payments 
            WHERE payment_status = 'Paid' 
            AND MONTH(payment_date) = MONTH(CURRENT_DATE())
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
        ");
        return $stmt->fetchColumn() ?: 0;
    }

    /**
     * Total revenue from all Paid payments — aggregate SUM.
     */
    public static function getTotalRevenue(): float {
        $db = static::getDb();
        $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'");
        return (float) $stmt->fetchColumn();
    }

    /**
     * Revenue grouped by doctor specialization (department), top N by revenue.
     * Cross-table JOIN + GROUP BY aggregate — kept inside the Model.
     */
    public static function getRevenueByDepartment(int $limit = 5): array {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT d.specialization, COALESCE(SUM(p.amount), 0) as revenue
            FROM doctors d
            LEFT JOIN appointments a ON a.doctor_id = d.doctor_id
            LEFT JOIN payments p ON p.appointment_id = a.appointment_id AND p.payment_status = 'Paid'
            GROUP BY d.specialization
            ORDER BY revenue DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paid revenue for each of the last N months (for trend charts).
     * Aggregate SUM + GROUP BY — kept inside the Model.
     */
    public static function getMonthlyRevenueTrend(int $months = 6): array {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT DATE_FORMAT(payment_date, '%b') as month, COALESCE(SUM(amount), 0) as total
            FROM payments
            WHERE payment_status = 'Paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
        ");
        $stmt->bindValue(1, $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllPayments() {
        $db = static::getDb();
        // Cross-table listing — kept as raw SQL
        $stmt = $db->query("
            SELECT p.*, a.reason as service_name, pat.full_name as patient_name
            FROM payments p
            JOIN appointments a ON p.appointment_id = a.appointment_id
            JOIN patients pat ON p.patient_id = pat.patient_id
            ORDER BY p.payment_date DESC, p.invoice_no DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
