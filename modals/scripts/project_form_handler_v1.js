console.log('Script loaded');
console.log('Modal element:', document.getElementById('projectModal'));
console.log('Open button:', document.querySelector('.add-project-btn'));

// First, let's store users globally so we can access them throughout the code
let globalUsers = [];

// Add global variable for categories
let globalCategories = [];

// Add global variable for current user
let currentUser = null;

// Replace the sampleProjects object with a function to fetch real projects
let globalProjects = [];

// Add a variable to track if we're editing a project
let isEditMode = false;
let currentProjectId = null;

// Function to fetch project suggestions
async function fetchProjectSuggestions() {
    try {
        console.log('Fetching project suggestions...');
        const response = await fetch('api/get_project_suggestions.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            // Ensure IDs are properly formatted
            globalProjects = data.data.map(project => ({
                ...project,
                id: project.id.toString() // Ensure ID is string for consistent comparison
            }));
            
            console.log('Loaded projects:', globalProjects);
            return globalProjects;
        } else {
            console.error('Error fetching projects:', data.message);
            return [];
        }
    } catch (error) {
        console.error('Failed to fetch projects:', error);
        return [];
    }
}

// Fetch users and store them globally
async function fetchUsers() {
    try {
        console.log('Fetching users...');
        const response = await fetch('api/get_users.php');
        const data = await response.json();
        console.log('Received users data:', data);
        
        if (data.status === 'success') {
            globalUsers = data.data;
            console.log('Stored global users:', globalUsers);
            return data.data;
        } else {
            console.error('Error fetching users:', data.message);
            return [];
        }
    } catch (error) {
        console.error('Failed to fetch users:', error);
        return [];
    }
}

// Add function to fetch categories
async function fetchCategories() {
    try {
        console.log('Fetching categories...');
        const response = await fetch('api/get_project_categories.php');
        
        // Log the raw response for debugging
        console.log('Raw response:', response);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Categories response:', data);
        
        if (data.status === 'success') {
            globalCategories = data.data;
            console.log('Stored categories:', globalCategories);
            return data.data;
        } else {
            console.error('Error fetching categories:', data.message);
            showNotification('Error loading categories: ' + data.message, 'error');
            return [];
        }
    } catch (error) {
        console.error('Failed to fetch categories:', error);
        showNotification('Failed to load categories. Please check console for details.', 'error');
        return [];
    }
}

