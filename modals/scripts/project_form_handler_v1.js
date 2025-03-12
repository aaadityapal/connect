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
    return users[userId] || 'Unknown User';
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
            <div class="title-input-container">
                <!-- Dropdown Select -->
                <div class="title-dropdown-wrapper">
                    <select id="substageTitle${stageNum}_${substageCount}" 
                            name="stages[${stageNum}][substages][${substageCount}][title]" 
                            class="title-dropdown"
                            onchange="handleTitleOptionChange(this, ${stageNum}, ${substageCount})" required>
                        <option value="">Select Title</option>
                        ${titleOptionsHtml}
                        <option value="custom">+ Add Custom Title</option>
                    </select>
                </div>
                
                <!-- Custom Title Input (Hidden by default) -->
                <div class="custom-title-wrapper" style="display: none;">
                    <div class="custom-title-input-group">
                        <button type="button" class="back-to-dropdown" 
                                onclick="switchToDropdown(${stageNum}, ${substageCount})">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <input type="text" 
                               id="customTitle${stageNum}_${substageCount}"
                               class="custom-title-input"
                               placeholder="Enter custom title"
                               onchange="updateTitleValue(${stageNum}, ${substageCount})">
                    </div>
                </div>
            </div>
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
    const stageBlock = substageBlock.closest('.stage-block');
    const stageNum = stageBlock.dataset.stage;
    const stageAssignSelect = stageBlock.querySelector(`#assignTo${stageNum}`);
    
    if (stageAssignSelect && selectElement.value && selectElement.value !== stageAssignSelect.value) {
        const assignNote = substageBlock.querySelector('.stage-assign-note');
        if (assignNote) {
        assignNote.innerHTML = `
            <i class="fas fa-info-circle"></i>
                Note: The stage is assigned to ${getUserName(stageAssignSelect.value)}
        `;
        assignNote.style.display = 'block';
        }
    } else {
        const assignNote = substageBlock.querySelector('.stage-assign-note');
        if (assignNote) {
        assignNote.style.display = 'none';
        }
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
            // Initialize autocomplete after modal is visible
            initializeProjectTitleAutocomplete();
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

    // Use the exact ID from your HTML
    const projectInput = document.querySelector('#projectTitle');
    
    if (!projectInput) {
        console.error('Project input element not found!');
        return;
    }

    console.log('Project input found:', projectInput); // Debug log

    // Listen for when a suggestion is selected
    projectInput.addEventListener('change', function(e) {
        console.log("Project selected:", e.target.value);
        if (sampleProjects[e.target.value]) {
            selectProject(e.target.value);
        }
    });

    // Optional: Listen for input changes to handle suggestions
    projectInput.addEventListener('input', function(e) {
        console.log("Input changed:", e.target.value);
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

// Add these new functions to handle title input switching
function handleTitleOptionChange(select, stageNum, substageCount) {
    if (select.value === 'custom') {
        switchToCustomInput(stageNum, substageCount);
    }
}

function switchToCustomInput(stageNum, substageCount) {
    const dropdownWrapper = document.querySelector(`#substageTitle${stageNum}_${substageCount}`).closest('.title-dropdown-wrapper');
    const customWrapper = dropdownWrapper.nextElementSibling;
    
    dropdownWrapper.style.display = 'none';
    customWrapper.style.display = 'block';
    
    // Focus the custom input
    const customInput = document.getElementById(`customTitle${stageNum}_${substageCount}`);
    customInput.focus();
}

function switchToDropdown(stageNum, substageCount) {
    const select = document.getElementById(`substageTitle${stageNum}_${substageCount}`);
    const dropdownWrapper = select.closest('.title-dropdown-wrapper');
    const customWrapper = dropdownWrapper.nextElementSibling;
    
    customWrapper.style.display = 'none';
    dropdownWrapper.style.display = 'block';
    
    // Reset the dropdown to "Select Title"
    select.value = '';
    
    // Clear the custom input
    document.getElementById(`customTitle${stageNum}_${substageCount}`).value = '';
}

function updateTitleValue(stageNum, substageCount) {
    const customInput = document.getElementById(`customTitle${stageNum}_${substageCount}`);
    const select = document.getElementById(`substageTitle${stageNum}_${substageCount}`);
    
    // Update the hidden select value to match the custom input
    if (customInput.value.trim()) {
        select.value = 'custom';
    }
}

// Add this at the top of your file - Sample project data
const sampleProjects = {
    "Modern Villa Design": {
        type: "architecture",
        category: "Residential Architecture",
        description: "Luxury villa with contemporary design elements and sustainable features",
        startDate: "2024-03-15T09:00",
        dueDate: "2024-06-15T18:00",
        assignTo: "1",
        stages: [
            {
                assignTo: "1",
                startDate: "2024-03-20T09:00",
                dueDate: "2024-04-20T18:00",
                files: ["site-plan.pdf", "initial-sketches.jpg"],
                substages: [
                    {
                        title: "Site Analysis",
                        assignTo: "2",
                        startDate: "2024-03-20T09:00",
                        dueDate: "2024-03-25T18:00",
                        files: ["site-analysis-report.pdf"]
                    },
                    {
                        title: "Concept Design",
                        assignTo: "1",
                        startDate: "2024-03-26T09:00",
                        dueDate: "2024-04-05T18:00",
                        files: ["concept-drawings.pdf"]
                    }
                ]
            },
            {
                assignTo: "3",
                startDate: "2024-04-21T09:00",
                dueDate: "2024-05-20T18:00",
                files: ["construction-docs.pdf"],
                substages: [
                    {
                        title: "Construction Documents",
                        assignTo: "3",
                        startDate: "2024-04-21T09:00",
                        dueDate: "2024-05-10T18:00",
                        files: ["detailed-drawings.pdf"]
                    }
                ]
            }
        ]
    },
    "Commercial Office Interior": {
        type: "interior",
        description: "Modern office space design with focus on productivity and employee wellness",
        category: "Commercial Architecture",
        startDate: "2024-03-15T09:00",
        dueDate: "2024-04-15T18:00",
        assignTo: "2",
        stages: [
            {
                assignTo: "2",
                startDate: "2024-03-15T09:00",
                dueDate: "2024-04-15T18:00",
                files: ["floor-plan.pdf"],
                substages: [
                    {
                        title: "Space Planning",
                        assignTo: "2",
                        startDate: "2024-03-15T09:00",
                        dueDate: "2024-03-25T18:00",
                        files: ["space-layout.pdf"]
                    }
                ]
            }
        ]
    },
    "Hospital Construction": {
        type: "construction",
        description: "New hospital building with state-of-the-art medical facilities",
        category: "Institutional Architecture",
        startDate: "2024-04-01T09:00",
        dueDate: "2024-08-30T18:00",
        assignTo: "3",
        stages: [
            {
                assignTo: "3",
                startDate: "2024-04-01T09:00",
                dueDate: "2024-08-30T18:00",
                files: ["construction-plan.pdf"],
                substages: [
                    {
                        title: "Foundation Work",
                        assignTo: "3",
                        startDate: "2024-04-01T09:00",
                        dueDate: "2024-05-15T18:00",
                        files: ["foundation-details.pdf"]
                    }
                ]
            }
        ]
    }
};

// Update the project title input in your form HTML
function updateProjectTitleInput() {
    const projectTitleContainer = document.querySelector('.form-group:first-child');
    projectTitleContainer.innerHTML = `
        <label for="projectTitle">
            <i class="fas fa-heading"></i>
            Project Title
        </label>
        <div class="autocomplete-wrapper">
            <input type="text" 
                   id="projectTitle" 
                   name="projectTitle" 
                   required 
                   placeholder="Enter project title"
                   autocomplete="off"
                   oninput="handleProjectTitleInput(this)">
            <div class="suggestions-container" id="projectSuggestions"></div>
        </div>
    `;
}

// Add these new functions
function handleProjectTitleInput(input) {
    const suggestionsContainer = document.getElementById('projectSuggestions');
    const value = input.value.trim().toLowerCase();
    
    // Clear suggestions if input is empty
    if (!value) {
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.style.display = 'none';
        return;
    }
    
    // Filter matching projects
    const matches = Object.keys(sampleProjects).filter(title => 
        title.toLowerCase().includes(value)
    );
    
    if (matches.length > 0) {
        suggestionsContainer.innerHTML = matches.map(title => `
            <div class="suggestion-item" onclick="selectProject('${title}')">
                <div class="suggestion-title">
                    <i class="fas fa-project-diagram"></i>
                    ${title}
                </div>
                <div class="suggestion-type">
                    <i class="fas fa-tag"></i>
                    ${sampleProjects[title].type}
                </div>
            </div>
        `).join('');
        suggestionsContainer.style.display = 'block';
    } else {
        suggestionsContainer.style.display = 'none';
    }
}

function selectProject(project) {
    const projectData = sampleProjects[project];
    if (!projectData) return;

    // Set the project category
    const categorySelect = document.getElementById('projectCategory');
    if (categorySelect && projectData.type) {
        console.log("Setting category to:", projectData.type);
        categorySelect.value = projectData.type;
        // Trigger change event to update any dependent fields
        categorySelect.dispatchEvent(new Event('change'));
    }

    // ... rest of your function ...
}

// Add this function to initialize autocomplete
function initializeProjectTitleAutocomplete() {
    const projectTitleInput = document.getElementById('projectTitle');
    const suggestionsContainer = document.getElementById('projectSuggestions');
    
    // Check if elements exist
    if (!projectTitleInput || !suggestionsContainer) return;

    // Remove existing listener if any
    projectTitleInput.removeEventListener('input', handleInput);
    
    // Add input event listener
    projectTitleInput.addEventListener('input', handleInput);

    // Click outside listener
    document.addEventListener('click', function(e) {
        if (suggestionsContainer && !e.target.closest('.autocomplete-wrapper')) {
            suggestionsContainer.style.display = 'none';
        }
    });
}

function handleInput(e) {
    const value = e.target.value.trim().toLowerCase();
    const suggestionsContainer = document.getElementById('projectSuggestions');
    
    if (!suggestionsContainer) return;

    // Clear suggestions if input is empty
    if (!value) {
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.style.display = 'none';
        return;
    }

    // Filter matching projects
    const matches = Object.keys(sampleProjects).filter(title => 
        title.toLowerCase().includes(value)
    );

    // Show suggestions
    if (matches.length > 0) {
        suggestionsContainer.innerHTML = matches.map(title => `
            <div class="suggestion-item" onclick="selectProject('${title.replace(/'/g, "\\'")}')">
                <div class="suggestion-title">
                    <i class="fas fa-project-diagram"></i>
                    ${highlightMatch(title, value)}
                </div>
                <div class="suggestion-type">
                    <i class="fas ${getProjectTypeIcon(sampleProjects[title].type)}"></i>
                    ${capitalizeFirstLetter(sampleProjects[title].type)}
                </div>
            </div>
        `).join('');
        suggestionsContainer.style.display = 'block';
    } else {
        suggestionsContainer.style.display = 'none';
    }
}

// Helper function to highlight matching text
function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Helper function to get project type icon
function getProjectTypeIcon(type) {
    const icons = {
        'architecture': 'fa-building',
        'interior': 'fa-couch',
        'construction': 'fa-hard-hat'
    };
    return icons[type] || 'fa-project-diagram';
}

// Helper function to capitalize first letter
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Global function for project selection
window.selectProject = function(title) {
    const project = sampleProjects[title];
    if (!project) return;
    
    // Fill basic project details
    document.getElementById('projectTitle').value = title;
    document.getElementById('projectDescription').value = project.description;
    document.getElementById('projectSuggestions').style.display = 'none';
    
    // Select project type and trigger theme change
    const typeOption = document.querySelector(`.type-option[data-type="${project.type}"]`);
    if (typeOption) {
        typeOption.click(); // This will trigger theme change and category updates
    }
    
    // Clear existing stages first
    const stagesContainer = document.getElementById('stagesContainer');
    stagesContainer.innerHTML = '';
    
    // Add and fill stages
    project.stages.forEach((stageData, index) => {
        // Click add stage button to create new stage
        document.getElementById('addStageBtn').click();
        
        const stageNum = index + 1;
        const stageBlock = stagesContainer.lastElementChild;
        
        if (stageBlock) {
            // Fill stage details
            const assignSelect = stageBlock.querySelector(`#assignTo${stageNum}`);
            const startDateInput = stageBlock.querySelector(`#startDate${stageNum}`);
            const dueDateInput = stageBlock.querySelector(`#dueDate${stageNum}`);

            if (assignSelect) assignSelect.value = stageData.assignTo;
            if (startDateInput) startDateInput.value = stageData.startDate;
            if (dueDateInput) dueDateInput.value = stageData.dueDate;

            // Add and fill substages
            if (stageData.substages && stageData.substages.length > 0) {
                stageData.substages.forEach(substageData => {
                    // Click add substage button to create new substage
                    const addSubstageBtn = stageBlock.querySelector('.add-substage-btn');
                    if (addSubstageBtn) {
                        addSubstageBtn.click();

                        // Get the newly created substage
                        const substageBlock = stageBlock.querySelector('.substages-container').lastElementChild;
                        if (substageBlock) {
            // Fill substage details
                            const titleSelect = substageBlock.querySelector('select[id^="substageTitle"]');
                            const assignSelect = substageBlock.querySelector('select[id^="substageAssignTo"]');
                            const startDateInput = substageBlock.querySelector('input[id^="substageStartDate"]');
                            const dueDateInput = substageBlock.querySelector('input[id^="substageDueDate"]');

                            // Set substage title
                            if (titleSelect) {
                                // Check if it's a custom title
                                const standardTitle = titleSelect.querySelector(`option[value="${substageData.title.toLowerCase().replace(/\s+/g, '-')}"]`);
                                
                                if (standardTitle) {
                                    titleSelect.value = substageData.title.toLowerCase().replace(/\s+/g, '-');
                                } else {
                                    // Handle custom title
                                    titleSelect.value = 'custom';
                                    const customTitleInput = substageBlock.querySelector('input[id^="customTitle"]');
                                    if (customTitleInput) {
                                        customTitleInput.value = substageData.title;
                                        // Show custom input
                                        const dropdownWrapper = titleSelect.closest('.title-dropdown-wrapper');
                                        const customWrapper = dropdownWrapper.nextElementSibling;
                                        if (dropdownWrapper && customWrapper) {
                                            dropdownWrapper.style.display = 'none';
                                            customWrapper.style.display = 'block';
                                        }
                                    }
                                }
                            }

                            // Set other substage fields
                            if (assignSelect) assignSelect.value = substageData.assignTo;
                            if (startDateInput) startDateInput.value = substageData.startDate;
                            if (dueDateInput) dueDateInput.value = substageData.dueDate;

                            // Handle substage assignment note if different from stage
                            if (substageData.assignTo !== stageData.assignTo) {
                                const assignNote = substageBlock.querySelector('.stage-assign-note');
                                if (assignNote) {
                                    assignNote.style.display = 'block';
                                }
                            }
                        }
                    }
                });
            }
        }
    });

    // Scroll to top of form
    document.querySelector('.modal-form').scrollTo({
        top: 0,
        behavior: 'smooth'
    });
};