<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Test Payment Document Organization</title>
    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
    <link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\" rel=\"stylesheet\">
</head>
<body>
    <div class=\"container mt-4\">
        <div class=\"row\">
            <div class=\"col-md-10 mx-auto\">
                <div class=\"card\">
                    <div class=\"card-header\">
                        <h5 class=\"mb-0\"><i class=\"fas fa-folder-open me-2\"></i>Payment Document Organization Test</h5>
                    </div>
                    <div class=\"card-body\">
                        <div class=\"alert alert-info\">
                            <i class=\"fas fa-info-circle me-2\"></i>
                            <strong>New File Organization Structure:</strong><br>
                            Files are now organized as: <code>uploads/payment_documents/payment_id/recipient_id/</code>
                        </div>
                        
                        <h6 class=\"mb-3\"><i class=\"fas fa-cogs me-2\"></i>Test Helper Functions</h6>
                        
                        <?php
                        require_once './utils/payment_document_helpers.php';
                        
                        echo '<div class=\"mb-3\">';
                        echo '<h6>Function Tests:</h6>';
                        
                        // Test 1: Directory creation
                        echo '<div class=\"border p-3 mb-2\">';
                        echo '<strong>Test 1: Directory Creation</strong><br>';
                        $testPaymentId = 999;
                        $testRecipientId = 888;
                        $testDir = createPaymentDocumentDirectory($testPaymentId, $testRecipientId, false);
                        if (file_exists($testDir)) {
                            echo '<span class=\"text-success\"><i class=\"fas fa-check\"></i> Regular directory created: ' . $testDir . '</span><br>';
                        } else {
                            echo '<span class=\"text-danger\"><i class=\"fas fa-times\"></i> Failed to create directory</span><br>';
                        }
                        
                        $testSplitDir = createPaymentDocumentDirectory($testPaymentId, $testRecipientId, true);
                        if (file_exists($testSplitDir)) {
                            echo '<span class=\"text-success\"><i class=\"fas fa-check\"></i> Split directory created: ' . $testSplitDir . '</span>';
                        } else {
                            echo '<span class=\"text-danger\"><i class=\"fas fa-times\"></i> Failed to create split directory</span>';
                        }
                        echo '</div>';
                        
                        // Test 2: Filename generation
                        echo '<div class=\"border p-3 mb-2\">';
                        echo '<strong>Test 2: Filename Generation</strong><br>';
                        $testFilename1 = generateOrganizedFilename('invoice with spaces & special chars!.pdf', 123);
                        echo '<span class=\"text-info\">Original: \"invoice with spaces & special chars!.pdf\"</span><br>';
                        echo '<span class=\"text-success\">Generated: \"' . $testFilename1 . '\"</span><br>';
                        
                        $testFilename2 = generateOrganizedFilename('receipt.jpg', null, 'split_');
                        echo '<span class=\"text-info\">Original: \"receipt.jpg\" with split prefix</span><br>';
                        echo '<span class=\"text-success\">Generated: \"' . $testFilename2 . '\"</span>';
                        echo '</div>';
                        
                        // Test 3: Path generation
                        echo '<div class=\"border p-3 mb-2\">';
                        echo '<strong>Test 3: Relative Path Generation</strong><br>';
                        $relativePath1 = getPaymentDocumentRelativePath($testPaymentId, $testRecipientId, 'test_file.pdf', false);
                        echo '<span class=\"text-success\">Regular document path: ' . $relativePath1 . '</span><br>';
                        
                        $relativePath2 = getPaymentDocumentRelativePath($testPaymentId, $testRecipientId, 'split_proof.jpg', true);
                        echo '<span class=\"text-success\">Split document path: ' . $relativePath2 . '</span>';
                        echo '</div>';
                        
                        // Test 4: File validation
                        echo '<div class=\"border p-3 mb-2\">';
                        echo '<strong>Test 4: File Validation</strong><br>';
                        
                        // Test valid file
                        $validFile = [
                            'name' => 'test.pdf',
                            'type' => 'application/pdf',
                            'size' => 1024000, // 1MB
                            'error' => UPLOAD_ERR_OK
                        ];
                        $validation1 = validatePaymentDocumentFile($validFile);
                        if ($validation1 === true) {
                            echo '<span class=\"text-success\"><i class=\"fas fa-check\"></i> Valid PDF file passed validation</span><br>';
                        } else {
                            echo '<span class=\"text-danger\"><i class=\"fas fa-times\"></i> Valid file failed: ' . $validation1['error'] . '</span><br>';
                        }
                        
                        // Test invalid file
                        $invalidFile = [
                            'name' => 'test.exe',
                            'type' => 'application/octet-stream',
                            'size' => 1024000,
                            'error' => UPLOAD_ERR_OK
                        ];
                        $validation2 = validatePaymentDocumentFile($invalidFile);
                        if ($validation2 !== true) {
                            echo '<span class=\"text-success\"><i class=\"fas fa-check\"></i> Invalid file correctly rejected: ' . $validation2['error'] . '</span><br>';
                        } else {
                            echo '<span class=\"text-danger\"><i class=\"fas fa-times\"></i> Invalid file incorrectly accepted</span><br>';
                        }
                        
                        // Test oversized file
                        $oversizedFile = [
                            'name' => 'huge_file.pdf',
                            'type' => 'application/pdf',
                            'size' => 10485760, // 10MB
                            'error' => UPLOAD_ERR_OK
                        ];
                        $validation3 = validatePaymentDocumentFile($oversizedFile);
                        if ($validation3 !== true) {
                            echo '<span class=\"text-success\"><i class=\"fas fa-check\"></i> Oversized file correctly rejected: ' . $validation3['error'] . '</span>';
                        } else {
                            echo '<span class=\"text-danger\"><i class=\"fas fa-times\"></i> Oversized file incorrectly accepted</span>';
                        }
                        echo '</div>';
                        
                        echo '</div>';
                        ?>
                        
                        <div class=\"mt-4\">
                            <h6><i class=\"fas fa-migrate me-2\"></i>Migration Script</h6>
                            <p>To organize existing files, run the migration script:</p>
                            <a href=\"./utils/organize_payment_documents.php\" class=\"btn btn-warning\" target=\"_blank\">
                                <i class=\"fas fa-play me-2\"></i>Run Migration Script
                            </a>
                        </div>
                        
                        <div class=\"mt-4\">
                            <h6><i class=\"fas fa-upload me-2\"></i>Test Upload</h6>
                            <p>Create a new payment entry to test the organized file structure:</p>
                            <a href=\"./analytics/executive_insights_dashboard.php\" class=\"btn btn-primary\">
                                <i class=\"fas fa-plus me-2\"></i>Create Payment Entry
                            </a>
                        </div>
                        
                        <?php
                        // Clean up test directories if they're empty
                        if (file_exists('../uploads/payment_documents/payment_999/recipient_888/splits/') && count(scandir('../uploads/payment_documents/payment_999/recipient_888/splits/')) <= 2) {
                            rmdir('../uploads/payment_documents/payment_999/recipient_888/splits/');
                        }
                        if (file_exists('../uploads/payment_documents/payment_999/recipient_888/') && count(scandir('../uploads/payment_documents/payment_999/recipient_888/')) <= 2) {
                            rmdir('../uploads/payment_documents/payment_999/recipient_888/');
                        }
                        if (file_exists('../uploads/payment_documents/payment_999/') && count(scandir('../uploads/payment_documents/payment_999/')) <= 2) {
                            rmdir('../uploads/payment_documents/payment_999/');
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>