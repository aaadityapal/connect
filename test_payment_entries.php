<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment Entries API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Recent Payment Entries</h2>
        <p class="text-info">This page tests the recent payment entries API and displays the data.</p>
        
        <div class="card">
            <div class="card-header">
                <h5>API Test Results</h5>
                <button onclick="fetchPaymentEntries()" class="btn btn-primary btn-sm">Refresh Data</button>
            </div>
            <div class="card-body">
                <div id="apiResponse" class="mb-3">
                    <p>Click "Refresh Data" to test the API</p>
                </div>
                
                <div id="paymentEntriesList">
                    <!-- Payment entries will be loaded here -->
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
        function fetchPaymentEntries() {
            document.getElementById('apiResponse').innerHTML = '<div class="spinner-border spinner-border-sm"></div> Loading...';
            document.getElementById('paymentEntriesList').innerHTML = '';
            
            fetch('api/get_recent_payment_entries.php')
                .then(response => response.json())
                .then(data => {
                    // Show API response
                    document.getElementById('apiResponse').innerHTML = `
                        <h6>API Response:</h6>
                        <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(data, null, 2)}</pre>
                    `;
                    
                    // Display payment entries if successful
                    if (data.status === 'success' && data.payment_entries.length > 0) {
                        let entriesHtml = '<h6>Payment Entries:</h6><div class="row">';
                        
                        data.payment_entries.forEach(entry => {
                            entriesHtml += `
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Payment #${entry.payment_id}</h6>
                                            <p class="card-text">
                                                <strong>Amount:</strong> ${entry.formatted_payment_amount}<br>
                                                <strong>Project:</strong> ${entry.display_project_type}<br>
                                                <strong>Date:</strong> ${entry.formatted_payment_date}<br>
                                                <strong>Mode:</strong> ${entry.display_payment_mode}<br>
                                                <strong>Recipients:</strong> ${entry.recipient_count}<br>
                                                <strong>Created:</strong> ${entry.time_since_created}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        entriesHtml += '</div>';
                        document.getElementById('paymentEntriesList').innerHTML = entriesHtml;
                    } else if (data.status === 'success') {
                        document.getElementById('paymentEntriesList').innerHTML = '<p class="text-muted">No payment entries found.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('apiResponse').innerHTML = `<div class="text-danger">Error: ${error.message}</div>`;
                });
        }
        
        // Auto-load data on page load
        window.addEventListener('DOMContentLoaded', fetchPaymentEntries);
    </script>
</body>
</html>