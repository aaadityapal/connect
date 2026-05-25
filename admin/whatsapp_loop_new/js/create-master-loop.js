/* ══ CREATE MASTER LOOP PAGE ══ */
let MASTER_LOOP_TEMPLATES = [];
let editingMasterLoopId = null;
let isSavingMasterLoop = false;

function setMasterLoopSavingState(isSaving, status) {
  const saveBtn = document.getElementById('master-loop-save');
  const draftBtn = document.getElementById('master-loop-save-draft');
  if (!saveBtn || !draftBtn) return;

  saveBtn.disabled = isSaving;
  draftBtn.disabled = isSaving;
  saveBtn.classList.toggle('btn-loading', isSaving && status === 'active');
  draftBtn.classList.toggle('btn-loading', isSaving && status === 'draft');

  if (isSaving) {
    if (status === 'active') {
      saveBtn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Creating loop, please wait...';
      draftBtn.textContent = 'Save Draft';
    } else {
      draftBtn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Saving draft, please wait...';
      saveBtn.textContent = 'Create Loop';
    }
  } else {
    saveBtn.textContent = 'Create Loop';
    draftBtn.textContent = 'Save Draft';
  }
}

async function initCreateMasterLoop() {
  const list = document.getElementById('master-loop-template-list');
  const dropzone = document.getElementById('loop-canvas-dropzone');
  const saveBtn = document.getElementById('master-loop-save');
  const draftBtn = document.getElementById('master-loop-save-draft');
  if (!list) return;

  list.innerHTML = '<div class="template-loading">Loading templates from WhatsApp...</div>';
  if (dropzone) setupCanvasDragDrop(dropzone);
  if (saveBtn) saveBtn.onclick = () => saveMasterLoop('active');
  if (draftBtn) draftBtn.onclick = () => saveMasterLoop('draft');
  fetchMasterLoopsList();

  try {
    const response = await fetch(API_BASE + 'get_templates.php');
    const result = await response.json();

    MASTER_LOOP_TEMPLATES = normalizeMasterLoopTemplates(result);

    if (MASTER_LOOP_TEMPLATES.length === 0) {
      list.innerHTML = '<div class="template-empty">No approved templates available yet.</div>';
      return;
    }

    list.innerHTML = MASTER_LOOP_TEMPLATES.map(t => {
      const status = t.status || 'APPROVED';
      return `
        <div class="template-card" draggable="true" data-template-key="${t.key}">
          <div class="template-title">${t.label}</div>
          <div class="template-meta">${status} • ${t.language || 'en'}</div>
        </div>
      `;
    }).join('');

    bindTemplateDragEvents(list);
  } catch (error) {
    console.error('Failed to fetch templates for master loop:', error);
    list.innerHTML = '<div class="template-empty">Failed to load templates. Try again.</div>';
  }
}

async function saveMasterLoop(status) {
  if (isSavingMasterLoop) return;

  const nameInput = document.getElementById('master-loop-name');
  const loopName = nameInput ? nameInput.value.trim() : '';
  if (!loopName) {
    showToast('Please enter a loop name.', 'error');
    return;
  }

  const items = Array.from(document.querySelectorAll('#loop-canvas-items .canvas-item'));
  if (items.length === 0) {
    showToast('Add at least one template to the canvas.', 'error');
    return;
  }

  isSavingMasterLoop = true;
  setMasterLoopSavingState(true, status);

  try {
    const steps = await Promise.all(items.map(async (item, index) => {
      const delayValueEl = item.querySelector('.canvas-delay-value');
      const delayUnitEl = item.querySelector('.canvas-delay-unit');
      const mediaFileEl = item.querySelector('.canvas-media-file');

      const mediaPayload = await readMediaFile(mediaFileEl);
      return {
        step_order: index + 1,
        template_key: item.dataset.templateKey || '',
        header_type: item.dataset.headerType || 'NONE',
        delay_value: delayValueEl ? parseInt(delayValueEl.value, 10) || 0 : 0,
        delay_unit: delayUnitEl ? delayUnitEl.value.toLowerCase() : 'days',
        media_data: mediaPayload.data,
        media_filename: mediaPayload.name,
        media_path: item.dataset.mediaPath || ''
      };
    }));

    const response = await fetch(API_BASE + 'save_master_loop.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: editingMasterLoopId,
        name: loopName,
        status: status,
        steps: steps
      })
    });

    const result = await response.json();
    if (result.success) {
      editingMasterLoopId = result.id || editingMasterLoopId;
      showToast('Master loop saved successfully.', 'success');
      fetchMasterLoopsList();
    } else {
      showToast(result.error || 'Failed to save master loop.', 'error');
    }
  } catch (error) {
    console.error('Save master loop failed:', error);
    showToast('Failed to save master loop.', 'error');
  } finally {
    isSavingMasterLoop = false;
    setMasterLoopSavingState(false, status);
  }
}

