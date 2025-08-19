<?php
session_start();
// Basic user context (optional)
$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['user_role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Solstice Sidebar</title>
	<style>
		:root {
			--bg: #f5f7fb;
			--panel: #ffffff;
			--muted: #f3f4f6;
			--text: #0f172a;
			--text-dim: #475569;
			--accent: #2563eb;
			--accent-strong: #1d4ed8;
			--hover: #f5f7ff;
			--active: #eef2ff;
			--danger: #dc2626;
			--danger-strong: #b91c1c;
			--danger-bg: #fee2e2;
			--danger-border: #fecaca;
			--radius: 14px;
			--shadow: 0 10px 30px rgba(2, 6, 23, 0.08);
			--w: 248px;
			--w-collapsed: 72px;
		}

		* { box-sizing: border-box; }
		body { margin: 0; background: linear-gradient(180deg, #ffffff, var(--bg)); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; }

		.layout { display: flex; min-height: 100vh; }

		/* Sidebar */
		.sidebar {
			position: fixed;
			inset: 0 auto 0 0;
			width: var(--w);
			background: radial-gradient(1200px 600px at -10% -10%, rgba(37,99,235,0.06), transparent 40%),
				linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
			border-right: 1px solid #e5e7eb;
			display: flex; flex-direction: column;
			overflow: hidden; transition: width 220ms ease, transform 220ms ease;
			backdrop-filter: saturate(1.05);
			z-index: 1000;
			box-shadow: var(--shadow);
		}
		.sidebar.collapsed { width: var(--w-collapsed); }

		.header { display: flex; align-items: center; gap: 12px; padding: 14px 14px; border-bottom: 1px solid #e5e7eb; }
		.brand {
			width: 30px; height: 30px; border-radius: 10px; position: relative; background: #ffffff; border: 1px solid #c7d2fe;
			box-shadow: inset 0 0 20px rgba(37,99,235,0.15), 0 4px 18px rgba(2,6,23,0.06);
		}
		.brand::after { content: ""; position: absolute; inset: 4px; border-radius: 8px; background: radial-gradient(200px 120px at 30% 20%, rgba(37,99,235,0.45), transparent 50%); filter: blur(4px); }
		.title { font-weight: 700; letter-spacing: 0.2px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.sidebar.collapsed .title { display: none; }

		.nav { padding: 8px; display: grid; gap: 6px; flex: 1; min-height: 0; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
		.nav::-webkit-scrollbar { width: 0; height: 0; }
		.item { position: relative; display: grid; grid-template-columns: 36px 1fr auto; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; color: var(--text); text-decoration: none; transition: background-color 160ms ease, transform 160ms ease, border-color 160ms ease, color 160ms ease; border: 1px solid transparent; }
		.item::before { content: ""; position: absolute; left: -8px; top: 8px; bottom: 8px; width: 3px; border-radius: 3px; background: var(--accent-strong); opacity: 0; transform: scaleY(0.2); transform-origin: top; transition: opacity 180ms ease, transform 220ms ease; }
		.item:hover { background: var(--hover); transform: translateY(-1px); border-color: #e5e7eb; color: #0b1220; }
		.item:hover::before { opacity: 1; transform: scaleY(1); }
		.item:active { transform: translateY(0); }
		.item[aria-current="page"] { background: var(--active); border-color: #c7d2fe; color: #0b1220; }
		.item[aria-current="page"]::before { opacity: 1; transform: scaleY(1); }

		.icon { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(180deg, #ffffff, var(--panel)); border: 1px solid #e5e7eb; color: var(--accent); box-shadow: inset 0 0 12px rgba(37,99,235,0.06); font-size: 15px; transition: border-color 160ms ease, color 160ms ease, background-color 160ms ease; }
		.item:hover .icon { border-color: #c7d2fe; color: var(--accent-strong); background: #f8fafc; }
		.item[aria-current="page"] .icon { border-color: #c7d2fe; color: var(--accent-strong); background: #f8fafc; }

		/* Logout (danger) state */
		.item.logout:hover { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger-strong); }
		.item.logout:hover .icon { border-color: var(--danger-border); color: var(--danger-strong); background: #fff5f5; box-shadow: inset 0 0 12px rgba(220,38,38,0.08); }
		.label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; color: var(--text); }
		.hint { font-size: 11px; padding: 2px 6px; color: var(--text-dim); border: 1px solid #e5e7eb; border-radius: 6px; background: #ffffff; transition: color 160ms ease, border-color 160ms ease, background-color 160ms ease; }
		.item:hover .hint { color: var(--accent-strong); border-color: #c7d2fe; background: #f8fafc; }
		.sidebar.collapsed .label, .sidebar.collapsed .hint { display: none; }

		.section { margin: 8px 8px 4px; padding: 8px 8px 6px; font-size: 11px; color: var(--text-dim); letter-spacing: 0.14em; text-transform: uppercase; border-top: 1px dashed #e5e7eb; }
		.sidebar.collapsed .section { display: none; }

		.footer { margin-top: auto; padding: 10px; border-top: 1px solid #e5e7eb; background: linear-gradient(180deg, #ffffff, #fafafa); }
		.user { display: grid; grid-template-columns: 32px 1fr auto; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; }
		.avatar { width: 32px; height: 32px; border-radius: 50%; background: #ffffff; border: 1px solid #e5e7eb; overflow: hidden; display: grid; place-items: center; color: var(--text-dim); font-weight: 700; }
		.username { font-size: 13px; color: var(--text); }
		.role { font-size: 12px; color: var(--text-dim); }
		.sidebar.collapsed .username, .sidebar.collapsed .role { display: none; }

		/* Toggle */
		.toggle { position: fixed; left: 16px; top: 14px; width: 40px; height: 40px; border-radius: 12px; border: 1px solid #e5e7eb; background: #ffffff; color: var(--text); display: grid; place-items: center; box-shadow: var(--shadow); cursor: pointer; z-index: 1100; transition: transform 160ms ease, background-color 160ms ease, border-color 160ms ease; }
		.toggle:active { transform: scale(0.98); }
		.toggle:hover { border-color: #c7d2fe; background: #f8fafc; }
		.toggle:focus-visible { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,0.45); }

		/* Content */
		.content { flex: 1; width: 100%; padding: 28px; margin-left: var(--w); transition: margin-left 220ms ease; }
		.content.shifted { margin-left: var(--w-collapsed); }

		.card {
			max-width: 980px;
			margin: 60px auto 24px;
			background: #ffffff;
			border: 1px solid #e5e7eb;
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			padding: 24px;
		}
		.card h1 { margin: 0 0 8px; font-size: 22px; }
		.card p { margin: 0; color: var(--text-dim); }

		/* Accessibility focus styles */
		.item:focus-visible { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,0.35); background: var(--hover); }

		/* Mobile */
		@media (max-width: 768px) {
			.sidebar { transform: translateX(-125%); width: min(86vw, 320px); border-right: 1px solid #e5e7eb; border-radius: 0; }
			.sidebar.open { transform: translateX(0); }
			.content, .content.shifted { margin-left: 0; padding-top: 72px; }
			.backdrop { position: fixed; inset: 0; background: rgba(148,163,184,0.35); backdrop-filter: blur(2px); opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 900; }
			.backdrop.visible { opacity: 1; pointer-events: auto; }
		}
	</style>
</head>
<body>
	<button class="toggle" id="toggle" aria-label="Toggle sidebar" aria-expanded="true">☰</button>
	<aside class="sidebar" id="sidebar" aria-label="Sidebar Navigation">
		<div class="header">
			<div class="brand"></div>
			<div class="title">HR Workspace</div>
		</div>
		<nav class="nav" role="navigation">
			<div class="section">Main</div>
			<a class="item" href="similar_dashboard.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Dashboard</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M4 7a3 3 0 1 0 6 0 3 3 0 0 0-6 0Zm10 1h6M4 17h6M14 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="label">Employees</span>
			</a>
			<a class="item" href="attendance_visualizer.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<rect x="3" y="4.5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
						<path d="M8 2.5v4M16 2.5v4M3 9.5h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="label">Attendance</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M4 7h16M4 12h16M9 17h11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
						<circle cx="6" cy="17" r="1.5" stroke="currentColor" stroke-width="1.6"/>
					</svg>
				</span>
				<span class="label">Shifts</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M4 14h8a4 4 0 0 0 8 0V6H4v8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
						<path d="M7 10h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="label">Manager Payouts</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M4 16l4-4 3 3 5-6 4 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
						<rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
					</svg>
				</span>
				<span class="label">Company Stats</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/>
						<path d="M7 10h6M7 14h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="label">Salary</span>
			</a>
			<a class="item" href="employee_leave.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<rect x="3" y="4.5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
						<path d="M8 2.5v4M16 2.5v4M3 9.5h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="label">Leave Request</span>
			</a>
			<a class="item" href="admin/manage_geofence_locations.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 21s7-6.2 7-12.2A7 7 0 1 0 5 8.8C5 14.8 12 21 12 21Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
						<circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.6"/>
					</svg>
				</span>
				<span class="label">Geofence Locations</span>
			</a>
			<a class="item" href="std_travel_expenses.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M4 16h16M6 16l4-9h4l4 9M9 12h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Travel Expenses</span>
			</a>
			<a class="item" href="hr_overtime_approval.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.6"/>
						<path d="M12 13V8m0-4 3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Overtime Approval</span>
			</a>
			<a class="item" href="projects_list.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5v-9Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
						<path d="M12 12V21M3.5 7.5 12 12l8.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Projects</span>
			</a>
			<a class="item" href="#">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Password Reset</span>
			</a>

			<div class="section">System</div>
			<a class="item" href="settings.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.6"/>
						<path d="M19 12a7 7 0 0 0-.11-1.26l2.17-1.69-2-3.46-2.6 1a6.98 6.98 0 0 0-2.18-1.26l-.33-2.79h-4L9.62 5.33A6.98 6.98 0 0 0 7.44 6.6l-2.6-1-2 3.46 2.17 1.69A7.1 7.1 0 0 0 4.99 12c0 .43.04.85.11 1.26l-2.17 1.69 2 3.46 2.6-1c.64.53 1.39.95 2.18 1.26l.33 2.79h4l.33-2.79c.79-.31 1.54-.73 2.18-1.26l2.6 1 2-3.46-2.17-1.69c.07-.41.11-.83.11-1.26Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Settings</span>
			</a>
			<a class="item logout" href="logout.php">
				<span class="icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M14 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
						<path d="M10 12h9m0 0-3-3m3 3-3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
				<span class="label">Logout</span>
			</a>
		</nav>
		<div class="footer">
			<div class="user">
				<div class="avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
				<div>
					<div class="username"><?php echo htmlspecialchars($username); ?></div>
					<div class="role"><?php echo htmlspecialchars($role); ?></div>
				</div>
			</div>
		</div>
	</aside>

	<div class="backdrop" id="backdrop"></div>

	<!-- Content intentionally removed; sidebar remains fully functional. -->

	<script>
		(function() {
			const toggle = document.getElementById('toggle');
			const sidebar = document.getElementById('sidebar');
			const backdrop = document.getElementById('backdrop');
			const content = document.getElementById('content');

			function isMobile() { return matchMedia('(max-width: 768px)').matches; }

			function setExpanded(expanded) {
				toggle.setAttribute('aria-expanded', String(expanded));
				if (isMobile()) {
					sidebar.classList.toggle('open', expanded);
					backdrop.classList.toggle('visible', expanded);
					document.body.style.overflow = expanded ? 'hidden' : '';
				} else {
					sidebar.classList.toggle('collapsed', !expanded);
					if (content) content.classList.toggle('shifted', !expanded);
				}
			}

			// Initial
			setExpanded(!isMobile());

			toggle.addEventListener('click', function() {
				// On desktop, keep sidebar always expanded and ignore toggle clicks
				if (!isMobile()) { return; }
				const open = !sidebar.classList.contains('open');
				setExpanded(open);
			});

			backdrop.addEventListener('click', function() { setExpanded(false); });

			addEventListener('resize', function() {
				sidebar.classList.remove('open');
				backdrop.classList.remove('visible');
				document.body.style.overflow = '';
				if (isMobile()) {
					sidebar.classList.remove('collapsed');
					if (content) content.classList.remove('shifted');
					toggle.setAttribute('aria-expanded', 'false');
				} else {
					// Force expanded on desktop
					setExpanded(true);
				}
			});
		})();
	</script>
</body>
</html>

