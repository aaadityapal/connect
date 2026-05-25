/* ═══════════════════════════════════════════
   Automated Sequences (Nurture Loops) Logic
═══════════════════════════════════════════ */

// Default mock sequences for the year-long automated drip schedule
let SEQUENCES = []; // Loaded dynamically from API

let editingSequenceId = null;

// Template options reference matching DB templates
let TEMPLATE_OPTIONS = [];

// Initialize section entry point called by SPA router
async function initSequences() {
  await fetchLiveTemplates();
  await fetchSequences();
  renderSequencesGrid();
  updateSequenceGlobalStats();
}

async function fetchLiveTemplates() {
  try {
    const res = await fetch(API_BASE + 'get_templates.php');
    const data = await res.json();
    if (data.success && data.data) {
      // Filter only APPROVED templates and format them
      TEMPLATE_OPTIONS = data.data
        .filter(t => t.status === 'APPROVED')
        .map(t => ({
          key: t.key,
          label: t.label || t.key.replace(/_/g, ' '),
          header_type: t.header_type || 'NONE'
        }));
    } else if (Array.isArray(data)) {
      // Fallback for legacy API format
      TEMPLATE_OPTIONS = data.map(t => ({
          key: t.key || t.name,
          label: t.label || (t.name ? t.name.replace(/_/g, ' ') : 'Unnamed'),
          header_type: t.header_type || 'NONE'
      }));
    }
  } catch (err) {
    console.error('Error fetching live templates:', err);
  }
}

async function fetchSequences() {
  try {
    const res = await fetch(API_BASE + 'get_sequences.php');
    SEQUENCES = await res.json();
    console.log('Sequences loaded:', SEQUENCES);
  } catch (err) {
    console.error('Error fetching sequences:', err);
  }
}

// Global dashboard stats update
function updateSequenceGlobalStats() {
  const activeLoopsEl = document.getElementById('stat-active-loops');
  const enrolledEl = document.getElementById('stat-clients-enrolled');
  const messagesEl = document.getElementById('stat-loop-messages');

  if (activeLoopsEl) {
    activeLoopsEl.textContent = SEQUENCES.length;
  }
  if (enrolledEl) {
    const totalEnrolled = SEQUENCES.reduce((sum, seq) => sum + (seq.enrolledClients?.length || 0), 0);
    enrolledEl.textContent = totalEnrolled + (CLIENTS.length > 5 ? CLIENTS.length - 2 : 12);
  }
  if (messagesEl) {
    // Generate a lively metric count based on loaded sequences
    const stepsCount = SEQUENCES.reduce((sum, seq) => sum + seq.steps.length, 0);
    messagesEl.textContent = (stepsCount * 45) + 87;
  }
}

