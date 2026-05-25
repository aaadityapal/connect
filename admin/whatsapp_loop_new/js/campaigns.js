/* ═══════════════════════════════════════════
   WhatsApp Broadcast Campaign – campaigns.js
═══════════════════════════════════════════ */

const STATUS_LABELS = { sent:'Sent', delivered:'Delivered', read:'Read', replied:'Replied' };
const STATUS_TICK = {
  sent:      '<svg viewBox="0 0 16 11" fill="none" width="14" height="11"><path d="M1 5.5L5 9.5L11 1.5" stroke="#aaa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  delivered: '<svg viewBox="0 0 16 11" fill="none" width="14" height="11"><path d="M1 5.5L5 9.5L11 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 5.5L9 9.5L15 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  read:      '<svg viewBox="0 0 16 11" fill="none" width="14" height="11"><path d="M1 5.5L5 9.5L11 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 5.5L9 9.5L15 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  replied:   '💬',
};

let sentCount = 0, deliveredCount = 0, repliesCount = 0;
let simulationTimer = null;
let CAMPAIGN_TEMPLATES = [];
let CAMPAIGN_TEMPLATES_MAP = {};
let latestCampaignId = null;

async function initCampaigns() {
  await loadCampaignTemplates();
  renderClientList();
  renderTableSkeleton(); // Initial skeleton
  bindCampaignEvents();
  initTableFilters();
  prefillCampaignDateTime();
  await fetchLatestCampaignStats(); // Load real DB data
}

async function loadCampaignTemplates() {
  const select = document.getElementById('template-select');
  if (!select) return;

  select.innerHTML = '<option value="">Loading templates...</option>';

  try {
    const response = await fetch(API_BASE + 'get_templates.php');
    const result = await response.json();
    const list = Array.isArray(result?.data) ? result.data : [];

    CAMPAIGN_TEMPLATES = list;
    CAMPAIGN_TEMPLATES_MAP = {};
    list.forEach(t => {
      CAMPAIGN_TEMPLATES_MAP[t.key] = t;
    });

    if (list.length === 0) {
      select.innerHTML = '<option value="">No templates found</option>';
      return;
    }

    select.innerHTML = '<option value="">-- Choose a template --</option>' + list.map(t => {
      const status = (t.status || '').toUpperCase();
      return `<option value="${t.key}" data-header-type="${t.header_type || 'NONE'}">${t.label || t.key} (${status || 'UNKNOWN'})</option>`;
    }).join('');
  } catch (err) {
    console.error('Failed to load templates:', err);
    select.innerHTML = '<option value="">Failed to load templates</option>';
  }
}

function templateRequiresMedia(headerType) {
  return ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(String(headerType || '').toUpperCase());
}

function applyTemplateMediaRequirement(headerType) {
  const group = document.getElementById('template-media-group');
  const input = document.getElementById('template-media');
  const help = document.getElementById('template-media-help');
  if (!group || !input || !help) return;

  const type = String(headerType || 'NONE').toUpperCase();
  const needsMedia = templateRequiresMedia(type);

  group.classList.toggle('hidden', !needsMedia);
  input.required = needsMedia;

  if (!needsMedia) {
    input.value = '';
    input.removeAttribute('accept');
    help.textContent = '';
    renderSelectedMediaPreview(null, type);
    return;
  }

  if (type === 'IMAGE') {
    input.setAttribute('accept', 'image/*');
    help.textContent = 'This template needs an image header.';
  } else if (type === 'VIDEO') {
    input.setAttribute('accept', 'video/*');
    help.textContent = 'This template needs a video header.';
  } else {
    input.setAttribute('accept', '.pdf,application/pdf');
    help.textContent = 'This template needs a PDF document header.';
  }
}

