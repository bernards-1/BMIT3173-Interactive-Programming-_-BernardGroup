<?php
// Models/DoctorLeave.php

require_once __DIR__ . '/../core/Model.php';

class DoctorLeave extends Model {
    protected static $table = 'doctor_leaves';
    protected static $primaryKey = 'leave_id';

    /**
     * Create a new pending leave request with an auto-generated leave_id.
     */
    public static function request(string $doctorId, string $startDate, string $endDate, string $reason): self {
        $db = static::getDb();
        $count = (int) $db->query("SELECT COUNT(*) FROM doctor_leaves")->fetchColumn() + 1;
        $leaveId = 'DL' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        $leave = new self([
            'leave_id'   => $leaveId,
            'doctor_id'  => $doctorId,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => $reason,
            'status'     => 'Pending',
        ], false);
        $leave->save();
        return $leave;
    }

    public function approve(): bool {
        $this->status = 'Approved';
        return $this->save();
    }

    public function reject(?string $rejectReason = null): bool {
        $this->status = 'Rejected';
        $this->reject_reason = $rejectReason;
        return $this->save();
    }

    /**
     * Count of leave requests still pending review for a given doctor.
     */
    public static function getPendingCountForDoctor(string $doctorId): int {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM doctor_leaves WHERE doctor_id = ? AND status = 'Pending'");
        $stmt->execute([$doctorId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether a doctor is on approved leave covering a given date.
     */
    public static function isDoctorOnLeave(string $doctorId, string $date): bool {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM doctor_leaves 
            WHERE doctor_id = ? AND status = 'Approved' 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$doctorId, $date]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Approved leave date ranges for a doctor from today onward
     * (used to block booking on those dates).
     */
    public static function upcomingApprovedRanges(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare("SELECT start_date, end_date, reason FROM doctor_leaves WHERE doctor_id = ? AND status = 'Approved' AND end_date >= CURDATE() ORDER BY start_date ASC");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Leave history for a doctor, most recent first (single table + ORDER BY,
     * kept as a dedicated query since ActiveRecord's where() doesn't sort).
     */
    public static function historyForDoctor(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT start_date, end_date, reason, status, reject_reason, created_at 
            FROM doctor_leaves 
            WHERE doctor_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All approved leaves for a doctor (used to highlight leave days on doctor schedule calendar).
     */
    public static function approvedLeavesForDoctor(string $doctorId): array {
        $db = static::getDb();
        $stmt = $db->prepare("
            SELECT leave_id, start_date, end_date, reason 
            FROM doctor_leaves 
            WHERE doctor_id = ? AND status = 'Approved' 
            ORDER BY start_date ASC
        ");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All leave requests with the requesting doctor's name (Admin view) —
     * cross-table, kept raw.
     */
    public static function allWithDoctorName(): array {
        $db = static::getDb();
        $stmt = $db->query("
            SELECT dl.*, d.name AS doctor_name
            FROM doctor_leaves dl
            JOIN doctors d ON dl.doctor_id = d.doctor_id
            ORDER BY 
                CASE dl.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
                dl.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