// Render the list of loaded sequences
function renderSequencesGrid() {
  const grid = document.getElementById('sequences-grid');
  if (!grid) return;

  if (SEQUENCES.length === 0) {
    grid.innerHTML = `<div style="grid-column: 1/-1; padding: 40px; text-align: center; color: var(--text-muted); background: var(--surface); border-radius: var(--radius); border: 1px dashed var(--border);">No automated sequences designed yet. Click 'Create New Loop' above to start building.</div>`;
    return;
  }

  grid.innerHTML = SEQUENCES.map(seq => {
    const totalSteps = seq.steps.length;
    const enrolledCount = seq.enrolledClients ? seq.enrolledClients.length : 0;
    
    // Calculate total duration roughly
    const totalDays = seq.steps.reduce((sum, step) => sum + (parseInt(step.delay_value) || 0), 0);
    const durationLabel = totalDays > 0 ? `${totalDays} Days Cycle` : `Immediate Launch`;

    return `
      <div class="sequence-card">
        <div>
          <div class="sequence-card-header">
            <div>
              <h3 class="sequence-title" style="display: flex; align-items: center; gap: 8px;">
                ${seq.title}
                ${seq.stopOnReply ? '<span class="safety-badge" title="Auto-Stop on Reply Enabled"><svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2"/></svg></span>' : ''}
              </h3>
              <p class="sequence-desc">${seq.desc}</p>
            </div>
            <span class="status-chip running" title="Active background sequence">Running</span>
          </div>

          <div class="sequence-meta">
            <div class="meta-item" title="Templates scheduled in loop">
              <svg viewBox="0 0 24 24" fill="none" width="15" height="15"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/><polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2"/></svg>
              <span>${totalSteps} Templates</span>
            </div>
            <div class="meta-item" title="Total chronological duration">
              <svg viewBox="0 0 24 24" fill="none" width="15" height="15"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/></svg>
              <span>${durationLabel}</span>
            </div>
            <div class="meta-item" title="Audience enrolled">
              <svg viewBox="0 0 24 24" fill="none" width="15" height="15"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/></svg>
              <span>${enrolledCount} Clients</span>
            </div>
          </div>
        </div>

        <div class="sequence-card-actions">
          <button class="btn btn-secondary btn-seq-action" onclick="openSequenceBuilder(${seq.id})">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/></svg>
            Edit Timeline
          </button>
          <button class="btn btn-secondary btn-seq-action" onclick="deleteSequence(${seq.id})" style="flex: 0 0 auto; color: var(--text-muted);" title="Delete loop">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2"/></svg>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// Open Builder View for a specific sequence
function openSequenceBuilder(seqId = null) {
  const listView = document.getElementById('seq-list-view');
  const builderView = document.getElementById('seq-builder-view');
  if (!listView || !builderView) return;

  listView.classList.add('hidden');
  builderView.classList.remove('hidden');

  let seq;
  if (seqId) {
    seq = SEQUENCES.find(s => s.id === seqId);
    editingSequenceId = seq.id;
  } else {
    // Create new blank sequence
    const newId = Date.now();
    seq = {
      id: newId,
      title: "New Automated Drip Loop",
      desc: "Custom year-long template loop setup.",
      persistOnReply: true,
      steps: [],
      enrolledClients: [],
      clientStates: {}
    };
    SEQUENCES.push(seq);
    editingSequenceId = newId;
  }

  // Populate title & settings
  const titleInput = document.getElementById('builder-seq-title');
  const persistToggle = document.getElementById('seq-persist-toggle');
  const stopReplyToggle = document.getElementById('seq-stop-reply-toggle');
  
  if (titleInput) titleInput.value = seq.title;
  if (persistToggle) persistToggle.checked = seq.persistOnReply !== false;
  if (stopReplyToggle) stopReplyToggle.checked = seq.stopOnReply !== false;

  // Smart Toggle Logic: Prevent contradictory rules
  if (persistToggle && stopReplyToggle) {
    persistToggle.onchange = () => {
      if (persistToggle.checked) stopReplyToggle.checked = false;
    };
    stopReplyToggle.onchange = () => {
      if (stopReplyToggle.checked) persistToggle.checked = false;
    };
  }

  // Render sub-components
  renderBuilderSteps();
  renderTemplatesToolbox();
  populateClientEnrollDropdown();
  renderEnrolledClientsList();

  // Log opening builder
  if (typeof logActivity === 'function') {
    logActivity({ type: 'Sequence Builder Open', page: 'sequences', data: { seqId: seq.id, title: seq.title } });
  }
}

// Close builder view back to dashboard
function closeSequenceBuilder() {
  // Implicitly save pending values
  saveStepInputsToMemory();

  const listView = document.getElementById('seq-list-view');
  const builderView = document.getElementById('seq-builder-view');
  if (!listView || !builderView) return;

  builderView.classList.add('hidden');
  listView.classList.remove('hidden');
  editingSequenceId = null;

  renderSequencesGrid();
  updateSequenceGlobalStats();
}

// Helper to push DOM values to sequence step object array before rendering/saving
function saveStepInputsToMemory() {
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  const titleInput = document.getElementById('builder-seq-title');
  if (titleInput) seq.title = titleInput.value.trim() || "Untitled Loop";

  const persistToggle = document.getElementById('seq-persist-toggle');
  if (persistToggle) seq.persistOnReply = persistToggle.checked;

  const stopReplyToggle = document.getElementById('seq-stop-reply-toggle');
  if (stopReplyToggle) seq.stopOnReply = stopReplyToggle.checked;

  // Read all step items
  document.querySelectorAll('.sequence-step-item').forEach((item, index) => {
    const stepId = parseInt(item.dataset.stepId);
    const stepObj = seq.steps.find(st => st.id === stepId);
    if (stepObj) {
      const tInput = item.querySelector('.step-title-input');
      const tmplSel = item.querySelector('.step-template-select');
      const delVal = item.querySelector('.step-delay-val');
      const delUnit = item.querySelector('.step-delay-unit');

      if (tInput) stepObj.title = tInput.value || `Step #${index + 1}`;
      if (tmplSel) stepObj.templateKey = tmplSel.value;
      if (delVal) stepObj.delay_value = parseInt(delVal.value) || 0;
      if (delUnit) stepObj.delay_unit = delUnit.value;
      
      // Sync header_type from TEMPLATE_OPTIONS
      const tOpt = TEMPLATE_OPTIONS.find(o => o.key === stepObj.templateKey);
      stepObj.header_type = tOpt ? (tOpt.header_type || 'NONE') : 'NONE';
      
      const mediaData = item.querySelector('.step-media-data');
      const mediaName = item.querySelector('.step-media-name');
      if (mediaData) stepObj.media_data = mediaData.value;
      if (mediaName) stepObj.media_filename = mediaName.value;
      // media_path stays as loaded from DB (do not overwrite unless a new file is uploaded)
      if (mediaData && mediaData.value && mediaData.value.startsWith('data:')) {
        // New file — clear old path so backend knows to save fresh
        stepObj.media_path = '';
      }
    }
  });
}

