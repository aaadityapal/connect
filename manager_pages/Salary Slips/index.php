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
	<title>Salary Slips | Connect</title>
	<link rel="stylesheet" href="../employees_profile/style.css">
	<link rel="stylesheet" href="../employees_profile/header.css">
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
	<script>
		window.SIDEBAR_BASE_PATH = '../../studio_users/';
	</script>
	<script src="../../studio_users/components/sidebar-loader.js" defer></script>
	<style>
		.page-shell {
			padding: 1.5rem;
			width: 100%;
			box-sizing: border-box;
		}

		.page-card {
			background: #fff;
			border-radius: 20px;
			box-shadow: 0 10px 40px rgba(0,0,0,0.04);
			border: 1px solid #f0f2f5;
			min-height: calc(100vh - 120px);
			padding: 2rem;
		}
	</style>
</head>
<body class="el-1">
	<div class="dashboard-container">
		<div id="sidebar-mount"></div>

		<main class="main-content">
			<header class="dh-nav-header">
				<div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
					<button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
						<i data-lucide="menu" style="width:18px;height:18px;"></i>
					</button>
					<div>
						<div class="dh-user-info">
							<div class="dh-icon-orange">
								<i data-lucide="file-text" style="width:15px;height:15px;"></i>
							</div>
							<div class="dh-greeting">
								<span class="dh-greeting-text">Salary Slips</span>
								<span class="dh-greeting-name">Manager</span>
							</div>
						</div>
					</div>
				</div>
				<div class="dh-nav-right">
					<div class="dh-profile-box" id="profileDropdownContainer">
						<div class="dh-profile-avatar" id="profileAvatarBtn">
							<i data-lucide="user" style="width:17px;height:17px;"></i>
						</div>
					</div>
				</div>
			</header>

			<div class="page-shell">
				<div class="page-card">
					<h2 style="margin:0;">Salary Slips</h2>
					<p style="margin:0.6rem 0 0;color:#64748b;">Content will be added here.</p>
				</div>
			</div>
		</main>
	</div>
</body>
</html>
