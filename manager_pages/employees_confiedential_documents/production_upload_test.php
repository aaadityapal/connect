<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/db_connect.php';

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$allowedRoles = ['admin', 'hr', 'manager', 'superadmin'];
if (!in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$employees = [];
try {
    $empStmt = $pdo->query("SELECT id, username, email, status FROM users ORDER BY username ASC");
    $employees = $empStmt ? $empStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $employees = [];
}

function parseIniBytes($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $num = (float)$value;

    switch ($unit) {
        case 'g':
            return (int)($num * 1024 * 1024 * 1024);
        case 'm':
            return (int)($num * 1024 * 1024);
        case 'k':
            return (int)($num * 1024);
        default:
            return (int)$num;
    }
}

$checks = [];
$checks[] = ['label' => 'PHP Version', 'ok' => true, 'value' => PHP_VERSION];
$checks[] = ['label' => 'fileinfo extension loaded', 'ok' => extension_loaded('fileinfo'), 'value' => extension_loaded('fileinfo') ? 'Yes' : 'No'];
$checks[] = ['label' => 'upload_max_filesize', 'ok' => true, 'value' => ini_get('upload_max_filesize')];
$checks[] = ['label' => 'post_max_size', 'ok' => true, 'value' => ini_get('post_max_size')];
$checks[] = ['label' => 'max_file_uploads', 'ok' => true, 'value' => ini_get('max_file_uploads')];
$checks[] = ['label' => 'memory_limit', 'ok' => true, 'value' => ini_get('memory_limit')];

$uploadMax = parseIniBytes((string)ini_get('upload_max_filesize'));
$postMax = parseIniBytes((string)ini_get('post_max_size'));
$checks[] = [
    'label' => 'Limit consistency (post_max_size >= upload_max_filesize)',
    'ok' => ($postMax >= $uploadMax),
    'value' => ($postMax >= $uploadMax) ? 'OK' : 'Mismatch'
];

$tmpDir = sys_get_temp_dir();
$checks[] = ['label' => 'Temporary upload directory exists', 'ok' => is_dir($tmpDir), 'value' => $tmpDir];
$checks[] = ['label' => 'Temporary upload directory writable', 'ok' => is_writable($tmpDir), 'value' => is_writable($tmpDir) ? 'Yes' : 'No'];

$uploadBase = realpath(__DIR__ . '/../../uploads');
if ($uploadBase === false) {
    $uploadBase = __DIR__ . '/../../uploads';
}
$employeeDocsBase = rtrim($uploadBase, '/\\') . '/employee_confiedential_documents';

$employeeDocsBaseExists = is_dir($employeeDocsBase);
$employeeDocsBaseWritable = $employeeDocsBaseExists ? is_writable($employeeDocsBase) : is_writable(dirname($employeeDocsBase));

$checks[] = ['label' => 'employee_confiedential_documents folder exists', 'ok' => $employeeDocsBaseExists, 'value' => $employeeDocsBase];
$checks[] = ['label' => 'employee_confiedential_documents folder writable/creatable', 'ok' => $employeeDocsBaseWritable, 'value' => $employeeDocsBaseWritable ? 'Yes' : 'No'];

$requiredTables = ['employee_confiedential_documents', 'confiedential_document_permissions', 'global_activity_logs'];
foreach ($requiredTables as $tableName) {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $tableName]);
        $exists = (bool)$stmt->fetchColumn();
        $checks[] = ['label' => 'Table exists: ' . $tableName, 'ok' => $exists, 'value' => $exists ? 'Yes' : 'No'];
    } catch (Throwable $e) {
        $checks[] = ['label' => 'Table exists: ' . $tableName, 'ok' => false, 'value' => 'Check failed'];
    }
}

$requiredColumns = [
    'employee_id', 'uploaded_by', 'document_type_key', 'document_type_label', 'document_name',
    'document_date', 'expiry_date', 'visibility_mode', 'visibility_user_ids', 'notes',
    'file_original_name', 'file_stored_name', 'file_path', 'file_size', 'file_mime'
];

try {
    $colStmt = $pdo->query('SHOW COLUMNS FROM employee_confiedential_documents');
    $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $present = [];
    foreach ($cols as $col) {
        $name = trim((string)($col['Field'] ?? ''));
        if ($name !== '') {
            $present[$name] = true;
        }
    }

    foreach ($requiredColumns as $colName) {
        $ok = isset($present[$colName]);
        $checks[] = ['label' => 'Column exists: employee_confiedential_documents.' . $colName, 'ok' => $ok, 'value' => $ok ? 'Yes' : 'No'];
    }
} catch (Throwable $e) {
    $checks[] = ['label' => 'Column check: employee_confiedential_documents', 'ok' => false, 'value' => 'Check failed'];
}

