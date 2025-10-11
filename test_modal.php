<!DOCTYPE html>
<html>
<head>
    <title>Modal Test</title>
    <style>
        .work-report-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .work-report-modal.active { display: flex; }

        .work-report-content {
            background: #ffffff;
            width: 90%;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalSlideIn 0.25s ease;
        }
        .work-report-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .work-report-body { padding: 16px 20px; }
        .work-report-footer {
            padding: 16px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
        }
        .submit-btn {
            background: #4a6cf7;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <h1>Modal Test Page</h1>
    <p>This is a test page to verify modal functionality.</p>

    <!-- Custom Instant Modal -->
    <div class="work-report-modal" id="instantModal" style="display: none; z-index: 3000;">
        <div class="work-report-content" style="max-width: 500px;">
            <div class="work-report-header">
                <h3>Welcome to Your Dashboard</h3>
                <button class="close-modal" id="closeInstantModal">
                    <i class="fas fa-times">×</i>
                </button>
            </div>
            <div class="work-report-body">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-bell" style="font-size: 48px; color: #4a6cf7; margin-bottom: 15px;"></i>
                    <h4 style="margin-bottom: 15px;">Important Notice</h4>
                    <p>Welcome to your employee dashboard. Please review your tasks and deadlines for today.</p>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <p style="margin: 0; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> 
                            Remember to punch in when you start work and punch out when you leave.
                        </p>
                    </div>
                </div>
            </div>
            <div class="work-report-footer">
                <button class="submit-btn" id="acknowledgeBtn" style="width: 100%;">Acknowledge</button>
            </div>
        </div>
    </div>

    <script>
    // Show instant modal when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Check if the modal element exists before trying to use it
        const instantModal = document.getElementById('instantModal');
        if (!instantModal) {
            console.error('Instant modal not found');
            return;
        }
        
        const closeInstantModal = document.getElementById('closeInstantModal');
        const acknowledgeBtn = document.getElementById('acknowledgeBtn');

        // Function to show the instant modal
        function showInstantModal() {
            instantModal.style.display = 'flex';
            setTimeout(function() { 
                instantModal.classList.add('active'); 
            }, 10);
        }

        // Function to hide the instant modal
        function hideInstantModal() {
            instantModal.classList.remove('active');
            setTimeout(function() { 
                instantModal.style.display = 'none'; 
            }, 300);
        }

        // Event listeners for closing the modal
        if (closeInstantModal) {
            closeInstantModal.addEventListener('click', hideInstantModal);
        }
        
        if (acknowledgeBtn) {
            acknowledgeBtn.addEventListener('click', hideInstantModal);
        }

        // Show the modal instantly when page loads
        showInstantModal();
    });
    </script>
</body>
</html>