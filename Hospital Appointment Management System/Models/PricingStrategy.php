<?php
// Models/PricingStrategy.php

interface PricingStrategy {
    public function calculatePrice(float $baseFee): float;
}

// Concrete Strategy 1: Standard Pricing (100% base fee + $10.00 booking fee)
class StandardPricing implements PricingStrategy {
    public function calculatePrice(float $baseFee): float {
        return $baseFee + 10.00;
    }
}

// Concrete Strategy 2: Follow-up Pricing (50% base fee + $10.00 booking fee)
class FollowUpPricing implements PricingStrategy {
    public function calculatePrice(float $baseFee): float {
        return ($baseFee * 0.5) + 10.00;
    }
}

// Concrete Strategy 3: Routine Pricing (80% base fee + $10.00 booking fee)
class RoutinePricing implements PricingStrategy {
    public function calculatePrice(float $baseFee): float {
        return ($baseFee * 0.8) + 10.00;
    }
}

// Context class that uses a PricingStrategy
class PaymentContext {
    private $strategy;

    public function __construct(PricingStrategy $strategy) {
        $this->strategy = $strategy;
    }

    public function getFinalPrice(float $baseFee): float {
        return $this->strategy->calculatePrice($baseFee);
    }
}
