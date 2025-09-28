<!DOCTYPE html>
<html>
<head>
    <title>Rejection Information Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .rejection-item { background-color: #fff; padding: 12px; border-radius: 6px; border: 1px solid #ffcdd2; margin-bottom: 10px; }
        .rejection-role .badge { font-size: 0.8rem; padding: 6px 12px; }
        .rejected-by-info { font-size: 0.85rem; color: #666; font-style: italic; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Travel Expense Rejection Information Test</h2>
        <p><strong>Test:</strong> Shows who rejected the travel expense</p>
        
        <?php
        // Test functions
        function getRejectedBy($expense) {
            $rejectedBy = [];
            if (isset($expense['manager_status']) && $expense['manager_status'] === 'rejected') {
                $rejectedBy[] = 'Manager';
            }
            if (isset($expense['accountant_status']) && $expense['accountant_status'] === 'rejected') {
                $rejectedBy[] = 'Accountant';
            }
            if (isset($expense['hr_status']) && $expense['hr_status'] === 'rejected') {
                $rejectedBy[] = 'HR';
            }
            return $rejectedBy;
        }
        
        function getRejectionReasons($expense) {
            $reasons = [];
            if (isset($expense['manager_status']) && $expense['manager_status'] === 'rejected' && !empty($expense['manager_reason'])) {
                $reasons['Manager'] = $expense['manager_reason'];
            }
            if (isset($expense['accountant_status']) && $expense['accountant_status'] === 'rejected' && !empty($expense['accountant_reason'])) {
                $reasons['Accountant'] = $expense['accountant_reason'];
            }
            if (isset($expense['hr_status']) && $expense['hr_status'] === 'rejected' && !empty($expense['hr_reason'])) {
                $reasons['HR'] = $expense['hr_reason'];
            }
            return $reasons;
        }
        
        function formatRejectionInfo($expense) {
            $rejectedBy = getRejectedBy($expense);
            $reasons = getRejectionReasons($expense);
            if (empty($rejectedBy)) return null;
            return [
                'rejected_by' => $rejectedBy,
                'reasons' => $reasons,
                'display_text' => 'Rejected by: ' . implode(', ', $rejectedBy)
            ];
        }
        
        // Test cases
        $test_expenses = [
            [
                'id' => 1, 'purpose' => 'Client Meeting', 'status' => 'rejected', 'amount' => 2500,
                'manager_status' => 'rejected', 'manager_reason' => 'Receipt not clear enough',
                'accountant_status' => 'pending', 'accountant_reason' => '',
                'hr_status' => 'pending', 'hr_reason' => ''
            ],
            [
                'id' => 2, 'purpose' => 'Training Session', 'status' => 'rejected', 'amount' => 1800,
                'manager_status' => 'approved', 'manager_reason' => '',
                'accountant_status' => 'rejected', 'accountant_reason' => 'Amount exceeds policy limit',
                'hr_status' => 'pending', 'hr_reason' => ''
            ],
            [
                'id' => 3, 'purpose' => 'Site Visit', 'status' => 'rejected', 'amount' => 3200,
                'manager_status' => 'rejected', 'manager_reason' => 'Not pre-approved',
                'accountant_status' => 'rejected', 'accountant_reason' => 'Missing supporting documents',
                'hr_status' => 'rejected', 'hr_reason' => 'Travel policy violation'
            ]
        ];
        
        foreach ($test_expenses as $expense):
            $rejection_info = formatRejectionInfo($expense);
        ?>
        
        <div class="test-card">
            <h5><?php echo $expense['purpose']; ?> - ₹<?php echo number_format($expense['amount'], 2); ?></h5>
            <span class="badge badge-danger">REJECTED</span>
            
            <?php if ($rejection_info): ?>
                <div class="rejected-by-info mt-2">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($rejection_info['display_text']); ?>
                </div>
                
                <?php if (!empty($rejection_info['reasons'])): ?>
                    <div class="mt-3">
                        <strong>Detailed Rejection Information:</strong>
                        <?php foreach ($rejection_info['reasons'] as $role => $reason): ?>
                            <div class="rejection-item">
                                <div class="rejection-role">
                                    <span class="badge badge-danger">
                                        <i class="fas fa-<?php echo $role === 'Manager' ? 'user-tie' : ($role === 'Accountant' ? 'calculator' : 'users'); ?>"></i>
                                        Rejected by <?php echo htmlspecialchars($role); ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($reason); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php endforeach; ?>
    </div>
</body>
</html>