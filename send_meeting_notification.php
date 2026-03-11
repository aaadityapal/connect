<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp/WhatsAppService.php';

$response = null;
$uploadedPdfUrl = '';
$uploadedPdfName = '';
$sentCount = 0;
$failedCount = 0;
$logs = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $greeting_name = trim($_POST['greeting_name'] ?? '');
    $meeting_date  = trim($_POST['meeting_date'] ?? '');
    $meeting_time  = trim($_POST['meeting_time'] ?? '');
    $meeting_day   = trim($_POST['meeting_day'] ?? '');
    $report_time   = trim($_POST['report_time'] ?? '');
    $from_time     = trim($_POST['from_time'] ?? '');
    $to_time       = trim($_POST['to_time'] ?? '');
    $selected_users = $_POST['selected_users'] ?? [];

    // Validate required fields
    $errors = [];
    if (empty($meeting_date))  $errors[] = 'Meeting date is required.';
    if (empty($meeting_time))  $errors[] = 'Meeting time is required.';
    if (empty($meeting_day))   $errors[] = 'Meeting day is required.';
    if (empty($report_time))   $errors[] = 'Report time (AM) is required.';
    if (empty($from_time))     $errors[] = 'From time is required.';
    if (empty($to_time))       $errors[] = 'To time is required.';
    if (empty($selected_users)) $errors[] = 'Please select at least one recipient.';

    // Handle PDF upload
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/meeting_pdfs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $originalName = basename($_FILES['pdf_file']['name']);
        $safeName = 'meeting_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
        $uploadPath = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPath)) {
            // Build public URL — adjust domain if needed
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $uploadedPdfUrl  = $protocol . '://' . $host . '/connect/uploads/meeting_pdfs/' . $safeName;
            $uploadedPdfName = $safeName;
        } else {
            $errors[] = 'Failed to upload PDF file.';
        }
    }

    if (!empty($errors)) {
        $response = ['type' => 'error', 'message' => implode('<br>', $errors)];
    } else {
        // Fetch selected user phone numbers
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id IN ($placeholders) AND phone IS NOT NULL AND phone != ''");
        $stmt->execute($selected_users);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $waService = new WhatsAppService();

        foreach ($recipients as $user) {
            $phone    = $user['phone'];
            $name     = $greeting_name ?: $user['username'];

            // Template: meeting_schedule_notification
            // {{1}} Name
            // {{2}} Date
            // {{3}} Time
            // {{4}} Day
            // {{5}} Report Time (AM)
            // {{6}} From Time (AM)
            // {{7}} To Time (PM)
            $params = [
                $name,         // {{1}} Hello {{1}}
                $meeting_date, // {{2}} Date
                $meeting_time, // {{3}} Time
                $meeting_day,  // {{4}} Day
                $report_time,  // {{5}} Report by X AM
                $from_time,    // {{6}} From X AM
                $to_time,      // {{7}} To X PM
            ];

            if (!empty($uploadedPdfUrl)) {
                $result = $waService->sendTemplateMessageWithDocument(
                    $phone,
                    'meeting_schedule_notification',
                    'en_US',
                    $params,
                    $uploadedPdfUrl,
                    $uploadedPdfName
                );
            } else {
                $result = $waService->sendTemplateMessage(
                    $phone,
                    'meeting_schedule_notification',
                    'en_US',
                    $params
                );
            }

            if ($result['success']) {
                $sentCount++;
                $logs[] = ['status' => 'success', 'user' => $user['username'], 'phone' => $phone];
            } else {
                $failedCount++;
                $logs[] = ['status' => 'failed', 'user' => $user['username'], 'phone' => $phone, 'error' => $result['response'] ?? 'Unknown error'];
            }
        }

        if ($sentCount > 0) {
            $response = [
                'type'    => 'success',
                'message' => "✅ Notifications sent successfully to <strong>$sentCount</strong> recipient(s)." .
                             ($failedCount > 0 ? " <strong>$failedCount</strong> failed." : '')
            ];
        } else {
            $response = ['type' => 'error', 'message' => "❌ All $failedCount message(s) failed to send. Check WhatsApp API credentials."];
        }
    }
}

