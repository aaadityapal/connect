<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Project Titles in Payment Entries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Test: Project Titles in Payment Entries</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="testPaymentEntries()">Load Payment Entries</button>
                        <hr>
                        <div id="results"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function testPaymentEntries() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading payment entries...</p></div>';
            
            fetch('./api/get_recent_payment_entries.php')
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    
                    if (data.status === 'success') {
                        let html = '<h6 class="mb-3">Payment Entries with Project Titles:</h6>';
                        
                        if (data.payment_entries.length === 0) {
                            html += '<p class="text-muted">No payment entries found.</p>';
                        } else {
                            data.payment_entries.forEach((entry, index) => {
                                html += `
                                    <div class="border rounded p-3 mb-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="text-primary">
                                                    ${escapeHtml(entry.display_project_title || 'Payment #' + entry.payment_id)}
                                                </h6>
                                                <p class="mb-1">
                                                    <strong>Summary:</strong> ${escapeHtml(entry.payment_summary)}
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Amount:</strong> ${entry.formatted_payment_amount}
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Date:</strong> ${entry.formatted_payment_date}
                                                </p>
                                                <small class="text-muted">Added ${entry.time_since_created}</small>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <small class="text-muted">
                                                    Payment ID: ${entry.payment_id}<br>
                                                    Project ID: ${entry.project_id}<br>
                                                    ${entry.project_title ? 'Project Title: ' + entry.project_title : 'No project title'}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        
                        resultsDiv.innerHTML = html;
                    } else {
                        resultsDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = `<div class="alert alert-danger">Network error: ${error.message}</div>`;
                });
        }
    </script>
</body>
</html>