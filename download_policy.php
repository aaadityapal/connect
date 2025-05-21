<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get the document ID
$document_id = isset($_GET['id']) ? $_GET['id'] : null;

// If it's a sample document, serve a sample PDF
if ($document_id === 'sample') {
    serve_sample_document();
    exit();
}

// Check if document ID is valid
if (!$document_id || !is_numeric($document_id)) {
    error_response("Invalid document ID");
}

try {
    // Get document from database
    $stmt = $pdo->prepare("SELECT * FROM policy_documents WHERE id = ?");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if document exists
    if (!$document) {
        error_response("Document not found");
    }
    
    // Check if document is approved or user has permission to download
    if ($document['status'] !== 'approved' && $_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Site Supervisor') {
        error_response("You don't have permission to download this document");
    }
    
    // Construct file path
    $file_path = 'uploads/documents/policy/' . $document['stored_filename'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        // If original file doesn't exist, serve a sample document with the correct name
        serve_sample_document($document['policy_name']);
        exit();
    }
    
    // Determine MIME type
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];
    
    $mime_type = isset($mime_types[$file_extension]) ? $mime_types[$file_extension] : 'application/octet-stream';
    
    // Set appropriate headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $document['policy_name'] . '.' . $file_extension . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read the file and output it to the browser
    readfile($file_path);
    exit();
    
} catch (PDOException $e) {
    error_response("Database error: " . $e->getMessage());
}

// Function to serve a sample document
function serve_sample_document($document_name = "Sample Policy Document") {
    // Create a sample directory if it doesn't exist
    $sample_dir = 'sample_documents';
    if (!file_exists($sample_dir)) {
        mkdir($sample_dir, 0777, true);
    }
    
    // Path to the sample PDF
    $sample_path = $sample_dir . '/policy_sample.pdf';
    
    // If sample doesn't exist, create a basic PDF
    if (!file_exists($sample_path)) {
        create_sample_pdf($sample_path);
    }
    
    // Set appropriate headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $document_name . '.pdf"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($sample_path));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read the file and output it to the browser
    readfile($sample_path);
    exit();
}

// Function to create a basic sample PDF
function create_sample_pdf($output_path) {
    // Check if FPDF is available
    if (file_exists('vendor/fpdf/fpdf.php')) {
        require('vendor/fpdf/fpdf.php');
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Sample Policy Document');
        $pdf->Ln(15);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, 'This is a sample policy document provided for demonstration purposes only. In a real system, this would be an actual document from the database.');
        $pdf->Ln(10);
        $pdf->MultiCell(0, 10, 'Please contact your administrator to access the actual policy documents.');
        $pdf->Output('F', $output_path);
    } else {
        // If FPDF is not available, create a text file with sample content
        $sample_content = "SAMPLE POLICY DOCUMENT\n\n";
        $sample_content .= "This is a sample policy document provided for demonstration purposes only. ";
        $sample_content .= "In a real system, this would be an actual document from the database.\n\n";
        $sample_content .= "Please contact your administrator to access the actual policy documents.";
        
        file_put_contents($output_path, $sample_content);
    }
}

// Function to display error message
function error_response($message) {
    header('HTTP/1.1 404 Not Found');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Document Download Error</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .error-container {
                max-width: 600px;
                margin: 100px auto;
                background-color: white;
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                text-align: center;
            }
            h1 {
                color: #d9534f;
                font-size: 24px;
                margin-bottom: 20px;
            }
            p {
                color: #333;
                font-size: 16px;
                line-height: 1.5;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                transition: background-color 0.3s;
            }
            .btn:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Document Download Error</h1>
            <p>' . htmlspecialchars($message) . '</p>
            <a href="site_supervisor_profile.php" class="btn">Return to Profile</a>
        </div>
    </body>
    </html>';
    exit();
}
?>