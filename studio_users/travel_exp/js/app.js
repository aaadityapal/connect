document.addEventListener("DOMContentLoaded", () => {
    const loadSection = async (containerId, filePath) => {
        try {
            const response = await fetch(filePath);
            if (response.ok) {
                const htmlCode = await response.text();
                document.getElementById(containerId).innerHTML = htmlCode;
            } else {
                console.error(`Error loading ${filePath}: ${response.statusText}`);
            }
        } catch (error) {
            console.error(`Fetch error for ${filePath}:`, error);
        }
    };

    Promise.all([
        loadSection("header-container",  "sections/header.html"),
        loadSection("summary-container", "sections/summary.html"),
        loadSection("filter-container",  "sections/filter.html"),
        loadSection("table-container",   "sections/table.html"),
        loadSection("modals-container",  "sections/modals.html")
    ]).then(() => {
        // Immediately update badge text to current month/year
        const badgeText = document.getElementById("badge-text");
        if (badgeText) badgeText.textContent = `Showing ${pickerMonth} ${pickerYear}`;
        const yearLabel = document.getElementById("picker-year-label");
        if (yearLabel) yearLabel.textContent = pickerYear;

        initializeInteractions();
        initMonthPicker();
        initModals();
        fetchExpenses(); 
    });
});

async function fetchExpenses() {
    try {
        // 1. Fetch role config — now returns meter_modes from travel_meter_photo_perms
        const roleResp = await fetch('../api/fetch_travel_role_config.php');
        const roleData = await roleResp.json();
        if (roleData.success) {
            // NEW: array of transport modes that require meter photos for this user
            userMeterModes = Array.isArray(roleData.meter_modes) ? roleData.meter_modes : [];
        }

        // 2. Fetch transport rates
        const rateResp = await fetch('../api/fetch_travel_transport_rates.php');
        const rateData = await rateResp.json();
        if (rateData.success) {
            rateData.rates.forEach(r => {
                transportRates[r.transport_mode] = parseFloat(r.rate_per_km);
            });
        }

        const response = await fetch('../api/fetch_travel_expenses.php');
        const json = await response.json();
        if (json.success) {
            tableData = json.data;
            applyFilters();
        } else {
            console.error("Error from API:", json.message);
        }
    } catch (error) {
        console.error("Fetch error:", error);
    }
}

/* ═══════════════════════════════════════════════
   DATA STORE
═══════════════════════════════════════════════ */
let tableData = [];

// Tracks which row is being edited or deleted
let activeEditId   = null;
let activeDeleteId = null;

// ── Status Alert Modal (Higher Z-index than main modals)
function showStatusAlert(msg, title = "Attention") {
    let alertModal = document.getElementById("status-alert-modal");
    if (!alertModal) {
        alertModal = document.createElement("div");
        alertModal.id = "status-alert-modal";
        alertModal.className = "modal-overlay"; 
        alertModal.style.cssText = "position:fixed; inset:0; z-index:40000 !important; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); display:none; align-items:center; justify-content:center; padding: 20px;";
        
        alertModal.innerHTML = `
            <div class="modal-content" style="max-width: 420px; width: 100%; border-radius: 24px; padding: 0 !important; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); animation: statusBounce 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); background: #ffffff;">
                <style>
                    @keyframes statusBounce { from { opacity:0; transform:scale(0.9) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
                    #status-alert-modal.open { display: flex !important; }
                    .alert-btn:hover { background: #1d4ed8 !important; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
                </style>
                <div style="padding: 32px 32px 24px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 20px;">
                    <div style="width: 56px; height: 56px; background: #fee2e2; color: #ef4444; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.1);">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 id="status-alert-title" style="margin: 0 0 10px; font-size: 20px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">${title}</h3>
                        <p id="status-alert-msg" style="margin: 0; color: #475569; font-size: 15px; line-height: 1.6; font-weight: 500;">${msg}</p>
                    </div>
                </div>
                <div style="padding: 0 32px 32px; display: flex; justify-content: stretch;">
                    <button class="alert-btn" onclick="closeModal('status-alert-modal')" style="flex:1; padding: 14px; font-size: 15px; border-radius: 14px; font-weight: 700; background: #2563eb; color: #fff; border: none; cursor: pointer; transition: 0.2s; outline: none;">Got it</button>
                </div>
            </div>
        `;
        document.body.appendChild(alertModal);
    } else {
        document.getElementById("status-alert-title").textContent = title;
        document.getElementById("status-alert-msg").textContent = msg;
    }
    openModal("status-alert-modal");
}

// Leaflet & Role specific
let routingControl = null;
let leafletMap = null;
// NEW: Array of transport modes that require meter photos for this user
// e.g. ['Bike', 'Car'] — sourced from travel_meter_photo_perms table
let userMeterModes = [];
let transportRates = {}; // { 'Car': 10, 'Bike': 5, ... }

// Modes that NEVER require any photo upload (neither meter nor bill)
const PHOTO_EXEMPT_MODES = ['E-Rickshaw', 'Metro'];

/* ═══════════════════════════════════════════════
   MONTH PICKER (Header badge)
═══════════════════════════════════════════════ */
const MONTHS = ["January","February","March","April","May","June","July","August","September","October","November","December"];
const _now = new Date();
let pickerYear  = _now.getFullYear();
let pickerMonth = MONTHS[_now.getMonth()]; // default to current month

function initMonthPicker() {
    const badge = document.getElementById("month-badge");
    const dropdown = document.getElementById("month-dropdown");
    if (!badge || !dropdown) return;

    // Toggle open/close on badge click
    badge.addEventListener("click", (e) => {
        e.stopPropagation();
        dropdown.classList.toggle("open");
        if (dropdown.classList.contains("open")) buildPickerUI();
    });

    // Close on outside click
    document.addEventListener("click", (e) => {
        if (!dropdown.contains(e.target) && e.target !== badge) {
            dropdown.classList.remove("open");
        }
    });

    // Year nav
    document.getElementById("picker-year-prev").addEventListener("click", () => { pickerYear--; buildPickerUI(); });
    document.getElementById("picker-year-next").addEventListener("click", () => { pickerYear++; buildPickerUI(); });
}

function buildPickerUI() {
    const yearLabel = document.getElementById("picker-year-label");
    const grid      = document.getElementById("picker-months-grid");
    if (!yearLabel || !grid) return;

    yearLabel.textContent = pickerYear;
    grid.innerHTML = MONTHS.map(m => `
        <button class="month-item ${m === pickerMonth && pickerYear === _now.getFullYear() ? 'active' : ''}" data-month="${m}">
            ${m.slice(0, 3)}
        </button>
    `).join("");

    // Month select
    grid.querySelectorAll(".month-item").forEach(btn => {
        btn.addEventListener("click", () => {
            pickerMonth = btn.dataset.month;
            // Update the badge label
            const badgeText = document.getElementById("badge-text");
            if (badgeText) badgeText.textContent = `Showing ${pickerMonth} ${pickerYear}`;
            
            document.getElementById("month-dropdown").classList.remove("open");
            applyFilters();
        });
    });
}

/* ═══════════════════════════════════════════════
   FILTER LOGIC
═══════════════════════════════════════════════ */
function initializeInteractions() {
    initCustomSelects(); // Initialize custom UI dropdowns
    applyFilters(); // Apply initial filter and KPIs
    const applyBtn = document.getElementById("btn-apply-filter");
    const resetBtn = document.getElementById("btn-reset-filter");
    if (applyBtn) applyBtn.addEventListener("click", applyFilters);
    if (resetBtn) resetBtn.addEventListener("click", resetFilters);
}