// Add function to fetch current user
async function fetchCurrentUser() {
    try {
        const response = await fetch('api/get_current_user.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            currentUser = data.data;
            return data.data;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Failed to fetch current user:', error);
        showNotification('Error: Please log in again', 'error');
        // Optionally redirect to login page
        // window.location.href = '/login.php';
        return null;
    }
}

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

// Define project type specific substage titles with groups
const projectSubstageTitles = {
    architecture: {
        'Concept Drawings': [
            'Concept Plan',
            'PPT',
            '3D Model'
        ],
        'Structure Drawings - All Floor': [
            'Excavation Layout Plan',
            'Setting Layout Plan',
            'Foundation Plan',
            'Foundation Details',
            'Column Layout Plan',
            'Column Details',
            'Footing Layout Plan',
            'Column & Setting Layout Plan',
            'Column & Footing Details',
            'Plinth Beam Layout Plan',
            'Basement Roof Slab Beam Layout Plan',
            'Stilt Roof Slab Beam Layout Plan',
            'Stilt Floor Roof Slab Beam Layout Plan',
            'Ground Floor Roof Slab Beam Layout Plan',
            'First Floor Roof Slab Beam Layout Plan',
            'Second Floor Roof Slab Beam Layout Plan',
            'Third Floor Roof Slab Beam Layout Plan',
            'Fourth Floor Roof Slab Beam Layout Plan',
            'Fifth Floor Roof Slab Beam Layout Plan',
            'Terrace Roof Slab Beam Layout Plan',
            'Basement Roof Slab Beam Layout Plan',
            'Stilt Roof Slab Beam Layout Plan',
            'Stilt Floor Roof Slab Beam Layout Plan',
            'Ground Floor Roof Slab Beam Layout Plan',
            'First Floor Roof Slab Beam Layout Plan',
            'Second Floor Roof Slab Beam Layout Plan',
            'Third Floor Roof Slab Beam Layout Plan',
            'Fourth Floor Roof Slab Beam Layout Plan',
            'Fifth Floor Roof Slab Beam Layout Plan',
            'Terrace Roof Slab Beam Layout Plan',
            
        ],
        'Architecture Working Drawings - All Floor': [
            'Basement Furniture Layout Plan',
            'Stilt Floor Furniture Layout Plan',
            'Ground Floor Furniture Layout Plan',
            'First Floor Furniture Layout Plan',
            'Second Floor Furniture Layout Plan',
            'Third Floor Furniture Layout Plan',
            'Fourth Floor Furniture Layout Plan',
            'Fifth Floor Furniture Layout Plan',
            'Terrace Furniture Layout Plan',
            'Basement Working Layout Plan',
            'Stilt Working Layout Plan',
            'Ground Floor Working Layout Plan',
            'First Floor Working Layout Plan',
            'Second Floor Working Layout Plan',
            'Third Floor Working Layout Plan',
            'Fourth Floor Working Layout Plan',
            'Fifth Floor Working Layout Plan',
            'Terrace Working Layout Plan',
            'Basement Door & Window Schedule Details',
            'Stilt Floor Door & Window Schedule Details',
            'Ground Floor Door & Window Schedule Details',
            'First Floor Door & Window Schedule Details',
            'Second Floor Door & Window Schedule Details',
            'Third Floor Door & Window Schedule Details',
            'Fourth Floor Door & Window Schedule Details',
            'Fifth Floor Door & Window Schedule Details',
            'Terrace Door & Window Schedule Details',
            'Front Elevation Details',
            'Side 1 Elevation Details',
            'Side 2 Elevation Details',
            'Section Elevations X-X',
            'Section Elevations Y-Y',
            'Site Plans'
        ],
        'Electrical Drawings - All Floor': [
            'Basement Wall Electrical Layout',
            'Stilt Floor Wall Electrical Layout',
            'Ground Floor Wall Electrical Layout',
            'First Floor Wall Electrical Layout',
            'Second Floor Wall Electrical Layout',
            'Third Floor Wall Electrical Layout',
            'Fourth Floor Wall Electrical Layout',
            'Fifth Floor Wall Electrical Layout',
            'Terrace Wall Electrical Layout'
        ],
        'Electrical Drawings - All Floor': [
            'Basement Wall Electrical Layout',
            'Stilt Floor Wall Electrical Layout',
            'Ground Floor Wall Electrical Layout',
            'First Floor Wall Electrical Layout',
            'Second Floor Wall Electrical Layout',
            'Third Floor Wall Electrical Layout',
            'Fourth Floor Wall Electrical Layout',
            'Fifth Floor Wall Electrical Layout',
            'Terrace Wall Electrical Layout'
        ],
        'Ceiling Drawings - All Floor': [
            'Basement Ceiling Layout Plan',
            'Stilt Floor Ceiling Layout Plan',
            'Ground Floor Ceiling Layout Plan',
            'First Floor Ceiling Layout Plan',
            'Second Floor Ceiling Layout Plan',
            'Third Floor Ceiling Layout Plan',
            'Fourth Floor Ceiling Layout Plan',
            'Fifth Floor Ceiling Layout Plan',
            'Terrace Ceiling Layout Plan'
        ],
        'Plumbing Drawings - All Floor': [
            'Basement Plumbing Layout Plan',
            'Stilt Floor Plumbing Layout Plan',
            'Ground Floor Plumbing Layout Plan',
            'First Floor Plumbing Layout Plan',
            'Second Floor Plumbing Layout Plan',
            'Third Floor Plumbing Layout Plan',
            'Fourth Floor Plumbing Layout Plan',
            'Fifth Floor Plumbing Layout Plan',
            'Terrace Plumbing Layout Plan'
        ],
        'Water Supply Drawings - All Floor': [
            'Basement Water Supply Layout Plan',
            'Stilt Floor Water Supply Layout Plan',
            'Ground Floor Water Supply Layout Plan',
            'First Floor Water Supply Layout Plan',
            'Second Floor Water Supply Layout Plan',
            'Third Floor Water Supply Layout Plan',
            'Fourth Floor Water Supply Layout Plan',
            'Fifth Floor Water Supply Layout Plan',
            'Terrace Water Supply Layout Plan'
        ],
        'Details Drawings': [
            'Staircase Details',
            'Finishing Schedule',
            'Ramp Details',
            'Kitchen Details',
            'Lift Details',
            'Toilet Details',
            'Saptic Tank Details',
            'Compound Wall Details',
            'Landscape Details',
            'Slab Details',
            'Roof Details',
            'Wall Details',
            'Floor Details',
            'Ceiling Details',
            'Door Details',
            'Window Details'
        ],
        'Other Drawings': [
            'Site Plan',
            'Front Elevation',
            'Rear Elevation',
            'Side Elevation',
            'Section Elevation',
            'Roof Plan',
            'Floor Plan',
            'Ceiling Plan',
            'Door & Window Schedule'
        ]
    },
    interior: {
        'Concept Design': [
            'Concept Plan',
            'PPT',
            '3D Views',
            'Render plan Basement',
            'Render plan Stilt Floor',
            'Render plan Ground Floor',
            'Render plan First Floor',
            'Render plan Second Floor',
            'Render plan Third Floor',
            'Render plan Fourth Floor',
        ],
        '3D Views': [
            'Daughters Bed Room',
            'Sons Bed Room',
            'Master Bed Room',
            'Guest Bed Room',
            'Toilet - 01',
            'Toilet - 02',
            'Toilet - 03',
            'Toilet - 04',
            'Toilet - 05',
            'Prayer Room',
            'Study Room',
            'Home Theater',
            'Kitchen',
            'Dining Room',
            'Living Room',
            'GYM / Multi-purpose Room',
            'Servant Room',
            'Family Lounge',
            'Staircase',
            'Landscape Area',
            'Recreation Area',
            'Swimming Pool',
            'Living & Dining Room',
            'Living Room',
            'Dining Room',
            'Kitchen',
            'Balcony - 01',
            'Balcony - 02',
            'Balcony - 03',
            'Balcony - 04',
            'Balcony - 05',
            'Utility Area',
            'Mumty False Ceiling Plan',
            'Mumty',
            'Front Elevation',
            'Side 1 Elevation',
            'Side 2 Elevation',
            'Section Elevation X-X',
            'Section Elevation Y-Y',
            'Entrance Lobby',
            'Manager Cabin',
            'Work Station Area - 01',
            'Work Station Area - 02',
            'Work Station Area - 03',
            'Work Station Area - 04',
            'Work Station Area - 05',
            'Work Station Area - 06',
            'Reception Area',
            'Conference Room',
            'Meeting Room',
            'Waiting Area',
            'Lobby - 01',
            'Lobby - 02',
            'Lobby - 03'
        ],
        'Flooring Drawings': [
            'Flooring layout Plan Basement',
            'Flooring layout Plan Stilt Floor',
            'Flooring layout Plan Ground Floor',
            'Flooring layout Plan First Floor',
            'Flooring layout Plan Second Floor',
            'Flooring layout Plan Third Floor',
            'Flooring layout Plan Fourth Floor',
            'Flooring Layout Plan Fifth Floor',
            'Flooring layout Plan Terrace',
        ],
        'False Ceiling Drawings': [
            'False Ceiling Layout Plan Basement',
            'False Ceiling Layout Plan Stilt Floor',
            'False Ceiling Layout Plan Ground Floor',
            'False Ceiling Layout Plan First Floor',
            'False Ceiling Layout Plan Second Floor',
            'False Ceiling Layout Plan Third Floor',
            'False Ceiling Layout Plan Fourth Floor',
            'False Ceiling Layout Plan Fifth Floor',
            'False Ceiling Layout Plan Terrace',
            'Master Bed Room False Ceiling',
            'Daughters Bed Room False Ceiling',
            'Sons Bed Room False Ceiling',
            'Guest Bed Room False Ceiling',
            'Toilet - 01 False Ceiling',
            'Toilet - 02 False Ceiling',
            'Toilet - 03 False Ceiling',
            'Toilet - 04 False Ceiling',
            'Toilet - 05 False Ceiling',
            'Prayer Room False Ceiling',
            'Study Room False Ceiling',
            'Home Theater False Ceiling',
            'Kitchen False Ceiling Layout Plan & Section Details',
            'Dining Room False Ceiling Layout Plan & Section Details',
            'Living Room False Ceiling Layout Plan & Section Details',
            'GYM / Multi-purpose Room False Ceiling Layout Plan & Section Details',
            'Servant Room False Ceiling Layout Plan & Section Details',
            'Family Lounge False Ceiling Layout Plan & Section Details',
            'Staircase False Ceiling Layout Plan & Section Details',
            'Landscape Area False Ceiling Layout Plan & Section Details',
            'Recreation Area False Ceiling',
            'Office Space False Ceiling Layout Plan & Section Details',
            'Conference Room False Ceiling Layout Plan & Section Details',
            'Meeting Room False Ceiling Layout Plan & Section Details',
            'Waiting Area False Ceiling Layout Plan & Section Details',
            'Lobby - 01 False Ceiling Layout Plan & Section Details',
            'Lobby - 02 False Ceiling Layout Plan & Section Details',
            'Lobby - 03 False Ceiling Layout Plan & Section Details',
            'Reception Area False Ceiling Layout Plan & Section Details',
            'Manager Cabin False Ceiling Layout Plan & Section Details',
            'Work Station Area - 01 False Ceiling Layout Plan & Section Details',
            'Work Station Area - 02 False Ceiling Layout Plan & Section Details',
            'Work Station Area - 03 False Ceiling Layout Plan & Section Details',
            'Work Station Area - 04 False Ceiling Layout Plan & Section Details',
            'Work Station Area - 05 False Ceiling Layout Plan & Section Details',
            
        ],
        'Ceiling Drawings': [
            'Ceiling Layout Plan Basement',
            'Ceiling Layout Plan Stilt Floor',
            'Ceiling Layout Plan Ground Floor',
            'Ceiling Layout Plan First Floor',
            'Ceiling Layout Plan Second Floor',
            'Ceiling Layout Plan Third Floor',
            'Ceiling Layout Plan Fourth Floor',
            'Ceiling Layout Plan Fifth Floor'
        ],

        'Electrical Drawings': [
            'Electrical Layout Plan Basement',
            'Electrical Layout Plan Stilt Floor',
            'Electrical Layout Plan Ground Floor',
            'Electrical Layout Plan First Floor',
            'Electrical Layout Plan Second Floor',
            'Electrical Layout Plan Third Floor',
            'Electrical Layout Plan Fourth Floor',
            'Electrical Layout Plan Fifth Floor'
        ],
        'Plumbing Drawings': [
            'Plumbing Layout Plan Basement',
            'Plumbing Layout Plan Stilt Floor',
            'Plumbing Layout Plan Ground Floor',
            'Plumbing Layout Plan First Floor',
            'Plumbing Layout Plan Second Floor',
            'Plumbing Layout Plan Third Floor',
            'Plumbing Layout Plan Fourth Floor',
        ],
        'Water Supply Drawings': [
            'Water Supply Layout Plan Basement',
            'Water Supply Layout Plan Stilt Floor',
            'Water Supply Layout Plan Ground Floor',
            'Water Supply Layout Plan First Floor',
            'Water Supply Layout Plan Second Floor',
            'Water Supply Layout Plan Third Floor',
            'Water Supply Layout Plan Fourth Floor',
            'Water Supply Layout Plan Fifth Floor'
        ],
        'Details Drawings': [
            'Staircase Details',
            'Finishing Details',
            'Ramp Details',
            'Kitchen Details',
            'Lift Details',
            'Toilet Details',
            'Saptic Tank Details',
            'Compound Wall Details',
            'Landscape Details',
            'Slab Details',
            'Roof Details',
            'Wall Details',
            'Floor Details',
            'Ceiling Details',
            
        ]
    },
    construction: {
        'Preparation Phase': [
            'Site Preparation',
            'Foundation Work'
        ],
        'Construction Phase': [
            'Structural Work',
            'MEP Installation'
        ],
        'Finishing Phase': [
            'Interior Finishing',
            'Exterior Finishing',
            'Quality Inspection'
        ]
    }
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

// Add helper function to get user name by ID
function getUserNameById(userId) {
    const user = globalUsers.find(u => u.id === userId);
    return user ? `${user.username} - ${user.role}` : 'Unknown User';
}

function addSubstage(stageNum) {
    const stage = document.querySelector(`.stage-block[data-stage="${stageNum}"]`);
    const substagesContainer = stage.querySelector('.substages-container');
    const substageCount = substagesContainer.children.length + 1;
    
    // Create user options HTML using global users
    const userOptionsHtml = globalUsers.map(user => 
        `<option value="${user.id}">${user.username} - ${user.role}</option>`
    ).join('');
    
    const projectType = document.querySelector('.modal-container').dataset.theme;
    const parentAssignTo = document.getElementById(`assignTo${stageNum}`).value;
    const parentUserName = getUserNameById(parentAssignTo);
    const parentStartDate = document.getElementById(`startDate${stageNum}`).value;
    const parentDueDate = document.getElementById(`dueDate${stageNum}`).value;

    const titleOptions = projectSubstageTitles[projectType] || {};
    const titleOptionsHtml = Object.entries(titleOptions).map(([group, titles]) => `
        <optgroup label="${group}">
            ${titles.map(title => 
                `<option value="${title}">${title}</option>`
            ).join('')}
        </optgroup>
    `).join('');

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
    newSubstage.dataset.stageNum = stageNum;
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
                <option value="">Select Team Member</option>
                ${userOptionsHtml}
            </select>
            <div class="stage-assign-note" style="display: ${parentAssignTo ? 'block' : 'none'};">
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
    
    // Set the default value to match the stage's assigned user
    const assignSelect = newSubstage.querySelector(`#substageAssignTo${stageNum}_${substageCount}`);
    if (assignSelect && parentAssignTo) {
        assignSelect.value = parentAssignTo;
    }
    
    setTimeout(() => {
        newSubstage.style.opacity = '1';
        newSubstage.style.transform = 'translateY(0)';
    }, 10);

    newSubstage.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

// Add this function to handle stage assignment changes
function handleStageAssignChange(stageNum) {
    const stage = document.querySelector(`.stage-block[data-stage="${stageNum}"]`);
    if (!stage) return; // Add safety check

    const stageAssignSelect = document.getElementById(`assignTo${stageNum}`);
    if (!stageAssignSelect) return; // Add safety check

    const substages = stage.querySelectorAll('.substage-block');
    
    const assignedUserName = getUserNameById(stageAssignSelect.value);
    
    substages.forEach(substage => {
        const substageNum = substage.dataset.substage;
        const assignNote = substage.querySelector('.stage-assign-note');
        const substageAssignSelect = substage.querySelector(`#substageAssignTo${stageNum}_${substageNum}`);
        
        if (stageAssignSelect.value && assignNote) {
            assignNote.innerHTML = `
                <i class="fas fa-info-circle"></i>
                *The stage is assigned to ${assignedUserName}
            `;
            assignNote.style.display = 'block';
            
            // Optionally, update substage assignment to match stage
            if (substageAssignSelect && !substageAssignSelect.value) {
                substageAssignSelect.value = stageAssignSelect.value;
            }
        } else if (assignNote) {
            assignNote.style.display = 'none';
        }
    });
}

// Update the handleSubstageAssignChange function
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
                Note: The stage is assigned to ${getUserNameById(stageAssignSelect.value)}
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
    const stageBlock = substagesContainer.closest('.stage-block');
    
    // Store the substage ID if it exists
    const substageId = substage.dataset.substageId;
    console.log('Deleting substage with ID:', substageId);
    
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
            
            // Update input names and IDs
            updateSubstageElements(block, stageBlock.dataset.stage, substageNum);
        });
    }, 300);
}

