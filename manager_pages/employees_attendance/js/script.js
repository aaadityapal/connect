window.currentUserPermissions = window.currentUserPermissions || {
    can_approve_attendance: 0,
    can_reject_attendance: 0,
    can_edit_attendance: 0
};

/* ── Global profile-picture helpers ─────────────────────────────────── */

// Mirrors PHP profilePictureUrl(): strips leading ./ or ../ then prepends ../../
window._resolveProfilePic = function(pic) {
    if (!pic || pic === 'null' || pic === 'default.jpg' || pic === 'default.png') return '';
    let clean = pic.trim().replace(/^(\.\/|\.\.\/)+/, '').replace(/^\//, '');
    if (!clean) return '';
    return '../../' + clean;
};

// Called via onerror="_avatarError(this)" — replaces broken img with icon SVG
window._avatarError = function(img) {
    const wrap = img.parentElement;
    if (!wrap) return;
    const s = wrap.offsetWidth || 36;
    const ic = Math.round(s * 0.55);
    wrap.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e0e7ff;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${ic}" height="${ic}" viewBox="0 0 24 24"
             fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg></div>`;
};

/* ─────────────────────────────────────────────────────────────────────── */

document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const userFilter = document.getElementById("userFilter");
    const fromDate = document.getElementById("fromDate");
    const toDate = document.getElementById("toDate");
    const attendanceTableBody = document.getElementById("attendanceTableBody");
    
    // Stats Elements
    const statPresent    = document.getElementById("statPresent");
    const statPresentSub = document.getElementById("statPresentSub");
    const statOnLeave    = document.getElementById("statOnLeave");
    const statLate       = document.getElementById("statLate");
    const statGeofence   = document.getElementById("statGeofence");

    let currentData = [];
    let currentUserPermissions = window.currentUserPermissions;

    // Fetch data from API
    async function loadAttendanceData() {
        attendanceTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 3rem; color: #64748b;"><i class="lucide-loader animate-spin" style="width: 24px; height: 24px; display:inline-block; margin-bottom: 5px;"></i><br/> Fetching data...</td></tr>`;
        if (window.lucide) lucide.createIcons();
        
        try {
            const formData = new URLSearchParams();
            if(fromDate && fromDate.value) formData.append('from', fromDate.value);
            if(toDate && toDate.value) formData.append('to', toDate.value);
            if(userFilter && userFilter.value) formData.append('user_id', userFilter.value);
            
            const res = await fetch(`api/get_attendance.php?${formData.toString()}`);
            const payload = await res.json();
            
            if (payload.success) {
                currentData = payload.data;
                currentUserPermissions = payload.current_user_permissions || currentUserPermissions;
                window.currentUserPermissions = currentUserPermissions;
                updateStats(payload.stats, payload.total_active_users || 0);
                renderTable();
            } else {
                attendanceTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 2rem; color: #ef4444;">Error: ${payload.message}</td></tr>`;
            }
        } catch (e) {
            attendanceTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 2rem; color: #ef4444;">Failed to communicate with API.</td></tr>`;
            console.error(e);
        }
    }

    function updateStats(stats, totalActiveUsers) {
        const present = (stats['Present'] || 0);
        const onLeave = (stats['On Leave'] || 0);
        const late    = (stats['Late'] || 0);
        const geo     = (stats['Geofence Issues'] || 0);

        // Present card: show "X / Y" where Y = total active users
        if (statPresent) {
            if (totalActiveUsers && totalActiveUsers > 0) {
                statPresent.textContent = `${present} / ${totalActiveUsers}`;
            } else {
                statPresent.textContent = present;
            }
        }
        if (statPresentSub && totalActiveUsers > 0) {
            const pct = Math.round((present / totalActiveUsers) * 100);
            statPresentSub.textContent = `${pct}% workforce present`;
        }

        if (statOnLeave)  statOnLeave.textContent  = onLeave;
        if (statLate)     statLate.textContent      = late;
        if (statGeofence) statGeofence.textContent  = geo;
    }

    const ROWS_PER_PAGE = 35;
    let currentPage = 1;

    function renderTable() {
        const query = searchInput ? searchInput.value.toLowerCase() : "";
        const statusType = statusFilter ? statusFilter.value.toLowerCase() : "";

        attendanceTableBody.innerHTML = "";

        const filteredData = currentData.filter(user => {
            const name = (user.username || "").toLowerCase();
            const code = (user.unique_id || "").toLowerCase();
            const rowStatus = (user.attendance_status || "").toLowerCase();
            const matchQuery = name.includes(query) || code.includes(query);
            const matchStatus = statusType === "" || rowStatus.includes(statusType);
            return matchQuery && matchStatus;
        });

        // Always store FULL filtered set — modals use absolute index into this array
        window.latestAttendanceData = filteredData;

        if (filteredData.length === 0) {
            attendanceTableBody.innerHTML = `<tr>
                <td colspan="14" style="text-align: center; padding: 2rem; color: #94a3b8; font-weight: 500;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                        <i data-lucide="frown" style="width: 24px; height: 24px; opacity: 0.5;"></i>
                        No employee data found matching criteria.
                    </div>
                </td>
            </tr>`;
            if (window.lucide) lucide.createIcons();
            renderPagination(0, 1);
            return;
        }

        // Pagination calculations
        const totalRecords = filteredData.length;
        const totalPages = Math.max(1, Math.ceil(totalRecords / ROWS_PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * ROWS_PER_PAGE;
        const pageData = filteredData.slice(startIdx, startIdx + ROWS_PER_PAGE);

        // ── Profile picture helper (uses global _resolveProfilePic + _avatarError)
        const renderAvatar = (user, size = 36) => {
            const src = window._resolveProfilePic(user.profile_picture);
            const ic  = Math.round(size * 0.55);
            if (src) {
                return `<div style="width:${size}px;height:${size}px;border-radius:9px;overflow:hidden;flex-shrink:0;border:1.5px solid #e2e8f0;background:#f1f5f9;">
                    <img src="${src}" alt="${user.username || ''}" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="_avatarError(this)">
                </div>`;
            }
            return `<div style="width:${size}px;height:${size}px;border-radius:9px;overflow:hidden;flex-shrink:0;background:#e0e7ff;display:flex;align-items:center;justify-content:center;border:1.5px solid #c7d2fe;">
                <svg xmlns="http://www.w3.org/2000/svg" width="${ic}" height="${ic}" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
            </div>`;
        };
        // ───────────────────────────────────────────────────────────────────

        pageData.forEach((user, localIndex) => {
            const absoluteIndex = startIdx + localIndex; // Real index in window.latestAttendanceData
            const displayRowNum = absoluteIndex + 1;     // Global S.No across all pages

            const status = user.attendance_status;
            let statusClass = 'status-leave';
            let iconName = 'calendar-minus';
            const hasOutsideIn = !!(user.punch_in_outside_reason && user.punch_in_outside_reason.trim().length > 0);
            const hasOutsideOut = !!(user.punch_out_outside_reason && user.punch_out_outside_reason.trim().length > 0);
            const hasOutsideGeofence = hasOutsideIn || hasOutsideOut;
            const canAnyDecision = (Number(currentUserPermissions.can_approve_attendance) === 1 || Number(currentUserPermissions.can_reject_attendance) === 1);
            const canDecision = !!user.attendance_id && hasOutsideGeofence && canAnyDecision;
            const canEdit = Number(currentUserPermissions.can_edit_attendance) === 1;

            // Determine display label for status — show leave type name if on leave
            let statusLabel = status;
            if (status === 'On Leave' && user.leave_type_name) {
                statusLabel = user.leave_type_name;
            }

            if (status === 'On Time') {
                statusClass = 'status-present'; iconName = 'check-circle-2';
            } else if (status === 'Absent') {
                statusClass = 'status-absent'; iconName = 'x-circle';
            } else if (status === 'Late') {
                statusClass = 'status-late'; iconName = 'clock-4';
            }

            const renderPhoto = (photoStr) => {
                if(photoStr && photoStr !== '-' && photoStr !== 'null') {
                    const fileName = photoStr.includes('/') ? photoStr.split('/').pop() : photoStr;
                    const picUrl = `../../uploads/attendance/${fileName}`;
                    return `<a href="${picUrl}" target="_blank" title="Click to open picture address: ${picUrl}">
                                <img src="${picUrl}" alt="photo" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; display: block; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            </a>`;
                }
                return '<span style="color: #94a3b8; font-size: 0.75rem; display:block; text-align:center;">No Photo</span>';
            };

            const renderLocation = (locStr, lat, lng) => {
                const safeLabel = (locStr || '-').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                if (locStr && locStr !== '-') {
                    const passLat = lat && lat !== '0' && lat !== 'null' ? lat : 'null';
                    const passLng = lng && lng !== '0' && lng !== 'null' ? lng : 'null';
                    return `<div onclick="openLocationModal(${passLat}, ${passLng}, '${safeLabel}')" style="font-size:0.75rem; color:#3b82f6; text-decoration:underline; cursor:pointer; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${safeLabel}">${locStr}</div>`;
                }
                return `<div style="font-size:0.75rem; color:#94a3b8; max-width: 160px;">-</div>`;
            };

            const renderReport = (reportStr) => {
                const label = reportStr && reportStr !== '-' && reportStr.trim() !== '' ? reportStr : null;
                if (!label) {
                    return `<div style="font-size:0.75rem; color:#94a3b8; width: 100%; text-align:center;">-</div>`;
                }
                const safeEncoded = encodeURIComponent(label).replace(/'/g, "%27");
                return `<div onclick="openReportModal('${safeEncoded}')" style="font-size:0.75rem; color:#6366f1; font-weight: 500; cursor:pointer; display: flex; align-items: center; justify-content: center; gap: 4px; padding: 0.35rem 0.6rem; background: #e0e7ff; border-radius: 6px; width: fit-content; margin: 0 auto; transition: 0.2s;" onmouseover="this.style.background='#c7d2fe'" onmouseout="this.style.background='#e0e7ff'">
                            <i data-lucide="file-text" style="width:14px;height:14px;"></i> View
                        </div>`;
            };

            const renderGeofence = (reasonStr, timeStr, approvalStatus) => {
                if (!timeStr || timeStr === '--:--' || timeStr === '') {
                    return `<div style="text-align:center;color:#94a3b8;font-size:0.75rem;">-</div>`;
                }
                if (reasonStr && reasonStr.trim().length > 0) {
                    const isRejected = (approvalStatus || '').toLowerCase() === 'rejected';
                    if (isRejected) {
                        return `<div style="display:flex; align-items:center; justify-content:center; gap:4px; font-size:0.75rem; font-weight:600; color:#ef4444; background:#fef2f2; padding:0.25rem 0.5rem; border-radius:4px; width: fit-content; margin: 0 auto;" title="Outside geofence request rejected">
                                    <i data-lucide="x-circle" style="width:12px;height:12px;"></i> Rejected
                                </div>`;
                    }
                    const safeEncoded = encodeURIComponent("Geofence Override Reason:\n\n" + reasonStr).replace(/'/g, "%27");
                    return `<div onclick="openReportModal('${safeEncoded}')" style="display:flex; align-items:center; justify-content:center; gap:4px; font-size:0.75rem; font-weight:500; color:#ef4444; background:#fef2f2; padding:0.25rem 0.5rem; border-radius:4px; cursor:pointer; width: fit-content; margin: 0 auto; transition:0.2s" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'" title="Click to view reason">
                                <i data-lucide="alert-triangle" style="width:12px;height:12px;"></i> Outside
                            </div>`;
                } else {
                    return `<div style="display:flex; align-items:center; justify-content:center; gap:4px; font-size:0.75rem; font-weight:500; color:#10b981;">
                                <i data-lucide="check-circle-2" style="width:14px;height:14px;"></i> Inside
                            </div>`;
                }
            };

            const tr = document.createElement("tr");
            tr.className = "attendance-row";
            tr.innerHTML = `
                <td style="padding-right: 0.5rem; text-align: center;"><strong style="color: #64748b; font-size: 0.85rem;">${displayRowNum}</strong></td>
                <td style="padding-left: 0.5rem;"><span style="font-size:0.8rem; font-weight:500; color:#334155; white-space:nowrap;">${user.attendance_date}</span></td>
                <td>
                    <div class="user-cell" style="display: flex; align-items: center; gap: 0.75rem;">
                        ${renderAvatar(user, 36)}
                        <div class="user-name" style="font-weight: 600; color: #1e293b;">${user.username}</div>
                    </div>
                </td>
                <td style="text-align: center;"><span style="font-size:0.8rem; font-weight:600; color:#334155;">${user.check_in}</span></td>
                <td>${renderLocation(user.punch_in_location, user.latitude, user.longitude)}</td>
                <td style="text-align: center;">${renderGeofence(user.punch_in_outside_reason, user.check_in, user.approval_status)}</td>
                <td>${renderPhoto(user.punch_in_photo)}</td>
                <td style="text-align: center;"><span style="font-size:0.8rem; font-weight:600; color:#334155;">${user.check_out}</span></td>
                <td>${renderLocation(user.punch_out_location, null, null)}</td>
                <td style="text-align: center;">${renderGeofence(user.punch_out_outside_reason, user.check_out, user.approval_status)}</td>
                <td>${renderPhoto(user.punch_out_photo)}</td>
                <td>${renderReport(user.work_report)}</td>
                <td style="text-align: center;">
                    <span class="status-badge ${statusClass}" title="${status === 'On Leave' ? 'Leave Type: ' + statusLabel : ''}">
                        <i data-lucide="${iconName}" style="width: 12px; height: 12px;"></i>
                        ${statusLabel}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.15rem; justify-content: center;">
                        <button onclick="openViewModal(${absoluteIndex})" class="action-btn" title="View Details" style="background: none; border: none; cursor: pointer; color: #3b82f6; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='none'">
                            <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button onclick="openEditModal(${absoluteIndex})" class="action-btn" title="Edit Record" ${canEdit ? '' : 'disabled'} style="background: none; border: none; cursor: ${canEdit ? 'pointer' : 'not-allowed'}; color: ${canEdit ? '#8b5cf6' : '#94a3b8'}; opacity:${canEdit ? '1' : '0.55'}; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="if(!this.disabled){this.style.background='#f3e8ff'}" onmouseout="this.style.background='none'">
                            <i data-lucide="edit-3" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button onclick="openGeofenceDecisionModal(${absoluteIndex}, 'approve')" class="action-btn" title="Review Geofence" ${canDecision ? '' : 'disabled'} style="background: none; border: none; cursor: ${canDecision ? 'pointer' : 'not-allowed'}; color: ${canDecision ? '#6366f1' : '#94a3b8'}; opacity:${canDecision ? '1' : '0.55'}; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="if(!this.disabled){this.style.background='#eef2ff'}" onmouseout="this.style.background='none'">
                            <i data-lucide="circle-ellipsis" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                </td>
            `;
            attendanceTableBody.appendChild(tr);
        });

        if (window.lucide) lucide.createIcons();
        renderPagination(totalRecords, totalPages);
    }

    function renderPagination(totalRecords, totalPages) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;

        if (totalPages <= 1) {
            // Still show the record count even when no paging needed
            if (totalRecords > 0) {
                container.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:flex-end; padding: 0.6rem 1.25rem; background:#fff; border:1px solid #e2e8f0; border-radius:12px; font-size:0.78rem; color:#64748b;">
                        Showing <strong style="margin:0 4px; color:#334155;">${totalRecords}</strong> record${totalRecords !== 1 ? 's' : ''}
                    </div>`;
            } else {
                container.innerHTML = '';
            }
            return;
        }

        const startRecord = (currentPage - 1) * ROWS_PER_PAGE + 1;
        const endRecord = Math.min(currentPage * ROWS_PER_PAGE, totalRecords);

        // Build page number buttons (show window of 5 around current)
        const maxVisible = 5;
        let pageStart = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let pageEnd = Math.min(totalPages, pageStart + maxVisible - 1);
        if (pageEnd - pageStart < maxVisible - 1) {
            pageStart = Math.max(1, pageEnd - maxVisible + 1);
        }

        const btnBase = `style="min-width:32px; height:32px; border-radius:7px; border:1px solid #e2e8f0; background:#fff; font-size:0.8rem; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all 0.15s; color:#475569; padding: 0 6px;"`;
        const btnActive = `style="min-width:32px; height:32px; border-radius:7px; border:none; background: linear-gradient(135deg,#818cf8,#6366f1); font-size:0.8rem; font-weight:600; cursor:default; display:inline-flex; align-items:center; justify-content:center; color:#fff; padding:0 6px; box-shadow:0 2px 6px rgba(99,102,241,0.3);"`;
        const btnDisabled = `style="min-width:32px; height:32px; border-radius:7px; border:1px solid #f1f5f9; background:#f8fafc; font-size:0.8rem; cursor:not-allowed; display:inline-flex; align-items:center; justify-content:center; color:#cbd5e1; padding:0 6px;"`;

        let pageBtns = '';

        // First + Prev
        if (currentPage > 1) {
            pageBtns += `<button ${btnBase} onclick="window._goToPage(1)" title="First page" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">«</button>`;
            pageBtns += `<button ${btnBase} onclick="window._goToPage(${currentPage - 1})" title="Previous page" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">‹</button>`;
        } else {
            pageBtns += `<button ${btnDisabled} disabled>«</button>`;
            pageBtns += `<button ${btnDisabled} disabled>‹</button>`;
        }

        // Ellipsis before
        if (pageStart > 1) {
            pageBtns += `<span style="color:#94a3b8; font-size:0.85rem; padding:0 2px;">…</span>`;
        }

        // Page number buttons
        for (let p = pageStart; p <= pageEnd; p++) {
            if (p === currentPage) {
                pageBtns += `<button ${btnActive}>${p}</button>`;
            } else {
                pageBtns += `<button ${btnBase} onclick="window._goToPage(${p})" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">${p}</button>`;
            }
        }

        // Ellipsis after
        if (pageEnd < totalPages) {
            pageBtns += `<span style="color:#94a3b8; font-size:0.85rem; padding:0 2px;">…</span>`;
        }

        // Next + Last
        if (currentPage < totalPages) {
            pageBtns += `<button ${btnBase} onclick="window._goToPage(${currentPage + 1})" title="Next page" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">›</button>`;
            pageBtns += `<button ${btnBase} onclick="window._goToPage(${totalPages})" title="Last page" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">»</button>`;
        } else {
            pageBtns += `<button ${btnDisabled} disabled>›</button>`;
            pageBtns += `<button ${btnDisabled} disabled>»</button>`;
        }

        container.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; padding: 0.75rem 1.25rem; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
                <div style="font-size:0.78rem; color:#64748b;">
                    Showing <strong style="color:#334155;">${startRecord}–${endRecord}</strong> of <strong style="color:#334155;">${totalRecords}</strong> records
                    <span style="color:#cbd5e1; margin:0 6px;">|</span>
                    Page <strong style="color:#334155;">${currentPage}</strong> of <strong style="color:#334155;">${totalPages}</strong>
                </div>
                <div style="display:flex; align-items:center; gap:0.3rem;">
                    ${pageBtns}
                </div>
            </div>`;
    }

    // Page navigation — exposed on window so inline onclick handlers work
    window._goToPage = function(page) {
        currentPage = page;
        renderTable();
        // Smooth scroll to top of table
        const tableEl = document.querySelector('.table-container');
        if (tableEl) tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Attach Listeners — reset to page 1 on any filter change
    if (searchInput) searchInput.addEventListener("input", () => { currentPage = 1; renderTable(); });
    if (statusFilter) statusFilter.addEventListener("change", () => { currentPage = 1; renderTable(); });

    // Core parameters that require API refetch — also reset page
    if (fromDate) fromDate.addEventListener("change", () => { currentPage = 1; loadAttendanceData(); });
    if (toDate) toDate.addEventListener("change", () => { currentPage = 1; loadAttendanceData(); });
    if (userFilter) userFilter.addEventListener("change", () => { currentPage = 1; loadAttendanceData(); });


    // Initial load
    loadAttendanceData();
});

// Expose modal dispatchers to the global window context
window.openLocationModal = function(lat, lng, addressText) {
    const modal = document.getElementById('locationModal');
    const mapContainer = document.getElementById('modalMapContainer');
    const footerText = document.getElementById('modalAddressText');

    footerText.innerHTML = `<strong>Address Log:</strong><br/>${addressText}`;
    
    // Fallback: If lat & lang are missing, we query Google Maps purely by the string address
    let mapQuery = encodeURIComponent(addressText);
    if (lat && lng && lat != 'null' && lng != 'null' && lat != 0 && lng != 0) {
        mapQuery = `${lat},${lng}`;
    }

    // Embed iFrame
    if (mapQuery && mapQuery !== '-') {
        mapContainer.innerHTML = `<iframe src="https://maps.google.com/maps?q=${mapQuery}&t=&z=15&ie=UTF8&iwloc=&output=embed" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>`;
    } else {
        mapContainer.innerHTML = '<div style="display:flex;height:100%;align-items:center;justify-content:center;color:#64748b;font-weight:500;">No precise coordinate mapping available</div>';
    }

    modal.style.display = 'flex';
};

window.closeLocationModal = function() {
    document.getElementById('locationModal').style.display = 'none';
    document.getElementById('modalMapContainer').innerHTML = '';
};

window.openViewModal = function(index) {
    const user = window.latestAttendanceData[index];
    if (!user) return;

    // Header Mapping
    document.getElementById('detailUserName').textContent = user.username;

    // Avatar: profile picture or icon fallback
    const avatarEl = document.getElementById('detailUserAvatar');
    const detSrc   = window._resolveProfilePic(user.profile_picture);
    avatarEl.style.padding  = '0';
    avatarEl.style.overflow = 'hidden';
    if (detSrc) {
        avatarEl.innerHTML = `<img src="${detSrc}" alt="${user.username}" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;" onerror="_avatarError(this)">`;
    } else {
        avatarEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>`;
    }
    document.getElementById('detailUserDate').textContent = user.attendance_date;
    document.getElementById('detailUserShift').textContent = `${user.shift_name} (${user.shift_start} - ${user.shift_end})`;
    
    let statusClass = '';
    let statusIcon = '';
    const statusLower = user.attendance_status.toLowerCase();
    
    if (statusLower === 'present' || statusLower === 'on time' || statusLower === 'late') {
        const inTime = user.check_in !== '--:--' && user.check_in ? user.check_in : '23:59';
        
        let isLate = false;
        if (inTime !== '23:59' && user.shift_start) {
            const shiftStartHour = parseInt(user.shift_start.split(':')[0], 10);
            const shiftStartMin = parseInt(user.shift_start.split(':')[1], 10);
            
            // Calculate total minutes since midnight buffer for precise mathematical check
            const graceThresholdMinutes = (shiftStartHour * 60) + shiftStartMin + 15;
            
            const inHour = parseInt(inTime.split(':')[0], 10);
            const inMin = parseInt(inTime.split(':')[1], 10);
            const punchInMinutes = (inHour * 60) + inMin;
            
            isLate = punchInMinutes > graceThresholdMinutes;
        }
        
        // If API returned "Late", override purely matching backend calculations
        if (statusLower === 'late') isLate = true;
        
        statusClass = isLate ? 'status-late' : 'status-present';
        statusIcon = isLate ? 'clock' : 'check-circle-2';
    } else if (statusLower === 'absent') {
        statusClass = 'status-absent';
        statusIcon = 'x-circle';
    } else if (statusLower === 'on leave') {
        statusClass = 'status-leave';
        statusIcon = 'calendar-minus';
    } else {
        statusClass = 'status-absent';
        statusIcon = 'help-circle';
    }
    
    // Show the actual leave type name if available
    let displayStat = statusLower.charAt(0).toUpperCase() + statusLower.slice(1);
    if (statusClass === 'status-late') displayStat = 'Late';
    if (displayStat === 'Present') displayStat = 'On Time';
    if (statusLower === 'on leave' && user.leave_type_name) displayStat = user.leave_type_name;
    
    document.getElementById('detailUserStatus').innerHTML = `<span class="status-badge ${statusClass}" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;">
                        <i data-lucide="${statusIcon}" style="width: 14px; height: 14px;"></i>
                        ${displayStat}
                    </span>`;
                    
    // Timeline Mapping
    document.getElementById('detailPunchIn').textContent = user.check_in || '--:--';
    document.getElementById('detailPunchOut').textContent = user.check_out || '--:--';
    document.getElementById('detailPunchInLocation').textContent = user.punch_in_location || 'No location logged';
    document.getElementById('detailPunchOutLocation').textContent = user.punch_out_location || 'No location logged';
    
    // Photo Engine mapping
    const parsePhotoHTML = (photoStr) => {
        if (!photoStr || photoStr === '-' || photoStr === 'null') {
            return '<span style="font-size:0.75rem; color:#94a3b8;">No photo attached</span>';
        }
        
        const fileName = photoStr.includes('/') ? photoStr.split('/').pop() : photoStr;
        const picUrl = `../../uploads/attendance/${fileName}`;
        
        return `<a href="${picUrl}" target="_blank" title="Click to view full photo" style="display: block; width: fit-content; text-decoration: none;">
                    <img src="${picUrl}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                </a>`;
    };

    document.getElementById('detailPunchInPhoto').innerHTML = parsePhotoHTML(user.punch_in_photo);
    document.getElementById('detailPunchOutPhoto').innerHTML = parsePhotoHTML(user.punch_out_photo);
        
    // Geofence parsing logic
    const applyGeofenceTemplate = (reasonStr, approvalStatus) => {
        if (reasonStr && reasonStr.trim().length > 0) {
            const isRejected = (approvalStatus || '').toLowerCase() === 'rejected';
            if (isRejected) {
                return `<div style="display:inline-flex; align-items:center; gap:4px; font-size:0.75rem; font-weight:600; color:#ef4444; background:#fef2f2; padding:0.35rem 0.6rem; border-radius:6px; margin-top: 0.5rem;"><i data-lucide="x-circle" style="width:12px;height:12px;"></i> Geofence Request Rejected</div>`;
            }
            return `<div style="display:inline-flex; align-items:center; gap:4px; font-size:0.75rem; font-weight:600; color:#ef4444; background:#fef2f2; padding:0.35rem 0.6rem; border-radius:6px; margin-top: 0.5rem;"><i data-lucide="alert-triangle" style="width:12px;height:12px;"></i> <span>Outside Perimeter:<br/><span style="font-weight:400; font-size:0.75rem; color:#b91c1c; margin-top: 2px; display: block;">"${reasonStr}"</span></span></div>`;
        } else {
            return `<div style="display:inline-flex; align-items:center; gap:4px; font-size:0.75rem; font-weight:600; color:#10b981; background:#ecfdf5; padding:0.35rem 0.6rem; border-radius:6px; margin-top: 0.5rem;"><i data-lucide="check-circle-2" style="width:12px;height:12px;"></i> Inside Geofence Bounds</div>`;
        }
    };
    
    document.getElementById('detailPunchInGeofence').innerHTML = user.check_in && user.check_in !== '--:--' ? applyGeofenceTemplate(user.punch_in_outside_reason, user.approval_status) : '';
    document.getElementById('detailPunchOutGeofence').innerHTML = user.check_out && user.check_out !== '--:--' ? applyGeofenceTemplate(user.punch_out_outside_reason, user.approval_status) : '';
    
    // Work Report Engine
    const wrWrap = document.getElementById('detailWorkReportWrapper');
    if (user.work_report && user.work_report !== '-' && user.work_report.trim().length > 0) {
        document.getElementById('detailWorkReport').textContent = user.work_report;
        wrWrap.style.display = 'block';
    } else {
        wrWrap.style.display = 'none';
        document.getElementById('detailWorkReport').textContent = '';
    }
    
    document.getElementById('viewDetailsModal').style.display = 'flex';
    
    // Vital to re-render lucide dynamically injected string icons inside the modal
    setTimeout(() => lucide.createIcons(), 10);
};

window.closeViewModal = function() {
    document.getElementById('viewDetailsModal').style.display = 'none';
};

window.openReportModal = function(encodedText) {
    const text = decodeURIComponent(encodedText);
    const modal = document.getElementById('reportModal');
    const textContainer = document.getElementById('modalReportText');
    
    // Safety fallback just in case formatting is malformed
    textContainer.textContent = text || 'No report contents identified.';
    
    modal.style.display = 'flex';
};

window.closeReportModal = function() {
    document.getElementById('reportModal').style.display = 'none';
};

window.openGeofenceDecisionModal = function(index, actionType) {
    const user = window.latestAttendanceData[index];
    if (!user) return;

    const hasOutsideIn = !!(user.punch_in_outside_reason && user.punch_in_outside_reason.trim().length > 0);
    const hasOutsideOut = !!(user.punch_out_outside_reason && user.punch_out_outside_reason.trim().length > 0);
    const hasOutsideGeofence = hasOutsideIn || hasOutsideOut;

    if (!user.attendance_id || !hasOutsideGeofence) {
        return;
    }

    const modal = document.getElementById('geofenceDecisionModal');
    const title = document.getElementById('geofenceDecisionTitle');
    const approveBtn = document.getElementById('geofenceDecisionApproveBtn');
    const rejectBtn = document.getElementById('geofenceDecisionRejectBtn');
    const msg = document.getElementById('geofenceDecisionMessage');

    document.getElementById('geofenceActionType').value = actionType;
    document.getElementById('geofenceAttendanceId').value = user.attendance_id;
    document.getElementById('geofenceDecisionComment').value = '';
    msg.style.display = 'none';
    msg.textContent = '';

    title.textContent = `Geofence Decision • ${user.username} • ${user.attendance_date}`;
    const canApprove = Number(window.currentUserPermissions.can_approve_attendance) === 1;
    const canReject = Number(window.currentUserPermissions.can_reject_attendance) === 1;
    approveBtn.disabled = !canApprove;
    rejectBtn.disabled = !canReject;
    approveBtn.style.opacity = canApprove ? '1' : '0.55';
    rejectBtn.style.opacity = canReject ? '1' : '0.55';
    approveBtn.style.cursor = canApprove ? 'pointer' : 'not-allowed';
    rejectBtn.style.cursor = canReject ? 'pointer' : 'not-allowed';

    if (actionType === 'reject') {
        rejectBtn.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.2)';
        approveBtn.style.boxShadow = 'none';
    } else {
        approveBtn.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.2)';
        rejectBtn.style.boxShadow = 'none';
    }

    const inCard = document.getElementById('geofencePunchInCard');
    const outCard = document.getElementById('geofencePunchOutCard');
    const inCheckbox = document.getElementById('geofenceSelectPunchIn');
    const outCheckbox = document.getElementById('geofenceSelectPunchOut');

    document.getElementById('geofencePunchInReason').textContent = hasOutsideIn ? user.punch_in_outside_reason : '';
    document.getElementById('geofencePunchOutReason').textContent = hasOutsideOut ? user.punch_out_outside_reason : '';

    inCard.style.display = hasOutsideIn ? 'block' : 'none';
    outCard.style.display = hasOutsideOut ? 'block' : 'none';
    inCheckbox.checked = hasOutsideIn;
    outCheckbox.checked = hasOutsideOut;
    inCheckbox.disabled = !hasOutsideIn;
    outCheckbox.disabled = !hasOutsideOut;

    modal.style.display = 'flex';
    setTimeout(() => lucide.createIcons(), 10);
};

window.closeGeofenceDecisionModal = function() {
    const modal = document.getElementById('geofenceDecisionModal');
    if (modal) modal.style.display = 'none';
};

window.submitGeofenceDecision = async function(actionOverride) {
    const action = actionOverride || document.getElementById('geofenceActionType').value;
    const attendanceId = document.getElementById('geofenceAttendanceId').value;
    const comments = document.getElementById('geofenceDecisionComment').value.trim();
    const approveBtn = document.getElementById('geofenceDecisionApproveBtn');
    const rejectBtn = document.getElementById('geofenceDecisionRejectBtn');
    const btn = action === 'reject' ? rejectBtn : approveBtn;
    const msg = document.getElementById('geofenceDecisionMessage');
    const selectedPoints = [];

    if (action === 'approve' && Number(window.currentUserPermissions.can_approve_attendance) !== 1) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'You are not allowed to approve attendance.';
        return;
    }
    if (action === 'reject' && Number(window.currentUserPermissions.can_reject_attendance) !== 1) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'You are not allowed to reject attendance.';
        return;
    }

    if (document.getElementById('geofenceSelectPunchIn').checked) {
        selectedPoints.push('punch_in');
    }
    if (document.getElementById('geofenceSelectPunchOut').checked) {
        selectedPoints.push('punch_out');
    }

    if (!attendanceId) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Attendance record id is missing.';
        return;
    }

    if (selectedPoints.length === 0) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Select at least one checkpoint (Punch In or Punch Out).';
        return;
    }

    const rejectWordCount = comments.split(/\s+/).filter(Boolean).length;

    if (action === 'reject' && rejectWordCount < 10) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = `Rejection reason must be at least 10 words. Current count: ${rejectWordCount}`;
        return;
    }

    const originalText = btn.textContent;
    approveBtn.disabled = true;
    rejectBtn.disabled = true;
    btn.textContent = 'Processing...';
    msg.style.display = 'none';

    try {
        const payload = new URLSearchParams();
        payload.append('attendance_id', attendanceId);
        payload.append('action', action);
        payload.append('comments', comments);
        payload.append('selected_points', selectedPoints.join(','));

        const res = await fetch('api/approve_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        });

        const data = await res.json();

        if (data.success) {
            msg.style.display = 'block';
            msg.style.color = '#10b981';
            msg.textContent = `Attendance ${action === 'approve' ? 'approved' : 'rejected'} successfully.`;

            setTimeout(() => {
                closeGeofenceDecisionModal();
                document.getElementById('fromDate').dispatchEvent(new Event('change'));
            }, 700);
        } else {
            msg.style.display = 'block';
            msg.style.color = '#ef4444';
            msg.textContent = data.message || 'Failed to process attendance decision.';
        }
    } catch (err) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Request failed. Please try again.';
    } finally {
        approveBtn.disabled = false;
        rejectBtn.disabled = false;
        btn.textContent = originalText;
    }
};