function initCustomSelects() {
    if (window.customSelectsInitialized) return;
    window.customSelectsInitialized = true;

    document.addEventListener("click", (e) => {
        const trigger = e.target.closest(".select-trigger");
        if (trigger) {
            const select = trigger.closest(".custom-select");
            if (select.classList.contains("disabled")) return;
            document.querySelectorAll(".custom-select.open").forEach(other => {
                if (other !== select) other.classList.remove("open");
            });
            select.classList.toggle("open");
            return;
        }

        const item = e.target.closest(".select-item");
        if (item) {
            const select = item.closest(".custom-select");
            if (select && !select.classList.contains("disabled")) {
                let hiddenInput = null;
                if (select.dataset.target) {
                    hiddenInput = document.getElementById(select.dataset.target);
                } else {
                    hiddenInput = select.querySelector("input[type='hidden']");
                }
                const valueSpan = select.querySelector(".select-value");
                
                select.querySelectorAll(".select-item").forEach(i => i.classList.remove("active"));
                item.classList.add("active");
                
                if (hiddenInput) {
                    hiddenInput.value = item.dataset.value;
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (valueSpan) {
                    valueSpan.textContent = item.textContent;
                    valueSpan.style.color = item.dataset.value === "" ? "#94a3b8" : "inherit";
                }
                select.classList.remove("open");
            }
            return;
        }

        document.querySelectorAll(".custom-select.open").forEach(select => {
            select.classList.remove("open");
        });
    });
}

function updateSummaryStats(filteredData) {
    // If filteredData is provided, use it. Otherwise use the default tableData for the selected month/year.
    const internalData = filteredData || tableData.filter(item => {
        const monthsMap = {
            "January":"01","February":"02","March":"03","April":"04",
            "May":"05","June":"06","July":"07","August":"08",
            "September":"09","October":"10","November":"11","December":"12"
        };
        const monthNum = monthsMap[pickerMonth];
        return (item.date.substring(0, 4) === pickerYear.toString() && item.date.substring(5, 7) === monthNum);
    });

    let stats = { count: 0, total: 0, approved: 0, paid: 0, pending: 0, rejected: 0 };
    
    internalData.forEach(item => {
        stats.count++;
        const amt = parseFloat(item.amount);
        stats.total += amt;
        
        let isPaid = (item.payment_status && item.payment_status.toLowerCase() === 'paid');
        
        if (isPaid) stats.paid += amt;
        else if (item.status === 'approved') stats.approved += amt;
        else if (item.status === 'pending') stats.pending += amt;
        else if (item.status === 'rejected') stats.rejected += amt;
    });

    // Update DOM
    const safeFmt = (val) => "₹" + val.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    const el = (id) => document.getElementById(id);
    
    if(el('summary-month-text')) {
        const dateFrom = document.getElementById("filter-date-from")?.value;
        const dateTo   = document.getElementById("filter-date-to")?.value;
        if (dateFrom || dateTo) {
            el('summary-month-text').textContent = "Filtered Period";
        } else {
            el('summary-month-text').textContent = `${pickerMonth} ${pickerYear}`;
        }
    }

    if(el('stat-count'))    el('stat-count').textContent    = stats.count;
    if(el('stat-total'))    el('stat-total').textContent    = safeFmt(stats.total);
    if(el('stat-approved')) el('stat-approved').textContent = safeFmt(stats.approved);
    if(el('stat-paid'))     el('stat-paid').textContent     = safeFmt(stats.paid);
    if(el('stat-pending'))  el('stat-pending').textContent  = safeFmt(stats.pending);
    if(el('stat-rejected')) el('stat-rejected').textContent = safeFmt(stats.rejected);
}

function applyFilters() {
    const statusVal = (document.getElementById("filter-status")?.value || "").toLowerCase();
    const dateFrom  = document.getElementById("filter-date-from")?.value || "";
    const dateTo    = document.getElementById("filter-date-to")?.value || "";

    const monthsMap = {
        "January":"01","February":"02","March":"03","April":"04",
        "May":"05","June":"06","July":"07","August":"08",
        "September":"09","October":"10","November":"11","December":"12"
    };

    const filtered = tableData.filter(item => {
        // 1. Status Filter
        if (statusVal && statusVal !== "") {
            let itemFinalStatus = item.status;
            if (item.payment_status && item.payment_status.toLowerCase() === 'paid') {
                itemFinalStatus = 'paid';
            }
            if (itemFinalStatus !== statusVal) return false;
        }
        
        // 2. Date Filter (If dates provided, ignore the header month badge)
        if (dateFrom || dateTo) {
            if (dateFrom && new Date(item.date) < new Date(dateFrom)) return false;
            if (dateTo   && new Date(item.date) > new Date(dateTo))   return false;
        } else {
            // Default: Filter by the current pickerYear and pickerMonth from header
            const monthNum = monthsMap[pickerMonth];
            if (pickerYear && item.date.substring(0, 4) !== pickerYear.toString()) return false;
            if (monthNum && item.date.substring(5, 7) !== monthNum) return false;
        }
        return true;
    });

    renderTable(filtered);
    updateSummaryStats(filtered); // Pass filtered data to summary
}

function resetFilters() {
    const s = document.getElementById("filter-status");
    const f = document.getElementById("filter-date-from");
    const t = document.getElementById("filter-date-to");
    
    if (s) {
        s.value = "";
        const cSelect = document.querySelector('.custom-select[data-target="filter-status"]');
        if (cSelect) {
            cSelect.querySelector('.select-value').textContent = "All Statuses";
            cSelect.querySelectorAll('.select-item').forEach(i => i.classList.remove("active"));
            const first = cSelect.querySelector('.select-item[data-value=""]');
            if (first) first.classList.add("active");
        }
    }
    
    if (f) f.value = "";
    if (t) t.value = "";
    
    applyFilters();
}

function fmt(amount) {
    return "₹" + parseFloat(amount).toLocaleString("en-IN", { minimumFractionDigits: 2 });
}

function trunc(str, len = 25) {
    return (str && str.length > len) ? str.substring(0, len) + "....." : str;
}

/* ═══════════════════════════════════════════════
   TABLE RENDERER
═══════════════════════════════════════════════ */

function renderTable(dataArray) {
    const tbody = document.getElementById("expense-table-body");
    if (!tbody) return;

    if (dataArray.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="11" style="padding:0;">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-car-side"></i></div>
                    <div class="empty-state-title">No travel expenses found</div>
                    <div class="empty-state-text">No records match your selected filters.</div>
                </div>
            </td></tr>`;
        return;
    }

    tbody.innerHTML = dataArray.map(item => {
        let finalStatus = (item.status || 'pending').toLowerCase();
        let displayStatus = finalStatus.charAt(0).toUpperCase() + finalStatus.slice(1);
        
        if (finalStatus === 'pending') {
            const m = (item.manager_status || 'pending').toLowerCase();
            const h = (item.hr_status || 'pending').toLowerCase();
            const a = (item.accountant_status || 'pending').toLowerCase();
            if (m === 'approved' || h === 'approved' || a === 'approved') {
                finalStatus = 'partially';
                displayStatus = 'Partially Approved';
            }
        }

        // Resubmit overrides
        if (finalStatus === 'resubmit') {
            displayStatus = 'Resubmitted';
        }

        if (item.payment_status && item.payment_status.toLowerCase() === 'paid') {
            finalStatus = 'paid';
            displayStatus = 'Paid';
        }

        const badgeClass = `badge badge-${finalStatus}`;
        const statusText = displayStatus;
        const isLocked = item.manager_status === 'approved' || 
                         item.accountant_status === 'approved' || 
                         item.hr_status === 'approved' || 
                         item.status === 'approved';

        let rowBgColor = '';
        if (finalStatus === 'rejected') rowBgColor = '#fef2f2';         // light red
        else if (finalStatus === 'approved') rowBgColor = '#f0fdf4';    // light green
        else if (finalStatus === 'partially') rowBgColor = '#faf5ff';   // light purple
        else if (finalStatus === 'pending') rowBgColor = '#fefce8';     // light yellow
        else if (finalStatus === 'resubmit') rowBgColor = '#f0f9ff';    // powder blue
        else if (finalStatus === 'paid') rowBgColor = '#f0fdfa';        // light teal

        return `
            <tr style="background-color: ${rowBgColor};">
                <td><strong>${item.id}</strong></td>
                <td>${item.date}</td>
                <td>${item.purpose}</td>
                <td title="${item.from}">${trunc(item.from)}</td>
                <td title="${item.to}">${trunc(item.to)}</td>
                <td>${item.mode}</td>
                <td>${item.distance}</td>
                <td><strong>${fmt(item.amount)}</strong></td>
                <td><span class="${badgeClass}">${statusText}</span></td>
                <td>
                    <span class="badge ${item.payment_status && item.payment_status.toLowerCase() === 'paid' ? 'badge-approved' : 'badge-pending'}" style="${item.payment_status && item.payment_status.toLowerCase() === 'paid' ? 'background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;' : 'background:#fffbe6; color:#f59e0b; border:1px solid #ffe58f;'}">
                        ${item.payment_status || 'Unpaid'}
                    </span>
                </td>
                <td>
                    <div class="actions-cell">
                        <button class="action-btn-custom view" title="View Details" data-id="${item.id}"><i class="fa-solid fa-eye"></i></button>
                        ${!isLocked && finalStatus !== 'rejected' ? `
                            <button class="action-btn-custom edit" title="Edit" data-id="${item.id}"><i class="fa-solid fa-pen" style="color:#ff8b45;"></i></button>
                            <button class="action-btn-custom del"  title="Delete" data-id="${item.id}"><i class="fa-solid fa-trash-can" style="color:#aba2c5;"></i></button>
                        ` : (finalStatus === 'rejected' ? `
                            <button class="action-btn-custom resubmit" title="Resubmit" data-id="${item.id}"><i class="fa-solid fa-rotate-right" style="color:#3b82f6;"></i></button>
                        ` : `
                            <button class="action-btn-custom" title="Locked" style="opacity:0.4; cursor:not-allowed;" disabled><i class="fa-solid fa-lock"></i></button>
                        `)}
                    </div>
                </td>
            </tr>`;
    }).join("");

    // Attach row-level action events
    tbody.querySelectorAll(".action-btn-custom.view").forEach(b => b.addEventListener("click", () => openViewModal(b.dataset.id)));
    tbody.querySelectorAll(".action-btn-custom.edit").forEach(b => b.addEventListener("click", () => openEditModal(b.dataset.id, false)));
    tbody.querySelectorAll(".action-btn-custom.resubmit").forEach(b => b.addEventListener("click", () => openEditModal(b.dataset.id, true)));
    tbody.querySelectorAll(".action-btn-custom.del") .forEach(b => b.addEventListener("click", () => openDeleteModal(b.dataset.id)));
}

/* ═══════════════════════════════════════════════
   MODALS
═══════════════════════════════════════════════ */
// Temp list of expenses staged in the add modal
let stagedExpenses = [];

function initModals() {
    // ── View modal close
    ["view-modal-close", "view-modal-close-btn"].forEach(id => {
        document.getElementById(id)?.addEventListener("click", () => closeModal("view-modal"));
    });

    // ── Edit modal close / save
    ["edit-modal-close", "edit-modal-cancel"].forEach(id => {
        document.getElementById(id)?.addEventListener("click", () => closeModal("edit-modal"));
    });
    document.getElementById("edit-modal-save")?.addEventListener("click", saveEdit);

    // ── Delete modal close / confirm
    ["delete-modal-close", "delete-modal-cancel"].forEach(id => {
        document.getElementById(id)?.addEventListener("click", () => closeModal("delete-modal"));
    });
    document.getElementById("delete-modal-confirm")?.addEventListener("click", confirmDelete);

    // ── Add Expense modal
    const addBtn = document.getElementById("btn-add-expense");
    if (addBtn) addBtn.addEventListener("click", () => {
        openAddExpenseModal();
        initLeafletMap();
    });

    ["add-modal-close", "add-modal-close-btn"].forEach(id => {
        document.getElementById(id)?.addEventListener("click", () => closeModal("add-expense-modal"));
    });

    document.getElementById("btn-add-to-list")?.addEventListener("click", stageExpense);
    document.getElementById("btn-save-all-expenses")?.addEventListener("click", saveAllExpenses);

    // -- Return Trip Confirmation
    document.getElementById("return-trip-yes")?.addEventListener("click", () => handleReturnTripChoice(true));
    document.getElementById("return-trip-no")?.addEventListener("click", () => handleReturnTripChoice(false));

    // File pickers — show file name + green state (Event Delegation)
    const formsContainer = document.getElementById("expense-forms-container");
    if (formsContainer) {
        formsContainer.addEventListener("change", e => {
            if (e.target.classList.contains("e-mode")) {
                const form = e.target.closest(".expense-entry-form");
                const mode = e.target.value;

                // Exempt modes (E-Rickshaw, Metro) — show bill zone but mark as optional
                const isExempt   = PHOTO_EXEMPT_MODES.includes(mode);
                // Meter-required modes — from permission table
                const showMeters = !isExempt && userMeterModes.includes(mode);
                // Bill zone: visible for non-meter modes, except for Bike/Car which never have bills
                const isPersonalVehicle = ['Bike', 'Car'].includes(mode);
                const showBill   = !showMeters && !isPersonalVehicle;

                form.querySelectorAll(".meter-photo-field").forEach(el => el.style.display = showMeters ? "flex" : "none");
                form.querySelectorAll(".bill-photo-field").forEach(el  => el.style.display = showBill  ? "flex" : "none");

                // Asterisk: hide for exempt modes (optional upload), show for all others
                form.querySelectorAll(".bill-photo-field label .req, .meter-photo-field label .req").forEach(req => {
                    req.style.display = isExempt ? "none" : "inline";
                });

                // Update amount based on new mode rate
                updateAmountBasedOnDistance(form);
                updateModalSummary();
            }
            if (e.target.classList.contains("e-meter-start-input")) {
                const name = e.target.files[0]?.name || "Choose file…";
                const form = e.target.closest(".expense-entry-form");
                form.querySelector(".e-meter-start-name").textContent = name;
                form.querySelector(".e-meter-start-lbl").classList.toggle("has-file", !!e.target.files[0]);
            }
            if (e.target.classList.contains("e-meter-end-input")) {
                const name = e.target.files[0]?.name || "Choose file…";
                const form = e.target.closest(".expense-entry-form");
                form.querySelector(".e-meter-end-name").textContent = name;
                form.querySelector(".e-meter-end-lbl").classList.toggle("has-file", !!e.target.files[0]);
            }
            if (e.target.classList.contains("e-bill-input")) {
                const name = e.target.files[0]?.name || "Choose file…";
                const form = e.target.closest(".expense-entry-form");
                form.querySelector(".e-bill-name").textContent = name;
                form.querySelector(".e-bill-lbl").classList.toggle("has-file", !!e.target.files[0]);
            }
        });

        // Delegate remove expense handling
        formsContainer.addEventListener("click", e => {
            const btn = e.target.closest(".remove-expense-btn");
            if (btn) {
                const form = btn.closest(".expense-entry-form");
                form.remove();
                updateExpenseHeaders();
            }
        });

        // Live amount calculation on distance input
        formsContainer.addEventListener("input", e => {
            if (e.target.classList.contains("e-distance") || e.target.classList.contains("e-amount") || e.target.classList.contains("e-purpose")) {
                const form = e.target.closest(".expense-entry-form");
                
                if (e.target.classList.contains("e-distance")) {
                    e.target.dataset.manual = "true"; // Flag as manually edited
                    updateAmountBasedOnDistance(form);
                }
                updateModalSummary();
            }
        });
        document.addEventListener("input", e => {
            if (e.target.id === "edit-distance") {
                const modal = document.getElementById("edit-modal");
                e.target.dataset.manual = "true"; // Flag as manually edited
                if (modal) updateAmountBasedOnDistance(modal);
            }
        });
    }

    // Close on backdrop click
    document.querySelectorAll(".modal-overlay").forEach(overlay => {
        overlay.addEventListener("click", e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });
}

function openModal(id)  { document.getElementById(id)?.classList.add("open"); }
function closeModal(id) { document.getElementById(id)?.classList.remove("open"); }

// ── VIEW
function openViewModal(id) {
    const item = tableData.find(r => r.id === id);
    if (!item) return;
    const statusText = item.status.charAt(0).toUpperCase() + item.status.slice(1);
    
    // Format date roughly like '3/6/2026'
    let dateStr = item.date;
    try {
        const d = new Date(item.date);
        if (!isNaN(d.getTime())) {
            dateStr = `${d.getMonth()+1}/${d.getDate()}/${d.getFullYear()}`;
        }
    } catch(e){}

    const isAppr = item.status === 'approved';
    const mainIcon = isAppr ? 'circle-check' : (item.status === 'rejected' ? 'circle-xmark' : 'hourglass-half');
    const bgCol = isAppr ? '#10b981' : (item.status === 'rejected' ? '#ef4444' : '#f59e0b');

    // Number part of ID
    const numId = item.id.replace('EXP-', '');

    const getTierMarkup = (statusStr) => {
        const s = (statusStr || 'pending').toLowerCase();
        let c = '#f59e0b'; let t = 'Pending';
        if (s === 'approved') { c = '#10b981'; t = 'Approved'; }
        if (s === 'rejected') { c = '#ef4444'; t = 'Rejected'; }
        return `<div class="ev-status-pill" style="background: ${c}15; color: ${c}; font-size:10px; font-weight:800; padding: 4px 10px; text-transform:uppercase;">${t}</div>`;
    };

    let mStat = item.manager_status || 'pending';
    let hStat = item.hr_status || 'pending';
    let aStat = item.accountant_status || 'pending';

    // Cascade Rejection UI
    if (item.status.toLowerCase() === 'rejected') {
        if (mStat.toLowerCase() === 'rejected') {
            hStat = 'rejected'; aStat = 'rejected';
        } else if (hStat.toLowerCase() === 'rejected') {
            aStat = 'rejected';
        }
    }

    const managerMarkup = getTierMarkup(mStat);
    const hrMarkup = getTierMarkup(hStat);
    const acctMarkup = getTierMarkup(aStat);

    // Build Rejection Banner
    let rejectionBanner = '';
    if (item.status.toLowerCase() === 'rejected') {
        let rejecterRole = 'Unknown';
        let rejectReason = 'No reason provided.';
        
        if (mStat.toLowerCase() === 'rejected') {
            rejecterRole = 'Manager'; rejectReason = item.manager_reason;
        } else if (hStat.toLowerCase() === 'rejected') {
            rejecterRole = 'HR'; rejectReason = item.hr_reason;
        } else if (aStat.toLowerCase() === 'rejected') {
            rejecterRole = 'Sr. Manager'; rejectReason = item.accountant_reason;
        }

        rejectionBanner = `
            <div style="margin-bottom: 24px; padding: 16px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 12px; color: #ef4444;">
                <div style="font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 1.05rem;">
                    <i class="fa-solid fa-circle-xmark" style="font-size: 18px;"></i>
                    Claim Rejected by ${rejecterRole}
                </div>
                <div style="font-size: 0.9rem; padding-left: 26px; color: #b91c1c;">
                    <strong>Reason:</strong> ${rejectReason || 'No details provided.'}
                </div>
            </div>
        `;
    }

    document.getElementById("view-modal-dynamic-content").innerHTML = `
        <!-- Minimalist Header -->
        <div class="ev-header">
            <div class="ev-header-left">
                <h2>${item.purpose || 'No purpose provided'}</h2>
                <p>Expense #${numId}</p>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <div class="ev-status-pill" style="background: ${bgCol}20; color: ${bgCol};">
                    <i class="fa-solid fa-${mainIcon}"></i> ${statusText}
                </div>
                <button class="ev-close-x" id="view-modal-close-new"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>

        <!-- Minimalist Body -->
        <div class="ev-body">
            ${rejectionBanner}
            <!-- Stats Grid -->
            <div class="ev-grid">
                <div class="ev-stat-box">
                    <span class="ev-stat-label"><i class="fa-solid fa-car-side" style="margin-right:5px; opacity:0.8;"></i> Transport</span>
                    <span class="ev-stat-value">${item.mode}</span>
                </div>
                <div class="ev-stat-box">
                    <span class="ev-stat-label"><i class="fa-solid fa-route" style="margin-right:5px; opacity:0.8;"></i> Distance</span>
                    <span class="ev-stat-value">${item.distance}</span>
                </div>
                <div class="ev-stat-box" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                    <span class="ev-stat-label" style="color:#16a34a"><i class="fa-solid fa-wallet" style="margin-right:5px; opacity:0.9;"></i> Total Amount</span>
                    <span class="ev-stat-value amount" style="color:#16a34a">₹${parseFloat(item.amount).toFixed(2)}</span>
                </div>
            </div>

            <div class="ev-details-section">
                <!-- Left: Trip Info -->
                <div class="ev-card-bordered">
                    <h5 class="ev-section-title">Trip Details</h5>
                    <div class="ev-info-row">
                        <span class="ev-info-label"><i class="fa-solid fa-location-dot" style="margin-right:6px; color:#94a3b8;"></i> From</span>
                        <span class="ev-info-value">${item.from}</span>
                    </div>
                    <div class="ev-info-row">
                        <span class="ev-info-label"><i class="fa-solid fa-location-crosshairs" style="margin-right:6px; color:#94a3b8;"></i> To</span>
                        <span class="ev-info-value">${item.to}</span>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
                        <div class="ev-info-row">
                            <span class="ev-info-label"><i class="fa-solid fa-calendar-day" style="margin-right:6px; color:#94a3b8;"></i> Travel Date</span>
                            <span class="ev-info-value">${dateStr}</span>
                        </div>
                        <div class="ev-info-row">
                            <span class="ev-info-label"><i class="fa-solid fa-clock" style="margin-right:6px; color:#94a3b8;"></i> Created On</span>
                            <span class="ev-info-value">${dateStr}</span>
                        </div>
                    </div>
                    ${item.resubmission_count > 0 ? `
                    <div class="ev-info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
                        <span class="ev-info-label" style="display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-rotate-right" style="font-size: 10px;"></i> Resubmissions
                        </span>
                        <span class="ev-info-value" style="color: #3b82f6; font-weight: 700; font-size: 13px;">
                            ${item.resubmission_count} / ${item.max_resubmissions || 3}
                        </span>
                    </div>
                    ` : ''}
                </div>

                <!-- Right: Workflow status -->
                <div class="ev-card-bordered">
                    <h5 class="ev-section-title">Approval Workflow</h5>
                    <div class="ev-workflow-list">
                        <div class="ev-workflow-item">
                            <div class="ev-workflow-user">
                                <div class="ev-user-icon"><i class="fa-solid fa-user-tie"></i></div>
                                <span class="ev-workflow-name">Manager</span>
                            </div>
                            ${managerMarkup}
                        </div>
                        <div class="ev-workflow-item">
                            <div class="ev-workflow-user">
                                <div class="ev-user-icon"><i class="fa-solid fa-users-gear"></i></div>
                                <span class="ev-workflow-name">HR</span>
                            </div>
                            ${hrMarkup}
                        </div>
                        <div class="ev-workflow-item">
                            <div class="ev-workflow-user">
                                <div class="ev-user-icon"><i class="fa-solid fa-user-shield"></i></div>
                                <span class="ev-workflow-name">Sr. Manager</span>
                            </div>
                            ${acctMarkup}
                        </div>
                    </div>
                </div>
            </div>

            ${item.attachments && item.attachments.length > 0 ? `
            <div class="ev-card-bordered">
                <h5 class="ev-section-title">Attachments</h5>
                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    ${item.attachments.map(att => {
                        const isImg = att.path.match(/\.(jpg|jpeg|png|gif)$/i);
                        const icon = isImg ? 'image' : (att.path.endsWith('.pdf') ? 'file-pdf' : 'file-lines');
                        const label = att.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                        
                        return `
                        <div class="ev-attachment-card" style="min-width: 240px; border-style:dashed;">
                            <div class="ev-file-icon"><i class="fa-regular fa-${icon}"></i></div>
                            <div class="ev-file-details">
                                <span class="ev-file-name">${label}</span>
                                <span class="ev-file-size" title="${att.name}">${att.name.length > 20 ? att.name.substring(0, 20) + '...' : att.name}</span>
                            </div>
                            <a href="../../${att.path}" target="_blank" class="ev-download-btn" title="View Attachment">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </div>
                        `;
                    }).join('')}
                </div>
            </div>
            ` : ''}
        </div>
        
        <!-- Minimalist Footer -->
        <div class="ev-footer">
            <button class="btn-minimal" id="view-modal-close-footer">Dismiss</button>
            <button class="btn-primary-minimal" id="view-modal-print">
                <i class="fa-solid fa-print"></i> Print Details
            </button>
        </div>
    `;
    
    // Add event listeners for the new close buttons
    document.getElementById("view-modal-close-new")?.addEventListener("click", () => closeModal("view-modal"));
    document.getElementById("view-modal-close-footer")?.addEventListener("click", () => closeModal("view-modal"));
    document.getElementById("view-modal-print")?.addEventListener("click", () => window.print());

    openModal("view-modal");
}

// ── EDIT & RESUBMIT
let isEditModeResubmit = false;

function openEditModal(id, isResubmit = false) {
    const item = tableData.find(r => r.id === id);
    if (!item) return;
    activeEditId = id;
    isEditModeResubmit = isResubmit;
    
    if (isResubmit) {
        const curr = parseInt(item.resubmission_count) || 0;
        const maxR = parseInt(item.max_resubmissions) || 3;
        if (curr >= maxR) {
            showStatusAlert(`Maximum resubmissions reached (${maxR}).`, 'Limit Exceeded');
            return;
        }
    }
    
    // UI Helpers
    const statusText = item.status.charAt(0).toUpperCase() + item.status.slice(1);
    const bgCol = item.status === 'approved' ? '#10b981' : (item.status === 'rejected' ? '#ef4444' : '#f59e0b');
    
    // Number part of ID
    const numId = item.id.replace('EXP-', '');

    const resubmitBanner = isResubmit ? `
        <div style="margin-bottom: 24px; padding: 16px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 12px; color: #b45309;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 1.05rem;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
                Caution: Resubmission Review
            </div>
            <div style="font-size: 0.9rem; padding-left: 26px; color: #92400e;">
                Make sure everything is correct before resubmitting. You can only resubmit up to <strong>${item.max_resubmissions || 3}</strong> times. 
                <br>Current resubmissions: <strong>${item.resubmission_count || 0}</strong>.
            </div>
        </div>
    ` : '';

    document.getElementById("edit-modal-dynamic-content").innerHTML = `
        <!-- Minimalist Header -->
        <div class="ev-header">
            <div class="ev-header-left">
                <h2>${isResubmit ? 'Resubmit Expense' : 'Edit Expense'} #${numId}</h2>
                <p>${isResubmit ? 'Correct your details below to resubmit' : 'Update your travel claim details'}</p>
            </div>
            <button class="ev-close-x" id="edit-modal-close-new"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Minimalist Body -->
        <div class="ev-body">
            ${resubmitBanner}
            <!-- Section 1: Basics -->
            <div class="ev-card-bordered">
                <h5 class="ev-section-title">General Details</h5>
                <div class="edit-grid">
                    <div class="edit-field">
                        <label>Travel Date</label>
                        <input type="date" id="edit-date" class="add-input e-date" value="${item.date}">
                    </div>
                    <div class="edit-field" style="grid-column: 1 / -1;">
                        <label>Purpose</label>
                        <textarea id="edit-purpose" class="add-input e-purpose" style="min-height: 80px; resize: vertical;">${item.purpose}</textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Route & Transport -->
            <div class="ev-card-bordered">
                <h5 class="ev-section-title">Travel Information</h5>
                <div class="edit-grid">
                    <div class="edit-field">
                        <label>From Location</label>
                        <div class="field-wrap">
                            <input type="text" id="edit-from" class="add-input e-from" value="${item.from}" autocomplete="off">
                            <div class="address-suggestions" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="edit-field">
                        <label>To Location</label>
                        <div class="field-wrap">
                            <input type="text" id="edit-to" class="add-input e-to" value="${item.to}" autocomplete="off">
                            <div class="address-suggestions" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="edit-field">
                        <label>Mode of Transport</label>
                        <div class="custom-select" tabindex="0" id="edit-mode-select">
                            <div class="select-trigger add-input" style="justify-content: space-between; display: flex; align-items:center;">
                                <span class="select-value">${item.mode || 'Select mode'}</span>
                                <i class="fa-solid fa-chevron-down" style="color:#94a3b8;font-size:11px;"></i>
                            </div>
                            <div class="select-dropdown" style="z-index: 100;">
                                ${['Auto', 'Bike', 'Bike Taxi', 'Bus', 'Cab', 'Car', 'E-Rickshaw', 'Flight', 'Metro', 'Other', 'Train'].map(m => `
                                    <div class="select-item ${item.mode === m ? 'active' : ''}" data-value="${m}">${m}</div>
                                `).join('')}
                            </div>
                            <input type="hidden" id="edit-mode" class="e-mode" value="${item.mode}">
                        </div>
                    </div>
                    <div class="edit-field">
                        <label>Distance</label>
                        <input type="text" id="edit-distance" class="add-input e-distance" value="${item.distance}">
                    </div>
                </div>

                <!-- Vehicle Specific Meter Photos (Dynamic) -->
                <div id="edit-meter-photos-container" style="display: ${['Bike', 'Car', 'E-Rickshaw'].includes(item.mode) ? 'grid' : 'none'}; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                    <div class="edit-field">
                        <label>New Meter Start Photo</label>
                        <label class="file-drop-zone edit-meter-start-lbl" style="background:#fff; border-radius:12px; padding:12px; border:1px solid #e2e8f0; cursor:pointer; display:flex; align-items:center;">
                            <i class="fa-solid fa-gauge-high" style="font-size:16px; color:#94a3b8;"></i>
                            <span class="file-status-text" style="font-size:11px; margin-left:10px; color:#64748b;">Change Start Photo...</span>
                            <input type="file" id="edit-meter-start" class="e-meter-start-input" accept="image/*" style="display:none;">
                        </label>
                    </div>
                    <div class="edit-field">
                        <label>New Meter End Photo</label>
                        <label class="file-drop-zone edit-meter-end-lbl" style="background:#fff; border-radius:12px; padding:12px; border:1px solid #e2e8f0; cursor:pointer; display:flex; align-items:center;">
                            <i class="fa-solid fa-gauge-high" style="font-size:16px; color:#94a3b8;"></i>
                            <span class="file-status-text" style="font-size:11px; margin-left:10px; color:#64748b;">Change End Photo...</span>
                            <input type="file" id="edit-meter-end" class="e-meter-end-input" accept="image/*" style="display:none;">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Existing Media Preview -->
            ${item.attachments && item.attachments.length > 0 ? `
            <div class="ev-card-bordered" style="background:#f8fafc; border-style:dashed;">
                <h5 class="ev-section-title" style="margin-bottom:12px;">Existing Attachments</h5>
                <div style="display:flex; gap:12px; overflow-x:auto; padding-bottom:4px;">
                    ${item.attachments.map(att => {
                        const isImg = att.path.match(/\.(jpg|jpeg|png|gif)$/i);
                        return `
                        <div style="position:relative; width:64px; height:64px; border-radius:10px; overflow:hidden; border:1.5px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); background:#fff; flex-shrink:0;">
                            ${isImg ? `<img src="../../${att.path}" style="width:100%; height:100%; object-fit:cover;">` : `<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#94a3b8; font-size:20px;"><i class="fa-solid fa-file-lines"></i></div>`}
                            <a href="../../${att.path}" target="_blank" style="position:absolute; inset:0; background:rgba(15,23,42,0.4); display:flex; align-items:center; justify-content:center; opacity:0; transition:0.2s; color:#fff;">
                                <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                            </a>
                        </div>
                        `;
                    }).join('')}
                </div>
                <p style="font-size:10px; color:#94a3b8; margin-top:10px; font-style:italic;">* Uploading new files below will replace these documents.</p>
            </div>
            ` : ''}

            <!-- Section 3: Amount & File (Side by Side) -->
            <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:24px;">
                <div class="ev-card-bordered">
                    <h5 class="ev-section-title">Amount (₹)</h5>
                    <div class="edit-field">
                        <input type="text" id="edit-amount" class="add-input e-amount" value="${parseFloat(item.amount).toFixed(2)}" style="font-size:18px; font-weight:700; color:#16a34a;">
                    </div>
                </div>
                <div class="ev-card-bordered" style="background:#f8fafc; border-style:dashed;">
                    <h5 class="ev-section-title">Upload Bill/Receipt</h5>
                    <div class="edit-field">
                        <label class="file-drop-zone edit-file-lbl" style="background:#fff; border-radius:12px; padding:15px; border:1px solid #e2e8f0; cursor:pointer; display:flex; align-items:center;">
                            <i class="fa-solid fa-file-arrow-up" style="font-size:18px; color:#94a3b8;"></i>
                            <span class="edit-file-name" style="font-size:12px; margin-left:12px; color:#475569;">${item.attachments && item.attachments.length > 0 ? 'Replace existing bill...' : 'Choose new bill...'}</span>
                            <input type="file" id="edit-attachment" class="e-bill-input" accept="image/*,.pdf" style="display:none;">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Administration Info -->
            <div class="ev-card-bordered" style="background:#fef2f2; border-color:#fee2e2;">
                <h5 class="ev-section-title" style="color:#991b1b; display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <i class="fa-solid fa-lock"></i> Administration
                </h5>
                <p style="font-size:12px; color:#991b1b; margin: 0 0 15px 0; opacity:0.8;">Verification status is managed by the administrative department.</p>
                <div class="edit-field">
                    <div class="custom-select disabled" tabindex="-1" style="opacity: 0.8; cursor: not-allowed;">
                        <div class="select-trigger add-input" style="justify-content: space-between; display: flex; background: #fff; align-items:center; border-color:#fee2e2;">
                            <span class="select-value" style="font-weight:700; color:#991b1b;">${statusText}</span>
                            <i class="fa-solid fa-circle" style="color:${bgCol}; font-size:8px;"></i>
                        </div>
                        <input type="hidden" id="edit-status" value="${item.status}" class="e-status">
                    </div>
                </div>
            </div>
        </div>

        <!-- Minimalist Footer -->
        <div class="ev-footer">
            <button class="btn-minimal" id="edit-modal-cancel-new">Discard Changes</button>
            <button class="btn-primary-minimal" id="edit-modal-save-new">
                <i class="fa-solid fa-${isResubmit ? 'rotate-right' : 'floppy-disk'}"></i> ${isResubmit ? 'Resubmit Expense' : 'Update Expense'}
            </button>
        </div>
    `;

    // Reattach listeners to newly created buttons
    document.getElementById("edit-modal-close-new")?.addEventListener("click", () => closeModal("edit-modal"));
    document.getElementById("edit-modal-cancel-new")?.addEventListener("click", () => closeModal("edit-modal"));
    document.getElementById("edit-modal-save-new")?.addEventListener("click", saveEdit);

    // Attachment field listener
    const setupFileListener = (inputId, lblClass, nameClass) => {
        const input = document.getElementById(inputId);
        const lbl = document.querySelector(`.${lblClass}`);
        const nameSpan = document.querySelector(`.${nameClass}`);
        if (!input || !lbl) return;
        input.addEventListener("change", (e) => {
            const name = e.target.files[0]?.name || "Choose file...";
            if (nameSpan) nameSpan.textContent = name;
            if (e.target.files.length > 0) {
                lbl.classList.add("has-file");
                lbl.style.borderColor = "#86efac";
                lbl.style.background = "#f0fdf4";
            }
        });
    };

    setupFileListener("edit-attachment", "edit-file-lbl", "edit-file-name");
    setupFileListener("edit-meter-start", "edit-meter-start-lbl", "edit-meter-start-lbl .file-status-text");
    setupFileListener("edit-meter-end", "edit-meter-end-lbl", "edit-meter-end-lbl .file-status-text");

    // Mode change logic for Edit Modal
    const editModeInput = document.getElementById("edit-mode");
    if (editModeInput) {
        editModeInput.addEventListener("change", (e) => {
            const mode = e.target.value;
            const meterContainer = document.getElementById("edit-meter-photos-container");
            const isVehicle = ['Bike', 'Car', 'E-Rickshaw'].includes(mode);
            if (meterContainer) {
                meterContainer.style.display = isVehicle ? 'grid' : 'none';
            }
            updateAmountBasedOnDistance(document.getElementById("edit-modal-dynamic-content"));
        });
    }
    
    initAddressAutocomplete(document.getElementById("edit-modal-dynamic-content"));
    updateAmountBasedOnDistance(document.getElementById("edit-modal-dynamic-content")); // ADDED: initialize lock for correct mode on opening

    openModal("edit-modal");
}

async function saveEdit() {
    if (!activeEditId) return;
    const saveBtn = document.getElementById("edit-modal-save-new");
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
    }

    const selectedDateStr = document.getElementById("edit-date").value;
    const selectedDate = new Date(selectedDateStr);
    const today = new Date();
    today.setHours(0,0,0,0);
    const diffDays = Math.ceil((today - selectedDate) / (1000 * 60 * 60 * 24));
    
    if (diffDays > 15 && selectedDate < today) {
        showStatusAlert("Expense date cannot be more than 15 days in the past.", "Policy Restriction");
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Expense';
        }
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', activeEditId);
        formData.append('date',     selectedDateStr);
        formData.append('purpose',  document.getElementById("edit-purpose").value);
        formData.append('from',     document.getElementById("edit-from").value);
        formData.append('to',       document.getElementById("edit-to").value);
        formData.append('mode',     document.getElementById("edit-mode").value);
        const rawDistance = document.getElementById("edit-distance").value;
        const cleanDistance = parseFloat(rawDistance.replace(/[^\d.]/g, '')) || 0;
        
        const rawAmount = document.getElementById("edit-amount").value;
        const cleanAmount = parseFloat(rawAmount.replace(/[^\d.]/g, '')) || 0;

        formData.append('distance', cleanDistance);
        formData.append('amount',   cleanAmount);

        const billInput = document.getElementById("edit-attachment");
        const startInput = document.getElementById("edit-meter-start");
        const endInput = document.getElementById("edit-meter-end");

        if (billInput && billInput.files[0]) formData.append('bill', billInput.files[0]);
        if (startInput && startInput.files[0]) formData.append('meter_start', startInput.files[0]);
        if (endInput && endInput.files[0]) formData.append('meter_end', endInput.files[0]);

        const apiUrl = isEditModeResubmit ? '../api/resubmit_travel_expense.php' : '../api/update_travel_expense.php';

        const resp = await fetch(apiUrl, {
            method: 'POST',
            body: formData
        });
        const result = await resp.json();

        if (result.success) {
            // Re-fetch all expenses to ensure local tableData is in sync with server (including new file paths)
            await fetchExpenses(); 
            closeModal("edit-modal");
        } else {
            showStatusAlert(result.message, "Update Failed");
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Expense';
            }
        }
    } catch (err) {
        console.error("Save edit error:", err);
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Expense';
        }
        showStatusAlert("An error occurred while saving your changes.", "System Error");
    }

    activeEditId = null;
}

// ── DELETE
function openDeleteModal(id) {
    const item = tableData.find(r => r.id === id);
    if (!item) return;
    activeDeleteId = id;
    document.getElementById("delete-modal-msg").textContent =
        `You are about to delete "${item.purpose}" (${item.id}). This action cannot be undone.`;
    openModal("delete-modal");
}

async function confirmDelete() {
    if (!activeDeleteId) return;
    const confirmBtn = document.getElementById("delete-modal-confirm");
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    }

    try {
        const fd = new FormData();
        fd.append('id', activeDeleteId);
        
        const resp = await fetch('../api/delete_travel_expense.php', {
            method: 'POST',
            body: fd
        });
        const result = await resp.json();
        
        if (result.success) {
            // Update local state and UI
            tableData = tableData.filter(r => r.id !== activeDeleteId);
            closeModal("delete-modal");
            applyFilters();
            
            // Visual success indicator could be added here
        } else {
            console.error("Delete failed:", result.message);
            // Re-enable button on error
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Confirm Delete';
            }
            showStatusAlert(result.message, "Deletion Failed");
        }
    } catch (err) {
        console.error("Delete error:", err);
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Confirm Delete';
        }
        showStatusAlert("An unexpected error occurred during deletion.", "System Error");
    }
    
    activeDeleteId = null;
}

/* ═══════════════════════════════════════════════
   ADD EXPENSE MODAL LOGIC (DYNAMIC FORMS)
═══════════════════════════════════════════════ */
function openAddExpenseModal() {
    const container = document.getElementById("expense-forms-container");
    if (!container) return;

    // Keep only the first form, clear it
    const forms = container.querySelectorAll(".expense-entry-form");
    forms.forEach((val, idx) => {
        if (idx !== 0) val.remove();
    });

    const firstForm = container.querySelector(".expense-entry-form");
    if (firstForm) {
        firstForm.querySelectorAll(".add-input, .e-mode").forEach(el => {
            if (el.tagName !== "DIV") el.value = "";
            el.style.borderColor = "";
            el.style.boxShadow = "";
        });
        firstForm.querySelectorAll('.custom-select').forEach(sel => {
            const defItem = sel.querySelector('.select-item[data-value=""]');
            const valSpan = sel.querySelector('.select-value');
            if (defItem && valSpan) {
                valSpan.textContent = defItem.textContent;
                valSpan.style.color = "#94a3b8";
            }
            sel.querySelectorAll('.select-item').forEach(i => i.classList.remove('active'));
            if (defItem) defItem.classList.add('active');
        });
        firstForm.querySelectorAll(".e-meter-start-input, .e-meter-end-input, .e-bill-input").forEach(el => el.value = "");
        firstForm.querySelectorAll(".e-meter-start-name, .e-meter-end-name, .e-bill-name").forEach(el => el.textContent = "Choose file…");
        firstForm.querySelectorAll(".file-drop-zone").forEach(el => el.classList.remove("has-file"));
        
        // Default visibility: hide both photo sections until user picks a mode
        firstForm.querySelectorAll(".meter-photo-field").forEach(el => el.style.display = "none");
        firstForm.querySelectorAll(".bill-photo-field").forEach(el => el.style.display = "none");
        
        // Ensure no remove button on first form
        const rm = firstForm.querySelector(".remove-expense-btn");
        if (rm) rm.remove();

        const today = new Date().toISOString().split("T")[0];
        const dateInput = firstForm.querySelector(".e-date");
        if (dateInput) dateInput.value = today;

        initAddressAutocomplete(firstForm);
        initDateLimits(firstForm);
    }

    updateExpenseHeaders();
    openModal("add-expense-modal");
}

function initDateLimits(form) {
    const dateInput = form.querySelector(".e-date");
    if (!dateInput) return;

    // Use a fixed date reference to avoid time-of-day edge cases
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const pastLimit = new Date();
    pastLimit.setDate(today.getDate() - 15);
    pastLimit.setHours(0, 0, 0, 0);

    // Format as YYYY-MM-DD
    const maxStr = today.toLocaleDateString('en-CA'); // 'en-CA' gives YYYY-MM-DD format
    const minStr = pastLimit.toLocaleDateString('en-CA');

    dateInput.setAttribute('max', maxStr);
    dateInput.setAttribute('min', minStr);
}

// Orange button: add a new cloned form below the current ones
let lastValidatedForm = null;

function stageExpense() {
    const container = document.getElementById("expense-forms-container");
    if (!container) return;

    const forms = container.querySelectorAll(".expense-entry-form");
    const lastForm = forms[forms.length - 1];
    if (!lastForm) return;

    // Validate the last form before letting them add a new one
    if (!validateForm(lastForm)) {
        shakeModal("add-expense-modal");
        return;
    }

    lastValidatedForm = lastForm;
    openModal("return-trip-modal");
}

function handleReturnTripChoice(isReturnTrip) {
    closeModal("return-trip-modal");
    
    if (isReturnTrip && lastValidatedForm) {
        // Create Return Trip entry
        const returnForm = createNewExpenseForm(lastValidatedForm, true);
        document.getElementById("expense-forms-container").appendChild(returnForm);
        updateExpenseHeaders();
        initAddressAutocomplete(returnForm);
        
        // After return trip, add a blank one? 
        // User says "next expense will automatically added". Let's add a blank one as well for next entry.
        const blankForm = createNewExpenseForm(returnForm, false);
        document.getElementById("expense-forms-container").appendChild(blankForm);
        updateExpenseHeaders();
        initAddressAutocomplete(blankForm);
        
        setTimeout(() => { blankForm.scrollIntoView({ behavior: "smooth", block: "start" }); }, 100);
    } else if (lastValidatedForm) {
        // Just add a blank form as before
        const blankForm = createNewExpenseForm(lastValidatedForm, false);
        document.getElementById("expense-forms-container").appendChild(blankForm);
        updateExpenseHeaders();
        initAddressAutocomplete(blankForm);
        
        initDateLimits(blankForm);
        
        setTimeout(() => { blankForm.scrollIntoView({ behavior: "smooth", block: "start" }); }, 100);
    }
    updateModalSummary();
}

function createNewExpenseForm(sourceForm, forReturnTrip = false) {
    const newForm = sourceForm.cloneNode(true);
    const today = new Date().toISOString().split("T")[0];

    // Clear file inputs
    newForm.querySelectorAll(".e-meter-start-input, .e-meter-end-input, .e-bill-input").forEach(el => el.value = "");
    newForm.querySelectorAll(".e-meter-start-name, .e-meter-end-name, .e-bill-name").forEach(el => el.textContent = "Choose file…");
    newForm.querySelectorAll(".file-drop-zone").forEach(el => el.classList.remove("has-file"));

    if (forReturnTrip) {
        const originalDate = sourceForm.querySelector(".e-date")?.value;
        const originalPurpose = sourceForm.querySelector(".e-purpose")?.value;
        const originalFrom = sourceForm.querySelector(".e-from")?.value;
        const originalTo = sourceForm.querySelector(".e-to")?.value;
        const originalMode = sourceForm.querySelector(".e-mode")?.value;
        const originalDistance = sourceForm.querySelector(".e-distance")?.value;
        const originalAmount = sourceForm.querySelector(".e-amount")?.value;

        newForm.querySelector(".e-date").value = originalDate;
        newForm.querySelector(".e-purpose").value = `Return: ${originalPurpose}`;
        newForm.querySelector(".e-from").value = originalTo;
        newForm.querySelector(".e-to").value = originalFrom;
        
        const modeInput = newForm.querySelector(".e-mode");
        if (modeInput) modeInput.value = originalMode;
        
        // Update custom select UI for return trip mode
        const modeSelect = newForm.querySelector(".custom-select");
        if (modeSelect) {
            const valSpan = modeSelect.querySelector(".select-value");
            if (valSpan) valSpan.textContent = originalMode || "Select mode";
            modeSelect.querySelectorAll(".select-item").forEach(i => {
                i.classList.toggle("active", i.dataset.value === originalMode);
            });
        }

        newForm.querySelector(".e-distance").value = originalDistance;
        newForm.querySelector(".e-amount").value = originalAmount;
    } else {
        // Blank form
        newForm.querySelectorAll(".add-input, .e-mode").forEach(el => {
            if (el.tagName !== "DIV") {
                if (!el.classList.contains("e-date")) el.value = "";
                else el.value = today;
            }
            el.style.borderColor = "";
            el.style.boxShadow = "";
        });
        newForm.querySelectorAll('.custom-select').forEach(sel => {
            const defItem = sel.querySelector('.select-item[data-value=""]');
            const valSpan = sel.querySelector('.select-value');
            if (defItem && valSpan) {
                valSpan.textContent = defItem.textContent;
                valSpan.style.color = "#94a3b8";
            }
            sel.querySelectorAll('.select-item').forEach(i => i.classList.remove('active'));
            if (defItem) defItem.classList.add('active');
        });
    }

    // Ensure correct field visibility based on the cloned form's selected mode
    const clonedMode = newForm.querySelector(".e-mode")?.value || "";
    const clonedNeedsMeters = userMeterModes.includes(clonedMode);
    const isPersonalVehicle = ['Bike', 'Car'].includes(clonedMode);
    newForm.querySelectorAll(".meter-photo-field").forEach(el => el.style.display = clonedNeedsMeters ? "flex" : "none");
    newForm.querySelectorAll(".bill-photo-field").forEach(el => el.style.display = (!clonedNeedsMeters && clonedMode && !isPersonalVehicle) ? "flex" : "none");

    // Initialize the locked/unlocked state of the amount field matching this cloned form
    updateAmountBasedOnDistance(newForm);

    // Add remove button if it doesn't exist (since it's not the 1st one)
    let header = newForm.querySelector(".expense-entry-header");
    if (header && !header.querySelector(".remove-expense-btn")) {
        header.innerHTML += `<button class="remove-expense-btn" title="Remove Entry"><i class="fa-solid fa-trash-can"></i></button>`;
    }

    return newForm;
}

// Read and validate a specific form element
function readFormEntryElement(form) {
    const date     = form.querySelector(".e-date")?.value.trim()     || "";
    const purpose  = form.querySelector(".e-purpose")?.value.trim()  || "";
    const from     = form.querySelector(".e-from")?.value.trim()     || "";
    const to       = form.querySelector(".e-to")?.value.trim()       || "";
    const mode     = form.querySelector(".e-mode")?.value            || "";
    const distance = form.querySelector(".e-distance")?.value.trim() || "";
    const amount   = form.querySelector(".e-amount")?.value.trim()   || "";

    if (!date || !purpose || !from || !to || !mode || !distance || !amount) return null;

    return {
        id: `EXP-${Math.floor(1000 + Math.random() * 9000)}`,
        date, purpose, from, to, mode,
        distance: `${distance} km`,
        amount,
        status: "pending"
    };
}

function validateForm(form) {
    let valid = true;
    const required = [".e-date", ".e-purpose", ".e-from", ".e-to", ".e-mode", ".e-distance", ".e-amount"];
    
    required.forEach(cls => {
        const el = form.querySelector(cls);
        if (!el) return;
        if (!el.value.trim()) {
            valid = false;
            el.style.borderColor = "#f87171";
            el.style.boxShadow   = "0 0 0 3px rgba(248,113,113,0.15)";
            el.addEventListener("input", () => {
                el.style.borderColor = "";
                el.style.boxShadow   = "";
            }, { once: true });
        }
    });

    // ───── Date Validation: Max 15 days in the past ─────
    const dateInput = form.querySelector(".e-date");
    if (dateInput && dateInput.value) {
        const selectedDate = new Date(dateInput.value);
        const diffTime = new Date() - selectedDate;
        const diffDays = Math.ceil(diffTime / (1000 * 3600 * 24));

        if (diffDays > 15) {
            valid = false;
            dateInput.style.borderColor = "#f87171";
            dateInput.style.boxShadow   = "0 0 0 3px rgba(248,113,113,0.15)";
            showToast("Expenses older than 15 days cannot be submitted.");
        }
    }

    // File validation logic — sourced from travel_meter_photo_perms table
    const mode = form.querySelector(".e-mode")?.value;

    // E-Rickshaw & Metro are fully exempt — no photo required at all
    if (!PHOTO_EXEMPT_MODES.includes(mode)) {
        const modeRequiresMeters = userMeterModes.includes(mode);
        const isPersonalVehicle = ['Bike', 'Car'].includes(mode);

        if (modeRequiresMeters) {
            // This mode requires meter start + end photos for this user
            const start = form.querySelector(".e-meter-start-input");
            const end   = form.querySelector(".e-meter-end-input");
            if (!start?.files.length || !end?.files.length) {
                valid = false;
                if (!start?.files.length) markFileError(form.querySelector(".meter-photo-field:first-child .file-drop-zone"));
                if (!end?.files.length)   markFileError(form.querySelector(".meter-photo-field:last-child .file-drop-zone"));
            }
        } else if (!isPersonalVehicle) {
            // Mode does NOT require meters and is NOT Bike/Car — require a bill/ticket photo instead
            const bill = form.querySelector(".e-bill-input");
            if (!bill?.files.length) {
                valid = false;
                markFileError(form.querySelector(".bill-photo-field .file-drop-zone"));
            }
        }
    }
    // else: exempt mode (E-Rickshaw / Metro) — skip all photo validation

    return valid;
}

function markFileError(zone) {
    if (!zone) return;
    zone.style.borderColor = "#f87171";
    zone.style.background = "#fef2f2";
    zone.addEventListener("click", () => {
        zone.style.borderColor = "";
        zone.style.background = "";
    }, { once: true });
}

// Green button: parse all forms and save to Database via API
async function saveAllExpenses() {
    const container = document.getElementById("expense-forms-container");
    const forms = container.querySelectorAll(".expense-entry-form");
    
    let allValid = true;
    const formData = new FormData();

    forms.forEach((form, index) => {
        const isValid = validateForm(form);
        if (!isValid) {
            allValid = false;
        } else {
            // Mapping fields to the index-based FormData keys that PHP expects
            formData.append(`expenses[${index}][date]`,     form.querySelector(".e-date")?.value || "");
            formData.append(`expenses[${index}][purpose]`,  form.querySelector(".e-purpose")?.value || "");
            formData.append(`expenses[${index}][from]`,     form.querySelector(".e-from")?.value || "");
            formData.append(`expenses[${index}][to]`,       form.querySelector(".e-to")?.value || "");
            formData.append(`expenses[${index}][mode]`,     form.querySelector(".e-mode")?.value || "");
            formData.append(`expenses[${index}][distance]`, form.querySelector(".e-distance")?.value || 0);
            formData.append(`expenses[${index}][amount]`,   form.querySelector(".e-amount")?.value || 0);
            formData.append(`expenses[${index}][notes]`,    form.querySelector(".e-notes")?.value || "");

            // Attachments
            const billImg  = form.querySelector(".e-bill-input")?.files[0];
            const startImg = form.querySelector(".e-meter-start-input")?.files[0];
            const endImg   = form.querySelector(".e-meter-end-input")?.files[0];

            if (billImg)  formData.append(`expenses[${index}][bill]`, billImg);
            if (startImg) formData.append(`expenses[${index}][meter_start]`, startImg);
            if (endImg)   formData.append(`expenses[${index}][meter_end]`, endImg);
        }
    });

    if (!allValid) {
        shakeModal("add-expense-modal");
        return;
    }

    // Button loading state
    const saveBtn = document.getElementById("btn-save-all-expenses");
    const originalHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';

    try {
        const response = await fetch('../api/save_travel_expenses.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            closeModal("add-expense-modal");
            fetchExpenses(); // Re-fetch all data to refresh the main table
            showToast(result.message);
        } else {
            showToast("Failed to save: " + result.message);
        }
    } catch (error) {
        console.error("Save error:", error);
        showToast("A system error occurred. Please try again.");
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHtml;
    }
}

// Minimal toast notification
function showToast(message) {
    let toast = document.getElementById("app-toast");
    if (!toast) {
        toast = document.createElement("div");
        toast.id = "app-toast";
        toast.style.cssText = `
            position:fixed; bottom:28px; right:28px; z-index:9999;
            background:#1e293b; color:#fff;
            padding:13px 20px; border-radius:10px;
            font-family:var(--font); font-size:13.5px; font-weight:500;
            box-shadow:0 8px 24px rgba(0,0,0,0.25);
            display:flex; align-items:center; gap:10px;
            animation: slideUp 0.25s ease;
            transition: opacity 0.3s;`;
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#4ade80;"></i> ${message}`;
    toast.style.opacity = "1";
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.opacity = "0"; }, 3000);
}