// Add this function to verify category data
function validateCategoryData() {
    console.log('Global Categories:', globalCategories);
    console.log('Global Projects:', globalProjects);
    
    globalProjects.forEach(project => {
        const category = globalCategories.find(cat => cat.id === project.category_id);
        console.log(`Project ${project.id} category:`, {
            project_category_id: project.category_id,
            found_category: category
        });
    });
}

// Move these functions outside of DOMContentLoaded event listener
// Add these functions for handling stage and substage files
async function getStageFiles(stageNum) {
    try {
        const fileInput = document.getElementById(`stageFileInput_${stageNum}`);
        if (!fileInput || !fileInput.files) {
            return [];
        }
        return await processFiles(fileInput.files);
    } catch (error) {
        console.error('Error getting stage files:', error);
        return [];
    }
}

async function getSubstageFiles(stageNum, substageNum) {
    try {
        const fileInput = document.getElementById(`substageFileInput_${stageNum}_${substageNum}`);
        if (!fileInput || !fileInput.files) {
            return [];
        }
        return await processFiles(fileInput.files);
    } catch (error) {
        console.error('Error getting substage files:', error);
        return [];
    }
}

async function processFiles(files) {
    const processedFiles = [];
    if (!files || files.length === 0) return processedFiles;

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

// Update the DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', async function() {
    try {
        // Fetch all necessary data
        await Promise.all([
            fetchCurrentUser(),
            fetchUsers(),
            fetchCategories(),
            fetchProjectSuggestions()
        ]);
        
        validateCategoryData(); // Add this line to check category data
        
        console.log('Data loaded:', {
            projects: globalProjects,
            categories: globalCategories,
            currentUser: currentUser
        });
        
        if (!currentUser) {
            console.error('No user logged in');
            return;
        }

        // Initialize project title input
        const projectTitleInput = document.getElementById('projectTitle');
        if (projectTitleInput) {
            projectTitleInput.addEventListener('input', (e) => handleProjectTitleInput(e.target));
        }

        // Initialize other form elements
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
            
                // Update categories with the selected type
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
        
        // Create user options HTML using global users
        const userOptionsHtml = globalUsers.map(user => 
            `<option value="${user.id}">${user.username} - ${user.role}</option>`
        ).join('');
        
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
                <select id="assignTo${stageCount}" 
                        name="stages[${stageCount}][assignTo]" 
                        onchange="handleStageAssignChange(${stageCount})" 
                        required>
                    <option value="">Select Team Member</option>
                    ${userOptionsHtml}
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
        
        // Reset edit mode
        isEditMode = false;
        currentProjectId = null;
        
        // Reset form button and title
        const submitBtn = document.querySelector('#createProjectForm button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Create Project';
        document.querySelector('.modal-header h2').textContent = 'Create New Project';
        
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
        if (selectedType instanceof Event) {
            selectedType = document.getElementById('projectType').value;
        }

        console.log('Updating categories for type:', selectedType);
        console.log('Available categories:', globalCategories);

        const projectCategorySelect = document.getElementById('projectCategory');
        projectCategorySelect.innerHTML = '<option value="">Select Category</option>';
        projectCategorySelect.disabled = !selectedType;

        if (selectedType) {
            // Find parent category ID based on type
            let parentId;
            switch(selectedType) {
                case 'architecture':
                    parentId = 1;
                    break;
                case 'interior':
                    parentId = 2;
                    break;
                case 'construction':
                    parentId = 3;
                    break;
            }

            console.log('Looking for categories with parent_id:', parentId);

            // Filter categories by parent_id
            const relevantCategories = globalCategories.filter(cat => {
                console.log('Checking category:', cat);
                return parseInt(cat.parent_id) === parentId;
            });
            
            console.log('Filtered categories:', relevantCategories);

            if (relevantCategories.length > 0) {
                projectCategorySelect.disabled = false;
                
                relevantCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    projectCategorySelect.appendChild(option);
                });
            } else {
                console.log('No categories found for parent_id:', parentId);
            }
        }
    }

    // Add this function to get category ID from name
    function getCategoryIdByName(categoryName) {
        const category = globalCategories.find(cat => 
            cat.name.toLowerCase().replace(/\s+/g, '-') === categoryName.toLowerCase().replace(/\s+/g, '-')
        );
        return category ? category.id : null;
    }

    // Update the handleSubmit function
    async function handleSubmit(e) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            if (isEditMode && currentProjectId) {
                // Update existing project
                await updateProject(e, currentProjectId);
                showNotification('Project updated successfully!', 'success');
            } else {
                // Create new project
                const projectData = await createProject(e);
                
                if (!projectData.project_id) {
                    throw new Error('No project ID returned');
                }
                
                // Create stages and substages
                await createStagesAndSubstages(projectData.project_id);
                showNotification('Project created successfully!', 'success');
            }
            
        closeModal();
            
        } catch (error) {
            console.error('Error:', error);
            showNotification(error.message, 'error');
        } finally {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }
    }

    async function createProject(e) {
        const formData = new FormData(e.target);
        
        // Get the category ID directly from the select element
        const categorySelect = document.getElementById('projectCategory');
        const categoryId = categorySelect.value;

        // Debug logs
        console.log('Selected category ID:', categoryId);
        
        const projectData = {
            projectTitle: formData.get('projectTitle'),
            projectDescription: formData.get('projectDescription'),
            projectType: formData.get('projectType'),
            projectCategory: categoryId, // Use the category ID directly
            startDate: formData.get('startDate'),
            dueDate: formData.get('dueDate'),
            assignTo: formData.get('assignTo'),
            created_by: currentUser.id
        };

        // Debug log
        console.log('Sending project data:', projectData);

        const response = await fetch('api/create_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(projectData)
        });

        const result = await response.json();
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to create project');
        }
        
        return result;
    }

    async function createStagesAndSubstages(projectId) {
        const stages = [];
        const stageBlocks = document.querySelectorAll('.stage-block');
        
        for (const stageBlock of stageBlocks) {
            const stageNum = stageBlock.dataset.stage;
            const stageData = {
                assignTo: document.getElementById(`assignTo${stageNum}`).value,
                startDate: document.getElementById(`startDate${stageNum}`).value,
                dueDate: document.getElementById(`dueDate${stageNum}`).value,
                files: await getStageFiles(stageNum),
                substages: []
            };
            
            // Get substages
            const substageBlocks = stageBlock.querySelectorAll('.substage-block');
            for (const substageBlock of substageBlocks) {
                const substageNum = substageBlock.dataset.substage;
                const substageData = {
                    title: document.getElementById(`substageTitle${stageNum}_${substageNum}`).value,
                    assignTo: document.getElementById(`substageAssignTo${stageNum}_${substageNum}`).value,
                    startDate: document.getElementById(`substageStartDate${stageNum}_${substageNum}`).value,
                    dueDate: document.getElementById(`substageDueDate${stageNum}_${substageNum}`).value,
                    files: await getSubstageFiles(stageNum, substageNum)
                };
                stageData.substages.push(substageData);
            }
            
            stages.push(stageData);
        }
        
        const response = await fetch('api/create_project_stages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                project_id: projectId,
                stages: stages
            })
        });
        
        const result = await response.json();
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to create stages');
        }
        
        return result;
    }

    // Add notification function
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
        
        // Create toast content
        toast.innerHTML = `
            <i class="toast-icon fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <div class="toast-message">${message}</div>
            <div class="toast-close">
                <i class="fas fa-times"></i>
            </div>
        `;
        
        // Add to container
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

        // Log for debugging
        console.log('Notification shown:', message, type);
    }

    // Add notification styles to your CSS
    const notificationStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        animation: slideIn 0.3s ease;
        z-index: 1100;
    }

    .notification.success {
        background: #28a745;
    }

    .notification.error {
        background: #dc3545;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    `;

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
                const titleOptions = projectSubstageTitles[selectedType] || {};
                titleSelect.innerHTML = '<option value="">Select Title</option>' + 
                    Object.entries(titleOptions).map(([group, titles]) => `
                        <optgroup label="${group}">
                            ${titles.map(title => 
                                `<option value="${title.toLowerCase().replace(/\s+/g, '-')}">${title}</option>`
                            ).join('')}
                        </optgroup>
                    `).join('');
                
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
            if (globalProjects[e.target.value]) {
            selectProject(e.target.value);
        }
    });

    // Optional: Listen for input changes to handle suggestions
    projectInput.addEventListener('input', function(e) {
        console.log("Input changed:", e.target.value);
    });

        // Fetch and populate users
        const users = await fetchUsers();
        populateUserDropdowns(users);

    // Add event listeners for stage assignment changes
    document.querySelectorAll('[id^="assignTo"]').forEach(select => {
        select.addEventListener('change', function() {
            const stageNum = this.id.replace('assignTo', '');
            handleStageAssignChange(stageNum);
        });
    });

    } catch (error) {
        console.error('Error initializing form:', error);
        showNotification('Error loading form data', 'error');
    }
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

