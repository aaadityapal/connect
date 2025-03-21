document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const createTaskForm = document.getElementById('createProjectForm');
    const backOfficeForm = document.getElementById('backOfficeForm');
    
    // Handle form toggle
    document.querySelectorAll('input[name="taskType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            toggleForms(this.value === 'backoffice');
        });
    });

    // Handle back office form submission
    backOfficeForm.addEventListener('submit', handleBackOfficeSubmit);

    // Initialize back office form
    initializeBackOfficeForm();

    // Add stage button handler for back office form
    const backOfficeAddStageBtn = document.getElementById('backOfficeAddStageBtn');
    if (backOfficeAddStageBtn) {
        backOfficeAddStageBtn.addEventListener('click', addBackOfficeStage);
    }
});

// Add the missing functions
function populateBackOfficeUsers(users) {
    const assignSelect = document.getElementById('backOfficeAssignTo');
    if (!assignSelect) return;

    // Clear existing options
    assignSelect.innerHTML = '<option value="">Select Team Member</option>';

    // Add user options
    users.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.username} - ${user.role}`;
        assignSelect.appendChild(option);
    });
}

// Reuse the existing showNotification function from your main JS file
function showNotification(message, type = 'success') {
    // Check if container exists, if not create it
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    toast.innerHTML = `
        <i class="toast-icon fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <div class="toast-message">${message}</div>
        <div class="toast-close">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Add click handler to close button
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }
    }, 5000);
}

async function getBackOfficeFiles() {
    const fileInput = document.getElementById('backOfficeFileInput');
    if (!fileInput || !fileInput.files.length) return [];

    const files = Array.from(fileInput.files);
    const processedFiles = [];

    for (const file of files) {
        try {
            const uploadedFile = await uploadFile(file);
            processedFiles.push({
                name: file.name,
                path: uploadedFile.path || '',
                originalName: file.name,
                type: file.type,
                size: file.size
            });
        } catch (error) {
            console.error('Error processing file:', file.name, error);
        }
    }

    return processedFiles;
}

// Reuse the existing uploadFile function from your main JS file
async function uploadFile(file) {
    try {
        const formData = new FormData();
        formData.append('file', file);
        
        const response = await fetch('api/upload_files.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to upload file');
        }
        
        return {
            path: result.file_path,
            status: 'success'
        };
    } catch (error) {
        console.error('Error uploading file:', error);
        return {
            path: '',
            status: 'error',
            error: error.message
        };
    }
}

function closeModal() {
    const modal = document.getElementById('projectModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            // Reset forms
            document.querySelector('.modal-form').reset();
            document.querySelector('.back-office-form').reset();
        }, 300);
    }
}

async function initializeBackOfficeForm() {
    try {
        // Fetch and populate team members
        const users = await fetchUsers(); // This function should be available from your main JS
        populateBackOfficeUsers(users);

        // Initialize file upload
        initializeBackOfficeFileUpload();

    } catch (error) {
        console.error('Error initializing back office form:', error);
        showNotification('Error loading form data', 'error');
    }
}

function initializeBackOfficeFileUpload() {
    const fileInput = document.getElementById('backOfficeFileInput');
    const addFileBtn = document.querySelector('.back-office-form .add-file-btn');
    const selectedFilesContainer = document.getElementById('backOfficeSelectedFiles');

    if (addFileBtn && fileInput) {
        addFileBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleBackOfficeFileSelect);
    }
}

function handleBackOfficeFileSelect(e) {
    const files = Array.from(e.target.files);
    const container = document.getElementById('backOfficeSelectedFiles');
    
    if (container) {
        files.forEach(file => {
            displayBackOfficeFile(file, container);
        });
    }
}

function displayBackOfficeFile(file, container) {
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
        <button type="button" class="remove-file-btn" onclick="removeBackOfficeFile(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(fileWrapper);
}

