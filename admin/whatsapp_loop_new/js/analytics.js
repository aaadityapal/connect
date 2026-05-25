async function initAnalytics() {
  console.log('Analytics initialized, fetching data...');
  try {
    const response = await fetch('api/get_analytics_data.php');
    if (!response.ok) throw new Error('Network response was not ok');
    const data = await response.json();

    if (data.error) {
      console.error('DB Error:', data.error);
      return;
    }

    // 1. Update Top Metrics
    if (data.metrics) {
      document.getElementById('an-total-sent').textContent = data.metrics.total_sent || 0;
      document.getElementById('an-delivery-rate').textContent = (data.metrics.delivery_rate || 0) + '%';
      document.getElementById('an-reply-rate').textContent = (data.metrics.reply_rate || 0) + '%';
      document.getElementById('an-open-rate').textContent = (data.metrics.open_rate || 0) + '%';
    }

    // 2. Render Campaign Performance Bars
    if (data.performance) {
      renderAnalyticsBars(data.performance);
    }

  } catch (error) {
    console.error('Failed to load analytics data:', error);
  }
}

function renderAnalyticsBars(performanceData) {
  const bars = document.getElementById('analytics-bars');
  if (!bars) return;
  
  if (performanceData.length === 0) {
    bars.innerHTML = '<div style="padding: 30px; text-align: center; color: var(--text-muted);">No campaign performance data available yet.</div>';
    return;
  }

  bars.innerHTML = performanceData.map(d => {
    // We get sent, delivered, read (we will map read to replied visually for the existing CSS class)
    const delivPct = d.del_rate || 0;
    const replyPct = d.read_rate || 0; 
    
    return `
      <div class="analytics-row">
        <div class="analytics-row-header">
          <span class="analytics-row-name">${d.name} <span style="font-size: .75rem; color: var(--text-muted); font-weight: normal; margin-left: 5px;">(${d.date})</span></span>
          <span class="analytics-row-val">${d.delivered}/${d.sent} delivered · ${d.read} opens/replies</span>
        </div>
        <div class="analytics-bar-track">
          <div class="analytics-bar-fill bar-delivered" style="width:0%" data-w="${delivPct}"></div>
        </div>
        <div class="analytics-bar-track" style="height:6px;margin-top:-4px">
          <div class="analytics-bar-fill bar-replied" style="width:0%;height:100%" data-w="${replyPct}"></div>
        </div>
      </div>`;
  }).join('');

  // Animate bars after paint
  requestAnimationFrame(() => {
    setTimeout(() => {
      bars.querySelectorAll('.analytics-bar-fill').forEach(el => {
        el.style.width = el.dataset.w + '%';
      });
    }, 50); // slight delay ensures smooth animation trigger
  });
}

window.initAnalytics = initAnalytics;