// Update the handleTitleOptionChange function
function handleTitleOptionChange(select, stageNum, substageCount) {
    const customTitleWrapper = select.closest('.title-input-container').querySelector('.custom-title-wrapper');
    const customTitleInput = document.getElementById(`customTitle${stageNum}_${substageCount}`);
    
    if (select.value === 'custom') {
        // Show custom input
        select.closest('.title-dropdown-wrapper').style.display = 'none';
        customTitleWrapper.style.display = 'block';
        customTitleInput.focus();
    } else {
        // Hide custom input if not custom
        customTitleWrapper.style.display = 'none';
    }
}

// Add function to update the actual title value
function updateTitleValue(stageNum, substageCount) {
    const customInput = document.getElementById(`customTitle${stageNum}_${substageCount}`);
    const titleSelect = document.getElementById(`substageTitle${stageNum}_${substageCount}`);
    const customValue = customInput.value.trim();
    
    if (customValue) {
        // Create a new option for the custom value
        const customOption = document.createElement('option');
        customOption.value = customValue; // Use the actual custom text as value
        customOption.textContent = customValue;
        
        // Remove any previous custom option
        titleSelect.querySelectorAll('option[data-custom="true"]').forEach(opt => opt.remove());
        
        // Add the new custom option and select it
        customOption.dataset.custom = 'true';
        titleSelect.insertBefore(customOption, titleSelect.querySelector('option[value="custom"]'));
        titleSelect.value = customValue;
    }
}

