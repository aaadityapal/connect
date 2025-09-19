<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment Entry Document Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-images me-2"></i>Payment Entry Document Display Test</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Testing Document Display:</strong><br>
                            This page tests the document display in payment entry modals using the new organized file structure:
                            <code>uploads/payment_documents/payment_id/recipient_id/</code>
                        </div>
                        
                        <div class="mb-3">
                            <label for="paymentId" class="form-label">Payment Entry ID</label>
                            <input type="number" class="form-control" id="paymentId" placeholder="Enter payment entry ID" value="1">
                        </div>
                        <button class="btn btn-primary" onclick="testDocumentDisplay()">
                            <i class="fas fa-eye me-1"></i>
                            Test Document Display
                        </button>
                        <button class="btn btn-secondary ms-2" onclick="checkDocumentPaths()">
                            <i class="fas fa-search me-1"></i>
                            Check Document Paths
                        </button>
                        <hr>
                        <div id="results"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkDocumentPaths() {
            const paymentId = document.getElementById('paymentId').value;
            const resultsDiv = document.getElementById('results');
            
            if (!paymentId) {
                resultsDiv.innerHTML = '<div class="alert alert-warning">Please enter a payment entry ID.</div>';
                return;
            }
            
            resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Checking document paths...</p></div>';
            
            fetch(`./api/get_payment_entry_details.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    
                    let html = '<h6 class="mb-3">Document Path Analysis:</h6>';
                    
                    if (data.status === 'success') {
                        let totalDocuments = 0;
                        let documentPaths = [];
                        
                        data.recipients.forEach((recipient, index) => {
                            if (recipient.documents && recipient.documents.length > 0) {
                                html += `<div class="card mb-3">`;
                                html += `<div class="card-header"><strong>Recipient ${index + 1}: ${recipient.name}</strong></div>`;
                                html += `<div class="card-body">`;
                                
                                recipient.documents.forEach(doc => {
                                    totalDocuments++;
                                    documentPaths.push(doc.file_path);
                                    
                                    const fullPath = '../' + doc.file_path;
                                    html += `<div class="border p-2 mb-2">`;
                                    html += `<strong>File:</strong> ${doc.file_name}<br>`;
                                    html += `<strong>Stored Path:</strong> <code>${doc.file_path}</code><br>`;
                                    html += `<strong>Display Path:</strong> <code>${fullPath}</code><br>`;
                                    html += `<strong>File Type:</strong> ${doc.file_type}<br>`;
                                    html += `<strong>Size:</strong> ${doc.formatted_file_size}<br>`;
                                    
                                    // Test if file exists by trying to load it
                                    html += `<button class="btn btn-sm btn-outline-primary mt-2" onclick="testFilePath('${fullPath}', '${doc.file_name}')">`;
                                    html += `<i class="fas fa-check me-1"></i>Test File Access</button>`;
                                    
                                    if (doc.file_type.toLowerCase().includes('image')) {
                                        html += ` <button class="btn btn-sm btn-outline-info mt-2" onclick="showImagePreview('${fullPath}', '${doc.file_name}')">`;
                                        html += `<i class="fas fa-image me-1"></i>Preview Image</button>`;
                                    }
                                    html += `</div>`;
                                });
                                
                                html += `</div></div>`;
                            }
                        });
                        
                        if (totalDocuments === 0) {
                            html += '<div class="alert alert-info">No documents found for this payment entry.</div>';
                        } else {
                            html += `<div class="alert alert-success">Found ${totalDocuments} document(s) across ${data.recipients.length} recipient(s).</div>`;
                        }
                        
                    } else {
                        html += `<div class="alert alert-danger">Error: ${data.message}</div>`;
                    }
                    
                    resultsDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = `<div class="alert alert-danger">Network error: ${error.message}</div>`;
                });
        }
        
        function testFilePath(filePath, fileName) {
            // Create a temporary image element to test if file exists
            const testImg = document.createElement('img');
            testImg.onload = function() {
                alert(`✅ File accessible: ${fileName}`);
            };
            testImg.onerror = function() {
                alert(`❌ File NOT accessible: ${fileName}\nPath: ${filePath}`);
            };
            testImg.src = filePath;
        }
        
        function showImagePreview(filePath, fileName) {
            // Create a modal-like preview
            const preview = document.createElement('div');
            preview.style.cssText = `
                position: fixed; 
                top: 0; left: 0; 
                width: 100%; height: 100%; 
                background: rgba(0,0,0,0.8); 
                z-index: 9999; 
                display: flex; 
                align-items: center; 
                justify-content: center;
            `;
            
            const img = document.createElement('img');
            img.src = filePath;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px;';
            img.onerror = function() {
                preview.innerHTML = `
                    <div style="color: white; text-align: center;">
                        <h3>❌ Image not found</h3>
                        <p>File: ${fileName}</p>
                        <p>Path: ${filePath}</p>
                        <button onclick="this.parentElement.parentElement.remove()" style="padding: 10px 20px; margin-top: 20px;">Close</button>
                    </div>
                `;
            };
            
            preview.appendChild(img);
            preview.onclick = function(e) {
                if (e.target === preview) {
                    preview.remove();
                }
            };
            
            document.body.appendChild(preview);
        }
        
        function testDocumentDisplay() {
            const paymentId = document.getElementById('paymentId').value;
            
            if (!paymentId) {
                alert('Please enter a payment entry ID.');
                return;
            }
            
            // Open the actual payment entry modal
            window.open(`./analytics/executive_insights_dashboard.php`, '_blank');
            alert(`Open the dashboard and view payment entry #${paymentId} to test the document display.`);
        }
    </script>
</body>
</html>