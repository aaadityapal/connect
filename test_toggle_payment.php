<?php
// Test file to include modals properly
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment Entry Toggle</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Payment Entry Modal with Toggle</h1>
        <button type="button" class="btn btn-primary" onclick="openPaymentModal()">
            Open Payment Entry Modal
        </button>
    </div>

    <!-- Include the streamlined payment entry modal -->
    <?php include 'includes/streamlined_payment_entry_modal.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function openPaymentModal() {
            // Show the streamlined payment entry modal
            const modal = new bootstrap.Modal(document.getElementById('streamlinedPaymentEntryModal'));
            modal.show();
        }
    </script>
</body>
</html>