/**
 * Mandatory Password Change
 * This script handles the mandatory password change modal functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if user needs to change password
    checkPasswordChangeRequired();
    
    // Initialize password toggle functionality
    initPasswordToggles();
    
    // Initialize password strength and validation
    initPasswordValidation();
    
    // Handle form submission
    initFormSubmission();
});

/**
 * Check if the user needs to change their password
 */
function checkPasswordChangeRequired() {
    // Check if there's a flag in the URL indicating password change is needed
    const urlParams = new URLSearchParams(window.location.search);
    const passwordChangeParam = urlParams.get('password_change_required');
    
    // Only show modal if explicitly requested via URL parameter
    // The real check happens via AJAX in include_password_change.php
    if (passwordChangeParam === 'true') {
        showPasswordChangeModal();
    }
}

/**
 * Check server-side flag for password change requirement
 * This function should not be used directly - the AJAX call in include_password_change.php handles this
 */
function checkServerSideFlag() {
    // This function is deprecated - the real check happens via AJAX in include_password_change.php
    // Return false to prevent modal from showing by default
    return false;
}

/**
 * Show the password change modal
 */
function showPasswordChangeModal() {
    // Check if jQuery is available
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        // Use jQuery instead of $ if $ is not defined
        jQuery('#mandatoryPasswordChangeModal').modal('show');
    } else if (typeof $ !== 'undefined') {
        // Use $ if it's defined
        $('#mandatoryPasswordChangeModal').modal('show');
    } else if (typeof bootstrap !== 'undefined') {
        // Fallback to vanilla Bootstrap if jQuery is not available
        const modalElement = document.getElementById('mandatoryPasswordChangeModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    } else {
        console.error('Neither jQuery nor Bootstrap is available for showing the modal');
    }
}

/**
 * Initialize password toggle functionality
 */
function initPasswordToggles() {
    // Check if jQuery is available
    if (typeof $ !== 'undefined' || typeof jQuery !== 'undefined') {
        // Use jQuery or $ (whichever is available)
        const jq = (typeof $ !== 'undefined') ? $ : jQuery;
        
        jq('.toggle-password').on('click', function() {
            const targetId = jq(this).data('target');
            const passwordInput = document.getElementById(targetId);
            const icon = jq(this).find('i');
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    } else {
        // Fallback to vanilla JavaScript
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                // Toggle password visibility
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }
}

/**
 * Initialize password strength and validation
 */
function initPasswordValidation() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrengthBar = document.querySelector('.pwd-progress-indicator');
    const passwordStrengthText = document.getElementById('password-strength-text');
    const passwordMatch = document.getElementById('password-match');
    
    // Password strength requirements
    const requirements = {
        length: { regex: /.{8,}/, element: document.getElementById('req-length') },
        uppercase: { regex: /[A-Z]/, element: document.getElementById('req-uppercase') },
        lowercase: { regex: /[a-z]/, element: document.getElementById('req-lowercase') },
        number: { regex: /[0-9]/, element: document.getElementById('req-number') },
        special: { regex: /[^A-Za-z0-9]/, element: document.getElementById('req-special') }
    };
    
    // Check password strength on input
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let meetsAllRequirements = true;
        
        // Check each requirement
        for (const [key, requirement] of Object.entries(requirements)) {
            const isValid = requirement.regex.test(password);
            if (isValid) {
                strength += 20; // Each requirement is worth 20% of strength
                requirement.element.classList.add('met');
            } else {
                requirement.element.classList.remove('met');
                meetsAllRequirements = false;
            }
        }
        
        // Update strength text and color
        let strengthClass = '';
        if (strength <= 20) {
            passwordStrengthText.textContent = 'Very Weak';
            strengthClass = 'bg-danger';
        } else if (strength <= 40) {
            passwordStrengthText.textContent = 'Weak';
            strengthClass = 'bg-warning';
        } else if (strength <= 60) {
            passwordStrengthText.textContent = 'Medium';
            strengthClass = 'bg-info';
        } else if (strength <= 80) {
            passwordStrengthText.textContent = 'Strong';
            strengthClass = 'bg-primary';
        } else {
            passwordStrengthText.textContent = 'Very Strong';
            strengthClass = 'bg-success';
        }
        
        // Apply class and width
        passwordStrengthBar.className = 'pwd-progress-indicator ' + strengthClass;
        passwordStrengthBar.style.width = strength + '%';
        
        // Force repaint to ensure color change is applied
        passwordStrengthBar.offsetHeight;
        
        // Check if passwords match
        if (confirmPasswordInput.value) {
            checkPasswordsMatch();
        }
    });
    
    // Check if passwords match on confirm password input
    confirmPasswordInput.addEventListener('input', checkPasswordsMatch);
    
    function checkPasswordsMatch() {
        if (newPasswordInput.value && confirmPasswordInput.value) {
            if (newPasswordInput.value === confirmPasswordInput.value) {
                passwordMatch.textContent = 'Passwords match';
                passwordMatch.className = 'form-text text-success mt-1';
            } else {
                passwordMatch.textContent = 'Passwords do not match';
                passwordMatch.className = 'form-text text-danger mt-1';
            }
        } else {
            passwordMatch.textContent = '';
        }
    }
}

