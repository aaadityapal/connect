<!-- Come In Modal -->
<div id="comeInModal" class="come-in-modal-overlay">
    <div class="come-in-modal-content">
        <div class="come-in-modal-header">
            <h4 id="camera-title">Attendance Camera</h4>
            <button id="closeComeInBtn" class="come-in-close-btn"><i class="fas fa-times"></i></button>
        </div>
        <div class="come-in-modal-body">
            <div class="come-in-camera-container">
                <video id="comeInCameraVideo" autoplay playsinline></video>
                <canvas id="comeInCameraCanvas" style="display: none;"></canvas>
                <div id="comeInCameraCaptureBtn" class="come-in-camera-capture-btn">
                    <i class="fas fa-camera"></i>
                </div>
                <button id="rotateComeInCameraBtn" class="btn btn-info come-in-camera-rotate-btn">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
            <div class="come-in-captured-image-container" style="display: none;">
                <img id="comeInCapturedImage" src="" alt="Captured image">
            </div>
            <div class="come-in-location-info">
                <div class="come-in-location-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="comeInLocationStatus">Getting your location...</span>
                </div>
                <div class="come-in-location-item">
                    <i class="fas fa-map"></i>
                    <span id="comeInLocationAddress">Fetching address...</span>
                </div>
                <div class="come-in-location-item">
                    <i class="fas fa-building"></i>
                    <span id="comeInGeofenceStatus">Checking location...</span>
                </div>
                <div id="outsideLocationReasonContainer" class="outside-location-reason" style="display: none; margin-top: 10px;">
                    <label for="outsideLocationReason">Please provide a reason for being outside assigned location:</label>
                    <textarea id="outsideLocationReason" class="form-control" rows="3" placeholder="Enter reason here..." style="width: 100%; min-height: 80px; margin-top: 5px; padding: 8px;"></textarea>
                </div>
            </div>
        </div>
        <div class="come-in-modal-footer">
            <button id="comeInRetakeBtn" class="btn btn-secondary" style="display: none;">Retake</button>
            <button id="comeInSaveBtn" class="btn btn-success" style="display: none;">Confirm</button>
        </div>
    </div>
</div>

<style>
.come-in-modal-overlay {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.come-in-modal-overlay.active {
    opacity: 1;
    pointer-events: all;
}

.come-in-modal-content {
    background-color: white;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.come-in-modal-header {
    padding: 15px;
    background-color: #28a745;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.come-in-modal-header h4 {
    margin: 0;
    font-size: 1.2rem;
}

.come-in-close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: white;
    cursor: pointer;
}

.come-in-modal-body {
    padding: 15px;
    overflow-y: auto;
    flex-grow: 1;
}

.come-in-camera-container {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 75%;
    background: #f0f0f0;
    overflow: hidden;
    border-radius: 5px;
    margin-bottom: 15px;
}

#comeInCameraVideo, #comeInCapturedImage {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #000;
}

.come-in-camera-capture-btn {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.come-in-camera-capture-btn i {
    font-size: 24px;
    color: #28a745;
}

.come-in-camera-rotate-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.come-in-captured-image-container {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 75%;
    background: #f0f0f0;
    border-radius: 5px;
    margin-bottom: 15px;
    overflow: hidden;
}

.come-in-location-info {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
    font-size: 0.9rem;
    margin-top: 10px;
}

.come-in-location-item {
    margin-bottom: 5px;
}

.come-in-location-item i {
    color: #28a745;
    width: 20px;
    text-align: center;
    margin-right: 5px;
}

.come-in-modal-footer {
    padding: 15px;
    background: #f9f9f9;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
}

/* New styles for outside location reason container */
.outside-location-reason {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
    margin-top: 15px !important;
}

.outside-location-reason label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: #495057;
}

.outside-location-reason textarea {
    display: block;
    width: 100%;
    min-height: 80px;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #fff;
    font-size: 0.9rem;
    resize: vertical;
}

.outside-location-reason textarea:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style> 