window.openEditModal = function(index) {
    if (Number(window.currentUserPermissions.can_edit_attendance) !== 1) {
        return;
    }
    const user = window.latestAttendanceData[index];
    if (!user) return;
    
    // Core payload tracking
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editDate').value = user.attendance_raw_date;
    
    // UI Visual Banners
    document.getElementById('editUserName').textContent = user.username;
    document.getElementById('editUserAvatar').textContent = user.initial;
    document.getElementById('editUserDateDisplay').textContent = user.attendance_date;
    
    // Data populating variables. Parse the string formatted time variables intelligently 
    // to map down to <input type="time"> which demands strict 'HH:mm' military standard format. 
    const formatNativeTimeInput = (inTimeString) => {
        if (!inTimeString || inTimeString === '--:--' || inTimeString === '-') return '';
        // If it holds 'PM' or 'AM', we need to convert to native 24 hour string.
        let parsed = inTimeString;
        if (inTimeString.includes(' AM') || inTimeString.includes(' PM')) {
            const tempD = new Date("01/01/2000 " + inTimeString);
            if (!isNaN(tempD.getTime())) {
                parsed = tempD.toTimeString().split(' ')[0].substring(0, 5); 
            }
        }
        return parsed.substring(0, 5); // Fallback: extract '09:00'
    };
    
    document.getElementById('editPunchIn').value = formatNativeTimeInput(user.check_in);
    document.getElementById('editPunchOut').value = formatNativeTimeInput(user.check_out);
    
    const wr = user.work_report === '-' ? '' : user.work_report;
    document.getElementById('editWorkReport').value = wr;
    
    // Evaluate initial counter natively
    countWorkReportWords();
    
    // reset messaging
    const msg = document.getElementById('editFormMessage');
    msg.style.display = 'none';
    msg.textContent = '';
    
    document.getElementById('editAttendanceModal').style.display = 'flex';
    setTimeout(() => lucide.createIcons(), 10);
};

