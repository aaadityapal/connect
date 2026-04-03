document.addEventListener('DOMContentLoaded', () => {
    
    // Modal Selectors
    const viewReportModal = document.getElementById('viewReportModal');
    const closeReportModalBtn = document.getElementById('closeReportModal');
    const closeReportModalFooterBtn = document.getElementById('closeReportModalFooter');
    
    const modalEmployeeName = document.getElementById('modalEmployeeName');
    const modalEmployeeRole = document.getElementById('modalEmployeeRole');
    const modalReportPeriod = document.getElementById('modalReportPeriod');
    const reportModalBody = document.getElementById('reportModalBody');
    
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');

    // Main employee list rows (exclude modal rows)
    const employeeRows = Array.from(document.querySelectorAll('.btn-view'))
        .map(btn => btn.closest('tr'))
        .filter(Boolean);

    // Search + role filter for employee list
    const applyEmployeeFilters = () => {
        const query = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
        const roleSel = document.getElementById('roleFilter')?.value || '';
        const selectedRole = roleSel.trim().toLowerCase();

        let visibleSerial = 0;
        
        // Grab rows freshly to ensure we don't hold a stale reference
        const currentRows = document.querySelectorAll('table tbody tr');

        currentRows.forEach(row => {
            // Ignore modal rows
            if (row.closest('#viewReportModal') || row.closest('.modal-overlay')) return;

            const textC = (row.textContent || '').toLowerCase();
            const viewBtn = row.querySelector('.btn-view');
            
            // If it's a data row without a view button for some reason, maybe skip or use text
            const userRole = (viewBtn?.getAttribute('data-role') || '').toLowerCase();

            const matchesSearch = query === '' || textC.includes(query);
            const matchesRole = selectedRole === '' || userRole === selectedRole;
            const shouldShow = matchesSearch && matchesRole;

            row.style.display = shouldShow ? '' : 'none';

            // Keep S.No continuous after filtering
            if (shouldShow) {
                visibleSerial += 1;
                const serialCell = row.firstElementChild;
                if (serialCell && viewBtn) { // only update serial for valid rows
                    serialCell.textContent = visibleSerial;
                }
            }
        });
    };

    // Open Modal Function
    const openModal = async (userId, username, role) => {
        // Set basic info
        modalEmployeeName.textContent = username;
        modalEmployeeRole.textContent = role;
        
        const selectedMonth = monthFilter.options[monthFilter.selectedIndex].text;
        const selectedYear = yearFilter.value;
        const monthVal = monthFilter.value;
        
        modalReportPeriod.textContent = `${selectedMonth} ${selectedYear}`;
        
        // Show modal temporarily as loading
        reportModalBody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 2rem; color: #a3a3a3;"><i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>Loading reports...</td></tr>`;
        
        viewReportModal.style.display = 'flex';
        // Force reflow
        viewReportModal.offsetHeight; 
        viewReportModal.classList.add('active');

        try {
            const resp = await fetch(`api/get_user_reports.php?user_id=${userId}&month=${monthVal}&year=${selectedYear}`);
            const result = await resp.json();
            
            if (result.success) {
                renderReports(result.data);
            } else {
                reportModalBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #ef4444;">${result.message || 'Failed to load data.'}</td></tr>`;
            }
        } catch (error) {
            console.error('Error fetching reports:', error);
            reportModalBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #ef4444;">Network error occurred.</td></tr>`;
        }
    };

    // Render reports to the table
    const renderReports = (data) => {
        if (!data || data.length === 0) {
            reportModalBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #737373; padding: 2rem;">No work reports found for this period.</td></tr>`;
            return;
        }

        let html = '';
        data.forEach((row, index) => {
            html += `
                <tr>
                    <td style="padding-left: 1rem; color: #737373;">${index + 1}</td>
                    <td style="font-weight: 500; color: #262626; white-space: nowrap;">${row.date}</td>
                    <td style="color: #52525b;">${row.day}</td>
                    <td style="color: #52525b; line-height: 1.5; padding-right: 1rem;">${row.report}</td>
                </tr>
            `;
        });
        reportModalBody.innerHTML = html;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    };

    // Close Modal Logic
    const closeModal = () => {
        viewReportModal.classList.remove('active');
        setTimeout(() => {
            viewReportModal.style.display = 'none';
        }, 200); // Wait for transition
    };

    closeReportModalBtn.addEventListener('click', closeModal);
    closeReportModalFooterBtn.addEventListener('click', closeModal);
    
    // Close on overlay click
    viewReportModal.addEventListener('click', (e) => {
        if (e.target === viewReportModal) {
            closeModal();
        }
    });

    // Attach click events to all "View" buttons
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const userId = btn.getAttribute('data-userid');
            const username = btn.getAttribute('data-username');
            const role = btn.getAttribute('data-role');
            openModal(userId, username, role);
        });
    });

    // Handle Excel Download
    document.querySelectorAll('.btn-excel').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const viewBtn = e.currentTarget.parentElement.querySelector('.btn-view');
            const userId = viewBtn.getAttribute('data-userid');
            
            const monthVal = monthFilter.value;
            const yearVal = yearFilter.value;
            
            const startDate = `${yearVal}-${monthVal.padStart(2, '0')}-01`;
            const lastDay = new Date(yearVal, monthVal, 0).getDate();
            const endDate = `${yearVal}-${monthVal.padStart(2, '0')}-${lastDay.toString().padStart(2, '0')}`;
            
            window.location.href = `../../export_user_work_reports.php?user_id=${userId}&start_date=${startDate}&end_date=${endDate}`;
        });
    });

    // Handle PDF Download
    document.querySelectorAll('.btn-pdf').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const viewBtn = e.currentTarget.parentElement.querySelector('.btn-view');
            const userId = viewBtn.getAttribute('data-userid');
            
            const monthVal = monthFilter.value;
            const yearVal = yearFilter.value;
            
            const startDate = `${yearVal}-${monthVal.padStart(2, '0')}-01`;
            const lastDay = new Date(yearVal, monthVal, 0).getDate();
            const endDate = `${yearVal}-${monthVal.padStart(2, '0')}-${lastDay.toString().padStart(2, '0')}`;
            
            window.location.href = `../../export_user_work_reports_pdf.php?user_id=${userId}&start_date=${startDate}&end_date=${endDate}`;
        });
    });

    // Filter bindings
    if (searchInput) {
        searchInput.addEventListener('input', applyEmployeeFilters);
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', applyEmployeeFilters);
    }

    // Initial filter pass
    applyEmployeeFilters();
});
