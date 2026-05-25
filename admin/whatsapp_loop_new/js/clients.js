let AVAILABLE_TAGS = ['Lead', 'Customer', 'VIP', 'Prospect', 'Partner']; // Default fallbacks
let currentPageNumber = 1;
const itemsPerPage = 50;
let currentSearchQuery = '';

async function fetchTags() {
  try {
    const res = await fetch('api/get_tags.php');
    const data = await res.json();
    if(data && data.length > 0) {
      AVAILABLE_TAGS = data.map(t => t.name);
    }
  } catch (err) {
    console.error('Error fetching tags:', err);
  }
}

function renderTagSelectors() {
  const t1 = document.getElementById('tag-selector');
  const t2 = document.getElementById('import-tag-selector');
  const filterSelect = document.getElementById('clients-tag-filter');
  
  if(t1) {
    t1.innerHTML = AVAILABLE_TAGS.map(t => `<button type="button" class="tag-option" data-tag="${t}" onclick="toggleTag(this)">${t}</button>`).join('');
  }
  if(t2) {
    t2.innerHTML = AVAILABLE_TAGS.map(t => `<button type="button" class="tag-option" data-tag="${t}" onclick="toggleImportTag(this)">${t}</button>`).join('');
  }
  if(filterSelect) {
    const currentVal = filterSelect.value;
    filterSelect.innerHTML = `<option value="All">All Tags</option>` + AVAILABLE_TAGS.map(t => `<option value="${t}">${t}</option>`).join('');
    filterSelect.value = currentVal || 'All';
  }
}

function addCustomTag(containerId, inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const val = input.value.trim();
  if (!val) return;
  
  const container = document.getElementById(containerId);
  if (!container) return;
  
  // check if already exists visually
  const existing = container.querySelector(`[data-tag="${val}"]`);
  if (existing) {
    existing.classList.add('active');
  } else {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tag-option active';
    btn.dataset.tag = val;
    btn.textContent = val;
    if(containerId === 'import-tag-selector') {
      btn.onclick = function() { toggleImportTag(this) };
    } else {
      btn.onclick = function() { toggleTag(this) };
    }
    container.appendChild(btn);
  }
  input.value = '';
}

async function initClients() {
  await fetchTags();
  renderTagSelectors();
  renderClientsTable();
  bindClientsSearch();

  // Close modal on overlay click
  const modal = document.getElementById('add-client-modal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeAddClientModal();
    });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAddClientModal();
  });
}

let monthsPopulated = false;

function populateMonthFilter() {
  const filterSelect = document.getElementById('clients-month-filter');
  if (!filterSelect) return;
  
  const currentVal = filterSelect.value;
  const map = new Map();
  CLIENTS.forEach(c => {
    if(c.added_month && c.added_month_label) {
      map.set(c.added_month, c.added_month_label);
    }
  });
  
  const sortedMonths = Array.from(map.keys()).sort().reverse();
  filterSelect.innerHTML = `<option value="All">All Months</option>` + sortedMonths.map(m => `<option value="${m}">${map.get(m)}</option>`).join('');
  
  // Try to restore value, otherwise All
  if (Array.from(filterSelect.options).some(o => o.value === currentVal)) {
      filterSelect.value = currentVal;
  } else {
      filterSelect.value = 'All';
  }
}

function renderClientsTable() {
  populateMonthFilter();

  const tbody = document.getElementById('clients-tbody');
  const countEl = document.getElementById('client-count-subtitle');
  const filterSelect = document.getElementById('clients-tag-filter');
  const tagFilter = filterSelect ? filterSelect.value : 'All';
  
  const monthFilterSelect = document.getElementById('clients-month-filter');
  const monthFilter = monthFilterSelect ? monthFilterSelect.value : 'All';
  
  if (!tbody) return;
  
  let filteredClients = CLIENTS;
  
  if (tagFilter !== 'All') {
    filteredClients = filteredClients.filter(c => c.tags && c.tags.includes(tagFilter));
  }
  
  if (monthFilter !== 'All') {
    filteredClients = filteredClients.filter(c => c.added_month === monthFilter);
  }
  
  if (currentSearchQuery) {
    filteredClients = filteredClients.filter(c => {
      return (c.name || '').toLowerCase().includes(currentSearchQuery) ||
             (c.phone || '').toLowerCase().includes(currentSearchQuery) ||
             (c.email || '').toLowerCase().includes(currentSearchQuery);
    });
  }
  
  if (countEl) countEl.textContent = `${filteredClients.length} contacts matching`;
  
  if (filteredClients.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No clients found matching criteria.</td></tr>';
    updatePaginationUI(0, 0, 0);
    return;
  }

  const totalItems = filteredClients.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  
  if (currentPageNumber > totalPages) currentPageNumber = totalPages;
  if (currentPageNumber < 1) currentPageNumber = 1;
  
  const startIndex = (currentPageNumber - 1) * itemsPerPage;
  const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
  
  const paginatedClients = filteredClients.slice(startIndex, endIndex);

  tbody.innerHTML = paginatedClients.map(c => buildClientRow(c)).join('');
  
  updatePaginationUI(startIndex + 1, endIndex, totalItems);
}

