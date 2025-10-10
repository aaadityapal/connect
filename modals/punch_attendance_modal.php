<style>
.punch-attendance-modal-unique-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.punch-attendance-modal-unique-overlay.active {
    opacity: 1;
    visibility: visible;
}

.punch-attendance-modal-unique-content {
    background-color: #fff;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.punch-attendance-modal-unique-header {
    padding: 15px 20px;
    background-color: #3498db;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.punch-attendance-modal-unique-header h4 {
    margin: 0;
    font-size: 1.2rem;
}

.punch-attendance-close-modal-unique-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.punch-attendance-modal-unique-body {
    padding: 20px;
    overflow-y: auto;
    flex-grow: 1;
}

.punch-attendance-camera-container-unique {
    position: relative;
    width: 100%;
    height: 300px;
    background-color: #000;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.punch-attendance-camera-container-unique video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.punch-attendance-camera-capture-btn-unique {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.2);
    border: 2px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.punch-attendance-camera-capture-btn-unique:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.punch-attendance-camera-capture-btn-unique i {
    color: white;
    font-size: 24px;
}

.punch-attendance-switch-camera-btn-unique {
    position: absolute;
    top: 20px;
    right: 20px;
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    border-radius: 20px;
    padding: 8px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.punch-attendance-switch-camera-btn-unique:hover {
    background-color: rgba(0, 0, 0, 0.7);
}

.punch-attendance-captured-image-container-unique {
    width: 100%;
    height: 300px;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.punch-attendance-captured-image-container-unique img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.punch-attendance-location-container-unique {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}

.punch-attendance-location-container-unique h5 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.punch-attendance-location-status-unique {
    text-align: center;
    padding: 10px;
    color: #666;
}

.punch-attendance-location-details-unique p {
    margin: 8px 0;
    font-size: 0.9rem;
}

.punch-attendance-location-details-unique strong {
    color: #333;
}

/* Geofence Status Tag Styles */
.geofence-status-tag-unique {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.geofence-status-tag-unique.inside {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.geofence-status-tag-unique.outside {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Geofence Reason Textarea Styles */
.geofence-reason-textarea-unique {
    width: 100%;
    min-height: 80px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: inherit;
    font-size: 0.9rem;
    resize: vertical;
    margin-top: 5px;
}

.geofence-reason-textarea-unique:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.geofence-reason-error-unique {
    color: #e74c3c;
    font-size: 0.85rem;
    margin-top: 5px;
    display: block;
}

.punch-attendance-modal-unique-footer {
    padding: 15px 20px;
    background: #f9f9f9;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
}

.punch-attendance-retake-btn-unique,
.punch-attendance-submit-btn-unique {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}

.punch-attendance-retake-btn-unique {
    background-color: #6c757d;
    color: white;
}

.punch-attendance-retake-btn-unique:hover {
    background-color: #5a6268;
}

.punch-attendance-submit-btn-unique {
    background-color: #28a745;
    color: white;
}

.punch-attendance-submit-btn-unique:hover {
    background-color: #218838;
}

@media (max-width: 576px) {
    .punch-attendance-modal-unique-content {
        width: 95%;
        max-width: 95%;
    }
    
    .punch-attendance-modal-unique-header h4 {
        font-size: 1.1rem;
    }
    
    .punch-attendance-camera-container-unique {
        height: 250px;
    }
    
    .punch-attendance-captured-image-container-unique {
        height: 250px;
    }
    
    .punch-attendance-modal-unique-body {
        padding: 15px;
    }
    
    .geofence-status-tag-unique {
        font-size: 0.7rem;
        padding: 2px 6px;
    }
}
</style>