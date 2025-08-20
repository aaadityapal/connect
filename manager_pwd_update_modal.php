<?php
/**
 * Manager Password Update Modal - Unique version of password change modal
 * This modal forces users to update their password when required
 */
?>

<!-- Manager Password Update Modal -->
<div class="modal fade" id="managerPwdUpdateModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="managerPwdUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="managerPwdUpdateModalLabel">Update Your Password</h5>
                <!-- No close button to make it mandatory -->
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0" style="background-color: #fff3cd; color: #856404; border-radius: 6px; padding: 12px; font-size: 0.95rem;">
                    For security reasons, you must update your password to continue using the system.
                </div>
                
                <form id="managerPwdUpdateForm">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required 
                                style="border: 1px solid #ced4da; border-right: none; border-radius: 4px 0 0 4px; padding: 10px 15px; height: auto;">
                            <div class="input-group-append">
                                <button class="toggle-password-mgr" type="button" data-target="current_password" 
                                    style="background: #f8f9fa; border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0; color: #6c757d; padding: 0; display: flex; align-items: center; justify-content: center; min-width: 45px; font-size: 14px; line-height: 1;">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required 
                                style="border: 1px solid #ced4da; border-right: none; border-radius: 4px 0 0 4px; padding: 10px 15px; height: auto;">
                            <div class="input-group-append">
                                <button class="toggle-password-mgr" type="button" data-target="new_password" 
                                    style="background: #f8f9fa; border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0; color: #6c757d; padding: 0; display: flex; align-items: center; justify-content: center; min-width: 45px; font-size: 14px; line-height: 1;">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mgr-progress-container" style="height: 6px; background-color: #e9ecef; border-radius: 3px; margin-top: 10px; overflow: hidden;">
                            <div class="mgr-progress-indicator" style="height: 100%; width: 0%; background-color: #dc3545; transition: width 0.3s ease, background-color 0.3s ease;"></div>
                        </div>
                        
                        <div class="requirements-grid-mgr" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px;">
                            <div class="requirement-mgr" data-requirement="length">
                                <i class="fas fa-times-circle text-danger"></i> 8+ characters
                            </div>
                            <div class="requirement-mgr" data-requirement="uppercase">
                                <i class="fas fa-times-circle text-danger"></i> Uppercase letter
                            </div>
                            <div class="requirement-mgr" data-requirement="lowercase">
                                <i class="fas fa-times-circle text-danger"></i> Lowercase letter
                            </div>
                            <div class="requirement-mgr" data-requirement="number">
                                <i class="fas fa-times-circle text-danger"></i> Number
                            </div>
                            <div class="requirement-mgr" data-requirement="special">
                                <i class="fas fa-times-circle text-danger"></i> Special character
                            </div>
                            <div class="requirement-mgr" data-requirement="match">
                                <i class="fas fa-times-circle text-danger"></i> Passwords match
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                style="border: 1px solid #ced4da; border-right: none; border-radius: 4px 0 0 4px; padding: 10px 15px; height: auto;">
                            <div class="input-group-append">
                                <button class="toggle-password-mgr" type="button" data-target="confirm_password" 
                                    style="background: #f8f9fa; border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0; color: #6c757d; padding: 0; display: flex; align-items: center; justify-content: center; min-width: 45px; font-size: 14px; line-height: 1;">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="password-error-mgr" class="alert alert-danger" style="display: none; font-size: 0.9rem; padding: 8px 12px; margin-top: 10px; animation: shake 0.5s;"></div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn-update-mgr" style="background: #1e2a78; border: none; color: white; padding: 10px 25px; font-weight: 500; border-radius: 4px; transition: all 0.2s ease;">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Unique styles for Manager Password Update Modal */
#managerPwdUpdateModal .modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
}

#managerPwdUpdateModal .modal-header {
    background-color: #1e2a78;
    color: white;
    border-radius: 8px 8px 0 0;
    padding: 15px 20px;
}

#managerPwdUpdateModal .modal-body {
    padding: 20px;
}

#managerPwdUpdateModal .form-group label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

#managerPwdUpdateModal .btn-update-mgr:hover {
    background: #151d54;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

#managerPwdUpdateModal .toggle-password-mgr:hover {
    background-color: #e9ecef;
    color: #495057;
}

#managerPwdUpdateModal .requirement-mgr {
    font-size: 0.85rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 5px;
}

#managerPwdUpdateModal .requirement-mgr i {
    font-size: 0.9rem;
}

/* Progress indicator colors with higher specificity */
#managerPwdUpdateModal .mgr-progress-indicator.bg-danger {
    background-color: #dc3545 !important;
}

#managerPwdUpdateModal .mgr-progress-indicator.bg-warning {
    background-color: #ffc107 !important;
}

#managerPwdUpdateModal .mgr-progress-indicator.bg-info {
    background-color: #17a2b8 !important;
}

#managerPwdUpdateModal .mgr-progress-indicator.bg-success {
    background-color: #28a745 !important;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

#password-error-mgr {
    border-left: 4px solid #dc3545;
}
</style>

<!-- Ensure Font Awesome is loaded -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
