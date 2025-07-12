<!-- Update Modal Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Banner Styles */
    .updates-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.6);
        z-index: 1000;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        backdrop-filter: blur(3px);
    }
    
    .updates-overlay.visible {
        opacity: 1;
        visibility: visible;
    }
    
    .updates-banner {
        background-color: white;
        width: 90%;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        transform: translateY(20px);
        transition: transform 0.3s ease-out;
    }
    
    .updates-overlay.visible .updates-banner {
        transform: translateY(0);
    }
    
    .updates-header {
        background: linear-gradient(135deg, #4a6fa5 0%, #3a5a8a 100%);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .updates-title {
        font-weight: 600;
        font-size: 1.3em;
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .updates-title:before {
        content: "🔔";
        margin-right: 10px;
        font-size: 1.1em;
    }
    
    .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.8em;
        cursor: pointer;
        padding: 0 0 0 15px;
        line-height: 1;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    
    .close-btn:hover {
        opacity: 1;
    }
    
    .updates-body {
        padding: 25px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .update-item {
        display: flex;
        margin-bottom: 20px;
        align-items: flex-start;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .update-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .update-icon {
        background-color: #e8f0f8;
        color: #4a6fa5;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .update-text {
        flex: 1;
    }
    
    .update-text strong {
        color: #2c3e50;
        font-size: 1.05em;
    }
    
    .update-date {
        font-size: 0.85em;
        color: #888;
        margin-top: 5px;
        display: block;
    }
    
    .updates-footer {
        padding: 15px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #f9f9f9;
    }
    
    .dont-show-again {
        display: flex;
        align-items: center;
        font-size: 0.9em;
        color: #555;
    }
    
    .dont-show-again input {
        margin-right: 6px;
    }
    
    .got-it-btn {
        background: linear-gradient(135deg, #4a6fa5 0%, #3a5a8a 100%);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.95em;
        transition: all 0.2s;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(74, 111, 165, 0.2);
    }
    
    .got-it-btn:hover {
        background: linear-gradient(135deg, #3a5a8a 0%, #2d4a75 100%);
        box-shadow: 0 4px 8px rgba(74, 111, 165, 0.3);
    }
    
    /* Animation for new items */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .update-item {
        animation: fadeIn 0.3s ease forwards;
        opacity: 0;
    }
    
    .update-item:nth-child(1) { animation-delay: 0.1s; }
    .update-item:nth-child(2) { animation-delay: 0.2s; }
    .update-item:nth-child(3) { animation-delay: 0.3s; }
    .update-item:nth-child(4) { animation-delay: 0.4s; }
    
    /* Feature highlight styles */
    .feature-highlight {
        background: linear-gradient(135deg, #f0f9ff 0%, #e1f5fe 100%);
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        border-left: 4px solid #4a6fa5;
    }
    
    .feature-highlight .update-icon {
        background-color: #4a6fa5;
        color: white;
        width: 36px;
        height: 36px;
        font-size: 1.2em;
    }
    
    .feature-highlight .update-text strong {
        color: #1e3a8a;
        font-size: 1.1em;
    }
    
    .feature-description {
        margin-top: 10px;
        color: #334155;
        line-height: 1.5;
    }
    
    .feature-benefits {
        display: flex;
        margin-top: 12px;
        gap: 10px;
    }
    
    .benefit-item {
        background: white;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.85em;
        color: #334155;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .benefit-item i {
        color: #4a6fa5;
        font-size: 0.9em;
    }
</style>

<!-- Updates Overlay and Banner -->
<div class="updates-overlay" id="updatesOverlay">
    <div class="updates-banner">
        <div class="updates-header">
            <h2 class="updates-title">What's New</h2>
            <button class="close-btn" id="closeBannerBtn">&times;</button>
        </div>
        <div class="updates-body">
            <div class="update-item feature-highlight">
                <div class="update-icon">🌍</div>
                <div class="update-text">
                    <strong>New Geofencing Technology</strong> - Smart Location Awareness
                    <span class="update-date">Aug 15, 2024</span>
                    <div class="feature-description">
                        We've implemented advanced geofencing technology that validates attendance within a 30-meter radius of the office location. This ensures accurate time tracking while providing flexibility.
                    </div>
                    <div class="feature-benefits">
                        <div class="benefit-item"><i class="fas fa-check-circle"></i> Accurate tracking</div>
                        <div class="benefit-item"><i class="fas fa-map-marker-alt"></i> 30m flexibility</div>
                        <div class="benefit-item"><i class="fas fa-shield-alt"></i> Prevents misuse</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="updates-footer">
            <label class="dont-show-again">
                <input type="checkbox" id="dontShowAgain"> Don't show until next update
            </label>
            <button class="got-it-btn" id="gotItBtn">Got It!</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('updatesOverlay');
        const closeBtn = document.getElementById('closeBannerBtn');
        const gotItBtn = document.getElementById('gotItBtn');
        const dontShowAgain = document.getElementById('dontShowAgain');
        
        // Check if user has opted out of seeing updates
        checkUserPreference();
        
        // Show the modal automatically when page loads (if user hasn't opted out)
        function checkUserPreference() {
            // Make an AJAX request to check if user has opted out
            fetch('ajax_handlers/check_update_preference.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.dontShow) {
                        setTimeout(() => {
                            overlay.classList.add('visible');
                        }, 1000); // Small delay for better user experience
                    }
                })
                .catch(error => {
                    console.error('Error checking update preference:', error);
                    // If error, show the modal anyway
                    setTimeout(() => {
                        overlay.classList.add('visible');
                    }, 1000);
                });
        }
        
        // Close button functionality
        closeBtn.addEventListener('click', closeBanner);
        gotItBtn.addEventListener('click', function() {
            if (dontShowAgain.checked) {
                saveUserPreference(true);
            }
            closeBanner();
        });
        
        // Close when clicking outside the banner
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                if (dontShowAgain.checked) {
                    saveUserPreference(true);
                }
                closeBanner();
            }
        });
        
        function closeBanner() {
            overlay.classList.remove('visible');
        }
        
        function saveUserPreference(dontShow) {
            // Make an AJAX request to save user preference
            fetch('ajax_handlers/save_update_preference.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'dontShow=' + (dontShow ? '1' : '0')
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to save preference:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving update preference:', error);
            });
        }
        
        // Keyboard accessibility - close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('visible')) {
                if (dontShowAgain.checked) {
                    saveUserPreference(true);
                }
                closeBanner();
            }
        });
    });
</script>