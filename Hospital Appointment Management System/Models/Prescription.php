<?php
/**
 * Prescription Model
 */

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Medicine.php';
require_once __DIR__ . '/Pharmacist.php';

class Prescription extends Model {
    protected static $table = 'prescriptions';
    protected static $primaryKey = 'prescription_id';

    /**
     * @var Medicine|null In-memory object reference
     */
    private ?Medicine $medicineInstance = null;

    /**
     * @var Pharmacist|null In-memory object reference
     */
    private ?Pharmacist $pharmacistInstance = null;

    /**
     * Object Reference: Get associated Medicine entity instance.
     * Fulfills report Section 3: Use object references instead of foreign keys.
     */
    public function getMedicine(): ?Medicine {
        if ($this->medicineInstance === null && !empty($this->medicine_id)) {
            $this->medicineInstance = Medicine::find($this->medicine_id);
        }
        return $this->medicineInstance;
    }

    /**
     * Object Reference: Set associated Medicine entity instance.
     */
    public function setMedicine(Medicine $medicine): void {
        $this->medicineInstance = $medicine;
        $this->medicine_id = $medicine->medicine_id;
    }

    /**
     * Object Reference: Get Pharmacist entity instance who dispensed this prescription.
     */
    public function getPharmacist(): ?Pharmacist {
        if ($this->pharmacistInstance === null && !empty($this->dispensed_by)) {
            $this->pharmacistInstance = Pharmacist::find($this->dispensed_by);
        }
        return $this->pharmacistInstance;
    }

    /**
     * Mark this prescription as dispensed and persist via ORM.
     */
    public function markAsDispensed(string $pharmacistId, string $notes = ''): bool {
        $this->is_dispensed = 1;
        $this->dispensed_at = date('Y-m-d H:i:s');
        $this->dispensed_by = $pharmacistId;
        $this->dispense_notes = $notes;
        return $this->save();
    }
}
