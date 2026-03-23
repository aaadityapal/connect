<?php
// ── Authentication Guard ──────────────────────────────────────────────────────
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// ── Fetch logged-in user info from DB ─────────────────────────────────────────
require_once '../../config/db_connect.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$username        = $user ? htmlspecialchars($user['username'])        : 'User';
$profile_picture = $user ? htmlspecialchars($user['profile_picture']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — <?php echo $username; ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS - Cache Busted -->
    <link rel="stylesheet" href="css/desktop.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/mobile.css?v=<?php echo time(); ?>" media="screen and (max-width: 768px)">
    <!-- Global Sidebar CSS (Main Dashboard style) -->
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../header.css?v=<?php echo time(); ?>">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- HEIC to JPEG conversion library for previews -->
    <script src="https://unpkg.com/heic2any@0.0.3/dist/heic2any.min.js"></script>

    <script>
        // Set base path for sidebar components (one level up)
        window.SIDEBAR_BASE_PATH = '../';

        // Make session user data available to JS
        window.SESSION_USER_ID   = <?php echo json_encode((int)$user_id); ?>;
        window.SESSION_USERNAME  = <?php echo json_encode($username); ?>;
        window.SESSION_PROFILE_PIC = <?php echo json_encode($profile_picture); ?>;
    </script>
    <script src="../components/sidebar-loader.js?v=<?php echo time(); ?>" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Mount Point -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="dh-nav-header">
                <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div class="dh-user-info">
                        <div class="dh-greeting">
                            <span class="dh-greeting-text">My Profile</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-container">
                <!-- Top Tabs -->
                <header class="section-header">
                    <nav class="profile-tabs">
                        <button class="tab-btn active" data-target="personal-info">Personal Info</button>
                        <button class="tab-btn" data-target="security">Security</button>
                        <button class="tab-btn" data-target="hr-documents">HR Documents</button>
                    </nav>
                </header>
 
                <main class="profile-content">
                    <div class="tab-page active" id="personal-info"></div>
                    <div class="tab-page" id="security"></div>
                    <div class="tab-page" id="hr-documents"></div>
                </main>
            </div>
        </main>
    </div>

    <!-- MODALS MOUNT POINT (Dynamic) -->
    <div id="modal-mount"></div>

    <!-- MINIMALISTIC PASSWORD RESET VERIFICATION MODAL -->
    <div class="ag-modal-overlay" id="ag-password-reset-modal">
        <div class="ag-modal-card" style="max-width: 380px; border-radius: 24px; padding: 2.5rem; background: #fff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); border: none;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="background: #f8fafc; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; border: 1px solid #f1f5f9;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-fingerprint"><path d="M2 12C2 6.48 6.48 2 12 2s10 4.48 10 10"/><path d="M5 15C5 11.13 8.13 8 12 8s7 3.13 7 7"/><path d="M8 18C8 15.79 9.79 14 12 14s4 1.79 4 4"/><path d="M11 21C11 18.24 13.24 16 16 16s5 2.24 5 5"/></svg>
                </div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; letter-spacing: -0.025em;">Verify Identity</h3>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.5;">Confirm your email address to securely reset your password.</p>
            </div>

            <!-- STEP 1: Email Verification -->
            <div id="ag-reset-step-1">
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: block; text-align: center;">Registered Email Address</label>
                    <span id="ag-reset-email-hint" style="display: block; text-align: center; font-size: 0.8rem; color: #64748b; margin-bottom: 12px; font-weight: 500;"></span>
                    <input type="email" id="ag-reset-verify-email" placeholder="Enter your email" required 
                           style="width: 100%; padding: 12px 0; background: transparent; border: none; border-bottom: 2px solid #f1f5f9; border-radius: 0; font-size: 1.1rem; color: #1e293b; text-align: center; outline: none; transition: all 0.2s;">
                </div>
            </div>

            <!-- STEP 2: OTP Verification (Hidden by default) -->
            <div id="ag-reset-step-2" style="display: none;">
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: block; text-align: center;">Enter OTP Code</label>
                    <p style="text-align: center; font-size: 0.8rem; color: #64748b; margin-bottom: 15px;">Check <span id="ag-reset-sent-to" style="color: #2563eb; font-weight: 600;"></span> for a 6-digit code.</p>
                    <input type="text" id="ag-reset-otp-code" placeholder="000000" maxlength="6"
                           style="width: 100%; padding: 12px 0; background: transparent; border: none; border-bottom: 2px solid #2563eb; border-radius: 0; font-size: 1.5rem; color: #1e293b; text-align: center; outline: none; letter-spacing: 0.5em; font-weight: 700;">
                    <div style="text-align: center; margin-top: 15px;">
                        <button type="button" id="ag-resend-otp-btn" disabled
                                style="background: none; border: none; font-size: 0.8rem; color: #94a3b8; cursor: not-allowed; font-weight: 600; text-decoration: underline; transition: all 0.2s;">
                            Resend Code <span id="ag-resend-timer"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: New Password (Hidden by default) -->
            <div id="ag-reset-step-3" style="display: none;">
                <div class="form-group" style="margin-bottom: 1.5rem; position: relative;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Set New Password</label>
                    <input type="password" id="ag-reset-new-password" placeholder="Min. 8 chars, 1 capital, 1 number"
                           style="width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; color: #1e293b; outline: none; transition: border-color 0.2s;">
                    <i class="bi bi-eye ag-toggle-reset-pwd" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #94a3b8;"></i>
                </div>
                <div class="form-group" style="margin-bottom: 2rem; position: relative;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Confirm New Password</label>
                    <input type="password" id="ag-reset-confirm-password" placeholder="Repeat new password"
                           style="width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; color: #1e293b; outline: none; transition: border-color 0.2s;">
                    <i class="bi bi-eye ag-toggle-reset-pwd" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #94a3b8;"></i>
                </div>
            </div>

            <div id="ag-reset-modal-alert" style="display:none; margin-bottom: 1.5rem; padding: 12px; border-radius: 12px; font-size: 0.85rem; text-align: center; border: 1px solid transparent;"></div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary" id="ag-submit-password-reset" 
                        style="width:100%; padding: 15px; background: #0f172a; color: #fff; border: none; border-radius: 14px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    Send OTP
                </button>
                <button type="button" id="ag-close-password-reset" 
                        style="background: none; border: none; color: #94a3b8; padding: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: color 0.2s;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- AG-UPLOAD MODAL (Embedded for absolute reliability) -->
    <div class="ag-modal-overlay" id="ag-upload-modal">
        <div class="ag-modal-card">
            <div class="ag-modal-header">
                <h3 class="ag-modal-title">Upload Profile Photo</h3>
                <button class="ag-modal-close" id="ag-close-upload">&times;</button>
            </div>
            <div class="ag-modal-body">
                <div class="ag-upload-container" id="ag-drop-zone">
                    <input type="file" class="ag-file-input" id="ag-avatar-input" accept="image/jpeg, image/png, image/heic, .heic">
                    <div class="ag-upload-content" id="ag-upload-instructions">
                        <svg class="ag-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <p class="ag-upload-text" id="ag-file-name">Click to select or drag & drop</p>
                        <p class="ag-upload-hint">Supported formats: JPG, PNG, HEIC (Max 5MB)</p>
                    </div>
                    <!-- New Preview Container -->
                    <div id="ag-preview-box" style="display:none; width:100%; height:100%; position:relative;">
                        <img id="ag-preview-img" style="width:100%; height:200px; object-fit:contain; border-radius:12px;">
                        <button id="ag-remove-preview" style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.5); color:white; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center;">&times;</button>
                    </div>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button class="ag-btn-submit" id="ag-save-photo">Upload Image</button>
            </div>
        </div>
    </div>
    <!-- AG-EDUCATION MODAL -->
    <div class="ag-modal-overlay" id="ag-education-modal">
        <div class="ag-modal-card">
            <div class="ag-modal-header">
                <h3 class="ag-modal-title">Add Education Background</h3>
                <button class="ag-modal-close" id="ag-close-education">&times;</button>
            </div>
            <div class="ag-modal-body">
                <div class="ag-form-grid">
                    <div class="ag-form-group">
                        <label class="ag-form-label">Degree Level*</label>
                        <select class="ag-form-input" id="ag-edu-degree">
                            <option value="">Select Degree</option>
                            <option value="High School">High School</option>
                            <option value="Bachelor's">Bachelor's</option>
                            <option value="Master's">Master's</option>
                            <option value="PhD">PhD</option>
                        </select>
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Institution Name*</label>
                        <input type="text" class="ag-form-input" id="ag-edu-institution" placeholder="e.g. Stanford University">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Field of Study*</label>
                        <input type="text" class="ag-form-input" id="ag-edu-field" placeholder="e.g. Computer Science">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Graduation Year*</label>
                        <input type="text" class="ag-form-input" id="ag-edu-year" placeholder="e.g. 2024">
                    </div>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button class="ag-btn-submit" id="ag-save-education">Save Education History</button>
            </div>
        </div>
    </div>

    <!-- AG-EXPERIENCE MODAL -->
    <div class="ag-modal-overlay" id="ag-experience-modal">
        <div class="ag-modal-card">
            <div class="ag-modal-header">
                <h3 class="ag-modal-title">Add Work Experience</h3>
                <button class="ag-modal-close" id="ag-close-experience">&times;</button>
            </div>
            <div class="ag-modal-body">
                <div class="ag-form-grid">
                    <div class="ag-form-group">
                        <label class="ag-form-label">Company Name*</label>
                        <input type="text" class="ag-form-input" id="ag-exp-company" placeholder="e.g. Google, Microsoft">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Job Title*</label>
                        <input type="text" class="ag-form-input" id="ag-exp-title" placeholder="e.g. Senior Software Engineer">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Years of Experience*</label>
                        <input type="text" class="ag-form-input" id="ag-exp-years" placeholder="e.g. 2.5 Years, 2021-2023">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Key Responsibilities / Description</label>
                        <textarea class="ag-form-input" id="ag-exp-desc" rows="3" placeholder="Describe your role and achievements..."></textarea>
                    </div>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button class="ag-btn-submit" id="ag-save-experience">Save Work Experience History</button>
            </div>
        </div>
    </div>

    <!-- AG-DOCUMENT MODAL -->
    <div class="ag-modal-overlay" id="ag-document-modal">
        <div class="ag-modal-card">
            <div class="ag-modal-header">
                <h3 class="ag-modal-title">Upload New Document</h3>
                <button class="ag-modal-close" id="ag-close-document">&times;</button>
            </div>
            <div class="ag-modal-body">
                <div class="ag-form-grid">
                    <div class="ag-form-group">
                        <label class="ag-form-label">Document Type*</label>
                        <select class="ag-form-input" id="ag-doc-type">
                            <option value="">Select Category</option>
                            <option value="ID Proof">Aadhar / ID Proof</option>
                            <option value="PAN Card">PAN Card</option>
                            <option value="Certificate">Experience Certificate</option>
                            <option value="Salary Slip">Salary Slip / Bank Proof</option>
                            <option value="Educational Doc">Degree / Marks Card</option>
                            <option value="Other">Other Document</option>
                        </select>
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Document Label / Name*</label>
                        <input type="text" class="ag-form-input" id="ag-doc-name" placeholder="e.g. My Bachelor's Degree">
                    </div>
                    <div class="ag-form-group">
                        <label class="ag-form-label">Select File* (PDF, JPG, PNG)</label>
                        <input type="file" id="ag-doc-file-input" style="display:none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="ag-upload-zone" id="ag-doc-trigger-select" style="border: 2px dashed #e2e8f0; border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div id="ag-doc-instructions">
                                <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📄</span>
                                <span style="font-size: 0.85rem; color: #64748b;">Click here to select a file from your device</span>
                            </div>
                            <div id="ag-doc-preview-name" style="display:none; color: #3b82f6; font-weight: 600;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button class="ag-btn-submit" id="ag-save-document">Start Upload</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Main Logic - Cache Busted -->
    <!-- Security Logic (Separate file) -->
    <script src="js/security_logic.js?v=<?php echo time(); ?>"></script>
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
