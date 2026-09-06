<?php
/**
 * Medicine Model
 */

require_once __DIR__ . '/../core/Model.php';

class Medicine extends Model {
    protected static $table = 'medicines';
    protected static $primaryKey = 'medicine_id';

    /**
     * Check if medicine stock is low (at or below minimum limit).
     */
    public function isLowStock(): bool {
        $stock = (int)($this->stock_quantity ?? 0);
        $minStock = (int)($this->minimum_stock ?? 0);
        return $stock > 0 && $stock <= $minStock;
    }

    /**
     * Check if medicine is out of stock.
     */
    public function isOutOfStock(): bool {
        return (int)($this->stock_quantity ?? 0) <= 0;
    }

    /**
     * Deduct stock quantity and persist via ORM.
     * 
     * @throws Exception If insufficient stock
     */
    public function deduct(int $quantity): bool {
        $currentStock = (int)($this->stock_quantity ?? 0);
        if ($currentStock < $quantity) {
            throw new Exception("Insufficient stock for {$this->brand_name} (ID: {$this->medicine_id}). Available: {$currentStock}, Requested: {$quantity}.");
        }

        $this->stock_quantity = $currentStock - $quantity;
        return $this->save();
    }

    /**
     * Restock quantity and persist via ORM.
     */
    public function restock(int $quantity): bool {
        $this->stock_quantity = ((int)($this->stock_quantity ?? 0)) + $quantity;
        return $this->save();
    }

    /**
     * Retrieve all prescriptions linked to this medicine (hasMany relationship).
     */
    public function getPrescriptions(): array {
        require_once __DIR__ . '/Prescription.php';
        return $this->hasMany(Prescription::class, 'medicine_id');
    }
}
