<?php
// Views/Patient/appointment_modals.php
?>
<!-- Cancel Appointment Modal -->
<div class="modal-overlay" id="cancelAppointmentModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="modal-header" style="background-color: #ef4444;">
            <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> Cancel Appointment</div>
            <button type="button" class="modal-close" onclick="closeCancelModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cancelAptId">
            <p style="margin: 0 0 16px; font-size: 14px; color: var(--slate-700); line-height: 1.5;">
                Are you sure you want to cancel your appointment with <strong id="cancelDoctorName" style="color: var(--slate-900);">Dr. Doctor</strong>?
            </p>
            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; font-size: 13px; color: #991b1b;">
                <i class="fa-solid fa-circle-info" style="font-size: 18px; color: #ef4444;"></i>
                <div>
                    <div id="cancelAptDetail" style="font-weight: 600;">Date & Time</div>
                    <div style="font-size: 12px; opacity: 0.85; margin-top: 2px;">This action cannot be undone.</div>
                </div>
            </div>
        </div>
        <div style="padding: 16px 24px; background: var(--slate-50); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn" style="background: white; border: 1px solid #d1d5db; color: #374151;" onclick="closeCancelModal()">Keep Appointment</button>
            <button type="button" class="btn" id="confirmCancelBtn" style="background: #ef4444; border: 1px solid #dc2626; color: white;" onclick="submitCancelAppointment()">Yes, Cancel</button>
        </div>
    </div>
</div>

<!-- Reschedule Appointment Modal -->
<div class="modal-overlay" id="rescheduleAppointmentModal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header" style="background-color: var(--primary-blue, #3b82f6);">
            <div class="modal-title"><i class="fa-solid fa-calendar-days" style="margin-right: 8px;"></i> Reschedule Appointment</div>
            <button type="button" class="modal-close" onclick="closeRescheduleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rescheduleAptId">
            <div style="margin-bottom: 16px; background: #eff6ff; padding: 12px 14px; border-radius: 8px; border: 1px solid #dbeafe;">
                <div style="font-size: 12px; color: #1e40af; font-weight: 600;">Current Appointment</div>
                <div id="rescheduleDoctorName" style="font-size: 14px; font-weight: 700; color: #1e3a8a; margin-top: 2px;">Doctor Name</div>
                <div id="rescheduleCurrentDetail" style="font-size: 12px; color: #3b82f6; margin-top: 2px;">Aug 23, 2026 at 2:30 PM</div>
            </div>

            <!-- Doctor Leave Warning Alert -->
            <div id="rescheduleLeaveAlert" style="display: none; margin-bottom: 16px; background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 12px; font-size: 13px; color: #991b1b; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; font-size: 16px;"></i>
                <span>The doctor is on leave on this date. Please pick another date.</span>
            </div>

            <div style="margin-bottom: 16px;">
                <label for="rescheduleNewDate" style="display: block; font-size: 13px; font-weight: 600; color: var(--slate-700); margin-bottom: 6px;">Select New Date</label>
                <input type="date" id="rescheduleNewDate" class="doctor-search-input" style="padding-left: 12px;" min="<?= date('Y-m-d') ?>" onchange="checkAvailableSlots()">
            </div>

            <div style="margin-bottom: 8px;">
                <label for="rescheduleNewTime" style="display: block; font-size: 13px; font-weight: 600; color: var(--slate-700); margin-bottom: 6px;">Select New Time Slot</label>
                <select id="rescheduleNewTime" class="doctor-search-input" style="padding-left: 12px;">
                    <option value="09:00:00" data-label="09:00 AM">09:00 AM</option>
                    <option value="09:30:00" data-label="09:30 AM">09:30 AM</option>
                    <option value="10:00:00" data-label="10:00 AM">10:00 AM</option>
                    <option value="10:30:00" data-label="10:30 AM">10:30 AM</option>
                    <option value="11:00:00" data-label="11:00 AM">11:00 AM</option>
                    <option value="11:30:00" data-label="11:30 AM">11:30 AM</option>
                    <option value="14:00:00" data-label="02:00 PM">02:00 PM</option>
                    <option value="14:30:00" data-label="02:30 PM">02:30 PM</option>
                    <option value="15:00:00" data-label="03:00 PM">03:00 PM</option>
                    <option value="15:30:00" data-label="03:30 PM">03:30 PM</option>
                    <option value="16:00:00" data-label="04:00 PM">04:00 PM</option>
                    <option value="16:30:00" data-label="04:30 PM">04:30 PM</option>
                </select>
            </div>
        </div>
        <div style="padding: 16px 24px; background: var(--slate-50); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn" style="background: white; border: 1px solid #d1d5db; color: #374151;" onclick="closeRescheduleModal()">Close</button>
            <button type="button" class="btn" id="confirmRescheduleBtn" style="background: var(--primary-blue, #3b82f6); border: none; color: white;" onclick="submitRescheduleAppointment()">Confirm Reschedule</button>
        </div>
    </div>