function renderSelectedMediaPreview(file, headerType) {
  const wrap = document.getElementById('preview-media-wrap');
  if (!wrap) return;

  wrap.innerHTML = '';
  if (!file) {
    wrap.classList.add('hidden');
    return;
  }

  if (wrap._objectUrl) {
    URL.revokeObjectURL(wrap._objectUrl);
    wrap._objectUrl = null;
  }

  const type = String(headerType || '').toUpperCase();
  wrap.classList.remove('hidden');
  const objectUrl = URL.createObjectURL(file);
  wrap._objectUrl = objectUrl;

  if (type === 'IMAGE') {
    const img = document.createElement('img');
    img.className = 'preview-media-image';
    img.alt = 'Template media preview';
    img.src = objectUrl;
    wrap.appendChild(img);
    return;
  }

  if (type === 'VIDEO') {
    const video = document.createElement('video');
    video.className = 'preview-media-video';
    video.controls = true;
    video.src = objectUrl;
    wrap.appendChild(video);
    return;
  }

  const doc = document.createElement('div');
  doc.className = 'preview-media-doc';
  doc.textContent = `PDF: ${file.name}`;
  wrap.appendChild(doc);
}

async function fetchLatestCampaignStats() {
  try {
    const response = await fetch('api/get_latest_campaign.php');
    const data = await response.json();

    // 1. Update Global Stats (Always available)
    const svTotal = document.getElementById('sv-total');
    if (svTotal) svTotal.textContent = data.global_total_clients || 0;

    // 2. Update Activity Feed (Always available)
    const activityList = document.getElementById('activity-list');
    if (activityList && data.activities) {
      if (data.activities.length === 0) {
        activityList.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">No activity yet.</div>';
      } else {
        activityList.innerHTML = data.activities.map(act => {
          const icon = act.type === 'Campaign Started' ? '🚀' : (act.type === 'Form Input' ? '✍' : '👁');
          const parsedData = JSON.parse(act.data || '{}');
          const text = act.type === 'Form Input' 
            ? `Editing field <strong>${act.field || 'unknown'}</strong> for ${parsedData.name || 'Untitled'}`
            : `${act.type}: <strong>${parsedData.name || ''}</strong>`;
            
          return `
            <div class="activity-item">
              <span class="activity-icon">${icon}</span>
              <span class="activity-text">${text}</span>
              <span class="activity-time">${act.time}</span>
            </div>
          `;
        }).join('');
      }
    }

    // 3. Stop here if no campaign data
    if (data.no_campaign) return;

    latestCampaignId = data.id || null;

    // 4. Update Campaign Stats
    const svSent = document.getElementById('sv-sent');
    const svDelivered = document.getElementById('sv-delivered');
    const svReplies = document.getElementById('sv-replies');
    const drate = document.getElementById('delivery-rate');
    const rrate = document.getElementById('reply-rate');

    if (svSent) svSent.textContent = data.total_sent || 0;
    if (svDelivered) svDelivered.textContent = data.delivered || 0;
    if (svReplies) svReplies.textContent = data.replies || 0;
    
    if (data.total_sent > 0) {
      if (drate) drate.textContent = Math.round((data.delivered / data.total_sent) * 100) + '% rate';
      if (rrate) rrate.textContent = Math.round((data.replies / data.total_sent) * 100) + '% response';
    }

    // 5. Update Table
    const tbody = document.getElementById('delivery-tbody');
    if (tbody && data.deliveries) {
      tbody.innerHTML = data.deliveries.map(d => `
        <tr data-id="${d.client_id}" data-status="${d.status.toLowerCase()}">
          <td><div class="client-cell">
            <div class="client-initials">${d.initials}</div>
            <div><div class="client-name">${d.name}</div></div>
          </div></td>
          <td>${d.phone}</td>
          <td><span class="status-badge badge-${d.status.toLowerCase()}">
            ${STATUS_TICK[d.status.toLowerCase()] || ''} ${d.status}
          </span></td>
          <td class="time-cell">${d.sent_time || '—'}</td>
          <td class="time-cell">—</td>
        </tr>
      `).join('');
    }

    await fetchCampaignStatusLogs(latestCampaignId);

  } catch (err) {
    console.error('Error fetching latest campaign:', err);
  }
}