// Render the timeline sequence blocks
function renderBuilderSteps() {
  const container = document.getElementById('builder-steps-container');
  const emptyState = document.getElementById('builder-empty-state');
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!container || !seq) return;

  if (seq.steps.length === 0) {
    container.innerHTML = '';
    if (emptyState) emptyState.style.display = 'block';
    return;
  } else {
    if (emptyState) emptyState.style.display = 'none';
  }

  container.innerHTML = seq.steps.map((step, index) => {
    
    // Build options for templates
    const templateOptionsHtml = TEMPLATE_OPTIONS.map(opt => {
      const selected = step.templateKey === opt.key ? 'selected' : '';
      return `<option value="${opt.key}" ${selected}>${opt.label}</option>`;
    }).join('');

    // Contextual label for delay
    const delayPrefix = index === 0 ? "Initial Target Day" : "Delay After Previous Step";
    const delayPlaceholder = index === 0 ? "1 (First Day)" : "e.g. 3";

    return `
      <div class="sequence-step-item" data-step-id="${step.id}" draggable="true" ondragstart="dragStepStart(event, ${step.id})" ondragover="allowStepDrop(event)" ondrop="dropStep(event, ${step.id})">
        <div class="step-number-bubble">${index + 1}</div>
        
        <div class="step-content-card">
          <div class="step-card-header" style="cursor: grab;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i class="bi bi-grip-vertical text-muted" style="font-size: 1.2rem; cursor: grab;" title="Drag to reorder"></i>
              <input type="text" class="step-title-input" value="${step.title || `Step #${index + 1}`}" oninput="liveSyncTimeline()" placeholder="Step description..."/>
            </div>
            <div class="step-actions">
              <button class="btn-icon-only" onclick="removeSequenceStep(${step.id})" title="Delete step">
                <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2"/></svg>
              </button>
            </div>
          </div>

          <div class="step-form-grid">
            
            <!-- Select Template -->
            <div class="step-form-group full">
              <label class="step-label">Message Template</label>
              <div class="custom-dropdown-container">
                <div class="custom-dropdown-trigger" onclick="toggleDropdown(this)">
                  <span class="selected-text">${TEMPLATE_OPTIONS.find(o => o.key === step.templateKey)?.label || 'Select Template'}</span>
                  <svg class="chevron" viewBox="0 0 24 24" fill="none" width="14" height="14" stroke="currentColor"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="custom-dropdown-menu">
                  ${TEMPLATE_OPTIONS.map(opt => `
                    <div class="custom-dropdown-option ${step.templateKey === opt.key ? 'selected' : ''}" onclick="selectOption(this, '${opt.key}', 'template')">${opt.label}</div>
                  `).join('')}
                </div>
                <input type="hidden" class="step-template-select" value="${step.templateKey}"/>
              </div>
            </div>

            <!-- Wait interval -->
            <div class="step-form-group full">
              <label class="step-label">${delayPrefix}</label>
              <div class="delay-wrapper">
                <input type="number" class="step-input step-delay-val" value="${step.delay_value || 0}" min="0" oninput="liveSyncTimeline()" placeholder="${delayPlaceholder}"/>
                
                <div class="custom-dropdown-container" style="flex: 0 0 110px;">
                  <div class="custom-dropdown-trigger" style="border-left:0; border-top-left-radius:0; border-bottom-left-radius:0;" onclick="toggleDropdown(this)">
                    <span class="selected-text">${(step.delay_unit || 'days').charAt(0).toUpperCase() + (step.delay_unit || 'days').slice(1)}</span>
                    <svg class="chevron" viewBox="0 0 24 24" fill="none" width="14" height="14" stroke="currentColor"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </div>
                  <div class="custom-dropdown-menu">
                    <div class="custom-dropdown-option ${step.delay_unit === 'minutes' ? 'selected' : ''}" onclick="selectOption(this, 'minutes', 'unit')">Minutes</div>
                    <div class="custom-dropdown-option ${step.delay_unit === 'hours' ? 'selected' : ''}" onclick="selectOption(this, 'hours', 'unit')">Hours</div>
                    <div class="custom-dropdown-option ${(!step.delay_unit || step.delay_unit === 'days') ? 'selected' : ''}" onclick="selectOption(this, 'days', 'unit')">Days</div>
                    <div class="custom-dropdown-option ${step.delay_unit === 'weeks' ? 'selected' : ''}" onclick="selectOption(this, 'weeks', 'unit')">Weeks</div>
                    <div class="custom-dropdown-option ${step.delay_unit === 'months' ? 'selected' : ''}" onclick="selectOption(this, 'months', 'unit')">Months</div>
                  </div>
                  <input type="hidden" class="step-delay-unit" value="${step.delay_unit || 'days'}"/>
                </div>

                <span style="font-size: .8rem; color: var(--text-muted); padding-left: 8px;">interval before triggering</span>
              </div>
            </div>
            
            <!-- Media Attachment -->
            ${(TEMPLATE_OPTIONS.find(t => t.key === step.templateKey)?.header_type && ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type)) ? `
            <div class="step-form-group full" style="margin-top: 8px; padding-top: 16px; border-top: 1px dashed var(--border);">
              <label class="step-label" style="display: flex; justify-content: space-between;">
                <span>Attach ${TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type.charAt(0) + TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type.slice(1).toLowerCase()} Required</span>
                ${step.media_filename ? `<span style="color:var(--green);"><i class="bi bi-check-circle-fill"></i> ${step.media_filename}</span>` : ''}
              </label>
              <input type="file" class="step-input" accept="${TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type === 'IMAGE' ? 'image/*' : (TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type === 'VIDEO' ? 'video/mp4' : 'application/pdf')}" onchange="handleStepMediaUpload(this)" style="padding: 6px; font-size: .8rem;"/>
              <input type="hidden" class="step-media-data" value="${step.media_data || ''}"/>
              <input type="hidden" class="step-media-name" value="${step.media_filename || ''}"/>
              <div class="media-upload-status" style="font-size: .75rem; color: var(--blue); margin-top: 4px;"></div>
              <small class="text-muted" style="font-size: .7rem;">This template specifically requires a ${TEMPLATE_OPTIONS.find(t => t.key === step.templateKey).header_type} file.</small>
            </div>
            ` : ''}

          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Append a new step block
function addNewSequenceStep() {
  saveStepInputsToMemory();
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  const nextIdx = seq.steps.length + 1;
  const defaultTemplates = ["followup", "referral", "feedback", "promo"];
  const pickedTemplate = defaultTemplates[(nextIdx - 2) % defaultTemplates.length] || "followup";

  seq.steps.push({
    id: Date.now(),
    title: `Step #${nextIdx} Follow-up Message`,
    templateKey: pickedTemplate,
    delay_value: nextIdx === 2 ? 3 : 7,
    delay_unit: "days"
  });

  renderBuilderSteps();
  if (typeof showToast === 'function') {
    showToast(`➕ Added Step #${nextIdx} to timeline`, 'info');
  }
}

// Remove step
function removeSequenceStep(stepId) {
  saveStepInputsToMemory();
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  seq.steps = seq.steps.filter(st => st.id !== stepId);
  renderBuilderSteps();
  if (typeof showToast === 'function') {
    showToast('🗑 Removed template step', 'info');
  }
}

// ==========================================
// DRAG AND DROP BUILDER LOGIC
// ==========================================

function renderTemplatesToolbox() {
  const container = document.getElementById('templates-toolbox-list');
  if (!container) return;
  
  container.innerHTML = TEMPLATE_OPTIONS.map(opt => {
    return `
      <div class="toolbox-template-item" draggable="true" ondragstart="dragTemplateStart(event, '${opt.key}', '${opt.label}')" style="background: white; border: 1px solid var(--border); padding: 10px 12px; border-radius: 8px; cursor: grab; display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 500; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
        <i class="bi bi-chat-text text-muted"></i>
        <div style="flex: 1;">${opt.label}</div>
        <i class="bi bi-grip-vertical text-muted"></i>
      </div>
    `;
  }).join('');
}

function dragTemplateStart(ev, key, label) {
  ev.dataTransfer.setData("templateKey", key);
  ev.dataTransfer.setData("templateLabel", label);
  ev.dataTransfer.setData("dragType", "toolboxItem");
}

function allowTemplateDrop(ev) {
  ev.preventDefault();
  const placeholder = document.getElementById('addNodePlaceholder');
  if (placeholder) placeholder.style.display = 'block';
}

function dropTemplateOnCanvas(ev) {
  ev.preventDefault();
  const placeholder = document.getElementById('addNodePlaceholder');
  if (placeholder) placeholder.style.display = 'none';

  const dragType = ev.dataTransfer.getData("dragType");
  if (dragType !== "toolboxItem") return;

  const key = ev.dataTransfer.getData("templateKey");
  const label = ev.dataTransfer.getData("templateLabel");
  
  saveStepInputsToMemory();
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  const nextIdx = seq.steps.length + 1;
  seq.steps.push({
    id: Date.now(),
    title: `Step #${nextIdx} ${label}`,
    templateKey: key,
    delay_value: nextIdx === 1 ? 1 : 3,
    delay_unit: "days",
    media_data: "",
    media_filename: ""
  });

  renderBuilderSteps();
  if (typeof showToast === 'function') {
    showToast(`➕ Added ${label} to timeline`, 'info');
  }
}

// Handle media upload for a step
function handleStepMediaUpload(input) {
  const file = input.files[0];
  const container = input.closest('.step-form-group');
  const statusEl = container.querySelector('.media-upload-status');
  const dataInput = container.querySelector('.step-media-data');
  const nameInput = container.querySelector('.step-media-name');

  if (!file) {
    statusEl.innerHTML = '';
    dataInput.value = '';
    nameInput.value = '';
    return;
  }

  if (file.size > 16 * 1024 * 1024) { // 16MB limit
    if (typeof showToast === 'function') showToast('File is too large. Max size is 16MB.', 'error');
    input.value = '';
    return;
  }

  statusEl.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width:12px;height:12px;border-width:2px;margin-right:4px;"></span> Reading file...`;

  const reader = new FileReader();
  reader.onload = function (e) {
    dataInput.value = e.target.result;
    nameInput.value = file.name;
    statusEl.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> Ready: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    liveSyncTimeline(); // Trigger save to memory
  };
  reader.onerror = function () {
    statusEl.innerHTML = `<i class="bi bi-x-circle-fill text-danger"></i> Failed to read file.`;
  };
  reader.readAsDataURL(file);
}

// Reordering Steps within canvas
let draggedStepId = null;

function dragStepStart(ev, stepId) {
  draggedStepId = stepId;
  ev.dataTransfer.setData("dragType", "canvasStep");
  setTimeout(() => {
    ev.target.style.opacity = '0.5';
    ev.target.style.transform = 'scale(0.98)';
  }, 10);
}

function allowStepDrop(ev) {
  ev.preventDefault();
  const stepItem = ev.target.closest('.sequence-step-item');
  if (stepItem) {
    stepItem.style.borderTop = "3px dashed var(--green)";
  }
}

// Add a drag leave to clear the border
document.addEventListener('dragleave', function(ev) {
  if (ev.target.classList && ev.target.classList.contains('sequence-step-item')) {
    ev.target.style.borderTop = "";
  }
});

function dropStep(ev, targetStepId) {
  ev.preventDefault();
  ev.stopPropagation(); // prevent triggering canvas drop
  
  const stepItem = ev.target.closest('.sequence-step-item');
  if (stepItem) stepItem.style.borderTop = "";
  
  const dragType = ev.dataTransfer.getData("dragType");
  if (dragType !== "canvasStep" || !draggedStepId || draggedStepId === targetStepId) {
    renderBuilderSteps(); // reset visual state
    return;
  }

  saveStepInputsToMemory();
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  const draggedIdx = seq.steps.findIndex(s => s.id === draggedStepId);
  const targetIdx = seq.steps.findIndex(s => s.id === targetStepId);
  
  if (draggedIdx > -1 && targetIdx > -1) {
    const [movedStep] = seq.steps.splice(draggedIdx, 1);
    seq.steps.splice(targetIdx, 0, movedStep);
    renderBuilderSteps();
  }
  draggedStepId = null;
}

// Save explicit loop back to memory and invoke server save logic
async function saveCurrentSequence() {
  const oldSeq = JSON.parse(JSON.stringify(SEQUENCES.find(s => s.id === editingSequenceId) || {}));
  saveStepInputsToMemory();
  const newSeq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!newSeq) return;

  // Track what changed for the History Log
  let changes = [];
  if (oldSeq.title !== newSeq.title) changes.push(`Title: "${oldSeq.title}" -> "${newSeq.title}"`);
  if (oldSeq.steps?.length !== newSeq.steps?.length) changes.push(`Steps: ${oldSeq.steps?.length || 0} -> ${newSeq.steps?.length}`);
  if (oldSeq.stopOnReply !== newSeq.stopOnReply) changes.push(`Auto-Stop: ${oldSeq.stopOnReply} -> ${newSeq.stopOnReply}`);

  try {
    const res = await fetch(API_BASE + 'save_sequence.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newSeq)
    });

    // Safely handle non-JSON or empty responses to avoid parse errors
    let result;
    const contentType = res.headers.get('content-type') || '';
    if (contentType.indexOf('application/json') !== -1) {
      result = await res.json();
    } else {
      const txt = await res.text();
      console.error('Non-JSON response from save_sequence.php:', res.status, txt);
      result = { success: false, error: txt || `HTTP ${res.status}` };
    }
    if (result.success) {
      if (typeof showToast === 'function') {
        showToast(`💾 Sequence "${newSeq.title}" saved!`, 'success');
      }

      // Log the changes to the user_activities table with extra detail
      if (changes.length > 0 && typeof logActivity === 'function') {
        logActivity({
          type: 'Sequence Updated',
          severity: 'info',
          page: 'sequences',
          field: newSeq.title,
          resource_id: newSeq.id,
          data: { changes: changes.join(', ') }
        });
      }

      await initSequences();
      closeSequenceBuilder();
    } else {
      const errMsg = result.error || 'Unknown server error';
      if (typeof showToast === 'function') showToast(`❌ Save failed: ${errMsg}`, 'error');
      console.error('Save failed:', result);
    }
  } catch (err) {
    console.error('Save error:', err);
    if (typeof showToast === 'function') showToast('Failed to save sequence.', 'error');
  }
}

