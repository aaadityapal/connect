<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO agreements (
                client_name, 
                project_name, 
                start_date, 
                end_date, 
                agreement_type,
                amount,
                payment_terms,
                scope_of_work,
                special_conditions,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['client_name'],
            $_POST['project_name'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['agreement_type'],
            $_POST['amount'],
            $_POST['payment_terms'],
            $_POST['scope_of_work'],
            $_POST['special_conditions'],
            $_SESSION['user_id']
        ]);

        $_SESSION['success_message'] = "Agreement created successfully!";
        header("Location: agreements.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error creating agreement: " . $e->getMessage();
    }
}

// Fetch existing agreements
$agreements = $pdo->query("
    SELECT a.*, u.username as created_by_name 
    FROM agreements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreements Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .agreement-form {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .agreement-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .agreement-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .agreement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .agreement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .agreement-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
        }

        .agreement-type {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .agreement-type.contract {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .agreement-type.mou {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .agreement-type.nda {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .agreement-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 500;
        }

        .agreement-actions {
            display: flex;
            gap: 10px;
        }

        .action-button {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .action-button i {
            font-size: 1rem;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-label {
            font-weight: 500;
            color: #4b5563;
        }

        .required::after {
            content: "*";
            color: #ef4444;
            margin-left: 4px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .status-badge.active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-badge.expired {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-badge.draft {
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <!-- Create Agreement Form -->
        <div class="agreement-form">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-file-earmark-text"></i>
                    Create New Agreement
                </h2>
            </div>

            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Client Name</label>
                        <input type="text" class="form-control" name="client_name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Project Name</label>
                        <input type="text" class="form-control" name="project_name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Agreement Type</label>
                        <select class="form-select" name="agreement_type" required>
                            <option value="">Select Type</option>
                            <option value="contract">Contract</option>
                            <option value="mou">MOU</option>
                            <option value="nda">NDA</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Amount</label>
                        <input type="number" class="form-control" name="amount" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label required">Payment Terms</label>
                        <textarea class="form-control" name="payment_terms" rows="3" required></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label required">Scope of Work</label>
                        <textarea class="form-control" name="scope_of_work" rows="4" required></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Special Conditions</label>
                        <textarea class="form-control" name="special_conditions" rows="3"></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Create Agreement
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Agreements List -->
        <div class="agreement-list">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-files"></i>
                    Existing Agreements
                </h2>
            </div>

            <?php foreach ($agreements as $agreement): ?>
                <div class="agreement-card">
                    <div class="agreement-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="agreement-title"><?php echo htmlspecialchars($agreement['project_name']); ?></span>
                            <span class="agreement-type <?php echo $agreement['agreement_type']; ?>">
                                <?php echo strtoupper($agreement['agreement_type']); ?>
                            </span>
                        </div>
                        <span class="status-badge <?php echo strtotime($agreement['end_date']) < time() ? 'expired' : 'active'; ?>">
                            <?php echo strtotime($agreement['end_date']) < time() ? 'Expired' : 'Active'; ?>
                        </span>
                    </div>

                    <div class="agreement-details">
                        <div class="detail-item">
                            <span class="detail-label">Client</span>
                            <span class="detail-value"><?php echo htmlspecialchars($agreement['client_name']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value">
                                <?php 
                                echo date('d M Y', strtotime($agreement['start_date'])) . ' - ' . 
                                     date('d M Y', strtotime($agreement['end_date']));
                                ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Amount</span>
                            <span class="detail-value">₹<?php echo number_format($agreement['amount']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Created By</span>
                            <span class="detail-value"><?php echo htmlspecialchars($agreement['created_by_name']); ?></span>
                        </div>
                    </div>

                    <div class="agreement-actions">
                        <button class="btn btn-outline-primary action-button" onclick="viewAgreement(<?php echo $agreement['id']; ?>)">
                            <i class="bi bi-eye"></i>
                            View
                        </button>
                        <button class="btn btn-outline-success action-button" onclick="downloadAgreement(<?php echo $agreement['id']; ?>)">
                            <i class="bi bi-download"></i>
                            Download
                        </button>
                        <button class="btn btn-outline-warning action-button" onclick="editAgreement(<?php echo $agreement['id']; ?>)">
                            <i class="bi bi-pencil"></i>
                            Edit
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewAgreement(id) {
            // Implement view functionality
            console.log('Viewing agreement:', id);
        }

        function downloadAgreement(id) {
            // Implement download functionality
            console.log('Downloading agreement:', id);
        }

        function editAgreement(id) {
            // Implement edit functionality
            console.log('Editing agreement:', id);
        }
    </script>
</body>
</html> 