async function fetchMasterLoopsList() {
  const list = document.getElementById('master-loops-list');
  if (!list) return;
  list.innerHTML = '<div class="saved-loops-empty">Loading saved loops...</div>';

  try {
    const response = await fetch(API_BASE + 'get_master_loops.php');
    const result = await response.json();

    if (!result.success || !Array.isArray(result.data)) {
      list.innerHTML = '<div class="saved-loops-empty">Failed to load loops.</div>';
      return;
    }

    if (result.data.length === 0) {
      list.innerHTML = '<div class="saved-loops-empty">No loops saved yet.</div>';
      return;
    }

    list.innerHTML = result.data.map(loop => {
      const meta = `${loop.status} • ${loop.step_count} steps`;
      return `
        <div class="saved-loop-item">
          <div>
            <div class="saved-loop-title">${loop.name}</div>
            <div class="saved-loop-meta">${meta}</div>
          </div>
          <div class="saved-loop-actions">
            <button class="saved-loop-edit" type="button" data-loop-id="${loop.id}">Edit</button>
            <button class="saved-loop-delete" type="button" data-loop-id="${loop.id}">Delete</button>
          </div>
        </div>
      `;
    }).join('');

    list.querySelectorAll('.saved-loop-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.loopId, 10);
        if (id) loadMasterLoop(id);
      });
    });

    list.querySelectorAll('.saved-loop-delete').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.loopId, 10);
        if (id) deleteMasterLoop(id);
      });
    });
  } catch (error) {
    console.error('Failed to load master loops:', error);
    list.innerHTML = '<div class="saved-loops-empty">Failed to load loops.</div>';
  }
}

async function loadMasterLoop(loopId) {
  try {
    const response = await fetch(API_BASE + `get_master_loop.php?id=${loopId}`);
    const result = await response.json();
    if (!result.success || !result.data) {
      showToast('Unable to load loop.', 'error');
      return;
    }

    editingMasterLoopId = result.data.id;
    const nameInput = document.getElementById('master-loop-name');
    if (nameInput) nameInput.value = result.data.name || '';

    const items = document.getElementById('loop-canvas-items');
    const empty = document.getElementById('loop-canvas-empty');
    if (items) items.innerHTML = '';
    if (empty) empty.style.display = 'flex';

    const steps = Array.isArray(result.data.steps) ? result.data.steps : [];
    steps.sort((a, b) => (a.step_order || 0) - (b.step_order || 0));
    steps.forEach(step => {
      const template = MASTER_LOOP_TEMPLATES.find(t => t.key === step.template_key) || {
        key: step.template_key,
        label: step.template_key.replace(/_/g, ' '),
        header_type: step.header_type || 'NONE'
      };
      addTemplateToCanvas(template, step);
    });

    showToast('Loop loaded. You can edit now.', 'success');
  } catch (error) {
    console.error('Load master loop failed:', error);
    showToast('Unable to load loop.', 'error');
  }
}

async function deleteMasterLoop(loopId) {
  if (!confirm('Delete this loop? This cannot be undone.')) return;

  try {
    const response = await fetch(API_BASE + 'delete_master_loop.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: loopId })
    });
    const result = await response.json();

    if (result.success) {
      showToast('Loop deleted.', 'success');
      if (editingMasterLoopId === loopId) {
        editingMasterLoopId = null;
        const nameInput = document.getElementById('master-loop-name');
        if (nameInput) nameInput.value = '';
        const items = document.getElementById('loop-canvas-items');
        const empty = document.getElementById('loop-canvas-empty');
        if (items) items.innerHTML = '';
        if (empty) empty.style.display = 'flex';
      }
      fetchMasterLoopsList();
    } else {
      showToast(result.error || 'Failed to delete loop.', 'error');
    }
  } catch (error) {
    console.error('Delete loop failed:', error);
    showToast('Failed to delete loop.', 'error');
  }
}

function readMediaFile(inputEl) {
  return new Promise((resolve) => {
    if (!inputEl || !inputEl.files || inputEl.files.length === 0) {
      resolve({ data: '', name: '' });
      return;
    }

    const file = inputEl.files[0];
    const reader = new FileReader();
    reader.onload = () => resolve({ data: reader.result || '', name: file.name });
    reader.onerror = () => resolve({ data: '', name: file.name });
    reader.readAsDataURL(file);
  });
}

function bindTemplateDragEvents(list) {
  list.querySelectorAll('.template-card').forEach(card => {
    card.addEventListener('dragstart', (event) => {
      card.classList.add('dragging');
      event.dataTransfer.effectAllowed = 'copy';
      event.dataTransfer.setData('text/plain', card.dataset.templateKey || '');
    });

    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
    });
  });
}

function setupCanvasDragDrop(dropzone) {
  dropzone.addEventListener('dragover', (event) => {
    event.preventDefault();
    dropzone.classList.add('drag-over');

    const items = document.getElementById('loop-canvas-items');
    const dragging = items ? items.querySelector('.canvas-dragging') : null;
    if (!items || !dragging) return;

    const afterElement = getDragAfterElement(items, event.clientY);
    if (afterElement == null) {
      items.appendChild(dragging);
    } else {
      items.insertBefore(dragging, afterElement);
    }
  });

  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('drag-over');
  });

  dropzone.addEventListener('drop', (event) => {
    event.preventDefault();
    dropzone.classList.remove('drag-over');

    const key = event.dataTransfer.getData('text/plain');
    const template = MASTER_LOOP_TEMPLATES.find(t => t.key === key);
    if (template) {
      addTemplateToCanvas(template);
    }
    renumberCanvasSteps();
  });
}

