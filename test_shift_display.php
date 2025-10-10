<?php
// Include database configuration
require_once 'config.php';

// Force user ID 21 for testing
$user_id = 21;
$username = "Aditya Kumar Pal";

// Initialize shift information
$shift_info = null;
$remaining_time = null;
$is_weekly_off = false;

// Fetch user shift information
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $currentDay = date('l');
        
        $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs
                  FROM user_shifts us 
                  JOIN shifts s ON us.shift_id = s.id 
                  WHERE us.user_id = ?
                  AND us.effective_from <= ?
                  AND (us.effective_to IS NULL OR us.effective_to >= ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate, $currentDate]);
        
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shift) {
            $shift_info = $shift;
            
            // Check if today is a weekly off
            $weekly_offs = $shift['weekly_offs'];
            
            // Check if today is a weekly off
            if (!empty($weekly_offs)) {
                if (strpos($weekly_offs, $currentDay) !== false) {
                    $is_weekly_off = true;
                }
            }
            
            // If not a weekly off, calculate remaining time
            if (!$is_weekly_off) {
                // Calculate remaining time
                $endTime = strtotime($currentDate . ' ' . $shift['end_time']);
                $currentTimestamp = strtotime('now');
                $remaining_time = $endTime - $currentTimestamp;
            }
        }
    } catch (Exception $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>\n";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Display Test</title>
    <style>
        .shift-info {
            font-size: 1rem;
            font-weight: 500;
            color: #4b5563;
            background-color: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            margin: 10px 0;
            display: inline-block;
        }
        
        .shift-info.warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .shift-info.danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
    </style>
</head>
<body>
    <h2>Shift Display Test</h2>
    
    <h3>Debug Information:</h3>
    <p>User ID: <?php echo $user_id; ?></p>
    <p>Username: <?php echo htmlspecialchars($username); ?></p>
    <p>Current Date: <?php echo $currentDate; ?></p>
    <p>Current Day: <?php echo $currentDay; ?></p>
    
    <h3>Shift Information:</h3>
    <?php if ($shift_info): ?>
        <?php if ($is_weekly_off): ?>
            <div class="shift-info">
                <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift (Weekly Off Today)
            </div>
        <?php elseif ($remaining_time !== null): ?>
            <div class="shift-info <?php echo ($remaining_time < 3600) ? 'danger' : (($remaining_time < 7200) ? 'warning' : ''); ?>">
                <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift ends in: <?php echo gmdate('H:i:s', max(0, $remaining_time)); ?>
            </div>
        <?php else: ?>
            <div class="shift-info">
                <?php echo htmlspecialchars($shift_info['shift_name']); ?> shift (Active)
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="shift-info">
            No shift assigned
        </div>
    <?php endif; ?>
    
    <h3>Raw Data:</h3>
    <pre><?php print_r($shift_info); ?></pre>
</body>
</html>