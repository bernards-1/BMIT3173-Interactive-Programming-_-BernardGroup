<?php
// Models/AppointmentState.php

interface AppointmentContext {
    public function transitionTo(AppointmentState $state): void;
}

interface AppointmentState {
    public function getStatusName(): string;
    public function complete(AppointmentContext $appointment): void;
    public function cancel(AppointmentContext $appointment): void;
    public function expire(AppointmentContext $appointment): void;
}

class ScheduledState implements AppointmentState {
    public function getStatusName(): string {
        return 'Scheduled';
    }

    public function complete(AppointmentContext $appointment): void {
        $appointment->transitionTo(new CompletedState());
    }

    public function cancel(AppointmentContext $appointment): void {
        $appointment->transitionTo(new CancelledState());
    }

    public function expire(AppointmentContext $appointment): void {
        $appointment->transitionTo(new ExpiredState());
    }
}

class CompletedState implements AppointmentState {
    public function getStatusName(): string {
        return 'Completed';
    }

    public function complete(AppointmentContext $appointment): void {
        throw new Exception("Completed appointments cannot be completed again.");
    }

    public function cancel(AppointmentContext $appointment): void {
        throw new Exception("Completed appointments cannot be cancelled.");
    }

    public function expire(AppointmentContext $appointment): void {
        throw new Exception("Completed appointments cannot be expired.");
    }
}

class CancelledState implements AppointmentState {
    public function getStatusName(): string {
        return 'Cancelled';
    }

    public function complete(AppointmentContext $appointment): void {
        throw new Exception("Cancelled appointments cannot be completed.");
    }

    public function cancel(AppointmentContext $appointment): void {
        throw new Exception("Cancelled appointments cannot be cancelled again.");
    }

    public function expire(AppointmentContext $appointment): void {
        throw new Exception("Cancelled appointments cannot be expired.");
    }
}

class ExpiredState implements AppointmentState {
    public function getStatusName(): string {
        return 'Expired';
    }

    public function complete(AppointmentContext $appointment): void {
        throw new Exception("Expired appointments cannot be completed.");
    }

    public function cancel(AppointmentContext $appointment): void {
        throw new Exception("Expired appointments cannot be cancelled.");
    }

    public function expire(AppointmentContext $appointment): void {
        throw new Exception("Expired appointments cannot be expired again.");
    }
}
