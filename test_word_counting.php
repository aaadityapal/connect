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
    <title>Test Word Counting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <h1>Test Word Counting</h1>
    
    <h2>Missing Punch In Modal</h2>
    <button id="openPunchInModalBtn">Open Missing Punch In Modal</button>
    
    <h2>Missing Punch Out Modal</h2>
    <button id="openPunchOutModalBtn">Open Missing Punch Out Modal</button>
    
    <!-- Include the Missing Punch In Modal -->
    <?php include 'modals/missing_punch_modal.php'; ?>
    
    <!-- Include the Missing Punch Out Modal -->
    <?php include 'modals/missing_punch_out_modal.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const openPunchInModalBtn = document.getElementById('openPunchInModalBtn');
            const openPunchOutModalBtn = document.getElementById('openPunchOutModalBtn');
            
            openPunchInModalBtn.addEventListener('click', function() {
                // Open the modal with today's date for testing
                const today = new Date().toISOString().split('T')[0];
                openMissingPunchModal(today);
            });
            
            openPunchOutModalBtn.addEventListener('click', function() {
                // Open the modal with today's date for testing
                const today = new Date().toISOString().split('T')[0];
                openMissingPunchOutModal(today);
            });
        });
    </script>
</body>
</html>