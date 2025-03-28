<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Page - Project Form</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Project Form Styles -->
    <link rel="stylesheet" href="modals/styles/project_form_styles_v1.css">
    
    <style>
        /* Basic styling for the test page */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }

        .test-button {
            padding: 15px 30px;
            font-size: 16px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .test-button:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .test-button i {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <!-- Add Project Button -->
    <button class="test-button add-project-btn">
        <i class="fas fa-plus-circle"></i>
        Add New Project
    </button>

    <!-- Include the Project Form Modal -->
    <?php include 'modals/project_form.php'; ?>

    <!-- Project Form Handler Script -->
    <script src="modals/scripts/project_form_handler_v1.js"></script>
</body>
</html> 