// Add function to switch back to dropdown
function switchToDropdown(stageNum, substageCount) {
    const titleContainer = document.querySelector(`#substageTitle${stageNum}_${substageCount}`).closest('.title-input-container');
    const dropdownWrapper = titleContainer.querySelector('.title-dropdown-wrapper');
    const customWrapper = titleContainer.querySelector('.custom-title-wrapper');
    const titleSelect = document.getElementById(`substageTitle${stageNum}_${substageCount}`);
    const customInput = document.getElementById(`customTitle${stageNum}_${substageCount}`);
    
    customWrapper.style.display = 'none';
    dropdownWrapper.style.display = 'block';
    
    // Reset the dropdown to default if no custom value was entered
    if (!customInput.value.trim()) {
        titleSelect.value = '';
    }
    customInput.value = '';
}

// Add this function to populate user dropdowns
function populateUserDropdowns(users) {
    // Get all assign-to select elements
    const assignSelects = document.querySelectorAll('select[id^="assignTo"]');
    
    // Create the default option
    const defaultOption = '<option value="">Select Team Member</option>';
    
    // Create options for each user
    const userOptions = users.map(user => 
        `<option value="${user.id}">${user.username} - ${user.role}</option>`
    ).join('');
    
    // Populate all assign-to selects
    assignSelects.forEach(select => {
        select.innerHTML = defaultOption + userOptions;
    });
}

