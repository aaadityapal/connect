<?php
require_once __DIR__ . '/../config.php';

try {
    // Drop table if exists to start fresh
    $pdo->exec("DROP TABLE IF EXISTS pa_tasks");

    // Create table
    $sql = "
    CREATE TABLE pa_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(100) NOT NULL,
        task_name VARCHAR(150) NOT NULL,
        status ENUM('Completed', 'In Progress', 'Pending', 'Not Started') NOT NULL,
        due_date DATE NOT NULL,
        completed_date DATE NULL,
        created_at DATE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table pa_tasks created.\n";

    // Insert dummy data
    $stmt = $pdo->prepare("INSERT INTO pa_tasks (project_name, task_name, status, due_date, completed_date, created_at) VALUES (?, ?, ?, ?, ?, ?)");

    $now = new DateTime();
    
    // Helper to format dates
    $dateStr = function($diffStr) use ($now) {
        $d = clone $now;
        $d->modify($diffStr);
        return $d->format('Y-m-d');
    };

    $dummy_data = [
        // Completed Tasks (On Time)
        ['CRM Upgrade', 'Database Migration', 'Completed', $dateStr('-5 days'), $dateStr('-6 days'), $dateStr('-30 days')],
        ['CRM Upgrade', 'API Setup', 'Completed', $dateStr('-10 days'), $dateStr('-10 days'), $dateStr('-30 days')],
        ['Website Redesign', 'Homepage Mockup', 'Completed', $dateStr('-20 days'), $dateStr('-22 days'), $dateStr('-60 days')],
        ['Website Redesign', 'CSS Styling', 'Completed', $dateStr('-15 days'), $dateStr('-15 days'), $dateStr('-60 days')],
        // Completed Tasks (Late)
        ['App Dev', 'User Auth', 'Completed', $dateStr('-40 days'), $dateStr('-35 days'), $dateStr('-70 days')],
        ['App Dev', 'Push Notifications', 'Completed', $dateStr('-25 days'), $dateStr('-20 days'), $dateStr('-70 days')],
        
        // In Progress
        ['CRM Upgrade', 'Frontend Integration', 'In Progress', $dateStr('+2 days'), null, $dateStr('-10 days')],
        ['Website Redesign', 'Contact Form', 'In Progress', $dateStr('+5 days'), null, $dateStr('-5 days')],
        ['App Dev', 'Offline Mode', 'In Progress', $dateStr('+15 days'), null, $dateStr('-2 days')],
        
        // Pending
        ['CRM Upgrade', 'Deployment', 'Pending', $dateStr('+20 days'), null, $dateStr('-10 days')],
        ['Website Redesign', 'SEO Optimization', 'Pending', $dateStr('+10 days'), null, $dateStr('-5 days')],
        
        // Not Started
        ['App Dev', 'Beta Testing', 'Not Started', $dateStr('+30 days'), null, $dateStr('-2 days')],
        ['App Dev', 'App Store Submission', 'Not Started', $dateStr('+45 days'), null, $dateStr('-2 days')],

        // Older completed tasks for 12-month trend data
        ['Old Project 1', 'Task A', 'Completed', $dateStr('-3 months'), $dateStr('-3 months'), $dateStr('-5 months')],
        ['Old Project 1', 'Task B', 'Completed', $dateStr('-4 months'), $dateStr('-3 months'), $dateStr('-5 months')], // Late
        ['Old Project 2', 'Task C', 'Completed', $dateStr('-5 months'), $dateStr('-6 months'), $dateStr('-8 months')], // Early
        ['Old Project 2', 'Task D', 'Completed', $dateStr('-7 months'), $dateStr('-7 months'), $dateStr('-8 months')],
        ['Old Project 3', 'Task E', 'Completed', $dateStr('-9 months'), $dateStr('-8 months'), $dateStr('-10 months')], // Late
    ];

    foreach ($dummy_data as $row) {
        $stmt->execute($row);
    }
    
    echo "Dummy data inserted successfully: " . count($dummy_data) . " rows.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
