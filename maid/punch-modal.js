// Punch-in Modal with Camera Functionality

class PunchInModal {
    constructor() {
        this.modal = null;
        this.video = null;
        this.canvas = null;
        this.stream = null;
        this.isFrontCamera = true;
        this.capturedImage = null;
        this.isPunchedIn = false;
        this.currentPunchData = null;
        this.geofences = [];
        this.currentLocation = null;
        this.isWithinGeofence = false;

        // Filter state
        this.filters = {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            hue: 0,
            blur: 0
        };

        this.init();
    }

    init() {
        this.createModal();
        this.setupEventListeners();
        // Fetch real status from server instead of trusting local storage blindly
        this.fetchServerStatus();
    }

    /**
     * Update button state externally (called from initialization)
     */
    updateButtonState() {
        // Trigger the greeting manager to update button state
        const updateEvent = new CustomEvent('updatePunchButtonState');
        document.dispatchEvent(updateEvent);
    }

    /**
     * Check if user already punched in today
     */
    /**
     * Fetch punch status from server to truth-check local storage
     */
    async fetchServerStatus() {
        try {
            const response = await fetch('api_check_status.php');
            const data = await response.json();

            if (data.success) {
                // Determine actual state
                const isPunchedInServer = data.has_record && !data.punch_out_time;

                // Update internal state
                this.isPunchedIn = isPunchedInServer;

                // Construct punch data object compatible with local storage structure
                if (data.has_record) {
                    const punchData = {
                        date: data.date, // Server returned valid format
                        time: data.punch_in_time,
                        attendance_id: data.attendance_id,
                        status: 'success',
                        punchOutTime: data.punch_out_time || null
                    };

                    this.currentPunchData = punchData;
                    localStorage.setItem('lastPunchIn', JSON.stringify(punchData));
                } else {
                    // No record for today
                    this.currentPunchData = null;
                    localStorage.removeItem('lastPunchIn');
                }

                // Dispatch event to update external UI (buttons in index.php)
                const eventName = isPunchedInServer ? 'punchInSuccess' : 'punchOutSuccess';
                const event = new CustomEvent(eventName, {
                    detail: this.currentPunchData || {}
                });
                document.dispatchEvent(event);

                console.log('Synced with server status:', isPunchedInServer ? 'Punched In' : 'Punched Out');
            }
        } catch (error) {
            console.error('Error fetching server status:', error);
            // Fallback to local storage if server check fails
            this.checkPunchStatus();
        }
    }

    /**
     * Check if user already punched in today (Local Storage Fallback)
     */
    checkPunchStatus() {
        const lastPunchIn = localStorage.getItem('lastPunchIn');
        if (lastPunchIn) {
            try {
                const punchData = JSON.parse(lastPunchIn);
                const today = new Date().toLocaleDateString('en-IN');

                if (punchData.date === today && !punchData.punchOutTime) {
                    this.isPunchedIn = true;
                    this.currentPunchData = punchData;
                    // Dispatch event for UI
                    document.dispatchEvent(new CustomEvent('punchInSuccess', { detail: punchData }));
                } else {
                    document.dispatchEvent(new CustomEvent('punchOutSuccess', { detail: {} }));
                }
            } catch (e) {
                console.error('Error parsing punch data:', e);
            }
        }
    }