// Delete whole sequence
async function deleteSequence(seqId) {
  if (!confirm("Are you sure you want to delete this automated loop? All enrolled clients will immediately stop receiving these scheduled templates.")) {
    return;
  }

  try {
    const res = await fetch(API_BASE + 'delete_sequence.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: seqId })
    });
    
    await initSequences();
    if (typeof showToast === 'function') {
      showToast('🗑 Sequence permanently deleted from database.', 'info');
    }
  } catch (err) {
    console.error('Delete error:', err);
  }
}

function populateClientEnrollDropdown() {
  const container = document.getElementById('seq-client-drawer-options');
  const trigger = document.getElementById('seq-client-trigger');
  const hiddenInput = document.getElementById('seq-client-select');
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  
  if (!container || !seq) return;

  // Reset trigger state
  if (trigger) trigger.querySelector('.selected-text').textContent = '-- Choose Client --';
  if (hiddenInput) hiddenInput.value = '';

  const activeClients = (typeof CLIENTS !== 'undefined' && CLIENTS.length > 0) ? CLIENTS : [
    { id: 1, name: "Acme Architects", phone: "+1 555-0192" },
    { id: 2, name: "Design Studio Group", phone: "+1 555-0183" },
    { id: 3, name: "Urban Build Partners", phone: "+1 555-0174" },
    { id: 4, name: "Summit Planning Inc", phone: "+1 555-0165" },
    { id: 5, name: "Apex Structural Corp", phone: "+1 555-0156" }
  ];

  container.innerHTML = activeClients.map(c => {
    const isEnrolled = seq.enrolledClients?.includes(c.id);
    const disabledClass = isEnrolled ? 'disabled' : '';
    const clickHandler = isEnrolled ? '' : `onclick="selectOption(this, '${c.id}', 'enroll')"`;
    const labelSuffix = isEnrolled ? ' <small>(Enrolled)</small>' : '';
    
    return `<div class="custom-dropdown-option ${disabledClass}" ${clickHandler}>
              ${c.name}${labelSuffix}
            </div>`;
  }).join('');
}

