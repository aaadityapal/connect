// =====================================================
// EDIT ASSIGNED TASK — Modal Logic
// Extracted from script.js — loaded via index.php
// =====================================================
document.addEventListener('DOMContentLoaded', function () {
    (function () {
        const editModal = document.getElementById('editTaskModal');
        const closeEditBtn = document.getElementById('closeEditTaskModal');
        const cancelEditBtn = document.getElementById('cancelEditTask');
        const saveEditBtn = document.getElementById('saveEditTask');

        const fName = null; // replaced by project search — kept to avoid reference errors
        const fPriority = document.getElementById('editTaskPriority');
        const fDescription = document.getElementById('editTaskDescription');
        const fDate = document.getElementById('editTaskDate');
        const fTime = document.getElementById('editTaskTime');

        // ── Edit Modal Project Search ──
        const epInput  = document.getElementById('editProjectSearchInput');
        const epStageContainer = document.getElementById('editStageContainer');
        const epStageSelect    = document.getElementById('editStageSelect');
        let epTimeout = null;

        // Move project search menu to body so position:fixed isn't clipped by modal overflow
        let epMenu = document.getElementById('editProjectSearchMenu');
        if (epMenu && epMenu.parentElement !== document.body) {
            document.body.appendChild(epMenu);
            epMenu.style.position = 'fixed';
            epMenu.style.zIndex   = '99999';
        }


        function epPositionMenu() {
            if (!epInput || !epMenu) return;
            const rect = epInput.getBoundingClientRect();
            epMenu.style.top   = (rect.bottom + 4) + 'px';
            epMenu.style.left  = rect.left + 'px';
            epMenu.style.width = rect.width + 'px';
        }

        function epHideMenu() {
            if (epMenu) { epMenu.style.display = 'none'; epMenu.innerHTML = ''; }
        }

        async function epFetchStages(projectId, preSelectStageId) {
            if (!epStageContainer || !epStageSelect) return;
            try {
                const res  = await fetch(`api/fetch_project_stages.php?project_id=${projectId}`);
                const data = await res.json();
                if (data.success && data.stages && data.stages.length > 0) {
                    epStageSelect.innerHTML = '<option value="">Select a stage...</option>' +
                        data.stages.map(s => `<option value="${s.id}">Stage ${s.stage_number || s.id}</option>`).join('');
                    epStageContainer.style.display = 'block';
                    // Auto-select the pre-existing stage if provided
                    if (preSelectStageId) {
                        epStageSelect.value = preSelectStageId;
                    }
                } else {
                    epStageSelect.innerHTML = '<option value="">Select a stage...</option>';
                    epStageContainer.style.display = 'none';
                }
            } catch { epStageContainer.style.display = 'none'; }
        }

        async function epFetchProjects(query) {
            try {
                const res  = await fetch(`api/search_projects.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                if (data.success && data.projects.length > 0) {
                    epMenu.innerHTML = data.projects.map(p => `
                        <div class="project-search-item" data-id="${p.id}" data-title="${p.title.replace(/"/g,'&quot;')}"
                             style="padding:0.6rem 1rem;cursor:pointer;transition:background 0.2s;font-size:0.9rem;font-weight:500;display:flex;flex-direction:column;gap:0.15rem;">
                            <span style="color:#1e293b;">${p.title}</span>
                            ${p.project_type ? `<span style="font-size:0.75rem;color:#64748b;">${p.project_type}</span>` : ''}
                        </div>`).join('');
                    epPositionMenu();
                    epMenu.style.display = 'block';

                    epMenu.querySelectorAll('.project-search-item').forEach(item => {
                        item.addEventListener('click', e => {
                            e.stopPropagation();
                            epInput.value = item.getAttribute('data-title');
                            epInput.dataset.projectId = item.getAttribute('data-id');
                            epHideMenu();
                            epFetchStages(item.getAttribute('data-id'));
                        });
                        item.addEventListener('mouseenter', () => item.style.background = '#f3f4f6');
                        item.addEventListener('mouseleave', () => item.style.background = 'transparent');
                    });
                } else {
                    epMenu.innerHTML = '<div style="padding:0.6rem 1rem;color:#94a3b8;font-size:0.85rem;">No projects found</div>';
                    epPositionMenu();
                    epMenu.style.display = 'block';
                }
            } catch { epHideMenu(); }
        }

        if (epInput) {
            epInput.addEventListener('input', () => {
                const val = epInput.value.trim();
                delete epInput.dataset.projectId;
                // Reset stages
                if (epStageContainer) epStageContainer.style.display = 'none';
                if (epStageSelect)    epStageSelect.innerHTML = '<option value="">Select a stage...</option>';
                clearTimeout(epTimeout);
                if (val.length < 2) { epHideMenu(); return; }
                epTimeout = setTimeout(() => epFetchProjects(val), 300);
            });
        }

        document.addEventListener('click', e => {
            if (epInput && !epInput.contains(e.target) && epMenu && !epMenu.contains(e.target)) epHideMenu();
        });

        // ── Edit Modal @Mention System ──
        const eW  = document.getElementById('editMentionWrapper');
        const eI  = document.getElementById('editMentionInput');
        // Move editMentionMenu to body so it isn't clipped by modal overflow
        let eM = document.getElementById('editMentionMenu');
        if (eM && eM.parentElement !== document.body) {
            document.body.appendChild(eM);
            eM.style.position = 'fixed';
            eM.style.zIndex   = '99999';
        }
        let editEmp = [];       // populated from API
        let editSelected = [];  // currently picked names
        let editActiveIdx = -1;

        // Fetch active users once (reuse same API as Assign Task)
        fetch('api/fetch_users.php')
            .then(r => r.json())
            .then(d => { if (d.success) editEmp = d.users; })
            .catch(() => {});

        function eGetMention() {
            const val = eI.value;
            const lastAt = val.lastIndexOf('@');
            if (lastAt === -1) return null;
            const after = val.substring(lastAt + 1);
            if (after.includes(' ')) return null;
            return { start: lastAt, term: after.toLowerCase() };
        }

        function eSetActive(opts, idx) {
            opts.forEach(o => o.classList.remove('m-opt-active'));
            if (idx >= 0 && idx < opts.length) {
                opts[idx].classList.add('m-opt-active');
                opts[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        function eHideMenu() {
            if (!eM) return;
            eM.style.display = 'none';
            eM.innerHTML = '';
        }

        function ePositionMenu() {
            if (!eW || !eM) return;
            const rect = eW.getBoundingClientRect();
            eM.style.top   = (rect.bottom + 4) + 'px';
            eM.style.left  = rect.left + 'px';
            eM.style.width = rect.width + 'px';
        }

        function eAddChip(name) {
            const emp = editEmp.find(e => e.name === name) || { color: '#94a3b8', initials: name.substring(0,2).toUpperCase(), id: null };
            const chip = document.createElement('span');
            chip.className = 'm-chip';
            chip.dataset.name = name;
            if (emp.id) chip.dataset.userId = emp.id;
            chip.style.cssText = `background:${emp.color}18;border:1px solid ${emp.color}55;color:${emp.color};`;
            chip.innerHTML = `
                <span style="width:20px;height:20px;border-radius:50%;background:${emp.color};color:#fff;font-size:0.58rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">${emp.initials}</span>
                <span>${name.split(' ')[0]}</span>
                <span class="m-chip-x" data-name="${name}">&#x2715;</span>
            `;
            chip.querySelector('.m-chip-x').addEventListener('click', e => {
                e.stopPropagation();
                editSelected = editSelected.filter(n => n !== name);
                chip.remove();
                eI.focus();
            });
            eW.insertBefore(chip, eI);
        }

        function ePickEmployee(name) {
            if (editSelected.includes(name)) { eHideMenu(); return; }
            editSelected.push(name);
            const mention = eGetMention();
            if (mention) eI.value = eI.value.substring(0, mention.start);
            eAddChip(name);
            eHideMenu();
            eI.focus();
        }

        function eRenderMenu(term) {
            // @all magic — select every user instantly
            if (term === 'all') {
                editEmp.filter(e => !editSelected.includes(e.name)).forEach(e => {
                    editSelected.push(e.name);
                    eAddChip(e.name);
                });
                const mention = eGetMention();
                if (mention) eI.value = eI.value.substring(0, mention.start);
                eHideMenu();
                eI.focus();
                return;
            }

            const filtered = editEmp.filter(e =>
                !editSelected.includes(e.name) &&
                (term === '' ||
                 e.name.toLowerCase().split(' ').some(w => w.startsWith(term)) ||
                 e.name.toLowerCase().includes(term))
            );
            if (!filtered.length) { eHideMenu(); return; }

            editActiveIdx = -1;
            eM.innerHTML = filtered.map(e => `
                <div class="m-opt" data-name="${e.name}" style="display:flex;align-items:center;gap:0.6rem;padding:0.45rem 1rem;cursor:pointer;transition:background 0.15s;">
                    <div style="width:30px;height:30px;border-radius:50%;background:${e.color};color:#fff;font-size:0.62rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${e.initials}</div>
                    <div style="display:flex;flex-direction:column;gap:0.1rem;flex:1;">
                        <span style="font-size:0.88rem;font-weight:600;color:#1e293b;line-height:1.2;">${e.name}</span>
                        ${e.role ? `<span style="font-size:0.72rem;color:#6366f1;font-weight:500;">${e.role}</span>` : ''}
                    </div>
                </div>
            `).join('');

            eM.querySelectorAll('.m-opt').forEach(opt => {
                opt.addEventListener('mousedown', ev => { ev.preventDefault(); ePickEmployee(opt.dataset.name); });
                opt.addEventListener('mouseenter', () => {
                    editActiveIdx = [...eM.querySelectorAll('.m-opt')].indexOf(opt);
                    eSetActive([...eM.querySelectorAll('.m-opt')], editActiveIdx);
                });
            });
            ePositionMenu();
            eM.style.display = 'block';
        }

        if (eI) {
            eI.addEventListener('input', () => {
                const m = eGetMention();
                if (m !== null) eRenderMenu(m.term); else eHideMenu();
            });

            eI.addEventListener('keydown', e => {
                const opts = [...eM.querySelectorAll('.m-opt')];
                if (!opts.length) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); editActiveIdx = Math.min(editActiveIdx + 1, opts.length - 1); eSetActive(opts, editActiveIdx); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); editActiveIdx = Math.max(editActiveIdx - 1, 0); eSetActive(opts, editActiveIdx); }
                else if (e.key === 'Enter' && editActiveIdx >= 0) { e.preventDefault(); ePickEmployee(opts[editActiveIdx].dataset.name); }
                else if (e.key === 'Escape') eHideMenu();
            });

            eW && eW.addEventListener('click', () => eI.focus());
        }

        document.addEventListener('click', e => {
            if (eW && !eW.contains(e.target) && eM && !eM.contains(e.target)) eHideMenu();
        });

        // Keep menus anchored when modal body scrolls or window resizes
        const modalBody = document.querySelector('#editTaskModal .modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', () => {
                if (eM && eM.style.display !== 'none') ePositionMenu();
                if (epMenu && epMenu.style.display !== 'none') epPositionMenu();
            });
        }
        window.addEventListener('resize', () => {
            if (eM && eM.style.display !== 'none') ePositionMenu();
            if (epMenu && epMenu.style.display !== 'none') epPositionMenu();
        });

        // Focus-ring style for edit wrapper
        if (!document.getElementById('edit-mention-styles')) {
            const s = document.createElement('style');
            s.id = 'edit-mention-styles';
            s.textContent = `#editMentionWrapper:focus-within { border-color: #6366f1 !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.12) !important; }`;
            document.head.appendChild(s);
        }

        // Helpers exposed for openEditModal / saveTaskEdits to call
        function eGetSelected() { return [...editSelected]; }
        function eGetSelectedIds() {
            return [...eW.querySelectorAll('.m-chip')].map(c => c.dataset.userId).filter(Boolean);
        }
        function eClear() {
            editSelected = [];
            eW && eW.querySelectorAll('.m-chip').forEach(c => c.remove());
            if (eI) eI.value = '';
            eHideMenu();
        }
        function eSetChips(namesCsv) {
            eClear();
            if (!namesCsv) return;
            namesCsv.split(',').map(n => n.trim()).filter(Boolean).forEach(name => {
                if (!editSelected.includes(name)) { editSelected.push(name); eAddChip(name); }
            });
        }

        var activeRow = null;
        const fCBs = function () { return []; }; // no longer used but kept to avoid ref errors

        function convertTo24Hr(timeStr) {
            if (!timeStr) return '';
            var match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
            if (!match) return '';
            var h = parseInt(match[1], 10), m = match[2], period = match[3];
            if (period.toUpperCase() === 'PM' && h !== 12) h += 12;
            if (period.toUpperCase() === 'AM' && h === 12) h = 0;
            return String(h).padStart(2, '0') + ':' + m;
        }

        function convertTo12Hr(s) {
            if (!s) return '';
            var parts = s.split(':').map(Number);
            var h24 = parts[0], m = parts[1];
            var period = h24 >= 12 ? 'PM' : 'AM';
            var h12 = h24 % 12 || 12;
            return String(h12).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ' ' + period;
        }

        function getPriorityColor(priority) {
            if (priority === 'High') return '#dc2626';
            if (priority === 'Medium') return '#d97706';
            return '#16a34a';
        }

        function showToast(message) {
            var toast = document.getElementById('editTaskToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'editTaskToast';
                toast.style.cssText = 'position:fixed;bottom:2rem;right:2rem;z-index:99999;background:linear-gradient(135deg,#f97316,#ea580c);color:white;padding:0.75rem 1.5rem;border-radius:0.75rem;font-weight:600;font-size:0.9rem;box-shadow:0 8px 24px rgba(249,115,22,0.35);display:flex;align-items:center;gap:0.5rem;transform:translateY(20px);opacity:0;transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1);';
                document.body.appendChild(toast);
            }
            toast.innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + message;
            requestAnimationFrame(function () {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            });
            clearTimeout(toast._timer);
            toast._timer = setTimeout(function () {
                toast.style.transform = 'translateY(20px)';
                toast.style.opacity = '0';
            }, 3000);
        }

        // ── Validation: Restrict past Dates/Times ──────────────────────
        function setEditTimeConstraints() {
            if (!fDate || !fTime) return;
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const strToday = `${yyyy}-${mm}-${dd}`;
            
            fDate.min = strToday; // Prevent choosing past dates

            // Restrict time if today is currently selected
            if (fDate.value === strToday) {
                const hh = String(today.getHours()).padStart(2, '0');
                const mn = String(today.getMinutes()).padStart(2, '0');
                fTime.min = `${hh}:${mn}`;
            } else {
                fTime.min = '';
            }
        }

        if (fDate) fDate.addEventListener('change', setEditTimeConstraints);
        if (fTime) {
            fTime.addEventListener('change', function() {
                if (fDate && fDate.value === fDate.min && this.value && this.value < this.min) {
                    try {
                        if (typeof showCustomAlert === 'function') {
                            showCustomAlert('Invalid Time', 'You cannot set a deadline in the past for today.', 'error');
                        }
                    } catch(e){}
                    this.value = this.min;
                }
            });
        }
        // ─────────────────────────────────────────────────────────────

        function openEditModal(row) {
            // ── Shield: Prevent editing if task is globally Completed OR partially finished ──
            const status = row.dataset.taskStatus || '';
            const completedBy = row.dataset.taskCompletedBy || ''; // String of IDs
            const historyJson = row.dataset.taskCompletionHistory || '{}';
            const assignedIds = (row.dataset.taskAssignedTo || '').split(',');
            const assignedNames = (row.dataset.taskAssigneeNames || '').split(',').map(n => n.trim());

            if (status === 'Completed' || (completedBy && completedBy.trim().length > 0)) {
                let detailMsg = 'This task cannot be edited as it has already been completed by one or more members.<br><br>';
                
                try {
                    const history = JSON.parse(historyJson);
                    const completedEntries = Object.entries(history);
                    
                    if (completedEntries.length > 0) {
                        detailMsg += '<b style="color: #1e293b; font-size: 0.9rem;">Completion Audit:</b><br>';
                        completedEntries.forEach(([uid, timestamp]) => {
                            const idx = assignedIds.indexOf(uid);
                            const name = idx !== -1 ? assignedNames[idx] : 'Unknown Member';
                            const dateObj = new Date(timestamp);
                            const formattedTime = isNaN(dateObj.getTime()) ? timestamp : dateObj.toLocaleString('en-IN', { 
                                day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' 
                            });
                            detailMsg += `<span style="display: block; margin-left: 8px; color: #475569;">• <b>${name}</b> finished at ${formattedTime}</span>`;
                        });
                        detailMsg += '<br>';
                    }
                } catch (e) {
                    console.error("Error parsing history:", e);
                }

                detailMsg += '<div style="background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 12px; border-radius: 4px; font-size: 0.82rem; color: #92400e;">';
                detailMsg += '<b>⚠️ WARNING:</b> Any changes to historical task data will negatively impact user performance reports.';
                detailMsg += '</div>';

                if (typeof showCustomAlert === 'function') {
                    showCustomAlert(detailMsg, 'Task Locked', 'warning');
                }
                return;
            }

            activeRow = row;

            // ── Project search input — use the raw project name (not the merged title) ──
            var projectName = row.dataset.taskProjectName || row.dataset.taskName || '';
            var projectId   = row.dataset.taskProjectId  || '';
            var stageId     = row.dataset.taskStageId    || '';

            if (epInput) {
                epInput.value = projectName;
                if (projectId) {
                    epInput.dataset.projectId = projectId;
                } else {
                    delete epInput.dataset.projectId;
                }
            }

            // Reset stage dropdown, then fetch & pre-select if we have a projectId
            if (epStageContainer) epStageContainer.style.display = 'none';
            if (epStageSelect)    epStageSelect.innerHTML = '<option value="">Select a stage...</option>';
            if (projectId) {
                epFetchStages(projectId, stageId || null);
            }

            // Description
            var descText = row.dataset.taskDescription || '';
            if (fDescription) fDescription.value = descText;

            // Date
            var rawDate = row.dataset.taskDate || '';
            if (!rawDate) { var dc = row.querySelector('td:nth-child(3)'); if (dc) rawDate = dc.textContent.trim(); }
            if (fDate) {
                if (rawDate) {
                    var d = new Date(rawDate);
                    fDate.value = isNaN(d.getTime()) ? '' :
                        d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                } else { fDate.value = ''; }
            }

            // Time
            var timeText = row.dataset.taskTime || '';
            if (!timeText) { var tc = row.querySelector('td.time-col'); if (tc) timeText = tc.textContent.trim(); }
            if (fTime) fTime.value = convertTo24Hr(timeText);

            // ── Run constraints immediately on open ──
            setEditTimeConstraints();

            // Assignees — pre-fill chips
            eSetChips(row.dataset.taskAssigneeNames || '');

            if (editModal) {
                editModal.classList.add('visible', 'open');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeEditModal() {
            if (editModal) editModal.classList.remove('visible', 'open');
            document.body.style.overflow = '';
            activeRow = null;
            eClear(); // clear mention chips
            epHideMenu();
            if (epInput) { epInput.value = ''; delete epInput.dataset.projectId; }
            if (epStageContainer) epStageContainer.style.display = 'none';
            if (epStageSelect) epStageSelect.innerHTML = '<option value="">Select a stage...</option>';
        }

        function saveTaskEdits() {
            if (!activeRow) return;

            var oldAssignees = activeRow.dataset.taskAssigneeNames || 'Unassigned';

            var newName        = epInput ? epInput.value.trim() : '';
            var newProjectId   = epInput ? (epInput.dataset.projectId || null) : null;
            var newStageId     = (epStageSelect && epStageSelect.value) ? epStageSelect.value : null;
            var newStageLabel  = (epStageSelect && epStageSelect.selectedIndex > 0)
                                    ? epStageSelect.options[epStageSelect.selectedIndex].text : null;
            var newPriority    = fPriority ? fPriority.value : (activeRow.dataset.taskPriority || 'Low');
            var newDescription = fDescription ? fDescription.value.trim() : '';
            var newDateVal     = fDate ? fDate.value : '';
            var newTimeVal     = fTime ? fTime.value : '';

            if (!newName) {
                if (epInput) { epInput.style.borderColor = '#ef4444'; epInput.focus(); }
                return;
            }
            if (epInput) epInput.style.borderColor = '';

            // Build display title (project + optional stage)
            var displayTitle = newName + (newStageLabel ? ' — ' + newStageLabel : '');

            // Update table row cells if this came from the task table
            var nc = activeRow.querySelector('td:nth-child(1)');
            if (nc) nc.textContent = displayTitle;

            var pc = activeRow.querySelector('td:nth-child(2)');
            if (pc) {
                var color = getPriorityColor(newPriority);
                pc.innerHTML = '<div style="display:flex;align-items:center;gap:0.35rem;color:' + color + ';font-weight:600;"><i class="fa-solid fa-flag" style="color:' + color + ';font-size:0.75em;"></i>' + newPriority + '</div>';
            }

            if (newDateVal) {
                var dc2 = activeRow.querySelector('td:nth-child(3)');
                if (dc2) {
                    var d2 = new Date(newDateVal);
                    if (!isNaN(d2.getTime())) dc2.textContent = d2.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                }
            }

            if (newTimeVal) {
                var tc2 = activeRow.querySelector('td.time-col');
                if (tc2) tc2.textContent = convertTo12Hr(newTimeVal);
            }

            activeRow.dataset.taskName = newName;
            activeRow.dataset.taskPriority = newPriority;
            activeRow.dataset.taskDescription = newDescription;
            if (newDateVal) activeRow.dataset.taskDate = newDateVal;
            if (newTimeVal) activeRow.dataset.taskTime = convertTo12Hr(newTimeVal);

            // Assignees from @mention chips
            var nms = eGetSelected();
            var nmsIds = eGetSelectedIds();

            var ac = activeRow.querySelector('td:nth-child(5)');
            if (ac) {
                if (nms.length > 0) {
                    var cnt = nms.length === 1 ? '1 person' : nms.length + ' people';
                    ac.innerHTML = '<div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;"><span style="font-size:0.82rem;color:#475569;font-weight:600;">' + cnt + '</span><span style="font-size:0.78rem;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:20px;" title="' + nms.join(', ') + '">' + nms.join(', ') + '</span></div>';
                } else {
                    ac.innerHTML = '<span style="color:#94a3b8;font-size:0.82rem;font-style:italic;">Unassigned</span>';
                }
            }

            if (typeof activeRow._updateCard === 'function') {
                var asStr = nms.length > 0 ? nms.join(', ') : 'Unassigned';
                activeRow._updateCard(displayTitle, newPriority, newDescription, newDateVal, newTimeVal, asStr);
            }

            // Sync with global tasksData
            if (typeof tasksData !== 'undefined') {
                var taskId = activeRow.dataset.taskId;
                Object.keys(tasksData).forEach(function (type) {
                    tasksData[type].forEach(function (task) {
                        if (taskId && String(task.id) === String(taskId)) {
                            task.title = displayTitle;
                            task.desc  = newDescription;
                            task.badge = newPriority;
                            if (newDateVal) {
                                var dt = new Date(newDateVal);
                                var now = new Date();
                                task.time = (dt.getDate() === now.getDate() && dt.getMonth() === now.getMonth() && dt.getFullYear() === now.getFullYear()) ? 'Today' : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                                task.rawDate = newDateVal;
                            }
                            if (newTimeVal) task.time = convertTo12Hr(newTimeVal);
                            task.assignees = nms;
                        }
                    });
                });
                if (typeof renderTasks === 'function') renderTasks(typeof currentFilter !== 'undefined' ? currentFilter : 'daily');
            }

            // ── Persist to Database & Log the edit activity ──────────────────
            (function () {
                var taskId = activeRow ? activeRow.dataset.taskId : null;
                if (!taskId) return;
                
                var updatePayload = {
                    task_id: taskId,
                    project_id: newProjectId,
                    project_name: newName,
                    stage_id: newStageId,
                    stage_number: newStageLabel ? newStageLabel.replace('Stage ', '') : null,
                    priority: newPriority,
                    task_description: newDescription,
                    due_date: newDateVal,
                    due_time: newTimeVal ? newTimeVal + ':00' : null,
                    assigned_to: nmsIds.join(','),
                    assigned_names: nms.join(', ')
                };

                fetch('api/update_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updatePayload)
                }).catch(function () {});

                var newAssignees = nms.length > 0 ? nms.join(', ') : 'Unassigned';
                var logDesc = 'Task edited: "' + displayTitle + '"';
                var actionType = 'task_edited';
                if (oldAssignees !== newAssignees) {
                    logDesc += ' (Assignees changed from ' + oldAssignees + ' to ' + newAssignees + ')';
                    actionType = 'task_reassigned';
                }

                fetch('api/log_activity.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action_type:  actionType,
                        entity_type:  'task',
                        entity_id:    parseInt(taskId),
                        description:  logDesc,
                        metadata: {
                            task_id:            taskId,
                            title:              displayTitle,
                            priority:           newPriority,
                            description:        newDescription,
                            due_date:           newDateVal,
                            due_time:           newTimeVal,
                            previous_assignees: oldAssignees,
                            new_assignees:      newAssignees,
                            assignees:          newAssignees
                        }
                    })
                }).catch(function () {}); // non-fatal
            })();
            // ─────────────────────────────────────────────────────────────

            if (typeof window.playTaskSound === 'function') window.playTaskSound();
            closeEditModal();
            showToast('Task updated successfully!');
        }

        var staticTableBody = document.getElementById('taskListTableBody');
        if (staticTableBody) {
            staticTableBody.addEventListener('click', function (e) {
                var editBtn = e.target.closest('.tl-edit-btn');
                if (editBtn) {
                    e.stopPropagation();
                    var row = editBtn.closest('.task-list-row');
                    if (row) openEditModal(row);
                }
            });
        }

        if (closeEditBtn) closeEditBtn.addEventListener('click', closeEditModal);
        if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);
        if (saveEditBtn) saveEditBtn.addEventListener('click', saveTaskEdits);

        if (editModal) {
            editModal.addEventListener('click', function (e) {
                if (e.target === editModal) closeEditModal();
            });
        }

        if (fName) {
            fName.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') saveTaskEdits();
            });
        }

        if (saveEditBtn) {
            saveEditBtn.addEventListener('mouseover', function () {
                saveEditBtn.style.transform = 'translateY(-2px)';
                saveEditBtn.style.boxShadow = '0 6px 16px rgba(234,88,12,0.4)';
            });
            saveEditBtn.addEventListener('mouseout', function () {
                saveEditBtn.style.transform = '';
                saveEditBtn.style.boxShadow = '0 4px 12px rgba(234,88,12,0.3)';
            });
        }

        // ── Delegate: Edit buttons inside #assignedTasksList ──
        var assignedList2 = document.getElementById('assignedTasksList');
        if (assignedList2) {
            assignedList2.addEventListener('click', function (e) {
                var editBtn2 = e.target.closest('.unique-edit-assigned-btn');
                if (!editBtn2) return;
                e.stopPropagation();

                var card = editBtn2.closest('.assigned-task-item');
                if (!card) return;

                // Build a synthetic row-like object that openEditModal can use
                var syntheticRow = {
                    querySelector: function (sel) {
                        if (sel === 'td:nth-child(1)') return { textContent: card.dataset.taskName || '' };
                        if (sel === 'td:nth-child(2)') return { textContent: card.dataset.taskPriority || '' };
                        if (sel === 'td:nth-child(3)') return { textContent: card.dataset.taskDate || '' };
                        if (sel === 'td.time-col') return card.dataset.taskTime ? { textContent: card.dataset.taskTime } : null;
                        if (sel === 'td:nth-child(6) span' || sel === 'td:last-of-type span') return { textContent: card.dataset.taskStatus || 'Pending' };
                        if (sel === '.task-assignee') return null;
                        return null;
                    },
                    dataset: {
                        taskId:          card.dataset.taskId          || '',
                        taskName:        card.dataset.taskName        || '',
                        taskProjectId:   card.dataset.taskProjectId   || '',
                        taskProjectName: card.dataset.taskProjectName || '',
                        taskStageId:     card.dataset.taskStageId     || '',
                        taskStageNumber: card.dataset.taskStageNumber || '',
                        taskPriority:    card.dataset.taskPriority    || '',
                        taskDate:        card.dataset.taskDate        || '',
                        taskTime:        card.dataset.taskTime        || '',
                        taskStatus:      card.dataset.taskStatus      || 'Pending',
                        taskCompletedBy: card.dataset.taskCompletedBy || '',
                        taskAssignedTo:  card.dataset.taskAssignedTo  || '',
                        taskCompletionHistory: card.dataset.taskCompletionHistory || '{}',
                        taskDescription: card.dataset.taskDescription || '',
                        taskAssigneeNames: card.dataset.taskAssigneeNames || ''
                    },
                    _card: card
                };

                // Expose a hook so saveTaskEdits can update the card
                syntheticRow._updateCard = function (name, priority, descVal, dateVal, timeVal, assigneeNames) {
                    var nameEl = card.querySelector('.atl-task-name');
                    if (nameEl) { nameEl.textContent = name; nameEl.title = name; }

                    var descEl = card.querySelector('.atl-task-desc');
                    if (descEl) { descEl.textContent = descVal; descEl.title = descVal; }

                    var badgeEl = card.querySelector('.atl-priority-badge');
                    if (badgeEl) {
                        var bc = priority === 'High' ? 'high' : priority === 'Medium' ? 'medium' : 'low';
                        badgeEl.className = 'atl-priority-badge ' + bc;
                        badgeEl.textContent = priority;
                    }

                    var assigneeEl2 = card.querySelector('.atl-assignee');
                    if (assigneeEl2 && assigneeNames) assigneeEl2.innerHTML = '<i class="fa-solid fa-users"></i> ' + assigneeNames;

                    var dateEl = card.querySelector('.atl-date');
                    if (dateEl && dateVal) {
                        var d3 = new Date(dateVal);
                        if (!isNaN(d3.getTime())) dateEl.innerHTML = '<i class="fa-regular fa-calendar"></i> ' + d3.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    }

                    card.dataset.taskName = name;
                    card.dataset.taskPriority = priority;
                    card.dataset.taskDescription = descVal;
                    if (dateVal) card.dataset.taskDate = dateVal;
                    if (timeVal) card.dataset.taskTime = timeVal;
                };

                // Delegate to openEditModal — it handles all pre-filling including project+stage
                openEditModal(syntheticRow);
            });
        }
    })();
});
