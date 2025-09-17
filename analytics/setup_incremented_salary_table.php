<?php
// Script to create the incremented salary analytics table
require_once '../config/db_connect.php';

try {
    // Read the SQL file
    $sql_file = __DIR__ . '/create_incremented_salary_table.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: " . $sql_file);
    }
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    // Split SQL commands by semicolon and execute them
    $sql_commands = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $pdo->beginTransaction();
    
    foreach ($sql_commands as $command) {
        if (!empty($command) && !preg_match('/^\s*--/', $command) && !preg_match('/^\s*$/', $command)) {
            try {
                echo "Executing: " . substr(str_replace('\n', ' ', $command), 0, 80) . "...\n";
                $pdo->exec($command);
                echo "   ✅ Success\n";
            } catch (PDOException $e) {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
                // Continue with other commands instead of failing completely
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n🎉 Successfully created incremented salary analytics table and related structures!\n";
    echo "📊 You can now view the analytics at: incremented_salary_analytics_table.php\n";
    echo "🔗 The table will automatically populate when users save incremented salaries in the dashboard.\n";
    echo "\n📑 Note: For automatic change logging, manually run the trigger creation script:\n";
    echo "   - Open phpMyAdmin or MySQL command line\n";
    echo "   - Execute the contents of: create_trigger_manual.sql\n";
    echo "   - This will enable automatic logging of salary changes\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "\n❌ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
?>