window.closeEditModal = function() {
    document.getElementById('editAttendanceModal').style.display = 'none';
};

window.countWorkReportWords = function() {
    const text = document.getElementById('editWorkReport').value;
    
    // Exclude special characters and emojis strictly keeping AlphaNumeric arrays
    const cleanStr = text.replace(/[^\w\s]/gi, '');
    const tokens = cleanStr.split(/\s+/).filter(Boolean);
    const count = tokens.length;
    
    const counter = document.getElementById('editWordCounter');
    counter.textContent = count >= 20 ? `${count} Words Logged` : `${count} / 20 Words Minimum`;
    
    // Toggle validity colors
    counter.style.color = count >= 20 ? '#10b981' : '#ef4444';
    
    return count;
};

window.submitEditAttendance = async function(event) {
    event.preventDefault();
    
    const wordCount = countWorkReportWords();
    const btn = document.getElementById('editSubmitBtn');
    const msg = document.getElementById('editFormMessage');
    
    if (wordCount < 20) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = `A minimum of 20 detailed alphanumeric words are required. Current valid count: ${wordCount}`;
        return;
    }
    
    const form = document.getElementById('editAttendanceForm');
    const formData = new FormData(form);
    
    btn.innerHTML = `<i class="lucide-loader animate-spin" style="width:16px;height:16px;"></i> Saving...`;
    btn.disabled = true;
    msg.style.display = 'none';
    
    try {
        const res = await fetch('api/edit_attendance.php', {
            method: 'POST',
            body: formData
        });
        
        const payload = await res.json();
        
        if (payload.success) {
            msg.style.display = 'block';
            msg.style.color = '#10b981';
            msg.textContent = 'Attendance log explicitly updated! Reloading parameters...';
            
            // Wait 1.5s then reload main grid mapped payload naturally
            setTimeout(() => {
                closeEditModal();
                document.getElementById('fromDate').dispatchEvent(new Event('change')); // Natively triggers reload
            }, 1000);
        } else {
            msg.style.display = 'block';
            msg.style.color = '#ef4444';
            msg.textContent = payload.message || 'Validation failure resolving constraints.';
        }
    } catch (e) {
        msg.style.display = 'block';
        msg.style.color = '#ef4444';
        msg.textContent = 'Server collision detected. Request failed natively.';
    } finally {
        btn.innerHTML = `<i data-lucide="save" style="width:16px;height:16px;"></i> Save Record`;
        btn.disabled = false;
        lucide.createIcons();
    }
};

