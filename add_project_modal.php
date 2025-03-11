<div id="addProjectModal" class="modal">
    <div class="modal-content" data-modal="addProject">
        <div class="modal-header">
            <div class="header-content">
                <h2>Add New Project</h2>
                <div class="category-indicator" style="display: none;">
                    <span class="category-name"></span>
                </div>
            </div>
            <button class="close-modal" data-close-modal="addProject">&times;</button>
        </div>
        <form id="addProjectForm">
            <div class="form-group">
                <label for="projectTitle">Project Title*</label>
                <div class="project-title-wrapper">
                    <input type="text" id="projectTitle" name="projectTitle" required>
                    <div id="projectSuggestions" class="project-suggestions"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="projectDescription">Project Description*</label>
                <textarea id="projectDescription" name="projectDescription" rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label for="projectType">Project Type*</label>
                <select id="projectCategory" name="projectCategory" required>
                    <option value="">Select Type</option>
                </select>
                <small class="help-text"></small>
            </div>

            <div class="form-group">
                <label for="projectCategory">Project Category*</label>
                <select id="projectType" name="projectType" required>
                    <option value="">Select Category</option>
                </select>
                <small class="help-text"></small>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label for="startDate">Start Date*</label>
                    <input type="datetime-local" 
                           id="startDate" 
                           name="startDate" 
                           required 
                           value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                <div class="form-group half">
                    <label for="dueDate">Due Date*</label>
                    <input type="datetime-local" 
                           id="dueDate" 
                           name="dueDate" 
                           required 
                           value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="assignTo">Assign To*</label>
                <select id="assignTo" name="assignTo" required>
                    <option value="">Select Team Member</option>
                </select>
            </div>

            <div class="stages-container">
                <div id="stagesWrapper">
                    <!-- Stages will be added here dynamically -->
                </div>
                <button type="button" id="addStageBtn" class="add-stage-btn">
                    <i class="fas fa-plus"></i> Add Stage
                </button>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary cancel-modal">Cancel</button>
                <button type="submit" class="btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>