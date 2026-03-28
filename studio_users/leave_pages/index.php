<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Leave Management - Apply and track your leave requests">
    <title>Leave Application | CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Sidebar requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../';
    </script>
    <script src="../components/sidebar-loader.js" defer></script>

    <!-- Base styles: always loaded -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Desktop layout: screens wider than 768px -->
    <link rel="stylesheet" href="css/desktop.css" media="(min-width: 769px)">
    <!-- Mobile layout: screens 768px and below -->
    <link rel="stylesheet" href="css/mobile.css"  media="(max-width: 768px)">
    
    <style>
        /* Layout adjustments for integrated sidebar */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background: #f5f6fa;
        }
        .main-content {
            flex: 1;
            min-width: 0;
            padding: 0;
        }
        #content-wrapper { display: block; }
        
        .leave-dashboard {
            width: 100%;
        }

        /* Mobile Hamburger Button */
        .mobile-hamburger-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100; /* Above sidebar overlay */
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .mobile-hamburger-btn:hover { background: #f9fafb; }
        .mobile-hamburger-btn:active { transform: scale(0.95); }

        @media (max-width: 768px) {
            .mobile-hamburger-btn {
                display: flex;
            }
            .leave-dashboard {
                padding-top: 60px; /* Space for the floating button */
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar injected here -->
        <div id="sidebar-mount"></div>
        
        <!-- Mobile Hamburger Button -->
        <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
            <i data-lucide="menu" style="width:20px;height:20px;"></i>
        </button>

        <main class="main-content">
            <div id="content-wrapper">
                <div class="leave-dashboard">

                <!-- Page Heading -->
                <div class="page-heading">
                    <h1>Leave Application</h1>
                    <p>Apply for leave, track your balance and review your history</p>
                </div>

                <!-- ─── Stats Row ────────────────────────────── -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon orange">⏱️</div>
                        <div class="stat-body">
                            <div class="stat-label">Short Leaves</div>
                            <div class="stat-value" id="stat-short">2</div>
                            <div class="stat-sub">available to use</div>
                            <span class="stat-tag orange">Per month limit</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">⚖️</div>
                        <div class="stat-body">
                            <div class="stat-label">Compensation Leaves</div>
                            <div class="stat-value" id="stat-comp">0</div>
                            <div class="stat-sub">days available</div>
                            <span class="stat-tag purple">Earned time off</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">🌴</div>
                        <div class="stat-body">
                            <div class="stat-label">Casual Leaves</div>
                            <div class="stat-value" id="stat-casual">12</div>
                            <div class="stat-sub">days remaining</div>
                            <span class="stat-tag green">0/2 used this month</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">📁</div>
                        <div class="stat-body">
                            <div class="stat-label">Other Leaves</div>
                            <div class="stat-value" id="stat-other">--</div>
                            <div class="stat-sub">days total</div>
                            <span class="stat-tag blue">Multiple types</span>
                        </div>
                    </div>
                </div>

                <!-- ─── Form + Balance ───────────────────────── -->
                <div class="main-grid">

                    <!-- Leave Form (New Leave Request - Redesigned) -->
                    <div class="nlr-card">
                        <!-- Dark header bar -->
                        <div class="nlr-header">
                            <div class="nlr-header-left">
                                <div class="nlr-header-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <div>
                                    <h2 class="nlr-title">New Leave Request</h2>
                                    <p class="nlr-subtitle">Fill details below and submit for manager approval</p>
                                </div>
                            </div>
                            <span class="nlr-status-pill">Draft</span>
                        </div>

                        <form class="nlr-form" id="application-form">
                            <!-- Row 1: Approver -->
                            <div class="nlr-field-row">
                                <div class="nlr-field nlr-field--full">
                                    <label class="nlr-label" for="mrf_approver">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        Approver <span class="nlr-required">*</span>
                                    </label>
                                    <div class="nlr-select-wrap">
                                        <select id="mrf_approver" name="approver" class="nlr-select" required>
                                            <option value="">Loading approvers...</option>
                                        </select>
                                        <svg class="nlr-select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 2: Date range + Generate -->
                            <div class="nlr-field-row nlr-date-row">
                                <div class="nlr-field">
                                    <label class="nlr-label" for="mrf_from_date">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        From Date <span class="nlr-required">*</span>
                                    </label>
                                    <input type="date" id="mrf_from_date" name="from_date" class="nlr-input" required>
                                </div>
                                <div class="nlr-field">
                                    <label class="nlr-label" for="mrf_to_date">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        To Date <span class="nlr-required">*</span>
                                    </label>
                                    <input type="date" id="mrf_to_date" name="to_date" class="nlr-input" required>
                                </div>
                                <div class="nlr-field nlr-field--btn">
                                    <label class="nlr-label nlr-label--ghost">&nbsp;</label>
                                    <button type="button" class="nlr-generate-btn js-generate-dates">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                                        Generate
                                    </button>
                                </div>
                            </div>

                            <!-- Row 3: Reason -->
                            <div class="nlr-field-row">
                                <div class="nlr-field nlr-field--full">
                                    <label class="nlr-label" for="reason">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        Reason for Leave <span class="nlr-required">*</span>
                                    </label>
                                    <textarea id="reason" name="reason" class="nlr-textarea" rows="3" placeholder="Briefly describe the reason for your leave request..." required></textarea>
                                </div>
                            </div>

                            <!-- Generated dates table -->
                            <div class="nlr-table-section">
                                <div class="nlr-table-header">
                                    <span class="nlr-table-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                        Leave Breakdown
                                    </span>
                                    <label class="nlr-select-all-wrap">
                                        <input type="checkbox" id="select-all-dates" class="nlr-check">
                                        <span>Select All</span>
                                    </label>
                                </div>
                                <div class="nlr-table-wrap">
                                    <table class="nlr-table">
                                        <thead>
                                            <tr>
                                                <th style="width:44px; text-align:center;"></th>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th>Leave Type</th>
                                                <th>Day Type</th>
                                            </tr>
                                        </thead>
                                        <tbody id="generated-dates-body">
                                            <tr>
                                                <td colspan="5" class="nlr-empty-state">
                                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                                    <p>Pick a date range and click <strong>Generate</strong> to populate leave days</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Sick Leave Upload (Hidden by default) -->
                            <div id="sick-leave-upload-section" style="display: none; margin: 20px 0;">
                                <div class="nlr-field nlr-field--full">
                                    <label class="nlr-label">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        Medical Documents (Mandatory for Sick Leave) <span class="nlr-required">*</span>
                                    </label>
                                    <div class="file-upload-wrapper" style="border: 2px dashed #e5e7eb; padding: 24px; border-radius: 12px; text-align: center; background: #fafafa; transition: all 0.2s; cursor: pointer; border-color: #d1d5db;" onmouseover="this.style.borderColor='#9ca3af'; this.style.background='#f3f4f6';" onmouseout="this.style.borderColor='#d1d5db'; this.style.background='#fafafa';">
                                        <input type="file" id="sick-leave-files" name="sick_leave_files[]" multiple accept=".pdf,.png,.jpg,.jpeg" style="display: none;">
                                        <label for="sick-leave-files" style="cursor: pointer; display: block;">
                                            <div style="font-size: 2rem; margin-bottom: 12px;">📄</div>
                                            <div style="font-weight: 600; color: #374151; font-size: 0.95rem;">Click to upload or drag files here</div>
                                            <div style="font-size: 0.78rem; color: #6b7280; margin-top: 6px;">Supports PDF, PNG, JPG, JPEG (Multiple files allowed)</div>
                                        </label>
                                        <div id="file-list-preview" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;"></div>
                                    </div>
                                    <style>
                                        .file-chip {
                                            background: white; border: 1px solid #e5e7eb; padding: 8px 10px; border-radius: 8px; font-size: 0.75rem; 
                                            display: flex; align-items: center; justify-content: space-between; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                                            animation: slideIn 0.2s ease;
                                        }
                                        .file-chip span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; color: #4b5563; font-weight: 500; }
                                        .file-chip .remove-file { color: #9ca3af; cursor: pointer; transition: color 0.1s; }
                                        .file-chip .remove-file:hover { color: #ef4444; }
                                        @keyframes slideIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
                                    </style>
                                </div>
                            </div>

                            <!-- Duration pill -->
                            <div class="nlr-duration-bar">
                                <div class="nlr-duration-left">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span>Calculated Duration</span>
                                </div>
                                <div class="nlr-duration-right">
                                    <span class="duration-badge nlr-duration-badge">0m</span>
                                    <span class="nlr-duration-note">incl. start &amp; end dates</span>
                                </div>
                            </div>

                            <!-- Form actions -->
                            <div class="nlr-actions">
                                <button type="button" class="nlr-btn-cancel" id="cancel-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Cancel
                                </button>
                                <button type="submit" class="nlr-btn-submit" id="submit-btn">
                                    Submit Application
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>


                    <!-- Right Column Wrapper -->
                    <div class="right-column-grid" style="display: flex; flex-direction: column; gap: 16px;">
                        
                        <!-- Leave Balance -->
                        <div class="card" style="flex-shrink: 0;">
                            <div class="balance-head">
                                <h3>Leave Bank</h3>
                                <div class="balance-filters">
                                    <div class="custom-dropdown" id="bank-month-dropdown">
                                        <button class="dropdown-trigger" type="button" style="padding: 4px 10px; font-size: 0.75rem;">
                                            <span class="selected-value">March</span>
                                            <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                        <div class="dropdown-menu">
                                        <div class="dropdown-item" data-value="January">January</div>
                                        <div class="dropdown-item" data-value="February">February</div>
                                        <div class="dropdown-item active" data-value="March">March</div>
                                        <div class="dropdown-item" data-value="April">April</div>
                                        <div class="dropdown-item" data-value="May">May</div>
                                        <div class="dropdown-item" data-value="June">June</div>
                                        <div class="dropdown-item" data-value="July">July</div>
                                        <div class="dropdown-item" data-value="August">August</div>
                                        <div class="dropdown-item" data-value="September">September</div>
                                        <div class="dropdown-item" data-value="October">October</div>
                                        <div class="dropdown-item" data-value="November">November</div>
                                        <div class="dropdown-item" data-value="December">December</div>
                                        </div>
                                    </div>

                                    <div class="custom-dropdown" id="bank-year-dropdown" style="min-width: 90px;">
                                        <button class="dropdown-trigger" type="button" style="padding: 4px 10px; font-size: 0.75rem;">
                                            <span class="selected-value">2026</span>
                                            <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item" data-value="2024">2024</div>
                                            <div class="dropdown-item" data-value="2025">2025</div>
                                            <div class="dropdown-item active" data-value="2026">2026</div>
                                            <div class="dropdown-item" data-value="2027">2027</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <ul class="balance-list">
                                <li>
                                    <div class="bl-info"><span class="bl-icon green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg></span><span class="bl-name">Casual Leave</span></div>
                                    <div class="bl-val">12 days <span class="mini">0/2 used this month</span></div>
                                </li>
                                <li>
                                    <div class="bl-info"><span class="bl-icon orange"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg></span><span class="bl-name">Compensation </span></div>
                                    <div class="bl-val">0 days</div>
                                </li>
                        
                                <li>
                                    <div class="bl-info"><span class="bl-icon blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></span><span class="bl-name">Maternity</span></div>
                                    <div class="bl-val">60 days</div>
                                </li>
                                <li>
                                    <div class="bl-info"><span class="bl-icon blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span><span class="bl-name">Paternity</span></div>
                                    <div class="bl-val">7 days</div>
                                </li>
                                <li>
                                    <div class="bl-info"><span class="bl-icon orange"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="2" x2="14" y2="2"></line><line x1="12" y1="14" x2="15" y2="11"></line><circle cx="12" cy="14" r="8"></circle></svg></span><span class="bl-name">Short Leave</span></div>
                                    <div class="bl-val">
                                        2   
                                        <div class="mini-prog"><div class="mini-prog-fill" style="width:25%"></div></div>
                                    </div>
                                </li>
                                <li>
                                    <div class="bl-info"><span class="bl-icon amber"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg></span><span class="bl-name">Sick</span></div>
                                    <div class="bl-val">6 days</div>
                                </li>
                                
                            </ul>

                            <div class="balance-note">
                                Balances include approved &amp; pending requests. Rejected leaves don't reduce balance.
                                Casual leave capped at 2/month.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── 2026 Holidays (Full Width) ─────────────────────────── -->
                <div class="card" style="margin-bottom: 16px;">
                    <div class="balance-head" style="margin-bottom: 12px;">
                        <h3 style="font-size: 0.9rem; color: var(--text-secondary);">2026 Holidays Overview</h3>
                    </div>
                    <style>
                        .holidays-marquee {
                            display: flex;
                            gap: 12px;
                            overflow-x: auto;
                            padding-bottom: 6px;
                            -webkit-overflow-scrolling: touch;
                        }
                        .holidays-marquee::-webkit-scrollbar { height: 4px; }
                        .holidays-marquee::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
                        .holiday-pill {
                            display: flex;
                            align-items: center;
                            gap: 6px;
                            padding: 6px 14px;
                            background: var(--bg-body);
                            border: 1px solid var(--border);
                            border-radius: 100px;
                            white-space: nowrap;
                            font-size: 0.82rem;
                            font-weight: 500;
                            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
                        }
                        .holiday-pill .h-icon {
                            font-size: 1rem;
                        }
                        .holiday-pill .h-date {
                            color: var(--text-secondary);
                            font-size: 0.75rem;
                            margin-left: 2px;
                        }
                    </style>
                    <div class="holidays-marquee">
                        <div class="holiday-pill"><span class="h-icon">🎉</span> <span class="h-name">New Year</span> <span class="h-date">01 Jan</span></div>
                        <div class="holiday-pill"><span class="h-icon">🇮🇳</span> <span class="h-name">Republic Day</span> <span class="h-date">26 Jan</span></div>
                        <div class="holiday-pill"><span class="h-icon">🕉️</span> <span class="h-name">Maha Shivaratri</span> <span class="h-date">15 Feb</span></div>
                        <div class="holiday-pill"><span class="h-icon">🎨</span> <span class="h-name">Holi</span> <span class="h-date">04 Mar</span></div>
                        <div class="holiday-pill"><span class="h-icon">🏹</span> <span class="h-name">Ram Navmi</span> <span class="h-date">26 Mar</span></div>
                        <div class="holiday-pill"><span class="h-icon">🇮🇳</span> <span class="h-name">Independence Day</span> <span class="h-date">15 Aug</span></div>
                        <div class="holiday-pill"><span class="h-icon">🎗️</span> <span class="h-name">Raksha Bandhan</span> <span class="h-date">28 Aug</span></div>
                        <div class="holiday-pill"><span class="h-icon">🦚</span> <span class="h-name">Krishna Janmashtami</span> <span class="h-date">04 Sep</span></div>
                        <div class="holiday-pill"><span class="h-icon">🕊️</span> <span class="h-name">Gandhi Jayanti</span> <span class="h-date">02 Oct</span></div>
                        <div class="holiday-pill"><span class="h-icon">🏹</span> <span class="h-name">Dussehra</span> <span class="h-date">20 Oct</span></div>
                        <div class="holiday-pill"><span class="h-icon">🪔</span> <span class="h-name">Diwali</span> <span class="h-date">08 Nov</span></div>
                        <div class="holiday-pill"><span class="h-icon">⛰️</span> <span class="h-name">Govardhan Puja</span> <span class="h-date">09 Nov</span></div>
                        <div class="holiday-pill"><span class="h-icon">🌺</span> <span class="h-name">Bhai Dooj</span> <span class="h-date">11 Nov</span></div>
                    </div>
                </div>

                <!-- ─── Leave History ─────────────────────────── -->
                <div class="card history-card">
                    <div class="history-head">
                        <div class="section-title">
                            <div class="accent-bar"></div>
                            <h3>Leave History</h3>
                        </div>
                        <div class="history-filters">
                            <div class="custom-dropdown" id="month-dropdown">
                                <button class="dropdown-trigger" type="button">
                                    <span class="selected-value">March</span>
                                    <svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="dropdown-menu">
                                    <div class="dropdown-item" data-value="January">January</div>
                                    <div class="dropdown-item" data-value="February">February</div>
                                    <div class="dropdown-item active" data-value="March">March</div>
                                    <div class="dropdown-item" data-value="April">April</div>
                                    <div class="dropdown-item" data-value="May">May</div>
                                    <div class="dropdown-item" data-value="June">June</div>
                                    <div class="dropdown-item" data-value="July">July</div>
                                    <div class="dropdown-item" data-value="August">August</div>
                                    <div class="dropdown-item" data-value="September">September</div>
                                    <div class="dropdown-item" data-value="October">October</div>
                                    <div class="dropdown-item" data-value="November">November</div>
                                    <div class="dropdown-item" data-value="December">December</div>
                                </div>
                            </div>
                            <div class="custom-dropdown" id="year-dropdown">
                                <button class="dropdown-trigger" type="button">
                                    <span class="selected-value">2026</span>
                                    <svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="dropdown-menu">
                                    <div class="dropdown-item" data-value="2024">2024</div>
                                    <div class="dropdown-item" data-value="2025">2025</div>
                                    <div class="dropdown-item active" data-value="2026">2026</div>
                                    <div class="dropdown-item" data-value="2027">2027</div>
                                </div>
                            </div>
                            <button class="btn btn-ghost" id="load-history-btn">Apply</button>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Manager Status</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body">
                                <tr>
                                    <td colspan="7" class="loading-state">Loading history...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- View Modal -->
    <div id="view-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>View Leave Application</h3>
                <button type="button" class="close-btn" onclick="closeModal('view-modal')">&times;</button>
            </div>
            <div class="modal-body" id="view-modal-content">
                <!-- Data injected via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('view-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Edit Leave Application</h3>
                <button type="button" class="close-btn" onclick="closeModal('edit-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-leave-form">
                    <div class="form-group">
                        <label for="edit_reason">Reason for Leave <span class="required">*</span></label>
                        <textarea id="edit_reason" rows="3" required></textarea>
                    </div>
                    <div class="date-row">
                        <div class="form-group date-group">
                            <label for="edit_from_date">From Date <span class="required">*</span></label>
                            <input type="date" id="edit_from_date" required>
                        </div>
                        <div class="form-group date-group">
                            <label for="edit_to_date">To Date <span class="required">*</span></label>
                            <input type="date" id="edit_to_date" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEditLeave()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 style="color: var(--red);">Delete Leave Application</h3>
                <button type="button" class="close-btn" onclick="closeModal('delete-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this leave request? This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal('delete-modal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmDeleteLeave()">Delete Request</button>
            </div>
        </div>
    </div>

    <!-- ─── Immersive Loader ────────────────────────── -->
    <div class="nlr-loader-overlay" id="submit-loader">
        <div class="nlr-spinner"></div>
        <div class="nlr-loader-text" id="loader-quote">Connecting to database...</div>
    </div>

    <!-- ─── Result Modal ────────────────────────────── -->
    <div class="nlr-modal-backdrop" id="result-modal">
        <div class="nlr-result-modal">
            <div class="nlr-modal-icon" id="result-icon">
                <i data-lucide="check" style="width:32px; height:32px;"></i>
            </div>
            <h3 class="nlr-modal-title" id="result-title">Success!</h3>
            <p class="nlr-modal-desc" id="result-desc">Your message here</p>
            <button class="nlr-modal-btn" onclick="closeResultModal()">Okay, Got it</button>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
