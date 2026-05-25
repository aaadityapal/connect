<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

try {
    $result = array();

    // 1. Get total clients overall (Always returned)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clients");
    $result['global_total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Get recent activities for the feed (Always returned)
    $stmt = $conn->query("SELECT activity_type as type, activity_data as data, DATE_FORMAT(created_at, '%h:%i:%s %p') as time 
                          FROM user_activities 
                          ORDER BY created_at DESC LIMIT 10");
    $result['activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get the latest campaign (Running or Completed)
    $query = "SELECT c.id, c.name, c.status, DATE_FORMAT(c.created_at, '%b %d, %h:%i %p') as time 
              FROM campaigns c 
              WHERE c.status != 'Draft' 
              ORDER BY c.created_at DESC LIMIT 1";
    $stmt = $conn->query($query);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        $result['no_campaign'] = true;
        echo json_encode($result);
        exit;
    }

    $result['id'] = $campaign['id'];
    $result['name'] = $campaign['name'];
    $result['status'] = $campaign['status'];
    $result['time'] = $campaign['time'];
    $campaign_id = $campaign['id'];

    // 4. Get stats for this specific campaign
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM campaign_deliveries WHERE campaign_id = :cid");
    $stmt->execute(['cid' => $campaign_id]);
    $result['total_sent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM campaign_deliveries WHERE campaign_id = :cid AND status IN ('Delivered', 'Read', 'Replied')");
    $stmt->execute(['cid' => $campaign_id]);
    $result['delivered'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM campaign_deliveries WHERE campaign_id = :cid AND status = 'Replied'");
    $stmt->execute(['cid' => $campaign_id]);
    $result['replies'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 5. Get delivery details for the table
    $query = "SELECT cl.name, cl.phone, cl.id as client_id, d.status, DATE_FORMAT(d.sent_at, '%h:%i %p') as sent_time 
              FROM campaign_deliveries d 
              JOIN clients cl ON d.client_id = cl.id 
              WHERE d.campaign_id = :cid";
    $stmt = $conn->prepare($query);
    $stmt->execute(['cid' => $campaign_id]);
    
    $deliveries = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $words = explode(" ", $row['name']);
        $initials = strtoupper($words[0][0] . ($words[1][0] ?? ''));
        $row['initials'] = $initials;
        $deliveries[] = $row;
    }
    $result['deliveries'] = $deliveries;

    echo json_encode($result);

} catch(Exception $e) {
    echo json_encode(array("error" => $e->getMessage()));
}
?>
