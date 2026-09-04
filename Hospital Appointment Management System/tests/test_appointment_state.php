<?php
// test_appointment_state.php
// A test script to verify allowed and rejected state transitions for the Appointment State Pattern.

require_once 'Models/AppointmentState.php';

// Mock AppointmentContext to avoid database dependency during unit tests
class MockAppointment implements AppointmentContext {
    private $state;
    public $transitions = [];

    public function __construct(AppointmentState $initialState) {
        $this->state = $initialState;
    }

    public function transitionTo(AppointmentState $state): void {
        $oldName = $this->state->getStatusName();
        $this->state = $state;
        $newName = $state->getStatusName();
        $this->transitions[] = "$oldName -> $newName";
    }

    public function getStatus(): string {
        return $this->state->getStatusName();
    }

    public function complete(): void {
        $this->state->complete($this);
    }

    public function cancel(): void {
        $this->state->cancel($this);
    }

    public function expire(): void {
        $this->state->expire($this);
    }
}

function runTests() {
    echo "==================================================\n";
    echo "APPOINTMENT STATE PATTERN - TRANSITION TEST SUITE\n";
    echo "==================================================\n\n";

    // Test Case 1: Standard Lifecycle (Scheduled -> Completed)
    echo "Test Case 1: Scheduled -> Completed (Allowed)\n";
    $appointment = new MockAppointment(new ScheduledState());
    try {
        $appointment->complete();
        echo "✅ SUCCESS: Status transitioned to " . $appointment->getStatus() . "\n";
        echo "   Transition Log: " . implode(" | ", $appointment->transitions) . "\n";
    } catch (Exception $e) {
        echo "❌ FAILED: Unexpected exception: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test Case 2: Scheduled -> Cancelled (Allowed)
    echo "Test Case 2: Scheduled -> Cancelled (Allowed)\n";
    $appointment = new MockAppointment(new ScheduledState());
    try {
        $appointment->cancel();
        echo "✅ SUCCESS: Status transitioned to " . $appointment->getStatus() . "\n";
        echo "   Transition Log: " . implode(" | ", $appointment->transitions) . "\n";
    } catch (Exception $e) {
        echo "❌ FAILED: Unexpected exception: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test Case 3: Scheduled -> Expired (Allowed)
    echo "Test Case 3: Scheduled -> Expired (Allowed)\n";
    $appointment = new MockAppointment(new ScheduledState());
    try {
        $appointment->expire();
        echo "✅ SUCCESS: Status transitioned to " . $appointment->getStatus() . "\n";
        echo "   Transition Log: " . implode(" | ", $appointment->transitions) . "\n";
    } catch (Exception $e) {
        echo "❌ FAILED: Unexpected exception: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test Case 4: Invalid transition (Completed -> Cancelled) - Rejected
    echo "Test Case 4: Completed -> Cancelled (Rejected)\n";
    $appointment = new MockAppointment(new CompletedState());
    try {
        $appointment->cancel();
        echo "❌ FAILED: Transition was allowed but should have been blocked.\n";
    } catch (Exception $e) {
        echo "✅ SUCCESS: Transition blocked as expected.\n";
        echo "   Thrown Exception Message: \"" . $e->getMessage() . "\"\n";
    }
    echo "\n";

    // Test Case 5: Invalid transition (Cancelled -> Completed) - Rejected
    echo "Test Case 5: Cancelled -> Completed (Rejected)\n";
    $appointment = new MockAppointment(new CancelledState());
    try {
        $appointment->complete();
        echo "❌ FAILED: Transition was allowed but should have been blocked.\n";
    } catch (Exception $e) {
        echo "✅ SUCCESS: Transition blocked as expected.\n";
        echo "   Thrown Exception Message: \"" . $e->getMessage() . "\"\n";
    }
    echo "\n";

    // Test Case 6: Invalid transition (Expired -> Completed) - Rejected
    echo "Test Case 6: Expired -> Completed (Rejected)\n";
    $appointment = new MockAppointment(new ExpiredState());
    try {
        $appointment->complete();
        echo "❌ FAILED: Transition was allowed but should have been blocked.\n";
    } catch (Exception $e) {
        echo "✅ SUCCESS: Transition blocked as expected.\n";
        echo "   Thrown Exception Message: \"" . $e->getMessage() . "\"\n";
    }
    echo "\n";
}

runTests();
?>