/* ─────────────────────────────────────────────────────────────
   Stats Detail Modal  — opened when a stat card is clicked
───────────────────────────────────────────────────────────── */

// Config per card type
const STATS_MODAL_CONFIG = {
    'present': {
        title: 'Present Employees',
        icon: 'user-check',
        iconBg: 'linear-gradient(135deg,#34d399,#10b981)',
        filter: (u) => u.attendance_status === 'On Time' || u.attendance_status === 'Late',
    },
    'on-leave': {
        title: 'Employees On Leave',
        icon: 'calendar-off',
        iconBg: 'linear-gradient(135deg,#818cf8,#6366f1)',
        filter: (u) => u.attendance_status === 'On Leave',
    },
    'late': {
        title: 'Late Arrivals',
        icon: 'clock-alert',
        iconBg: 'linear-gradient(135deg,#fbbf24,#f59e0b)',
        filter: (u) => u.attendance_status === 'Late',
    },
    'geofence': {
        title: 'Geofence Issues',
        icon: 'map-pin-off',
        iconBg: 'linear-gradient(135deg,#f97316,#ea580c)',
        filter: (u) => !!(
            (u.punch_in_outside_reason  && u.punch_in_outside_reason.trim().length  > 0) ||
            (u.punch_out_outside_reason && u.punch_out_outside_reason.trim().length > 0)
        ),
    },
};

