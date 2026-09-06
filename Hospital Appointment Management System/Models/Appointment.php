<?php
/**
 * Appointment Model
 * Implements ActiveRecord ORM and State Pattern
 */

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/AppointmentState.php';

class Appointment extends Model implements AppointmentContext {
    protected static $table = 'appointments';
    protected static $primaryKey = 'appointment_id';

    /**
     * @var AppointmentState State Pattern holder
     */
    private $statusState;

    /**
     * Compatible constructor supporting both:
     * - ORM instantiation: new Appointment(array $attributes, bool $exists)
     * - Legacy instantiation: new Appointment(string $appointment_id, string $status)
     */
    public function __construct($attributesOrId = [], $statusOrExists = false) {
        if (is_string($attributesOrId)) {
            // Legacy signature: new Appointment($appointment_id, $status)
            $status = is_string($statusOrExists) ? $statusOrExists : 'Scheduled';
            parent::__construct([
                'appointment_id' => $attributesOrId,
                'status'         => $status
            ], true);
            $this->setStatus($status);
        } else {
            // ORM signature: new Appointment(array $attributes, bool $exists)
            $attributes = is_array($attributesOrId) ? $attributesOrId : [];
            $exists = is_bool($statusOrExists) ? $statusOrExists : false;
            parent::__construct($attributes, $exists);
            $status = $this->attributes['status'] ?? 'Scheduled';
            $this->setStatus($status);
        }
    }

    /**
     * Bind appropriate State object based on status string.
     */
    public function setStatus($status) {
        $this->attributes['status'] = $status;
        switch ($status) {
            case 'Completed':
                $this->statusState = new CompletedState();
                break;
            case 'Cancelled':
                $this->statusState = new CancelledState();
                break;
            case 'Expired':
                $this->statusState = new ExpiredState();
                break;
            case 'Scheduled':
            default:
                $this->statusState = new ScheduledState();
                break;
        }
    }

    /**
     * Get current status name via State object.
     */
    public function getStatus(): string {
        return $this->statusState ? $this->statusState->getStatusName() : ($this->status ?? 'Scheduled');
    }

    /**
     * Transition to a new AppointmentState.
     * 
     * Conforms to ORM standards requested by the tutor:
     * Instead of writing raw SQL ($this->pdo->prepare("UPDATE appointments...")),
     * we update the model's attribute and persist via $this->save().
     */
    public function transitionTo(AppointmentState $state): void {
        $this->statusState = $state;
        $this->status = $state->getStatusName();
        // Persist to database automatically via ORM
        $this->save();
    }

    public function complete(): void {
        if ($this->statusState === null) {
            $this->setStatus($this->status ?? 'Scheduled');
        }
        $this->statusState->complete($this);
    }

    public function cancel(): void {
        if ($this->statusState === null) {
            $this->setStatus($this->status ?? 'Scheduled');
        }
        $this->statusState->cancel($this);
    }

    public function expire(): void {
        if ($this->statusState === null) {
            $this->setStatus($this->status ?? 'Scheduled');
        }
        $this->statusState->expire($this);
    }

    /**
     * Object Reference Helper: Get Doctor entity instance for this appointment.
     */
    public function getDoctor(): ?object {
        require_once __DIR__ . '/Doctor.php';
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }

    /**
     * Object Reference Helper: Get Patient entity instance for this appointment.
     */
    public function getPatient(): ?object {
        require_once __DIR__ . '/Patient.php';
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    /**
     * Static helper to load an appointment context via ORM find.
     */
    public static function load($appointment_id) {
        $appt = self::find($appointment_id);
        if ($appt) {
            $appt->setStatus($appt->status ?? 'Scheduled');
            return $appt;
        }
        return null;
    }

    // ── Static reporting & query helpers ─────────────────────────

    public static function getTodayCount() {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public static function getRecentAppointments($limit = 5) {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT a.*, p.full_name as patient_name, d.name as doctor_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllAppointments() {
        $db = static::getDb();
        $stmt = $db->query("
            SELECT a.*, p.full_name as patient_name, d.name as doctor_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
