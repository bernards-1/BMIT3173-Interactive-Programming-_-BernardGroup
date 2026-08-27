<?php
// Models/Payment.php

class Payment {
    public static function getMonthlyRevenue() {
        global $pdo;
        // Sum payments for the current month
        $stmt = $pdo->query("
            SELECT SUM(amount) FROM payments 
            WHERE payment_status = 'Paid' 
            AND MONTH(payment_date) = MONTH(CURRENT_DATE())
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
        ");
        return $stmt->fetchColumn() ?: 0;
    }

    public static function getAllPayments() {
        global $pdo;
        $stmt = $pdo->query("
            SELECT p.*, a.reason as service_name, pat.full_name as patient_name
            FROM payments p
            JOIN appointments a ON p.appointment_id = a.appointment_id
            JOIN patients pat ON p.patient_id = pat.patient_id
            ORDER BY p.payment_date DESC, p.invoice_no DESC
        ");
        return $stmt->fetchAll();
    }
}
