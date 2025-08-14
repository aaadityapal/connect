<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $_SESSION['error'] = "You must log in to access this page";
    header('Location: login.php');
    exit();
}

$allowed_roles = ['Senior Manager (Site)'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: login.php');
    exit();
}

$userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        :root {
            --surface: #ffffff;
            --surface-2: #f7f8fa;
            --border: #e6e8ec;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #0d6efd;
            --soft-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 2px 8px rgba(16, 24, 40, 0.06);
        }
        body { background-color: var(--surface-2); color: var(--text); }
        .main-container { display: flex; height: 100vh; overflow: hidden; }
        .main-content { flex: 1; padding: 28px; overflow-y: auto; height: 100vh; box-sizing: border-box; margin-left: var(--panel-width); }
        .page-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--soft-shadow); padding: 18px; }
        .header-title { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .header-title h3 { font-size: 1.25rem; margin: 0; }
        .header-sub { color: var(--muted); font-size: 0.9rem; }
        .count-chip { background: #eef2ff; color: #3b4b6a; border: 1px solid #e3e8ff; border-radius: 999px; padding: 6px 10px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; }
        .count-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); display: inline-block; }
        .filters { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 12px; gap: 12px; box-shadow: var(--soft-shadow); }
        .filters { position: sticky; top: 18px; z-index: 10; }
        .filters .form-control { min-width: 140px; }
        .filters .filter-label { color: var(--muted); font-size: 0.75rem; margin-bottom: 4px; }
        .filters .input-group-text { border-left: 0; background: transparent; }
        .btn-outline-primary { border-color: var(--primary); color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); color: #fff; }
        .status-pills { display: flex; align-items: center; gap: 6px; }
        .status-pill { border: 1px solid var(--border); background: var(--surface); padding: 6px 10px; border-radius: 999px; font-size: .8rem; color: var(--text); cursor: pointer; transition: all .15s ease; }
        .status-pill:hover { border-color: #d7dbe2; box-shadow: var(--soft-shadow); }
        .status-pill.active { border-color: var(--primary); color: var(--primary); background: #f2f7ff; }
        .leave-requests-container { max-height: calc(100vh - 240px); overflow-y: auto; padding-right: 6px; }
        .leave-requests-container::-webkit-scrollbar { width: 8px; }
        .leave-requests-container::-webkit-scrollbar-thumb { background: #cfd4dc; border-radius: 10px; }
        .leave-request-card { transition: box-shadow .2s ease, transform .12s ease; }
        .leave-request-card .card { border: 1px solid var(--border); box-shadow: none; }
        .leave-request-card:hover { transform: translateY(-1px); }
        .avatar { min-width: 42px; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: .9rem; }
        .section-divider { height: 1px; background: var(--border); margin: 10px 0 12px; }
        .badge-soft { border-radius: 999px; padding: 4px 10px; font-size: .75rem; font-weight: 600; }
        .badge-soft-warning { color: #9a6b00; background: #fff7e6; border: 1px solid #ffedc2; }
        .badge-soft-success { color: #166534; background: #e8f7ef; border: 1px solid #c8efdb; }
        .badge-soft-danger { color: #8a2323; background: #feecec; border: 1px solid #f8d2d2; }
        .meta { color: var(--muted); font-size: .8rem; }
        .meta i { color: var(--primary); opacity: .7; width: 14px; text-align: center; margin-right: 6px; }
        .reason { color: var(--text); font-size: .85rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .subtle { color: var(--muted); }
        .skeleton { background: linear-gradient(90deg, #f2f4f7 25%, #eceff3 37%, #f2f4f7 63%); border-radius: 6px; background-size: 400% 100%; animation: shimmer 1.2s infinite; }
        @keyframes shimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .leave-requests-container { max-height: unset; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include_once('includes/manager_panel.php'); ?>
        <div class="main-content">
            <div class="page-card mb-3">
                <div class="header-title">
                    <div>
                        <h3>Leave Requests</h3>
                        <div class="header-sub">Minimal view of employees' leave requests</div>
                    </div>
                    <div class="count-chip" id="totalRequestsChip"><span class="dot"></span> Total: 0</div>
                </div>
                <div class="mt-3 filters d-flex flex-wrap align-items-end">
                    <div class="mr-2 mb-2">
                        <div class="filter-label">Status</div>
                        <select id="statusFilter" class="form-control form-control-sm">
                            <option value="pending" selected>Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="mr-2 mb-2">
                        <div class="filter-label">Month</div>
                        <select id="monthYearFilter" class="form-control form-control-sm">
                            <option value="">All Dates</option>
                        </select>
                    </div>
                    <div class="mr-2 mb-2" style="min-width: 260px;">
                        <div class="filter-label">Search</div>
                        <div class="input-group input-group-sm">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name, type, reason...">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-search subtle"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="ml-auto mb-2">
                        <button id="refreshBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="page-card">
                <div id="listContainer">
                    <div class="py-4">
                        <div class="skeleton" style="height: 16px; width: 180px; margin-bottom: 14px;"></div>
                        <div class="skeleton" style="height: 82px; width: 100%; margin-bottom: 10px;"></div>
                        <div class="skeleton" style="height: 82px; width: 100%; margin-bottom: 10px;"></div>
                        <div class="skeleton" style="height: 82px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function populateMonthYearDropdown(targetId) {
            const dropdown = document.getElementById(targetId);
            if (!dropdown) return;
            while (dropdown.options.length > 1) dropdown.remove(1);
            const now = new Date();
            for (let i = 0; i < 12; i++) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const m = d.getMonth() + 1;
                const y = d.getFullYear();
                const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                const opt = document.createElement('option');
                opt.value = `${m}-${y}`;
                opt.textContent = `${monthNames[m - 1]} ${y}`;
                dropdown.appendChild(opt);
            }
        }

        function getInitials(name) {
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
            return parts[0].substring(0, 2).toUpperCase();
        }

        function getRandomColor(seed) {
            if (!seed) return '#6c757d';
            let hash = 0;
            for (let i = 0; i < seed.length; i++) hash = seed.charCodeAt(i) + ((hash << 5) - hash);
            const colors = ['#007bff','#28a745','#17a2b8','#6f42c1','#e83e8c','#fd7e14','#20c997','#6610f2'];
            return colors[Math.abs(hash) % colors.length];
        }

        function getStatusBadge(status) {
            let cls = 'badge-secondary', icon = 'question', text = status;
            if (status === 'pending') { cls = 'badge-warning'; icon = 'clock'; text = 'Pending'; }
            if (status === 'approved') { cls = 'badge-success'; icon = 'check'; text = 'Approved'; }
            if (status === 'rejected') { cls = 'badge-danger'; icon = 'times'; text = 'Rejected'; }
            return `<span class="badge ${cls} badge-pill"><i class="fas fa-${icon} mr-1"></i>${text}</span>`;
        }

        function showToast(message, type = 'info') {
            if (typeof window.showNotification === 'function') { window.showNotification(message, type); return; }
            alert(message);
        }

        function buildCard(request) {
            const typeColor = request.color_code || '#607D8B';
            const halfInfo = request.has_half_day_info ? ` (${request.half_day_type === 'first_half' ? 'Morning' : 'Afternoon'})` : '';
            const compInfo = (request.is_compensate_leave && request.comp_off_source_date) ? ' (Comp)' : '';
            const shortTime = request.is_short_leave ? `<div class="col-12 col-sm-6 mb-1"><strong>Time:</strong> ${request.time_from || 'N/A'} - ${request.time_to || 'N/A'}</div>` : '';
            const halfDetail = request.has_half_day_info ? `<div class="col-12 col-sm-6 mb-1"><strong>Half Day Type:</strong> ${request.half_day_type === 'first_half' ? 'First Half (Morning)' : 'Second Half (Afternoon)'}</div>` : '';
            const actions = request.status === 'pending' ? `
                <div class="d-flex flex-wrap justify-content-end mt-3">
                    <button type="button" class="btn btn-outline-success btn-sm mr-2 approve-btn" data-id="${request.id}"><i class="fas fa-check mr-1"></i>Approve</button>
                    <button type="button" class="btn btn-outline-danger btn-sm reject-btn" data-id="${request.id}"><i class="fas fa-times mr-1"></i>Reject</button>
                </div>` : '';

            return `
            <div class="leave-request-card mb-3" data-request-id="${request.id}">
                <div class="card border-0">
                    <div class="card-body p-3 position-relative">
                        <div class="d-flex align-items-start mb-2">
                            <div class="mr-3" style="min-width: 44px; width: 44px; height: 44px; background:${getRandomColor(request.name)}; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">${getInitials(request.name)}</div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0 font-weight-bold text-truncate" title="${request.name}">${request.name}</h6>
                                    ${getStatusBadge(request.status)}
                                </div>
                                <small class="text-muted">${request.created_at || ''}</small>
                            </div>
                        </div>
                        <div class="mb-2 pl-2" style="border-left: 3px solid ${typeColor};">
                            <div class="row">
                                <div class="col-12 col-sm-6 mb-1"><strong>Type:</strong> ${request.leave_type_name || request.leave_type}${halfInfo}${compInfo}</div>
                                <div class="col-12 col-sm-6 mb-1"><strong>Duration:</strong> ${request.duration}</div>
                                <div class="col-12 col-sm-6 mb-1"><strong>Date Range:</strong> ${request.formatted_start_date} ${request.start_date !== request.end_date ? ' to ' + request.formatted_end_date : ''}</div>
                                ${shortTime}
                                ${halfDetail}
                                <div class="col-12 mb-1"><strong>Reason:</strong> <span title="${request.reason || 'No reason provided'}">${request.reason || 'No reason provided'}</span></div>
                            </div>
                        </div>
                        ${actions}
                    </div>
                </div>
            </div>`;
        }

        function renderList(data) {
            const container = document.getElementById('listContainer');
            const totalChip = document.getElementById('totalRequestsChip');
            if (!data || !data.success) {
                container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>${(data && data.error) || 'Failed to load leave requests'}</div>`;
                if (totalChip) totalChip.textContent = 'Total: 0';
                return;
            }
            if (totalChip) totalChip.textContent = `Total: ${data.total_requests}`;
            const items = data.leave_requests || [];
            if (!items.length) {
                container.innerHTML = `<div class="alert alert-info mb-0"><i class="fas fa-info-circle mr-2"></i>No leave requests found.</div>`;
                return;
            }
            let html = `<div class="leave-requests-container">`;
            items.forEach(req => { html += buildCard(req); });
            html += `</div>`;
            container.innerHTML = html;
            bindActionButtons();
            applySearchFilter();
        }

        function fetchData() {
            const status = document.getElementById('statusFilter').value || 'pending';
            const monthYear = document.getElementById('monthYearFilter').value || '';
            let url = `ajax_handlers/fetch_pending_leave_requests.php?status=${encodeURIComponent(status)}`;
            if (monthYear) {
                const [m, y] = monthYear.split('-');
                url += `&month=${encodeURIComponent(m)}&year=${encodeURIComponent(y)}`;
            }
            document.getElementById('listContainer').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3 text-muted">Loading leave requests...</p>
                </div>`;
            fetch(url)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(renderList)
                .catch(err => {
                    console.error(err);
                    renderList({ success: false, error: 'Network error while fetching data' });
                });
        }

        function bindActionButtons() {
            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', () => openReasonModal('approve', btn.getAttribute('data-id')));
            });
            document.querySelectorAll('.reject-btn').forEach(btn => {
                btn.addEventListener('click', () => openReasonModal('reject', btn.getAttribute('data-id')));
            });
        }

        function openReasonModal(action, requestId) {
            const isApprove = action === 'approve';
            let modal = document.getElementById('leaveActionReasonModal');
            if (!modal) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                <div class="modal fade" id="leaveActionReasonModal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header py-2">
                                <h6 class="modal-title" id="leaveActionReasonLabel"></h6>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body pt-3 pb-2">
                                <div class="form-group mb-2">
                                    <label for="leaveActionReasonInput" class="small mb-1">Reason</label>
                                    <textarea id="leaveActionReasonInput" class="form-control" rows="3" placeholder=""></textarea>
                                    <small class="form-text text-muted">This note will be saved with the action.</small>
                                </div>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary btn-sm" id="leaveActionReasonSubmitBtn"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                            </div>
                        </div>
                    </div>
                </div>`;
                document.body.appendChild(wrapper.firstElementChild);
                modal = document.getElementById('leaveActionReasonModal');
            }
            document.getElementById('leaveActionReasonLabel').textContent = `${isApprove ? 'Approve' : 'Reject'} Leave Request`;
            const input = document.getElementById('leaveActionReasonInput');
            input.value = '';
            input.placeholder = isApprove ? 'Reason for approval (optional)...' : 'Reason for rejection (required)...';
            const submitBtn = document.getElementById('leaveActionReasonSubmitBtn');
            submitBtn.replaceWith(submitBtn.cloneNode(true));
            document.getElementById('leaveActionReasonSubmitBtn').addEventListener('click', () => {
                const reason = (input.value || '').trim();
                if (!isApprove && reason.length === 0) { showToast('Please provide a reason for rejection.', 'error'); return; }
                submitAction(action, requestId, reason);
            }, { once: true });
            $('#leaveActionReasonModal').modal('show');
        }

        function submitAction(action, id, reason) {
            const body = new URLSearchParams({ id, action, reason }).toString();
            fetch('ajax_handlers/update_leave_status.php', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    $('#leaveActionReasonModal').modal('hide');
                    showToast(data.message || 'Leave updated successfully', 'success');
                    fetchData();
                } else {
                    showToast((data && data.error) || 'Failed to update leave request', 'error');
                }
            })
            .catch(() => showToast('Network error while updating leave request', 'error'));
        }

        function applySearchFilter() {
            const input = document.getElementById('searchInput');
            const term = (input.value || '').toLowerCase();
            document.querySelectorAll('.leave-request-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? '' : 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            populateMonthYearDropdown('monthYearFilter');
            document.getElementById('statusFilter').addEventListener('change', fetchData);
            document.getElementById('monthYearFilter').addEventListener('change', fetchData);
            document.getElementById('refreshBtn').addEventListener('click', fetchData);
            document.getElementById('searchInput').addEventListener('input', applySearchFilter);
            fetchData();
        });
    </script>
</body>
</html>


