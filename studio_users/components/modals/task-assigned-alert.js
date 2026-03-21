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

        // --- Populate fields ---
        setText('taaProject',     meta.project_name     || '—');
        setText('taaStage',       meta.stage_number     ? 'Stage ' + meta.stage_number : '—');
        setText('taaDescription', meta.task_description || log.description || '—');

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