// Fetch all users with phone numbers for the recipient list
$usersStmt = $pdo->prepare("SELECT id, username, phone, role, department FROM users WHERE phone IS NOT NULL AND phone != '' AND status = 'active' ORDER BY username ASC");
$usersStmt->execute();
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Schedule Notification — ArchitectsHive</title>
    <meta name="description" content="Send 4th Saturday meeting schedule notifications to employees via WhatsApp with PDF attachment.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0d0f1a;
            --surface:     #141626;
            --surface2:    #1c1f35;
            --surface3:    #232742;
            --accent:      #25d366;
            --accent-dark: #1aab50;
            --accent-glow: rgba(37,211,102,.18);
            --brand:       #7c5cfc;
            --brand-glow:  rgba(124,92,252,.18);
            --danger:      #ff4c6a;
            --warn:        #f5a623;
            --text:        #e8eaf6;
            --text-sub:    #8b90b8;
            --border:      rgba(255,255,255,.07);
            --radius:      14px;
            --shadow:      0 8px 32px rgba(0,0,0,.45);
            --transition:  .23s cubic-bezier(.4,0,.2,1);
        }

        html { font-size: 16px; scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(124,92,252,.22) 0%, transparent 70%),
                radial-gradient(ellipse 60% 40% at 80% 110%, rgba(37,211,102,.1) 0%, transparent 60%);
        }

        /* ── TOP-BAR ────────────────────────────────────────────── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            height: 64px;
            background: rgba(13,15,26,.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--brand), #a987ff);
            border-radius: 10px;
            display: grid; place-items: center;
            font-size: 18px; color: #fff;
            box-shadow: 0 4px 18px rgba(124,92,252,.4);
        }
        .brand-name {
            font-size: 17px; font-weight: 700;
            background: linear-gradient(90deg, #fff, #c5b8ff);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .back-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-sub);
            text-decoration: none;
            font-size: 13px; font-weight: 500;
            transition: var(--transition);
        }
        .back-btn:hover { background: var(--surface3); color: var(--text); }

        /* ── PAGE WRAPPER ───────────────────────────────────────── */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px 80px;
        }

        /* ── PAGE HEADER ────────────────────────────────────────── */
        .page-header {
            text-align: center;
            margin-bottom: 44px;
        }
        .page-header .badge {
            display: inline-flex;
            align-items: center; gap: 8px;
            padding: 6px 16px;
            background: var(--accent-glow);
            border: 1px solid rgba(37,211,102,.25);
            border-radius: 100px;
            font-size: 12px; font-weight: 600;
            color: var(--accent);
            text-transform: uppercase; letter-spacing: .08em;
            margin-bottom: 18px;
        }
        .page-header h1 {
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, #fff 30%, #c5b8ff);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }
        .page-header p {
            color: var(--text-sub);
            font-size: 15px; max-width: 540px; margin: 0 auto;
        }

        /* ── GRID LAYOUT ────────────────────────────────────────── */
        .grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 28px;
            align-items: start;
        }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

        /* ── CARD ───────────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .card-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 700;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .card-title i {
            width: 32px; height: 32px;
            display: grid; place-items: center;
            border-radius: 8px;
            font-size: 14px;
        }
        .icon-green  { background: var(--accent-glow); color: var(--accent); }
        .icon-purple { background: var(--brand-glow);  color: var(--brand); }
        .icon-orange { background: rgba(245,166,35,.15); color: var(--warn); }
        .icon-red    { background: rgba(255,76,106,.15); color: var(--danger); }

        /* ── FORM ───────────────────────────────────────────────── */
        .form-group { margin-bottom: 18px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }

        label {
            display: block;
            font-size: 12px; font-weight: 600;
            color: var(--text-sub);
            text-transform: uppercase; letter-spacing: .06em;
            margin-bottom: 7px;
        }
        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-sub); font-size: 14px;
            pointer-events: none;
        }
        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            padding: 11px 14px 11px 40px;
            transition: var(--transition);
            outline: none;
            -webkit-appearance: none;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--brand);
            background: var(--surface3);
            box-shadow: 0 0 0 3px var(--brand-glow);
        }
        input.no-icon, select.no-icon, textarea.no-icon { padding-left: 14px; }

        /* Date / Time Native Icon color */
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(1) opacity(.5);
            cursor: pointer;
        }

        /* ── TEMPLATE PREVIEW ───────────────────────────────────── */
        .preview-bubble {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 13.5px;
            line-height: 1.75;
            color: var(--text);
            white-space: pre-wrap;
            word-break: break-word;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            min-height: 220px;
        }
        .preview-bubble .highlight {
            background: rgba(37,211,102,.18);
            color: #4fffb0;
            border-radius: 4px;
            padding: 1px 5px;
            font-weight: 600;
            font-size: 12.5px;
        }
        .char-counter {
            text-align: right;
            font-size: 11px;
            color: var(--text-sub);
            margin-top: 6px;
        }

        /* ── PDF DROP ZONE ──────────────────────────────────────── */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .drop-zone:hover, .drop-zone.drag-over {
            border-color: var(--accent);
            background: var(--accent-glow);
        }
        .drop-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }
        .drop-zone-icon {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
        }
        .drop-zone p { color: var(--text-sub); font-size: 13px; }
        .drop-zone strong { color: var(--text); }
        .file-selected {
            display: none;
            align-items: center; gap: 10px;
            margin-top: 14px;
            background: var(--surface3);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
        }
        .file-selected i { color: var(--danger); font-size: 22px; }
        .file-selected .fname { font-weight: 600; flex: 1; text-align: left; }
        .file-selected .fsize { color: var(--text-sub); font-size: 12px; }
        .remove-file { cursor: pointer; color: var(--danger); background: none; border: none; font-size: 16px; }

        /* ── RECIPIENTS TABLE ───────────────────────────────────── */
        .recipient-search {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            padding: 11px 14px 11px 42px;
            margin-bottom: 14px;
            outline: none;
            transition: var(--transition);
        }
        .recipient-search:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
        .recipient-search-wrap { position: relative; }
        .recipient-search-wrap i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-sub); font-size: 14px;
            pointer-events: none;
        }

        .select-actions {
            display: flex; gap: 8px;
            margin-bottom: 12px;
        }
        .select-actions button {
            flex: 1;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface2);
            color: var(--text-sub);
            font-size: 12px; font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        .select-actions button:hover { background: var(--surface3); color: var(--text); }
        .recipients-count {
            font-size: 12px; font-weight: 600;
            color: var(--accent);
            margin-left: auto;
        }

        .user-list {
            max-height: 340px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface2);
        }
        .user-list::-webkit-scrollbar { width: 5px; }
        .user-list::-webkit-scrollbar-track { background: transparent; }
        .user-list::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 4px; }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
        }
        .user-item:last-child { border-bottom: none; }
        .user-item:hover { background: var(--surface3); }
        .user-item.selected { background: rgba(37,211,102,.07); }
        .user-cb { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0; }
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), #a987ff);
            display: grid; place-items: center;
            font-size: 13px; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }
        .user-info { flex: 1; min-width: 0; }
        .user-name { font-size: 13.5px; font-weight: 600; }
        .user-meta { font-size: 11.5px; color: var(--text-sub); }
        .user-phone {
            font-size: 12px; color: var(--text-sub);
            display: flex; align-items: center; gap: 4px;
        }

        /* ── SUBMIT BUTTON ──────────────────────────────────────── */
        .btn-send {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #1aab50);
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 15px; font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-top: 24px;
            box-shadow: 0 6px 24px rgba(37,211,102,.3);
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(37,211,102,.45);
        }
        .btn-send:active { transform: translateY(0); }
        .btn-send:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        /* ── ALERT BANNERS ──────────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 28px;
            font-size: 14px;
            animation: slideDown .35s ease;
        }
        @keyframes slideDown { from { opacity:0; transform: translateY(-14px); } to { opacity:1; transform: none; } }
        .alert-success { background: rgba(37,211,102,.1); border: 1px solid rgba(37,211,102,.3); color: #4fffb0; }
        .alert-error   { background: rgba(255,76,106,.1);  border: 1px solid rgba(255,76,106,.3);  color: #ff8fa3; }
        .alert i { margin-top: 2px; font-size: 18px; flex-shrink: 0; }

        /* ── LOGS TABLE ─────────────────────────────────────────── */
        .logs-card { margin-top: 28px; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .log-table th {
            text-align: left; padding: 10px 14px;
            background: var(--surface2);
            color: var(--text-sub);
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .07em;
            border-bottom: 1px solid var(--border);
        }
        .log-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .log-table tr:last-child td { border-bottom: none; }
        .log-table tr:hover td { background: rgba(255,255,255,.02); }
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 11.5px; font-weight: 600;
        }
        .pill-ok  { background: rgba(37,211,102,.15); color: var(--accent); }
        .pill-err { background: rgba(255,76,106,.15);  color: var(--danger); }

        /* ── STICKY PREVIEW SIDEBAR ─────────────────────────────── */
        .sticky-sidebar { position: sticky; top: 88px; }

        /* ── DIVIDER ────────────────────────────────────────────── */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 20px 0;
        }

        /* ── RESPONSIVE FIXES ───────────────────────────────────── */
        @media (max-width: 600px) {
            .topbar { padding: 0 16px; }
            .page-wrapper { padding: 24px 16px 60px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- TOP-BAR -->
<header class="topbar">
    <a href="#" class="topbar-brand">
        <div class="brand-icon"><i class="fab fa-whatsapp"></i></div>
        <span class="brand-name">ArchitectsHive</span>
    </a>
    <a href="javascript:history.back()" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</header>

<!-- MAIN -->
<main class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="badge"><i class="fab fa-whatsapp"></i> WhatsApp Notification</div>
        <h1>4th Saturday Meeting<br>Schedule Notification</h1>
        <p>Fill in the meeting details, attach the schedule PDF, and blast the invite to selected employees via WhatsApp.</p>
    </div>

    <!-- ALERTS -->
    <?php if ($response): ?>
        <div class="alert alert-<?= $response['type'] === 'success' ? 'success' : 'error' ?>">
            <i class="fas fa-<?= $response['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $response['message'] ?></div>
        </div>
    <?php endif; ?>

    <form id="notifForm" method="POST" enctype="multipart/form-data">
        <div class="grid">

            <!-- ── LEFT COLUMN ──────────────────────────────────── -->
            <div>

                <!-- MEETING DETAILS CARD -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-title">
                        <span class="icon-purple"><i class="fas fa-calendar-alt"></i></span>
                        Meeting Details
                    </div>

                    <div class="form-group">
                        <label>Greeting Name <span style="color:var(--text-sub);font-weight:400;text-transform:none">(optional — defaults to each user's name)</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="greeting_name" name="greeting_name"
                                   placeholder="e.g. Team"
                                   value="<?= htmlspecialchars($_POST['greeting_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📅 Meeting Date</label>
                            <div class="input-wrap">
                                <i class="fas fa-calendar"></i>
                                <input type="date" id="meeting_date" name="meeting_date" required
                                       value="<?= htmlspecialchars($_POST['meeting_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>📆 Meeting Day</label>
                            <div class="input-wrap">
                                <i class="fas fa-calendar-week"></i>
                                <input type="text" id="meeting_day" name="meeting_day" required
                                       placeholder="e.g. Saturday"
                                       value="<?= htmlspecialchars($_POST['meeting_day'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>⏰ Meeting Time</label>
                        <div class="input-wrap">
                            <i class="fas fa-clock"></i>
                            <input type="text" id="meeting_time" name="meeting_time" required
                                   placeholder="e.g. 10:00 AM"
                                   value="<?= htmlspecialchars($_POST['meeting_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div style="font-size:12px;font-weight:600;color:var(--text-sub);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px;">
                        Site Team Timing Details
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>🏢 Reach Office By (AM)</label>
                            <div class="input-wrap">
                                <i class="fas fa-building"></i>
                                <input type="text" id="report_time" name="report_time" required
                                       placeholder="e.g. 9"
                                       value="<?= htmlspecialchars($_POST['report_time'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>🕐 Non-Available From (AM)</label>
                            <div class="input-wrap">
                                <i class="fas fa-ban"></i>
                                <input type="text" id="from_time" name="from_time" required
                                       placeholder="e.g. 10"
                                       value="<?= htmlspecialchars($_POST['from_time'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>🕒 Non-Available To (PM)</label>
                        <div class="input-wrap">
                            <i class="fas fa-clock"></i>
                            <input type="text" id="to_time" name="to_time" required
                                   placeholder="e.g. 1"
                                   value="<?= htmlspecialchars($_POST['to_time'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- PDF UPLOAD CARD -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-title">
                        <span class="icon-red"><i class="fas fa-file-pdf"></i></span>
                        Attach Meeting Schedule PDF
                    </div>

                    <div class="drop-zone" id="dropZone">
                        <input type="file" name="pdf_file" id="pdfFile" accept=".pdf" onchange="handleFileSelect(this)">
                        <span class="drop-zone-icon">📎</span>
                        <strong>Click to upload or drag & drop</strong>
                        <p>PDF files only • Max 10 MB</p>
                    </div>
                    <div class="file-selected" id="fileSelected">
                        <i class="fas fa-file-pdf"></i>
                        <span class="fname" id="fileName">—</span>
                        <span class="fsize" id="fileSize">—</span>
                        <button type="button" class="remove-file" onclick="removeFile()" title="Remove file">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                    <p style="font-size:12px;color:var(--text-sub);margin-top:10px;">
                        <i class="fas fa-info-circle"></i> The PDF will be sent as a WhatsApp document header attachment.
                        If no file is uploaded, a text-only template will be sent.
                    </p>
                </div>

                <!-- RECIPIENTS CARD -->
                <div class="card">
                    <div class="card-title">
                        <span class="icon-green"><i class="fas fa-users"></i></span>
                        Select Recipients
                        <span class="recipients-count" id="recipientsCount">0 selected</span>
                    </div>

                    <div class="recipient-search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="recipient-search" id="searchInput"
                               placeholder="Search by name, role or phone…" oninput="filterUsers(this.value)">
                    </div>

                    <div class="select-actions">
                        <button type="button" onclick="selectAll()"><i class="fas fa-check-double"></i> Select All</button>
                        <button type="button" onclick="clearAll()"><i class="fas fa-times"></i> Clear All</button>
                    </div>

                    <div class="user-list" id="userList">
                        <?php foreach ($allUsers as $user): ?>
                        <label class="user-item" id="item_<?= $user['id'] ?>">
                            <input type="checkbox" class="user-cb"
                                   name="selected_users[]"
                                   value="<?= $user['id'] ?>"
                                   onchange="updateCount(this)"
                                   <?= in_array($user['id'], $_POST['selected_users'] ?? []) ? 'checked' : '' ?>>
                            <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="user-meta"><?= htmlspecialchars($user['role'] ?? '') ?><?= !empty($user['department']) ? ' · ' . htmlspecialchars($user['department']) : '' ?></div>
                            </div>
                            <div class="user-phone">
                                <i class="fas fa-phone" style="font-size:10px"></i>
                                <?= htmlspecialchars($user['phone']) ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($allUsers)): ?>
                        <div style="padding:28px;text-align:center;color:var(--text-sub);font-size:14px;">
                            <i class="fas fa-user-slash" style="font-size:32px;opacity:.4;display:block;margin-bottom:10px;"></i>
                            No users with phone numbers found.
                        </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- SEND BUTTON -->
                <button type="submit" name="send_notification" id="sendBtn" class="btn-send">
                    <i class="fab fa-whatsapp" style="font-size:20px;"></i>
                    Send WhatsApp Notifications
                </button>

            </div><!-- /left col -->

            <!-- ── RIGHT COLUMN (sticky preview) ───────────────── -->
            <div class="sticky-sidebar">

                <div class="card">
                    <div class="card-title">
                        <span class="icon-green"><i class="fas fa-eye"></i></span>
                        Message Preview
                    </div>

                    <div id="previewBubble" class="preview-bubble">Hello <span class="highlight">{{Name}}</span>, 👋

The 4th Saturday Meeting will be held as per the details below:

📅 Date: <span class="highlight">{{Date}}</span>
⏰ Time: <span class="highlight">{{Time}}</span>
📆 Day: <span class="highlight">{{Day}}</span>

Kindly check the attached PDF and ensure availability as per the schedule.

The site team is expected to reach the office by <span class="highlight">{{Report Time}}</span> AM and conduct prior management-level intimation to clients for your non-availability between <span class="highlight">{{From}}</span> AM to <span class="highlight">{{To}}</span> PM.

– HR Dept.
– ArchitectsHive</div>
                    <div class="char-counter" id="charCounter">~490 chars</div>

                    <div class="divider"></div>
                    <div style="font-size:12px;color:var(--text-sub);margin-bottom:6px;font-weight:600;">TEMPLATE NAME</div>
                    <code style="font-size:12px;background:var(--surface2);padding:6px 10px;border-radius:6px;color:#a987ff;display:block;">meeting_schedule_notification</code>

                    <div class="divider"></div>
                    <div style="font-size:12px;color:var(--text-sub);margin-bottom:10px;font-weight:600;">VARIABLES MAPPING</div>
                    <div style="font-size:12px;display:grid;gap:6px;">
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;1&#125;&#125;</span> <span style="color:#a987ff">Name / Greeting</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;2&#125;&#125;</span> <span style="color:#a987ff">Date</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;3&#125;&#125;</span> <span style="color:#a987ff">Time</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;4&#125;&#125;</span> <span style="color:#a987ff">Day</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;5&#125;&#125;</span> <span style="color:#a987ff">Reach Office By (AM)</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;6&#125;&#125;</span> <span style="color:#a987ff">From Time (AM)</span></div>
                        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-sub)">&#123;&#123;7&#125;&#125;</span> <span style="color:#a987ff">To Time (PM)</span></div>
                    </div>
                </div>

                <!-- Stats Card (shown only after send) -->
                <?php if ($response && !empty($logs)): ?>
                <div class="card" style="margin-top:24px;">
                    <div class="card-title">
                        <span class="icon-green"><i class="fas fa-chart-pie"></i></span>
                        Send Results
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div style="text-align:center;background:var(--surface2);border-radius:10px;padding:16px;">
                            <div style="font-size:32px;font-weight:800;color:var(--accent);"><?= $sentCount ?></div>
                            <div style="font-size:12px;color:var(--text-sub);">Sent</div>
                        </div>
                        <div style="text-align:center;background:var(--surface2);border-radius:10px;padding:16px;">
                            <div style="font-size:32px;font-weight:800;color:var(--danger);"><?= $failedCount ?></div>
                            <div style="font-size:12px;color:var(--text-sub);">Failed</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /right col -->
        </div><!-- /grid -->

        <!-- DELIVERY LOGS (full width, below the grid) -->
        <?php if (!empty($logs)): ?>
        <div class="card logs-card">
            <div class="card-title">
                <span class="icon-orange"><i class="fas fa-list-alt"></i></span>
                Delivery Log
            </div>
            <table class="log-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td style="font-weight:600"><?= htmlspecialchars($log['user']) ?></td>
                        <td><?= htmlspecialchars($log['phone']) ?></td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span class="status-pill pill-ok"><i class="fas fa-check-circle"></i> Sent</span>
                            <?php else: ?>
                                <span class="status-pill pill-err"><i class="fas fa-times-circle"></i> Failed</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-sub);font-size:12px;">
                            <?= htmlspecialchars($log['error'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </form>
</main>

<script>
// ── PREVIEW LIVE UPDATE ──────────────────────────────────────────
const previewFields = {
    greeting_name: '{{Name}}',
    meeting_date:  '{{Date}}',
    meeting_time:  '{{Time}}',
    meeting_day:   '{{Day}}',
    report_time:   '{{Report Time}}',
    from_time:     '{{From}}',
    to_time:       '{{To}}'
};

const templateBase =
`Hello {1}, 👋

The 4th Saturday Meeting will be held as per the details below:

📅 Date: {2}
⏰ Time: {3}
📆 Day: {4}

Kindly check the attached PDF and ensure availability as per the schedule.

The site team is expected to reach the office by {5} AM and conduct prior management-level intimation to clients for your non-availability between {6} AM to {7} PM.

– HR Dept.
– ArchitectsHive`;

function getVal(id, fallback) {
    const el = document.getElementById(id);
    return el && el.value.trim() ? el.value.trim() : fallback;
}

function updatePreview() {
    const vals = {
        1: getVal('greeting_name', '{{Name}}'),
        2: getVal('meeting_date',  '{{Date}}'),
        3: getVal('meeting_time',  '{{Time}}'),
        4: getVal('meeting_day',   '{{Day}}'),
        5: getVal('report_time',   '{{Report Time}}'),
        6: getVal('from_time',     '{{From}}'),
        7: getVal('to_time',       '{{To}}')
    };

    let html = templateBase;
    for (const [k, v] of Object.entries(vals)) {
        const isPlaceholder = v.startsWith('{{');
        const display = isPlaceholder
            ? `<span class="highlight">${escHtml(v)}</span>`
            : `<strong style="color:#4fffb0">${escHtml(v)}</strong>`;
        html = html.replace(new RegExp('\\{' + k + '\\}', 'g'), display);
    }

    document.getElementById('previewBubble').innerHTML = html;

    // Rough char count
    const plain = templateBase;
    let charCount = plain.length;
    for (const [k, v] of Object.entries(vals)) {
        charCount += v.length - 3;
    }
    document.getElementById('charCounter').textContent = '~' + Math.max(charCount, 0) + ' chars';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Attach listeners
['greeting_name','meeting_date','meeting_time','meeting_day','report_time','from_time','to_time'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
});

updatePreview();

// ── RECIPIENT SELECTION ──────────────────────────────────────────
function updateCount(cb) {
    const total = document.querySelectorAll('.user-cb:checked').length;
    document.getElementById('recipientsCount').textContent = total + ' selected';
    cb.closest('.user-item').classList.toggle('selected', cb.checked);
}

function selectAll() {
    document.querySelectorAll('#userList .user-item:not([style*="display: none"]) .user-cb').forEach(cb => {
        cb.checked = true;
        cb.closest('.user-item').classList.add('selected');
    });
    const total = document.querySelectorAll('.user-cb:checked').length;
    document.getElementById('recipientsCount').textContent = total + ' selected';
}

function clearAll() {
    document.querySelectorAll('.user-cb').forEach(cb => {
        cb.checked = false;
        cb.closest('.user-item').classList.remove('selected');
    });
    document.getElementById('recipientsCount').textContent = '0 selected';
}

function filterUsers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.user-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(q) ? '' : 'none';
    });
}

