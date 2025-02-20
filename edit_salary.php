<?php
require_once 'config/db_connect.php';

// Get employee ID and month from URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $salary_amount = filter_input(INPUT_POST, 'salary_amount', FILTER_VALIDATE_FLOAT);
    $overtime_amount = filter_input(INPUT_POST, 'overtime_amount', FILTER_VALIDATE_FLOAT);
    $travel_amount = filter_input(INPUT_POST, 'travel_amount', FILTER_VALIDATE_FLOAT);
    $misc_amount = filter_input(INPUT_POST, 'misc_amount', FILTER_VALIDATE_FLOAT);
    $travel_expenses = filter_input(INPUT_POST, 'travel_expenses', FILTER_VALIDATE_FLOAT);

    // Update salary details
    $update_query = "INSERT INTO salary_details 
        (user_id, month_year, salary_amount, overtime_amount, travel_amount, 
         misc_amount, travel_expenses, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        salary_amount = VALUES(salary_amount),
        overtime_amount = VALUES(overtime_amount),
        travel_amount = VALUES(travel_amount),
        misc_amount = VALUES(misc_amount),
        travel_expenses = VALUES(travel_expenses),
        updated_at = NOW()";

    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('isddddd', 
        $employee_id, 
        $month_start,
        $salary_amount,
        $overtime_amount,
        $travel_amount,
        $misc_amount,
        $travel_expenses
    );

    if ($stmt->execute()) {
        header("Location: salary_overview.php?month=" . $selected_month . "&success=1");
        exit;
    } else {
        $error = "Error updating salary details";
    }
}

// Fetch current employee details
$query = "SELECT 
    u.*, 
    s.*,
    (
        SELECT COUNT(DISTINCT DATE(a.date))
        FROM attendance a
        WHERE a.user_id = u.id 
        AND DATE(a.date) BETWEEN ? AND ?
        AND a.status = 'present'
    ) as present_days
    FROM users u
    LEFT JOIN salary_details s ON u.id = s.user_id 
        AND DATE_FORMAT(s.month_year, '%Y-%m') = ?
    WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('sssi', $month_start, $month_end, $selected_month, $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Salary Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse your existing CSS variables and add these specific styles */
        .salary-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 20px auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .readonly-field {
            background-color: #f8fafc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="salary_overview.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Overview
        </a>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="salary-form">
            <h2 class="section-title">Edit Salary Details - <?php echo date('F Y', strtotime($month_start)); ?></h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>Employee Name</label>
                    <input type="text" class="form-control readonly-field" 
                           value="<?php echo htmlspecialchars($employee['username']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Present Days</label>
                    <input type="text" class="form-control readonly-field" 
                           value="<?php echo $employee['present_days']; ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Base Salary</label>
                    <input type="text" class="form-control readonly-field" 
                           value="₹<?php echo number_format($employee['base_salary'], 2); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Salary Amount</label>
                    <input type="number" step="0.01" class="form-control" name="salary_amount"
                           value="<?php echo $employee['salary_amount'] ?? 0; ?>" required>
                </div>

                <div class="form-group">
                    <label>Overtime Amount</label>
                    <input type="number" step="0.01" class="form-control" name="overtime_amount"
                           value="<?php echo $employee['overtime_amount'] ?? 0; ?>">
                </div>

                <div class="form-group">
                    <label>Travel Expenses</label>
                    <input type="number" step="0.01" class="form-control" name="travel_expenses"
                           value="<?php echo $employee['travel_expenses'] ?? 0; ?>">
                </div>

                <div class="form-group">
                    <label>Travel Amount</label>
                    <input type="number" step="0.01" class="form-control" name="travel_amount"
                           value="<?php echo $employee['travel_amount'] ?? 0; ?>">
                </div>

                <div class="form-group">
                    <label>Miscellaneous Amount</label>
                    <input type="number" step="0.01" class="form-control" name="misc_amount"
                           value="<?php echo $employee['misc_amount'] ?? 0; ?>">
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add any client-side validation or calculations here
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            // Add validation if needed
            const salaryAmount = parseFloat(form.salary_amount.value);
            if (salaryAmount < 0) {
                e.preventDefault();
                alert('Salary amount cannot be negative');
            }
        });
    });
    </script>
</body>
</html> 