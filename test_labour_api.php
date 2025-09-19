<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labour API Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Labour API Test</h2>
        <div class="row">
            <div class="col-md-6">
                <h4>API Response</h4>
                <button class="btn btn-primary" onclick="testAPI()">Test get_recent_labours.php</button>
                <pre id="apiResponse" class="mt-3 p-3 bg-light" style="white-space: pre-wrap;"></pre>
            </div>
            <div class="col-md-6">
                <h4>Formatted Display</h4>
                <div id="formattedDisplay" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
        function testAPI() {
            document.getElementById('apiResponse').textContent = 'Loading...';
            document.getElementById('formattedDisplay').innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
            
            fetch('api/get_recent_labours.php')
                .then(response => response.json())
                .then(data => {
                    // Show raw API response
                    document.getElementById('apiResponse').textContent = JSON.stringify(data, null, 2);
                    
                    // Show formatted display
                    if (data.status === 'success') {
                        let html = '<div class="alert alert-success">✅ API Working! Found ' + data.count + ' labours</div>';
                        
                        if (data.labours.length > 0) {
                            html += '<div class="list-group">';
                            data.labours.forEach(labour => {
                                html += `
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">${labour.full_name}</h6>
                                            <small>${labour.time_since_created}</small>
                                        </div>
                                        <p class="mb-1">${labour.display_position} • ${labour.display_labour_type}</p>
                                        <small>Salary: ${labour.formatted_salary} | Phone: ${labour.phone_number}</small>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        } else {
                            html += '<div class="alert alert-info">No labours found in database</div>';
                        }
                        
                        document.getElementById('formattedDisplay').innerHTML = html;
                    } else {
                        document.getElementById('formattedDisplay').innerHTML = '<div class="alert alert-danger">❌ API Error: ' + (data.message || 'Unknown error') + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('apiResponse').textContent = 'Error: ' + error.message;
                    document.getElementById('formattedDisplay').innerHTML = '<div class="alert alert-danger">❌ Network Error: ' + error.message + '</div>';
                });
        }
    </script>
</body>
</html>