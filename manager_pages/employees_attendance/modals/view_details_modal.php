<?php
// modals/view_details_modal.php
?>
<!-- View Attendance Details Modal -->
<style>
    #viewDetailsModal .modal-content {
        max-width: 580px;
        width: 95%;
        border-radius: 16px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        border: 1px solid #f1f5f9;
        overflow: hidden;
    }
    .minimal-header {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
    }
    .minimal-title {
        font-size: 1rem;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .timeline-card {
        background: #fafafa;
        border: 1px solid #f4f4f5;
        border-radius: 12px;
        padding: 1.25rem;
    }
    .timeline-heading {
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .info-row:last-child {
        margin-bottom: 0;
    }
    .info-icon {
        width: 14px;
        height: 14px;
        color: #94a3b8;
        margin-top: 2px;
    }
    .info-label {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .info-value {
        font-size: 0.85rem;
        color: #334155;
        font-weight: 500;
        line-height: 1.4;
    }
    .report-card {
        margin-top: 1.25rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
    }
</style>

<div id="viewDetailsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="minimal-header">
            <h3 class="minimal-title"><i data-lucide="clipboard-list" style="width:16px;height:16px;color:#6366f1;"></i> Attendance Record</h3>
            <button class="modal-close" onclick="closeViewModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;transition:0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 0 1.5rem 1.5rem 1.5rem; background: #fff; max-height: 80vh; overflow-y: auto;">
            
            <!-- Minimal Profile -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1.25rem; border-bottom: 1px dashed #e2e8f0; margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <div id="detailUserAvatar" style="width: 44px; height: 44px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 600;"></div>
                    <div>
                        <h4 id="detailUserName" style="margin: 0; font-size: 1rem; color: #0f172a; font-weight: 600;"></h4>
                        <div style="display: flex; align-items: center; gap: 4px; margin-top: 0.35rem;">
                            <i data-lucide="calendar" style="width: 12px; height: 12px; color: #64748b;"></i>
                            <span id="detailUserDate" style="font-size: 0.75rem; color: #64748b; font-weight: 500;"></span>
                            <span style="color:#cbd5e1; margin:0 4px;">|</span>
                            <i data-lucide="sun" style="width: 12px; height: 12px; color: #64748b;"></i>
                            <span id="detailUserShift" style="font-size: 0.75rem; color: #64748b; font-weight: 500;"></span>
                        </div>
                    </div>
                </div>
                <div id="detailUserStatus"></div>
            </div>

            <!-- Dual Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                
                <!-- Punch In -->
                <div class="timeline-card">
                    <div class="timeline-heading"><i data-lucide="log-in" style="width:14px;height:14px;color:#3b82f6;"></i> Punch In</div>
                    <div class="info-row" style="margin-bottom: 1.25rem;">
                        <i data-lucide="camera" class="info-icon"></i>
                        <div>
                            <div class="info-label">Photo</div>
                            <div id="detailPunchInPhoto" style="margin-top: 0.25rem;"></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <i data-lucide="clock" class="info-icon"></i>
                        <div>
                            <div class="info-label">Time</div>
                            <div id="detailPunchIn" class="info-value" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <i data-lucide="map-pin" class="info-icon"></i>
                        <div>
                            <div class="info-label">Location</div>
                            <div id="detailPunchInLocation" class="info-value"></div>
                            <div id="detailPunchInGeofence" style="margin-top: 0.35rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- Punch Out -->
                <div class="timeline-card">
                    <div class="timeline-heading"><i data-lucide="log-out" style="width:14px;height:14px;color:#8b5cf6;"></i> Punch Out</div>
                    <div class="info-row" style="margin-bottom: 1.25rem;">
                        <i data-lucide="camera" class="info-icon"></i>
                        <div>
                            <div class="info-label">Photo</div>
                            <div id="detailPunchOutPhoto" style="margin-top: 0.25rem;"></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <i data-lucide="clock" class="info-icon"></i>
                        <div>
                            <div class="info-label">Time</div>
                            <div id="detailPunchOut" class="info-value" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <i data-lucide="map-pin" class="info-icon"></i>
                        <div>
                            <div class="info-label">Location</div>
                            <div id="detailPunchOutLocation" class="info-value"></div>
                            <div id="detailPunchOutGeofence" style="margin-top: 0.35rem;"></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Report -->
            <div id="detailWorkReportWrapper" class="report-card" style="display: none;">
                <div class="timeline-heading" style="color: #64748b; margin-bottom: 0.75rem;"><i data-lucide="file-text" style="width:14px;height:14px;"></i> Work Report</div>
                <div id="detailWorkReport" style="font-size: 0.85rem; color: #475569; line-height: 1.6; white-space: pre-wrap;"></div>
            </div>

        </div>
    </div>
</div>