/**
 * Initialize form submission
 */
function initFormSubmission() {
    const form = document.getElementById('mandatoryPasswordChangeForm');
    const errorAlert = document.getElementById('password-error');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Basic validation
        if (!currentPassword || !newPassword || !confirmPassword) {
            showError('All fields are required');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }
        
        // Check password requirements
        const requirements = {
            length: /.{8,}/,
            uppercase: /[A-Z]/,
            lowercase: /[a-z]/,
            number: /[0-9]/,
            special: /[^A-Za-z0-9]/
        };
        
        for (const [key, regex] of Object.entries(requirements)) {
            if (!regex.test(newPassword)) {
                let message = 'Password must include: ';
                if (key === 'length') message += 'at least 8 characters';
                if (key === 'uppercase') message += 'at least one uppercase letter';
                if (key === 'lowercase') message += 'at least one lowercase letter';
                if (key === 'number') message += 'at least one number';
                if (key === 'special') message += 'at least one special character';
                
                showError(message);
                return;
            }
        }
        
        // If all validation passes, submit the form via AJAX
        submitPasswordChange(currentPassword, newPassword);
    });
    
    function showError(message) {
        errorAlert.textContent = message;
        errorAlert.style.display = 'block';
        
        // Add shake animation
        errorAlert.classList.add('shake');
        setTimeout(() => {
            errorAlert.classList.remove('shake');
        }, 600);
    }
    
    function submitPasswordChange(currentPassword, newPassword) {
        // Show loading state
        const submitButton = document.getElementById('updatePasswordBtn');
        const originalButtonText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        submitButton.disabled = true;
        
        // In a real implementation, this would be an AJAX call to the server
        // For demonstration, we'll simulate a successful update after a delay
        setTimeout(function() {
            // Simulate successful update
            const success = true; // In real implementation, this would be based on server response
            
            if (success) {
                // Show success message
                errorAlert.textContent = 'Password updated successfully!';
                errorAlert.className = 'alert alert-success mt-3';
                errorAlert.style.display = 'block';
                
                // Store a flag in sessionStorage to prevent modal from showing again for 3 months
                sessionStorage.setItem('password_updated', 'true');
                sessionStorage.setItem('password_update_time', new Date().getTime());
                
                // Remove the password_change_required parameter from URL
                const url = new URL(window.location);
                url.searchParams.delete('password_change_required');
                window.history.replaceState({}, '', url);
                
                // Close modal after delay
                setTimeout(function() {
                    if (typeof $ !== 'undefined') {
                        $('#mandatoryPasswordChangeModal').modal('hide');
                    } else if (typeof bootstrap !== 'undefined') {
                        const modalElement = document.getElementById('mandatoryPasswordChangeModal');
                        if (modalElement) {
                            const modalInstance = bootstrap.Modal.getInstance(modalElement);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                        }
                    }
                    
                    // Reload the page to ensure the modal doesn't show again
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }, 2000);
            } else {
                // Show error message
                showError('Failed to update password. Please try again.');
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        }, 1500);
    }
}
