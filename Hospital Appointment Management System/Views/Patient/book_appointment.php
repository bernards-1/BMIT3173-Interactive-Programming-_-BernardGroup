<?php
require_once '../../db.php';
require_once '../../Models/User.php';
require_once '../../Models/PatientRepository.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure redirect if not logged in as patient
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../Login/login.php');
    exit;
}

$patientRepository = new PatientRepository($pdo);

// Handle POST request to book appointment
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $doctor_id = $input['doctorId'] ?? '';
    $appointment_date = $input['date'] ?? '';
    $appointment_time = $input['time'] ?? '';
    $reason = $input['reason'] ?? '';
    $type = $input['type'] ?? '';

    if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($reason) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    // 1. Validate date/time not in the past
    $today = date('Y-m-d');
    if ($appointment_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
        exit;
    }
    if ($appointment_date === $today && $appointment_time <= date('H:i:s')) {
        echo json_encode(['success' => false, 'message' => 'Appointment time cannot be in the past.']);
        exit;
    }

    // 2. Validate doctor leave — Consume Doctor Module's Web Service (api/doctor_details.php)
    $doctor_details = null;
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $base_dir = '/Hospital Appointment Management System';
    if (strpos($script, '/Hospital Appointment Management System') !== false) {
        $base_dir = '/Hospital Appointment Management System';
    } else {
        $parts = explode('/', trim($script, '/'));
        if (!empty($parts)) {
            $base_dir = '/' . $parts[0];
        }
    }
    // doctor_details.php requires requestID and timestamp as mandatory parameters —
    // both must be generated and sent, otherwise the service rejects the request with 400.
    $requestID = 'REQ-' . bin2hex(random_bytes(4));
    $requestTimestamp = date('Y-m-d H:i:s');

    $api_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $host
        . '/' . implode('/', array_map('rawurlencode', explode('/', trim($base_dir, '/'))))
        . '/api/doctor_details.php?doctorId=' . urlencode($doctor_id)
        . '&requestID=' . urlencode($requestID)
        . '&timestamp=' . urlencode($requestTimestamp);

    error_log("[Web Service Consumption] Request to doctor_details.php: " . $api_url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    $api_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($api_response) {
        error_log("[Web Service Consumption] Response from doctor_details.php: " . $api_response);
        $api_data = json_decode($api_response, true);
        if (isset($api_data['status']) && $api_data['status'] === 'S') {
            $doctor_details = $api_data['data'];
        }
    } else {
        error_log("[Web Service Consumption] doctor_details.php call failed/timed out: " . $curl_error);
    }

    // Fallback to direct DB query if the Doctor Module's web service is offline or times out
    if (!$doctor_details) {
        error_log("[Web Service Consumption] Falling back to direct DB query for doctor leaves (doctor_id={$doctor_id})");
        $doctor_details = ['approvedLeaves' => $patientRepository->getApprovedDoctorLeaves($doctor_id)];
    }

    foreach ($doctor_details['approvedLeaves'] as $leave) {
        if ($appointment_date >= $leave['start_date'] && $appointment_date <= $leave['end_date']) {
            echo json_encode(['success' => false, 'message' => 'The selected doctor is on leave on this date. Please choose another date.']);
            exit;
        }
    }

    // 2.2 Verify Doctor Active Duty Status via Admin Module Web Service (IFA Standard)
    $admin_api_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $host
        . '/' . implode('/', array_map('rawurlencode', explode('/', trim($base_dir, '/'))))
        . '/api/admin_doctor_status.php';
    
    $adminReqPayload = json_encode([
        'requestID' => 'REQ-ADM-' . bin2hex(random_bytes(4)),
        'timestamp' => date('Y-m-d H:i:s'),
        'doctorId'  => $doctor_id,
        'checkDate' => $appointment_date
    ]);

    $ch_admin = curl_init();
    curl_setopt($ch_admin, CURLOPT_URL, $admin_api_url);
    curl_setopt($ch_admin, CURLOPT_POST, true);
    curl_setopt($ch_admin, CURLOPT_POSTFIELDS, $adminReqPayload);
    curl_setopt($ch_admin, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_admin, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch_admin, CURLOPT_TIMEOUT, 1);
    $admin_response = curl_exec($ch_admin);
    curl_close($ch_admin);

    if ($admin_response) {
        $admin_data = json_decode($admin_response, true);
        if (isset($admin_data['status']) && $admin_data['status'] === 'S' && isset($admin_data['data']['isAvailable']) && !$admin_data['data']['isAvailable']) {
            echo json_encode(['success' => false, 'message' => 'The selected doctor is unavailable or on approved leave according to administrative records.']);
            exit;
        }
    }

    // 3. Validate time slot conflict
    if ($patientRepository->hasAppointmentConflict($doctor_id, $appointment_date, $appointment_time)) {
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked for this doctor. Please choose another time slot.']);
        exit;
    }

    // Get patient_id corresponding to user_id
    $patient = $patientRepository->getPatientByUserId($_SESSION['user']['user_id']);
    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient record not found.']);
        exit;
    }
    $patient_id = $patient['patient_id'];

    // Generate new unique appointment_id (e.g. A003)
    $last_id = $patientRepository->getLastAppointmentId();
    if ($last_id) {
        $num = (int) substr($last_id, 1);
        $next_id = 'A' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_id = 'A001';
    }

    // Format reason as "[Type] Reason"
    $full_reason = "[" . $type . "] " . $reason;

    try {
        // Delegate fee calculation to PatientController (Strategy Pattern)
        require_once '../../Controllers/PatientController.php';
        $patientController = new PatientController();
        $feeCalculation = $patientController->calculateAppointmentFee($doctor_id, $type);
        if (!$feeCalculation['success']) {
            echo json_encode(['success' => false, 'message' => 'Pricing calculation failed: ' . $feeCalculation['message']]);
            exit;
        }
        $final_amount = $feeCalculation['final_amount'];

        // 2. Generate new unique payment_id (e.g. PA002) and invoice_no
        $pay_count = $patientRepository->getPaymentCount() + 1;
        $pay_id = 'PA' . str_pad($pay_count, 3, '0', STR_PAD_LEFT);

        // Ensure uniqueness (now checked via a bound parameter — see paymentIdExists()
        // in PatientRepository, which fixes a previous SQL Injection vulnerability
        // where $pay_id was concatenated directly into the query string here)
        while ($patientRepository->paymentIdExists($pay_id)) {
            $pay_count++;
            $pay_id = 'PA' . str_pad($pay_count, 3, '0', STR_PAD_LEFT);
        }

        $invoice_no = 'INV-2026-' . str_pad($pay_count, 4, '0', STR_PAD_LEFT);

        // Create the appointment and payment together in a single transaction
        $patientRepository->createAppointmentWithPayment(
            [
                'appointment_id' => $next_id,
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'reason' => $full_reason,
            ],
            [
                'payment_id' => $pay_id,
                'amount' => $final_amount,
                'invoice_no' => $invoice_no,
            ]
        );

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch all doctors from database with leave status
require_once '../../Models/DoctorLeave.php';
$doctors = $patientRepository->getAllDoctors();
$todayDate = date('Y-m-d');
$doctorLeavesMap = [];
foreach ($doctors as $idx => $doc) {
    $docId = $doc['doctor_id'];
    $leaves = DoctorLeave::upcomingApprovedRanges($docId);
    $doctorLeavesMap[$docId] = $leaves;
    $doctors[$idx]['is_on_leave_today'] = DoctorLeave::isDoctorOnLeave($docId, $todayDate);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - MediCare</title>
    <link rel="stylesheet" href="../Layout/style.css">
    <link rel="stylesheet" href="../Layout/Patient/style.css">
    <link rel="stylesheet" href="../Layout/Patient/book_appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Calendar interactive styles */
        .calendar-day:not(.muted) {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .calendar-day:not(.muted):hover {
            background-color: #f1f5f9;
            border-radius: 6px;
        }

        /* Selected date highlight */
        .calendar-day.selected {
            background-color: var(--primary-blue, #3b82f6) !important;
            color: #ffffff !important;
            border-radius: 6px;
            font-weight: bold;
        }

        /* Doctor on-leave styles */
        .calendar-day.on-leave {
            background-color: #fef2f2 !important;
            color: #ef4444 !important;
            cursor: not-allowed !important;
            border: 1px dashed #fca5a5 !important;
            position: relative;
        }

        .calendar-day.on-leave::after {
            content: '';
            position: absolute;
            bottom: 3px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background-color: #ef4444;
        }

        /* Doctor card selection */
        .doctor-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .doctor-card.selected {
            border: 2px solid var(--primary-blue, #3b82f6);
            background-color: rgba(59, 130, 246, 0.04);
        }

        .time-slot-btn.selected {
            background-color: var(--primary-blue, #3b82f6) !important;
            color: white !important;
        }

        .time-slot-btn.disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background-color: #f1f5f9 !important;
            color: #94a3b8 !important;
            pointer-events: none;
        }

        .btn-confirm-booking.disabled {
            background-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: none !important;
        }
    </style>
</head>

<body class="patient-page-bg">

    <?php include '../Layout/Patient/navigation.php'; ?>

    <div class="dashboard-container">

        <div class="booking-page-header">
            <h1>Book an Appointment</h1>
            <p>Schedule a visit with our healthcare professionals</p>
        </div>

        <div class="booking-layout">

            <div class="booking-main-col">

                <div class="booking-card">
                    <h2 class="booking-card-title">Select Doctor</h2>

                    <div class="doctor-search-wrapper">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="doctorSearch" class="doctor-search-input"
                            placeholder="Search by name or specialty...">
                    </div>

                    <div class="doctor-grid">
                        <?php if (!empty($doctors)): ?>
                            <?php foreach ($doctors as $doctor): 
                                $onLeaveToday = !empty($doctor['is_on_leave_today']);
                            ?>
                                <div class="doctor-card" data-id="<?= e($doctor['doctor_id']) ?>"
                                    data-name="<?= e($doctor['name']) ?>" data-fee="<?= e($doctor['consultation_fee']) ?>">
                                    <div class="doctor-avatar-icon"
                                        style="background-color: <?= e($doctor['color']) ?>; color: #ffffff; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                        <?= e($doctor['initials']) ?>
                                    </div>
                                    <div class="doctor-card-info">
                                        <div class="doctor-card-name"><?= e($doctor['name']) ?></div>
                                        <div class="doctor-card-specialty"><?= e($doctor['specialization']) ?></div>
                                        <?php if ($onLeaveToday): ?>
                                            <div class="doctor-card-status on-leave" style="color: #d97706; font-weight: 600; font-size: 12px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fa-solid fa-plane-departure" style="font-size: 11px;"></i> On Leave Today
                                            </div>
                                        <?php else: ?>
                                            <div class="doctor-card-status available">Available</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="padding: 16px; color: var(--slate-500);">No doctors available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="booking-card">
                    <h2 class="booking-card-title">Select Date &amp; Time</h2>

                    <div class="date-time-layout">
                        <div>
                            <div class="date-time-col-label">Choose Date</div>
                            <div class="calendar-widget">
                                <div class="calendar-header">
                                    <button type="button" class="calendar-nav-btn" id="prevMonthBtn"
                                        aria-label="Previous month">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                    <div class="calendar-header-label" id="calendarMonthYear">June 2026</div>
                                    <button type="button" class="calendar-nav-btn" id="nextMonthBtn"
                                        aria-label="Next month">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="calendar-weekdays">
                                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                                </div>
                                <div class="calendar-days-grid" id="calendarGrid"></div>
                            </div>
                        </div>

                        <div>
                            <div class="date-time-col-label">Available Time Slots</div>
                            <!-- Doctor Leave Alert Banner -->
                            <div id="doctorLeaveBanner" style="display: none; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; font-size: 13px; color: #991b1b; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-circle-exclamation" style="font-size: 16px; flex-shrink: 0; color: #ef4444;"></i>
                                <span id="doctorLeaveBannerText">Doctor is on approved leave on this date. Please choose another date.</span>
                            </div>
                            <div class="time-slot-grid" id="timeSlotGrid">
                                <button type="button" class="time-slot-btn" data-time="09:00:00"><i
                                        class="fa-regular fa-clock"></i> 09:00 AM</button>
                                <button type="button" class="time-slot-btn" data-time="09:30:00"><i
                                        class="fa-regular fa-clock"></i> 09:30 AM</button>
                                <button type="button" class="time-slot-btn" data-time="10:00:00"><i
                                        class="fa-regular fa-clock"></i> 10:00 AM</button>
                                <button type="button" class="time-slot-btn" data-time="10:30:00"><i
                                        class="fa-regular fa-clock"></i> 10:30 AM</button>
                                <button type="button" class="time-slot-btn" data-time="11:00:00"><i
                                        class="fa-regular fa-clock"></i> 11:00 AM</button>
                                <button type="button" class="time-slot-btn" data-time="11:30:00"><i
                                        class="fa-regular fa-clock"></i> 11:30 AM</button>
                                <button type="button" class="time-slot-btn" data-time="14:00:00"><i
                                        class="fa-regular fa-clock"></i> 02:00 PM</button>
                                <button type="button" class="time-slot-btn" data-time="14:30:00"><i
                                        class="fa-regular fa-clock"></i> 02:30 PM</button>
                                <button type="button" class="time-slot-btn" data-time="15:00:00"><i
                                        class="fa-regular fa-clock"></i> 03:00 PM</button>
                                <button type="button" class="time-slot-btn" data-time="15:30:00"><i
                                        class="fa-regular fa-clock"></i> 03:30 PM</button>
                                <button type="button" class="time-slot-btn" data-time="16:00:00"><i
                                        class="fa-regular fa-clock"></i> 04:00 PM</button>
                                <button type="button" class="time-slot-btn" data-time="16:30:00"><i
                                        class="fa-regular fa-clock"></i> 04:30 PM</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="booking-card">
                    <h2 class="booking-card-title">Additional Details</h2>

                    <div class="form-field-group">
                        <label class="form-field-label" for="appointmentType">Appointment Type</label>
                        <select class="form-field-select" id="appointmentType">
                            <option value="" selected disabled>Select appointment type</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Routine Check-up">Routine Check-up</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Lab Test Review">Lab Test Review</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label class="form-field-label" for="reasonForVisit">Reason for Visit</label>
                        <textarea class="form-field-textarea" id="reasonForVisit"
                            placeholder="Please describe your symptoms or reason for visit..."></textarea>
                    </div>
                </div>

            </div>

            <div class="booking-summary-card">
                <h2 class="booking-summary-title">Booking Summary</h2>

                <div class="summary-row">
                    <div class="summary-row-label">Selected Doctor</div>
                    <div class="summary-row-value muted" id="summaryDoctor">Not selected</div>
                </div>

                <div class="summary-row">
                    <div class="summary-row-label">Date</div>
                    <div class="summary-row-value" id="summaryDate">Not selected</div>
                </div>

                <div class="summary-row">
                    <div class="summary-row-label">Time</div>
                    <div class="summary-row-value muted" id="summaryTime">Not selected</div>
                </div>

                <hr class="summary-divider">

                <div class="summary-fee-row">
                    <span>Consultation Fee</span>
                    <span id="consultationFeeVal">$0.00</span>
                </div>
                <div class="summary-fee-row">
                    <span>Booking Fee</span>
                    <span>$10.00</span>
                </div>

                <hr class="summary-divider tight">

                <div class="summary-fee-row total">
                    <span>Total</span>
                    <span id="totalFeeVal">$10.00</span>
                </div>

                <a href="javascript:void(0);" id="btnConfirmBooking" class="btn-confirm-booking disabled">
                    <i class="fa-regular fa-calendar-check"></i> Confirm Booking
                </a>

                <p class="summary-footnote">By booking, you agree to our terms and conditions</p>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Preloaded approved leaves for all doctors
            const doctorLeavesMap = <?= json_encode($doctorLeavesMap) ?>;

            function isDateOnLeave(dateStr) {
                if (!bookingData.doctorId || !dateStr) return false;
                const leaves = doctorLeavesMap[bookingData.doctorId] || [];
                for (const l of leaves) {
                    if (dateStr >= l.start_date && dateStr <= l.end_date) {
                        return l;
                    }
                }
                return false;
            }

            // Dynamically update each doctor card's badge based on the selected calendar date
            function updateDoctorCardsStatus(selectedDate) {
                if (!selectedDate) return;
                const today = new Date();
                const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                const isToday = selectedDate === todayStr;

                document.querySelectorAll('.doctor-card').forEach(card => {
                    const docId = card.getAttribute('data-id');
                    const statusEl = card.querySelector('.doctor-card-status');
                    if (!statusEl) return;

                    const leaves = doctorLeavesMap[docId] || [];
                    let onLeave = false;
                    for (const l of leaves) {
                        if (selectedDate >= l.start_date && selectedDate <= l.end_date) {
                            onLeave = true;
                            break;
                        }
                    }

                    if (onLeave) {
                        const badgeText = isToday ? 'On Leave Today' : 'On Leave';
                        statusEl.className = 'doctor-card-status on-leave';
                        statusEl.style.color = '#d97706';
                        statusEl.style.fontWeight = '600';
                        statusEl.style.fontSize = '12px';
                        statusEl.style.display = 'flex';
                        statusEl.style.alignItems = 'center';
                        statusEl.style.gap = '4px';
                        statusEl.innerHTML = `<i class="fa-solid fa-plane-departure" style="font-size: 11px;"></i> ${badgeText}`;
                    } else {
                        statusEl.className = 'doctor-card-status available';
                        statusEl.style.color = '#16a34a';
                        statusEl.style.fontWeight = '600';
                        statusEl.style.fontSize = '12.5px';
                        statusEl.style.display = 'block';
                        statusEl.innerHTML = 'Available';
                    }
                });
            }

            // 1. Initialize date state
            let currentDate = new Date();

            let bookingData = {
                doctorId: null,
                doctorName: null,
                date: null, // "YYYY-MM-DD"
                time: null, // "HH:MM:SS"
                type: null,
                reason: "",
                fee: 0
            };

            const monthYearLabel = document.getElementById("calendarMonthYear");
            const calendarGrid = document.getElementById("calendarGrid");
            const prevMonthBtn = document.getElementById("prevMonthBtn");
            const nextMonthBtn = document.getElementById("nextMonthBtn");

            const summaryDoctor = document.getElementById("summaryDoctor");
            const summaryDate = document.getElementById("summaryDate");
            const summaryTime = document.getElementById("summaryTime");
            const btnConfirm = document.getElementById("btnConfirmBooking");

            const months = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            // Render calendar grid dynamically
            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                monthYearLabel.innerText = `${months[month]} ${year}`;
                calendarGrid.innerHTML = "";

                const firstDayIndex = new Date(year, month, 1).getDay();
                const totalDays = new Date(year, month + 1, 0).getDate();
                const prevTotalDays = new Date(year, month, 0).getDate();

                // Leading days from previous month
                for (let i = firstDayIndex; i > 0; i--) {
                    const dayDiv = document.createElement("div");
                    dayDiv.classList.add("calendar-day", "muted");
                    dayDiv.innerText = prevTotalDays - i + 1;
                    calendarGrid.appendChild(dayDiv);
                }

                // Current month days
                for (let day = 1; day <= totalDays; day++) {
                    const dayDiv = document.createElement("div");
                    dayDiv.classList.add("calendar-day");
                    dayDiv.innerText = day;

                    const formattedMonthNum = String(month + 1).padStart(2, '0');
                    const formattedDayNum = String(day).padStart(2, '0');
                    const matchStr = `${year}-${formattedMonthNum}-${formattedDayNum}`;
                    const displayStr = `${months[month]} ${day}, ${year}`;

                    const leaveInfo = isDateOnLeave(matchStr);
                    if (leaveInfo) {
                        dayDiv.classList.add("on-leave");
                        dayDiv.title = `Dr. ${bookingData.doctorName || 'Doctor'} is on approved leave (${leaveInfo.start_date} to ${leaveInfo.end_date})`;
                    }

                    if (bookingData.date === matchStr) {
                        dayDiv.classList.add("selected");
                    }

                    dayDiv.addEventListener("click", function () {
                        const leave = isDateOnLeave(matchStr);
                        if (leave) {
                            document.getElementById('errorModalMsg').innerText = `Dr. ${bookingData.doctorName || 'The selected doctor'} is on approved leave from ${leave.start_date} to ${leave.end_date}. No appointments can be scheduled on this date.`;
                            document.getElementById('bookingErrorModal').classList.add('active');
                            return;
                        }

                        document.querySelectorAll("#calendarGrid .calendar-day").forEach(d => d.classList.remove("selected"));
                        this.classList.add("selected");

                        bookingData.date = matchStr;
                        summaryDate.innerText = displayStr;
                        updateDoctorCardsStatus(matchStr);
                        updateTimeSlotAvailability();
                        validateForm();
                    });

                    calendarGrid.appendChild(dayDiv);
                }

                // Trailing days for next month to complete matrix
                const totalSlotsFilled = firstDayIndex + totalDays;
                const nextMonthSlotsNeeded = totalSlotsFilled % 7 === 0 ? 0 : 7 - (totalSlotsFilled % 7);
                for (let j = 1; j <= nextMonthSlotsNeeded; j++) {
                    const dayDiv = document.createElement("div");
                    dayDiv.classList.add("calendar-day", "muted");
                    dayDiv.innerText = j;
                    calendarGrid.appendChild(dayDiv);
                }
            }

            // Month navigation controls
            prevMonthBtn.addEventListener("click", function () {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });

            nextMonthBtn.addEventListener("click", function () {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });

            // Initialize default selected date to today
            const initYear = currentDate.getFullYear();
            const initMonth = currentDate.getMonth();
            const initDay = currentDate.getDate();
            const initFormattedMonth = String(initMonth + 1).padStart(2, '0');
            const initFormattedDay = String(initDay).padStart(2, '0');
            bookingData.date = `${initYear}-${initFormattedMonth}-${initFormattedDay}`;
            summaryDate.innerText = `${months[initMonth]} ${initDay}, ${initYear}`;

            // Initial render
            renderCalendar();
            updateDoctorCardsStatus(bookingData.date);


            // 3. Doctor selection handling
            const doctorCards = document.querySelectorAll(".doctor-card");
            doctorCards.forEach(card => {
                card.addEventListener("click", function () {
                    if (this.classList.contains("unavailable")) {
                        document.getElementById('errorModalMsg').innerText = "This doctor is currently unavailable. Please select another doctor.";
                        document.getElementById('bookingErrorModal').classList.add('active');
                        return;
                    }
                    doctorCards.forEach(c => c.classList.remove("selected"));
                    this.classList.add("selected");

                    bookingData.doctorId = this.getAttribute("data-id");
                    bookingData.doctorName = this.getAttribute("data-name");
                    bookingData.fee = parseFloat(this.getAttribute("data-fee"));

                    summaryDoctor.innerText = bookingData.doctorName;
                    summaryDoctor.classList.remove("muted");

                    // Re-render calendar so this doctor's leave dates are immediately styled
                    renderCalendar();

                    // Update doctor card badge and time slots for current selected date
                    updateDoctorCardsStatus(bookingData.date);
                    updateTimeSlotAvailability();

                    // Update fees in UI
                    updateFees();

                    validateForm();

                    // Client-side Web Service Consumption: real-time refresh of doctor details & approved leaves
                    const liveReqId = 'REQ-LIVE-' + Math.random().toString(36).substring(2, 9);
                    const liveTimestamp = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    fetch(`../../api/doctor_details.php?doctorId=${encodeURIComponent(bookingData.doctorId)}&requestID=${encodeURIComponent(liveReqId)}&timestamp=${encodeURIComponent(liveTimestamp)}`)
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'S' && res.data && res.data.approvedLeaves) {
                                doctorLeavesMap[bookingData.doctorId] = res.data.approvedLeaves;
                                renderCalendar();
                                updateDoctorCardsStatus(bookingData.date);
                                updateTimeSlotAvailability();
                            }
                        })
                        .catch(() => {});
                });
            });

            // 4. Time slot selection handling
            const timeBtns = document.querySelectorAll("#timeSlotGrid .time-slot-btn");
            timeBtns.forEach(btn => {
                btn.addEventListener("click", function () {
                    if (this.classList.contains("disabled")) return;
                    timeBtns.forEach(b => b.classList.remove("selected"));
                    this.classList.add("selected");

                    bookingData.time = this.getAttribute("data-time");
                    summaryTime.innerText = this.innerText.trim();
                    summaryTime.classList.remove("muted");
                    validateForm();
                });
            });

            // Disable time slots that have already passed when today is selected or doctor is on leave
            function updateTimeSlotAvailability() {
                const leaveBanner = document.getElementById('doctorLeaveBanner');
                const leaveBannerText = document.getElementById('doctorLeaveBannerText');
                const leave = isDateOnLeave(bookingData.date);

                if (leave) {
                    if (leaveBanner) {
                        leaveBanner.style.display = 'flex';
                        leaveBannerText.innerText = `Dr. ${bookingData.doctorName || 'Doctor'} is on approved leave on this date (${bookingData.date}). Please choose another date.`;
                    }
                    timeBtns.forEach(btn => {
                        btn.classList.add("disabled");
                        btn.classList.remove("selected");
                    });
                    bookingData.time = "";
                    summaryTime.innerText = "Not selected";
                    summaryTime.classList.add("muted");
                    validateForm();
                    return;
                } else {
                    if (leaveBanner) {
                        leaveBanner.style.display = 'none';
                    }
                }

                const now = new Date();
                const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                const isToday = bookingData.date === todayStr;
                const nowHms = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

                timeBtns.forEach(btn => {
                    const slotTime = btn.getAttribute("data-time");
                    const isPast = isToday && slotTime <= nowHms;
                    btn.classList.toggle("disabled", isPast);
                    if (isPast && btn.classList.contains("selected")) {
                        btn.classList.remove("selected");
                        bookingData.time = "";
                        summaryTime.innerText = "Not selected";
                        summaryTime.classList.add("muted");
                    }
                });
                validateForm();
            }
            updateTimeSlotAvailability();

            // Dynamic fee calculator matching Strategy Pattern
            function updateFees() {
                if (!bookingData.fee) return;

                let finalConsultationFee = bookingData.fee;
                if (bookingData.type === 'Follow-up') {
                    finalConsultationFee = bookingData.fee * 0.5;
                } else if (['Routine Check-up', 'Vaccination', 'Lab Test Review'].includes(bookingData.type)) {
                    finalConsultationFee = bookingData.fee * 0.8;
                }

                document.getElementById("consultationFeeVal").innerText = `$${finalConsultationFee.toFixed(2)}`;
                const bookingFee = 10.00;
                const totalFee = finalConsultationFee + bookingFee;
                document.getElementById("totalFeeVal").innerText = `$${totalFee.toFixed(2)}`;
            }

            // 5. Input change event listeners
            const appointmentType = document.getElementById("appointmentType");
            const reasonForVisit = document.getElementById("reasonForVisit");

            appointmentType.addEventListener("change", function () {
                bookingData.type = this.value;
                updateFees();
                validateForm();
            });

            reasonForVisit.addEventListener("input", function () {
                bookingData.reason = this.value.trim();
                validateForm();
            });

            // 6. Form validation
            function validateForm() {
                const onLeave = isDateOnLeave(bookingData.date);
                if (!onLeave && bookingData.doctorId && bookingData.date && bookingData.time && bookingData.type && bookingData.reason.length > 0) {
                    btnConfirm.classList.remove("disabled");
                } else {
                    btnConfirm.classList.add("disabled");
                }
            }

            // 7. Submit booking request
            btnConfirm.addEventListener("click", function () {
                if (this.classList.contains("disabled")) return;

                btnConfirm.classList.add("disabled");

                fetch("book_appointment.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(bookingData)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const successMsg = `Doctor: ${bookingData.doctorName}\nDate: ${bookingData.date}\nTime: ${summaryTime.innerText.trim()}`;
                            document.getElementById('successModalMsg').innerText = successMsg;
                            document.getElementById('bookingSuccessModal').classList.add('active');
                        } else {
                            document.getElementById('errorModalMsg').innerText = data.message || "Failed to book appointment.";
                            document.getElementById('bookingErrorModal').classList.add('active');
                            btnConfirm.classList.remove("disabled");
                        }
                    })
                    .catch(err => {
                        document.getElementById('errorModalMsg').innerText = "Error sending booking request. Please check your network connection.";
                        document.getElementById('bookingErrorModal').classList.add('active');
                        btnConfirm.classList.remove("disabled");
                    });
            });

            // 8. Doctor search and filter handling
            const searchInput = document.getElementById("doctorSearch");
            searchInput.addEventListener("input", function () {
                const filter = this.value.toLowerCase();
                doctorCards.forEach(card => {
                    const name = card.getAttribute("data-name").toLowerCase();
                    const specialty = card.querySelector(".doctor-card-specialty").innerText.toLowerCase();
                    if (name.includes(filter) || specialty.includes(filter)) {
                        card.style.display = "";
                    } else {
                        card.style.display = "none";
                    }
                });
            });
        });
    </script>
    <style>
        @keyframes modalFadeIn {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>

    <!-- Booking Success Modal -->
    <div id="bookingSuccessModal" class="modal-overlay">
        <div class="modal-card"
            style="background: white; border-radius: 16px; width: 400px; max-width: 90%; text-align: center; padding: 32px 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: modalFadeIn 0.3s ease-out; border: none;">
            <div
                style="width: 64px; height: 64px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Booking Confirmed!</h2>
            <p id="successModalMsg"
                style="font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.5; text-align: left; background: #f8fafc; padding: 12px; border-radius: 8px; font-family: monospace; white-space: pre-line;">
            </p>

            <button onclick="closeSuccessAndRedirect()"
                style="width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s;">
                Go to Dashboard
            </button>
        </div>
    </div>

    <!-- Booking Error Modal -->
    <div id="bookingErrorModal" class="modal-overlay">
        <div class="modal-card"
            style="background: white; border-radius: 16px; width: 400px; max-width: 90%; text-align: center; padding: 32px 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: modalFadeIn 0.3s ease-out; border: none;">
            <div
                style="width: 64px; height: 64px; background: #fef2f2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Booking Failed</h2>
            <p id="errorModalMsg" style="font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.5;"></p>

            <button onclick="closeErrorModal()"
                style="width: 100%; background: #ef4444; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s;">
                OK
            </button>
        </div>
    </div>

    <script>
        function closeSuccessAndRedirect() {
            window.location.href = "mainpage.php";
        }

        function closeErrorModal() {
            document.getElementById('bookingErrorModal').classList.remove('active');
        }
    </script>
</body>

</html>