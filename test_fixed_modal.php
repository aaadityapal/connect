<?php
session_start();
// For testing purposes, we'll simulate a logged-in user
$_SESSION['user_id'] = 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Fixed Missing Punch Out Modal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <h1>Test Fixed Missing Punch Out Modal</h1>
    <button id="openModalBtn">Open Fixed Missing Punch Out Modal</button>
    
    <!-- Include the Fixed Missing Punch Out Modal -->
    <?php include 'modals/missing_punch_out_modal.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const openModalBtn = document.getElementById('openModalBtn');
            
            openModalBtn.addEventListener('click', function() {
                // Open the modal with today's date for testing
                const today = new Date().toISOString().split('T')[0];
                openMissingPunchOutModal(today);
            });
        });
    </script>
</body>
</html>