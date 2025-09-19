<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Edit Labour with File Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-test me-2"></i>Test Edit Labour with File Upload</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-info">This page tests the edit labour functionality with file upload capability.</p>
                        
                        <!-- Test Status -->
                        <div id="testStatus" class="alert alert-info">
                            <strong>Status:</strong> Ready to test
                        </div>
                        
                        <!-- Test Files -->
                        <div class="mb-4">
                            <h5>Test Files for Upload</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="testAadhar">Test Aadhar Card Image:</label>
                                        <input type="file" class="form-control" id="testAadhar" accept="image/*,.pdf">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="testPan">Test PAN Card Image:</label>
                                        <input type="file" class="form-control" id="testPan" accept="image/*,.pdf">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test Labour Data -->
                        <div class="mb-4">
                            <h5>Test Labour Data</h5>
                            <button class="btn btn-primary" onclick="fetchTestLabour()">
                                <i class="fas fa-download me-2"></i>Fetch Test Labour
                            </button>
                            <div id="testLabourData" class="mt-3" style="display: none;">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Sample Labour Found:</h6>
                                        <div id="labourInfo"></div>
                                        <button class="btn btn-success mt-2" onclick="openEditModal()" id="editBtn" style="display: none;">
                                            <i class="fas fa-edit me-2"></i>Test Edit with File Upload
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test Results -->
                        <div id="testResults" class="mt-4" style="display: none;">
                            <h5>Test Results</h5>
                            <div id="resultsContent"></div>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="mt-4">
                            <a href="analytics/executive_insights_dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let testLabourId = null;
        
        function updateStatus(message, type = 'info') {
            const statusEl = document.getElementById('testStatus');
            statusEl.className = `alert alert-${type}`;
            statusEl.innerHTML = `<strong>Status:</strong> ${message}`;
        }
        
        function fetchTestLabour() {
            updateStatus('Fetching test labour data...', 'info');
            
            fetch('api/get_recent_labours.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.labours.length > 0) {
                        const labour = data.labours[0]; // Get first labour
                        testLabourId = labour.labour_id;
                        
                        document.getElementById('labourInfo').innerHTML = `
                            <p><strong>ID:</strong> ${labour.labour_id}</p>
                            <p><strong>Name:</strong> ${labour.full_name}</p>
                            <p><strong>Position:</strong> ${labour.display_position}</p>
                            <p><strong>Type:</strong> ${labour.display_labour_type}</p>
                        `;
                        
                        document.getElementById('testLabourData').style.display = 'block';
                        document.getElementById('editBtn').style.display = 'inline-block';
                        updateStatus('Test labour data loaded successfully', 'success');
                    } else {
                        updateStatus('No labour data found', 'warning');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateStatus('Error fetching labour data', 'danger');
                });
        }
        
        function openEditModal() {
            updateStatus('Testing edit functionality...', 'info');
            
            // Simulate opening the edit modal by making a test API call
            if (testLabourId) {
                window.location.href = `analytics/executive_insights_dashboard.php#editLabour_${testLabourId}`;
            }
        }
        
        // Auto-load test data on page load
        window.addEventListener('DOMContentLoaded', function() {
            fetchTestLabour();
        });
    </script>
</body>
</html>