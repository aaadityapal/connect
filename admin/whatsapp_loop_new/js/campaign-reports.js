/* ═══════════════════════════════════════════
   Campaign Reports – campaign-reports.js
═══════════════════════════════════════════ */

async function initCampaignReports() {
  await fetchCampaignReports();

  const refresh = document.getElementById('refresh-campaign-reports');
  if (refresh) {
    refresh.addEventListener('click', fetchCampaignReports);
  }
}

async function fetchCampaignReports() {
  const tbody = document.getElementById('campaign-report-tbody');
  if (tbody) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">Loading reports...</td></tr>';
  }

  try {
    const res = await fetch(API_BASE + 'get_campaign_reports.php');
    const data = await res.json();

    if (!data.success) {
      throw new Error(data.message || 'Failed to load reports');
    }

    const totals = data.totals || {};
    const rows = Array.isArray(data.rows) ? data.rows : [];

    const totalCampaigns = document.getElementById('cr-total-campaigns');
    const completedCampaigns = document.getElementById('cr-completed-campaigns');
    const queuedCampaigns = document.getElementById('cr-queued-campaigns');
    const totalClients = document.getElementById('cr-total-clients');

    if (totalCampaigns) totalCampaigns.textContent = totals.total_campaigns || 0;
    if (completedCampaigns) completedCampaigns.textContent = totals.completed_campaigns || 0;
    if (queuedCampaigns) queuedCampaigns.textContent = totals.queued_campaigns || 0;
    if (totalClients) totalClients.textContent = totals.total_clients || 0;

    if (!tbody) return;

    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">No campaigns found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(row => {
      return `
        <tr>
          <td>${row.name || 'Untitled'}</td>
          <td>${row.status || 'Unknown'}</td>
          <td>${row.total_clients || 0}</td>
          <td>${row.pending_count || 0}</td>
          <td>${row.sent_count || 0}</td>
          <td>${row.delivered_count || 0}</td>
          <td>${row.read_count || 0}</td>
          <td>${row.failed_count || 0}</td>
          <td>${row.created_label || ''}</td>
        </tr>
      `;
    }).join('');
  } catch (err) {
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">Failed to load reports.</td></tr>';
    }
  }
}

// Global hook for the router
window.initCampaignReports = initCampaignReports;
