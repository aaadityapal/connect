<?php
// Test script to check if user tracking is working
session_start();

echo "<h2>User Tracking Implementation Test</h2>";

if (isset($_SESSION['user_id'])) {
    echo "<p><strong>✅ Session Active</strong></p>";
    echo "<p>Current User ID: " . $_SESSION['user_id'] . "</p>";
    if (isset($_SESSION['username'])) {
        echo "<p>Username: " . $_SESSION['username'] . "</p>";
    }
    if (isset($_SESSION['role'])) {
        echo "<p>Role: " . $_SESSION['role'] . "</p>";
    }
} else {
    echo "<p><strong>❌ No Session Found</strong></p>";
    echo "<p>Please log in to test user tracking functionality.</p>";
    echo "<p><a href='login.php'>Login</a></p>";
}

echo "<h3>Implementation Summary</h3>";
echo "<ul>";
echo "<li>✅ Added session handling to save_vendor.php API</li>";
echo "<li>✅ Added session handling to save_labour.php API</li>";
echo "<li>✅ Added session handling to save_payment_entry.php API</li>";
echo "<li>✅ Added session handling to update_vendor.php API</li>";
echo "<li>✅ Added hidden fields to add_vendor_modal.php</li>";
echo "<li>✅ Added hidden fields to add_labour_modal.php</li>";
echo "<li>✅ Added hidden fields to add_payment_entry_modal.php</li>";
echo "</ul>";

echo "<h3>Database Columns Added</h3>";
echo "<p>The following columns should be added to the database tables:</p>";
echo "<ul>";
echo "<li><strong>hr_vendors:</strong> created_by (INT), updated_by (INT)</li>";
echo "<li><strong>hr_labours:</strong> created_by (INT), updated_by (INT)</li>";
echo "<li><strong>hr_payment_entries:</strong> created_by (INT), updated_by (INT)</li>";
echo "<li><strong>hr_payment_recipients:</strong> created_by (INT), updated_by (INT)</li>";
echo "</ul>";

echo "<h3>SQL Commands to Add Columns</h3>";
echo "<pre>";
echo "-- Add columns to hr_vendors table\n";
echo "ALTER TABLE hr_vendors ADD COLUMN created_by INT DEFAULT NULL AFTER additional_notes;\n";
echo "ALTER TABLE hr_vendors ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by;\n\n";

echo "-- Add columns to hr_labours table\n";
echo "ALTER TABLE hr_labours ADD COLUMN created_by INT DEFAULT NULL AFTER notes;\n";
echo "ALTER TABLE hr_labours ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by;\n\n";

echo "-- Add columns to hr_payment_entries table\n";
echo "ALTER TABLE hr_payment_entries ADD COLUMN created_by INT DEFAULT NULL AFTER recipient_count;\n";
echo "ALTER TABLE hr_payment_entries ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by;\n\n";

echo "-- Add columns to hr_payment_recipients table\n";
echo "ALTER TABLE hr_payment_recipients ADD COLUMN created_by INT DEFAULT NULL AFTER payment_mode;\n";
echo "ALTER TABLE hr_payment_recipients ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by;\n\n";

echo "-- Add foreign key constraints (optional but recommended)\n";
echo "ALTER TABLE hr_vendors ADD CONSTRAINT fk_vendor_created_by FOREIGN KEY (created_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_vendors ADD CONSTRAINT fk_vendor_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_labours ADD CONSTRAINT fk_labour_created_by FOREIGN KEY (created_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_labours ADD CONSTRAINT fk_labour_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_payment_entries ADD CONSTRAINT fk_payment_created_by FOREIGN KEY (created_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_payment_entries ADD CONSTRAINT fk_payment_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_payment_recipients ADD CONSTRAINT fk_recipient_created_by FOREIGN KEY (created_by) REFERENCES users(id);\n";
echo "ALTER TABLE hr_payment_recipients ADD CONSTRAINT fk_recipient_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);\n";
echo "</pre>";

echo "<p><strong>Note:</strong> Please run the SQL commands above in your database to add the required columns before testing the functionality.</p>";
?>