    /**
     * Create the punch-in modal HTML
     */
    createModal() {
        const modalHTML = `
            <div class="modal-overlay" id="punchInModal">
                <div class="modal punch-modal">
                    <div class="modal-header">
                        <h3 class="modal-title" id="punchModalTitle">Punch In - Capture Photo</h3>
                        <button class="close-modal" id="closePunchModalBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Punch Status Display -->
                        <div class="punch-status-section" id="punchStatusSection" style="display: none;">
                            <div class="punch-status-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; text-align: center;">
                                <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Punch In Time</div>
                                <div style="font-size: 1.3rem; color: var(--status-qualified); font-weight: 600;" id="punchInTimeDisplay"></div>
                            </div>
                        </div>

                        <!-- Shifts Information Section -->
                        <div class="shifts-section" id="shiftsSection" style="display: none;">
                            <div class="shifts-header">
                                <h4 class="shifts-title">Your Shift</h4>
                            </div>
                            <div class="shifts-container" id="shiftsContainer">
                                <div class="shift-loading">Loading shift information...</div>
                            </div>
                        </div>

                        <div class="camera-container" id="cameraContainer">
                            <video id="punchCamera" autoplay playsinline style="filter: var(--camera-filter);"></video>
                            <canvas id="captureCanvas" style="display: none;"></canvas>
                            
                            <!-- Filter Controls -->
                            <div class="filter-panel" id="filterPanel">
                                <div class="filter-toggle-btn" id="filterToggleBtn" title="Toggle Filters">
                                    <i data-feather="sliders"></i>
                                </div>
                                
                                <div class="filter-controls" id="filterControls" style="display: none;">
                                    <div class="filter-group">
                                        <label for="brightnessFilter">
                                            <i data-feather="sun" style="width: 14px; height: 14px;"></i>
                                            Brightness
                                        </label>
                                        <input type="range" id="brightnessFilter" class="filter-slider" min="0" max="200" value="100" step="10">
                                        <span class="filter-value" id="brightnessValue">100%</span>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label for="contrastFilter">
                                            <i data-feather="maximize-2" style="width: 14px; height: 14px;"></i>
                                            Contrast
                                        </label>
                                        <input type="range" id="contrastFilter" class="filter-slider" min="0" max="200" value="100" step="10">
                                        <span class="filter-value" id="contrastValue">100%</span>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label for="saturationFilter">
                                            <i data-feather="droplet" style="width: 14px; height: 14px;"></i>
                                            Saturation
                                        </label>
                                        <input type="range" id="saturationFilter" class="filter-slider" min="0" max="200" value="100" step="10">
                                        <span class="filter-value" id="saturationValue">100%</span>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label for="hueFilter">
                                            <i data-feather="sliders" style="width: 14px; height: 14px;"></i>
                                            Hue Rotation
                                        </label>
                                        <input type="range" id="hueFilter" class="filter-slider" min="0" max="360" value="0" step="15">
                                        <span class="filter-value" id="hueValue">0°</span>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label for="blurFilter">
                                            <i data-feather="wind" style="width: 14px; height: 14px;"></i>
                                            Sharpness
                                        </label>
                                        <input type="range" id="blurFilter" class="filter-slider" min="0" max="10" value="0" step="1">
                                        <span class="filter-value" id="blurValue">Normal</span>
                                    </div>
                                    
                                    <div class="filter-presets">
                                        <button class="filter-preset-btn" id="presetNormal" title="Reset to Normal">
                                            <span>Normal</span>
                                        </button>
                                        <button class="filter-preset-btn" id="presetVivid" title="Vivid Colors">
                                            <span>Vivid</span>
                                        </button>
                                        <button class="filter-preset-btn" id="presetPortrait" title="Portrait Mode">
                                            <span>Portrait</span>
                                        </button>
                                        <button class="filter-preset-btn" id="presetLandscape" title="Landscape Mode">
                                            <span>Landscape</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="camera-controls">
                                <button class="camera-btn flip-btn" id="flipCameraBtn" title="Flip Camera">
                                    <i data-feather="repeat"></i>
                                </button>
                                <button class="camera-btn capture-btn" id="capturePhotoBtn" title="Capture Photo">
                                    <i data-feather="camera"></i>
                                </button>
                            </div>
                        </div>

                        <div class="preview-container" id="previewContainer" style="display: none;">
                            <img id="capturedPreview" src="" alt="Captured Photo" class="captured-photo">
                            <div class="preview-info">
                                <p id="previewMessage">Photo captured successfully!</p>
                                <p class="preview-time" id="captureTime"></p>
                            </div>

                            <!-- Location Address Section -->
                            <div id="addressSection" style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border-color);">
                                <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                                    <i data-feather="map-pin" style="width: 18px; height: 18px; color: var(--text-secondary);"></i>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Location</span>
                                </div>
                                <p id="addressText" style="margin: 0; color: var(--text-primary); font-weight: 500;">Loading address...</p>
                                <p id="coordsText" style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);"></p>
                            </div>

                            <!-- Geofence Status Section -->
                            <div id="geofenceStatusSection" style="margin-top: 1.5rem; padding: 1rem; border-radius: 8px; display: none;">
                                <div id="geofenceWithinBox" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); padding: 1rem; border-radius: 8px; display: none;">
                                    <div style="display: flex; align-items: center; gap: 0.8rem; color: var(--status-qualified);">
                                        <i data-feather="check-circle" style="width: 20px; height: 20px;"></i>
                                        <div>
                                            <div style="font-weight: 600;">Within Geofence</div>
                                            <div style="font-size: 0.85rem;" id="geofenceLocationName"></div>
                                        </div>
                                    </div>
                                </div>

                                <div id="geofenceOutsideBox" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); padding: 1rem; border-radius: 8px; display: none;">
                                    <div style="margin-bottom: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.8rem; color: var(--status-lost); margin-bottom: 0.8rem;">
                                            <i data-feather="alert-circle" style="width: 20px; height: 20px;"></i>
                                            <div style="font-weight: 600;">Outside Geofence</div>
                                        </div>
                                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Please provide reason for being outside the authorized location (minimum 10 words required)</p>
                                    </div>
                                    <textarea id="geofenceReasonTextarea" placeholder="Explain why you are punching outside the authorized geofence location..." style="width: 100%; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-input); color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; min-height: 80px; resize: vertical;"></textarea>
                                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                        <span id="geofenceWordCount" style="font-size: 0.8rem; color: var(--text-muted);">0 / 10 words</span>
                                        <span id="geofenceWordWarning" style="font-size: 0.8rem; color: var(--status-lost); display: none;">Minimum 10 words required</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Work Report Section - Only for Punch Out -->
                            <div id="workReportSection" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                <label style="display: block; margin-bottom: 0.8rem; font-weight: 600; color: var(--text-primary);">Work Report <span style="color: var(--status-lost);">*</span></label>
                                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">Please describe what you accomplished (minimum 20 words required)</p>
                                <textarea id="workReportTextarea" placeholder="Describe your work, tasks completed, projects worked on, etc..." style="width: 100%; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-input); color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; min-height: 100px; resize: vertical;"></textarea>
                                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                    <span id="wordCount" style="font-size: 0.8rem; color: var(--text-muted);">0 / 20 words</span>
                                    <span id="wordWarning" style="font-size: 0.8rem; color: var(--status-lost); display: none;">Minimum 20 words required</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" id="cancelPunchBtn">Cancel</button>
                        <button class="btn-primary" id="confirmPunchBtn" style="display: none;">Confirm & Punch In</button>
                        <button class="btn-primary" id="confirmPunchOutBtn" style="display: none; background-color: var(--status-lost);">Confirm & Punch Out</button>
                        <button class="btn-secondary" id="retakePunchBtn" style="display: none;">Retake Photo</button>
                    </div>
                </div>
            </div>
        `;

        // Insert modal into DOM
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);

        // Cache modal elements
        this.modal = document.getElementById('punchInModal');
        this.video = document.getElementById('punchCamera');
        this.canvas = document.getElementById('captureCanvas');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Modal open/close
        const punchBtn = document.getElementById('punchInBtn');
        const closeBtn = document.getElementById('closePunchModalBtn');
        const cancelBtn = document.getElementById('cancelPunchBtn');

