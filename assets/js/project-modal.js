const projectTypes = {
    architecture: [
        'Residential Architecture',
        'Commercial Architecture',
        'Industrial Architecture',
        'Institutional Architecture',
        'Heritage Conservation',
        'Landscape Architecture',
        'Urban Design',
        'Interior Architecture'
    ],
    interior: [
        'Residential Interior',
        'Commercial Interior',
        'Retail Interior',
        'Office Interior',
        'Restaurant Interior',
        'Hotel Interior',
        'Healthcare Interior',
        'Educational Interior'
    ],
    construction: [
        'Residential Construction',
        'Commercial Construction',
        'Industrial Construction',
        'Infrastructure Development',
        'Renovation',
        'Site Development',
        'High-rise Construction',
        'Green Building'
    ]
};

// Update the substage types with grouped options
const substageTypes = {
    architecture: {
        'Planning & Design': [
            'Design Development',
            'Site Analysis',
            'Space Planning',
            'Concept Design'
        ],
        'Documentation': [
            'Construction Documentation',
            'Technical Drawings',
            'Permit Documentation',
            'Detail Drawings'
        ],
        'Material & Cost': [
            'Material Selection',
            'Cost Estimation',
            'Specification Writing',
            'Quantity Takeoff'
        ],
        'Review & Approval': [
            'Client Review',
            'Authority Approval',
            'Design Review',
            'Quality Check'
        ]
    },
    interior: {
        'Design Phase': [
            'Space Planning',
            'Concept Development',
            'Design Development',
            'Mood Board Creation'
        ],
        'Material & Finishes': [
            'Material Selection',
            'Color Scheme',
            'Texture Selection',
            'Finish Schedule'
        ],
        'Technical Planning': [
            'Lighting Design',
            'Furniture Layout',
            'Detail Drawing',
            'MEP Coordination'
        ],
        'Execution': [
            'Installation Supervision',
            'Site Coordination',
            'Quality Control',
            'Final Inspection'
        ]
    },
    construction: {
        'Pre-Construction': [
            'Site Preparation',
            'Resource Planning',
            'Mobilization Plan',
            'Safety Setup'
        ],
        'Structure Work': [
            'Foundation Work',
            'Structural Work',
            'Waterproofing',
            'Roof Installation'
        ],
        'Services': [
            'MEP Installation',
            'HVAC Setup',
            'Plumbing Work',
            'Electrical Work'
        ],
        'Finishing': [
            'Finishing Work',
            'Quality Inspection',
            'Safety Audit',
            'Handover Preparation'
        ]
    }
};

