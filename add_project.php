<?php
require_once 'config/db_connect.php';
session_start();

// Add this at the top to fetch marketing executives for the dropdown
$marketing_query = "SELECT user_id, username FROM tbl_users WHERE role = 'marketing'";
$marketing_result = $conn->query($marketing_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
</head>
<body>
    <div class="container mt-5">
        <h2>Add New Project</h2>
        <form action="process_project.php" method="POST" class="needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="client_name" class="form-label">Client Name*</label>
                    <input type="text" class="form-control" id="client_name" name="client_name" required>
                </div>
                
                <div class="col-md-6">
                    <label for="contact_number" class="form-label">Contact Number*</label>
                    <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email*</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="col-md-6">
                    <label for="project_type" class="form-label">Project Type*</label>
                    <select class="form-select" id="project_type" name="project_type" required>
                        <option value="">Select Project Type</option>
                        <option value="Website">Website</option>
                        <option value="Mobile App">Mobile App</option>
                        <option value="Desktop App">Desktop App</option>
                        <option value="Digital Marketing">Digital Marketing</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="project_cost" class="form-label">Project Cost*</label>
                    <input type="number" class="form-control" id="project_cost" name="project_cost" required>
                </div>

                <div class="col-md-6">
                    <label for="marketing_executive" class="form-label">Marketing Executive*</label>
                    <select class="form-select" id="marketing_executive" name="marketing_executive" required>
                        <option value="">Select Marketing Executive</option>
                        <?php while($row = $marketing_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="start_date" class="form-label">Start Date*</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>

                <div class="col-md-6">
                    <label for="end_date" class="form-label">End Date*</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Add Project</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>