</div>

<script>
function openCancelModal(aptId, doctorName, displayDate, displayTime) {
    document.getElementById('cancelAptId').value = aptId;
    document.getElementById('cancelDoctorName').innerText = doctorName;
    document.getElementById('cancelAptDetail').innerText = displayDate + ' at ' + displayTime;
    document.getElementById('cancelAppointmentModal').classList.add('active');
}

function closeCancelModal() {
    document.getElementById('cancelAppointmentModal').classList.remove('active');
}

function openRescheduleModal(aptId, doctorName, rawDate, rawTime, displayDate, displayTime) {
    document.getElementById('rescheduleAptId').value = aptId;
    document.getElementById('rescheduleDoctorName').innerText = doctorName;
    document.getElementById('rescheduleCurrentDetail').innerText = displayDate + ' at ' + displayTime;
    
    const dateInput = document.getElementById('rescheduleNewDate');
    if (rawDate) {
        dateInput.value = rawDate;
    } else {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    const timeSelect = document.getElementById('rescheduleNewTime');
    if (rawTime) {
        timeSelect.value = rawTime;
    }
    
    document.getElementById('rescheduleAppointmentModal').classList.add('active');
    
    // Check available slots for selected date
    checkAvailableSlots();
}

function closeRescheduleModal() {
    document.getElementById('rescheduleAppointmentModal').classList.remove('active');
}

function checkAvailableSlots() {
    const aptId = document.getElementById('rescheduleAptId').value;
    const selectedDate = document.getElementById('rescheduleNewDate').value;
    const leaveAlert = document.getElementById('rescheduleLeaveAlert');
    const submitBtn = document.getElementById('confirmRescheduleBtn');
    const timeSelect = document.getElementById('rescheduleNewTime');

    if (!selectedDate || !aptId) return;

    fetch(`../../api/appointment_action.php?action=get_booked_slots&appointment_id=${encodeURIComponent(aptId)}&date=${encodeURIComponent(selectedDate)}`)
    .then(res => res.json())
    .then(data => {
        const isSuccess = (data.status === 'success' || data.status === 'no_data' || data.success);
        if (!isSuccess) return;

        const payload = data.data || data;
        const isOnLeave = payload.on_leave || false;
        const bookedSlots = payload.booked_slots || [];

        if (isOnLeave) {
            leaveAlert.style.display = 'flex';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        } else {
            leaveAlert.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }

        const options = timeSelect.querySelectorAll('option');
        let hasAvailableSelected = false;

        const now = new Date();
        const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const isToday = selectedDate === todayStr;
        const nowHms = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;

        options.forEach(opt => {
            const timeVal = opt.value;
            const originalLabel = opt.getAttribute('data-label') || opt.value;
            const isPast = isToday && timeVal <= nowHms;

            if (bookedSlots.includes(timeVal)) {
                opt.disabled = true;
                opt.innerText = `${originalLabel} (Booked)`;
                opt.style.color = '#94a3b8';
            } else if (isPast) {
                opt.disabled = true;
                opt.innerText = `${originalLabel} (Past)`;
                opt.style.color = '#94a3b8';
            } else {
                opt.disabled = false;
                opt.innerText = originalLabel;
                opt.style.color = '';
                if (opt.selected && !opt.disabled) {
                    hasAvailableSelected = true;
                }
            }
        });

        // If current selection is disabled (booked), select the first available option
        if (!hasAvailableSelected && !isOnLeave) {
            for (let opt of options) {
                if (!opt.disabled) {
                    timeSelect.value = opt.value;
                    break;
                }
            }
        }
    })
    .catch(err => console.error(err));
}

function updateUIDAfterCancel(aptId) {
    // 1. Check if we are on mainpage.php (Dashboard)
    const mainItem = document.getElementById('apt-item-' + aptId);
    if (mainItem) {
        mainItem.style.transition = 'all 0.3s ease';
        mainItem.style.opacity = '0';
        mainItem.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            mainItem.remove();
            
            const bannerCnt = document.getElementById('bannerUpcomingCount');
            if (bannerCnt) {
                let count = parseInt(bannerCnt.textContent) || 0;
                bannerCnt.textContent = Math.max(0, count - 1);
            }
            const sectionCnt = document.getElementById('sectionUpcomingCount');
            if (sectionCnt) {
                let count = parseInt(sectionCnt.textContent) || 0;
                let newCount = Math.max(0, count - 1);
                sectionCnt.textContent = newCount + ' scheduled';
            }
            
            const wrapper = document.querySelector('.schedule-items-wrapper');
            if (wrapper && wrapper.children.length === 0) {
                wrapper.innerHTML = '<p style="padding: 24px; color: var(--slate-500); text-align: center;">No upcoming appointments.</p>';
            }
        }, 300);
    }

    // 2. Check if we are on my_appointment.php
    const aptRow = document.getElementById('apt-row-' + aptId);
    if (aptRow) {
        aptRow.setAttribute('data-status', 'Cancelled');
        
        const badge = aptRow.querySelector('.apt-badge');
        if (badge) {
            badge.className = 'apt-badge cancelled';
            badge.textContent = 'Cancelled';
        }
        
        const actionsDiv = aptRow.querySelector('.apt-row-right');
        if (actionsDiv) {
            const buttons = actionsDiv.querySelectorAll('.apt-action-btn');
            buttons.forEach(b => b.remove());
        }

        const scheduledStat = document.getElementById('statScheduledCount');
        if (scheduledStat) {
            let val = parseInt(scheduledStat.textContent) || 0;
            scheduledStat.textContent = Math.max(0, val - 1);
        }
        const cancelledStat = document.getElementById('statCancelledCount');
        if (cancelledStat) {
            let val = parseInt(cancelledStat.textContent) || 0;
            cancelledStat.textContent = val + 1;
        }

        const activeTab = document.querySelector('#aptFilterTabs .apt-filter-btn.active');
        if (activeTab) {
            const currentFilter = activeTab.getAttribute('data-filter');
            if (currentFilter === 'Scheduled') {
                aptRow.style.transition = 'all 0.3s ease';
                aptRow.style.opacity = '0';
                setTimeout(() => {
                    aptRow.style.display = 'none';
                    aptRow.style.opacity = '1';
                }, 300);
            }
        }
    }
}

