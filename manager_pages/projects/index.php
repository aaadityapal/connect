<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/db_connect.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function getProjectPermissionFlags(PDO $pdo, int $userId): array {
    if (!tableExists($pdo, 'project_permissions')) {
        return [
            'can_create_project' => false,
            'can_upload_substage_media' => false
        ];
    }

    $colsStmt = $pdo->query('SHOW COLUMNS FROM project_permissions');
    $availableCols = [];
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (!empty($col['Field'])) {
            $availableCols[$col['Field']] = true;
        }
    }

    $createExpr = isset($availableCols['can_create_project']) ? 'can_create_project' : '0 AS can_create_project';
    $mediaExpr = isset($availableCols['can_upload_substage_media']) ? 'can_upload_substage_media' : '0 AS can_upload_substage_media';

    $stmt = $pdo->prepare("SELECT {$createExpr}, {$mediaExpr} FROM project_permissions WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'can_create_project' => isset($row['can_create_project']) && (int)$row['can_create_project'] === 1,
        'can_upload_substage_media' => isset($row['can_upload_substage_media']) && (int)$row['can_upload_substage_media'] === 1
    ];
}

$username = 'Manager';
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!empty($row['username'])) {
    $username = $row['username'];
}

$projectPermissionFlags = getProjectPermissionFlags($pdo, (int)$_SESSION['user_id']);
$canCreateProject = $projectPermissionFlags['can_create_project'];
$canUploadSubstageMedia = $projectPermissionFlags['can_upload_substage_media'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body class="projects-page">
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="page-header">
                <div class="header-left">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div>
                        <h1>Projects</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($username); ?>.</p>
                    </div>
                </div>
                <?php if ($canCreateProject): ?>
                    <button class="btn-primary" type="button" id="openProjectModal">
                        <i data-lucide="plus" style="width:16px;height:16px;"></i>
                        New Project
                    </button>
                <?php endif; ?>
            </header>

            <section class="kpi-grid">
                <article class="kpi-card">
                    <span class="kpi-label">Total Projects</span>
                    <h2>24</h2>
                    <small class="kpi-up">+4 this month</small>
                </article>
                <article class="kpi-card">
                    <span class="kpi-label">In Progress</span>
                    <h2>11</h2>
                    <small>5 near deadline</small>
                </article>
                <article class="kpi-card">
                    <span class="kpi-label">Completed</span>
                    <h2>09</h2>
                    <small>On-time: 87%</small>
                </article>
                <article class="kpi-card">
                    <span class="kpi-label">At Risk</span>
                    <h2>04</h2>
                    <small class="kpi-down">Needs review</small>
                </article>
            </section>

            <section class="toolbar-card">
                <div class="search-box">
                    <i data-lucide="search" style="width:16px;height:16px;"></i>
                    <input type="text" id="projectSearch" placeholder="Search by project name...">
                </div>
                <div class="toolbar-actions">
                    <select id="categoryFilter">
                        <option value="all">All Projects</option>
                        <option value="architecture">Architecture</option>
                        <option value="interior">Interior</option>
                        <option value="construction">Construction</option>
                    </select>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="in progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="planning">Planning</option>
                        <option value="blocked">Blocked</option>
                    </select>
                    <button type="button" class="btn-outline" id="resetFilters">Reset</button>
                </div>
            </section>

            <section class="projects-grid" id="projectsGrid"></section>
            <div class="pagination-wrap" id="paginationContainer"></div>
        </main>
    </div>

    <div class="modal-overlay" id="projectDetailsModal" style="display:none;">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="projectDetailsTitle">
            <div class="modal-head">
                <h3 id="projectDetailsTitle">Project Details</h3>
                <button type="button" class="modal-close-btn" id="closeProjectDetailsModal" aria-label="Close details">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            </div>
            <div class="modal-body" id="projectDetailsBody"></div>
        </div>
    </div>

    <div class="modal-overlay" id="substageUploadModal" style="display:none;">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="substageUploadTitle" style="width:min(560px,100%);">
            <div class="modal-head">
                <h3 id="substageUploadTitle">Upload Substage File</h3>
                <button type="button" class="modal-close-btn" id="closeSubstageUploadModal" aria-label="Close upload modal">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="substageUploadForm" class="project-edit-form">
                    <input type="hidden" id="uploadSubstageId" name="substageId" value="">
                    <section class="detail-block">
                        <p class="detail-section-heading"><i data-lucide="upload" style="width:14px;height:14px;"></i> Media Upload</p>
                        <div class="edit-grid edit-grid-4">
                            <label class="edit-span-4">Substage
                                <input type="text" class="edit-input" id="uploadSubstageName" value="" readonly>
                            </label>
                            <label class="edit-span-4">Media File Name
                                <input type="text" class="edit-input" id="uploadMediaName" name="fileName" placeholder="Enter media file name" required>
                            </label>
                            <label class="edit-span-4">Upload File
                                <input type="file" class="edit-input" id="uploadMediaFile" name="file" required>
                            </label>
                        </div>
                    </section>

                    <div id="substageUploadLoader" class="upload-loader" style="display:none;">
                        <span class="upload-loader-spinner" aria-hidden="true"></span>
                        <span>Uploading file to server, please wait...</span>
                    </div>

                    <div class="modal-form-actions">
                        <button type="button" class="btn-outline" id="cancelSubstageUploadBtn">Cancel</button>
                        <button type="submit" class="btn-primary" id="submitSubstageUploadBtn">
                            <i data-lucide="upload" style="width:14px;height:14px;"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modalContainer"></div>

    <script src="js/script.js"></script>
    <script>
        window.PROJECT_FORM_BASE_PATH = '../../';
        window.CURRENT_USER_ID = <?php echo (int)$_SESSION['user_id']; ?>;
        window.CAN_CREATE_PROJECT = <?php echo $canCreateProject ? 'true' : 'false'; ?>;
        window.CAN_UPLOAD_SUBSTAGE_MEDIA = <?php echo $canUploadSubstageMedia ? 'true' : 'false'; ?>;
        window.__PROJECT_FORM_HANDLER_READY = null;

        document.addEventListener('DOMContentLoaded', function () {
            const openBtn = document.getElementById('openProjectModal');
            const modalContainer = document.getElementById('modalContainer');
            let modalLoaded = false;
            let fallbackBound = false;
            let fallbackUsers = [];

            function loadScriptOnce(selector, src, dataAttr) {
                const existing = document.querySelector(selector);
                if (existing) {
                    if (existing.dataset.loaded === '1') {
                        return Promise.resolve();
                    }
                    return new Promise((resolve, reject) => {
                        existing.addEventListener('load', () => resolve(), { once: true });
                        existing.addEventListener('error', () => reject(new Error('Failed to load script: ' + src)), { once: true });
                    });
                }

                return new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = src;
                    script.setAttribute(dataAttr, '1');
                    script.addEventListener('load', () => {
                        script.dataset.loaded = '1';
                        resolve();
                    }, { once: true });
                    script.addEventListener('error', () => reject(new Error('Failed to load script: ' + src)), { once: true });
                    document.body.appendChild(script);
                });
            }

            function ensureProjectAssets() {
                if (!document.querySelector('link[data-project-form-style="1"]')) {
                    const style = document.createElement('link');
                    style.rel = 'stylesheet';
                    style.href = '../../modals/styles/project_form_styles_v1.css';
                    style.setAttribute('data-project-form-style', '1');
                    document.head.appendChild(style);
                }

                return Promise.all([
                    loadScriptOnce('script[data-project-form-handler="1"]', '../../modals/scripts/project_form_handler_v1.js', 'data-project-form-handler'),
                    loadScriptOnce('script[data-project-form-stage-fix="1"]', '../../modals/scripts/stage_fix.js', 'data-project-form-stage-fix'),
                    loadScriptOnce('script[data-project-form-direct-assign-fix="1"]', '../../modals/scripts/direct_assign_fix.js', 'data-project-form-direct-assign-fix')
                ]);
            }

            async function loadFallbackUsers() {
                try {
                    const res = await fetch('../../api/get_users.php', { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data && data.status === 'success' && Array.isArray(data.data)) {
                        fallbackUsers = data.data;
                    }
                } catch (e) {
                    console.warn('Fallback users load failed:', e);
                    fallbackUsers = [];
                }
            }

            function getFallbackUserOptionsHtml() {
                const options = fallbackUsers.map((u) => `<option value="${u.id}">${u.username} - ${u.role || ''}</option>`).join('');
                return `<option value="0" selected>Unassigned</option>${options}`;
            }

            function renumberFallbackSubstages(stageBlock) {
                if (!stageBlock) return;
                stageBlock.querySelectorAll('.substage-block').forEach((sub, idx) => {
                    const n = idx + 1;
                    const h = sub.querySelector('.substage-header h4');
                    if (h) h.textContent = `Task ${n}`;
                    sub.dataset.substage = String(n);
                });
            }

            function bindProjectModalFallbackIfNeeded() {
                if (fallbackBound || window.__PROJECT_FORM_HANDLER_READY) return;

                const modal = document.getElementById('projectModal');
                const stagesContainer = document.getElementById('stagesContainer');
                if (!modal || !stagesContainer) return;

                fallbackBound = true;

                const assignTo = document.getElementById('assignTo');
                const backOfficeAssignTo = document.getElementById('backOfficeAssignTo');
                const userOptionsHtml = getFallbackUserOptionsHtml();
                if (assignTo) assignTo.innerHTML = userOptionsHtml;
                if (backOfficeAssignTo) backOfficeAssignTo.innerHTML = userOptionsHtml;

                let stageCount = stagesContainer.querySelectorAll('.stage-block').length;

                modal.addEventListener('click', function (event) {
                    const closeBtn = event.target.closest('#closeModal, #cancelProject, #cancelBackOffice');
                    if (closeBtn) {
                        modal.classList.remove('active');
                        setTimeout(() => { modal.style.display = 'none'; }, 200);
                        return;
                    }

                    const addStageBtn = event.target.closest('#addStageBtn, #backOfficeAddStageBtn');
                    if (addStageBtn) {
                        stageCount += 1;
                        const stageNum = stageCount;
                        const stageEl = document.createElement('div');
                        stageEl.className = 'stage-block';
                        stageEl.dataset.stage = String(stageNum);
                        stageEl.innerHTML = `
                            <div class="stage-header">
                                <h3>Stage ${stageNum}</h3>
                                <button type="button" class="delete-stage"><i class="fas fa-trash"></i></button>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user-plus"></i> Assign To</label>
                                <select id="assignTo${stageNum}">${userOptionsHtml}</select>
                            </div>
                            <div class="form-dates">
                                <div class="form-group"><label><i class="fas fa-calendar-plus"></i> Start Date & Time</label><input type="datetime-local" id="startDate${stageNum}"></div>
                                <div class="form-group"><label><i class="fas fa-calendar-check"></i> Due By</label><input type="datetime-local" id="dueDate${stageNum}"></div>
                            </div>
                            <div class="form-substages-container" style="display:block;"></div>
                            <button type="button" class="add-substage-btn"><i class="fas fa-plus"></i> Add Substage</button>
                        `;
                        stagesContainer.appendChild(stageEl);
                        return;
                    }

                    const deleteStageBtn = event.target.closest('.delete-stage');
                    if (deleteStageBtn) {
                        const stageEl = deleteStageBtn.closest('.stage-block');
                        if (stageEl) stageEl.remove();
                        stagesContainer.querySelectorAll('.stage-block').forEach((s, idx) => {
                            const n = idx + 1;
                            s.dataset.stage = String(n);
                            const h = s.querySelector('.stage-header h3');
                            if (h) h.textContent = `Stage ${n}`;
                        });
                        stageCount = stagesContainer.querySelectorAll('.stage-block').length;
                        return;
                    }

                    const addSubstageBtn = event.target.closest('.add-substage-btn');
                    if (addSubstageBtn) {
                        const stageEl = addSubstageBtn.closest('.stage-block');
                        const stageNum = Number(stageEl?.dataset.stage || 0);
                        const subContainer = stageEl?.querySelector('.form-substages-container');
                        if (!stageNum || !subContainer) return;
                        const subNum = subContainer.querySelectorAll('.substage-block').length + 1;
                        const subEl = document.createElement('div');
                        subEl.className = 'substage-block';
                        subEl.dataset.substage = String(subNum);
                        subEl.innerHTML = `
                            <div class="substage-header">
                                <h4>Task ${subNum}</h4>
                                <button type="button" class="delete-substage"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="form-group"><label><i class="fas fa-tasks"></i> Substage Title</label><input type="text"></div>
                            <div class="form-group"><label><i class="fas fa-user-plus"></i> Assign To</label><select>${userOptionsHtml}</select></div>
                            <div class="form-dates">
                                <div class="form-group"><label><i class="fas fa-calendar-plus"></i> Start Date & Time</label><input type="datetime-local"></div>
                                <div class="form-group"><label><i class="fas fa-calendar-check"></i> Due By</label><input type="datetime-local"></div>
                            </div>
                        `;
                        subContainer.appendChild(subEl);
                        return;
                    }

                    const deleteSubstageBtn = event.target.closest('.delete-substage');
                    if (deleteSubstageBtn) {
                        const subEl = deleteSubstageBtn.closest('.substage-block');
                        const stageEl = deleteSubstageBtn.closest('.stage-block');
                        if (subEl) subEl.remove();
                        renumberFallbackSubstages(stageEl);
                    }
                });

                console.warn('Project modal fallback bindings activated');
            }

            function loadProjectModal() {
                if (modalLoaded) return Promise.resolve();

                return fetch('../../modals/project_form.php')
                    .then(response => response.text())
                    .then(html => {
                        const sanitizedHtml = html
                            .replace(/<script[^>]*src=["']modals\/scripts\/stage_fix\.js["'][^>]*><\/script>/gi, '')
                            .replace(/<script[^>]*src=["']modals\/scripts\/direct_assign_fix\.js["'][^>]*><\/script>/gi, '');

                        modalContainer.innerHTML = sanitizedHtml;
                        modalLoaded = true;
                        return ensureProjectAssets();
                    })
                    .catch(error => {
                        console.error('Error loading project modal:', error);
                    });
            }

            if (openBtn) {
                openBtn.addEventListener('click', function () {
                    loadProjectModal().then(() => {
                        const projectModal = document.getElementById('projectModal');
                        if (!projectModal) return;
                        projectModal.style.display = 'flex';
                        setTimeout(() => projectModal.classList.add('active'), 10);

                        if (typeof window.initializeProjectTitleAutocomplete === 'function') {
                            window.initializeProjectTitleAutocomplete();
                        }
                        if (typeof window.bindProjectSuggestionClicks === 'function') {
                            window.bindProjectSuggestionClicks();
                        }
                        if (typeof window.fetchProjectSuggestions === 'function') {
                            window.fetchProjectSuggestions();
                        }

                        setTimeout(async () => {
                            if (window.__PROJECT_FORM_HANDLER_READY === false) {
                                await loadFallbackUsers();
                                bindProjectModalFallbackIfNeeded();
                            }
                        }, 2000);
                    });
                });
            }
        });
    </script>
</body>
</html>