function updatePaginationUI(start, end, total) {
  const infoEl = document.getElementById('clients-pagination-info');
  const prevBtn = document.getElementById('btn-prev-page');
  const nextBtn = document.getElementById('btn-next-page');
  
  if (infoEl) {
    infoEl.textContent = `Showing ${start} to ${end} of ${total} entries`;
  }
  
  if (prevBtn) {
    prevBtn.disabled = (currentPageNumber <= 1);
  }
  if (nextBtn) {
    const totalPages = Math.ceil(total / itemsPerPage);
    nextBtn.disabled = (currentPageNumber >= totalPages);
  }
}

function changeClientsPage(direction) {
  currentPageNumber += direction;
  renderClientsTable();
}

window.changeClientsPage = changeClientsPage;

function buildClientRow(c) {
  // Use DB provided dates/counts or defaults
  const date = c.added_date || 'N/A';
  const campaigns = c.campaign_count || 0;
  
  const tagsHtml = (c.tags || []).map(t => `<span class="client-tag-mini">${t}</span>`).join('');
  
  return `
    <tr data-client-id="${c.id}">
      <td><div class="client-cell">
        <div class="client-initials">${c.initials}</div>
        <div>
          <div class="client-name">${c.name}</div>
          <div class="client-tags-list">${tagsHtml}</div>
        </div>
      </div></td>
      <td>${c.phone}</td>
      <td class="time-cell">${date}</td>
      <td style="font-weight:600;color:var(--green-dark)">${campaigns}</td>
      <td><span class="status-badge badge-delivered">${c.status || 'Active'}</span></td>
      <td>
        <div class="actions-cell">
          <button class="action-icon-btn view" onclick="viewClient(${c.id})" title="View Client">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="action-icon-btn edit" onclick="editClient(${c.id})" title="Edit Client">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="action-icon-btn delete" onclick="removeClientRow(${c.id}, '${c.name.split(' ')[0]}')" title="Delete Client">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="10" y1="11" x2="10" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="14" y1="11" x2="14" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `;
}

window.viewClient = function(id) {
  const client = CLIENTS.find(c => c.id == id);
  if (!client) return;

  const modal = document.getElementById('view-client-modal');
  if (!modal) return;

  // Populate data
  document.getElementById('view-client-name').textContent = client.name || 'N/A';
  document.getElementById('view-client-phone').textContent = client.phone || 'N/A';
  document.getElementById('view-client-email').textContent = client.email || 'N/A';
  document.getElementById('view-client-date').textContent = client.added_date || 'N/A';
  document.getElementById('view-client-status').textContent = client.status || 'Active';
  
  if (client.notes) {
    document.getElementById('view-client-notes').textContent = client.notes;
  } else {
    document.getElementById('view-client-notes').innerHTML = '<span style="color:var(--text-muted);font-style:italic;">No notes provided</span>';
  }

  // Tags
  const tagsContainer = document.getElementById('view-client-tags');
  if (client.tags && client.tags.length > 0) {
    tagsContainer.innerHTML = client.tags.map(t => `<span class="client-tag-mini">${t}</span>`).join('');
  } else {
    tagsContainer.innerHTML = '<span style="color:var(--text-muted);font-style:italic;">None</span>';
  }

  modal.classList.add('open');
};

window.closeViewClientModal = function() {
  const modal = document.getElementById('view-client-modal');
  if (modal) modal.classList.remove('open');
};

let editingClientId = null;

