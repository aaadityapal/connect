/**
 * Manager Password Update Modal - Unique version
 * Handles password update functionality for managers
 */

// Execute when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize password toggles
    initPasswordTogglesMgr();
    
    // Initialize password validation
    initPasswordValidationMgr();
    
    // Initialize form submission
    initFormSubmissionMgr();
});

/**
 * Initialize password toggle functionality
 */
function initPasswordTogglesMgr() {
    // Get jQuery instance - use jQuery if available, otherwise use $ if it exists
    const jq = (typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined' ? $ : null);
    
    // If jQuery is available, use it
    if (jq) {
        jq('.toggle-password-mgr').on('click', function() {
            const targetId = jq(this).data('target');
            const input = jq('#' + targetId);
            const icon = jq(this).find('i');
            
            // Toggle password visibility
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    } else {
        // Fallback to vanilla JS
        document.querySelectorAll('.toggle-password-mgr').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                // Toggle password visibility
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }
}

/**
 * Initialize password validation
 */
function initPasswordValidationMgr() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrengthBar = document.querySelector('.mgr-progress-indicator');
    
    if (!newPasswordInput || !confirmPasswordInput || !passwordStrengthBar) return;
    
    // Requirements check
    const requirements = {
        length: { regex: /.{8,}/, element: document.querySelector('.requirement-mgr[data-requirement="length"]') },
        uppercase: { regex: /[A-Z]/, element: document.querySelector('.requirement-mgr[data-requirement="uppercase"]') },
        lowercase: { regex: /[a-z]/, element: document.querySelector('.requirement-mgr[data-requirement="lowercase"]') },
        number: { regex: /[0-9]/, element: document.querySelector('.requirement-mgr[data-requirement="number"]') },
        special: { regex: /[^A-Za-z0-9]/, element: document.querySelector('.requirement-mgr[data-requirement="special"]') },
        match: { 
            check: () => newPasswordInput.value === confirmPasswordInput.value && newPasswordInput.value !== '',
            element: document.querySelector('.requirement-mgr[data-requirement="match"]')
        }
    };
    
    // Function to update requirement status
    function updateRequirement(requirement, isValid) {
        if (!requirement.element) return;
        
        const icon = requirement.element.querySelector('i');
        if (!icon) return;
        
        if (isValid) {
            icon.className = 'fas fa-check-circle text-success';
        } else {
            icon.className = 'fas fa-times-circle text-danger';
        }
    }
    
    // Function to check password strength
    function checkPasswordStrength() {
        const password = newPasswordInput.value;
        let strength = 0;
        let validRequirements = 0;
        
        // Check each requirement
        for (const key in requirements) {
            if (key === 'match') continue; // Skip match check for strength calculation
            
            const req = requirements[key];
            const isValid = req.regex ? req.regex.test(password) : req.check();
            
            if (isValid) {
                validRequirements++;
            }
            
            updateRequirement(req, isValid);
        }
        
        // Calculate strength based on valid requirements
        strength = (validRequirements / 5) * 100;
        
        // Update strength bar
        passwordStrengthBar.style.width = strength + '%';
        
        // Force repaint to ensure color change is visible
        passwordStrengthBar.offsetHeight;
        
        // Update color based on strength
        if (strength < 40) {
            passwordStrengthBar.className = 'mgr-progress-indicator bg-danger';
        } else if (strength < 60) {
            passwordStrengthBar.className = 'mgr-progress-indicator bg-warning';
        } else if (strength < 80) {
            passwordStrengthBar.className = 'mgr-progress-indicator bg-info';
        } else {
            passwordStrengthBar.className = 'mgr-progress-indicator bg-success';
        }
        
        return validRequirements;
    }
    
    // Function to check if passwords match
    function checkPasswordsMatch() {
        const isMatch = requirements.match.check();
        updateRequirement(requirements.match, isMatch);
        return isMatch;
    }
    
    // Add event listeners
    newPasswordInput.addEventListener('input', checkPasswordStrength);
    confirmPasswordInput.addEventListener('input', checkPasswordsMatch);
}

/**
 * Initialize form submission
 */
function initFormSubmissionMgr() {
    const form = document.getElementById('managerPwdUpdateForm');
    const errorElement = document.getElementById('password-error-mgr');
    
    if (!form || !errorElement) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form values
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Validate passwords
        if (!currentPassword) {
            showErrorMgr('Please enter your current password');
            return;
        }
        
        if (!newPassword) {
            showErrorMgr('Please enter a new password');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showErrorMgr('New passwords do not match');
            return;
        }
        
        // Check password requirements
        const requirements = {
            length: { regex: /.{8,}/, message: 'Password must be at least 8 characters' },
            uppercase: { regex: /[A-Z]/, message: 'Password must contain at least one uppercase letter' },
            lowercase: { regex: /[a-z]/, message: 'Password must contain at least one lowercase letter' },
            number: { regex: /[0-9]/, message: 'Password must contain at least one number' },
            special: { regex: /[^A-Za-z0-9]/, message: 'Password must contain at least one special character' }
        };
        
        for (const key in requirements) {
            const req = requirements[key];
            if (!req.regex.test(newPassword)) {
                showErrorMgr(req.message);
                return;
            }
        }
        
        // Hide error message
        errorElement.style.display = 'none';
        
        // Submit form data
        submitPasswordChangeMgr(currentPassword, newPassword);
    });
}

