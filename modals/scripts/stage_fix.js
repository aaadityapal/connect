/**
 * Stage Assignment Fix Script
 * This script specifically addresses the issue where stage assignments don't show correctly
 * when editing projects due to user option mismatches.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Loading stage assignment fix script...');
    
    // Wait for project form to be loaded
    const checkFormInterval = setInterval(() => {
        const projectForm = document.getElementById('createProjectForm');
        if (projectForm) {
            clearInterval(checkFormInterval);
            console.log('Project form found, initializing fix...');
            initializeFix();
        }
    }, 100);
    
    function initializeFix() {
        // Override the populateUserDropdowns function to ensure it includes all necessary users
        const originalPopulateUserDropdowns = window.populateUserDropdowns;
        
        // Fix for the populateUserDropdowns function
        window.populateUserDropdowns = function(users) {
            console.log('Enhanced populateUserDropdowns called with users:', users);
            
            // First, call the original function
            if (typeof originalPopulateUserDropdowns === 'function') {
                originalPopulateUserDropdowns(users);
            }
            
            // Then check if all project assignments are in the dropdown options
            ensureAllAssignmentsAreInDropdowns();
        };
        
        // Ensure all project assignments are available in dropdowns
        async function ensureAllAssignmentsAreInDropdowns() {
            console.log('Ensuring all assignments are in dropdowns...');
            
            try {
                // Fetch all unique user IDs used in projects
                const response = await fetch('api/get_all_project_assignments.php');
                const result = await response.json();
                
                if (result.status === 'success' && result.data && result.data.length) {
                    const assignedUserIds = result.data;
                    console.log('Found assigned user IDs:', assignedUserIds);
                    
                    // Get all assign-to select elements
                    const assignSelects = document.querySelectorAll('select[id^="assignTo"]');
                    
                    // For each dropdown
                    assignSelects.forEach(select => {
                        // Check if all assigned users are in the dropdown
                        assignedUserIds.forEach(userId => {
                            // Skip if userId is null, empty or "0"
                            if (!userId || userId === "0") return;
                            
                            // Check if this user ID exists in options
                            const userExists = Array.from(select.options).some(option => 
                                option.value === userId.toString()
                            );
                            
                            // If user doesn't exist in options, add a placeholder option
                            if (!userExists) {
                                console.log(`Adding missing user ID ${userId} to dropdown ${select.id}`);
                                const newOption = document.createElement('option');
                                newOption.value = userId.toString();
                                newOption.text = `User ID ${userId}`;
                                newOption.classList.add('missing-user');
                                select.appendChild(newOption);
                            }
                        });
                    });
                    
                    // Apply current selections again if we're in edit mode
                    if (window.isEditMode && window.currentProjectId) {
                        console.log('Re-applying selections for edit mode');
                        applySelectionsFromCurrentProject();
                    }
                }
            } catch (error) {
                console.error('Error ensuring assignments:', error);
            }
        }
        
        // Apply selections based on current project data
        async function applySelectionsFromCurrentProject() {
            if (!window.currentProjectId) return;
            
            try {
                // Fetch current project data
                const response = await fetch(`api/get_project_details_fixed.php?id=${window.currentProjectId}`);
                const result = await response.json();
                
                if (result.status === 'success' && result.data) {
                    const project = result.data;
                    console.log('Re-applying project data:', project);
                    
                    // Main project assignment
                    if (project.assigned_to) {
                        const mainAssignSelect = document.getElementById('assignTo');
                        if (mainAssignSelect) {
                            console.log('Setting main project assigned_to:', project.assigned_to);
                            mainAssignSelect.value = project.assigned_to.toString();
                        }
                    }
                    
                    // Stage assignments
                    if (project.stages && project.stages.length) {
                        project.stages.forEach((stage, index) => {
                            const stageNum = index + 1;
                            const stageAssignSelect = document.getElementById(`assignTo${stageNum}`);
                            
                            if (stageAssignSelect && stage.assigned_to) {
                                console.log(`Setting stage ${stageNum} assigned_to:`, stage.assigned_to);
                                stageAssignSelect.value = stage.assigned_to.toString();
                                
                                // Highlight the selection to make it visible
                                stageAssignSelect.style.backgroundColor = '#e8f0fe';
                                setTimeout(() => {
                                    stageAssignSelect.style.backgroundColor = '';
                                }, 2000);
                            }
                            
                            // Substage assignments
                            if (stage.substages && stage.substages.length) {
                                stage.substages.forEach((substage, subIndex) => {
                                    const substageNum = subIndex + 1;
                                    const substageAssignSelect = document.getElementById(`substageAssignTo${stageNum}_${substageNum}`);
                                    
                                    if (substageAssignSelect && substage.assigned_to) {
                                        console.log(`Setting substage ${stageNum}_${substageNum} assigned_to:`, substage.assigned_to);
                                        substageAssignSelect.value = substage.assigned_to.toString();
                                        
                                        // Highlight the selection
                                        substageAssignSelect.style.backgroundColor = '#e8f0fe';
                                        setTimeout(() => {
                                            substageAssignSelect.style.backgroundColor = '';
                                        }, 2000);
                                    }
                                });
                            }
                        });
                    }
                }
            } catch (error) {
                console.error('Error applying selections:', error);
            }
        }
        
        // Add styles for missing users
        const style = document.createElement('style');
        style.textContent = `
            .missing-user {
                background-color: #fff8e1;
                font-style: italic;
                border-left: 3px solid #ffc107;
            }
        `;
        document.head.appendChild(style);
        
        // Listen for add stage events
        document.addEventListener('stage-added', function() {
            setTimeout(() => {
                ensureAllAssignmentsAreInDropdowns();
            }, 200);
        });
        
        // Initial check
        ensureAllAssignmentsAreInDropdowns();
        
        console.log('Stage assignment fix initialized');
    }
});

// Define createStage function
function createStage(stageNum) {
    return `
        <div class="stage-block" data-stage="${stageNum}">
            <div class="stage-header">
                <h3><i class="fas fa-layer-group"></i> Stage ${stageNum}</h3>
                <button type="button" class="delete-stage" onclick="deleteStage(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group">
                <label for="stageTitle${stageNum}">
                    <i class="fas fa-heading"></i>
                    Stage Title
                </label>
                <input type="text" id="stageTitle${stageNum}" name="stages[${stageNum}][title]" placeholder="Enter stage title" required>
            </div>
            <div class="form-group">
                <label for="assignTo${stageNum}">
                    <i class="fas fa-user-plus"></i>
                    Assign To
                </label>
                <select id="assignTo${stageNum}" name="stages[${stageNum}][assignTo]" onchange="handleStageAssignChange(${stageNum})" required>
                    <option value="0" selected>Unassigned</option>
                </select>
            </div>
            <div class="form-dates">
                <div class="form-group">
                    <label for="startDate${stageNum}">
                        <i class="fas fa-calendar-plus"></i>
                        Start Date
                    </label>
                    <input type="date" id="startDate${stageNum}" name="stages[${stageNum}][startDate]" required>
                </div>
                <div class="form-group">
                    <label for="dueDate${stageNum}">
                        <i class="fas fa-calendar-check"></i>
                        Due Date
                    </label>
                    <input type="date" id="dueDate${stageNum}" name="stages[${stageNum}][dueDate]" required>
                </div>
            </div>
            <div class="substages-container" id="substagesContainer${stageNum}">
                <!-- Substages will be inserted here -->
            </div>
            <button type="button" class="add-substage-btn" onclick="addSubstage(${stageNum})">
                <i class="fas fa-plus"></i>
                Add Task
            </button>
            <div class="stage-toggle-next" id="stageToggleNext${stageNum}" style="display: none;">
                <button type="button" class="toggle-next-stage-btn" onclick="toggleNextStage(${stageNum})">
                    <i class="fas fa-chevron-down"></i>
                    <span>Show Next Stage</span>
                </button>
            </div>
        </div>
    `;
}

// Fix for the populateUserDropdowns function
function populateUserDropdowns() {
    // Safety check for globalUsers
    if (!Array.isArray(globalUsers)) {
        console.warn('Global users is not an array:', globalUsers);
        return;
    }
    
    // Get all assign-to selects
    const assignSelects = document.querySelectorAll('select[id^="assignTo"]');
    if (!assignSelects || assignSelects.length === 0) {
        console.warn('No assign selects found');
        return;
    }
    
    console.log('Populating dropdown for', assignSelects.length, 'selects with', globalUsers.length, 'users');
    
    // Create options HTML
    let userOptionsHtml = '';
    if (globalUsers.length > 0) {
        userOptionsHtml = globalUsers.map(user => 
            `<option value="${user.id}">${user.username} - ${user.role}</option>`
        ).join('');
    }
    
    // Add options to selects
    assignSelects.forEach(select => {
        // Keep the current selection if any
        const currentValue = select.value;
        
        // Update HTML with default option and user options
        select.innerHTML = `<option value="0" selected>Unassigned</option>${userOptionsHtml}`;
        
        // Restore selection if valid
        if (currentValue && currentValue !== '0') {
            select.value = currentValue;
        }
    });
}

// To implement this fix:
// 1. Include this file in project_form.php after the main script:
//    <script src="modals/scripts/stage_fix.js"></script>
//
// 2. Fix any references to the createStage function in the main script
