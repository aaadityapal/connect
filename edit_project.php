<?php
session_start();
require_once 'config/db_connect.php';

// RBAC: allow only HR and Senior Manager (Studio)
$allowed_roles = ['HR', 'Senior Manager (Studio)'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access Denied</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
    echo '<div class="container py-5"><div class="alert alert-danger"><strong>Access denied.</strong> You do not have permission to edit projects.</div></div>';
    echo '</body></html>';
    exit;
}

// Validate project id
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($projectId <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invalid Project</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
    echo '<div class="container py-5"><div class="alert alert-warning"><strong>Invalid project.</strong> A valid project ID is required.</div></div>';
    echo '</body></html>';
    exit;
}

// Fetch project details
$project = null;
$categories = [];
$users = [];
try {
    $stmt = $pdo->prepare("SELECT p.*, pc.name as category_name FROM projects p LEFT JOIN project_categories pc ON p.category_id = pc.id WHERE p.id = :id AND p.deleted_at IS NULL");
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Categories
    $categoriesStmt = $pdo->query("SELECT id, name FROM project_categories WHERE deleted_at IS NULL ORDER BY name");
    $categories = $categoriesStmt->fetchAll();

    // Users (assignees)
    $usersStmt = $pdo->query("SELECT id, username, role FROM users WHERE status = 'active' ORDER BY username");
    $users = $usersStmt->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
    echo '<div class="container py-5"><div class="alert alert-danger">Failed to load project: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Project #<?= htmlspecialchars($projectId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{
            --bg: #f6f7fb;
            --primary: #6366f1;
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --muted: #6b7280;
            --card-radius: 14px;
        }
        body { background: radial-gradient(1200px 600px at 100% -10%, rgba(99,102,241,.12), transparent 60%), var(--bg); }
        .card { border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-radius: var(--card-radius); }
        .card-header { background: #fff; border-bottom: 1px solid #eef0f4; }
        .btn-icon { display: inline-flex; align-items: center; gap: .4rem; }
        .required::after { content: ' *'; color: #dc3545; }
        .chip { display:inline-flex; align-items:center; gap:.35rem; padding:.15rem .5rem; border-radius:999px; background:#eef0f4; font-size:.78rem; color:#333; }
        .chip i { font-size:.9rem; color:#6b7280; }
        .stage-header { cursor: pointer; }
        .list-compact .list-group-item { padding: .5rem .75rem; }
        .list-compact .meta { font-size: .8rem; color: #6b7280; }
        .sticky-actions { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: .5rem 1rem; z-index: 1050; box-shadow: 0 -6px 18px rgba(0,0,0,0.05); }
        .container-has-sticky { padding-bottom: 72px; }
        .accordion-button { background:#fff; }
        .file-list { max-height: 220px; overflow: auto; }
        .badge-stage { background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .badge-substage { background: linear-gradient(90deg, var(--accent), #60a5fa); }
        .stage-card { border-radius: var(--card-radius); border: 1px solid #eef0f4; }
        .stage-card .card-header { border-top-left-radius: var(--card-radius); border-top-right-radius: var(--card-radius); }
        .stage-card .card-body { border-bottom-left-radius: var(--card-radius); border-bottom-right-radius: var(--card-radius); }
        .status-chip { color: #111827; background: #eef2ff; }
        .btn-outline-primary { border-color: var(--primary); color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); color: #fff; }
    </style>
</head>
<body>
    <div class="container-fluid py-4 container-has-sticky">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Project</h4>
            <a href="projects.php" class="btn btn-outline-secondary btn-icon"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="accordion" id="editAccordion">
            <div class="accordion-item mb-3 border-0">
                <h2 class="accordion-header" id="headingInfo">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInfo" aria-expanded="true" aria-controls="collapseInfo">
                        <i class="bi bi-info-circle me-2 text-primary"></i> Project Information
                    </button>
                </h2>
                <div id="collapseInfo" class="accordion-collapse collapse show" aria-labelledby="headingInfo" data-bs-parent="#editAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="projectTitle" placeholder="Title" value="<?= htmlspecialchars($project['title'] ?? '') ?>" />
                                    <label for="projectTitle" class="required">Title</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="projectType" aria-label="Project Type">
                                        <?php
                                            $currentType = strtolower($project['project_type'] ?? '');
                                            $typeOptions = ['Architecture','Interior','Construction'];
                                            foreach ($typeOptions as $opt) {
                                                $sel = ($currentType === strtolower($opt)) ? 'selected' : '';
                                                echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.$opt.'</option>';
                                            }
                                        ?>
                                    </select>
                                    <label for="projectType">Project Type</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="projectDescription" placeholder="Description" style="height: 90px;"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                                    <label for="projectDescription">Description</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="projectCategory" aria-label="Project Category">
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['id'] ?>" <?= ($project['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="projectCategory">Category</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="assignTo" aria-label="Assigned To">
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>" <?= ($project['assigned_to'] ?? null) == $u['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="assignTo">Assigned To</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="projectStatus" aria-label="Status">
                                        <?php
                                            $workflowStatuses = ['not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked'];
                                            $currentWorkflow = strtolower($project['status'] ?? 'not_started');
                                            foreach ($workflowStatuses as $s) {
                                                $sel = $currentWorkflow === $s ? 'selected' : '';
                                                $label = ucwords(str_replace('_',' ', $s));
                                                echo '<option value="'.htmlspecialchars($s).'" '.$sel.'>'.$label.'</option>';
                                            }
                                        ?>
                                    </select>
                                    <label for="projectStatus">Status</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="datetime-local" class="form-control" id="startDate" placeholder="Start" value="<?= !empty($project['start_date']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime($project['start_date']))) : '' ?>" />
                                    <label for="startDate">Start</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="datetime-local" class="form-control" id="dueDate" placeholder="End" value="<?= !empty($project['end_date']) ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime($project['end_date']))) : '' ?>" />
                                    <label for="dueDate">End</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="client_name" placeholder="Client Name" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>" />
                                    <label for="client_name">Client Name</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="contact_number" placeholder="Contact Number" value="<?= htmlspecialchars($project['contact_number'] ?? '') ?>" />
                                    <label for="contact_number">Contact Number</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="client_address" placeholder="Client Address" value="<?= htmlspecialchars($project['client_address'] ?? '') ?>" />
                                    <label for="client_address">Client Address</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="project_location" placeholder="Site Location" value="<?= htmlspecialchars($project['project_location'] ?? '') ?>" />
                                    <label for="project_location">Site Location</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="plot_area" placeholder="Plot Area" value="<?= htmlspecialchars($project['plot_area'] ?? '') ?>" />
                                    <label for="plot_area">Plot Area</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item mb-3 border-0">
                <h2 class="accordion-header" id="headingStages">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStages" aria-expanded="true" aria-controls="collapseStages">
                        <i class="bi bi-diagram-3 me-2 text-primary"></i> Stages & Substages
                    </button>
                </h2>
                <div id="collapseStages" class="accordion-collapse collapse show" aria-labelledby="headingStages" data-bs-parent="#editAccordion">
                    <div class="accordion-body">
                        <div id="stagesContainer">
                            <div class="text-muted">Loading stages…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky action bar -->
    <div class="sticky-actions d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            <span class="chip me-2"><i class="bi bi-hash"></i> Project #<?= htmlspecialchars($projectId) ?></span>
            <span id="projectStatusChip" class="chip status-chip"><i class="bi bi-circle-fill" style="font-size:.55rem"></i><span class="label"></span></span>
        </div>
        <div class="d-flex gap-2">
            <a href="projects.php" class="btn btn-outline-secondary btn-icon"><i class="bi bi-x-circle"></i> Cancel</a>
            <button id="saveProjectBtn" class="btn btn-primary btn-icon"><i class="bi bi-save"></i> Save Changes</button>
            <button id="saveProjectBtnBottom" class="btn btn-primary d-none">Save</button>
        </div>
    </div>

    <script>
    const PROJECT_ID = <?= json_encode($projectId) ?>;
    const USERS = <?= json_encode($users) ?>;

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = 'toast align-items-center text-white border-0 show position-fixed bottom-0 end-0 m-3';
        el.style.zIndex = 9999;
        el.style.background = type === 'success' ? '#198754' : (type === 'danger' ? '#dc3545' : '#0d6efd');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    const stagesState = [];

    // Architecture substage titles dataset: use global if provided; fallback to minimal local set
    const ARCH_TITLES = (window.PROJECT_SUBSTAGE_TITLES && window.PROJECT_SUBSTAGE_TITLES.architecture)
        || (window.projectSubstageTitles && window.projectSubstageTitles.architecture)
        || {
            'Concept Drawings': [
                'Concept Plan','PPT','3D Model'
            ],
            'Structure Drawings - All Floor': [
                'Excavation Layout Plan','Setting Layout Plan','Foundation Plan','Foundation Details','Column Layout Plan','Column Details','Footing Layout Plan','Plinth Beam Layout Plan','Ground Floor Roof Slab Beam Layout Plan','First Floor Roof Slab Beam Layout Plan','Terrace Roof Slab Beam Layout Plan'
            ],
            'Master Plan': [
                'Site Plan Layout','Boundary Wall Layout Plan','Boundary Wall Details','Site Plan Details','Site Plan Section','Site Plan Electrical Layout','Site Plan Plumbing Layout','Site Plan External Lighting Layout','Site Plan Internal Lighting Layout','Site Plan Landscaping'
            ],
            'Landscape Drawings - All Floor': [
                'Basement Landscape Layout Plan','Stilt Floor Landscape Layout Plan','Ground Floor Landscape Layout Plan','First Floor Landscape Layout Plan','Second Floor Landscape Layout Plan','Third Floor Landscape Layout Plan','Fourth Floor Ceiling Layout Plan','Fifth Floor Ceiling Layout Plan','Terrace Ceiling Layout Plan'
            ],
            'Architecture Working Drawings - All Floor': [
                'Basement Working Layout Plan','Ground Floor Working Layout Plan','First Floor Working Layout Plan','Second Floor Working Layout Plan','Third Floor Working Layout Plan','Fourth Floor Working Layout Plan','Fifth Floor Working Layout Plan','Terrace Working Layout Plan','Front Elevation Details','Side 1 Elevation Details','Section Elevations X-X','Site Plans'
            ],
            'Architecture Furniture Drawings - All Floor': [
                'Basement Furniture Layout Plan','Stilt Floor Furniture Layout Plan','Ground Floor Furniture Layout Plan','First Floor Furniture Layout Plan','Second Floor Furniture Layout Plan','Third Floor Furniture Layout Plan','Fourth Floor Furniture Layout Plan','Fifth Floor Furniture Layout Plan','Terrace Furniture Layout Plan'
            ],
            'Electrical Drawings Wall - All Floor': [
                'Basement Wall Electrical Layout','Stilt Floor Wall Electrical Layout','Ground Floor Wall Electrical Layout','First Floor Wall Electrical Layout','Second Floor Wall Electrical Layout','Third Floor Wall Electrical Layout','Fourth Floor Wall Electrical Layout','Fifth Floor Wall Electrical Layout','Terrace Wall Electrical Layout'
            ],
            'Electrical Drawings Ceiling - All Floor': [
                'Basement Ceiling Electrical Layout','Stilt Floor Ceiling Electrical Layout','Ground Floor Ceiling Electrical Layout','First Floor Ceiling Electrical Layout','Second Floor Ceiling Electrical Layout','Third Floor Ceiling Electrical Layout','Fourth Floor Ceiling Electrical Layout','Fifth Floor Ceiling Electrical Layout','Terrace Ceiling Electrical Layout'
            ],
            'Plumbing Drawings - All Floor': [
                'Basement Plumbing Layout Plan','Stilt Floor Plumbing Layout Plan','Ground Floor Plumbing Layout Plan','First Floor Plumbing Layout Plan','Second Floor Plumbing Layout Plan','Third Floor Plumbing Layout Plan','Fourth Floor Plumbing Layout Plan','Fifth Floor Plumbing Layout Plan','Terrace Plumbing Layout Plan'
            ],
            'Water Supply Drawings - All Floor': [
                'Basement Water Supply Layout Plan','Stilt Floor Water Supply Layout Plan','Ground Floor Water Supply Layout Plan','First Floor Water Supply Layout Plan','Second Floor Water Supply Layout Plan','Third Floor Water Supply Layout Plan','Fourth Floor Water Supply Layout Plan','Fifth Floor Water Supply Layout Plan','Terrace Water Supply Layout Plan'
            ],
            'Details Drawings': [
                'Staircase Details','Finishing Schedule','Ramp Details','Kitchen Details','Lift Details','Toilet Details','Saptic Tank Details','Compound Wall Details','Landscape Details','Slab Details','Roof Details','Wall Details','Floor Details','Ceiling Details','Door Details','Window Details'
            ],
            'Other Drawings': [
                'Site Plan','Front Elevation','Rear Elevation','Side Elevation','Section Elevation','Roof Plan','Floor Plan','Ceiling Plan','Door & Window Schedule','Finishing Schedule','Landscape Plan','Slab Plan','Roof Details','Wall Details','Floor Details','Ceiling Details'
            ]
        };
    // Interior substage titles dataset: use global if provided; fallback to minimal local set
    const INTERIOR_TITLES = (window.PROJECT_SUBSTAGE_TITLES && window.PROJECT_SUBSTAGE_TITLES.interior)
        || (window.projectSubstageTitles && window.projectSubstageTitles.interior)
        || {
            'Concept Design': [
                'Concept Plan', 'PPT', '3D Views', 'Render plan Basement', 'Render plan Stilt Floor', 'Render plan Ground Floor', 'Render plan First Floor', 'Render plan Second Floor', 'Render plan Third Floor', 'Render plan Fourth Floor'
            ],
            '3D Views': [
                'Daughters Bed Room','Sons Bed Room','Master Bed Room','Guest Bed Room','Toilet - 01','Toilet - 02','Toilet - 03','Toilet - 04','Toilet - 05','Prayer Room','Study Room','Home Theater','Kitchen','Dining Room','Living Room','GYM / Multi-purpose Room','Servant Room','Family Lounge','Staircase','Landscape Area','Recreation Area','Swimming Pool','Living & Dining Room','Living Room','Dining Room','Kitchen','Balcony - 01','Balcony - 02','Balcony - 03','Balcony - 04','Balcony - 05','Utility Area','Mumty False Ceiling Plan','Mumty','Front Elevation','Side 1 Elevation','Side 2 Elevation','Section Elevation X-X','Section Elevation Y-Y','Entrance Lobby','Manager Cabin','Work Station Area - 01','Work Station Area - 02','Work Station Area - 03','Work Station Area - 04','Work Station Area - 05','Work Station Area - 06','Reception Area','Conference Room','Meeting Room','Waiting Area','Lobby - 01','Lobby - 02','Lobby - 03'
            ],
            'Flooring Drawings': [
                'Flooring layout Plan Basement','Flooring layout Plan Stilt Floor','Flooring layout Plan Ground Floor','Flooring layout Plan First Floor','Flooring layout Plan Second Floor','Flooring layout Plan Third Floor','Flooring layout Plan Fourth Floor','Flooring Layout Plan Fifth Floor','Flooring layout Plan Terrace'
            ],
            'False Ceiling Drawings': [
                'False Ceiling Layout Plan Basement','False Ceiling Layout Plan Stilt Floor','False Ceiling Layout Plan Ground Floor','False Ceiling Layout Plan First Floor','False Ceiling Layout Plan Second Floor','False Ceiling Layout Plan Third Floor','False Ceiling Layout Plan Fourth Floor','False Ceiling Layout Plan Fifth Floor','False Ceiling Layout Plan Terrace','Master Bed Room False Ceiling','Daughters Bed Room False Ceiling','Sons Bed Room False Ceiling','Guest Bed Room False Ceiling','Toilet - 01 False Ceiling','Toilet - 02 False Ceiling','Toilet - 03 False Ceiling','Toilet - 04 False Ceiling','Toilet - 05 False Ceiling','Prayer Room False Ceiling','Study Room False Ceiling','Home Theater False Ceiling','Kitchen False Ceiling Layout Plan & Section Details','Dining Room False Ceiling Layout Plan & Section Details','Living Room False Ceiling Layout Plan & Section Details','GYM / Multi-purpose Room False Ceiling Layout Plan & Section Details','Servant Room False Ceiling Layout Plan & Section Details','Family Lounge False Ceiling Layout Plan & Section Details','Staircase False Ceiling Layout Plan & Section Details','Landscape Area False Ceiling Layout Plan & Section Details','Recreation Area False Ceiling','Office Space False Ceiling Layout Plan & Section Details','Conference Room False Ceiling Layout Plan & Section Details','Meeting Room False Ceiling Layout Plan & Section Details','Waiting Area False Ceiling Layout Plan & Section Details','Lobby - 01 False Ceiling Layout Plan & Section Details','Lobby - 02 False Ceiling Layout Plan & Section Details','Lobby - 03 False Ceiling Layout Plan & Section Details','Reception Area False Ceiling Layout Plan & Section Details','Manager Cabin False Ceiling Layout Plan & Section Details','Work Station Area - 01 False Ceiling Layout Plan & Section Details','Work Station Area - 02 False Ceiling Layout Plan & Section Details','Work Station Area - 03 False Ceiling Layout Plan & Section Details','Work Station Area - 04 False Ceiling Layout Plan & Section Details','Work Station Area - 05 False Ceiling Layout Plan & Section Details'
            ],
            'Ceiling Drawings': [
                'Ceiling Layout Plan Basement','Ceiling Layout Plan Stilt Floor','Ceiling Layout Plan Ground Floor','Ceiling Layout Plan First Floor','Ceiling Layout Plan Second Floor','Ceiling Layout Plan Third Floor','Ceiling Layout Plan Fourth Floor','Ceiling Layout Plan Fifth Floor'
            ],
            'Electrical Drawings': [
                'Electrical Layout Plan Basement','Electrical Layout Plan Stilt Floor','Electrical Layout Plan Ground Floor','Electrical Layout Plan First Floor','Electrical Layout Plan Second Floor','Electrical Layout Plan Third Floor','Electrical Layout Plan Fourth Floor','Electrical Layout Plan Fifth Floor'
            ],
            'Plumbing Drawings': [
                'Plumbing Layout Plan Basement','Plumbing Layout Plan Stilt Floor','Plumbing Layout Plan Ground Floor','Plumbing Layout Plan First Floor','Plumbing Layout Plan Second Floor','Plumbing Layout Plan Third Floor','Plumbing Layout Plan Fourth Floor'
            ],
            'Water Supply Drawings': [
                'Water Supply Layout Plan Basement','Water Supply Layout Plan Stilt Floor','Water Supply Layout Plan Ground Floor','Water Supply Layout Plan First Floor','Water Supply Layout Plan Second Floor','Water Supply Layout Plan Third Floor','Water Supply Layout Plan Fourth Floor','Water Supply Layout Plan Fifth Floor'
            ],
            'Details Drawings': [
                'Staircase Details','Finishing Details','Ramp Details','Kitchen Details','Lift Details','Toilet Details','Saptic Tank Details','Compound Wall Details','Landscape Details','Slab Details','Roof Details','Wall Details','Floor Details','Ceiling Details'
            ]
        };

    function buildGroupedTitleSelect(current, stageIdx, subIdx) {
        const type = (document.getElementById('projectType')?.value || '').toLowerCase();
        const DS = type === 'architecture' ? ARCH_TITLES : (type === 'interior' ? INTERIOR_TITLES : null);
        if (!DS) {
            return `<input type="text" class="form-control sub-title" data-stage="${stageIdx}" data-idx="${subIdx}" value="${escapeHtml(current||'')}" />`;
        }
        let html = `<select class="form-select sub-title-select" data-type="${type}" data-stage="${stageIdx}" data-idx="${subIdx}">`;
        let found = false;
        for (const [group, items] of Object.entries(DS)) {
            html += `<optgroup label="${escapeHtml(group)}">`;
            for (const item of items) {
                const sel = String(current||'') === item ? 'selected' : '';
                if (sel) found = true;
                html += `<option value="${escapeHtml(item)}" ${sel}>${escapeHtml(item)}</option>`;
            }
            html += `</optgroup>`;
        }
        if (!found && current) {
            html += `<option value="${escapeHtml(current)}" selected>${escapeHtml(current)} (custom)</option>`;
        }
        html += `<option value="__CUSTOM__">Custom title…</option>`;
        html += `</select>`;
        return html;
    }

    function shouldUseDropdown() {
        const val = (document.getElementById('projectType')?.value || '').toLowerCase();
        return val === 'architecture' || val === 'interior';
    }

    function statusOptions(selected) {
        const list = ['not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked'];
        return list.map(s => `<option value="${s}" ${String(selected||'')===s?'selected':''}>${s.replaceAll('_',' ')}</option>`).join('');
    }

    const STATUS_COLORS = {
        not_started: '#6b7280',
        pending: '#f59e0b',
        in_progress: '#0ea5e9',
        in_review: '#a855f7',
        completed: '#10b981',
        on_hold: '#f97316',
        cancelled: '#ef4444',
        blocked: '#dc2626'
    };
    function statusLabel(s){ return String(s||'').replaceAll('_',' ').replace(/\b\w/g, m=>m.toUpperCase()); }
    function hexToRgba(hex, a){
        const h = hex.replace('#','');
        const bigint = parseInt(h, 16);
        const r = (bigint >> 16) & 255;
        const g = (bigint >> 8) & 255;
        const b = bigint & 255;
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }
    function decorateStatusChip(el, value){
        const color = STATUS_COLORS[value] || '#6b7280';
        el.style.background = hexToRgba(color, .12);
        el.style.color = '#111827';
        const dot = el.querySelector('i');
        if (dot) { dot.style.color = color; }
        const lab = el.querySelector('.label');
        if (lab) lab.textContent = statusLabel(value);
    }
    function updateProjectStatusChip(){
        const sel = document.getElementById('projectStatus');
        const chip = document.getElementById('projectStatusChip');
        if (sel && chip) decorateStatusChip(chip, sel.value);
    }

    function toDateTimeLocal(value) {
        if (!value) return '';
        // Accept values like 'YYYY-MM-DD HH:MM:SS' or ISO strings
        try {
            let s = String(value).trim();
            // Replace space with 'T' if present
            s = s.replace(' ', 'T');
            // If there is no 'T', and looks like date only, append time 00:00
            if (!s.includes('T')) s = s + 'T00:00:00';
            const d = new Date(s);
            if (isNaN(d.getTime())) return '';
            const pad = n => String(n).padStart(2, '0');
            const yyyy = d.getFullYear();
            const mm = pad(d.getMonth()+1);
            const dd = pad(d.getDate());
            const hh = pad(d.getHours());
            const mi = pad(d.getMinutes());
            return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
        } catch { return ''; }
    }

    function userOptions(selectedId) {
        let html = `<option value="0">Unassigned</option>`;
        (USERS || []).forEach(u => {
            const sel = String(selectedId || '') === String(u.id) ? 'selected' : '';
            html += `<option value="${u.id}" ${sel}>${escapeHtml(u.username)} (${escapeHtml(u.role)})</option>`;
        });
        return html;
    }

    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

    function getUserById(id){
        id = String(id||'');
        return (USERS||[]).find(u => String(u.id)===id);
    }

    function renderStages() {
        const container = document.getElementById('stagesContainer');
        if (!stagesState.length) {
            container.innerHTML = `<div class="text-muted">No stages. Click "Add Stage" to create one.</div>`;
            return;
        }
        container.innerHTML = '';
        stagesState.forEach((st, idx) => {
            const stageNumber = st.stage_number ?? (idx + 1);
            const stageId = st.id || '';
            const card = document.createElement('div');
            card.className = 'card mb-3 stage-card';
            const assignedUser = getUserById(st.assigned_to);
            const assignedLabel = assignedUser ? `${assignedUser.username}` : 'Unassigned';
            card.innerHTML = `
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge badge-stage">Stage ${stageNumber}</span>
                        <span class="chip"><i class="bi bi-person"></i>${escapeHtml(assignedLabel)}</span>
                        <span id="stage-status-chip-${idx}" class="chip status-chip"><i class="bi bi-circle-fill" style="font-size:.55rem"></i><span class="label"></span></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-primary add-substage-btn" data-idx="${idx}"><i class="bi bi-plus-circle"></i> Substage</button>
                        <button class="btn btn-sm btn-outline-danger remove-stage-btn" data-idx="${idx}"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#stage-body-${idx}" aria-expanded="true" aria-controls="stage-body-${idx}"><i class="bi bi-chevron-down"></i></button>
                    </div>
                </div>
                <div id="stage-body-${idx}" class="card-body collapse show">
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Assigned To</small>
                            <select class="form-select form-select-sm stage-assign" data-idx="${idx}">${userOptions(st.assigned_to)}</select>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Start</small>
                            <input type="datetime-local" class="form-control form-control-sm stage-start" data-idx="${idx}" value="${toDateTimeLocal(st.start_date)}" />
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">End</small>
                            <input type="datetime-local" class="form-control form-control-sm stage-end" data-idx="${idx}" value="${toDateTimeLocal(st.end_date)}" />
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Status</small>
                            <select class="form-select form-select-sm stage-status" data-idx="${idx}">${statusOptions(st.status)}</select>
                        </div>
                    </div>
                    <div class="row g-2" id="substage-list-${idx}"></div>
                </div>`;
            container.appendChild(card);

            // Render substages
            renderSubstages(idx);
        });

        // Append floating Add Stage button after the last stage
        const addWrap = document.createElement('div');
        addWrap.className = 'd-flex justify-content-end';
        addWrap.innerHTML = `<button class="btn btn-sm btn-outline-primary btn-icon" id="addStageBtn"><i class="bi bi-plus-circle"></i> Add Stage</button>`;
        container.appendChild(addWrap);

        // Bind stage-level events
        container.querySelectorAll('.stage-assign').forEach(el => el.addEventListener('change', e => {
            const i = +e.target.dataset.idx; stagesState[i].assignTo = e.target.value; stagesState[i].assigned_to = e.target.value; }));
        container.querySelectorAll('.stage-start').forEach(el => el.addEventListener('change', e => {
            const i = +e.target.dataset.idx; stagesState[i].start_date = e.target.value; stagesState[i].startDate = e.target.value; }));
        container.querySelectorAll('.stage-end').forEach(el => el.addEventListener('change', e => {
            const i = +e.target.dataset.idx; stagesState[i].end_date = e.target.value; stagesState[i].dueDate = e.target.value; }));
        container.querySelectorAll('.add-substage-btn').forEach(btn => btn.addEventListener('click', e => {
            const i = +e.currentTarget.dataset.idx; addSubstage(i); }));
        container.querySelectorAll('.remove-stage-btn').forEach(btn => btn.addEventListener('click', e => {
            const i = +e.currentTarget.dataset.idx; removeStage(i); }));
        container.querySelectorAll('.stage-status').forEach(el => {
            const i = +el.dataset.idx; stagesState[i].status = el.value || stagesState[i].status;
            const chip = document.getElementById(`stage-status-chip-${i}`);
            if (chip) decorateStatusChip(chip, el.value);
            el.addEventListener('change', e => {
                const idx = +e.target.dataset.idx; stagesState[idx].status = e.target.value; const c = document.getElementById(`stage-status-chip-${idx}`); if (c) decorateStatusChip(c, e.target.value); });
        });
    }

    function renderSubstages(stageIdx) {
        const stage = stagesState[stageIdx];
        if (!stage.sub_stages && stage.substages) stage.sub_stages = stage.substages; // normalize
        stage.sub_stages = stage.sub_stages || [];
        const list = document.getElementById(`substage-list-${stageIdx}`);
        list.innerHTML = '';
        stage.sub_stages.forEach((ss, sidx) => {
            const subId = ss.id || '';
            const row = document.createElement('div');
            row.className = 'col-12';
            row.innerHTML = `
                <div class="card substage-card mb-2">
                    <div class="card-body pt-3 pb-2">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-substage me-2">Substage</span>
                            <span class="text-muted small">Part of Stage ${stageIdx + 1}</span>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3 sub-title-col" data-stage="${stageIdx}" data-idx="${sidx}">
                                <label class="form-label">Title</label>
                                ${ shouldUseDropdown() ? buildGroupedTitleSelect(ss.title, stageIdx, sidx) : `<input type="text" class="form-control sub-title" data-stage="${stageIdx}" data-idx="${sidx}" value="${escapeHtml(ss.title || '')}" />` }
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Drawing No.</label>
                                <input type="text" class="form-control sub-drawing" data-stage="${stageIdx}" data-idx="${sidx}" value="${escapeHtml(ss.drawing_number || ss.drawingNumber || '')}" />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select sub-assign" data-stage="${stageIdx}" data-idx="${sidx}">${userOptions(ss.assigned_to)}</select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Start</label>
                                <input type="datetime-local" class="form-control sub-start" data-stage="${stageIdx}" data-idx="${sidx}" value="${toDateTimeLocal(ss.start_date)}" />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">End</label>
                                <input type="datetime-local" class="form-control sub-end" data-stage="${stageIdx}" data-idx="${sidx}" value="${toDateTimeLocal(ss.end_date)}" />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select sub-status" data-stage="${stageIdx}" data-idx="${sidx}">${statusOptions(ss.status)}</select>
                            </div>
                            <div class="col-md-1 d-flex gap-2 justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm" title="Upload files" data-upload data-stage="${stageIdx}" data-idx="${sidx}"><i class="bi bi-upload"></i></button>
                                <button class="btn btn-outline-danger btn-sm" title="Remove substage" data-remove data-stage="${stageIdx}" data-idx="${sidx}"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="small text-muted mb-1">Files</div>
                            <div class="file-list border rounded p-2" id="files-${stageIdx}-${sidx}">
                                <div class="text-muted">No files loaded</div>
                            </div>
                        </div>
                    </div>
                </div>`;
            list.appendChild(row);
        });

        // Bind substage events
        list.querySelectorAll('.sub-title').forEach(el => el.addEventListener('input', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].title = e.target.value; }));
        list.querySelectorAll('.sub-title-select').forEach((el) => el.addEventListener('change', e => {
            const si = +e.target.dataset.stage; const i = +e.target.dataset.idx;
            if (e.target.value === '__CUSTOM__') {
                // Swap select -> input with back button
                const col = list.querySelector(`.sub-title-col[data-stage="${si}"][data-idx="${i}"]`);
                if (col) {
                    col.innerHTML = `
                        <label class="form-label">Title</label>
                        <div class="input-group">
                            <button class="btn btn-outline-secondary btn-sm back-to-dropdown" type="button" data-stage="${si}" data-idx="${i}" title="Back to options"><i class="bi bi-arrow-left"></i></button>
                            <input type="text" class="form-control sub-title" data-stage="${si}" data-idx="${i}" value="" placeholder="Enter custom title" />
                        </div>`;
                    // Bind events for new elements
                    col.querySelector('.back-to-dropdown').addEventListener('click', (ev) => {
                        const s = +ev.currentTarget.dataset.stage; const j = +ev.currentTarget.dataset.idx;
                        const parent = ev.currentTarget.closest('.sub-title-col');
                        if (parent) parent.innerHTML = `<label class="form-label">Title</label>${buildGroupedTitleSelect(stagesState[s].sub_stages[j].title||'', s, j)}`;
                        // rebind select handler
                        renderSubstages(stageIdx);
                    });
                    col.querySelector('.sub-title').addEventListener('input', (ev) => {
                        const s = +ev.target.dataset.stage; const j = +ev.target.dataset.idx; stagesState[s].sub_stages[j].title = ev.target.value; 
                    });
                }
                return;
            }
            stagesState[si].sub_stages[i].title = e.target.value;
        }));
        list.querySelectorAll('.sub-drawing').forEach(el => el.addEventListener('input', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].drawing_number = e.target.value; stagesState[si].sub_stages[i].drawingNumber = e.target.value; }));
        list.querySelectorAll('.sub-assign').forEach(el => el.addEventListener('change', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].assignTo = e.target.value; stagesState[si].sub_stages[i].assigned_to = e.target.value; }));
        list.querySelectorAll('.sub-start').forEach(el => el.addEventListener('change', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].start_date = e.target.value; stagesState[si].sub_stages[i].startDate = e.target.value; }));
        list.querySelectorAll('.sub-end').forEach(el => el.addEventListener('change', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].end_date = e.target.value; stagesState[si].sub_stages[i].dueDate = e.target.value; }));
        list.querySelectorAll('button[data-remove]').forEach(btn => btn.addEventListener('click', e => {
            const si = +btn.dataset.stage, i = +btn.dataset.idx; stagesState[si].sub_stages.splice(i, 1); renderSubstages(si); }));
        list.querySelectorAll('.sub-status').forEach(el => el.addEventListener('change', e => {
            const si = +e.target.dataset.stage, i = +e.target.dataset.idx; stagesState[si].sub_stages[i].status = e.target.value; }));
        list.querySelectorAll('button[data-upload]').forEach(btn => btn.addEventListener('click', async e => {
            const si = +btn.dataset.stage, i = +btn.dataset.idx; const sub = stagesState[si].sub_stages[i];
            if (!sub.id) { toast('Please save the project first to create the substage before uploading files.', 'danger'); return; }
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.onchange = async () => {
                const files = Array.from(input.files || []);
                for (const f of files) {
                    await uploadSubstageFile(sub.id, f);
                }
                loadFilesForSubstage(sub.id, si, i);
            };
            input.click();
        }));
    }

    function addStage() {
        const nextNumber = stagesState.length ? Math.max(...stagesState.map(s => +s.stage_number || 0)) + 1 : 1;
        stagesState.push({ id: null, stage_number: nextNumber, assigned_to: null, start_date: '', end_date: '', assignTo: '0', startDate: '', dueDate: '', substages: [], sub_stages: [] });
        renderStages();
    }
    function removeStage(idx) { stagesState.splice(idx, 1); renderStages(); }
    function addSubstage(stageIdx) {
        const stage = stagesState[stageIdx];
        const list = stage.sub_stages || (stage.sub_stages = []);
        const nextNum = list.length ? Math.max(...list.map(s => +s.substage_number || 0)) + 1 : 1;
        list.push({ id: null, substage_number: nextNum, title: '', drawing_number: '', assigned_to: null, start_date: '', end_date: '', assignTo: '0', startDate: '', dueDate: '' });
        renderSubstages(stageIdx);
    }

    async function loadStages() {
        try {
            const res = await fetch(`ajax_handlers/get_project_stages.php?project_id=${PROJECT_ID}`);
            const json = await res.json();
            if (json.success) {
                stagesState.splice(0, stagesState.length, ...json.data);
                // Normalize keys
                stagesState.forEach(st => {
                    st.assignTo = st.assigned_to || '0';
                    st.startDate = st.start_date || '';
                    st.dueDate = st.end_date || '';
                    if (!st.sub_stages && st.substages) st.sub_stages = st.substages;
                    (st.sub_stages || []).forEach(ss => {
                        ss.assignTo = ss.assigned_to || '0';
                        ss.startDate = ss.start_date || '';
                        ss.dueDate = ss.end_date || '';
                        ss.drawingNumber = ss.drawing_number || '';
                    });
                });
                renderStages();
                // Load files for each existing substage
                stagesState.forEach((st, si) => (st.sub_stages||[]).forEach((ss, i) => ss.id && loadFilesForSubstage(ss.id, si, i)));
            } else {
                document.getElementById('stagesContainer').innerHTML = `<div class="text-danger">${json.message || 'Failed to load stages'}</div>`;
            }
        } catch (e) {
            document.getElementById('stagesContainer').innerHTML = `<div class="text-danger">Error loading stages</div>`;
        }
    }

    async function loadFilesForSubstage(substageId, stageIdx, subIdx) {
        try {
            const res = await fetch(`ajax_handlers/get_substage_files.php?substage_id=${substageId}`);
            const json = await res.json();
            const list = document.getElementById(`files-${stageIdx}-${subIdx}`);
            if (!json.success) { list.innerHTML = '<div class="text-danger">Failed to load files</div>'; return; }
            if (!json.data.length) { list.innerHTML = '<div class="text-muted">No files</div>'; return; }
            list.innerHTML = json.data.map(f => `
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <div class="small"><i class="bi bi-file-earmark me-1"></i>${escapeHtml(f.file_name)} <span class="badge bg-light text-dark ms-2">${escapeHtml(f.type || f.file_type || '')}</span></div>
                    <a class="btn btn-sm btn-outline-primary" href="${f.file_path}" target="_blank"><i class="bi bi-download"></i></a>
                </div>
            `).join('');
        } catch (e) {}
    }

    async function uploadSubstageFile(substageId, file) {
        const form = new FormData();
        form.append('substage_id', substageId);
        form.append('file_name', file.name.replace(/\.[^.]+$/, '')); // name without ext
        form.append('file', file);
        try {
            const res = await fetch('upload_substage_file.php', { method: 'POST', body: form });
            const json = await res.json();
            if (!json.success) { toast(json.message || 'Upload failed', 'danger'); }
            else { toast('File uploaded', 'success'); }
        } catch (e) {
            toast('Upload error', 'danger');
        }
    }

    function gatherPayload() {
        return {
            projectId: PROJECT_ID,
            projectTitle: document.getElementById('projectTitle').value.trim(),
            projectDescription: document.getElementById('projectDescription').value.trim(),
            projectType: document.getElementById('projectType').value.trim(),
            projectCategory: document.getElementById('projectCategory').value || null,
            startDate: document.getElementById('startDate').value || null,
            dueDate: document.getElementById('dueDate').value || null,
            assignTo: document.getElementById('assignTo').value || '0',
            projectStatus: document.getElementById('projectStatus').value,
            client_name: document.getElementById('client_name').value.trim(),
            client_address: document.getElementById('client_address').value.trim(),
            project_location: document.getElementById('project_location').value.trim(),
            plot_area: document.getElementById('plot_area').value.trim(),
            contact_number: document.getElementById('contact_number').value.trim(),
            stages: stagesState.map((st, idx) => ({
                id: st.id || null,
                stage_number: st.stage_number || (idx + 1),
                assignTo: st.assignTo || '0',
                startDate: st.startDate || st.start_date || null,
                dueDate: st.dueDate || st.end_date || null,
                status: st.status || 'not_started',
                substages: (st.sub_stages || []).map((ss, sidx) => ({
                    id: ss.id || null,
                    substage_number: ss.substage_number || (sidx + 1),
                    title: ss.title || '',
                    drawingNumber: ss.drawingNumber || ss.drawing_number || '',
                    assignTo: ss.assignTo || '0',
                    startDate: ss.startDate || ss.start_date || null,
                    dueDate: ss.dueDate || ss.end_date || null,
                    status: ss.status || 'not_started',
                }))
            }))
        };
    }

    async function saveProject() {
        const payload = gatherPayload();
        if (!payload.projectTitle) { toast('Title is required', 'danger'); return; }
        const btns = [document.getElementById('saveProjectBtn'), document.getElementById('saveProjectBtnBottom')];
        btns.forEach(b => b && (b.disabled = true, b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'));
        try {
            const res = await fetch('ajax_handlers/update_projects.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const json = await res.json();
            if (res.status === 403) { toast('Not authorized to update project', 'danger'); return; }
            if (json.status === 'success') {
                toast('Project updated', 'success');
                // Reload stages to get newly created IDs
                await loadStages();
            } else {
                toast(json.message || 'Update failed', 'danger');
            }
        } catch (e) {
            toast('Network error during save', 'danger');
        } finally {
            btns.forEach(b => b && (b.disabled = false, b.innerHTML = '<i class="bi bi-save"></i> Save Changes'));
        }
    }

    // Delegate click for dynamic Add Stage button at bottom
    document.addEventListener('click', (e) => {
        if (e.target && (e.target.id === 'addStageBtn' || e.target.closest && e.target.closest('#addStageBtn'))) {
            addStage();
        }
    });
    document.getElementById('saveProjectBtn').addEventListener('click', saveProject);
    document.getElementById('saveProjectBtnBottom').addEventListener('click', saveProject);
    loadStages();
    updateProjectStatusChip();
    document.getElementById('projectStatus').addEventListener('change', updateProjectStatusChip);
    // When project type changes, re-render substage title controls accordingly
    document.getElementById('projectType').addEventListener('change', () => renderStages());
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


