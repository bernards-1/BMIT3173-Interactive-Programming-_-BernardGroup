<?php
// services/PricingService.php

require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Models/PricingStrategy.php';

class PricingService {
    /**
     * Calculate final appointment amount based on Doctor ORM entity & appointment type.
     * 
     * @param Doctor $doctor Doctor ORM model instance
     * @param string $appointmentType e.g., 'Follow-up', 'Routine Check-up', 'Consultation'
     * @return float Final calculated fee
     */
    public static function calculateFee(Doctor $doctor, string $appointmentType): float {
        // 1. Retrieve base consultation fee from Doctor ORM object
        $baseFee = (float)($doctor->consultation_fee ?? 50.00);

        // 2. Select appropriate Strategy based on appointment type
        switch ($appointmentType) {
            case 'Follow-up':
                $strategy = new FollowUpPricing();
                break;

            case 'Routine Check-up':
            case 'Vaccination':
            case 'Lab Test Review':
                $strategy = new RoutinePricing();
                break;

            case 'Consultation':
            case 'General Checkup':
            default:
                $strategy = new StandardPricing();
                break;
        }

        // 3. Execute Strategy through PaymentContext
        $context = new PaymentContext($strategy);
        return (float)$context->getFinalPrice($baseFee);
    }

    /**
     * Factory helper to obtain the PricingStrategy instance directly.
     */
    public static function getStrategyForType(string $appointmentType): PricingStrategy {
        switch ($appointmentType) {
            case 'Follow-up':
                return new FollowUpPricing();
            case 'Routine Check-up':
            case 'Vaccination':
            case 'Lab Test Review':
                return new RoutinePricing();
            default:
                return new StandardPricing();
        }
    }
}
