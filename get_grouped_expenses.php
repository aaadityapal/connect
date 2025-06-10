<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-4">Authentication required</div>';
    exit;
}

// Check if user has the appropriate role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo '<div class="alert alert-danger m-4">Unauthorized access</div>';
    exit;
}

// Include database connection
include_once('includes/db_connect.php');

// Get expense IDs from request
if (!isset($_GET['ids'])) {
    echo '<div class="alert alert-danger m-4">No expense IDs provided</div>';
    exit;
}

// Parse expense IDs
try {
    $expenseIds = json_decode($_GET['ids'], true);
    if (!is_array($expenseIds) || empty($expenseIds)) {
        throw new Exception('Invalid expense IDs format');
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger m-4">Invalid expense IDs format</div>';
    exit;
}

// Prepare placeholders for SQL query
$placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
$types = str_repeat('i', count($expenseIds));

// Query to get expense details
$query = "SELECT e.id, e.user_id, e.purpose, e.mode_of_transport, e.from_location, e.to_location, 
                 e.distance, e.amount, e.travel_date, e.created_at, e.status, e.notes, 
                 e.manager_status, e.accountant_status, e.hr_status,
                 u.username, u.profile_picture, u.employee_id
          FROM travel_expenses e
          LEFT JOIN users u ON e.user_id = u.id
          WHERE e.id IN ($placeholders)
          ORDER BY e.travel_date DESC, e.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="alert alert-danger m-4">Database error: ' . $conn->error . '</div>';
    exit;
}

// Bind the expense IDs
$stmt->bind_param($types, ...$expenseIds);
$stmt->execute();
$result = $stmt->get_result();

// Check if we have results
if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning m-4">No expense reports found</div>';
    exit;
}

// Function to get appropriate transport icon
function getTransportIcon($mode) {
    switch (strtolower($mode)) {
        case 'car': return 'fa-car';
        case 'bike': case 'motorcycle': return 'fa-motorcycle';
        case 'bus': return 'fa-bus';
        case 'train': return 'fa-train';
        case 'flight': case 'airplane': case 'plane': return 'fa-plane';
        case 'taxi': case 'cab': return 'fa-taxi';
        case 'rickshaw': case 'auto': case 'auto rickshaw': return 'fa-shuttle-van';
        case 'subway': case 'metro': return 'fa-subway';
        case 'bicycle': return 'fa-bicycle';
        case 'ferry': case 'boat': return 'fa-ship';
        case 'walk': case 'walking': return 'fa-walking';
        case 'shared': case 'carpool': return 'fa-users';
        default: return 'fa-route';
    }
}

// Fetch expenses
$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

// Get first expense for basic information
$firstExpense = $expenses[0];
$employeeName = $firstExpense['username'];
$employeeId = !empty($firstExpense['employee_id']) ? $firstExpense['employee_id'] : 'EMP-'.rand(1000,9999);
$travelDate = new DateTime($firstExpense['travel_date']);
$formattedDate = $travelDate->format('M d, Y');

// Calculate total amount and distance
$totalAmount = 0;
$totalDistance = 0;
foreach ($expenses as $expense) {
    $totalAmount += $expense['amount'];
    // Add distance to total (if it exists and is numeric)
    if (isset($expense['distance']) && is_numeric($expense['distance'])) {
        $totalDistance += $expense['distance'];
    }
}

// Prepare profile picture URL
$profilePic = "assets/images/no-image.png"; // Default image
if (!empty($firstExpense['profile_picture'])) {
    $picture = $firstExpense['profile_picture'];
    if (filter_var($picture, FILTER_VALIDATE_URL)) {
        $profilePic = $picture;
    } else if (strpos($picture, 'http://') === 0 || strpos($picture, 'https://') === 0) {
        $profilePic = $picture;
    } else if (strpos($picture, 'uploads/profile_pictures/') === 0) {
        $profilePic = $picture;
    } else {
        $temp_path = "uploads/profile_pictures/" . $picture;
        if (file_exists($temp_path)) {
            $profilePic = $temp_path;
        }
    }
}
?>

