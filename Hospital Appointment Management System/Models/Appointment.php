<?php
// Models/Appointment.php

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
     * Transition to a new AppointmentState and persist via ORM.
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

    /**
     * Count of a doctor's appointments today (CURDATE()), optionally filtered
     * by status. Aggregate — kept inside the Model.
     */
    public static function getTodayCountForDoctor(string $doctorId): int {
        $db = static::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()');
        $stmt->execute([$doctorId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of a doctor's appointments overall, optionally filtered by status.
     */
    public static function getCountForDoctor(string $doctorId, ?string $status = null): int {
        $db = static::getDb();
        $sql = 'SELECT COUNT(*) FROM appointments WHERE doctor_id = ?';
        $params = [$doctorId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Full appointment list for a doctor, with patient details, linked
     * medical record id, and a per-appointment prescription count.
     * Cross-table JOIN + correlated aggregate subquery — kept inside the Model.
     */
    public static function getListForDoctor(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                p.patient_id,
                p.full_name,
                p.date_of_birth,
                mr.medical_record_id,
                (SELECT COUNT(*) FROM prescriptions pr WHERE pr.record_id = mr.medical_record_id) as prescription_count
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN medical_records mr ON a.appointment_id = mr.appointment_id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Earliest upcoming Scheduled appointment date for a patient (MIN aggregate).
     */
    public static function getNextScheduledDateForPatient(string $patientId): ?string {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT MIN(appointment_date) 
            FROM appointments 
            WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = 'Scheduled'
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Whether the given doctor already has a non-cancelled/expired appointment
     * at the given date/time, excluding a given appointment id (for edits).
     */
    public static function doctorHasConflict(string $doctorId, string $date, string $time, string $excludeId = ''): bool {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled', 'Expired') AND appointment_id <> ?");
        $stmt->execute([$doctorId, $date, $time, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Whether the given patient already has a non-cancelled/expired appointment
     * at the given date/time, excluding a given appointment id (for edits).
     */
    public static function patientHasConflict(string $patientId, string $date, string $time, string $excludeId = ''): bool {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled', 'Expired') AND appointment_id <> ?");
        $stmt->execute([$patientId, $date, $time, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Retrieve all scheduled appointment ORM instances for a doctor in a date range.
     *
     * @return Appointment[]
     */
    public static function getScheduledBetween(string $doctorId, string $startDate, string $endDate): array {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT * FROM appointments WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? AND status = 'Scheduled'");
        $stmt->execute([$doctorId, $startDate, $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $instances = [];
        foreach ($rows as $row) {
            $instances[] = new self($row, true);
        }
        return $instances;
    }

    /**
     * Count of a doctor's appointments on a given date, optionally filtered
     * further by status. Compound WHERE aggregate — kept inside the Model.
     */
    public static function getCountForDoctorOnDate(string $doctorId, string $date, ?string $status = null): int {
        $db = static::getDb();
        $sql = 'SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ?';
        $params = [$doctorId, $date];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of distinct patients a given doctor has ever had an appointment with.
     */
    public static function getDistinctPatientCountForDoctor(string $doctorId): int {
        return static::countDistinct('patient_id', 'doctor_id', $doctorId);
    }

    /**
     * A doctor's appointments on a given date, joined with patient details,
     * ordered by time — used for the "today's schedule" widget.
     */
    public static function getScheduleForDoctorOnDate(string $doctorId, string $date): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT 
                a.appointment_id, 
                a.appointment_time, 
                a.reason, 
                a.status, 
                p.patient_id, 
                p.full_name 
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.patient_id 
            WHERE a.doctor_id = ? AND a.appointment_date = ? 
            ORDER BY a.appointment_time ASC
        ');
        $stmt->execute([$doctorId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All appointments for a given doctor, joined with patient name, ordered
     * by time — used to drive the doctor's schedule / calendar dots.
     */
    public static function getForDoctorSchedule(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare('
            SELECT
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                p.patient_id,
                p.full_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_time ASC
        ');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Whether the given doctor already has a Scheduled appointment at the
     * given date/time, excluding a given appointment id (for rescheduling).
     */
    public static function hasScheduledConflict(string $doctorId, string $date, string $time, string $excludeId): bool {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND appointment_id != ? AND status = 'Scheduled'");
        $stmt->execute([$doctorId, $date, $time, $excludeId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Generate the next sequential appointment id (A001, A002, ...) based on
     * the current MAX in the table — aggregate query, kept inside the Model.
     */
    public static function generateNextId(): string {
        $db = static::getDb();
        $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(appointment_id, 2) AS UNSIGNED)) FROM appointments WHERE appointment_id REGEXP '^A[0-9]+$'");
        $nextId = ((int) $stmt->fetchColumn()) + 1;
        return 'A' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Count of appointments grouped by status (Scheduled/Completed/Cancelled/Expired).
     * Returns an assoc array keyed by status with 0 defaults for missing statuses.
     */
    public static function getStatusCounts(array $statuses = ['Scheduled', 'Completed', 'Cancelled', 'Expired']): array {
        $db = static::getDb();
        $counts = array_fill_keys($statuses, 0);
        $stmt = $db->query("SELECT status, COUNT(*) as cnt FROM appointments GROUP BY status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }
}