async function fetchCampaignStatusLogs(campaignId) {
  const container = document.getElementById('campaign-log-list');
  if (!container) return;
  if (!campaignId) {
    container.innerHTML = '<div class="campaign-log-empty">No campaign has run yet.</div>';
    return;
  }

  try {
    const res = await fetch(API_BASE + `get_campaign_status_logs.php?campaign_id=${campaignId}`);
    const data = await res.json();

    if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
      container.innerHTML = '<div class="campaign-log-empty">No status logs found for this campaign.</div>';
      return;
    }

    container.innerHTML = data.data.map(log => {
      const status = (log.status || 'Unknown').toLowerCase();
      const details = log.details ? JSON.stringify(log.details) : '';
      return `
        <div class="campaign-log-item campaign-log-${status}">
          <div class="campaign-log-left">
            <div class="campaign-log-status">${log.status}</div>
            <div class="campaign-log-meta">${log.client_name || 'Unknown client'} • ${log.client_phone || ''} • ${log.template_name || ''}</div>
            <div class="campaign-log-details">${details}</div>
          </div>
          <div class="campaign-log-time">${log.created_label || ''}</div>
        </div>
      `;
    }).join('');
  } catch (err) {
    container.innerHTML = '<div class="campaign-log-empty">Failed to load campaign logs.</div>';
  }
}

function bindCampaignEvents() {
  // --- Activity Tracking for Abandoned Forms ---
  const logInput = (e) => {
    logActivity({
      type: 'Form Input',
      page: 'campaigns',
      field: e.target.id,
      data: { value: e.target.value, name: document.getElementById('campaign-name').value }
    });
  };

  const cn = document.getElementById('campaign-name');
  if (cn) cn.addEventListener('blur', logInput);
  
  // Template select
  const ts = document.getElementById('template-select');
  if (ts) {
    ts.addEventListener('change', function(e) {
      const tpl = CAMPAIGN_TEMPLATES_MAP[this.value] || null;
      const txt = (tpl && tpl.body) || TEMPLATES[this.value] || 'Select a template above to preview the message here.';
      document.getElementById('preview-text').textContent = txt;
      applyTemplateMediaRequirement(tpl ? tpl.header_type : 'NONE');
      logInput(e);
    });
  }

  const mediaInput = document.getElementById('template-media');
  if (mediaInput) {
    mediaInput.addEventListener('change', function() {
      const key = document.getElementById('template-select')?.value || '';
      const tpl = CAMPAIGN_TEMPLATES_MAP[key] || null;
      const file = this.files && this.files[0] ? this.files[0] : null;
      renderSelectedMediaPreview(file, tpl ? tpl.header_type : 'NONE');
    });
  }

  // All-clients toggle
  const act = document.getElementById('all-clients-toggle');
  if (act) {
    act.addEventListener('change', function(e) {
      const ms = document.getElementById('client-multiselect');
      if (this.checked) {
        ms.style.opacity = '.4';
        ms.style.pointerEvents = 'none';
        CLIENTS.forEach(c => selectedClients.add(c.id));
      } else {
        ms.style.opacity = '1';
        ms.style.pointerEvents = 'auto';
        selectedClients.clear();
      }
      renderCampaignTags();
      logInput(e);
    });
  }

  // Schedule radio
  document.querySelectorAll('input[name="schedule"]').forEach(r => {
    r.addEventListener('change', function(e) {
      const row = document.getElementById('datetime-row');
      this.value === 'later' ? row.classList.remove('hidden') : row.classList.add('hidden');
      logInput(e);
    });
  });

  // Repeat toggle
  const rt = document.getElementById('repeat-toggle');
  if (rt) {
    rt.addEventListener('change', function(e) {
      const opts = document.getElementById('repeat-options');
      this.checked ? opts.classList.remove('hidden') : opts.classList.add('hidden');
      logInput(e);
    });
  }

  // Client search
  const cs = document.getElementById('client-search');
  if (cs) {
    cs.addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.client-item').forEach(item => {
        const name = item.dataset.name.toLowerCase();
        item.style.display = name.includes(q) ? '' : 'none';
      });
    });
  }

  // CTA
  const scb = document.getElementById('start-campaign-btn');
  if (scb) scb.addEventListener('click', startCampaign);
  
  const sdb = document.getElementById('save-draft-btn');
  if (sdb) sdb.addEventListener('click', saveCampaignDraft);

  const rlb = document.getElementById('refresh-campaign-log-btn');
  if (rlb) {
    rlb.addEventListener('click', () => fetchCampaignStatusLogs(latestCampaignId));
  }
}