/**
 * Show error message
 */
function showErrorMgr(message) {
    const errorElement = document.getElementById('password-error-mgr');
    if (!errorElement) return;
    
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    
    // Reset animation to trigger it again
    errorElement.style.animation = 'none';
    errorElement.offsetHeight; // Force reflow
    errorElement.style.animation = 'shake 0.5s';
}

/**
 * Submit password change to server
 */
function submitPasswordChangeMgr(currentPassword, newPassword) {
    // Get jQuery instance
    const jq = (typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined' ? $ : null);
    
    // Update button to show loading state
    const submitButton = document.querySelector('.btn-update-mgr');
    if (submitButton) {
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitButton.disabled = true;
    }
    
    // Prepare data
    const data = {
        action: 'update_password',
        current_password: currentPassword,
        new_password: newPassword
    };
    
    // Use jQuery AJAX if available
    if (jq) {
        jq.ajax({
            url: 'password_change_handler.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                handlePasswordUpdateResponseMgr(response);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showErrorMgr('Server error. Please try again later.');
                
                // Reset button
                if (submitButton) {
                    submitButton.innerHTML = 'Update Password';
                    submitButton.disabled = false;
                }
            }
        });
    } else {
        // Fallback to fetch API
        fetch('password_change_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(data => {
            handlePasswordUpdateResponseMgr(data);
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMgr('Server error. Please try again later.');
            
            // Reset button
            if (submitButton) {
                submitButton.innerHTML = 'Update Password';
                submitButton.disabled = false;
            }
        });
    }
}

/**
 * Handle password update response from server
 */
function handlePasswordUpdateResponseMgr(response) {
    const submitButton = document.querySelector('.btn-update-mgr');
    
    if (response.success) {
        // Store in sessionStorage that password was updated
        sessionStorage.setItem('password_updated', 'true');
        sessionStorage.setItem('password_update_time', new Date().getTime());
        
        // Hide modal
        const jq = (typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined' ? $ : null);
        
        if (jq) {
            // Using jQuery
            jq('#managerPwdUpdateModal').modal('hide');
        } else {
            // Using vanilla JS with Bootstrap 5 API if available
            const modalElement = document.getElementById('managerPwdUpdateModal');
            if (modalElement && typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
        
        // Show success message
        alert('Password updated successfully!');
        
        // Reload page after a short delay
        setTimeout(function() {
            window.location.reload();
        }, 500);
    } else {
        // Show error message
        showErrorMgr(response.message || 'Failed to update password');
        
        // Reset button
        if (submitButton) {
            submitButton.innerHTML = 'Update Password';
            submitButton.disabled = false;
        }
    }
}
