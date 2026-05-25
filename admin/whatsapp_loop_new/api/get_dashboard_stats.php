<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

try {
    $stats = array();

    // Total Clients
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clients");
    $stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active Campaigns (Running or Completed)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM campaigns WHERE status != 'Draft'");
    $stats['total_campaigns'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Messages Sent (Pending, Sent, Delivered, Read, Replied)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM campaign_deliveries");
    $stats['messages_sent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Delivery Rate (Delivered + Read + Replied) / Total
    $stmt = $conn->query("SELECT COUNT(*) as total FROM campaign_deliveries WHERE status IN ('Delivered', 'Read', 'Replied')");
    $delivered = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stats['delivery_rate'] = $stats['messages_sent'] > 0 ? round(($delivered / $stats['messages_sent']) * 100) : 0;

    // Recent Activity (from user_activities and campaigns)
    $recent_activity = array();
    
    // Get latest 5 campaigns started
    $stmt = $conn->query("SELECT name, status, DATE_FORMAT(created_at, '%h:%i %p') as time FROM campaigns ORDER BY created_at DESC LIMIT 5");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $icon = '🚀';
        if($row['status'] == 'Draft') $icon = '💾';
        $recent_activity[] = array(
            "icon" => $icon,
            "text" => "Campaign <strong>" . htmlspecialchars($row['name']) . "</strong> was " . ($row['status'] == 'Draft' ? 'saved' : 'launched'),
            "time" => $row['time']
        );
    }
    
    $stats['recent_activity'] = $recent_activity;

    echo json_encode($stats);

} catch(Exception $e) {
    echo json_encode(array("error" => $e->getMessage()));
}
?>
