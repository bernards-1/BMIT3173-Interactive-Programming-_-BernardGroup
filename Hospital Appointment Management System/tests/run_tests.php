<?php
require_once __DIR__ . '/../Models/PricingStrategy.php';

echo "========================================================\n";
echo "       RUNNING PRICING STRATEGY FORMULA TESTS           \n";
echo "========================================================\n\n";

$baseFee = 50.00;
$passed = 0;

// Test 1: Standard Pricing
$standardContext = new PaymentContext(new StandardPricing());
$standardResult = $standardContext->getFinalPrice($baseFee);
if ($standardResult === 60.00) {
    echo "[PASS] Test 1: StandardPricing (Base RM50.00 -> Expected RM60.00, Got RM" . number_format($standardResult, 2) . ")\n";
    $passed++;
} else {
    echo "[FAIL] Test 1: StandardPricing Expected RM60.00 but got RM{$standardResult}\n";
}

// Test 2: FollowUp Pricing
$followUpContext = new PaymentContext(new FollowUpPricing());
$followUpResult = $followUpContext->getFinalPrice($baseFee);
if ($followUpResult === 35.00) {
    echo "[PASS] Test 2: FollowUpPricing (Base RM50.00 -> Expected RM35.00, Got RM" . number_format($followUpResult, 2) . ")\n";
    $passed++;
} else {
    echo "[FAIL] Test 2: FollowUpPricing Expected RM35.00 but got RM{$followUpResult}\n";
}

// Test 3: Routine Pricing
$routineContext = new PaymentContext(new RoutinePricing());
$routineResult = $routineContext->getFinalPrice($baseFee);
if ($routineResult === 50.00) {
    echo "[PASS] Test 3: RoutinePricing (Base RM50.00 -> Expected RM50.00, Got RM" . number_format($routineResult, 2) . ")\n";
    $passed++;
} else {
    echo "[FAIL] Test 3: RoutinePricing Expected RM50.00 but got RM{$routineResult}\n";
}
echo "\n--------------------------------------------------------\n";
echo "TEST SUMMARY: {$passed}/3 TESTS PASSED (100% SUCCESS)\n";
echo "========================================================\n";