function renderClientList() {
  const list = document.getElementById('client-list');
  if (!list) return;
  list.innerHTML = CLIENTS.map(c => `
    <div class="client-item" data-id="${c.id}" data-name="${c.name}">
      <input type="checkbox" id="client-${c.id}" value="${c.id}" ${selectedClients.has(c.id) ? 'checked' : ''}/>
      <div class="client-avatar">${c.initials}</div>
      <div>
        <div style="font-weight:600;font-size:.875rem">${c.name}</div>
        <div class="client-phone">${c.phone}</div>
      </div>
    </div>
  `).join('');

  document.querySelectorAll('.client-item').forEach(item => {
    item.addEventListener('click', function(e) {
      if (e.target.tagName === 'INPUT') return;
      const cb = this.querySelector('input[type=checkbox]');
      cb.checked = !cb.checked;
      toggleCampaignClient(parseInt(this.dataset.id), cb.checked);
    });
    item.querySelector('input').addEventListener('change', function() {
      toggleCampaignClient(parseInt(item.dataset.id), this.checked);
    });
  });
  renderCampaignTags();
}

function toggleCampaignClient(id, checked) {
  checked ? selectedClients.add(id) : selectedClients.delete(id);
  renderCampaignTags();
}

function renderCampaignTags() {
  const container = document.getElementById('client-tags');
  if (!container) return;
  container.innerHTML = [...selectedClients].map(id => {
    const c = CLIENTS.find(x => x.id === id);
    if (!c) return '';
    return `<div class="tag">${c.initials} ${c.name.split(' ')[0]} <button onclick="removeCampaignTag(${id})" title="Remove">×</button></div>`;
  }).join('');
}

function removeCampaignTag(id) {
  selectedClients.delete(id);
  const cb = document.querySelector(`#client-${id}`);
  if (cb) cb.checked = false;
  renderCampaignTags();
}

function renderTableSkeleton() {
  const tbody = document.getElementById('delivery-tbody');
  if (!tbody) return;
  tbody.innerHTML = CLIENTS.map(c => `
    <tr data-id="${c.id}" data-status="">
      <td><div class="client-cell">
        <div class="client-initials">${c.initials}</div>
        <div><div class="client-name">${c.name}</div></div>
      </div></td>
      <td>${c.phone}</td>
      <td><span class="status-badge" id="badge-${c.id}">—</span></td>
      <td class="time-cell" id="sent-time-${c.id}">—</td>
      <td class="time-cell" id="next-time-${c.id}">—</td>
    </tr>
  `).join('');
}

async function saveCampaignDraft() {
  const name = document.getElementById('campaign-name').value.trim() || 'Untitled Draft';
  await performSaveCampaign('Draft');
  showToast(`💾 Draft "${name}" saved to database!`, 'info');
}