// Store currently displayed rows for search filtering
window._sdmRows = [];
window._sdmType = '';

window.openStatsModal = function(type) {
    const cfg = STATS_MODAL_CONFIG[type];
    if (!cfg) return;

    const allData = window.latestAttendanceData || [];
    const rows    = allData.filter(cfg.filter);

    window._sdmRows = rows;
    window._sdmType = type;

    // Header
    document.getElementById('sdmTitle').textContent    = cfg.title;
    document.getElementById('sdmSubtitle').textContent = `${rows.length} record${rows.length !== 1 ? 's' : ''} found`;
    document.getElementById('sdmIconWrap').style.background = cfg.iconBg;
    const iconEl = document.getElementById('sdmIcon');
    iconEl.setAttribute('data-lucide', cfg.icon);

    // Reset search
    const searchEl = document.getElementById('sdmSearchInput');
    if (searchEl) searchEl.value = '';

    // Render list
    renderSdmList(rows, type);

    // Show modal
    document.getElementById('statsDetailModal').style.display = 'flex';
    setTimeout(() => lucide.createIcons(), 10);
};

function renderSdmList(rows, type) {
    const list    = document.getElementById('sdmList');
    const footer  = document.getElementById('sdmFooter');
    if (!list) return;

    if (rows.length === 0) {
        list.innerHTML = `
            <div class="sdm-empty">
                <i data-lucide="inbox" style="width:32px;height:32px;opacity:0.3;"></i>
                No records found matching this filter.
            </div>`;
        if (footer) footer.textContent = '';
        if (window.lucide) lucide.createIcons();
        return;
    }

    list.innerHTML = rows.map(u => buildSdmRow(u, type)).join('');
    if (footer) footer.textContent = `Showing ${rows.length} record${rows.length !== 1 ? 's' : ''}`;
    if (window.lucide) lucide.createIcons();
}

