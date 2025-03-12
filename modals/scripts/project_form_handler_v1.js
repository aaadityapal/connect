console.log('Script loaded');
console.log('Modal element:', document.getElementById('projectModal'));
console.log('Open button:', document.querySelector('.add-project-btn'));

// Define these functions in the global scope
function createSubstage(stageNum, substageNum) {
    return `
        <div class="substage-block" data-substage="${substageNum}">
            <div class="substage-header">
                <h4>Task ${substageNum}</h4>
                <button type="button" class="delete-substage" onclick="deleteSubstage(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group">
                <label for="substageTitle${stageNum}_${substageNum}">
                    <i class="fas fa-tasks"></i>
                    Substage Title
                </label>
                <select id="substageTitle${stageNum}_${substageNum}" 
                        name="stages[${stageNum}][substages][${substageNum}][title]" required>
                    <option value="">Select Title</option>
                    <option value="planning">Planning</option>
                    <option value="design">Design</option>
                    <option value="development">Development</option>
                    <option value="review">Review</option>
                </select>
            </div>
            <div class="form-group">
                <label for="substageAssignTo${stageNum}_${substageNum}">
                    <i class="fas fa-user-plus"></i>
                    Assign To
                </label>
                <select id="substageAssignTo${stageNum}_${substageNum}" 
                        name="stages[${stageNum}][substages][${substageNum}][assignTo]" required>
                    <option value="">Select Employee</option>
                    <option value="1">John Smith</option>
                    <option value="2">Sarah Johnson</option>
                    <option value="3">Mike Anderson</option>
                </select>
            </div>
            <div class="form-dates">
                <div class="form-group">
                    <label for="substageStartDate${stageNum}_${substageNum}">
                        <i class="fas fa-calendar-plus"></i>
                        Start Date & Time
                    </label>
                    <input type="datetime-local" 
                           id="substageStartDate${stageNum}_${substageNum}" 
                           name="stages[${stageNum}][substages][${substageNum}][startDate]" required>
                </div>
                <div class="form-group">
                    <label for="substageDueDate${stageNum}_${substageNum}">
                        <i class="fas fa-calendar-check"></i>
                        Due By
                    </label>
                    <input type="datetime-local" 
                           id="substageDueDate${stageNum}_${substageNum}" 
                           name="stages[${stageNum}][substages][${substageNum}][dueDate]" required>
                </div>
            </div>
            <div class="form-group">
                <label for="substageAttachFile${stageNum}_${substageNum}">
                    <i class="fas fa-paperclip"></i>
                    Attach File
                </label>
                <input type="file" 
                       id="substageAttachFile${stageNum}_${substageNum}" 
                       name="stages[${stageNum}][substages][${substageNum}][attachFile]" 
                       class="file-input">
            </div>
        </div>
    `;
}

// Define project type specific substage titles
const projectSubstageTitles = {
    architecture: [
        'Site Analysis',
        'Concept Design',
        'Schematic Design',
        'Design Development',
        'Construction Documents',
        'Building Permits',
        'Construction Administration'
    ],
    interior: [
        'Space Planning',
        'Material Selection',
        'Furniture Layout',
        'Lighting Design',
        'Color Scheme',
        'Decor Selection',
        'Installation Supervision'
    ],
    construction: [
        'Site Preparation',
        'Foundation Work',
        'Structural Work',
        'MEP Installation',
        'Interior Finishing',
        'Exterior Finishing',
        'Quality Inspection'
    ]
};

// Add a helper function to get user name by ID
function getUserName(userId) {
    const users = {
        '1': 'John Smith',
        '2': 'Sarah Johnson',
        '3': 'Mike Anderson'
    };
    return users[userId] || '';
}