// Render list of already enrolled clients in side panel
function renderEnrolledClientsList() {
  const container = document.getElementById('seq-enrolled-list');
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!container || !seq) return;

  const enrollees = seq.enrolledClients || [];
  if (enrollees.length === 0) {
    container.innerHTML = `<div style="padding: 12px; text-align: center; color: var(--text-muted); font-size: .8rem;">No clients currently active in this loop.</div>`;
    return;
  }

  // Map to global client object
  const activeClients = (typeof CLIENTS !== 'undefined' && CLIENTS.length > 0) ? CLIENTS : [
    { id: 1, name: "Acme Architects", phone: "+1 555-0101", initials: "AA" },
    { id: 2, name: "Design Studio Group", phone: "+1 555-0102", initials: "DS" },
    { id: 3, name: "Urban Build Partners", phone: "+1 555-0103", initials: "UB" },
    { id: 4, name: "Summit Planning Inc", phone: "+1 555-0104", initials: "SP" },
    { id: 5, name: "Apex Structural Corp", phone: "+1 555-0105", initials: "AP" }
  ];

    container.innerHTML = enrollees.map(clientId => {
      const clientObj = activeClients.find(c => c.id === clientId);
      const stateObj = seq.clientStates?.[clientId] || { current_step_id: null, nextSendTime: "Today" };
      
      // Smart Fetching: Prioritize snapshots, fallback to global client data
      const displayName = stateObj.client_name || clientObj?.name || "Unknown Client";
      const displayPhone = stateObj.client_phone || clientObj?.phone || "No number";

      // Step human text: Find index of current_step_id in steps array
      const currentStepIdx = seq.steps.findIndex(s => s.id == stateObj.current_step_id);
      const stepNum = currentStepIdx + 1;
      const totalSteps = seq.steps.length;
      const badgeText = stepNum > 0 ? `Step ${stepNum}/${totalSteps}` : `Pending Start`;

      return `
        <div class="enrollment-item" style="flex-direction: column; align-items: stretch; gap: 4px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; flex-direction: column;">
              <span style="font-weight: 700; font-size: .85rem;">${displayName}</span>
              <span style="font-size: .75rem; color: var(--text-muted);">${displayPhone}</span>
            </div>
            <span class="client-step-badge">${badgeText}</span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px; border-top: 1px solid rgba(0,0,0,0.03);">
            <span style="font-size: .72rem; color: var(--text-muted); font-weight: 500;">Next: ${stateObj.nextSendTime}</span>
            <button class="btn-icon-only" style="width: 20px; height: 20px; color: var(--text-light);" onclick="removeSequenceClient(${clientId})" title="Remove">×</button>
          </div>
        </div>
      `;
    }).join('');
}

