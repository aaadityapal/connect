<?php
require_once 'config/db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Short Leave Count</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .debug-info { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .test-section { margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .highlight { background-color: #e6f3ff; }
    </style>
</head>
<body>
    <h1>Short Leave Debug Test</h1>

    <?php
    // Get all users
    $users_query = "SELECT id, username FROM users WHERE status = 'active' ORDER BY username";
    $users = $conn->query($users_query)->fetch_all(MYSQLI_ASSOC);
    ?>

    <div class="test-section">
        <h2>Select User to Test</h2>
        <select id="userSelect" onchange="testUser(this.value)">
            <option value="">Select User</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>">
                    <?php echo htmlspecialchars($user['username']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="results"></div>

    <script>
    function testUser(userId) {
        if (!userId) return;

        fetch(`test_short_leave_ajax.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('results');
                let html = `
                    <div class="debug-info">
                        <h3>Debug Information for User ID: ${userId}</h3>
                        
                        <h4>Leave Type Information:</h4>
                        <table>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Max Days</th>
                            </tr>
                            <tr>
                                <td>${data.leaveType.id}</td>
                                <td>${data.leaveType.name}</td>
                                <td>${data.leaveType.max_days}</td>
                            </tr>
                        </table>

                        <h4>Short Leave Records:</h4>
                        <table>
                            <tr>
                                <th>ID</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Manager Approval</th>
                                <th>HR Approval</th>
                            </tr>
                `;

                data.shortLeaves.forEach(leave => {
                    html += `
                        <tr>
                            <td>${leave.id}</td>
                            <td>${leave.start_date}</td>
                            <td>${leave.end_date}</td>
                            <td>${leave.status}</td>
                            <td>${leave.manager_approval}</td>
                            <td>${leave.hr_approval}</td>
                        </tr>
                    `;
                });

                html += `
                        </table>

                        <h4>Count Summary:</h4>
                        <p>Total Approved Short Leaves: ${data.shortLeaveCount}</p>
                        <p>Used Days in Leave Balance: ${data.usedDays}</p>
                    </div>
                `;

                resultsDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('results').innerHTML = `
                    <div class="debug-info">
                        <p style="color: red;">Error loading data: ${error.message}</p>
                    </div>
                `;
            });
    }
    </script>
</body>
</html> 