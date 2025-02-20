<?php
// Query to get user roles
$roles_query = "SELECT r.role_name 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = ?";
$stmt = mysqli_prepare($conn, $roles_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$roles_result = mysqli_stmt_get_result($stmt);

$user_roles = array();
while($role = mysqli_fetch_assoc($roles_result)) {
    $user_roles[] = $role['role_name'];
}
?>

<!-- Display roles -->
<div class="user-roles">
    <strong>Roles:</strong>
    <?php if (!empty($user_roles)): ?>
        <?php foreach($user_roles as $role): ?>
            <span class="badge badge-primary"><?php echo htmlspecialchars($role); ?></span>
        <?php endforeach; ?>
    <?php else: ?>
        <span class="text-muted">No roles assigned</span>
    <?php endif; ?>
</div>
