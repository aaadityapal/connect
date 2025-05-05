<?php
/**
 * Work Progress Media Template
 * 
 * Template for work progress media upload section
 * @param int $index Index of the work progress item
 */

// Default index is 0 if not provided
$index = isset($index) ? $index : 0;
?>

<div class="media-upload-container mt-3 p-3 rounded">
    <div class="media-upload-header mb-2">
        <h6><i class="fas fa-photo-video text-primary"></i> Media Files (Images/Videos)</h6>
        <p class="text-muted small">Upload images or videos showing work progress</p>
    </div>
    
    <div id="work-media-container-<?= $index ?>" class="media-container">
        <!-- Initial media upload item -->
        <div class="media-item p-2">
            <div class="custom-file-upload">
                <label class="file-label">
                    <input type="file" name="work_media_file[<?= $index ?>][0]" class="work-media-file" accept="image/*,video/*">
                    <span class="file-custom"><i class="fas fa-upload"></i> Choose Media File</span>
                </label>
            </div>
            <div class="media-preview mt-2"></div>
        </div>
    </div>
    
    <div class="text-center mt-2">
        <button type="button" class="btn btn-sm btn-outline-primary add-media-btn" onclick="addWorkMediaField(<?= $index ?>)">
            <i class="fas fa-plus-circle"></i> Add Another Media File
        </button>
    </div>
</div> 