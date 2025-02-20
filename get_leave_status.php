<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$leave_status_query = "SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $pdo->prepare($leave_status_query);
$stmt->execute([$_SESSION['user_id']]);
$recent_leaves = $stmt->fetchAll();

ob_start();
if (count($recent_leaves) > 0):
    foreach ($recent_leaves as $leave):
        ?>
        <div class="leave-status-item">
            <div class="leave-type">
                <?php echo htmlspecialchars($leave['leave_type']); ?>
            </div>
            <div class="leave-dates">
                <?php 
                echo date('M d', strtotime($leave['start_date']));
                if ($leave['start_date'] !== $leave['end_date']) {
                    echo ' - ' . date('M d', strtotime($leave['end_date']));
                }
                ?>
            </div>
            <div class="leave-status-badge <?php echo strtolower($leave['status']); ?>">
                <?php echo ucfirst($leave['status']); ?>
            </div>
        </div>
        <?php
    endforeach;
else:
    ?>
    <p class="text-muted text-center mb-0">No recent leave applications</p>
    <?php
endif;

$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
?>
