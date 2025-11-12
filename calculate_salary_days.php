<?php
/**
 * Function to calculate salary days based on attendance and leave data
 * 
 * @param float $baseSalary Base salary of the employee
 * @param int $workingDays Total working days in the month
 * @param int $presentDays Number of days present
 * @param int $unpaidLeave Number of unpaid leave days
 * @param int $shortLeave Number of short leave days
 * @param int $latePunchIns Number of late punch-ins
 * @return array Associative array with calculation details
 */
function calculateSalaryDays($baseSalary, $workingDays, $presentDays, $unpaidLeave, $shortLeave, $latePunchIns) {
    // Initialize calculation details
    $calculationDetails = [
        'base_salary' => $baseSalary,
        'working_days' => $workingDays,
        'present_days' => $presentDays,
        'unpaid_leave' => $unpaidLeave,
        'short_leave' => $shortLeave,
        'late_punch_ins' => $latePunchIns,
        'calculations' => []
    ];
    
    // Start with present days
    $salaryDays = $presentDays;
    $calculationDetails['calculations'][] = "Start with present days: $presentDays";
    
    // Apply unpaid leave deduction (1 day per unpaid leave)
    $unpaidDeduction = $unpaidLeave;
    $salaryDays -= $unpaidDeduction;
    $calculationDetails['calculations'][] = "Minus unpaid leave deduction: $unpaidDeduction day(s)";
    
    // Apply short leave reduction logic (max 2 short leaves can be used for reduction)
    $effectiveShortLeaves = min($shortLeave, 2);
    $calculationDetails['calculations'][] = "Effective short leaves (max 2): $effectiveShortLeaves";
    
    // Reduce late punch-ins using short leaves
    $reducedLateDays = max(0, $latePunchIns - $effectiveShortLeaves);
    $calculationDetails['calculations'][] = "Late days after short leave reduction: $latePunchIns - $effectiveShortLeaves = $reducedLateDays";
    
    // Calculate late deduction based on reduced late days
    // 3 late days = 0.5 day deduction
    // Additional 3 late days = additional 0.5 day deduction, etc.
    $lateDeductionDays = 0;
    if ($reducedLateDays >= 3) {
        // Initial half-day for first 3 late days
        $lateDeductionDays = 0.5;
        $calculationDetails['calculations'][] = "Initial 0.5 day deduction for first 3 late days";
        
        // Additional half-day for every 3 more late days
        $additionalLateDays = $reducedLateDays - 3;
        if ($additionalLateDays > 0) {
            $additionalHalfDays = floor($additionalLateDays / 3);
            $additionalDeduction = $additionalHalfDays * 0.5;
            $lateDeductionDays += $additionalDeduction;
            $calculationDetails['calculations'][] = "Additional $additionalHalfDays deduction(s) of 0.5 day(s) for remaining $additionalLateDays late days = $additionalDeduction";
        }
    }
    
    $salaryDays -= $lateDeductionDays;
    $calculationDetails['calculations'][] = "Minus late deduction: $lateDeductionDays day(s)";
    
    // Ensure salary days don't go below 0
    $salaryDays = max(0, $salaryDays);
    
    // Ensure salary days don't exceed working days
    $salaryDays = min($salaryDays, $workingDays);
    
    // Calculate net salary
    $perDaySalary = ($workingDays > 0) ? ($baseSalary / $workingDays) : 0;
    $netSalary = $salaryDays * $perDaySalary;
    
    // Add final results to calculation details
    $calculationDetails['salary_days_calculated'] = $salaryDays;
    $calculationDetails['per_day_salary'] = $perDaySalary;
    $calculationDetails['net_salary'] = $netSalary;
    
    return $calculationDetails;
}

// Example usage:
// $result = calculateSalaryDays(22000, 23, 23, 1, 2, 9);
// print_r($result);
?>