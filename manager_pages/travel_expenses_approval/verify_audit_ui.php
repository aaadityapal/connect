<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Audit Tool | Connect</title>
    <link rel="stylesheet" href="../../manager_pages/employees_performance/css/global.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --accent-primary: #38bdf8;
            --accent-success: #22c55e;
            --accent-warning: #f59e0b;
            --accent-error: #ef4444;
            --text-muted: #94a3b8;
        }

        body {
            background: var(--bg-dark);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 40px;
            text-align: center;
        }

        .header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            margin: 0;
            background: linear-gradient(to right, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            color: var(--text-muted);
            margin-top: 10px;
        }

        .audit-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            text-align: left;
            padding: 16px;
            color: var(--text-muted);
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 20px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }

        .id-badge {
            background: rgba(56, 189, 248, 0.1);
            color: var(--accent-primary);
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .dist-value {
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
        }

        .discrepancy {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .status-ok {
            background: rgba(34, 197, 94, 0.1);
            color: var(--accent-success);
        }

        .status-warn {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent-warning);
        }

        .status-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-error);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .refresh-btn {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 20px;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(56, 189, 248, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Distance Verification Audit</h1>
            <p>Comparing multi-level verification distances for transparency and accuracy</p>
        </div>

        <button class="refresh-btn" onclick="fetchAuditData()">Refresh Production Data</button>

        <div class="audit-card">
            <div id="audit-content">
                <div class="loading">Fetching data from production...</div>
            </div>
        </div>
    </div>

    <script>
        const targetIds = '2664,2665';

        async function fetchAuditData() {
            const container = document.getElementById('audit-content');
            container.innerHTML = '<div class="loading">Loading audit reports...</div>';

            try {
                const response = await fetch(`api/check_audit.php?ids=${targetIds}`);
                const result = await response.json();

                if (result.success) {
                    renderTable(result.data);
                } else {
                    container.innerHTML = `<div class="status-error" style="padding:20px">${result.message}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="status-error" style="padding:20px">Error connecting to API</div>`;
            }
        }

        function getStatusClass(diff) {
            if (diff === null) return 'status-warn';
            if (diff === 0) return 'status-ok';
            if (diff <= 3) return 'status-warn';
            return 'status-error';
        }

        function renderTable(data) {
            const container = document.getElementById('audit-content');
            if (data.length === 0) {
                container.innerHTML = '<div class="loading">No records found for these IDs.</div>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Expense ID</th>
                            <th>Claimed (Emp)</th>
                            <th>L1 (Manager)</th>
                            <th>L2 (HR)</th>
                            <th>Max Discrepancy</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(row => {
                const maxDiff = Math.max(
                    row.discrepancies.mgr_vs_claim || 0,
                    row.discrepancies.hr_vs_mgr || 0
                );

                html += `
                    <tr>
                        <td><span class="id-badge">#${row.id}</span></td>
                        <td><span class="dist-value">${row.claimed_distance} KM</span></td>
                        <td>
                            <div class="dist-value">${row.manager_distance || '-'} KM</div>
                            <small style="color:var(--text-muted)">${row.distance_confirmed_at || ''}</small>
                        </td>
                        <td>
                            <div class="dist-value">${row.hr_distance || '-'} KM</div>
                            <small style="color:var(--text-muted)">${row.hr_confirmed_at || ''}</small>
                        </td>
                        <td>
                            <span class="discrepancy ${getStatusClass(maxDiff)}">
                                ${maxDiff.toFixed(2)} KM
                                ${maxDiff > 3 ? '⚠️ Mismatch' : (maxDiff === 0 ? '✅ Perfect' : '⚡ Within Limit')}
                            </span>
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;
        }

        // Initial fetch
        fetchAuditData();
    </script>
</body>
</html>
