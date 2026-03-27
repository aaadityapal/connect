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
    <title>Travel Expenses Tracker</title>
    <meta name="description" content="Submit, track, and manage all your travel reimbursements in one place.">

    <!-- Sidebar Requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.SIDEBAR_BASE_PATH = '../';
    </script>
    <script src="../components/sidebar-loader.js" defer></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Modular CSS -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/summary.css">
    <link rel="stylesheet" href="css/filter.css">
    <link rel="stylesheet" href="css/table.css">
    <link rel="stylesheet" href="css/modals.css">
    <link rel="stylesheet" href="css/mobile.css">

    <!-- Leaflet & Routing (Free Maps) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
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
                <div id="header-container"></div>
                <div id="summary-container"></div>
                <div id="filter-container"></div>
                <div id="table-container"></div>
            </div>
        </main>
    </div>

    <!-- Modals rendered outside page-wrapper to avoid z-index stacking issues -->
    <div id="modals-container"></div>

    <script src="js/app.js"></script>
</body>

</html>