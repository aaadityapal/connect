// Vendor Name Filter Functions
let vendorNameOptions = [];
let selectedVendorNames = [];

function toggleVendorNameFilter(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('vendorNameFilterDropdown');
    const icon = event.currentTarget;

    // Close other dropdowns
    closeAllFilterDropdowns();

    // Toggle this dropdown
    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        icon.classList.add('active');
        loadVendorNameOptions();
    } else {
        dropdown.style.display = 'none';
        icon.classList.remove('active');
    }
}

function loadVendorNameOptions() {
    fetch('get_vendors.php?limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                vendorNameOptions = [...new Set(data.data.map(v => v.vendor_full_name))].sort();
                displayVendorNameOptions(vendorNameOptions);
            }
        });
}

function displayVendorNameOptions(options) {
    const container = document.getElementById('vendorNameFilterOptions');
    let html = '';
    options.forEach(name => {
        const checked = selectedVendorNames.includes(name) ? 'checked' : '';
        html += `
            <div class="filter-option">
                <input type="checkbox" value="${name}" ${checked} onchange="toggleVendorNameSelection('${name.replace(/'/g, "\\'")}')">
                <span>${name}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterVendorNameOptions() {
    const search = document.getElementById('vendorNameFilterSearch').value.toLowerCase();
    const filtered = vendorNameOptions.filter(name => name.toLowerCase().includes(search));
    displayVendorNameOptions(filtered);
}

function toggleVendorNameSelection(name) {
    const index = selectedVendorNames.indexOf(name);
    if (index > -1) {
        selectedVendorNames.splice(index, 1);
    } else {
        selectedVendorNames.push(name);
    }
}

function applyVendorNameFilter() {
    vendorPaginationState.nameFilter = selectedVendorNames;
    vendorPaginationState.currentPage = 1; // Reset to page 1
    loadVendorsWithFilters();
    closeAllFilterDropdowns();
}

function clearVendorNameFilter() {
    selectedVendorNames = [];
    vendorPaginationState.nameFilter = [];
    vendorPaginationState.currentPage = 1;
    loadVendorsWithFilters();
    closeAllFilterDropdowns();
}

// Vendor Type Filter Functions
let vendorTypeOptions = [];
let selectedVendorTypes = [];

function toggleVendorTypeFilter(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('vendorTypeFilterDropdown');
    const icon = event.currentTarget;

    closeAllFilterDropdowns();

    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        icon.classList.add('active');
        loadVendorTypeOptions();
    } else {
        dropdown.style.display = 'none';
        icon.classList.remove('active');
    }
}

function loadVendorTypeOptions() {
    fetch('get_vendors.php?limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                vendorTypeOptions = [...new Set(data.data.map(v => v.vendor_type_category))].sort();
                displayVendorTypeOptions(vendorTypeOptions);
            }
        });
}

function displayVendorTypeOptions(options) {
    const container = document.getElementById('vendorTypeFilterOptions');
    let html = '';
    options.forEach(type => {
        const checked = selectedVendorTypes.includes(type) ? 'checked' : '';
        html += `
            <div class="filter-option">
                <input type="checkbox" value="${type}" ${checked} onchange="toggleVendorTypeSelection('${type.replace(/'/g, "\\'")}')">
                <span>${type}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterVendorTypeOptions() {
    const search = document.getElementById('vendorTypeFilterSearch').value.toLowerCase();
    const filtered = vendorTypeOptions.filter(type => type.toLowerCase().includes(search));
    displayVendorTypeOptions(filtered);
}

function toggleVendorTypeSelection(type) {
    const index = selectedVendorTypes.indexOf(type);
    if (index > -1) {
        selectedVendorTypes.splice(index, 1);
    } else {
        selectedVendorTypes.push(type);
    }
}

function applyVendorTypeFilter() {
    vendorPaginationState.typeFilter = selectedVendorTypes;
    vendorPaginationState.currentPage = 1;
    loadVendorsWithFilters();
    closeAllFilterDropdowns();
}

function clearVendorTypeFilter() {
    selectedVendorTypes = [];
    vendorPaginationState.typeFilter = [];
    vendorPaginationState.currentPage = 1;
    loadVendorsWithFilters();
    closeAllFilterDropdowns();
}

// Labour Name Filter Functions
let labourNameOptions = [];
let selectedLabourNames = [];

function toggleLabourNameFilter(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('labourNameFilterDropdown');
    const icon = event.currentTarget;

    closeAllFilterDropdowns();

    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        icon.classList.add('active');
        loadLabourNameOptions();
    } else {
        dropdown.style.display = 'none';
        icon.classList.remove('active');
    }
}

function loadLabourNameOptions() {
    fetch('get_labours.php?limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                labourNameOptions = [...new Set(data.data.map(l => l.full_name))].sort();
                displayLabourNameOptions(labourNameOptions);
            }
        });
}