window.editClient = function(id) {
  const client = CLIENTS.find(c => c.id == id);
  if (!client) return;

  editingClientId = id;
  const modal = document.getElementById('add-client-modal');
  if (!modal) return;

  document.getElementById('client-modal-title').textContent = 'Edit Client';
  document.getElementById('client-modal-subtitle').textContent = 'Update contact details';
  document.getElementById('save-client-btn-text').textContent = 'Update Client';

  document.getElementById('new-client-name').value = client.name || '';
  
  let phone = client.phone || '';
  if (phone.startsWith('+91 ')) phone = phone.substring(4);
  else if (phone.startsWith('+91')) phone = phone.substring(3);
  document.getElementById('new-client-phone').value = phone;

  document.getElementById('new-client-email').value = client.email || '';
  document.getElementById('new-client-notes').value = client.notes || '';

  document.querySelectorAll('#tag-selector .tag-option').forEach(t => {
    if (client.tags && client.tags.includes(t.dataset.tag)) {
      t.classList.add('active');
    } else {
      t.classList.remove('active');
    }
  });

  modal.classList.add('open');
  setTimeout(() => document.getElementById('new-client-name').focus(), 100);
};

let clientToDelete = null;

function removeClientRow(id, firstName) {
  clientToDelete = { id, firstName };
  const modal = document.getElementById('confirm-delete-modal');
  const textEl = document.getElementById('confirm-delete-text');
  if (modal && textEl) {
    textEl.innerHTML = `Are you sure you want to remove <strong>${firstName}</strong>? This action cannot be undone.`;
    modal.classList.add('open');
  }
}

function closeConfirmDeleteModal() {
  const modal = document.getElementById('confirm-delete-modal');
  if (modal) modal.classList.remove('open');
  clientToDelete = null;
}

async function executeClientDeletion() {
  if (!clientToDelete) return;
  const { id, firstName } = clientToDelete;
  
  const confirmBtn = document.getElementById('confirm-delete-btn');
  if (confirmBtn) confirmBtn.textContent = 'Deleting...';

  try {
    const response = await fetch('api/delete_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    });

    const result = await response.json();

    if (result.success) {
      closeConfirmDeleteModal();
      const row = document.querySelector(`tr[data-client-id="${id}"]`);
      if (row) {
        row.style.transition = 'opacity .3s, transform .3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => {
          row.remove();
          CLIENTS = CLIENTS.filter(c => c.id != id);
          const countEl = document.getElementById('client-count-subtitle');
          if (countEl) countEl.textContent = `${CLIENTS.length} contacts in your list`;
        }, 300);
      }
      showToast(`Contact ${firstName} removed permanently`, 'success');
      logActivity({ type: 'Client Removed', page: 'clients', data: { name: firstName, id: id } });
    } else {
      closeConfirmDeleteModal();
      showToast('Error: ' + result.message, 'error');
    }
  } catch (err) {
    console.error('Error deleting client:', err);
    closeConfirmDeleteModal();
    showToast('Failed to delete client. Check connection.', 'error');
  } finally {
    if (confirmBtn) confirmBtn.textContent = 'Delete';
  }
}

function bindClientsSearch() {
  const searchInput = document.getElementById('clients-page-search');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      currentSearchQuery = this.value.toLowerCase();
      currentPageNumber = 1;
      renderClientsTable();
    });
  }
}

// ── MODAL FUNCTIONS ───────────────────────

function openAddClientModal() {
  editingClientId = null;
  const modal = document.getElementById('add-client-modal');
  if (!modal) return;
  
  const titleEl = document.getElementById('client-modal-title');
  const subtitleEl = document.getElementById('client-modal-subtitle');
  const btnTextEl = document.getElementById('save-client-btn-text');
  
  if (titleEl) titleEl.textContent = 'Add New Client';
  if (subtitleEl) subtitleEl.textContent = 'Add a contact to your broadcast list';
  if (btnTextEl) btnTextEl.textContent = 'Save Client';

  // Reset form
  document.getElementById('new-client-name').value = '';
  document.getElementById('new-client-phone').value = '';
  document.getElementById('new-client-email').value = '';
  document.getElementById('new-client-notes').value = '';
  document.querySelectorAll('#tag-selector .tag-option').forEach(t => t.classList.remove('active'));
  document.querySelector('#tag-selector .tag-option[data-tag="Lead"]')?.classList.add('active');

  modal.classList.add('open');
  setTimeout(() => document.getElementById('new-client-name').focus(), 100);
}

function closeAddClientModal() {
  const modal = document.getElementById('add-client-modal');
  if (modal) modal.classList.remove('open');
  editingClientId = null;
}

function toggleTag(btn) {
  btn.classList.toggle('active');
}