function submitCancelAppointment() {
    const aptId = document.getElementById('cancelAptId').value;
    const btn = document.getElementById('confirmCancelBtn');
    btn.disabled = true;
    btn.innerText = 'Cancelling...';

    fetch('../../api/appointment_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'cancel',
            appointment_id: aptId
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = 'Yes, Cancel';

        if (data.status === 'success' || data.success) {
            closeCancelModal();
            updateUIDAfterCancel(aptId);
        } else {
            alert(data.message || 'Failed to cancel appointment.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerText = 'Yes, Cancel';
    });
}

function submitRescheduleAppointment() {
    const aptId = document.getElementById('rescheduleAptId').value;
    const newDate = document.getElementById('rescheduleNewDate').value;
    const newTime = document.getElementById('rescheduleNewTime').value;
    
    if (!newDate || !newTime) {
        alert('Please select both a date and time.');
        return;
    }

    const btn = document.getElementById('confirmRescheduleBtn');
    btn.disabled = true;
    btn.innerText = 'Rescheduling...';

    fetch('../../api/appointment_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'reschedule',
            appointment_id: aptId,
            appointment_date: newDate,
            appointment_time: newTime
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success' || data.success) {
            closeRescheduleModal();
            location.reload();
        } else {
            alert(data.message || 'Failed to reschedule appointment.');
            btn.disabled = false;
            btn.innerText = 'Confirm Reschedule';
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerText = 'Confirm Reschedule';
    });
}
</script>
