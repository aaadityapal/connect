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
    <title>Food Reimbursement | Connect</title>
    <meta name="description" content="Submit and track your food reimbursement requests with ease.">

    <!-- Sidebar Requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../';
    </script>
    <script src="../components/sidebar-loader.js" defer></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Module CSS -->
    <link rel="stylesheet" href="css/style.css">
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
            <div class="page-wrapper">

                <!-- ── Page Header ─────────────────────────────── -->
                <div class="page-header">
                    <div class="page-header-left">
                        <div class="page-icon-wrap">
                            <i data-lucide="utensils" style="width:22px;height:22px;"></i>
                        </div>
                        <div>
                            <h1 class="page-title">Food Reimbursement</h1>
                            <p class="page-subtitle">Nights you worked past 9 PM — eligible for food reimbursement</p>
                        </div>
                    </div>
                </div>

                <!-- ── Summary Cards ───────────────────────────── -->
                <div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="summary-card summary-card--total">
                        <div class="summary-icon">
                            <i data-lucide="inbox" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="summary-info">
                            <span class="summary-label">Unsubmitted</span>
                            <span class="summary-value" id="statUnsubmitted">0</span>
                        </div>
                    </div>
                    <div class="summary-card summary-card--pending">
                        <div class="summary-icon" style="color:var(--clr-amber);">
                            <i data-lucide="indian-rupee" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="summary-info">
                            <span class="summary-label">Unsubmitted Amt</span>
                            <span class="summary-value" id="statUnsubmittedAmt">₹0</span>
                        </div>
                    </div>
                    <div class="summary-card summary-card--approved">
                        <div class="summary-icon" style="color:var(--clr-emerald);">
                            <i data-lucide="check-circle" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="summary-info">
                            <span class="summary-label">Paid Amount</span>
                            <span class="summary-value" id="statPaidAmt">₹0</span>
                        </div>
                    </div>
                    <div class="summary-card summary-card--rejected" style="background:#fff; border-radius:12px; padding:1.25rem; display:flex; align-items:center; gap:1rem; border:1px solid #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div class="summary-icon" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(220, 38, 38, 0.1); color:var(--clr-rose);">
                            <i data-lucide="clock" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="summary-info" style="display:flex; flex-direction:column; gap:4px;">
                            <span class="summary-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-secondary);">Unpaid Amount</span>
                            <span class="summary-value" id="statUnpaidAmt" style="font-size:1.5rem;font-weight:800;color:var(--text-primary);letter-spacing:-0.5px;line-height:1;">₹0</span>
                        </div>
                    </div>
                </div>

                <!-- ── Filters ─────────────────────────────────── -->
                <div class="filter-bar card">
                    <div class="filter-bar-left">
                        <div class="filter-group">
                            <label class="filter-label">Month</label>
                            <input type="month" class="filter-select" id="filterMonth" value="<?php echo date('Y-m'); ?>">
                        </div>
                    </div>
                    <div class="filter-bar-right">
                        <button class="btn btn-ghost" id="resetFilters">
                            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                            Reset
                        </button>
                        <button class="btn btn-blue" id="applyFilters">
                            <i data-lucide="filter" style="width:14px;height:14px;"></i>
                            Apply
                        </button>
                    </div>
                </div>

                <!-- ── Claims Table ────────────────────────────── -->
                <div class="table-card card">
                    <div class="table-header">
                        <h2 class="table-title">
                            <span class="accent-bar"></span>
                            Late-Night Attendance
                        </h2>
                        <div class="table-search-wrap">
                            <i data-lucide="search" style="width:14px;height:14px;"></i>
                            <input type="text" class="table-search" id="tableSearch" placeholder="Search by date or report…">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="claims-table" id="claimsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Punch Out</th>
                                    <th>Status</th>
                                    <th>HR Status</th>
                                    <th>Manager Status</th>
                                    <th>Payment Status</th>
                                    <th>Work Report</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody id="claimsTableBody">
                                <!-- Rows injected by JS -->
                            </tbody>
                        </table>
                        <div class="table-empty" id="tableEmpty" style="display:none;">
                            <i data-lucide="moon" style="width:40px;height:40px;color:#cbd5e1;"></i>
                            <p>No late-night punch-outs found for this period</p>
                        </div>
                        <div class="table-loading" id="tableLoading">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size:1.8rem;color:#6366f1;"></i>
                            <p>Loading claims…</p>
                        </div>
                    </div>

                    <div class="table-footer" id="tableFooter" style="display:none;">
                        <span class="table-count" id="tableCount"></span>
                        <div class="pagination" id="pagination"></div>
                    </div>
                </div>

            </div><!-- /.page-wrapper -->
        </main>
    </div><!-- /.dashboard-container -->



    <!-- ════════════════════════════════════════
         VIEW DETAILS MODAL
    ════════════════════════════════════════ -->
    <div class="modal-backdrop" id="viewModalBackdrop">
        <div class="modal modal--wide" id="viewModal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i data-lucide="file-text" style="width:18px;height:18px;"></i>
                    Claim Details
                </h3>
                <button class="modal-close" id="viewModalClose">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Injected by JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" id="closeViewBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════
         EDIT CLAIM MODAL
    ════════════════════════════════════════ -->
    <div class="modal-backdrop" id="editModalBackdrop">
        <div class="modal modal--wide" id="editModal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i data-lucide="pencil" style="width:18px;height:18px;color:var(--clr-amber);"></i>
                    Edit Claim
                </h3>
                <button class="modal-close" id="editModalClose">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <div class="modal-body">

                <!-- Read-only date info -->
                <div class="edit-date-banner">
                    <i data-lucide="calendar" style="width:15px;height:15px;flex-shrink:0;"></i>
                    <span id="editDateDisplay">—</span>
                </div>

                <input type="hidden" id="editClaimId">

                <div class="form-row" style="margin-top:18px;">
                    <div class="form-group">
                        <label class="form-label" for="editMealType">Meal Type</label>
                        <select class="form-control" id="editMealType">
                            <option value="">Select…</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snacks">Snacks</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editCategory">Category</label>
                        <select class="form-control" id="editCategory">
                            <option value="">Select…</option>
                            <option value="team_lunch">Team Lunch</option>
                            <option value="client_meal">Client Meal</option>
                            <option value="overtime_meal">Overtime Meal</option>
                            <option value="travel_meal">Travel Meal</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="editAmount">Amount (₹)</label>
                        <input type="number" class="form-control" id="editAmount" min="1" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editVendor">Restaurant / Vendor</label>
                        <input type="text" class="form-control" id="editVendor" placeholder="e.g. Swiggy, Zomato…">
                    </div>
                </div>

                <div class="form-group form-group--full">
                    <label class="form-label" for="editDescription">Description</label>
                    <textarea class="form-control" id="editDescription" rows="3" placeholder="Brief reason / occasion…"></textarea>
                </div>

                <div class="form-group form-group--full" style="margin-bottom:0;">
                    <label class="form-label" for="editNotes">
                        Additional Notes <span style="color:var(--text-muted);font-weight:400;">(optional)</span>
                    </label>
                    <textarea class="form-control" id="editNotes" rows="2" placeholder="Any extra information…"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" id="cancelEditBtn">Cancel</button>
                <button class="btn btn-edit" id="saveEditBtn">
                    <i data-lucide="save" style="width:15px;height:15px;"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- ════════════════════════════════════════
         SEND CLAIM MODAL
    ════════════════════════════════════════ -->
    <div class="modal-backdrop" id="sendModalBackdrop">
        <div class="modal" id="sendModal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i data-lucide="send" style="width:18px;height:18px;color:var(--clr-blue);"></i>
                    Send Claim for Approval
                </h3>
                <button class="modal-close" id="sendModalClose">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Claim summary injected by JS -->
                <div id="sendModalSummary"></div>

                <!-- Divider -->
                <div style="border-top:1px solid var(--border-color);margin:20px 0;"></div>

                <!-- Optional note -->
                <div class="form-group form-group--full" style="margin-bottom:0;">
                    <label class="form-label" for="sendNote">
                        Note <span style="color:var(--text-muted);font-weight:400;">(optional)</span>
                    </label>
                    <textarea class="form-control" id="sendNote" rows="3"
                        placeholder="Add any extra information for HR / Manager…"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" id="cancelSendBtn">Cancel</button>
                <button class="btn btn-send" id="confirmSendBtn">
                    <i data-lucide="send" style="width:15px;height:15px;"></i>
                    Send Claim
                </button>
            </div>
        </div>
    </div>

    <!-- Module JS -->
    <script src="js/app.js"></script>
</body>
</html>
