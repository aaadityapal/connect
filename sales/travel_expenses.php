<?php
session_start();
// Include database connection
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Fetch user details for the header
try {
    $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_details = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_name = $user_details ? $user_details['username'] : 'User';
    $user_role = $user_details ? $user_details['role'] : 'Employee';
} catch (Exception $e) {
    $user_name = 'User';
    $user_role = 'Employee';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Include the main logic functions usually found in std_travel_expenses.php
    // Since we are duplicating logic, we define helper functions at the bottom or inline here.
    // For simplicity and robustness, I will inline the logic or include the root file if possible, 
    // but including root file might trigger output (HTML) if not careful.
    // So I will duplicate the necessary functions here.

    try {
        switch ($_POST['action']) {
            case 'save_expenses':
                // This is likely handled by sales/save_travel_expenses.php via the Modal JS, 
                // but if the list view has a save function, we handle it here.
                if (isset($_POST['expenses']) && is_array($_POST['expenses'])) {
                    $result = saveExpenses($pdo, $user_id, $_POST['expenses']);
                    echo json_encode(['success' => $result]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid data']);
                }
                exit;

            case 'get_expenses':
                $expenses = getExpenses($pdo, $user_id);
                echo json_encode(['success' => true, 'expenses' => $expenses]);
                exit;

            case 'delete_expense':
                if (isset($_POST['expense_id'])) {
                    $result = deleteExpense($pdo, $user_id, $_POST['expense_id']);
                    echo json_encode(['success' => $result]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
                }
                exit;

            case 'resubmit_expense':
                if (isset($_POST['expense_id'])) {
                    $result = resubmitExpense($pdo, $user_id, $_POST['expense_id']);
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// --- PHP Helper Functions (Duplicated from std_travel_expenses.php) ---

function saveExpenses($pdo, $user_id, $expenses)
{
    try {
        $pdo->beginTransaction();
        foreach ($expenses as $expense) {
            if (isset($expense['id']) && $expense['id'] > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE travel_expenses SET travel_date = :td, purpose = :pur, mode_of_transport = :mod, from_location = :fl, to_location = :tl, distance = :dist, amount = :amt, status = :st, notes = :nt, updated_at = NOW() WHERE id = :id AND user_id = :uid");
                $stmt->execute([
                    ':td' => $expense['date'],
                    ':pur' => $expense['description'] ?? $expense['purpose'] ?? '',
                    ':mod' => $expense['category'] ?? $expense['mode'] ?? 'Car',
                    ':fl' => $expense['from'] ?? 'Office',
                    ':tl' => $expense['to'] ?? 'Destination',
                    ':dist' => $expense['distance'] ?? 0,
                    ':amt' => $expense['amount'],
                    ':st' => $expense['status'] ?? 'pending',
                    ':nt' => $expense['notes'] ?? '',
                    ':id' => $expense['id'],
                    ':uid' => $user_id
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO travel_expenses (user_id, travel_date, purpose, mode_of_transport, from_location, to_location, distance, amount, status, notes, manager_status, accountant_status, hr_status, resubmission_count, is_resubmitted, max_resubmissions, created_at, updated_at) VALUES (:uid, :td, :pur, :mod, :fl, :tl, :dist, :amt, :st, :nt, 'pending', 'pending', 'pending', 0, 0, 3, NOW(), NOW())");
                $stmt->execute([
                    ':uid' => $user_id,
                    ':td' => $expense['date'],
                    ':pur' => $expense['description'] ?? $expense['purpose'] ?? '',
                    ':mod' => $expense['category'] ?? $expense['mode'] ?? 'Car',
                    ':fl' => $expense['from'] ?? 'Office',
                    ':tl' => $expense['to'] ?? 'Destination',
                    ':dist' => $expense['distance'] ?? 0,
                    ':amt' => $expense['amount'],
                    ':st' => $expense['status'] ?? 'pending',
                    ':nt' => $expense['notes'] ?? ''
                ]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function getExpenses($pdo, $user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM travel_expenses WHERE user_id = :user_id ORDER BY travel_date DESC, created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function deleteExpense($pdo, $user_id, $expense_id)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM travel_expenses WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $expense_id, ':user_id' => $user_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function resubmitExpense($pdo, $user_id, $expense_id)
{
    // Simplified logic similar to original but condensed
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM travel_expenses WHERE id = :id AND user_id = :uid AND status = 'rejected'");
        $stmt->execute([':id' => $expense_id, ':uid' => $user_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense)
            throw new Exception('Expense not found or not rejected');

        // 15 day check
        $rejectionDate = new DateTime($expense['updated_at']);
        $currentDate = new DateTime();
        if ($currentDate->diff($rejectionDate)->days > 15)
            throw new Exception('Rejection older than 15 days');

        // Resubmission limit
        if (($expense['resubmission_count'] ?? 0) >= ($expense['max_resubmissions'] ?? 3))
            throw new Exception('Max resubmissions reached');

        // Duplicate
        $stmt = $pdo->prepare("INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, to_location, travel_date, distance, amount, notes, status, 
            bill_file_path, meter_start_photo_path, meter_end_photo_path, manager_status, accountant_status, hr_status, 
            original_expense_id, resubmission_count, is_resubmitted, resubmitted_from, resubmission_date, max_resubmissions, created_at
        ) VALUES (
            :uid, :pur, :mod, :fl, :tl, :td, :dist, :amt, :nt, 'pending',
            :bfp, :msp, :mep, 'pending', 'pending', 'pending',
            :oid, :cnt, 1, :rfrom, NOW(), :max, NOW()
        )");

        $root_id = $expense['original_expense_id'] ?? $expense['id'];
        $count = ($expense['resubmission_count'] ?? 0) + 1;

        $stmt->execute([
            ':uid' => $user_id,
            ':pur' => $expense['purpose'],
            ':mod' => $expense['mode_of_transport'],
            ':fl' => $expense['from_location'],
            ':tl' => $expense['to_location'],
            ':td' => $expense['travel_date'],
            ':dist' => $expense['distance'],
            ':amt' => $expense['amount'],
            ':nt' => $expense['notes'],
            ':bfp' => $expense['bill_file_path'],
            ':msp' => $expense['meter_start_photo_path'],
            ':mep' => $expense['meter_end_photo_path'],
            ':oid' => $root_id,
            ':cnt' => $count,
            ':rfrom' => $expense_id,
            ':max' => $expense['max_resubmissions'] ?? 3
        ]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Resubmitted successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fetch initial data
$expenses = getExpenses($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses | Connect</title>
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Travel Expense Specific CSS (Relative paths adjusted) -->
    <link rel="stylesheet" href="../css/supervisor/travel-expense-modal.css">
    <link rel="stylesheet" href="../css/supervisor/new-travel-expense-modal.css">

    <!-- Bootstrap (Required for Modal) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">

    <style>
        :root {
            /* Theme Colors */
            --bg-body: #0f1115;
            --bg-card: #161b22;
            --bg-input: #0d1117;
            --text-primary: #f0f6fc;
            --text-secondary: #8b949e;
            --border-color: #30363d;
            --accent-blue: #58a6ff;
            --accent-green: #238636;
            --accent-red: #da3633;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;

            /* Sidebar Variables (Required for sidebar.html) */
            --sidebar-width: 260px;
            --bg-sidebar: #010409;
            --bg-hover: #1f242c;
            --accent-color: #58a6ff;
            --text-muted: #8b949e;
            --border-light: #30363d;
            --accent-inverse: #ffffff;
            --status-lost: #da3633;
            --transition-speed: 0.3s;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* Layout Structure */
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* The sidebar container gets populated by JS */
        .sidebar {
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            left: 0;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
            background-color: var(--bg-sidebar);
            z-index: 1000;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 2.5rem;
            width: 0;
            /* Prevents flex items from overflowing or pushing width */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 991px) {
            .main-content {
                padding: 1.5rem;
            }

            .sidebar {
                position: fixed;
                /* Sidebar becomes fixed overlay on mobile */
                height: 100%;
            }
        }

        /* Overrides */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--text-primary);
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .text-dim {
            color: var(--text-secondary) !important;
        }

        /* Components */
        .card-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .summary-card {
            padding: 1.5rem;
            height: 100%;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            border-color: #484f58;
        }

        /* Icons in cards */
        .icon-box {
            min-width: 48px;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 1rem;
            background: rgba(255, 255, 255, 0.03);
        }

        /* Color variants for icons */
        .icon-blue {
            color: #58a6ff;
            background: rgba(88, 166, 255, 0.1);
        }

        .icon-orange {
            color: #d29922;
            background: rgba(210, 153, 34, 0.1);
        }

        .icon-green {
            color: #3fb950;
            background: rgba(63, 185, 80, 0.1);
        }

        .icon-red {
            color: #f85149;
            background: rgba(248, 81, 73, 0.1);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Filters */
        .filter-container {
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Form Elements */
        .form-control,
        .custom-select {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 6px;
            height: 42px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control:focus,
        .custom-select:focus {
            background-color: var(--bg-input);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.15);
        }

        label.small-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        .btn {
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 6px;
            padding: 0 1.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #238636;
            border-color: #238636;
        }

        .btn-primary:hover {
            background-color: #2ea043;
            border-color: #2ea043;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--bg-card);
            border: 1px solid #30363d;
            color: #f85149;
        }

        .btn-danger:hover {
            background-color: rgba(248, 81, 73, 0.1);
            border-color: #f85149;
            color: #f85149;
        }

        .btn-add {
            background: linear-gradient(180deg, #1f6feb 0%, #1158c7 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: 600;
        }

        .btn-add:hover {
            opacity: 0.9;
            color: white;
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.3);
        }

        /* Table */
        .table-custom {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .table-custom th {
            background-color: rgba(22, 27, 34, 0.5);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-custom td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .table-row:hover td {
            background-color: rgba(177, 186, 196, 0.04);
        }

        .table-row:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge-status {
            padding: 0.35em 0.8em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }

        .badge-pending {
            background: rgba(210, 153, 34, 0.15);
            color: #d29922;
            border: 1px solid rgba(210, 153, 34, 0.2);
        }

        .badge-approved {
            background: rgba(63, 185, 80, 0.15);
            color: #3fb950;
            border: 1px solid rgba(63, 185, 80, 0.2);
        }

        .badge-rejected {
            background: rgba(248, 81, 73, 0.15);
            color: #f85149;
            border: 1px solid rgba(248, 81, 73, 0.2);
        }

        /* Empty State */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-icon {
            font-size: 3rem;
            color: #30363d;
            margin-bottom: 1rem;
        }

        /* Helper */
        .avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #21262d;
            border: 1px solid #30363d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        /* Modal Dark Theme Fixes */
        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border-color);
        }

        .modal-title {
            color: var(--text-primary);
            font-weight: 600;
        }

        .close {
            color: var(--text-secondary);
            text-shadow: none;
            opacity: 1;
        }

        .close:hover {
            color: var(--text-primary);
        }

        /* Modal Form Controls */
        #newTravelExpenseModal .form-control,
        #newTravelExpenseModal .custom-select {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        #newTravelExpenseModal .form-control:focus,
        #newTravelExpenseModal .custom-select:focus {
            background-color: var(--bg-input);
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }

        #newTravelExpenseModal label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Modal Form Controls (Global for all modals) */
        .modal .form-control,
        .modal .custom-select {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        .modal .form-control:focus,
        .modal .custom-select:focus {
            background-color: var(--bg-input) !important;
            border-color: var(--accent-blue) !important;
            color: var(--text-primary) !important;
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.15) !important;
        }

        /* Fix placeholder visibility */
        .modal .form-control::placeholder {
            color: var(--text-secondary) !important;
            opacity: 1;
        }

        #newTravelExpenseModal label,
        #returnTripConfirmModal label,
        #returnTripConfirmModal strong {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Return Trip Modal Specifics */
        #returnTripConfirmModal .from-location,
        #returnTripConfirmModal .to-location {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            flex: 1;
        }

        #returnTripConfirmModal .from-location {
            margin-right: 0.5rem;
        }

        #returnTripConfirmModal .to-location {
            margin-left: 0.5rem;
        }

        #returnTripConfirmModal .from-location span,
        #returnTripConfirmModal .to-location span {
            color: var(--text-primary);
            font-weight: 600;
            display: block;
            margin-top: 0.25rem;
        }

        /* Enforce dark secondary buttons */
        .modal .btn-secondary {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .modal .btn-secondary:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Dark Theme for Custom Toasts (Overrides JS inline styles) */
        #custom-toast-container>div {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
        }

        #custom-toast-container>div button {
            color: var(--text-secondary) !important;
        }

        #custom-toast-container>div button:hover {
            color: var(--text-primary) !important;
        }

        /* File Input Dark Theme */
        .custom-file-label {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .custom-file-label::after {
            background-color: var(--bg-card);
            border-left: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        /* Modal Table */
        #addedExpensesTable {
            color: var(--text-primary);
        }

        #addedExpensesTable thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--text-secondary);
        }

        #addedExpensesTable td,
        #addedExpensesTable th {
            border-color: var(--border-color);
        }

        /* Prevent white background flash */
        .modal {
            color: var(--text-primary);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebarContainer"></aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1 class="h3 mb-2">Travel Expenses</h1>
                    <p class="text-dim mb-0">Track and manage your reimbursement claims.</p>
                </div>
                <div class="d-flex align-items-center">
                    <div class="text-right mr-3 d-none d-md-block">
                        <div class="font-weight-bold" style="font-size: 0.9rem;">
                            <?php echo htmlspecialchars($user_name); ?>
                        </div>
                        <div class="text-dim" style="font-size: 0.8rem;"><?php echo htmlspecialchars($user_role); ?>
                        </div>
                    </div>
                    <div class="avatar-small">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="row mb-5">
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="card-box summary-card">
                        <div class="icon-box icon-blue">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="summary-total-expenses">0</div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="card-box summary-card">
                        <div class="icon-box icon-orange">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="summary-pending-amount">₹0.00</div>
                            <div class="stat-label">Pending Amount</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="card-box summary-card">
                        <div class="icon-box icon-green">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="summary-approved-amount">₹0.00</div>
                            <div class="stat-label">Approved Amount</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card-box summary-card">
                        <div class="icon-box icon-red">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="summary-rejected-amount">₹0.00</div>
                            <div class="stat-label">Rejected Amount</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Actions -->
            <div class="card-box filter-container">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label class="small-label">Status</label>
                        <select id="filter-status" class="custom-select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label class="small-label">Period</label>
                        <select id="filter-month" class="custom-select">
                            <option value="">All Time</option>
                            <option value="0">January</option>
                            <option value="1">February</option>
                            <option value="2">March</option>
                            <option value="3">April</option>
                            <option value="4">May</option>
                            <option value="5">June</option>
                            <option value="6">July</option>
                            <option value="7">August</option>
                            <option value="8">September</option>
                            <option value="9">October</option>
                            <option value="10">November</option>
                            <option value="11">December</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3 mb-md-0">
                        <label class="small-label">&nbsp;</label>
                        <button class="btn btn-primary btn-block" onclick="applyFilters()">
                            Filter
                        </button>
                    </div>
                    <div class="col-md-4 text-md-right mt-3 mt-md-0">
                        <label class="small-label d-none d-md-block">&nbsp;</label>
                        <button id="addTravelExpenseBtn" class="btn btn-add">
                            <i class="fas fa-plus mr-2"></i> New Claim
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-box" style="overflow: hidden;">
                <div class="table-responsive">
                    <table class="table-custom" id="travelExpensesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Mode</th>
                                <th>Route</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="travelExpensesTableBody">
                            <tr>
                                <td colspan="7" class="text-center p-5 text-dim">Loading expenses...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Modal Include -->
    <?php include '../modals/travel_expense_modal_new.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Loader -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetch('sidebar.html')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('sidebarContainer').innerHTML = html;
                    if (window.feather) feather.replace();

                    // Setup Sidebar Toggle
                    const toggleBtn = document.getElementById('sidebarToggle');
                    const sidebar = document.getElementById('sidebarContainer');
                    if (toggleBtn) toggleBtn.addEventListener('click', () => sidebar.classList.toggle('collapsed'));

                    // --- Fix: Update Sidebar User Info ---
                    const sidebarUsername = document.getElementById('sidebarUsername');
                    const sidebarUserRole = document.getElementById('sidebarUserRole');
                    const userInitials = document.getElementById('userInitials');

                    // PHP variables passed to JS
                    const currentUserName = "<?php echo htmlspecialchars($user_name); ?>";
                    const currentUserRole = "<?php echo htmlspecialchars($user_role); ?>";

                    if (sidebarUsername) sidebarUsername.textContent = currentUserName;
                    if (sidebarUserRole) sidebarUserRole.textContent = currentUserRole;
                    if (userInitials) userInitials.textContent = currentUserName.charAt(0).toUpperCase();

                    // --- Fix: Highlight Active Link ---
                    const currentPath = 'travel_expenses.php';
                    const navLinks = document.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        if (link.getAttribute('href') === currentPath) {
                            link.classList.add('active');
                            // Optional: Scroll to active link if sidebar is scrollable
                            link.scrollIntoView({ block: 'nearest' });
                        }
                    });
                });
        });
    </script>

    <!-- Page Logic -->
    <script>
        let allExpenses = <?php echo json_encode($expenses); ?>;

        function renderTable(expenses) {
            const tbody = document.getElementById('travelExpensesTableBody');
            tbody.innerHTML = '';

            let total = 0, pending = 0, approved = 0, rejected = 0;

            if (expenses.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                            <h5 class="mb-1">No expenses found</h5>
                            <p class="text-dim small">Try adjusting your filters or add a new claim.</p>
                        </td>
                    </tr>`;
            }

            expenses.forEach(exp => {
                const amt = parseFloat(exp.amount) || 0;
                total += amt;
                if (exp.status === 'pending') pending += amt;
                else if (exp.status === 'approved') approved += amt;
                else if (exp.status === 'rejected') rejected += amt;

                // Calculate Action Buttons
                let actions = '';

                // 1. View Button (Always)
                actions += `<button class="btn btn-sm btn-outline-info mr-1" onclick="viewExpenseDetails(${exp.id})" title="View Details"><i class="fas fa-eye"></i></button>`;

                // 2. Resubmit Button (If Rejected)
                if (exp.status === 'rejected') {
                    // Check 15-day restriction
                    const rejectionDate = new Date(exp.updated_at);
                    const currentDate = new Date();
                    const daysDifference = Math.floor((currentDate - rejectionDate) / (1000 * 60 * 60 * 24));

                    if (daysDifference > 15) {
                        actions += `<button class="btn btn-sm btn-secondary mr-1" disabled title="Expired (Over 15 days)"><i class="fas fa-calendar-times"></i></button>`;
                    } else {
                        const maxAllowed = exp.max_resubmissions || 3;
                        const currentCount = exp.resubmission_count || 0;

                        if (currentCount < maxAllowed) {
                            actions += `<button class="btn btn-sm btn-outline-warning mr-1" onclick="window.resubmitExpenseClick(${exp.id})" title="Resubmit"><i class="fas fa-redo"></i></button>`;
                        } else {
                            actions += `<button class="btn btn-sm btn-secondary mr-1" disabled title="Max resubmissions reached"><i class="fas fa-ban"></i></button>`;
                        }
                    }
                }

                // 3. Delete Button (If Pending)
                if (exp.status === 'pending') {
                    actions += `<button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(${exp.id})" title="Delete"><i class="fas fa-trash-alt"></i></button>`;
                }

                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td>
                        <div class="font-weight-500">${exp.travel_date}</div>
                    </td>
                    <td>
                        <div class="text-wrap" style="max-width: 250px;">${exp.purpose}</div>
                    </td>
                    <td>
                        <i class="${getModeIcon(exp.mode_of_transport)} mr-2 text-dim"></i> 
                        ${exp.mode_of_transport}
                    </td>
                    <td>
                        <div class="small text-dim">FROM</div>
                        <div>${exp.from_location}</div>
                        <div class="small text-dim mt-1">TO</div>
                        <div>${exp.to_location}</div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-white">₹${amt.toFixed(2)}</div>
                    </td>
                    <td><span class="badge-status badge-${exp.status}">${exp.status.toUpperCase()}</span></td>
                    <td class="text-right">
                        ${actions}
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // animate numbers
            animateValue("summary-total-expenses", parseInt(document.getElementById("summary-total-expenses").textContent), expenses.length, 500);
            document.getElementById('summary-pending-amount').textContent = '₹' + pending.toFixed(2);
            document.getElementById('summary-approved-amount').textContent = '₹' + approved.toFixed(2);
            document.getElementById('summary-rejected-amount').textContent = '₹' + rejected.toFixed(2);
        }

        function getModeIcon(mode) {
            mode = mode.toLowerCase();
            if (mode.includes('car') || mode.includes('cab')) return 'fas fa-car';
            if (mode.includes('bike') || mode.includes('moto')) return 'fas fa-motorcycle';
            if (mode.includes('bus')) return 'fas fa-bus';
            if (mode.includes('train') || mode.includes('metro')) return 'fas fa-train';
            if (mode.includes('flight') || mode.includes('air')) return 'fas fa-plane';
            return 'fas fa-walking';
        }

        function animateValue(id, start, end, duration) {
            if (start === end) return;
            const range = end - start;
            let current = start;
            const increment = end > start ? 1 : -1;
            const stepTime = Math.abs(Math.floor(duration / range));
            const obj = document.getElementById(id);
            const timer = setInterval(function () {
                current += increment;
                obj.innerHTML = current;
                if (current == end) {
                    clearInterval(timer);
                }
            }, stepTime);
        }

        function applyFilters() {
            const status = document.getElementById('filter-status').value.toLowerCase();
            const month = document.getElementById('filter-month').value;

            const filtered = allExpenses.filter(exp => {
                const expDate = new Date(exp.travel_date);
                const expMonth = expDate.getMonth();

                let sMatch = !status || exp.status.toLowerCase() === status;
                let mMatch = month === '' || expMonth == month;

                return sMatch && mMatch;
            });
            renderTable(filtered);
        }

        function deleteExpense(id) {
            if (!confirm('Are you sure you want to delete this expense?')) return;
            $.post('travel_expenses.php', { action: 'delete_expense', expense_id: id }, function (res) {
                if (res.success) location.reload();
                else alert('Error deleting: ' + res.error);
            }, 'json');
        }

        window.resubmitExpenseClick = function (id) {
            if (!confirm('Resubmit this expense?')) return;
            $.post('travel_expenses.php', { action: 'resubmit_expense', expense_id: id }, function (res) {
                alert(res.message);
                if (res.success) location.reload();
            }, 'json');
        };

        // Init
        document.getElementById('filter-month').value = new Date().getMonth();
        renderTable(allExpenses);
        applyFilters();
    </script>

    <!-- View Expense Details Modal -->
    <div class="modal fade" id="viewExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title">Expense Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-4">
                    <div class="d-flex align-items-center mb-4">
                        <div id="view-icon-box" class="icon-box mr-3"></div>
                        <div>
                            <h4 class="mb-0" id="view-amount"></h4>
                            <span id="view-status" class="badge-status"></span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="small-label">Date</label>
                            <div id="view-date" class="font-weight-500"></div>
                        </div>
                        <div class="col-6">
                            <label class="small-label">Mode</label>
                            <div id="view-mode" class="font-weight-500"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small-label">Route</label>
                        <div class="d-flex align-items-center">
                            <span id="view-from" class="font-weight-500"></span>
                            <i class="fas fa-arrow-right mx-2 text-dim small"></i>
                            <span id="view-to" class="font-weight-500"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small-label">Purpose</label>
                        <div id="view-purpose" class="p-2 rounded" style="background: var(--bg-input);"></div>
                    </div>

                    <div id="view-notes-container" class="mb-3 d-none">
                        <label class="small-label">Notes</label>
                        <div id="view-notes" class="text-dim small"></div>
                    </div>

                    <div id="view-rejection-container" class="mb-3 d-none p-3 rounded"
                        style="background: rgba(248, 81, 73, 0.1); border: 1px solid rgba(248, 81, 73, 0.2);">
                        <label class="small-label text-danger">Rejection Reason</label>
                        <div id="view-rejection-reason" class="text-danger small"></div>
                    </div>

                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Modal Logic -->
    <script src="../js/supervisor/new-travel-expense-modal.js"></script>

    <script>
        function viewExpenseDetails(id) {
            const exp = allExpenses.find(e => e.id == id);
            if (!exp) return;

            // Populate Modal
            document.getElementById('view-amount').textContent = '₹' + parseFloat(exp.amount).toFixed(2);
            document.getElementById('view-date').textContent = exp.travel_date;
            document.getElementById('view-mode').textContent = exp.mode_of_transport;
            document.getElementById('view-from').textContent = exp.from_location;
            document.getElementById('view-to').textContent = exp.to_location;
            document.getElementById('view-purpose').textContent = exp.purpose;

            // Status and Icon
            const statusEl = document.getElementById('view-status');
            const iconBox = document.getElementById('view-icon-box');

            statusEl.className = `badge-status badge-${exp.status}`;
            statusEl.textContent = exp.status.toUpperCase();

            iconBox.className = 'icon-box mr-3';
            if (exp.status === 'approved') {
                iconBox.classList.add('icon-green');
                iconBox.innerHTML = '<i class="fas fa-check"></i>';
            } else if (exp.status === 'rejected') {
                iconBox.classList.add('icon-red');
                iconBox.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                iconBox.classList.add('icon-orange');
                iconBox.innerHTML = '<i class="fas fa-clock"></i>';
            }

            // Notes
            if (exp.notes) {
                document.getElementById('view-notes-container').classList.remove('d-none');
                document.getElementById('view-notes').textContent = exp.notes;
            } else {
                document.getElementById('view-notes-container').classList.add('d-none');
            }

            // Rejection Reason (Mock logic, assumed field might be 'rejection_reason' or similar if it existed)
            // For now, hiding as we don't have this field in the fetched data explicitely shown in previous context
            // But if it exists in 'exp', we show it.
            if (exp.status === 'rejected' && exp.manager_remarks) { // Assuming manager_remarks might hold it? Or we can just hide for now.
                // document.getElementById('view-rejection-container').classList.remove('d-none');
                // document.getElementById('view-rejection-reason').textContent = exp.manager_remarks;
            } else {
                document.getElementById('view-rejection-container').classList.add('d-none');
            }

            $('#viewExpenseModal').modal('show');
        }
    </script>

</body>

</html>