async function saveNewClient() {
  const name  = document.getElementById('new-client-name').value.trim();
  const phone = document.getElementById('new-client-phone').value.trim();
  const email = document.getElementById('new-client-email').value.trim();
  const notes = document.getElementById('new-client-notes').value.trim();
  const tags  = [...document.querySelectorAll('.tag-option.active')].map(t => t.dataset.tag);

  // Validation
  if (!name) {
    document.getElementById('new-client-name').focus();
    document.getElementById('new-client-name').style.borderColor = 'var(--red)';
    showToast('Please enter the client\'s full name.', 'error');
    return;
  }
  if (!phone || phone.length < 10) {
    document.getElementById('new-client-phone').focus();
    document.getElementById('new-client-phone').style.borderColor = 'var(--red)';
    showToast('Please enter a valid phone number.', 'error');
    return;
  }

  const saveBtn = document.getElementById('save-client-btn');
  saveBtn.disabled = true;
  saveBtn.textContent = editingClientId ? 'Updating...' : 'Saving...';

  const endpoint = editingClientId ? 'api/update_client.php' : 'api/save_client.php';
  const payload = { name, phone: `+91 ${phone}`, email, notes, tags };
  if (editingClientId) payload.id = editingClientId;

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (result.success !== false && (result.id || result.success === true)) {
      showToast(`✅ ${name} ${editingClientId ? 'updated' : 'added'} successfully!`, 'success');
      logActivity({ type: editingClientId ? 'Client Updated' : 'Client Added', page: 'clients', data: { name: name, phone: phone, tags: tags } });
      // Refresh local data
      await fetchInitialData();
      // Re-render table if on clients page
      if (currentPage === 'clients') renderClientsTable();
      closeAddClientModal();
    } else {
      showToast('Error: ' + result.message, 'error');
    }
  } catch (err) {
    console.error('Error saving client:', err);
    showToast('Connection error. Please try again.', 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = editingClientId ? 'Update Client' : 'Save Client';
  }
}
// ── EXCEL IMPORT FUNCTIONS ───────────────────────

let parsedExcelData = [];

function openImportExcelModal() {
  const modal = document.getElementById('import-excel-modal');
  if (!modal) return;
  resetExcelUpload();
  modal.classList.add('open');
  bindExcelUploadEvents();
}

function closeImportExcelModal() {
  const modal = document.getElementById('import-excel-modal');
  if (modal) modal.classList.remove('open');
}

function toggleImportTag(btn) {
  btn.classList.toggle('active');
}

function bindExcelUploadEvents() {
  const dropzone = document.getElementById('excel-dropzone');
  const fileInput = document.getElementById('excel-file-input');
  if(!dropzone || dropzone.dataset.bound === 'true') return;

  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });

  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
  });

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      handleExcelFile(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', (e) => {
    if (e.target.files.length) {
      handleExcelFile(e.target.files[0]);
    }
  });

  dropzone.dataset.bound = 'true';
}

function handleExcelFile(file) {
  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const data = new Uint8Array(e.target.result);
      const workbook = XLSX.read(data, {type: 'array'});
      const firstSheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[firstSheetName];
      let jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
      
      if (jsonData.length < 2) {
        showToast('File appears to be empty or missing data rows.', 'error');
        return;
      }

      const headers = jsonData[0].map(h => (h||'').toString().toLowerCase().trim());
      const nameIdx = headers.findIndex(h => h.includes('name'));
      const phoneIdx = headers.findIndex(h => h.includes('phone') || h.includes('number') || h.includes('contact'));
      const emailIdx = headers.findIndex(h => h.includes('email'));
      const tagsIdx = headers.findIndex(h => h.includes('tag') || h.includes('tags'));

      if (nameIdx === -1 || phoneIdx === -1) {
        showToast('Could not find "Name" or "Phone" columns. Please check your file.', 'error');
        return;
      }

      parsedExcelData = [];
      for (let i = 1; i < jsonData.length; i++) {
        const row = jsonData[i];
        if (!row || !row[phoneIdx]) continue;
        
        let phone = String(row[phoneIdx]).replace(/[^0-9]/g, '');
        if (phone.length === 10) phone = '+91 ' + phone;
        else if (phone.length === 12 && phone.startsWith('91')) phone = '+91 ' + phone.substring(2);
        else if (!phone.startsWith('+')) phone = '+' + phone;

        let clientTags = [];
        if (tagsIdx !== -1 && row[tagsIdx]) {
          clientTags = String(row[tagsIdx]).split(',').map(t => t.trim()).filter(t => t);
        }

        parsedExcelData.push({
          name: row[nameIdx] || 'Unknown',
          phone: phone,
          email: emailIdx !== -1 ? (row[emailIdx] || '') : '',
          tags: clientTags
        });
      }

      if (parsedExcelData.length > 0) {
        document.getElementById('excel-dropzone').style.display = 'none';
        document.getElementById('excel-preview-container').style.display = 'block';
        document.getElementById('excel-preview-count').textContent = `(${parsedExcelData.length} rows)`;
        document.getElementById('import-confirm-btn').disabled = false;
        
        const theadRow = document.querySelector('#excel-preview-container thead tr');
        if (theadRow) {
          theadRow.innerHTML = `<th>Name</th><th>Phone</th><th>Email</th>${tagsIdx !== -1 ? '<th>Tags</th>' : ''}`;
        }
        
        const tbody = document.getElementById('excel-preview-tbody');
        tbody.innerHTML = parsedExcelData.slice(0, 50).map(r => `
          <tr>
            <td>${r.name}</td>
            <td>${r.phone}</td>
            <td>${r.email}</td>
            ${tagsIdx !== -1 ? `<td>${r.tags.join(', ')}</td>` : ''}
          </tr>
        `).join('');
        if (parsedExcelData.length > 50) {
           tbody.innerHTML += `<tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding: 10px;">+ ${parsedExcelData.length - 50} more rows...</td></tr>`;
        }
      } else {
        showToast('No valid data found in the file.', 'error');
      }
    } catch(err) {
      console.error(err);
      showToast('Failed to parse Excel file.', 'error');
    }
  };
  reader.readAsArrayBuffer(file);
}

