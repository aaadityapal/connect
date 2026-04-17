document.addEventListener('DOMContentLoaded', () => {
    const projectsGrid = document.getElementById('projectsGrid');
    const paginationContainer = document.getElementById('paginationContainer');
    const searchInput = document.getElementById('projectSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetButton = document.getElementById('resetFilters');
    const modal = document.getElementById('projectDetailsModal');
    const modalBody = document.getElementById('projectDetailsBody');
    const modalCloseBtn = document.getElementById('closeProjectDetailsModal');
    const substageUploadModal = document.getElementById('substageUploadModal');
    const substageUploadForm = document.getElementById('substageUploadForm');
    const substageUploadCloseBtn = document.getElementById('closeSubstageUploadModal');
    const substageUploadCancelBtn = document.getElementById('cancelSubstageUploadBtn');
    const uploadSubstageIdInput = document.getElementById('uploadSubstageId');
    const uploadSubstageNameInput = document.getElementById('uploadSubstageName');
    const uploadMediaNameInput = document.getElementById('uploadMediaName');
    const uploadMediaFileInput = document.getElementById('uploadMediaFile');
    const uploadSubmitBtn = document.getElementById('submitSubstageUploadBtn');
    const substageUploadLoader = document.getElementById('substageUploadLoader');
    const canUploadSubstageMedia = Boolean(window.CAN_UPLOAD_SUBSTAGE_MEDIA);
    const ITEMS_PER_PAGE = 15;
    let isSubstageUploading = false;

    const sampleProjects = [
        {
            id: 1,
            name: 'Skyline Retail Hub',
            client: 'Nexus Build',
            status: 'in progress',
            category: 'Construction',
            tags: ['Construction', 'Architecture'],
            progress: 74,
            due: '22 Apr 2026',
            team: 8,
            stages: [
                {
                    id: 11,
                    name: 'Stage 1',
                    status: 'completed',
                    substages: [
                        { id: 111, name: 'Site survey', status: 'completed', media: ['survey-plan.pdf', 'site-photos.zip'] },
                        { id: 112, name: 'Scope freeze', status: 'completed', media: [] },
                        { id: 113, name: 'Budget approval', status: 'completed', media: ['budget-v3.xlsx'] }
                    ]
                },
                {
                    id: 12,
                    name: 'Stage 2',
                    status: 'in progress',
                    substages: [
                        { id: 121, name: 'Foundation work', status: 'completed', media: ['foundation-report.pdf'] },
                        { id: 122, name: 'Civil structure', status: 'in progress', media: [] },
                        { id: 123, name: 'Interior fitout', status: 'pending', media: ['fitout-boq.xlsx', 'fitout-reference.jpg'] }
                    ]
                },
                {
                    id: 13,
                    name: 'Stage 3',
                    status: 'pending',
                    substages: [
                        { id: 131, name: 'Snag list', status: 'pending', media: ['snag-list.docx'] },
                        { id: 132, name: 'Final QA', status: 'pending', media: [] },
                        { id: 133, name: 'Client sign-off', status: 'pending', media: ['signoff-scan.pdf'] }
                    ]
                }
            ]
        },
        {
            id: 2,
            name: 'Harbor Office Renovation',
            client: 'UrbanWave',
            status: 'planning',
            category: 'Architecture',
            tags: ['Architecture', 'Interior'],
            progress: 28,
            due: '12 May 2026',
            team: 5,
            stages: [
                {
                    id: 21,
                    name: 'Stage 1',
                    status: 'in progress',
                    substages: [
                        { id: 211, name: 'Space plan', status: 'completed', media: ['space-plan-v2.dwg'] },
                        { id: 212, name: 'Material board', status: 'in progress', media: [] }
                    ]
                },
                {
                    id: 22,
                    name: 'Stage 2',
                    status: 'pending',
                    substages: [
                        { id: 221, name: 'Vendor shortlist', status: 'pending', media: ['vendor-list.pdf'] },
                        { id: 222, name: 'Purchase orders', status: 'pending', media: [] }
                    ]
                }
            ]
        },
        {
            id: 3,
            name: 'Greenline Interiors',
            client: 'Leafstone Pvt Ltd',
            status: 'completed',
            category: 'Interior',
            tags: ['Interior', 'Construction'],
            progress: 100,
            due: '29 Mar 2026',
            team: 6,
            stages: [
                {
                    id: 31,
                    name: 'Stage 1',
                    status: 'completed',
                    substages: [
                        { id: 311, name: 'Requirement gathering', status: 'completed', media: ['requirements-final.pdf'] },
                        { id: 312, name: 'Timeline setup', status: 'completed', media: ['project-schedule.mpp'] }
                    ]
                },
                {
                    id: 32,
                    name: 'Stage 2',
                    status: 'completed',
                    substages: [
                        { id: 321, name: 'Installations', status: 'completed', media: ['installation-checklist.pdf'] },
                        { id: 322, name: 'Final walkthrough', status: 'completed', media: [] }
                    ]
                }
            ]
        }
    ];

    let projects = [];
    let assignableUsers = [];
    let apiErrorMessage = '';
    let currentPage = 1;

    const expandedProjects = new Set();
    const expandedStages = new Set();
    const expandedSubstages = new Set();

    function statusClass(value) {
        return `status-${String(value || 'pending').toLowerCase().replace(/\s+/g, '-')}`;
    }

    function slugify(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, '-');
    }

    function normalizeStatus(value) {
        const raw = String(value || 'pending').toLowerCase();
        if (raw === 'completed') return 'completed';
        if (raw === 'in progress') return 'in-progress';
        return 'pending';
    }

    function makeStageKey(projectId, stageId) {
        return `${projectId}-${stageId}`;
    }

    function makeSubstageKey(projectId, stageId, substageId) {
        return `${projectId}-${stageId}-${substageId}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function resolveFileUrl(path) {
        const raw = String(path || '').trim();
        if (!raw) return '';
        if (/^https?:\/\//i.test(raw)) return raw;
        if (raw.startsWith('/')) return raw;
        return `../../${raw.replace(/^\.\//, '')}`;
    }

    function formatDate(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function toDateInputValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function parseIdListFromCsv(value) {
        return String(value || '')
            .split(',')
            .map((v) => Number(String(v).trim()))
            .filter((n) => Number.isInteger(n) && n > 0);
    }

    function getAssignedToSelect(selectedRaw) {
        const selectedIds = parseIdListFromCsv(selectedRaw);
        const selectedId = selectedIds.length > 0 ? selectedIds[0] : 0;
        const options = assignableUsers.map((u) => {
            const uid = Number(u.id) || 0;
            const uname = String(u.username || `User ${uid}`);
            return `<option value="${uid}" ${selectedId === uid ? 'selected' : ''}>${escapeHtml(uname)} (ID: ${uid})</option>`;
        }).join('');

        return `<select class="edit-input" data-field="assigned_to"><option value="">Select user</option>${options}</select>`;
    }

    function getCreatedBySelect(selectedId) {
        const selectedNum = Number(selectedId) || 0;
        const options = assignableUsers.map((u) => {
            const uid = Number(u.id) || 0;
            const uname = String(u.username || `User ${uid}`);
            return `<option value="${uid}" ${selectedNum === uid ? 'selected' : ''}>${escapeHtml(uname)} (ID: ${uid})</option>`;
        }).join('');

        return `<select class="edit-input" data-field="created_by"><option value="">Select user</option>${options}</select>`;
    }

    function inferTags(projectType) {
        const pt = String(projectType || '').toLowerCase();
        if (pt.includes('interior')) return ['Interior'];
        if (pt.includes('architect')) return ['Architecture'];
        if (pt.includes('construct')) return ['Construction'];
        return ['Construction'];
    }

    function normalizeProjectFromApi(row, index) {
        const normalizedStatus = String(row.status || 'pending').toLowerCase();
        const primaryTag = inferTags(row.project_type)[0];
        const projectId = Number(row.id) || index + 1;

        const stageRows = Array.isArray(row.stages) ? row.stages : [];
        const normalizedStages = stageRows.map((s, stageIndex) => {
            const stageNumber = Number(s.stage_number) || (stageIndex + 1);
            const stageStatus = String(s.status || 'pending').toLowerCase();
            const stageId = Number(s.id) || (projectId * 100 + stageIndex + 1);
            const substageRows = Array.isArray(s.substages) ? s.substages : [];

            const normalizedSubstages = substageRows.map((sub, subIndex) => {
                const subNumber = Number(sub.substage_number) || (subIndex + 1);
                const subStatus = String(sub.status || 'pending').toLowerCase();
                const subId = Number(sub.id) || (stageId * 100 + subIndex + 1);
                return {
                    id: subId,
                    substageNumber: subNumber,
                    name: sub.title || `Substage ${subNumber}`,
                    title: sub.title || `Substage ${subNumber}`,
                    status: subStatus,
                    assignedToRaw: String(sub.assigned_to || '').trim(),
                    createdById: Number(sub.created_by) || 0,
                    assignedToNames: String(sub.assigned_to_names || '').trim(),
                    assignedByName: String(sub.assigned_by_name || '').trim(),
                    drawingNumber: sub.drawing_number || '',
                    identifier: sub.substage_identifier || '',
                    startDateRaw: toDateInputValue(sub.start_date),
                    endDateRaw: toDateInputValue(sub.end_date),
                    startDate: formatDate(sub.start_date),
                    endDate: formatDate(sub.end_date),
                    assignmentStatus: sub.assignment_status || '',
                    media: (Array.isArray(sub.files) ? sub.files : []).map((f) => ({
                        id: Number(f.id) || 0,
                        file_name: f.file_name || 'Untitled file',
                        file_path: f.file_path || '',
                        type: f.type || '',
                        uploaded_by: Number(f.uploaded_by) || 0,
                        uploaded_by_name: String(f.uploaded_by_name || '').trim(),
                        uploaded_at: f.uploaded_at || ''
                    }))
                };
            });

            return {
                id: stageId,
                stageNumber: stageNumber,
                name: `Stage ${stageNumber}`,
                status: stageStatus,
                assignedToRaw: String(s.assigned_to || '').trim(),
                createdById: Number(s.created_by) || 0,
                assignedToNames: String(s.assigned_to_names || '').trim(),
                assignedByName: String(s.assigned_by_name || '').trim(),
                startDateRaw: toDateInputValue(s.start_date),
                endDateRaw: toDateInputValue(s.end_date),
                startDate: formatDate(s.start_date),
                endDate: formatDate(s.end_date),
                assignmentStatus: s.assignment_status || '',
                substages: normalizedSubstages
            };
        });

        const fallbackStages = [
            {
                id: projectId * 10 + 1,
                name: 'Stage 1',
                status: normalizedStatus === 'completed' ? 'completed' : (normalizedStatus === 'in progress' ? 'in progress' : 'pending'),
                substages: [
                    {
                        id: projectId * 100 + 1,
                        name: row.description ? 'Project brief' : 'Initial scope',
                        status: normalizedStatus === 'completed' ? 'completed' : (normalizedStatus === 'in progress' ? 'in progress' : 'pending'),
                        media: []
                    }
                ]
            }
        ];

        return {
            id: projectId,
            name: row.title || `Project #${index + 1}`,
            client: row.client_name || 'N/A',
            description: row.description || '',
            status: normalizedStatus,
            assignedToRaw: String(row.assigned_to || '').trim(),
            createdById: Number(row.created_by) || 0,
            assignedToNames: String(row.assigned_to_names || '').trim(),
            assignedByName: String(row.assigned_by_name || '').trim(),
            startDateRaw: toDateInputValue(row.start_date),
            endDateRaw: toDateInputValue(row.end_date),
            startDate: formatDate(row.start_date),
            assignmentStatus: row.assignment_status || '',
            category: primaryTag,
            tags: inferTags(row.project_type),
            projectType: row.project_type || '',
            progress: normalizedStatus === 'completed' ? 100 : (normalizedStatus === 'in progress' ? 55 : 10),
            due: formatDate(row.end_date),
            team: String(row.assigned_to || '').trim()
                ? String(row.assigned_to).split(',').map((v) => v.trim()).filter(Boolean).length
                : 0,
            stages: normalizedStages.length > 0 ? normalizedStages : fallbackStages
        };
    }

    async function loadProjectsFromApi() {
        apiErrorMessage = '';
        try {
            const res = await fetch('api/get_projects.php', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            const data = await res.json();
            if (data && data.success && Array.isArray(data.data)) {
                projects = data.data.map(normalizeProjectFromApi);
                return;
            }

            projects = [];
            apiErrorMessage = (data && (data.error || data.message))
                ? String(data.error || data.message)
                : 'Unable to fetch projects.';
            return;
        } catch (err) {
            console.error('Failed to load projects API:', err);
            projects = [];
            apiErrorMessage = 'Failed to connect to projects API.';
        }
    }

    async function loadAssignableUsers() {
        try {
            const res = await fetch('api/get_users.php', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            const data = await res.json();
            if (data && data.success && Array.isArray(data.data)) {
                assignableUsers = data.data;
                return;
            }
            assignableUsers = [];
        } catch (err) {
            console.error('Failed to load users:', err);
            assignableUsers = [];
        }
    }

    function renderPagination(totalItems, totalPages) {
        if (!paginationContainer) return;
        if (totalItems <= ITEMS_PER_PAGE) {
            paginationContainer.innerHTML = '';
            return;
        }

        let numberButtons = '';
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, start + 4);

        for (let p = start; p <= end; p++) {
            numberButtons += `<button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }

        paginationContainer.innerHTML = `
            <button type="button" class="page-btn" data-page="prev" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>
            ${numberButtons}
            <button type="button" class="page-btn" data-page="next" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
            <span class="page-summary">Page ${currentPage} of ${totalPages}</span>
        `;
    }

    function projectTemplate(project) {
        const isProjectOpen = expandedProjects.has(project.id);
        const categorySlug = slugify(project.category);
        const tagsHtml = (project.tags || []).map((tag) => {
            const tagSlug = slugify(tag);
            return `<span class="project-tag tag-${tagSlug}">${tag}</span>`;
        }).join('');

        const stagesHtml = project.stages.map((stage) => {
            const key = makeStageKey(project.id, stage.id);
            const isStageOpen = expandedStages.has(key);
            const stageSubstages = Array.isArray(stage.substages) ? stage.substages : [];
            const stageAssignedTo = String(stage.assignedToNames || '').trim() || 'Unassigned';
            const stageAssignedBy = String(stage.assignedByName || '').trim() || '-';
            const substagesHtml = stageSubstages.map((substage) => {
                const subKey = makeSubstageKey(project.id, stage.id, substage.id);
                const isSubstageOpen = expandedSubstages.has(subKey);
                const media = Array.isArray(substage.media) ? substage.media : [];
                const substageStatusClass = normalizeStatus(substage.status);
                const subAssignedTo = String(substage.assignedToNames || '').trim() || 'Unassigned';
                const subAssignedBy = String(substage.assignedByName || '').trim() || '-';

                const uploadActionHtml = canUploadSubstageMedia
                    ? `<span class="substage-inline-actions">
                                <span class="substage-upload-btn" title="Upload file" data-substage-id="${substage.id}" data-substage-name="${escapeHtml(substage.name || 'Substage')}">
                                    <i data-lucide="upload" style="width:14px;height:14px;"></i>
                                </span>
                            </span>`
                    : '';

                return `
                        <div class="substage-item status-${substageStatusClass} ${isSubstageOpen ? 'open' : ''}">
                        <button type="button" class="substage-toggle" data-project-id="${project.id}" data-stage-id="${stage.id}" data-substage-id="${substage.id}">
                            <span class="chev ${isSubstageOpen ? 'open' : ''}">▸</span>
                            <span class="entity-icon substage-icon-wrap">
                                <i data-lucide="list-todo" style="width:14px;height:14px;"></i>
                            </span>
                            <span class="substage-main">
                                <span class="substage-name">${substage.name}</span>
                                <span class="entity-assignment">
                                    <span class="assign-chip"><i data-lucide="user-check" style="width:12px;height:12px;"></i> To: ${subAssignedTo}</span>
                                    <span class="assign-chip"><i data-lucide="user-plus" style="width:12px;height:12px;"></i> By: ${subAssignedBy}</span>
                                </span>
                            </span>
                                <span class="entity-status-badge status-${substageStatusClass}">${substage.status || 'Pending'}</span>
                            ${uploadActionHtml}
                            <span class="substage-media-count">${media.length} file${media.length === 1 ? '' : 's'}</span>
                        </button>
                        <div class="substage-media" style="display:${isSubstageOpen ? 'block' : 'none'};">
                            ${media.length > 0
                                ? `<ul>${media.map((file, fileIndex) => {
                                    const fileName = typeof file === 'string' ? file : (file.file_name || 'Untitled file');
                                    const filePath = typeof file === 'string' ? '' : (file.file_path || '');
                                    const uploadedBy = typeof file === 'string'
                                        ? ''
                                        : (String(file.uploaded_by_name || '').trim() || (Number(file.uploaded_by) > 0 ? `User ${file.uploaded_by}` : 'Unknown'));
                                    const uploadedAt = typeof file === 'string' ? '-' : formatDateTime(file.uploaded_at || '');
                                    return `
                                    <li class="media-file-item">
                                        <div class="media-file-info">
                                            <span class="media-file-name"><span class="media-file-serial">${fileIndex + 1}.</span> ${fileName}</span>
                                            <span class="media-file-meta">Uploaded by ${escapeHtml(uploadedBy)} on ${escapeHtml(uploadedAt)}</span>
                                        </div>
                                        <div class="media-actions">
                                            <button type="button" class="media-action-btn" data-action="view" data-file-name="${fileName}" data-file-path="${filePath}" title="View file">
                                                <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                            </button>
                                            <button type="button" class="media-action-btn" data-action="download" data-file-name="${fileName}" data-file-path="${filePath}" title="Download file">
                                                <i data-lucide="download" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </li>
                                `;}).join('')}</ul>`
                                : '<p class="media-empty">No media uploaded yet.</p>'}
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="stage-item status-${normalizeStatus(stage.status)} ${isStageOpen ? 'open' : ''}">
                    <button type="button" class="stage-toggle" data-project-id="${project.id}" data-stage-id="${stage.id}">
                        <span class="chev ${isStageOpen ? 'open' : ''}">▸</span>
                        <span class="entity-icon stage-icon-wrap">
                            <i data-lucide="layers-3" style="width:14px;height:14px;"></i>
                        </span>
                        <span class="stage-main">
                            <span class="stage-name">${stage.name}</span>
                            <span class="entity-assignment">
                                <span class="assign-chip"><i data-lucide="user-check" style="width:12px;height:12px;"></i> To: ${stageAssignedTo}</span>
                                <span class="assign-chip"><i data-lucide="user-plus" style="width:12px;height:12px;"></i> By: ${stageAssignedBy}</span>
                            </span>
                        </span>
                            <span class="entity-status-badge status-${normalizeStatus(stage.status)}">${stage.status || 'Pending'}</span>
                        <span class="stage-count">${stageSubstages.length} sub-stages</span>
                    </button>
                    <div class="stage-substages" style="display:${isStageOpen ? 'block' : 'none'};">
                        ${stageSubstages.length > 0
                            ? substagesHtml
                            : '<p class="no-substage-msg">No substage in this stage.</p>'}
                    </div>
                </div>
            `;
        }).join('');

        return `
            <article class="project-item project-item--${categorySlug} ${isProjectOpen ? 'open' : ''}">
                <button type="button" class="project-toggle" data-project-id="${project.id}">
                    <div class="project-top">
                        <div class="project-toggle-left">
                            <span class="chev ${isProjectOpen ? 'open' : ''}">▸</span>
                            <div>
                                <h3 class="project-name">${project.name}</h3>
                                <p class="project-client">${project.client}</p>
                                <div class="project-tags">${tagsHtml}</div>
                            </div>
                        </div>
                        <div class="project-right">
                            <span class="status-chip ${statusClass(project.status)}">${project.status}</span>
                        </div>
                    </div>
                </button>

                <div class="project-body" style="display:${isProjectOpen ? 'block' : 'none'};">
                    <div class="project-meta">
                        <span>Status: <strong>${project.status}</strong></span>
                        <span>Assigned To: <strong>${project.assignedToNames || 'Unassigned'}</strong></span>
                        <span>Assigned By: <strong>${project.assignedByName || '-'}</strong></span>
                        <span>Completion: <strong>${project.progress}%</strong></span>
                        <span>Due: <strong>${project.due}</strong></span>
                        <span>Team: <strong>${project.team}</strong></span>
                    </div>

                    <div class="stages-wrap">
                        ${stagesHtml}
                    </div>
                </div>
            </article>
        `;
    }

    function render() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const selectedCategory = (categoryFilter?.value || 'all').toLowerCase();
        const selectedStatus = statusFilter.value;

        const filtered = projects.filter((project) => {
            const stageText = project.stages
                .flatMap((s) => [
                    s.name,
                    ...(s.substages || []).flatMap((sub) => [
                        sub.name,
                        ...((sub.media || []).map((m) => (typeof m === 'string' ? m : (m.file_name || '')))
                    )]
                    )
                ])
                .join(' ')
                .toLowerCase();
            const tagsText = (project.tags || []).join(' ').toLowerCase();
            const categoryText = String(project.category || '').toLowerCase();

            const inSearch =
                project.name.toLowerCase().includes(q) ||
                project.client.toLowerCase().includes(q) ||
                stageText.includes(q) ||
                tagsText.includes(q) ||
                categoryText.includes(q);

            const inStatus = selectedStatus === 'all' ? true : project.status === selectedStatus;
            const categorySlug = slugify(project.category || '');
            const inCategory = selectedCategory === 'all' ? true : categorySlug === selectedCategory;

            return inSearch && inStatus && inCategory;
        });

        if (!filtered.length) {
            const msg = apiErrorMessage || 'No projects found for the selected filters.';
            projectsGrid.innerHTML = `<div class="empty-state">${msg}</div>`;
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
        const paginated = filtered.slice(startIndex, startIndex + ITEMS_PER_PAGE);

        projectsGrid.innerHTML = `<div class="projects-list">${paginated.map(projectTemplate).join('')}</div>`;
        renderPagination(filtered.length, totalPages);

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function openSubstageUploadModal(substageId, substageName) {
        if (!substageUploadModal) return;
        if (uploadSubstageIdInput) uploadSubstageIdInput.value = String(substageId || '');
        if (uploadSubstageNameInput) uploadSubstageNameInput.value = String(substageName || 'Substage');
        if (uploadMediaNameInput) uploadMediaNameInput.value = '';
        if (uploadMediaFileInput) uploadMediaFileInput.value = '';
        substageUploadModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
        setTimeout(() => {
            if (uploadMediaNameInput) uploadMediaNameInput.focus();
        }, 40);
    }

    function closeSubstageUploadModal() {
        if (!substageUploadModal) return;
        if (isSubstageUploading) return;
        substageUploadModal.style.display = 'none';
        if (!modal || modal.style.display === 'none') {
            document.body.style.overflow = '';
        }
    }

    async function uploadSubstageFileFromModal() {
        if (!substageUploadForm) return;

        const substageId = Number(uploadSubstageIdInput?.value || 0);
        const mediaName = String(uploadMediaNameInput?.value || '').trim();
        const file = uploadMediaFileInput?.files?.[0] || null;

        if (!substageId) {
            alert('Invalid substage selected.');
            return;
        }
        if (!mediaName) {
            alert('Please enter media file name.');
            return;
        }
        if (!file) {
            alert('Please choose a file to upload.');
            return;
        }

        if (uploadSubmitBtn) {
            isSubstageUploading = true;
            uploadSubmitBtn.disabled = true;
            uploadSubmitBtn.textContent = 'Uploading...';
        }
        if (substageUploadLoader) {
            substageUploadLoader.style.display = 'inline-flex';
        }
        if (substageUploadCloseBtn) substageUploadCloseBtn.disabled = true;
        if (substageUploadCancelBtn) substageUploadCancelBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('substageId', String(substageId));
            formData.append('fileName', mediaName);
            formData.append('columnType', file.type || 'general');
            formData.append('file', file);

            const res = await fetch('../../api/upload_substage_file.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const result = await res.json();
            if (!result || !result.success) {
                throw new Error((result && result.message) || 'Failed to upload file.');
            }

            closeSubstageUploadModal();
            await loadProjectsFromApi();
            render();
        } catch (err) {
            alert(err.message || 'Failed to upload file.');
        } finally {
            isSubstageUploading = false;
            if (substageUploadLoader) {
                substageUploadLoader.style.display = 'none';
            }
            if (substageUploadCloseBtn) substageUploadCloseBtn.disabled = false;
            if (substageUploadCancelBtn) substageUploadCancelBtn.disabled = false;
            if (uploadSubmitBtn) {
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.innerHTML = '<i data-lucide="upload" style="width:14px;height:14px;"></i> Upload';
                if (window.lucide) lucide.createIcons();
            }
        }
    }

    projectsGrid.addEventListener('click', (event) => {
        const substageUploadBtn = event.target.closest('.substage-upload-btn');
        if (substageUploadBtn) {
            if (!canUploadSubstageMedia) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            const substageId = Number(substageUploadBtn.dataset.substageId || 0);
            const substageName = substageUploadBtn.dataset.substageName || 'Substage';
            openSubstageUploadModal(substageId, substageName);
            return;
        }

        const projectToggle = event.target.closest('.project-toggle');
        if (projectToggle) {
            const projectId = Number(projectToggle.dataset.projectId);
            if (expandedProjects.has(projectId)) {
                expandedProjects.delete(projectId);
            } else {
                expandedProjects.add(projectId);
            }
            render();
            return;
        }

        const stageToggle = event.target.closest('.stage-toggle');
        if (stageToggle) {
            const projectId = Number(stageToggle.dataset.projectId);
            const stageId = Number(stageToggle.dataset.stageId);
            const key = makeStageKey(projectId, stageId);

            if (expandedStages.has(key)) {
                expandedStages.delete(key);
            } else {
                expandedStages.add(key);
                expandedProjects.add(projectId);
            }
            render();
            return;
        }

        const substageToggle = event.target.closest('.substage-toggle');
        if (substageToggle) {
            const projectId = Number(substageToggle.dataset.projectId);
            const stageId = Number(substageToggle.dataset.stageId);
            const substageId = Number(substageToggle.dataset.substageId);
            const key = makeSubstageKey(projectId, stageId, substageId);

            if (expandedSubstages.has(key)) {
                expandedSubstages.delete(key);
            } else {
                expandedSubstages.add(key);
                expandedProjects.add(projectId);
                expandedStages.add(makeStageKey(projectId, stageId));
            }
            render();
            return;
        }

        const mediaActionBtn = event.target.closest('.media-action-btn');
        if (mediaActionBtn) {
            event.preventDefault();
            const action = mediaActionBtn.dataset.action;
            const fileName = mediaActionBtn.dataset.fileName || 'file';
            const filePath = mediaActionBtn.dataset.filePath || '';
            const url = resolveFileUrl(filePath);

            if (!url) {
                console.warn('Missing file path for media action:', fileName);
                return;
            }

            if (action === 'view') {
                window.open(url, '_blank', 'noopener,noreferrer');
                return;
            }

            if (action === 'download') {
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
            }
        }
    });

    let tempStageCounter = -1;
    let tempSubstageCounter = -1;

    function statusOptions(selected) {
        const options = ['pending', 'in progress', 'completed', 'planning', 'blocked', 'not_started'];
        return options.map((v) => `<option value="${v}" ${String(selected || '').toLowerCase() === v ? 'selected' : ''}>${v}</option>`).join('');
    }

    function stageEditorTemplate(stage) {
        const stageId = stage.id ?? `new-stage-${Math.abs(tempStageCounter--)}`;
        const stageNo = Number(stage.stageNumber) || 1;
        const substages = Array.isArray(stage.substages) ? stage.substages : [];

        return `
            <div class="edit-stage-item" data-stage-id="${escapeHtml(stageId)}">
                <div class="edit-stage-head">
                    <h4>Stage ${stageNo}</h4>
                    <button type="button" class="btn-outline add-substage-btn" data-stage-id="${escapeHtml(stageId)}">
                        <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Substage
                    </button>
                </div>

                <div class="edit-grid edit-grid-4">
                    <label>Stage Number<input type="number" class="edit-input" data-field="stage_number" value="${stageNo}" min="1"></label>
                    <label>Status<select class="edit-input" data-field="status">${statusOptions(stage.status || 'pending')}</select></label>
                    <label>Start Date<input type="date" class="edit-input" data-field="start_date" value="${escapeHtml(stage.startDateRaw || '')}"></label>
                    <label>End Date<input type="date" class="edit-input" data-field="end_date" value="${escapeHtml(stage.endDateRaw || '')}"></label>
                    <label>Assigned To${getAssignedToSelect(stage.assignedToRaw || '')}</label>
                    <label>Assigned By${getCreatedBySelect(stage.createdById)}</label>
                    <label class="edit-span-2">Assignment Status<input type="text" class="edit-input" data-field="assignment_status" value="${escapeHtml(stage.assignmentStatus || '')}" placeholder="assigned"></label>
                </div>

                <div class="edit-substages-list" data-substages-for="${escapeHtml(stageId)}">
                    ${substages.map((sub) => {
                        const subId = sub.id ?? `new-substage-${Math.abs(tempSubstageCounter--)}`;
                        const subNo = Number(sub.substageNumber) || 1;
                        return `
                            <div class="edit-substage-item" data-substage-id="${escapeHtml(subId)}">
                                <div class="edit-substage-head-row">
                                    <div class="edit-substage-head">Substage ${subNo}</div>
                                    <button type="button" class="btn-outline remove-substage-btn" title="Remove substage">
                                        <i data-lucide="trash-2" style="width:13px;height:13px;"></i> Remove
                                    </button>
                                </div>
                                <div class="edit-grid edit-grid-4">
                                    <label>Substage Number<input type="number" class="edit-input" data-field="substage_number" value="${subNo}" min="1"></label>
                                    <label>Title<textarea class="edit-input auto-grow" data-field="title" rows="1">${escapeHtml(sub.title || sub.name || '')}</textarea></label>
                                    <label>Status<select class="edit-input" data-field="status">${statusOptions(sub.status || 'pending')}</select></label>
                                    <label>Assigned To${getAssignedToSelect(sub.assignedToRaw || '')}</label>
                                    <label>Assigned By${getCreatedBySelect(sub.createdById)}</label>
                                    <label>Start Date<input type="date" class="edit-input" data-field="start_date" value="${escapeHtml(sub.startDateRaw || '')}"></label>
                                    <label>End Date<input type="date" class="edit-input" data-field="end_date" value="${escapeHtml(sub.endDateRaw || '')}"></label>
                                    <label>Assignment Status<input type="text" class="edit-input" data-field="assignment_status" value="${escapeHtml(sub.assignmentStatus || '')}" placeholder="assigned"></label>
                                    <label>ID<input type="text" class="edit-input" data-field="substage_identifier" value="${escapeHtml(sub.identifier || '')}"></label>
                                    <label>Drawing No<input type="text" class="edit-input" data-field="drawing_number" value="${escapeHtml(sub.drawingNumber || '')}"></label>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    function openProjectDetailsModal(project) {
        if (!modal || !modalBody) return;

        const titleEl = document.getElementById('projectDetailsTitle');
        if (titleEl) titleEl.textContent = 'Edit Project';

        const stages = Array.isArray(project.stages) ? project.stages : [];

        modalBody.innerHTML = `
            <form id="projectEditForm" class="project-edit-form" data-project-id="${project.id}">
                <section class="detail-block project-information-block">
                    <p class="detail-section-heading"><i data-lucide="info" style="width:14px;height:14px;"></i> Project Information</p>
                    <div class="edit-grid edit-grid-4">
                        <label>Project Title<input type="text" class="edit-input" data-field="title" value="${escapeHtml(project.name || '')}"></label>
                        <label>Client<input type="text" class="edit-input" data-field="client_name" value="${escapeHtml(project.client || '')}"></label>
                        <label>Project Type<input type="text" class="edit-input" data-field="project_type" value="${escapeHtml(project.projectType || '')}"></label>
                        <label>Status<select class="edit-input" data-field="status">${statusOptions(project.status || 'pending')}</select></label>
                        <label>Start Date<input type="date" class="edit-input" data-field="start_date" value="${escapeHtml(project.startDateRaw || '')}"></label>
                        <label>End Date<input type="date" class="edit-input" data-field="end_date" value="${escapeHtml(project.endDateRaw || '')}"></label>
                        <label>Assigned To${getAssignedToSelect(project.assignedToRaw || '')}</label>
                        <label>Assigned By${getCreatedBySelect(project.createdById)}</label>
                        <label class="edit-span-2">Assignment Status<input type="text" class="edit-input" data-field="assignment_status" value="${escapeHtml(project.assignmentStatus || '')}" placeholder="assigned"></label>
                        <label class="edit-span-4">Description<textarea class="edit-input" data-field="description" rows="3">${escapeHtml(project.description || '')}</textarea></label>
                    </div>
                </section>

                <section class="detail-block hierarchy-block">
                    <div class="edit-stage-topbar">
                        <p class="detail-section-heading"><i data-lucide="git-branch-plus" style="width:14px;height:14px;"></i> Project Hierarchy</p>
                        <button type="button" class="btn-primary" id="addStageBtn"><i data-lucide="plus" style="width:14px;height:14px;"></i> Add Stage</button>
                    </div>

                    <div id="stageEditorList" class="edit-stage-list">
                        ${stages.map((stage) => stageEditorTemplate(stage)).join('')}
                    </div>
                </section>

                <div class="modal-form-actions">
                    <button type="button" class="btn-outline" id="cancelProjectChangesBtn">Cancel</button>
                    <button type="button" class="btn-primary" id="saveProjectChangesBtn"><i data-lucide="save" style="width:14px;height:14px;"></i> Save Changes</button>
                </div>
            </form>
        `;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        initAutoGrowTextareas(modalBody);
        renumberAllSubstages(modalBody);
        if (window.lucide) lucide.createIcons();
    }

    function readField(container, fieldName) {
        const el = container.querySelector(`[data-field="${fieldName}"]`);
        if (!el) return '';
        if (el.tagName === 'SELECT' && el.multiple) {
            return Array.from(el.selectedOptions)
                .map((o) => String(o.value || '').trim())
                .filter(Boolean)
                .join(',');
        }
        return String(el.value || '').trim();
    }

    function autoResizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${Math.max(el.scrollHeight, 36)}px`;
    }

    function initAutoGrowTextareas(root) {
        if (!root) return;
        root.querySelectorAll('textarea.auto-grow').forEach((ta) => autoResizeTextarea(ta));
    }

    function renumberSubstagesInStage(stageContainer) {
        if (!stageContainer) return;
        const items = stageContainer.querySelectorAll('.edit-substage-item');
        items.forEach((item, idx) => {
            const n = idx + 1;
            const title = item.querySelector('.edit-substage-head');
            if (title) {
                title.textContent = `Substage ${n}`;
            }
            const numInput = item.querySelector('[data-field="substage_number"]');
            if (numInput) {
                numInput.value = String(n);
            }
        });
    }

    function renumberAllSubstages(root) {
        if (!root) return;
        root.querySelectorAll('.edit-stage-item').forEach((stageItem) => {
            renumberSubstagesInStage(stageItem);
        });
    }

    async function saveProjectChangesFromModal() {
        const form = modalBody ? modalBody.querySelector('#projectEditForm') : null;
        if (!form) return;

        const projectId = Number(form.dataset.projectId || 0);
        if (!projectId) return;

        const projectPayload = {
            id: projectId,
            title: readField(form, 'title'),
            client_name: readField(form, 'client_name'),
            description: readField(form, 'description'),
            project_type: readField(form, 'project_type'),
            status: readField(form, 'status'),
            start_date: readField(form, 'start_date'),
            end_date: readField(form, 'end_date'),
            assigned_to: readField(form, 'assigned_to'),
            created_by: readField(form, 'created_by'),
            assignment_status: readField(form, 'assignment_status')
        };

        const stagesPayload = Array.from(form.querySelectorAll('.edit-stage-item')).map((stageEl, stageIndex) => {
            const rawStageId = String(stageEl.dataset.stageId || '').trim();
            const stageId = /^\d+$/.test(rawStageId) ? Number(rawStageId) : rawStageId;

            const stageData = {
                id: stageId,
                stage_number: Number(readField(stageEl, 'stage_number')) || (stageIndex + 1),
                status: readField(stageEl, 'status') || 'pending',
                start_date: readField(stageEl, 'start_date'),
                end_date: readField(stageEl, 'end_date'),
                assigned_to: readField(stageEl, 'assigned_to'),
                created_by: readField(stageEl, 'created_by'),
                assignment_status: readField(stageEl, 'assignment_status'),
                substages: []
            };

            stageData.substages = Array.from(stageEl.querySelectorAll('.edit-substage-item')).map((subEl, subIndex) => {
                const rawSubId = String(subEl.dataset.substageId || '').trim();
                const subId = /^\d+$/.test(rawSubId) ? Number(rawSubId) : rawSubId;

                return {
                    id: subId,
                    substage_number: Number(readField(subEl, 'substage_number')) || (subIndex + 1),
                    title: readField(subEl, 'title'),
                    status: readField(subEl, 'status') || 'pending',
                    assigned_to: readField(subEl, 'assigned_to'),
                    created_by: readField(subEl, 'created_by'),
                    start_date: readField(subEl, 'start_date'),
                    end_date: readField(subEl, 'end_date'),
                    assignment_status: readField(subEl, 'assignment_status'),
                    substage_identifier: readField(subEl, 'substage_identifier'),
                    drawing_number: readField(subEl, 'drawing_number')
                };
            });

            return stageData;
        });

        const saveBtn = form.querySelector('#saveProjectChangesBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        try {
            const res = await fetch('api/update_project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ project: projectPayload, stages: stagesPayload })
            });

            const result = await res.json();
            if (!result || !result.success) {
                throw new Error((result && (result.error || result.message)) || 'Failed to save project.');
            }

            closeProjectDetailsModal();
            await loadProjectsFromApi();
            render();
        } catch (err) {
            alert(err.message || 'Failed to save project.');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i data-lucide="save" style="width:14px;height:14px;"></i> Save Changes';
                if (window.lucide) lucide.createIcons();
            }
        }
    }

    function closeProjectDetailsModal() {
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeProjectDetailsModal);
    }

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeProjectDetailsModal();
            }
        });
    }

    if (substageUploadCloseBtn) {
        substageUploadCloseBtn.addEventListener('click', closeSubstageUploadModal);
    }

    if (substageUploadCancelBtn) {
        substageUploadCancelBtn.addEventListener('click', closeSubstageUploadModal);
    }

    if (substageUploadModal) {
        substageUploadModal.addEventListener('click', (event) => {
            if (isSubstageUploading) return;
            if (event.target === substageUploadModal) {
                closeSubstageUploadModal();
            }
        });
    }

    if (substageUploadForm) {
        substageUploadForm.addEventListener('submit', (event) => {
            event.preventDefault();
            uploadSubstageFileFromModal();
        });
    }

    if (modalBody) {
        modalBody.addEventListener('click', (event) => {
            const cancelBtn = event.target.closest('#cancelProjectChangesBtn');
            if (cancelBtn) {
                closeProjectDetailsModal();
                return;
            }

            const saveBtn = event.target.closest('#saveProjectChangesBtn');
            if (saveBtn) {
                saveProjectChangesFromModal();
                return;
            }

            const addStageBtn = event.target.closest('#addStageBtn');
            if (addStageBtn) {
                const list = modalBody.querySelector('#stageEditorList');
                if (!list) return;
                const stageCount = list.querySelectorAll('.edit-stage-item').length;
                const html = stageEditorTemplate({
                    id: `new-stage-${Math.abs(tempStageCounter--)}`,
                    stageNumber: stageCount + 1,
                    status: 'pending',
                    startDateRaw: '',
                    endDateRaw: '',
                    assignedToRaw: '',
                    createdById: '',
                    assignmentStatus: '',
                    substages: []
                });
                list.insertAdjacentHTML('beforeend', html);
                if (window.lucide) lucide.createIcons();
                return;
            }

            const addSubstageBtn = event.target.closest('.add-substage-btn');
            if (addSubstageBtn) {
                const stageId = addSubstageBtn.dataset.stageId;
                if (!stageId) return;
                const list = modalBody.querySelector(`[data-substages-for="${stageId}"]`);
                if (!list) return;
                const subCount = list.querySelectorAll('.edit-substage-item').length;

                const subHtml = `
                    <div class="edit-substage-item" data-substage-id="new-substage-${Math.abs(tempSubstageCounter--)}">
                        <div class="edit-substage-head-row">
                            <div class="edit-substage-head">Substage ${subCount + 1}</div>
                            <button type="button" class="btn-outline remove-substage-btn" title="Remove substage">
                                <i data-lucide="trash-2" style="width:13px;height:13px;"></i> Remove
                            </button>
                        </div>
                        <div class="edit-grid edit-grid-4">
                            <label>Substage Number<input type="number" class="edit-input" data-field="substage_number" value="${subCount + 1}" min="1"></label>
                            <label>Title<textarea class="edit-input auto-grow" data-field="title" rows="1"></textarea></label>
                            <label>Status<select class="edit-input" data-field="status">${statusOptions('pending')}</select></label>
                            <label>Assigned To${getAssignedToSelect('')}</label>
                            <label>Assigned By${getCreatedBySelect('')}</label>
                            <label>Start Date<input type="date" class="edit-input" data-field="start_date" value=""></label>
                            <label>End Date<input type="date" class="edit-input" data-field="end_date" value=""></label>
                            <label>Assignment Status<input type="text" class="edit-input" data-field="assignment_status" value="" placeholder="assigned"></label>
                            <label>ID<input type="text" class="edit-input" data-field="substage_identifier" value=""></label>
                            <label>Drawing No<input type="text" class="edit-input" data-field="drawing_number" value=""></label>
                        </div>
                    </div>
                `;

                list.insertAdjacentHTML('beforeend', subHtml);
                initAutoGrowTextareas(list);
                renumberSubstagesInStage(list.closest('.edit-stage-item'));
                if (window.lucide) lucide.createIcons();
                return;
            }

            const removeSubstageBtn = event.target.closest('.remove-substage-btn');
            if (removeSubstageBtn) {
                const stageItem = removeSubstageBtn.closest('.edit-stage-item');
                const item = removeSubstageBtn.closest('.edit-substage-item');
                if (item) item.remove();
                renumberSubstagesInStage(stageItem);
                return;
            }
        });

        modalBody.addEventListener('input', (event) => {
            const textarea = event.target.closest('textarea.auto-grow');
            if (textarea) {
                autoResizeTextarea(textarea);
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (!isSubstageUploading) {
                closeSubstageUploadModal();
            }
            closeProjectDetailsModal();
        }
    });

    if (paginationContainer) {
        paginationContainer.addEventListener('click', (event) => {
            const btn = event.target.closest('.page-btn');
            if (!btn || btn.disabled) return;

            const page = btn.dataset.page;
            if (page === 'prev') {
                currentPage = Math.max(1, currentPage - 1);
            } else if (page === 'next') {
                currentPage += 1;
            } else {
                currentPage = Math.max(1, Number(page) || 1);
            }

            render();
        });
    }

    searchInput.addEventListener('input', () => {
        currentPage = 1;
        render();
    });
    if (categoryFilter) {
        categoryFilter.addEventListener('change', () => {
            currentPage = 1;
            render();
        });
    }
    statusFilter.addEventListener('change', () => {
        currentPage = 1;
        render();
    });
    resetButton.addEventListener('click', () => {
        searchInput.value = '';
        if (categoryFilter) {
            categoryFilter.value = 'all';
        }
        statusFilter.value = 'all';
        currentPage = 1;
        render();
    });

    Promise.all([loadAssignableUsers(), loadProjectsFromApi()]).then(() => {
        render();
    });

    if (window.lucide) {
        lucide.createIcons();
    }
});
