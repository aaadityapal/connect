<?php
/**
 * CLI script to run the migration directly
 * Usage: php run_migration.php
 */

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

echo "Starting migration process...\n";

// Include and run the migration script
require_once __DIR__ . '/migrate_project_payouts.php';

echo "Migration script execution completed.\n"; 