        if (punchBtn) {
            punchBtn.addEventListener('click', () => this.openModal());
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeModal());
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeModal());
        }

        // Camera controls
        const flipBtn = document.getElementById('flipCameraBtn');
        const captureBtn = document.getElementById('capturePhotoBtn');
        const retakeBtn = document.getElementById('retakePunchBtn');
        const confirmBtn = document.getElementById('confirmPunchBtn');
        const confirmOutBtn = document.getElementById('confirmPunchOutBtn');

        if (flipBtn) {
            flipBtn.addEventListener('click', () => this.flipCamera());
        }

        if (captureBtn) {
            captureBtn.addEventListener('click', () => this.capturePhoto());
        }

        if (retakeBtn) {
            retakeBtn.addEventListener('click', () => this.retakePhoto());
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmPunchIn());
        }

        if (confirmOutBtn) {
            confirmOutBtn.addEventListener('click', () => this.confirmPunchOut());
        }

        // Close on outside click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Filter controls
        this.setupFilterControls();
    }

    /**
     * Setup filter controls event listeners
     */
    setupFilterControls() {
        const filterToggleBtn = document.getElementById('filterToggleBtn');
        const filterControls = document.getElementById('filterControls');

        if (filterToggleBtn) {
            filterToggleBtn.addEventListener('click', () => {
                const isVisible = filterControls.style.display !== 'none';
                filterControls.style.display = isVisible ? 'none' : 'block';
            });
        }

        // Brightness filter
        const brightnessFilter = document.getElementById('brightnessFilter');
        if (brightnessFilter) {
            brightnessFilter.addEventListener('input', (e) => {
                this.filters.brightness = parseInt(e.target.value);
                document.getElementById('brightnessValue').textContent = e.target.value + '%';
                this.applyFiltersToVideo();
            });
        }

        // Contrast filter
        const contrastFilter = document.getElementById('contrastFilter');
        if (contrastFilter) {
            contrastFilter.addEventListener('input', (e) => {
                this.filters.contrast = parseInt(e.target.value);
                document.getElementById('contrastValue').textContent = e.target.value + '%';
                this.applyFiltersToVideo();
            });
        }

        // Saturation filter
        const saturationFilter = document.getElementById('saturationFilter');
        if (saturationFilter) {
            saturationFilter.addEventListener('input', (e) => {
                this.filters.saturation = parseInt(e.target.value);
                document.getElementById('saturationValue').textContent = e.target.value + '%';
                this.applyFiltersToVideo();
            });
        }

        // Hue rotation filter
        const hueFilter = document.getElementById('hueFilter');
        if (hueFilter) {
            hueFilter.addEventListener('input', (e) => {
                this.filters.hue = parseInt(e.target.value);
                document.getElementById('hueValue').textContent = e.target.value + '°';
                this.applyFiltersToVideo();
            });
        }

        // Blur/Sharpness filter
        const blurFilter = document.getElementById('blurFilter');
        if (blurFilter) {
            blurFilter.addEventListener('input', (e) => {
                this.filters.blur = parseInt(e.target.value);
                const blurValue = ['Normal', 'Soft', 'Softer', 'Softest', 'Very Soft', 'Dreamy', 'Very Dreamy', 'Ultra Soft', 'Misty', 'Hazy', 'Very Hazy'];
                document.getElementById('blurValue').textContent = blurValue[parseInt(e.target.value)] || 'Normal';
                this.applyFiltersToVideo();
            });
        }

        // Preset buttons
        const presetButtons = {
            'presetNormal': () => this.applyPreset('normal'),
            'presetVivid': () => this.applyPreset('vivid'),
            'presetPortrait': () => this.applyPreset('portrait'),
            'presetLandscape': () => this.applyPreset('landscape')
        };

        for (const [id, handler] of Object.entries(presetButtons)) {
            const btn = document.getElementById(id);
            if (btn) {
                btn.addEventListener('click', handler);
            }
        }
    }

    /**
     * Apply current filters to video stream
     */
    applyFiltersToVideo() {
        if (!this.video) return;

        const filterString = `
            brightness(${this.filters.brightness}%)
            contrast(${this.filters.contrast}%)
            saturate(${this.filters.saturation}%)
            hue-rotate(${this.filters.hue}deg)
            blur(${this.filters.blur * 0.5}px)
        `;

        this.video.style.filter = filterString;
        // Store in CSS variable for canvas
        document.documentElement.style.setProperty('--camera-filter', filterString);
    }

    /**
     * Apply filter presets
     */
    applyPreset(preset) {
        const presets = {
            normal: { brightness: 100, contrast: 100, saturation: 100, hue: 0, blur: 0 },
            vivid: { brightness: 110, contrast: 130, saturation: 150, hue: 0, blur: 0 },
            portrait: { brightness: 105, contrast: 115, saturation: 120, hue: 0, blur: 2 },
            landscape: { brightness: 100, contrast: 120, saturation: 140, hue: 0, blur: 0 }
        };

        const selectedPreset = presets[preset] || presets.normal;

        this.filters = { ...selectedPreset };

        // Update UI sliders
        document.getElementById('brightnessFilter').value = this.filters.brightness;
        document.getElementById('contrastFilter').value = this.filters.contrast;
        document.getElementById('saturationFilter').value = this.filters.saturation;
        document.getElementById('hueFilter').value = this.filters.hue;
        document.getElementById('blurFilter').value = this.filters.blur;

        // Update values
        document.getElementById('brightnessValue').textContent = this.filters.brightness + '%';
        document.getElementById('contrastValue').textContent = this.filters.contrast + '%';
        document.getElementById('saturationValue').textContent = this.filters.saturation + '%';
        document.getElementById('hueValue').textContent = this.filters.hue + '°';

        const blurValue = ['Normal', 'Soft', 'Softer', 'Softest', 'Very Soft', 'Dreamy', 'Very Dreamy', 'Ultra Soft', 'Misty', 'Hazy', 'Very Hazy'];
        document.getElementById('blurValue').textContent = blurValue[this.filters.blur] || 'Normal';

        this.applyFiltersToVideo();
    }

    /**
     * Open punch-in modal and start camera
     */
    async openModal() {
        this.modal.classList.add('active');

        // Update modal based on punch status
        const modalTitle = document.getElementById('punchModalTitle');
        const punchStatusSection = document.getElementById('punchStatusSection');
        const cameraContainer = document.getElementById('cameraContainer');
        const confirmPunchBtn = document.getElementById('confirmPunchBtn');
        const confirmPunchOutBtn = document.getElementById('confirmPunchOutBtn');

        if (this.isPunchedIn && this.currentPunchData) {
            // Show punch out mode
            modalTitle.textContent = 'Punch Out - Capture Photo';
            punchStatusSection.style.display = 'block';
            cameraContainer.style.display = 'block';
            confirmPunchBtn.style.display = 'none';
            confirmPunchOutBtn.style.display = 'none';

            // Display punch in time
            const punchInTimeDisplay = document.getElementById('punchInTimeDisplay');
            if (this.currentPunchData.time) {
                punchInTimeDisplay.textContent = this.currentPunchData.time;
            }
        } else {
            // Show punch in mode
            modalTitle.textContent = 'Punch In - Capture Photo';
            punchStatusSection.style.display = 'none';
            cameraContainer.style.display = 'block';
            confirmPunchBtn.style.display = 'none';
            confirmPunchOutBtn.style.display = 'none';
        }

        await this.fetchAndDisplayShifts();
        await this.fetchGeofences();
        await this.startCamera();

        // Re-initialize feather icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    /**
     * Close punch-in modal and stop camera
     */
    closeModal() {
        this.modal.classList.remove('active');
        this.stopCamera();
        this.resetModal();
    }

    /**
     * Fetch and display user shifts
     */
    async fetchAndDisplayShifts() {
        const shiftsSection = document.getElementById('shiftsSection');
        const shiftsContainer = document.getElementById('shiftsContainer');

        try {
            const response = await fetch('api_get_shifts.php');
            const data = await response.json();

            if (data.success && data.shifts.length > 0) {
                shiftsSection.style.display = 'block';

                let shiftsHTML = '';
                data.shifts.forEach(shift => {
                    shiftsHTML += `
                        <div class="shift-card">
                            <div class="shift-info">
                                <h5 class="shift-name">${shift.shift_name}</h5>
                                <div class="shift-timings">
                                    <span class="shift-time">
                                        <i data-feather="clock" style="width: 14px; height: 14px;"></i>
                                        ${shift.start_time} - ${shift.end_time}
                                    </span>
                                </div>
                            </div>
                            ${shift.weekly_offs ? `
                                <div class="shift-footer">
                                    <span class="shift-weekly-off">
                                        <i data-feather="calendar" style="width: 12px; height: 12px;"></i>
                                        Weekly Off: ${shift.weekly_offs}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });

                shiftsContainer.innerHTML = shiftsHTML;

                // Re-initialize feather icons
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            } else {
                shiftsSection.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching shifts:', error);
            shiftsContainer.innerHTML = '<div class="shift-error">Unable to load shift information</div>';
        }
    }

    /**
     * Start camera stream
     */
    async startCamera() {
        try {
            const constraints = {
                video: {
                    facingMode: this.isFrontCamera ? 'user' : 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };

            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.stream;

            // Wait for video to load
            this.video.onloadedmetadata = () => {
                this.video.play();
            };
        } catch (error) {
            console.error('Error accessing camera:', error);
            alert('Unable to access camera. Please check permissions and try again.');
            this.closeModal();
        }
    }

    /**
     * Stop camera stream
     */
    stopCamera() {
        if (this.stream) {
            const tracks = this.stream.getTracks();
            tracks.forEach(track => track.stop());
            this.stream = null;
        }
    }

    /**
     * Flip between front and back camera
     */
    async flipCamera() {
        this.isFrontCamera = !this.isFrontCamera;
        this.stopCamera();
        await this.startCamera();
    }

    /**
     * Get address from latitude and longitude using reverse geocoding
     */
    async getAddressFromCoordinates(latitude, longitude) {
        try {
            // Using OpenStreetMap Nominatim (free reverse geocoding service)
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`,
                {
                    headers: {
                        'Accept-Language': 'en',
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    }
                }
            );

            if (!response.ok) {
                console.warn('Geocoding API error:', response.status);
                return `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
            }

            const data = await response.json();

            // Try multiple address fields for better results
            const address = data.address?.road ||
                data.address?.pedestrian ||
                data.address?.residential ||
                data.address?.village ||
                data.address?.suburb ||
                data.address?.city ||
                data.address?.town ||
                data.address?.county ||
                `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;

            return address;
        } catch (error) {
            console.warn('Error fetching address:', error);
            return `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
        }
    }

    /**
     * Capture photo from video stream
     */
    async capturePhoto() {
        const context = this.canvas.getContext('2d');
        const width = this.video.videoWidth;
        const height = this.video.videoHeight;

        // Set canvas size
        this.canvas.width = width;
        this.canvas.height = height;

        // Mirror the image for front camera
        if (this.isFrontCamera) {
            context.scale(-1, 1);
            context.drawImage(this.video, -width, 0, width, height);
        } else {
            context.drawImage(this.video, 0, 0, width, height);
        }

        // Apply filters to canvas image data
        this.applyFiltersToCanvas(context, width, height);

        // Get image data with optimized quality
        this.capturedImage = this.canvas.toDataURL('image/jpeg', 0.8);

        // Get current geolocation for geofence check
        if ('geolocation' in navigator) {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    });
                });

                this.currentLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };

                // Get address from coordinates with timeout
                let address = `${this.currentLocation.latitude.toFixed(4)}, ${this.currentLocation.longitude.toFixed(4)}`;

                try {
                    const addressPromise = this.getAddressFromCoordinates(
                        this.currentLocation.latitude,
                        this.currentLocation.longitude
                    );

                    // Set 3 second timeout for address fetch
                    const timeoutPromise = new Promise((resolve) => {
                        setTimeout(() => resolve(address), 3000);
                    });

                    address = await Promise.race([addressPromise, timeoutPromise]);
                } catch (error) {
                    console.warn('Address fetch error:', error);
                }

                this.currentLocation.address = address;
            } catch (error) {
                console.warn('Geolocation error during photo capture:', error);
                this.currentLocation = null;
            }
        }

        // Show preview
        this.showPreview();
    }

    /**
     * Apply filters to canvas image data
     */
    applyFiltersToCanvas(context, width, height) {
        const imageData = context.getImageData(0, 0, width, height);
        const data = imageData.data;

        // Apply filters using pixel manipulation
        for (let i = 0; i < data.length; i += 4) {
            // Get RGB values
            let r = data[i];
            let g = data[i + 1];
            let b = data[i + 2];

            // Apply brightness
            const brightnessFactor = this.filters.brightness / 100;
            r = Math.min(255, r * brightnessFactor);
            g = Math.min(255, g * brightnessFactor);
            b = Math.min(255, b * brightnessFactor);

            // Apply contrast
            const contrastFactor = this.filters.contrast / 100;
            r = Math.min(255, Math.max(0, (r - 128) * contrastFactor + 128));
            g = Math.min(255, Math.max(0, (g - 128) * contrastFactor + 128));
            b = Math.min(255, Math.max(0, (b - 128) * contrastFactor + 128));

            // Convert RGB to HSL for saturation and hue adjustments
            const rgb = this.rgbToHsl(r, g, b);

            // Apply saturation
            rgb.s = Math.min(100, rgb.s * (this.filters.saturation / 100));

            // Apply hue rotation
            rgb.h = (rgb.h + this.filters.hue) % 360;

            // Convert back to RGB
            const newRgb = this.hslToRgb(rgb.h, rgb.s, rgb.l);

            data[i] = newRgb.r;
            data[i + 1] = newRgb.g;
            data[i + 2] = newRgb.b;
        }

        // Apply blur effect if needed (simplified)
        if (this.filters.blur > 0) {
            const blurAmount = this.filters.blur;
            // Simple blur using canvas filter is more efficient
            // This pixel method would be too slow for blur
            context.filter = `blur(${blurAmount * 0.5}px)`;
            context.drawImage(this.canvas, 0, 0);
        }

        context.putImageData(imageData, 0, 0);
    }

    /**
     * Convert RGB to HSL
     */
    rgbToHsl(r, g, b) {
        r /= 255;
        g /= 255;
        b /= 255;

        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;

        if (max === min) {
            h = s = 0;
        } else {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

            switch (max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }

        return { h: h * 360, s: s * 100, l: l * 100 };
    }

    /**
     * Convert HSL to RGB
     */
    hslToRgb(h, s, l) {
        h = h % 360;
        s = s / 100;
        l = l / 100;

        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h / 60) % 2 - 1));
        const m = l - c / 2;

        let r, g, b;

        if (h < 60) {
            r = c; g = x; b = 0;
        } else if (h < 120) {
            r = x; g = c; b = 0;
        } else if (h < 180) {
            r = 0; g = c; b = x;
        } else if (h < 240) {
            r = 0; g = x; b = c;
        } else if (h < 300) {
            r = x; g = 0; b = c;
        } else {
            r = c; g = 0; b = x;
        }

        return {
            r: Math.round((r + m) * 255),
            g: Math.round((g + m) * 255),
            b: Math.round((b + m) * 255)
        };
    }

    /**
     * Show captured photo preview
     */
    showPreview() {
        const cameraContainer = document.getElementById('cameraContainer');
        const previewContainer = document.getElementById('previewContainer');
        const capturedPreview = document.getElementById('capturedPreview');
        const captureTime = document.getElementById('captureTime');
        const previewMessage = document.getElementById('previewMessage');
        const captureBtn = document.getElementById('capturePhotoBtn');
        const flipBtn = document.getElementById('flipCameraBtn');
        const retakeBtn = document.getElementById('retakePunchBtn');
        const confirmBtn = document.getElementById('confirmPunchBtn');
        const confirmOutBtn = document.getElementById('confirmPunchOutBtn');

        // Hide camera, show preview
        cameraContainer.style.display = 'none';
        previewContainer.style.display = 'block';

        // Set preview image
        capturedPreview.src = this.capturedImage;

        // Set capture time
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata'
        });
        captureTime.textContent = `Captured at ${timeStr} IST`;

        // Update geofence status UI with current location
        if (this.currentLocation) {
            this.updateGeofenceStatusUI(this.currentLocation.latitude, this.currentLocation.longitude);
            this.initializeGeofenceReasonCounter();

            // Display address
            const addressText = document.getElementById('addressText');
            const coordsText = document.getElementById('coordsText');
            if (addressText) {
                addressText.textContent = this.currentLocation.address || 'Address unavailable';
                coordsText.textContent = `Latitude: ${this.currentLocation.latitude.toFixed(6)} | Longitude: ${this.currentLocation.longitude.toFixed(6)}`;
            }
        }

        // Update preview message based on punch status
        const workReportSection = document.getElementById('workReportSection');
        const workReportTextarea = document.getElementById('workReportTextarea');

        if (this.isPunchedIn) {
            previewMessage.textContent = 'Punch out photo captured!';
            // Show work report section for punch-out
            workReportSection.style.display = 'block';
            workReportTextarea.value = '';
            this.initializeWordCounter();
        } else {
            previewMessage.textContent = 'Photo captured successfully!';
            workReportSection.style.display = 'none';
        }

        // Update buttons
        captureBtn.style.display = 'none';
        flipBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-flex';

        if (this.isPunchedIn) {
            confirmBtn.style.display = 'none';
            confirmOutBtn.style.display = 'inline-flex';
        } else {
            confirmBtn.style.display = 'inline-flex';
            confirmOutBtn.style.display = 'none';
        }
    }

    /**
     * Retake photo
     */
    retakePhoto() {
        const cameraContainer = document.getElementById('cameraContainer');
        const previewContainer = document.getElementById('previewContainer');
        const captureBtn = document.getElementById('capturePhotoBtn');
        const flipBtn = document.getElementById('flipCameraBtn');
        const retakeBtn = document.getElementById('retakePunchBtn');
        const confirmBtn = document.getElementById('confirmPunchBtn');
        const confirmOutBtn = document.getElementById('confirmPunchOutBtn');

        // Hide preview, show camera
        cameraContainer.style.display = 'block';
        previewContainer.style.display = 'none';

        // Reset buttons
        captureBtn.style.display = 'inline-flex';
        flipBtn.style.display = 'inline-flex';
        retakeBtn.style.display = 'none';
        confirmBtn.style.display = 'none';
        confirmOutBtn.style.display = 'none';

        // Clear captured image
        this.capturedImage = null;
    }

    /**
     * Confirm punch-in with captured photo
     */
    async confirmPunchIn() {
        if (!this.capturedImage) {
            console.log('Please capture a photo first.');
            return;
        }

        // Validate geofence reason if needed
        if (!this.validateGeofenceReason()) {
            return;
        }

        // Show loading state
        const confirmBtn = document.getElementById('confirmPunchBtn');
        const originalText = confirmBtn.textContent;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Submitting...';

        try {
            // Get current geolocation if available
            let latitude = null;
            let longitude = null;
            let accuracy = null;

            if ('geolocation' in navigator) {
                try {
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });

                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    accuracy = position.coords.accuracy;
                } catch (error) {
                    console.warn('Geolocation not available:', error);
                }
            }

            // Prepare punch-in data
            const punchData = {
                photo: this.capturedImage,
                camera: this.isFrontCamera ? 'front' : 'back',
                latitude: latitude,
                longitude: longitude,
                accuracy: accuracy,
                address: this.currentLocation?.address
            };

            // Add geofence details if location available
            if (latitude && longitude) {
                const geofenceDetails = this.getGeofenceDetails(latitude, longitude);
                punchData.within_geofence = geofenceDetails.within_geofence;
                punchData.distance_from_geofence = geofenceDetails.distance_from_geofence;
                punchData.geofence_id = geofenceDetails.geofence_id;
            }

            // Add geofence reason if outside geofence
            const geofenceReason = this.getGeofenceReason();
            if (geofenceReason) {
                punchData.geofence_outside_reason = geofenceReason;
            }

            // Send to backend API with timeout
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 30000); // 30 second timeout

            const response = await fetch('api_punch_in.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(punchData),
                signal: controller.signal
            });

            clearTimeout(timeout);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`Server error: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Store locally for reference (without the huge photo)
                const localData = {
                    date: new Date().toLocaleDateString('en-IN'),
                    time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }),
                    timestamp: Date.now(),
                    camera: this.isFrontCamera ? 'front' : 'back',
                    attendance_id: result.attendance_id,
                    status: 'success',
                    punchOutTime: null  // Will be set on punch out
                };

                localStorage.setItem('lastPunchIn', JSON.stringify(localData));

                // Update punch status
                this.isPunchedIn = true;
                this.currentPunchData = localData;

                // Trigger success event
                this.dispatchPunchInEvent(localData);

                // Play success sound
                this.playPunchSound('in');

                // Show success message
                this.showSuccessMessage('Punch-in recorded successfully!');

                // Close modal after delay
                setTimeout(() => {
                    this.closeModal();
                }, 1500);
            } else {
                throw new Error(result.error || 'Failed to record punch-in');
            }
        } catch (error) {
            console.error('Punch-in error:', error);
            let errorMessage = 'Failed to record punch-in. Please try again.';

            if (error.name === 'AbortError') {
                errorMessage = 'Request timeout. Please check your connection and try again.';
            } else if (error.message) {
                errorMessage = error.message;
            }

            // Check if it's a network error
            if (error instanceof TypeError) {
                errorMessage = 'Network error: Unable to connect to server';
            }

            this.showErrorMessage(errorMessage);

            // Re-enable button
            const confirmBtn = document.getElementById('confirmPunchBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Confirm & Punch In';
            }
        }
    }

    /**
     * Confirm punch-out with captured photo
     */
    async confirmPunchOut() {
        if (!this.capturedImage) {
            console.log('Please capture a photo first.');
            return;
        }

        // Validate work report
        if (!this.validateWorkReport()) {
            return;
        }

        // Validate geofence reason if needed
        if (!this.validateGeofenceReason()) {
            return;
        }

        // Get work report
        const workReportTextarea = document.getElementById('workReportTextarea');
        const workReport = workReportTextarea.value.trim();

        // Show loading state
        const confirmBtn = document.getElementById('confirmPunchOutBtn');
        const originalText = confirmBtn.textContent;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Submitting...';

        try {
            // Get current geolocation if available
            let latitude = null;
            let longitude = null;
            let accuracy = null;

            if ('geolocation' in navigator) {
                try {
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });

                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    accuracy = position.coords.accuracy;
                } catch (error) {
                    console.warn('Geolocation not available:', error);
                }
            }

            // Prepare punch-out data
            const punchOutData = {
                photo: this.capturedImage,
                camera: this.isFrontCamera ? 'front' : 'back',
                latitude: latitude,
                longitude: longitude,
                accuracy: accuracy,
                attendance_id: this.currentPunchData?.attendance_id,
                workReport: workReport,
                punch_out_address: this.currentLocation?.address
            };

            // Add geofence details if location available
            if (latitude && longitude) {
                const geofenceDetails = this.getGeofenceDetails(latitude, longitude);
                punchOutData.punch_out_within_geofence = geofenceDetails.within_geofence;
                punchOutData.punch_out_distance_from_geofence = geofenceDetails.distance_from_geofence;
                punchOutData.punch_out_geofence_id = geofenceDetails.geofence_id;
            }

            // Add geofence reason if outside geofence
            const geofenceReason = this.getGeofenceReason();
            if (geofenceReason) {
                punchOutData.geofence_outside_reason = geofenceReason;
            }

            // Send to backend API with timeout (would need api_punch_out.php)
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 30000);

            const response = await fetch('api_punch_out.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(punchOutData),
                signal: controller.signal
            });

            clearTimeout(timeout);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`Server error: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Update local data with punch out time
                const punchOutTime = new Date().toLocaleTimeString('en-IN', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: 'Asia/Kolkata'
                });

                const updatedData = {
                    ...this.currentPunchData,
                    punchOutTime: punchOutTime,
                    punchOutTimestamp: Date.now()
                };

                localStorage.setItem('lastPunchIn', JSON.stringify(updatedData));

                // Reset punch status - allow next day punch in
                this.isPunchedIn = false;
                this.currentPunchData = null;

                // Trigger success event
                this.dispatchPunchOutEvent(updatedData);

                // Play success sound
                this.playPunchSound('out');

                // Show success message
                this.showSuccessMessage('Punch-out recorded successfully!');

                // Close modal after delay
                setTimeout(() => {
                    this.closeModal();
                }, 1500);
            } else {
                throw new Error(result.error || 'Failed to record punch-out');
            }
        } catch (error) {
            console.error('Punch-out error:', error);
            let errorMessage = 'Failed to record punch-out. Please try again.';

            if (error.name === 'AbortError') {
                errorMessage = 'Request timeout. Please check your connection and try again.';
            } else if (error.message) {
                errorMessage = error.message;
            }

            if (error instanceof TypeError) {
                errorMessage = 'Network error: Unable to connect to server';
            }

            this.showErrorMessage(errorMessage);

            // Re-enable button
            const confirmBtn = document.getElementById('confirmPunchOutBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Confirm & Punch Out';
            }
        }

    }

    /**
     * Show success message
     */
    showSuccessMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            font-size: 0.95rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);

        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => messageDiv.remove(), 300);
        }, 3000);
    }

    /**
     * Show error message
     */
    showErrorMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            font-size: 0.95rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);

        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => messageDiv.remove(), 300);
        }, 4000);
    }

    /**
     * Dispatch custom punch-in event
     */
    dispatchPunchInEvent(punchData) {
        const event = new CustomEvent('punchInSuccess', {
            detail: punchData
        });
        document.dispatchEvent(event);
    }

    /**
     * Dispatch custom punch-out event
     */
    dispatchPunchOutEvent(punchData) {
        const event = new CustomEvent('punchOutSuccess', {
            detail: punchData
        });
        document.dispatchEvent(event);
    }

    /**
     * Initialize word counter for work report
     */
    initializeWordCounter() {
        const workReportTextarea = document.getElementById('workReportTextarea');
        const wordCount = document.getElementById('wordCount');
        const wordWarning = document.getElementById('wordWarning');

        if (!workReportTextarea) return;

        workReportTextarea.addEventListener('input', () => {
            const text = workReportTextarea.value.trim();
            const words = text.length > 0 ? text.split(/\s+/).length : 0;

            wordCount.textContent = `${words} / 20 words`;

            if (words < 20) {
                wordWarning.style.display = 'inline';
                wordCount.style.color = 'var(--status-lost)';
            } else {
                wordWarning.style.display = 'none';
                wordCount.style.color = 'var(--status-qualified)';
            }
        });
    }

    /**
     * Fetch active geofence locations from API
     */
    async fetchGeofences() {
        try {
            const response = await fetch('api_get_geofences.php');
            const data = await response.json();

            if (data.success) {
                this.geofences = data.geofences || [];
            }
        } catch (error) {
            console.error('Error fetching geofences:', error);
            this.geofences = [];
        }
    }

    /**
     * Get geofence details for current location
     * Returns object with: withinGeofence (boolean), distanceFromGeofence (meters), geofenceId (id or null)
     */
    getGeofenceDetails(userLat, userLon) {
        const details = {
            within_geofence: false,
            distance_from_geofence: null,
            geofence_id: null
        };

        if (!this.geofences || this.geofences.length === 0) {
            return details;
        }

        let closestDistance = Infinity;
        let closestGeofence = null;

        for (let geofence of this.geofences) {
            const distance = this.getDistanceBetweenCoordinates(
                userLat, userLon,
                geofence.latitude, geofence.longitude
            );

            if (distance < closestDistance) {
                closestDistance = distance;
                closestGeofence = geofence;
            }
        }

        if (closestGeofence) {
            details.geofence_id = closestGeofence.id;
            details.distance_from_geofence = Math.round(closestDistance); // Store in meters
            details.within_geofence = closestDistance <= closestGeofence.radius ? 1 : 0;
        }

        return details;
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * Returns distance in meters
     */
    getDistanceBetweenCoordinates(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    /**
     * Check if user is within any geofence radius
     */
    checkIfWithinGeofence(userLat, userLon) {
        if (!this.geofences || this.geofences.length === 0) {
            return false;
        }

        for (let geofence of this.geofences) {
            const distance = this.getDistanceBetweenCoordinates(
                userLat, userLon,
                geofence.latitude, geofence.longitude
            );

            if (distance <= geofence.radius) {
                this.isWithinGeofence = true;
                return true;
            }
        }

        this.isWithinGeofence = false;
        return false;
    }

    /**
     * Update geofence status in the UI
     */
    updateGeofenceStatusUI(userLat, userLon) {
        const geofenceStatusSection = document.getElementById('geofenceStatusSection');
        const geofenceWithinBox = document.getElementById('geofenceWithinBox');
        const geofenceOutsideBox = document.getElementById('geofenceOutsideBox');

        // Don't show geofence section if no geofences available
        if (!geofenceStatusSection || !this.geofences || this.geofences.length === 0) {
            if (geofenceStatusSection) {
                geofenceStatusSection.style.display = 'none';
            }
            return;
        }

        const isWithinGeofence = this.checkIfWithinGeofence(userLat, userLon);

        geofenceStatusSection.style.display = 'block';

        if (isWithinGeofence) {
            geofenceWithinBox.style.display = 'block';
            geofenceOutsideBox.style.display = 'none';

            // Find and display the closest geofence name
            let closestGeofence = null;
            let closestDistance = Infinity;

            for (let geofence of this.geofences) {
                const distance = this.getDistanceBetweenCoordinates(
                    userLat, userLon,
                    geofence.latitude, geofence.longitude
                );

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestGeofence = geofence;
                }
            }

            if (closestGeofence) {
                document.getElementById('geofenceLocationName').textContent = closestGeofence.name;
            }
        } else {
            geofenceWithinBox.style.display = 'none';
            geofenceOutsideBox.style.display = 'block';
        }
    }

    /**
     * Initialize geofence reason word counter
     */
    initializeGeofenceReasonCounter() {
        const reasonTextarea = document.getElementById('geofenceReasonTextarea');
        if (!reasonTextarea) return;

        reasonTextarea.addEventListener('input', () => {
            const text = reasonTextarea.value.trim();
            const words = text.length > 0 ? text.split(/\s+/).length : 0;
            const wordCount = document.getElementById('geofenceWordCount');
            const wordWarning = document.getElementById('geofenceWordWarning');

            if (wordCount) {
                wordCount.textContent = `${words} / 10 words`;

                if (words < 10) {
                    wordWarning.style.display = 'inline';
                    reasonTextarea.style.borderColor = 'var(--status-lost)';
                } else {
                    wordWarning.style.display = 'none';
                    reasonTextarea.style.borderColor = 'var(--status-qualified)';
                }
            }
        });
    }

    /**
     * Validate geofence reason if outside geofence
     */
    validateGeofenceReason() {
        const geofenceOutsideBox = document.getElementById('geofenceOutsideBox');

        // If geofence section is not visible or user is within geofence, no validation needed
        if (!geofenceOutsideBox || geofenceOutsideBox.style.display === 'none') {
            return true;
        }

        const reasonTextarea = document.getElementById('geofenceReasonTextarea');
        const text = reasonTextarea.value.trim();
        const words = text.length > 0 ? text.split(/\s+/).length : 0;

        if (words < 10) {
            this.showErrorMessage('Reason for being outside geofence must contain at least 10 words');
            return false;
        }
        return true;
    }

    /**
     * Get geofence reason text
     */
    getGeofenceReason() {
        const reasonTextarea = document.getElementById('geofenceReasonTextarea');
        if (reasonTextarea && reasonTextarea.style.display !== 'none') {
            return reasonTextarea.value.trim();
        }
        return null;
    }

    /**
     * Validate work report has minimum 20 words
     */
    validateWorkReport() {
        const workReportTextarea = document.getElementById('workReportTextarea');
        const text = workReportTextarea.value.trim();
        const words = text.length > 0 ? text.split(/\s+/).length : 0;

        if (words < 20) {
            this.showErrorMessage('Work report must contain at least 20 words');
            return false;
        }
        return true;
    }

    /**
     * Reset modal to initial state
     */
    resetModal() {
        const cameraContainer = document.getElementById('cameraContainer');
        const previewContainer = document.getElementById('previewContainer');
        const captureBtn = document.getElementById('capturePhotoBtn');
        const flipBtn = document.getElementById('flipCameraBtn');
        const retakeBtn = document.getElementById('retakePunchBtn');
        const confirmBtn = document.getElementById('confirmPunchBtn');
        const confirmOutBtn = document.getElementById('confirmPunchOutBtn');
        const punchStatusSection = document.getElementById('punchStatusSection');

        // Reset UI
        cameraContainer.style.display = 'block';
        previewContainer.style.display = 'none';
        punchStatusSection.style.display = 'none';
        captureBtn.style.display = 'inline-flex';
        flipBtn.style.display = 'inline-flex';
        retakeBtn.style.display = 'none';
        confirmBtn.style.display = 'none';
        confirmOutBtn.style.display = 'none';

        // Clear captured image
        this.capturedImage = null;
    }

    /**
     * Play punch sound
     * @param {string} type 'in' or 'out'
     */
    playPunchSound(type) {
        try {
            const filename = type === 'in' ? 'punch_in.mp3' : 'punch_out.mp3';
            // Path relative to the sales directory
            const audioPath = `../notification/${filename}`;

            const audio = new Audio(audioPath);
            audio.play().catch(e => {
                console.warn('Audio play failed:', e);
            });
        } catch (error) {
            console.warn('Could not play sound:', error);
        }
    }

    /**
     * Get punch-in history with photos
     */
    getPunchHistory() {
        const history = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('punchData_')) {
                history.push(JSON.parse(localStorage.getItem(key)));
            }
        }
        return history;
    }
}

// Initialize punch-in modal when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new PunchInModal();
});
