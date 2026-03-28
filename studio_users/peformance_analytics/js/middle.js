// ─── Task Status Pie Chart ────────────────────────────
const ctxPie = document.getElementById('statusPieChart')?.getContext('2d');

if (ctxPie) {
    const pieData = window.apiData ? window.apiData.pie : [0,0,0,0];
    const labels = ['Completed', 'In Progress', 'Pending', 'Not Started'];
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#94a3b8'];
    const totalTasks = pieData.reduce((a, b) => a + b, 0);

    // Custom Plugin: Draw total tasks in center
    const centerTextPlugin = {
        id: 'centerText',
        beforeDraw: function(chart) {
            if (chart.config.type !== 'doughnut' || totalTasks === 0) return;
            var width = chart.width, height = chart.height, ctx = chart.ctx;
            ctx.restore();
            
            var fontSize = (height / 8).toFixed(2);
            ctx.font = "bold " + fontSize + "px Inter";
            ctx.textBaseline = "middle";
            ctx.fillStyle = "#1e293b";
            
            var text = totalTasks.toString(),
                textX = Math.round((width - ctx.measureText(text).width) / 2),
                textY = height / 2 - 8;
            ctx.fillText(text, textX, textY);
            
            ctx.font = "500 " + (fontSize * 0.4).toFixed(2) + "px Inter";
            ctx.fillStyle = "#64748b";
            var label = "Tasks",
                labelX = Math.round((width - ctx.measureText(label).width) / 2),
                labelY = height / 2 + 14;
            ctx.fillText(label, labelX, labelY);
            ctx.save();
        }
    };

    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: pieData,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false }, // we use custom legend
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#111827',
                    bodyColor: '#4b5563',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let l = context.label || '';
                            let v = context.raw || 0;
                            let p = totalTasks > 0 ? Math.round((v / totalTasks) * 100) + '%' : '0%';
                            return ` ${l}: ${v} Tasks (${p})`;
                        }
                    }
                }
            }
        },
        plugins: [centerTextPlugin]
    });

    // Render detailed premium custom legend
    const detailsContainer = document.getElementById('pie-details');
    if (detailsContainer) {
        let html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; width: 100%;">';
        labels.forEach((label, i) => {
            let val = pieData[i];
            let pct = totalTasks > 0 ? Math.round((val / totalTasks) * 100) : 0;
            
            // Generate a subtle tint for the badge background (e.g., #10b98115)
            // Adding '15' for 15% opacity hex transparency
            let bgTint = colors[i] + '1A';

            html += `
            <div style="
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
                display: flex;
                flex-direction: column;
                gap: 10px;
                transition: transform 0.2s, box-shadow 0.2s;
                cursor: default;
            " onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px -5px rgba(0,0,0,0.06)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.02)'">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:10px; height:10px; border-radius:50%; background:${colors[i]}; box-shadow: 0 0 10px ${colors[i]}99;"></div>
                    <span style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.3px;">${label}</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                    <span style="font-size:24px; font-weight:800; color:#0f172a; line-height:1;">${val}</span>
                    <span style="font-size:13px; font-weight:700; color:${colors[i]}; background:${bgTint}; padding:3px 8px; border-radius:6px;">${pct}%</span>
                </div>
            </div>`;
        });
        html += '</div>';
        detailsContainer.innerHTML = html;
    }
}