const sampleProjects = [
    {
        id: 1,
        title: 'Modern Office Complex',
        description: 'A contemporary office building with sustainable features',
        category: 'architecture',
        type: 'commercial-architecture',
        startDate: '2024-03-20',
        dueDate: '2024-09-20',
        assignTo: '2',
        stages: [
            {
                title: 'Planning Phase',
                assignTo: '2',
                startDate: '2024-03-20T09:00',
                dueDate: '2024-04-20T18:00',
                attachments: [],
                substages: [
                    {
                        title: 'Site Analysis',
                        assignTo: '2',
                        startDate: '2024-03-20T09:00',
                        dueDate: '2024-03-30T18:00',
                        attachments: []
                    }
                ]
            }
        ]
    }
    // Add more sample projects as needed
];

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('addProjectModal');
    const addProjectBtn = document.querySelector('.add-project');
    const closeBtn = document.querySelector('[data-close-modal="addProject"]');
    const cancelBtn = document.querySelector('.cancel-modal');
    const projectCategorySelect = document.getElementById('projectCategory');
    const projectTypeSelect = document.getElementById('projectType');
    const addProjectForm = document.getElementById('addProjectForm');
    const modalContent = document.querySelector('[data-modal="addProject"]');
    const addStageBtn = document.getElementById('addStageBtn');
    const stagesWrapper = document.getElementById('stagesWrapper');
    let stageCount = 0;

    // Open modal
    addProjectBtn.addEventListener('click', openModal);

    // Close modal only when clicking the close button
    closeBtn.addEventListener('click', closeModal);

    // Prevent modal from closing when clicking inside the modal content
    modalContent.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Prevent modal from closing when clicking the modal backdrop
    modal.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    function openModal() {
        const modal = document.getElementById('addProjectModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Add blur class to main content
        document.querySelector('.main-content').style.filter = 'blur(5px)';
        document.querySelector('.sidebar').style.filter = 'blur(5px)';
        
        // Load users and categories
        loadUsers();
        loadProjectCategories();
    }

    function closeModal() {
        const modal = document.getElementById('addProjectModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore background scrolling
        
        // Remove blur from main content
        document.querySelector('.main-content').style.filter = '';
        document.querySelector('.sidebar').style.filter = '';
        
        // Reset form and other states
        const modalContent = document.querySelector('.modal-content');
        modalContent.classList.remove('architecture', 'interior', 'construction');
        document.getElementById('addProjectForm').reset();
        document.getElementById('projectType').disabled = true;
        
        // Reset header styles
        document.querySelector('.modal-header h2').style.color = '#333';
        
        // Reset Create Project button color
        const submitButton = document.querySelector('.btn-primary');
        submitButton.style.backgroundColor = '';
        
        // Reset category indicator
        const categoryIndicator = document.querySelector('.category-indicator');
        if (categoryIndicator) {
            categoryIndicator.style.display = 'none';
        }
    }

    cancelBtn.addEventListener('click', closeModal);

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Add event listener for category change
    projectCategorySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const categoryId = selectedOption.dataset.id;
        const projectTypeSelect = document.getElementById('projectType');
        
        // Clear existing options
        projectTypeSelect.innerHTML = '<option value="">Select Category</option>';
        
        if (categoryId && window.projectSubcategories[categoryId]) {
            projectTypeSelect.disabled = false;
            
            // Add subcategories as options
            window.projectSubcategories[categoryId].forEach(subCategory => {
                const option = document.createElement('option');
                option.value = subCategory.id; // Use the actual category ID
                option.textContent = subCategory.name;
                option.title = subCategory.description;
                projectTypeSelect.appendChild(option);
            });
        } else {
            projectTypeSelect.disabled = true;
        }

        // Update modal styling
        const modalContent = document.querySelector('.modal-content');
        modalContent.classList.remove('architecture', 'interior', 'construction');
        if (this.value) {
            modalContent.classList.add(this.value);
        }
    });

    // Initialize project type as disabled
    projectTypeSelect.disabled = true;

    // Update the form submission handler
    addProjectForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('Form submission started...');
        
        try {
            // Get all stage containers
            const stagesWrapper = document.getElementById('stagesWrapper');
            const stageElements = stagesWrapper.querySelectorAll('.stage-card:not(.substage)');
            console.log('Found stages:', stageElements.length);

            // Collect form data
            const formData = {
                projectTitle: document.getElementById('projectTitle').value,
                projectDescription: document.getElementById('projectDescription').value,
                projectType: document.getElementById('projectCategory').value,
                category_id: parseInt(document.getElementById('projectType').value, 10),
                startDate: formatDate(document.getElementById('startDate').value),
                dueDate: formatDate(document.getElementById('dueDate').value),
                assignTo: parseInt(document.getElementById('assignTo').value, 10),
                stages: []
            };

            console.log('Basic form data collected:', formData);

            // Collect stages data
            stageElements.forEach((stage, index) => {
                const stageData = {
                    assignTo: parseInt(stage.querySelector('select[name="assign_to"]').value, 10),
                    startDate: formatDate(stage.querySelector('input[name="start_date"]').value),
                    endDate: formatDate(stage.querySelector('input[name="due_date"]').value),
                    substages: []
                };

                // Get substages for this stage
                const substagesWrapper = stage.querySelector('.substages-wrapper');
                const substageElements = substagesWrapper.querySelectorAll('.substage');

                console.log(`Processing stage ${index + 1}, found ${substageElements.length} substages`);

                // Collect substages data
                substageElements.forEach((substage, subIndex) => {
                    const substageData = {
                        title: substage.querySelector('.substage-title').value,
                        assignTo: parseInt(substage.querySelector('.substage-assign').value, 10),
                        startDate: formatDate(substage.querySelector('.substage-start').value),
                        endDate: formatDate(substage.querySelector('.substage-due').value)
                    };
                    stageData.substages.push(substageData);
                    console.log(`Added substage ${subIndex + 1} to stage ${index + 1}`);
                });

                formData.stages.push(stageData);
                console.log(`Added stage ${index + 1} to form data`);
            });

            console.log('Final form data:', formData);

            // Send data to server
            const response = await fetch('api/create_project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            console.log('Server response:', result);

            if (result.status === 'success') {
                alert('Project created successfully!');
                closeModal();
                // Reload projects if needed
                if (typeof loadProjects === 'function') {
                    loadProjects();
                }
            } else {
                throw new Error(result.message || 'Failed to create project');
            }

        } catch (error) {
            console.error('Form submission error:', error);
            alert('Error creating project: ' + error.message);
        }
    });

    // Helper function to format date
    function formatDate(dateString) {
        if (!dateString) return null;
        const date = new Date(dateString);
        return date.toISOString().slice(0, 19).replace('T', ' ');
    }

    // Validate dates
    const startDateInput = document.getElementById('startDate');
    const dueDateInput = document.getElementById('dueDate');

    startDateInput.addEventListener('change', function() {
        dueDateInput.min = this.value;
    });

    dueDateInput.addEventListener('change', function() {
        if (startDateInput.value && this.value < startDateInput.value) {
            this.value = startDateInput.value;
        }
    });

    // Remove any existing keydown listeners
    document.removeEventListener('keydown', handleEscape);
    
    // Prevent Escape key from closing the modal
    function handleEscape(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
        }
    }
    
    // Add the new escape key handler
    document.addEventListener('keydown', handleEscape);

    // Replace onclick with event listener
    document.getElementById('addStageBtn').addEventListener('click', function() {
        stageCount++;
        addNewStage(stageCount);
    });

    function addNewStage(stageNum) {
        const stageCard = document.createElement('div');
        stageCard.className = 'stage-card';
        stageCard.id = `stage-${stageNum}`;
        
        // Add data attribute to identify as stage
        stageCard.dataset.type = 'stage';

        stageCard.innerHTML = `
            <div class="stage-header">
                <h3 class="stage-title">Stage ${stageNum}</h3>
                <button type="button" class="delete-stage" onclick="deleteStage(${stageNum})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stage-form-group">
                <label>Stage Title</label>
                <input type="text" class="form-control stage-title-input" 
                       name="stage_title" 
                       value="Stage ${stageNum}" 
                       placeholder="Enter stage title">
            </div>
            <div class="stage-form-group">
                <label>Assign To</label>
                <select class="form-control" name="assign_to" required>
                    ${document.getElementById('assignTo').innerHTML}
                </select>
            </div>
            <div class="stage-form-row">
                <div class="stage-form-group">
                    <label>Start Date & Time</label>
                    <input type="datetime-local" class="form-control" name="start_date" required>
                </div>
                <div class="stage-form-group">
                    <label>Due By</label>
                    <input type="datetime-local" class="form-control" name="due_date" required>
                </div>
            </div>
            <div class="substages-wrapper" id="substages-${stageNum}"></div>
            <button type="button" class="add-substage-btn" onclick="addSubstage(${stageNum})">
                <i class="fas fa-plus"></i> Add Substage
            </button>
        `;

        document.getElementById('stagesWrapper').appendChild(stageCard);
        console.log(`Added new stage ${stageNum}`);

        // Initialize date inputs with project dates
        const projectStartDate = document.getElementById('startDate').value;
        const projectDueDate = document.getElementById('dueDate').value;
        if (projectStartDate) {
            stageCard.querySelector('input[name="start_date"]').value = projectStartDate;
        }
        if (projectDueDate) {
            stageCard.querySelector('input[name="due_date"]').value = projectDueDate;
        }

        return stageCard;
    }

    // Make sure addStage is globally available
    window.addStage = function() {
        stageCount++;
        addNewStage(stageCount);
    };

    // Update deleteStage function
    window.deleteStage = function(stageNum) {
        const stage = document.getElementById(`stage-${stageNum}`);
        if (stage) {
            stage.remove();
            console.log(`Deleted stage ${stageNum}`);
            
            // Renumber remaining stages
            const stages = document.querySelectorAll('.stage-card:not(.substage)');
            stages.forEach((stage, index) => {
                const newStageNum = index + 1;
                stage.id = `stage-${newStageNum}`;
                stage.querySelector('.stage-title').textContent = `Stage ${newStageNum}`;
                stage.querySelector('.substages-wrapper').id = `substages-${newStageNum}`;
                
                // Update substage button onclick
                const substageBtn = stage.querySelector('.add-substage-btn');
                substageBtn.setAttribute('onclick', `addSubstage(${newStageNum})`);
                
                // Update delete button onclick
                const deleteBtn = stage.querySelector('.delete-stage');
                deleteBtn.setAttribute('onclick', `deleteStage(${newStageNum})`);
            });
            
            stageCount = stages.length;
            console.log(`Updated stage count: ${stageCount}`);
        }
    };

    // Update the add substage function
    window.addSubstage = function(stageNum) {
        const parentStage = document.getElementById(`stage-${stageNum}`);
        const substagesWrapper = document.getElementById(`substages-${stageNum}`);
        const substageCount = substagesWrapper.children.length + 1;
        
        // Get parent stage's values
        const parentAssignSelect = parentStage.querySelector('select[name="assign_to"]');
        const parentAssignTo = parentAssignSelect.value;
        const parentAssignText = parentAssignSelect.options[parentAssignSelect.selectedIndex].text;
        
        // Get parent stage's dates
        const parentStartDate = parentStage.querySelector('input[name="start_date"]').value;
        const parentDueDate = parentStage.querySelector('input[name="due_date"]').value;

        // Validate parent dates
        if (!parentStartDate || !parentDueDate) {
            alert('Please set the stage dates before adding substages');
            return;
        }
        
        const substageElement = document.createElement('div');
        substageElement.className = 'stage-card substage';
        
        // Create grouped substage title options
        let substageOptions = '<option value="">Select Substage Title</option>';
        const projectCategory = document.getElementById('projectCategory').value;
        if (projectCategory && substageTypes[projectCategory]) {
            Object.entries(substageTypes[projectCategory]).forEach(([group, options]) => {
                substageOptions += `
                    <optgroup label="${group}">
                        ${options.map(title => 
                            `<option value="${title.toLowerCase().replace(/\s+/g, '-')}">${title}</option>`
                        ).join('')}
                    </optgroup>
                `;
            });
        }

        // Update the substageElement innerHTML
        substageElement.innerHTML = `
            <div class="stage-header">
                <h4 class="stage-title">Substage ${substageCount}</h4>
                <button type="button" class="delete-stage" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stage-form-group">
                <label>Substage Title</label>
                <select class="form-control substage-title" name="substage_title" required>
                    ${substageOptions}
                </select>
            </div>
            <div class="stage-form-group">
                <label>Assign To</label>
                <select class="form-control substage-assign" name="substage_assign_to" required>
                    ${document.getElementById('assignTo').innerHTML}
                </select>
                <small class="assign-hint" style="display: none; color: #666; font-size: 11px; margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> Originally assigned to: <span class="original-assignee">${parentAssignText}</span>
                </small>
            </div>
            <div class="stage-form-row">
                <div class="stage-form-group">
                    <label>Start Date & Time</label>
                    <input type="datetime-local" class="form-control substage-start" 
                        name="substage_start_date"
                        required 
                        value="${parentStartDate}" 
                        min="${parentStartDate}" 
                        max="${parentDueDate}">
                </div>
                <div class="stage-form-group">
                    <label>Due By</label>
                    <input type="datetime-local" class="form-control substage-due" 
                        name="substage_due_date"
                        required 
                        value="${parentDueDate}" 
                        min="${parentStartDate}" 
                        max="${parentDueDate}">
                </div>
            </div>
            <div class="file-attachment-section">
                <label>Attachments</label>
                <div class="file-input-wrapper">
                    <input type="file" class="file-input" multiple style="display: none;">
                    <button type="button" class="attach-file-btn">
                        <i class="fas fa-paperclip"></i> Attach Files
                    </button>
                </div>
                <div class="attached-files-list"></div>
            </div>
        `;
        
        substagesWrapper.appendChild(substageElement);

        // Set the substage assign value to match parent stage
        const substageAssign = substageElement.querySelector('.substage-assign');
        substageAssign.value = parentAssignTo;

        // Add change event listener for assignment changes
        substageAssign.addEventListener('change', function() {
            const assignHint = substageElement.querySelector('.assign-hint');
            if (this.value !== parentAssignTo) {
                assignHint.style.display = 'block';
            } else {
                assignHint.style.display = 'none';
            }
        });

        // Add CSS class to update substage header based on selected title
        const substageTitleSelect = substageElement.querySelector('.substage-title');
        substageTitleSelect.addEventListener('change', function() {
            const substageHeader = this.closest('.stage-card').querySelector('.stage-title');
            if (this.value) {
                const selectedText = this.options[this.selectedIndex].text;
                substageHeader.textContent = `Substage ${substageCount} (${selectedText})`;
            } else {
                substageHeader.textContent = `Substage ${substageCount}`;
            }
        });

        // Setup date validation for the substage
        setupSubstageDateValidation(substageElement, parentStartDate, parentDueDate);

        // Add file attachment functionality
        setupFileAttachment(substageElement);

        // Add event listener to parent stage date changes
        const parentStartInput = parentStage.querySelector('input[name="start_date"]');
        const parentDueInput = parentStage.querySelector('input[name="due_date"]');
        
        parentStartInput.addEventListener('change', function() {
            const substageStart = substageElement.querySelector('.substage-start');
            const substageDue = substageElement.querySelector('.substage-due');
            substageStart.min = this.value;
            substageDue.min = this.value;
            if (substageStart.value < this.value) {
                substageStart.value = this.value;
            }
        });

        parentDueInput.addEventListener('change', function() {
            const substageStart = substageElement.querySelector('.substage-start');
            const substageDue = substageElement.querySelector('.substage-due');
            substageStart.max = this.value;
            substageDue.max = this.value;
            if (substageDue.value > this.value) {
                substageDue.value = this.value;
            }
        });
    };

    // Add this function to handle date validation for substages
    function setupSubstageDateValidation(substageElement, parentStartDate, parentDueDate) {
        const startInput = substageElement.querySelector('.substage-start');
        const dueInput = substageElement.querySelector('.substage-due');

        // Validate start date
        startInput.addEventListener('change', function() {
            if (this.value < parentStartDate) {
                alert("Substage start date cannot be earlier than the main stage start date");
                this.value = parentStartDate;
            }
            if (this.value > dueInput.value) {
                dueInput.value = this.value;
            }
        });

        // Validate due date
        dueInput.addEventListener('change', function() {
            if (this.value > parentDueDate) {
                alert("Substage due date cannot be later than the main stage due date");
                this.value = parentDueDate;
            }
            if (this.value < startInput.value) {
                startInput.value = this.value;
            }
        });
    }

    // Function to apply category-specific styles to stages
    function applyStageStyles(stageCard, category) {
        const colors = {
            architecture: {
                bg: '#fff5f5',
                border: '#ffd5d5'
            },
            interior: {
                bg: '#f3f0ff',
                border: '#e5dbff'
            },
            construction: {
                bg: '#fff9db',
                border: '#ffe066'
            }
        };

        if (colors[category]) {
            stageCard.style.backgroundColor = colors[category].bg;
            stageCard.style.borderColor = colors[category].border;
        }
    }

    // Update stage styles when category changes
    document.getElementById('projectCategory').addEventListener('change', function() {
        const stages = document.querySelectorAll('.stage-card');
        stages.forEach(stage => {
            applyStageStyles(stage, this.value);
        });
    });

    // Function to update substage assignments when parent stage assignment changes
    function updateSubstageAssignments(stageId, newValue, newText) {
        const stage = document.getElementById(`stage-${stageId}`);
        const substages = stage.querySelectorAll('.substage');
        
        substages.forEach(substage => {
            const substageAssign = substage.querySelector('.substage-assign');
            const assignHint = substage.querySelector('.assign-hint');
            
            // Update the original assignee text
            substage.querySelector('.original-assignee').textContent = newText;
            
            // Only update if the substage hasn't been manually changed
            if (assignHint.style.display === 'none') {
                substageAssign.value = newValue;
            }
        });
    }

    // Add some CSS for the hint
    const style = document.createElement('style');
    style.textContent = `
        .assign-hint {
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-top: 4px;
            font-style: italic;
        }
        
        .assign-hint i {
            color: #666;
            margin-right: 4px;
        }
    `;
    document.head.appendChild(style);

    // Add CSS for the substage title dropdown
    const style2 = document.createElement('style');
    style2.textContent = `
        .substage-title {
            margin-bottom: 15px;
            font-weight: 500;
        }

        .substage-title:focus {
            border-color: var(--architecture-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .stage-card.architecture .substage-title:focus {
            border-color: var(--architecture-color);
        }

        .stage-card.interior .substage-title:focus {
            border-color: var(--interior-color);
        }

        .stage-card.construction .substage-title:focus {
            border-color: var(--construction-color);
        }
    `;
    document.head.appendChild(style2);

    // Add CSS for the optgroups
    const style3 = document.createElement('style');
    style3.textContent = `
        .substage-title optgroup {
            font-weight: 600;
            color: #333;
            background-color: #f8f9fa;
        }

        .substage-title option {
            font-weight: normal;
            color: #666;
            padding: 8px;
        }

        .substage-title {
            padding: 10px;
            border-radius: 8px;
        }

        /* Category-specific styling for optgroups */
        .stage-card.architecture .substage-title optgroup {
            color: var(--architecture-color);
        }

        .stage-card.interior .substage-title optgroup {
            color: var(--interior-color);
        }

        .stage-card.construction .substage-title optgroup {
            color: var(--construction-color);
        }

        /* Hover effect for options */
        .substage-title option:hover {
            background-color: #f0f0f0;
        }
    `;
    document.head.appendChild(style3);

    // Function to handle file attachments
    function setupFileAttachment(container) {
        const fileInput = container.querySelector('.file-input');
        const attachButton = container.querySelector('.attach-file-btn');
        const filesList = container.querySelector('.attached-files-list');

        attachButton.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            const files = Array.from(fileInput.files);
            files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'attached-file-item';
                
                // Format file size
                const size = formatFileSize(file.size);
                
                fileItem.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">${size}</span>
                    </div>
                    <button type="button" class="remove-file-btn">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                // Add remove functionality
                const removeBtn = fileItem.querySelector('.remove-file-btn');
                removeBtn.addEventListener('click', () => {
                    fileItem.remove();
                });
                
                filesList.appendChild(fileItem);
            });
        });
    }

    // Helper function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Add CSS styles for file attachment
    const fileAttachmentStyles = document.createElement('style');
    fileAttachmentStyles.textContent = `
        .file-attachment-section {
            margin-top: 15px;
        }

        .file-input-wrapper {
            margin-bottom: 10px;
        }

        .attach-file-btn {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            padding: 8px 16px;
            border-radius: 8px;
            color: #495057;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .attach-file-btn:hover {
            background: #fff;
            border-color: #80bdff;
            color: #0056b3;
        }

        .attached-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e0e0e0;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-name {
            font-size: 13px;
            color: #495057;
        }

        .file-size {
            font-size: 12px;
            color: #6c757d;
        }

        .remove-file-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            opacity: 0.6;
            transition: all 0.2s ease;
        }

        .remove-file-btn:hover {
            opacity: 1;
            background: rgba(220, 53, 69, 0.1);
        }

        /* Category-specific styling */
        .stage-card.architecture .attach-file-btn:hover {
            border-color: var(--architecture-color);
            color: var(--architecture-color);
        }

        .stage-card.interior .attach-file-btn:hover {
            border-color: var(--interior-color);
            color: var(--interior-color);
        }

        .stage-card.construction .attach-file-btn:hover {
            border-color: var(--construction-color);
            color: var(--construction-color);
        }
    `;

    document.head.appendChild(fileAttachmentStyles);

    function setupProjectSuggestions() {
        const projectTitleInput = document.getElementById('projectTitle');
        const suggestionsContainer = document.getElementById('projectSuggestions');
        
        // Add test data for immediate visibility
        const testProjects = [
            {
                id: 1,
                title: 'Modern Office Complex',
                description: 'A contemporary office building with sustainable features',
                category: 'architecture',
                type: 'commercial-architecture',
                startDate: '2024-03-20',
                dueDate: '2024-09-20',
                assignTo: '2',
                stages: [
                    {
                        title: 'Planning Phase',
                        assignTo: '2',
                        startDate: '2024-03-20T09:00',
                        dueDate: '2024-04-20T18:00',
                        substages: [
                            {
                                title: 'Site Analysis',
                                assignTo: '2',
                                startDate: '2024-03-20T09:00',
                                dueDate: '2024-03-30T18:00'
                            },
                            {
                                title: 'Design Development',
                                assignTo: '3',
                                startDate: '2024-04-01T09:00',
                                dueDate: '2024-04-15T18:00'
                            }
                        ]
                    },
                    {
                        title: 'Construction Phase',
                        assignTo: '3',
                        startDate: '2024-04-21T09:00',
                        dueDate: '2024-08-20T18:00',
                        substages: [
                            {
                                title: 'Foundation Work',
                                assignTo: '3',
                                startDate: '2024-04-21T09:00',
                                dueDate: '2024-05-20T18:00'
                            }
                        ]
                    }
                ]
            }
            // Add more test projects as needed
        ];

        // Log to verify the input element and event binding
        console.log('Project Title Input:', projectTitleInput);
        
        projectTitleInput.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            console.log('Search term:', searchTerm); // Debug log
            
            if (searchTerm.length < 1) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            const matches = testProjects.filter(project => 
                project.title.toLowerCase().includes(searchTerm)
            );
            console.log('Matches found:', matches); // Debug log

            if (matches.length > 0) {
                displaySuggestions(matches);
            } else {
                suggestionsContainer.style.display = 'none';
            }
        });

        // Ensure suggestions are visible by updating CSS
        const suggestionStyles = document.createElement('style');
        suggestionStyles.textContent = `
            .project-suggestions {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1050;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                margin-top: 4px;
            }

            .suggestion-item {
                padding: 12px 16px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
            }

            .suggestion-item:hover {
                background-color: #f8f9fa;
            }

            .project-title-wrapper {
                position: relative;
            }
        `;
        document.head.appendChild(suggestionStyles);
    }

    function displaySuggestions(projects) {
        const suggestionsContainer = document.getElementById('projectSuggestions');
        suggestionsContainer.innerHTML = '';
        
        projects.forEach(project => {
            const suggestionItem = document.createElement('div');
            suggestionItem.className = `suggestion-item ${project.category}`;
            
            suggestionItem.innerHTML = `
                <div class="suggestion-info">
                    <div class="suggestion-title">${project.title}</div>
                    <div class="suggestion-category">${project.category}</div>
                </div>
            `;
            
            // Update click handler to fill the entire form
            suggestionItem.addEventListener('click', () => {
                fillProjectForm(project);
                suggestionsContainer.style.display = 'none';
            });
            
            suggestionsContainer.appendChild(suggestionItem);
        });
        
        suggestionsContainer.style.display = 'block';
    }

    function fillProjectForm(project) {
        // Fill basic project information
        document.getElementById('projectTitle').value = project.title;
        document.getElementById('projectDescription').value = project.description;
        
        // Set category and trigger change event
        const categorySelect = document.getElementById('projectCategory');
        categorySelect.value = project.category;
        categorySelect.dispatchEvent(new Event('change'));
        
        // Set project type after a small delay to ensure category change has processed
        setTimeout(() => {
            const projectTypeSelect = document.getElementById('projectType');
            projectTypeSelect.value = project.type;
        }, 100);
        
        // Set dates
        document.getElementById('startDate').value = project.startDate;
        document.getElementById('dueDate').value = project.dueDate;
        
        // Set assigned person
        if (project.assignTo) {
            document.getElementById('assignTo').value = project.assignTo;
        }

        // Clear existing stages
        const stagesWrapper = document.getElementById('stagesWrapper');
        stagesWrapper.innerHTML = '';
        stageCount = 0;

        // Add stages if they exist
        if (project.stages && project.stages.length > 0) {
            project.stages.forEach(stage => {
                // Increment stage count
                stageCount++;
                
                // Add new stage
                addNewStage(stageCount);
                
                // Get the newly created stage card
                const stageCard = document.getElementById(`stage-${stageCount}`);
                
                // Fill stage details
                if (stageCard) {
                    // Set stage title
                    const titleInput = stageCard.querySelector('.stage-title-input');
                    if (titleInput) {
                        titleInput.value = stage.title;
                        // Update header title
                        const headerTitle = stageCard.querySelector('.stage-title');
                        if (headerTitle) {
                            headerTitle.textContent = stage.title;
                        }
                    }

                    // Set assignee
                    const assignSelect = stageCard.querySelector('select[name="assign_to"]');
                    if (assignSelect) {
                        assignSelect.value = stage.assignTo;
                    }

                    // Set dates
                    const startDateInput = stageCard.querySelector('input[name="start_date"]');
                    const dueDateInput = stageCard.querySelector('input[name="due_date"]');
                    if (startDateInput && dueDateInput) {
                        startDateInput.value = stage.startDate;
                        dueDateInput.value = stage.dueDate;
                    }

                    // Add substages if they exist
                    if (stage.substages && stage.substages.length > 0) {
                        stage.substages.forEach(substage => {
                            // Add new substage
                            addSubstage(stageCount);
                            
                            // Get the substages wrapper
                            const substagesWrapper = document.getElementById(`substages-${stageCount}`);
                            const lastSubstage = substagesWrapper.lastElementChild;
                            
                            if (lastSubstage) {
                                // Set substage title
                                const substageTitleSelect = lastSubstage.querySelector('.substage-title');
                                if (substageTitleSelect) {
                                    // First add the option if it doesn't exist
                                    const option = document.createElement('option');
                                    option.value = substage.title.toLowerCase().replace(/\s+/g, '-');
                                    option.textContent = substage.title;
                                    substageTitleSelect.appendChild(option);
                                    substageTitleSelect.value = option.value;
                                }

                                // Update substage header
                                const substageHeader = lastSubstage.querySelector('.stage-title');
                                if (substageHeader) {
                                    substageHeader.textContent = `Substage ${substagesWrapper.children.length} (${substage.title})`;
                                }

                                // Set assignee
                                const substageAssign = lastSubstage.querySelector('.substage-assign');
                                if (substageAssign) {
                                    substageAssign.value = substage.assignTo;
                                }

                                // Set dates
                                const substageStart = lastSubstage.querySelector('.substage-start');
                                const substageDue = lastSubstage.querySelector('.substage-due');
                                if (substageStart && substageDue) {
                                    substageStart.value = substage.startDate;
                                    substageDue.value = substage.dueDate;
                                }
                            }
                        });
                    }
                }
            });
        }
    }

    // Make sure to call setupProjectSuggestions after DOM is loaded
    setupProjectSuggestions();

    // Add this function to your existing code
    async function loadUsers() {
        try {
            const response = await fetch('api/get_users.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.status === 'success' && Array.isArray(data.data)) {
                // Update main Assign To dropdown
                const assignToSelect = document.getElementById('assignTo');
                assignToSelect.innerHTML = '<option value="">Select Team Member</option>';
                
                data.data.forEach(user => {
                    const option = createUserOption(user);
                    assignToSelect.appendChild(option.cloneNode(true));
                });

                // Update all existing stage and substage assign dropdowns
                updateAllAssignDropdowns();
            } else {
                console.error('Invalid data format received:', data);
            }
        } catch (error) {
            console.error('Error loading users:', error);
            const assignToSelect = document.getElementById('assignTo');
            assignToSelect.innerHTML = '<option value="">Error loading users</option>';
        }
    }

    function createUserOption(user) {
        const option = document.createElement('option');
        option.value = user.id || '';
        
        // Safely handle potentially missing data
        const username = user.username || 'Unknown User';
        const role = user.role ? ` (${user.role})` : '';
        const dept = user.department ? ` - ${user.department}` : '';
        
        option.textContent = `${username}${role}${dept}`;
        
        // Safely add data attributes
        if (user.position) option.dataset.position = user.position;
        if (user.department) option.dataset.department = user.department;
        if (user.designation) option.dataset.designation = user.designation;
        
        return option;
    }

    // Function to update all assign dropdowns
    function updateAllAssignDropdowns() {
        const mainAssignSelect = document.getElementById('assignTo');
        
        // Update stage assign dropdowns
        document.querySelectorAll('.stage-card:not(.substage) select[name="assign_to"]').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = mainAssignSelect.innerHTML;
            if (currentValue) select.value = currentValue;
        });

        // Update substage assign dropdowns
        document.querySelectorAll('.substage select[name="substage_assign_to"]').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = mainAssignSelect.innerHTML;
            if (currentValue) select.value = currentValue;
        });
    }

    // Update the populateStageAssign function
    function populateStageAssign(stageCard) {
        const mainAssignSelect = document.getElementById('assignTo');
        const assignSelect = stageCard.querySelector('select[name="assign_to"], select[name="substage_assign_to"]');
        
        if (mainAssignSelect && assignSelect) {
            assignSelect.innerHTML = mainAssignSelect.innerHTML;
        }
    }

    // Add event listener for main assign select changes
    document.getElementById('assignTo').addEventListener('change', function() {
        const selectedValue = this.value;
        const selectedText = this.options[this.selectedIndex].text;
        
        // Update all stage assign dropdowns that haven't been manually changed
        document.querySelectorAll('.stage-card:not(.substage)').forEach(stage => {
            const stageId = stage.id.split('-')[1];
            updateSubstageAssignments(stageId, selectedValue, selectedText);
        });
    });

    // Add this function to load categories when the modal opens
    async function loadProjectCategories() {
        try {
            const response = await fetch('api/get_project_categories.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                const projectTypeSelect = document.getElementById('projectCategory');
                const mainCategories = data.data;
                
                // Clear existing options except the first one
                projectTypeSelect.innerHTML = '<option value="">Select Type</option>';
                
                // Add main categories
                mainCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.name.toLowerCase();
                    option.textContent = category.name;
                    option.dataset.id = category.id;
                    projectTypeSelect.appendChild(option);
                });
                
                // Store subcategories for later use
                window.projectSubcategories = {};
                mainCategories.forEach(category => {
                    window.projectSubcategories[category.id] = category.subcategories;
                });
            }
        } catch (error) {
            console.error('Error loading project categories:', error);
        }
    }

    // Add this function to validate form data
    function validateProjectForm(formData) {
        const errors = [];
        
        if (!formData.projectTitle.trim()) errors.push('Project title is required');
        if (!formData.projectDescription.trim()) errors.push('Project description is required');
        if (!formData.projectCategory) errors.push('Project type is required');
        if (!formData.projectType) errors.push('Project category is required');
        if (!formData.startDate) errors.push('Start date is required');
        if (!formData.dueDate) errors.push('Due date is required');
        if (!formData.assignTo) errors.push('Project assignee is required');
        
        if (new Date(formData.startDate) > new Date(formData.dueDate)) {
            errors.push('Start date cannot be after due date');
        }
        
        // Validate stages
        if (formData.stages.length === 0) {
            errors.push('At least one stage is required');
        }
        
        formData.stages.forEach((stage, index) => {
            if (!stage.assignTo) errors.push(`Stage ${index + 1} assignee is required`);
            if (!stage.startDate) errors.push(`Stage ${index + 1} start date is required`);
            if (!stage.endDate) errors.push(`Stage ${index + 1} end date is required`);
            
            // Validate stage dates
            if (new Date(stage.startDate) > new Date(stage.endDate)) {
                errors.push(`Stage ${index + 1} start date cannot be after end date`);
            }
            
            // Validate substages if any
            stage.substages.forEach((substage, subIndex) => {
                if (!substage.title) errors.push(`Substage ${index + 1}.${subIndex + 1} title is required`);
                if (!substage.assignTo) errors.push(`Substage ${index + 1}.${subIndex + 1} assignee is required`);
                if (!substage.startDate) errors.push(`Substage ${index + 1}.${subIndex + 1} start date is required`);
                if (!substage.endDate) errors.push(`Substage ${index + 1}.${subIndex + 1} end date is required`);
                
                if (new Date(substage.startDate) > new Date(substage.endDate)) {
                    errors.push(`Substage ${index + 1}.${subIndex + 1} start date cannot be after end date`);
                }
            });
        });
        
        return errors;
    }
}); 