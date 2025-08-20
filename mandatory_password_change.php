<?php
// This file contains the mandatory password change modal
// It should be included in all dashboard files
?>

<!-- Make sure Font Awesome is loaded -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Mandatory Password Change Modal -->
<div class="modal fade" id="mandatoryPasswordChangeModal" data-backdrop="static" data-bs-backdrop="static" data-keyboard="false" data-bs-keyboard="false" tabindex="-1" aria-labelledby="mandatoryPasswordChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mandatoryPasswordChangeModalLabel">
                    Password Update Required
                </h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0">
                    For security reasons, you must change your password before continuing.
                </div>
                
                <form id="mandatoryPasswordChangeForm">
                    <div class="form-group mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn toggle-password" type="button" data-target="current_password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn toggle-password" type="button" data-target="new_password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="pwd-strength-meter mt-2">
                            <div class="pwd-progress-container" style="height: 6px;">
                                <div class="pwd-progress-indicator bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="form-text text-muted mt-1">Password strength: <span id="password-strength-text">Weak</span></small>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small id="password-match" class="form-text mt-1"></small>
                    </div>
                    
                    <div class="password-requirements mb-4">
                        <p class="text-muted mb-2"><small>Password must include:</small></p>
                        <div class="requirements-grid">
                            <div class="req-item"><i class="fas fa-circle requirement" id="req-length"></i> <span>8+ characters</span></div>
                            <div class="req-item"><i class="fas fa-circle requirement" id="req-uppercase"></i> <span>Uppercase</span></div>
                            <div class="req-item"><i class="fas fa-circle requirement" id="req-lowercase"></i> <span>Lowercase</span></div>
                            <div class="req-item"><i class="fas fa-circle requirement" id="req-number"></i> <span>Number</span></div>
                            <div class="req-item"><i class="fas fa-circle requirement" id="req-special"></i> <span>Special char</span></div>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger mt-3" id="password-error" style="display: none;"></div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-update" id="updatePasswordBtn">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for the password change modal */
    #mandatoryPasswordChangeModal .modal-content {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: none;
        background-color: #fff;
    }
    
    #mandatoryPasswordChangeModal .modal-header {
        border-bottom: none;
        padding: 1.5rem 1.5rem 0.5rem;
    }
    
    #mandatoryPasswordChangeModal .modal-title {
        font-weight: 500;
        color: #333;
        font-size: 1.25rem;
    }
    
    #mandatoryPasswordChangeModal .modal-body {
        padding: 1rem 1.5rem 1.5rem;
    }
    
    #mandatoryPasswordChangeModal .alert-warning {
        background-color: #fff8e1;
        color: #856404;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    #mandatoryPasswordChangeModal .form-group label {
        font-weight: 500;
        color: #555;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
    }
    
    #mandatoryPasswordChangeModal .form-control {
        border: 1px solid #e0e0e0;
        border-right: none;
        border-radius: 6px 0 0 6px;
        padding: 0.6rem 0.75rem;
        height: auto;
        transition: all 0.2s;
    }
    
    #mandatoryPasswordChangeModal .form-control:focus {
        border-color: #4f46e5;
        box-shadow: none;
    }
    
    #mandatoryPasswordChangeModal .toggle-password {
        background: transparent;
        border: 1px solid #e0e0e0;
        border-left: none;
        border-radius: 0 6px 6px 0;
        color: #888;
        padding: 0.375rem 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
    }
    
    #mandatoryPasswordChangeModal .toggle-password:hover {
        color: #4f46e5;
        background-color: #f9f9f9;
    }
    
    #mandatoryPasswordChangeModal .toggle-password i {
        font-size: 16px;
        line-height: 1;
    }
    
    #mandatoryPasswordChangeModal .btn-update {
        background-color: #4f46e5;
        border-color: #4f46e5;
        color: white;
        padding: 0.6rem 1rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    #mandatoryPasswordChangeModal .btn-update:hover {
        background-color: #4338ca;
        border-color: #4338ca;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }
    
    .requirement {
        font-size: 6px;
        margin-right: 5px;
        color: #ddd;
    }
    
    .requirement.met {
        color: #4f46e5;
    }
    
    #password-match.text-success {
        color: #4f46e5 !important;
    }
    
    #password-match.text-danger {
        color: #ef4444 !important;
    }
    
    .requirements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.5rem;
    }
    
    .req-item {
        display: flex;
        align-items: center;
        font-size: 0.8rem;
        color: #666;
    }
    
    .req-item span {
        margin-left: 4px;
    }
    
    .pwd-progress-container {
        border-radius: 2px;
        background-color: #f1f5f9;
        width: 100%;
        overflow: hidden;
    }
    
    .pwd-progress-indicator {
        height: 100%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
    
    /* Vibrant indicator colors with increased specificity */
    #mandatoryPasswordChangeModal .pwd-progress-indicator.bg-danger { 
        background-color: #ef4444 !important; 
    }
    #mandatoryPasswordChangeModal .pwd-progress-indicator.bg-warning { 
        background-color: #f59e0b !important; 
    }
    #mandatoryPasswordChangeModal .pwd-progress-indicator.bg-info { 
        background-color: #0ea5e9 !important; 
    }
    #mandatoryPasswordChangeModal .pwd-progress-indicator.bg-primary { 
        background-color: #3b82f6 !important; 
    }
    #mandatoryPasswordChangeModal .pwd-progress-indicator.bg-success { 
        background-color: #10b981 !important; 
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
        20%, 40%, 60%, 80% { transform: translateX(3px); }
    }
    
    .shake {
        animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
    }
    
    #password-error {
        border-radius: 6px;
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        border: none;
    }
</style>
