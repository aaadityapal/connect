<?php
require_once 'config/db_connect.php';
require_once 'manage_leave_balance.php';
session_start();

// Fetch all active users
$users_query = "SELECT id, username FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY username";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $year = $_POST['year'] ?? date('Y');
    
    if ($user_id) {
        if (initializeUserLeaveBalance($user_id, $year)) {
            $_SESSION['success'] = "Leave balance initialized successfully for year $year";
        } else {
            $_SESSION['error'] = "Error initializing leave balance";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Leave Balance | HR Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Copy the CSS variables and common styles from edit_leave.php */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --danger-color: #dc2626;
            --background-color: #f1f5f9;
            --border-color: #e2e8f0;
            --text-color: #1e293b;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .balance-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .balance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .balance-table th,
        .balance-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-cogs"></i>
                Manage Leave Balance
            </h1>
            <button class="btn btn-secondary" onclick="window.location.href='edit_leave.php'">
                <i class="fas fa-arrow-left"></i>
                Back to Leave Management
            </button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="balance-form">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_id">Select Employee</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">Choose an employee</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Select Year</label>
                        <select name="year" id="year" class="form-control" required>
                            <?php
                            $current_year = date('Y');
                            for ($year = $current_year + 1; $year >= $current_year - 1; $year--) {
                                echo "<option value='$year'>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i>
                    Initialize Leave Balance
                </button>
            </form>
        </div>

        <div id="balanceDisplay"></div>
    </div>

    <script>
        document.getElementById('user_id').addEventListener('change', function() {
            const userId = this.value;
            const year = document.getElementById('year').value;
            if (userId) {
                fetch(`fetch_user_leave_balance.php?user_id=${userId}&year=${year}`)
                    .then(response => response.json())
                    .then(data => {
                        displayBalance(data);
                    });
            }
        });

        document.getElementById('year').addEventListener('change', function() {
            const userId = document.getElementById('user_id').value;
            if (userId) {
                const year = this.value;
                fetch(`fetch_user_leave_balance.php?user_id=${userId}&year=${year}`)
                    .then(response => response.json())
                    .then(data => {
                        displayBalance(data);
                    });
            }
        });

        function displayBalance(data) {
            const display = document.getElementById('balanceDisplay');
            let html = `
                <table class="balance-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Total Days</th>
                            <th>Used Days</th>
                            <th>Remaining Days</th>
                            <th>Carried Forward</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(balance => {
                const remaining = balance.total_days - balance.used_days;
                html += `
                    <tr>
                        <td>${balance.leave_type_name}</td>
                        <td>${balance.total_days}</td>
                        <td>${balance.used_days}</td>
                        <td>${remaining}</td>
                        <td>${balance.carried_forward_days}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            display.innerHTML = html;
        }
    </script>
</body>
</html> 