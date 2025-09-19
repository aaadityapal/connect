<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Edit from View Modal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Edit Button in Labour View Modal</h2>
        <p class="text-info">This test verifies that the edit button in the labour view modal works correctly.</p>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Flow</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Click "Open View Modal" to simulate viewing a labour record</li>
                    <li>In the view modal, click the "Edit Labour" button</li>
                    <li>Verify that:
                        <ul>
                            <li>View modal closes smoothly</li>
                            <li>Edit modal opens with the correct labour ID</li>
                            <li>Form is populated with labour data</li>
                        </ul>
                    </li>
                </ol>
                
                <button onclick="testViewModal()" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Open View Modal (Labour ID: 1)
                </button>
                
                <div class="mt-3">
                    <h6>Expected Behavior:</h6>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <li>✅ View modal opens and loads labour data</li>
                            <li>✅ Edit button shows in modal footer</li>
                            <li>✅ Clicking edit button closes view modal</li>
                            <li>✅ Edit modal opens after 300ms delay</li>
                            <li>✅ Edit form is populated with labour data</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="analytics/executive_insights_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Go to Dashboard
            </a>
        </div>
    </div>

    <script>
        function testViewModal() {
            // Redirect to dashboard with a specific hash to open the view modal
            window.location.href = 'analytics/executive_insights_dashboard.php';
            
            // Wait for page load, then trigger view modal
            setTimeout(() => {
                if (typeof viewLabour === 'function') {
                    viewLabour(1);
                } else {
                    alert('Dashboard functions not loaded yet. Please navigate to the dashboard and manually click the view button for a labour record.');
                }
            }, 1000);
        }
    </script>
</body>
</html>