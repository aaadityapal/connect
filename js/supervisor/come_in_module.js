/**
 * Come In Module - Handles camera functionality for the Come In button
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const comeInBtn = document.getElementById('comeInBtn');
    const comeInModal = document.getElementById('comeInModal');
    const closeComeInBtn = document.getElementById('closeComeInBtn');
    const comeInCameraVideo = document.getElementById('comeInCameraVideo');
    const comeInCameraCanvas = document.getElementById('comeInCameraCanvas');
    const comeInCameraCaptureBtn = document.getElementById('comeInCameraCaptureBtn');
    const rotateComeInCameraBtn = document.getElementById('rotateComeInCameraBtn');
    const comeInCapturedImage = document.getElementById('comeInCapturedImage');
    const comeInCapturedContainer = document.querySelector('.come-in-captured-image-container');
    const comeInCameraContainer = document.querySelector('.come-in-camera-container');
    const comeInRetakeBtn = document.getElementById('comeInRetakeBtn');
    const comeInSaveBtn = document.getElementById('comeInSaveBtn');
    const comeInLocationStatus = document.getElementById('comeInLocationStatus');
    const comeInLocationAddress = document.getElementById('comeInLocationAddress');
    const comeInGeofenceStatus = document.getElementById('comeInGeofenceStatus');
    const outsideLocationReasonContainer = document.getElementById('outsideLocationReasonContainer');
    const outsideLocationReason = document.getElementById('outsideLocationReason');

    // Variables
    let stream = null;
    let currentFacingMode = 'user';
    let photoData = null;
    let locationData = {};
    let geofenceLocations = [];
    let isWithinGeofence = false;
    let closestLocationName = "";
    let comeInAction = 'come_in'; // Default action is come_in

    // Event listeners
    if (comeInBtn) {
        comeInBtn.addEventListener('click', openComeInModal);
    }
    
    // Come Out button event listener
    const comeOutBtn = document.getElementById('comeOutBtn');
    if (comeOutBtn) {
        comeOutBtn.addEventListener('click', openComeOutModal);
    }
    
    if (closeComeInBtn) {
        closeComeInBtn.addEventListener('click', closeComeInModal);
    }
    
    if (comeInCameraCaptureBtn) {
        comeInCameraCaptureBtn.addEventListener('click', capturePhoto);
    }
    
    if (rotateComeInCameraBtn) {
        rotateComeInCameraBtn.addEventListener('click', rotateCamera);
    }
    
    if (comeInRetakeBtn) {
        comeInRetakeBtn.addEventListener('click', retakePhoto);
    }
    
    if (comeInSaveBtn) {
        comeInSaveBtn.addEventListener('click', savePhoto);
    }

    /**
     * Opens the Come In modal and initializes camera
     */
    function openComeInModal() {
        // First check if the button is disabled (already completed cycle)
        const comeInBtn = document.getElementById('comeInBtn');
        if (!comeInBtn || comeInBtn.disabled) {
            showNotification('Already Completed', 'You have already completed your attendance for today.', 'info');
            return;
        }
        
        if (comeInModal) {
            // Update modal title for punch in
            const modalTitle = document.getElementById('camera-title');
            if (modalTitle) {
                modalTitle.textContent = 'Punch In Camera';
            }
            
            // Reset header background color to green for punch in
            const modalHeader = document.querySelector('.come-in-modal-header');
            if (modalHeader) {
                modalHeader.style.backgroundColor = '#28a745'; // Green color for punch in
            }
            
            // Reset work report section for punch in
            const workReportSection = document.getElementById('outsideLocationReasonContainer');
            if (workReportSection) {
                const label = workReportSection.querySelector('label');
                if (label) {
                    label.textContent = 'Please provide a reason for being outside assigned location:';
                }
                
                const textarea = document.getElementById('outsideLocationReason');
                if (textarea) {
                    textarea.placeholder = 'Enter reason here...';
                    textarea.value = ''; // Clear any previous input
                    
                    // Create word count display for outside location reason if it doesn't exist
                    let wordCountDisplay = document.getElementById('comeInWordCount');
                    if (!wordCountDisplay) {
                        wordCountDisplay = document.createElement('div');
                        wordCountDisplay.id = 'comeInWordCount';
                        wordCountDisplay.className = 'word-count-display';
                        wordCountDisplay.textContent = 'Words: 0 (minimum 5)';
                        wordCountDisplay.style.fontSize = '12px';
                        wordCountDisplay.style.color = '#6c757d';
                        wordCountDisplay.style.textAlign = 'right';
                        wordCountDisplay.style.marginTop = '5px';
                        
                        // Add it after the textarea
                        if (textarea.parentNode) {
                            textarea.parentNode.insertBefore(wordCountDisplay, textarea.nextSibling);
                        }
                        
                        // Add input event listener for word count
                        textarea.addEventListener('input', function() {
                            updateWordCount(this, wordCountDisplay);
                        });
                    } else {
                        // Update existing word count
                        updateWordCount(textarea, wordCountDisplay);
                    }
                }
                
                // Hide work report initially (will show if outside geofence)
                workReportSection.style.display = 'none';
            }
            
            // Set action to come_in
            comeInAction = 'come_in';
            
            comeInModal.classList.add('active');
            startCamera(currentFacingMode);
            getLocation();
            fetchGeofenceLocations();
        }
    }
    
    /**
     * Opens the Come Out modal for punch out
     */
    function openComeOutModal() {
        // First check if the button is disabled or doesn't exist (already completed cycle)
        const comeOutBtn = document.getElementById('comeOutBtn');
        if (!comeOutBtn || comeOutBtn.disabled) {
            showNotification('Already Completed', 'You have already completed your attendance for today.', 'info');
            return;
        }
        
        if (comeInModal) {
            // Update modal titles for punch out
            const modalTitle = document.getElementById('camera-title');
            if (modalTitle) {
                modalTitle.textContent = 'Punch Out Camera';
            }
            
            // Change header background color to red for punch out
            const modalHeader = document.querySelector('.come-in-modal-header');
            if (modalHeader) {
                modalHeader.style.backgroundColor = '#dc3545'; // Red color for punch out
            }
            
            // Create or show the outside geofence reason container if it doesn't exist
            let outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
            if (!outsideGeofenceContainer) {
                // Create the container if it doesn't exist
                outsideGeofenceContainer = document.createElement('div');
                outsideGeofenceContainer.id = 'outsideGeofenceReasonContainer';
                outsideGeofenceContainer.className = 'outside-location-reason';
                outsideGeofenceContainer.style.marginTop = '15px';
                outsideGeofenceContainer.style.display = 'block';
                
                // Create the label
                const label = document.createElement('label');
                label.setAttribute('for', 'outsideGeofenceReason');
                label.textContent = 'Please explain why you are outside the assigned location:';
                
                // Create the textarea
                const textarea = document.createElement('textarea');
                textarea.id = 'outsideGeofenceReason';
                textarea.className = 'form-control';
                textarea.rows = 3;
                textarea.placeholder = 'Enter reason for being outside the location...';
                textarea.style.width = '100%';
                textarea.style.minHeight = '80px';
                textarea.style.marginTop = '5px';
                textarea.style.padding = '8px';
                
                // Create word count display
                const wordCountDisplay = document.createElement('div');
                wordCountDisplay.id = 'outsideGeofenceWordCount';
                wordCountDisplay.className = 'word-count-display';
                wordCountDisplay.textContent = 'Words: 0 (minimum 5)';
                wordCountDisplay.style.fontSize = '12px';
                wordCountDisplay.style.color = '#6c757d';
                wordCountDisplay.style.textAlign = 'right';
                wordCountDisplay.style.marginTop = '5px';
                
                // Append elements to the container
                outsideGeofenceContainer.appendChild(label);
                outsideGeofenceContainer.appendChild(textarea);
                outsideGeofenceContainer.appendChild(wordCountDisplay);
                
                // Find the location in the DOM to insert this container
                const locationInfo = document.querySelector('.come-in-location-info');
                if (locationInfo) {
                    locationInfo.appendChild(outsideGeofenceContainer);
                }
                
                // Add input event listener for word count
                textarea.addEventListener('input', function() {
                    updateWordCount(this, wordCountDisplay);
                });
            } else {
                // If it exists, just make it visible
                outsideGeofenceContainer.style.display = 'block';
                
                // Clear any previous input
                const textarea = document.getElementById('outsideGeofenceReason');
                if (textarea) {
                    textarea.value = '';
                    // Update word count
                    const wordCountDisplay = document.getElementById('outsideGeofenceWordCount');
                    if (wordCountDisplay) {
                        updateWordCount(textarea, wordCountDisplay);
                    }
                }
            }
            
            // Show work report section for punch out (AFTER the outside geofence reason)
            const workReportSection = document.getElementById('outsideLocationReasonContainer');
            if (workReportSection) {
                const label = workReportSection.querySelector('label');
                if (label) {
                    label.textContent = 'Please provide a summary of your work today:';
                }
                
                const textarea = document.getElementById('outsideLocationReason');
                if (textarea) {
                    textarea.placeholder = 'Enter details about your work today...';
                    textarea.value = ''; // Clear any previous input
                    
                    // Create word count display for work report if it doesn't exist
                    let workReportWordCount = document.getElementById('workReportWordCount');
                    if (!workReportWordCount) {
                        workReportWordCount = document.createElement('div');
                        workReportWordCount.id = 'workReportWordCount';
                        workReportWordCount.className = 'word-count-display';
                        workReportWordCount.textContent = 'Words: 0 (minimum 20)';
                        workReportWordCount.style.fontSize = '12px';
                        workReportWordCount.style.color = '#6c757d';
                        workReportWordCount.style.textAlign = 'right';
                        workReportWordCount.style.marginTop = '5px';
                        
                        // Add it after the textarea
                        if (textarea.parentNode) {
                            textarea.parentNode.insertBefore(workReportWordCount, textarea.nextSibling);
                        }
                        
                        // Add input event listener for word count
                        textarea.addEventListener('input', function() {
                            updateWorkReportWordCount(this, workReportWordCount);
                        });
                    } else {
                        // Update existing word count
                        updateWorkReportWordCount(textarea, workReportWordCount);
                    }
                }
                
                // Always show work report for punch out
                workReportSection.style.display = 'block';
                
                // Move the work report section after the outside geofence reason
                const locationInfo = document.querySelector('.come-in-location-info');
                if (locationInfo && workReportSection.parentNode === locationInfo) {
                    locationInfo.appendChild(workReportSection);
                }
            }
            
            // Set action to come_out
            comeInAction = 'come_out';
            
            // Open the modal
            comeInModal.classList.add('active');
            startCamera(currentFacingMode);
            getLocation();
            fetchGeofenceLocations();
        }
    }
    
    /**
     * Updates the word count display for a textarea with 5-word minimum
     * Now with special character filtering
     */
    function updateWordCount(textarea, displayElement) {
        if (!textarea || !displayElement) return;
        
        const text = textarea.value.trim();
        // Filter out special characters and keep only valid words
        const wordCount = text ? text.split(/\s+/)
            .filter(word => word.length > 0)
            .filter(word => /^[a-zA-Z0-9\u0900-\u097F]+$/.test(word)) // Allow alphanumeric and Hindi characters
            .length : 0;
        
        // Update the display
        displayElement.textContent = `Words: ${wordCount} (minimum 5)`;
        
        // Change color based on word count
        if (wordCount < 5) {
            displayElement.style.color = '#dc3545'; // Red for less than minimum
        } else {
            displayElement.style.color = '#28a745'; // Green for meeting minimum
        }
    }
    
    /**
     * Updates the word count display for work report with 20-word minimum
     * Now with special character filtering
     */
    function updateWorkReportWordCount(textarea, displayElement) {
        if (!textarea || !displayElement) return;
        
        const text = textarea.value.trim();
        // Filter out special characters and keep only valid words
        const wordCount = text ? text.split(/\s+/)
            .filter(word => word.length > 0)
            .filter(word => /^[a-zA-Z0-9\u0900-\u097F]+$/.test(word)) // Allow alphanumeric and Hindi characters
            .length : 0;
        
        // Update the display
        displayElement.textContent = `Words: ${wordCount} (minimum 20)`;
        
        // Change color based on word count
        if (wordCount < 20) {
            displayElement.style.color = '#dc3545'; // Red for less than minimum
        } else {
            displayElement.style.color = '#28a745'; // Green for meeting minimum
        }
    }
    
    /**
     * Fetches geofence locations from the server
     */
    function fetchGeofenceLocations() {
        comeInGeofenceStatus.textContent = 'Loading geofence data...';
        
        // Fetch geofence locations from the server
        fetch('api/get_geofence_locations.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    geofenceLocations = data.locations;
                    console.log('Geofence locations loaded:', geofenceLocations);
                    
                    // If we already have location data, check geofence
                    if (locationData.latitude && locationData.longitude) {
                        checkGeofence(locationData.latitude, locationData.longitude);
                    }
                } else {
                    throw new Error(data.message || 'Failed to load geofence locations');
                }
            })
            .catch(error => {
                console.error('Error loading geofence locations:', error);
                comeInGeofenceStatus.textContent = 'Could not load location boundaries';
                comeInGeofenceStatus.style.color = '#dc3545'; // Red color for error
            });
    }

    /**
     * Closes the Come In modal and stops the camera
     */
    function closeComeInModal() {
        if (comeInModal) {
            comeInModal.classList.remove('active');
            stopCamera();
            resetUI();
            
            // Reset header background color to default green
            const modalHeader = document.querySelector('.come-in-modal-header');
            if (modalHeader) {
                modalHeader.style.backgroundColor = '#28a745';
            }
        }
    }

    /**
     * Starts the camera with specified facing mode
     */
    function startCamera(facingMode) {
        // Stop any existing stream
        stopCamera();

        // Set up camera constraints
        const constraints = {
            video: {
                facingMode: facingMode,
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };

        // Start the camera
        navigator.mediaDevices.getUserMedia(constraints)
            .then(function(mediaStream) {
                stream = mediaStream;
                comeInCameraVideo.srcObject = mediaStream;
                comeInCameraVideo.play()
                    .catch(error => {
                        console.error('Error playing video:', error);
                    });
                currentFacingMode = facingMode;
            })
            .catch(function(error) {
                console.error('Error accessing camera:', error);
                comeInLocationStatus.textContent = 'Camera error: ' + error.message;
            });
    }

    /**
     * Stops the camera stream
     */
    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop();
            });
            stream = null;
        }
    }

    /**
     * Rotates the camera between front and back
     */
    function rotateCamera() {
        const newFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        startCamera(newFacingMode);
    }

    /**
     * Captures a photo from the video stream
     */
    function capturePhoto() {
        if (!comeInCameraVideo.videoWidth) {
            console.error('Video not ready');
            return;
        }

        // Set canvas dimensions to match video
        comeInCameraCanvas.width = comeInCameraVideo.videoWidth;
        comeInCameraCanvas.height = comeInCameraVideo.videoHeight;
        
        // Draw the video frame to the canvas
        const context = comeInCameraCanvas.getContext('2d');
        context.drawImage(comeInCameraVideo, 0, 0, comeInCameraCanvas.width, comeInCameraCanvas.height);
        
        // Convert to data URL
        photoData = comeInCameraCanvas.toDataURL('image/jpeg', 0.8);
        
        // Display the captured image
        comeInCapturedImage.src = photoData;
        comeInCapturedContainer.style.display = 'block';
        comeInCameraContainer.style.display = 'none';
        
        // Show action buttons
        comeInRetakeBtn.style.display = 'block';
        comeInSaveBtn.style.display = 'block';
    }

    /**
     * Allows retaking the photo
     */
    function retakePhoto() {
        // Hide the captured image and show the camera again
        comeInCapturedContainer.style.display = 'none';
        comeInCameraContainer.style.display = 'block';
        
        // Hide action buttons
        comeInRetakeBtn.style.display = 'none';
        comeInSaveBtn.style.display = 'none';
        
        // Clear photo data
        photoData = null;
    }

    /**
     * Saves the captured photo and location data
     */
    function savePhoto() {
        if (!photoData) {
            showNotification('Photo Required', 'Please capture a photo first', 'warning');
            return;
        }
        
        // Get the work report text
        const workReportInput = outsideLocationReason ? outsideLocationReason.value.trim() : '';
        
        // Get the outside geofence reason (for Come Out when outside geofence)
        const outsideGeofenceTextarea = document.getElementById('outsideGeofenceReason');
        const outsideGeofenceReason = outsideGeofenceTextarea ? outsideGeofenceTextarea.value.trim() : '';
        
        // For Come In: Validate reason if outside geofence
        if (comeInAction === 'come_in' && !isWithinGeofence && outsideLocationReasonContainer.style.display !== 'none') {
            if (!workReportInput) {
                showNotification('Input Required', 'Please provide a reason for being outside the assigned location', 'warning');
                if (outsideLocationReason) outsideLocationReason.focus();
                return;
            }
            
            // Check if reason has at least 5 valid words (excluding special characters)
            const wordCount = workReportInput.split(/\s+/)
                .filter(word => word.length > 0)
                .filter(word => /^[a-zA-Z0-9\u0900-\u097F]+$/.test(word)) // Allow alphanumeric and Hindi characters
                .length;
                
            if (wordCount < 5) {
                showNotification('More Details Needed', 'Please provide a more detailed reason (minimum 5 words). Special characters are not counted as words.', 'warning');
                if (outsideLocationReason) outsideLocationReason.focus();
                return;
            }
        }
        
        // For Come Out: Always validate work report
        if (comeInAction === 'come_out') {
            if (!workReportInput) {
                showNotification('Input Required', 'Please provide a summary of your work today', 'warning');
                if (outsideLocationReason) outsideLocationReason.focus();
                return;
            }
            
            // Check if work report has at least 20 valid words (excluding special characters)
            const wordCount = workReportInput.split(/\s+/)
                .filter(word => word.length > 0)
                .filter(word => /^[a-zA-Z0-9\u0900-\u097F]+$/.test(word)) // Allow alphanumeric and Hindi characters
                .length;
                
            if (wordCount < 20) {
                showNotification('More Details Needed', 'Please provide a more detailed work summary (minimum 20 words). Special characters are not counted as words.', 'warning');
                if (outsideLocationReason) outsideLocationReason.focus();
                return;
            }
            
            // For Come Out: Validate outside geofence reason if not within geofence
            if (!isWithinGeofence && outsideGeofenceTextarea) {
                if (!outsideGeofenceReason) {
                    showNotification('Input Required', 'Please explain why you are outside the assigned location', 'warning');
                    outsideGeofenceTextarea.focus();
                    return;
                }
                
                // Check if outside geofence reason has at least 5 valid words (excluding special characters)
                const outsideReasonWordCount = outsideGeofenceReason.split(/\s+/)
                    .filter(word => word.length > 0)
                    .filter(word => /^[a-zA-Z0-9\u0900-\u097F]+$/.test(word)) // Allow alphanumeric and Hindi characters
                    .length;
                    
                if (outsideReasonWordCount < 5) {
                    showNotification('More Details Needed', 'Please provide a more detailed explanation for being outside the location (minimum 5 words). Special characters are not counted as words.', 'warning');
                    outsideGeofenceTextarea.focus();
                    return;
                }
            }
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', comeInAction);
        formData.append('photo', photoData);
        formData.append('latitude', locationData.latitude || '');
        formData.append('longitude', locationData.longitude || '');
        formData.append('accuracy', locationData.accuracy || '');
        formData.append('address', locationData.address || 'Not available');
        
        // Send both parameter names for compatibility
        formData.append('is_within_geofence', isWithinGeofence ? '1' : '0');
        formData.append('within_geofence', isWithinGeofence ? '1' : '0');
        
        formData.append('closest_location', closestLocationName);
        
        // Add geofence ID if available
        if (locationData.geofenceId) {
            formData.append('geofence_id', locationData.geofenceId);
        }
        
        // Add distance from geofence if available
        if (typeof locationData.distanceFromGeofence !== 'undefined') {
            formData.append('distance_from_geofence', locationData.distanceFromGeofence);
        }
        
        // For Come In: Add reason if outside geofence
        if (comeInAction === 'come_in' && !isWithinGeofence) {
            formData.append('outside_location_reason', workReportInput);
        }
        
        // For Come Out: Add work report and outside geofence reason if applicable
        if (comeInAction === 'come_out') {
            // Always add work report
            formData.append('work_report', workReportInput);
            
            // Add outside location reason if not within geofence
            if (!isWithinGeofence) {
                formData.append('outside_location_reason', outsideGeofenceReason);
            }
        }
        
        // Add Indian Standard Time (IST) for timezone handling
        const now = new Date();
        // Convert to IST (UTC+5:30)
        const istTime = new Date(now.getTime() + (5.5 * 60 * 60 * 1000));
        const istTimeString = istTime.toISOString();
        
        formData.append('client_time', istTimeString);
        formData.append('timezone', 'Asia/Kolkata');
        formData.append('timezone_offset', '+05:30');
        
        // Show loading state
        comeInSaveBtn.disabled = true;
        comeInSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        // Send data to the server
        fetch('ajax_handlers/submit_attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            return response.text(); // Get as text first to debug any issues
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Error parsing response:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                // Show appropriate message based on geofence status and action
                if (!isWithinGeofence) {
                    // For attendance outside geofence - show pending approval message
                    if (comeInAction === 'come_in') {
                        showNotification('Pending Approval', 'Your punch in has been recorded but requires manager approval as you are outside the assigned location.', 'warning', () => {
                            // Refresh the page to update the button state
                            window.location.reload();
                        });
                    } else {
                        showNotification('Pending Approval', 'Your punch out has been recorded but requires manager approval as you are outside the assigned location.', 'warning', () => {
                            // Refresh the page to update the button state
                            window.location.reload();
                        });
                    }
                } else {
                    // For attendance within geofence - show regular success message
                    if (comeInAction === 'come_in') {
                        showNotification('Success', 'Punch In Recorded successfully!', 'success', () => {
                            // Refresh the page to update the button state
                            window.location.reload();
                        });
                    } else {
                        showNotification('Success', 'Punch Out recorded successfully! Your attendance for today is now complete.', 'success', () => {
                            // Refresh the page to update the button state
                            window.location.reload();
                        });
                    }
                }
                
                // Close the modal
                closeComeInModal();
            } else {
                // Show error message with details
                console.error('Server error:', data);
                showNotification('Error', data.message || 'Unknown error occurred', 'error');
                comeInSaveBtn.disabled = false;
                comeInSaveBtn.innerHTML = 'Confirm';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Connection Error', 'Failed to record attendance: ' + error.message, 'error');
            comeInSaveBtn.disabled = false;
            comeInSaveBtn.innerHTML = 'Confirm';
        });
    }

    /**
     * Gets the user's location
     */
    function getLocation() {
        if (navigator.geolocation) {
            comeInLocationStatus.textContent = 'Getting your location...';
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    locationData = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    
                    comeInLocationStatus.textContent = `Location found (Accuracy: ${Math.round(position.coords.accuracy)}m)`;
                    
                    // Get address from coordinates
                    getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
                    
                    // Check if within geofence
                    if (geofenceLocations.length > 0) {
                        checkGeofence(position.coords.latitude, position.coords.longitude);
                    }
                },
                function(error) {
                    comeInLocationStatus.textContent = 'Unable to get location: ' + error.message;
                }
            );
        } else {
            comeInLocationStatus.textContent = 'Geolocation is not supported by this browser';
        }
    }

    /**
     * Gets an address from coordinates using reverse geocoding
     */
    function getAddressFromCoordinates(latitude, longitude) {
        comeInLocationAddress.textContent = 'Fetching address...';
        
        // Use Nominatim API for reverse geocoding
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
        
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'HR Attendance System'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.display_name) {
                locationData.address = data.display_name;
                
                // Display a shorter version of the address
                let displayAddress = data.display_name;
                if (displayAddress.length > 60) {
                    displayAddress = displayAddress.substring(0, 57) + '...';
                }
                
                comeInLocationAddress.textContent = displayAddress;
            } else {
                throw new Error('No address found');
            }
        })
        .catch(error => {
            console.error('Error getting address:', error);
            comeInLocationAddress.textContent = 'Address could not be determined';
        });
    }

    /**
     * Resets the UI to its initial state
     */
    function resetUI() {
        comeInCapturedContainer.style.display = 'none';
        comeInCameraContainer.style.display = 'block';
        comeInRetakeBtn.style.display = 'none';
        comeInSaveBtn.style.display = 'none';
        photoData = null;
        
        // Reset work report textarea
        if (outsideLocationReason) {
            outsideLocationReason.value = '';
        }
        
        // Reset outside geofence reason textarea if it exists
        const outsideGeofenceTextarea = document.getElementById('outsideGeofenceReason');
        if (outsideGeofenceTextarea) {
            outsideGeofenceTextarea.value = '';
        }
        
        // Hide the outside geofence reason container if it exists
        const outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
        if (outsideGeofenceContainer) {
            outsideGeofenceContainer.style.display = 'none';
        }
    }

    /**
     * Checks if the user's location is within any geofence
     */
    function checkGeofence(latitude, longitude) {
        comeInGeofenceStatus.textContent = 'Checking location boundaries...';
        
        // If no geofence locations available yet, try to fetch them
        if (geofenceLocations.length === 0) {
            fetchGeofenceLocations();
            return;
        }
        
        let minDistance = Infinity;
        let closestLocation = null;
        
        // Check distance to each geofence location
        geofenceLocations.forEach(location => {
            const distance = calculateDistance(
                latitude, 
                longitude, 
                parseFloat(location.latitude), 
                parseFloat(location.longitude)
            );
            
            if (distance < minDistance) {
                minDistance = distance;
                closestLocation = location;
            }
        });
        
        // If we found a closest location
        if (closestLocation) {
            closestLocationName = closestLocation.name;
            const locationRadius = parseInt(closestLocation.radius);
            
            // Store geofence ID if available
            if (closestLocation.id) {
                locationData.geofenceId = closestLocation.id;
            }
            
            // Check if within radius
            if (minDistance <= locationRadius) {
                isWithinGeofence = true;
                comeInGeofenceStatus.textContent = `Within ${closestLocation.name} (${Math.round(minDistance)}m from center)`;
                comeInGeofenceStatus.style.color = '#28a745'; // Green color for success
                
                // Only hide the reason container for Come In
                if (comeInAction === 'come_in') {
                    outsideLocationReasonContainer.style.display = 'none';
                }
                
                // Hide the outside geofence reason container if it exists
                const outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
                if (outsideGeofenceContainer) {
                    outsideGeofenceContainer.style.display = 'none';
                }
                
                locationData.distanceFromGeofence = 0; // Inside geofence, so distance is 0
            } else {
                isWithinGeofence = false;
                comeInGeofenceStatus.textContent = `Outside ${closestLocation.name} (${Math.round(minDistance)}m from center)`;
                comeInGeofenceStatus.style.color = '#dc3545'; // Red color for error
                
                // Show the appropriate container based on action
                if (comeInAction === 'come_in') {
                    outsideLocationReasonContainer.style.display = 'block';
                    
                    // Hide the outside geofence container if it exists
                    const outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
                    if (outsideGeofenceContainer) {
                        outsideGeofenceContainer.style.display = 'none';
                    }
                } else if (comeInAction === 'come_out') {
                    // For Come Out, show the outside geofence reason container
                    const outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
                    if (outsideGeofenceContainer) {
                        outsideGeofenceContainer.style.display = 'block';
                    }
                }
                
                locationData.distanceFromGeofence = Math.round(minDistance - locationRadius); // Distance beyond the geofence boundary
            }
        } else {
            isWithinGeofence = false;
            comeInGeofenceStatus.textContent = 'No registered locations found';
            comeInGeofenceStatus.style.color = '#ffc107'; // Yellow/warning color
            
            if (comeInAction === 'come_in') {
                outsideLocationReasonContainer.style.display = 'block';
            } else if (comeInAction === 'come_out') {
                // For Come Out, show the outside geofence reason container
                const outsideGeofenceContainer = document.getElementById('outsideGeofenceReasonContainer');
                if (outsideGeofenceContainer) {
                    outsideGeofenceContainer.style.display = 'block';
                }
            }
        }
        
        // For come_out action, always ensure the work report is visible
        if (comeInAction === 'come_out') {
            outsideLocationReasonContainer.style.display = 'block';
        }
    }
    
    /**
     * Calculates the distance between two coordinates in meters using the Haversine formula
     */
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth radius in meters
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;
        
        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        
        return R * c; // Distance in meters
    }

    /**
     * Shows a professional notification modal instead of using alert()
     * @param {string} title - The title of the notification
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, error, warning, info)
     * @param {function} callback - Optional callback to execute after closing
     */
    function showNotification(title, message, type = 'success', callback = null) {
        // Remove any existing notification modal
        const existingModal = document.getElementById('customNotificationModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal container
        const modalContainer = document.createElement('div');
        modalContainer.id = 'customNotificationModal';
        modalContainer.style.position = 'fixed';
        modalContainer.style.top = '0';
        modalContainer.style.left = '0';
        modalContainer.style.width = '100%';
        modalContainer.style.height = '100%';
        modalContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        modalContainer.style.display = 'flex';
        modalContainer.style.alignItems = 'center';
        modalContainer.style.justifyContent = 'center';
        modalContainer.style.zIndex = '10000';
        modalContainer.style.opacity = '0';
        modalContainer.style.transition = 'opacity 0.3s ease';
        
        // Get appropriate icon and color based on type
        let icon, bgColor, borderColor;
        switch (type) {
            case 'success':
                icon = '<i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745;"></i>';
                bgColor = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                borderColor = '#28a745';
                break;
            case 'error':
                icon = '<i class="fas fa-times-circle" style="font-size: 3rem; color: #dc3545;"></i>';
                bgColor = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                borderColor = '#dc3545';
                break;
            case 'warning':
                icon = '<i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ffc107;"></i>';
                bgColor = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                borderColor = '#ffc107';
                break;
            case 'info':
            default:
                icon = '<i class="fas fa-info-circle" style="font-size: 3rem; color: #17a2b8;"></i>';
                bgColor = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                borderColor = '#17a2b8';
                break;
        }
        
        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.style.backgroundColor = 'white';
        modalContent.style.borderRadius = '8px';
        modalContent.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
        modalContent.style.width = '90%';
        modalContent.style.maxWidth = '450px';
        modalContent.style.padding = '0';
        modalContent.style.overflow = 'hidden';
        modalContent.style.transform = 'translateY(20px)';
        modalContent.style.transition = 'transform 0.3s ease';
        modalContent.style.border = `1px solid ${borderColor}`;
        
        // Create modal header
        const modalHeader = document.createElement('div');
        modalHeader.style.padding = '20px';
        modalHeader.style.background = bgColor;
        modalHeader.style.borderBottom = `3px solid ${borderColor}`;
        modalHeader.style.textAlign = 'center';
        
        // Create modal body
        const modalBody = document.createElement('div');
        modalBody.style.padding = '30px 20px';
        modalBody.style.textAlign = 'center';
        
        // Create modal footer
        const modalFooter = document.createElement('div');
        modalFooter.style.padding = '15px';
        modalFooter.style.textAlign = 'center';
        modalFooter.style.borderTop = '1px solid #dee2e6';
        
        // Create OK button
        const okButton = document.createElement('button');
        okButton.textContent = 'OK';
        okButton.style.backgroundColor = borderColor;
        okButton.style.color = 'white';
        okButton.style.border = 'none';
        okButton.style.borderRadius = '4px';
        okButton.style.padding = '10px 30px';
        okButton.style.fontSize = '1rem';
        okButton.style.cursor = 'pointer';
        okButton.style.transition = 'background-color 0.2s ease';
        okButton.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.1)';
        
        // Hover effect
        okButton.onmouseover = function() {
            this.style.backgroundColor = adjustColor(borderColor, -20); // Darken color
        };
        okButton.onmouseout = function() {
            this.style.backgroundColor = borderColor;
        };
        
        // Click event
        okButton.onclick = function() {
            // Fade out animation
            modalContainer.style.opacity = '0';
            modalContent.style.transform = 'translateY(20px)';
            
            // Remove after animation completes
            setTimeout(() => {
                modalContainer.remove();
                if (callback && typeof callback === 'function') {
                    callback();
                }
            }, 300);
        };
        
        // Set content
        modalHeader.innerHTML = icon;
        modalBody.innerHTML = `
            <h3 style="margin-top: 0; color: #333; font-weight: 600;">${title}</h3>
            <p style="color: #555; font-size: 1.1rem; margin: 10px 0 0;">${message}</p>
        `;
        modalFooter.appendChild(okButton);
        
        // Assemble modal
        modalContent.appendChild(modalHeader);
        modalContent.appendChild(modalBody);
        modalContent.appendChild(modalFooter);
        modalContainer.appendChild(modalContent);
        
        // Add to document
        document.body.appendChild(modalContainer);
        
        // Trigger animation
        setTimeout(() => {
            modalContainer.style.opacity = '1';
            modalContent.style.transform = 'translateY(0)';
        }, 10);
        
        // Auto close after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                if (document.body.contains(modalContainer)) {
                    okButton.click();
                }
            }, 5000);
        }
    }
    
    /**
     * Helper function to adjust color brightness
     * @param {string} color - Hex color
     * @param {number} amount - Amount to adjust (-100 to 100)
     * @returns {string} - Adjusted color
     */
    function adjustColor(color, amount) {
        // Handle non-hex colors
        if (!color.startsWith('#')) {
            // For named colors or rgb/rgba
            return color;
        }
        
        // Remove # if present
        color = color.replace('#', '');
        
        // Convert to RGB
        let r = parseInt(color.substring(0, 2), 16);
        let g = parseInt(color.substring(2, 4), 16);
        let b = parseInt(color.substring(4, 6), 16);
        
        // Adjust
        r = Math.max(0, Math.min(255, r + amount));
        g = Math.max(0, Math.min(255, g + amount));
        b = Math.max(0, Math.min(255, b + amount));
        
        // Convert back to hex
        return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }
}); 