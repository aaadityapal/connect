/**
 * Supervisor Camera Module
 * Handles camera functionality for site supervisors
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const supervisorCameraBtn = document.getElementById('supervisorCameraBtn');
    const supervisorCameraModal = document.getElementById('supervisorCameraModal');
    const closeSupervisorCameraBtn = document.getElementById('closeSupervisorCameraBtn');
    const supervisorCameraVideo = document.getElementById('supervisorCameraVideo');
    const supervisorCameraCanvas = document.getElementById('supervisorCameraCanvas');
    const supervisorCameraCaptureBtn = document.getElementById('supervisorCameraCaptureBtn');
    const supervisorCapturedImage = document.getElementById('supervisorCapturedImage');
    const supervisorCapturedImageContainer = document.querySelector('.supervisor-captured-image-container');
    const supervisorCameraContainer = document.querySelector('.supervisor-camera-container');
    const supervisorRetakeBtn = document.getElementById('supervisorRetakeBtn');
    const supervisorSaveImageBtn = document.getElementById('supervisorSaveImageBtn');
    
    // Variables
    let stream = null;
    let currentFacingMode = 'user'; // Default to front camera
    let capturedImageData = null;
    let userLocation = {
        latitude: null,
        longitude: null,
        address: 'Location not available'
    };
    let locationFetched = false; // New flag to track location status
    
    // Create location info section in modal footer
    const locationInfoElement = document.createElement('div');
    locationInfoElement.className = 'supervisor-location-info';
    locationInfoElement.innerHTML = `
        <div class="supervisor-location-coordinates">
            <i class="fas fa-map-marker-alt"></i> 
            <span id="supervisor-location-coords">Getting location...</span>
        </div>
        <div class="supervisor-location-address">
            <i class="fas fa-map"></i> 
            <span id="supervisor-location-address">Fetching address...</span>
        </div>
    `;
    
    // Create button container for footer
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'supervisor-button-container';
    
    // Insert location info and button container in footer
    const modalFooter = document.querySelector('.supervisor-camera-modal-footer');
    modalFooter.innerHTML = ''; // Clear existing content
    modalFooter.appendChild(locationInfoElement);
    modalFooter.appendChild(buttonContainer);
    
    // Move buttons to the button container
    buttonContainer.appendChild(supervisorRetakeBtn);
    buttonContainer.appendChild(supervisorSaveImageBtn);
    
    // Add camera switch button to the DOM
    const cameraSwitchBtn = document.createElement('button');
    cameraSwitchBtn.className = 'supervisor-camera-switch-btn';
    cameraSwitchBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
    cameraSwitchBtn.title = 'Switch Camera';
    supervisorCameraContainer.appendChild(cameraSwitchBtn);
    
    // Add camera frame guide
    const cameraFrameGuide = document.createElement('div');
    cameraFrameGuide.className = 'supervisor-camera-frame-guide';
    supervisorCameraContainer.appendChild(cameraFrameGuide);
    
    // Open camera modal
    supervisorCameraBtn.addEventListener('click', function() {
        // Skip if button is disabled (completed state)
        if (this.disabled) return;
        
        // Get current punch type from dataset
        const punchType = this.dataset.punchType || 'in';
        
        // Update the modal title based on punch type
        const modalTitle = document.querySelector('.supervisor-camera-modal-header h4');
        if (modalTitle) {
            modalTitle.textContent = `Take Photo for Punch ${punchType.charAt(0).toUpperCase() + punchType.slice(1)}`;
        }
        
        // Open camera
        openSupervisorCamera();
    });
    
    // Close camera modal
    closeSupervisorCameraBtn.addEventListener('click', function() {
        closeSupervisorCamera();
    });
    
    // Switch between front and back cameras
    cameraSwitchBtn.addEventListener('click', function() {
        // Add rotation animation
        this.classList.add('rotating');
        setTimeout(() => {
            this.classList.remove('rotating');
        }, 500);
        
        switchCamera();
    });
    
    // Capture image directly (no countdown)
    supervisorCameraCaptureBtn.addEventListener('click', function() {
        captureImage();
    });
    
    // Retake photo
    supervisorRetakeBtn.addEventListener('click', function() {
        retakePhoto();
    });
    
    // Save captured image
    supervisorSaveImageBtn.addEventListener('click', function() {
        saveImage();
    });
    
    // Close modal when clicking outside content
    supervisorCameraModal.addEventListener('click', function(e) {
        if (e.target === supervisorCameraModal) {
            closeSupervisorCamera();
        }
    });
    
    /**
     * Open the camera modal and initialize camera
     */
    function openSupervisorCamera() {
        supervisorCameraModal.classList.add('active');
        
        // Add entrance animation class
        supervisorCameraContainer.classList.add('fade-in');
        setTimeout(() => {
            supervisorCameraContainer.classList.remove('fade-in');
        }, 1000);
        
        initCamera(currentFacingMode);
        
        // Disable capture button initially
        supervisorCameraCaptureBtn.classList.add('disabled');
        supervisorCameraCaptureBtn.style.opacity = '0.5';
        supervisorCameraCaptureBtn.style.cursor = 'not-allowed';
        
        getLocation();
        
        // Reset UI state
        supervisorCapturedImageContainer.style.display = 'none';
        supervisorCameraContainer.style.display = 'block';
        supervisorRetakeBtn.style.display = 'none';
        supervisorSaveImageBtn.style.display = 'none';
        
        // Check if this is a punch out
        const punchType = supervisorCameraBtn.dataset.punchType || 'in';
        if (punchType === 'out') {
            showWorkReportField();
        } else {
            hideWorkReportField();
        }
        
        updateSaveButtonText();
    }
    
    /**
     * Close the camera modal and stop camera stream
     */
    function closeSupervisorCamera() {
        supervisorCameraModal.classList.remove('active');
        stopCameraStream();
    }
    
    /**
     * Get user's current location
     */
    function getLocation() {
        const coordsElement = document.getElementById('supervisor-location-coords');
        const addressElement = document.getElementById('supervisor-location-address');
        
        // Reset location display and status
        coordsElement.textContent = 'Getting location...';
        coordsElement.className = '';
        addressElement.textContent = 'Fetching address...';
        locationFetched = false;
        
        // Add pulsing animation to location info
        locationInfoElement.classList.add('pulsing');
        
        // Check if geolocation is available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Stop pulsing animation
                    locationInfoElement.classList.remove('pulsing');
                    
                    // Store location data
                    userLocation.latitude = position.coords.latitude;
                    userLocation.longitude = position.coords.longitude;
                    
                    // Display coordinates
                    coordsElement.textContent = `Lat: ${position.coords.latitude.toFixed(6)}, Long: ${position.coords.longitude.toFixed(6)}`;
                    coordsElement.className = 'location-success';
                    
                    // Get address from coordinates
                    getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
                    
                    // Enable capture button
                    enableCaptureButton();
                },
                function(error) {
                    // Stop pulsing animation
                    locationInfoElement.classList.remove('pulsing');
                    
                    // Handle location error
                    coordsElement.textContent = 'Location error: ' + getLocationErrorMessage(error);
                    coordsElement.className = 'location-error';
                    addressElement.textContent = 'Address unavailable';
                    
                    // Show warning notification
                    showNotification('Location Warning', 'Unable to get your location. Photo capture is disabled.', 'warning');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            // Stop pulsing animation
            locationInfoElement.classList.remove('pulsing');
            
            coordsElement.textContent = 'Geolocation not supported by this browser';
            coordsElement.className = 'location-error';
            addressElement.textContent = 'Address unavailable';
            
            // Show warning notification
            showNotification('Location Warning', 'Geolocation is not supported by your browser. Photo capture is disabled.', 'warning');
        }
    }
    
    /**
     * Enable the capture button once location is fetched
     */
    function enableCaptureButton() {
        locationFetched = true;
        supervisorCameraCaptureBtn.classList.remove('disabled');
        supervisorCameraCaptureBtn.style.opacity = '1';
        supervisorCameraCaptureBtn.style.cursor = 'pointer';
        
        // Add a visual indicator that the button is now enabled
        supervisorCameraCaptureBtn.classList.add('pulse-once');
        setTimeout(() => {
            supervisorCameraCaptureBtn.classList.remove('pulse-once');
        }, 1000);
        
        // Show notification
        showNotification('Location Found', 'Your location has been found. You can now take a photo.', 'success');
    }
    
    /**
     * Get human-readable error message for geolocation errors
     */
    function getLocationErrorMessage(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return "Location permission denied";
            case error.POSITION_UNAVAILABLE:
                return "Location information unavailable";
            case error.TIMEOUT:
                return "Location request timed out";
            case error.UNKNOWN_ERROR:
                return "Unknown location error";
            default:
                return "Error getting location";
        }
    }
    
    /**
     * Get address from coordinates using reverse geocoding
     */
    function getAddressFromCoordinates(latitude, longitude) {
        const addressElement = document.getElementById('supervisor-location-address');
        
        // Use Nominatim API for reverse geocoding (free and no API key required)
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
        
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'HR Attendance System' // Nominatim requires a user agent
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Geocoding service failed');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.display_name) {
                // Store the address
                userLocation.address = data.display_name;
                
                // Display full address without truncation
                // Create a span to ensure proper styling
                addressElement.innerHTML = ''; // Clear existing content
                const addressSpan = document.createElement('span');
                addressSpan.textContent = data.display_name;
                addressSpan.className = 'location-success';
                addressElement.appendChild(addressSpan);
            } else {
                throw new Error('No address found');
            }
        })
        .catch(error => {
            console.error('Error getting address:', error);
            addressElement.textContent = 'Address could not be determined';
            addressElement.className = 'location-error';
        });
    }
    
    /**
     * Initialize camera with specified facing mode
     */
    function initCamera(facingMode) {
        // Stop any existing stream
        stopCameraStream();
        
        // Set up camera constraints
        const constraints = {
            video: {
                facingMode: facingMode,
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };
        
        // Request camera access
        navigator.mediaDevices.getUserMedia(constraints)
            .then(function(mediaStream) {
                stream = mediaStream;
                supervisorCameraVideo.srcObject = mediaStream;
                
                // Play video
                supervisorCameraVideo.play()
                    .catch(function(error) {
                        console.error('Error playing video:', error);
                        showCameraError('Could not start video playback');
                    });
            })
            .catch(function(error) {
                console.error('Error accessing camera:', error);
                handleCameraError(error);
            });
    }
    
    /**
     * Stop the camera stream
     */
    function stopCameraStream() {
        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop();
            });
            stream = null;
        }
    }
    
    /**
     * Switch between front and back cameras
     */
    function switchCamera() {
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        initCamera(currentFacingMode);
    }
    
    /**
     * Capture image from video stream
     */
    function captureImage() {
        // Check if location has been fetched
        if (!locationFetched) {
            showNotification('Location Required', 'Please wait for your location to be determined before taking a photo.', 'warning');
            return;
        }
        
        // Create flash effect
        const flash = document.createElement('div');
        flash.className = 'camera-flash';
        supervisorCameraContainer.appendChild(flash);
        
        // Add camera shutter sound
        const shutterSound = new Audio('sounds/camera-shutter.mp3');
        shutterSound.play().catch(e => console.log('Audio play failed:', e));
        
        // Remove flash after animation completes
        setTimeout(() => {
            flash.remove();
        }, 500);
        
        // Capture frame from video
        supervisorCameraCanvas.width = supervisorCameraVideo.videoWidth;
        supervisorCameraCanvas.height = supervisorCameraVideo.videoHeight;
        const context = supervisorCameraCanvas.getContext('2d');
        context.drawImage(supervisorCameraVideo, 0, 0, supervisorCameraCanvas.width, supervisorCameraCanvas.height);
        
        // Get image data
        capturedImageData = supervisorCameraCanvas.toDataURL('image/jpeg', 0.9);
        supervisorCapturedImage.src = capturedImageData;
        
        // Show captured image
        supervisorCapturedImageContainer.style.opacity = 0;
        supervisorCapturedImageContainer.style.display = 'block';
        supervisorCameraContainer.style.display = 'none';
        
        // Ensure buttons are visible
        supervisorRetakeBtn.style.display = 'block';
        supervisorSaveImageBtn.style.display = 'block';
        
        // Fade in captured image
        setTimeout(() => {
            supervisorCapturedImageContainer.style.opacity = 1;
        }, 100);
    }
    
    /**
     * Retake photo
     */
    function retakePhoto() {
        // Show camera view again
        supervisorCapturedImageContainer.style.display = 'none';
        supervisorCameraContainer.style.display = 'block';
        supervisorRetakeBtn.style.display = 'none';
        supervisorSaveImageBtn.style.display = 'none';
        
        // Clear captured image data
        capturedImageData = null;
    }
    
    /**
     * Save the captured image
     */
    function saveImage() {
        // Get current punch type
        const punchType = supervisorCameraBtn.dataset.punchType || 'in';
        
        // For punch out, validate work report
        if (punchType === 'out') {
            const workReportText = document.getElementById('supervisorWorkReportText');
            if (!workReportText || !workReportText.value.trim()) {
                showNotification('Error', 'Please fill out the work report before punching out', 'error');
                
                // Highlight the textarea
                if (workReportText) {
                    workReportText.classList.add('is-invalid');
                    workReportText.focus();
                    
                    // Remove highlight when user starts typing
                    workReportText.addEventListener('input', function() {
                        this.classList.remove('is-invalid');
                    }, { once: true });
                }
                
                return;
            }
        }
        
        if (!capturedImageData) {
            showNotification('Error', 'No image captured', 'error');
            return;
        }
        
        // Show loading state
        supervisorSaveImageBtn.disabled = true;
        supervisorSaveImageBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        // Create a form to send the image data
        const formData = new FormData();
        formData.append('photo_data', capturedImageData);
        formData.append('punch_type', punchType);
        
        // Add location data if available
        if (userLocation.latitude && userLocation.longitude) {
            formData.append('latitude', userLocation.latitude);
            formData.append('longitude', userLocation.longitude);
            formData.append('accuracy', 10); // Default accuracy
            formData.append('address', userLocation.address);
        }
        
        // Add work report if punching out
        if (punchType === 'out') {
            const workReport = document.getElementById('supervisorWorkReportText').value;
            formData.append('work_report', workReport);
        }
        
        // Send to server
        fetch('api/process_punch.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                showNotification('Success', `Punch ${punchType} recorded successfully`, 'success');
                closeSupervisorCamera();
                
                // Update the button state to reflect the new punch status
                checkPunchStatus();
                
                // If this was a punch out, show additional info
                if (punchType === 'out' && data.working_hours) {
                    showNotification('Work Summary', `You worked for ${data.working_hours}`, 'info');
                }
            } else {
                showNotification('Error', data.message || 'Failed to record punch', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving punch data:', error);
            showNotification('Error', 'Failed to record punch: ' + error.message, 'error');
        })
        .finally(() => {
            supervisorSaveImageBtn.disabled = false;
            supervisorSaveImageBtn.innerHTML = punchType === 'in' ? 'Punch In' : 'Punch Out';
        });
    }
    
    /**
     * Check current punch status and update button accordingly
     */
    function checkPunchStatus() {
        fetch('api/check_punch_status.php')
        .then(response => response.json())
        .then(data => {
            // Update button text and class based on punch status
            if (data.is_punched_in) {
                if (!data.is_completed) {
                    // User is punched in but not out
                    supervisorCameraBtn.innerHTML = '<i class="fas fa-camera"></i> Punch Out ';
                    supervisorCameraBtn.classList.remove('btn-primary');
                    supervisorCameraBtn.classList.add('btn-danger');
                    
                    // Store punch type for next action
                    supervisorCameraBtn.dataset.punchType = 'out';
                } else {
                    // User has completed attendance for today
                    supervisorCameraBtn.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                    supervisorCameraBtn.classList.remove('btn-primary', 'btn-danger');
                    supervisorCameraBtn.classList.add('btn-secondary');
                    supervisorCameraBtn.disabled = true;
                }
            } else {
                // User has not punched in
                supervisorCameraBtn.innerHTML = '<i class="fas fa-camera"></i> Punch In';
                supervisorCameraBtn.classList.remove('btn-danger', 'btn-secondary');
                supervisorCameraBtn.classList.add('btn-primary');
                supervisorCameraBtn.disabled = false;
                
                // Store punch type for next action
                supervisorCameraBtn.dataset.punchType = 'in';
            }
        })
        .catch(error => {
            console.error('Error checking punch status:', error);
        });
    }
    
    /**
     * Handle camera errors
     */
    function handleCameraError(error) {
        let errorMessage = 'Camera error';
        
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Camera access denied. Please allow camera access in your browser settings.';
        } else if (error.name === 'NotFoundError') {
            errorMessage = 'No camera found on this device.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            errorMessage = 'Camera is in use by another application.';
        } else if (error.name === 'OverconstrainedError') {
            // Try again with relaxed constraints
            initCamera({ video: true, audio: false });
            return;
        } else {
            errorMessage = `Camera error: ${error.message}`;
        }
        
        showCameraError(errorMessage);
    }
    
    /**
     * Display camera error message
     */
    function showCameraError(message) {
        // Create error overlay
        const errorOverlay = document.createElement('div');
        errorOverlay.className = 'supervisor-camera-error';
        errorOverlay.innerHTML = `
            <div class="supervisor-camera-error-content">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
                <button class="btn btn-sm btn-warning retry-camera-btn">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        `;
        
        // Add to camera container
        supervisorCameraContainer.appendChild(errorOverlay);
        
        // Add retry functionality
        errorOverlay.querySelector('.retry-camera-btn').addEventListener('click', function() {
            errorOverlay.remove();
            initCamera(currentFacingMode);
        });
    }
    
    /**
     * Show notification toast
     */
    function showNotification(title, message, type) {
        // Check if notification container exists
        let notificationContainer = document.getElementById('supervisor-notification-container');
        
        if (!notificationContainer) {
            // Create notification container
            notificationContainer = document.createElement('div');
            notificationContainer.id = 'supervisor-notification-container';
            notificationContainer.className = 'supervisor-notification-container';
            document.body.appendChild(notificationContainer);
            
            // Add styles if not already added
            if (!document.getElementById('supervisor-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'supervisor-notification-styles';
                style.textContent = `
                    .supervisor-notification-container {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                    }
                    
                    .supervisor-notification {
                        background-color: white;
                        border-radius: 8px;
                        padding: 15px;
                        margin-bottom: 10px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        min-width: 280px;
                        max-width: 350px;
                        transform: translateX(100%);
                        opacity: 0;
                        transition: all 0.3s ease;
                    }
                    
                    .supervisor-notification.show {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    
                    .supervisor-notification-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 8px;
                    }
                    
                    .supervisor-notification-title {
                        font-weight: bold;
                        font-size: 1rem;
                    }
                    
                    .supervisor-notification-close {
                        background: none;
                        border: none;
                        font-size: 1.2rem;
                        cursor: pointer;
                        color: #999;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 24px;
                        height: 24px;
                    }
                    
                    .supervisor-notification-body {
                        font-size: 0.9rem;
                        color: #555;
                    }
                    
                    .supervisor-notification-success {
                        border-left: 4px solid #28a745;
                    }
                    
                    .supervisor-notification-error {
                        border-left: 4px solid #dc3545;
                    }
                    
                    .supervisor-notification-warning {
                        border-left: 4px solid #ffc107;
                    }
                    
                    .supervisor-notification-info {
                        border-left: 4px solid #17a2b8;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `supervisor-notification supervisor-notification-${type}`;
        notification.innerHTML = `
            <div class="supervisor-notification-header">
                <div class="supervisor-notification-title">${title}</div>
                <button class="supervisor-notification-close">&times;</button>
            </div>
            <div class="supervisor-notification-body">${message}</div>
        `;
        
        // Add to container
        notificationContainer.appendChild(notification);
        
        // Show notification with slight delay for animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Add close functionality
        notification.querySelector('.supervisor-notification-close').addEventListener('click', function() {
            closeNotification(notification);
        });
        
        // Auto close after 5 seconds
        setTimeout(() => {
            closeNotification(notification);
        }, 5000);
    }
    
    /**
     * Close notification with animation
     */
    function closeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
    
    // Add keyboard support (Escape to close modal)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && supervisorCameraModal.classList.contains('active')) {
            closeSupervisorCamera();
        }
    });
    
    // Check punch status when page loads
    checkPunchStatus();
    
    // Check punch status periodically to keep button state updated
    setInterval(checkPunchStatus, 60000); // Check every minute
    
    /**
     * Show work report field when punching out
     */
    function showWorkReportField() {
        // Create work report container if it doesn't exist
        if (!document.getElementById('supervisorWorkReportContainer')) {
            const workReportContainer = document.createElement('div');
            workReportContainer.id = 'supervisorWorkReportContainer';
            workReportContainer.className = 'supervisor-work-report-container';
            workReportContainer.innerHTML = `
                <div class="supervisor-work-report-header">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Daily Work Report</span>
                </div>
                <div class="form-group">
                    <textarea id="supervisorWorkReportText" class="form-control" rows="4" 
                        placeholder="Please describe the work you completed today..." required></textarea>
                    <small class="form-text text-muted">* This field is mandatory for punch out</small>
                </div>
            `;
            
            // Insert before the button container
            const modalFooter = document.querySelector('.supervisor-camera-modal-footer');
            modalFooter.insertBefore(workReportContainer, document.querySelector('.supervisor-button-container'));
        }
        
        // Show the work report container
        document.getElementById('supervisorWorkReportContainer').style.display = 'block';
    }
    
    /**
     * Hide work report field
     */
    function hideWorkReportField() {
        const workReportContainer = document.getElementById('supervisorWorkReportContainer');
        if (workReportContainer) {
            workReportContainer.style.display = 'none';
        }
    }
    
    function updateSaveButtonText() {
        const punchType = supervisorCameraBtn.dataset.punchType || 'in';
        supervisorSaveImageBtn.innerHTML = punchType === 'in' ? 'Punch In' : 'Punch Out';
    }
}); 