// Update the handleProjectTitleInput function
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
    const matches = globalProjects.filter(project => 
        project.title.toLowerCase().includes(value)
    );
    
    if (matches.length > 0) {
        // Debug log
        console.log('Matching projects:', matches);
        
        suggestionsContainer.innerHTML = matches.map(project => `
            <div class="suggestion-item" 
                 data-project-id="${project.id}" 
                 onclick="selectProject('${project.id}')">
                <div class="suggestion-title">
                    <i class="fas fa-project-diagram"></i>
                    ${highlightMatch(project.title, value)}
                </div>
                <div class="suggestion-type">
                    <i class="fas ${getProjectTypeIcon(project.project_type)}"></i>
                    ${capitalizeFirstLetter(project.project_type)}
                </div>
            </div>
        `).join('');
        suggestionsContainer.style.display = 'block';
    } else {
        suggestionsContainer.style.display = 'none';
    }
}

// Update the selectProject function to properly handle stage and substage IDs
async function selectProject(projectId) {
    console.log('SelectProject called with ID:', projectId);
    
    const project = globalProjects.find(p => p.id.toString() === projectId.toString());
    
    if (!project) {
        console.error('Project not found:', projectId);
        showNotification('Project not found', 'error');
        return;
    }
    
    try {
        isEditMode = true;
        currentProjectId = parseInt(projectId);
        
        console.log('Loading project for editing:', project);

        // Update form UI
        const submitBtn = document.querySelector('#createProjectForm button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Project';
        document.querySelector('.modal-header h2').textContent = 'Edit Project';
        
        // Fill basic project details
        document.getElementById('projectTitle').value = project.title;
        document.getElementById('projectDescription').value = project.description;
        document.getElementById('projectSuggestions').style.display = 'none';
        
        // Handle project type and category
        const typeOption = document.querySelector(`.type-option[data-type="${project.project_type}"]`);
        if (typeOption) {
            typeOption.click();
            setTimeout(() => {
                const categorySelect = document.getElementById('projectCategory');
                if (categorySelect) {
                    categorySelect.value = project.category_id.toString();
                }
            }, 100);
        }
        
        // Set dates and assigned team member
        if (project.start_date) {
            document.getElementById('startDate').value = formatDateForInput(project.start_date);
        }
        if (project.end_date) {
            document.getElementById('dueDate').value = formatDateForInput(project.end_date);
        }
        if (project.assigned_to) {
            document.getElementById('assignTo').value = project.assigned_to.toString();
        }

        // Handle stages and substages
        const stagesContainer = document.getElementById('stagesContainer');
        
        // Only clear existing stages if we have new ones to load
        if (project.stages && project.stages.length > 0) {
            console.log('Loading stages:', project.stages);
            stagesContainer.innerHTML = '';
            
            for (let stageIndex = 0; stageIndex < project.stages.length; stageIndex++) {
                const stageData = project.stages[stageIndex];
                const stageNum = stageIndex + 1;
                
                // Create new stage
                document.getElementById('addStageBtn').click();
                const stageBlock = stagesContainer.lastElementChild;
                
                if (stageBlock) {
                    // Important: Store the stage ID
                    stageBlock.dataset.stageId = stageData.id;
                    console.log(`Set stage ID ${stageData.id} for stage ${stageNum}`);
                    
                    // Fill stage details
                    const assignSelect = document.getElementById(`assignTo${stageNum}`);
                    const startDateInput = document.getElementById(`startDate${stageNum}`);
                    const dueDateInput = document.getElementById(`dueDate${stageNum}`);

                    if (assignSelect) assignSelect.value = stageData.assigned_to;
                    if (startDateInput) startDateInput.value = formatDateForInput(stageData.start_date);
                    if (dueDateInput) dueDateInput.value = formatDateForInput(stageData.end_date);

                    // Handle substages
                    if (stageData.substages && stageData.substages.length > 0) {
                        console.log(`Loading ${stageData.substages.length} substages for stage ${stageNum}`);
                        
                        for (const substageData of stageData.substages) {
                            // Create new substage
                            const addSubstageBtn = stageBlock.querySelector('.add-substage-btn');
                            if (addSubstageBtn) {
                                addSubstageBtn.click();
                                
                                const substagesContainer = stageBlock.querySelector('.substages-container');
                                const substageBlock = substagesContainer.lastElementChild;
                                
                                if (substageBlock) {
                                    // Important: Store the substage ID
                                    substageBlock.dataset.substageId = substageData.id;
                                    console.log(`Set substage ID ${substageData.id} for substage in stage ${stageNum}`);
                                    
                                    // Fill substage details
                                    const substageNum = substageBlock.dataset.substage;
                                    const titleSelect = document.getElementById(`substageTitle${stageNum}_${substageNum}`);
                                    const assignSelect = document.getElementById(`substageAssignTo${stageNum}_${substageNum}`);
                                    const startDateInput = document.getElementById(`substageStartDate${stageNum}_${substageNum}`);
                                    const dueDateInput = document.getElementById(`substageDueDate${stageNum}_${substageNum}`);

                                    if (titleSelect) {
                                        // Handle custom titles
                                        const title = substageData.title;
                                        if (!titleSelect.querySelector(`option[value="${title}"]`)) {
                                            const customOption = document.createElement('option');
                                            customOption.value = title;
                                            customOption.textContent = title;
                                            customOption.dataset.custom = 'true';
                                            titleSelect.insertBefore(customOption, titleSelect.querySelector('option[value="custom"]'));
                                        }
                                        titleSelect.value = title;
                                    }
                                    
                                    if (assignSelect) assignSelect.value = substageData.assigned_to;
                                    if (startDateInput) startDateInput.value = formatDateForInput(substageData.start_date);
                                    if (dueDateInput) dueDateInput.value = formatDateForInput(substageData.end_date);
                                }
                            }
                        }
                    }
                }
            }
        }

    } catch (error) {
        console.error('Error selecting project:', error);
        showNotification('Error loading project details: ' + error.message, 'error');
    }
}

// Helper function to format date for datetime-local input
function formatDateForInput(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            console.error('Invalid date:', dateString);
            return '';
        }
        return date.toISOString().slice(0, 16); // Format: YYYY-MM-DDThh:mm
    } catch (error) {
        console.error('Error formatting date:', error);
        return '';
    }
}

