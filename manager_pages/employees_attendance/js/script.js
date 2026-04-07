window.currentUserPermissions = window.currentUserPermissions || {
    can_approve_attendance: 0,
    can_reject_attendance: 0,
    can_edit_attendance: 0
};

document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const userFilter = document.getElementById("userFilter");
    const fromDate = document.getElementById("fromDate");
    const toDate = document.getElementById("toDate");
    const attendanceTableBody = document.getElementById("attendanceTableBody");
    
    // Stats Elements
    const statTotal = document.getElementById("statTotal");
    const statOnTime = document.getElementById("statOnTime");
    const statLate = document.getElementById("statLate");
    const statAbsent = document.getElementById("statAbsent");

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
                updateStats(payload.stats);
                renderTable();
            } else {
                attendanceTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 2rem; color: #ef4444;">Error: ${payload.message}</td></tr>`;
            }
        } catch (e) {
            attendanceTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 2rem; color: #ef4444;">Failed to communicate with API.</td></tr>`;
            console.error(e);
        }
    }

    function updateStats(stats) {
        if(statTotal) statTotal.textContent = stats.Total || 0;
        if(statOnTime) statOnTime.textContent = stats['On Time'] || 0;
        if(statLate) statLate.textContent = stats.Late || 0;
        if(statAbsent) statAbsent.textContent = stats.Absent || 0;
    }

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

        if (filteredData.length === 0) {
            attendanceTableBody.innerHTML = `<tr>
                <td colspan="9" style="text-align: center; padding: 2rem; color: #94a3b8; font-weight: 500;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                        <i data-lucide="frown" style="width: 24px; height: 24px; opacity: 0.5;"></i>
                        No employee data found matching criteria.
                    </div>
                </td>
            </tr>`;
            if (window.lucide) lucide.createIcons();
            window.latestAttendanceData = filteredData;
            return;
        }

        window.latestAttendanceData = filteredData;

        filteredData.forEach((user, index) => {
            const status = user.attendance_status;
            let statusClass = 'status-leave';
            let iconName = 'concierge-bell';
            const hasOutsideIn = !!(user.punch_in_outside_reason && user.punch_in_outside_reason.trim().length > 0);
            const hasOutsideOut = !!(user.punch_out_outside_reason && user.punch_out_outside_reason.trim().length > 0);
            const hasOutsideGeofence = hasOutsideIn || hasOutsideOut;
            const canAnyDecision = (Number(currentUserPermissions.can_approve_attendance) === 1 || Number(currentUserPermissions.can_reject_attendance) === 1);
            const canDecision = !!user.attendance_id && hasOutsideGeofence && canAnyDecision;
            const canEdit = Number(currentUserPermissions.can_edit_attendance) === 1;
            
            if (status === 'On Time') { 
                statusClass = 'status-present'; iconName = 'check-circle-2'; 
            } else if (status === 'Absent') { 
                statusClass = 'status-absent'; iconName = 'x-circle'; 
            } else if (status === 'Late') { 
                statusClass = 'status-late'; iconName = 'clock-4'; 
            }

            const renderPhoto = (photoStr) => {
                if(photoStr && photoStr !== '-' && photoStr !== 'null') {
                    // Extract just the filename to avoid duplicate subdirectories traversing
                    const fileName = photoStr.includes('/') ? photoStr.split('/').pop() : photoStr;
                    // Format relatively to bubble up from /manager_pages/employees_attendance/ to root, working natively on local AND production
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
                <td style="padding-right: 0.5rem; text-align: center;"><strong style="color: #64748b; font-size: 0.85rem;">${index + 1}</strong></td>
                <td style="padding-left: 0.5rem;"><span style="font-size:0.8rem; font-weight:500; color:#334155; white-space:nowrap;">${user.attendance_date}</span></td>
                <td>
                    <div class="user-cell" style="display: flex; align-items: center; gap: 0.75rem;">
                        <div class="user-avatar">${user.initial}</div>
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
                    <span class="status-badge ${statusClass}">
                        <i data-lucide="${iconName}" style="width: 12px; height: 12px;"></i>
                        ${status}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.15rem; justify-content: center;">
                        <button onclick="openViewModal(${index})" class="action-btn" title="View Details" style="background: none; border: none; cursor: pointer; color: #3b82f6; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='none'">
                            <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button onclick="openEditModal(${index})" class="action-btn" title="Edit Record" ${canEdit ? '' : 'disabled'} style="background: none; border: none; cursor: ${canEdit ? 'pointer' : 'not-allowed'}; color: ${canEdit ? '#8b5cf6' : '#94a3b8'}; opacity:${canEdit ? '1' : '0.55'}; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="if(!this.disabled){this.style.background='#f3e8ff'}" onmouseout="this.style.background='none'">
                            <i data-lucide="edit-3" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button onclick="openGeofenceDecisionModal(${index}, 'approve')" class="action-btn" title="Review Geofence" ${canDecision ? '' : 'disabled'} style="background: none; border: none; cursor: ${canDecision ? 'pointer' : 'not-allowed'}; color: ${canDecision ? '#6366f1' : '#94a3b8'}; opacity:${canDecision ? '1' : '0.55'}; padding: 0.35rem; border-radius: 6px; transition: background 0.2s;" onmouseover="if(!this.disabled){this.style.background='#eef2ff'}" onmouseout="this.style.background='none'">
                            <i data-lucide="circle-ellipsis" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                </td>
            `;
            attendanceTableBody.appendChild(tr);
        });

        if (window.lucide) lucide.createIcons();
    }

    // Attach Listeners
    if (searchInput) searchInput.addEventListener("input", renderTable);
    if (statusFilter) statusFilter.addEventListener("change", renderTable);
    
    // Core parameters that require API refetch
    if (fromDate) fromDate.addEventListener("change", loadAttendanceData);
    if (toDate) toDate.addEventListener("change", loadAttendanceData);
    if (userFilter) userFilter.addEventListener("change", loadAttendanceData);

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
    document.getElementById('modalMapContainer').innerHTML = ''; // Prevent background iframe tracking and memory leaks
};

window.openViewModal = function(index) {
    const user = window.latestAttendanceData[index];
    if (!user) return;
    
    // Header Mapping
    document.getElementById('detailUserName').textContent = user.username;
    document.getElementById('detailUserAvatar').textContent = user.initial;
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
    } else if (statusLower === 'leave') {
        statusClass = 'status-leave';
        statusIcon = 'calendar-minus';
    } else {
        statusClass = 'status-absent';
        statusIcon = 'help-circle';
    }
    
    let displayStat = statusLower.charAt(0).toUpperCase() + statusLower.slice(1);
    if (statusClass === 'status-late') displayStat = 'Late';
    if (displayStat === 'Present') displayStat = 'On Time';
    
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
