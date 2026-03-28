// ─── Performance Trend Chart ──────────────────────────
const ctxTrend = document.getElementById('performanceTrendChart').getContext('2d');

const data6M = window.apiData.trend.data6M;
const data12M = window.apiData.trend.data12M;

let gradientBlue = ctxTrend.createLinearGradient(0, 0, 0, 350);
gradientBlue.addColorStop(0, 'rgba(59,130,246,0.4)');
gradientBlue.addColorStop(1, 'rgba(59,130,246,0.0)');

let gradientGreen = ctxTrend.createLinearGradient(0, 0, 0, 350);
gradientGreen.addColorStop(0, 'rgba(16,185,129,0.3)');
gradientGreen.addColorStop(1, 'rgba(16,185,129,0.0)');

let trendChart = new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: data6M.labels,
        datasets: [
            {
                label: 'Efficiency %',
                data: data6M.efficiency,
                borderColor: '#3b82f6',
                backgroundColor: gradientBlue,
                fill: true,
                tension: 0.4,
                cubicInterpolationMode: 'monotone',
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointHoverBorderWidth: 3,
                pointHoverBackgroundColor: '#3b82f6',
                pointHoverBorderColor: '#fff',
                hitRadius: 20
            },
            {
                label: 'Completion Rate %',
                data: data6M.completion,
                borderColor: '#10b981',
                backgroundColor: gradientGreen,
                fill: true,
                tension: 0.4,
                cubicInterpolationMode: 'monotone',
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointHoverBorderWidth: 3,
                pointHoverBackgroundColor: '#10b981',
                pointHoverBorderColor: '#fff',
                hitRadius: 20
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                align: 'center',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'line',
                    boxWidth: 24,
                    font: { size: 12, family: 'Inter' },
                    color: '#4b5563'
                }
            },
            tooltip: {
                backgroundColor: '#fff',
                titleColor: '#111827',
                bodyColor: '#4b5563',
                borderColor: '#e2e8f0',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    stepSize: 10,
                    font: { size: 11, family: 'Inter' },
                    color: '#9ca3af',
                    callback: v => v + ''
                },
                grid: { color: '#f1f5f9' },
                border: { display: false }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 11, family: 'Inter' },
                    color: '#9ca3af'
                },
                border: { display: false }
            }
        }
    }
});

window.updateTrend = function(period, btn) {
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const d = period === '12M' ? data12M : data6M;
    trendChart.data.labels = d.labels;
    trendChart.data.datasets[0].data = d.efficiency;
    trendChart.data.datasets[1].data = d.completion;
    trendChart.update('active');
};
