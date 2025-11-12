<?php
require_once 'calculate_salary_days.php';

// Test with the example data provided
$baseSalary = 22000;
$workingDays = 23;
$presentDays = 23;
$unpaidLeave = 1;
$shortLeave = 2;
$latePunchIns = 9;

$result = calculateSalaryDays($baseSalary, $workingDays, $presentDays, $unpaidLeave, $shortLeave, $latePunchIns);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Calculation Test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Salary Calculation Test</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h4>Input Data</h4>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item">Base Salary: ₹<?= number_format($baseSalary, 2) ?></li>
                    <li class="list-group-item">Working Days: <?= $workingDays ?></li>
                    <li class="list-group-item">Present Days: <?= $presentDays ?></li>
                    <li class="list-group-item">Unpaid Leave: <?= $unpaidLeave ?> day(s)</li>
                    <li class="list-group-item">Short Leave: <?= $shortLeave ?> day(s)</li>
                    <li class="list-group-item">Late Punch-ins: <?= $latePunchIns ?> time(s)</li>
                </ul>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h4>Calculation Steps</h4>
            </div>
            <div class="card-body">
                <ol class="list-group">
                    <?php foreach ($result['calculations'] as $calculation): ?>
                        <li class="list-group-item"><?= $calculation ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h4>Final Results</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h5>Salary Days Calculated</h5>
                            <h2><?= $result['salary_days_calculated'] ?> days</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <h5>Per Day Salary</h5>
                            <h2>₹<?= number_format($result['per_day_salary'], 2) ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h5>Net Salary</h5>
                            <h2>₹<?= number_format($result['net_salary'], 2) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>