$defaultEmployeeId = 0;
if (!empty($employees)) {
    $defaultEmployeeId = (int)($employees[0]['id'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Upload Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f7fb;
            color: #0f172a;
        }
        .wrap {
            max-width: 980px;
            margin: 24px auto;
            padding: 0 16px;
        }
        .card {
            background: #fff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .card h2 {
            margin: 0;
            padding: 12px 14px;
            background: #eef4fb;
            border-bottom: 1px solid #dbe3ee;
            font-size: 16px;
        }
        .card .body {
            padding: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            border-bottom: 1px solid #e7edf4;
            padding: 8px;
            font-size: 13px;
            vertical-align: top;
        }
        .ok {
            color: #166534;
            font-weight: 700;
        }
        .bad {
            color: #b91c1c;
            font-weight: 700;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field.full {
            grid-column: 1 / -1;
        }
        input, select, textarea, button {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px;
            font-size: 13px;
            font-family: inherit;
        }
        button {
            cursor: pointer;
            background: #0f766e;
            color: #fff;
            border-color: #0f766e;
            font-weight: 700;
        }
        pre {
            margin: 0;
            background: #0b1220;
            color: #dbeafe;
            padding: 10px;
            border-radius: 8px;
            overflow: auto;
            max-height: 340px;
            font-size: 12px;
        }
        .muted {
            color: #64748b;
            font-size: 12px;
        }
        @media (max-width: 860px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2>Production Upload Diagnostics</h2>
        <div class="body">
            <p class="muted">Use this page in production to identify why upload API responses are not valid JSON.</p>
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$check['label']); ?></td>
                        <td class="<?php echo !empty($check['ok']) ? 'ok' : 'bad'; ?>">
                            <?php echo !empty($check['ok']) ? 'PASS' : 'FAIL'; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$check['value']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Live Upload API Test</h2>
        <div class="body">
            <form id="diagUploadForm" enctype="multipart/form-data">
                <div class="grid">
                    <label class="field">
                        <span>Employee</span>
                        <select name="employee_id" required>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int)($emp['id'] ?? 0); ?>" <?php echo ((int)($emp['id'] ?? 0) === $defaultEmployeeId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)($emp['username'] ?? 'User')); ?> (ID: <?php echo (int)($emp['id'] ?? 0); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Document Type</span>
                        <select name="document_type" required>
                            <option value="salary-slip">Salary Slip</option>
                            <option value="joining-letter">Joining Letter</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Document Name</span>
                        <input type="text" name="document_name" value="Production Upload Test" required>
                    </label>

                    <label class="field">
                        <span>Document Date</span>
                        <input type="date" name="document_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </label>

                    <label class="field">
                        <span>Expiry Date</span>
                        <input type="date" name="expiry_date">
                    </label>

                    <label class="field">
                        <span>Visibility</span>
                        <select name="visibility_mode">
                            <option value="all" selected>To all</option>
                            <option value="specific_users">Specific users</option>
                        </select>
                    </label>

                    <label class="field full">
                        <span>Visibility User IDs (comma separated)</span>
                        <input type="text" name="visibility_user_ids" placeholder="12,18,27">
                    </label>

                    <label class="field full">
                        <span>Notes</span>
                        <textarea name="notes" rows="2">Production diagnostic upload</textarea>
                    </label>

                    <label class="field full">
                        <span>Document File</span>
                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    </label>

                    <div class="field full">
                        <button type="submit">Run Upload Test</button>
                        <span class="muted">This sends a real request to api/upload_employee_document.php and shows raw response + JSON parsing result.</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Response Capture</h2>
        <div class="body">
            <pre id="responseBox">No request yet.</pre>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('diagUploadForm');
    const responseBox = document.getElementById('responseBox');

    function print(data) {
        responseBox.textContent = data;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const fd = new FormData(form);
        const visibilityMode = String(fd.get('visibility_mode') || 'all');
        if (visibilityMode !== 'specific_users') {
            fd.set('visibility_user_ids', '');
        }

        print('Running upload test...');

        try {
            const res = await fetch('api/upload_employee_document.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            const text = await res.text();
            let parsed = null;
            let parseError = null;
            try {
                parsed = JSON.parse(text);
            } catch (err) {
                parseError = err && err.message ? err.message : String(err);
            }

            const details = {
                request: {
                    endpoint: 'api/upload_employee_document.php',
                    method: 'POST'
                },
                response: {
                    status: res.status,
                    ok: res.ok,
                    contentType: res.headers.get('content-type') || '',
                    contentLength: res.headers.get('content-length') || '',
                    bodyLength: text.length
                },
                jsonParse: {
                    success: parseError === null,
                    error: parseError
                },
                json: parsed,
                rawBody: text
            };

            print(JSON.stringify(details, null, 2));
        } catch (networkError) {
            print(JSON.stringify({
                networkError: networkError && networkError.message ? networkError.message : String(networkError)
            }, null, 2));
        }
    });
})();
</script>
</body>
</html>