function displayLabourNameOptions(options) {
    const container = document.getElementById('labourNameFilterOptions');
    let html = '';
    options.forEach(name => {
        const checked = selectedLabourNames.includes(name) ? 'checked' : '';
        html += `
            <div class="filter-option">
                <input type="checkbox" value="${name}" ${checked} onchange="toggleLabourNameSelection('${name.replace(/'/g, "\\'")}')">
                <span>${name}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterLabourNameOptions() {
    const search = document.getElementById('labourNameFilterSearch').value.toLowerCase();
    const filtered = labourNameOptions.filter(name => name.toLowerCase().includes(search));
    displayLabourNameOptions(filtered);
}

function toggleLabourNameSelection(name) {
    const index = selectedLabourNames.indexOf(name);
    if (index > -1) {
        selectedLabourNames.splice(index, 1);
    } else {
        selectedLabourNames.push(name);
    }
}

function applyLabourNameFilter() {
    labourPaginationState.nameFilter = selectedLabourNames;
    labourPaginationState.currentPage = 1;
    loadLaboursWithFilters();
    closeAllFilterDropdowns();
}

function clearLabourNameFilter() {
    selectedLabourNames = [];
    labourPaginationState.nameFilter = [];
    labourPaginationState.currentPage = 1;
    loadLaboursWithFilters();
    closeAllFilterDropdowns();
}

// Labour Type Filter Functions
let labourTypeOptions = [];
let selectedLabourTypes = [];

function toggleLabourTypeFilter(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('labourTypeFilterDropdown');
    const icon = event.currentTarget;

    closeAllFilterDropdowns();

    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        icon.classList.add('active');
        loadLabourTypeOptions();
    } else {
        dropdown.style.display = 'none';
        icon.classList.remove('active');
    }
}

function loadLabourTypeOptions() {
    fetch('get_labours.php?limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                labourTypeOptions = [...new Set(data.data.map(l => l.labour_type))].sort();
                displayLabourTypeOptions(labourTypeOptions);
            }
        });
}

function displayLabourTypeOptions(options) {
    const container = document.getElementById('labourTypeFilterOptions');
    let html = '';
    options.forEach(type => {
        const checked = selectedLabourTypes.includes(type) ? 'checked' : '';
        html += `
            <div class="filter-option">
                <input type="checkbox" value="${type}" ${checked} onchange="toggleLabourTypeSelection('${type.replace(/'/g, "\\'")}')">
                <span>${type}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterLabourTypeOptions() {
    const search = document.getElementById('labourTypeFilterSearch').value.toLowerCase();
    const filtered = labourTypeOptions.filter(type => type.toLowerCase().includes(search));
    displayLabourTypeOptions(filtered);
}

function toggleLabourTypeSelection(type) {
    const index = selectedLabourTypes.indexOf(type);
    if (index > -1) {
        selectedLabourTypes.splice(index, 1);
    } else {
        selectedLabourTypes.push(type);
    }
}

function applyLabourTypeFilter() {
    labourPaginationState.typeFilter = selectedLabourTypes;
    labourPaginationState.currentPage = 1;
    loadLaboursWithFilters();
    closeAllFilterDropdowns();
}

function clearLabourTypeFilter() {
    selectedLabourTypes = [];
    labourPaginationState.typeFilter = [];
    labourPaginationState.currentPage = 1;
    loadLaboursWithFilters();
    closeAllFilterDropdowns();
}

// Helper Functions
function closeAllFilterDropdowns() {
    document.querySelectorAll('.filter-dropdown').forEach(dropdown => {
        dropdown.style.display = 'none';
    });
    document.querySelectorAll('.filter-icon').forEach(icon => {
        icon.classList.remove('active');
    });
}

function loadVendorsWithFilters() {
    const params = new URLSearchParams({
        limit: vendorPaginationState.limit,
        offset: (vendorPaginationState.currentPage - 1) * vendorPaginationState.limit,
        search: vendorPaginationState.search,
        status: vendorPaginationState.status,
        nameFilter: JSON.stringify(vendorPaginationState.nameFilter),
        typeFilter: JSON.stringify(vendorPaginationState.typeFilter)
    });

    // Call the existing loadVendors function with current state
    loadVendors(
        vendorPaginationState.limit,
        vendorPaginationState.currentPage,
        vendorPaginationState.search,
        vendorPaginationState.status
    );
}

function loadLaboursWithFilters() {
    // Call the existing loadLabours function with current state
    loadLabours(
        labourPaginationState.limit,
        labourPaginationState.currentPage,
        labourPaginationState.search,
        labourPaginationState.status
    );
}

// Close dropdowns when clicking outside
document.addEventListener('click', function (e) {
    if (!e.target.closest('.filter-header-cell')) {
        closeAllFilterDropdowns();
    }
});