<div class="grouped-expenses-container">
    <!-- Employee info header -->
    <div class="grouped-expenses-header">
        <div class="employee-summary">
            <div class="employee-info">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                     alt="Employee" 
                     class="employee-avatar"
                     onerror="this.src='assets/images/no-image.png'">
                <div>
                    <div class="employee-name"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="employee-id"><?php echo htmlspecialchars($employeeId); ?></div>
                </div>
            </div>
            <div class="expense-summary">
                <div class="expense-date">
                    <i class="fas fa-calendar-alt"></i> <?php echo $formattedDate; ?>
                </div>
                <div class="expense-total">
                    <i class="fas fa-money-bill-wave"></i> Total: ₹<?php echo number_format($totalAmount, 2); ?>
                </div>
                <div class="expense-distance">
                    <i class="fas fa-route"></i> Distance: <?php echo number_format($totalDistance, 1); ?> km
                </div>
                <div class="expense-count">
                    <i class="fas fa-receipt"></i> <?php echo count($expenses); ?> Expenses
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expenses table -->
    <div class="grouped-expenses-table-container">
        <table class="grouped-expenses-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Purpose</th>
                    <th>Mode</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Distance</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Manager Status</th>
                    <th>Accountant Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $index => $expense): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($expense['purpose']); ?></td>
                    <td>
                        <div class="transport-mode">
                            <i class="fas <?php echo getTransportIcon($expense['mode_of_transport']); ?>"></i>
                            <span><?php echo htmlspecialchars($expense['mode_of_transport'] ?? 'N/A'); ?></span>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($expense['from_location'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($expense['to_location'] ?? 'N/A'); ?></td>
                    <td><?php echo $expense['distance'] ? $expense['distance'] . ' km' : 'N/A'; ?></td>
                    <td class="expense-amount">₹<?php echo number_format($expense['amount'], 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $expense['status']; ?>">
                            <?php echo ucfirst($expense['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $managerStatus = isset($expense['manager_status']) ? $expense['manager_status'] : 'pending';
                        ?>
                        <span class="status-badge status-<?php echo $managerStatus; ?>">
                            <?php echo ucfirst($managerStatus); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $accountantStatus = isset($expense['accountant_status']) ? $expense['accountant_status'] : 'pending';
                        ?>
                        <span class="status-badge status-<?php echo $accountantStatus; ?>">
                            <?php echo ucfirst($accountantStatus); ?>
                        </span>
                    </td>
                    <td class="expense-actions">
                        <button type="button" class="btn btn-sm btn-action view-single-expense" data-id="<?php echo $expense['id']; ?>" title="View Details">
                            <i class="fas fa-eye text-primary"></i>
                        </button>
                        <?php if ($expense['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-sm btn-action approve-single-expense" data-id="<?php echo $expense['id']; ?>" title="Approve">
                                <i class="fas fa-check-circle text-success"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-action reject-single-expense" data-id="<?php echo $expense['id']; ?>" title="Reject">
                                <i class="fas fa-times-circle text-danger"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-action" disabled title="Already processed">
                                <i class="fas fa-ban text-muted"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                    <td><strong><?php echo number_format($totalDistance, 1); ?> km</strong></td>
                    <td class="expense-amount"><strong>₹<?php echo number_format($totalAmount, 2); ?></strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <!-- Notes section if any expenses have notes -->
    <?php
    $hasNotes = false;
    foreach ($expenses as $expense) {
        if (!empty($expense['notes'])) {
            $hasNotes = true;
            break;
        }
    }
    
    if ($hasNotes):
    ?>
    <div class="grouped-expenses-notes">
        <h6><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
        <div class="notes-list">
            <?php foreach ($expenses as $index => $expense): ?>
                <?php if (!empty($expense['notes'])): ?>
                <div class="note-item">
                    <div class="note-header">
                        <span class="note-title">Expense #<?php echo $index + 1; ?>: <?php echo htmlspecialchars($expense['purpose']); ?></span>
                    </div>
                    <div class="note-content">
                        <?php echo nl2br(htmlspecialchars($expense['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .grouped-expenses-container {
        padding: 0;
        max-width: 100%;
    }
    
    .grouped-expenses-header {
        background-color: #f8f9fa;
        padding: 20px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .employee-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .employee-info {
        display: flex;
        align-items: center;
    }
    
    .employee-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 3px solid white;
    }
    
    .employee-name {
        font-weight: 600;
        font-size: 1.2rem;
        color: #333;
        margin-bottom: 4px;
    }
    
    .employee-id {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .expense-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .expense-date, .expense-total, .expense-distance, .expense-count {
        padding: 8px 15px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-size: 1rem;
        font-weight: 500;
    }
    
    .expense-date i, .expense-total i, .expense-distance i, .expense-count i {
        margin-right: 8px;
        color: #007bff;
    }
    
    .grouped-expenses-table-container {
        padding: 20px;
        overflow-x: auto;
    }
    
    .grouped-expenses-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .grouped-expenses-table th, .grouped-expenses-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .grouped-expenses-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        text-align: left;
        white-space: nowrap;
        border-bottom: 2px solid #dee2e6;
    }
    
    .grouped-expenses-table tr:last-child td {
        border-bottom: none;
    }
    
    .grouped-expenses-table tr:hover {
        background-color: #f8f9fa;
    }
    
    .transport-mode {
        display: flex;
        align-items: center;
    }
    
    .transport-mode i {
        margin-right: 8px;
        color: #4a6cf7;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }
    
    .expense-amount {
        font-weight: 600;
        color: #28a745;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-align: center;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        min-width: 90px;
    }
    
    .status-pending {
        background-color: #fff8dd;
        color: #ffc107;
        border: 1px solid #ffe69c;
    }
    
    .status-approved {
        background-color: #e6f7ef;
        color: #28a745;
        border: 1px solid #c3e6cb;
    }
    
    .status-rejected {
        background-color: #fbe7e9;
        color: #dc3545;
        border: 1px solid #f5c6cb;
    }
    
    .expense-actions {
        white-space: nowrap;
        text-align: center;
    }
    
    .btn-action {
        padding: 0.25rem 0.5rem;
        border: none;
        background: transparent;
        transition: all 0.2s;
    }
    
    .btn-action:hover:not([disabled]) {
        transform: scale(1.2);
    }
    
    .btn-action i {
        font-size: 1.2rem;
    }
    
    .approve-single-expense:hover i {
        color: #28a745 !important;
        text-shadow: 0 0 5px rgba(40, 167, 69, 0.3);
    }
    
    .reject-single-expense:hover i {
        color: #dc3545 !important;
        text-shadow: 0 0 5px rgba(220, 53, 69, 0.3);
    }
    
    .view-single-expense:hover i {
        color: #007bff !important;
        text-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
    }
    
    .grouped-expenses-notes {
        padding: 20px;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    
    .grouped-expenses-notes h6 {
        margin-bottom: 15px;
        color: #495057;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .notes-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .note-item {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .note-header {
        padding: 10px 15px;
        background-color: #e9ecef;
        font-weight: 500;
        font-size: 0.95rem;
    }
    
    .note-content {
        padding: 15px;
        font-size: 0.95rem;
        color: #555;
    }
    
    /* Mobile responsive styles */
    @media (max-width: 992px) {
        .employee-summary {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }
        
        .expense-summary {
            width: 100%;
            justify-content: space-between;
        }
    }
    
    @media (max-width: 768px) {
        .grouped-expenses-table th, .grouped-expenses-table td {
            padding: 10px 8px;
            font-size: 0.9rem;
        }
        
        .employee-avatar {
            width: 50px;
            height: 50px;
            margin-right: 15px;
        }
        
        .employee-name {
            font-size: 1.1rem;
        }
    }
</style> 