<div class="modal-overlay" id="projectModal">
    <div class="modal-container" data-theme="default">
        <div class="modal-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="header-text">
                    <h2>Create New Project</h2>
                    <p class="project-type-label">New Project</p>
                </div>
            </div>
            <button type="button" class="close-modal" id="closeModal" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Toggle buttons -->
        <div class="task-type-toggle">
            <div class="toggle-container">
                <input type="radio" id="createTask" name="taskType" value="create" checked>
                <label for="createTask">Create Task</label>
                <input type="radio" id="backOfficeTask" name="taskType" value="backoffice">
                <label for="backOfficeTask">Back Office Task</label>
                <span class="slider"></span>
            </div>
        </div>

        <!-- Project Form -->
        <form id="createProjectForm" class="modal-form">
            <div class="form-group">
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
                           autocomplete="off">
                    <div class="suggestions-container" id="projectSuggestions"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="projectDescription">
                    <i class="fas fa-align-left"></i>
                    Project Description
                </label>
                <textarea id="projectDescription" name="projectDescription" required 
                          placeholder="Enter project description"></textarea>
            </div>

            <div class="form-group project-type-group">
                <label for="projectType">
                    <i class="fas fa-building"></i>
                    Project Type
                </label>
                <div class="type-selector">
                    <div class="type-option" data-type="architecture">
                        <i class="fas fa-landmark"></i>
                        <span>Architecture</span>
                    </div>
                    <div class="type-option" data-type="interior">
                        <i class="fas fa-couch"></i>
                        <span>Interior</span>
                    </div>
                    <div class="type-option" data-type="construction">
                        <i class="fas fa-hard-hat"></i>
                        <span>Construction</span>
                    </div>
                </div>
                <input type="hidden" id="projectType" name="projectType" required>
            </div>

            <div class="form-group">
                <label for="projectCategory">
                    <i class="fas fa-tags"></i>
                    Project Category
                </label>
                <select id="projectCategory" name="projectCategory" required disabled>
                    <option value="">Select Project Type First</option>
                </select>
            </div>

            <div class="form-dates">
                <div class="form-group">
                    <label for="startDate">
                        <i class="fas fa-calendar-plus"></i>
                        Start Date
                    </label>
                    <input type="datetime-local" id="startDate" name="startDate" required>
                </div>

                <div class="form-group">
                    <label for="dueDate">
                        <i class="fas fa-calendar-check"></i>
                        Due Date
                    </label>
                    <input type="datetime-local" id="dueDate" name="dueDate" required>
                </div>
            </div>

            <div class="form-group">
                <label for="assignTo">
                    <i class="fas fa-user-plus"></i>
                    Assign To
                </label>
                <select id="assignTo" name="assignTo" required>
                    <option value="0" selected>Unassigned</option>
                    <!-- Users will be populated dynamically -->
                </select>
            </div>

            <div class="stages-container" id="stagesContainer">
                <!-- Stages will be added here dynamically -->
            </div>

            <button type="button" class="add-stage-btn" id="addStageBtn">
                <i class="fas fa-plus"></i>
                Add Stage
            </button>

            <div class="form-actions">
                <button type="button" class="btn-secondary" id="cancelProject">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Project
                </button>
            </div>
        </form>

        <!-- Back Office Form -->
        <form id="backOfficeForm" class="back-office-form" style="display: none;">
            <div class="form-group">
                <label for="backOfficeTitle">
                    <i class="fas fa-heading"></i>
                    Task Title
                </label>
                <div class="autocomplete-wrapper">
                    <input type="text" 
                           id="backOfficeTitle" 
                           name="backOfficeTitle" 
                           required 
                           placeholder="Enter task title"
                           autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label for="backOfficeDescription">
                    <i class="fas fa-align-left"></i>
                    Task Description
                </label>
                <textarea id="backOfficeDescription" 
                          name="backOfficeDescription" 
                          required 
                          placeholder="Enter task description"></textarea>
            </div>

            <div class="form-dates">
                <div class="form-group">
                    <label for="backOfficeStartDate">
                        <i class="fas fa-calendar-plus"></i>
                        Start Date
                    </label>
                    <input type="datetime-local" id="backOfficeStartDate" name="backOfficeStartDate" required>
                </div>

                <div class="form-group">
                    <label for="backOfficeDueDate">
                        <i class="fas fa-calendar-check"></i>
                        Due Date
                    </label>
                    <input type="datetime-local" id="backOfficeDueDate" name="backOfficeDueDate" required>
                </div>
            </div>

            <div class="form-group">
                <label for="backOfficeAssignTo">
                    <i class="fas fa-user-plus"></i>
                    Assign To
                </label>
                <select id="backOfficeAssignTo" name="backOfficeAssignTo" required>
                    <option value="0" selected>Unassigned</option>
                    <!-- Users will be populated dynamically -->
                </select>
            </div>

            <div class="stages-container" id="backOfficeStagesContainer">
                <!-- Stages will be added here dynamically -->
            </div>

            <button type="button" class="add-stage-btn" id="backOfficeAddStageBtn">
                <i class="fas fa-plus"></i>
                Add Stage
            </button>

            <div class="form-actions">
                <button type="button" class="btn-secondary" id="cancelBackOffice">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Task
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast container should be outside the modal -->
<div id="toastContainer" class="toast-container"></div>

<!-- Add the stage fix script -->
<script src="modals/scripts/stage_fix.js"></script>
<!-- Add the direct assignment fix script -->
<script src="modals/scripts/direct_assign_fix.js"></script>