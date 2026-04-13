// components/table/table.js
import { openDetailModal, openEditModal, openConfirmModal } from '../modal/modal.js';
import { renderMetrics } from '../metrics/metrics.js';
import { renderUserFilter } from '../filters/filters.js';

/* ─────────────────────────────────────────────────────
   Data state logic
   ───────────────────────────────────────────────────── */
let tableData = [];

export async function fetchOvertimeData() {
    try {
        const response = await fetch(`api/fetch_overtime.php`);
        const result = await response.json();
        if (result.success) {
            tableData = result.data;
            window.HAS_UNSUBMITTED_PERM = result.hasUnsubmittedPerm;
            window.HAS_EXPIRED_PERM = result.hasExpiredPerm;
            window.HAS_MODIFY_COMPLETED_PERM = result.hasModifyCompletedPerm;
            renderTableData();
            renderUserFilter(tableData);
        } else {
            console.error('Failed to fetch overtime data:', result.message);
        }
    } catch (error) {
        console.error('Error fetching overtime data:', error);
    }
}

async function updateOvertimeStatus(attendanceId, status, comments = '', otHours = null) {
    try {
        const response = await fetch('api/update_overtime.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attendance_id: attendanceId,
                status: status,
                comments: comments,
                otHours: otHours
            })
        });
        const result = await response.json();
        if (result.success) {
            await fetchOvertimeData();
            return true;
        } else {
            alert('Update failed: ' + result.message);
            return false;
        }
    } catch (error) {
        console.error('Error updating overtime status:', error);
        return false;
    }
}

/* ─────────────────────────────────────────────────────
   Helpers
   ───────────────────────────────────────────────────── */
function getInitials(name) {
    if (!name) return '??';
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
}

function formatDate(dateStr) {
    if (!dateStr || dateStr === '—') return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function truncateText(text, maxLength = 30) {
    if (!text) return '—';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/* ─────────────────────────────────────────────────────
   Render table rows
   ───────────────────────────────────────────────────── */
export function renderTableData() {
    const tableBody      = document.getElementById('table-body');
    const emptyState     = document.getElementById('empty-state');
    const tableContainer = document.querySelector('.table-container');

    if (!tableBody) return;

    // Get current filter values
    const userFilter   = (document.getElementById('filter-user')?.value || 'all').toLowerCase();
    const statusFilter = (document.getElementById('filter-status')?.value || 'all').toLowerCase();
    const monthFilter  = (document.getElementById('filter-month')?.value || 'all');
    const yearFilter   = (document.getElementById('filter-year')?.value || 'all');

    let filtered = tableData.filter(item => {
        // User filter
        const matchUser = userFilter === 'all' || item.employee.toLowerCase() === userFilter;
        
        // Status filter
        const matchStatus = statusFilter === 'all' || item.status.toLowerCase() === statusFilter;
        
        // Date filters
        const dateObj = new Date(item.date);
        const itemMonth = (dateObj.getMonth() + 1).toString().padStart(2, '0');
        const itemYear = dateObj.getFullYear().toString();
        
        const matchMonth = monthFilter === 'all' || itemMonth === monthFilter;
        const matchYear = yearFilter === 'all' || itemYear === yearFilter;

        return matchUser && matchStatus && matchMonth && matchYear;
    });

    // Update metrics
    renderMetrics(filtered);

    tableBody.innerHTML = '';

    if (filtered.length === 0) {
        document.querySelector('.data-table').style.display = 'none';
        emptyState.classList.remove('hidden');
        return;
    }

    document.querySelector('.data-table').style.display = '';
    emptyState.classList.add('hidden');

    filtered.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.style.animationDelay = `${index * 0.05}s`;
        const badgeClass = `ot-badge ot-badge-${row.status.toLowerCase()}`;

        // Permission-based action logic
        const isSubmitted = row.request_id !== null && row.request_id !== '';
        const status = row.status.toLowerCase();
        const isTerminal = ['approved', 'rejected', 'paid'].includes(status);
        
        let canAction = false;
        if (isTerminal) {
            canAction = window.HAS_MODIFY_COMPLETED_PERM;
        } else if (status === 'expired') {
            canAction = window.HAS_EXPIRED_PERM;
        } else {
            // Pending cases
            canAction = isSubmitted || window.HAS_UNSUBMITTED_PERM;
        }

        tr.innerHTML = `
            <td>
                <div class="employee-name">
                    <div class="employee-avatar">${getInitials(row.employee)}</div>
                    ${row.employee}
                </div>
            </td>
            <td>${formatDate(row.date)}</td>
            <td>${row.endTime}</td>
            <td>${row.punchOut}</td>
            <td><strong>${row.otHours}h</strong></td>
            <td class="highlighted-cell">${row.submittedOt}h</td>
            <td><span class="truncate js-view-btn" data-atid="${row.attendance_id}" title="${(row.workReport || '').replace(/"/g, '&quot;')}">${truncateText(row.workReport)}</span></td>
            <td><span class="truncate js-view-btn" data-atid="${row.attendance_id}" title="${(row.otReport || '').replace(/"/g, '&quot;')}">${truncateText(row.otReport)}</span></td>
            <td><span class="${badgeClass}">${row.status}</span></td>
            <td>
                <div class="ot-action-icons">
                    <button class="ot-action-btn approve js-approve-btn" title="Approve" data-atid="${row.attendance_id}" style="${!canAction ? 'display:none;' : ''}">
                        <i class="ph-fill ph-check-circle"></i>
                    </button>
                    <button class="ot-action-btn reject js-reject-btn" title="Reject" data-atid="${row.attendance_id}" style="${!canAction ? 'display:none;' : ''}">
                        <i class="ph-fill ph-x-circle"></i>
                    </button>
                    <button class="ot-action-btn edit js-edit-btn" title="Edit Hours" data-atid="${row.attendance_id}" style="${!canAction ? 'display:none;' : ''}">
                        <i class="ph-bold ph-pencil-simple"></i>
                    </button>
                    <button class="ot-action-btn view js-view-btn" title="View Details" data-atid="${row.attendance_id}">
                        <i class="ph-bold ph-eye"></i>
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    // Wire events
    tableBody.querySelectorAll('.js-view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = tableData.find(d => d.attendance_id == btn.dataset.atid);
            if (data) openDetailModal(data);
        });
    });

    tableBody.querySelectorAll('.js-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = tableData.find(d => d.attendance_id == btn.dataset.atid);
            if (data) openEditModal(data, (newVal) => updateOvertimeStatus(data.attendance_id, data.status, data.managerComment, newVal));
        });
    });

    tableBody.querySelectorAll('.js-approve-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = tableData.find(d => d.attendance_id == btn.dataset.atid);
            if (data) openConfirmModal('approve', data, 
                (comment, adjustedHours) => updateOvertimeStatus(data.attendance_id, 'Approved', comment, adjustedHours)
            );
        });
    });

    tableBody.querySelectorAll('.js-reject-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = tableData.find(d => d.attendance_id == btn.dataset.atid);
            if (data) openConfirmModal('reject', data, (comment) => updateOvertimeStatus(data.attendance_id, 'Rejected', comment, data.submittedOt));
        });
    });
}

export function initTable() {
    fetchOvertimeData();
}