function getDragAfterElement(container, y) {
  const draggableElements = [...container.querySelectorAll('.canvas-item:not(.canvas-dragging)')];

  return draggableElements.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) {
      return { offset: offset, element: child };
    }
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function addTemplateToCanvas(template) {
  const items = document.getElementById('loop-canvas-items');
  const empty = document.getElementById('loop-canvas-empty');
  if (!items) return;

  const stepNumber = items.children.length + 1;
  const item = document.createElement('div');
  item.className = 'canvas-item';
  item.dataset.templateKey = template.key;
  item.dataset.headerType = template.header_type || 'NONE';
  item.setAttribute('draggable', 'true');
  const mediaConfig = getMediaConfig(template.header_type);
  item.innerHTML = `
    <div class="canvas-item-left">
      <span class="canvas-step">${stepNumber}</span>
      <div>
        <div class="canvas-item-title">${template.label}</div>
        <div class="canvas-item-meta">${template.key}</div>
      </div>
    </div>
    <div class="canvas-item-actions">
      <span class="canvas-item-badge">Added</span>
      <button class="canvas-item-remove" type="button" aria-label="Remove step">Remove</button>
    </div>
    <div class="canvas-item-fields">
      <div class="canvas-field-row">
        <div class="canvas-field">
          <label class="canvas-label">Initial Target Day</label>
          <div class="canvas-input-group">
            <input class="canvas-input canvas-delay-value" type="number" min="0" value="1" />
            <select class="canvas-select canvas-delay-unit">
              <option>Minutes</option>
              <option>Hours</option>
              <option>Days</option>
              <option>Weeks</option>
              <option>Months</option>
            </select>
          </div>
          <div class="canvas-help">interval before triggering</div>
        </div>
      </div>
      <div class="canvas-field-row">
        <div class="canvas-field">
          <label class="canvas-label">${mediaConfig.label}</label>
          <input class="canvas-file canvas-media-file" type="file" ${mediaConfig.accept ? `accept="${mediaConfig.accept}"` : ''} ${mediaConfig.required ? 'required' : ''} />
          <div class="canvas-help">${mediaConfig.help}</div>
        </div>
      </div>
    </div>
  `;

  items.appendChild(item);
  if (empty) empty.style.display = 'none';

  bindCanvasItemEvents(item);
  renumberCanvasSteps();

  const dropzone = document.getElementById('loop-canvas-dropzone');
  if (dropzone) {
    dropzone.scrollTop = dropzone.scrollHeight;
  }
}

function bindCanvasItemEvents(item) {
  const removeBtn = item.querySelector('.canvas-item-remove');
  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      item.remove();
      renumberCanvasSteps();
      const empty = document.getElementById('loop-canvas-empty');
      const items = document.getElementById('loop-canvas-items');
      if (empty && items && items.children.length === 0) {
        empty.style.display = 'flex';
      }
    });
  }

  item.addEventListener('dragstart', (event) => {
    item.classList.add('canvas-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  item.addEventListener('dragend', () => {
    item.classList.remove('canvas-dragging');
    renumberCanvasSteps();
  });
}

function renumberCanvasSteps() {
  document.querySelectorAll('#loop-canvas-items .canvas-item').forEach((el, idx) => {
    const step = el.querySelector('.canvas-step');
    if (step) step.textContent = String(idx + 1);
  });
}

function getMediaConfig(headerType) {
  const type = (headerType || 'NONE').toUpperCase();
  if (type === 'IMAGE') {
    return {
      label: 'Attach Image Required',
      accept: 'image/*',
      required: true,
      help: 'This template requires an image file.'
    };
  }
  if (type === 'VIDEO') {
    return {
      label: 'Attach Video Required',
      accept: 'video/*',
      required: true,
      help: 'This template requires a video file.'
    };
  }
  if (type === 'DOCUMENT') {
    return {
      label: 'Attach Document Required',
      accept: '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      required: true,
      help: 'This template requires a document file.'
    };
  }
  return {
    label: 'Attach Media',
    accept: '',
    required: false,
    help: 'Upload optional media for this step.'
  };
}

function normalizeMasterLoopTemplates(result) {
  if (result && result.success && Array.isArray(result.data)) {
    return result.data
      .filter(t => t.status === 'APPROVED')
      .map(t => ({
        key: t.key,
        label: t.label || t.key.replace(/_/g, ' '),
        status: t.status,
        language: t.language,
        header_type: t.header_type || 'NONE'
      }));
  }

  if (Array.isArray(result)) {
    return result.map(t => ({
      key: t.key || t.name,
      label: t.label || (t.name ? t.name.replace(/_/g, ' ') : 'Unnamed'),
      status: t.status || 'APPROVED',
      language: t.language || 'en',
      header_type: t.header_type || 'NONE'
    }));
  }

  return [];
}
