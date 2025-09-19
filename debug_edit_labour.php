<!DOCTYPE html>
<html>
<head>
    <title>Debug Edit Labour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Debug Edit Labour File Upload</h2>
        
        <div class="card">
            <div class="card-body">
                <form id="testForm" enctype="multipart/form-data">
                    <input type="hidden" name="labour_id" value="1">
                    <input type="text" name="full_name" value="Test Labour" class="form-control mb-2">
                    <input type="text" name="position" value="worker" class="form-control mb-2">
                    <input type="text" name="labour_type" value="permanent_labour" class="form-control mb-2">
                    <input type="text" name="phone_number" value="1234567890" class="form-control mb-2">
                    <input type="date" name="join_date" value="2024-01-01" class="form-control mb-2">
                    <input type="text" name="aadhar_card" value="1234-5678-9012" class="form-control mb-2">
                    <input type="file" name="aadhar_file" class="form-control mb-2">
                    <button type="button" onclick="testUpdate()" class="btn btn-primary">Test Update</button>
                </form>
                
                <div class="mt-4">
                    <h5>Response:</h5>
                    <pre id="response">Select file and click Test Update</pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        function testUpdate() {
            const formData = new FormData(document.getElementById('testForm'));
            
            fetch('api/update_labour.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('response').textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                document.getElementById('response').textContent = 'Error: ' + error.message;
            });
        }
    </script>
</body>
</html>