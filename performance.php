<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');
$conn->query("SET time_zone = '+05:30'");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get current date for calculations
$currentDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics -
        <?php echo htmlspecialchars($user_data['username']); ?>
    </title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <!-- Lucide Icons (required by sidebar) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- Set sidebar base-path BEFORE sidebar-loader.js so it resolves correctly -->
    <script>window.SIDEBAR_BASE_PATH = 'studio_users/';</script>
    <!-- Reusable Sidebar Loader -->
    <script src="studio_users/components/sidebar-loader.js" defer></script>

    <style>
        /* ── Google Font ─────────────────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        /* ── Tokens ──────────────────────────────────────────────── */
        :root {
            --primary:      #4f46e5;
            --primary-lt:   #ede9fe;
            --success:      #10b981;
            --success-lt:   #ecfdf5;
            --warning:      #f59e0b;
            --warning-lt:   #fffbeb;
            --danger:       #ef4444;
            --danger-lt:    #fef2f2;
            --info:         #06b6d4;
            --info-lt:      #ecfeff;
            --bg:           #f5f6fa;
            --surface:      #ffffff;
            --border:       #e5e7eb;
            --text:         #111827;
            --muted:        #6b7280;
            --shadow-sm:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow:       0 4px 12px rgba(0,0,0,.06);
            --radius:       10px;
            --sidebar-w:    260px;
            --sidebar-col:  76px;
            --transition:   220ms ease;
        }

        /* ── Reset ───────────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { font-size: 14px; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        /* ── Layout ──────────────────────────────────────────────── */

        /* Sidebar-mount must have no height — the sidebar inside is position:fixed */
        #sidebar-mount {
            height: 0;
            overflow: visible;
        }

        .performance-container {
            margin-left: var(--sidebar-w);
            padding: 1.5rem 1.75rem;
            min-height: 100vh;
            transition: margin-left var(--transition);
        }

        /* sidebar collapsed state */
        #appSidebar.collapsed ~ #sidebar-mount + .performance-container,
        body:has(#appSidebar.collapsed) .performance-container {
            margin-left: var(--sidebar-col);
        }

        /* ── Page Header ─────────────────────────────────────────── */
        .page-header {
            margin-bottom: 1.5rem;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: var(--radius);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.2px;
        }

        .page-title i { font-size: 1.1rem; opacity: 0.85; }

        .page-subtitle {
            color: rgba(255,255,255,.75);
            font-size: 0.8rem;
            margin-top: 3px;
        }

        /* ── Metric Cards Grid ───────────────────────────────────── */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.1rem 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            border-top: 3px solid var(--primary);
            transition: box-shadow .2s;
            position: relative;
        }

        .metric-card:hover { box-shadow: var(--shadow); }

        .metric-card.success { border-top-color: var(--success); }
        .metric-card.warning { border-top-color: var(--warning); }
        .metric-card.danger  { border-top-color: var(--danger);  }
        .metric-card.info    { border-top-color: var(--info);    }
        .metric-card.purple  { border-top-color: #8b5cf6;         }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.6rem;
        }

        .metric-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
        }

        .metric-icon.primary { background: var(--primary); }
        .metric-icon.success { background: var(--success); }
        .metric-icon.warning { background: var(--warning); }
        .metric-icon.danger  { background: var(--danger);  }
        .metric-icon.info    { background: var(--info);    }
        .metric-icon.purple  { background: #8b5cf6;         }

        .metric-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 0.25rem;
        }

        .metric-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .metric-description {
            color: var(--muted);
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .metric-progress {
            width: 100%; height: 5px;
            background: var(--bg);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.4rem;
        }

        .metric-progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width .8s ease;
        }

        .metric-progress-fill.success { background: var(--success); }
        .metric-progress-fill.warning { background: var(--warning); }
        .metric-progress-fill.danger  { background: var(--danger);  }

        .metric-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--muted);
        }

        /* ── Charts Section ──────────────────────────────────────── */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .chart-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border);
        }

        .chart-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
        }

        .chart-filters { display: flex; gap: 0.35rem; }

        .filter-btn {
            padding: 0.3rem 0.7rem;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            border-radius: 6px;
            cursor: pointer;
            transition: all .2s;
            font-size: 0.75rem;
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .chart-container {
            position: relative;
            height: 240px;
            width: 100%;
        }

        /* ── Breakdown / Stat Cards ───────────────────────────────── */
        .breakdown-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .breakdown-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .breakdown-header {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 1rem; padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border);
        }

        .breakdown-title { font-size: 0.85rem; font-weight: 600; color: var(--text); }

        .breakdown-list {
            display: flex; flex-direction: column; gap: 0.6rem;
            max-height: 320px; overflow-y: auto; padding-right: 4px;
        }

        .breakdown-list::-webkit-scrollbar { width: 4px; }
        .breakdown-list::-webkit-scrollbar-track { background: var(--bg); border-radius: 2px; }
        .breakdown-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .breakdown-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.65rem 0.85rem;
            background: var(--bg);
            border-radius: 8px;
            border-left: 3px solid var(--primary);
            transition: all .2s;
        }

        .breakdown-item:hover { transform: translateX(3px); box-shadow: var(--shadow-sm); }
        .breakdown-item.success { border-left-color: var(--success); }
        .breakdown-item.warning { border-left-color: var(--warning); }
        .breakdown-item.danger  { border-left-color: var(--danger);  }

        .breakdown-info { display: flex; flex-direction: column; }
        .breakdown-label { font-weight: 600; color: var(--text); font-size: 0.8rem; margin-bottom: 2px; }
        .breakdown-meta { font-size: 0.72rem; color: var(--muted); }
        .breakdown-value { font-size: 1rem; font-weight: 700; color: var(--text); }

        /* ── Spinners & Empty States ─────────────────────────────── */
        .loading-spinner { display: flex; justify-content: center; align-items: center; height: 120px; }

        .spinner {
            width: 28px; height: 28px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: 2rem; color: var(--muted); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.4; display: block; }

        /* ── Section Header ─────────────────────────────────────── */
        .completion-tracking-section { margin-bottom: 1.5rem; }

        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; padding: 0.9rem 1.25rem;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 0.95rem; font-weight: 600; color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }

        .tracking-filters { display: flex; gap: 0.35rem; }

        /* ── Completion Stat Cards ──────────────────────────────── */
        .completion-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .completion-stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1rem 1.1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex; align-items: center; gap: 0.85rem;
            transition: box-shadow .2s;
        }

        .completion-stat-card:hover { box-shadow: var(--shadow); }

        .stat-icon {
            width: 44px; height: 44px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff; flex-shrink: 0;
        }

        .stat-icon.stages   { background: var(--primary); }
        .stat-icon.substages{ background: var(--info);    }
        .stat-icon.late     { background: var(--danger);  }

        .stat-content { flex: 1; }

        .stat-title {
            font-size: 0.68rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;
        }

        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1; margin-bottom: 3px; }
        .stat-details { font-size: 0.72rem; color: var(--muted); }

        /* ── Dual Container ─────────────────────────────────────── */
        .dual-container-section {
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
            height: 520px;
        }

        .projects-container, .recent-completions-container {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex; flex-direction: column; overflow: hidden;
        }

        .container-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.9rem 1.1rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
            flex-shrink: 0;
        }

        .container-title {
            font-size: 0.85rem; font-weight: 600; color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }

        .project-search { position: relative; max-width: 200px; }

        .search-input {
            width: 100%;
            padding: 0.35rem 2rem 0.35rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.78rem;
            background: var(--surface);
            transition: border-color .2s;
        }

        .search-input:focus { outline: none; border-color: var(--primary); }
        .search-icon { position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.75rem; pointer-events: none; }

        .filter-select {
            padding: 0.35rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text);
            font-size: 0.78rem;
            cursor: pointer;
        }

        .filter-select:focus { outline: none; border-color: var(--primary); }

        /* Scroll areas */
        .projects-scroll-container, .completions-scroll-container {
            flex: 1; overflow-y: auto; padding: 0.75rem;
            scrollbar-width: thin; scrollbar-color: var(--border) transparent;
        }

        .projects-scroll-container::-webkit-scrollbar,
        .completions-scroll-container::-webkit-scrollbar { width: 4px; }
        .projects-scroll-container::-webkit-scrollbar-thumb,
        .completions-scroll-container::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        /* Project Cards */
        .project-card {
            background: var(--bg);
            border-radius: 8px;
            margin-bottom: 0.6rem;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: box-shadow .2s;
        }

        .project-card:hover { box-shadow: var(--shadow-sm); }

        .project-card-header {
            background: var(--surface);
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            display: flex; justify-content: space-between; align-items: center;
        }

        .project-card-title {
            font-weight: 600; color: var(--text); font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
        }

        .project-card-stats { font-size: 0.72rem; color: var(--muted); text-align: right; line-height: 1.4; }

        .expand-icon { transition: transform .25s; color: var(--muted); font-size: 0.75rem; }
        .project-card.expanded .expand-icon { transform: rotate(180deg); }

        .project-card-content { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
        .project-card.expanded .project-card-content { max-height: 600px; }

        .stages-list { padding: 0.6rem; max-height: 320px; overflow-y: auto; }
        .stages-list::-webkit-scrollbar { width: 4px; }
        .stages-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .stage-item {
            margin-bottom: 0.5rem; padding: 0.65rem 0.85rem;
            background: var(--surface); border-radius: 6px;
            border-left: 3px solid var(--primary);
        }

        .stage-item.completed { border-left-color: var(--success); }
        .stage-item.late      { border-left-color: var(--danger);  }
        .stage-item.pending   { border-left-color: var(--warning); }

        .stage-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; }
        .stage-title  { font-weight: 600; color: var(--text); font-size: 0.8rem; }

        .stage-status {
            font-size: 0.68rem; padding: 2px 7px; border-radius: 10px; font-weight: 500;
        }

        .stage-status.completed { background: var(--success-lt); color: var(--success); }
        .stage-status.late      { background: var(--danger-lt);  color: var(--danger);  }
        .stage-status.pending   { background: var(--warning-lt); color: var(--warning); }

        .substages-list {
            margin-top: 0.5rem; padding-left: 0.75rem;
            border-left: 2px solid var(--border);
            max-height: 240px; overflow-y: auto;
        }

        .substages-list::-webkit-scrollbar { width: 3px; }
        .substages-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .substage-item {
            padding: 0.4rem 0; border-bottom: 1px solid rgba(229,231,235,.5);
            display: flex; justify-content: space-between; align-items: center;
        }

        .substage-item:last-child { border-bottom: none; }
        .substage-info { flex: 1; }
        .substage-title { font-size: 0.78rem; color: var(--text); margin-bottom: 2px; }
        .substage-meta  { font-size: 0.68rem; color: var(--muted); }

        /* Recent Completions */
        .completion-item {
            background: var(--bg); border-radius: 8px; padding: 0.75rem 0.9rem;
            margin-bottom: 0.5rem; border-left: 3px solid var(--primary);
            transition: transform .2s;
        }

        .completion-item:hover { transform: translateX(3px); }
        .completion-item.on-time { border-left-color: var(--success); }
        .completion-item.late    { border-left-color: var(--danger);  }

        .completion-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.3rem; }

        .completion-title {
            font-weight: 600; color: var(--text); font-size: 0.8rem;
            display: flex; align-items: center; gap: 5px;
        }

        .completion-status {
            font-size: 0.68rem; padding: 2px 7px; border-radius: 10px; font-weight: 500;
        }

        .completion-status.on-time { background: var(--success-lt); color: var(--success); }
        .completion-status.late    { background: var(--danger-lt);  color: var(--danger);  }

        .completion-meta  { font-size: 0.75rem; color: var(--muted); margin-bottom: 2px; }
        .completion-dates { font-size: 0.7rem; color: var(--muted); }

        .days-indicator { font-size: 0.7rem; font-weight: 600; margin-top: 3px; }
        .days-indicator.positive { color: var(--success); }
        .days-indicator.negative { color: var(--danger);  }
        .days-indicator.neutral  { color: var(--muted);   }

        /* ── Suggestions ────────────────────────────────────────── */
        .suggestions-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 0.75rem;
            padding: 0.25rem 0;
        }

        .suggestion-card {
            background: var(--surface);
            border-radius: 8px; padding: 1rem;
            border-left: 3px solid var(--primary);
            box-shadow: var(--shadow-sm);
            transition: box-shadow .2s, transform .2s;
        }

        .suggestion-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .suggestion-card.critical { border-left-color: var(--danger);  background: linear-gradient(135deg, rgba(239,68,68,.02) 0%, #fff 100%); }
        .suggestion-card.warning  { border-left-color: var(--warning); background: linear-gradient(135deg, rgba(245,158,11,.02) 0%, #fff 100%); }
        .suggestion-card.info     { border-left-color: var(--info);    background: linear-gradient(135deg, rgba(6,182,212,.02) 0%, #fff 100%); }
        .suggestion-card.success  { border-left-color: var(--success); background: linear-gradient(135deg, rgba(16,185,129,.02) 0%, #fff 100%); }

        .suggestion-header { display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; }

        .suggestion-icon {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: 0.9rem;
        }

        .suggestion-icon.critical { color: var(--danger);  background: var(--danger-lt);  }
        .suggestion-icon.warning  { color: var(--warning); background: var(--warning-lt); }
        .suggestion-icon.info     { color: var(--info);    background: var(--info-lt);    }
        .suggestion-icon.success  { color: var(--success); background: var(--success-lt); }

        .suggestion-title   { font-weight: 600; color: var(--text); font-size: 0.82rem; flex: 1; }
        .suggestion-message { font-size: 0.78rem; color: var(--muted); line-height: 1.5; }
        .suggestion-message strong { color: var(--primary); font-weight: 600; }
        .suggestion-card.critical .suggestion-message strong { color: var(--danger);  }
        .suggestion-card.warning  .suggestion-message strong { color: var(--warning); }
        .suggestion-card.success  .suggestion-message strong { color: var(--success); }

        .suggestion-list { margin: 0.5rem 0 0 0; padding-left: 1.25rem; list-style: none; }
        .suggestion-list li { position: relative; margin-bottom: 0.3rem; font-size: 0.76rem; line-height: 1.4; }
        .suggestion-list li::before { content: '✓'; position: absolute; left: -1.25rem; color: var(--success); font-weight: 700; font-size: 0.8rem; }
        .suggestion-card.critical .suggestion-list li::before { content: '!'; color: var(--danger);  }
        .suggestion-card.warning  .suggestion-list li::before { content: '⚠'; color: var(--warning); }

        /* Priority labels */
        .suggestion-card.critical .suggestion-title::after {
            content: 'URGENT'; font-size: 0.6rem; font-weight: 700; color: var(--danger);
            background: var(--danger-lt); padding: 1px 5px; border-radius: 3px; margin-left: 6px; vertical-align: middle;
        }
        .suggestion-card.success .suggestion-title::after {
            content: 'KEEP IT UP'; font-size: 0.6rem; font-weight: 700; color: var(--success);
            background: var(--success-lt); padding: 1px 5px; border-radius: 3px; margin-left: 6px; vertical-align: middle;
        }

        /* ── Animations ─────────────────────────────────────────── */
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ─────────────────────────────────────────── */
        @media (max-width: 900px) {
            .charts-section          { grid-template-columns: 1fr; }
            .dual-container-section  { grid-template-columns: 1fr; height: auto; }
            .projects-container,
            .recent-completions-container { height: 380px; }
        }

        @media (max-width: 768px) {
            .performance-container { margin-left: 0; padding: 1rem; padding-top: 3.5rem; }
            .metrics-grid          { grid-template-columns: 1fr 1fr; }
            .charts-section        { grid-template-columns: 1fr; }
            .section-header        { flex-direction: column; gap: 0.75rem; }
            .tracking-filters      { flex-wrap: wrap; }
            .completion-stats-grid { grid-template-columns: 1fr; }
            .suggestions-container { grid-template-columns: 1fr; }
            .container-header      { flex-direction: column; gap: 0.6rem; align-items: flex-start; }
            .project-search        { max-width: 100%; }
        }

        @media (max-width: 480px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <!-- Sidebar injected here by studio_users/components/sidebar-loader.js -->
    <div id="sidebar-mount"></div>

    <div class="performance-container">

        <div class="page-header">

            <h1 class="page-title">
                <i class="fas fa-chart-bar"></i>
                Performance Analytics
            </h1>
            <p class="page-subtitle">Comprehensive overview of your work performance and productivity metrics</p>
        </div>

        <!-- Key Metrics Grid -->
        <div class="metrics-grid">
            <!-- Overall Efficiency -->
            <div class="metric-card success">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Overall Efficiency</div>
                        <?php
                        // Calculate overall efficiency
                        $efficiencyQuery = "SELECT 
                            COUNT(*) as total_completed,
                            SUM(CASE WHEN pss.updated_at <= pss.end_date THEN 1 ELSE 0 END) as on_time_completed
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status = 'completed'
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";

                        $stmt = $conn->prepare($efficiencyQuery);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $efficiencyData = $stmt->get_result()->fetch_assoc();

                        $totalCompleted = $efficiencyData['total_completed'];
                        $onTimeCompleted = $efficiencyData['on_time_completed'];
                        $efficiencyPercentage = $totalCompleted > 0 ? round(($onTimeCompleted / $totalCompleted) * 100) : 0;
                        ?>
                        <div class="metric-value">
                            <?php echo $efficiencyPercentage; ?>%
                        </div>
                        <div class="metric-description">On-time completion rate</div>
                    </div>
                    <div class="metric-icon success">
                        <i class="fas fa-bullseye"></i>
                    </div>
                </div>
                <div class="metric-progress">
                    <div class="metric-progress-fill success" style="width: <?php echo $efficiencyPercentage; ?>%">
                    </div>
                </div>
                <div class="metric-details">
                    <span>
                        <?php echo $onTimeCompleted; ?> on-time
                    </span>
                    <span>
                        <?php echo $totalCompleted; ?> total
                    </span>
                </div>
            </div>

            <!-- Active Tasks -->
            <div class="metric-card primary">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Active Tasks</div>
                        <?php
                        // Count active tasks
                        $activeQuery = "SELECT COUNT(*) as active_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status IN ('pending', 'in_progress', 'in_review')
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";

                        $stmt = $conn->prepare($activeQuery);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $activeData = $stmt->get_result()->fetch_assoc();
                        $activeTasks = $activeData['active_count'];
                        ?>
                        <div class="metric-value">
                            <?php echo $activeTasks; ?>
                        </div>
                        <div class="metric-description">Currently in progress</div>
                    </div>
                    <div class="metric-icon primary">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>

            <!-- Monthly Completions -->
            <div class="metric-card info">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">This Month</div>
                        <?php
                        // Count monthly completions
                        $monthlyQuery = "SELECT COUNT(*) as monthly_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status = 'completed'
                        AND DATE_FORMAT(pss.updated_at, '%Y-%m') = ?
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";

                        $stmt = $conn->prepare($monthlyQuery);
                        $stmt->bind_param("is", $user_id, $currentMonth);
                        $stmt->execute();
                        $monthlyData = $stmt->get_result()->fetch_assoc();
                        $monthlyCompletions = $monthlyData['monthly_count'];
                        ?>
                        <div class="metric-value">
                            <?php echo $monthlyCompletions; ?>
                        </div>
                        <div class="metric-description">Tasks completed</div>
                    </div>
                    <div class="metric-icon info">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Upcoming Deadlines -->
            <div class="metric-card warning">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Upcoming Deadlines</div>
                        <?php
                        // Count upcoming deadlines (next 7 days)
                        $upcomingQuery = "SELECT COUNT(*) as upcoming_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status NOT IN ('completed', 'cancelled')
                        AND pss.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";

                        $stmt = $conn->prepare($upcomingQuery);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $upcomingData = $stmt->get_result()->fetch_assoc();
                        $upcomingDeadlines = $upcomingData['upcoming_count'];
                        ?>
                        <div class="metric-value">
                            <?php echo $upcomingDeadlines; ?>
                        </div>
                        <div class="metric-description">Next 7 days</div>
                    </div>
                    <div class="metric-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Trend Chart -->
        <div class="chart-card" style="margin-bottom: 2rem;">
            <div class="chart-header">
                <h3 class="chart-title">Performance Trend</h3>
                <div class="chart-filters">
                    <button class="filter-btn active" data-period="6months">6 Months</button>
                    <button class="filter-btn" data-period="12months">12 Months</button>
                </div>
            </div>
            <div class="chart-container" style="margin-top: 20px;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Enhanced Detailed Breakdown with Performance Suggestions -->
        <div class="charts-section">
            <!-- Task Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Task Status Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>

            <!-- Performance Improvement Suggestions -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Performance Improvement Suggestions By A.I. ðŸ‘½</h3>
                </div>
                <div id="performanceSuggestions" class="suggestions-container">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Stage and Substage Completion Tracking -->
        <div class="completion-tracking-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-tasks"></i>
                    Detailed Completion Tracking
                </h2>
                <div class="tracking-filters">
                    <button class="filter-btn active" data-filter="all">All Tasks</button>
                    <button class="filter-btn" data-filter="stages">Stages Only</button>
                    <button class="filter-btn" data-filter="substages">Substages Only</button>
                    <button class="filter-btn" data-filter="late">Late Tasks</button>
                </div>
            </div>

            <!-- Completion Statistics Cards -->
            <div class="completion-stats-grid">
                <div class="completion-stat-card">
                    <div class="stat-icon stages">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Stages</div>
                        <div class="stat-value" id="totalStages">-</div>
                        <div class="stat-details">
                            <span id="completedStages">-</span> completed â€¢
                            <span id="onTimeStages">-</span> on-time
                        </div>
                    </div>
                </div>

                <div class="completion-stat-card">
                    <div class="stat-icon substages">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Substages</div>
                        <div class="stat-value" id="totalSubstages">-</div>
                        <div class="stat-details">
                            <span id="completedSubstages">-</span> completed â€¢
                            <span id="onTimeSubstages">-</span> on-time
                        </div>
                    </div>
                </div>

                <div class="completion-stat-card">
                    <div class="stat-icon late">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Late Tasks</div>
                        <div class="stat-value" id="lateTasks">-</div>
                        <div class="stat-details">
                            <span id="overdueTasks">-</span> overdue
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Side-by-Side Containers for Projects and Recent Completions -->
        <div class="dual-container-section">
            <!-- All Projects with Stages and Substages Container -->
            <div class="projects-container">
                <div class="container-header">
                    <h3 class="container-title">
                        <i class="fas fa-project-diagram"></i>
                        All Projects - Stages & Substages
                    </h3>
                    <div class="project-search">
                        <input type="text" id="projectSearch" placeholder="Search projects..." class="search-input">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>
                <div id="allProjectsData" class="projects-scroll-container">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>

            <!-- Recent Task Completions Container -->
            <div class="recent-completions-container">
                <div class="container-header">
                    <h3 class="container-title">
                        <i class="fas fa-history"></i>
                        Recent Task Completions
                    </h3>
                    <div class="completion-filters">
                        <select id="completionTimeFilter" class="filter-select">
                            <option value="7">Last 7 days</option>
                            <option value="14">Last 14 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 3 months</option>
                        </select>
                    </div>
                </div>
                <div id="recentCompletionsData" class="completions-scroll-container">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function () {
                    const sidebar = document.getElementById('msbSidebar');
                    const backdrop = document.getElementById('msbBackdrop');

                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('is-open');
                        backdrop.classList.toggle('is-visible');
                        document.body.style.overflow = sidebar.classList.contains('is-open') ? 'hidden' : '';
                    }
                });
            }

            // Handle sidebar collapse for performance container
            const sidebar = document.getElementById('msbSidebar');
            const performanceContainer = document.querySelector('.performance-container');

            if (sidebar && performanceContainer) {
                // Observe sidebar changes
                const observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            const isCollapsed = sidebar.classList.contains('is-collapsed');
                            if (window.innerWidth > 768) {
                                performanceContainer.style.paddingLeft = isCollapsed ? '80px' : '280px';
                            }
                        }
                    });
                });

                observer.observe(sidebar, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            // Performance Trend Chart
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                try {
                    // Get performance data for charts
                    fetch('get_performance_data.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            new Chart(performanceCtx.getContext('2d'), {
                                type: 'line',
                                data: {
                                    labels: data.months,
                                    datasets: [{
                                        label: 'Efficiency %',
                                        data: data.efficiency,
                                        borderColor: '#3b82f6',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4
                                    }, {
                                        label: 'Completion Rate %',
                                        data: data.completion,
                                        borderColor: '#10b981',
                                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            position: 'top'
                                        }
                                    }
                                }
                            });

                            // Task Distribution Chart
                            const distributionCtx = document.getElementById('distributionChart');
                            if (distributionCtx) {
                                new Chart(distributionCtx.getContext('2d'), {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Completed', 'In Progress', 'Pending', 'Not Started'],
                                        datasets: [{
                                            data: data.distribution,
                                            backgroundColor: [
                                                '#10b981',
                                                '#3b82f6',
                                                '#f59e0b',
                                                '#6b7280'
                                            ],
                                            borderWidth: 0,
                                            cutout: '50%'
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    padding: 15,
                                                    usePointStyle: true,
                                                    font: {
                                                        size: 12
                                                    }
                                                }
                                            }
                                        },
                                        layout: {
                                            padding: {
                                                top: 10,
                                                bottom: 10
                                            }
                                        }
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error loading performance data:', error);
                            showChartError('performanceChart', 'Failed to load performance trend data');
                        });
                } catch (error) {
                    console.error('Chart initialization error:', error);
                    showChartError('performanceChart', 'Chart initialization failed');
                }
            }

            // Load detailed performance analytics
            loadDetailedPerformanceData();

            // Setup interactive features
            setupProjectSearch();
            setupCompletionFilter();

            // Filter functionality
            const filterButtons = document.querySelectorAll('.tracking-filters .filter-btn');
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    // Update active filter
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    applyTaskFilter(filter);
                });
            });

            // Period filter functionality
            const periodFilterButtons = document.querySelectorAll('.chart-filters .filter-btn');
            periodFilterButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    periodFilterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const period = this.dataset.period;
                    // Update chart data based on period
                    // Implementation would fetch new data and update chart
                    console.log('Period filter changed to:', period);
                });
            });
        });

        // Global functions accessible from HTML
        window.toggleProjectCard = function (header) {
            const projectCard = header.closest('.project-card');
            projectCard.classList.toggle('expanded');
        };

        function showChartError(chartId, message) {
            const chartContainer = document.getElementById(chartId).parentElement;
            chartContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                </div>
            `;
        }

        // Load detailed performance analytics
        function loadDetailedPerformanceData() {
            fetch('fetch_detailed_stage_performance_analytics.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCompletionStats(data.completion_stats);
                        displayPerformanceSuggestions(data.improvement_suggestions);
                        displayAllProjectsData(data.project_data);
                        displayRecentCompletions(data.recent_completions);
                    } else {
                        console.error('Error loading detailed data:', data.error);
                        showErrorMessage('Failed to load detailed performance data.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching detailed data:', error);
                    showErrorMessage('Network error while loading performance data.');
                });
        }

        // Update completion statistics
        function updateCompletionStats(stats) {
            document.getElementById('totalStages').textContent = stats.total_stages;
            document.getElementById('completedStages').textContent = stats.completed_stages;
            document.getElementById('onTimeStages').textContent = stats.on_time_stages;
            document.getElementById('totalSubstages').textContent = stats.total_substages;
            document.getElementById('completedSubstages').textContent = stats.completed_substages;
            document.getElementById('onTimeSubstages').textContent = stats.on_time_substages;
            document.getElementById('lateTasks').textContent = stats.late_substages;
            document.getElementById('overdueTasks').textContent = stats.overdue_substages;
        }

        // Display performance improvement suggestions
        function displayPerformanceSuggestions(suggestions) {
            const container = document.getElementById('performanceSuggestions');

            if (suggestions.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-lightbulb"></i><p>Loading personalized suggestions...</p></div>';
                return;
            }

            // Sort suggestions by priority (critical first, then warning, info, success)
            const priorityOrder = { 'critical': 1, 'warning': 2, 'info': 3, 'success': 4 };
            suggestions.sort((a, b) => priorityOrder[a.type] - priorityOrder[b.type]);

            const suggestionsHTML = suggestions.map((suggestion, index) => {
                // Add a subtle animation delay for each card
                const animationDelay = index * 100;

                return `
                    <div class="suggestion-card ${suggestion.type}" style="animation-delay: ${animationDelay}ms;">
                        <div class="suggestion-header">
                            <div class="suggestion-icon ${suggestion.type}">
                                <i class="${suggestion.icon}"></i>
                            </div>
                            <div class="suggestion-title">${suggestion.title}</div>
                        </div>
                        <div class="suggestion-message">${formatSuggestionMessage(suggestion.message)}</div>
                    </div>
                `;
            }).join('');

            container.innerHTML = `<div class="suggestions-container">${suggestionsHTML}</div>`;

            // Add entrance animation
            setTimeout(() => {
                const cards = container.querySelectorAll('.suggestion-card');
                cards.forEach(card => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.animation = 'slideInUp 0.5s ease forwards';
                });
            }, 100);
        }

        // Format suggestion messages for better readability
        function formatSuggestionMessage(message) {
            // Convert numbered lists to proper HTML lists
            if (message.includes('1)') || message.includes('2)') || message.includes('3)') || message.includes('4)')) {
                const parts = message.split(/(?=\d\))/);
                if (parts.length > 1) {
                    const intro = parts[0].trim();
                    const listItems = parts.slice(1).map(item => {
                        const cleanItem = item.replace(/^\d\)\s*/, '').trim();
                        return `<li>${cleanItem}</li>`;
                    }).join('');
                    return `${intro}<ul class="suggestion-list">${listItems}</ul>`;
                }
            }

            // Highlight important numbers and percentages
            return message.replace(/(\d+%|\d+\.?\d*\s*days?|\d+\s*tasks?)/gi, '<strong>$1</strong>');
        }

        // Display all projects data in collapsible cards
        function displayAllProjectsData(projectData) {
            const container = document.getElementById('allProjectsData');

            if (projectData.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>No project data available.</p></div>';
                return;
            }

            const projectsHTML = projectData.map(project => {
                const stagesHTML = Object.values(project.stages).map(stage => {
                    const substagesHTML = stage.substages.map(substage => {
                        let statusClass = 'pending';
                        let statusText = 'Pending';

                        switch (substage.completion_status) {
                            case 'on_time':
                                statusClass = 'completed';
                                statusText = 'On Time';
                                break;
                            case 'late':
                                statusClass = 'late';
                                statusText = 'Late';
                                break;
                            case 'overdue':
                                statusClass = 'late';
                                statusText = 'Overdue';
                                break;
                            default:
                                statusClass = 'pending';
                                statusText = 'Pending';
                        }

                        return `
                            <div class="substage-item">
                                <div class="substage-info">
                                    <div class="substage-title">${substage.title}</div>
                                    <div class="substage-meta">
                                        Substage ${substage.substage_number}
                                        ${substage.drawing_number ? ` â€¢ Drawing: ${substage.drawing_number}` : ''}
                                        â€¢ Deadline: ${new Date(substage.end_date).toLocaleDateString()}
                                    </div>
                                </div>
                                <div class="stage-status ${statusClass}">${statusText}</div>
                            </div>
                        `;
                    }).join('');

                    let stageStatusClass = 'pending';
                    let stageStatusText = 'Pending';

                    if (stage.stage_status === 'completed') {
                        stageStatusClass = 'completed';
                        stageStatusText = 'Completed';
                    } else if (stage.stage_status === 'in_progress') {
                        stageStatusClass = 'pending';
                        stageStatusText = 'In Progress';
                    }

                    return `
                        <div class="stage-item ${stageStatusClass}">
                            <div class="stage-header">
                                <div class="stage-title">
                                    <i class="fas fa-layer-group"></i>
                                    Stage ${stage.stage_number}
                                </div>
                                <div class="stage-status ${stageStatusClass}">${stageStatusText}</div>
                            </div>
                            <div class="substages-list">
                                ${substagesHTML}
                            </div>
                        </div>
                    `;
                }).join('');

                const projectStats = project.project_stats;
                const completionRate = projectStats.total_substages > 0 ?
                    Math.round((projectStats.completed_substages / projectStats.total_substages) * 100) : 0;

                // Count total stages
                const totalStages = Object.keys(project.stages).length;

                return `
                    <div class="project-card" data-project-title="${project.project_title.toLowerCase()}">
                        <div class="project-card-header" onclick="toggleProjectCard(this)">
                            <div class="project-card-title">
                                <i class="fas fa-project-diagram"></i>
                                ${project.project_title}
                            </div>
                            <div class="project-card-stats">
                                ${completionRate}% complete â€¢ ${projectStats.completed_substages}/${projectStats.total_substages} tasks
                                <br>
                                <small style="font-size: 0.75rem; color: var(--text-muted); opacity: 0.8;">
                                    ${totalStages} stages â€¢ ${projectStats.total_substages} substages
                                </small>
                            </div>
                            <i class="fas fa-chevron-down expand-icon"></i>
                        </div>
                        <div class="project-card-content">
                            <div class="stages-list">
                                ${stagesHTML}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = projectsHTML;
        }

        // Display recent completions in scroll container
        function displayRecentCompletions(recentCompletions) {
            const container = document.getElementById('recentCompletionsData');

            if (recentCompletions.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No recent completions found.</p></div>';
                return;
            }

            const completionsHTML = recentCompletions.map(completion => {
                const statusClass = completion.status === 'on_time' ? 'on-time' : 'late';
                const statusText = completion.status === 'on_time' ? 'On Time' : 'Late';
                const daysText = completion.days_difference !== null ?
                    (completion.days_difference <= 0 ?
                        `${Math.abs(completion.days_difference)} days early` :
                        `${completion.days_difference} days late`) : '';

                return `
                    <div class="completion-item ${statusClass}">
                        <div class="completion-header">
                            <div class="completion-title">
                                <i class="fas fa-check-circle"></i>
                                ${completion.substage_title}
                            </div>
                            <div class="completion-status ${statusClass}">${statusText}</div>
                        </div>
                        <div class="completion-meta">
                            ${completion.project_title}
                        </div>
                        <div class="completion-meta">
                            Stage ${completion.stage_number} â€¢ Substage ${completion.substage_number}
                            ${completion.drawing_number ? ` â€¢ Drawing: ${completion.drawing_number}` : ''}
                        </div>
                        <div class="completion-dates">
                            Completed: ${new Date(completion.updated_at).toLocaleDateString()}
                            â€¢ Deadline: ${new Date(completion.end_date).toLocaleDateString()}
                        </div>
                        ${daysText ? `<div class="days-indicator ${completion.status === 'on_time' ? 'positive' : 'negative'}">${daysText}</div>` : ''}
                    </div>
                `;
            }).join('');

            container.innerHTML = completionsHTML;
        }

        // Show error message
        function showErrorMessage(message) {
            const errorHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>${message}</p></div>`;
            document.getElementById('performanceSuggestions').innerHTML = errorHTML;
            document.getElementById('allProjectsData').innerHTML = errorHTML;
            document.getElementById('recentCompletionsData').innerHTML = errorHTML;
        }

        // Toggle project card expansion
        function toggleProjectCard(header) {
            const projectCard = header.closest('.project-card');
            projectCard.classList.toggle('expanded');
        }

        // Project search functionality
        function setupProjectSearch() {
            const searchInput = document.getElementById('projectSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const searchTerm = this.value.toLowerCase();
                    const projectCards = document.querySelectorAll('.project-card');

                    projectCards.forEach(card => {
                        const projectTitle = card.dataset.projectTitle;
                        const shouldShow = projectTitle.includes(searchTerm);
                        card.style.display = shouldShow ? 'block' : 'none';
                    });
                });
            }
        }

        // Completion time filter functionality
        function setupCompletionFilter() {
            const filterSelect = document.getElementById('completionTimeFilter');
            if (filterSelect) {
                filterSelect.addEventListener('change', function () {
                    const days = parseInt(this.value);
                    // Reload recent completions with new filter
                    loadRecentCompletions(days);
                });
            }
        }

        // Load recent completions with time filter
        function loadRecentCompletions(days = 30) {
            const container = document.getElementById('recentCompletionsData');
            container.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';

            fetch(`fetch_detailed_stage_performance_analytics.php?recent_days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.recent_completions) {
                        displayRecentCompletions(data.recent_completions);
                    } else {
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No recent completions found.</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading recent completions:', error);
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load recent completions.</p></div>';
                });
        }

        function applyTaskFilter(filter) {
            const cards = document.querySelectorAll('.task-completion-card');

            cards.forEach(card => {
                const isStage = card.querySelector('.task-title i.fa-layer-group') !== null;
                const isSubstage = card.querySelector('.task-title i.fa-list-ul') !== null;
                const isLate = card.classList.contains('late') || card.classList.contains('overdue');

                let shouldShow = true;

                switch (filter) {
                    case 'stages':
                        shouldShow = isStage;
                        break;
                    case 'substages':
                        shouldShow = isSubstage;
                        break;
                    case 'late':
                        shouldShow = isLate;
                        break;
                    case 'all':
                    default:
                        shouldShow = true;
                }

                card.style.display = shouldShow ? 'block' : 'none';
            });
        }

        // Filter buttons functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const period = this.dataset.period;
                // Update chart data based on period
                // Implementation would fetch new data and update chart
            });
        });
    </script>
</body>

</html>