function addSubstage(stageNum) {
    const stage = document.querySelector(`.stage-block[data-stage="${stageNum}"]`);
    const substagesContainer = stage.querySelector('.substages-container');
    const substageCount = substagesContainer.children.length + 1;
    
    const projectType = document.querySelector('.modal-container').dataset.theme;
    const parentAssignTo = document.getElementById(`assignTo${stageNum}`).value;
    const parentUserName = getUserName(parentAssignTo);
    const parentStartDate = document.getElementById(`startDate${stageNum}`).value;
    const parentDueDate = document.getElementById(`dueDate${stageNum}`).value;

    const titleOptions = projectSubstageTitles[projectType] || [];
    const titleOptionsHtml = titleOptions.map(title => 
        `<option value="${title.toLowerCase().replace(/\s+/g, '-')}">${title}</option>`
    ).join('');

    // Update the file attachment section in substage with unique IDs
    const substageFileSection = `
        <div class="form-group file-upload-group">
            <label>
                <i class="fas fa-paperclip"></i>
                Attach Files
            </label>
            <div class="file-upload-container" id="substageFileContainer_${stageNum}_${substageCount}">
                <div class="file-input-wrapper">
                    <input type="file" 
                           class="file-input hidden-file-input" 
                           id="substageFileInput_${stageNum}_${substageCount}"
                           onchange="handleSubstageFileSelect(this, ${stageNum}, ${substageCount})"
                           multiple>
                    <button type="button" class="add-file-btn" 
                            onclick="triggerSubstageFileInput(${stageNum}, ${substageCount})">
                        <i class="fas fa-plus"></i>
                        Add Files
                    </button>
                </div>
                <div class="selected-files" id="substageSelectedFiles_${stageNum}_${substageCount}"></div>
            </div>
        </div>
    `;

    // Create substage element
    const newSubstage = document.createElement('div');
    newSubstage.className = 'substage-block';
    newSubstage.dataset.substage = substageCount;
    newSubstage.dataset.parentAssignTo = parentAssignTo;
    newSubstage.dataset.parentUserName = parentUserName;
    newSubstage.style.opacity = '0';
    newSubstage.style.transform = 'translateY(20px)';
    
    // Add substage content including the file section
    newSubstage.innerHTML = `
        <div class="substage-header">
            <h4>Task ${substageCount}</h4>
            <button type="button" class="delete-substage" onclick="deleteSubstage(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="form-group">
            <label for="substageTitle${stageNum}_${substageCount}">
                <i class="fas fa-tasks"></i>
                Substage Title
            </label>
            <select id="substageTitle${stageNum}_${substageCount}" 
                    name="stages[${stageNum}][substages][${substageCount}][title]" required>
                <option value="">Select Title</option>
                ${titleOptionsHtml}
            </select>
        </div>
        <div class="form-group assign-to-group">
            <label for="substageAssignTo${stageNum}_${substageCount}">
                <i class="fas fa-user-plus"></i>
                Assign To
            </label>
            <select id="substageAssignTo${stageNum}_${substageCount}" 
                    name="stages[${stageNum}][substages][${substageCount}][assignTo]" 
                    onchange="handleSubstageAssignChange(this)" required>
                <option value="">Select Employee</option>
                <option value="1" ${parentAssignTo === "1" ? "selected" : ""}>John Smith</option>
                <option value="2" ${parentAssignTo === "2" ? "selected" : ""}>Sarah Johnson</option>
                <option value="3" ${parentAssignTo === "3" ? "selected" : ""}>Mike Anderson</option>
            </select>
            <div class="stage-assign-note" style="display: none;">
                <i class="fas fa-info-circle"></i>
                *The stage is assigned to ${parentUserName}
            </div>
        </div>
        <div class="form-dates">
            <div class="form-group">
                <label for="substageStartDate${stageNum}_${substageCount}">
                    <i class="fas fa-calendar-plus"></i>
                    Start Date & Time
                </label>
                <input type="datetime-local" 
                       id="substageStartDate${stageNum}_${substageCount}" 
                       name="stages[${stageNum}][substages][${substageCount}][startDate]" 
                       value="${parentStartDate}"
                       required>
            </div>
            <div class="form-group">
                <label for="substageDueDate${stageNum}_${substageCount}">
                    <i class="fas fa-calendar-check"></i>
                    Due By
                </label>
                <input type="datetime-local" 
                       id="substageDueDate${stageNum}_${substageCount}" 
                       name="stages[${stageNum}][substages][${substageCount}][dueDate]" 
                       value="${parentDueDate}"
                       required>
            </div>
        </div>
        ${substageFileSection}
    `;

    if (projectType) {
        newSubstage.classList.add(`theme-${projectType}`);
    }
    
    substagesContainer.appendChild(newSubstage);
    
    setTimeout(() => {
        newSubstage.style.opacity = '1';
        newSubstage.style.transform = 'translateY(0)';
    }, 10);

    newSubstage.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

// Add this new function to handle assignment changes
function handleSubstageAssignChange(selectElement) {
    const substageBlock = selectElement.closest('.substage-block');
    const parentAssignTo = substageBlock.dataset.parentAssignTo;
    const parentUserName = substageBlock.dataset.parentUserName;
    const assignNote = substageBlock.querySelector('.stage-assign-note');
    
    if (selectElement.value && selectElement.value !== parentAssignTo) {
        // Show informational note
        assignNote.innerHTML = `
            <i class="fas fa-info-circle"></i>
            Note: The parent stage is assigned to ${parentUserName}
        `;
        assignNote.style.display = 'block';
    } else {
        assignNote.style.display = 'none';
    }
}

function deleteSubstage(button) {
    const substage = button.closest('.substage-block');
    const substagesContainer = substage.parentElement;
    
    // Fade out animation
    substage.style.opacity = '0';
    substage.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        substage.remove();
        // Renumber remaining substages
        substagesContainer.querySelectorAll('.substage-block').forEach((block, index) => {
            const substageNum = index + 1;
            block.dataset.substage = substageNum;
            block.querySelector('h4').textContent = `Task ${substageNum}`;
        });
    }, 300);
}

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const modal = document.getElementById('projectModal');
    const modalContainer = modal.querySelector('.modal-container');
    const openModalBtn = document.querySelector('.add-project-btn');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelProject');
    const projectTypeSelect = document.getElementById('projectType');
    const projectCategorySelect = document.getElementById('projectCategory');
    const createProjectForm = document.getElementById('createProjectForm');
    const typeOptions = document.querySelectorAll('.type-option');
    const projectTypeLabel = document.querySelector('.project-type-label');
    const addStageBtn = document.getElementById('addStageBtn');
    const stagesContainer = document.getElementById('stagesContainer');
    let stageCount = 0; // Start from 0 since no stages exist initially

    // Project Categories Data
    const projectCategories = {
        architecture: [
            'Commercial Architecture',
            'Residential Architecture',
            'Industrial Architecture',
            'Institutional Architecture',
            'Landscape Architecture'
        ],
        interior: [
            'Residential Interior',
            'Commercial Interior',
            'Office Interior',
            'Retail Interior',
            'Hospitality Interior'
        ],
        construction: [
            'Residential Construction',
            'Commercial Construction',
            'Industrial Construction',
            'Infrastructure Construction',
            'Renovation'
        ]
    };

    // Event Listeners
    if (openModalBtn) {
        openModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openModal();
        });
    } else {
        console.error('Add Project button not found!');
    }

    if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
    }

    if (cancelBtn) {
    cancelBtn.addEventListener('click', closeModal);
    }

    projectTypeSelect.addEventListener('change', updateCategories);
    createProjectForm.addEventListener('submit', handleSubmit);

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal(e);
        }
    });

    // Handle project type selection
    typeOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Remove active class from all options
            typeOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to selected option
            option.classList.add('active');
            
            // Get selected type
            const selectedType = option.dataset.type;
            
            // Update hidden input
            projectTypeSelect.value = selectedType;
            
            // Update modal theme
            modalContainer.dataset.theme = selectedType;
            
            // Update project type label
            projectTypeLabel.textContent = `${selectedType.charAt(0).toUpperCase() + selectedType.slice(1)} Project`;
            
            // Update categories
            updateCategories(selectedType);
            
            // Add animation class
            modalContainer.classList.add('theme-transition');
            setTimeout(() => {
                modalContainer.classList.remove('theme-transition');
            }, 300);
        });
    });

    addStageBtn.addEventListener('click', function() {
        stageCount++;
        
        const newStage = document.createElement('div');
        newStage.className = 'stage-block';
        newStage.dataset.stage = stageCount;
        
        newStage.innerHTML = `
            <div class="stage-header">
                <h3>Stage ${stageCount}</h3>
                <button type="button" class="delete-stage" onclick="deleteStage(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="form-group">
                <label for="assignTo${stageCount}">
                    <i class="fas fa-user-plus"></i>
                    Assign To
                </label>
                <select id="assignTo${stageCount}" name="stages[${stageCount}][assignTo]" required>
                    <option value="">Select Employee</option>
                    <option value="1">John Smith</option>
                    <option value="2">Sarah Johnson</option>
                    <option value="3">Mike Anderson</option>
                </select>
            </div>
            <div class="form-dates">
                <div class="form-group">
                    <label for="startDate${stageCount}">
                        <i class="fas fa-calendar-plus"></i>
                        Start Date & Time
                    </label>
                    <input type="datetime-local" id="startDate${stageCount}" 
                           name="stages[${stageCount}][startDate]" required>
                </div>
                <div class="form-group">
                    <label for="dueDate${stageCount}">
                        <i class="fas fa-calendar-check"></i>
                        Due By
                    </label>
                    <input type="datetime-local" id="dueDate${stageCount}" 
                           name="stages[${stageCount}][dueDate]" required>
                </div>
            </div>
            <div class="form-group file-upload-group">
                <label>
                    <i class="fas fa-paperclip"></i>
                    Attach Files
                </label>
                <div class="file-upload-container" id="stageFileContainer_${stageCount}">
                    <div class="file-input-wrapper">
                        <input type="file" 
                               class="file-input hidden-file-input" 
                               id="stageFileInput_${stageCount}"
                               onchange="handleStageFileSelect(this, ${stageCount})"
                               multiple>
                        <button type="button" class="add-file-btn" 
                                onclick="triggerStageFileInput(${stageCount})">
                            <i class="fas fa-plus"></i>
                            Add Files
                        </button>
                    </div>
                    <div class="selected-files" id="stageSelectedFiles_${stageCount}"></div>
                </div>
            </div>
            <div class="substages-container">
                <!-- Substages will be added here -->
            </div>
            <button type="button" class="add-substage-btn" onclick="addSubstage(${stageCount})">
                <i class="fas fa-plus"></i>
                Add Substage
            </button>
        `;

        // Add animation class for smooth entry
        newStage.style.opacity = '0';
        newStage.style.transform = 'translateY(20px)';
        
        // Add theme-based styling
        const currentTheme = document.querySelector('.modal-container').dataset.theme;
        if (currentTheme) {
            newStage.classList.add(`theme-${currentTheme}`);
        }
        
        stagesContainer.appendChild(newStage);

        // Trigger animation
        setTimeout(() => {
            newStage.style.opacity = '1';
            newStage.style.transform = 'translateY(0)';
        }, 10);
        
        // Smooth scroll to new stage
        newStage.scrollIntoView({ behavior: 'smooth', block: 'end' });
    });

    // Functions
    function openModal() {
        console.log('Opening modal...');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        document.body.style.overflow = 'hidden';
    }

    function closeModal(e) {
        if (e) e.preventDefault();
        
        // Add closing animation
        modal.classList.remove('active');
        
        // Reset form after animation completes
        setTimeout(() => {
        modal.style.display = 'none';
        createProjectForm.reset();
            document.body.style.overflow = ''; // Restore background scrolling
            
            // Reset project type selection
            typeOptions.forEach(opt => opt.classList.remove('active'));
            modalContainer.dataset.theme = 'default';
            projectTypeLabel.textContent = 'New Project';
        projectCategorySelect.disabled = true;
            projectCategorySelect.innerHTML = '<option value="">Select Project Type First</option>';
        }, 300);
    }

    function updateCategories(selectedType) {
        projectCategorySelect.innerHTML = '<option value="">Select Category</option>';
        projectCategorySelect.disabled = !selectedType;

        if (selectedType && projectCategories[selectedType]) {
            projectCategories[selectedType].forEach(category => {
                const option = document.createElement('option');
                option.value = category.toLowerCase().replace(/\s+/g, '-');
                option.textContent = category;
                projectCategorySelect.appendChild(option);
            });
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        
        // Collect form data
        const formData = new FormData(createProjectForm);
        const projectData = Object.fromEntries(formData);

        // Here you would typically send the data to your backend
        console.log('Project Data:', projectData);

        // Show success message with theme-based styling
        showNotification('Project created successfully!', modalContainer.dataset.theme);
        closeModal();
    }

    // Show themed notification
    function showNotification(message, theme) {
        // Add notification implementation here
    }

    // Add event listener for project type changes
    typeOptions.forEach(option => {
        option.addEventListener('click', () => {
            const selectedType = option.dataset.type;
            const existingSubstages = document.querySelectorAll('.substage-block');
            
            // Update titles in existing substages
            existingSubstages.forEach(substage => {
                const titleSelect = substage.querySelector('select[id^="substageTitle"]');
                const currentValue = titleSelect.value;
                
                // Rebuild options
                const titleOptions = projectSubstageTitles[selectedType] || [];
                titleSelect.innerHTML = '<option value="">Select Title</option>' + 
                    titleOptions.map(title => 
                        `<option value="${title.toLowerCase().replace(/\s+/g, '-')}">${title}</option>`
                    ).join('');
                
                // Try to maintain selected value if it exists in new options
                if (currentValue) {
                    titleSelect.value = currentValue;
                }
            });
        });
    });
});