// Initialize counts on page load (for re-submitted forms)
(function() {
    const total = document.querySelectorAll('.user-cb:checked').length;
    document.getElementById('recipientsCount').textContent = total + ' selected';
    document.querySelectorAll('.user-cb:checked').forEach(cb => cb.closest('.user-item').classList.add('selected'));
})();

// ── FILE UPLOAD ──────────────────────────────────────────────────
function handleFileSelect(input) {
    if (!input.files.length) return;
    const file = input.files[0];

    if (file.type !== 'application/pdf') {
        alert('Please select a valid PDF file.');
        input.value = '';
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('File size must not exceed 10 MB.');
        input.value = '';
        return;
    }

    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('fileSelected').style.display = 'flex';
    document.getElementById('dropZone').style.background = 'var(--accent-glow)';
}

function removeFile() {
    document.getElementById('pdfFile').value = '';
    document.getElementById('fileSelected').style.display = 'none';
    document.getElementById('dropZone').style.background = '';
}

// Drag & drop highlight
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop', e => {
    dz.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('pdfFile').files = dt.files;
        handleFileSelect(document.getElementById('pdfFile'));
    }
});

// ── FORM SUBMIT GUARD ────────────────────────────────────────────
document.getElementById('notifForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.user-cb:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('Please select at least one recipient before sending.');
        return;
    }
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
});

// Auto-fill Day when Date changes
document.getElementById('meeting_date').addEventListener('change', function() {
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if (this.value) {
        const d = new Date(this.value + 'T00:00:00');
        document.getElementById('meeting_day').value = days[d.getDay()];
        updatePreview();
    }
});
</script>

</body>
</html>
