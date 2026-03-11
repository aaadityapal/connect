<?php
/**
 * user_list.php
 * -------------------------------------------------------
 * Displays all active users with their names and phone
 * numbers, fetched live from the database.
 * -------------------------------------------------------
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';

// ── Fetch users ────────────────────────────────────────────────────────────────
$users = [];
$dbError = null;

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            username        AS name,
            phone,
            email,
            role,
            designation,
            employee_id,
            profile_picture,
            status
        FROM users
        WHERE LOWER(status) = 'active'
          AND deleted_at IS NULL
        ORDER BY username ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$totalUsers = count($users);
$generatedAt = date('d M Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Registry – Connect</title>
    <meta name="description"
        content="Complete directory of all active Connect platform users with their names and contact numbers." />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        /* ═══════════════════════════════════════════════
           DESIGN TOKENS
        ═══════════════════════════════════════════════ */
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface-2: #232738;
            --border: rgba(255, 255, 255, 0.07);
            --border-hover: rgba(255, 255, 255, 0.15);
            --accent: #6c63ff;
            --accent-soft: rgba(108, 99, 255, 0.15);
            --accent-glow: rgba(108, 99, 255, 0.35);
            --green: #22c55e;
            --yellow: #f59e0b;
            --text-1: #f1f5f9;
            --text-2: #94a3b8;
            --text-3: #64748b;
            --radius: 14px;
            --radius-sm: 8px;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
            --transition: 0.22s cubic-bezier(.4, 0, .2, 1);
        }

        /* ═══════════════════════════════════════════════
           RESET & BASE
        ═══════════════════════════════════════════════ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-1);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ═══════════════════════════════════════════════
           HEADER / HERO
        ═══════════════════════════════════════════════ */
        .hero {
            background: linear-gradient(135deg, #1a1d27 0%, #0f1117 50%, #12102a 100%);
            border-bottom: 1px solid var(--border);
            padding: 48px 32px 32px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .hero-left h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-left p {
            color: var(--text-2);
            font-size: 0.9rem;
            margin-top: 6px;
        }

        .hero-left p span {
            color: var(--accent);
            font-weight: 600;
        }

        .hero-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-2);
            white-space: nowrap;
        }

        .badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .6;
                transform: scale(1.3);
            }
        }

        /* ═══════════════════════════════════════════════
           TOOLBAR
        ═══════════════════════════════════════════════ */
        .toolbar {
            max-width: 1200px;
            margin: 28px auto 0;
            padding: 0 32px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-wrap {
            flex: 1;
            min-width: 220px;
            position: relative;
        }

        .search-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 0.85rem;
        }

        #searchInput {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px 10px 38px;
            color: var(--text-1);
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        #searchInput::placeholder {
            color: var(--text-3);
        }

        #searchInput:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        .view-toggle {
            display: flex;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .view-btn {
            background: var(--surface);
            border: none;
            padding: 10px 14px;
            color: var(--text-3);
            cursor: pointer;
            font-size: 0.85rem;
            transition: background var(--transition), color var(--transition);
        }

        .view-btn.active,
        .view-btn:hover {
            background: var(--accent);
            color: #fff;
        }

        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 18px;
            color: var(--text-2);
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: border-color var(--transition), color var(--transition);
        }

        .export-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ═══════════════════════════════════════════════
           STATS BAR
        ═══════════════════════════════════════════════ */
        .stats-bar {
            max-width: 1200px;
            margin: 20px auto 0;
            padding: 0 32px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
        }

        .stat-pill i {
            color: var(--accent);
            font-size: 0.9rem;
        }

        .stat-pill strong {
            color: var(--text-1);
            font-weight: 700;
            margin-right: 4px;
        }

        .stat-pill span {
            color: var(--text-3);
        }

        /* ═══════════════════════════════════════════════
           MAIN CONTENT
        ═══════════════════════════════════════════════ */
        .main {
            max-width: 1200px;
            margin: 24px auto 60px;
            padding: 0 32px;
        }

        /* --- GRID VIEW --- */
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .user-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: transform var(--transition), border-color var(--transition), box-shadow var(--transition);
            cursor: default;
            animation: fadeUp .35s ease both;
        }

        .user-card:hover {
            transform: translateY(-3px);
            border-color: var(--border-hover);
            box-shadow: var(--shadow);
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--surface-2);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            border: 1.5px solid var(--border);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .card-info {
            flex: 1;
            min-width: 0;
        }

        .card-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-role {
            font-size: 0.75rem;
            color: var(--accent);
            font-weight: 500;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-phone {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            font-size: 0.82rem;
            color: var(--text-2);
        }

        .card-phone i {
            color: var(--text-3);
            font-size: 0.75rem;
        }

        .card-phone a {
            color: var(--text-2);
            text-decoration: none;
            transition: color var(--transition);
        }

        .card-phone a:hover {
            color: var(--green);
        }

        .no-phone {
            color: var(--text-3);
            font-style: italic;
        }

        .emp-id-chip {
            display: inline-block;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px 7px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-3);
            margin-top: 6px;
            letter-spacing: 0.3px;
        }

        /* Active status badge */
        .status-active {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.30);
            border-radius: 999px;
            padding: 2px 9px;
            font-size: 0.68rem;
            font-weight: 600;
            color: #22c55e;
            margin-top: 6px;
            letter-spacing: 0.2px;
        }

        .status-active::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        /* Active pill inside table */
        .td-status-active {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.28);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            color: #22c55e;
            white-space: nowrap;
        }

        .td-status-active::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        /* --- TABLE VIEW --- */
        .table-view {
            display: none;
        }

        .table-view.active {
            display: block;
        }

        .grid-view.active {
            display: grid;
        }

        .registry-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .registry-table thead th {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-3);
            white-space: nowrap;
        }

        .registry-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }

        .registry-table tbody tr:hover {
            background: var(--surface-2);
        }

        .registry-table td {
            padding: 14px 16px;
            color: var(--text-2);
            vertical-align: middle;
        }

        .td-name {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .td-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--accent);
            flex-shrink: 0;
            overflow: hidden;
        }

        .td-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .td-name-text strong {
            display: block;
            color: var(--text-1);
            font-size: 0.875rem;
        }

        .td-name-text small {
            color: var(--text-3);
            font-size: 0.73rem;
        }

        .phone-link {
            color: var(--text-2);
            text-decoration: none;
        }

        .phone-link:hover {
            color: var(--green);
        }

        .role-badge {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--accent);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ═══════════════════════════════════════════════
           EMPTY / ERROR STATES
        ═══════════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-3);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 14px;
            display: block;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            color: #fca5a5;
            font-size: 0.875rem;
            margin-bottom: 24px;
        }

        /* ═══════════════════════════════════════════════
           RESULTS COUNT
        ═══════════════════════════════════════════════ */
        .results-count {
            font-size: 0.8rem;
            color: var(--text-3);
            margin-bottom: 16px;
        }

        .results-count span {
            color: var(--accent);
            font-weight: 600;
        }

        /* ═══════════════════════════════════════════════
           MODAL FOR WHATSAPP
        ═══════════════════════════════════════════════ */
        .wa-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .wa-modal-overlay.active {
            display: flex;
        }

        .wa-modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            padding: 28px;
            box-shadow: var(--shadow);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: fadeUp 0.3s ease;
        }

        .wa-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .wa-modal-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--text-3);
            cursor: pointer;
            font-size: 1.25rem;
        }

        .close-modal:hover {
            color: var(--text-1);
        }

        .wa-form-group {
            margin-bottom: 16px;
        }

        .wa-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .wa-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .wa-input {
            width: 100%;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            color: var(--text-1);
            font-size: 0.85rem;
            font-family: inherit;
            outline: none;
            transition: 0.2s;
            color-scheme: dark;
        }

        .wa-input:focus {
            border-color: var(--green);
        }

        .wa-submit-btn {
            width: 100%;
            background: var(--green);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }

        .wa-submit-btn:hover {
            background: #1da851;
        }

        .wa-submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .selected-count-badge {
            background: rgba(34, 197, 94, 0.15);
            color: var(--green);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 8px;
        }

        /* ═══════════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════════ */
        @media (max-width: 600px) {

            .hero,
            .toolbar,
            .stats-bar,
            .main {
                padding-left: 16px;
                padding-right: 16px;
            }

            .hero {
                padding-top: 30px;
            }

            .grid-view {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- ═══════════════════════ HERO ══════════════════════════════ -->
    <header class="hero">
        <div class="hero-inner">
            <div class="hero-left">
                <h1><i class="fas fa-address-book"
                        style="font-size:1.4rem; margin-right:10px; -webkit-text-fill-color:#6c63ff;"></i>User Registry
                </h1>
                <p>All active employees — <span><?= $totalUsers ?> users</span> found &nbsp;·&nbsp; Last updated
                    <?= $generatedAt ?>
                </p>
            </div>
            <div class="hero-meta">
                <span class="badge"><span class="dot"></span>Live Database</span>
                <span class="badge"><i class="fas fa-shield-halved" style="color:#6c63ff;font-size:.7rem;"></i> Internal
                    Use</span>
            </div>
        </div>
    </header>

    <!-- ═══════════════════════ TOOLBAR ═══════════════════════════ -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search by name, phone or role…" autocomplete="off">
        </div>
        <div class="view-toggle">
            <button class="view-btn active" id="btnGrid" title="Grid view"><i class="fas fa-grip"></i></button>
            <button class="view-btn" id="btnTable" title="Table view"><i class="fas fa-list"></i></button>
        </div>
        <button class="export-btn" id="selectAllBtn"><i class="fas fa-check-square"></i> Select All</button>
        <button class="export-btn" id="waBulkBtn" style="color:var(--green);border-color:var(--green);"><i
                class="fab fa-whatsapp"></i> Send WhatsApp</button>
        <button class="export-btn" id="exportBtn"><i class="fas fa-download"></i> Export JSON</button>
    </div>

    <!-- ═══════════════════════ STATS BAR ═════════════════════════ -->
    <div class="stats-bar">
        <div class="stat-pill">
            <i class="fas fa-users"></i>
            <div><strong><?= $totalUsers ?></strong><span>Active Users</span></div>
        </div>
        <?php
        $withPhone = count(array_filter($users, fn($u) => !empty($u['phone'])));
        $withoutPhone = $totalUsers - $withPhone;
        ?>
        <div class="stat-pill">
            <i class="fas fa-phone"></i>
            <div><strong><?= $withPhone ?></strong><span>Have Phone Numbers</span></div>
        </div>
        <?php if ($withoutPhone > 0): ?>
            <div class="stat-pill">
                <i class="fas fa-phone-slash" style="color:#f59e0b;"></i>
                <div><strong><?= $withoutPhone ?></strong><span>Missing Numbers</span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════ MAIN ══════════════════════════════ -->
    <main class="main">

        <?php if ($dbError): ?>
            <div class="error-banner">
                <i class="fas fa-circle-exclamation"></i>
                Database Error: <?= htmlspecialchars($dbError) ?>
            </div>
        <?php endif; ?>

        <p class="results-count" id="resultsCount">Showing <span id="visibleCount"><?= $totalUsers ?></span> of
            <?= $totalUsers ?> users
        </p>

        <!-- GRID VIEW -->
        <div class="grid-view active" id="gridView">
            <?php if (empty($users)): ?>
                <div class="empty-state" style="grid-column:1/-1;">
                    <i class="fas fa-users-slash"></i>
                    <p>No active users found in the database.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($users as $i => $u):
                $initials = strtoupper(substr($u['name'], 0, 1));
                $picSrc = !empty($u['profile_picture'])
                    ? '../uploads/profile_pictures/' . htmlspecialchars($u['profile_picture'])
                    : null;
                ?>
                <div class="user-card" style="animation-delay: <?= min($i * 0.04, 0.6) ?>s"
                    data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>"
                    data-phone="<?= htmlspecialchars($u['phone']) ?>"
                    data-role="<?= strtolower(htmlspecialchars($u['role'])) ?>">

                    <div style="margin-top:2px;">
                        <input type="checkbox" class="user-cb" value="<?= $u['id'] ?>"
                            data-name="<?= htmlspecialchars($u['name']) ?>"
                            data-phone="<?= htmlspecialchars($u['phone']) ?>"
                            style="width:18px;height:18px;cursor:pointer;accent-color:var(--green);">
                    </div>

                    <div class="avatar">
                        <?php if ($picSrc): ?>
                            <img src="<?= $picSrc ?>" alt="<?= htmlspecialchars($u['name']) ?>"
                                onerror="this.style.display='none';this.parentNode.textContent='<?= $initials ?>';">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>

                    <div class="card-info">
                        <div class="card-name"><?= htmlspecialchars($u['name']) ?></div>
                        <?php if (!empty($u['role'])): ?>
                            <div class="card-role"><?= htmlspecialchars($u['role']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($u['employee_id'])): ?>
                            <span class="emp-id-chip">#<?= htmlspecialchars($u['employee_id']) ?></span>
                        <?php endif; ?>

                        <!-- Active status badge -->
                        <span class="status-active">Active</span>

                        <div class="card-phone">
                            <i class="fas fa-phone"></i>
                            <?php if (!empty($u['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($u['phone']) ?>"><?= htmlspecialchars($u['phone']) ?></a>
                            <?php else: ?>
                                <span class="no-phone">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- TABLE VIEW -->
        <div class="table-view" id="tableView">
            <?php if (!empty($users)): ?>
                <table class="registry-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="tableSelectAllCb"
                                    style="width:16px;height:16px;cursor:pointer;accent-color:var(--green);"></th>
                            <th>#</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Phone Number</th>
                            <th>Role</th>
                            <th>Designation</th>
                            <th>Emp ID</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($users as $i => $u):
                            $initials = strtoupper(substr($u['name'], 0, 1));
                            $picSrc = !empty($u['profile_picture'])
                                ? '../uploads/profile_pictures/' . htmlspecialchars($u['profile_picture'])
                                : null;
                            ?>
                            <tr data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>"
                                data-phone="<?= htmlspecialchars($u['phone']) ?>"
                                data-role="<?= strtolower(htmlspecialchars($u['role'])) ?>">
                                <td>
                                    <input type="checkbox" class="user-cb" value="<?= $u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-phone="<?= htmlspecialchars($u['phone']) ?>"
                                        style="width:16px;height:16px;cursor:pointer;accent-color:var(--green);">
                                </td>
                                <td style="color:var(--text-3); font-size:.8rem;"><?= $i + 1 ?></td>
                                <td>
                                    <div class="td-name">
                                        <div class="td-avatar">
                                            <?php if ($picSrc): ?>
                                                <img src="<?= $picSrc ?>" alt="<?= htmlspecialchars($u['name']) ?>"
                                                    onerror="this.style.display='none';this.parentNode.textContent='<?= $initials ?>';">
                                            <?php else: ?>
                                                <?= $initials ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="td-name-text">
                                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                                            <?php if (!empty($u['email'])): ?>
                                                <small><?= htmlspecialchars($u['email']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <!-- Status column -->
                                <td><span class="td-status-active">Active</span></td>
                                <td>
                                    <?php if (!empty($u['phone'])): ?>
                                        <a class="phone-link" href="tel:<?= htmlspecialchars($u['phone']) ?>">
                                            <i class="fas fa-phone"
                                                style="font-size:.7rem; margin-right:5px; color:var(--green);"></i>
                                            <?= htmlspecialchars($u['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-3); font-style:italic;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['role'])): ?>
                                        <span class="role-badge"><?= htmlspecialchars($u['role']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-3);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($u['designation']) ? htmlspecialchars($u['designation']) : '<span style="color:var(--text-3);">—</span>' ?>
                                </td>
                                <td style="color:var(--text-3); font-size:.8rem;">
                                    <?= htmlspecialchars($u['employee_id'] ?? '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No active users found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- No results message (hidden by default) -->
        <div class="empty-state" id="noResults" style="display:none;">
            <i class="fas fa-magnifying-glass" style="color:var(--text-3);"></i>
            <p>No users match your search.</p>
        </div>

    </main>

    <!-- ═══════════════════════ WHATSAPP MODAL ════════════════════════════ -->
    <div class="wa-modal-overlay" id="waOverlay">
        <div class="wa-modal">
            <div class="wa-modal-header">
                <div class="wa-modal-title">
                    <i class="fab fa-whatsapp" style="color:var(--green); font-size:1.4rem;"></i>
                    Send Meeting Schedule
                    <span class="selected-count-badge" id="selectedCountBadge">0 selected</span>
                </div>
                <button class="close-modal" id="closeWaModal"><i class="fas fa-times"></i></button>
            </div>

            <form id="waForm">
                <div class="wa-form-row">
                    <div class="wa-form-group">
                        <label class="wa-label">📅 Meeting Date</label>
                        <input type="date" name="meeting_date" class="wa-input" required>
                    </div>
                    <div class="wa-form-group">
                        <label class="wa-label">⏰ Meeting Time</label>
                        <input type="time" name="meeting_time" class="wa-input" value="10:00" required>
                    </div>
                </div>

                <div class="wa-form-group">
                    <label class="wa-label">📆 Meeting Day</label>
                    <input type="text" name="meeting_day" class="wa-input" placeholder="e.g. Saturday" value="Saturday"
                        required>
                </div>

                <div class="wa-form-row">
                    <div class="wa-form-group">
                        <label class="wa-label">🏢 Reach Office By</label>
                        <input type="time" name="reach_by" class="wa-input" value="11:30" required>
                    </div>
                </div>

                <div class="wa-form-row">
                    <div class="wa-form-group">
                        <label class="wa-label">🚫 Non-Available From</label>
                        <input type="time" name="na_from" class="wa-input" value="11:00" required>
                    </div>
                    <div class="wa-form-group">
                        <label class="wa-label">🕒 Non-Available To</label>
                        <input type="time" name="na_to" class="wa-input" value="15:00" required>
                    </div>
                </div>

                <div class="wa-form-group">
                    <label class="wa-label">📎 Attach PDF Schedule File</label>
                    <input type="file" name="pdf_file" accept=".pdf" class="wa-input" style="padding: 7px 10px;"
                        required>
                </div>

                <button type="submit" class="wa-submit-btn" id="waSubmitBtn">
                    <i class="fas fa-paper-plane"></i> Send to Selected Users
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════ SCRIPTS ════════════════════════════ -->
    <script>
        /* ── View Toggle ───────────────────────────────── */
        const btnGrid = document.getElementById('btnGrid');
        const btnTable = document.getElementById('btnTable');
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');

        btnGrid.addEventListener('click', () => {
            gridView.classList.add('active');
            tableView.classList.remove('active');
            btnGrid.classList.add('active');
            btnTable.classList.remove('active');
        });

        btnTable.addEventListener('click', () => {
            tableView.classList.add('active');
            gridView.classList.remove('active');
            btnTable.classList.add('active');
            btnGrid.classList.remove('active');
        });

        /* ── Search / Filter ───────────────────────────── */
        const searchInput = document.getElementById('searchInput');
        const visibleCount = document.getElementById('visibleCount');
        const noResults = document.getElementById('noResults');

        searchInput.addEventListener('input', filterUsers);

        function filterUsers() {
            const q = searchInput.value.toLowerCase().trim();

            // Grid
            const cards = gridView.querySelectorAll('.user-card');
            // Table
            const rows = document.querySelectorAll('#tableBody tr');

            let count = 0;

            cards.forEach(el => {
                const match = !q
                    || el.dataset.name.includes(q)
                    || el.dataset.phone.includes(q)
                    || el.dataset.role.includes(q);
                el.style.display = match ? '' : 'none';
                if (match) count++;
            });

            rows.forEach(el => {
                const match = !q
                    || el.dataset.name.includes(q)
                    || el.dataset.phone.includes(q)
                    || el.dataset.role.includes(q);
                el.style.display = match ? '' : 'none';
            });

            visibleCount.textContent = count;
            noResults.style.display = count === 0 ? 'block' : 'none';
        }

        /* ── Export JSON ───────────────────────────────── */
        document.getElementById('exportBtn').addEventListener('click', () => {
            const anchor = document.createElement('a');
            anchor.href = 'fetch_user_registry.php';
            anchor.download = 'user_registry_<?= date('Ymd') ?>.json';
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
        });

        /* ── Checkbox Selection ────────────────────────── */
        const selectAllBtn = document.getElementById('selectAllBtn');
        const tableSelectAllCb = document.getElementById('tableSelectAllCb');
        const userCbs = document.querySelectorAll('.user-cb');

        function getSelectedUsers() {
            // Because we duplicated inputs (one for grid, one for table), 
            // we filter for checked inputs, then extract unique IDs.
            const checked = Array.from(document.querySelectorAll('.user-cb:checked')).map(cb => cb.value);
            return [...new Set(checked)]; // unique values
        }

        function toggleAllSelection(check) {
            userCbs.forEach(cb => {
                // Only select visible ones if searching
                const item = cb.closest('tr') || cb.closest('.user-card');
                if (item && item.style.display !== 'none') {
                    cb.checked = check;
                }
            });
            if (tableSelectAllCb) tableSelectAllCb.checked = check;
        }

        selectAllBtn.addEventListener('click', () => {
            // Check if all visible are selected
            const visibleCbs = Array.from(userCbs).filter(cb => {
                const item = cb.closest('tr') || cb.closest('.user-card');
                return item && item.style.display !== 'none';
            });
            const allChecked = visibleCbs.every(cb => cb.checked);
            toggleAllSelection(!allChecked);
        });

        if (tableSelectAllCb) {
            tableSelectAllCb.addEventListener('change', (e) => {
                toggleAllSelection(e.target.checked);
            });
        }

        // Sync grid and table checkboxes for same user id
        userCbs.forEach(cb => {
            cb.addEventListener('change', (e) => {
                const id = e.target.value;
                const state = e.target.checked;
                document.querySelectorAll(`.user-cb[value="${id}"]`).forEach(el => el.checked = state);
            });
        });

        /* ── WhatsApp Bulk Send Modal ──────────────────── */
        const waBulkBtn = document.getElementById('waBulkBtn');
        const waOverlay = document.getElementById('waOverlay');
        const closeWaModal = document.getElementById('closeWaModal');
        const waForm = document.getElementById('waForm');
        const waSubmitBtn = document.getElementById('waSubmitBtn');
        const selectedCountBadge = document.getElementById('selectedCountBadge');

        waBulkBtn.addEventListener('click', () => {
            const selectedIds = getSelectedUsers();
            if (selectedIds.length === 0) {
                alert("Please select at least one user to send WhatsApp messages.");
                return;
            }
            selectedCountBadge.innerText = `${selectedIds.length} user${selectedIds.length > 1 ? 's' : ''} selected`;
            waOverlay.classList.add('active');
        });

        closeWaModal.addEventListener('click', () => {
            waOverlay.classList.remove('active');
        });

        function formatTime12(time24) {
            if (!time24) return "";
            const [h, m] = time24.split(":");
            let hh = parseInt(h, 10);
            const ampm = hh >= 12 ? 'PM' : 'AM';
            hh = hh % 12 || 12;
            return `${hh}:${m} ${ampm}`;
        }

        function formatDateEn(dateStr) {
            if (!dateStr) return "";
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        waForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const selectedIds = getSelectedUsers();
            if (selectedIds.length === 0) return;

            waSubmitBtn.disabled = true;
            waSubmitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending...';

            const formData = new FormData(waForm);

            // Format dates/times nicely before sending
            formData.set('meeting_date', formatDateEn(formData.get('meeting_date')));
            formData.set('meeting_time', formatTime12(formData.get('meeting_time')));
            formData.set('reach_by', formatTime12(formData.get('reach_by')));
            formData.set('na_from', formatTime12(formData.get('na_from')));
            formData.set('na_to', formatTime12(formData.get('na_to')));

            selectedIds.forEach(id => formData.append('user_ids[]', id));

            try {
                const res = await fetch('send_bulk_whatsapp.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert(`✅ Success: ${data.message}\nSent: ${data.sentCount} | Failed: ${data.failedCount}`);
                    waOverlay.classList.remove('active');
                    waForm.reset();
                    toggleAllSelection(false);
                } else {
                    alert(`❌ Error: ${data.message || 'Failed to send'}`);
                }
            } catch (err) {
                console.error(err);
                alert('An emergency error occurred while communicating with the server.');
            } finally {
                waSubmitBtn.disabled = false;
                waSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send to Selected Users';
            }
        });
    </script>
</body>

</html>