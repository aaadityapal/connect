<?php
// Start session and include necessary files
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Initialize variables
$labours = [];
$search = '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Handle search
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    error_log("Search term received: '" . $search . "'");
}

try {
    // Build query based on search
    if (!empty($search)) {
        error_log("Executing search query for term: '" . $search . "'");
        $sql = "SELECT 
                    labour_id,
                    full_name,
                    position,
                    position_custom,
                    phone_number,
                    alternative_number,
                    join_date,
                    labour_type,
                    daily_salary,
                    aadhar_card,
                    pan_card,
                    voter_id,
                    other_document,
                    address,
                    city,
                    state,
                    notes,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                FROM hr_labours 
                WHERE full_name LIKE :search 
                   OR phone_number LIKE :search 
                   OR labour_type LIKE :search
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Search returned " . count($labours) . " results");
        
        // Debug: Log all results
        foreach ($labours as $index => $labour) {
            error_log("Result $index: ID=" . $labour['labour_id'] . ", Name=" . $labour['full_name']);
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM hr_labours 
                     WHERE full_name LIKE :search 
                        OR phone_number LIKE :search 
                        OR labour_type LIKE :search";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $countStmt->execute();
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Total records matching search: " . $totalRecords);
    } else {
        error_log("No search term, fetching all labours");
        // Fetch all labours
        $sql = "SELECT 
                    labour_id,
                    full_name,
                    position,
                    position_custom,
                    phone_number,
                    alternative_number,
                    join_date,
                    labour_type,
                    daily_salary,
                    aadhar_card,
                    pan_card,
                    voter_id,
                    other_document,
                    address,
                    city,
                    state,
                    notes,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                FROM hr_labours 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM hr_labours";
        $countStmt = $pdo->query($countSql);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    $totalPages = ceil($totalRecords / $limit);
    error_log("Total pages: " . $totalPages . ", Total records: " . $totalRecords);
    
    // Debug: Log search variable status
    error_log("Search variable status - Empty: " . (empty($search) ? 'true' : 'false') . ", Value: '" . $search . "'");
} catch (Exception $e) {
    error_log("Error fetching labours: " . $e->getMessage());
    $error = "Failed to fetch labour data. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labour Information - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c7be5;
            --primary-dark: #1a68d1;
            --secondary: #6e84a3;
            --success: #00d97e;
            --danger: #e63757;
            --warning: #f6c343;
            --info: #39afd1;
            --light: #f5f7fb;
            --dark: #12263f;
            --gray-100: #f9fbfd;
            --gray-200: #edf2f9;
            --gray-300: #e3ebf6;
            --gray-400: #d2dbe7;
            --gray-500: #b1c2d9;
            --gray-600: #99a9be;
            --gray-700: #6e84a3;
            --gray-800: #3b506c;
            --gray-900: #12263f;
            --border: #e3ebf6;
            --card-shadow: 0 0.75rem 1.5rem rgba(18, 38, 63, 0.03);
            --modal-shadow: 0 0.5rem 1rem rgba(18, 38, 63, 0.1);
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--gray-800);
            line-height: 1.5;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--dark);
        }

        .main-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }

        .search-container {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .search-box {
            position: relative;
            max-width: 500px;
        }

        .search-box input {
            padding: 12px 20px 12px 45px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            width: 100%;
            transition: var(--transition);
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.15);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
            font-size: 16px;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
        }

        .table th {
            background-color: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: rgba(44, 123, 229, 0.03);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-primary {
            background-color: rgba(44, 123, 229, 0.1);
            color: var(--primary);
        }

        .badge-success {
            background-color: rgba(0, 217, 126, 0.1);
            color: var(--success);
        }

        .view-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--primary);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .view-btn:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
        }

        .pagination .page-link {
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--gray-700);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .pagination .page-link:hover {
            background-color: var(--gray-100);
            color: var(--primary);
            border-color: var(--primary);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .back-btn {
            background: white;
            border: 1px solid var(--border);
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .salary {
            font-weight: 600;
            color: var(--success);
        }

        .text-muted {
            color: var(--gray-600) !important;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--modal-shadow);
        }

        .modal-header {
            background: var(--gray-100);
            border-bottom: 1px solid var(--border);
            padding: 18px 25px;
        }

        .modal-title {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 25px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--gray-700);
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            flex: 1;
            color: var(--dark);
        }

        .document-card {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .document-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .doc-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            color: var(--primary);
        }

        .doc-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 6px;
            margin-top: 10px;
            display: none;
        }

        .no-document {
            color: var(--gray-600);
            font-style: italic;
        }

        .modal-footer {
            background: var(--gray-100);
            border-top: 1px solid var(--border);
            padding: 15px 25px;
        }

        .btn-secondary {
            background: var(--gray-300);
            border: 1px solid var(--border);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-400);
        }

        .btn-primary {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .main-card {
                padding: 20px 15px;
            }
            
            .search-container {
                padding: 15px;
            }
            
            .table th, .table td {
                padding: 12px 10px;
                font-size: 0.85rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <h1 class="page-title">
                <i class="fas fa-hard-hat"></i>
                Labour Information
            </h1>
            <a href="analytics/executive_insights_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <div class="main-card">
        <div class="search-container">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-auto">
                    <div class="search-box position-relative">
                        <i class="fas fa-search position-absolute" style="left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray-600);"></i>
                        <input type="text" name="search" class="form-control ps-4" placeholder="Search by name, phone, or type..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 40px !important;">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Position</th>
                            <th>Phone</th>
                            <th>Labour Type</th>
                            <th>Join Date</th>
                            <th>Daily Salary</th>
                            <th>City</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labours)): ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>No labours found</h3>
                                    <p>Try adjusting your search criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($labours as $labour): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($labour['labour_id']); ?></td>
                                    <td><?php echo htmlspecialchars($labour['full_name']); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php 
                                            $position = !empty($labour['position_custom']) ? $labour['position_custom'] : $labour['position'];
                                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $position))); 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($labour['phone_number']); ?></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $labour['labour_type']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($labour['join_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($labour['daily_salary']) && $labour['daily_salary'] > 0): ?>
                                            <span class="salary">₹<?php echo number_format($labour['daily_salary'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($labour['city'] ?: 'N/A'); ?></td>
                                    <td>
                                        <button class="view-btn" onclick="viewLabourDetails(<?php echo $labour['labour_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="page-link <?php echo $i == $page ? 'active' : ''; ?>" 
                           href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Labour Details Modal -->
    <div class="modal fade" id="labourDetailsModal" tabindex="-1" aria-labelledby="labourDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="labourDetailsModalLabel">
                        <i class="fas fa-user me-2"></i>
                        Labour Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="labourDetailsContent">
                        <!-- Labour details will be loaded here via AJAX -->
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading labour details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentPreviewModalLabel">
                        <i class="fas fa-file me-2"></i>
                        Document Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="documentPreviewContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading document...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="downloadDocumentBtn" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to view labour details
        function viewLabourDetails(labourId) {
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('labourDetailsModal'));
            modal.show();
            
            // Clear previous content
            document.getElementById('labourDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading labour details...</p>
                </div>
            `;
            
            // Fetch labour details
            fetch(`api/get_labour_details.php?id=${labourId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayLabourDetails(data.labour);
                    } else {
                        document.getElementById('labourDetailsContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Failed to load labour details: ${data.message || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('labourDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Network error. Please try again later.
                        </div>
                    `;
                });
        }
        
        // Function to display labour details in the modal
        function displayLabourDetails(labour) {
            const content = `
                <div class="detail-section">
                    <h5><i class="fas fa-user-circle"></i> Personal Information</h5>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-signature"></i> Full Name</div>
                        <div class="detail-value">${escapeHtml(labour.full_name)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-briefcase"></i> Position</div>
                        <div class="detail-value">${escapeHtml(labour.display_position)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-user-tag"></i> Labour Type</div>
                        <div class="detail-value">${escapeHtml(labour.display_labour_type)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-phone"></i> Phone Number</div>
                        <div class="detail-value">${escapeHtml(labour.phone_number)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-phone-alt"></i> Alternative Number</div>
                        <div class="detail-value">${escapeHtml(labour.alternative_number || 'Not provided')}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-building"></i> Employment Information</h5>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-id-badge"></i> Labour ID</div>
                        <div class="detail-value">${escapeHtml(labour.labour_id)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-calendar-plus"></i> Join Date</div>
                        <div class="detail-value">${escapeHtml(labour.formatted_join_date)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-money-bill-wave"></i> Daily Salary</div>
                        <div class="detail-value">${escapeHtml(labour.formatted_salary)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-clock"></i> Experience</div>
                        <div class="detail-value">${escapeHtml(labour.experience_text)}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-map-marker-alt"></i> Address Information</h5>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-home"></i> Address</div>
                        <div class="detail-value">${escapeHtml(labour.address || 'Not provided')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-city"></i> City</div>
                        <div class="detail-value">${escapeHtml(labour.city || 'Not provided')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-flag"></i> State</div>
                        <div class="detail-value">${escapeHtml(labour.state || 'Not provided')}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-file-alt"></i> Documents</h5>
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-title">
                                <i class="fas fa-address-card"></i> Aadhar Card
                            </div>
                            <div class="document-actions">
                                ${labour.aadhar_card_file_info && labour.aadhar_card_file_info.exists ? 
                                    `<button class="doc-btn" onclick="viewDocument('${labour.aadhar_card_file_info.path}', '${labour.aadhar_card_file_info.filename}')">
                                        <i class="fas fa-eye"></i> View
                                    </button>` : 
                                    `<span class="no-document">No document uploaded</span>`
                                }
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-hashtag"></i> Document Number</div>
                            <div class="detail-value">${escapeHtml(labour.aadhar_card_masked)}</div>
                        </div>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-title">
                                <i class="fas fa-id-card"></i> PAN Card
                            </div>
                            <div class="document-actions">
                                ${labour.pan_card_file_info && labour.pan_card_file_info.exists ? 
                                    `<button class="doc-btn" onclick="viewDocument('${labour.pan_card_file_info.path}', '${labour.pan_card_file_info.filename}')">
                                        <i class="fas fa-eye"></i> View
                                    </button>` : 
                                    `<span class="no-document">No document uploaded</span>`
                                }
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-hashtag"></i> Document Number</div>
                            <div class="detail-value">${escapeHtml(labour.pan_card_masked || 'Not provided')}</div>
                        </div>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-title">
                                <i class="fas fa-vote-yea"></i> Voter ID
                            </div>
                            <div class="document-actions">
                                ${labour.voter_id_file_info && labour.voter_id_file_info.exists ? 
                                    `<button class="doc-btn" onclick="viewDocument('${labour.voter_id_file_info.path}', '${labour.voter_id_file_info.filename}')">
                                        <i class="fas fa-eye"></i> View
                                    </button>` : 
                                    `<span class="no-document">No document uploaded</span>`
                                }
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-hashtag"></i> Document Number</div>
                            <div class="detail-value">${escapeHtml(labour.voter_id_masked || 'Not provided')}</div>
                        </div>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-title">
                                <i class="fas fa-file"></i> Other Document
                            </div>
                            <div class="document-actions">
                                ${labour.other_document_file_info && labour.other_document_file_info.exists ? 
                                    `<button class="doc-btn" onclick="viewDocument('${labour.other_document_file_info.path}', '${labour.other_document_file_info.filename}')">
                                        <i class="fas fa-eye"></i> View
                                    </button>` : 
                                    `<span class="no-document">No document uploaded</span>`
                                }
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-sticky-note"></i> Document Name</div>
                            <div class="detail-value">${escapeHtml(labour.other_document || 'Not provided')}</div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-sticky-note"></i> Additional Notes</h5>
                    <div class="detail-value">
                        ${escapeHtml(labour.notes || 'No additional notes')}
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-info-circle"></i> System Information</h5>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-user-plus"></i> Created By</div>
                        <div class="detail-value">${escapeHtml(labour.created_by_user)} (${escapeHtml(labour.created_by_role)})</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-calendar-alt"></i> Created At</div>
                        <div class="detail-value">${escapeHtml(labour.created_at)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-user-edit"></i> Updated By</div>
                        <div class="detail-value">${escapeHtml(labour.updated_by_user)} (${escapeHtml(labour.updated_by_role)})</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-history"></i> Updated At</div>
                        <div class="detail-value">${escapeHtml(labour.updated_at)}</div>
                    </div>
                </div>
            `;
            
            document.getElementById('labourDetailsContent').innerHTML = content;
        }
        
        // Function to view document
        function viewDocument(filePath, fileName) {
            // Show the document preview modal
            const modal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));
            modal.show();
            
            // Set download button href
            document.getElementById('downloadDocumentBtn').href = filePath;
            
            // Update modal title with file name
            document.getElementById('documentPreviewModalLabel').innerHTML = `
                <i class="fas fa-file me-2"></i>
                Document Preview: ${escapeHtml(fileName)}
            `;
            
            // Clear previous content
            document.getElementById('documentPreviewContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading document...</p>
                </div>
            `;
            
            // Determine file type and display appropriately
            const fileExtension = fileName.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                document.getElementById('documentPreviewContent').innerHTML = `
                    <img src="${filePath}" class="img-fluid" alt="Document Image" style="max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                `;
            } else if (fileExtension === 'pdf') {
                // Display PDF embed
                document.getElementById('documentPreviewContent').innerHTML = `
                    <embed src="${filePath}" type="application/pdf" width="100%" height="600px" style="border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <p class="mt-3">If the PDF is not displaying, you can <a href="${filePath}" target="_blank">download it here</a>.</p>
                `;
            } else {
                // For other file types, provide download link
                document.getElementById('documentPreviewContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-file me-2"></i>
                        This file type cannot be previewed directly. 
                        <a href="${filePath}" target="_blank" class="alert-link">Click here to download ${escapeHtml(fileName)}</a>
                    </div>
                `;
            }
        }
        
        // Utility function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Initialize search functionality when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Get search input element
            const searchInput = document.querySelector('input[name="search"]');
            
            if (searchInput) {
                // Debounced input handling for real-time search
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    
                    // Only auto-search for non-empty terms
                    const searchTerm = this.value.trim();
                    if (searchTerm !== '') {
                        searchTimeout = setTimeout(() => {
                            // Submit the parent form
                            this.form.submit();
                        }, 500);
                    }
                });
                
                // Handle Enter key to submit immediately
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
            }
        });

    </script>
</body>
</html>