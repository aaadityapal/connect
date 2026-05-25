async function initDashboard() {
  console.log('Dashboard initialized, fetching stats...');
  try {
    const response = await fetch('api/get_dashboard_stats.php');
    if (!response.ok) throw new Error('Network response was not ok');
    const data = await response.json();
    
    if (data.error) {
      console.error('DB Error:', data.error);
      return;
    }

    // Update Top Stats
    document.getElementById('dash-total-clients').textContent = data.total_clients || 0;
    document.getElementById('dash-active-campaigns').textContent = data.total_campaigns || 0;
    document.getElementById('dash-messages-sent').textContent = data.messages_sent || 0;
    document.getElementById('dash-delivery-rate').textContent = (data.delivery_rate || 0) + '%';

    // Update Recent Activity List
    const activityContainer = document.getElementById('dash-recent-activity');
    if (activityContainer && data.recent_activity) {
      if (data.recent_activity.length === 0) {
        activityContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No recent campaigns found.</div>';
      } else {
        activityContainer.innerHTML = data.recent_activity.map(act => `
          <div class="dash-camp-item">
            <div class="dash-camp-info" style="display:flex; align-items:center; gap: 10px;">
              <span style="font-size: 1.2rem;">${act.icon}</span>
              <div>
                <div class="dash-camp-name" style="font-size: .875rem; color: var(--text);">${act.text}</div>
                <div class="dash-camp-meta" style="font-size: .75rem; color: var(--text-muted);">${act.time}</div>
              </div>
            </div>
          </div>
        `).join('');
      }
    }
  } catch (error) {
    console.error('Failed to load dashboard stats:', error);
  }
}

window.initDashboard = initDashboard;

