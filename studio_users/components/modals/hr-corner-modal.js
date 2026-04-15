/**
 * HR Corner Viewer Modal — JavaScript Controller
 * File: components/modals/hr-corner-modal.js
 *
 * Exposes: window.HRCornerModal.open() / .close() / .refresh()
 */
(function () {
    'use strict';

    /* ── DOM references ─────────────────────────────────── */
    const overlay      = document.getElementById('hrCornerViewerModal');
    const backdrop     = document.getElementById('hrcmBackdrop');
    const closeBtn     = document.getElementById('hrcmCloseBtn');
    const tabBtns      = overlay ? overlay.querySelectorAll('.hrcm-tab') : [];
    const policiesPane = document.getElementById('hrcmPoliciesPane');
    const noticesPane  = document.getElementById('hrcmNoticesPane');
    const policiesList = document.getElementById('hrcmPoliciesList');
    const noticesList  = document.getElementById('hrcmNoticesList');
    const policyCountEl = document.getElementById('hrcmPolicyCount');
    const noticeCountEl = document.getElementById('hrcmNoticeCount');

    /* Policy detail */
    const policyDetailEmpty   = document.getElementById('hrcmPolicyDetailEmpty');
    const policyDetailContent = document.getElementById('hrcmPolicyDetailContent');
    const policyBadgeEl       = document.getElementById('hrcmPolicyBadge');
    const policyVersionEl     = document.getElementById('hrcmPolicyVersion');
    const policyTitleEl       = document.getElementById('hrcmPolicyTitle');
    const policyMetaEl        = document.getElementById('hrcmPolicyMeta');
    const policyBodyEl        = document.getElementById('hrcmPolicyBody');
    const policyFooterEl      = document.getElementById('hrcmPolicyFooter');

    /* Notice detail */
    const noticeDetailEmpty   = document.getElementById('hrcmNoticeDetailEmpty');
    const noticeDetailContent = document.getElementById('hrcmNoticeDetailContent');
    const noticeVersionEl     = document.getElementById('hrcmNoticeVersion');
    const noticeTitleEl       = document.getElementById('hrcmNoticeTitle');
    const noticeMetaEl        = document.getElementById('hrcmNoticeMeta');
    const noticeBodyEl        = document.getElementById('hrcmNoticeBody');
    const noticeFooterEl      = document.getElementById('hrcmNoticeFooter');

    /* ── State ──────────────────────────────────────────── */
    let cachedData = null;
    let activeTab  = 'policies';
    let isLoading  = false;

    /* ── Helpers ────────────────────────────────────────── */
    function safeText(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function formatDate(str) {
        if (!str) return '';
        try {
            return new Date(str).toLocaleDateString('en-IN', {
                day: 'numeric', month: 'short', year: 'numeric'
            });
        } catch (_) { return str; }
    }

    function ackStatus(item, type) {
        const key = type === 'policy'
            ? `hrPolicy_db_accepted_${item.id}`
            : `hrNotice_db_accepted_${item.id}`;
        return item.is_acknowledged == 1 || sessionStorage.getItem(key) === 'true';
    }

    /**
     * Resolve a root-relative attachment URL stored in DB to a working URL.
     * DB stores: /uploads/notices/file.jpg  (production)
     *        or: /connect/uploads/notices/file.jpg  (local XAMPP)
     *
     * Detects the sub-directory prefix from window.location and prepends it
     * when the stored URL is missing it, so images work on both environments.
     */
    function resolveAttachmentUrl(storedUrl) {
        if (!storedUrl) return storedUrl;
        if (/^https?:\/\//i.test(storedUrl)) return storedUrl;  // already absolute

        // Detect sub-dir: on XAMPP pathname = '/connect/studio_users/...' → '/connect'
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const subDir    = pathParts.length > 1 ? ('/' + pathParts[0]) : '';

        if (subDir && storedUrl.startsWith(subDir + '/')) return storedUrl;
        if (subDir && storedUrl.startsWith('/uploads/'))  return subDir + storedUrl;
        return storedUrl;
    }

    /** Detect attachment type from URL extension */
    function detectAttachment(url) {
        if (!url) return null;
        const clean = url.split('?')[0].toLowerCase();
        if (/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i.test(clean)) return { kind: 'image', url };
        if (/\.pdf$/i.test(clean))                               return { kind: 'pdf',   url };
        if (/\.(mp4|webm|ogg|mov)$/i.test(clean))               return { kind: 'video', url };
        return { kind: 'file', url };
    }

    /** Build inline media HTML */
    function buildMediaBlock(attachmentUrl) {
        const resolvedUrl = resolveAttachmentUrl(attachmentUrl);
        const att = detectAttachment(resolvedUrl);
        if (!att) return '';
        const safeUrl = encodeURI(att.url);

        if (att.kind === 'image') {
            return `
            <div class="hrcm-media-block">
                <div class="hrcm-media-label"><i class="fa-regular fa-image"></i> Attached Image</div>
                <a href="${safeUrl}" target="_blank" rel="noopener" class="hrcm-media-img-wrap">
                    <img src="${safeUrl}" alt="Attachment" class="hrcm-media-img"
                         onerror="this.parentElement.parentElement.innerHTML='<div class=hrcm-media-broken><i class=\\'fa-solid fa-image-slash\\'></i><span>Image could not be loaded</span></div>'">
                    <div class="hrcm-media-img-overlay"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open full size</div>
                </a>
            </div>`;
        }
        if (att.kind === 'pdf') {
            return `
            <div class="hrcm-media-block">
                <div class="hrcm-media-label"><i class="fa-solid fa-file-pdf"></i> Attached Document</div>
                <div class="hrcm-pdf-embed-wrap">
                    <iframe src="${safeUrl}" class="hrcm-pdf-embed" title="PDF Attachment" loading="lazy"></iframe>
                    <div class="hrcm-pdf-fallback">
                        <i class="fa-solid fa-file-pdf"></i>
                        <span>Can't display inline?</span>
                        <a href="${safeUrl}" target="_blank" rel="noopener" class="hrcm-file-link">Open PDF</a>
                    </div>
                </div>
            </div>`;
        }
        if (att.kind === 'video') {
            return `
            <div class="hrcm-media-block">
                <div class="hrcm-media-label"><i class="fa-solid fa-film"></i> Attached Video</div>
                <video class="hrcm-video" controls preload="metadata">
                    <source src="${safeUrl}">
                    <a href="${safeUrl}" target="_blank" rel="noopener">Download video</a>
                </video>
            </div>`;
        }
        const filename = att.url.split('/').pop().split('?')[0] || 'Attachment';
        return `
        <div class="hrcm-media-block">
            <div class="hrcm-media-label"><i class="fa-solid fa-paperclip"></i> Attachment</div>
            <a href="${safeUrl}" target="_blank" rel="noopener" class="hrcm-file-link hrcm-file-link-block">
                <i class="fa-solid fa-file-arrow-down"></i>
                <span>${safeText(filename)}</span>
            </a>
        </div>`;
    }

    /* ─────────────────────────────────────────────────────
       buildAckSection — renders the acknowledge checkbox +
       button for ANY policy or notice (mandatory or not).
       itemId   : DB id
       itemType : 'policy' | 'notice'
       alreadyAcked : bool
       onSuccess : callback() called after DB save succeeds
    ───────────────────────────────────────────────────── */
    function buildAckSection(itemId, itemType, alreadyAcked, onSuccess) {
        const uid     = `hrcmAck_${itemType}_${itemId}`;
        const btnUid  = `hrcmAckBtn_${itemType}_${itemId}`;

        const section = document.createElement('div');
        section.className = 'hrcm-ack-section';

        if (alreadyAcked) {
            section.innerHTML = `
                <div class="hrcm-ack-done">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>You have acknowledged this</span>
                </div>`;
            return section;
        }

        section.innerHTML = `
            <label class="hrcm-ack-label" for="${uid}">
                <input type="checkbox" id="${uid}" class="hrcm-ack-checkbox">
                <span class="hrcm-ack-checkmark"></span>
                <span class="hrcm-ack-text">I have read and understood this ${itemType === 'policy' ? 'policy' : 'notice'}</span>
            </label>
            <button id="${btnUid}" class="hrcm-btn hrcm-btn-primary hrcm-ack-btn" disabled>
                <i class="fa-solid fa-signature"></i> Acknowledge
            </button>`;

        const checkbox = section.querySelector(`#${uid}`);
        const btn      = section.querySelector(`#${btnUid}`);

        checkbox.addEventListener('change', () => {
            btn.disabled = !checkbox.checked;
        });

        btn.addEventListener('click', async () => {
            if (!checkbox.checked) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            try {
                const res  = await fetch('api/acknowledge_hr_item.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ item_id: itemId, item_type: itemType })
                });
                const data = await res.json();

                if (data.success) {
                    /* Persist to sessionStorage so the compliance hub also sees it */
                    const sessionKey = itemType === 'policy'
                        ? `hrPolicy_db_accepted_${itemId}`
                        : `hrNotice_db_accepted_${itemId}`;
                    sessionStorage.setItem(sessionKey, 'true');

                    /* Update the in-memory cached data so the list pill refreshes */
                    if (cachedData) {
                        const arr = itemType === 'policy' ? cachedData.policies : cachedData.notices;
                        const found = arr.find(x => x.id == itemId);
                        if (found) found.is_acknowledged = 1;
                    }

                    /* Swap section to "done" state */
                    section.innerHTML = `
                        <div class="hrcm-ack-done">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Acknowledged successfully!</span>
                        </div>`;

                    /* Refresh the list item pill */
                    const listEl = itemType === 'policy' ? policiesList : noticesList;
                    if (listEl) {
                        const activeItem = listEl.querySelector('.hrcm-item-active');
                        if (activeItem) {
                            const pill = activeItem.querySelector('.hrcm-pill-pending');
                            if (pill) {
                                pill.className = 'hrcm-pill hrcm-pill-acked';
                                pill.innerHTML = '<i class="fa-solid fa-check"></i> Acknowledged';
                            }
                        }
                    }

                    /* Update meta chip in detail panel */
                    const metaEl = itemType === 'policy' ? policyMetaEl : noticeMetaEl;
                    if (metaEl) {
                        const chip = metaEl.querySelector('.hrcm-chip-amber');
                        if (chip) {
                            chip.className = 'hrcm-meta-chip hrcm-chip-green';
                            chip.innerHTML = '<i class="fa-solid fa-circle-check"></i> You acknowledged this';
                        }
                    }

                    /* Sync mandatory compliance hub (mandatoryPolicies[]) */
                    if (window.mandatoryPolicies) {
                        const key = itemType === 'policy' ? 'db_id' : 'notif_db_id';
                        const mp = window.mandatoryPolicies.find(p => p[key] == itemId);
                        if (mp) mp.accepted = true;
                    }
                    if (typeof window.updateHRCornerDisplay === 'function') window.updateHRCornerDisplay();
                    if (typeof renderPolicySteps === 'function') renderPolicySteps();

                    if (onSuccess) onSuccess();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-signature"></i> Acknowledge';
                    alert('Could not save acknowledgement: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[HR Ack]', e);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-signature"></i> Acknowledge';
                alert('Network error. Please try again.');
            }
        });

        return section;
    }

    /* ── Open / Close ───────────────────────────────────── */
    function open(startTab) {
        if (!overlay) return;
        switchTab(startTab === 'notices' ? 'notices' : 'policies');
        overlay.classList.add('hrcm-open');
        document.body.style.overflow = 'hidden';
        loadData();
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('hrcm-open');
        document.body.style.overflow = '';
    }

    /* ── Tab switching ──────────────────────────────────── */
    function switchTab(tab) {
        activeTab = tab;
        tabBtns.forEach(btn => btn.classList.toggle('hrcm-tab-active', btn.dataset.tab === tab));
        if (policiesPane) policiesPane.classList.toggle('hrcm-tab-pane-active', tab === 'policies');
        if (noticesPane)  noticesPane.classList.toggle('hrcm-tab-pane-active',  tab === 'notices');
    }

    /* ── Load data ──────────────────────────────────────── */
    async function loadData(forceRefresh) {
        if (isLoading) return;
        if (cachedData && !forceRefresh) { renderAll(cachedData); return; }
        isLoading = true;
        showListLoader(policiesList);
        showListLoader(noticesList);
        try {
            const res  = await fetch('api/get_hr_dashboard_content.php?v=' + Date.now());
            const data = await res.json();
            if (data.success) { cachedData = data; renderAll(data); }
            else {
                showError(policiesList, data.message || 'Failed to load');
                showError(noticesList,  data.message || 'Failed to load');
            }
        } catch (e) {
            console.error('[HR Corner Modal]', e);
            showError(policiesList, 'Network error. Try again.');
            showError(noticesList,  'Network error. Try again.');
        } finally { isLoading = false; }
    }

    function showListLoader(el) {
        if (!el) return;
        el.innerHTML = `<div class="hrcm-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading…</span></div>`;
    }
    function showError(el, msg) {
        if (!el) return;
        el.innerHTML = `<div class="hrcm-empty-state"><i class="fa-solid fa-triangle-exclamation"></i><p>${safeText(msg)}</p></div>`;
    }

    /* ── Render ─────────────────────────────────────────── */
    function renderAll(data) {
        renderPolicies(data.policies || []);
        renderNotices(data.notices  || []);
    }

    /* ── Policy list ─────────────────────────────────────── */
    function renderPolicies(policies) {
        if (!policiesList) return;
        if (policyCountEl) policyCountEl.textContent = policies.length;

        if (!policies.length) {
            policiesList.innerHTML = `<div class="hrcm-empty-state"><i class="fa-regular fa-file-lines"></i><p>No policies published yet.</p></div>`;
            return;
        }

        policiesList.innerHTML = '';
        policies.forEach((p, idx) => {
            const acked     = ackStatus(p, 'policy');
            const mandatory = p.is_mandatory == 1;
            const item      = document.createElement('div');
            item.className  = 'hrcm-item';

            item.innerHTML = `
                <div class="hrcm-item-title">${safeText(p.heading)}</div>
                <div class="hrcm-item-desc">${safeText(p.short_desc)}</div>
                <div class="hrcm-item-footer">
                    ${mandatory
                        ? (acked
                            ? `<span class="hrcm-pill hrcm-pill-acked"><i class="fa-solid fa-check"></i> Acknowledged</span>`
                            : `<span class="hrcm-pill hrcm-pill-pending"><i class="fa-solid fa-clock"></i> Pending</span>`)
                        : (acked ? `<span class="hrcm-pill hrcm-pill-acked"><i class="fa-solid fa-check"></i> Acknowledged</span>` : '')}
                    <span class="hrcm-item-date">${formatDate(p.updated_at)}</span>
                </div>`;

            item.addEventListener('click', () => {
                policiesList.querySelectorAll('.hrcm-item').forEach(el => el.classList.remove('hrcm-item-active'));
                item.classList.add('hrcm-item-active');
                showPolicyDetail(p, ackStatus(p, 'policy'));
            });

            policiesList.appendChild(item);
            if (idx === 0) { item.classList.add('hrcm-item-active'); showPolicyDetail(p, acked); }
        });
    }

    /* ── Policy detail ───────────────────────────────────── */
    function showPolicyDetail(p, acked) {
        if (!policyDetailContent) return;
        policyDetailEmpty.style.display   = 'none';
        policyDetailContent.style.display = 'block';
        policyDetailContent.style.animation = 'none';
        void policyDetailContent.offsetWidth;
        policyDetailContent.style.animation = '';

        const mandatory = p.is_mandatory == 1;
        const dateStr   = formatDate(p.updated_at);

        if (policyBadgeEl)   policyBadgeEl.textContent   = mandatory ? 'Mandatory Policy' : 'Policy';
        if (policyVersionEl) policyVersionEl.textContent = `v${new Date(p.updated_at).toLocaleDateString('en-IN').replace(/\//g, '.')}`;
        if (policyTitleEl)   policyTitleEl.textContent   = p.heading;

        if (policyMetaEl) {
            let html = `<span class="hrcm-meta-chip"><i class="fa-regular fa-calendar"></i> Updated ${dateStr}</span>`;
            if (mandatory) {
                html += acked
                    ? `<span class="hrcm-meta-chip hrcm-chip-green"><i class="fa-solid fa-circle-check"></i> You acknowledged this</span>`
                    : `<span class="hrcm-meta-chip hrcm-chip-amber"><i class="fa-solid fa-circle-exclamation"></i> Action required</span>`;
            } else if (acked) {
                html += `<span class="hrcm-meta-chip hrcm-chip-green"><i class="fa-solid fa-circle-check"></i> You acknowledged this</span>`;
            }
            policyMetaEl.innerHTML = html;
        }

        if (policyBodyEl) policyBodyEl.innerHTML = p.long_desc || p.short_desc || '';

        if (policyFooterEl) {
            policyFooterEl.innerHTML = '';
            /* Inline media */
            if (p.attachment) policyFooterEl.innerHTML += buildMediaBlock(p.attachment);
            /* Acknowledge section (ALL policies, not just mandatory) */
            const ackSection = buildAckSection(p.id, 'policy', acked, () => {
                /* Re-render detail to reflect the new ack state */
                showPolicyDetail(p, true);
            });
            policyFooterEl.appendChild(ackSection);

            /* Extra shortcut button for mandatory+unacked: open Compliance Hub */
            if (mandatory && !acked) {
                const hubBtn = document.createElement('button');
                hubBtn.className = 'hrcm-btn hrcm-btn-secondary';
                hubBtn.innerHTML = '<i class="fa-solid fa-file-shield"></i> Open Compliance Hub';
                hubBtn.addEventListener('click', () => {
                    close();
                    if (typeof loadPolicy === 'function') {
                        const idx = (window.mandatoryPolicies || []).findIndex(mp => mp.db_id == p.id);
                        if (idx !== -1) loadPolicy(idx);
                    }
                    const pm = document.getElementById('policyModal');
                    if (pm) pm.classList.add('visible', 'open');
                });
                policyFooterEl.appendChild(hubBtn);
            }
        }
    }

    /* ── Notice list ─────────────────────────────────────── */
    function renderNotices(notices) {
        if (!noticesList) return;
        if (noticeCountEl) noticeCountEl.textContent = notices.length;

        if (!notices.length) {
            noticesList.innerHTML = `<div class="hrcm-empty-state"><i class="fa-regular fa-bell-slash"></i><p>No notices published yet.</p></div>`;
            return;
        }

        noticesList.innerHTML = '';
        notices.forEach((n, idx) => {
            const acked     = ackStatus(n, 'notice');
            const mandatory = n.is_mandatory == 1;
            const att       = detectAttachment(n.attachment);
            const mediaIcon = att
                ? (att.kind === 'image' ? ' <i class="fa-regular fa-image hrcm-attach-icon" title="Image attached"></i>'
                : att.kind === 'pdf'    ? ' <i class="fa-solid fa-file-pdf hrcm-attach-icon" title="PDF attached"></i>'
                : att.kind === 'video'  ? ' <i class="fa-solid fa-film hrcm-attach-icon" title="Video attached"></i>'
                : ' <i class="fa-solid fa-paperclip hrcm-attach-icon" title="File attached"></i>')
                : '';

            const item = document.createElement('div');
            item.className = 'hrcm-item';

            item.innerHTML = `
                <div class="hrcm-item-title">${safeText(n.title)}${mediaIcon}</div>
                <div class="hrcm-item-desc">${safeText(n.short_desc)}</div>
                <div class="hrcm-item-footer">
                    ${mandatory
                        ? (acked
                            ? `<span class="hrcm-pill hrcm-pill-acked"><i class="fa-solid fa-check"></i> Acknowledged</span>`
                            : `<span class="hrcm-pill hrcm-pill-pending"><i class="fa-solid fa-clock"></i> Pending</span>`)
                        : (acked ? `<span class="hrcm-pill hrcm-pill-acked"><i class="fa-solid fa-check"></i> Acknowledged</span>` : '')}
                    <span class="hrcm-item-date">${formatDate(n.created_at)}</span>
                </div>`;

            item.addEventListener('click', () => {
                noticesList.querySelectorAll('.hrcm-item').forEach(el => el.classList.remove('hrcm-item-active'));
                item.classList.add('hrcm-item-active');
                showNoticeDetail(n, ackStatus(n, 'notice'));
            });

            noticesList.appendChild(item);
            if (idx === 0) { item.classList.add('hrcm-item-active'); showNoticeDetail(n, acked); }
        });
    }

    /* ── Notice detail ───────────────────────────────────── */
    function showNoticeDetail(n, acked) {
        if (!noticeDetailContent) return;
        noticeDetailEmpty.style.display   = 'none';
        noticeDetailContent.style.display = 'block';
        noticeDetailContent.style.animation = 'none';
        void noticeDetailContent.offsetWidth;
        noticeDetailContent.style.animation = '';

        const mandatory = n.is_mandatory == 1;
        const dateStr   = formatDate(n.created_at);

        if (noticeVersionEl) noticeVersionEl.textContent = `Broadcast: ${dateStr}`;
        if (noticeTitleEl)   noticeTitleEl.textContent   = n.title;

        if (noticeMetaEl) {
            let html = `<span class="hrcm-meta-chip"><i class="fa-regular fa-calendar"></i> ${dateStr}</span>`;
            if (n.created_by) html += `<span class="hrcm-meta-chip"><i class="fa-solid fa-user-tie"></i> By ${safeText(n.created_by)}</span>`;
            if (mandatory || acked) {
                html += (mandatory && !acked)
                    ? `<span class="hrcm-meta-chip hrcm-chip-amber"><i class="fa-solid fa-circle-exclamation"></i> Action required</span>`
                    : `<span class="hrcm-meta-chip hrcm-chip-green"><i class="fa-solid fa-circle-check"></i> Acknowledged</span>`;
            }
            noticeMetaEl.innerHTML = html;
        }

        if (noticeBodyEl) noticeBodyEl.innerHTML = n.long_desc || n.short_desc || '';

        if (noticeFooterEl) {
            noticeFooterEl.innerHTML = '';
            /* Inline media */
            if (n.attachment) noticeFooterEl.innerHTML += buildMediaBlock(n.attachment);
            /* Acknowledge section (ALL notices) */
            const ackSection = buildAckSection(n.id, 'notice', acked, () => {
                showNoticeDetail(n, true);
            });
            noticeFooterEl.appendChild(ackSection);

            /* Extra hub shortcut for mandatory+unacked */
            if (mandatory && !acked) {
                const hubBtn = document.createElement('button');
                hubBtn.className = 'hrcm-btn hrcm-btn-secondary';
                hubBtn.innerHTML = '<i class="fa-solid fa-file-shield"></i> Open Compliance Hub';
                hubBtn.addEventListener('click', () => {
                    close();
                    if (typeof loadPolicy === 'function') {
                        const idx = (window.mandatoryPolicies || []).findIndex(mp => mp.notif_db_id == n.id);
                        if (idx !== -1) loadPolicy(idx);
                    }
                    const pm = document.getElementById('policyModal');
                    if (pm) pm.classList.add('visible', 'open');
                });
                noticeFooterEl.appendChild(hubBtn);
            }
        }
    }

    /* ── Events ─────────────────────────────────────────── */
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (backdrop) backdrop.addEventListener('click', close);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('hrcm-open')) close();
    });
    tabBtns.forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.tab)));

    /* ── Public API ─────────────────────────────────────── */
    window.HRCornerModal = { open, close, refresh: () => loadData(true) };

})();
