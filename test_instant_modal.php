<?php
session_start();
// Simulate user authentication
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'employee';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Instant Modal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .work-report-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .work-report-modal.active { display: flex; }

        .work-report-content {
            background: #ffffff;
            width: 90%;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalSlideIn 0.25s ease;
        }
        .work-report-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .work-report-body { padding: 16px 20px; }
        .work-report-footer {
            padding: 16px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
        }
        .submit-btn {
            background: #4a6cf7;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <h1>Test Page for Instant Modal</h1>
    <p>This page demonstrates including the instant modal from a separate file.</p>
    
    <!-- Include the instant modal -->
    <?php include 'instant_modal.php'; ?>
</body>
</html>