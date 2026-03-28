<?php
require_once __DIR__ . '/../config.php';

try {
    // We will insert 50 random records into `pa_tasks`
    $projects = [
        'CRM Enhancement', 'Website Redesign', 'Mobile App V2', 
        'Marketing Campaign', 'HR Portal Update', 'Q3 Financials', 
        'Server Migration', 'Customer API', 'Security Audit'
    ];

    $task_adjectives = ['Update', 'Create', 'Review', 'Test', 'Deploy', 'Design', 'Analyze', 'Document', 'Optimize', 'Migrate'];
    $task_nouns = ['Database', 'Frontend', 'Backend', 'Authentication', 'UI/UX', 'Reports', 'API', 'Scripts', 'Logs', 'Assets'];

    $statuses = ['Completed', 'Completed', 'Completed', 'In Progress', 'In Progress', 'Pending', 'Not Started']; // weighted towards completed

    $stmt = $pdo->prepare("INSERT INTO pa_tasks (project_name, task_name, status, due_date, completed_date, created_at) VALUES (?, ?, ?, ?, ?, ?)");

    $inserted = 0;
    
    // Let's insert 50 random records
    for ($i = 0; $i < 50; $i++) {
        $proj = $projects[array_rand($projects)];
        $task = $task_adjectives[array_rand($task_adjectives)] . ' ' . $task_nouns[array_rand($task_nouns)];
        $status = $statuses[array_rand($statuses)];
        
        // Random created_at between -6 months and today
        $created_offset = rand(0, 180);
        $created_date = date('Y-m-d', strtotime("-$created_offset days"));

        // Random due_date between created_date and created_date + 60 days
        $due_offset = rand(5, 60);
        $due_date = date('Y-m-d', strtotime("$created_date +$due_offset days"));

        $completed_date = null;
        if ($status === 'Completed') {
            // Random completed_date between created_date and due_date + 10 days (to simulate some late and some early)
            // But must be <= today, since it is completed
            $max_completed = min(strtotime('today'), strtotime("$due_date +10 days"));
            $min_completed = strtotime($created_date);
            
            // Just in case
            if ($min_completed > $max_completed) {
                $max_completed = strtotime('today');
                $min_completed = strtotime("$max_completed - 5 days");
            }
            
            $comp_stamp = rand($min_completed, $max_completed);
            $completed_date = date('Y-m-d', $comp_stamp);
        }

        $stmt->execute([$proj, $task, $status, $due_date, $completed_date, $created_date]);
        $inserted++;
    }

    echo "Successfully generated and inserted $inserted random data records into pa_tasks.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