// Update the getProjectTypeIcon function to handle all project types
function getProjectTypeIcon(type) {
    const icons = {
        'architecture': 'fa-building',
        'interior': 'fa-couch',
        'construction': 'fa-hard-hat'
    };
    return icons[type] || 'fa-project-diagram';
}

// Helper function to highlight matching text
function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Helper function to capitalize first letter
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
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
    const matches = globalProjects.filter(project => 
        project.title.toLowerCase().includes(value)
    );

    // Show suggestions
    if (matches.length > 0) {
        suggestionsContainer.innerHTML = matches.map(project => `
            <div class="suggestion-item" 
                 data-project-id="${project.id}" 
                 onclick="selectProject('${project.id}')">
                <div class="suggestion-title">
                    <i class="fas fa-project-diagram"></i>
                    ${highlightMatch(project.title, value)}
                </div>
                <div class="suggestion-type">
                    <i class="fas ${getProjectTypeIcon(project.project_type)}"></i>
                    ${capitalizeFirstLetter(project.project_type)}
                </div>
            </div>
        `).join('');
        suggestionsContainer.style.display = 'block';
    } else {
        suggestionsContainer.style.display = 'none';
    }
}

// Update the updateProject function
async function updateProject(e, projectId) {
    try {
        const form = e.target;
        const stages = await getStagesData();
        
        // Debug log
        console.log('Updating project with stages:', stages);
        
        const projectData = {
            projectId: projectId,
            projectTitle: form.querySelector('#projectTitle').value,
            projectDescription: form.querySelector('#projectDescription').value,
            projectType: form.querySelector('#projectType').value,
            projectCategory: form.querySelector('#projectCategory').value,
            startDate: form.querySelector('#startDate').value,
            dueDate: form.querySelector('#dueDate').value,
            assignTo: form.querySelector('#assignTo').value,
            stages: stages.map(stage => ({
                ...stage,
                // Ensure we're sending the correct IDs
                id: stage.id || null,
                substages: stage.substages.map(substage => ({
                    ...substage,
                    id: substage.id || null,
                    stage_id: stage.id || null
                }))
            }))
        };

        console.log('Sending update data:', projectData);

        const response = await fetch('ajax_handlers/update_projects.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(projectData)
        });

        const result = await response.json();
        console.log('Update response:', result);
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to update project');
        }
        
        return result;
    } catch (error) {
        console.error('Error in updateProject:', error);
        throw error;
    }
}

