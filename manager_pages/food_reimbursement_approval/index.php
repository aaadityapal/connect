<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Reimbursement Approval | Connect</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <!-- Sidebar configuration -->
    <script>window.SIDEBAR_BASE_PATH = '../../studio_users/';</script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
    
    <style>
        /* ─── Reset & Base ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: #f0f4f8; color: #1e293b; }
        .dashboard-container { display: flex; min-height: 100vh; }
        #sidebar-mount { position: sticky; top: 0; height: 100vh; flex-shrink: 0; }
        .main-content { flex: 1; background: #f0f4f8; min-height: 100vh; overflow-x: hidden; }

        /* ─── Top Header ────────────────────────────────────────── */
        .page-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.25rem 2.5rem; background: #ffffff; border-bottom: 1px solid #e8edf5;
            position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-icon-wrap {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex; align-items: center; justify-content: center; color: white;
            box-shadow: 0 4px 14px rgba(59,130,246,0.35); flex-shrink: 0;
        }
        .topbar-title-block h1 { font-size: 1.2rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
        .topbar-title-block p { font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 2px; }

        /* ─── Page Content ──────────────────────────────────────── */
        .page-container { padding: 2rem 2.5rem; animation: fadeSlideUp 0.45s ease-out; }
        @keyframes fadeSlideUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        /* ─── Tabs ──────────────────────────────────────────────── */
        .tabs-nav {
            display: flex; gap: 0.5rem; margin-bottom: 1.75rem; background: #fff;
            border-radius: 14px; padding: 0.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.05); width: fit-content;
        }
        .tab-btn {
            padding: 0.65rem 1.5rem; border: none; background: transparent; border-radius: 10px;
            font-family: inherit; font-size: 0.9rem; font-weight: 600; color: #64748b;
            cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;
        }
        .tab-btn.active { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

        /* ─── Card & Table ──────────────────────────────────────── */
        .card { background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 28px -6px rgba(0,0,0,0.06); overflow: hidden; }
        .filter-bar { padding: 1.25rem 1.75rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 0.9rem 1.5rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; white-space: nowrap; text-align: left; }
        td { padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; text-align: left; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #fafbff; }

        /* Column Config */
        colgroup col:nth-child(1) { width: 22%; } /* Employee */
        colgroup col:nth-child(2) { width: 15%; } /* Date & Time */
        colgroup col:nth-child(3) { width: 12%; } /* Amount */
        colgroup col:nth-child(4) { width: 10%; } /* Mgr Status */
        colgroup col:nth-child(5) { width: 10%; } /* HR Status */
        colgroup col:nth-child(6) { width: 10%; } /* Payment */
        colgroup col:nth-child(7) { width: 21%; } /* Actions */

        /* ─── UI Elements ───────────────────────────────────────── */
        .emp-cell { display: flex; align-items: center; gap: 0.75rem; }
        .emp-avatar { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.82rem; color: #fff; flex-shrink: 0; }
        .emp-name { font-weight: 600; color: #1e293b; font-size: 0.9rem; }
        .emp-email { font-size: 0.75rem; color: #94a3b8; }

        .time-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .time-badge.late-severe { background: #fee2e2; color: #b91c1c; } /* 11PM+ */
        .time-badge.late-high { background: #fef3c7; color: #b45309; }   /* 10-11PM */
        .time-badge.late-mild { background: #f1f5f9; color: #475569; }   /* 9-10PM */

        .status-badge { display: inline-flex; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }
        .status-paid { background: #e0e7ff; color: #4338ca; }
        .status-unpaid { background: #f1f5f9; color: #475569; }

        .price-tag { font-weight: 700; color: #059669; font-size: 1.05rem; }

        .action-btns { display: flex; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-family: inherit; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.4rem; }
        .btn-approve { background: #dcfce7; color: #166534; }
        .btn-approve:hover { background: #bbf7d0; transform: translateY(-1px); }
        .btn-reject { background: #fee2e2; color: #b91c1c; }
        .btn-reject:hover { background: #fecaca; transform: translateY(-1px); }
        .btn-pay { background: #1e293b; color: white; }
        .btn-pay:hover { background: #334155; transform: translateY(-1px); }

        .state-cell { text-align: center; padding: 5rem 2rem; color: #94a3b8; }
        .spin { animation: spin360 1.2s linear infinite; display: inline-block; margin-bottom: 1rem; }
        @keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* Toast */
        #toast {
            position: fixed; bottom: 2rem; right: 2rem; padding: 0.9rem 1.6rem; border-radius: 12px;
            background: #1e293b; color: #fff; font-weight: 600; font-size: 0.9rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18); transform: translateY(120%);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 9999;
            display: flex; align-items: center; gap: 0.6rem;
        }
        #toast.show { transform: translateY(0); }
        #toast.success { background: #059669; }
        #toast.error { background: #dc2626; }

        /* Modal */
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-backdrop.open { display: flex; }
        .modal { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow: hidden; animation: slideUp 0.25s ease; }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
        .modal-close { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; color: #64748b; border-radius: 8px; transition: 0.15s; }
        .modal-close:hover { background: #f1f5f9; color: #0f172a; }
        .modal-body { padding: 1.5rem; flex: 1; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; background: #f8fafc; }
        
        /* Modal Checklists & Textbox */
        .check-group { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.25rem; }
        .check-item { display: flex; align-items: flex-start; gap: 0.75rem; }
        .check-item input[type="checkbox"] { margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; }
        .check-item label { font-size: 0.9rem; color: #334155; font-weight: 500; cursor: pointer; line-height: 1.4; }
        
        .textarea-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
        .textarea-wrap label { font-size: 0.85rem; font-weight: 600; color: #475569; }
        .textarea-wrap textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-family: inherit; font-size: 0.9rem; resize: vertical; min-height: 100px; transition: border 0.15s; }
        .textarea-wrap textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .word-count { font-size: 0.75rem; text-align: right; color: #94a3b8; margin-top: -4px; }

        .btn-modal { padding: 0.65rem 1.25rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.15s; font-family: inherit; }
        .btn-cancel { background: white; border: 1px solid #cbd5e1; color: #475569; }
        .btn-cancel:hover { background: #f8fafc; }
        .btn-confirm-approve { background: #16a34a; color: white; }
        .btn-confirm-approve:hover { background: #15803d; }
        .btn-confirm-reject { background: #dc2626; color: white; }
        .btn-confirm-reject:hover { background: #b91c1c; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div id="sidebar-mount"></div>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="page-topbar">
            <div class="topbar-left">
                <div class="topbar-icon-wrap">
                    <i data-lucide="check-square" style="width:20px;height:20px;"></i>
                </div>
                <div class="topbar-title-block">
                    <h1>Food Reimbursement Approval</h1>
                    <p>Review and approve late-night meal claims</p>
                </div>
            </div>
        </div>

        <div class="page-container">
            <!-- Filter Bar -->
            <div class="filter-bar" style="margin-bottom: 1rem; border-radius: 12px;">
                <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <select id="filterUser" style="padding: 0.5rem; border-radius: 8px; border: 1px solid #cbd5e1; outline:none; min-width:150px;">
                        <option value="">All Employees</option>
                    </select>
                    <select id="filterMonth" style="padding: 0.5rem; border-radius: 8px; border: 1px solid #cbd5e1; outline:none; min-width:120px;">
                        <option value="">All Months</option>
                        <option value="01">January</option><option value="02">February</option><option value="03">March</option>
                        <option value="04">April</option><option value="05">May</option><option value="06">June</option>
                        <option value="07">July</option><option value="08">August</option><option value="09">September</option>
                        <option value="10">October</option><option value="11">November</option><option value="12">December</option>
                    </select>
                    <select id="filterYear" style="padding: 0.5rem; border-radius: 8px; border: 1px solid #cbd5e1; outline:none; min-width:100px;">
                        <option value="">All Years</option>
                        <!-- Populated dynamically -->
                    </select>
                    <select id="filterStatus" style="padding: 0.5rem; border-radius: 8px; border: 1px solid #cbd5e1; outline:none; min-width:130px;">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
            </div>

            <!-- Card/Table -->
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <colgroup>
                            <col style="width:22%">
                            <col style="width:15%">
                            <col style="width:12%">
                            <col style="width:10%">
                            <col style="width:10%">
                            <col style="width:10%">
                            <col style="width:21%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Manager</th>
                                <th>HR</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="claimsTableBody">
                            <tr>
                                <td colspan="7" class="state-cell">
                                    <div class="spin"><i data-lucide="loader-2" style="width:36px;height:36px;"></i></div>
                                    <p>Loading claims…</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Toast -->
<div id="toast">
    <i data-lucide="check-circle" id="toastIcon" style="width:16px;height:16px;"></i>
    <span id="toastMsg">Action successful</span>
</div>

<!-- Action Modal -->
<div class="modal-backdrop" id="actionModalBackdrop">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="actionModalTitle">Process Claim</div>
            <button class="modal-close" onclick="closeActionModal()"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <!-- Checkpoints (only for approve) -->
            <div class="check-group" id="approveCheckpoints" style="display:none;">
                <div class="check-item">
                    <input type="checkbox" id="chk1">
                    <label for="chk1">I have verified the attendance punch-out time is accurate.</label>
                </div>
                <div class="check-item">
                    <input type="checkbox" id="chk2">
                    <label for="chk2">I have verified the claim amount is within the allowed budget limit.</label>
                </div>
                <div class="check-item">
                    <input type="checkbox" id="chk3">
                    <label for="chk3">The claim complies with the company's food reimbursement policy.</label>
                </div>
            </div>
            
            <div class="textarea-wrap">
                <label for="actionNote" id="actionNoteLabel">Note (Optional)</label>
                <textarea id="actionNote" placeholder="Enter additional details..."></textarea>
                <div class="word-count" id="wordCountText">0 words</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-cancel" onclick="closeActionModal()">Cancel</button>
            <button class="btn-modal btn-confirm-approve" id="btnConfirmAction" onclick="submitClaimAction()">Confirm</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('claimsTableBody');
    
    // Toast setup
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toastMsg');
    const toastIcon = document.getElementById('toastIcon');

    function showToast(msg, type = 'success') {
        toastMsg.textContent = msg;
        toast.className = `show ${type}`;
        toastIcon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 'alert-circle');
        if (window.lucide) lucide.createIcons();
        setTimeout(() => { toast.className = ''; }, 3000);
    }

    // Helpers
    function getInitials(name) { return (name||'').split(' ').map(n=>n[0]).join('').toUpperCase().substring(0,2); }
    function getHSL(str) { let h=0; for(let i=0;i<str.length;i++) h=str.charCodeAt(i)+((h<<5)-h); return `hsl(${Math.abs(h%360)},58%,44%)`; }
    function escapeHTML(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    let allClaims = [];
    
    // Filter Elements
    const filterUser = document.getElementById('filterUser');
    const filterMonth = document.getElementById('filterMonth');
    const filterYear = document.getElementById('filterYear');
    const filterStatus = document.getElementById('filterStatus');

    // Populate Year Dropdown dynamically
    const currentYear = new Date().getFullYear();
    for (let y = currentYear; y >= currentYear - 3; y--) {
        const opt = document.createElement('option');
        opt.value = y; opt.textContent = y;
        filterYear.appendChild(opt);
    }
    
    [filterUser, filterMonth, filterYear, filterStatus].forEach(el => {
        el.addEventListener('change', applyFilters);
    });

    // Load Data
    async function loadClaims() {
        tableBody.innerHTML = `<tr><td colspan="7" class="state-cell"><div class="spin"><i data-lucide="loader-2" style="width:36px;height:36px;"></i></div><p>Loading claims…</p></td></tr>`;
        if(window.lucide) lucide.createIcons();

        try {
            const res = await fetch(`api/get_pending_claims.php`);
            const data = await res.json();

            if (!data.success) {
                tableBody.innerHTML = `<tr><td colspan="7" class="state-cell" style="color:#dc2626;"><p>${data.error || 'Failed to load claims'}</p></td></tr>`;
                return;
            }

            allClaims = data.data || [];
            
            // Populate user dropdown filter
            const users = new Set();
            allClaims.forEach(c => users.add(c.employee_name));
            const currentSelectedUser = filterUser.value;
            filterUser.innerHTML = '<option value="">All Employees</option>';
            Array.from(users).sort().forEach(u => {
                filterUser.innerHTML += `<option value="${escapeHTML(u)}">${escapeHTML(u)}</option>`;
            });
            if(currentSelectedUser) filterUser.value = currentSelectedUser;

            applyFilters();

        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="7" class="state-cell" style="color:#dc2626;"><p>Network error while loading claims.</p></td></tr>`;
        }
    }

    function applyFilters() {
        let filtered = allClaims;

        const uVal = filterUser.value;
        const mVal = filterMonth.value;
        const yVal = filterYear.value;
        const sVal = filterStatus.value;

        if (uVal) filtered = filtered.filter(c => c.employee_name === uVal);
        if (mVal || yVal) {
            filtered = filtered.filter(c => {
                // c.date format is YYYY-MM-DD
                const parts = c.date.split('-');
                if (yVal && parts[0] !== yVal) return false;
                if (mVal && parts[1] !== mVal) return false;
                return true;
            });
        }
        
        if (sVal) {
            filtered = filtered.filter(c => {
                if (sVal === 'paid') return c.payment_status === 'paid';
                if (sVal === 'rejected') return c.manager_status === 'rejected' || c.hr_status === 'rejected';
                if (sVal === 'approved') return c.manager_status === 'approved' && c.hr_status === 'approved';
                if (sVal === 'pending') return c.manager_status === 'pending' || c.hr_status === 'pending';
                return true;
            });
        }

        renderTable(filtered);
    }

    // Render Table
    function renderTable(claims) {
        if (!claims || claims.length === 0) {
            let msg = "No claims found matching your filters.";
            tableBody.innerHTML = `<tr><td colspan="7" class="state-cell"><i data-lucide="inbox" style="width:36px;height:36px;margin-bottom:1rem;color:#cbd5e1;"></i><p>${msg}</p></td></tr>`;
            if(window.lucide) lucide.createIcons();
            return;
        }

        function getStatusBadge(status) {
            const s = (status || 'pending').toLowerCase();
            return `<span class="status-badge status-${s}">${s}</span>`;
        }

        tableBody.innerHTML = claims.map(c => {
            // Calculate severity based on punch_out time
            const hour = parseInt((c.punch_out_fmt || '00:00').split(':')[0], 10);
            let timeClass = 'late-mild';
            if (hour >= 23) timeClass = 'late-severe';
            else if (hour >= 22) timeClass = 'late-high';

            const price = parseFloat(c.price_per_meal || 100).toFixed(2);

            let actionButtons = '';
            
            // Build action buttons depending on permissions
            if (c.can_approve_manager) {
                actionButtons += `
                    <button class="btn btn-approve" title="Approve" onclick="processClaim(${c.id}, 'approve', 'manager')"><i data-lucide="check"></i></button>
                    <button class="btn btn-reject" title="Reject" onclick="processClaim(${c.id}, 'reject', 'manager')"><i data-lucide="x"></i></button>
                `;
            } else if (c.can_approve_hr) {
                actionButtons += `
                    <button class="btn btn-approve" title="Approve" onclick="processClaim(${c.id}, 'approve', 'hr')"><i data-lucide="check"></i></button>
                    <button class="btn btn-reject" title="Reject" onclick="processClaim(${c.id}, 'reject', 'hr')"><i data-lucide="x"></i></button>
                `;
            } else if (c.can_mark_paid) {
                actionButtons += `
                    <button class="btn btn-pay" title="Mark Paid" onclick="processClaim(${c.id}, 'pay', 'payment')"><i data-lucide="check-circle"></i> Pay</button>
                `;
            } else {
                 actionButtons = `
                    <button class="btn" title="Action locked until user resubmits or pending other approval" disabled style="opacity:0.6; cursor:not-allowed; background:#f8fafc; color:#64748b; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px; padding:0.35rem 0.6rem; border-radius:6px; font-size:0.8rem; font-weight:500;">
                        <i data-lucide="lock" style="width:13px;height:13px;"></i> Locked
                    </button>
                 `;
            }

            return `
            <tr>
                <td>
                    <div class="emp-cell">
                        <div class="emp-avatar" style="background:${getHSL(c.employee_name)}">${getInitials(c.employee_name)}</div>
                        <div>
                            <div class="emp-name">${escapeHTML(c.employee_name)}</div>
                            <div class="emp-email">${escapeHTML(c.employee_email)}</div>
                            ${c.resubmit_count > 0 
                                ? `<div style="font-size:0.75rem; color:var(--clr-blue); margin-top:4px; font-weight:600; display:flex; align-items:center; gap:3px;">
                                     <i data-lucide="refresh-cw" style="width:11px;height:11px;"></i>
                                     Resubmitted (${c.resubmit_count}/3)
                                   </div>` 
                                : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight:600;color:#334155;">${c.date_fmt}</div>
                    <div class="time-badge ${timeClass}" style="margin-top:4px;">
                        <i data-lucide="log-out" style="width:12px;height:12px;"></i> ${c.punch_out_fmt}
                    </div>
                </td>
                <td>
                    <div class="price-tag">₹${price}</div>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">Requested: ₹${parseFloat(c.amount || 0).toFixed(2)}</div>
                </td>
                <td>${getStatusBadge(c.manager_status)}</td>
                <td>${getStatusBadge(c.hr_status)}</td>
                <td>${getStatusBadge(c.payment_status)}</td>
                <td>
                    <div class="action-btns" style="flex-wrap: wrap;">
                        ${actionButtons}
                    </div>
                </td>
            </tr>
            `;
        }).join('');

        if (window.lucide) lucide.createIcons();
    }

    // Process Action (Approve/Reject/Pay)
    let currentActionContext = null;

    window.processClaim = (attendanceId, action, level) => {
        currentActionContext = { attendanceId, action, level };
        
        const modal = document.getElementById('actionModalBackdrop');
        const title = document.getElementById('actionModalTitle');
        const noteLabel = document.getElementById('actionNoteLabel');
        const note = document.getElementById('actionNote');
        const checkpoints = document.getElementById('approveCheckpoints');
        const btn = document.getElementById('btnConfirmAction');
        const chks = [document.getElementById('chk1'), document.getElementById('chk2'), document.getElementById('chk3')];

        // Reset fields
        note.value = '';
        document.getElementById('wordCountText').textContent = '0 words';
        chks.forEach(c => { c.checked = false; c.style.outline = 'none'; });
        note.style.borderColor = '#cbd5e1';

        if (action === 'approve') {
            title.textContent = `Approve Claim (${level.toUpperCase()})`;
            checkpoints.style.display = 'flex';
            noteLabel.innerHTML = 'Approval Note <span style="color:#94a3b8;font-weight:400;">(Optional)</span>';
            btn.textContent = 'Approve Claim';
            btn.className = 'btn-modal btn-confirm-approve';
        } else if (action === 'reject') {
            title.textContent = `Reject Claim (${level.toUpperCase()})`;
            checkpoints.style.display = 'none';
            noteLabel.innerHTML = 'Rejection Reason <span style="color:#dc2626;">* (Min 10 words)</span>';
            btn.textContent = 'Reject Claim';
            btn.className = 'btn-modal btn-confirm-reject';
        } else if (action === 'pay') {
            title.textContent = `Mark Claim as Paid`;
            checkpoints.style.display = 'none';
            noteLabel.innerHTML = 'Payment Reference / Note <span style="color:#94a3b8;font-weight:400;">(Optional)</span>';
            btn.textContent = 'Confirm Payment';
            btn.className = 'btn-modal btn-confirm-approve'; // Green button
        }

        modal.classList.add('open');
    };

    window.closeActionModal = () => {
        document.getElementById('actionModalBackdrop').classList.remove('open');
        currentActionContext = null;
    };

    // Word count checker
    const actionNote = document.getElementById('actionNote');
    actionNote.addEventListener('input', () => {
        const words = actionNote.value.trim().split(/\s+/).filter(w => w.length > 0).length;
        document.getElementById('wordCountText').textContent = `${words} word${words !== 1 ? 's' : ''}`;
    });

    window.submitClaimAction = () => {
        if (!currentActionContext) return;
        
        const { attendanceId, action, level } = currentActionContext;
        const noteVal = actionNote.value.trim();
        const words = noteVal.split(/\s+/).filter(w => w.length > 0).length;

        if (action === 'reject') {
            if (words < 10) {
                actionNote.style.borderColor = '#dc2626';
                showToast("Rejection requires at least 10 words.", "error");
                return;
            }
        }

        if (action === 'approve') {
            const chks = [document.getElementById('chk1'), document.getElementById('chk2'), document.getElementById('chk3')];
            let allChecked = true;
            chks.forEach(c => {
                if (!c.checked) {
                    c.style.outline = '2px solid #dc2626';
                    allChecked = false;
                } else {
                    c.style.outline = 'none';
                }
            });
            if (!allChecked) {
                showToast("Please verify all checkpoints.", "error");
                return;
            }
        }

        executeAction(attendanceId, action, level, noteVal);
        closeActionModal();
    };

    async function executeAction(attendanceId, action, level, noteVal) {
        try {
            const res = await fetch('api/update_claim_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attendance_id: attendanceId,
                    action: action,
                    level: level,
                    note: noteVal
                })
            });
            const data = await res.json();

            if (data.success) {
                let actionVerb = action === 'approve' ? 'approved' : (action === 'reject' ? 'rejected' : 'marked as paid');
                showToast(`Claim successfully ${actionVerb}.`);
                loadClaims(); // refresh list
            } else {
                showToast(data.error || "Failed to process claim.", 'error');
            }
        } catch (e) {
            showToast("Network error occurred.", 'error');
        }
    }

    // Initial Load
    loadClaims();
});
</script>

</body>
</html>
