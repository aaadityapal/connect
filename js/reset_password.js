/**
 * Reset Password Modal Functionality
 * This script handles the display and functionality of the password reset modal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if URL has reset password parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('reset_password')) {
        showResetModal();
    }

    // Add click event to "Forgot Password" link
    const forgotPasswordLink = document.getElementById('forgot-password-link');
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            showResetModal();
        });
    }

    // Function to show the reset password modal
    function showResetModal() {
        // Create modal backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
        
        // Create modal container
        const modalContainer = document.createElement('div');
        modalContainer.className = 'modal fade show';
        modalContainer.id = 'resetPasswordModal';
        modalContainer.style.display = 'block';
        modalContainer.setAttribute('tabindex', '-1');
        modalContainer.setAttribute('aria-labelledby', 'resetPasswordModalLabel');
        modalContainer.setAttribute('aria-hidden', 'false');
        
        // Load the reset password content
        fetch('reset_password_modal.php')
            .then(response => response.text())
            .then(html => {
                // Extract the body content from the HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const resetContainer = doc.querySelector('.reset-container');
                
                if (resetContainer) {
                    // Create modal dialog
                    const modalDialog = document.createElement('div');
                    modalDialog.className = 'modal-dialog modal-dialog-centered';
                    modalDialog.innerHTML = `
                        <div class="modal-content border-0" style="background: transparent; box-shadow: none;">
                            <div class="modal-header border-0 p-0">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 15px; top: 15px; z-index: 10; color: var(--dark-gray); opacity: 0.7;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-0">
                                ${resetContainer.outerHTML}
                            </div>
                        </div>
                    `;
                    
                    modalContainer.appendChild(modalDialog);
                    document.body.appendChild(modalContainer);
                    
                    // Add event listener to close button
                    const closeButton = modalContainer.querySelector('.close');
                    if (closeButton) {
                        closeButton.addEventListener('click', closeResetModal);
                    }
                    
                    // Close modal when clicking outside
                    modalContainer.addEventListener('click', function(e) {
                        if (e.target === modalContainer) {
                            closeResetModal();
                        }
                    });
                    
                    // Initialize the form interactions and animations
                    initResetFormInteractions(modalContainer);
                }
            })
            .catch(error => {
                console.error('Error loading reset password modal:', error);
            });
    }
    
    // Function to close the reset password modal
    function closeResetModal() {
        const modal = document.getElementById('resetPasswordModal');
        const backdrop = document.querySelector('.modal-backdrop');
        
        if (modal) {
            modal.classList.remove('show');
            modal.classList.add('fade');
            setTimeout(() => {
                document.body.removeChild(modal);
            }, 300);
        }
        
        if (backdrop) {
            backdrop.classList.remove('show');
            backdrop.classList.add('fade');
            setTimeout(() => {
                document.body.removeChild(backdrop);
            }, 300);
        }
        
        // Remove the reset_password parameter from URL
        const url = new URL(window.location);
        url.searchParams.delete('reset_password');
        window.history.replaceState({}, '', url);
    }
    
    // Initialize form interactions for the reset modal
    function initResetFormInteractions(container) {
        // Enhanced form interactions
        const formGroups = container.querySelectorAll('.form-group');
        const inputs = container.querySelectorAll('.form-control');

        inputs.forEach(input => {
            // Add active class on focus
            input.addEventListener('focus', function() {
                this.closest('.form-group').classList.add('active');
                
                // Subtle animation
                const group = this.closest('.form-group');
                group.style.transform = 'translateX(5px)';
                setTimeout(() => {
                    group.style.transform = 'translateX(0)';
                }, 300);
            });

            // Remove active class on blur if empty
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.closest('.form-group').classList.remove('active');
                }
            });

            // Check if input has value on load
            if (input.value !== '') {
                input.closest('.form-group').classList.add('active');
            }
        });

        // Button animation
        const resetButtons = container.querySelectorAll('.btn-reset');
        resetButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    this.classList.remove('animate__animated', 'animate__pulse');
                }, 1000);
            });
        });

        // Password strength checker
        const newPassword = container.querySelector('#new_password');
        const passwordStrengthBar = container.querySelector('.password-strength-bar');
        
        if (newPassword && passwordStrengthBar) {
            newPassword.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 1;
                
                // Character variety checks
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update strength bar
                passwordStrengthBar.className = 'password-strength-bar';
                if (strength === 1) passwordStrengthBar.classList.add('strength-weak');
                else if (strength === 2) passwordStrengthBar.classList.add('strength-medium');
                else if (strength === 3) passwordStrengthBar.classList.add('strength-strong');
                else if (strength >= 4) passwordStrengthBar.classList.add('strength-very-strong');
            });
        }
        
        // Password match checker
        const confirmPassword = container.querySelector('#confirm_password');
        const matchIndicator = container.querySelector('.password-match-indicator');
        
        if (confirmPassword && newPassword && matchIndicator) {
            confirmPassword.addEventListener('input', function() {
                if (this.value === newPassword.value) {
                    matchIndicator.textContent = 'Passwords match';
                    matchIndicator.classList.add('match-success');
                    matchIndicator.classList.remove('match-error');
                } else {
                    matchIndicator.textContent = 'Passwords do not match';
                    matchIndicator.classList.add('match-error');
                    matchIndicator.classList.remove('match-success');
                }
            });
        }

        // Simulating the password reset flow (for UI demonstration)
        const sendResetLinkBtn = container.querySelector('#sendResetLink');
        const step1Form = container.querySelector('#step1Form');
        const step2Form = container.querySelector('#step2Form');
        const resetPasswordBtn = container.querySelector('#resetPassword');
        
        if (sendResetLinkBtn && step1Form && step2Form) {
            sendResetLinkBtn.addEventListener('click', function() {
                const email = container.querySelector('#email').value;
                if (email) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success animate__animated animate__fadeIn';
                    successAlert.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Reset link sent! Check your email.';
                    
                    // Insert before the form
                    step1Form.parentNode.insertBefore(successAlert, step1Form);
                    
                    // Hide step 1 and show step 2 after delay
                    setTimeout(() => {
                        step1Form.style.display = 'none';
                        step2Form.style.display = 'block';
                        
                        // Animate step 2 form groups
                        const step2Groups = step2Form.querySelectorAll('.form-group');
                        step2Groups.forEach((group, index) => {
                            group.style.animation = `fadeUp 0.5s ease-out ${0.3 + (index * 0.2)}s forwards`;
                        });
                        
                        // Animate button
                        if (resetPasswordBtn) {
                            resetPasswordBtn.style.animation = `fadeUp 0.5s ease-out ${0.3 + (step2Groups.length * 0.2)}s forwards`;
                        }
                    }, 2000);
                }
            });
        }
        
        if (resetPasswordBtn && step2Form) {
            resetPasswordBtn.addEventListener('click', function() {
                const newPass = container.querySelector('#new_password').value;
                const confirmPass = container.querySelector('#confirm_password').value;
                
                if (newPass && confirmPass && newPass === confirmPass) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success animate__animated animate__fadeIn';
                    successAlert.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Password reset successful! Redirecting to login...';
                    
                    // Insert before the form
                    step2Form.parentNode.insertBefore(successAlert, step2Form);
                    
                    // Hide step 2 form
                    step2Form.style.display = 'none';
                    
                    // Close modal after delay
                    setTimeout(() => {
                        closeResetModal();
                    }, 3000);
                }
            });
        }
    }
});