// Update getStagesData function to properly handle IDs
async function getStagesData() {
    const stages = [];
    const stageBlocks = document.querySelectorAll('.stage-block');
    
    console.log('Getting data for stages:', stageBlocks.length);
    
    for (let i = 0; i < stageBlocks.length; i++) {
        const stageBlock = stageBlocks[i];
        const stageNum = stageBlock.dataset.stage; // Use the correct stage number from dataset
        
        // Get existing stage ID if it exists
        const stageId = stageBlock.dataset.stageId || null;
        console.log(`Processing stage ${stageNum}, ID:`, stageId);
        
        // Add null checks for all element selections
        const assignToElement = document.getElementById(`assignTo${stageNum}`);
        const startDateElement = document.getElementById(`startDate${stageNum}`);
        const dueDateElement = document.getElementById(`dueDate${stageNum}`);
        
        // Debug logs
        console.log('Elements found:', {
            assignTo: assignToElement,
            startDate: startDateElement,
            dueDate: dueDateElement
        });
        
        const stageData = {
            id: stageId,
            stage_number: parseInt(stageNum),
            assignTo: assignToElement ? assignToElement.value : null,
            startDate: startDateElement ? startDateElement.value : null,
            dueDate: dueDateElement ? dueDateElement.value : null,
            files: await getStageFiles(stageNum),
            substages: []
        };
        
        // Get substages
        const substageBlocks = stageBlock.querySelectorAll('.substage-block');
        console.log(`Found ${substageBlocks.length} substages for stage ${stageNum}`);
        
        for (let j = 0; j < substageBlocks.length; j++) {
            const substageBlock = substageBlocks[j];
            const substageNum = substageBlock.dataset.substage;
            const substageId = substageBlock.dataset.substageId || null;
            
            console.log(`Processing substage ${substageNum}, ID:`, substageId);
            
            // Add null checks for substage elements
            const titleElement = document.getElementById(`substageTitle${stageNum}_${substageNum}`);
            const substageAssignElement = document.getElementById(`substageAssignTo${stageNum}_${substageNum}`);
            const substageStartElement = document.getElementById(`substageStartDate${stageNum}_${substageNum}`);
            const substageDueElement = document.getElementById(`substageDueDate${stageNum}_${substageNum}`);
            
            // Debug logs for substage elements
            console.log('Substage elements found:', {
                title: titleElement,
                assign: substageAssignElement,
                start: substageStartElement,
                due: substageDueElement
            });
            
            const substageData = {
                id: substageId,
                substage_number: j + 1,
                stage_id: stageId,
                title: titleElement ? titleElement.value : null,
                assignTo: substageAssignElement ? substageAssignElement.value : null,
                startDate: substageStartElement ? substageStartElement.value : null,
                dueDate: substageDueElement ? substageDueElement.value : null,
                files: await getSubstageFiles(stageNum, substageNum)
            };
            
            stageData.substages.push(substageData);
        }
        
        stages.push(stageData);
    }
    
    console.log('Final stages data:', stages);
    return stages;
}

// Update the updateSubstageElements function
function updateSubstageElements(substageBlock, stageNum, substageNum) {
    // Implementation of updateSubstageElements function
}