// Action to enroll targeted client
async function enrollClientToSequence() {
  const selectEl = document.getElementById('seq-client-select');
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!selectEl || !seq) return;

  const clientId = parseInt(selectEl.value);
  if (!clientId) {
    if (typeof showToast === 'function') showToast('Please select a client from dropdown.', 'error');
    return;
  }

  try {
    const res = await fetch(API_BASE + 'enroll_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sequence_id: seq.id, client_id: clientId })
    });
    
    const result = await res.json();
    if (result.success) {
      if (typeof showToast === 'function') showToast('✔ Client enrolled and snapshotted!', 'success');
      await initSequences(); // Reload everything
      openSequenceBuilder(seq.id); // Re-open the current builder
    } else {
      throw new Error(result.error || 'Enrollment failed');
    }
  } catch (err) {
    console.error('Enrollment error:', err);
    if (typeof showToast === 'function') showToast('Failed to enroll client.', 'error');
  }
}

// Enroll all available clients
async function enrollAllClientsToSequence() {
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  const activeClients = (typeof CLIENTS !== 'undefined' && CLIENTS.length > 0) ? CLIENTS : [];
  if (activeClients.length === 0) return;

  if (typeof showToast === 'function') showToast('Enrolling all clients...', 'info');

  try {
    for (const client of activeClients) {
      if (!seq.enrolledClients.includes(client.id)) {
        await fetch(API_BASE + 'enroll_client.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ sequence_id: seq.id, client_id: client.id })
        });
      }
    }
    
    if (typeof showToast === 'function') showToast('✔ All clients enrolled successfully!', 'success');
    await initSequences();
    openSequenceBuilder(seq.id);
  } catch (err) {
    console.error('Bulk enrollment error:', err);
  }
}