function deleteStage(button) {
    const stage = button.closest('.stage-block');
    const stagesLeft = document.querySelectorAll('.stage-block').length;
    
    // Fade out animation
    stage.style.opacity = '0';
    stage.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        stage.remove();
        // Renumber remaining stages
        document.querySelectorAll('.stage-block').forEach((block, index) => {
            const stageNum = index + 1;
            block.dataset.stage = stageNum;
            block.querySelector('h3').textContent = `Stage ${stageNum}`;
        });
    }, 300);
}

// Separate functions for stage and substage file handling
function triggerStageFileInput(stageNum) {
    document.getElementById(`stageFileInput_${stageNum}`).click();
}

function triggerSubstageFileInput(stageNum, substageNum) {
    document.getElementById(`substageFileInput_${stageNum}_${substageNum}`).click();
}

function handleStageFileSelect(input, stageNum) {
    const selectedFilesDiv = document.getElementById(`stageSelectedFiles_${stageNum}`);
    handleFiles(input.files, selectedFilesDiv);
    input.value = ''; // Clear input
}

function handleSubstageFileSelect(input, stageNum, substageNum) {
    const selectedFilesDiv = document.getElementById(`substageSelectedFiles_${stageNum}_${substageNum}`);
    handleFiles(input.files, selectedFilesDiv);
    input.value = ''; // Clear input
}

