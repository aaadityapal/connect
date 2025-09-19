<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Document Preservation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Document Preservation During Edit</h2>
        <p class="text-info">This test verifies that when uploading a new document, other existing documents are preserved.</p>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Current Labour Documents</h5>
                        <button onclick="fetchCurrentData()" class="btn btn-sm btn-outline-primary">Refresh Data</button>
                    </div>
                    <div class="card-body">
                        <div id="currentData">
                            <p>Click "Refresh Data" to load current document information</p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Test Update - Upload Only Aadhar</h5>
                    </div>
                    <div class="card-body">
                        <form id="testForm" enctype="multipart/form-data">
                            <input type="hidden" name="labour_id" value="1">
                            <input type="hidden" name="full_name" value="Test Labour">
                            <input type="hidden" name="position" value="worker">
                            <input type="hidden" name="labour_type" value="permanent_labour">
                            <input type="hidden" name="phone_number" value="1234567890">
                            <input type="hidden" name="join_date" value="2024-01-01">
                            
                            <div class="mb-3">
                                <label>Upload New Aadhar File (this should NOT affect other documents):</label>
                                <input type="file" name="aadhar_file" class="form-control" accept="image/*,.pdf">
                            </div>
                            
                            <button type="button" onclick="testUpdate()" class="btn btn-warning">
                                Test Update (Upload Aadhar Only)
                            </button>
                        </form>
                        
                        <div class="mt-3">
                            <h6>Test Response:</h6>
                            <pre id="response" class="bg-light p-2" style="max-height: 200px; overflow-y: auto;">
                                Select a file and click "Test Update"
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>Expected Behavior</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <h6>✅ Should Happen:</h6>
                            <ul class="mb-0">
                                <li>New Aadhar file uploaded</li>
                                <li>PAN document preserved</li>
                                <li>Voter ID preserved</li>
                                <li>Other document preserved</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-danger">
                            <h6>❌ Should NOT Happen:</h6>
                            <ul class="mb-0">
                                <li>PAN document removed</li>
                                <li>Voter ID removed</li>
                                <li>Other document removed</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fetchCurrentData() {
            document.getElementById('currentData').innerHTML = '<div class="spinner-border spinner-border-sm"></div> Loading...';
            
            fetch('api/get_labour_details.php?id=1')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const labour = data.labour;
                        document.getElementById('currentData').innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Document Fields in Database:</h6>
                                    <ul>
                                        <li><strong>Aadhar:</strong> ${labour.aadhar_card || 'Empty'}</li>
                                        <li><strong>PAN:</strong> ${labour.pan_card || 'Empty'}</li>
                                        <li><strong>Voter:</strong> ${labour.voter_id || 'Empty'}</li>
                                        <li><strong>Other:</strong> ${labour.other_document || 'Empty'}</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>File Existence Status:</h6>
                                    <ul>
                                        <li><strong>Aadhar File:</strong> ${labour.aadhar_card_file_info?.exists ? '✅ Exists' : '❌ Missing'}</li>
                                        <li><strong>PAN File:</strong> ${labour.pan_card_file_info?.exists ? '✅ Exists' : '❌ Missing'}</li>
                                        <li><strong>Voter File:</strong> ${labour.voter_id_file_info?.exists ? '✅ Exists' : '❌ Missing'}</li>
                                        <li><strong>Other File:</strong> ${labour.other_document_file_info?.exists ? '✅ Exists' : '❌ Missing'}</li>
                                    </ul>
                                </div>
                            </div>
                        `;
                    } else {
                        document.getElementById('currentData').innerHTML = `<div class="text-danger">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('currentData').innerHTML = `<div class="text-danger">Network error: ${error.message}</div>`;
                });
        }
        
        function testUpdate() {
            const formData = new FormData(document.getElementById('testForm'));
            
            document.getElementById('response').textContent = 'Sending update request...';
            
            fetch('api/update_labour.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('response').textContent = JSON.stringify(data, null, 2);
                
                // Auto-refresh data after 1 second to see the result
                setTimeout(fetchCurrentData, 1000);
            })
            .catch(error => {
                document.getElementById('response').textContent = 'Error: ' + error.message;
            });
        }
        
        // Auto-load current data on page load
        window.addEventListener('DOMContentLoaded', fetchCurrentData);
    </script>
</body>
</html>