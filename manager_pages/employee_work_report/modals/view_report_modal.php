<div class="modal-overlay" id="viewReportModal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Detailed Work Report</h2>
            <button class="modal-close-btn" id="closeReportModal">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="employee-info-bar">
                <div class="info-item">
                    <span class="info-label" style="display: flex; align-items: center; gap: 4px;"><i data-lucide="user" style="width: 12px; height: 12px;"></i> Employee</span>
                    <span class="info-value" id="modalEmployeeName">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label" style="display: flex; align-items: center; gap: 4px;"><i data-lucide="briefcase" style="width: 12px; height: 12px;"></i> Role</span>
                    <span class="info-value" id="modalEmployeeRole">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label" style="display: flex; align-items: center; gap: 4px;"><i data-lucide="calendar" style="width: 12px; height: 12px;"></i> Period</span>
                    <span class="info-value" id="modalReportPeriod">-</span>
                </div>
            </div>

            <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #eaeaea; border-radius: 6px;">
                <table class="report-details-table" style="margin-top: 0; width: 100%;">
                    <thead style="position: sticky; top: 0; background: #fafafa; z-index: 10;">
                        <tr>
                            <th style="width: 60px; padding-left: 1rem;"><div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="hash" style="width: 14px; height: 14px; color: #a3a3a3;"></i> S.No</div></th>
                            <th style="width: 160px;"><div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="calendar-days" style="width: 14px; height: 14px; color: #a3a3a3;"></i> Date</div></th>
                            <th style="width: 120px;"><div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="sun" style="width: 14px; height: 14px; color: #a3a3a3;"></i> Day</div></th>
                            <th style="padding-right: 1rem;"><div style="display: flex; align-items: center; gap: 6px;"><i data-lucide="clipboard-list" style="width: 14px; height: 14px; color: #a3a3a3;"></i> Work Report</div></th>
                        </tr>
                    </thead>
                    <tbody id="reportModalBody">
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: #a3a3a3;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-cancel" id="closeReportModalFooter">Close Window</button>
        </div>
    </div>
</div>