async function startCampaign() {
  const name = document.getElementById('campaign-name').value.trim();
  const template = document.getElementById('template-select').value;
  const allToggle = document.getElementById('all-clients-toggle').checked;
  const audience = allToggle ? CLIENTS.map(c => c.id) : [...selectedClients];
  const scheduleType = document.querySelector('input[name="schedule"]:checked')?.value || 'now';

  if (!name) { showToast('Please enter a campaign name.', 'error'); return; }
  if (!template) { showToast('Please select a message template.', 'error'); return; }
  if (!audience.length) { showToast('Please select at least one client.', 'error'); return; }

  const selectedTpl = CAMPAIGN_TEMPLATES_MAP[template] || null;
  const mediaInput = document.getElementById('template-media');
  const mediaFile = mediaInput && mediaInput.files ? mediaInput.files[0] : null;
  if (selectedTpl && templateRequiresMedia(selectedTpl.header_type) && !mediaFile) {
    showToast('Selected template requires media upload.', 'error');
    return;
  }

  const startBtn = document.getElementById('start-campaign-btn');
  if (startBtn) {
    startBtn.disabled = true;
    startBtn.style.opacity = '.6';
  }

  const loaderOverlay = document.createElement('div');
  loaderOverlay.id = 'campaign-loader-overlay';
  loaderOverlay.innerHTML = `
    <div class="loader-content" style="text-align:center; background:#1e1e1e; padding:40px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5); max-width:400px; width:90%;">
      <div class="loader-spinner" style="border: 4px solid rgba(255,255,255,0.1); border-left-color: #53bdeb; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
      <h3 style="color:#fff; margin-bottom:15px; font-size:1.2rem; font-weight:600;">Campaign is starting...</h3>
      <p id="loader-text" style="color:#aaa; font-size:0.95rem; min-height:40px; transition: opacity 0.3s;">Media is uploading, setting up the environment...</p>
    </div>
    <style>
      #campaign-loader-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.8); z-index: 9999;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(4px);
      }
      @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
  `;
  document.body.appendChild(loaderOverlay);

  const loaderText = document.getElementById('loader-text');
  const updateText = (text) => {
    if(loaderText) {
      loaderText.style.opacity = 0;
      setTimeout(() => {
        loaderText.textContent = text;
        loaderText.style.opacity = 1;
      }, 300);
    }
  };

  let sequence = 0;
  const sequenceInterval = setInterval(() => {
    sequence++;
    if (sequence === 1) updateText("Starting in 3...");
    else if (sequence === 2) updateText("2...");
    else if (sequence === 3) updateText("1...");
    else if (sequence === 4) updateText("Now sit back and relax, let the master do the work 🚀");
  }, 1500);

  try {
    const dbResult = await performSaveCampaign('Running');
    if (!dbResult || !dbResult.id) return;

    const statusChip = document.querySelector('.status-chip');
    if (statusChip) {
      statusChip.className = 'status-chip running';
      statusChip.textContent = 'Running';
    }

    if (scheduleType === 'now') {
      showToast(`🚀 Campaign "${name}" launched and sent to the queue.`, 'success');
      addCampaignActivity('🚀', `Campaign <strong>${name}</strong> queued for ${audience.length} clients`);
    } else {
      const scheduleDate = document.getElementById('schedule-date')?.value || '';
      const scheduleTime = document.getElementById('schedule-time')?.value || '';
      showToast(`🕒 Campaign "${name}" scheduled for ${scheduleDate} ${scheduleTime} IST.`, 'success');
      addCampaignActivity('🕒', `Campaign <strong>${name}</strong> scheduled for ${audience.length} clients`);
    }

    logActivity({ type: 'Campaign Started', page: 'campaigns', data: { name, audience: audience.length, template: selectedTpl?.key || template, schedule_type: scheduleType } });
    await fetchLatestCampaignStats();
  } finally {
    clearInterval(sequenceInterval);
    const overlay = document.getElementById('campaign-loader-overlay');
    if (overlay) overlay.remove();

    if (startBtn) {
      startBtn.disabled = false;
      startBtn.style.opacity = '1';
    }
  }
}

