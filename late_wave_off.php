<?php
// Include database connection
require_once 'includes/db_connect.php';

// Start session to access user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user role from session
$user_role = $_SESSION['role'] ?? '';

// Check if user has the required role to access this page
if ($user_role !== 'Senior Manager (Studio)' && $user_role !== 'Senior Manager (Site)') {
    // Redirect to unauthorized page or dashboard
    header('Location: unauthorized.php');
    exit();
}

// Determine default toggle based on user role
$default_toggle = 'studio'; // Default to studio
if ($user_role == 'Senior Manager (Site)') {
    $default_toggle = 'site';
} else if ($user_role == 'Senior Manager (Studio)') {
    $default_toggle = 'studio';
}

// Fetch only active users from the database
$users = [];
$sql = "SELECT id, username FROM users WHERE status = 'Active' ORDER BY username";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Late Wave Off</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Font Awesome for sidebar icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles to match the look of the image */
        :root {
            --sidebar-width-open: 240px; 
            --sidebar-width-collapsed: 70px; 
            --active-red: #ef4444; /* Tailwind red-500 */
            --text-gray: #374151; /* Tailwind gray-700 */
            /* Manager panel variables */
            --manager-panel-width: 280px;
            --manager-panel-collapsed: 70px;
        }
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6; /* Tailwind gray-100 */
            overflow: hidden; /* Prevent body scroll */
        }
        
        /* Modal styles */
        .modal {
            display: none;
        }
        
        .modal.show {
            display: block;
        }
        
        /* Main content area */
        #content-wrapper {
            margin-left: var(--sidebar-width-open);
            transition: margin-left 0.3s ease, width 0.3s ease; /* Added width transition */
            height: 100vh; /* Full viewport height */
            overflow-y: auto; /* Enable vertical scrolling for content */
        }
        
        /* Adjust for manager panel */
        .manager-panel-page #content-wrapper {
            margin-left: var(--manager-panel-width);
        }
        
        /* Adjust for collapsed manager panel */
        .manager-panel-page .main-content.expanded #content-wrapper {
            margin-left: var(--manager-panel-collapsed);
        }
        
        /* -------------------------------------- */
        /* Desktop Specific Styles (>= 1024px) */
        /* -------------------------------------- */
        @media (min-width: 1024px) {
            #content-wrapper {
                /* FIX: Explicitly set content width to ensure smooth reflow and prevent content jump */
                width: calc(100% - var(--sidebar-width-open));
                height: 100vh; /* Full viewport height */
            }
            
            /* Adjust for manager panel */
            .manager-panel-page #content-wrapper {
                width: calc(100% - var(--manager-panel-width));
            }
            
            /* Adjust for collapsed manager panel */
            .manager-panel-page .main-content.expanded #content-wrapper {
                width: calc(100% - var(--manager-panel-collapsed));
            }
        }
        
        /* -------------------------------------- */
        /* Mobile Specific Styles (<= 1023px) */
        /* -------------------------------------- */
        @media (max-width: 1023px) {
            /* Ensure content wrapper doesn't have margin when sidebar is hidden on mobile */
            #content-wrapper {
                margin-left: 0;
                width: 100%;
                height: 100vh; /* Full viewport height */
            }
        }
        
        /* Sidebar styles from sidebar.php */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width-open);
            background-color: #fff;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            /* Hide scrollbar for Chrome, Safari and Opera */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .sidebar::-webkit-scrollbar {
            display: none;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-width-collapsed);
        }
        
        .sidebar-header {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            padding: 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu li a:hover {
            background-color: #f5f5f5;
        }
        
        .sidebar-menu li.active a {
            background-color: #e9ecef;
            color: #007bff;
            font-weight: 500;
        }
        
        .sidebar-menu li a i {
            width: 20px;
            margin-right: 15px;
            font-size: 16px;
        }
        
        .sidebar-text {
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .toggle-btn {
            position: absolute;
            top: 15px;
            right: -12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 1px 1px 3px rgba(0,0,0,0.1);
            z-index: 1001;
        }
        
        .sidebar.collapsed .toggle-btn i {
            transform: rotate(180deg);
        }
        
        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid #eee;
        }
        
        .logout-btn {
            color: #dc3545 !important;
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Include the sidebar component -->
    <?php 
    if ($user_role == 'Senior Manager (Site)') {
        include 'includes/manager_panel.php';
    } else {
        include 'components/sidebar.php';
    }
    ?>
    
    <!-- Main Content Area -->
    <div id="content-wrapper" class="flex-1 p-6 md:p-10">
        <header class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Late Coming Waveoff Page</h2>
        </header>

        <!-- Image Modal -->
        <div id="image-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-3xl w-full mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Punch In Photo</h3>
                    <button id="close-image-modal" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="mb-4 flex justify-center">
                    <img id="punch-in-image" src="" alt="Punch In Photo" class="max-h-[70vh] object-contain">
                </div>
                <div class="flex justify-end">
                    <button id="close-image-modal-button" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <div id="success-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Notification</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p id="modal-message" class="text-gray-700"></p>
                </div>
                <div class="flex justify-end">
                    <button id="confirm-modal" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        OK
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirm-modal-dialog" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-96">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                    <button id="close-confirm-modal" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p id="confirm-modal-message" class="text-gray-700"></p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button id="cancel-confirm" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Cancel
                    </button>
                    <button id="proceed-confirm" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white p-6 rounded-xl shadow-md mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <div>
                    <label for="attendance-type" class="block text-sm font-medium text-gray-700 mb-1">Attendance Type</label>
                    <select id="attendance-type" class="w-full p-2 border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="late">Late Attendance</option>
                        <option value="all">All Attendance</option>
                    </select>
                </div>
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select id="month" class="w-full p-2 border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="">Select Month</option>
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
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select id="year" class="w-full p-2 border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="">Select Year</option>
                        <option value="2023">2023</option>
                        <option value="2024">2024</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
                <div>
                    <label for="user" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <select id="user" class="w-full p-2 border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="" selected>All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white p-6 rounded-xl shadow-md">
            <!-- Toggle Button -->
            <div class="flex justify-end mb-4">
                <div class="inline-flex rounded-md shadow-sm" role="group">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-l-lg hover:bg-gray-100 hover:text-red-500 focus:z-10 focus:ring-2 focus:ring-red-500" id="studio-toggle">
                        Studio
                    </button>
                    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-r-md hover:bg-gray-100 hover:text-red-500 focus:z-10 focus:ring-2 focus:ring-red-500" id="site-toggle">
                        Site
                    </button>
                </div>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift Start Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punch In Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punch In Address</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Minutes Late</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actioned At</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="attendance-table-body" class="bg-white divide-y divide-gray-200">
                    <!-- Data will be loaded here via JavaScript -->
                </tbody>
            </table>
            
            <!-- Pagination Controls -->
            <div id="pagination-controls" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                <div class="flex flex-1 justify-between sm:hidden">
                    <button id="prev-page-mobile" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</button>
                    <button id="next-page-mobile" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</button>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span id="page-info-start">0</span> to <span id="page-info-end">0</span> of <span id="page-info-total">0</span> results
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <button id="prev-page" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Previous</span>
                                <i data-lucide="chevron-left" class="h-5 w-5"></i>
                            </button>
                            <span id="page-numbers" class="flex items-center"></span>
                            <button id="next-page" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Next</span>
                                <i data-lucide="chevron-right" class="h-5 w-5"></i>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variable to track current toggle state
        let currentToggle = '<?php echo $default_toggle; ?>';
        let currentPage = 1;
        const recordsPerPage = 35;
        let totalRecords = 0;
        let allData = [];
        
        // Initialize Lucide icons
        lucide.createIcons();

        // Set current month and year as default
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const currentMonth = now.getMonth() + 1; // getMonth() returns 0-11
            const currentYear = now.getFullYear();
            
            // Set the month dropdown
            const monthSelect = document.getElementById('month');
            if (monthSelect) {
                monthSelect.value = currentMonth;
            }
            
            // Set the year dropdown
            const yearSelect = document.getElementById('year');
            if (yearSelect) {
                yearSelect.value = currentYear;
            }
            
            // Set default toggle based on user role
            setActiveToggle('<?php echo $default_toggle; ?>');
            
            // Load attendance data
            loadAttendanceData();
            
            // Initialize sidebar toggle functionality based on user role
            <?php if ($user_role == 'Senior Manager (Site)'): ?>
            // For manager panel, add body class to adjust styling
            document.body.classList.add('manager-panel-page');
            // Manager panel has its own toggle functionality, so we don't need to initialize anything here
            <?php else: ?>
            // Initialize sidebar toggle functionality for regular sidebar
            initializeSidebar();
            <?php endif; ?>
        });

        // Add event listeners to filter elements
        document.getElementById('month').addEventListener('change', loadAttendanceData);
        document.getElementById('year').addEventListener('change', loadAttendanceData);
        document.getElementById('user').addEventListener('change', loadAttendanceData);
        document.getElementById('attendance-type').addEventListener('change', loadAttendanceData);
        
        // Add event listeners for toggle buttons
        document.getElementById('studio-toggle').addEventListener('click', function() {
            setActiveToggle('studio');
            loadAttendanceData();
        });
        
        document.getElementById('site-toggle').addEventListener('click', function() {
            setActiveToggle('site');
            loadAttendanceData();
        });
        
        // Function to set active toggle button
        function setActiveToggle(type) {
            const studioBtn = document.getElementById('studio-toggle');
            const siteBtn = document.getElementById('site-toggle');
            
            if (type === 'studio') {
                studioBtn.classList.remove('bg-white', 'text-gray-900', 'hover:bg-gray-100');
                studioBtn.classList.add('bg-red-500', 'text-white', 'hover:bg-red-600');
                siteBtn.classList.remove('bg-red-500', 'text-white', 'hover:bg-red-600');
                siteBtn.classList.add('bg-white', 'text-gray-900', 'hover:bg-gray-100');
                currentToggle = 'studio';
            } else {
                siteBtn.classList.remove('bg-white', 'text-gray-900', 'hover:bg-gray-100');
                siteBtn.classList.add('bg-red-500', 'text-white', 'hover:bg-red-600');
                studioBtn.classList.remove('bg-red-500', 'text-white', 'hover:bg-red-600');
                studioBtn.classList.add('bg-white', 'text-gray-900', 'hover:bg-gray-100');
                currentToggle = 'site';
            }
        }
        
        // Function to show the success modal
        function showModal(message) {
            document.getElementById('modal-message').textContent = message;
            document.getElementById('success-modal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        // Function to hide the success modal
        function hideModal() {
            document.getElementById('success-modal').classList.add('hidden');
        }
        
        // Function to show the confirmation modal
        function showConfirmModal(message, onConfirm) {
            document.getElementById('confirm-modal-message').textContent = message;
            document.getElementById('confirm-modal-dialog').classList.remove('hidden');
            
            // Set up the confirmation callback
            window.confirmCallback = onConfirm;
            
            lucide.createIcons();
        }
        
        // Function to hide the confirmation modal
        function hideConfirmModal() {
            document.getElementById('confirm-modal-dialog').classList.add('hidden');
        }
        
        // Close modal when clicking the close button
        document.getElementById('close-modal').addEventListener('click', hideModal);
        
        // Close modal when clicking the confirm button
        document.getElementById('confirm-modal').addEventListener('click', function() {
            hideModal();
            // Reload the table after closing the modal
            loadAttendanceData();
        });
        
        // Close modal when clicking outside the modal content
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('success-modal');
            if (event.target === modal) {
                hideModal();
                // Reload the table after closing the modal
                loadAttendanceData();
            }
        });
        
        // Handle confirmation modal buttons
        document.getElementById('close-confirm-modal').addEventListener('click', hideConfirmModal);
        document.getElementById('cancel-confirm').addEventListener('click', hideConfirmModal);
        document.getElementById('proceed-confirm').addEventListener('click', function() {
            hideConfirmModal();
            // Execute the callback if it exists
            if (typeof window.confirmCallback === 'function') {
                window.confirmCallback();
            }
        });
        
        // Close confirmation modal when clicking outside
        window.addEventListener('click', function(event) {
            const confirmModal = document.getElementById('confirm-modal-dialog');
            if (event.target === confirmModal) {
                hideConfirmModal();
            }
        });
        
        // Function to load attendance data based on filters
        function loadAttendanceData() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const userId = document.getElementById('user').value;
            const attendanceType = document.getElementById('attendance-type').value;
            
            // Reset to first page when filters change
            currentPage = 1;
            
            // Build query string
            const params = new URLSearchParams();
            if (month) params.append('month', month);
            if (year) params.append('year', year);
            if (userId) params.append('user_id', userId);
            params.append('type', currentToggle || 'studio');
            params.append('attendance_type', attendanceType);
            
            // Fetch data from backend
            fetch(`fetch_late_attendance_data.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    allData = data;
                    totalRecords = data.length;
                    updateTable();
                    updatePaginationControls();
                })
                .catch(error => {
                    console.error('Error fetching attendance data:', error);
                });
        }

        // Function to update the table with fetched data
        function updateTable() {
            const tableBody = document.getElementById('attendance-table-body');
            tableBody.innerHTML = '';
            
            // Check if current user is Senior Manager (Studio)
            const isStudioManager = '<?php echo $user_role; ?>' === 'Senior Manager (Studio)';
            const currentViewType = currentToggle;
            
            // Calculate pagination indices
            const startIndex = (currentPage - 1) * recordsPerPage;
            const endIndex = Math.min(startIndex + recordsPerPage, allData.length);
            const paginatedData = allData.slice(startIndex, endIndex);
            
            if (paginatedData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">No attendance records found</td></tr>`;
                return;
            }
            
            paginatedData.forEach((record, index) => {
                const globalIndex = startIndex + index;
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                
                // Format punch in time
                const punchInTime = record.punch_in_time || '';
                
                // Format actioned at time
                const actionedAt = record.actioned_at ? new Date(record.actioned_at).toLocaleString() : '';
                
                // Format date
                const date = record.date ? new Date(record.date).toLocaleDateString() : '';
                
                // Determine status class
                const statusClass = record.status === 'Waved Off' ? 
                    'bg-red-100 text-red-800' : 
                    'bg-green-100 text-green-800';
                
                // Determine if actions should be disabled
                // Disable if Studio Manager viewing Site records
                const disableActions = isStudioManager && currentViewType === 'site';
                
                // Action buttons HTML
                let actionButtons = '';
                if (disableActions) {
                    // Disabled buttons for Studio Manager viewing Site records
                    actionButtons = `
                        <button class="text-gray-400 cursor-not-allowed mr-4" disabled>
                            <i data-lucide="rotate-ccw" class="w-4 h-4 inline mr-1"></i>Undo
                        </button>
                        <button class="text-gray-400 cursor-not-allowed" disabled>
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Wave Off
                        </button>
                    `;
                } else {
                    // Regular buttons
                    actionButtons = `
                        <button class="text-indigo-600 hover:text-indigo-900 mr-4" onclick="undoWaveOff(${record.id})">
                            <i data-lucide="rotate-ccw" class="w-4 h-4 inline mr-1"></i>Undo
                        </button>
                        <button class="text-red-600 hover:text-red-900" onclick="waveOff(${record.id})">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Wave Off
                        </button>
                    `;
                }
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${globalIndex + 1}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${record.username}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${date}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${record.shift_start_time}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${punchInTime}
                        <button class="ml-2 text-gray-500 hover:text-gray-700 folder-icon" data-record-id="${record.id}" data-user-id="${record.user_id}" data-username="${record.username}" data-date="${record.date}">
                            <i data-lucide="folder" class="w-4 h-4 inline"></i>
                        </button>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="${record.punch_in_address || ''}">${record.punch_in_address || ''}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${record.minutes_late}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${actionedAt}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">${record.status}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        ${actionButtons}
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
            
            // Reinitialize Lucide icons for new elements
            lucide.createIcons();
            
            // Add event listeners to folder icons
            document.querySelectorAll('.folder-icon').forEach(button => {
                button.addEventListener('click', function() {
                    const recordId = this.getAttribute('data-record-id');
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const date = this.getAttribute('data-date');
                    handleFolderIconClick(recordId, username, date);
                });
            });
        }
        
        // Function to handle folder icon clicks
        function handleFolderIconClick(recordId, username, date) {
            // Find the record in allData
            const record = allData.find(item => item.id == recordId);
            
            // Log the record for debugging
            console.log('Record data:', record);
            
            // Check if we have the photo data directly in the record
            if (record && record.punch_in_photo) {
                // Check if it's a file path that needs to be converted to a data URL
                if (record.punch_in_photo.startsWith('data:image')) {
                    // It's already a data URL, use it directly
                    showImageModal(record.punch_in_photo, username, date);
                } else if (record.punch_in_photo.startsWith('http')) {
                    // It's a URL, use it directly
                    showImageModal(record.punch_in_photo, username, date);
                } else {
                    // It might be a file path, try to construct the full path
                    // Try with uploads/attendance/ prefix first
                    const fullPath = 'uploads/attendance/' + record.punch_in_photo;
                    showImageModal(fullPath, username, date);
                }
            } 
            // If we don't have photo data in the record or it's empty, fetch it from the endpoint
            else if (record) {
                fetchAttendancePhoto(record.user_id, record.date, username, date);
            } else {
                // Show a message if no record is found
                alert('No record found for this entry.');
            }
        }
        
        // Function to show the image in the modal
        function showImageModal(imageSrc, username, date) {
            const imageModal = document.getElementById('image-modal');
            const imageElement = document.getElementById('punch-in-image');
            
            // Set the image source
            imageElement.src = imageSrc;
            imageElement.alt = `Punch in photo for ${username} on ${date}`;
            
            // Add error handling for the image
            imageElement.onerror = function() {
                console.error('Failed to load image:', imageSrc);
                alert('Failed to load the punch-in photo. The image may not exist or be accessible.');
                closeImageModal();
            };
            
            // Show the modal
            imageModal.classList.remove('hidden');
            
            // Reinitialize Lucide icons
            lucide.createIcons();
        }
        
        // Function to fetch photo from the dedicated endpoint
        function fetchAttendancePhoto(userId, travelDate, username, date) {
            // Show loading state
            const imageElement = document.getElementById('punch-in-image');
            imageElement.src = ''; // Clear previous image
            imageElement.alt = 'Loading...';
            
            // Show the modal immediately
            const imageModal = document.getElementById('image-modal');
            imageModal.classList.remove('hidden');
            
            // Fetch the photo from the get_attendance_photo.php endpoint
            fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=from`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Photo data received:', data);
                    if (data.success && data.photo) {
                        showImageModal(data.photo, username, date);
                    } else {
                        // Show a message if no image is available
                        imageElement.alt = 'No punch in photo available for this record.';
                        imageElement.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4=';
                        alert('No punch in photo available for this record.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching photo:', error);
                    imageElement.alt = 'Error loading punch in photo.';
                    imageElement.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZmZmIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkVycm9yIExvYWRpbmcgSW1hZ2U8L3RleHQ+PC9zdmc+';
                    alert('Error fetching punch in photo: ' + error.message);
                });
        }
        
        // Function to close the image modal
        function closeImageModal() {
            const imageModal = document.getElementById('image-modal');
            const imageElement = document.getElementById('punch-in-image');
            
            // Hide the modal
            imageModal.classList.add('hidden');
            
            // Clear the image source
            imageElement.src = '';
        }
        
        // Add event listeners for image modal
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking the close button
            document.getElementById('close-image-modal').addEventListener('click', closeImageModal);
            document.getElementById('close-image-modal-button').addEventListener('click', closeImageModal);
            
            // Close modal when clicking outside the modal content
            document.getElementById('image-modal').addEventListener('click', function(event) {
                if (event.target === this) {
                    closeImageModal();
                }
            });
        });
        
        // Function to update pagination controls
        function updatePaginationControls() {
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            
            // Update page info text
            const startIndex = totalRecords > 0 ? (currentPage - 1) * recordsPerPage + 1 : 0;
            const endIndex = Math.min(currentPage * recordsPerPage, totalRecords);
            
            document.getElementById('page-info-start').textContent = startIndex;
            document.getElementById('page-info-end').textContent = endIndex;
            document.getElementById('page-info-total').textContent = totalRecords;
            
            // Update pagination buttons
            document.getElementById('prev-page').disabled = currentPage === 1;
            document.getElementById('prev-page-mobile').disabled = currentPage === 1;
            document.getElementById('next-page').disabled = currentPage === totalPages || totalPages === 0;
            document.getElementById('next-page-mobile').disabled = currentPage === totalPages || totalPages === 0;
            
            // Update page numbers display
            const pageNumbersContainer = document.getElementById('page-numbers');
            pageNumbersContainer.innerHTML = '';
            
            // Show up to 5 page numbers centered around current page
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            // Adjust if at the start or end
            if (endPage - startPage < 4) {
                if (startPage === 1) {
                    endPage = Math.min(totalPages, startPage + 4);
                } else if (endPage === totalPages) {
                    startPage = Math.max(1, endPage - 4);
                }
            }
            
            // Create page number buttons
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.className = `relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0`;
                if (i === currentPage) {
                    pageButton.classList.add('bg-red-500', 'text-white');
                } else {
                    pageButton.classList.add('bg-white', 'text-gray-700');
                }
                pageButton.textContent = i;
                pageButton.onclick = () => goToPage(i);
                pageNumbersContainer.appendChild(pageButton);
            }
        }
        
        // Function to go to a specific page
        function goToPage(page) {
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            if (page < 1 || page > totalPages) return;
            
            currentPage = page;
            updateTable();
            updatePaginationControls();
        }
        
        // Function to go to the next page
        function nextPage() {
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            if (currentPage < totalPages) {
                goToPage(currentPage + 1);
            }
        }
        
        // Function to go to the previous page
        function prevPage() {
            if (currentPage > 1) {
                goToPage(currentPage - 1);
            }
        }
        
        // Add event listeners for pagination controls
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('prev-page').addEventListener('click', prevPage);
            document.getElementById('next-page').addEventListener('click', nextPage);
            document.getElementById('prev-page-mobile').addEventListener('click', prevPage);
            document.getElementById('next-page-mobile').addEventListener('click', nextPage);
        });
        
        // Function to wave off late coming
        function waveOff(id) {
            showConfirmModal('Are you sure you want to wave off this late coming?', function() {
                fetch('update_waved_off_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=wave_off&attendance_id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showModal(data.message);
                    } else {
                        showModal('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showModal('An error occurred while waving off the late coming');
                });
            });
        }
        
        // Function to undo wave off
        function undoWaveOff(id) {
            showConfirmModal('Are you sure you want to undo the wave off for this late coming?', function() {
                fetch('update_waved_off_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=undo_wave_off&attendance_id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showModal(data.message);
                    } else {
                        showModal('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showModal('An error occurred while undoing the wave off');
                });
            });
        }

        // Function to initialize sidebar functionality
        function initializeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const contentWrapper = document.getElementById('content-wrapper');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            if (sidebar && toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    // Update content wrapper margin based on sidebar state
                    if (sidebar.classList.contains('collapsed')) {
                        contentWrapper.style.marginLeft = '70px';
                        contentWrapper.style.width = 'calc(100% - 70px)';
                    } else {
                        contentWrapper.style.marginLeft = '240px';
                        contentWrapper.style.width = 'calc(100% - 240px)';
                    }
                });
            }
        }
    </script>
</body>
</html>