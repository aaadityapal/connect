/**
 * Greeting Section Functionality
 * Handles time-based greetings and real-time date/time display
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const greetingTimeEl = document.getElementById('greeting-time');
    const greetingIconEl = document.getElementById('greeting-icon');
    const smallCurrentTimeEl = document.getElementById('small-current-time');
    const smallCurrentDateEl = document.getElementById('small-current-date');
    const shiftRemainingTimeEl = document.getElementById('shift-remaining-time');
    const punchButton = document.getElementById('punchButton');
    
    // Camera container elements
    const cameraContainer = document.getElementById('cameraContainer');
    const cameraTitle = document.getElementById('camera-title');
    const closeCameraBtn = document.getElementById('closeCameraBtn');
    const cameraVideo = document.getElementById('cameraVideo');
    const cameraCanvas = document.getElementById('cameraCanvas');
    const cameraCaptureBtn = document.getElementById('cameraCaptureBtn');
    const capturedImage = document.getElementById('capturedImage');
    const videoWrapper = document.querySelector('.punch-video-wrapper');
    const capturedImageWrapper = document.querySelector('.punch-captured-image-wrapper');
    const retakePhotoBtn = document.getElementById('retakePhotoBtn');
    const confirmPunchBtn = document.getElementById('confirmPunchBtn');
    const locationStatus = document.getElementById('locationStatus');
    const locationCoords = document.getElementById('locationCoords');
    const locationAddress = document.getElementById('locationAddress');
    
    // Work report elements
    const workReportSection = document.getElementById('workReportSection');
    const workReportText = document.getElementById('workReportText');
    
    // Set initial punch status (would come from server in real implementation)
    let isPunchedIn = false;
    let isCompletedForToday = false; // New flag to track if attendance is completed for the day
    let stream = null;
    let capturedPhotoData = null;
    let userLocation = null;
    
    // User shift information (will be populated from server)
    let userShiftInfo = {
        shift_id: null,
        shift_name: 'Default Shift',
        start_time: '09:00:00',
        end_time: '18:00:00',
        weekly_offs: 'Saturday,Sunday'
    };
    
    // Check current punch status when page loads
    checkPunchStatus();
    
    // Update greeting, date and time initially
    updateGreeting();
    updateDateTime();
    
    // Update date and time every second
    setInterval(() => {
        updateDateTime();
        updateShiftTime();
        updateOvertimeTimer();
    }, 1000);
    
    // Punch button click event
    punchButton.addEventListener('click', function() {
        // Check if already completed for today
        if (isCompletedForToday) {
            showNotification('Attendance already completed for today', 'warning');
            return;
        }
        
        // Update camera title
        cameraTitle.textContent = isPunchedIn ? 'Take Selfie for Punch Out' : 'Take Selfie for Punch In';
        
        // Show/hide work report section based on punch type
        if (isPunchedIn) {
            workReportSection.style.display = 'block';
        } else {
            workReportSection.style.display = 'none';
        }
        
        // Open camera container
        openCameraContainer();
        
        // Initialize camera
        initCamera();
        
        // Get user location
        getUserLocation();
    });
    
    // Close camera button event
    closeCameraBtn.addEventListener('click', closeCameraContainer);
    
    // Capture photo button event
    cameraCaptureBtn.addEventListener('click', capturePhoto);
    
    // Retake photo button event
    retakePhotoBtn.addEventListener('click', function() {
        // Hide captured image and show video
        capturedImageWrapper.style.display = 'none';
        videoWrapper.style.display = 'block';
        
        // Hide retake and confirm buttons
        retakePhotoBtn.style.display = 'none';
        confirmPunchBtn.style.display = 'none';
        
        // Reset captured photo data
        capturedPhotoData = null;
    });
    
    // Confirm punch button event
    confirmPunchBtn.addEventListener('click', function() {
        // For punch out, validate work report
        if (isPunchedIn && workReportSection.style.display === 'block') {
            if (!workReportText.value.trim()) {
                showNotification('Please enter your work report before punching out', 'warning');
                workReportText.focus();
                return;
            }
        }
        
        // Show loading state
        confirmPunchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        confirmPunchBtn.disabled = true;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('punch_type', isPunchedIn ? 'out' : 'in');
        
        // Add photo data if available
        if (capturedPhotoData) {
            formData.append('photo_data', capturedPhotoData);
        }
        
        // Add location data if available
        if (userLocation) {
            formData.append('latitude', userLocation.latitude);
            formData.append('longitude', userLocation.longitude);
            formData.append('accuracy', userLocation.accuracy);
            if (userLocation.address) {
                formData.append('address', userLocation.address);
            }
        }
        
        // Add work report data if punching out
        if (isPunchedIn && workReportText.value.trim()) {
            formData.append('work_report', workReportText.value.trim());
        }
        
        // Send data to server
        fetch('api/process_punch.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // Close camera container
                closeCameraContainer();
                
                // Update punch status from server to ensure consistency
                checkPunchStatus();
                
                // Show success notification
                let successMessage = isPunchedIn ? 
                    `Punched out successfully at ${data.time}` : 
                    `Punched in successfully at ${data.time}`;
                
                // Add working hours info for punch out
                if (isPunchedIn && data.working_hours) {
                    successMessage += `<br>Hours worked: ${data.working_hours}h`;
                    if (data.overtime_hours > 0) {
                        successMessage += ` (including ${data.overtime_hours}h overtime)`;
                    }
                }
                
                showNotification(successMessage, 'success');
                
                // Reset work report field
                workReportText.value = '';
            } else {
                // Show error
                showNotification(data.message || 'Error processing punch', 'error');
                
                // Reset button
                confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
                confirmPunchBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error notification
            showNotification('Connection error: ' + error.message, 'error');
            
            // Reset button
            confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
            confirmPunchBtn.disabled = false;
        });
    });
    
    /**
     * Opens the camera container and prepares for punch in/out
     */
    function openCameraContainer() {
        cameraContainer.classList.add('open');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
    
    /**
     * Closes the camera container and cleans up resources
     */
    function closeCameraContainer() {
        cameraContainer.classList.remove('open');
        document.body.style.overflow = 'auto'; // Restore scrolling
        
        // Stop camera stream
        stopCamera();
        
        // Reset camera UI
        videoWrapper.style.display = 'block';
        capturedImageWrapper.style.display = 'none';
        retakePhotoBtn.style.display = 'none';
        confirmPunchBtn.style.display = 'none';
        workReportSection.style.display = 'none';
        workReportText.value = '';
    }
    
    /**
     * Initializes the camera stream
     */
    function initCamera() {
        // Check if browser supports getUserMedia
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            locationStatus.textContent = 'Camera not supported in this browser';
            return;
        }
        
        // Stop any existing stream
        stopCamera();
        
        // Request camera access
        navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false 
        })
        .then(function(mediaStream) {
            stream = mediaStream;
            cameraVideo.srcObject = mediaStream;
            cameraVideo.play()
                .catch(error => {
                    console.error('Error playing video:', error);
                });
        })
        .catch(function(error) {
            console.error('Error accessing camera:', error);
            locationStatus.textContent = 'Camera access denied or not available';
        });
    }
    
    /**
     * Stops the camera stream
     */
    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }
    
    /**
     * Captures a photo from the camera
     */
    function capturePhoto() {
        if (!stream) return;
        
        // Set canvas size to match video
        cameraCanvas.width = cameraVideo.videoWidth;
        cameraCanvas.height = cameraVideo.videoHeight;
        
        // Draw video frame to canvas
        const context = cameraCanvas.getContext('2d');
        context.drawImage(cameraVideo, 0, 0, cameraCanvas.width, cameraCanvas.height);
        
        // Get image data
        capturedPhotoData = cameraCanvas.toDataURL('image/jpeg');
        
        // Display captured image
        capturedImage.src = capturedPhotoData;
        
        // Show image and hide video
        videoWrapper.style.display = 'none';
        capturedImageWrapper.style.display = 'block';
        
        // Show retake and confirm buttons
        retakePhotoBtn.style.display = 'block';
        confirmPunchBtn.style.display = 'block';
    }
    
    /**
     * Gets the user's current location
     */
    function getUserLocation() {
        // Check if geolocation is supported
        if (!navigator.geolocation) {
            locationStatus.textContent = 'Geolocation not supported in this browser';
            locationCoords.textContent = 'Latitude: -- | Longitude: --';
            locationAddress.textContent = 'Address: --';
            return;
        }
        
        // Update status
        locationStatus.textContent = 'Getting your location...';
        
        // Get current position
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                userLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                
                // Update location info
                locationStatus.textContent = `Location found (Accuracy: ${Math.round(position.coords.accuracy)}m)`;
                locationCoords.textContent = `Latitude: ${position.coords.latitude.toFixed(6)} | Longitude: ${position.coords.longitude.toFixed(6)}`;
                
                // Get address from coordinates
                getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
            },
            // Error callback
            function(error) {
                console.error('Error getting location:', error);
                
                // Update status based on error
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        locationStatus.textContent = 'Location access denied';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        locationStatus.textContent = 'Location information unavailable';
                        break;
                    case error.TIMEOUT:
                        locationStatus.textContent = 'Location request timed out';
                        break;
                    default:
                        locationStatus.textContent = 'Unknown error getting location';
                        break;
                }
                
                locationCoords.textContent = 'Latitude: -- | Longitude: --';
                locationAddress.textContent = 'Address: --';
            },
            // Options
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
    
    /**
     * Gets address from coordinates using reverse geocoding
     */
    function getAddressFromCoordinates(latitude, longitude) {
        // Update status
        locationAddress.textContent = 'Getting address...';
        
        // Use Nominatim API for reverse geocoding (free and requires no API key)
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18`;
        
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'HR Attendance App' // Required by Nominatim
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.display_name) {
                locationAddress.textContent = `Address: ${data.display_name}`;
                userLocation.address = data.display_name;
            } else {
                locationAddress.textContent = 'Address: Could not determine address';
            }
        })
        .catch(error => {
            console.error('Error fetching address:', error);
            locationAddress.textContent = 'Address: Error retrieving address';
        });
    }
    
    /**
     * Updates the greeting based on the time of day
     */
    function updateGreeting() {
        const hour = new Date().getHours();
        let greeting = '';
        let iconClass = '';
        let iconColor = '';
        
        if (hour >= 5 && hour < 12) {
            greeting = 'Good morning';
            iconClass = 'fa-sun';
            iconColor = '#f39c12'; // yellow/orange
        } else if (hour >= 12 && hour < 17) {
            greeting = 'Good afternoon';
            iconClass = 'fa-sun';
            iconColor = '#e67e22'; // orange
        } else if (hour >= 17 && hour < 22) {
            greeting = 'Good evening';
            iconClass = 'fa-moon';
            iconColor = '#9b59b6'; // purple
        } else {
            greeting = 'Good night';
            iconClass = 'fa-star-and-crescent';
            iconColor = '#34495e'; // dark blue
        }
        
        greetingTimeEl.textContent = greeting;
        
        // Update the icon
        greetingIconEl.className = ''; // Clear all classes
        greetingIconEl.classList.add('fas', iconClass);
        greetingIconEl.style.color = iconColor;
    }
    
    /**
     * Updates the date and time display
     */
    function updateDateTime() {
        const now = new Date();
        
        // Format date for small display: May 17, 2023
        const smallDateOptions = {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        };
        const smallFormattedDate = now.toLocaleDateString('en-US', smallDateOptions);
        
        // Format time for small display: 3:45 PM
        const smallTimeOptions = {
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        };
        const smallFormattedTime = now.toLocaleTimeString('en-US', smallTimeOptions);
        
        // Update the elements
        smallCurrentDateEl.textContent = smallFormattedDate;
        smallCurrentTimeEl.textContent = smallFormattedTime;
    }
    
    /**
     * Updates the shift remaining time display using shift information from server
     */
    function updateShiftTime() {
        if (!userShiftInfo || !userShiftInfo.end_time) {
            shiftRemainingTimeEl.textContent = 'Shift info not available';
            return;
        }
        
        const now = new Date();
        
        // Parse shift end time
        const [endHours, endMinutes, endSeconds] = userShiftInfo.end_time.split(':').map(Number);
        
        // Create shift end date for today
        const shiftEndDate = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
            endHours,
            endMinutes,
            endSeconds || 0
        );
        
        // If current time is after shift end time, the shift has ended for today
        if (now > shiftEndDate) {
            shiftRemainingTimeEl.textContent = 'Shift ended';
            return;
        }
        
        // Calculate time difference
        let timeDiff = shiftEndDate - now;
        
        // Convert time difference to hours, minutes, seconds
        const hours = Math.floor(timeDiff / (1000 * 60 * 60));
        timeDiff -= hours * (1000 * 60 * 60);
        
        const minutes = Math.floor(timeDiff / (1000 * 60));
        timeDiff -= minutes * (1000 * 60);
        
        const seconds = Math.floor(timeDiff / 1000);
        
        // Update the display
        shiftRemainingTimeEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
    }
    
    /**
     * Updates the overtime timer if user is currently working beyond shift end time
     */
    function updateOvertimeTimer() {
        if (!userShiftInfo || !userShiftInfo.end_time) {
            return;
        }
        
        const now = new Date();
        
        // Parse shift end time
        const [endHours, endMinutes, endSeconds] = userShiftInfo.end_time.split(':').map(Number);
        
        // Create shift end date for today
        const shiftEndDate = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
            endHours,
            endMinutes,
            endSeconds || 0
        );
        
        // Remove existing overtime timer if exists
        const existingOvertimeTimer = document.querySelector('.overtime-timer');
        if (existingOvertimeTimer) {
            existingOvertimeTimer.remove();
        }
        
        // Only show overtime timer if shift has ended and user is punched in
        if (now > shiftEndDate && isPunchedIn) {
            // Calculate overtime
            let overtimeDiff = now - shiftEndDate;
            
            // Convert time difference to hours, minutes, seconds
            const hours = Math.floor(overtimeDiff / (1000 * 60 * 60));
            overtimeDiff -= hours * (1000 * 60 * 60);
            
            const minutes = Math.floor(overtimeDiff / (1000 * 60));
            overtimeDiff -= minutes * (1000 * 60);
            
            const seconds = Math.floor(overtimeDiff / 1000);
            
            // Calculate total overtime in minutes
            const totalOvertimeMinutes = (hours * 60) + minutes;
            
            // Round to nearest 30 minutes for database storage
            const roundedOvertimeMinutes = Math.round(totalOvertimeMinutes / 30) * 30;
            const roundedHours = Math.floor(roundedOvertimeMinutes / 60);
            const roundedMinutes = roundedOvertimeMinutes % 60;
            
            // Find shift info container to add overtime timer
            const shiftTimeContainer = document.querySelector('.shift-time-remaining');
            
            // Check if shift info exists
            const shiftInfoElem = document.querySelector('.shift-info');
            
            if (shiftTimeContainer) {
                // Create overtime timer element
                const overtimeTimerElem = document.createElement('div');
                overtimeTimerElem.className = 'overtime-timer';
                overtimeTimerElem.innerHTML = `
                    <i class="fas fa-stopwatch"></i> Overtime: <span>${hours}h ${minutes}m ${seconds}s</span>
                    <div class="rounded-overtime">Rounded: <span>${roundedHours}h ${roundedMinutes}m</span></div>
                `;
                
                // Insert overtime timer after shift info or after time display if no shift info
                if (shiftInfoElem) {
                    shiftInfoElem.after(overtimeTimerElem);
                } else {
                    const timeDisplay = shiftTimeContainer.querySelector('.time-display');
                    if (timeDisplay) {
                        timeDisplay.after(overtimeTimerElem);
                    } else {
                        shiftTimeContainer.appendChild(overtimeTimerElem);
                    }
                }
            }
        }
    }
    
    /**
     * Shows a notification message
     */
    function showNotification(message, type) {
        // Check if the notification container exists
        let notificationContainer = document.querySelector('.notification-container');
        
        // Create notification container if it doesn't exist
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.className = 'notification-container';
            document.body.appendChild(notificationContainer);
            
            // Add styles for notification
            const style = document.createElement('style');
            style.textContent = `
                .notification-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                }
                .notification {
                    background: white;
                    border-radius: 4px;
                    padding: 12px 20px;
                    margin-bottom: 10px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    transform: translateX(100%);
                    opacity: 0;
                    transition: all 0.3s ease;
                    max-width: 300px;
                }
                .notification.show {
                    transform: translateX(0);
                    opacity: 1;
                }
                .notification i {
                    margin-right: 10px;
                    font-size: 1.2rem;
                }
                .notification.success i {
                    color: #2ecc71;
                }
                .notification.error i {
                    color: #e74c3c;
                }
                .notification.warning i {
                    color: #f39c12;
                }
                .notification-message {
                    flex-grow: 1;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Set icon based on notification type
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="notification-message">${message}</div>
        `;
        
        // Add to container
        notificationContainer.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Check the current punch status from the server
     */
    function checkPunchStatus() {
        // Set button to loading state
        punchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        punchButton.disabled = true;
        
        // Fetch current status from server
        fetch('api/check_punch_status.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                // Update UI based on current status
                isPunchedIn = data.is_punched_in;
                isCompletedForToday = data.is_completed || false;
                
                // Update shift information for timer calculation
                if (data.shift_info) {
                    userShiftInfo = data.shift_info;
                    updateShiftTime(); // Update timer immediately
                    updateOvertimeTimer(); // Also update overtime timer
                }
                
                // Get parent container for positioning
                const buttonContainer = punchButton.closest('.punch-button-container');
                
                // Remove any existing elements
                const existingPunchTime = buttonContainer.querySelector('.punch-time');
                if (existingPunchTime) {
                    existingPunchTime.remove();
                }
                
                // Find shift timer container
                const shiftTimeContainer = document.querySelector('.shift-time-remaining');
                
                // Remove any existing shift info from any location
                const existingShiftInfo = document.querySelector('.shift-info');
                if (existingShiftInfo) {
                    existingShiftInfo.remove();
                }
                
                // Remove any existing completed overtime info
                const existingCompletedOvertime = document.querySelector('.completed-overtime');
                if (existingCompletedOvertime) {
                    existingCompletedOvertime.remove();
                }
                
                if (isCompletedForToday) {
                    // User has completed attendance for today (punched in and out)
                    punchButton.classList.remove('btn-success', 'btn-danger');
                    punchButton.classList.add('btn-secondary');
                    punchButton.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                    punchButton.disabled = true;
                    
                    // Show working hours if available
                    if (data.working_hours) {
                        // Create hours worked element
                        const punchTimeElem = document.createElement('div');
                        punchTimeElem.className = 'punch-time';
                        
                        // Check if we have decimal hours or a formatted string
                        if (data.working_hours_decimal !== undefined) {
                            punchTimeElem.innerHTML = `Hours worked: ${data.working_hours}`;
                        } else {
                            punchTimeElem.innerHTML = `Hours worked: ${data.working_hours}h`;
                        }
                        
                        // Add it to the button container
                        buttonContainer.appendChild(punchTimeElem);
                        
                        // Show completed overtime if available
                        if (data.overtime_hours && 
                            ((data.overtime_hours_decimal !== undefined && data.overtime_hours_decimal > 0) || 
                             (typeof data.overtime_hours === 'string' && data.overtime_hours !== '0:00'))) {
                            // Display the completed overtime information below shift info
                            if (shiftTimeContainer) {
                                const completedOvertimeElem = document.createElement('div');
                                completedOvertimeElem.className = 'completed-overtime';
                                
                                let overtimeDisplay = '';
                                // Format based on whether we have the new decimal format or old format
                                if (data.overtime_hours_decimal !== undefined) {
                                    // New format - we have hours and minutes already formatted
                                    overtimeDisplay = data.overtime_hours;
                                } else {
                                    // Legacy format - parse the decimal
                                    const overtimeHours = parseFloat(data.overtime_hours);
                                    const overtimeHoursWhole = Math.floor(overtimeHours);
                                    const overtimeMinutes = Math.round((overtimeHours - overtimeHoursWhole) * 60);
                                    overtimeDisplay = `${overtimeHoursWhole}h ${overtimeMinutes}m`;
                                }
                                
                                completedOvertimeElem.innerHTML = `
                                    <i class="fas fa-stopwatch"></i> Overtime: <span>${overtimeDisplay}</span>
                                    <div class="overtime-note">(Rounded to nearest 30 minutes)</div>
                                `;
                                
                                // Replace shift remaining time with overtime info
                                shiftRemainingTimeEl.textContent = 'Shift completed';
                                
                                // Add overtime info below shift info
                                if (existingShiftInfo) {
                                    existingShiftInfo.after(completedOvertimeElem);
                                } else {
                                    const timeDisplay = shiftTimeContainer.querySelector('.time-display');
                                    if (timeDisplay) {
                                        timeDisplay.after(completedOvertimeElem);
                                    } else {
                                        shiftTimeContainer.appendChild(completedOvertimeElem);
                                    }
                                }
                            }
                        }
                    }
                } else if (isPunchedIn) {
                    // User is punched in but not yet punched out
                    punchButton.classList.remove('btn-success', 'btn-secondary');
                    punchButton.classList.add('btn-danger');
                    punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                    punchButton.disabled = false;
                    
                    // Show punch in time if available
                    if (data.last_punch_in) {
                        const punchTimeElem = document.createElement('div');
                        punchTimeElem.className = 'punch-time';
                        punchTimeElem.innerHTML = `Since: ${data.last_punch_in}`;
                        
                        // Add it to the button container
                        buttonContainer.appendChild(punchTimeElem);
                    }
                } else {
                    // User is not punched in
                    punchButton.classList.remove('btn-danger', 'btn-secondary');
                    punchButton.classList.add('btn-success');
                    punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                    punchButton.disabled = false;
                }
                
                // Add shift info below the shift timer
                if (data.shift_info && data.shift_info.shift_name && shiftTimeContainer) {
                    const shiftInfoElem = document.createElement('div');
                    shiftInfoElem.className = 'shift-info';
                    shiftInfoElem.innerHTML = `Shift: ${data.shift_info.shift_name} (${data.shift_info.start_time_formatted} - ${data.shift_info.end_time_formatted})`;
                    shiftTimeContainer.appendChild(shiftInfoElem);
                }
            })
            .catch(error => {
                console.error('Error checking punch status:', error);
                
                // Set default state on error
                punchButton.classList.remove('btn-danger', 'btn-secondary');
                punchButton.classList.add('btn-success');
                punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                punchButton.disabled = false;
                
                // Show error notification
                showNotification('Failed to check punch status', 'error');
            });
    }
    
    // Update greeting when tab becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateGreeting();
            updateDateTime();
            updateShiftTime();
            updateOvertimeTimer();
            checkPunchStatus(); // Also check punch status when tab becomes visible
        }
    });
}); 