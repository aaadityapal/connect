/* ══ TEMPLATES PAGE ══ */
let TEMPLATE_META = [];
let tbVarCount = { 'tb-body': 0 };
let tbHeaderType = 'none';
let tbButtons = [];

// ─── Init ───────────────────────────────────────────────────
async function initTemplates() {
  const grid = document.getElementById('templates-grid');
  if (grid) {
    grid.innerHTML = '<div style="padding: 40px; text-align: center; color: var(--text-muted); grid-column: 1 / -1;">Loading templates from WhatsApp...</div>';
  }

  try {
    const response = await fetch('api/get_templates.php');
    const result = await response.json();

    if (result.success && result.data) {
      TEMPLATE_META = result.data;
      window.TEMPLATES = window.TEMPLATES || {};
      TEMPLATE_META.forEach(t => { window.TEMPLATES[t.key] = t.body; });
      renderTemplatesGrid();
    } else {
      if (grid) grid.innerHTML = `<div style="padding: 40px; text-align: center; color: var(--red); grid-column: 1 / -1;">Error loading templates: ${result.message || 'Unknown error'}</div>`;
    }
  } catch (error) {
    console.error('Failed to fetch templates:', error);
    if (grid) grid.innerHTML = '<div style="padding: 40px; text-align: center; color: var(--red); grid-column: 1 / -1;">Network error while fetching templates.</div>';
  }
}

function renderTemplatesGrid() {
  const grid = document.getElementById('templates-grid');
  if (!grid) return;

  if (TEMPLATE_META.length === 0) {
    grid.innerHTML = '<div style="padding: 40px; text-align: center; color: var(--text-muted); grid-column: 1 / -1;">No approved templates found. Click "New Template" to create one!</div>';
    return;
  }

  grid.innerHTML = TEMPLATE_META.map(t => {
    const isApproved = t.status === 'APPROVED';
    const statusBadge = isApproved
      ? `<span class="status-badge badge-delivered" style="font-size: 0.6rem;">${t.status}</span>`
      : `<span class="status-badge badge-failed" style="font-size: 0.6rem;">${t.status}</span>`;

    return `
    <div class="template-card">
      <div class="template-card-header" style="flex-direction:column; align-items:flex-start; gap:5px;">
        <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
          <span class="template-name">${t.label}</span>
          ${statusBadge}
        </div>
        <span class="template-category" style="margin-left:0;">${t.category} · ${t.language}</span>
      </div>
      <div class="template-body" style="white-space:pre-wrap;">${t.body || '<em style="opacity:.5">No body text</em>'}</div>
      <div class="template-actions">
        <button class="template-btn use" onclick="useTemplate('${t.key}', event)" ${!isApproved ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>Use Template</button>
      </div>
    </div>`;
  }).join('');
}

function useTemplate(key, event) {
  navigateTo('campaigns', event);
  setTimeout(() => {
    const sel = document.getElementById('template-select');
    if (sel) {
      if (!Array.from(sel.options).some(o => o.value === key)) {
        const t = TEMPLATE_META.find(tp => tp.key === key);
        if (t) sel.add(new Option(t.label, key));
      }
      sel.value = key;
      sel.dispatchEvent(new Event('change'));
      showToast('Template loaded in Campaign Setup!', 'success');
    }
  }, 100);
}

// ─── Template Builder ────────────────────────────────────────
function openTemplateBuilder() {
  tbReset();
  const modal = document.getElementById('template-builder-modal');
  if (modal) modal.classList.add('open');
}

function closeTemplateBuilder() {
  const modal = document.getElementById('template-builder-modal');
  if (modal) modal.classList.remove('open');
}

