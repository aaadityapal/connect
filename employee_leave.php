<?php
// Start session and restrict access to Site Supervisor and Site Coordinator
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Lightweight role guard
$currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

// Greeting based on local time
$hour = (int)date('H');
if ($hour < 12) { $greeting = 'Good morning'; }
elseif ($hour < 17) { $greeting = 'Good afternoon'; }
else { $greeting = 'Good evening'; }

// Fetch approvers from DB: users with role 'Senior Manager (Site)' or 'Senior Manager (Studio)'
require_once __DIR__ . '/config/db_connect.php';

$approvers = [];
$managerRole = (in_array($currentRole, ['Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Sales', 'Graphic Designer', 'Social Media Marketing'])) 
    ? 'Senior Manager (Site)' 
    : 'Senior Manager (Studio)';

try {
	// 1) Exact match using PDO (only active users)
	$sql = "SELECT id, COALESCE(username, unique_id) AS display_name
	        FROM users
	        WHERE role = ? AND LOWER(status) = 'active'
	        ORDER BY display_name ASC";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$managerRole]);
	$approvers = $stmt->fetchAll();

	// 2) If none found, try a tolerant LIKE search (handles case/spacing variants)
	if (!$approvers) {
		$sql2 = "SELECT id, COALESCE(username, unique_id) AS display_name
		         FROM users
		         WHERE LOWER(role) LIKE LOWER('%Senior%Manager%(Site)%') AND LOWER(status) = 'active'
		         ORDER BY display_name ASC";
		$approvers = $pdo->query($sql2)->fetchAll();
	}

	// 3) As a last resort, try mysqli exact match if available
	if (!$approvers && isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
		$res = $conn->query("SELECT id, COALESCE(username, unique_id) AS display_name FROM users WHERE role = 'Senior Manager (Site)' AND LOWER(status) = 'active' ORDER BY display_name ASC");
		if ($res) {
			while ($row = $res->fetch_assoc()) { $approvers[] = $row; }
		}
	}

	// 4) Final tolerant fallback: fetch all and filter in PHP (handles odd whitespace or encoding)
	if (!$approvers) {
		try {
			$all = $pdo->query("SELECT id, role, username, unique_id, status FROM users");
			if ($all) {
				foreach ($all as $row) {
					$roleRaw = isset($row['role']) ? $row['role'] : '';
					$normalized = strtolower(preg_replace('/\s+/', ' ', trim($roleRaw)));
					$statusNorm = isset($row['status']) ? strtolower(trim($row['status'])) : '';
					if ($statusNorm === 'active' && strpos($normalized, 'senior') !== false && strpos($normalized, 'manager') !== false && strpos($normalized, '(site)') !== false) {
						$display = $row['username'] ?: $row['unique_id'];
						$approvers[] = ['id' => $row['id'], 'display_name' => $display];
					}
				}
			}
		} catch (Exception $ie) {
			// ignore
		}
	}

} catch (Exception $e) {
	// Silently fail to an empty list; log to PHP error log
	error_log('Approver fetch failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="images/logo.png">
    <title>Leave Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .page {
            max-width: none;
            width: 100%;
            margin: 0;
            padding: 1rem 1.25rem;
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 1.25rem;
        }

        .topbar {
            width: 100%;
            background: #0f172a;
            color: #e5e7eb;
            padding: .85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topbar .title { font-weight: 600; letter-spacing: .2px; }
        .topbar .greet { color: #cbd5e1; }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .inline-actions {
            display: flex;
            align-items: flex-end;
            gap: .5rem;
        }

        .small-btn {
            padding: .55rem .9rem;
            font-size: .9rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            cursor: pointer;
        }
        .small-btn:hover { background: #eef2f7; }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: .75rem .9rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: .95rem;
            color: #334155;
        }
        .table th { background: #f8fafc; text-align: left; color: #475569; font-weight: 600; }
        .table tr:last-child td { border-bottom: none; }

        /* Badges for leave types and statuses */
        .badge-type {
            display: inline-block;
            padding: .25rem .5rem;
            border-radius: 999px;
            font-size: .85rem;
            font-weight: 600;
            color: #0f172a;
            border: 1px solid rgba(0,0,0,.06);
        }
        .status-pill {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
            color: #0f172a;
            background: #e5e7eb;
            border: 1px solid rgba(0,0,0,.06);
            text-transform: capitalize;
        }
        /* Row highlights for history table */
        .row-approved {
            background: #ecfdf5; /* green-50 */
        }
        .row-rejected {
            background: #fef2f2; /* red-50 */
        }
        .row-noaction {
            background: #fffbeb; /* amber-50 */
        }
        /* Icon buttons */
        .icon-actions { display:flex; gap:.4rem; align-items:center; }
        .icon-btn {
            width: 30px; height: 30px; border-radius: 6px; border: 1px solid #e5e7eb;
            background: #fff; display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: background .15s;
        }
        .icon-btn:hover { background: #f8fafc; }
        .icon-btn svg { width: 16px; height: 16px; stroke: #0f172a; }
        .icon-btn.edit svg { stroke: #2563eb; }
        .icon-btn.view svg { stroke: #16a34a; }
        .icon-btn.delete svg { stroke: #dc2626; }
        .icon-btn.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }

        /* Modal styling */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.5);
            display: none; align-items: center; justify-content: center; z-index: 1000;
        }
        .modal {
            width: min(720px, 92vw); background: #fff; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,.2);
            overflow: hidden; transform: translateY(10px); opacity: 0; transition: opacity .2s, transform .2s;
        }
        .modal.show { transform: translateY(0); opacity: 1; }
        .modal-header { padding: 16px 18px; border-bottom: 1px solid #eef2f7; display:flex; align-items:center; justify-content:space-between; }
        .modal-title { font-weight: 600; color: #0f172a; }
        .modal-close { border: none; background: transparent; cursor: pointer; font-size: 20px; line-height: 1; color: #64748b; }
        .modal-body { padding: 16px 18px; }
        .detail-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        .detail-item { background:#f8fafc; border:1px solid #eef2f7; border-radius:10px; padding:10px 12px; }
        .detail-item .label { font-size:.85rem; color:#64748b; }
        .detail-item .value { font-weight:600; color:#0f172a; }
        @media (max-width:640px){ .detail-grid{ grid-template-columns:1fr; } }

        .calc-banner {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .75rem 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            color: #334155;
        }
        .badge { background: #e2e8f0; border-radius: 999px; padding: .25rem .5rem; font-size: .85rem; color: #0f172a; }

        /* Right panel */
        .panel h3 { font-size: 1.05rem; margin-bottom: .75rem; color: #0f172a; }
        .balance-item { margin-bottom: .9rem; }
        .balance-title { display: flex; justify-content: space-between; align-items: center; font-size: .92rem; color: #1f2937; margin-bottom: .35rem; }
        .progress {
            height: 8px;
            border-radius: 999px;
            background: #eef2f7;
            overflow: hidden;
        }
        .progress > span { display: block; height: 100%; background: #3b82f6; }
        .muted { color: #64748b; font-size: .85rem; }
        .note { border-left: 3px solid #e2e8f0; padding-left: .75rem; margin-top: .75rem; }

        .form-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .form-select {
            cursor: pointer;
        }

        @media (max-width: 640px) {
            .page { margin: 0; padding: .75rem; grid-template-columns: 1fr; }
            .container { padding: 1.25rem; }
            .date-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column-reverse; }
            .btn { width: 100%; }
            /* Make the dates table usable on small iPhones (SE/XR) */
            .table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            #datesTable { min-width: 680px; }
            .table th, .table td { white-space: nowrap; }
            /* Ensure selects are visible and usable */
            .row-leave-type, .row-day-type { min-width: 160px; }
        }
    </style>
</head>
<body>
    <?php
    if ($currentRole === 'Site Supervisor') {
        include 'includes/supervisor_panel.php';
    } elseif (in_array($currentRole, ['Site Coordinator', 'Purchase Manager'])) {
        include 'includes/manager_panel.php';
    } else {
        include 'components/minimal_sidebar.php';
    }
    ?>
    <div class="main-content msb-content">
    <div class="topbar">
        <div class="title">Leave Application</div>
        <div class="greet"><?php echo htmlspecialchars($greeting . ', ' . $currentUsername); ?></div>
    </div>

    <div class="page">
    <div class="container">
        <div class="header">
            <h1>Leave Application</h1>
            <p>Please fill out the form below to request leave</p>
        </div>

                <form id="leaveForm">
                    <div class="form-group">
                        <label for="approver" class="form-label">Approver *</label>
                        <select id="approver" name="approver" class="form-select" required>
                            <option value="">Select an approver</option>
                        <?php if (empty($approvers)): ?>
                            <option value="" disabled>(No Senior Manager - Site found)</option>
                        <?php else: 
                            $firstApprover = $approvers[0];
                            $displayRoleLabel = str_replace(['(Site)','(Studio)'], ['- Site','- Studio'], $managerRole);
                            foreach ($approvers as $a): ?>
                            <option value="<?php echo htmlspecialchars($a['id']); ?>" <?php echo ($a['id'] == $firstApprover['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['display_name']); ?> (<?php echo htmlspecialchars($displayRoleLabel); ?>)
                            </option>
                        <?php endforeach; endif; ?>
                        </select>
            </div>

                    <div class="form-group">
                        <label for="reason" class="form-label">Reason for Leave *</label>
                    <textarea id="reason" name="reason" class="form-textarea" placeholder="Brief reason for leave" required></textarea>
                    </div>

                    <div class="date-row">
                        <div class="form-group">
                            <label for="fromDate" class="form-label">From Date *</label>
                            <input type="date" id="fromDate" name="fromDate" class="form-input" required>
            </div>

                    <div class="form-group inline-actions" style="gap: .75rem;">
                        <div style="flex:1;">
                            <label for="toDate" class="form-label">To Date *</label>
                            <input type="date" id="toDate" name="toDate" class="form-input" required>
                    </div>
                        <button type="button" id="generateDates" class="small-btn">Generate Date List</button>
                </div>
                </div>

                <div class="form-group">
                    <table class="table" id="datesTable">
                        <thead>
                            <tr>
                                <th style="width:48px;"><input type="checkbox" id="checkAll"></th>
                                <th style="width:180px;">Date</th>
                                <th>Day</th>
                                <th style="width:260px;">Leave Type</th>
                                <th style="width:180px;">Day Type</th>
                            </tr>
                        </thead>
                        <tbody id="datesTbody">
                            <tr><td colspan="5" class="muted">No dates generated yet</td></tr>
                        </tbody>
                    </table>
            </div>

                <div class="form-group calc-banner">
                    <span>Calculated duration:</span>
                    <span class="badge" id="calculatedDuration">0 day</span>
                    <span class="muted">Inclusive of start and end dates. Half-day reduces total to 0.5 day when applicable.</span>
                    </div>

                

                

                    <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
                </form>
            </div>

        <div class="container panel">
            <h3>Your Leave Balance</h3>
            <div id="leaveBank"></div>
            <div class="note muted">
                <div>Balances include approved and pending requests for 2025. Rejected leaves do not reduce your balance.</div>
                <div>Policy: Casual Leave – up to 1 per month; Short Leave – up to 2 per month.</div>
                    </div>
                </div>
            </div>

    <div class="page" style="grid-template-columns: 1fr;">
        <div class="container">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.75rem;">
                <h3 style="margin:0;">Leave Table History</h3>
                <div style="display:flex; gap:.5rem; align-items:center;">
                    <select id="historyMonth" class="form-select" style="width:160px;">
                        <option value="">All Months</option>
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <select id="historyYear" class="form-select" style="width:120px;"></select>
                    <button id="historyApply" class="small-btn">Apply</button>
                    </div>
                </div>
            <table class="table" id="leaveHistoryTable">
                <thead>
                    <tr>
                        <th style="width:160px;">Date</th>
                        <th>Leave Type</th>
                        <th style="width:120px;">Duration</th>
                        <th style="width:140px;">Status</th>
                        <th style="width:160px;">Manager Status</th>
                        <th>Reason</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveHistoryBody">
                    <tr><td colspan="7" class="muted">Loading...</td></tr>
                </tbody>
            </table>
                </div>
            </div>

    <!-- View Modal -->
    <div id="viewModalBackdrop" class="modal-backdrop">
        <div class="modal" id="viewModal">
            <div class="modal-header">
                <div class="modal-title">Leave Details</div>
                <button class="modal-close" id="viewModalClose">×</button>
                    </div>
            <div class="modal-body" id="viewModalBody">
                Loading...
                </div>
                </div>
            </div>

    <!-- Edit Modal -->
    <div id="editModalBackdrop" class="modal-backdrop">
        <div class="modal" id="editModal">
            <div class="modal-header">
                <div class="modal-title">Edit Leave</div>
                <button class="modal-close" id="editModalClose">×</button>
                    </div>
            <div class="modal-body">
                <form id="editLeaveForm">
                    <input type="hidden" id="editLeaveId" />
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="label">Start Date</div>
                            <input type="date" id="editStartDate" class="form-input" required />
                </div>
                        <div class="detail-item">
                            <div class="label">End Date</div>
                            <input type="date" id="editEndDate" class="form-input" required />
                </div>
                        <div class="detail-item">
                            <div class="label">Leave Type</div>
                            <select id="editLeaveType" class="form-select" required></select>
            </div>
                        <div class="detail-item">
                            <div class="label">Half Day</div>
                            <div style="display:flex; align-items:center; gap:.5rem;">
                                <input type="checkbox" id="editHalfDay" />
                                <select id="editHalfDayType" class="form-select" style="width:160px; display:none;">
                                    <option value="first_half">Morning Half</option>
                                    <option value="second_half">Second Half</option>
                                </select>
                    </div>
                </div>
                        <div class="detail-item" style="grid-column:1/-1">
                            <div class="label">Reason</div>
                            <textarea id="editReason" class="form-textarea" rows="3" required></textarea>
                </div>
            </div>
                    <div style="display:flex; justify-content:flex-end; gap:.5rem; margin-top:12px;">
                        <button type="button" class="btn btn-secondary" id="editCancelBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
                </div>
                </div>
            </div>

    <!-- Delete Confirm Modal -->
    <div id="deleteModalBackdrop" class="modal-backdrop">
        <div class="modal" id="deleteModal">
            <div class="modal-header">
                <div class="modal-title">Delete Leave Request</div>
                <button class="modal-close" id="deleteModalClose">×</button>
            </div>
            <div class="modal-body">
                <div id="deleteSummary" class="detail-grid" style="margin-bottom:12px;">
                    <div class="detail-item"><div class="label">Request ID</div><div class="value" id="delId">-</div></div>
                    <div class="detail-item"><div class="label">Date</div><div class="value" id="delRange">-</div></div>
                    <div class="detail-item"><div class="label">Leave Type</div><div class="value" id="delType">-</div></div>
                    <div class="detail-item"><div class="label">Duration</div><div class="value" id="delDuration">-</div></div>
                    <div class="detail-item"><div class="label">Status</div><div class="value" id="delStatus">-</div></div>
                    <div class="detail-item"><div class="label">Manager Status</div><div class="value" id="delMgr">-</div></div>
                    <div class="detail-item" style="grid-column:1/-1"><div class="label">Reason</div><div class="value" id="delReason">-</div></div>
                </div>
                <div class="note" style="margin-top:6px;">Are you sure you want to delete this pending leave request? This action cannot be undone.</div>
                <div style="display:flex; justify-content:flex-end; gap:.5rem; margin-top:12px;">
                    <button type="button" class="btn btn-secondary" id="deleteCancelBtn">Cancel</button>
                    <button type="button" class="btn btn-primary" id="deleteConfirmBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get leave types from the database
        let leaveTypes = [];
        let leaveTypeMap = {}; // Map of name to id
        let leaveBalances = [];
        let leavePolicy = {};
        let userShift = null; // Store user's shift information
        
        // Fetch user's shift information
        async function fetchUserShift() {
            try {
                const response = await fetch('ajax_handlers/get_user_shift.php');
                if (!response.ok) throw new Error('Failed to fetch shift information');
                const data = await response.json();
                
                if (data.success && data.shift) {
                    userShift = data;
                    return data;
                }
                return null;
            } catch (error) {
                console.error('Error fetching shift information:', error);
                return null;
            }
        }
        
        // Fetch leave types and balances on page load
        async function fetchLeaveData() {
            try {
                // Fetch user's shift information
                await fetchUserShift();
                
                // First fetch all leave types
                const typesResponse = await fetch('ajax_handlers/get_leave_types.php');
                if (!typesResponse.ok) throw new Error('Failed to fetch leave types');
                const typesData = await typesResponse.json();
                
                if (typesData.success && typesData.leave_types) {
                    // Store leave types globally
                    leaveTypes = typesData.leave_types.map(lt => lt.name);
                    
                    // Create a map of leave type names to IDs
                    typesData.leave_types.forEach(lt => {
                        leaveTypeMap[lt.name] = lt.id;
                    });
                    
                    // Store policy information
                    if (typesData.policy) {
                        leavePolicy = typesData.policy;
                    }
                    
                    // Fetch balance for each leave type
                    const balancePromises = typesData.leave_types.map(async (leaveType) => {
                        try {
                            const response = await fetch(`ajax_handlers/fetch_leave_history_modal_v1.php?leave_type_id=${leaveType.id}`);
                            if (!response.ok) return null;
                            
                            const data = await response.json();
                            if (!data.success) return null;
                            
                            return {
                                id: leaveType.id,
                                key: leaveType.name,
                                total: data.summary.max_days || 0,
                                used: data.summary.total_used || 0,
                                remaining: data.summary.remaining,
                                unlimited: data.summary.is_unlimited,
                                isCompensate: leaveType.name.toLowerCase().includes('compensate') || 
                                              leaveType.name.toLowerCase().includes('comp off'),
                                isCasual: leaveType.name.toLowerCase().includes('casual')
                            };
                        } catch (err) {
                            console.error(`Error fetching balance for ${leaveType.name}:`, err);
                            return null;
                        }
                    });
                    
                    // Wait for all balance requests to complete
                    const results = await Promise.all(balancePromises);
                    leaveBalances = results.filter(r => r !== null);
                    
                    // Render the leave bank with real data
                    renderLeaveBank();
                    
                    // Add policy information to the UI
                    updatePolicyInfo();
                }
            } catch (error) {
                console.error('Error fetching leave data:', error);
                // Fallback to default leave types if fetch fails
                leaveTypes = [
                    'Casual Leave',
                    'Compensate Leave',
                    'Emergency Leave',
                    'Half Day Leave',
                    'Maternity Leave',
                    'Paternity Leave',
                    'Short Leave',
                    'Sick Leave',
                    'Unpaid Leave'
                ];
                
                // Use fallback mock data for balances
                leaveBalances = [
                    { key: 'Casual Leave', total: 12, used: 4, isCasual: true },
                    { key: 'Compensate Leave', total: 6, used: 3, isCompensate: true },
                    { key: 'Emergency Leave', total: 3, used: 0 },
                    { key: 'Half Day Leave', total: null, used: 2, unlimited: true },
                    { key: 'Maternity Leave', total: 60, used: 2 },
                    { key: 'Paternity Leave', total: 7, used: 0 },
                    { key: 'Short Leave', total: 2, used: 3 },
                    { key: 'Sick Leave', total: 6, used: 0 },
                    { key: 'Unpaid Leave', total: null, used: 0, unlimited: true }
                ];
                
                // Mock policy
                leavePolicy = {
                    casual_leave_monthly_limit: 2,
                    casual_leave_used_this_month: 1,
                    compensate_leave_balance: 3
                };
                
                renderLeaveBank();
                updatePolicyInfo();
            }
        }
        
        // Update UI with policy information
        function updatePolicyInfo() {
            const noteDiv = document.querySelector('.note.muted');
            if (noteDiv) {
                // Update policy information
                const casualUsed = leavePolicy.casual_leave_used_this_month || 0;
                const casualLimit = leavePolicy.casual_leave_monthly_limit || 2;
                const casualRemaining = Math.max(0, casualLimit - casualUsed);
                
                const compBalance = leavePolicy.compensate_leave_balance || 0;
                
                noteDiv.innerHTML = `
                    <div>Balances include approved and pending requests for ${new Date().getFullYear()}. Rejected leaves do not reduce your balance.</div>
                    <div>Policy: Casual Leave – up to ${casualLimit} per month (${casualRemaining} remaining this month); Short Leave – up to 2 per month.</div>
                    ${compBalance > 0 
                        ? `<div><strong>Note:</strong> You have ${compBalance} Compensate Leave days available. These will be used first when applying for leave.</div>` 
                        : `<div><strong>Note:</strong> When Compensate Leave is exhausted, Unpaid Leave will be used automatically.</div>`
                    }
                `;
            }
        }

        

        // Allow selecting dates up to 15 days in the past
        const _todayObj = new Date();
        const _earliestObj = new Date();
        _earliestObj.setDate(_todayObj.getDate() - 16);
        const today = _todayObj.toISOString().split('T')[0];
        const earliestISO = _earliestObj.toISOString().split('T')[0];
        document.getElementById('fromDate').setAttribute('min', earliestISO);
        document.getElementById('toDate').setAttribute('min', earliestISO);

        // Update toDate minimum when fromDate changes
        document.getElementById('fromDate').addEventListener('change', function() {
            const fromDate = this.value;
            const toDateInput = document.getElementById('toDate');
            toDateInput.setAttribute('min', fromDate);
            
            // Clear toDate if it's before the new fromDate
            if (toDateInput.value && toDateInput.value < fromDate) {
                toDateInput.value = '';
            }
        });

        // Generate dates
        document.getElementById('generateDates').addEventListener('click', () => {
            const from = document.getElementById('fromDate').value;
            const to = document.getElementById('toDate').value;
            if (!from || !to) { alert('Select both From and To dates.'); return; }
            if (new Date(from) > new Date(to)) { alert('From date cannot be later than To date.'); return; }
            if (from < earliestISO || to < earliestISO) { alert('You can only apply for leave up to the past 15 days.'); return; }
            renderDatesTable(from, to);
        });

        

        // Form submission handler
        document.getElementById('leaveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Collect form data
            const formData = {
                approver_id: document.getElementById('approver').value,
                reason: document.getElementById('reason').value,
                start_date: document.getElementById('fromDate').value,
                end_date: document.getElementById('toDate').value,
                leave_type: null,
                leave_type_name: null,
                dates: [],
                duration: 0
            };
            
            // Basic validation
            if (!formData.approver_id || !formData.reason || !formData.start_date || !formData.end_date) {
                alert('Please fill in all required fields.');
                return;
            }
            
            if (new Date(formData.start_date) > new Date(formData.end_date)) {
                alert('From date cannot be later than To date.');
                return;
            }
            // Enforce past-15-days window
            if (formData.start_date < earliestISO || formData.end_date < earliestISO) {
                alert('You can only apply for leave up to the past 15 days.');
                return;
            }
            
            // Get selected dates and their details
            const rows = document.querySelectorAll('#datesTbody tr');
            if (!rows.length) {
                alert('Please generate date list first.');
                return;
            }

            let totalDuration = 0;
            const dates = [];
            
            rows.forEach(row => {
                const checkbox = row.querySelector('.row-check');
                if (checkbox && checkbox.checked) {
                    // Use the ISO date directly from the data attribute to avoid timezone issues
                    const isoDate = row.cells[1].getAttribute('data-iso-date') || '';
                    // Fallback to the parsing method if data-iso-date is not available
                    if (!isoDate) {
                        // Fix for date conversion issue - use the original date string directly
                        // instead of converting through Date object which can cause timezone issues
                        const dateCellText = row.cells[1].textContent; // human date string
                        // Extract the date in YYYY-MM-DD format directly from the date string
                        // This avoids timezone conversion issues with toISOString()
                        const dateMatch = dateCellText.match(/\w{3} (\w{3}) (\d{1,2}) (\d{4})/);
                        let isoDate = '';
                        if (dateMatch) {
                            const [, month, day, year] = dateMatch;
                            // Convert month name to month number
                            const months = {
                                'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
                                'May': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
                                'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
                            };
                            const monthNum = months[month] || '01';
                            const dayPadded = day.padStart(2, '0');
                            isoDate = `${year}-${monthNum}-${dayPadded}`;
                        } else {
                            // Fallback to original method if regex doesn't match
                            const dateObj = new Date(dateCellText);
                            isoDate = dateObj.toISOString().split('T')[0];
                        }
                    }
                    
                    const leaveTypeName = row.querySelector('.row-leave-type').value;
                    const leaveTypeId = leaveTypeMap[leaveTypeName] || parseInt(leaveTypeName, 10) || null;
                    const dayType = row.querySelector('.row-day-type').value;
                    
                    // Calculate duration based on day type
                    let duration = 1; // Full day default
                    if (leaveTypeName.toLowerCase().includes('short')) {
                        duration = 0.25; // Short leave
                    } else if (dayType === 'Half Day' || dayType === 'Morning Half' || dayType === 'Second Half') {
                        duration = 0.5; // Half day
                    } else if (dayType === 'Morning' || dayType === 'Evening') {
                        duration = 0.5; // Morning/Evening
                    }

                    totalDuration += duration;
                    dates.push({
                        date: isoDate,
                        dayType,
                        duration,
                        leave_type_id: leaveTypeId,
                        leave_type_name: leaveTypeName
                    });

                    // Store the first leave type id as default for payload
                    if (!formData.leave_type) {
                        formData.leave_type = leaveTypeId;
                        formData.leave_type_name = leaveTypeName;
                    }
                }
            });

            if (!dates.length) {
                alert('Please select at least one date.');
                return;
            }
            
            formData.dates = dates;
            formData.duration = totalDuration;

            try {
                // Debug log the form data
                console.log('Submitting form data:', formData);
                
                const response = await fetch('ajax_handlers/save_leave_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    alert('Leave request submitted successfully!');
                    // Reset form
                    document.getElementById('leaveForm').reset();
                    document.getElementById('datesTbody').innerHTML = '<tr><td colspan="5" class="muted">No dates generated yet</td></tr>';
                    document.getElementById('calculatedDuration').textContent = '0 day';
                    // Refresh leave balances
                    fetchLeaveData();
                    // Auto-refresh the page after alert is dismissed
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(result.message || 'Failed to submit leave request');
                }
            } catch (error) {
                console.error('Error:', error);
                alert(error.message || 'An error occurred while submitting the leave request');
            }
        });

        // Reset form function
        function resetForm() {
            document.getElementById('leaveForm').reset();
            document.getElementById('datesTbody').innerHTML = '<tr><td colspan="5" class="muted">No dates generated yet</td></tr>';
            document.getElementById('calculatedDuration').textContent = '0 day';
        }

        // Helpers
        function formatDateHuman(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' });
        }

        function eachDate(from, to) {
            const dates = [];
            let d = new Date(from + 'T00:00:00');
            const end = new Date(to + 'T00:00:00');
            const formatLocalISO = (dt) => {
                const y = dt.getFullYear();
                const m = String(dt.getMonth() + 1).padStart(2, '0');
                const day = String(dt.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            };
            while (d <= end) {
                const iso = formatLocalISO(d); // local time, not UTC
                dates.push(iso);
                d.setDate(d.getDate() + 1);
            }
            return dates;
        }

        function isFourthSaturday(isoDate) {
            const d = new Date(isoDate + 'T00:00:00');
            if (d.getDay() !== 6) return false; // 6 = Saturday
            const nth = Math.floor((d.getDate() - 1) / 7) + 1;
            return nth === 4;
        }

        function renderDatesTable(from, to) {
            const tbody = document.getElementById('datesTbody');
            const dates = eachDate(from, to);
            if (!dates.length) { tbody.innerHTML = '<tr><td colspan="5" class="muted">No dates generated</td></tr>'; return; }
            tbody.innerHTML = '';
            
            // Use current leave types or fallback to default types if not yet loaded
            const currentLeaveTypes = leaveTypes.length > 0 ? leaveTypes : [
                'Casual Leave',
                'Compensate Leave',
                'Emergency Leave',
                'Half Day Leave',
                'Maternity Leave',
                'Paternity Leave',
                'Short Leave',
                'Sick Leave',
                'Unpaid Leave'
            ];
            
            // Find compensate leave and casual leave in balances
            const compLeave = leaveBalances.find(lb => lb.isCompensate);
            const casualLeave = leaveBalances.find(lb => lb.isCasual);
            
            // Check if we have compensate leave balance
            const compBalance = leavePolicy.compensate_leave_balance || 0;
            const casualUsed = leavePolicy.casual_leave_used_this_month || 0;
            const casualLimit = leavePolicy.casual_leave_monthly_limit || 2;
            const casualRemaining = Math.max(0, casualLimit - casualUsed);
            
            // Group dates by month to track casual leave usage
            const datesByMonth = {};
            dates.forEach(date => {
                const month = new Date(date).getMonth() + 1; // 1-12
                if (!datesByMonth[month]) datesByMonth[month] = [];
                datesByMonth[month].push(date);
            });
            
            // Track how many casual leaves we're using per month
            const casualLeaveByMonth = {};
            Object.keys(datesByMonth).forEach(month => {
                // If this is the current month, start with what's already used
                casualLeaveByMonth[month] = month == leavePolicy.current_month ? 
                    casualUsed : 0;
            });
            
            // Determine default leave type based on policy
            let defaultLeaveType = 'Casual Leave'; // Default
            let compLeaveUsed = 0;
            
            // Find unpaid leave type
            const unpaidLeaveType = leaveTypes.find(lt => 
                lt.toLowerCase().includes('unpaid')
            );
            
            // If we have compensate leave balance, use it first
            if (compBalance > 0 && compLeave) {
                defaultLeaveType = compLeave.key;
            } else if (unpaidLeaveType) {
                // When compensate leave is exhausted, use unpaid leave
                defaultLeaveType = unpaidLeaveType;
            } else if (casualRemaining <= 0) {
                // If we've used all casual leaves this month, default to another type
                defaultLeaveType = leaveTypes.find(lt => 
                    !lt.toLowerCase().includes('casual') && 
                    !lt.toLowerCase().includes('comp')
                ) || 'Sick Leave';
            }
            
            let skippedFourthSaturdays = [];
            for (const date of dates) {
                if (isFourthSaturday(date)) {
                    skippedFourthSaturdays.push(date);
                    // no longer skipping; allow rendering
                }
                const tr = document.createElement('tr');
                const dateObj = new Date(date);
                const month = dateObj.getMonth() + 1; // 1-12
                
                // Determine appropriate leave type for this date
                let recommendedType = defaultLeaveType;
                
                // If using compensate leave and we've used all the balance
                if (recommendedType === compLeave?.key && compLeaveUsed >= compBalance) {
                    // Switch to Unpaid Leave when compensate leave is exhausted
                    const unpaidLeaveType = leaveTypes.find(lt => 
                        lt.toLowerCase().includes('unpaid')
                    );
                    
                    if (unpaidLeaveType) {
                        recommendedType = unpaidLeaveType;
                    } else {
                        // Fallback to casual if unpaid not available and we have remaining casual leaves
                        if (casualRemaining > 0) {
                            recommendedType = casualLeave?.key || 'Casual Leave';
                        } else {
                            recommendedType = leaveTypes.find(lt => 
                                !lt.toLowerCase().includes('casual') && 
                                !lt.toLowerCase().includes('comp')
                            ) || 'Sick Leave';
                        }
                    }
                }
                
                // If using casual leave and we've hit the monthly limit for this month
                if (recommendedType === casualLeave?.key && casualLeaveByMonth[month] >= casualLimit) {
                    // Switch to Unpaid Leave when casual leave limit is reached
                    const unpaidLeaveType = leaveTypes.find(lt => 
                        lt.toLowerCase().includes('unpaid')
                    );
                    
                    if (unpaidLeaveType) {
                        recommendedType = unpaidLeaveType;
                    } else {
                        // Fallback to another type if unpaid not available
                        recommendedType = leaveTypes.find(lt => 
                            !lt.toLowerCase().includes('casual') && 
                            !lt.toLowerCase().includes('comp')
                        ) || 'Sick Leave';
                    }
                }
                
                // Track usage
                if (recommendedType === compLeave?.key) {
                    compLeaveUsed++;
                } else if (recommendedType === casualLeave?.key) {
                    casualLeaveByMonth[month]++;
                }
                
                // Policy flags for locking the select
                const shouldLockAutoType = (recommendedType === (casualLeave?.key || 'Casual Leave'));
                const lockReason = 'Casual Leave auto-selected by policy';

                tr.innerHTML = `
                    <td><input type="checkbox" class="row-check" checked></td>
                    <td data-iso-date="${date}">${dateObj.toDateString()}</td>
                    <td>${dateObj.toLocaleDateString(undefined, { weekday: 'long' })}</td>
                    <td>
                        <select class="form-select row-leave-type" data-date="${date}" data-month="${month}" ${shouldLockAutoType ? 'data-locked="1" title="'+lockReason+'"' : ''}>
                            ${currentLeaveTypes.map(t => {
                                const isCasual = t.toLowerCase().includes('casual');
                                const isComp = t === (compLeave?.key || 'Compensate Leave');
                                const disabledByPolicy = (compBalance > 0 && isCasual); // only casual blocked when comp remains; short leave stays allowed
                                const disabledByCompCap = (compBalance > 0 && compLeaveUsed >= compBalance && isComp);
                                const selected = t === recommendedType ? 'selected' : '';
                                const disabledAttr = (disabledByPolicy || disabledByCompCap) ? 'disabled' : '';
                                const reasonNote = disabledByPolicy
                                    ? ' (disabled by policy)'
                                    : (disabledByCompCap ? ' (balance exhausted)' : '');
                                return `<option value="${t}" ${selected} ${disabledAttr}>${t}${reasonNote}</option>`;
                            }).join('')}
                        </select>
                    </td>
                    <td>
                        <select class="form-select row-day-type" data-leave-type="${recommendedType}">
                            ${recommendedType.toLowerCase().includes('short') && userShift && userShift.short_leave_slots ? `
                                <option value="Morning">Morning (${userShift.short_leave_slots.morning.start} - ${userShift.short_leave_slots.morning.end})</option>
                                <option value="Evening">Evening (${userShift.short_leave_slots.evening.start} - ${userShift.short_leave_slots.evening.end})</option>
                            ` : recommendedType.toLowerCase().includes('half') ? `
                                <option value="Morning Half">Morning Half</option>
                                <option value="Second Half">Second Half</option>
                            ` : `
                                <option value="Full Day">Full Day</option>
                                <option value="Half Day">Half Day</option>
                            `}
                        </select>
                    </td>
                `;
                tbody.appendChild(tr);
            }
            if (skippedFourthSaturdays.length > 0) {
                const info = document.createElement('tr');
                const humanList = skippedFourthSaturdays.map(d => new Date(d+'T00:00:00').toDateString()).join(', ');
                info.innerHTML = `<td colspan="5" class="muted">Warning: You are applying leave on 4th Saturday (or range includes it). Dates: ${humanList}. If not approved, 3 days salary will be deducted.</td>`;
                tbody.appendChild(info);
            }
            recalcDuration();

            // Bind events
            tbody.querySelectorAll('.row-day-type, .row-check').forEach(el => el.addEventListener('change', recalcDuration));
            
            // Add special handling for leave type changes
            tbody.querySelectorAll('.row-leave-type').forEach(el => {
                el.addEventListener('change', function(e) {
                    const select = e.target;
                    const selectedValue = select.value;
                    const month = select.dataset.month;
                    const row = select.closest('tr');
                    const dayTypeSelect = row.querySelector('.row-day-type');

                    // Prevent changing auto-assigned Compensate/Casual rows
                    if (select.getAttribute('data-locked') === '1') {
                        e.preventDefault();
                        // restore selected
                        const current = Array.from(select.options).find(o => o.selected);
                        if (current) select.value = current.value;
                        // Only casual auto-rows are locked
                        alert('This casual leave is auto-selected by policy and cannot be changed.');
                        return;
                    }
                    
                    // Block selecting Compensate beyond available balance
                    if (selectedValue === (compLeave?.key || 'Compensate Leave')) {
                        const currentCompCount = Array.from(document.querySelectorAll('.row-leave-type'))
                            .filter(s => s !== select && s.value === (compLeave?.key || 'Compensate Leave')).length;
                        if (compBalance <= 0 || currentCompCount >= compBalance) {
                            alert('All available Compensate Leave is already allocated in your selection. Switching to Unpaid Leave.');
                            const unpaid = unpaidLeaveType || leaveTypes.find(lt => lt.toLowerCase().includes('unpaid'));
                            if (unpaid) {
                                select.value = unpaid;
                            } else {
                                const prev = Array.from(select.options).find(o => o.defaultSelected) || select.options[0];
                                select.value = prev.value;
                            }
                            updateDayTypeOptions(dayTypeSelect, select.value);
                            recalcDuration();
                return;
                        }
                    }
                    
                    // Update day type options based on selected leave type
                    updateDayTypeOptions(dayTypeSelect, selectedValue);
                    
                    // Check if selecting casual leave would exceed monthly limit
                    if (selectedValue === casualLeave?.key) {
                        // Also block casual if compensate remains
                        if (compBalance > 0) {
                            alert('You have Compensate Leave remaining. Casual Leave cannot be used now. Switching to Unpaid Leave.');
                            // Switch to Unpaid if available; otherwise revert to previous
                            const unpaid = unpaidLeaveType || leaveTypes.find(lt => lt.toLowerCase().includes('unpaid'));
                            if (unpaid) {
                                select.value = unpaid;
                            } else {
                                const prev = Array.from(select.options).find(o => o.defaultSelected) || select.options[0];
                                select.value = prev.value;
                            }
                            updateDayTypeOptions(dayTypeSelect, select.value);
                            recalcDuration();
                return;
            }
                        // Count how many casual leaves are already selected for this month
                        const casualCount = Array.from(document.querySelectorAll(`.row-leave-type[data-month="${month}"]`))
                            .filter(s => s !== select && s.value === casualLeave?.key)
                            .length;
                        
                        // Add current month's already used casual leaves
                        const totalCasualForMonth = casualCount + 
                            (month == leavePolicy.current_month ? casualUsed : 0);
                            
                        if (totalCasualForMonth >= casualLimit) {
                            alert(`You've reached the limit of ${casualLimit} casual leaves for this month. Switching to Unpaid Leave.`);
                            // Switch to Unpaid if available
                            const unpaid = unpaidLeaveType || leaveTypes.find(lt => lt.toLowerCase().includes('unpaid'));
                            if (unpaid) {
                                select.value = unpaid;
                            } else {
                                const prev = Array.from(select.options).find(o => o.defaultSelected) || select.options[0];
                                select.value = prev.value;
                            }
                            // Update day type options again after changing leave type
                            updateDayTypeOptions(dayTypeSelect, select.value);
                        }
                    }
                    
                    recalcDuration();
                });
            });
            
            // Function to update day type options based on leave type
            function updateDayTypeOptions(dayTypeSelect, leaveType) {
                const lt = leaveType.toLowerCase();
                const isShortLeave = lt.includes('short');
                const isHalfDayLeaveType = lt.includes('half') && !lt.includes('short');
                const currentValue = dayTypeSelect.value;
                let options = '';
                
                // Add morning/evening options for Short Leave
                if (isShortLeave && userShift && userShift.short_leave_slots) {
                    const morning = userShift.short_leave_slots.morning;
                    const evening = userShift.short_leave_slots.evening;
                    
                    options += `
                        <option value="Morning">Morning (${morning.start} - ${morning.end})</option>
                        <option value="Evening">Evening (${evening.start} - ${evening.end})</option>
                    `;
                } else if (isHalfDayLeaveType) {
                    options += `
                        <option value="Morning Half">Morning Half</option>
                        <option value="Second Half">Second Half</option>
                    `;
                } else {
                    options += `
                        <option value="Full Day">Full Day</option>
                        <option value="Half Day">Half Day</option>
                    `;
                }
                
                dayTypeSelect.innerHTML = options;
                
                // Try to preserve the previous selection if it's still valid
                if (currentValue && Array.from(dayTypeSelect.options).some(opt => opt.value === currentValue)) {
                    dayTypeSelect.value = currentValue;
                } else if (isShortLeave) {
                    // Default to Morning for Short Leave
                    dayTypeSelect.value = 'Morning';
                } else if (isHalfDayLeaveType) {
                    // Default to Morning Half for Half Day leave type
                    dayTypeSelect.value = 'Morning Half';
                }
                
                // Update the data-leave-type attribute
                dayTypeSelect.setAttribute('data-leave-type', leaveType);
            }
            
            document.getElementById('checkAll').checked = true;
        }

        function recalcDuration() {
            const rows = Array.from(document.querySelectorAll('#datesTbody tr'));
            if (!rows.length) { document.getElementById('calculatedDuration').textContent = '0 day'; return; }
            let total = 0;
            rows.forEach(r => {
                const checked = r.querySelector('.row-check');
                if (checked && checked.checked) {
                    const dayTypeSelect = r.querySelector('.row-day-type');
                    const dayType = dayTypeSelect?.value || 'Full Day';
                    const leaveTypeSelect = r.querySelector('.row-leave-type');
                    const leaveType = leaveTypeSelect?.value || '';
                    
                    // Calculate duration based on leave type and day type
                    if (leaveType.toLowerCase().includes('short')) {
                        // Short leave is counted as 0.25 days (2 hours out of 8-hour workday)
                        total += 0.25;
                    } else if (dayType === 'Half Day' || dayType === 'Morning Half' || dayType === 'Second Half') {
                        total += 0.5;
                    } else if (dayType === 'Morning' || dayType === 'Evening') {
                        // Morning/Evening options for non-short leave types (if applicable)
                        total += 0.5;
                    } else {
                        total += 1; // Full Day
                    }
                }
            });
            document.getElementById('calculatedDuration').textContent = `${total} ${total === 1 ? 'day' : 'days'}`;
        }

        // Leave bank rendering
        function renderLeaveBank() {
            const container = document.getElementById('leaveBank');
            container.innerHTML = '';
            
            if (leaveBalances.length === 0) {
                container.innerHTML = '<div class="muted">Loading leave balances...</div>';
                return;
            }
            
            leaveBalances.forEach(item => {
                const percent = item.total ? Math.min(100, Math.round(((item.total - item.used) / item.total) * 100)) : 100;
                const wrapper = document.createElement('div');
                wrapper.className = 'balance-item';
                
                let rightText;
                if (item.unlimited) {
                    rightText = 'No limit';
                } else if (typeof item.remaining === 'string' && item.remaining.toLowerCase() === 'unlimited') {
                    rightText = 'No limit';
                } else {
                    rightText = `${item.remaining} days`;
                }
                
                // Add policy usage info
                let policyInfo = '';
                if (item.isCasual && leavePolicy.casual_leave_monthly_limit) {
                    policyInfo = ` (used ${leavePolicy.casual_leave_used_this_month || 0}/${leavePolicy.casual_leave_monthly_limit} this month)`;
                } else if (item.isCompensate && leavePolicy.compensate_leave_balance) {
                    policyInfo = ` (used ${leavePolicy.compensate_leave_balance - item.remaining}/${leavePolicy.compensate_leave_balance} this month)`;
                }
                
                // Set icon and color based on leave type
                let icon = '';
                let color = '#3b82f6';
                
                if (item.isCasual) {
                    icon = '🏖️';
                    color = '#f59e0b';
                } else if (item.isCompensate) {
                    icon = '🔄';
                    color = '#10b981';
                } else if (item.key.toLowerCase().includes('sick')) {
                    icon = '🤒';
                    color = '#ef4444';
                } else if (item.key.toLowerCase().includes('maternity')) {
                    icon = '🤰';
                    color = '#ec4899';
                } else if (item.key.toLowerCase().includes('paternity')) {
                    icon = '👨‍👦';
                    color = '#3b82f6';
                } else if (item.key.toLowerCase().includes('emergency')) {
                    icon = '🚨';
                    color = '#f97316';
                } else {
                    icon = '📅';
                }
                
                wrapper.innerHTML = `
                    <div class="balance-title"><span>${icon} ${item.key}</span><span class="muted">${rightText}${policyInfo}</span></div>
                    <div class="progress"><span style="width:${100 - percent}%; background:${color}"></span></div>
                `;
                container.appendChild(wrapper);
            });
        }

        // Update the dates table to use the fetched leave types
        function updateLeaveTypeOptions() {
            if (leaveTypes.length > 0) {
                const selects = document.querySelectorAll('.row-leave-type');
                selects.forEach(select => {
                    const currentValue = select.value;
                    select.innerHTML = leaveTypes.map(t => `<option value="${t}">${t}</option>`).join('');
                    
                    // Try to restore previous value if it exists in the new options
                    if (leaveTypes.includes(currentValue)) {
                        select.value = currentValue;
                    }
                });
            }
        }

        // Init - fetch data from server
        fetchLeaveData();
        // Seed month/year selectors
        (function seedHistoryFilters(){
            const ySel = document.getElementById('historyYear');
            const thisYear = new Date().getFullYear();
            let opts = '<option value="">All Years</option>';
            for (let y = thisYear; y >= thisYear - 5; y--) {
                opts += `<option value="${y}">${y}</option>`;
            }
            ySel.innerHTML = opts;
            document.getElementById('historyMonth').value = String(new Date().getMonth()+1);
            document.getElementById('historyYear').value = String(thisYear);
        })();

        loadLeaveHistory();
        
        // Add event listener to update leave type options when dates are generated
        document.getElementById('generateDates').addEventListener('click', function() {
            // This will run after renderDatesTable completes
            setTimeout(updateLeaveTypeOptions, 100);
        });

        // Fetch and render leave history table for current user
        async function loadLeaveHistory() {
            const body = document.getElementById('leaveHistoryBody');
            try {
                const m = document.getElementById('historyMonth').value;
                const y = document.getElementById('historyYear').value;
                const qs = new URLSearchParams();
                if (m) qs.set('month', m);
                if (y) qs.set('year', y);
                const res = await fetch('ajax_handlers/get_leave_history.php' + (qs.toString() ? `?${qs.toString()}` : ''));
                if (!res.ok) throw new Error('Failed to fetch history');
                const data = await res.json();
                if (!data.success || !Array.isArray(data.rows) || data.rows.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="muted">No leave history found.</td></tr>';
                    return;
                }
                const rows = data.rows.map(r => {
                    const range = (r.end_date && r.end_date !== r.start_date)
                        ? `${r.start_date} → ${r.end_date}`
                        : (r.start_date || '-');
                    const type = r.leave_type_name || r.leave_type || '-';
                    const color = (r.leave_color && /^#?[0-9a-fA-F]{6}$/.test(r.leave_color))
                        ? (r.leave_color.startsWith('#') ? r.leave_color : `#${r.leave_color}`)
                        : '#e5e7eb';
                    const dur = (r.duration !== undefined && r.duration !== null) ? r.duration : '-';
                    const status = r.status || '-';
                    const mgr = r.manager_approval ? String(r.manager_approval) : 'No Action taken';
                    const reason = r.reason ? String(r.reason).replace(/</g,'&lt;') : '-';
                    const rowClass = status === 'approved' ? 'row-approved' : (status === 'rejected' ? 'row-rejected' : (!r.manager_approval ? 'row-noaction' : ''));
                    const mgrPillBg = mgr === 'No Action taken' ? '#fde68a' : '#e5e7eb';
                    const canModify = !(status === 'approved' || status === 'rejected');
                    return `<tr class="${rowClass}" data-id="${r.id}" data-status="${status}">
                        <td>${range}</td>
                        <td><span class="badge-type" style="background:${color}1a; border-color:${color}33; color:#0f172a">${type}</span></td>
                        <td>${dur}</td>
                        <td><span class="status-pill" style="background:${status==='approved' ? '#bbf7d0' : status==='rejected' ? '#fecaca' : '#e5e7eb'}">${status}</span></td>
                        <td><span class="status-pill" style="background:${mgrPillBg}">${mgr}</span></td>
                        <td>${reason}</td>
                        <td>
                            <div class="icon-actions">
                                <button class="icon-btn view" title="View" data-action="view">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="icon-btn edit ${canModify ? '' : 'disabled'}" title="${canModify ? 'Edit' : 'Edit disabled for non-pending'}" data-action="edit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </button>
                                <button class="icon-btn delete ${canModify ? '' : 'disabled'}" title="${canModify ? 'Delete' : 'Delete disabled for non-pending'}" data-action="delete">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
            </div>
                        </td>
                    </tr>`;
                }).join('');
                body.innerHTML = rows;
            } catch (e) {
                console.error('History load error:', e);
                body.innerHTML = '<tr><td colspan="7" class="muted">Failed to load history.</td></tr>';
            }
        }

        // Edit modal logic - defined before event handlers
        const editBackdrop = document.getElementById('editModalBackdrop');
        const editModal = document.getElementById('editModal');
        const editClose = document.getElementById('editModalClose');
        const editForm = document.getElementById('editLeaveForm');
        const editCancelBtn = document.getElementById('editCancelBtn');
        function showEdit(){ editBackdrop.style.display='flex'; requestAnimationFrame(()=> editModal.classList.add('show')); }
        function hideEdit(){ editModal.classList.remove('show'); setTimeout(()=> editBackdrop.style.display='none', 150); }
        editClose.addEventListener('click', hideEdit);
        editBackdrop.addEventListener('click', (e)=>{ if(e.target===editBackdrop) hideEdit(); });
        editCancelBtn.addEventListener('click', hideEdit);

        async function ensureLeaveTypesLoaded(){
            if (Array.isArray(leaveTypes) && leaveTypes.length > 0 && Object.keys(leaveTypeMap||{}).length > 0) return;
            const typesResponse = await fetch('ajax_handlers/get_leave_types.php');
            const typesData = await typesResponse.json();
            leaveTypes = (typesData.leave_types || []).map(lt => lt.name);
            leaveTypeMap = {};
            (typesData.leave_types || []).forEach(lt => leaveTypeMap[lt.name] = lt.id);
        }

        async function openEditModal(id){
            try{
                await ensureLeaveTypesLoaded();
                // Fetch details
                const res = await fetch('ajax_handlers/get_leave_history.php?id=' + encodeURIComponent(id));
                const data = await res.json();
                if(!data.success || !data.row) throw new Error('Failed to load');
                const r = data.row;
                
                // Fill form
                document.getElementById('editLeaveId').value = r.id;
                document.getElementById('editStartDate').value = r.start_date || '';
                document.getElementById('editEndDate').value = r.end_date || r.start_date || '';
                document.getElementById('editReason').value = r.reason || '';
                
                // Apply policy rules for leave type dropdown
                const sel = document.getElementById('editLeaveType');
                const compLeave = leaveBalances.find(lb => lb.isCompensate);
                const casualLeave = leaveBalances.find(lb => lb.isCasual);
                const compBalance = leavePolicy.compensate_leave_balance || 0;
                const casualUsed = leavePolicy.casual_leave_used_this_month || 0;
                const casualLimit = leavePolicy.casual_leave_monthly_limit || 2;
                
                // Build leave type options with policy restrictions
                let leaveTypeOptions = '';
                Object.entries(leaveTypeMap).forEach(([name, typeId]) => {
                    const isCasual = name.toLowerCase().includes('casual');
                    const isComp = name === (compLeave?.key || 'Compensate Leave');
                    
                    // Apply policy restrictions
                    const disabledByPolicy = (compBalance > 0 && isCasual); // Can't use casual if compensate available
                    const selected = (r.leave_type && parseInt(r.leave_type) === typeId) ? 'selected' : '';
                    const disabledAttr = disabledByPolicy ? 'disabled' : '';
                    const reasonNote = disabledByPolicy ? ' (disabled by policy)' : '';
                    
                    leaveTypeOptions += `<option value="${typeId}" ${selected} ${disabledAttr}>${name}${reasonNote}</option>`;
                });
                
                sel.innerHTML = leaveTypeOptions;
                
                // Set up leave type change handler with policy enforcement
                sel.removeEventListener('change', editLeaveTypeChangeHandler); // Remove any existing handler
                sel.addEventListener('change', editLeaveTypeChangeHandler);
                
                // Handle half day checkbox and dropdown
                const halfChk = document.getElementById('editHalfDay');
                const halfSel = document.getElementById('editHalfDayType');
                const isHalf = r.duration_type === 'first_half' && (r.day_type==='first_half' || r.day_type==='second_half');
                halfChk.checked = !!isHalf;
                halfSel.style.display = isHalf ? 'block' : 'none';
                if (isHalf) {
                    halfSel.value = r.day_type || 'first_half';
                }
                
                // Update half day options based on current leave type
                updateEditHalfDayOptions(sel.value);
                
                halfChk.removeEventListener('change', editHalfDayChangeHandler);
                halfChk.addEventListener('change', editHalfDayChangeHandler);
                
                showEdit();
            }catch(err){
                alert(err.message || 'Failed to open editor');
            }
        }
        
        function editLeaveTypeChangeHandler(e) {
            const select = e.target;
            const selectedValue = select.value;
            const selectedName = Object.keys(leaveTypeMap).find(name => leaveTypeMap[name] == selectedValue);
            
            // Apply policy restrictions
            const compLeave = leaveBalances.find(lb => lb.isCompensate);
            const casualLeave = leaveBalances.find(lb => lb.isCasual);
            const compBalance = leavePolicy.compensate_leave_balance || 0;
            const casualUsed = leavePolicy.casual_leave_used_this_month || 0;
            const casualLimit = leavePolicy.casual_leave_monthly_limit || 2;
            
            // Block casual leave if compensate is available
            if (selectedName && selectedName.toLowerCase().includes('casual') && compBalance > 0) {
                alert('You have Compensate Leave remaining. Casual Leave cannot be used now. Switching to Unpaid Leave.');
                const unpaidType = Object.entries(leaveTypeMap).find(([name, id]) => 
                    name.toLowerCase().includes('unpaid')
                );
                if (unpaidType) {
                    select.value = unpaidType[1];
                } else {
                    // Revert to previous value
                    const prevSelected = Array.from(select.options).find(o => o.defaultSelected);
                    if (prevSelected) select.value = prevSelected.value;
                }
                updateEditHalfDayOptions(select.value);
                return;
            }
            
            // Block compensate leave if balance is exhausted
            if (selectedName === (compLeave?.key || 'Compensate Leave') && compBalance <= 0) {
                alert('All available Compensate Leave is already used. Switching to Unpaid Leave.');
                const unpaidType = Object.entries(leaveTypeMap).find(([name, id]) => 
                    name.toLowerCase().includes('unpaid')
                );
                if (unpaidType) {
                    select.value = unpaidType[1];
                } else {
                    const prevSelected = Array.from(select.options).find(o => o.defaultSelected);
                    if (prevSelected) select.value = prevSelected.value;
                }
                updateEditHalfDayOptions(select.value);
                return;
            }
            
            // Update half day options based on leave type
            updateEditHalfDayOptions(selectedValue);
        }
        
        function updateEditHalfDayOptions(leaveTypeId) {
            const selectedName = Object.keys(leaveTypeMap).find(name => leaveTypeMap[name] == leaveTypeId);
            const halfChk = document.getElementById('editHalfDay');
            const halfSel = document.getElementById('editHalfDayType');
            
            if (!selectedName) return;
            
            const lt = selectedName.toLowerCase();
            const isShortLeave = lt.includes('short');
            const isHalfDayLeaveType = lt.includes('half') && !lt.includes('short');
            
            if (isShortLeave) {
                // For short leave, hide half day option and show time slots
                halfChk.checked = false;
                halfChk.style.display = 'none';
                halfSel.style.display = 'none';
                
                // Show short leave time slots if available
                if (userShift && userShift.short_leave_slots) {
                    const morning = userShift.short_leave_slots.morning;
                    const evening = userShift.short_leave_slots.evening;
                    halfSel.innerHTML = `
                        <option value="Morning">Morning (${morning.start} - ${morning.end})</option>
                        <option value="Evening">Evening (${evening.start} - ${evening.end})</option>
                    `;
                    halfSel.style.display = 'block';
                    halfSel.value = 'Morning';
                }
            } else if (isHalfDayLeaveType) {
                // For half day leave type, show half day options
                halfChk.style.display = 'inline-block';
                halfSel.innerHTML = `
                    <option value="first_half">Morning Half</option>
                    <option value="second_half">Second Half</option>
                `;
                if (halfChk.checked) {
                    halfSel.style.display = 'block';
                }
            } else {
                // For other leave types, show normal half day checkbox
                halfChk.style.display = 'inline-block';
                halfSel.innerHTML = `
                    <option value="first_half">Morning Half</option>
                    <option value="second_half">Second Half</option>
                `;
                if (halfChk.checked) {
                    halfSel.style.display = 'block';
                }
            }
        }
        
        function editHalfDayChangeHandler() {
            const halfChk = document.getElementById('editHalfDay');
            const halfSel = document.getElementById('editHalfDayType');
            halfSel.style.display = halfChk.checked ? 'block' : 'none';
        }

        editForm.addEventListener('submit', async (e)=>{
            e.preventDefault();
            const payload = {
                id: parseInt(document.getElementById('editLeaveId').value, 10),
                leave_type: parseInt(document.getElementById('editLeaveType').value, 10),
                start_date: document.getElementById('editStartDate').value,
                end_date: document.getElementById('editEndDate').value,
                reason: document.getElementById('editReason').value,
                half_day: document.getElementById('editHalfDay').checked ? 1 : 0,
                half_day_type: document.getElementById('editHalfDay').checked ? document.getElementById('editHalfDayType').value : null
            };
            try{
                const res = await fetch('api/update_leave_request_20250810.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Update failed');
                hideEdit();
                loadLeaveHistory();
            }catch(err){
                alert(err.message || 'Failed to update');
            }
        });

        // Action handlers (hooks)
        document.getElementById('leaveHistoryTable').addEventListener('click', (e) => {
            const btn = e.target.closest('.icon-btn');
            if (!btn) return;
            const tr = btn.closest('tr');
            const id = tr?.getAttribute('data-id');
            const status = tr?.getAttribute('data-status') || '';
            const action = btn.getAttribute('data-action');
            if (!id || !action) return;
            if (action !== 'view' && (status === 'approved' || status === 'rejected')) {
                // safety guard even if UI is disabled
                alert('This leave is not editable or deletable.');
                return;
            }
            if (action === 'view') {
                openViewModal(id);
            } else if (action === 'edit') {
                openEditModal(id);
            } else if (action === 'delete') {
                openDeleteModal(id);
            }
        });

        async function deleteLeave(id){
            try {
                const res = await fetch('api/delete_leave_request_20250810.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Delete failed');
                // Refresh history after delete
                loadLeaveHistory();
            } catch (err) {
                alert(err.message || 'Failed to delete');
            }
        }

        // Modal helpers
        const modalBackdrop = document.getElementById('viewModalBackdrop');
        const modal = document.getElementById('viewModal');
        const modalBody = document.getElementById('viewModalBody');
        const modalClose = document.getElementById('viewModalClose');
        modalClose.addEventListener('click', () => hideModal());
        modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) hideModal(); });

        function showModal(){ modalBackdrop.style.display='flex'; requestAnimationFrame(()=> modal.classList.add('show')); }
        function hideModal(){ modal.classList.remove('show'); setTimeout(()=> modalBackdrop.style.display='none', 150); }

        async function openViewModal(id){
            try {
                modalBody.innerHTML = 'Loading...';
                showModal();
                const res = await fetch('ajax_handlers/get_leave_history.php?id=' + encodeURIComponent(id));
                const data = await res.json();
                if (!data.success || !data.row) throw new Error('Failed to load');
                const r = data.row;
                const color = (r.leave_color && /^#?[0-9a-fA-F]{6}$/.test(r.leave_color))
                    ? (r.leave_color.startsWith('#') ? r.leave_color : `#${r.leave_color}`)
                    : '#e5e7eb';
                const range = (r.end_date && r.end_date !== r.start_date) ? `${r.start_date} → ${r.end_date}` : (r.start_date || '-');
                const statusPillBg = r.status==='approved' ? '#bbf7d0' : r.status==='rejected' ? '#fecaca' : '#e5e7eb';
                const mgr = r.manager_approval ? String(r.manager_approval) : 'No Action taken';
                const tf = r.time_from ? r.time_from : '-';
                const tt = r.time_to ? r.time_to : '-';
                modalBody.innerHTML = `
                    <div class="detail-grid">
                        <div class="detail-item"><div class="label">Date</div><div class="value">${range}</div></div>
                        <div class="detail-item"><div class="label">Leave Type</div><div class="value"><span class="badge-type" style="background:${color}1a; border-color:${color}33;">${r.leave_type_name || '-'}</span></div></div>
                        <div class="detail-item"><div class="label">Duration</div><div class="value">${r.duration ?? '-'}</div></div>
                        <div class="detail-item"><div class="label">Status</div><div class="value"><span class="status-pill" style="background:${statusPillBg}">${r.status || '-'}</span></div></div>
                        <div class="detail-item"><div class="label">Manager Status</div><div class="value">${mgr}</div></div>
                        <div class="detail-item"><div class="label">Duration Type</div><div class="value">${r.duration_type || '-'}</div></div>
                        <div class="detail-item"><div class="label">Day Type</div><div class="value">${r.day_type || '-'}</div></div>
                        <div class="detail-item"><div class="label">Time</div><div class="value">${tf} - ${tt}</div></div>
                        <div class="detail-item" style="grid-column:1/-1"><div class="label">Reason</div><div class="value">${(r.reason || '-').replace(/</g,'&lt;')}</div></div>
                    </div>
                `;
            } catch (err) {
                modalBody.innerHTML = '<div style="color:#dc2626;">Failed to load leave details.</div>';
            }
        }

        // Delete Confirm Modal logic - defined before event handlers
        const deleteBackdrop = document.getElementById('deleteModalBackdrop');
        const deleteModal = document.getElementById('deleteModal');
        const deleteClose = document.getElementById('deleteModalClose');
        const deleteCancelBtn = document.getElementById('deleteCancelBtn');
        const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        function showDelete(){ deleteBackdrop.style.display='flex'; requestAnimationFrame(()=> deleteModal.classList.add('show')); }
        function hideDelete(){ deleteModal.classList.remove('show'); setTimeout(()=> deleteBackdrop.style.display='none', 150); }
        deleteClose.addEventListener('click', hideDelete);
        deleteBackdrop.addEventListener('click', (e)=>{ if(e.target===deleteBackdrop) hideDelete(); });
        deleteCancelBtn.addEventListener('click', hideDelete);

        async function openDeleteModal(id){
            try{
                const res = await fetch('ajax_handlers/get_leave_history.php?id=' + encodeURIComponent(id));
                const data = await res.json();
                if(!data.success || !data.row) throw new Error('Failed to load');
                const r = data.row;

                document.getElementById('delId').textContent = r.id;
                document.getElementById('delRange').textContent = (r.end_date && r.end_date !== r.start_date) ? `${r.start_date} → ${r.end_date}` : (r.start_date || '-');
                document.getElementById('delType').textContent = r.leave_type_name || r.leave_type || '-';
                document.getElementById('delDuration').textContent = r.duration ?? '-';
                document.getElementById('delStatus').textContent = r.status || '-';
                document.getElementById('delMgr').textContent = r.manager_approval ? String(r.manager_approval) : 'No Action taken';
                document.getElementById('delReason').textContent = r.reason ? String(r.reason).replace(/</g,'&lt;') : '-';

                showDelete();
            }catch(err){
                alert(err.message || 'Failed to open delete confirmation');
            }
        }

        deleteConfirmBtn.addEventListener('click', async () => {
            const id = document.getElementById('delId').textContent;
            try {
                const res = await fetch('api/delete_leave_request_20250810.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Delete failed');
                hideDelete();
                loadLeaveHistory();
            } catch (err) {
                alert(err.message || 'Failed to delete');
            }
        });
 
        loadLeaveHistory();
        
        // Add click handler for history filters
        const historyApplyBtn = document.getElementById('historyApply');
        if (historyApplyBtn) {
            historyApplyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                loadLeaveHistory();
            });
        }
 
    </script>
    </div>
</body>
</html>