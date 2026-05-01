<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Reimbursement Setup | Connect</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>window.SIDEBAR_BASE_PATH = '../../studio_users/';</script>
<script src="../../studio_users/components/sidebar-loader.js" defer></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:#f0f4f8;color:#1e293b}
.dashboard-container{display:flex;min-height:100vh}
#sidebar-mount{position:sticky;top:0;height:100vh;flex-shrink:0}
.main-content{flex:1;min-height:100vh;overflow-x:hidden;background:#f0f4f8}

/* Top Bar */
.page-topbar{display:flex;align-items:center;padding:1.25rem 2.5rem;background:#fff;border-bottom:1px solid #e8edf5;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.05)}
.topbar-icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 4px 14px rgba(249,115,22,.35);flex-shrink:0;margin-right:1rem}
.topbar-title h1{font-size:1.2rem;font-weight:700;color:#0f172a;line-height:1.2}
.topbar-title p{font-size:.8rem;color:#64748b;margin-top:2px}

/* Page */
.page-body{padding:2rem 2.5rem;animation:fadeUp .4s ease-out}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* Tabs */
.tabs-nav{display:flex;gap:.5rem;margin-bottom:1.75rem;background:#fff;border-radius:14px;padding:.5rem;box-shadow:0 2px 12px rgba(0,0,0,.05);width:fit-content}
.tab-btn{padding:.65rem 1.5rem;border:none;background:transparent;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:600;color:#64748b;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.5rem}
.tab-btn.active{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;box-shadow:0 4px 12px rgba(249,115,22,.3)}
.tab-pane{display:none}
.tab-pane.active{display:block}

/* Card */
.card{background:#fff;border-radius:20px;border:1px solid #e2e8f0;box-shadow:0 4px 24px -4px rgba(0,0,0,.06);overflow:hidden}
.card-header{padding:1.25rem 1.75rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.search-box{position:relative;flex:1;min-width:200px;max-width:380px}
.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:#94a3b8}
.search-box input{width:100%;padding:.6rem 1rem .6rem 36px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;font-family:inherit;font-size:.88rem;outline:none;transition:all .2s}
.search-box input:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.card-info{font-size:.78rem;color:#64748b;margin-left:auto;display:flex;align-items:center;gap:.4rem}

/* Table */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead tr{background:#f8fafc;border-bottom:2px solid #e2e8f0}
th{padding:.9rem 1.5rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;white-space:nowrap;text-align:left}
td{padding:1rem 1.5rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;text-align:left}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#fafbff}

/* Employee cell */
.emp-cell{display:flex;align-items:center;gap:.75rem}
.emp-av{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;color:#fff;flex-shrink:0}
.emp-name{font-weight:600;color:#1e293b;font-size:.9rem}
.emp-sub{font-size:.73rem;color:#94a3b8;margin-top:1px}

/* Price input */
.price-wrap{display:flex;align-items:center;gap:.5rem}
.currency-sym{font-weight:700;color:#475569;font-size:1rem}
.price-input{width:110px;padding:.55rem .75rem;border-radius:9px;border:1px solid #e2e8f0;background:#f8fafc;font-family:inherit;font-size:.92rem;font-weight:600;color:#1e293b;outline:none;transition:all .2s;text-align:right}
.price-input:focus{border-color:#f97316;background:#fff;box-shadow:0 0 0 3px rgba(249,115,22,.1)}

/* Toggle switch */
.switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:26px;transition:.3s}
.slider:before{position:absolute;content:'';height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.15)}
input:checked+.slider{background:#f97316}
input:checked+.slider:before{transform:translateX(20px)}
.perm-label{font-size:.85rem;font-weight:600;color:#475569}
.perm-row{display:flex;align-items:center;gap:.75rem}
.perm-status{font-size:.75rem;font-weight:700;padding:3px 9px;border-radius:6px}
.perm-on{background:#dcfce7;color:#166534}
.perm-off{background:#f1f5f9;color:#64748b}

/* Save btn */
.save-btn{background:#1e293b;color:#fff;border:none;padding:.55rem 1.1rem;border-radius:9px;font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.4rem;white-space:nowrap}
.save-btn:hover{background:#334155;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.12)}
.save-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* Bulk save footer */
.bulk-footer{padding:1.25rem 1.75rem;border-top:1px solid #f0f2f5;display:flex;justify-content:flex-end;background:#fafbfc}
.bulk-btn{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;padding:.7rem 1.75rem;border-radius:11px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.6rem;box-shadow:0 4px 14px rgba(249,115,22,.3)}
.bulk-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(249,115,22,.4)}
.bulk-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* State */
.state-cell{text-align:center;padding:5rem 2rem;color:#94a3b8}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.spin-anim{animation:spin 1.2s linear infinite;display:inline-block}

/* Toast */
#setup-toast{position:fixed;bottom:2rem;right:2rem;padding:.9rem 1.6rem;border-radius:12px;background:#1e293b;color:#fff;font-weight:600;font-size:.88rem;box-shadow:0 8px 32px rgba(0,0,0,.18);transform:translateY(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;display:flex;align-items:center;gap:.6rem}
#setup-toast.show{transform:translateY(0)}
#setup-toast.ok{background:#059669}
#setup-toast.err{background:#dc2626}

@media(max-width:768px){.page-body{padding:1.25rem 1rem}.page-topbar{padding:1rem 1.25rem}.tabs-nav{width:100%}.tab-btn{flex:1;justify-content:center}}
</style>
</head>
<body>
<div class="dashboard-container">
    <div id="sidebar-mount"></div>
    <main class="main-content">

        <div class="page-topbar">
            <div class="topbar-icon"><i data-lucide="settings-2" style="width:20px;height:20px;"></i></div>
            <div class="topbar-title">
                <h1>Food Reimbursement Setup</h1>
                <p>Configure meal prices and payment permissions</p>
            </div>
        </div>

        <div class="page-body">

            <!-- Tab Navigation -->
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="prices" onclick="switchTab('prices')">
                    <i data-lucide="indian-rupee" style="width:16px;height:16px;"></i>
                    Price Setup
                </button>
                <button class="tab-btn" data-tab="permissions" onclick="switchTab('permissions')">
                    <i data-lucide="shield-check" style="width:16px;height:16px;"></i>
                    Payment Permissions
                </button>
            </div>

            <!-- ── TAB 1: Price Setup ── -->
            <div class="tab-pane active" id="tab-prices">
                <div class="card">
                    <div class="card-header">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" id="priceSearch" placeholder="Search employee…">
                        </div>
                        <span class="card-info">
                            <i data-lucide="info" style="width:13px;height:13px;"></i>
                            Set per-meal reimbursement amount per employee, then Save All
                        </span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <colgroup>
                                <col style="width:35%">
                                <col style="width:25%">
                                <col style="width:22%">
                                <col style="width:18%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Role / Position</th>
                                    <th>Amount Per Meal (₹)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="priceTableBody">
                                <tr><td colspan="4" class="state-cell">
                                    <div class="spin-anim"><i data-lucide="loader-2" style="width:38px;height:38px;"></i></div>
                                    <p style="margin-top:.75rem">Loading…</p>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="bulk-footer">
                        <button class="bulk-btn" id="saveAllPricesBtn">
                            <i data-lucide="save" style="width:17px;height:17px;"></i>
                            Save All Prices
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── TAB 2: Payment Permissions ── -->
            <div class="tab-pane" id="tab-permissions">
                <div class="card">
                    <div class="card-header">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" id="permSearch" placeholder="Search employee…">
                        </div>
                        <span class="card-info">
                            <i data-lucide="info" style="width:13px;height:13px;"></i>
                            Toggle to allow users to mark food reimbursements as Paid
                        </span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <colgroup>
                                <col style="width:32%">
                                <col style="width:22%">
                                <col style="width:28%">
                                <col style="width:18%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Role / Position</th>
                                    <th>Can Mark as Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="permTableBody">
                                <tr><td colspan="4" class="state-cell">
                                    <div class="spin-anim"><i data-lucide="loader-2" style="width:38px;height:38px;"></i></div>
                                    <p style="margin-top:.75rem">Loading…</p>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<div id="setup-toast">
    <i data-lucide="check-circle" style="width:16px;height:16px;" id="toast-icon"></i>
    <span id="toast-msg">Done!</span>
</div>

<script>
/* ─── Tab Switch ─────────────────────────── */
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
    if (window.lucide) lucide.createIcons();
}

/* ─── Toast ──────────────────────────────── */
const toast = document.getElementById('setup-toast');
const toastMsg = document.getElementById('toast-msg');
const toastIcon = document.getElementById('toast-icon');
function showToast(msg, type = 'ok') {
    toastMsg.textContent = msg;
    toastIcon.setAttribute('data-lucide', type === 'ok' ? 'check-circle' : 'alert-circle');
    toast.className = `show ${type}`;
    if (window.lucide) lucide.createIcons();
    setTimeout(() => toast.className = '', 3000);
}

/* ─── Helpers ────────────────────────────── */
function initials(name) { return (name||'').split(' ').map(n=>n[0]).join('').toUpperCase().substring(0,2); }
function hsl(str) {
    let h=0; for(let i=0;i<str.length;i++) h=str.charCodeAt(i)+((h<<5)-h);
    return `hsl(${Math.abs(h%360)},58%,44%)`;
}

/* ══════════════════════════════════════════
   TAB 1 — PRICE SETUP
══════════════════════════════════════════ */
let priceUsers = [];

async function loadPrices() {
    try {
        const r = await fetch('api/get_food_prices.php');
        const d = await r.json();
        if (d.success) { priceUsers = d.users; renderPrices(); }
        else showError('priceTableBody', 4, d.error);
    } catch(e) { showError('priceTableBody', 4, 'Network error'); }
}

function renderPrices(filter = '') {
    const term = filter.toLowerCase();
    const list = priceUsers.filter(u =>
        u.name.toLowerCase().includes(term) ||
        (u.role||'').toLowerCase().includes(term) ||
        (u.position||'').toLowerCase().includes(term)
    );
    const tb = document.getElementById('priceTableBody');
    if (!list.length) { tb.innerHTML = `<tr><td colspan="4" class="state-cell"><i data-lucide="search-x" style="width:32px;height:32px;margin-bottom:.5rem;"></i><p>No results.</p></td></tr>`; if(window.lucide)lucide.createIcons(); return; }

    tb.innerHTML = list.map(u => `
    <tr data-uid="${u.id}">
        <td><div class="emp-cell">
            <div class="emp-av" style="background:${hsl(u.name)}">${initials(u.name)}</div>
            <div><div class="emp-name">${u.name}</div><div class="emp-sub">${u.email||''}</div></div>
        </div></td>
        <td><span class="emp-sub">${u.position||u.role||'—'}</span></td>
        <td><div class="price-wrap">
            <span class="currency-sym">₹</span>
            <input type="number" class="price-input" min="0" step="0.01" value="${parseFloat(u.price_per_meal).toFixed(2)}" data-price>
        </div></td>
        <td><button class="save-btn" onclick="saveSinglePrice(${u.id},this)">
            <i data-lucide="save" style="width:14px;height:14px;"></i>Save
        </button></td>
    </tr>`).join('');
    if(window.lucide) lucide.createIcons();
}

async function saveSinglePrice(userId, btn) {
    const row = btn.closest('tr');
    const price = parseFloat(row.querySelector('[data-price]').value);
    if (isNaN(price) || price < 0) { showToast('Enter a valid amount', 'err'); return; }
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    try {
        const r = await fetch('api/save_food_price.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({user_id:userId,price_per_meal:price}) });
        const d = await r.json();
        if (d.success) {
            btn.style.background='#059669'; btn.innerHTML='<i data-lucide="check" style="width:14px;height:14px;"></i>Saved!';
            if(window.lucide)lucide.createIcons();
            // Update local state
            const u = priceUsers.find(u=>String(u.id)===String(userId));
            if(u) u.price_per_meal = price;
            showToast('Price saved!');
            setTimeout(()=>{ btn.style.background=''; btn.innerHTML=orig; btn.disabled=false; if(window.lucide)lucide.createIcons(); }, 2000);
        } else { showToast(d.error||'Save failed','err'); btn.disabled=false; btn.innerHTML=orig; }
    } catch(e) { showToast('Network error','err'); btn.disabled=false; btn.innerHTML=orig; }
}

document.getElementById('saveAllPricesBtn').addEventListener('click', async function() {
    const rows = document.querySelectorAll('#priceTableBody tr[data-uid]');
    if(!rows.length) return;
    this.disabled = true;
    const orig = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving All…';
    let ok = 0, fail = 0;
    for (const row of rows) {
        const uid = row.dataset.uid;
        const price = parseFloat(row.querySelector('[data-price]').value);
        if (isNaN(price) || price < 0) { fail++; continue; }
        try {
            const r = await fetch('api/save_food_price.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({user_id:uid,price_per_meal:price}) });
            const d = await r.json();
            d.success ? ok++ : fail++;
        } catch(e) { fail++; }
    }
    showToast(fail ? `${ok} saved, ${fail} failed` : `All ${ok} prices saved!`, fail ? 'err' : 'ok');
    this.innerHTML = '<i data-lucide="check" style="width:17px;height:17px;"></i> Saved!';
    this.style.background = '#059669';
    if(window.lucide)lucide.createIcons();
    setTimeout(()=>{ this.innerHTML=orig; this.style.background=''; this.disabled=false; if(window.lucide)lucide.createIcons(); }, 2500);
});

document.getElementById('priceSearch').addEventListener('input', e => renderPrices(e.target.value));

/* ══════════════════════════════════════════
   TAB 2 — PAYMENT PERMISSIONS
══════════════════════════════════════════ */
let permUsers = [];

async function loadPerms() {
    try {
        const r = await fetch('api/get_payment_permissions.php');
        const d = await r.json();
        if (d.success) { permUsers = d.users; renderPerms(); }
        else showError('permTableBody', 4, d.error);
    } catch(e) { showError('permTableBody', 4, 'Network error'); }
}

function renderPerms(filter = '') {
    const term = filter.toLowerCase();
    const list = permUsers.filter(u =>
        u.name.toLowerCase().includes(term) ||
        (u.role||'').toLowerCase().includes(term) ||
        (u.position||'').toLowerCase().includes(term)
    );
    const tb = document.getElementById('permTableBody');
    if (!list.length) { tb.innerHTML = `<tr><td colspan="4" class="state-cell"><i data-lucide="search-x" style="width:32px;height:32px;margin-bottom:.5rem;"></i><p>No results.</p></td></tr>`; if(window.lucide)lucide.createIcons(); return; }

    tb.innerHTML = list.map(u => `
    <tr data-uid="${u.id}">
        <td><div class="emp-cell">
            <div class="emp-av" style="background:${hsl(u.name)}">${initials(u.name)}</div>
            <div><div class="emp-name">${u.name}</div><div class="emp-sub">${u.email||''}</div></div>
        </div></td>
        <td><span class="emp-sub">${u.position||u.role||'—'}</span></td>
        <td>
            <div class="perm-row">
                <label class="switch">
                    <input type="checkbox" ${parseInt(u.can_mark_paid)?'checked':''} onchange="togglePerm(${u.id},this)">
                    <span class="slider"></span>
                </label>
                <span class="perm-label">${parseInt(u.can_mark_paid)?'Allowed':'Restricted'}</span>
            </div>
        </td>
        <td><span class="perm-status ${parseInt(u.can_mark_paid)?'perm-on':'perm-off'}" id="perm-status-${u.id}">
            ${parseInt(u.can_mark_paid)?'✓ Can Mark Paid':'✗ No Access'}
        </span></td>
    </tr>`).join('');
    if(window.lucide) lucide.createIcons();
}

async function togglePerm(userId, checkbox) {
    const val = checkbox.checked ? 1 : 0;
    const row = checkbox.closest('tr');
    const label = row.querySelector('.perm-label');
    const statusEl = document.getElementById('perm-status-'+userId);
    checkbox.disabled = true;
    try {
        const r = await fetch('api/save_payment_permission.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({user_id:userId,can_mark_paid:val}) });
        const d = await r.json();
        if (d.success) {
            // Update local + UI
            const u = permUsers.find(u=>String(u.id)===String(userId));
            if(u) u.can_mark_paid = val;
            label.textContent = val ? 'Allowed' : 'Restricted';
            if(statusEl){ statusEl.className='perm-status '+(val?'perm-on':'perm-off'); statusEl.textContent=val?'✓ Can Mark Paid':'✗ No Access'; }
            showToast(val ? 'Permission granted!' : 'Permission revoked!');
        } else {
            checkbox.checked = !checkbox.checked; // revert
            showToast(d.error||'Failed to update','err');
        }
    } catch(e) {
        checkbox.checked = !checkbox.checked;
        showToast('Network error','err');
    }
    checkbox.disabled = false;
}

document.getElementById('permSearch').addEventListener('input', e => renderPerms(e.target.value));

/* ─── Error helper ───────────────────────── */
function showError(tbId, cols, msg) {
    document.getElementById(tbId).innerHTML = `<tr><td colspan="${cols}" class="state-cell" style="color:#dc2626;"><i data-lucide="alert-circle" style="width:32px;height:32px;margin-bottom:.5rem;"></i><p>${msg}</p></td></tr>`;
    if(window.lucide) lucide.createIcons();
}

/* ─── Init ───────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    loadPrices();
    loadPerms();
    if(window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
