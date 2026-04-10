<!-- Universal Response/Notification Modal -->
<div id="responseModal" class="modal-overlay" style="display: none; z-index: 9999;">
    <div class="modal-content" style="max-width: 420px; text-align: center;">
        <div class="modal-body" style="padding: 3rem 2rem;">
            
            <!-- Success State Icon -->
            <div id="resIconSuccess" class="res-icon-wrap success" style="display: none;">
                <div class="icon-circle">
                    <i data-lucide="check-circle-2"></i>
                </div>
            </div>

            <!-- Error State Icon -->
            <div id="resIconError" class="res-icon-wrap error" style="display: none;">
                <div class="icon-circle">
                    <i data-lucide="alert-circle"></i>
                </div>
            </div>

            <!-- Warning State Icon -->
            <div id="resIconWarning" class="res-icon-wrap warning" style="display: none;">
                <div class="icon-circle">
                    <i data-lucide="alert-triangle"></i>
                </div>
            </div>

            <h2 id="resTitle" class="res-title">Notification</h2>
            <p id="resMessage" class="res-message">Operation completed successfully.</p>
            
            <div style="margin-top: 2.5rem;">
                <button type="button" class="btn-primary res-btn" onclick="closeResponseModal()">
                    Understood
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.res-icon-wrap {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: center;
}

.res-icon-wrap .icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.res-icon-wrap .icon-circle i {
    width: 40px;
    height: 40px;
}

/* Success Colors */
.res-icon-wrap.success .icon-circle {
    background: #ecfdf5;
    color: #10b981;
    border: 4px solid #f0fdf4;
}

/* Error Colors */
.res-icon-wrap.error .icon-circle {
    background: #fef2f2;
    color: #ef4444;
    border: 4px solid #fff1f1;
}

/* Warning Colors */
.res-icon-wrap.warning .icon-circle {
    background: #fffbeb;
    color: #f59e0b;
    border: 4px solid #fffaf0;
}

.res-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 0.75rem;
}

.res-message {
    font-size: 0.95rem;
    color: #64748b;
    line-height: 1.6;
}

.res-btn {
    width: 100%;
    padding: 12px !important;
    font-size: 1rem !important;
    border-radius: 12px !important;
}
</style>
