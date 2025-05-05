<?php
/**
 * Inventory Media Template
 * 
 * Template for inventory media upload section
 * @param int $index Index of the inventory item
 */

// Default index is 0 if not provided
$index = isset($index) ? $index : 0;
?>

<div class="media-upload-container mt-3 p-3 rounded">
    <div class="media-upload-header mb-2">
        <h6><i class="fas fa-photo-video text-success"></i> Media Files (Images/Videos)</h6>
        <p class="text-muted small">Upload images or videos of inventory items</p>
    </div>
    
    <div id="inventory-media-container-<?= $index ?>" class="media-container">
        <!-- Initial media upload item -->
        <div class="media-item p-2">
            <div class="custom-file-upload">
                <label class="file-label">
                    <input type="file" name="inventory_media_file[<?= $index ?>][0]" class="inventory-media-file" accept="image/*,video/*">
                    <span class="file-custom"><i class="fas fa-upload"></i> Choose Media File</span>
                </label>
            </div>
            <div class="media-preview mt-2"></div>
        </div>
    </div>
    
    <div class="text-center mt-2">
        <button type="button" class="btn btn-sm btn-outline-success add-inventory-media-btn" onclick="addInventoryMediaField(<?= $index ?>)">
            <i class="fas fa-plus-circle"></i> Add Another Media File
        </button>
    </div>
</div> 