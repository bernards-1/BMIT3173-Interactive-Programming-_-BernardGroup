<?php
// Models/StockObserver.php

interface StockSubject {
    public function attach(StockObserver $observer): void;
    public function detach(StockObserver $observer): void;
    public function notify(string $medicineId, int $newStock, int $minStock): void;
}

interface StockObserver {
    public function update(string $medicineId, int $newStock, int $minStock): void;
}

class MedicineStockHandler implements StockSubject {
    private $observers = [];
    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function attach(StockObserver $observer): void {
        $this->observers[] = $observer;
    }

    public function detach(StockObserver $observer): void {
        foreach ($this->observers as $key => $obs) {
            if ($obs === $observer) {
                unset($this->observers[$key]);
            }
        }
        $this->observers = array_values($this->observers);
    }

    public function notify(string $medicineId, int $newStock, int $minStock): void {
        foreach ($this->observers as $observer) {
            $observer->update($medicineId, $newStock, $minStock);
        }
    }

    /**
     * Deduct stock of a medicine and notify observers.
     */
    public function deductStock(string $medicineId, int $deductQuantity): void {
        global $pdo;

        // Fetch current stock, minimum stock, and name
        $stmt = $pdo->prepare("SELECT stock_quantity, minimum_stock, brand_name FROM medicines WHERE medicine_id = ?");
        $stmt->execute([$medicineId]);
        $med = $stmt->fetch();

        if ($med) {
            $newStock = max((int)$med['stock_quantity'] - $deductQuantity, 0);
            $minStock = (int)$med['minimum_stock'];
            $brandName = $med['brand_name'];

            // Perform SQL update query
            $update = $pdo->prepare("UPDATE medicines SET stock_quantity = ? WHERE medicine_id = ?");
            $update->execute([$newStock, $medicineId]);

            // Notify observers of stock change
            $this->notify($brandName . " (ID: " . $medicineId . ")", $newStock, $minStock);
        }
    }
}

class LowStockAlertNotifier implements StockObserver {
    public function update(string $medicineId, int $newStock, int $minStock): void {
        if ($newStock <= $minStock) {
            // Log warning to server error log
            error_log("LOW STOCK WARNING: " . $medicineId . " is running low! Current stock: " . $newStock . " (Min limit: " . $minStock . ")");
            
            // Store alert in registry and session
            StockWarningRegistry::addWarning($medicineId, $newStock, $minStock);
        }
    }
}

class StockWarningRegistry {
    private static $warnings = [];

    public static function addWarning(string $medicineId, int $newStock, int $minStock): void {
        $warning = [
            'medicine'      => $medicineId,
            'current_stock' => $newStock,
            'min_stock'     => $minStock,
            'timestamp'     => date('Y-m-d H:i:s')
        ];
        
        self::$warnings[] = $warning;

        // Save warning to session so it is readable by the frontend UI
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        if (!isset($_SESSION['stock_warnings'])) {
            $_SESSION['stock_warnings'] = [];
        }
        $_SESSION['stock_warnings'][] = $warning;
    }

    public static function getWarnings(): array {
        return self::$warnings;
    }

    public static function clearWarnings(): void {
        self::$warnings = [];
        if (session_status() !== PHP_SESSION_NONE) {
            unset($_SESSION['stock_warnings']);
        }
    }
}
