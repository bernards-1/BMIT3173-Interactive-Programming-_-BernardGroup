<?php
// Subsystems/BillingSubsystem.php
require_once __DIR__ . '/../Models/Payment.php';

/**
 * BillingSubsystem handles payments, invoice aggregation, and financial metrics.
 */
class BillingSubsystem {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    public function getMonthlyRevenue() {
        return Payment::getMonthlyRevenue();
    }

    public function getAllPayments() {
        return Payment::getAllPayments();
    }

    public function getFinancialStatistics() {
        $payments = $this->getAllPayments();
        $totalRevenue = 0;
        $paid = 0;
        $pending = 0;
        $overdue = 0;

        foreach ($payments as $pay) {
            if ($pay['payment_status'] === 'Paid') {
                $paid += $pay['amount'];
                $totalRevenue += $pay['amount'];
            } elseif ($pay['payment_status'] === 'Unpaid') {
                $pending += $pay['amount'];
            }
        }

        return [
            'totalRevenue' => $totalRevenue,
            'paid' => $paid,
            'pending' => $pending,
            'overdue' => $overdue
        ];
    }
}