function buildSdmRow(u, type) {
    const name     = u.username || 'Unknown';
    const date     = u.attendance_date || '';

    // ── Avatar: photo or icon fallback (uses global helpers) ─────────────
    const src = window._resolveProfilePic(u.profile_picture);
    let avatarHtml = '';
    if (src) {
        avatarHtml = `<div style="width:36px;height:36px;border-radius:9px;overflow:hidden;flex-shrink:0;border:1.5px solid #e2e8f0;background:#f1f5f9;">
            <img src="${src}" alt="${name}" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="_avatarError(this)">
        </div>`;
    } else {
        avatarHtml = `<div style="width:36px;height:36px;border-radius:9px;overflow:hidden;flex-shrink:0;background:#e0e7ff;display:flex;align-items:center;justify-content:center;border:1.5px solid #c7d2fe;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
        </div>`;
    }
    // ─────────────────────────────────────────────────────────────────────

    // Right-side content varies by card type
    let rightHtml = '';

    if (type === 'present') {
        // Show punch-in time + on-time / late badge
        const isLate   = u.attendance_status === 'Late';
        const badgeCls = isLate ? 'status-late' : 'status-present';
        const badgeIcon= isLate ? 'clock-4' : 'check-circle-2';
        const label    = isLate ? 'Late' : 'On Time';

        // Calculate how many minutes late (only for late employees)
        let lateMin = '';
        if (isLate && u.shift_start && u.check_in && u.check_in !== '--:--') {
            try {
                const [sh, sm] = u.shift_start.split(':').map(Number);
                const shiftMins = sh * 60 + sm + 15; // 15-min grace
                const d = new Date('1/1/2000 ' + u.check_in);
                if (!isNaN(d)) {
                    const diff = (d.getHours() * 60 + d.getMinutes()) - shiftMins;
                    if (diff > 0) lateMin = `+${diff} min late`;
                }
            } catch (_) {}
        }

        rightHtml = `
            <div class="sdm-time">${u.check_in || '--:--'}</div>
            ${lateMin ? `<span style="font-size:0.68rem;color:#ef4444;font-weight:600;">${lateMin}</span>` : ''}
            <span class="status-badge ${badgeCls}" style="font-size:0.68rem;padding:0.2rem 0.45rem;gap:3px;">
                <i data-lucide="${badgeIcon}" style="width:10px;height:10px;"></i>${label}
            </span>`;

    } else if (type === 'on-leave') {
        // Show leave type badge
        const lt = u.leave_type_name || 'On Leave';
        rightHtml = `
            <span class="status-badge status-leave" style="font-size:0.7rem;padding:0.22rem 0.5rem;gap:3px;">
                <i data-lucide="calendar-minus" style="width:10px;height:10px;"></i>${lt}
            </span>`;

    } else if (type === 'late') {
        // Punch-in time + how many minutes late
        let lateMin = '';
        if (u.shift_start && u.check_in && u.check_in !== '--:--') {
            try {
                const [sh, sm] = u.shift_start.split(':').map(Number);
                const shiftMins = sh * 60 + sm + 15; // 15-min grace
                // check_in is "09:30 AM" format — parse it
                const d = new Date('1/1/2000 ' + u.check_in);
                if (!isNaN(d)) {
                    const checkMins = d.getHours() * 60 + d.getMinutes();
                    const diff = checkMins - shiftMins;
                    if (diff > 0) lateMin = `+${diff} min late`;
                }
            } catch (_) {}
        }
        rightHtml = `
            <div class="sdm-time">${u.check_in || '--:--'}</div>
            ${lateMin ? `<span style="font-size:0.68rem;color:#ef4444;font-weight:600;">${lateMin}</span>` : ''}`;

    } else if (type === 'geofence') {
        // Show in/out geofence status tags
        const hasIn  = !!(u.punch_in_outside_reason  && u.punch_in_outside_reason.trim());
        const hasOut = !!(u.punch_out_outside_reason && u.punch_out_outside_reason.trim());
        const isRej  = (u.approval_status || '').toLowerCase() === 'rejected';

        const tagClass = isRej ? 'sdm-geo-rej' : 'sdm-geo-out';
        const tagIcon  = isRej ? 'x-circle' : 'alert-triangle';
        const tagText  = isRej ? 'Rejected' : 'Outside';

        rightHtml = `<div class="sdm-geo-wrap">`;
        if (hasIn)  rightHtml += `<span class="sdm-geo-tag ${tagClass}"><i data-lucide="${tagIcon}" style="width:10px;height:10px;"></i>In ${tagText}</span>`;
        if (hasOut) rightHtml += `<span class="sdm-geo-tag ${tagClass}"><i data-lucide="${tagIcon}" style="width:10px;height:10px;"></i>Out ${tagText}</span>`;
        rightHtml += `</div>`;
    }

    return `
        <div class="sdm-row" data-name="${name.toLowerCase()}" data-date="${date.toLowerCase()}">
            ${avatarHtml}
            <div class="sdm-user-info">
                <div class="sdm-user-name">${name}</div>
                <div class="sdm-user-meta">${date}</div>
            </div>
            <div class="sdm-right">${rightHtml}</div>
        </div>`;
}

