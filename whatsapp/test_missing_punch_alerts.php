<?php
/**
 * Test Script for Missing Punch Alerts
 * 
 * This script creates a test scenario to verify that the missing punch alerts
 * are triggered correctly.
 * 
 * Scenarios:
 * 1. Missing Punch In: Creates a user with a shift that started 2 hours ago, with no punch in.
 * 2. Missing Punch Out: Creates a user with a shift that ended 2 hours ago, with punch in but no punch out.
 */

require_once __DIR__ . '/../config.php';

// Prevent header issues if run from browser
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "===== STARTING MISSING PUNCH ALERTS TEST =====\n\n";

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Helpers ---
function getOrCreateTestShift($pdo, $startTime, $endTime)
{
    // Check if a shift with these times already exists to avoid clutter
    $stmt = $pdo->prepare("SELECT id FROM shifts WHERE start_time = ? AND end_time = ? AND shift_name = 'TEST_SHIFT'");
    $stmt->execute([$startTime, $endTime]);
    $shift = $stmt->fetch();

    if ($shift) {
        return $shift['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time, created_at) VALUES ('TEST_SHIFT', ?, ?, NOW())");
    $stmt->execute([$startTime, $endTime]);
    return $pdo->lastInsertId();
}

function createTestUser($pdo, $name, $phone)
{
    // Delete if exists first
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$name]);

    $uniqueId = 'EMP' . substr($phone, -4) . rand(100, 999);
    $stmt = $pdo->prepare("INSERT INTO users (unique_id, username, phone, email, password, designation, department, role, status, created_at) VALUES (?, ?, ?, ?, 'dummy_hash', 'Test Tester', 'IT', 'Employee', 'active', NOW())");
    $stmt->execute([$uniqueId, $name, $phone, strtolower($name) . '@example.com']);
    return $pdo->lastInsertId();
}

function assignShift($pdo, $userId, $shiftId)
{
    $pdo->prepare("DELETE FROM user_shifts WHERE user_id = ?")->execute([$userId]);
    $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift_id, weekly_offs, effective_from, created_at) VALUES (?, ?, '', CURDATE(), NOW())");
    $stmt->execute([$userId, $shiftId]);
}

function clearAlertLog($pdo, $userId)
{
    $pdo->prepare("DELETE FROM daily_alert_logs WHERE user_id = ? AND date = CURDATE()")->execute([$userId]);
}

// ==========================================
// SCENARIO 1: MISSING PUNCH IN
// ==========================================
echo "--- Scenario 1: Missing Punch In ---\n";

// 1. Create User
$testUserName1 = "TestUser_NoPunchIn";
$userId1 = createTestUser($pdo, $testUserName1, '919999999991');
echo "[✓] Created test user: $testUserName1 (ID: $userId1)\n";

// 2. Assign Shift (Started 2 hours ago, so > 90 mins grace)
$startT = date('H:i:s', strtotime('-2 hours'));
$endT = date('H:i:s', strtotime('+6 hours'));
$shiftId1 = getOrCreateTestShift($pdo, $startT, $endT);
assignShift($pdo, $userId1, $shiftId1);
echo "[✓] Assigned shift: Start $startT, End $endT\n";

// 3. Ensure NO attendance record
$pdo->prepare("DELETE FROM attendance WHERE user_id = ? AND date = CURDATE()")->execute([$userId1]);
clearAlertLog($pdo, $userId1);
echo "[✓] Cleared attendance and alert logs\n";

// ==========================================
// SCENARIO 2: MISSING PUNCH OUT
// ==========================================
echo "\n--- Scenario 2: Missing Punch Out ---\n";

// 1. Create User
$testUserName2 = "TestUser_NoPunchOut";
$userId2 = createTestUser($pdo, $testUserName2, '919999999992');
echo "[✓] Created test user: $testUserName2 (ID: $userId2)\n";

// 2. Assign Shift (Ended 2 hours ago, so > 90 mins grace)
// Start 10 hours ago, End 2 hours ago
$startT2 = date('H:i:s', strtotime('-10 hours'));
$endT2 = date('H:i:s', strtotime('-2 hours'));
$shiftId2 = getOrCreateTestShift($pdo, $startT2, $endT2);
assignShift($pdo, $userId2, $shiftId2);
echo "[✓] Assigned shift: Start $startT2, End $endT2\n";

// 3. Insert Attendance (Punch In exists, Punch Out missing)
$pdo->prepare("DELETE FROM attendance WHERE user_id = ? AND date = CURDATE()")->execute([$userId2]);
clearAlertLog($pdo, $userId2);

// Punch in at shift start
$punchInTime = date('H:i:s', strtotime('-10 hours'));
$stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, punch_in, status, created_at) VALUES (?, CURDATE(), ?, 'Present', NOW())");
$stmt->execute([$userId2, $punchInTime]);
echo "[✓] Inserted attendance: Punch In at $punchInTime, No Punch Out\n";

// ==========================================
// RUN THE CRON SCRIPT
// ==========================================
echo "\n[>>>] executing cron_missing_punch_alerts.php...\n";
echo "-----------------------------------------------------\n";

// Capture output
ob_start();
include __DIR__ . '/cron_missing_punch_alerts.php';
$output = ob_get_clean();
echo $output;
echo "-----------------------------------------------------\n";

// ==========================================
// VERIFY RESULTS
// ==========================================
echo "\n--- Verification ---\n";

// Check Scenario 1
$stmt = $pdo->prepare("SELECT * FROM daily_alert_logs WHERE user_id = ? AND date = CURDATE() AND alert_type = 'missing_punch_in'");
$stmt->execute([$userId1]);
$log1 = $stmt->fetch();

if ($log1) {
    echo "[PASS] Scenario 1: 'missing_punch_in' alert logged for $testUserName1.\n";
} else {
    echo "[FAIL] Scenario 1: Alert NOT found for $testUserName1.\n";
}

// Check Scenario 2
$stmt = $pdo->prepare("SELECT * FROM daily_alert_logs WHERE user_id = ? AND date = CURDATE() AND alert_type = 'missing_punch_out'");
$stmt->execute([$userId2]);
$log2 = $stmt->fetch();

if ($log2) {
    echo "[PASS] Scenario 2: 'missing_punch_out' alert logged for $testUserName2.\n";
} else {
    echo "[FAIL] Scenario 2: Alert NOT found for $testUserName2.\n";
}

// ==========================================
// CLEANUP
// ==========================================
echo "\n--- Cleanup ---\n";
// Uncomment to keep data for inspection if needed
$cleanup = true;

if ($cleanup) {
    // Delete child records first
    $pdo->prepare("DELETE FROM attendance WHERE user_id IN (?, ?)")->execute([$userId1, $userId2]);
    $pdo->prepare("DELETE FROM user_shifts WHERE user_id IN (?, ?)")->execute([$userId1, $userId2]);
    $pdo->prepare("DELETE FROM daily_alert_logs WHERE user_id IN (?, ?)")->execute([$userId1, $userId2]);
    // Then delete users
    $pdo->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$userId1, $userId2]);
    // We usually keep the shifts as they might be reused or benign
    echo "[✓] Test users and data removed.\n";
} else {
    echo "[!] Cleanup skipped.\n";
}

echo "\n===== TEST COMPLETED =====\n";
