(function () {
  function $(id) { return document.getElementById(id); }

  async function checkRequired() {
    try {
      const res = await fetch('api/check_password_reset_required.php', { cache: 'no-store' });
      const data = await res.json();
      return !!(data && data.success && data.required);
    } catch (_) {
      return false;
    }
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
      const stillRequired = await checkRequired();
      if (stillRequired) {
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
    const required = await checkRequired();
    if (!required) return;

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
