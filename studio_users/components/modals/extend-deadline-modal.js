// ================================================
// EXTEND DEADLINE MODAL — Logic
// File: components/modals/extend-deadline-modal.js
// ================================================

(function () {

    // ── DOM refs ────────────────────────────────────────────────────────────────
    const overlay    = document.getElementById('extendDeadlineModal');
    const closeBtn   = document.getElementById('extendDeadlineCloseBtn');
    const cancelBtn  = document.getElementById('extendDeadlineCancelBtn');
    const applyBtn   = document.getElementById('extendDeadlineApplyBtn');
    const taskLabel  = document.getElementById('extendDeadlineTaskName');
    const previewRow = document.getElementById('extendCurrentDeadlineRow');
    const previewVal = document.getElementById('extendPreviewValue');
    const dateInput  = document.getElementById('extendNewDate');
    const timeInput  = document.getElementById('extendNewTime');
    const quickBtns  = document.querySelectorAll('.edm-quick-btn');
    const histRow    = document.getElementById('extendHistoryRow');
    const histText   = document.getElementById('extendHistoryText');
    const edmCard    = overlay.querySelector('.edm-card');

    const currDbVal  = document.getElementById('extendCurrentDeadlineValue');
    const btnViewHistory = document.getElementById('extendViewHistoryBtn');
    const formContainer  = document.getElementById('extendFormContainer');
    const historyView    = document.getElementById('extendHistoryListView');
    const btnBack        = document.getElementById('extendBackToFormBtn');
    const histItems      = document.getElementById('extendHistoryItems');

    if (!overlay) return; // guard if HTML not loaded

    // ── State ────────────────────────────────────────────────────────────────────
    let _task        = null;   // current task object
    let _period      = null;   // current period key (daily/weekly/…)
    let _newDeadline = null;   // Date object for the selected new deadline
    let _onApply     = null;   // callback(newDeadline, task, period)

    // ── Open / Close ─────────────────────────────────────────────────────────────
    function openExtendDeadlineModal(task, period, onApplyCallback) {
        _task     = task;
        _period   = period;
        _onApply  = onApplyCallback || null;
        _newDeadline = null;

        // Reset UI
        quickBtns.forEach(b => b.classList.remove('edm-selected'));
        dateInput.value = '';
        timeInput.value = '';
        previewRow.style.display = 'none';
        applyBtn.disabled = true;

        if (formContainer) formContainer.style.display = 'block';
        if (historyView) historyView.style.display = 'none';
        applyBtn.style.display = 'flex';
        cancelBtn.style.display = 'inline-block';

        // Set subtitle label from task title
        if (taskLabel) taskLabel.textContent = task.title || 'Selected task';

        // Set current deadline label
        if (currDbVal) {
            if (task.due_date) {
                const timeStr = task.due_time_24 || '23:59';
                const [y, m, d] = task.due_date.split('-').map(Number);
                const [hh, mm] = timeStr.split(':').map(Number);
                const localD = new Date(y, m - 1, d, hh, mm);
                
                currDbVal.textContent = localD.toLocaleDateString('en-US', {
                    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
                }) + ', ' + localD.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            } else {
                currDbVal.textContent = 'No Deadline';
            }
        }

        // Show extension history if available
        if (histRow && histText) {
            if (task.extension_count > 0) {
                // Safely parse previous date assuming YYYY-MM-DD string
                let prevInfo = '';
                if (task.previous_due_date) {
                    const parts = task.previous_due_date.split('-');
                    if (parts.length === 3) {
                        const prevDate = new Date(parts[0], parts[1] - 1, parts[2]);
                        const dtStr = prevDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        // Formatting previous time string manually if present
                        let tmStr = '';
                        if (task.previous_due_time) {
                            tmStr = ` ${task.previous_due_time}`;
                        }
                        prevInfo = ` (Previously: ${dtStr}${tmStr})`;
                    }
                }
                const timesWord = task.extension_count === 1 ? 'time' : 'times';
                histText.textContent = `Extended ${task.extension_count} ${timesWord} before${prevInfo}`;
                histRow.style.display = 'flex';
            } else {
                histRow.style.display = 'none';
            }
        }

        // Calculate 'Running Week Sunday 8 PM' Limit
        const nowLimit = new Date();
        const dayIdx   = nowLimit.getDay(); // 0=Sun
        const daysToSun = (7 - dayIdx) % 7;
        const sundayLimit = new Date(nowLimit);
        sundayLimit.setDate(nowLimit.getDate() + daysToSun);
        sundayLimit.setHours(20, 0, 0, 0); // Hard limit: Sunday 8 PM
        
        const yyyyL = sundayLimit.getFullYear();
        const mmL   = String(sundayLimit.getMonth() + 1).padStart(2, '0');
        const ddL   = String(sundayLimit.getDate()).padStart(2, '0');
        const maxDateStr = `${yyyyL}-${mmL}-${ddL}`;
        
        dateInput.max = maxDateStr;

        // Pre-fill custom inputs
        if (task.due_date) {
            dateInput.value = task.due_date;
            dateInput.min   = task.due_date;
            timeInput.value = task.due_time_24 || '23:59';
        } else {
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm   = String(now.getMonth() + 1).padStart(2, '0');
            const dd   = String(now.getDate()).padStart(2, '0');
            const hh   = String(now.getHours()).padStart(2, '0');
            const min  = String(now.getMinutes()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;
            dateInput.value = todayStr;
            dateInput.min   = todayStr;
            timeInput.value = `${hh}:${min}`;
        }

        // Apply dynamic theme color based on urgency
        if (edmCard) {
            edmCard.classList.remove('theme-red', 'theme-yellow', 'theme-green');
            if (task.checked || task.status === 'Completed' || !task.deadline) {
                edmCard.classList.add('theme-green');
            } else {
                const baseD = getBaseDeadline();
                const diffHours = (baseD - new Date()) / 3600000;
                if      (diffHours < 3)  edmCard.classList.add('theme-red');
                else if (diffHours < 6)  { /* default orange, do nothing */ }
                else if (diffHours < 48) edmCard.classList.add('theme-yellow');
                else                     edmCard.classList.add('theme-green');
            }
        }

        overlay.classList.add('edm-open');
        document.body.style.overflow = 'hidden';
    }

    function closeExtendDeadlineModal() {
        overlay.classList.remove('edm-open');
        document.body.style.overflow = '';
        _task = null;
        _period = null;
        _newDeadline = null;
        _onApply = null;
    }

    // Expose globally so my-tasks.js can call it
    window.openExtendDeadlineModal = openExtendDeadlineModal;

    // ── Preview helper ────────────────────────────────────────────────────────────
    function showPreview(date) {
        if (!date || isNaN(date.getTime())) {
            previewRow.style.display = 'none';
            applyBtn.disabled = true;
            _newDeadline = null;
            return;
        }

        // ── Hard Limit 1: Cannot exceed 8:00 PM on the same day ──────────────
        const dailyLimit = new Date(date);
        dailyLimit.setHours(20, 0, 0, 0); // 8:00 PM of the chosen day
        if (date > dailyLimit) {
            // Auto-clamp to 8:00 PM and warn the user
            date = dailyLimit;
            // Sync inputs to reflect the clamped value
            if (timeInput) timeInput.value = '20:00';
            if (dateInput) {
                const y  = date.getFullYear();
                const mo = String(date.getMonth() + 1).padStart(2, '0');
                const d  = String(date.getDate()).padStart(2, '0');
                dateInput.value = `${y}-${mo}-${d}`;
            }
            // Warn the user that the time was auto-adjusted
            const msg = "Deadline auto-adjusted to 08:00 PM — tasks cannot be extended past 8 PM.";
            if (window.showCustomAlert) {
                window.showCustomAlert(msg, "Auto-Adjusted to 08:00 PM", "info");
            } else {
                alert(msg);
            }
        }

        // ── Hard Limit 2: Cannot exceed this week's Sunday 8 PM ──────────────
        const dayIdx      = new Date().getDay();
        const daysToSun   = (7 - dayIdx) % 7;
        const sundayLimit = new Date();
        sundayLimit.setDate(new Date().getDate() + daysToSun);
        sundayLimit.setHours(20, 0, 0, 0);

        if (date > sundayLimit) {
            const msg = "Task extensions are limited to Sunday 8:00 PM of the current week.";
            if (window.showCustomAlert) {
                window.showCustomAlert(msg, "Weekly Policy Limit", "info");
            } else {
                alert(msg);
            }
            previewRow.style.display = 'none';
            applyBtn.disabled = true;
            _newDeadline = null;
            return;
        }

        _newDeadline = date;
        previewVal.textContent = date.toLocaleDateString('en-US', {
            weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
        }) + ' ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        previewRow.style.display = 'flex';
        applyBtn.disabled = false;
    }

    // ── Quick buttons ─────────────────────────────────────────────────────────────
    quickBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove previous selection
            quickBtns.forEach(b => b.classList.remove('edm-selected'));
            btn.classList.add('edm-selected');

            // Clear custom inputs
            dateInput.value = '';
            timeInput.value = '';

            const hours   = parseInt(btn.getAttribute('data-hours'), 10);
            // Always extend from the CURRENT time, not the old task deadline.
            // e.g. if now is 5:43 PM and user picks +3h → 8:43 PM (will fail daily limit).
            const base    = new Date();
            const newDate = new Date(base.getTime() + hours * 3600 * 1000);
            showPreview(newDate);
        });
    });

    // ── Custom date / time inputs ─────────────────────────────────────────────────
    function handleCustomChange() {
        if (!dateInput.value) {
            showPreview(null);
            return;
        }
        // Deselect quick buttons when custom is used
        quickBtns.forEach(b => b.classList.remove('edm-selected'));

        const timeStr = timeInput.value || '23:59';
        const newDate = new Date(dateInput.value + 'T' + timeStr + ':00');
        
        // Prevent cheating: date/time cannot be before or equal to current deadline
        const baseDate = getBaseDeadline();
        if (newDate <= baseDate) {
            if (window.showCustomAlert) {
                window.showCustomAlert("The extended deadline must be after the current deadline.", "Invalid Extension", "error");
            } else {
                alert("The extended deadline must be after the current deadline.");
            }
            timeInput.value = ""; // Force them to pick a valid time instead
            showPreview(null);
            return;
        }

        showPreview(newDate);
    }

    dateInput.addEventListener('change', handleCustomChange);
    timeInput.addEventListener('change', handleCustomChange);

    // ── Apply ─────────────────────────────────────────────────────────────────────
    applyBtn.addEventListener('click', () => {
        if (!_newDeadline || !_task) return;

        const originalBtnHTML = applyBtn.innerHTML;
        applyBtn.disabled = true;
        applyBtn.style.opacity = '0.7';
        applyBtn.style.cursor = 'wait';
        applyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        // Update task object in-memory
        _task.deadline = _newDeadline.toISOString();
        _task.due_date = _newDeadline.toISOString().slice(0, 10);
        _task.due_time = _newDeadline.toTimeString().slice(0, 5); // HH:MM
        _task.time     = _newDeadline.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        // Persist to DB
        fetch('api/extend_task_deadline.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: _task.id,
                due_date: _task.due_date,
                due_time: _task.due_time + ':00'  // ensure HH:MM:SS
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Trigger global event for other components (like Action Required modal)
                window.dispatchEvent(new Event('taskUpdate'));
                
                // Re-render My Tasks list ONLY after DB sync is confirmed
                if (typeof window.renderTasks === 'function') {
                    window.renderTasks(_period || window.currentFilter || 'daily');
                }
            } else {
                console.warn('[ExtendModal] DB update failed:', data.error);
            }
        })
        .catch(err => console.warn('[ExtendModal] Network error:', err))
        .finally(() => {
            applyBtn.disabled = false;
            applyBtn.style.opacity = '';
            applyBtn.style.cursor = '';
            applyBtn.innerHTML = originalBtnHTML;
            
            // Fire caller callback if provided
            if (typeof _onApply === 'function') _onApply(_newDeadline, _task, _period);

            // Play sound
            try {
                const extendAudio = new Audio('tones/tesk_extended.wav');
                extendAudio.play();
            } catch(e) { console.warn('Could not play audio', e); }

            closeExtendDeadlineModal();
        });
    });

    // ── Close triggers ────────────────────────────────────────────────────────────
    closeBtn.addEventListener('click',  closeExtendDeadlineModal);
    cancelBtn.addEventListener('click', closeExtendDeadlineModal);
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeExtendDeadlineModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && overlay.classList.contains('edm-open')) {
            closeExtendDeadlineModal();
        }
    });

    // ── History View Toggles ──────────────────────────────────────────────────────
    if (btnViewHistory && formContainer && historyView && btnBack) {
        btnViewHistory.addEventListener('click', () => {
            if (!_task) return;
            formContainer.style.display = 'none';
            applyBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            historyView.style.display = 'flex';
            histItems.innerHTML = '<div style="padding: 1rem; text-align: center; color: #94a3b8; font-size: 0.8rem;">Loading...</div>';

            fetch(`api/fetch_task_history.php?task_id=${_task.id}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || data.count === 0) {
                        histItems.innerHTML = '<div style="padding: 1rem; text-align: center; color: #94a3b8; font-size: 0.8rem;">No history found.</div>';
                        return;
                    }
                    histItems.innerHTML = '';
                    data.history.forEach(log => {
                        const card = document.createElement('div');
                        card.className = 'edm-history-item';

                        const dtObj = new Date(log.timestamp);
                        let dtStr = 'Unknown Date';
                        if (!isNaN(dtObj.getTime())) {
                            dtStr = dtObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + dtObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        }

                        card.innerHTML = `
                            <div class="edm-hi-date">${dtStr}</div>
                            <div class="edm-hi-desc">${log.description}</div>
                            <div class="edm-hi-author"><i class="fa-solid fa-user-clock"></i> ${log.author}</div>
                        `;
                        histItems.appendChild(card);
                    });
                })
                .catch(err => {
                    console.error('Error fetching history:', err);
                    histItems.innerHTML = '<div style="padding: 1rem; text-align: center; color: #ef4444; font-size: 0.8rem;">Error loading history.</div>';
                });
        });

        btnBack.addEventListener('click', () => {
            historyView.style.display = 'none';
            formContainer.style.display = 'block';
            applyBtn.style.display = 'flex';
            cancelBtn.style.display = 'inline-block';
        });
    }

    // ── Helper: get base date to add hours onto ────────────────────────────────
    function getBaseDeadline() {
        // If existing deadline, extend from there; otherwise from now
        if (_task && _task.due_date) {
            const timeStr = _task.due_time_24 || '23:59';
            const [y, m, d] = _task.due_date.split('-').map(Number);
            const [hh, mm] = timeStr.split(':').map(Number);
            return new Date(y, m - 1, d, hh, mm);
        }
        return new Date();
    }

})();
