<?php
session_start();
require_once 'config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

// Get shift details
$shift = null;
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $shift = $stmt->fetch();

        if (!$shift) {
            $_SESSION['error_message'] = "Shift not found.";
            header('Location: shifts.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: shifts.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shift'])) {
    try {
        $stmt = $pdo->prepare("UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ? WHERE id = ?");
        $stmt->execute([
            $_POST['shift_name'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_GET['id']
        ]);
        
        $_SESSION['success_message'] = "Shift updated successfully!";
        header('Location: shifts.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating shift: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Shift</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Shift</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="shift_name">Shift Name</label>
                <input type="text" 
                       id="shift_name" 
                       name="shift_name" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($shift['shift_name']); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="start_time">Start Time</label>
                <input type="time" 
                       id="start_time" 
                       name="start_time" 
                       class="form-control" 
                       value="<?php echo $shift['start_time']; ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="end_time">End Time</label>
                <input type="time" 
                       id="end_time" 
                       name="end_time" 
                       class="form-control" 
                       value="<?php echo $shift['end_time']; ?>" 
                       required>
            </div>

            <div class="form-group">
                <button type="submit" name="update_shift" class="btn btn-primary">
                    Update Shift
                </button>
                <a href="shifts.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Add client-side validation if needed
        document.querySelector('form').addEventListener('submit', function(e) {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime === endTime) {
                e.preventDefault();
                alert('Start time and end time cannot be the same.');
            }
        });
    </script>
</body>
</html> 