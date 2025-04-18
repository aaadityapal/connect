

// This function should be added to ensure proper handling of project suggestions
function handleSuggestionClick(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const projectId = this.dataset.projectId;
    console.log('Suggestion clicked directly, project ID:', projectId);
    
    // Hide suggestions
    const suggestionsContainer = document.getElementById('projectSuggestions');
    if (suggestionsContainer) {
        suggestionsContainer.style.display = 'none';
    }
    
    // Call selectProject with a short delay to ensure DOM is ready
    setTimeout(() => {
        selectProjectDirectly(projectId);
    }, 10);
    
    return false;
}

// A direct function to select projects that ensures stages are visible
function selectProjectDirectly(projectId) {
    console.log('Selecting project directly, ID:', projectId);
    
    // Make sure the modal is visible
    const modal = document.getElementById('projectModal');
    if (modal) {
        if (modal.style.display !== 'flex') {
            console.log('Opening modal before loading project');
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }
    
    // Get the project data
    const project = globalProjects.find(p => p.id.toString() === projectId.toString());
    if (!project) {
        console.error('Project not found:', projectId);
        return;
    }
    
    // Clear the form and stagesContainer
    const stagesContainer = document.getElementById('stagesContainer');
    if (stagesContainer) {
        stagesContainer.innerHTML = '';
    }
    
    // Set edit mode and project ID
    isEditMode = true;
    currentProjectId = parseInt(projectId);
    
    // Update UI for edit mode
    const submitBtn = document.querySelector('#createProjectForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Project';
    }
    
    const headerTitle = document.querySelector('.modal-header h2');
    if (headerTitle) {
        headerTitle.textContent = 'Edit Project';
    }
    
    // Fill basic project details
    const titleInput = document.getElementById('projectTitle');
    const descInput = document.getElementById('projectDescription');
    
    if (titleInput) titleInput.value = project.title;
    if (descInput) descInput.value = project.description;
    
    // Handle project type and category
    const typeOption = document.querySelector(`.type-option[data-type="${project.project_type}"]`);
    if (typeOption) {
        typeOption.click();
        setTimeout(() => {
            const categorySelect = document.getElementById('projectCategory');
            if (categorySelect && project.category_id) {
                categorySelect.value = project.category_id.toString();
            }
        }, 100);
    }
    
    // Set dates and assignment
    const startDateInput = document.getElementById('startDate');
    const dueDateInput = document.getElementById('dueDate');
    const assignSelect = document.getElementById('assignTo');
    
    if (startDateInput && project.start_date) {
        startDateInput.value = formatDateForInput(project.start_date);
    }
    
    if (dueDateInput && project.end_date) {
        dueDateInput.value = formatDateForInput(project.end_date);
    }
    
    if (assignSelect && project.assigned_to) {
        assignSelect.value = project.assigned_to.toString();
    }
    
    // Now add stages
    if (project.stages && project.stages.length > 0) {
        // Add the first stage explicitly
        const firstStageData = project.stages[0];
        addStage(false); // First stage should be visible
        
        // Get the newly created stage
        const firstStage = stagesContainer.querySelector('.stage-block');
        if (firstStage) {
            // Ensure it's visible
            firstStage.style.display = 'block';
            
            // Store the stage ID
            firstStage.dataset.stageId = firstStageData.id;
            
            // Fill in stage details
            const titleInput = firstStage.querySelector(`#stageTitle1`);
            const assignSelect = firstStage.querySelector(`#assignTo1`);
            const startDateInput = firstStage.querySelector(`#startDate1`);
            const dueDateInput = firstStage.querySelector(`#dueDate1`);
            
            if (titleInput && firstStageData.title) titleInput.value = firstStageData.title;
            if (assignSelect && firstStageData.assigned_to) assignSelect.value = firstStageData.assigned_to;
            if (startDateInput && firstStageData.start_date) startDateInput.value = formatDateForInput(firstStageData.start_date);
            if (dueDateInput && firstStageData.end_date) dueDateInput.value = formatDateForInput(firstStageData.end_date);
            
            // Add substages
            if (firstStageData.substages && firstStageData.substages.length > 0) {
                const substagesContainer = firstStage.querySelector('.substages-container');
                if (substagesContainer) {
                    substagesContainer.style.display = 'none'; // Keep substages hidden initially
                    
                    // Now add the Toggle Next Stage button if needed
                    if (project.stages.length > 1) {
                        const stageToggleNext = firstStage.querySelector('#stageToggleNext1');
                        if (stageToggleNext) {
                            stageToggleNext.style.display = 'block';
                        }
                    }
                }
            }
        }
        
        // Add the remaining stages (hidden)
        for (let i = 1; i < project.stages.length; i++) {
            const stageData = project.stages[i];
            const stageNum = i + 1;
            
            addStage(true); // Keep it hidden
            
            // Get the newly created stage
            const stageBlock = stagesContainer.querySelector(`.stage-block[data-stage="${stageNum}"]`);
            if (stageBlock) {
                // Ensure it's hidden
                stageBlock.style.display = 'none';
                
                // Store the stage ID
                stageBlock.dataset.stageId = stageData.id;
                
                // Fill in stage details
                const titleInput = stageBlock.querySelector(`#stageTitle${stageNum}`);
                const assignSelect = stageBlock.querySelector(`#assignTo${stageNum}`);
                const startDateInput = stageBlock.querySelector(`#startDate${stageNum}`);
                const dueDateInput = stageBlock.querySelector(`#dueDate${stageNum}`);
                
                if (titleInput && stageData.title) titleInput.value = stageData.title;
                if (assignSelect && stageData.assigned_to) assignSelect.value = stageData.assigned_to;
                if (startDateInput && stageData.start_date) startDateInput.value = formatDateForInput(stageData.start_date);
                if (dueDateInput && stageData.end_date) dueDateInput.value = formatDateForInput(stageData.end_date);
            }
        }
    }
    
    // Final check to ensure first stage is visible
    const firstStage = stagesContainer.querySelector('.stage-block[data-stage="1"]');
    if (firstStage) {
        console.log('Final visibility check for first stage');
        firstStage.style.display = 'block';
    }
}

// To implement this fix:
// 1. Add these functions to project_form_handler_v1.js
// 2. Modify handleInput function to add click handlers for suggestion items:
/*
    // Add a direct click event listener to ensure proper handling
    const suggestionItems = suggestionsContainer.querySelectorAll('.suggestion-item');
    suggestionItems.forEach(item => {
        item.removeEventListener('click', handleSuggestionClick);
        item.addEventListener('click', handleSuggestionClick);
    });
*/
// 3. Change the onclick attribute in the suggestion HTML to call selectProjectDirectly:
/*
    <div class="suggestion-item" 
         data-project-id="${project.id}" 
         onclick="selectProjectDirectly('${project.id}')">
*/
