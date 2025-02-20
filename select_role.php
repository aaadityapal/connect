<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user's roles
$user_id = $_SESSION['user_id'];
$query = "SELECT role FROM user_roles WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$roles = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .role-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }
        .role-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .welcome-text {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="role-container">
        <div class="welcome-text">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
            <p>Please select your role to continue</p>
        </div>

        <?php foreach ($roles as $role): ?>
            <div class="role-card" onclick="selectRole('<?php echo $role['role']; ?>')">
                <h4><?php echo htmlspecialchars($role['role']); ?></h4>
                <p class="text-muted">Click to continue as <?php echo htmlspecialchars($role['role']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
    function selectRole(role) {
        fetch('set_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'role=' + encodeURIComponent(role)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert('Error selecting role');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error selecting role');
        });
    }
    </script>
</body>
</html>
