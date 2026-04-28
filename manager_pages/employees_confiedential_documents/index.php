<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'Manager';

require_once '../../config/db_connect.php';

$stmt = $pdo->prepare("SELECT id, username, email, role, department, status, profile_picture, joining_date FROM users ORDER BY username ASC");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confidentialStats = [
    'totalEmployees' => count($employees),
    'docsUploaded' => 0,
    'pendingVerification' => 0,
    'expiredDocs' => 0,
];

function employeeAvatarUrl($path) {
    $raw = trim((string)$path);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $raw) || strpos($raw, '/') === 0) {
        return $raw;
    }
    $clean = preg_replace('/^(\.\/)+/', '', $raw);
    $clean = preg_replace('/^(\.\.\/)+/', '', $clean);
    $clean = ltrim($clean, '/');
    return '../../' . $clean;
}

function avatarTone($name) {
    $colors = ['#0f766e', '#1d4ed8', '#b45309', '#be123c', '#4f46e5', '#166534', '#0369a1', '#6d28d9'];
    $name = trim((string)$name);
    $firstChar = $name !== '' ? $name[0] : 'A';
    return $colors[ord($firstChar) % count($colors)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Confiedential Documents | Connect</title>
    <meta name="description" content="Manage company employees confidential documents.">

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="modal/upload_document_modal.css">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="page-header">
                <div class="header-left">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div>
                        <h1>Employees Confiedential Documents</h1>
                        <p>Securely organize, track, and audit employee-sensitive files.</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-secondary" id="bulkReminderBtn">
                        <i data-lucide="mail-warning" style="width:16px;height:16px;"></i>
                        <span>Send Pending Reminder</span>
                    </button>
                    <button class="btn-primary" id="uploadDocumentBtn">
                        <i data-lucide="file-up" style="width:16px;height:16px;"></i>
                        <span>Upload Document</span>
                    </button>
                </div>
            </header>

            <section class="stats-grid">
                <article class="stat-card">
                    <div class="stat-icon"><i data-lucide="users" style="width:18px;height:18px;"></i></div>
                    <div>
                        <p>Total Employees</p>
                        <h3><?php echo (int)$confidentialStats['totalEmployees']; ?></h3>
                    </div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon"><i data-lucide="files" style="width:18px;height:18px;"></i></div>
                    <div>
                        <p>Docs Uploaded</p>
                        <h3><?php echo (int)$confidentialStats['docsUploaded']; ?></h3>
                    </div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon"><i data-lucide="shield-alert" style="width:18px;height:18px;"></i></div>
                    <div>
                        <p>Pending Verification</p>
                        <h3><?php echo (int)$confidentialStats['pendingVerification']; ?></h3>
                    </div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon"><i data-lucide="calendar-x-2" style="width:18px;height:18px;"></i></div>
                    <div>
                        <p>Expired Docs</p>
                        <h3><?php echo (int)$confidentialStats['expiredDocs']; ?></h3>
                    </div>
                </article>
            </section>

            <section class="workspace-card">
                <div class="toolbar">
                    <div class="search-box">
                        <i data-lucide="search" style="width:15px;height:15px;"></i>
                        <input type="text" id="employeeSearch" placeholder="Search by name, email or role">
                    </div>
                    <div class="toolbar-actions">
                        <select id="statusFilter">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Role</th>
                                <th>Joining Date</th>
                                <th>Toggle</th>
                            </tr>
                        </thead>
                        <tbody id="employeeRows">
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="4" class="empty-row">No employees found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp):
                                    $employeeId = (int)($emp['id'] ?? 0);
                                    $name = trim((string)($emp['username'] ?? 'Employee'));
                                    $initial = strtoupper(substr($name !== '' ? $name : 'E', 0, 1));
                                    $avatarUrl = employeeAvatarUrl($emp['profile_picture'] ?? '');
                                    $status = strtolower(trim((string)($emp['status'] ?? 'inactive')));
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($status); ?>" data-employee-row="<?php echo $employeeId; ?>" data-employee-name="<?php echo htmlspecialchars($name); ?>">
                                    <td>
                                        <div class="employee-cell">
                                            <div class="avatar" style="background: <?php echo avatarTone($name); ?>">
                                                <?php if ($avatarUrl): ?>
                                                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($name); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <span class="fallback" style="display:none;"><?php echo htmlspecialchars($initial); ?></span>
                                                <?php else: ?>
                                                    <span class="fallback"><?php echo htmlspecialchars($initial); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                                <small><?php echo htmlspecialchars((string)($emp['email'] ?? '')); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($emp['role'] ?? 'N/A')); ?></td>
                                    <td><?php echo !empty($emp['joining_date']) ? date('d M Y', strtotime((string)$emp['joining_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="expand-btn" type="button" data-toggle-row="<?php echo $employeeId; ?>" aria-expanded="false" aria-controls="expand-row-<?php echo $employeeId; ?>">
                                            <i data-lucide="chevron-down" style="width:16px;height:16px;"></i>
                                            <span>Documents</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="expand-row" id="expand-row-<?php echo $employeeId; ?>" data-expand-content="<?php echo $employeeId; ?>" hidden>
                                    <td colspan="4">
                                        <div class="doc-expand-card">
                                            <div class="doc-expand-head">
                                                <h4>Employee Document Vault</h4>
                                                <button class="tab-upload-btn js-upload-doc" type="button" data-employee-id="<?php echo $employeeId; ?>" data-employee-name="<?php echo htmlspecialchars($name); ?>">
                                                    <i data-lucide="upload" style="width:14px;height:14px;"></i>
                                                    <span>Upload Document</span>
                                                </button>
                                            </div>

                                            <div class="doc-tabs" role="tablist" aria-label="Employee documents" data-doc-tabs></div>
                                            <div class="doc-panels" data-doc-panels></div>
                                            <div class="doc-empty-state" data-doc-empty>No documents uploaded yet. Click Upload Document to add the first file.</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/modal/upload_document_modal.php'; ?>

    <script src="script.js" defer></script>
    <script src="modal/upload_document_modal.js" defer></script>
</body>
</html>