// Subtle shake animation if validation fails
function shakeModal(overlayId) {
    const box = document.querySelector(`#${overlayId} .modal-box, #${overlayId} .add-expense-box`);
    if (!box) return;
    box.style.animation = "none";
    box.offsetHeight; // reflow
    box.style.animation = "shake 0.4s ease";
}

// Inject shake keyframe if not present
const shakeStyle = document.createElement("style");
shakeStyle.textContent = `
@keyframes shake {
  0%,100%{ transform: translateX(0); }
  20%{ transform: translateX(-8px); }
  40%{ transform: translateX(8px); }
  60%{ transform: translateX(-6px); }
  80%{ transform: translateX(4px); }
}`;
document.head.appendChild(shakeStyle);

// ─── Free Map Distance (Leaflet) ───
function initLeafletMap() {
    if (leafletMap) return;
    const mapDiv = document.getElementById('distance-map');
    if (!mapDiv) return;
    mapDiv.style.width = "1px";
    mapDiv.style.height = "1px";
    leafletMap = L.map('distance-map').setView([20.5937, 78.9629], 5);
}

async function calculateDistanceForForm(formRow) {
    const fromVal = formRow.querySelector('.e-from').value.trim();
    const toVal   = formRow.querySelector('.e-to').value.trim();
    const distInput = formRow.querySelector('.e-distance');

    if (!fromVal || !toVal || fromVal.length < 3 || toVal.length < 3) return;
    
    distInput.placeholder = "Calculating...";

    try {
        const geocode = async (addr) => {
            const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}&limit=1`);
            const results = await resp.json();
            return results.length > 0 ? L.latLng(results[0].lat, results[0].lon) : null;
        };

        const fromLatLng = await geocode(fromVal);
        const toLatLng   = await geocode(toVal);

        if (fromLatLng && toLatLng) {
            if (routingControl) leafletMap.removeControl(routingControl);
            
            routingControl = L.Routing.control({
                waypoints: [fromLatLng, toLatLng],
                router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
                createMarker: () => null,
                show: false,
                addWaypoints: false,
                lineOptions: { addWaypoints: false }
            }).addTo(leafletMap);

            routingControl.on('routesfound', (e) => {
                const distanceKm = (e.routes[0].summary.totalDistance / 1000).toFixed(2);
                
                // ONLY OVERWRITE IF NOT MANUALLY EDITED
                if (distInput.dataset.manual !== "true" || !distInput.value) {
                    distInput.value = distanceKm;
                    updateAmountBasedOnDistance(formRow);
                }
                distInput.placeholder = "Distance in km";
            });
        }
    } catch (e) {
        console.error("Distance error:", e);
        distInput.placeholder = "Distance in km";
    }
}

function updateAmountBasedOnDistance(form) {
    const modeEl = form.querySelector('.e-mode') || form.querySelector('#edit-mode');
    const distEl = form.querySelector('.e-distance') || form.querySelector('#edit-distance');
    const amtEl  = form.querySelector('.e-amount') || form.querySelector('#edit-amount');
    
    if (!modeEl || !distEl || !amtEl) return;

    const mode = modeEl.value;
    const distance = parseFloat(distEl.value) || 0;
    
    // Check if this mode has a fixed rate from the database
    const hasFixedRate = transportRates[mode] && transportRates[mode] > 0;

    if (hasFixedRate) {
        // Mode has fixed rate: calculation is automatic, field is locked
        if (distance > 0) {
            amtEl.value = (transportRates[mode] * distance).toFixed(2);
        }
        amtEl.readOnly = true;
        // Optionally style it to look disabled yet readable
        amtEl.style.backgroundColor = "#f8fafc";
        amtEl.style.cursor = "not-allowed";
        amtEl.style.opacity = "0.8";
    } else {
        // Custom amount mode: open for typing
        amtEl.readOnly = false;
        amtEl.style.backgroundColor = "";
        amtEl.style.cursor = "text";
        amtEl.style.opacity = "1";
    }
    
    updateModalSummary();
}

function updateModalSummary() {
    const summarySection = document.getElementById("modal-summary-section");
    const container = document.getElementById("expense-forms-container");
    const tbody = document.getElementById("modal-summary-tbody");
    const totalEl = document.getElementById("modal-summary-total");
    
    if (!summarySection || !container || !tbody || !totalEl) return;

    const forms = container.querySelectorAll(".expense-entry-form");
    summarySection.style.display = forms.length > 0 ? "block" : "none";

    let totalCount = 0;
    let totalAmt = 0;
    let html = "";
    
    forms.forEach((form, idx) => {
        const purpose = form.querySelector(".e-purpose")?.value || "";
        const mode = form.querySelector(".e-mode")?.value || "-";
        const amt = parseFloat(form.querySelector(".e-amount")?.value) || 0;
        
        totalAmt += amt;
        totalCount++;

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 10px; color: #94a3b8;">${idx + 1}</td>
                <td style="padding: 10px; color: #475569;">${purpose ? (purpose.length > 25 ? purpose.substring(0, 25) + "..." : purpose) : '<span style="color:#cbd5e1; font-style:italic">No purpose</span>'}</td>
                <td style="padding: 10px; color: #475569;">${mode}</td>
                <td style="padding: 10px; text-align: right; color: #1e293b; font-weight: 700;">₹ ${amt.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    totalEl.textContent = `₹ ${totalAmt.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
}

/**
 * Updates the numbered headers (e.g. "Expense #1") for all entry forms
 */
function updateExpenseHeaders() {
    const container = document.getElementById("expense-forms-container");
    if (!container) return;
    const forms = container.querySelectorAll(".expense-entry-form");
    forms.forEach((form, idx) => {
        const header = form.querySelector(".expense-entry-header h4");
        if (header) {
            header.textContent = `Expense #${idx + 1}`;
        }
        // Sync data-index just in case
        form.dataset.index = idx + 1;
    });
}

// ─── Address Autocomplete ───
function debounce(func, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function initAddressAutocomplete(parentEl) {
    const fromInput = parentEl.querySelector('.e-from') || parentEl.querySelector('#edit-from');
    const toInput   = parentEl.querySelector('.e-to') || parentEl.querySelector('#edit-to');
    
    [fromInput, toInput].forEach(innerInput => {
        if (!innerInput) return;
        const container = innerInput.nextElementSibling;
        if (!container || !container.classList.contains('address-suggestions')) return;
        
        innerInput.addEventListener('input', debounce(async () => {
            const query = innerInput.value.trim();
            if (query.length < 3) {
                container.style.display = 'none';
                return;
            }
            
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`;
                const response = await fetch(url);
                const results = await response.json();
                
                if (results && results.length > 0) {
                    container.innerHTML = results.map(res => `
                        <div class="suggestion-item" data-full="${res.display_name}">
                            <i class="fa-solid fa-location-dot"></i> ${res.display_name}
                        </div>
                    `).join('');
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            } catch (e) {
                console.error("Autocomplete fetch error:", e);
            }
        }, 500));
        
        container.addEventListener('mousedown', (e) => {
            const item = e.target.closest('.suggestion-item');
            if (item) {
                innerInput.value = item.dataset.full;
                container.style.display = 'none';
                
                // Clear manual override flag so system can recalculate
                const distInput = parentEl.querySelector('.e-distance') || parentEl.querySelector('#edit-distance');
                if (distInput) delete distInput.dataset.manual;

                calculateDistanceForForm(parentEl);
            }
        });
        
        innerInput.addEventListener('blur', () => {
            setTimeout(() => { container.style.display = 'none'; }, 200);
            calculateDistanceForForm(parentEl);
        });
    });
}