function removeBackOfficeFile(button) {
    const fileItem = button.closest('.file-item');
    if (fileItem) {
        fileItem.style.opacity = '0';
        fileItem.style.transform = 'translateX(10px)';
        
        setTimeout(() => {
            fileItem.remove();
        }, 300);
    }
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

async function handleBackOfficeSubmit(e) {
    e.preventDefault();
    
    try {
        const formData = new FormData(e.target);
        const files = await getBackOfficeFiles();
        
        const taskData = {
            title: formData.get('backOfficeTitle'),
            description: formData.get('backOfficeDescription'),
            category: formData.get('backOfficeCategory'),
            priority: formData.get('backOfficePriority'),
            startDate: formData.get('backOfficeStartDate'),
            dueDate: formData.get('backOfficeDueDate'),
            assignTo: formData.get('backOfficeAssignTo'),
            files: files
        };

        const response = await fetch('api/create_back_office_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(taskData)
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('Back office task created successfully!', 'success');
            closeModal();
        } else {
            throw new Error(result.message || 'Failed to create task');
        }

    } catch (error) {
        console.error('Error creating back office task:', error);
        showNotification(error.message, 'error');
    }
}

function toggleForms(showBackOffice) {
    const createTaskForm = document.getElementById('createProjectForm');
    const backOfficeForm = document.getElementById('backOfficeForm');
    
    if (showBackOffice) {
        // Hide project form
        createTaskForm.style.opacity = '0';
        setTimeout(() => {
            createTaskForm.style.display = 'none';
            // Show back office form
            backOfficeForm.style.display = 'block';
            setTimeout(() => {
                backOfficeForm.style.opacity = '1';
            }, 50);
        }, 300);
    } else {
        // Hide back office form
        backOfficeForm.style.opacity = '0';
        setTimeout(() => {
            backOfficeForm.style.display = 'none';
            // Show project form
            createTaskForm.style.display = 'block';
            setTimeout(() => {
                createTaskForm.style.opacity = '1';
            }, 50);
        }, 300);
    }
}

let backOfficeStageCount = 0;

function addBackOfficeStage() {
    backOfficeStageCount++;
    
    const stagesContainer = document.getElementById('backOfficeStagesContainer');
    const newStage = document.createElement('div');
    newStage.className = 'stage-block back-office-stage';
    newStage.dataset.stage = backOfficeStageCount;
    
    // Create user options HTML using global users
    const userOptionsHtml = globalUsers.map(user => 
        `<option value="${user.id}">${user.username} - ${user.role}</option>`
    ).join('');
    
    newStage.innerHTML = `
        <div class="stage-header">
            <h3>Stage ${backOfficeStageCount}</h3>
            <button type="button" class="delete-stage" onclick="deleteBackOfficeStage(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="form-group">
            <label for="backOfficeStageTitle${backOfficeStageCount}">
                <i class="fas fa-heading"></i>
                Stage Title
            </label>
            <input type="text" 
                   id="backOfficeStageTitle${backOfficeStageCount}" 
                   name="backOfficeStages[${backOfficeStageCount}][title]" 
                   required 
                   placeholder="Enter stage title">
        </div>
        <div class="form-group">
            <label for="backOfficeStageAssignTo${backOfficeStageCount}">
                <i class="fas fa-user-plus"></i>
                Assign To
            </label>
            <select id="backOfficeStageAssignTo${backOfficeStageCount}" 
                    name="backOfficeStages[${backOfficeStageCount}][assignTo]" 
                    required>
                <option value="">Select Team Member</option>
                ${userOptionsHtml}
            </select>
        </div>
        <div class="form-dates">
            <div class="form-group">
                <label for="backOfficeStageStartDate${backOfficeStageCount}">
                    <i class="fas fa-calendar-plus"></i>
                    Start Date
                </label>
                <input type="datetime-local" 
                       id="backOfficeStageStartDate${backOfficeStageCount}" 
                       name="backOfficeStages[${backOfficeStageCount}][startDate]" 
                       required>
            </div>
            <div class="form-group">
                <label for="backOfficeStageDueDate${backOfficeStageCount}">
                    <i class="fas fa-calendar-check"></i>
                    Due Date
                </label>
                <input type="datetime-local" 
                       id="backOfficeStageDueDate${backOfficeStageCount}" 
                       name="backOfficeStages[${backOfficeStageCount}][dueDate]" 
                       required>
            </div>
        </div>
        <div class="form-group file-upload-group">
            <label>
                <i class="fas fa-paperclip"></i>
                Attach Files
            </label>
            <div class="file-upload-container" id="backOfficeStageFileContainer_${backOfficeStageCount}">
                <div class="file-input-wrapper">
                    <input type="file" 
                           class="file-input hidden-file-input" 
                           id="backOfficeStageFileInput_${backOfficeStageCount}"
                           onchange="handleBackOfficeStageFileSelect(this, ${backOfficeStageCount})"
                           multiple>
                    <button type="button" class="add-file-btn" 
                            onclick="triggerBackOfficeStageFileInput(${backOfficeStageCount})">
                        <i class="fas fa-plus"></i>
                        Add Files
                    </button>
                </div>
                <div class="selected-files" id="backOfficeStageSelectedFiles_${backOfficeStageCount}"></div>
            </div>
        </div>
        <div class="substages-container" id="backOfficeSubstagesContainer${backOfficeStageCount}">
            <!-- Substages will be added here -->
        </div>
        <button type="button" class="add-substage-btn" onclick="addBackOfficeSubstage(${backOfficeStageCount})">
            <i class="fas fa-plus"></i>
            Add Task
        </button>
    `;

    stagesContainer.appendChild(newStage);
    
    // Add animation
    newStage.style.opacity = '0';
    newStage.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        newStage.style.opacity = '1';
        newStage.style.transform = 'translateY(0)';
    }, 10);

    newStage.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function addBackOfficeSubstage(stageNum) {
    const stage = document.querySelector(`.back-office-stage[data-stage="${stageNum}"]`);
    const substagesContainer = stage.querySelector('.substages-container');
    const substageCount = substagesContainer.children.length + 1;
    
    const userOptionsHtml = globalUsers.map(user => 
        `<option value="${user.id}">${user.username} - ${user.role}</option>`
    ).join('');

    const newSubstage = document.createElement('div');
    newSubstage.className = 'substage-block';
    newSubstage.dataset.substage = substageCount;
    
    const substageContent = `
        <div class="substage-header">
            <h4>Task ${substageCount}</h4>
            <button type="button" class="delete-substage" onclick="deleteBackOfficeSubstage(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="form-group">
            <label for="backOfficeSubstageTitle${stageNum}_${substageCount}">
                <i class="fas fa-tasks"></i>
                Task Title
            </label>
            <input type="text" 
                   id="backOfficeSubstageTitle${stageNum}_${substageCount}" 
                   name="backOfficeStages[${stageNum}][substages][${substageCount}][title]" 
                   required 
                   placeholder="Enter task title">
        </div>
        <div class="form-group">
            <label for="backOfficeSubstageAssignTo${stageNum}_${substageCount}">
                <i class="fas fa-user-plus"></i>
                Assign To
            </label>
            <select id="backOfficeSubstageAssignTo${stageNum}_${substageCount}" 
                    name="backOfficeStages[${stageNum}][substages][${substageCount}][assignTo]" 
                    required>
                <option value="">Select Team Member</option>
                ${userOptionsHtml}
            </select>
        </div>
        <div class="form-dates">
            <div class="form-group">
                <label for="backOfficeSubstageStartDate${stageNum}_${substageCount}">
                    <i class="fas fa-calendar-plus"></i>
                    Start Date
                </label>
                <input type="datetime-local" 
                       id="backOfficeSubstageStartDate${stageNum}_${substageCount}" 
                       name="backOfficeStages[${stageNum}][substages][${substageCount}][startDate]" 
                       required>
            </div>
            <div class="form-group">
                <label for="backOfficeSubstageDueDate${stageNum}_${substageCount}">
                    <i class="fas fa-calendar-check"></i>
                    Due Date
                </label>
                <input type="datetime-local" 
                       id="backOfficeSubstageDueDate${stageNum}_${substageCount}" 
                       name="backOfficeStages[${stageNum}][substages][${substageCount}][dueDate]" 
                       required>
            </div>
        </div>
        <div class="form-group file-upload-group">
            <label>
                <i class="fas fa-paperclip"></i>
                Attach Files
            </label>
            <div class="file-upload-container" id="backOfficeSubstageFileContainer_${stageNum}_${substageCount}">
                <div class="file-input-wrapper">
                    <input type="file" 
                           class="file-input hidden-file-input" 
                           id="backOfficeSubstageFileInput_${stageNum}_${substageCount}"
                           onchange="handleBackOfficeSubstageFileSelect(this, ${stageNum}, ${substageCount})"
                           multiple>
                    <button type="button" class="add-file-btn" 
                            onclick="triggerBackOfficeSubstageFileInput(${stageNum}, ${substageCount})">
                        <i class="fas fa-plus"></i>
                        Add Files
                    </button>
                </div>
                <div class="selected-files" id="backOfficeSubstageSelectedFiles_${stageNum}_${substageCount}"></div>
            </div>
        </div>
    `;

    newSubstage.innerHTML = substageContent;

    substagesContainer.appendChild(newSubstage);
    
    // Add animation
    newSubstage.style.opacity = '0';
    newSubstage.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        newSubstage.style.opacity = '1';
        newSubstage.style.transform = 'translateY(0)';
    }, 10);

    newSubstage.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function deleteBackOfficeStage(button) {
    const stage = button.closest('.stage-block');
    
    // Fade out animation
    stage.style.opacity = '0';
    stage.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        stage.remove();
        // Renumber remaining stages
        document.querySelectorAll('.back-office-stage').forEach((block, index) => {
            const stageNum = index + 1;
            block.dataset.stage = stageNum;
            block.querySelector('h3').textContent = `Stage ${stageNum}`;
            updateBackOfficeStageElements(block, stageNum);
        });
    }, 300);
}