function tbReset() {
  document.getElementById('tb-name').value = '';
  document.getElementById('tb-category').value = 'MARKETING';
  document.getElementById('tb-language').value = 'en';
  document.getElementById('tb-body').value = '';
  document.getElementById('tb-footer').value = '';
  document.getElementById('tb-header-text').value = '';
  tbVarCount = { 'tb-body': 0 };
  tbButtons = [];
  tbHeaderType = 'none';
  tbSetHeaderType('none', document.querySelector('.tb-type-btn[data-type="none"]'));
  tbRenderButtons();
  tbUpdatePreview();
  const submitBtn = document.getElementById('tb-submit-btn');
  if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M22 2L15 22l-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Submit to Meta`; }
}

// Sanitise template name input
function tbSyncName(input) {
  input.value = input.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
  tbUpdatePreview();
}

// ─── Header Type ─────────────────────────────────────────────
function tbSetHeaderType(type, el) {
  tbHeaderType = type;
  document.querySelectorAll('.tb-type-btn').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');

  const textWrap  = document.getElementById('tb-header-text-wrap');
  const mediaWrap = document.getElementById('tb-header-media-wrap');
  const noteEl    = document.getElementById('tb-media-note-text');

  textWrap.style.display  = type === 'text' ? 'block' : 'none';
  mediaWrap.style.display = ['image','video','document'].includes(type) ? 'block' : 'none';

  const notes = { image: 'Image will be supplied at send-time via the API.', video: 'Video will be supplied at send-time via the API.', document: 'Document will be supplied at send-time via the API.' };
  if (noteEl && notes[type]) noteEl.textContent = notes[type];
  tbUpdatePreview();
}

// ─── Live Preview ─────────────────────────────────────────────
function tbUpdatePreview() {
  const body     = document.getElementById('tb-body').value || 'Your message will appear here...';
  const header   = document.getElementById('tb-header-text').value;
  const footer   = document.getElementById('tb-footer').value;
  const category = document.getElementById('tb-category').value;

  // Char counters
  const bodyEl   = document.getElementById('tb-body-char');
  const footerEl = document.getElementById('tb-footer-char');
  const headerEl = document.getElementById('tb-header-char');
  const actualBody = document.getElementById('tb-body').value;
  if (footerEl) footerEl.textContent = `${footer.length} / 60`;
  if (headerEl) headerEl.textContent = `${document.getElementById('tb-header-text').value.length} / 60`;

  // Variable density check (Meta rule)
  if (bodyEl) {
    const vars    = actualBody.match(/\{\{\d+\}\}/g) || [];
    const rawLen  = actualBody.replace(/\{\{\d+\}\}/g, '').replace(/\s+/g, '').length;
    const tooShort = vars.length > 0 && rawLen < vars.length * 10;
    bodyEl.textContent = `${actualBody.length} / 1024`;
    bodyEl.style.color = tooShort ? 'var(--red)' : '';
    bodyEl.title = tooShort ? `⚠️ Too few surrounding characters for ${vars.length} variable(s). Meta requires ~10 chars of real text per variable.` : '';
    // Show inline warning
    const warnEl = document.getElementById('tb-var-density-warn');
    if (warnEl) {
      warnEl.style.display = tooShort ? 'flex' : 'none';
      warnEl.textContent = `⚠️ Add more text around your ${vars.length} variable(s) — Meta requires ~10 characters of surrounding text per variable.`;
    }
  }

  // Render header in bubble
  const previewHeader = document.getElementById('tb-preview-header');
  if (tbHeaderType === 'text' && header) {
    previewHeader.style.display = 'block';
    previewHeader.textContent = header;
  } else if (['image','video','document'].includes(tbHeaderType)) {
    previewHeader.style.display = 'block';
    const icons = { image: '🖼️ Image', video: '🎥 Video', document: '📄 Document' };
    previewHeader.textContent = icons[tbHeaderType] + ' (preview at send-time)';
    previewHeader.style.color = 'var(--blue)';
    previewHeader.style.fontSize = '0.75rem';
  } else {
    previewHeader.style.display = 'none';
  }

  // Format body text (bold, italic)
  const formatted = body
    .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/~(.*?)~/g, '<s>$1</s>');
  document.getElementById('tb-preview-body').innerHTML = formatted;

  // Footer
  const previewFooter = document.getElementById('tb-preview-footer');
  if (footer) {
    previewFooter.style.display = 'block';
    previewFooter.textContent = footer;
  } else {
    previewFooter.style.display = 'none';
  }

  // Category badge
  const badge = document.getElementById('tb-preview-category-badge');
  if (badge) badge.textContent = category;

  // Buttons preview
  const btnsContainer = document.getElementById('tb-preview-buttons');
  if (btnsContainer) {
    btnsContainer.innerHTML = tbButtons.map(b => `<div class="tb-preview-btn-item">${b.text || '—'}</div>`).join('');
  }
}

// ─── Variable Insert ─────────────────────────────────────────
function tbInsertVariable(fieldId) {
  const ta = document.getElementById(fieldId);
  if (!ta) return;
  tbVarCount[fieldId] = (tbVarCount[fieldId] || 0) + 1;
  const varStr = `{{${tbVarCount[fieldId]}}}`;
  const start = ta.selectionStart, end = ta.selectionEnd;
  ta.value = ta.value.substring(0, start) + varStr + ta.value.substring(end);
  ta.selectionStart = ta.selectionEnd = start + varStr.length;
  ta.focus();
  tbUpdatePreview();
}

function tbInsertFormat(fieldId, open, close) {
  const ta = document.getElementById(fieldId);
  if (!ta) return;
  const start = ta.selectionStart, end = ta.selectionEnd;
  const selected = ta.value.substring(start, end) || 'text';
  ta.value = ta.value.substring(0, start) + open + selected + close + ta.value.substring(end);
  ta.focus();
  tbUpdatePreview();
}

// ─── Buttons ─────────────────────────────────────────────────
function tbAddButton() {
  if (tbButtons.length >= 3) { showToast('Maximum 3 buttons allowed.', 'info'); return; }
  tbButtons.push({ type: 'QUICK_REPLY', text: '', value: '' });
  tbRenderButtons();
}

function tbRemoveButton(idx) {
  tbButtons.splice(idx, 1);
  tbRenderButtons();
  tbUpdatePreview();
}

function tbRenderButtons() {
  const list = document.getElementById('tb-buttons-list');
  const addBtn = document.getElementById('tb-add-btn-cta');
  if (!list) return;

  list.innerHTML = tbButtons.map((b, i) => `
    <div class="tb-button-row">
      <select onchange="tbUpdateButtonType(${i}, this.value)">
        <option value="QUICK_REPLY" ${b.type === 'QUICK_REPLY' ? 'selected' : ''}>Quick Reply</option>
        <option value="URL" ${b.type === 'URL' ? 'selected' : ''}>Visit Website</option>
        <option value="PHONE_NUMBER" ${b.type === 'PHONE_NUMBER' ? 'selected' : ''}>Call Phone</option>
      </select>
      <input type="text" placeholder="Button label" value="${b.text}" oninput="tbUpdateButtonText(${i}, this.value)" />
      ${b.type !== 'QUICK_REPLY' ? `<input type="text" placeholder="${b.type === 'URL' ? 'https://...' : '+91...'}" value="${b.value}" oninput="tbUpdateButtonValue(${i}, this.value)" />` : ''}
      <button class="tb-btn-remove" onclick="tbRemoveButton(${i})">✕</button>
    </div>
  `).join('');

  if (addBtn) addBtn.style.display = tbButtons.length >= 3 ? 'none' : 'inline-flex';
  tbUpdatePreview();
}

function tbUpdateButtonType(idx, val) { tbButtons[idx].type = val; tbRenderButtons(); }
function tbUpdateButtonText(idx, val) { tbButtons[idx].text = val; tbUpdatePreview(); }
function tbUpdateButtonValue(idx, val) { tbButtons[idx].value = val; }

// ─── Submit ───────────────────────────────────────────────────
async function submitTemplate() {
  const name     = document.getElementById('tb-name').value.trim();
  const category = document.getElementById('tb-category').value;
  const language = document.getElementById('tb-language').value;
  const body     = document.getElementById('tb-body').value.trim();
  const header   = tbHeaderType === 'text' ? document.getElementById('tb-header-text').value.trim() : '';
  const footer   = document.getElementById('tb-footer').value.trim();

  if (!name) { showToast('Please enter a template name.', 'error'); return; }
  if (!body)  { showToast('Body text is required.', 'error'); return; }
  if (body.length > 1024) { showToast('Body text exceeds 1024 characters.', 'error'); return; }

  // Meta rule: body must have enough surrounding text relative to variable count
  const varMatches = body.match(/\{\{\d+\}\}/g) || [];
  const varCount   = varMatches.length;
  const rawText    = body.replace(/\{\{\d+\}\}/g, '').replace(/\s+/g, '').length;
  if (varCount > 0 && rawText < varCount * 10) {
    showToast(`Body text too short for ${varCount} variable(s). Add more surrounding text (at least ~10 chars per variable) — Meta requirement.`, 'error');
    return;
  }

  const btn = document.getElementById('tb-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<span style="opacity:.7">Submitting to Meta...</span>';

  const payload = { name, category, language, body, header, footer, buttons: tbButtons };

  try {
    const res  = await fetch('api/create_template.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      closeTemplateBuilder();
      // Reload templates list after short delay
      setTimeout(() => initTemplates(), 1500);
    } else {
      showToast('Meta API Error: ' + data.message, 'error');
      btn.disabled = false;
      btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M22 2L15 22l-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Submit to Meta`;
    }
  } catch (e) {
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M22 2L15 22l-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Submit to Meta`;
  }
}

// Close on overlay click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('template-builder-modal');
  if (modal && e.target === modal) closeTemplateBuilder();
});

window.initTemplates      = initTemplates;
window.openTemplateBuilder = openTemplateBuilder;
window.closeTemplateBuilder = closeTemplateBuilder;
window.tbSetHeaderType    = tbSetHeaderType;
window.tbInsertVariable   = tbInsertVariable;
window.tbInsertFormat     = tbInsertFormat;
window.tbAddButton        = tbAddButton;
window.tbRemoveButton     = tbRemoveButton;
window.tbUpdateButtonType = tbUpdateButtonType;
window.tbUpdateButtonText = tbUpdateButtonText;
window.tbUpdateButtonValue = tbUpdateButtonValue;
window.tbUpdatePreview    = tbUpdatePreview;
window.tbSyncName         = tbSyncName;
window.submitTemplate     = submitTemplate;
window.useTemplate        = useTemplate;
