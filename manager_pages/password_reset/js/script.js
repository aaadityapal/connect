/* global.js — Password Reset Manager */
'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* ── DOM refs ── */
    const searchInput    = document.getElementById('userSearchInput');
    const roleFilter     = document.getElementById('roleFilter');
    const refreshBtn     = document.getElementById('refreshBtn');
    const modal          = document.getElementById('resetModal');
    const modalSubtitle  = document.getElementById('modalSubtitle');
    const modalCloseBtn  = document.getElementById('modalCloseBtn');
    const cancelBtn      = document.getElementById('cancelBtn');
    const form           = document.getElementById('resetPasswordForm');
    const targetUserIdEl = document.getElementById('targetUserId');
    const newPwEl        = document.getElementById('newPassword');
    const confirmPwEl    = document.getElementById('confirmPassword');
    const strengthFill   = document.getElementById('strengthFill');
    const strengthLabel  = document.getElementById('strengthLabel');
    const matchLabel     = document.getElementById('matchLabel');
    const confirmBtn     = document.getElementById('confirmResetBtn');
    const toast          = document.getElementById('toast');
    const suggestPwBtn   = document.getElementById('suggestPwBtn');
    const copyPwBtn      = document.getElementById('copyPwBtn');

    // Requirement indicators
    const reqLength = document.getElementById('req-length');
    const reqUpper  = document.getElementById('req-upper');
    const reqLower  = document.getElementById('req-lower');
    const reqNum    = document.getElementById('req-num');

    /* ── Lucide init (runs after defer scripts) ── */
    if (typeof lucide !== 'undefined') lucide.createIcons();

    /* ── Filter / Search ── */
    function applyFilters() {
        const q    = searchInput.value.toLowerCase().trim();
        const role = roleFilter.value;
        document.querySelectorAll('.user-row').forEach(row => {
            const matchQ    = row.dataset.username.includes(q) || row.dataset.email.includes(q);
            const matchRole = (role === 'All') || (row.dataset.role === role);
            row.style.display = (matchQ && matchRole) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', applyFilters);
    roleFilter.addEventListener('change', applyFilters);
    refreshBtn.addEventListener('click', () => window.location.reload());

    /* ── Open Modal ── */
    document.querySelectorAll('.btn-reset-action').forEach(btn => {
        btn.addEventListener('click', () => {
            targetUserIdEl.value = btn.dataset.userId;
            modalSubtitle.textContent = `Setting new password for: ${btn.dataset.username}`;
            newPwEl.value      = '';
            confirmPwEl.value  = '';
            resetStrength();
            resetMatch();
            resetRequirements();
            openModal();
        });
    });

    /* ── Modal open / close ── */
    function openModal() {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => newPwEl.focus(), 200);
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
        form.reset();
        resetStrength();
        resetMatch();
        resetRequirements();
        copyPwBtn.style.display = 'none';
    }

    modalCloseBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ── Toggle Password Visibility ── */
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            const isText = target.type === 'text';
            target.type  = isText ? 'password' : 'text';
            // Update icon
            const icon = btn.querySelector('svg');
            if (icon) {
                btn.innerHTML = isText
                    ? '<i data-lucide="eye" style="width:16px;height:16px;"></i>'
                    : '<i data-lucide="eye-off" style="width:16px;height:16px;"></i>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    });

    /* ── Suggest Password ── */
    function generatePassword() {
        const upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower   = 'abcdefghjkmnpqrstuvwxyz';
        const digits  = '23456789';
        const special = '@#$!%&*';
        const all     = upper + lower + digits + special;

        // Guarantee at least one of each required type
        const pick = (src) => src[Math.floor(Math.random() * src.length)];
        let pw = [pick(upper), pick(lower), pick(digits), pick(special)];

        // Fill to 12 chars
        for (let i = pw.length; i < 12; i++) pw.push(pick(all));

        // Shuffle
        for (let i = pw.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pw[i], pw[j]] = [pw[j], pw[i]];
        }
        return pw.join('');
    }

    suggestPwBtn.addEventListener('click', () => {
        const pw = generatePassword();
        newPwEl.value     = pw;
        confirmPwEl.value = pw;
        newPwEl.type      = 'text';
        confirmPwEl.type  = 'text';
        updateStrength(pw);
        updateRequirements(pw);
        checkMatch();
        copyPwBtn.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        newPwEl.focus();
    });

    copyPwBtn.addEventListener('click', () => {
        const pw = newPwEl.value;
        if (!pw) return;
        navigator.clipboard.writeText(pw).then(() => {
            copyPwBtn.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;"></i>';
            copyPwBtn.classList.add('copied');
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                copyPwBtn.innerHTML = '<i data-lucide="copy" style="width:14px;height:14px;"></i>';
                copyPwBtn.classList.remove('copied');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 1800);
        }).catch(() => showToast('Could not copy to clipboard.', 'error'));
    });

    /* ── Password Strength ── */
    function checkStrength(pw) {
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++; // bonus for special char
        return score;
    }

    const strengthConfig = [
        { label: '', color: '', width: '0%' },
        { label: 'Weak',      color: '#ef4444', width: '20%' },
        { label: 'Poor',      color: '#f97316', width: '40%' },
        { label: 'Fair',      color: '#f59e0b', width: '60%' },
        { label: 'Good',      color: '#84cc16', width: '80%' },
        { label: 'Strong',    color: '#10b981', width: '100%' },
    ];

    function updateStrength(pw) {
        const score = checkStrength(pw);
        const cfg   = strengthConfig[Math.min(score, 5)];
        strengthFill.style.width      = pw.length ? cfg.width : '0%';
        strengthFill.style.background = cfg.color;
        strengthLabel.textContent     = pw.length ? cfg.label : '';
        strengthLabel.style.color     = cfg.color;
    }

    function resetStrength() {
        strengthFill.style.width  = '0%';
        strengthLabel.textContent = '';
    }

    function updateRequirements(pw) {
        setReq(reqLength, pw.length >= 8);
        setReq(reqUpper,  /[A-Z]/.test(pw));
        setReq(reqLower,  /[a-z]/.test(pw));
        setReq(reqNum,    /[0-9]/.test(pw));
    }

    function setReq(el, met) {
        if (met) {
            el.classList.add('met');
            el.querySelector('svg')?.remove();
            if (!el.querySelector('svg')) {
                const i = document.createElement('i');
                i.setAttribute('data-lucide', 'check-circle-2');
                i.setAttribute('style', 'width:12px;height:12px;');
                el.prepend(i);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } else {
            el.classList.remove('met');
        }
    }

    function resetRequirements() {
        [reqLength, reqUpper, reqLower, reqNum].forEach(el => el.classList.remove('met'));
    }

    newPwEl.addEventListener('input', () => {
        updateStrength(newPwEl.value);
        updateRequirements(newPwEl.value);
        if (confirmPwEl.value) checkMatch();
    });

    /* ── Password Match ── */
    function checkMatch() {
        if (!confirmPwEl.value) { matchLabel.textContent = ''; return; }
        if (newPwEl.value === confirmPwEl.value) {
            matchLabel.textContent = '✓ Passwords match';
            matchLabel.className   = 'match-label match-ok';
        } else {
            matchLabel.textContent = '✗ Passwords do not match';
            matchLabel.className   = 'match-label match-err';
        }
    }

    function resetMatch() {
        matchLabel.textContent = '';
        matchLabel.className   = 'match-label';
    }

    confirmPwEl.addEventListener('input', checkMatch);

    /* ── Form Submit ── */
    form.addEventListener('submit', async e => {
        e.preventDefault();

        const userId  = targetUserIdEl.value;
        const newPw   = newPwEl.value.trim();
        const confPw  = confirmPwEl.value.trim();

        // Validations
        if (!newPw || !confPw) {
            showToast('Please fill in both password fields.', 'error');
            return;
        }
        if (newPw !== confPw) {
            showToast('Passwords do not match.', 'error');
            return;
        }
        if (newPw.length < 8) {
            showToast('Password must be at least 8 characters.', 'error');
            return;
        }
        if (!/[A-Z]/.test(newPw)) {
            showToast('Password needs at least one uppercase letter.', 'error');
            return;
        }
        if (!/[a-z]/.test(newPw)) {
            showToast('Password needs at least one lowercase letter.', 'error');
            return;
        }
        if (!/[0-9]/.test(newPw)) {
            showToast('Password needs at least one number.', 'error');
            return;
        }

        // Submit
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i data-lucide="loader-circle" style="width:16px;height:16px;"></i> Resetting...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const fd = new FormData();
            fd.append('user_id',      userId);
            fd.append('new_password', newPw);

            const res  = await fetch('api/reset_password.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                showToast('Password reset successfully!', 'success');
                closeModal();
            } else {
                showToast(data.message || 'Failed to reset password.', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'error');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i data-lucide="check-circle" style="width:16px;height:16px;"></i> Reset Password';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });

    /* ── Toast ── */
    let toastTimer;
    function showToast(msg, type = 'success') {
        clearTimeout(toastTimer);
        toast.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
        toast.className   = `toast toast-${type} show`;
        toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 3500);
    }
});
