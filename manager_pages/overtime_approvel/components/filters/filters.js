// components/filters/filters.js
import { renderTableData } from '../table/table.js';

export function initFilters() {
    // Set default month and year to current
    setDefaultDate();
    
    // Wire up all custom animated dropdowns
    initCustomDropdowns();

    const applyBtn = document.getElementById('apply-filters-btn');
    if (!applyBtn) return;

    applyBtn.addEventListener('click', () => {
        applyBtn.setAttribute('disabled', 'true');
        applyBtn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> &nbsp;Filtering...';
        applyBtn.style.opacity = '0.75';

        setTimeout(() => {
            renderTableData();
            applyBtn.removeAttribute('disabled');
            applyBtn.innerHTML = '<i class="ph ph-check"></i> Apply Filters';
            applyBtn.style.opacity = '';
        }, 420);
    });
}

function setDefaultDate() {
    const now = new Date();
    const currentMonth = (now.getMonth() + 1).toString().padStart(2, '0');
    const currentYear = now.getFullYear().toString();

    const ddMonth = document.getElementById('dd-month');
    const ddYear = document.getElementById('dd-year');

    if (ddMonth) {
        const input = ddMonth.querySelector('input[type="hidden"]');
        const display = ddMonth.querySelector('.dd-selected');
        const option = ddMonth.querySelector(`.dd-option[data-value="${currentMonth}"]`);
        
        if (input) input.value = currentMonth;
        if (display && option) display.textContent = option.textContent;
        if (option) {
            ddMonth.querySelectorAll('.dd-option').forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
        }
    }

    if (ddYear) {
        const input = ddYear.querySelector('input[type="hidden"]');
        const display = ddYear.querySelector('.dd-selected');
        const option = ddYear.querySelector(`.dd-option[data-value="${currentYear}"]`);
        
        if (input) input.value = currentYear;
        if (display && option) display.textContent = option.textContent;
        if (option) {
            ddYear.querySelectorAll('.dd-option').forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
        }
    }
}

/**
 * Dynamically populate the USER dropdown from fetched table data
 */
export function renderUserFilter(data) {
    const ddUser = document.getElementById('dd-user');
    if (!ddUser) return;

    const menu = ddUser.querySelector('.dd-menu');
    if (!menu) return;

    // Get unique employee names
    const employees = [...new Set(data.map(item => item.employee))].sort();

    // Rebuild menu
    let html = '<div class="dd-option selected" data-value="all">All Users</div>';
    employees.forEach(emp => {
        html += `<div class="dd-option" data-value="${emp}">${emp}</div>`;
    });
    menu.innerHTML = html;

    // Re-wire events for the new options
    wireDropdownOptions(ddUser);
}

/* ─────────────────────────────────────────────────────
   Custom Animated Dropdown Engine
   ───────────────────────────────────────────────────── */
function initCustomDropdowns() {
    const dropdowns = document.querySelectorAll('.custom-dropdown');
    dropdowns.forEach(dd => {
        const trigger = dd.querySelector('.dd-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = dd.classList.contains('is-open');
            closeAllDropdowns();
            if (!isOpen) {
                dd.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });

        wireDropdownOptions(dd);
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.custom-dropdown')) closeAllDropdowns();
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAllDropdowns();
    });
}

function wireDropdownOptions(dd) {
    const options     = dd.querySelectorAll('.dd-option');
    const selectedEl  = dd.querySelector('.dd-selected');
    const hiddenInput = dd.querySelector('input[type="hidden"]');
    const trigger     = dd.querySelector('.dd-trigger');

    options.forEach(opt => {
        // Remove existing listener to avoid clones (simplistic approach)
        const newOpt = opt.cloneNode(true);
        opt.parentNode.replaceChild(newOpt, opt);

        newOpt.addEventListener('click', (e) => {
            const value = newOpt.dataset.value;
            const label = newOpt.textContent.trim();

            if (hiddenInput) hiddenInput.value = value;
            if (selectedEl) selectedEl.textContent = label;

            dd.querySelectorAll('.dd-option').forEach(o => o.classList.remove('selected'));
            newOpt.classList.add('selected');

            dd.classList.remove('is-open');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });
}

function closeAllDropdowns() {
    document.querySelectorAll('.custom-dropdown.is-open').forEach(dd => {
        dd.classList.remove('is-open');
        const trigger = dd.querySelector('.dd-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
}