function resetExcelUpload() {
  document.getElementById('excel-file-input').value = '';
  parsedExcelData = [];
  document.getElementById('excel-dropzone').style.display = 'block';
  document.getElementById('excel-preview-container').style.display = 'none';
  document.getElementById('import-confirm-btn').disabled = true;
  document.getElementById('excel-preview-tbody').innerHTML = '';
}

async function executeExcelImport() {
  if (parsedExcelData.length === 0) return;
  
  const tags = [...document.querySelectorAll('#import-tag-selector .tag-option.active')].map(t => t.dataset.tag);
  
  const btn = document.getElementById('import-confirm-btn');
  btn.disabled = true;
  btn.textContent = 'Importing...';

  try {
    const response = await fetch('api/import_clients_bulk.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ clients: parsedExcelData, tags: tags })
    });
    
    const result = await response.json();
    
    if (result.success) {
      let msg = `Successfully imported ${result.imported_count} clients!`;
      if (result.skipped_count > 0) {
        msg += ` (${result.skipped_count} skipped as duplicates)`;
      }
      showToast(msg, 'success');
      if (typeof logActivity === 'function') {
        logActivity({ type: 'Bulk Import', page: 'clients', data: { count: result.imported_count } });
      }
      closeImportExcelModal();
      if (typeof fetchInitialData === 'function') await fetchInitialData();
      if (typeof renderClientsTable === 'function') renderClientsTable();
      
      if (result.skipped_count > 0 && result.skipped_details && result.skipped_details.length > 0) {
        showDuplicatesModal(result.skipped_details);
      }
    } else {
      showToast('Error: ' + result.message, 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Failed to import data.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import Data';
  }
}

window.initClients    = initClients;
window.openAddClientModal  = openAddClientModal;
window.closeAddClientModal = closeAddClientModal;
window.toggleTag      = toggleTag;
window.saveNewClient  = saveNewClient;
window.removeClientRow = removeClientRow;
window.closeConfirmDeleteModal = closeConfirmDeleteModal;
window.executeClientDeletion = executeClientDeletion;
window.openImportExcelModal = openImportExcelModal;
window.closeImportExcelModal = closeImportExcelModal;
window.toggleImportTag = toggleImportTag;
window.resetExcelUpload = resetExcelUpload;
window.executeExcelImport = executeExcelImport;
window.addCustomTag = addCustomTag;

window.closeDuplicatesModal = function() {
  const modal = document.getElementById('import-duplicates-modal');
  if (modal) modal.classList.remove('open');
};

function showDuplicatesModal(skippedDetails) {
  const modal = document.getElementById('import-duplicates-modal');
  if (!modal) return;
  
  const tbody = document.getElementById('duplicates-tbody');
  const subtitle = document.getElementById('duplicates-subtitle');
  if (subtitle) {
    subtitle.textContent = `${skippedDetails.length} contacts were already in your database and skipped.`;
  }
  
  if (tbody) {
    tbody.innerHTML = skippedDetails.map(d => `
      <tr>
        <td style="font-weight: 600;">${d.name || 'Unknown'}</td>
        <td style="color: var(--text-muted);">${d.phone}</td>
      </tr>
    `).join('');
  }
  
  modal.classList.add('open');
}
window.showDuplicatesModal = showDuplicatesModal;
