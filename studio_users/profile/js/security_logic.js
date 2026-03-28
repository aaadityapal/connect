/**
 * Security Tab Logic
 * Handles password changes, visibility toggles, and generation.
 */

// 1. Password Visibility Toggle
document.addEventListener('click', function(e) {
    const toggleBtn = e.target.closest('.toggle-password');
    if (toggleBtn) {
        const input = toggleBtn.previousElementSibling;
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.textContent = '🔒'; // Icon to hide
            } else {
                input.type = 'password';
                toggleBtn.textContent = '👁️'; // Icon to show
            }
        }
    }
});

/**
 * Generates a random strong password and populates the fields
 */
window.generateStrongPassword = function() {
    const caps = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const smalls = "abcdefghijklmnopqrstuvwxyz";
    const nums = "0123456789";
    const specials = "!@#$%^&*";
    const all = caps + smalls + nums + specials;
    
    let pass = "";
    pass += caps[Math.floor(Math.random() * caps.length)];
    pass += smalls[Math.floor(Math.random() * smalls.length)];
    pass += nums[Math.floor(Math.random() * nums.length)];
    pass += specials[Math.floor(Math.random() * specials.length)];
    
    for (let i = 4; i < 12; i++) {
        pass += all[Math.floor(Math.random() * all.length)];
    }
    
    // Shuffle
    pass = pass.split('').sort(() => 0.5 - Math.random()).join('');
    
    const newPassInput = document.getElementById('newPassword');
    const confirmPassInput = document.getElementById('confirmPassword');
    
    if (newPassInput && confirmPassInput) {
        newPassInput.value = pass;
        confirmPassInput.value = pass;
        
        // Temporarily reveal to user
        newPassInput.type = 'text';
        confirmPassInput.type = 'text';
        
        setTimeout(() => {
            newPassInput.type = 'password';
            confirmPassInput.type = 'password';
        }, 5000);
        
        if (typeof showToast === 'function') {
            showToast("Strong password generated and applied!");
        }
    }
};

/**
 * Initialize Form Submission for Security Tab
 */
function initSecurityTab() {
    // Note: We use a delegate or wait for the form to be available in the DOM
    const form = document.getElementById('changePasswordForm');
    if (!form) {
        // If not found yet (tabs are dynamic), we'll try again when tab changes
        return;
    }

    // Remove any existing listeners to prevent doubles
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const currentPass = document.getElementById('currentPassword').value;
        const newPass     = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;
        const alertBox    = document.getElementById('changePasswordAlert');
        const submitBtn   = document.getElementById('changePasswordBtn');

        if (newPass !== confirmPass) {
            alert("New passwords do not match!");
            return;
        }

        // Strong Password Validation: 8+ chars, 1 Capital, 1 Number
        const strongRegex = /^(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\W]{8,}$/;
        if (!strongRegex.test(newPass)) {
            alert("New password must be at least 8 characters long and include at least one uppercase letter and one number.");
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
        }

        try {
            const formData = new FormData();
            formData.append('current_password', currentPass);
            formData.append('new_password',     newPass);
            formData.append('confirm_password', confirmPass);

            const response = await fetch('../api/change_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                if (typeof showToast === 'function') showToast("Success! Your password has been changed.");
                newForm.reset();
                if (typeof initActivityLog === 'function') initActivityLog(); // Refresh log
                
                if (alertBox) {
                    alertBox.style.display = 'block';
                    alertBox.style.background = '#f0fdf4';
                    alertBox.style.color = '#15803d';
                    alertBox.style.padding = '12px';
                    alertBox.style.borderRadius = '8px';
                    alertBox.textContent = result.message;
                }
            } else {
                if (alertBox) {
                    alertBox.style.display = 'block';
                    alertBox.style.background = '#fef2f2';
                    alertBox.style.color = '#b91c1c';
                    alertBox.style.padding = '12px';
                    alertBox.style.borderRadius = '8px';
                    alertBox.textContent = result.message;
                } else {
                    alert(result.message);
                }
            }
        } catch (err) {
            console.error("Security update error:", err);
            alert("An error occurred. Please try again.");
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Password';
            }
        }
    });
}

// Export to window so it can be re-initialized when tab switches
window.initSecurityTab = initSecurityTab;

