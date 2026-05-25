/* ═══════════════════════════════════════════
   WhatsApp Broadcast Campaign – app.js (Core)
═══════════════════════════════════════════ */

let CLIENTS = [];
let TEMPLATES = {};

// Global State
let currentPage = '';
let selectedClients = new Set();
let campaignRunning = false;

// API Base URL for Live Server compatibility
const API_BASE = window.location.port === '5500' 
  ? 'http://localhost/whatsapp_messages_page/referral_video/api/' 
  : 'api/';

// ── INIT ──────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await fetchInitialData();
  fetchNotifications(); // Load real notifications on startup
  
  // Check URL hash or default
  const page = window.location.hash.replace('#', '') || 'campaigns';
  navigateTo(page);

  // Global Click Tracker
  document.addEventListener('click', (e) => {
    const target = e.target.closest('button, a, .toggle-label, .radio-option, .checkbox-option, .tag-option, .nav-item, .action-btn');
    if (target) {
      const actionName = target.textContent.trim() || target.id || target.className || 'Unknown Element';
      const cleanName = actionName.length > 50 ? actionName.substring(0, 50) + '...' : actionName;
      
      logActivity({
        type: 'User Click',
        page: currentPage || 'app_load',
        field: target.id || 'unnamed-element',
        data: { text: cleanName, element: target.tagName.toLowerCase() }
      });
    }

    // Handle Notification Dropdown Toggle
    const notifBtn = e.target.closest('#notif-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const avatarBtn = e.target.closest('#nav-avatar');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (notifBtn) {
      fetchNotifications();
      notifDropdown?.classList.toggle('show');
      profileDropdown?.classList.remove('show'); // close other dropdown
    } else if (avatarBtn) {
      profileDropdown?.classList.toggle('show');
      notifDropdown?.classList.remove('show'); // close other dropdown
    } else {
      // Clicked outside — close both
      if (!e.target.closest('#notif-dropdown')) notifDropdown?.classList.remove('show');
      if (!e.target.closest('#profile-dropdown')) profileDropdown?.classList.remove('show');
    }
  });
});

async function fetchInitialData() {
  try {
    const [clientsRes, templatesRes] = await Promise.all([
      fetch(API_BASE + 'get_clients.php'),
      fetch(API_BASE + 'get_templates.php')
    ]);
    
    CLIENTS = await clientsRes.json();
    
    const templatesRes2 = await templatesRes.json();
    // Handle both plain array (legacy) and {success, data} wrapper
    const templatesList = Array.isArray(templatesRes2) ? templatesRes2 : (templatesRes2.data || []);
    TEMPLATES = {};
    templatesList.forEach(t => {
      TEMPLATES[t.key] = t.body;
    });
    
    console.log('Data fetched:', { clients: CLIENTS.length, templates: Object.keys(TEMPLATES).length });
  } catch (err) {
    console.error('Error fetching initial data:', err);
    showToast('Failed to connect to database', 'error');
  }
}

// ── NAVIGATION (SPA ROUTER) ───────────────
async function navigateTo(page, event) {
  if (event) event.preventDefault();
  if (currentPage === page) return;

  const container = document.getElementById('section-container');
  
  try {
    // 1. Fetch the HTML
    const response = await fetch(`sections/${page}.html`);
    if (!response.ok) throw new Error('Page not found');
    const content = await response.text();
    
    // 2. Inject
    container.innerHTML = content;
    currentPage = page;
    window.location.hash = page;

    // 3. Update UI
    updateSidebarUI(page);
    updateBreadcrumb(page);

    // 4. Initialize section-specific JS
    initSection(page);

    // 5. Log Activity
    logActivity({ type: 'Page View', page: page });

  } catch (err) {
    console.error('Routing error:', err);
    showToast('Failed to load page', 'error');
  }
}

function updateSidebarUI(page) {
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const activeNav = document.getElementById(`nav-${page}`);
  if (activeNav) activeNav.classList.add('active');
}

function updateBreadcrumb(page) {
  const nameEl = document.getElementById('current-page-name');
  const names = {
    dashboard: 'Dashboard', clients: 'Clients',
    templates: 'Templates', campaigns: 'Broadcast Campaign',
    analytics: 'Analytics', settings: 'Settings',
    sequences: 'Automated Sequences', assign: 'Assign Loop',
    'create-master-loop': 'Create Master Loop',
    'campaign-reports': 'Campaign Reports',
    'whatsapp-web': 'WhatsApp Web'
  };
  if (nameEl) nameEl.textContent = names[page] || page;
}

function initSection(page) {
  // Call the init function from the modular files
  // These will be defined in dashboard.js, clients.js, etc.
  const fnName = `init${page
    .split('-')
    .map(p => p.charAt(0).toUpperCase() + p.slice(1))
    .join('')}`;
  if (typeof window[fnName] === 'function') {
    window[fnName]();
  }
}

// ── HELPERS ──────────────────────────────
function showToast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span>${msg}</span>`;
  c.appendChild(t);
  setTimeout(() => { 
    t.style.opacity = '0'; 
    t.style.transform = 'translateX(20px)'; 
    t.style.transition = '.3s'; 
    setTimeout(() => t.remove(), 300); 
  }, 3000);
}

async function logActivity(details) {
  try {
    await fetch(API_BASE + 'log_activity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(details)
    });
    // Refresh notifications in the background after every action
    fetchNotifications();
  } catch (err) { /* silent fail for logging */ }
}

// ── NOTIFICATIONS ─────────────────────────
async function fetchNotifications() {
  try {
    const res = await fetch(API_BASE + 'get_notifications.php');
    const notifications = await res.json();
    renderNotifications(notifications);
  } catch (err) { /* silent fail */ }
}

function renderNotifications(notifications) {
  const body = document.getElementById('notif-body');
  const badge = document.querySelector('.notif-badge');
  if (!body) return;

  const icons = {
    green:  `<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    blue:   `<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/></svg>`,
    red:    `<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`,
    purple: `<svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`
  };

  if (!notifications || notifications.length === 0) {
    body.innerHTML = `<div style="padding:30px;text-align:center;color:var(--text-muted);font-size:.875rem;">No recent activity</div>`;
    if (badge) badge.style.display = 'none';
    return;
  }

  // Only show unread badge for important events (not page views or clicks)
  const important = notifications.filter(n => n.type !== 'Page View' && n.type !== 'User Click');
  if (badge) {
    if (important.length > 0) {
      badge.style.display = 'flex';
      badge.textContent = important.length > 9 ? '9+' : important.length;
    } else {
      badge.style.display = 'none';
    }
  }

  body.innerHTML = notifications.map(n => {
    const iconSvg = icons[n.color] || icons.blue;
    const isImportant = n.type !== 'Page View' && n.type !== 'User Click';
    return `
      <div class="notif-item ${isImportant ? 'unread' : ''}">
        <div class="notif-icon ${n.color}">${iconSvg}</div>
        <div class="notif-content">
          <h4>${n.title}</h4>
          <p>${n.message}</p>
          <span class="notif-time">${n.time_label}</span>
        </div>
      </div>`;
  }).join('');
}
