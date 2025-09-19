<!-- View Labour Modal -->
<div class="modal fade" id="viewLabourModal" tabindex="-1" aria-labelledby="viewLabourModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewLabourModalLabel">
                    <i class="fas fa-user me-2"></i>
                    Labour Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loading State -->
                <div class="loader-overlay" id="labourDetailsLoader" style="display: none;">
                    <div class="loader-content">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading labour details...</p>
                    </div>
                </div>

                <!-- Error State -->
                <div class="alert alert-danger" id="labourDetailsError" style="display: none;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>
                            <h6 class="mb-1">Error Loading Labour Details</h6>
                            <p class="mb-0" id="labourErrorMessage">Failed to load labour information.</p>
                        </div>
                    </div>
                </div>

                <!-- Labour Details Content -->
                <div id="labourDetailsContent" style="display: none;">
                    <div class="row">
                        <!-- Personal Information Section -->
                        <div class="col-lg-6 mb-4">
                            <div class="detail-section">
                                <h6 class="detail-section-title">
                                    <i class="fas fa-user text-primary me-2"></i>
                                    Personal Information
                                </h6>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Full Name</span>
                                        <span class="detail-value" id="viewLabourFullName">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Position</span>
                                        <span class="detail-value" id="viewLabourPosition">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Labour Type</span>
                                        <span class="detail-value" id="viewLabourType">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Join Date</span>
                                        <span class="detail-value" id="viewLabourJoinDate">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Experience</span>
                                        <span class="detail-value" id="viewLabourExperience">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Daily Salary</span>
                                        <span class="detail-value" id="viewLabourSalary">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="col-lg-6 mb-4">
                            <div class="detail-section">
                                <h6 class="detail-section-title">
                                    <i class="fas fa-phone text-success me-2"></i>
                                    Contact Information
                                </h6>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Phone Number</span>
                                        <span class="detail-value">
                                            <a href="#" id="viewLabourPhone" class="contact-link">-</a>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Alternative Number</span>
                                        <span class="detail-value">
                                            <a href="#" id="viewLabourAltPhone" class="contact-link">-</a>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Address</span>
                                        <span class="detail-value" id="viewLabourAddress">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">City</span>
                                        <span class="detail-value" id="viewLabourCity">-</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">State</span>
                                        <span class="detail-value" id="viewLabourState">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Document Information Section -->
                        <div class="col-lg-12 mb-4">
                            <div class="detail-section">
                                <h6 class="detail-section-title">
                                    <i class="fas fa-id-card text-info me-2"></i>
                                    Document Information
                                </h6>
                                <div class="document-grid">
                                    <div class="document-item">
                                        <span class="detail-label">Aadhar Card</span>
                                        <div class="document-value">
                                            <span class="document-number" id="viewLabourAadhar">-</span>
                                            <div class="document-image" id="viewLabourAadharImage">
                                                <!-- Aadhar image will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                    <div class="document-item">
                                        <span class="detail-label">PAN Card</span>
                                        <div class="document-value">
                                            <span class="document-number" id="viewLabourPAN">-</span>
                                            <div class="document-image" id="viewLabourPANImage">
                                                <!-- PAN image will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                    <div class="document-item">
                                        <span class="detail-label">Voter ID</span>
                                        <div class="document-value">
                                            <span class="document-number" id="viewLabourVoterID">-</span>
                                            <div class="document-image" id="viewLabourVoterImage">
                                                <!-- Voter ID image will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                    <div class="document-item">
                                        <span class="detail-label">Other Document</span>
                                        <div class="document-value">
                                            <span class="document-number" id="viewLabourOtherDoc">-</span>
                                            <div class="document-image" id="viewLabourOtherImage">
                                                <!-- Other document image will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="detail-section">
                                <h6 class="detail-section-title">
                                    <i class="fas fa-sticky-note text-warning me-2"></i>
                                    Additional Notes
                                </h6>
                                <div class="notes-content">
                                    <p id="viewLabourNotes" class="mb-0">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Close
                </button>
                <button type="button" class="btn btn-primary" id="editLabourFromView" data-labour-id="">
                    <i class="fas fa-edit me-2"></i>
                    Edit Labour
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Overlay -->
<div class="image-preview-overlay" id="imagePreviewOverlay">
    <div class="image-preview-content">
        <button class="image-close-btn" onclick="closeImagePreview()">&times;</button>
        <img id="previewImage" src="" alt="Document Preview">
    </div>
</div>

<style>
/* View Labour Modal Styles */
.modal-xl {
    max-width: 1200px;
}

.detail-section {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
}

.detail-section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #dee2e6;
    display: flex;
    align-items: center;
}

.detail-grid {
    display: grid;
    gap: 1rem;
}

/* Document Grid Layout */
.document-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.document-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.document-value {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.document-number {
    font-size: 0.9rem;
    color: #495057;
    font-weight: 500;
    padding: 0.5rem;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.document-image {
    position: relative;
    min-height: 120px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #fafbfc;
    transition: all 0.3s ease;
}

.document-image:hover {
    border-color: #adb5bd;
}

.document-image img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.2s ease;
}

.document-image img:hover {
    transform: scale(1.05);
}

.document-placeholder {
    text-align: center;
    color: #6c757d;
    font-size: 0.875rem;
    padding: 1rem;
}

.document-placeholder i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.image-preview-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.image-preview-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.image-preview-content img {
    width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.image-close-btn {
    position: absolute;
    top: -40px;
    right: 0;
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    padding: 0.5rem;
}

.image-close-btn:hover {
    color: #ccc;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 0.9rem;
    color: #495057;
    font-weight: 500;
}

.contact-link {
    color: #28a745;
    text-decoration: none;
    transition: color 0.2s ease;
}

.contact-link:hover {
    color: #1e7e34;
    text-decoration: underline;
}

.notes-content {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    min-height: 80px;
}

.notes-content p {
    color: #6c757d;
    font-style: italic;
    line-height: 1.5;
}

.notes-content p:not(:empty) {
    color: #495057;
    font-style: normal;
}

/* Loader Styles */
.loader-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    border-radius: 16px;
}

.loader-content {
    text-align: center;
    color: #374151;
}

.loader-content p {
    margin: 10px 0 0;
    font-weight: 500;
}

/* Status badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.active {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background-color: #f8d7da;
    color: #721c24;
}

/* Responsive design */
@media (max-width: 768px) {
    .modal-xl {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .detail-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .detail-section-title {
        font-size: 0.9rem;
    }
    
    .detail-value {
        font-size: 0.85rem;
    }
}
</style>