// --- Password Reset Verification Modal Logic ---
function maskEmail(email) {
    if (!email || !email.includes('@')) return "********@****.***";
    const [user, domain] = email.split('@');
    const parts = domain.split('.');
    const ext = parts.pop();
    const dom = parts.join('.');
    
    const mUser = user.length > 2 ? user[0] + "*".repeat(user.length - 2) + user[user.length-1] : user[0] + "*".repeat(3);
    const mDom = dom.length > 2 ? dom[0] + "*".repeat(dom.length - 2) + dom[dom.length-1] : dom[0] + "*".repeat(3);
    
    return `${mUser}@${mDom}.${ext}`;
}

document.addEventListener('click', async function(e) {
    if (e.target.id === 'ag-trigger-password-reset') {
        const modal = document.getElementById('ag-password-reset-modal');
        const hint = document.getElementById('ag-reset-email-hint');
        
        if (modal) {
            modal.classList.add('ag-active');
            if (hint) hint.innerText = "Loading hint...";
            
            // Clear alert
            const alertBox = document.getElementById('ag-reset-modal-alert');
            if (alertBox) { alertBox.style.display = 'none'; alertBox.innerText = ''; }

            // Fetch and mask email for hint
            try {
                const response = await fetch('../api/get_user_info.php');
                const result = await response.json();
                if (result.status === 'success' && result.data.email) {
                    if (hint) hint.innerText = `Hint: ${maskEmail(result.data.email)}`;
                }
            } catch (err) { if (hint) hint.innerText = ""; }
        }
    }
    
    if (e.target.id === 'ag-close-password-reset' || e.target.classList.contains('ag-modal-overlay')) {
        const modal = document.getElementById('ag-password-reset-modal');
        if (modal && e.target.id !== 'ag-submit-password-reset') {
            modal.classList.remove('ag-active');
        }
    }
});

// --- Password Reset OTP & Verification Logic ---
let resetStep = 1; // 1: Email, 2: OTP, 3: New Password
let resendTimer = null;
let resendSeconds = 30;

function startResendCountdown() {
    const resendBtn = document.getElementById('ag-resend-otp-btn');
    const timerSpan = document.getElementById('ag-resend-timer');
    if (!resendBtn || !timerSpan) return;

    resendSeconds = 30;
    resendBtn.disabled = true;
    resendBtn.style.color = '#94a3b8';
    resendBtn.style.cursor = 'not-allowed';
    
    if (resendTimer) clearInterval(resendTimer);
    
    resendTimer = setInterval(() => {
        resendSeconds--;
        timerSpan.innerText = `(${resendSeconds}s)`;
        
        if (resendSeconds <= 0) {
            clearInterval(resendTimer);
            resendBtn.disabled = false;
            resendBtn.style.color = '#2563eb';
            resendBtn.style.cursor = 'pointer';
            timerSpan.innerText = "";
        }
    }, 1000);
}

document.getElementById('ag-resend-otp-btn')?.addEventListener('click', async function() {
    if (this.disabled) return;
    
    const emailInput = document.getElementById('ag-reset-verify-email');
    const alertBox = document.getElementById('ag-reset-modal-alert');
    const email = emailInput?.value.trim();

    this.disabled = true;
    this.innerText = "Resending...";

    try {
        const formData = new FormData();
        formData.append('email', email);
        const response = await fetch('../api/send_reset_otp.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#f0fdf4';
                alertBox.style.color = '#15803d';
                alertBox.innerText = "New OTP sent!";
            }
            this.innerHTML = 'Resend Code <span id="ag-resend-timer"></span>';
            startResendCountdown();
        } else {
            throw new Error(result.message);
        }
    } catch (err) {
        if (alertBox) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#fef2f2';
            alertBox.style.color = '#b91c1c';
            alertBox.innerText = err.message;
        }
        this.innerHTML = 'Resend Code <span id="ag-resend-timer"></span>';
        this.disabled = false;
    }
});

