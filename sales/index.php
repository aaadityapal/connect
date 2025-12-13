<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Get user data from database
$user_id = $_SESSION['user_id'];
$username = 'User';
$user_role = 'Employee';
$profile_image = '';

try {
    $stmt = $pdo->prepare("SELECT username, role, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        $username = isset($user['username']) ? $user['username'] : 'User';
        $user_role = isset($user['role']) ? $user['role'] : 'Employee';
        $profile_image = isset($user['profile_image']) ? $user['profile_image'] : '';
    }
} catch (Exception $e) {
    // Fallback to session data if query fails
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
    error_log("Error fetching user data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard | ArchitectsHive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <!-- Icons (Feather Icons) -->
    <script src="https://unpkg.com/feather-icons"></script>
    <!-- Pass user data to JavaScript -->
    <script>
        window.currentUsername = "<?php echo htmlspecialchars($username); ?>";
        window.userRole = "<?php echo htmlspecialchars($user_role); ?>";
        window.profileImageUrl = "<?php echo htmlspecialchars($profile_image); ?>";
    </script>
    <!-- Greeting Module -->
    <script src="greeting.js"></script>
    <!-- Punch-in Modal Module -->
    <script src="punch-modal.js"></script>
</head>

<body>

    <!-- Sidebar Container -->
    <aside class="sidebar" id="sidebarContainer">
        <!-- Sidebar content will be loaded here -->
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i data-feather="menu"></i>
            </button>
            <h1 class="page-title">Dashboard Overview</h1>

            <div class="header-actions">
                <div class="search-bar">
                    <i data-feather="search" class="search-icon"></i>
                    <input type="text" placeholder="Search leads, companies...">
                </div>

                <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                    <i data-feather="sun"></i>
                </button>

                <button class="btn-icon" id="notificationBtn">
                    <i data-feather="bell"></i>
                    <span class="notification-badge"></span>
                </button>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <span>Notifications</span>
                        <button class="mark-read">Mark all as read</button>
                    </div>
                    <div class="dropdown-body">
                        <div class="notification-item unread">
                            <div class="notif-icon bg-blue"><i data-feather="message-square"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text"><strong>Alex Smith</strong> sent a new message.</p>
                                <span class="notif-time">2 min ago</span>
                            </div>
                        </div>
                        <div class="notification-item unread">
                            <div class="notif-icon bg-green"><i data-feather="check-circle"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text">Lead <strong>Maria Jones</strong> qualified.</p>
                                <span class="notif-time">1 hour ago</span>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notif-icon bg-orange"><i data-feather="alert-circle"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text">Follow up with <strong>Robert Kim</strong> overdue.</p>
                                <span class="notif-time">Yesterday</span>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notif-icon bg-red"><i data-feather="x-circle"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text">Lead <strong>Sarah Lee</strong> marked as lost.</p>
                                <span class="notif-time">2 days ago</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn-primary" id="addLeadBtn">
                    <i data-feather="plus"></i>
                    <span>Add New Lead</span>
                </button>
            </div>
        </header>

        <!-- Dashboard View -->
        <div class="dashboard-view">

            <!-- Greeting Section -->
            <div class="greeting-section">
                <div class="greeting-content">
                    <div class="greeting-left">
                        <h2 class="greeting-text"><span id="greeting-msg">Good Morning</span>, <span id="username">User</span></h2>
                        <div class="datetime-display">
                            <div class="date-time-group">
                                <div class="date-item">
                                    <i data-feather="calendar"></i>
                                    <span id="date-info"></span>
                                </div>
                                <div class="time-item">
                                    <i data-feather="clock"></i>
                                    <span id="time-info"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="punch-btn" id="punchInBtn">
                        <i data-feather="log-in"></i>
                        <span>Punch In</span>
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Leads</span>
                        <i data-feather="users" class="stat-icon"></i>
                    </div>
                    <div class="stat-value">1,284</div>
                    <div class="stat-trend trend-up">
                        <i data-feather="trending-up" width="14"></i>
                        <span>12% vs last month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">New This Week</span>
                        <i data-feather="user-plus" class="stat-icon"></i>
                    </div>
                    <div class="stat-value">45</div>
                    <div class="stat-trend trend-up">
                        <i data-feather="trending-up" width="14"></i>
                        <span>8% vs last week</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Follow-ups</span>
                        <i data-feather="clock" class="stat-icon"></i>
                    </div>
                    <div class="stat-value">12</div>
                    <div class="stat-trend trend-down">
                        <i data-feather="alert-circle" width="14"></i>
                        <span>3 overdue</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Conversion Rate</span>
                        <i data-feather="pie-chart" class="stat-icon"></i>
                    </div>
                    <div class="stat-value">24.8%</div>
                    <div class="stat-trend trend-up">
                        <i data-feather="trending-up" width="14"></i>
                        <span>2.1% increase</span>
                    </div>
                </div>
            </div>

            <!-- Recent Leads Section -->
            <div class="leads-section">
                <div class="section-header">
                    <div class="header-top">
                        <h2 class="section-title">Recent Leads</h2>
                        <div class="filter-actions">
                            <!-- Could add export/print here -->
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <select class="filter-select">
                                <option value="">All Statuses</option>
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select">
                                <option value="">All Sources</option>
                                <option value="website">Website</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="referral">Referral</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <div class="date-range">
                                <i data-feather="calendar" width="14"></i>
                                <input type="text" class="date-input" placeholder="Start Date"
                                    onfocus="(this.type='date')" onblur="(this.type='text')">
                                <span>-</span>
                                <input type="text" class="date-input" placeholder="End Date"
                                    onfocus="(this.type='date')" onblur="(this.type='text')">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name / Company</th>
                                <th>Status</th>
                                <th>Value</th>
                                <th>Priority</th>
                                <th>Source</th>
                                <th>Next Follow-up</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar">AS</div>
                                        <div>
                                            <span class="lead-name">Alex Smith</span>
                                            <span class="lead-email">alex@urbanstudio.com</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-new">New</span></td>
                                <td>$12,000</td>
                                <td><span style="color: #ef4444; font-weight: 600;">High</span></td>
                                <td>LinkedIn</td>
                                <td>Nov 28, 2024</td>
                                <td>
                                    <button class="action-btn"><i data-feather="more-horizontal"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar">MJ</div>
                                        <div>
                                            <span class="lead-name">Maria Jones</span>
                                            <span class="lead-email">m.jones@construct.io</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-contacted">Contacted</span></td>
                                <td>$45,000</td>
                                <td><span style="color: #f59e0b; font-weight: 600;">Medium</span></td>
                                <td>Website</td>
                                <td>Nov 27, 2024</td>
                                <td>
                                    <button class="action-btn"><i data-feather="more-horizontal"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar">RK</div>
                                        <div>
                                            <span class="lead-name">Robert Kim</span>
                                            <span class="lead-email">r.kim@designgroup.net</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-qualified">Qualified</span></td>
                                <td>$120,000</td>
                                <td><span style="color: #ef4444; font-weight: 600;">High</span></td>
                                <td>Referral</td>
                                <td>Dec 01, 2024</td>
                                <td>
                                    <button class="action-btn"><i data-feather="more-horizontal"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar">SL</div>
                                        <div>
                                            <span class="lead-name">Sarah Lee</span>
                                            <span class="lead-email">sarah@modernhomes.co</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-lost">Lost</span></td>
                                <td>$8,500</td>
                                <td><span style="color: #10b981; font-weight: 600;">Low</span></td>
                                <td>Cold Call</td>
                                <td>-</td>
                                <td>
                                    <button class="action-btn"><i data-feather="more-horizontal"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar">DT</div>
                                        <div>
                                            <span class="lead-name">David Tan</span>
                                            <span class="lead-email">david@skyline.arch</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-new">New</span></td>
                                <td>$22,000</td>
                                <td><span style="color: #f59e0b; font-weight: 600;">Medium</span></td>
                                <td>Website</td>
                                <td>Nov 29, 2024</td>
                                <td>
                                    <button class="action-btn"><i data-feather="more-horizontal"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Add Lead Modal -->
    <div class="modal-overlay" id="addLeadModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add New Lead</h3>
                <button class="close-modal" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addLeadForm">
                    <div class="form-group">
                        <label for="leadIRN">IRN (Inquiry Registered Number)</label>
                        <input type="text" id="leadIRN" readonly placeholder="Auto-generated"
                            style="background-color: var(--bg-hover); cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="leadStatus">Status</label>
                        <select id="leadStatus">
                            <option value="NP">Not Picked (NP)</option>
                            <option value="NR">Not Required (NR)</option>
                            <option value="Vn">Vendor (Vn)</option>
                            <option value="JS">JS</option>
                            <option value="GC">GC</option>
                            <option value="FC">FC</option>
                            <option value="SMW">SMW</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="leadContact">Contact Number</label>
                        <input type="tel" id="leadContact" placeholder="Enter contact number">
                    </div>

                    <div class="form-group">
                        <label for="leadMobile">Mobile Number</label>
                        <input type="tel" id="leadMobile" placeholder="Enter mobile number">
                    </div>

                    <div class="form-group">
                        <label for="leadDescription">Description</label>
                        <textarea id="leadDescription" rows="4" placeholder="Enter description details..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn-primary" id="saveLeadBtn">Save Lead</button>
            </div>
        </div>
    </div>

    <!-- Add Follow Up Modal -->
    <div class="modal-overlay" id="addFollowUpModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add Follow Up</h3>
                <button class="close-modal" id="closeFollowUpBtn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addFollowUpForm">
                    <div class="form-group">
                        <label for="fuClientName">Client Name</label>
                        <input type="text" id="fuClientName" placeholder="Search or enter client name" required>
                    </div>
                    <div class="form-group">
                        <label for="fuMobile">Mobile Number</label>
                        <input type="tel" id="fuMobile" placeholder="+91 XXXXX XXXXX">
                    </div>

                    <div class="form-group" style="display: flex; gap: 1rem;">
                        <div style="flex: 1;">
                            <label for="fuDate">Date</label>
                            <input type="date" id="fuDate" required>
                        </div>
                        <div style="flex: 1;">
                            <label for="fuTime">Time</label>
                            <input type="time" id="fuTime" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="fuType">Interaction Type</label>
                        <select id="fuType">
                            <option value="call">Call</option>
                            <option value="meeting">Meeting / Visit</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fuNotes">Discussion Notes</label>
                        <textarea id="fuNotes" rows="3" placeholder="Summary of conversation..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="fuReminder">Next Follow Up / Reminder</label>
                        <select id="fuReminder">
                            <option value="none">No Reminder</option>
                            <option value="12h">Remind in 12 Hours</option>
                            <option value="24h">Remind in 24 Hours</option>
                            <option value="custom">Custom Date & Time</option>
                        </select>
                    </div>

                    <!-- Custom Reminder Fields (Hidden by default) -->
                    <div id="customReminderGroup" class="form-group"
                        style="display: none; gap: 1rem; background: var(--bg-hover); padding: 1rem; border-radius: 6px;">
                        <div style="flex: 1;">
                            <label for="fuCustomDate" style="font-size: 0.8rem;">Reminder Date</label>
                            <input type="date" id="fuCustomDate">
                        </div>
                        <div style="flex: 1;">
                            <label for="fuCustomTime" style="font-size: 0.8rem;">Reminder Time</label>
                            <input type="time" id="fuCustomTime">
                        </div>
                    </div>

                    <div class="form-group"
                        style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <label class="checkbox-container"
                            style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="fuCloseLead">
                            <span style="font-weight: 600; color: var(--status-lost);">Close This Lead?</span>
                        </label>
                    </div>

                    <!-- Closing Reason (Hidden by default) -->
                    <div id="closingReasonGroup" class="form-group" style="display: none;">
                        <label for="fuClosingReason">Reason for Closing <span
                                style="color: var(--status-lost);">*</span></label>
                        <textarea id="fuClosingReason" rows="2"
                            placeholder="Why is this lead being closed? (e.g. Not interested, Budget issue)"></textarea>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelFollowUpBtn">Cancel</button>
                <button class="btn-primary" id="saveFollowUpBtn">Save Follow Up</button>
            </div>
        </div>
    </div>

    <!-- Vendor Query Modal -->
    <div class="modal-overlay" id="vendorQueryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Vendor Query</h3>
                <button class="close-modal" id="closeVendorQueryBtn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="vendorQueryForm">
                    <div class="form-group">
                        <label for="vqIRN">IRN (Auto Generated)</label>
                        <input type="text" id="vqIRN" readonly
                            style="background-color: var(--bg-hover); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="vqName">Name</label>
                        <input type="text" id="vqName" placeholder="Enter name">
                    </div>
                    <div class="form-group">
                        <label for="vqPhone">Phone Number</label>
                        <input type="tel" id="vqPhone" placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label for="vqEmail">Email ID</label>
                        <input type="email" id="vqEmail" placeholder="Enter email address">
                    </div>
                    <div class="form-group">
                        <label for="vqCompany">Company Name</label>
                        <input type="text" id="vqCompany" placeholder="Enter company name">
                    </div>
                    <div class="form-group">
                        <label for="vqDealsIn">Deals In</label>
                        <input type="text" id="vqDealsIn" placeholder="e.g. Hardware, Software, Services">
                    </div>
                    <div class="form-group">
                        <label for="vqCatalogue">Catalogue (if any)</label>
                        <input type="file" id="vqCatalogue" style="padding: 0.5rem;">
                    </div>

                    <div class="form-group">
                        <label for="vqManagerShared">Vendor Manager Phone Number</label>
                        <select id="vqManagerShared">
                            <option value="not_shared">Not Shared</option>
                            <option value="shared">Shared</option>
                        </select>
                    </div>

                    <!-- Hidden Manager Details -->
                    <div id="vqManagerDetails"
                        style="display: none; gap: 1rem; background: var(--bg-hover); padding: 1rem; border-radius: 6px; margin-bottom: 1.2rem;">
                        <div style="flex: 1;">
                            <label for="vqManagerName" style="font-size: 0.8rem;">Manager Name</label>
                            <input type="text" id="vqManagerName" placeholder="Name">
                        </div>
                        <div style="flex: 1;">
                            <label for="vqManagerNumber" style="font-size: 0.8rem;">Manager Number</label>
                            <input type="tel" id="vqManagerNumber" placeholder="Number">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="vqGreeting">Greeting Message Sent?</label>
                        <select id="vqGreeting">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelVendorQueryBtn">Cancel</button>
                <button class="btn-primary" id="saveVendorQueryBtn">Save Query</button>
            </div>
        </div>
    </div>

    <!-- Lead Details Drawer -->
    <div class="drawer-overlay" id="leadDrawer">
        <div class="drawer">
            <div class="drawer-header">
                <div>
                    <h3 class="drawer-title">Alex Smith</h3>
                    <span class="drawer-subtitle">Urban Studio Architects</span>
                </div>
                <button class="close-drawer" id="closeDrawerBtn"><i data-feather="x"></i></button>
            </div>
            <div class="drawer-body">
                <div class="detail-section">
                    <span class="detail-label">Contact Info</span>
                    <div class="detail-value">alex@urbanstudio.com</div>
                    <div class="detail-value">+1 (555) 123-4567</div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Status</span>
                    <span class="status-badge status-new">New Lead</span>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Next Follow-up</span>
                    <div class="detail-value" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i data-feather="calendar" style="width: 16px;"></i>
                        <span>Nov 28, 2024</span>
                    </div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Tags</span>
                    <div class="tags-container">
                        <span class="tag">High Priority <i data-feather="x" class="remove-tag"></i></span>
                        <span class="tag">Architect <i data-feather="x" class="remove-tag"></i></span>
                        <button class="add-tag-btn"><i data-feather="plus"></i> Add Tag</button>
                    </div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Recent Activity</span>
                    <div class="activity-timeline">
                        <div class="activity-item">
                            <span class="activity-date">Today, 10:30 AM</span>
                            <div class="activity-content">Added to system via LinkedIn import.</div>
                        </div>
                        <div class="activity-item">
                            <span class="activity-date">Yesterday</span>
                            <div class="activity-content">Visited pricing page.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn-secondary" style="flex: 1;">Edit</button>
                <button class="btn-primary" style="flex: 1;">Email</button>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fab-container">
        <div class="fab-menu" id="fabMenu">
            <button class="fab-item" onclick="document.getElementById('addLeadBtn').click()">
                <span class="fab-label">Add Lead</span>
                <div class="fab-icon"><i data-feather="user-plus"></i></div>
            </button>
            <button class="fab-item" id="openFollowUpBtn">
                <span class="fab-label">Add Follow Up</span>
                <div class="fab-icon"><i data-feather="phone-call"></i></div>
            </button>
            <button class="fab-item" id="openVendorQueryBtn">
                <span class="fab-label">Vendor Query</span>
                <div class="fab-icon"><i data-feather="briefcase"></i></div>
            </button>
            <button class="fab-item">
                <span class="fab-label">Add Reminder</span>
                <div class="fab-icon"><i data-feather="bell"></i></div>
            </button>
            <button class="fab-item">
                <span class="fab-label">New Task</span>
                <div class="fab-icon"><i data-feather="check-square"></i></div>
            </button>
        </div>
        <button class="fab-main" id="fabMain">
            <i data-feather="plus"></i>
        </button>
    </div>

    <script>
        // Initialize Feather Icons
        feather.replace();

        // Modal Logic
        const modal = document.getElementById('addLeadModal');
        const addBtn = document.getElementById('addLeadBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveBtn = document.getElementById('saveLeadBtn');

        function openModal() {
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        addBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Save Lead (Mock functionality)
        saveBtn.addEventListener('click', () => {
            // Here you would typically gather form data and send to backend
            const name = document.getElementById('leadName').value;
            if (name) {
                alert(`Lead "${name}" added successfully! (This is a demo)`);
                closeModal();
                document.getElementById('addLeadForm').reset();
            } else {
                alert('Please enter a name');
            }
        });

        // Drawer Logic
        const drawer = document.getElementById('leadDrawer');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const actionBtns = document.querySelectorAll('.action-btn'); // Or row click

        function openDrawer() {
            drawer.classList.add('active');
        }

        function closeDrawer() {
            drawer.classList.remove('active');
        }

        // Add click event to all action buttons for demo
        actionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent bubbling if we add row click later
                openDrawer();
            });
        });

        closeDrawerBtn.addEventListener('click', closeDrawer);

        // Close drawer on outside click
        drawer.addEventListener('click', (e) => {
            if (e.target === drawer) {
                closeDrawer();
            }
        });

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        themeToggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');

            // Update icon
            if (document.body.classList.contains('light-mode')) {
                themeToggleBtn.innerHTML = '<i data-feather="moon"></i>';
            } else {
                themeToggleBtn.innerHTML = '<i data-feather="sun"></i>';
            }
            feather.replace();
        });

        // Notification Logic
        const notifBtn = document.getElementById('notificationBtn');
        const notifDropdown = document.getElementById('notificationDropdown');

        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('active');
        });

        // Close notification on outside click
        document.addEventListener('click', (e) => {
            if (notifDropdown.classList.contains('active')) {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.classList.remove('active');
                }
            }
        });

        // Load Sidebar
        fetch('sidebar.html')
            .then(response => response.text())
            .then(html => {
                document.getElementById('sidebarContainer').innerHTML = html;
                feather.replace(); // Re-init icons for sidebar

                // Update user profile data from window variables
                if (window.currentUsername) {
                    const usernameEl = document.getElementById('sidebarUsername');
                    const roleEl = document.getElementById('sidebarUserRole');
                    const initialsEl = document.getElementById('userInitials');
                    const profileImg = document.getElementById('profileImg');

                    if (usernameEl) {
                        usernameEl.textContent = window.currentUsername;
                    }

                    if (roleEl) {
                        roleEl.textContent = window.userRole || 'Employee';
                    }

                    if (initialsEl) {
                        // Generate initials
                        const names = window.currentUsername.split(' ');
                        const initials = names.map(n => n.charAt(0).toUpperCase()).join('');
                        initialsEl.textContent = initials || 'U';
                    }

                    // Load profile picture if available
                    if (profileImg && window.profileImageUrl && window.profileImageUrl.trim() !== '') {
                        profileImg.src = window.profileImageUrl;
                        profileImg.style.display = 'block';
                        if (initialsEl) {
                            initialsEl.style.display = 'none';
                        }
                    }
                }

                // Sidebar Toggle Logic
                const sidebar = document.getElementById('sidebarContainer');
                if (typeof feather !== 'undefined') feather.replace();

                // Highlight active link (Dashboard)
                setTimeout(() => {
                    const links = sidebar.querySelectorAll('.nav-link');
                    links.forEach(link => {
                        const href = link.getAttribute('href');
                        // Check for 'index.php' or empty path which usually implies index
                        if (href === 'index.php' || href === './' || (href === '' && window.location.pathname.endsWith('/'))) {
                            link.classList.add('active');
                        }
                    });
                }, 100);

                const toggleBtn = document.getElementById('sidebarToggle');
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                const sidebarOverlay = document.getElementById('sidebarOverlay');

                // Desktop Collapse
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('collapsed');
                    });
                }

                // Mobile Open
                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', () => {
                        sidebar.classList.add('mobile-open');
                        sidebarOverlay.classList.add('active');
                    });
                }

                // Mobile Close (Overlay Click)
                if (sidebarOverlay) {
                    sidebarOverlay.addEventListener('click', () => {
                        sidebar.classList.remove('mobile-open');
                        sidebarOverlay.classList.remove('active');
                    });
                }
            })
            .catch(err => console.error('Error loading sidebar:', err));

        // FAB Logic
        const fabMain = document.getElementById('fabMain');
        const fabMenu = document.getElementById('fabMenu');

        fabMain.addEventListener('click', () => {
            fabMain.classList.toggle('active');
            fabMenu.classList.toggle('active');
        });

        // Close FAB when clicking outside
        document.addEventListener('click', (e) => {
            if (!fabMain.contains(e.target) && !fabMenu.contains(e.target)) {
                fabMain.classList.remove('active');
                fabMenu.classList.remove('active');
            }
        });

        // --- Follow Up Modal Logic ---
        const fuModal = document.getElementById('addFollowUpModal');
        const openFuBtn = document.getElementById('openFollowUpBtn');
        const closeFuBtn = document.getElementById('closeFollowUpBtn');
        const cancelFuBtn = document.getElementById('cancelFollowUpBtn');
        const saveFuBtn = document.getElementById('saveFollowUpBtn');

        const fuReminderSelect = document.getElementById('fuReminder');
        const customReminderGroup = document.getElementById('customReminderGroup');

        const fuCloseLeadCheckbox = document.getElementById('fuCloseLead');
        const closingReasonGroup = document.getElementById('closingReasonGroup');
        const fuClosingReason = document.getElementById('fuClosingReason');

        // Set Default Date/Time to IST
        function setDateTimeDefaults() {
            const now = new Date();
            // Adjust to IST (UTC+5:30) if not already in local
            // Assuming browser is local, but let's just use local time for simplicity as requested "according to recent time"

            // Format Date: YYYY-MM-DD
            const dateStr = now.toISOString().split('T')[0];
            document.getElementById('fuDate').value = dateStr;

            // Format Time: HH:MM
            const timeStr = now.toTimeString().split(' ')[0].substring(0, 5);
            document.getElementById('fuTime').value = timeStr;
        }

        function openFuModal() {
            setDateTimeDefaults();
            fuModal.classList.add('active');
            // Close FAB if open
            fabMain.classList.remove('active');
            fabMenu.classList.remove('active');
        }

        function closeFuModal() {
            fuModal.classList.remove('active');
        }

        if (openFuBtn) openFuBtn.addEventListener('click', openFuModal);
        if (closeFuBtn) closeFuBtn.addEventListener('click', closeFuModal);
        if (cancelFuBtn) cancelFuBtn.addEventListener('click', closeFuModal);

        // Handle Custom Reminder Toggle
        fuReminderSelect.addEventListener('change', (e) => {
            if (e.target.value === 'custom') {
                customReminderGroup.style.display = 'flex';
            } else {
                customReminderGroup.style.display = 'none';
            }
        });

        // Handle Close Lead Toggle
        fuCloseLeadCheckbox.addEventListener('change', (e) => {
            if (e.target.checked) {
                closingReasonGroup.style.display = 'block';
                fuClosingReason.setAttribute('required', 'true');
            } else {
                closingReasonGroup.style.display = 'none';
                fuClosingReason.removeAttribute('required');
            }
        });

        // Save Follow Up (Mock)
        saveFuBtn.addEventListener('click', () => {
            const client = document.getElementById('fuClientName').value;
            const isClosing = fuCloseLeadCheckbox.checked;
            const reason = fuClosingReason.value;

            if (!client) {
                alert('Please enter a client name.');
                return;
            }

            if (isClosing && !reason.trim()) {
                alert('Please provide a reason for closing the lead.');
                return;
            }

            alert(`Follow up for "${client}" saved! ${isClosing ? '(Lead Closed)' : ''}`);
            closeFuModal();
            document.getElementById('addFollowUpForm').reset();
            customReminderGroup.style.display = 'none';
            closingReasonGroup.style.display = 'none';
        });

        // --- IRN Generation Logic ---
        const leadStatusSelect = document.getElementById('leadStatus');
        const leadIRNInput = document.getElementById('leadIRN');

        function generateIRN() {
            const status = leadStatusSelect.value;
            const now = new Date();
            const month = now.toLocaleString('default', { month: 'short' }).toUpperCase(); // e.g., NOV
            const year = now.getFullYear().toString().substr(-2); // e.g., 25

            // Calculate Week of Month (1-5)
            const week = Math.ceil(now.getDate() / 7);

            // Calculate Day of Week (1-7, Mon=1, Sun=7)
            let day = now.getDay();
            day = day === 0 ? 7 : day;

            // Format: STATUS-MMMYY-W#D# (e.g., NP-NOV25-W4D3)
            leadIRNInput.value = `${status}-${month}${year}-W${week}D${day}`;
        }

        if (leadStatusSelect) {
            leadStatusSelect.addEventListener('change', generateIRN);
            // Generate initial IRN when modal opens or page loads
            generateIRN();
        }

        // Regenerate IRN when Add Lead modal opens to ensure uniqueness
        if (addLeadBtn) {
            addLeadBtn.addEventListener('click', generateIRN);
        }

        // --- Vendor Query Modal Logic ---
        const vqModal = document.getElementById('vendorQueryModal');
        const openVqBtn = document.getElementById('openVendorQueryBtn');
        const closeVqBtn = document.getElementById('closeVendorQueryBtn');
        const cancelVqBtn = document.getElementById('cancelVendorQueryBtn');
        const saveVqBtn = document.getElementById('saveVendorQueryBtn');
        const vqManagerShared = document.getElementById('vqManagerShared');
        const vqManagerDetails = document.getElementById('vqManagerDetails');
        const vqIRN = document.getElementById('vqIRN');

        function openVqModal() {
            generateVendorIRN();
            vqModal.classList.add('active');
            fabMain.classList.remove('active');
            fabMenu.classList.remove('active');
        }

        function closeVqModal() {
            vqModal.classList.remove('active');
        }

        if (openVqBtn) openVqBtn.addEventListener('click', openVqModal);
        if (closeVqBtn) closeVqBtn.addEventListener('click', closeVqModal);
        if (cancelVqBtn) cancelVqBtn.addEventListener('click', closeVqModal);

        // Toggle Manager Details
        if (vqManagerShared) {
            vqManagerShared.addEventListener('change', (e) => {
                if (e.target.value === 'shared') {
                    vqManagerDetails.style.display = 'flex';
                } else {
                    vqManagerDetails.style.display = 'none';
                }
            });
        }

        // Generate Vendor IRN
        function generateVendorIRN() {
            const now = new Date();
            const month = now.toLocaleString('default', { month: 'short' }).toUpperCase();
            const year = now.getFullYear().toString().substr(-2);
            const week = Math.ceil(now.getDate() / 7);
            let day = now.getDay();
            day = day === 0 ? 7 : day;

            // Format: VQ-MMMYY-W#D#
            vqIRN.value = `VQ-${month}${year}-W${week}D${day}`;
        }

        // Save Vendor Query (Mock)
        if (saveVqBtn) {
            saveVqBtn.addEventListener('click', () => {
                alert('Vendor Query Saved!');
                closeVqModal();
                document.getElementById('vendorQueryForm').reset();
                vqManagerDetails.style.display = 'none';
            });
        }

    </script>
</body>


</html>