window.filterStatsModalList = function() {
    const q    = (document.getElementById('sdmSearchInput')?.value || '').toLowerCase().trim();
    const rows = window._sdmRows || [];
    const type = window._sdmType || '';

    if (!q) {
        renderSdmList(rows, type);
        return;
    }

    const filtered = rows.filter(u =>
        (u.username || '').toLowerCase().includes(q) ||
        (u.attendance_date || '').toLowerCase().includes(q) ||
        (u.leave_type_name || '').toLowerCase().includes(q)
    );
    renderSdmList(filtered, type);

    // Update footer to reflect filtered count
    const footer = document.getElementById('sdmFooter');
    if (footer) footer.textContent = `Showing ${filtered.length} of ${rows.length} records`;
};

window.closeStatsModal = function() {
    document.getElementById('statsDetailModal').style.display = 'none';
    window._sdmRows = [];
    window._sdmType = '';
};


// Close on backdrop click
document.getElementById('statsDetailModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeStatsModal();
});

/* ═══════════════════════════════════════════════════════════════════════
   PDF EXPORT  —  exports window.latestAttendanceData (full filtered set)
═══════════════════════════════════════════════════════════════════════ */

document.getElementById('exportPdfBtn')?.addEventListener('click', function () {
    const data = window.latestAttendanceData || [];
    if (!data.length) {
        alert('No data to export. Please apply filters and load attendance first.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    /* ── Filter context ───────────────────────────────────────────────── */
    const fromDate   = document.getElementById('fromDate')?.value   || '';
    const toDate     = document.getElementById('toDate')?.value     || '';
    const statusVal  = document.getElementById('statusFilter')?.value || 'All';
    const userSelect = document.getElementById('userFilter');
    const userLabel  = userSelect?.options[userSelect.selectedIndex]?.text || 'All Employees';
    const searchQ    = document.getElementById('searchInput')?.value?.trim() || '';

    /* ── Status colour helpers (plain RGB arrays for jsPDF) ──────────── */
    const statusBg = (s) => {
        const v = (s || '').toLowerCase();
        if (v === 'on time')  return [220, 252, 231];
        if (v === 'late')     return [254, 249, 195];
        if (v === 'absent')   return [254, 226, 226];
        if (v === 'on leave') return [237, 233, 254];
        return [248, 250, 252];
    };
    const statusFg = (s) => {
        const v = (s || '').toLowerCase();
        if (v === 'on time')  return [22,  163, 74 ];
        if (v === 'late')     return [161, 98,  7  ];
        if (v === 'absent')   return [220, 38,  38 ];
        if (v === 'on leave') return [109, 40,  217];
        return [100, 116, 139];
    };

    /* ── Page geometry ────────────────────────────────────────────────── */
    const pageW  = doc.internal.pageSize.getWidth();   // 297 mm landscape
    const pageH  = doc.internal.pageSize.getHeight();  // 210 mm
    const margin = 12;

    /* ── Header band (navy) ───────────────────────────────────────────── */
    doc.setFillColor(15, 23, 42);
    doc.rect(0, 0, pageW, 20, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.setTextColor(255, 255, 255);
    doc.text('Employees Attendance Report', margin, 13);
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(148, 163, 184);
    doc.text('connect  |  HR Portal', pageW - margin, 13, { align: 'right' });

    /* ── Filter summary band (slate) ─────────────────────────────────── */
    doc.setFillColor(241, 245, 249);
    doc.rect(0, 20, pageW, 11, 'F');
    const parts = [];
    if (fromDate || toDate) parts.push('Period: ' + (fromDate || '-') + '  to  ' + (toDate || '-'));
    parts.push('Employee: ' + userLabel);
    parts.push('Status: ' + statusVal);
    if (searchQ) parts.push('Search: ' + searchQ);
    parts.push('Records: ' + data.length);
    doc.setFontSize(7.5);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(71, 85, 105);
    doc.text(parts.join('   |   '), margin, 27.5);

    /* ── Stat boxes ───────────────────────────────────────────────────── */
    const statCounts = {
        'On Time' : data.filter(r => r.attendance_status === 'On Time').length,
        'Late'    : data.filter(r => r.attendance_status === 'Late').length,
        'Absent'  : data.filter(r => r.attendance_status === 'Absent').length,
        'On Leave': data.filter(r => r.attendance_status === 'On Leave').length,
    };
    const statClr = {
        'On Time' : [[220,252,231],[22,163,74]],
        'Late'    : [[254,249,195],[161,98,7]],
        'Absent'  : [[254,226,226],[220,38,38]],
        'On Leave': [[237,233,254],[109,40,217]],
    };
    const boxH = 9, boxY = 33, boxGap = 4;
    const boxW = 42;
    let bx = margin;
    Object.entries(statCounts).forEach(([lbl, cnt]) => {
        const [bg, fg] = statClr[lbl];
        doc.setFillColor(...bg);
        doc.roundedRect(bx, boxY, boxW, boxH, 2, 2, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7);
        doc.setTextColor(...fg);
        doc.text(lbl + ': ' + cnt, bx + boxW / 2, boxY + 5.8, { align: 'center' });
        bx += boxW + boxGap;
    });

    /* ── Build table rows (ASCII-safe only) ───────────────────────────── */
    // Usable width = 297 - 24 = 273 mm
    // Cols: # 11 | Date 25 | Employee 38 | Punch In 27 | Punch Out 27 | Hrs 16 | Status 30 | Work Report auto
    // Fixed sum = 11+25+38+27+27+16+30 = 174  → Work Report = 273-174 = 99 mm

    const tableRows = data.map((row, i) => {
        // ASCII-safe sentinel for missing values
        const NA = '-';

        const pIn  = (row.check_in  && row.check_in  !== '--:--') ? row.check_in  : NA;
        const pOut = (row.check_out && row.check_out !== '--:--') ? row.check_out : NA;

        // Work hours
        let hrs = NA;
        if (pIn !== NA && pOut !== NA) {
            try {
                const d1 = new Date('1/1/2000 ' + pIn);
                const d2 = new Date('1/1/2000 ' + pOut);
                if (!isNaN(d1) && !isNaN(d2) && d2 > d1) {
                    const mins = (d2 - d1) / 60000;
                    hrs = Math.floor(mins / 60) + 'h ' + Math.round(mins % 60) + 'm';
                }
            } catch (_) {}
        }

        // Status (show specific leave type if on leave)
        let status = row.attendance_status || NA;
        if (status === 'On Leave' && row.leave_type_name) status = row.leave_type_name;
        // Geofence flag — ASCII safe
        if (row.punch_in_outside_reason || row.punch_out_outside_reason) status += ' [!]';

        // Work report — truncate
        const report = (row.work_report && row.work_report !== '-')
            ? row.work_report.substring(0, 100)
            : NA;

        return [
            i + 1,
            row.attendance_date || NA,
            row.username        || NA,
            pIn,
            pOut,
            status,
            report,
        ];
    });

    /* ── AutoTable ────────────────────────────────────────────────────── */
    const STATUS_COL = 5; // 0-indexed (# Date Employee PunchIn PunchOut Status WorkReport)

    doc.autoTable({
        head: [['#', 'Date', 'Employee', 'Punch In', 'Punch Out', 'Status', 'Work Report']],
        body: tableRows,
        startY: boxY + boxH + 5,
        margin: { left: margin, right: margin },
        tableWidth: pageW - margin * 2,   // pin to exact usable width

        styles: {
            font: 'helvetica',
            fontSize: 7.5,
            cellPadding: { top: 3, right: 4, bottom: 3, left: 4 },
            overflow: 'linebreak',
            lineColor: [226, 232, 240],
            lineWidth: 0.3,
            valign: 'middle',
            textColor: [30, 41, 59],
        },

        headStyles: {
            fillColor: [15, 23, 42],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
            fontSize: 7.5,
            halign: 'center',
            cellPadding: { top: 4, right: 4, bottom: 4, left: 4 },
        },

        columnStyles: {
            0: { cellWidth: 11, halign: 'center' },  // #
            1: { cellWidth: 25, halign: 'center' },  // Date
            2: { cellWidth: 38 },                     // Employee
            3: { cellWidth: 27, halign: 'center' },  // Punch In
            4: { cellWidth: 27, halign: 'center' },  // Punch Out
            5: { cellWidth: 32, halign: 'center' },  // Status
            6: { cellWidth: 'auto' },                 // Work Report (fills rest)
        },

        didParseCell: function (data) {
            if (data.section !== 'body') return;
            const row    = tableRows[data.row.index];
            const rawRow = window.latestAttendanceData[data.row.index];
            const status = rawRow?.attendance_status || '';
            const even   = data.row.index % 2 === 0;

            if (data.column.index === STATUS_COL) {
                data.cell.styles.fillColor  = statusBg(status);
                data.cell.styles.textColor  = statusFg(status);
                data.cell.styles.fontStyle  = 'bold';
            } else {
                data.cell.styles.fillColor = even ? [255,255,255] : [248,250,252];
            }
        },

        didDrawPage: function () {
            const pg  = doc.internal.getCurrentPageInfo().pageNumber;
            const tot = doc.internal.getNumberOfPages();
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.setTextColor(148, 163, 184);
            doc.text(
                'Generated: ' + new Date().toLocaleString('en-IN') + '   |   Page ' + pg + ' of ' + tot,
                pageW / 2, pageH - 4, { align: 'center' }
            );
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.3);
            doc.line(margin, pageH - 7, pageW - margin, pageH - 7);
        },
    });

    /* ── Save ─────────────────────────────────────────────────────────── */
    const slug = userLabel.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
    const ds   = fromDate && toDate ? fromDate + '_to_' + toDate
               : fromDate || toDate || new Date().toISOString().slice(0, 10);
    doc.save('Attendance_' + slug + '_' + ds + '.pdf');
});


/* ═══════════════════════════════════════════════════════════════════════
   EXCEL EXPORT  —  exports window.latestAttendanceData (full filtered set)
═══════════════════════════════════════════════════════════════════════ */

document.getElementById('exportExcelBtn')?.addEventListener('click', function () {
    const data = window.latestAttendanceData || [];
    if (!data.length) {
        alert('No data to export. Please apply filters and load attendance first.');
        return;
    }

    /* ── Collect filter context ──────────────────────────────────────── */
    const fromDate   = document.getElementById('fromDate')?.value   || '';
    const toDate     = document.getElementById('toDate')?.value     || '';
    const statusVal  = document.getElementById('statusFilter')?.value || 'All';
    const userSelect = document.getElementById('userFilter');
    const userLabel  = userSelect?.options[userSelect.selectedIndex]?.text || 'All Employees';
    const searchQ    = document.getElementById('searchInput')?.value?.trim() || '';

    /* ── Filter summary string ───────────────────────────────────────── */
    const filterStr = [
        (fromDate || toDate) ? ('Period: ' + (fromDate || '-') + ' to ' + (toDate || '-')) : '',
        'Employee: ' + userLabel,
        'Status Filter: ' + statusVal,
        searchQ ? ('Search: ' + searchQ) : '',
        'Total Records: ' + data.length,
    ].filter(Boolean).join('   |   ');

    /* ── Build array-of-arrays sheet data ────────────────────────────── */
    // Row 0 : Report title (merged)
    // Row 1 : Filter summary (merged)
    // Row 2 : blank spacer
    // Row 3 : Column headers
    // Row 4+ : Data rows

    const HEADERS = ['#', 'Date', 'Employee', 'Punch In', 'Punch Out', 'Status', 'Work Report'];

    const sheetData = [
        ['Employees Attendance Report', '', '', '', '', '', ''],
        [filterStr,                     '', '', '', '', '', ''],
        ['',                            '', '', '', '', '', ''],
        HEADERS,
    ];

    data.forEach((row, i) => {
        const NA   = '-';
        const pIn  = (row.check_in  && row.check_in  !== '--:--') ? row.check_in  : NA;
        const pOut = (row.check_out && row.check_out !== '--:--') ? row.check_out : NA;

        let status = row.attendance_status || NA;
        if (status === 'On Leave' && row.leave_type_name) status = row.leave_type_name;
        if (row.punch_in_outside_reason || row.punch_out_outside_reason) status += ' [Geo]';

        const report = (row.work_report && row.work_report !== '-') ? row.work_report : NA;

        sheetData.push([
            i + 1,
            row.attendance_date || NA,
            row.username        || NA,
            pIn,
            pOut,
            status,
            report,
        ]);
    });

    /* ── Create worksheet ────────────────────────────────────────────── */
    const ws = XLSX.utils.aoa_to_sheet(sheetData);

    /* ── Column widths (character units) ─────────────────────────────── */
    ws['!cols'] = [
        { wch: 5  },   // #
        { wch: 14 },   // Date
        { wch: 22 },   // Employee
        { wch: 12 },   // Punch In
        { wch: 12 },   // Punch Out
        { wch: 22 },   // Status
        { wch: 55 },   // Work Report
    ];

    /* ── Merge title + filter rows across all columns ─────────────────── */
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } },  // Title
        { s: { r: 1, c: 0 }, e: { r: 1, c: 6 } },  // Filter summary
    ];

    /* ── Row heights ──────────────────────────────────────────────────── */
    ws['!rows'] = [
        { hpt: 26 },   // Title
        { hpt: 16 },   // Filter
        { hpt: 6  },   // Spacer
        { hpt: 18 },   // Header
    ];

    /* ── Workbook & download ──────────────────────────────────────────── */
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Attendance');

    const slug = userLabel.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
    const ds   = fromDate && toDate
        ? (fromDate + '_to_' + toDate)
        : (fromDate || toDate || new Date().toISOString().slice(0, 10));

    XLSX.writeFile(wb, 'Attendance_' + slug + '_' + ds + '.xlsx');
});