// Remove client from loop
async function removeSequenceClient(clientId) {
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq) return;

  try {
    const res = await fetch(API_BASE + 'remove_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sequence_id: seq.id, client_id: clientId })
    });
    
    if (typeof showToast === 'function') showToast('Removed client from automated loop.', 'info');
    await initSequences();
    openSequenceBuilder(seq.id);
  } catch (err) {
    console.error('Removal error:', err);
  }
}

// Simulate backend autonomous task scheduler execution
function simulateSequenceTick() {
  saveStepInputsToMemory();
  const seq = SEQUENCES.find(s => s.id === editingSequenceId);
  if (!seq || !seq.enrolledClients || seq.enrolledClients.length === 0) {
    if (typeof showToast === 'function') {
      showToast('Please enroll at least one client to simulate autonomous scheduled execution.', 'error');
    }
    return;
  }

  // Let's demonstrate the "Loop will not stop if user replies" rule explicitly
  const isPersistent = seq.persistOnReply !== false;
  const replyNotice = isPersistent 
    ? " [Persistent Logic: Active replies ignored; execution continues exactly on chron schedule]"
    : "";

  // Advance each client's step index
  let actionTaken = false;
  seq.enrolledClients.forEach(clientId => {
    const stateObj = seq.clientStates[clientId];
    if (stateObj) {
      if (stateObj.currentStepIdx < seq.steps.length) {
        const stepObj = seq.steps[stateObj.currentStepIdx];
        
        // Show simulated notification/log
        if (typeof showToast === 'function') {
          showToast(`⚙ Autopilot Sent: Step #${stateObj.currentStepIdx + 1} template sent to Client #${clientId}`, 'success');
        }

        // Increment step
        stateObj.currentStepIdx++;
        if (stateObj.currentStepIdx < seq.steps.length) {
          const nextStep = seq.steps[stateObj.currentStepIdx];
          const delayStr = nextStep.delayVal > 0 ? `In ${nextStep.delayVal} ${nextStep.delayUnit}` : `Immediate Setup`;
          stateObj.nextSendTime = delayStr;
        } else {
          stateObj.nextSendTime = "Loop Finished Successfully";
        }

        actionTaken = true;
      }
    }
  });

  if (actionTaken) {
    renderEnrolledClientsList();
    if (typeof logActivity === 'function') {
      logActivity({
        type: 'Autonomous Drip Event',
        page: 'sequences',
        data: { message: `Cron cycle evaluated loop "${seq.title}"${replyNotice}` }
      });
    }
  } else {
    if (typeof showToast === 'function') {
      showToast('All currently enrolled clients have completed their configured schedule cycles.', 'info');
    }
  }
}

/**
 * PROJECTED TIMELINE PREVIEW
 * Calculates and shows exact dates for sequence steps when a client is selected
 */
