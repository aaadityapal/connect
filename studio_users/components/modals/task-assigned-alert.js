/* ================================================
   TASK ASSIGNED ALERT MODAL — JavaScript
   File: components/modals/task-assigned-alert.js

   Usage:
   - Call TaskAssignedAlert.show(notifLog) passing a
     global_activity_logs row (with metadata JSON parsed).
   - The "Got it" button marks the notification as read
     via notification_actions.php?action=mark_single_read
   ================================================ */

const TaskAssignedAlert = (() => {
    let overlay, okBtn;
    let currentNotifId = null;

    function init() {
        overlay = document.getElementById('taskAssignedAlert');
        okBtn   = document.getElementById('taaOkBtn');

        if (!overlay || !okBtn) return;

        okBtn.addEventListener('click', handleOk);

        // Close on backdrop click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) handleOk();
        });
    }

    function show(log) {
        if (!overlay) init();
        if (!overlay) return;

        currentNotifId = log.id || null;

        // Parse metadata if it's a string
        let meta = {};
        try {
            meta = typeof log.metadata === 'string'
                ? JSON.parse(log.metadata)
                : (log.metadata || {});
        } catch (_) {}

        const currentUser = String(window.loggedUserName || '').trim().toLowerCase();

        const parseNames = (val) => {
            const dedup = (arr) => {
                const seen = new Set();
                return arr.filter(name => {
                    const key = String(name || '').trim().toLowerCase();
                    if (!key || seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });
            };

            if (Array.isArray(val)) {
                return dedup(val.map(v => String(v || '').trim()).filter(Boolean));
            }
            if (typeof val === 'string') {
                return dedup(val.split(',').map(v => v.trim()).filter(Boolean));
            }
            return [];
        };

        const assignedNames = parseNames(meta.assigned_names || meta.team_members || meta.assignees);
        const isTeamTask = assignedNames.length > 1;

        // Avoid showing current user name redundantly in long team strings
        const smartTeamText = assignedNames.length
            ? assignedNames.map(n => (currentUser && n.toLowerCase() === currentUser ? `${n} (You)` : n)).join(', ')
            : 'Only you';

        const assignedBy =
            (meta.assigned_by_name || meta.created_by_name || meta.assigned_by || meta.created_by || '').toString().trim() ||
            'System Admin';
        const isCreatorLog = /^\s*you assigned\s*:/i.test(String(log.description || ''));

        // --- Populate fields ---
        setText('taaProject',     meta.project_name     || '—');
        setText('taaStage',       meta.stage_number     ? 'Stage ' + meta.stage_number : '—');
        setText('taaDescription', meta.task_description || log.description || '—');
        setText('taaAssignedBy', assignedBy);
        setText('taaTeamMembers', smartTeamText);

        // Smart title/subtitle for creator vs assignee
        if (isCreatorLog) {
            setText('taaTitle', 'Task assignment sent!');
            setText('taaSubtitle', isTeamTask
                ? `You assigned this task to ${assignedNames.length} team members.`
                : 'You assigned this task successfully.');
        } else {
            setText('taaTitle', isTeamTask ? "You've been assigned a team task!" : "You've been assigned a task!");
            setText('taaSubtitle', isTeamTask
                ? `This task is assigned to ${assignedNames.length} team members including you.`
                : 'Here are the details of your new assignment:');
        }

        const teamRow = document.getElementById('taaTeamRow');
        if (teamRow) teamRow.style.display = assignedNames.length ? 'flex' : 'none';

        const assignedByRow = document.getElementById('taaAssignedByRow');
        if (assignedByRow) assignedByRow.style.display = assignedBy ? 'flex' : 'none';

        // Due date
        const dueDateRow = document.getElementById('taaDueDateRow');
        if (meta.due_date) {
            setText('taaDueDate', formatDate(meta.due_date));
            if (dueDateRow) dueDateRow.style.display = 'flex';
        } else {
            if (dueDateRow) dueDateRow.style.display = 'none';
        }

        // Received timestamp
        const receivedAt = log.created_at
            ? new Date(log.created_at).toLocaleString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: true
              })
            : '—';
        setText('taaReceivedAt', receivedAt);

        // Open modal
        overlay.classList.add('taa-open');
        document.body.style.overflow = 'hidden';
    }

    function handleOk() {
        // Mark this specific notification as read
        if (currentNotifId) {
            fetch('api/notification_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_single_read', notif_id: currentNotifId })
            }).catch(() => {});
        }

        // Close modal and refresh notification drawer
        close();

        // Refresh the notification list to reflect read status
        if (typeof fetchNotifications === 'function') {
            fetchNotifications();
        } else if (typeof window.fetchNotifications === 'function') {
            window.fetchNotifications();
        }
    }

    function close() {
        if (overlay) overlay.classList.remove('taa-open');
        document.body.style.overflow = '';
        currentNotifId = null;
    }

    // ── Helpers ─────────────────────────────────────
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function formatDate(dateStr) {
        try {
            return new Date(dateStr).toLocaleDateString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric'
            });
        } catch (_) {
            return dateStr;
        }
    }

    return { init, show, close };
})();

// Auto-init when DOM is ready
document.addEventListener('DOMContentLoaded', () => TaskAssignedAlert.init());
