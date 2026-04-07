<?php
// modals/edit_attendance_modal.php
?>
<style>
    #editAttendanceModal .modal-content {
        max-width: 500px;
        width: 95%;
        border-radius: 16px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15);
        border: 1px solid #f1f5f9;
        overflow: hidden;
    }
    .edit-form-group {
        margin-bottom: 1.25rem;
    }
    .edit-form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        display: block;
        margin-bottom: 0.5rem;
    }
    .edit-form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.9rem;
        color: #1e293b;
        background: #f8fafc;
        transition: 0.2s border-color;
        box-sizing: border-box;
    }
    .edit-form-input:focus {
        outline: none;
        border-color: #6366f1;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    .edit-btn-primary {
        width: 100%;
        padding: 0.75rem;
        background: #4f46e5;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s background;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .edit-btn-primary:hover {
        background: #4338ca;
    }
    .edit-user-banner {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f1f5f9;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
</style>

<div id="editAttendanceModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="minimal-header">
            <h3 class="minimal-title"><i data-lucide="edit-3" style="width:16px;height:16px;color:#8b5cf6;"></i> Edit Attendance</h3>
            <button class="modal-close" onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;transition:0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 1.5rem; background: #fff; max-height: 80vh; overflow-y: auto;">
            
            <form id="editAttendanceForm" onsubmit="submitEditAttendance(event)">
                <input type="hidden" id="editUserId" name="user_id">
                <input type="hidden" id="editDate" name="attendance_date">
                
                <!-- Display Context -->
                <div class="edit-user-banner">
                    <div id="editUserAvatar" style="width: 40px; height: 40px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 600;"></div>
                    <div>
                        <div id="editUserName" style="font-weight: 600; color: #0f172a; font-size: 0.95rem;"></div>
                        <div id="editUserDateDisplay" style="color: #64748b; font-size: 0.75rem; margin-top: 2px;"></div>
                    </div>
                </div>

                <!-- Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="edit-form-group">
                        <label class="edit-form-label">Punch In Time <span style="color: #ef4444;">*</span></label>
                        <input type="time" id="editPunchIn" name="punch_in" class="edit-form-input" required>
                    </div>
                    <div class="edit-form-group">
                        <label class="edit-form-label">Punch Out Time <span style="color: #ef4444;">*</span></label>
                        <input type="time" id="editPunchOut" name="punch_out" class="edit-form-input" required>
                    </div>
                </div>
                
                <!-- Photos -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="edit-form-group">
                        <label class="edit-form-label">Punch In Media</label>
                        <input type="file" name="punch_in_photo" accept="image/*" class="edit-form-input" style="padding: 0.6rem; font-size: 0.75rem;">
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 4px;">Optional. Overwrites existing photo.</div>
                    </div>
                    <div class="edit-form-group">
                        <label class="edit-form-label">Punch Out Media</label>
                        <input type="file" name="punch_out_photo" accept="image/*" class="edit-form-input" style="padding: 0.6rem; font-size: 0.75rem;">
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 4px;">Optional. Overwrites existing photo.</div>
                    </div>
                </div>

                <div class="edit-form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label class="edit-form-label" style="margin-bottom:0;">Work Report <span style="color: #ef4444;">*</span></label>
                        <span id="editWordCounter" style="font-size: 0.72rem; color: #ef4444; font-weight: 600;">0 / 20 Words Minimum</span>
                    </div>
                    <textarea id="editWorkReport" name="work_report" class="edit-form-input" rows="4" placeholder="Enter work summary or supervisor override notes..." style="resize: vertical;" required oninput="countWorkReportWords()"></textarea>
                </div>

                <!-- Submit Box -->
                <div style="margin-top: 1.5rem;">
                    <button type="submit" id="editSubmitBtn" class="edit-btn-primary">
                        <i data-lucide="save" style="width:16px;height:16px;"></i> Save Record
                    </button>
                    <div id="editFormMessage" style="text-align: center; margin-top: 0.75rem; font-size: 0.8rem; font-weight: 500; display: none;"></div>
                </div>

            </form>

        </div>
    </div>
</div>