function deleteBackOfficeSubstage(button) {
    const substage = button.closest('.substage-block');
    const substagesContainer = substage.parentElement;
    const stageBlock = substagesContainer.closest('.stage-block');
    
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
            updateBackOfficeSubstageElements(block, stageBlock.dataset.stage, substageNum);
        });
    }, 300);
}

function updateBackOfficeStageElements(stageBlock, stageNum) {
    // Update IDs and names of stage elements
    const elements = stageBlock.querySelectorAll('[id^="backOfficeStage"]');
    elements.forEach(element => {
        const oldId = element.id;
        element.id = oldId.replace(/\d+/, stageNum);
        if (element.name) {
            element.name = element.name.replace(/\[\d+\]/, `[${stageNum}]`);
        }
    });
}

function updateBackOfficeSubstageElements(substageBlock, stageNum, substageNum) {
    // Update IDs and names of substage elements
    const elements = substageBlock.querySelectorAll('[id^="backOfficeSubstage"]');
    elements.forEach(element => {
        const oldId = element.id;
        element.id = oldId.replace(/\d+_\d+/, `${stageNum}_${substageNum}`);
        if (element.name) {
            element.name = element.name.replace(/\[\d+\]\[substages\]\[\d+\]/, 
                `[${stageNum}][substages][${substageNum}]`);
        }
    });
}

function triggerBackOfficeStageFileInput(stageNum) {
    document.getElementById(`backOfficeStageFileInput_${stageNum}`).click();
}

function triggerBackOfficeSubstageFileInput(stageNum, substageNum) {
    document.getElementById(`backOfficeSubstageFileInput_${stageNum}_${substageNum}`).click();
}

function handleBackOfficeStageFileSelect(input, stageNum) {
    const selectedFilesDiv = document.getElementById(`backOfficeStageSelectedFiles_${stageNum}`);
    handleBackOfficeFiles(input.files, selectedFilesDiv);
    input.value = ''; // Clear input
}

function handleBackOfficeSubstageFileSelect(input, stageNum, substageNum) {
    const selectedFilesDiv = document.getElementById(`backOfficeSubstageSelectedFiles_${stageNum}_${substageNum}`);
    handleBackOfficeFiles(input.files, selectedFilesDiv);
    input.value = ''; // Clear input
}

function handleBackOfficeFiles(files, containerDiv) {
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
            <button type="button" class="remove-file-btn" onclick="removeBackOfficeFile(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        containerDiv.appendChild(fileWrapper);
    });
} 