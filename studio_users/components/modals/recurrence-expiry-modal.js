/* ================================================
   RECURRENCE EXPIRY MODAL — JavaScript
   File: components/modals/recurrence-expiry-modal.js

   Shows automatically when a task rendered in the My Tasks
   list has is_last_recurrence = true.

   Actions:
     "Mark as Done"      → updates task status to Completed
     "Extend Recurrence" → calls api/extend_recurrence.php
                           then refreshes the task list
   ================================================ */

const RecurrenceExpiryModal = (() => {
    let overlay, markDoneBtn, extendBtn, closeBtn;
    let currentTask = null;

    function init() {
        overlay     = document.getElementById('recurrenceExpiryModal');
        markDoneBtn = document.getElementById('remMarkDoneBtn');
        extendBtn   = document.getElementById('remExtendBtn');
        closeBtn    = document.getElementById('remCloseBtn');

        if (!overlay) return;

        markDoneBtn.addEventListener('click', handleMarkDone);
        extendBtn.addEventListener('click', handleExtend);
        closeBtn.addEventListener('click', close);

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
    }

    /** Call this from the task-rendering code when is_last_recurrence === true */
    function show(task) {
        if (!overlay) init();
        if (!overlay) return;

        currentTask = task;

        setText('remTaskName', task.title || task.desc || 'Recurring Task');
        setText('remFreq', task.recurrence_freq || 'Recurring');

        overlay.classList.add('rem-open');
        document.body.style.overflow = 'hidden';
    }

    function handleMarkDone() {
        if (!currentTask) return close();

        const masterTaskId = currentTask.master_task_id || parseInt(currentTask.id);

        // Call the existing update_task_status endpoint
        fetch('api/update_task_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: masterTaskId,
                status: 'Completed',
                is_recurring_close: true
            })
        })
        .then(r => r.json())
        .then(() => {
            showToastMsg('Recurring task marked as done. No more instances will be generated.', 'success');
            close();
            refreshTasks();
        })
        .catch(() => {
            showToastMsg('Could not mark as done. Please try again.', 'error');
        });
    }

    function handleExtend() {
        if (!currentTask) return close();

        const masterTaskId = currentTask.master_task_id || parseInt(currentTask.id);

        extendBtn.disabled = true;
        extendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Extending…';

        fetch('api/extend_recurrence.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: masterTaskId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const freq = currentTask.recurrence_freq || 'recurring';
                showToastMsg(`Recurrence extended! Another cycle of ${freq} instances added.`, 'success');
                close();
                refreshTasks();
            } else {
                showToastMsg(data.error || 'Could not extend recurrence.', 'error');
            }
        })
        .catch(() => {
            showToastMsg('Network error. Please try again.', 'error');
        })
        .finally(() => {
            if (extendBtn) {
                extendBtn.disabled = false;
                extendBtn.innerHTML = '<i class="fa-solid fa-clock-rotate-left"></i> Extend Recurrence';
            }
        });
    }

    function close() {
        if (overlay) overlay.classList.remove('rem-open');
        document.body.style.overflow = '';
        currentTask = null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function showToastMsg(msg, type) {
        if (typeof showToast === 'function') {
            showToast(msg, type);
        } else if (typeof showCustomAlert === 'function') {
            showCustomAlert(type === 'success' ? 'Done' : 'Error', msg, type);
        } else {
            alert(msg);
        }
    }

    function refreshTasks() {
        if (typeof loadRecentTasks === 'function') loadRecentTasks();
        // Also refresh the My Tasks list if the function is exposed
        if (typeof window.reloadMyTasks === 'function') window.reloadMyTasks();
    }

    return { init, show, close };
})();

document.addEventListener('DOMContentLoaded', () => RecurrenceExpiryModal.init());