// Common function to handle file display
function handleFiles(files, containerDiv) {
    Array.from(files).forEach(file => {
        const fileWrapper = document.createElement('div');
        fileWrapper.className = 'file-item';
        
        const extension = file.name.split('.').pop().toLowerCase();
        const size = (file.size / (1024 * 1024)).toFixed(2);
        const icon = getFileIcon(extension);
        
        fileWrapper.innerHTML = `
            <div class="file-info">
                <i class="${icon}"></i>
                <div class="file-details">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${size} MB</span>
                </div>
            </div>
            <button type="button" class="remove-file-btn" onclick="removeFile(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        containerDiv.appendChild(fileWrapper);
    });
}

function removeFile(button) {
    const fileItem = button.closest('.file-item');
    fileItem.style.opacity = '0';
    fileItem.style.transform = 'translateX(10px)';
    
    setTimeout(() => {
        fileItem.remove();
    }, 300);
}

function getFileIcon(extension) {
    const iconMap = {
        'pdf': 'fas fa-file-pdf',
        'doc': 'fas fa-file-word',
        'docx': 'fas fa-file-word',
        'xls': 'fas fa-file-excel',
        'xlsx': 'fas fa-file-excel',
        'ppt': 'fas fa-file-powerpoint',
        'pptx': 'fas fa-file-powerpoint',
        'jpg': 'fas fa-file-image',
        'jpeg': 'fas fa-file-image',
        'png': 'fas fa-file-image',
        'gif': 'fas fa-file-image',
        'zip': 'fas fa-file-archive',
        'rar': 'fas fa-file-archive',
        'txt': 'fas fa-file-alt'
    };
    
    return iconMap[extension] || 'fas fa-file';
}