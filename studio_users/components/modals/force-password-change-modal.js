(function () {
  function $(id) { return document.getElementById(id); }
  function isValidAlphanumericPassword(pwd) {
    // At least 8 chars, must contain at least one letter and one number, only letters/numbers.
    return /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/.test(pwd);
  }

  async function checkRequired() {
    try {
      const res = await fetch('api/check_password_reset_required.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data || !data.success) {
        return { required: false, reason: null, maxAgeDays: 90 };
      }
      return {
        required: !!data.required,
        reason: data.reason || null,
        maxAgeDays: Number(data.max_age_days || 90)
      };
    } catch (_) {
      return { required: false, reason: null, maxAgeDays: 90 };
    }
  }

  function setSubtitle(reason, maxAgeDays) {
    const sub = $('fpcmSub');
    if (!sub) return;

    if (reason === 'age_policy') {
      sub.textContent = `For security, passwords must be updated every ${maxAgeDays} days. Please change your password to continue.`;
      return;
    }

    sub.textContent = 'For security, you must change your password before continuing.';
  }

  function show() {
    const overlay = $('forcePasswordChangeOverlay');
    if (!overlay) return;
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('fpcm-locked');

    // Focus first input
    setTimeout(() => {
      $('fpcmCurrent')?.focus();
    }, 50);

    // Block escape key
    window.addEventListener('keydown', blockEscape, true);
  }

  function hide() {
    const overlay = $('forcePasswordChangeOverlay');
    if (!overlay) return;
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('fpcm-locked');
    window.removeEventListener('keydown', blockEscape, true);
  }

  function blockEscape(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
    }
  }

  function setError(msg) {
    const el = $('fpcmError');
    if (!el) return;
    if (!msg) {
      el.style.display = 'none';
      el.textContent = '';
      return;
    }
    el.style.display = 'block';
    el.textContent = msg;
  }

  async function submit(form) {
    const btn = $('fpcmSubmit');
    setError('');

    const current = $('fpcmCurrent')?.value || '';
    const next = $('fpcmNew')?.value || '';
    const confirm = $('fpcmConfirm')?.value || '';

    if (!current || !next || !confirm) {
      setError('All fields are required.');
      return;
    }
    if (next !== confirm) {
      setError('New password and confirm password do not match.');
      return;
    }
    if (!isValidAlphanumericPassword(next)) {
      setError('Password must be alphanumeric and at least 8 characters.');
      return;
    }

    try {
      if (btn) btn.disabled = true;

      const fd = new FormData(form);
      const res = await fetch('api/change_password.php', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();

      if (!data || data.status !== 'success') {
        setError((data && data.message) ? data.message : 'Failed to update password.');
        return;
      }

      // Re-check requirement; only hide once cleared.
      const status = await checkRequired();
      if (status.required) {
        setError('Password update failed to unlock the account. Please try again.');
        return;
      }

      hide();
      // Optional: reload so the app boots with fresh state
      window.location.reload();

    } catch (e) {
      setError('Network error. Please try again.');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const status = await checkRequired();
    if (!status.required) return;

    setSubtitle(status.reason, status.maxAgeDays);

    show();

    const form = $('forcePasswordChangeForm');
    if (form && !form.dataset.bound) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        submit(form);
      });
      form.dataset.bound = '1';
    }

    // Prevent clicking overlay to close
    $('forcePasswordChangeOverlay')?.addEventListener('click', (e) => {
      // Swallow background clicks
      if (e.target && e.target.id === 'forcePasswordChangeOverlay') {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  });
})();
