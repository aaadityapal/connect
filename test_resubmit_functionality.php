<!DOCTYPE html>
<html>
<head>
    <title>Resubmission Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Travel Expense Resubmission Test</h2>
        <p><strong>Current Date:</strong> <?php echo date('Y-m-d'); ?></p>
        <p><strong>Rule:</strong> Cannot resubmit if travel date is older than 15 days</p>
        
        <?php
        $test_expenses = [
            ['id' => 1, 'purpose' => 'Meeting', 'travel_date' => date('Y-m-d', strtotime('-5 days')), 'status' => 'rejected', 'resubmission_count' => 0, 'max_resubmissions' => 3],
            ['id' => 2, 'purpose' => 'Training', 'travel_date' => date('Y-m-d', strtotime('-20 days')), 'status' => 'rejected', 'resubmission_count' => 0, 'max_resubmissions' => 3]
        ];
        
        function canResubmit($expense) {
            if ($expense['status'] !== 'rejected') return false;
            $current_count = intval($expense['resubmission_count']);
            $max_allowed = intval($expense['max_resubmissions']);
            $travel_date = new DateTime($expense['travel_date']);
            $current_date = new DateTime();
            $date_diff = $current_date->diff($travel_date)->days;
            if ($date_diff > 15) return false;
            return $current_count < $max_allowed;
        }
        
        function isExpenseTooOld($expense) {
            $travel_date = new DateTime($expense['travel_date']);
            $current_date = new DateTime();
            $date_diff = $current_date->diff($travel_date)->days;
            return $date_diff > 15;
        }
        
        foreach ($test_expenses as $expense):
            $travel_date = new DateTime($expense['travel_date']);
            $current_date = new DateTime();
            $days_since = $current_date->diff($travel_date)->days;
        ?>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5><?php echo $expense['purpose']; ?></h5>
                <p>Travel Date: <?php echo $expense['travel_date']; ?> (<?php echo $days_since; ?> days ago)</p>
                
                <?php if (canResubmit($expense)): ?>
                    <button class="btn btn-primary">
                        <i class="fas fa-redo"></i> Resubmit
                    </button>
                    <span class="badge badge-success">Can Resubmit</span>
                <?php elseif (isExpenseTooOld($expense)): ?>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-clock"></i> Too Old
                    </button>
                    <span class="badge badge-warning">Too Old (<?php echo $days_since; ?> days)</span>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-ban"></i> Max Reached
                    </button>
                    <span class="badge badge-danger">Max Resubmissions</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endforeach; ?>
    </div>
</body>
</html>