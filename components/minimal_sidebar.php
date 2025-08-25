<!-- Minimal Sidebar Component (include-ready) -->
<style>
        :root {
            --msb-bg: #ffffff;
            --msb-bg-muted: #f7fafc; /* light subtle */
            --msb-bg-hover: #f3f4f6; /* gray-100 */
            --msb-border: #e5e7eb; /* gray-200 */
            --msb-text: #0f172a; /* slate-900 */
            --msb-text-dim: #475569; /* slate-600 */
            --msb-accent: #2563eb; /* blue-600 */
            --msb-width: 240px;
            --msb-width-collapsed: 64px;
            --msb-radius: 12px;
            --msb-shadow: 0 10px 30px rgba(2, 6, 23, 0.08);
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; background: #f5f7fb; color: var(--msb-text); }

        .msb-layout { display: flex; min-height: 100vh; }

                 /* Sidebar */
         .msb-sidebar {
             position: fixed;
             inset: 0 auto 0 0;
             width: var(--msb-width);
             background: linear-gradient(180deg, #ffffff, #fafbff);
             border-right: 1px solid var(--msb-border);
             display: flex;
             flex-direction: column;
             overflow: hidden;
             transition: width 220ms ease, transform 220ms ease;
             will-change: width, transform;
             backdrop-filter: saturate(1.05);
             z-index: 1000;
         }

        .msb-sidebar.is-collapsed { width: var(--msb-width-collapsed); }

        /* Header */
        .msb-header { display: flex; align-items: center; gap: 10px; padding: 14px 14px; border-bottom: 1px solid var(--msb-border); }
        .msb-logo { width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #0ea5e9); box-shadow: 0 6px 18px rgba(37,99,235,0.25); }
        .msb-title { font-weight: 700; letter-spacing: 0.2px; color: var(--msb-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .msb-sidebar.is-collapsed .msb-title { display: none; }

        /* Nav */
        .msb-nav { padding: 8px; display: grid; gap: 4px; flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
        /* Hide scrollbar while preserving scroll */
        .msb-nav { scrollbar-width: none; -ms-overflow-style: none; }
        .msb-nav::-webkit-scrollbar { width: 0; height: 0; }
        .msb-item { display: grid; grid-template-columns: 28px 1fr auto; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px; color: var(--msb-text); text-decoration: none; transition: background-color 160ms ease, color 160ms ease, transform 160ms ease; }
        .msb-item:hover { background: var(--msb-bg-hover); transform: translateY(-1px); }
        .msb-item[aria-current="page"] { background: #eef2ff; color: #1d4ed8; border: 1px solid #dbe3ff; }

        .msb-icon { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 8px; color: #1f2937; font-size: 14px; }
        .msb-icon.msb-blue { background: #dbeafe; color: #1d4ed8; }
        .msb-icon.msb-purple { background: #ede9fe; color: #6d28d9; }
        .msb-icon.msb-pink { background: #ffe4e6; color: #be123c; }
        .msb-icon.msb-green { background: #dcfce7; color: #15803d; }
        .msb-icon.msb-amber { background: #fef3c7; color: #b45309; }
        .msb-icon.msb-red { background: #fee2e2; color: #b91c1c; }

        .msb-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--msb-text); font-size: 14px; }
        .msb-kbd { font-size: 11px; padding: 2px 6px; color: var(--msb-text-dim); border: 1px solid var(--msb-border); border-radius: 6px; background: var(--msb-bg-muted); }
        .msb-sidebar.is-collapsed .msb-label, .msb-sidebar.is-collapsed .msb-kbd { display: none; }

        .msb-section { margin: 6px 8px 2px; padding: 8px 8px 6px; font-size: 11px; color: var(--msb-text-dim); letter-spacing: 0.12em; text-transform: uppercase; border-top: 1px solid var(--msb-border); }
        .msb-sidebar.is-collapsed .msb-section { display: none; }

        /* Footer */
        .msb-footer { margin-top: auto; padding: 8px; border-top: 1px solid var(--msb-border); background: linear-gradient(180deg, #ffffff, #f9fafb); }
        .msb-user { display: grid; grid-template-columns: 28px 1fr auto; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; }
        .msb-avatar { width: 28px; height: 28px; border-radius: 50%; background: #e5e7eb; overflow: hidden; }
        .msb-username { font-size: 13px; color: var(--msb-text); }
        .msb-role { font-size: 12px; color: var(--msb-text-dim); }
        .msb-sidebar.is-collapsed .msb-username, .msb-sidebar.is-collapsed .msb-role { display: none; }

        /* Logout item */
        .msb-logout { margin-top: 6px; border: 1px solid #fecaca; }
        .msb-logout:hover { background: #fee2e2; }

        /* Toggle */
                 .msb-toggle {
             position: fixed;
             left: 12px;
             top: 12px;
             width: 36px;
             height: 36px;
             border-radius: 8px;
             border: 1px solid var(--msb-border);
             background: #ffffff;
             color: var(--msb-text);
             display: grid;
             place-items: center;
             box-shadow: var(--msb-shadow);
             cursor: pointer;
             z-index: 1100;
             transition: transform 160ms ease, background-color 160ms ease;
         }
        .msb-toggle:active { transform: scale(0.98); }

                 /* Content */
         .msb-content { flex: 1; width: 100%; padding: 24px; margin-left: var(--msb-width); transition: margin-left 220ms ease; }
         .msb-content.is-expanded { margin-left: var(--msb-width-collapsed); }
         
         /* Fix for dashboard layout */
         .dashboard-container .msb-content { margin-left: 0; padding-left: var(--msb-width); transition: padding-left 220ms ease; }
         .dashboard-container .msb-content.is-expanded { padding-left: var(--msb-width-collapsed); }

        /* Mobile */
                 @media (max-width: 768px) {
             .msb-sidebar { transform: translateX(-120%); width: min(86vw, 320px); inset: 0; border-radius: 0; border: none; border-right: 1px solid var(--msb-border); }
             .msb-sidebar.is-open { transform: translateX(0); }
             .msb-content, .msb-content.is-expanded { margin-left: 0; padding-top: 60px; }
             .dashboard-container .msb-content, .dashboard-container .msb-content.is-expanded { padding-left: 24px; }
             .msb-toggle { left: 12px; top: 12px; }
            .msb-backdrop { position: fixed; inset: 0; background: rgba(148,163,184,0.35); backdrop-filter: blur(2px); opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 900; }
            .msb-backdrop.is-visible { opacity: 1; pointer-events: auto; }
        }
    </style>

    <button class="msb-toggle" id="msbToggle" aria-label="Toggle sidebar" aria-expanded="true">
        ☰
    </button>

        <?php 
        $__currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        
        // Get user information from session and database
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Guest';
        $user_role = $_SESSION['user_role'] ?? 'User';
        
        // Get profile image if available
        $profile_image = '';
        if ($user_id) {
            // Check if connection exists
            if (isset($conn)) {
                $img_query = "SELECT profile_picture FROM users WHERE id = ?";
                $img_stmt = $conn->prepare($img_query);
                if ($img_stmt) {
                    $img_stmt->bind_param("i", $user_id);
                    $img_stmt->execute();
                    $img_result = $img_stmt->get_result();
                    if ($img_row = $img_result->fetch_assoc()) {
                        if (!empty($img_row['profile_picture'])) {
                            $profile_image = $img_row['profile_picture'];
                        }
                    }
                }
            }
        }
        ?>
        <aside class="msb-sidebar" id="msbSidebar" aria-label="Sidebar Navigation">
            <div class="msb-header">
                <div class="msb-logo"></div>
                <div class="msb-title">Studio Workspace</div>
            </div>
            <nav class="msb-nav" role="navigation">
                <div class="msb-section">Main</div>
                <a class="msb-item" href="similar_dashboard.php" <?php echo $__currentPage==='similar_dashboard.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-blue">🏠</span>
                    <span class="msb-label">Dashboard</span>
                </a>

                <div class="msb-section">Personal</div>
                <a class="msb-item" href="profile.php" <?php echo $__currentPage==='profile.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-purple">🧑‍💼</span>
                    <span class="msb-label">My Profile</span>
                </a>

                <div class="msb-section">Leave & Expenses</div>
                <a class="msb-item" href="employee_leave.php" <?php echo $__currentPage==='employee_leave.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-green">📅</span>
                    <span class="msb-label">Apply Leave</span>
                </a>
                <a class="msb-item" href="std_travel_expenses.php" <?php echo $__currentPage==='std_travel_expenses.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-amber">🧾</span>
                    <span class="msb-label">Travel Expenses</span>
                </a>
                <a class="msb-item" href="employee_overtime.php" <?php echo $__currentPage==='employee_overtime.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-pink">⏱️</span>
                    <span class="msb-label">Overtime</span>
                </a>

                <div class="msb-section">Work</div>
                <a class="msb-item" href="projects_list.php" <?php echo $__currentPage==='projects_list.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-blue">📦</span>
                    <span class="msb-label">Projects</span>
                </a>
                <a class="msb-item" href="site_updates.php" <?php echo $__currentPage==='site_updates.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-purple">📢</span>
                    <span class="msb-label">Site Updates</span>
                </a>
                <a class="msb-item" href="my_tasks.php" <?php echo $__currentPage==='my_tasks.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-green">✅</span>
                    <span class="msb-label">My Tasks</span>
                </a>
                <a class="msb-item" href="work_sheet.php" <?php echo $__currentPage==='work_sheet.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-amber">🗂️</span>
                    <span class="msb-label">Work Sheet & Attendance</span>
                </a>
                <a class="msb-item" href="performance.php" <?php echo $__currentPage==='performance.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-pink">📈</span>
                    <span class="msb-label">Performance Analytics</span>
                </a>

                <div class="msb-section">System</div>
                <a class="msb-item" href="settings.php" <?php echo $__currentPage==='settings.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-blue">⚙️</span>
                    <span class="msb-label">Settings</span>
                </a>
                <a class="msb-item" href="help_support.php" <?php echo $__currentPage==='help_support.php' ? 'aria-current="page"' : '';?>>
                    <span class="msb-icon msb-purple">❓</span>
                    <span class="msb-label">Help & Support</span>
                </a>
                <a class="msb-item msb-logout" href="logout.php">
                    <span class="msb-icon msb-red">⎋</span>
                    <span class="msb-label">Logout</span>
                </a>
            </nav>

            <div class="msb-footer">
                <div class="msb-user">
                    <div class="msb-avatar">
                        <?php if (!empty($profile_image)): ?>
                            <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #e5e7eb; color: #6b7280; font-size: 14px; font-weight: bold;">
                                <?php echo substr($username, 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="msb-username"><?php echo htmlspecialchars($username); ?></div>
                        <div class="msb-role"><?php echo htmlspecialchars($user_role); ?></div>
                    </div>
                </div>
            </div>
        </aside>

    <div class="msb-backdrop" id="msbBackdrop"></div>

    <script>
        (function() {
            const toggle = document.getElementById('msbToggle');
            const sidebar = document.getElementById('msbSidebar');
            const content = document.querySelector('.msb-content');
            const backdrop = document.getElementById('msbBackdrop');

            function isMobile() { return window.matchMedia('(max-width: 768px)').matches; }

            function setExpanded(expanded) {
                toggle.setAttribute('aria-expanded', String(expanded));
                if (isMobile()) {
                    sidebar.classList.toggle('is-open', expanded);
                    backdrop.classList.toggle('is-visible', expanded);
                    document.body.style.overflow = expanded ? 'hidden' : '';
                } else {
                    sidebar.classList.toggle('is-collapsed', !expanded);
                    if (content) {
                        content.classList.toggle('is-expanded', !expanded);
                    }
                }
            }

            // Initial (expanded on desktop, closed on mobile)
            setExpanded(!isMobile());

            toggle.addEventListener('click', function() {
                if (isMobile()) {
                    const open = !sidebar.classList.contains('is-open');
                    setExpanded(open);
                } else {
                    const expanded = sidebar.classList.contains('is-collapsed');
                    setExpanded(expanded);
                }
            });

            backdrop.addEventListener('click', function() { setExpanded(false); });

            window.addEventListener('resize', function() {
                // Reset states across breakpoints
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-visible');
                document.body.style.overflow = '';
                if (isMobile()) {
                    sidebar.classList.remove('is-collapsed');
                    if (content) content.classList.remove('is-expanded');
                    toggle.setAttribute('aria-expanded', 'false');
                } else {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        })();
    </script>


