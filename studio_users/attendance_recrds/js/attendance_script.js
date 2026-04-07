document.addEventListener('DOMContentLoaded', () => {
    // Current date logic map
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();

    let selectedMonth = currentMonth;
    let selectedYear = currentYear;
    let selectedStatus = 'All Status';
    let currentAttendanceRecords = []; // For export functionality

    // Dropdown mappings
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    
    // UI elements update
    const monthDropdown = document.getElementById('monthDropdown');
    const yearDropdown = document.getElementById('yearDropdown');
    const filterDropdown = document.getElementById('filterDropdown');

    // Init Dropdowns
    monthDropdown.querySelector('.ws-dropdown-selected').textContent = months[currentMonth - 1];
    yearDropdown.querySelector('.ws-dropdown-selected').textContent = currentYear;

    function applyFilters() {
        const monthText = monthDropdown.querySelector('.ws-dropdown-selected').textContent.trim();
        const yearText = yearDropdown.querySelector('.ws-dropdown-selected').textContent.trim();
        
        // The filter toggle text doesn't update, so we must rely on the active li item
        const statusActiveNode = filterDropdown.querySelector('.ws-dropdown-item.active');
        const statusText = statusActiveNode ? statusActiveNode.textContent.trim() : 'All Status';

        selectedMonth = months.indexOf(monthText) + 1;
        selectedYear = parseInt(yearText);
        selectedStatus = statusText;

        fetchAttendanceData();
    }

    // Attach listener to dropdown items
    document.querySelectorAll('.ws-dropdown-item').forEach(item => {
        item.addEventListener('click', (e) => {
            // setTimeout to wait for the UI text to update by the inline script
            setTimeout(applyFilters, 50); 
        });
    });

    const formatTime = (timeStr) => {
        if (!timeStr) return '-';
        const [hours, minutes] = timeStr.split(':');
        const d = new Date();
        d.setHours(parseInt(hours), parseInt(minutes));
        return d.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
    };

    const fetchAttendanceData = async () => {
        try {
            const url = `api/fetch_attendance.php?month=${selectedMonth}&year=${selectedYear}&status=${encodeURIComponent(selectedStatus)}`;
            const response = await fetch(url);
            const res = await response.json();

            if (res.success) {
                currentAttendanceRecords = res.data; // Store for export
                updateKPIs(res.kpis);
                updateTable(res.data);
                updateCharts(res.chart_data, res.kpis);
            } else {
                console.error('Error fetching attendance:', res.message);
                // Handle unauthorized or other errors 
                if (res.message === 'Unauthorized') {
                    window.location.href = '../../login.php';
                }
            }
        } catch (err) {
            console.error('Fetch exception:', err);
        }
    };

    const updateKPIs = (kpis) => {
        document.querySelector('.ws-card-present .ws-kpi-value').textContent = kpis.present_days;
        document.querySelector('.ws-card-hours .ws-kpi-value').textContent = kpis.total_hours;
        document.querySelector('.ws-card-overtime .ws-kpi-value').textContent = kpis.overtime_hours;
        document.querySelector('.ws-card-rate .ws-kpi-value').textContent = kpis.attendance_rate + '%';
        document.querySelector('.ws-card-late .ws-kpi-value').textContent = kpis.late_punches;
        document.querySelector('.ws-card-leaves .ws-kpi-value').textContent = kpis.leaves_taken;
        document.querySelector('.ws-card-leaves .ws-kpi-badge').textContent = `Unpaid Leave: ${kpis.leaves_taken}`;
        
        // Modal Trigger bindings
        const lateBtn = document.getElementById('lateInfoBtn');
        const leaveBtn = document.getElementById('leaveInfoBtn');
        const overtimeBtn = document.getElementById('overtimeInfoBtn');
        
        const openDetailsModal = (title, itemsHtml) => {
            document.getElementById('detailsModalTitle').textContent = title;
            document.getElementById('detailsModalContent').innerHTML = itemsHtml;
            document.getElementById('detailsModal').classList.add('active');
        };

        if (lateBtn) {
            lateBtn.onclick = () => {
                let html = '';
                if (!kpis.late_details || kpis.late_details.length === 0) {
                    html = '<p class="ws-text-muted" style="text-align:center; padding: 20px;">No late punches recorded.</p>';
                } else {
                    html = `<table class="ws-data-table" style="width: 100%; text-align: left;">
                                <thead>
                                    <tr>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Date</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Shift Start</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Punched In</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    kpis.late_details.forEach(detail => {
                        html += `<tr>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.date}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.shift_start}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border); color: var(--ws-danger-text); font-weight: 500;">${detail.punch_in}</td>
                                 </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                openDetailsModal('Late Punch Details', html);
            };
        }

        if (leaveBtn) {
            leaveBtn.onclick = () => {
                let html = '';
                if (!kpis.leave_details || kpis.leave_details.length === 0) {
                    html = '<p class="ws-text-muted" style="text-align:center; padding: 20px;">No approved leaves taken.</p>';
                } else {
                    html = `<table class="ws-data-table" style="width: 100%; text-align: left;">
                                <thead>
                                    <tr>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Date(s)</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Type</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Duration</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    kpis.leave_details.forEach(detail => {
                        html += `<tr>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.date}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.type}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border); text-transform: capitalize;">${detail.duration.replace('_', ' ')}</td>
                                 </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                openDetailsModal('Leave Details', html);
            };
        }

        if (overtimeBtn) {
            overtimeBtn.onclick = () => {
                let html = '';
                if (!kpis.overtime_details || kpis.overtime_details.length === 0) {
                    html = '<p class="ws-text-muted" style="text-align:center; padding: 20px;">No overtime over 1.5 hours recorded.</p>';
                } else {
                    html = `<table class="ws-data-table" style="width: 100%; text-align: left;">
                                <thead>
                                    <tr>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Date</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Punch Out Time</th>
                                        <th style="padding: 10px; border-bottom: 1px solid var(--ws-border);">Overtime Earned</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    kpis.overtime_details.forEach(detail => {
                        html += `<tr>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.date}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border);">${detail.punch_out}</td>
                                    <td style="padding: 10px; border-bottom: 1px solid var(--ws-border); color: var(--ws-primary-color); font-weight: 500;">${detail.overtime_hours}</td>
                                 </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                openDetailsModal('Overtime Details', html);
            };
        }
    };

    const updateTable = (records) => {
        const tbody = document.querySelector('.ws-table-body');
        tbody.innerHTML = ''; // clear

        if (records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 20px;">No attendance records found for this period.</td></tr>`;
            return;
        }

        records.forEach(r => {
            const dateObj = new Date(r.date);
            const dateStr = `${String(dateObj.getDate()).padStart(2, '0')}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${dateObj.getFullYear()}`;
            
            let statusBadge = '';
            if (r.status === 'On Time') {
                statusBadge = `<span class="ws-status-badge" style="background:var(--ws-success-bg);color:var(--ws-success-text)">On Time</span>`;
            } else if (r.status === 'Late') {
                statusBadge = `<span class="ws-status-badge ws-badge-late"><i class="fa-solid fa-circle-xmark"></i> Late</span>`;
            } else if (r.status === 'Absent') {
                statusBadge = `<span class="ws-status-badge" style="background:#fef3f2;color:#b42318">Absent</span>`;
            } else if (r.status === 'Leave') {
                statusBadge = `<span class="ws-status-badge" style="background:#fffafa;color:#b54708">Leave</span>`;
            } else if (r.status === 'Holiday') {
                statusBadge = `<span class="ws-status-badge" style="background:#f3e8ff;color:#7e22ce"><i class="fa-solid fa-gift"></i> Holiday</span>`;
            } else {
                 statusBadge = `<span class="ws-status-badge" style="background:#f3f4f6;color:#374151">${r.status || 'N/A'}</span>`;
            }

            // ── Three-level photo fallback (mirrors attendance_visualizer.php) ────────
            // Root of connect/ is ../../ from this page's location.
            // Level 1: primary path (new records: 'uploads/attendance/filename.jpg')
            // Level 2: fallback path (old bare-filename records: prepend 'uploads/attendance/')
            // Level 3: placeholder (if both 404)
            const NO_PHOTO     = 'https://placehold.co/400x400/e9ecef/4b5563?text=No+Photo';
            const ROOT         = '../../';

            const inPrimary    = r.punch_in_photo_primary  ? (ROOT + r.punch_in_photo_primary)  : null;
            const inFallback   = r.punch_in_photo_fallback ? (ROOT + r.punch_in_photo_fallback) : null;
            const outPrimary   = r.punch_out_photo_primary  ? (ROOT + r.punch_out_photo_primary)  : null;
            const outFallback  = r.punch_out_photo_fallback ? (ROOT + r.punch_out_photo_fallback) : null;

            const inPhoto  = inPrimary  || NO_PHOTO;
            const outPhoto = outPrimary || NO_PHOTO;

            // Helper to build onerror chain identical to visualizer
            const makeOnerror = (fallbackSrc) => fallbackSrc
                ? `this.onerror=function(){this.src='${NO_PHOTO}';this.onerror=null;};this.src='${fallbackSrc}';`
                : `this.src='${NO_PHOTO}';this.onerror=null;`;
            
            let inAddressFull = r.address || r.location || 'Unknown location';
            let outAddressFull = r.punch_out_address || r.address || r.location || 'Unknown location';

            // Format truncated address
            const formatLoc = (addr) => {
                if (!addr) return 'Unknown Location';
                const words = addr.trim().split(/\s+/);
                if (words.length > 3) {
                    return words.slice(0, 3).join(' ') + '...';
                }
                return addr;
            };

            const inLocShort = formatLoc(inAddressFull);
            const outLocShort = formatLoc(outAddressFull);
            
            const tr = document.createElement('tr');
            tr.className = 'ws-table-row';
            
            // Re-use logic from UI
            let inCell = `<td class="ws-td ws-text-muted">-</td>`;
            if (r.punch_in) {
                 inCell = `<td class="ws-td">
                                <div class="ws-punch-cell">
                                    <span class="ws-time">${formatTime(r.punch_in)}</span>
                                    <div class="ws-punch-image-wrapper ws-tooltip-trigger ws-in-img" data-img="${inPhoto}" data-time="${formatTime(r.punch_in)}">
                                        <img src="${inPhoto}" alt="In" class="ws-punch-avatar" onerror="${makeOnerror(inFallback)}">
                                        <div class="ws-tooltip">View Punch In Selfie</div>
                                    </div>
                                </div>
                            </td>`;
            }
            
            let inLocCell = `<td class="ws-td ws-text-muted">-</td>`;
            if (r.punch_in) {
                inLocCell = `<td class="ws-td">
                                <div class="ws-location-text ws-tooltip-trigger ws-in-loc" data-title="Punch In Location" data-desc="${inAddressFull}">
                                    <i class="fa-solid fa-location-dot ws-location-icon"></i> <span class="ws-loc-clickable" style="cursor: pointer; color: var(--ws-primary-color); text-decoration: underline;" data-full-desc="${inAddressFull.replace(/"/g, '&quot;')}">${inLocShort}</span>
                                </div>
                            </td>`;
            }

            let outCell = `<td class="ws-td ws-text-muted">-</td>`;
            if (r.punch_out) {
                 outCell = `<td class="ws-td">
                                <div class="ws-punch-cell">
                                    <span class="ws-time">${formatTime(r.punch_out)}</span>
                                    <div class="ws-punch-image-wrapper ws-tooltip-trigger ws-out-img" data-img="${outPhoto}" data-time="${formatTime(r.punch_out)}">
                                        <img src="${outPhoto}" alt="Out" class="ws-punch-avatar" onerror="${makeOnerror(outFallback)}">
                                        <div class="ws-tooltip">View Punch Out Selfie</div>
                                    </div>
                                </div>
                            </td>`;
            }

            let outLocCell = `<td class="ws-td ws-text-muted">-</td>`;
            if (r.punch_out) {
                 outLocCell = `<td class="ws-td">
                                <div class="ws-location-text ws-tooltip-trigger ws-out-loc" data-title="Punch Out Location" data-desc="${outAddressFull}">
                                    <i class="fa-solid fa-location-dot ws-location-icon"></i> <span class="ws-loc-clickable" style="cursor: pointer; color: var(--ws-primary-color); text-decoration: underline;" data-full-desc="${outAddressFull.replace(/"/g, '&quot;')}">${outLocShort}</span>
                                </div>
                            </td>`;
            }
            
            let reportHtml = `<td class="ws-td ws-text-muted">-</td>`;
            if (r.work_report) {
                const shortRep = r.work_report.length > 50 ? r.work_report.substring(0, 50) + '...' : r.work_report;
                reportHtml = `<td class="ws-td ws-report-text" data-full-report="${r.work_report.replace(/"/g, '&quot;')}">${shortRep}</td>`;
            }

            tr.innerHTML = `
                <td class="ws-td ws-font-medium">${dateStr}</td>
                <td class="ws-td ws-text-subtle">${r.shift_time || '<span style="color:#94a3b8;font-style:italic;">No Shift</span>'}</td>
                ${inCell}
                ${inLocCell}
                ${outCell}
                ${outLocCell}
                <td class="ws-td ws-font-semibold">${r.working_hours || '-'}</td>
                <td class="ws-td ws-text-muted">${r.overtime_hours && r.overtime_hours !== '00:00' ? r.overtime_hours : '-'}</td>
                ${reportHtml}
                <td class="ws-td">${statusBadge}</td>
            `;

            tbody.appendChild(tr);
        });
        
        attachModalListeners();
    };

    const updateCharts = (chartData, kpis) => {
        // Bar chart container
        const barContainer = document.querySelector('.ws-bar-chart .ws-chart-mockup');
        barContainer.innerHTML = '';
        
        if (chartData.length === 0) {
            barContainer.innerHTML = '<span class="ws-text-muted" style="margin:auto;">No attendance data for chart</span>';
            return;
        }

        // Max possible hours, e.g., 14 for scaling
        const maxHours = 14; 

        chartData.forEach(cd => {
            // Apply a minimum height of 2% so even 0 hours (e.g. missing punch-out) shows a tiny bar
            const h = Math.max(Math.min((cd.hours / maxHours) * 100, 100), 2);
            const html = `
                <div class="ws-bar-wrapper" title="${cd.hours.toFixed(1)} Hours">
                    <div class="ws-bar" style="height: ${h}%;"></div>
                    <span class="ws-bar-label">${cd.date}</span>
                </div>
            `;
            barContainer.insertAdjacentHTML('beforeend', html);
        });

        // For donut chart we need numeric totals
        const tHours = parseInt(kpis.total_hours.split(':')[0]) + (parseInt(kpis.total_hours.split(':')[1])/60);
        const oHours = parseInt(kpis.overtime_hours.split(':')[0]) + (parseInt(kpis.overtime_hours.split(':')[1])/60);
        
        const total = tHours; 
        const regular = Math.max(0, tHours - oHours);
        
        const pctOvertime = total > 0 ? (oHours / total) * 100 : 0;
        const colorStop = Math.max(0, 100 - pctOvertime);
        
        const donut = document.querySelector('.ws-donut-ring');
        if (!donut) return;

        // Using explicit hex colors to ensure cross-browser inline style compatibility
        const colorPrimary = '#fd7e14'; // var(--ws-chart-bar)
        const colorDanger = '#b42318';  // var(--ws-danger-text)
        
        if (total === 0) {
             // Mockup a transparent empty circle so it doesn't look structurally broken
             donut.style.background = `conic-gradient(rgba(253, 126, 20, 0.1) 0% 100%)`;
        } else {
             donut.style.background = `conic-gradient(${colorPrimary} 0% ${colorStop}%, ${colorDanger} ${colorStop}% 100%)`;
        }
    };

    const attachModalListeners = () => {
        // Redefined for dynamic elements
        document.querySelectorAll('.ws-in-img').forEach(el => {
            el.addEventListener('click', function() {
                const tr = this.closest('tr');
                const locEl = tr.querySelector('.ws-in-loc');
                // Use the actual resolved src from the thumbnail img (already corrected by onerror chain)
                // This ensures the modal gets the URL that is KNOWN to work, not the failing primary URL
                const thumbImg = this.querySelector('img');
                const resolvedSrc = thumbImg ? thumbImg.src : this.dataset.img;
                const fallbackSrc = this.dataset.imgFallback || null;
                openPunchModal('Punch In Selfie', this.dataset.time, resolvedSrc, fallbackSrc, {
                    title: locEl ? locEl.dataset.title : 'Unknown',
                    desc: locEl ? locEl.dataset.desc : 'No data'
                });
            });
        });

        document.querySelectorAll('.ws-out-img').forEach(el => {
            el.addEventListener('click', function() {
                const tr = this.closest('tr');
                const locEl = tr.querySelector('.ws-out-loc');
                const thumbImg = this.querySelector('img');
                const resolvedSrc = thumbImg ? thumbImg.src : this.dataset.img;
                const fallbackSrc = this.dataset.imgFallback || null;
                openPunchModal('Punch Out Selfie', this.dataset.time, resolvedSrc, fallbackSrc, {
                    title: locEl ? locEl.dataset.title : 'Unknown',
                    desc: locEl ? locEl.dataset.desc : 'No data'
                });
            });
        });

        const reportModal = document.getElementById('reportModal');
        document.querySelectorAll('.ws-report-text').forEach(cell => {
            cell.addEventListener('click', function() {
                const dateCell = this.closest('.ws-table-row').querySelector('.ws-td.ws-font-medium');
                const date = dateCell ? dateCell.textContent : '';
                const fullReport = this.getAttribute('data-full-report');
                
                document.getElementById('reportModalDate').textContent = date;
                document.getElementById('reportModalContent').textContent = fullReport;
                
                reportModal.classList.add('active');
            });
        });

        const detailsModal = document.getElementById('detailsModal');
        document.querySelectorAll('.ws-loc-clickable').forEach(cell => {
            cell.addEventListener('click', function(e) {
                // Prevent bubbling to other cell events if any
                e.stopPropagation();
                
                const fullDesc = this.getAttribute('data-full-desc');
                const title = "Location Details";
                
                document.getElementById('detailsModalTitle').textContent = title;
                document.getElementById('detailsModalContent').innerHTML = `<p style="padding: 15px; font-size: 14px; line-height: 1.5;">${fullDesc}</p>`;
                
                detailsModal.classList.add('active');
            });
        });
    };

    // --- Export Functionality ---
    function getExportData() {
        return currentAttendanceRecords.map(r => {
            const dateObj = new Date(r.date);
            const dateStr = `${String(dateObj.getDate()).padStart(2, '0')}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${dateObj.getFullYear()}`;
            
            return {
                "Date": dateStr,
                "Shift": r.shift_time || '09:00 AM - 06:00 PM',
                "Punch In": r.punch_in ? formatTime(r.punch_in) : '-',
                "Punch In Location": r.address || r.location || '-',
                "Punch Out": r.punch_out ? formatTime(r.punch_out) : '-',
                "Punch Out Location": r.punch_out_address || r.address || r.location || '-',
                "Working Hours": r.working_hours || '-',
                "Overtime": r.overtime_hours && r.overtime_hours !== '00:00' ? r.overtime_hours : '-',
                "Work Report": r.work_report || '-',
                "Status": r.status || 'N/A'
            };
        });
    }

    const exportToExcel = () => {
        if (!currentAttendanceRecords || currentAttendanceRecords.length === 0) {
            alert('No data available to export.');
            return;
        }
        const data = getExportData();
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Attendance Report");
        
        // Auto-fit columns
        const colWidths = Object.keys(data[0]).map(key => ({
            wch: Math.max(key.length, ...data.map(obj => (obj[key] ? obj[key].toString().length : 10))) + 2
        }));
        ws['!cols'] = colWidths;

        const filename = `Attendance_Report_${months[selectedMonth-1]}_${selectedYear}.xlsx`;
        XLSX.writeFile(wb, filename);
    };

    const exportToPdf = () => {
        if (!currentAttendanceRecords || currentAttendanceRecords.length === 0) {
            alert('No data available to export.');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); // Landscape
        
        const data = getExportData();
        const headers = [Object.keys(data[0])];
        const body = data.map(obj => Object.values(obj));

        doc.setFont("helvetica", "bold");
        doc.setFontSize(22);
        doc.setTextColor(253, 126, 20); // Primary orange
        doc.text("ATTENDANCE HISTORY REPORT", 14, 20);
        
        doc.setFontSize(11);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(100);
        doc.text(`Period: ${months[selectedMonth-1]} ${selectedYear}`, 14, 28);
        doc.text(`Filter: ${selectedStatus}`, 14, 34);
        doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 40);

        doc.autoTable({
            head: headers,
            body: body,
            startY: 45,
            theme: 'grid',
            headStyles: { fillColor: [253, 126, 20], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
            styles: { fontSize: 8, cellPadding: 2, font: 'helvetica', overflow: 'linebreak' },
            alternateRowStyles: { fillColor: [250, 250, 250] },
            columnStyles: {
                0: { cellWidth: 20 },
                1: { cellWidth: 25 },
                8: { cellWidth: 60 }, // Work Report
                9: { halign: 'center' }
            }
        });

        const filename = `Attendance_Report_${months[selectedMonth-1]}_${selectedYear}.pdf`;
        doc.save(filename);
    };

    const excelBtn = document.getElementById('exportExcelBtn');
    const pdfBtn = document.getElementById('exportPdfBtn');
    if (excelBtn) excelBtn.addEventListener('click', exportToExcel);
    if (pdfBtn) pdfBtn.addEventListener('click', exportToPdf);

    // Initial Load
    fetchAttendanceData();
});