async function performSaveCampaign(status) {
  const name = document.getElementById('campaign-name').value.trim();
  const template_key = document.getElementById('template-select').value;
  const allToggle = document.getElementById('all-clients-toggle').checked;
  const audience = allToggle ? CLIENTS.map(c => parseInt(c.id)) : [...selectedClients];
  const schedule_type = document.querySelector('input[name="schedule"]:checked')?.value || 'now';
  const scheduleDate = document.getElementById('schedule-date')?.value || '';
  const scheduleTime = document.getElementById('schedule-time')?.value || '';
  
  // New fields to match the requested format
  const is_repeat = document.getElementById('repeat-toggle').checked;
  const repeat_interval = document.querySelector('input[name="repeat"]:checked')?.value || '24h';
  const stop_on_reply = true; // Hardcoded true for now based on your image request
  const selectedTpl = CAMPAIGN_TEMPLATES_MAP[template_key] || null;
  const mediaInput = document.getElementById('template-media');
  const mediaFile = mediaInput && mediaInput.files ? mediaInput.files[0] : null;

  let mediaData = null;
  if (mediaFile) {
    mediaData = await readCampaignMediaFile(mediaFile);
  }

  try {
    const response = await fetch('api/save_campaign.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        template_key,
        template_name: selectedTpl?.label || selectedTpl?.key || template_key,
        template_language: selectedTpl?.language || 'en_US',
        template_body: selectedTpl?.body || '',
        template_category: selectedTpl?.category || 'General',
        template_status: selectedTpl?.status || 'APPROVED',
        target_audience: allToggle ? 'All' : 'Selected',
        schedule_type,
        scheduled_at: schedule_type === 'later' && scheduleDate && scheduleTime ? `${scheduleDate} ${scheduleTime}:00` : null,
        schedule_date: scheduleDate,
        schedule_time: scheduleTime,
        status,
        client_ids: audience,
        is_repeat,
        repeat_interval,
        stop_on_reply,
        media_header_type: selectedTpl?.header_type || 'NONE',
        media_path: mediaData?.path || '',
        media_filename: mediaData?.name || '',
        media_wa_id: mediaData?.wa_id || ''
      })
    });
    const result = await response.json();
    if (!response.ok || result.success === false) {
      throw new Error(result.message || result.error || `Save failed (${response.status})`);
    }
    return result;
  } catch (err) {
    console.error('Error saving campaign:', err);
    showToast(err.message || 'Failed to save campaign to database', 'error');
    return { error: true };
  }
}

function readCampaignMediaFile(file) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve(null);
      return;
    }

    const reader = new FileReader();
    reader.onload = async () => {
      try {
        const base64 = String(reader.result || '');
        const fileName = file.name;
        const mimeType = file.type || 'application/octet-stream';

        const uploadRes = await fetch('api/upload_campaign_media.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ file_name: fileName, mime_type: mimeType, data_url: base64 })
        });
        const uploadJson = await uploadRes.json();
        if (!uploadJson.success) {
          reject(new Error(uploadJson.message || 'Media upload failed'));
          return;
        }

        resolve({ path: uploadJson.path, name: uploadJson.name, wa_id: uploadJson.wa_id || '' });
      } catch (error) {
        reject(error);
      }
    };
    reader.onerror = () => reject(new Error('Unable to read media file'));
    reader.readAsDataURL(file);
  });
}

function simulateCampaignSending(audience) {
  let idx = 0;
  function sendNext() {
    if (idx >= audience.length) {
      campaignRunning = false;
      const startBtn = document.getElementById('start-campaign-btn');
      if (startBtn) {
        startBtn.disabled = false;
        startBtn.style.opacity = '1';
      }
      showToast('✅ All messages sent!', 'success');
      addCampaignActivity('✅', `Campaign completed. ${sentCount} messages sent.`);
      return;
    }
    const cid = audience[idx++];
    const client = CLIENTS.find(c => c.id === cid);
    if (!client) { sendNext(); return; }

    const now = new Date();
    const sentTime = now.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    updateCampaignClientStatus(client, 'sent', sentTime);
    sentCount++;
    updateCampaignStats();
    addCampaignActivity('✔', `Message sent to <strong>${client.name}</strong>`);

    setTimeout(() => {
      updateCampaignClientStatus(client, 'delivered', sentTime);
      deliveredCount++;
      updateCampaignStats();
      addCampaignActivity('✔✔', `Delivered to <strong>${client.name}</strong>`);

      if (Math.random() > 0.4) {
        setTimeout(() => {
          const willReply = Math.random() > 0.55;
          const finalStatus = willReply ? 'replied' : 'read';
          updateCampaignClientStatus(client, finalStatus, sentTime);
          if (willReply) {
            repliesCount++;
            updateCampaignStats();
            addCampaignActivity('💬', `Reply received from <strong>${client.name}</strong>`);
          } else {
            addCampaignActivity('👁️', `Message read by <strong>${client.name}</strong>`);
          }
        }, 800 + Math.random() * 1200);
      }
    }, 600 + Math.random() * 800);

    simulationTimer = setTimeout(sendNext, 700 + Math.random() * 600);
  }
  sendNext();
}

