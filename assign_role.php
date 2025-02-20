<form action="process_role_assignment.php" method="POST">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    
    <div class="form-group">
        <label>Select Roles:</label>
        <select name="roles[]" class="form-control select2" multiple="multiple">
            <?php
            // Fetch all available roles
            $role_query = "SELECT * FROM roles ORDER BY role_name";
            $role_result = mysqli_query($conn, $role_query);
            
            // Fetch user's current roles
            $current_roles_query = "SELECT role_id FROM user_roles WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $current_roles_query);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $current_roles_result = mysqli_stmt_get_result($stmt);
            $current_roles = array();
            while($row = mysqli_fetch_assoc($current_roles_result)) {
                $current_roles[] = $row['role_id'];
            }
            
            while($role = mysqli_fetch_assoc($role_result)) {
                $selected = in_array($role['id'], $current_roles) ? 'selected' : '';
                echo "<option value='" . $role['id'] . "' $selected>" . $role['role_name'] . "</option>";
            }
            ?>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary">Assign Roles</button>
</form>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 38px;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border: 1px solid #006fe6;
        color: #fff;
        padding: 2px 8px;
        margin: 4px;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #fff;
        margin-right: 5px;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #fff;
        opacity: 0.8;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "Select roles",
            allowClear: true,
            width: '100%'
        });
    });
</script>