document.getElementById('ag-submit-password-reset')?.addEventListener('click', async function() {
    const emailInput = document.getElementById('ag-reset-verify-email');
    const otpInput = document.getElementById('ag-reset-otp-code');
    const newPwdInput = document.getElementById('ag-reset-new-password');
    const confirmPwdInput = document.getElementById('ag-reset-confirm-password');
    const alertBox = document.getElementById('ag-reset-modal-alert');
    const submitBtn = this;
    const step1 = document.getElementById('ag-reset-step-1');
    const step2 = document.getElementById('ag-reset-step-2');
    const step3 = document.getElementById('ag-reset-step-3');

    const showAlert = (msg, isError = true) => {
        if (!alertBox) return;
        alertBox.style.display = 'block';
        alertBox.style.background = isError ? '#fef2f2' : '#f0fdf4';
        alertBox.style.color = isError ? '#b91c1c' : '#15803d';
        alertBox.style.border = `1px solid ${isError ? '#fee2e2' : '#dcfce7'}`;
        alertBox.innerText = msg;
    };

    if (resetStep === 1) {
        // --- STEP 1: Verify Email & Send OTP ---
        const email = emailInput?.value.trim();
        if (!email) { showAlert("Please enter your registered email."); return; }

        submitBtn.disabled = true;
        submitBtn.innerText = "Sending OTP...";

        try {
            const formData = new FormData();
            formData.append('email', email);

            const response = await fetch('../api/send_reset_otp.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                resetStep = 2;
                if (step1) step1.style.display = 'none';
                if (step2) step2.style.display = 'block';
                
                // Show where the email was sent
                const sentToSpan = document.getElementById('ag-reset-sent-to');
                if (sentToSpan) sentToSpan.innerText = maskEmail(email);

                showAlert(result.message, false);
                submitBtn.innerText = "Verify Code";
                startResendCountdown();
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            showAlert(err.message);
        } finally {
            submitBtn.disabled = false;
        }
    } else if (resetStep === 2) {
        // --- STEP 2: Verify OTP ---
        const otp = otpInput?.value.trim();
        const email = emailInput?.value.trim();

        if (!otp || otp.length < 6) { showAlert("Please enter the 6-digit OTP."); return; }

        submitBtn.disabled = true;
        submitBtn.innerText = "Verifying...";

        try {
            const formData = new FormData();
            formData.append('otp', otp);
            formData.append('email', email);

            const response = await fetch('../api/verify_reset_otp.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                resetStep = 3;
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'block';
                if (resendTimer) clearInterval(resendTimer);
                showAlert("Identity verified! Now set your new password.", false);
                submitBtn.innerText = "Complete Reset";
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            showAlert(err.message);
        } finally {
            submitBtn.disabled = false;
        }
    } else if (resetStep === 3) {
        // --- STEP 3: Reset Password ---
        const newPwd = newPwdInput?.value;
        const confirmPwd = confirmPwdInput?.value;

        if (!newPwd || !confirmPwd) { showAlert("Both password fields are required."); return; }
        if (newPwd !== confirmPwd) { showAlert("Passwords do not match."); return; }
        
        // Frontend validation for rules
        const strongRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
        if (!strongRegex.test(newPwd)) {
            showAlert("Password must be 8+ chars and include a capital letter and a number.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = "Updating...";

        try {
            const formData = new FormData();
            formData.append('new_password', newPwd);
            formData.append('confirm_password', confirmPwd);

            const response = await fetch('../api/reset_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                showAlert("Password updated! Logging out...", false);
                setTimeout(() => {
                    window.location.href = '../../logout.php';
                }, 1500);
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            showAlert(err.message);
        } finally {
            submitBtn.disabled = false;
        }
    }
});

// Toggle password visibility in Step 3
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('ag-toggle-reset-pwd')) {
        const input = e.target.previousElementSibling;
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        e.target.classList.toggle('bi-eye');
        e.target.classList.toggle('bi-eye-slash');
    }
});

// Update modal close to reset steps
document.getElementById('ag-close-password-reset')?.addEventListener('click', () => {
    resetStep = 1;
    if (resendTimer) clearInterval(resendTimer);
    document.getElementById('ag-reset-step-1').style.display = 'block';
    document.getElementById('ag-reset-step-2').style.display = 'none';
    document.getElementById('ag-reset-step-3').style.display = 'none';
    const btn = document.getElementById('ag-submit-password-reset');
    if (btn) btn.innerText = "Send OTP";
    const alertBox = document.getElementById('ag-reset-modal-alert');
    if (alertBox) alertBox.style.display = 'none';
});

// Keyboard support for Enter key
document.querySelectorAll('#ag-password-reset-modal input').forEach(input => {
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('ag-submit-password-reset')?.click();
        }
    });
});

// Run on load
// Note: This is now manually initialized by script.js after dynamic tab loading
// document.addEventListener('DOMContentLoaded', initSecurityTab);