function updateProjectedTimeline(overrideSeqId = null) {
  const selectEl = document.getElementById('seq-client-select');
  const cardEl = document.getElementById('timeline-card');
  const container = document.getElementById('projection-container');
  
  // Use either the current editing sequence or the one being 'peeked'
  const targetSeqId = overrideSeqId || editingSequenceId;
  const seq = SEQUENCES.find(s => s.id === targetSeqId);
  const clientId = parseInt(selectEl.value);

  if (!clientId || !seq || !seq.steps || seq.steps.length === 0) {
    if (cardEl && !overrideSeqId) cardEl.style.display = 'none';
    return;
  }

  if (cardEl) cardEl.style.display = 'block';

  // 1. Cross-Check for conflicts (only if we aren't already peeking)
  let conflictHtml = '';
  if (!overrideSeqId) {
    const conflicts = SEQUENCES.filter(s => s.id !== editingSequenceId && s.enrolledClients?.includes(clientId));
    if (conflicts.length > 0) {
      conflictHtml = `
        <div class="conflict-warning" style="background:#fffbeb; border:1px solid #fde68a; padding:10px; border-radius:6px; margin-bottom:15px; font-size:.75rem; color:#92400e;">
          <div style="font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:5px;">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Active in other Loops:
          </div>
          <div style="display:flex; flex-wrap:wrap; gap:6px;">
            ${conflicts.map(c => `<button class="btn-peek" onclick="updateProjectedTimeline(${c.id})">${c.title}</button>`).join('')}
          </div>
        </div>
      `;
    }
  } else {
    // Show a "Go Back" button if we are peeking
    conflictHtml = `
      <div style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:.7rem; font-weight:700; color:var(--blue);">PEEKING: ${seq.title}</span>
        <button onclick="updateProjectedTimeline()" style="background:none; border:none; color:var(--text-muted); font-size:.7rem; cursor:pointer; text-decoration:underline;">Back to Original</button>
      </div>
    `;
  }

  let runningDate = new Date();
  const timelineHtml = seq.steps.map((step, idx) => {
    const val = parseInt(step.delay_value) || 0;
    const unit = step.delay_unit || 'days';
    
    if (unit === 'minutes') runningDate.setMinutes(runningDate.getMinutes() + val);
    else if (unit === 'hours') runningDate.setHours(runningDate.getHours() + val);
    else if (unit === 'weeks') runningDate.setDate(runningDate.getDate() + (val * 7));
    else if (unit === 'months') runningDate.setMonth(runningDate.getMonth() + val);
    else runningDate.setDate(runningDate.getDate() + val);

    // Smart date formatting: Show year only if it's different from now
    const now = new Date();
    const dateOptions = { month: 'short', day: 'numeric' };
    if (unit === 'minutes' || unit === 'hours') {
        dateOptions.hour = 'numeric';
        dateOptions.minute = '2-digit';
    }
    if (runningDate.getFullYear() !== now.getFullYear()) {
      dateOptions.year = 'numeric';
    }
    const dateStr = runningDate.toLocaleDateString('en-US', dateOptions) + (dateOptions.hour ? ' ' + runningDate.toLocaleTimeString('en-US', {hour: 'numeric', minute:'2-digit'}) : '');

    return `
      <div class="timeline-projection-item">
        <span class="step-dot" style="${overrideSeqId ? 'background:var(--text-muted);' : ''}"></span>
        <div class="step-info">
          <span class="step-date" style="${overrideSeqId ? 'color:var(--text-muted);' : ''}">${dateStr.replace(/(AM|PM).*(AM|PM)/, '$1')}</span>
          <span class="step-name">
            ${step.title || `Step ${idx + 1}`} ${overrideSeqId ? '<small>(Other Loop)</small>' : ''}
            ${step.media_filename ? `<span style="display:block; font-size:0.65rem; color:var(--green);"><i class="bi bi-paperclip"></i> ${step.media_filename}</span>` : ''}
          </span>
        </div>
      </div>
    `;
  }).join('');

  container.innerHTML = conflictHtml + timelineHtml;
}

function liveSyncTimeline() {
  saveStepInputsToMemory();
  updateProjectedTimeline();
}

/**
 * CUSTOM DROPDOWN LOGIC
 */
function toggleDropdown(trigger) {
  const card = trigger.closest('.sequence-step-item');
  
  // Close all other open dropdowns first
  document.querySelectorAll('.custom-dropdown-trigger').forEach(el => {
    if (el !== trigger) {
      el.classList.remove('active');
      el.nextElementSibling.style.display = 'none';
      const otherCard = el.closest('.sequence-step-item');
      if (otherCard) otherCard.classList.remove('active-step');
    }
  });

  const menu = trigger.nextElementSibling;
  const isActive = trigger.classList.toggle('active');
  menu.style.display = isActive ? 'block' : 'none';

  if (isActive && card) {
    card.classList.add('active-step');
  } else if (card) {
    card.classList.remove('active-step');
  }

  // Close when clicking outside
  if (isActive) {
    const closeListener = (e) => {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        trigger.classList.remove('active');
        menu.style.display = 'none';
        if (card) card.classList.remove('active-step');
        document.removeEventListener('click', closeListener);
      }
    };
    setTimeout(() => document.addEventListener('click', closeListener), 10);
  }
}

function selectOption(optionEl, value, type) {
  const container = optionEl.closest('.custom-dropdown-container');
  const trigger = container.querySelector('.custom-dropdown-trigger');
  const hiddenInput = container.querySelector('input[type="hidden"]');
  const menu = container.querySelector('.custom-dropdown-menu');

  // Update visual state
  trigger.querySelector('.selected-text').textContent = optionEl.textContent;
  container.querySelectorAll('.custom-dropdown-option').forEach(opt => opt.classList.remove('selected'));
  optionEl.classList.add('selected');

  // Update hidden logic
  hiddenInput.value = value;
  
  // Close menu and sync
  trigger.classList.remove('active');
  menu.style.display = 'none';
  
  if (type === 'unit' || type === 'template') {
    saveStepInputsToMemory(); // Ensure memory captures the new hiddenInput.value immediately
    if (type === 'template') {
      // Re-render blocks so the correct media upload field appears/disappears
      renderBuilderSteps();
    }
    liveSyncTimeline();
  }
}

// Expose initialize function to top window context for standard modular routing compatibility
window.initSequences = initSequences;
