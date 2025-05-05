/**
 * Media Upload Handling
 * 
 * Handles the file uploads for work progress and inventory items
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize work progress media upload
    initWorkProgressMedia();
    
    // Initialize inventory media upload
    initInventoryMedia();
    
    // Add work progress item button
    const addWorkBtn = document.getElementById('addWorkBtn');
    if (addWorkBtn) {
        addWorkBtn.addEventListener('click', function() {
            // Wait for DOM to update with the new work progress item
            setTimeout(function() {
                initWorkProgressMedia();
            }, 100);
        });
    }
    
    // Add inventory item button
    const addInventoryBtn = document.getElementById('addInventoryBtn');
    if (addInventoryBtn) {
        addInventoryBtn.addEventListener('click', function() {
            // Wait for DOM to update with the new inventory item
            setTimeout(function() {
                initInventoryMedia();
            }, 100);
        });
    }
});

/**
 * Initialize work progress media uploads
 */
function initWorkProgressMedia() {
    const workMediaUploads = document.querySelectorAll('.work-media-file');
    
    workMediaUploads.forEach(function(fileInput, index) {
        if (fileInput.hasAttribute('data-initialized')) {
            return; // Skip already initialized
        }
        
        const previewContainer = fileInput.closest('.media-container').querySelector('.media-preview');
        const fileLabel = fileInput.closest('.media-container').querySelector('.file-custom');
        
        fileInput.addEventListener('change', function(e) {
            handleFileSelection(e.target, previewContainer, fileLabel);
        });
        
        fileInput.setAttribute('data-initialized', 'true');
    });
}

/**
 * Initialize inventory media uploads
 */
function initInventoryMedia() {
    const inventoryMediaUploads = document.querySelectorAll('.inventory-media-file');
    
    inventoryMediaUploads.forEach(function(fileInput, index) {
        if (fileInput.hasAttribute('data-initialized')) {
            return; // Skip already initialized
        }
        
        const previewContainer = fileInput.closest('.media-container').querySelector('.media-preview');
        const fileLabel = fileInput.closest('.media-container').querySelector('.file-custom');
        
        fileInput.addEventListener('change', function(e) {
            handleFileSelection(e.target, previewContainer, fileLabel);
        });
        
        fileInput.setAttribute('data-initialized', 'true');
    });
}

/**
 * Handle file selection for preview
 */
function handleFileSelection(fileInput, previewContainer, fileLabel) {
    if (!fileInput.files || !fileInput.files[0]) {
        previewContainer.innerHTML = '';
        fileLabel.innerHTML = '<i class="fas fa-upload"></i> Choose Media File';
        return;
    }
    
    const file = fileInput.files[0];
    fileLabel.innerHTML = '<i class="fas fa-check"></i> ' + file.name;
    
    // Clear previous preview
    previewContainer.innerHTML = '';
    
    // Check if file is an image or video
    if (file.type.startsWith('image/')) {
        // Create image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-preview';
            previewContainer.appendChild(img);
        }
        reader.readAsDataURL(file);
    } else if (file.type.startsWith('video/')) {
        // Create video preview
        const video = document.createElement('video');
        video.className = 'video-preview';
        video.controls = true;
        
        const source = document.createElement('source');
        source.src = URL.createObjectURL(file);
        source.type = file.type;
        
        video.appendChild(source);
        previewContainer.appendChild(video);
    } else {
        // Generic file icon for other file types
        previewContainer.innerHTML = '<i class="fas fa-file fa-3x"></i><p>' + file.name + '</p>';
    }
}

/**
 * Add new work progress media upload field
 */
function addWorkMediaField(workIndex) {
    const container = document.querySelector(`#work-media-container-${workIndex}`);
    if (!container) return;
    
    const mediaCount = container.querySelectorAll('.media-item').length;
    const newIndex = mediaCount;
    
    const mediaItem = document.createElement('div');
    mediaItem.className = 'media-item mt-2 p-2';
    mediaItem.innerHTML = `
        <div class="custom-file-upload">
            <label class="file-label">
                <input type="file" name="work_media_file[${workIndex}][${newIndex}]" class="work-media-file" accept="image/*,video/*">
                <span class="file-custom"><i class="fas fa-upload"></i> Choose Media File</span>
            </label>
        </div>
        <div class="media-preview mt-2"></div>
    `;
    
    container.appendChild(mediaItem);
    
    // Initialize the new file input
    const fileInput = mediaItem.querySelector('.work-media-file');
    const previewContainer = mediaItem.querySelector('.media-preview');
    const fileLabel = mediaItem.querySelector('.file-custom');
    
    fileInput.addEventListener('change', function(e) {
        handleFileSelection(e.target, previewContainer, fileLabel);
    });
}

/**
 * Add new inventory media upload field
 */
function addInventoryMediaField(inventoryIndex) {
    const container = document.querySelector(`#inventory-media-container-${inventoryIndex}`);
    if (!container) return;
    
    const mediaCount = container.querySelectorAll('.media-item').length;
    const newIndex = mediaCount;
    
    const mediaItem = document.createElement('div');
    mediaItem.className = 'media-item mt-2 p-2';
    mediaItem.innerHTML = `
        <div class="custom-file-upload">
            <label class="file-label">
                <input type="file" name="inventory_media_file[${inventoryIndex}][${newIndex}]" class="inventory-media-file" accept="image/*,video/*">
                <span class="file-custom"><i class="fas fa-upload"></i> Choose Media File</span>
            </label>
        </div>
        <div class="media-preview mt-2"></div>
    `;
    
    container.appendChild(mediaItem);
    
    // Initialize the new file input
    const fileInput = mediaItem.querySelector('.inventory-media-file');
    const previewContainer = mediaItem.querySelector('.media-preview');
    const fileLabel = mediaItem.querySelector('.file-custom');
    
    fileInput.addEventListener('change', function(e) {
        handleFileSelection(e.target, previewContainer, fileLabel);
    });
} 