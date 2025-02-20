<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    die('Unauthorized access');
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   u.username as employee_name,
                   u.email as employee_email,
                   u.reporting_manager,
                   DATEDIFF(l.end_date, l.start_date) + 1 as duration,
                   a.username as approved_by_name
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN users a ON l.approved_by = a.id
            WHERE l.id = ?
        ");
        
        $stmt->execute([$_GET['id']]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($leave) {
            $statusClass = match($leave['status']) {
                'Approved' => 'success',
                'Rejected' => 'danger',
                'On Hold' => 'warning',
                default => 'secondary'
            };
?>
            <div class="leave-details">
                <!-- Status Banner -->
                <div class="alert alert-<?php echo $statusClass; ?> d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <div>
                        Leave Application Status: 
                        <strong><?php echo htmlspecialchars($leave['status']); ?></strong>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Employee Information -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-user me-2 text-primary"></i>
                                    Employee Details
                                </h6>
                                <div class="details-list">
                                    <div class="detail-item">
                                        <span class="label">Name</span>
                                        <span class="value"><?php echo htmlspecialchars($leave['employee_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Email</span>
                                        <span class="value"><?php echo htmlspecialchars($leave['employee_email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Manager</span>
                                        <span class="value"><?php echo htmlspecialchars($leave['reporting_manager']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Information -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                    Leave Details
                                </h6>
                                <div class="details-list">
                                    <div class="detail-item">
                                        <span class="label">Leave Type</span>
                                        <span class="value">
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <?php echo htmlspecialchars($leave['leave_type']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Duration</span>
                                        <span class="value"><?php echo $leave['duration']; ?> days</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Start Date</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">End Date</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Applied On</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Section -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-comment-alt me-2 text-primary"></i>
                                    Reason for Leave
                                </h6>
                                <p class="card-text reason-text">
                                    <?php echo nl2br(htmlspecialchars($leave['reason'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($leave['remarks'])): ?>
                    <!-- Remarks Section -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-comments me-2 text-primary"></i>
                                    HR Remarks
                                </h6>
                                <p class="card-text remarks-text">
                                    <?php echo nl2br(htmlspecialchars($leave['remarks'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <style>
                .leave-details {
                    font-size: 0.95rem;
                }
                .card {
                    border: 1px solid rgba(0,0,0,0.1);
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                .card-title {
                    color: #2c3e50;
                    font-weight: 600;
                    font-size: 1rem;
                }
                .details-list {
                    display: flex;
                    flex-direction: column;
                    gap: 0.8rem;
                }
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 0.8rem;
                    border-bottom: 1px solid rgba(0,0,0,0.05);
                }
                .detail-item:last-child {
                    border-bottom: none;
                    padding-bottom: 0;
                }
                .detail-item .label {
                    color: #6c757d;
                    font-weight: 500;
                }
                .detail-item .value {
                    font-weight: 500;
                    color: #2c3e50;
                }
                .reason-text, .remarks-text {
                    background: #f8f9fa;
                    padding: 1rem;
                    border-radius: 6px;
                    margin: 0;
                    color: #2c3e50;
                    line-height: 1.6;
                }
            </style>
<?php
        } else {
            echo '<div class="alert alert-danger">Leave application not found.</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error fetching leave details.</div>';
    }
}
?>
