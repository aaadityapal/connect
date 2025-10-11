<?php
session_start();
// Simulate user authentication
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'employee';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Missing Punches Modal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        /* Work Report Modal Styles (copied from dashboard-styles.css) */
        .work-report-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .work-report-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }

        .work-report-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .work-report-header h3 {
            font-size: 18px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .work-report-header h3 i {
            font-size: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }

        .close-modal:hover {
            color: #333;
        }

        .work-report-body {
            padding: 20px;
        }

        .work-report-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .cancel-btn, .submit-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .cancel-btn {
            background: #f5f5f5;
            border: 1px solid #ddd;
            color: #666;
        }

        .cancel-btn:hover {
            background: #eee;
        }

        .submit-btn {
            background: #4a6cf7;
            border: none;
            color: white;
        }

        .submit-btn:hover {
            background: #3a5cdc;
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn {
            background: #4a6cf7;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: inline-block;
            margin: 10px 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #3a5cdc;
        }
        
        /* Additional styles for the modal content */
        .missing-punch-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #4a6cf7;
            transition: all 0.3s ease;
            position: relative;
        }

        .missing-punch-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .missing-punch-item.punch-out {
            border-left-color: #10b981;
        }

        .missing-punch-item i {
            font-size: 20px;
            margin-right: 15px;
            width: 24px;
            text-align: center;
        }

        .missing-punch-item.punch-in i {
            color: #4a6cf7;
        }

        .missing-punch-item.punch-out i {
            color: #10b981;
        }

        .missing-punch-details {
            flex: 1;
        }

        .missing-punch-date {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .missing-punch-type {
            font-size: 14px;
            color: #7f8c8d;
        }

        .missing-punch-status {
            font-weight: 600;
            color: #e74c3c;
            font-size: 14px;
        }

        .missing-punch-timer {
            font-size: 13px;
            color: #e74c3c;
            font-weight: 600;
            background: #ffeaea;
            padding: 5px 10px;
            border-radius: 20px;
            margin-top: 8px;
            display: inline-block;
        }

        .missing-punch-timer.warning {
            color: #f39c12;
            background: #fff9e6;
        }

        .missing-punch-timer.safe {
            color: #27ae60;
            background: #e8f7ef;
        }

        .no-missing-punches {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .no-missing-punches i {
            font-size: 48px;
            color: #10b981;
            margin-bottom: 15px;
        }

        .error-message {
            text-align: center;
            padding: 30px 20px;
            background: #fff3f3;
            border-radius: 8px;
            border: 1px solid #ffcfcf;
            color: #c0392b;
        }

        .error-message i {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .loading-content {
            text-align: center;
            padding: 30px 20px;
        }

        .loading-content i {
            font-size: 48px;
            color: #4a6cf7;
            margin-bottom: 15px;
        }

        .alert-box {
            margin-bottom: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-box i {
            margin-right: 8px;
        }

        .scrollable-content {
            max-height: 350px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .action-btn.punch-in {
            background: #4a6cf7;
            color: white;
        }

        .action-btn.punch-out {
            background: #10b981;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Missing Punches Modal</h1>
        
        <div class="info-box">
            <p>This page demonstrates the missing punches modal that fetches data from <code>ajax_handlers/get_missing_punches.php</code>.</p>
            <p>Click the button below to show the modal:</p>
        </div>
        
        <button class="btn" onclick="showTestModal()"><i class="fas fa-eye"></i> Show Missing Punches Modal</button>
        <button class="btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh Page</button>
        
        <!-- Include the instant modal -->
        <?php include 'instant_modal.php'; ?>
    </div>
    
    <script>
        // Function to show the modal for testing
        function showTestModal() {
            const modal = document.getElementById('instantModal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(function() { 
                    modal.classList.add('active'); 
                }, 10);
                
                // Fetch missing punches data when showing the modal
                // This simulates what happens when the modal is shown on page load
                if (typeof fetchMissingPunches === 'function') {
                    fetchMissingPunches();
                }
            }
        }
        
        // Add a simple fetchMissingPunches function for testing purposes
        function fetchMissingPunches() {
            // In a real implementation, this would fetch from the server
            // For testing, we'll simulate the response
            const missingPunchesContent = document.getElementById('missingPunchesContent');
            
            // Simulate loading for 1 second
            setTimeout(() => {
                // Simulate data with missing punches
                const mockData = {
                    success: true,
                    count: 3,
                    data: [
                        {
                            id: 101,
                            date: '2023-06-15',
                            type: 'punch_in',
                            punch_in: null,
                            punch_out: '17:30:00'
                        },
                        {
                            id: 102,
                            date: '2023-06-14',
                            type: 'punch_out',
                            punch_in: '09:00:00',
                            punch_out: null
                        },
                        {
                            id: 103,
                            date: '2023-06-10',
                            type: 'punch_in',
                            punch_in: null,
                            punch_out: null
                        }
                    ]
                };
                
                // Display the mock data
                if (mockData.success && mockData.count > 0) {
                    displayMissingPunches(mockData.data);
                } else if (mockData.success) {
                    displayNoMissingPunches();
                } else {
                    displayError('Failed to load missing punches data');
                }
            }, 1000);
        }
        
        // Mock functions for testing
        function displayMissingPunches(missingPunches) {
            // Sort punches by date (newest first - descending order)
            missingPunches.sort((a, b) => new Date(b.date) - new Date(a.date));
            
            // Group punches by date
            const groupedPunches = {};
            missingPunches.forEach(punch => {
                if (!groupedPunches[punch.date]) {
                    groupedPunches[punch.date] = [];
                }
                groupedPunches[punch.date].push(punch);
            });

            // Create HTML for display
            let html = `
                <div class="alert-box">
                    <p style="margin: 0; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> 
                        You have <strong>${missingPunches.length}</strong> missing punch records in the last 15 days. Please submit the missing punches before the deadline to avoid being marked absent.
                    </p>
                </div>
                <div class="scrollable-content">
            `;

            // Display each date with its missing punches
            for (const [date, punches] of Object.entries(groupedPunches)) {
                const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                html += `<div style="margin-bottom: 20px;">`;
                html += `<h4 style="margin: 0 0 10px 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 8px;">${formattedDate}</h4>`;
                
                // Sort punches for this date by type (punch_in first, then punch_out)
                punches.sort((a, b) => {
                    if (a.type === b.type) return 0;
                    return a.type === 'punch_in' ? -1 : 1;
                });
                
                punches.forEach(punch => {
                    const punchType = punch.type === 'punch_in' ? 'Punch In' : 'Punch Out';
                    const icon = punch.type === 'punch_in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
                    const cssClass = punch.type === 'punch_in' ? 'punch-in' : 'punch-out';
                    
                    // Calculate deadline (24 hours from the missing punch date)
                    const punchDate = new Date(punch.date);
                    const deadline = new Date(punchDate);
                    deadline.setDate(deadline.getDate() + 1); // 24 hours from the date
                    
                    // Calculate time remaining
                    const now = new Date();
                    const timeDiff = deadline.getTime() - now.getTime();
                    const hoursRemaining = Math.floor(timeDiff / (1000 * 60 * 60));
                    const minutesRemaining = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                    
                    // Determine timer class based on time remaining
                    let timerClass = 'missing-punch-timer';
                    if (hoursRemaining < 0) {
                        timerClass += ' expired'; // Red for expired
                    } else if (hoursRemaining < 6) {
                        timerClass += ' warning'; // Orange for urgent
                    } else {
                        timerClass += ' safe'; // Green for safe
                    }
                    
                    // Format timer text
                    let timerText;
                    if (hoursRemaining < 0) {
                        timerText = 'Deadline passed - Marked as absent';
                    } else {
                        timerText = `Submit within ${hoursRemaining}h ${minutesRemaining}m to avoid being marked absent`;
                    }
                    
                    html += `
                        <div class="missing-punch-item ${cssClass}">
                            <i class="fas ${icon}"></i>
                            <div class="missing-punch-details">
                                <div class="missing-punch-date">${punchType}</div>
                                <div class="missing-punch-type">Attendance Record ID: ${punch.id || 'N/A'}</div>
                                <div class="${timerClass}">${timerText}</div>
                            </div>
                            <div class="missing-punch-status">Missing</div>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            html += `</div>`;

            document.getElementById('missingPunchesContent').innerHTML = html;
        }
        
        function displayNoMissingPunches() {
            document.getElementById('missingPunchesContent').innerHTML = `
                <div class="no-missing-punches">
                    <i class="fas fa-check-circle"></i>
                    <h4 style="margin-bottom: 15px; color: #2c3e50;">No Missing Punch Records</h4>
                    <p style="margin: 0; color: #7f8c8d; font-size: 16px;">
                        Great job! You don't have any missing punch records in the last 15 days.
                    </p>
                    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                        <p style="margin: 0; font-size: 14px; color: #2e7d32;">
                            <i class="fas fa-lightbulb"></i> 
                            Remember to continue punching in and out as required.
                        </p>
                    </div>
                </div>
            `;
        }
        
        function displayError(message) {
            document.getElementById('missingPunchesContent').innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4 style="margin-bottom: 15px; color: #c0392b;">Error Loading Data</h4>
                    <p style="margin: 0; font-size: 16px;">${message}</p>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <p style="margin: 0; font-size: 14px; color: #7f8c8d;">
                            <i class="fas fa-info-circle"></i> 
                            Please try refreshing the page or contact support if the issue persists.
                        </p>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>