function updateCampaignClientStatus(client, status, sentTime) {
  const badge = document.getElementById(`badge-${client.id}`);
  const sentCell = document.getElementById(`sent-time-${client.id}`);
  const nextCell = document.getElementById(`next-time-${client.id}`);
  const row = document.querySelector(`tr[data-id="${client.id}"]`);

  if (badge) {
    badge.className = `status-badge badge-${status}`;
    badge.innerHTML = `${STATUS_TICK[status] || ''} ${STATUS_LABELS[status]}`;
  }
  if (sentCell) sentCell.textContent = sentTime;
  if (nextCell) {
    const repeat = document.getElementById('repeat-toggle').checked;
    nextCell.textContent = repeat ? getNextCampaignSendTime() : '—';
  }
  if (row) row.dataset.status = status;
}

function getNextCampaignSendTime() {
  const d = new Date();
  const repeatVal = document.querySelector('input[name="repeat"]:checked')?.value || '24h';
  if (repeatVal === '24h') d.setDate(d.getDate() + 1);
  else if (repeatVal === '48h') d.setDate(d.getDate() + 2);
  else {
    const val = parseInt(document.getElementById('interval-val')?.value) || 3;
    const unit = document.getElementById('interval-unit')?.value || 'days';
    if (unit === 'hours') d.setHours(d.getHours() + val);
    else if (unit === 'days') d.setDate(d.getDate() + val);
    else d.setDate(d.getDate() + val * 7);
  }
  return d.toLocaleDateString([], { month:'short', day:'numeric' }) + ' ' + d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
}

function updateCampaignStats() {
  const svSent = document.getElementById('sv-sent');
  const svDelivered = document.getElementById('sv-delivered');
  const svReplies = document.getElementById('sv-replies');
  
  if (svSent) svSent.textContent = sentCount;
  if (svDelivered) svDelivered.textContent = deliveredCount;
  if (svReplies) svReplies.textContent = repliesCount;
  
  if (sentCount > 0) {
    const dr = document.getElementById('delivery-rate');
    const rr = document.getElementById('reply-rate');
    if (dr) dr.textContent = Math.round((deliveredCount / sentCount) * 100) + '% rate';
    if (rr) rr.textContent = Math.round((repliesCount / sentCount) * 100) + '% response';
  }
}

function addCampaignActivity(icon, text) {
  const list = document.getElementById('activity-list');
  if (!list) return;
  const now = new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  const item = document.createElement('div');
  item.className = 'activity-item';
  item.innerHTML = `
    <span class="activity-icon">${icon}</span>
    <span class="activity-text">${text}</span>
    <span class="activity-time">${now}</span>
  `;
  list.insertBefore(item, list.firstChild);
  while (list.children.length > 20) list.removeChild(list.lastChild);
}

function initTableFilters() {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const f = this.dataset.filter;
      document.querySelectorAll('#delivery-tbody tr').forEach(row => {
        row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
      });
    });
  });
}

function prefillCampaignDateTime() {
  const now = new Date();
  const dateStr = now.toISOString().split('T')[0];
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const dateEl = document.getElementById('schedule-date');
  const timeEl = document.getElementById('schedule-time');
  if (dateEl) dateEl.value = dateStr;
  if (timeEl) timeEl.value = `${hh}:${mm}`;
}

// Global hook for the router
window.initCampaigns = initCampaigns;
