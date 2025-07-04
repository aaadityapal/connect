<?php
// Start session for authentication
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to view this page.";
    exit();
}

// Get expense ID from query parameter
$expenseId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$expenseId) {
    echo "Please provide an expense ID using ?id=X in the URL.";
    exit();
}

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, "http://{$_SERVER['HTTP_HOST']}/hr/get_expense_details.php?id=" . $expenseId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
    exit();
}

// Close cURL session
curl_close($ch);

// Display the response
echo "<h1>Expense Details Test</h1>";
echo "<h2>Raw Response:</h2>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Decode and display structured data
$expenseData = json_decode($response, true);
echo "<h2>Decoded Data:</h2>";
echo "<pre>" . print_r($expenseData, true) . "</pre>";

// If there's an error in the response, display it prominently
if (isset($expenseData['error'])) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . htmlspecialchars($expenseData['error']);
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Expense Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Expense Details</h1>
        <p>Testing expense details for expense ID: <?php echo $expenseId; ?></p>
        
        <div class="row">
            <div class="col-md-6">
                <button id="testButton" class="btn btn-primary" data-id="<?php echo $expenseId; ?>">
                    Test View Expense Details
                </button>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div id="result" class="alert alert-info">
                    Click the button to test the expense details modal.
                </div>
            </div>
        </div>
    </div>
    
    <div id="modalContainer"></div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#testButton').on('click', function() {
                const expenseId = $(this).data('id');
                $('#result').html('Loading expense details for ID: ' + expenseId);
                
                $.ajax({
                    url: 'get_expense_details.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { id: expenseId },
                    success: function(response) {
                        console.log('Response:', response);
                        
                        if (response.error) {
                            $('#result').html('<div class="alert alert-danger">' + response.error + '</div>');
                            return;
                        }
                        
                        // Show success message
                        $('#result').html('<div class="alert alert-success">Expense details loaded successfully!</div>');
                        
                        // Format date for display
                        const expenseDate = new Date(response.travel_date);
                        const formattedDate = expenseDate.toLocaleDateString();
                        
                        // Create status badge
                        let statusBadge = '';
                        switch(response.status) {
                            case 'approved':
                                statusBadge = '<span class="badge badge-success">Approved</span>';
                                break;
                            case 'rejected':
                                statusBadge = '<span class="badge badge-danger">Rejected</span>';
                                break;
                            case 'pending':
                            default:
                                statusBadge = '<span class="badge badge-warning">Pending</span>';
                                break;
                        }
                        
                        // Create bill attachment link if available
                        let billAttachment = 'No bill attached';
                        if (response.bill_file_path) {
                            const fileName = response.bill_file_path.split('/').pop();
                            billAttachment = `<a href="${response.bill_file_path}" target="_blank">${fileName}</a>`;
                        }
                        
                        // Create modal HTML
                        const modalHTML = `
                            <div class="modal fade" id="expenseDetailsModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="expenseDetailsModalLabel">Expense Details</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <p><strong>ID:</strong> ${response.id}</p>
                                                    <p><strong>Purpose:</strong> ${response.purpose}</p>
                                                    <p><strong>Date:</strong> ${formattedDate}</p>
                                                    <p><strong>From:</strong> ${response.from_location}</p>
                                                    <p><strong>To:</strong> ${response.to_location}</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Mode:</strong> ${response.mode_of_transport}</p>
                                                    <p><strong>Distance:</strong> ${response.distance} km</p>
                                                    <p><strong>Amount:</strong> ₹${parseFloat(response.amount).toFixed(2)}</p>
                                                    <p><strong>Status:</strong> ${statusBadge}</p>
                                                    <p><strong>Bill:</strong> ${billAttachment}</p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <p><strong>Notes:</strong></p>
                                                    <p>${response.notes || 'No notes provided'}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Remove any existing modal
                        $('#expenseDetailsModal').remove();
                        
                        // Add modal to body
                        $('#modalContainer').html(modalHTML);
                        
                        // Show modal
                        $('#expenseDetailsModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.status, xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        $('#result').html('<div class="alert alert-danger">Error: ' + xhr.statusText + '</div>');
                    }
                });
            });
        });
    </script>
</body>
</html> 