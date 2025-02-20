    <?php
    require_once 'config.php';
    session_start();

    echo "<h2>Task View Debug</h2>";

    try {
        $user_id = $_SESSION['user_id'];
        echo "<h3>Tasks for User ID: $user_id</h3>";

        $query = "
            SELECT 
                t.id, 
                t.title, 
                t.description,
                tst.due_date,
                tst.priority,
                tst.status,
                tst.stage_number,
                tst.assigned_to,
                tst.created_at,
                tst.updated_at
            FROM tasks t
            INNER JOIN task_stages tst ON t.id = tst.task_id
            WHERE tst.assigned_to = ?
            ORDER BY t.due_date ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tasks)) {
            echo "<p>No tasks found for this user.</p>";
        } else {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Due Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Stage</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>";
            
            foreach ($tasks as $task) {
                echo "<tr>";
                echo "<td>{$task['id']}</td>";
                echo "<td>{$task['title']}</td>";
                echo "<td>{$task['description']}</td>";
                echo "<td>{$task['due_date']}</td>";
                echo "<td>{$task['priority']}</td>";
                echo "<td>{$task['status']}</td>";
                echo "<td>{$task['stage_number']}</td>";
                echo "<td>{$task['created_at']}</td>";
                echo "<td>{$task['updated_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }

    } catch (Exception $e) {
        echo "<h3>Error:</h3>";
        echo "Message: " . $e->getMessage() . "<br>";
    }
    ?> 