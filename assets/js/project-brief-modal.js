/**
 * Project Brief Modal JavaScript
 * This file handles the functionality of the modal that appears when a user clicks
 * on a project in the project calendar modal
 */

class ProjectBriefModal {
    constructor() {
        this.modalOverlay = null;
        this.modalContainer = null;
        this.currentProjectId = null;
        this.isOpen = false;
        this.currentUserId = this.getCurrentUserId();
        this.isAdmin = this.isAdminUser();
        this.initEventListeners();
        this.injectStyles();
    }

    // Inject CSS styles for the modal components
    injectStyles() {
        // Check if styles are already injected
        if (document.getElementById('project-brief-modal-styles')) {
            return;
        }
        
        // Create style element
        const styleEl = document.createElement('style');
        styleEl.id = 'project-brief-modal-styles';
        
        // Define styles
        styleEl.textContent = `
            .project_brief_status_select select {
                padding: 5px 8px;
                border-radius: 4px;
                border: 1px solid #ddd;
                background-color: white;
                font-size: 14px;
                min-width: 150px;
                color: #333;
            }
            
            .project_brief_status_select select:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            }
        `;
        
        // Append style element to document head
        document.head.appendChild(styleEl);
    }

    // Get current user ID from the document
    getCurrentUserId() {
        const userIdElement = document.querySelector('meta[name="user-id"]');
        return userIdElement ? userIdElement.getAttribute('content') : null;
    }

    // Get current user role from the document
    getCurrentUserRole() {
        const userRoleElement = document.querySelector('meta[name="user-role"]');
        return userRoleElement ? userRoleElement.getAttribute('content') : null;
    }

    // Check if current user is an admin
    isAdminUser() {
        const userRole = this.getCurrentUserRole();
        return userRole === 'admin';
    }

    // Initialize event listeners
    initEventListeners() {
        // This will be called automatically via constructor
        // Listen for project clicks in the calendar modal
        document.addEventListener('click', (e) => {
            const projectItem = e.target.closest('.pr-calendar-modal-item.project-item');
            if (projectItem && !e.target.closest('.pr-calendar-toggle-btn')) {
                e.preventDefault();
                e.stopPropagation();
                
                const projectId = projectItem.dataset.projectId;
                if (projectId) {
                    this.openProjectModal(projectId);
                }
            }
        });
    }

    // Open the project modal
    async openProjectModal(projectId) {
        this.currentProjectId = projectId;
        
        try {
            console.log('Opening project modal for ID:', projectId);
            
            // Show loading indicator or placeholder
            this.showLoadingIndicator();
            
            const data = await this.fetchProjectData(projectId);
            if (data.success) {
                console.log('Successfully fetched project data');
                this.renderProjectModal(data.project);
            } else {
                console.error('Error fetching project data:', data.message);
                
                // Show error message to user
                this.showErrorModal(data.message || 'Failed to load project details');
                
                // Fall back to direct page navigation if needed
                setTimeout(() => {
                    if (confirm('Would you like to view the project details page instead?')) {
                        window.location.href = `project-details.php?id=${projectId}`;
                    }
                }, 2000);
            }
        } catch (error) {
            console.error('Exception in openProjectModal:', error);
            this.showErrorModal('An unexpected error occurred');
        }
    }

    // Fetch project data from the server
    async fetchProjectData(projectId) {
        try {
            console.log('Fetching project data for ID:', projectId);
            
            const response = await fetch('get_project_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId
                })
            });
            
            // Log response status
            console.log('Response status:', response.status);
            
            // Get response text first to debug potential JSON parsing issues
            const responseText = await response.text();
            console.log('Response text:', responseText);
            
            // Try to parse the response as JSON
            let jsonData;
            try {
                jsonData = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                throw new Error(`Failed to parse response as JSON: ${responseText.substring(0, 200)}...`);
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}, Message: ${jsonData.message || 'Unknown error'}`);
            }
            
            return jsonData;
        } catch (error) {
            console.error('Error fetching project data:', error);
            return { 
                success: false, 
                message: error.message || 'Failed to fetch project data',
                error: error.toString()
            };
        }
    }

    // Render the project modal
    renderProjectModal(project) {
        // Store the current project data for reference
        this.currentProject = project;
        
        const modalHtml = this.createProjectModalHtml(project);
        this.showModal(modalHtml);
        this.setupModalEventListeners();
    }

    // Create HTML for the project modal
    createProjectModalHtml(project) {
        // Format dates for display
        const startDate = this.formatDate(project.start_date);
        const endDate = this.formatDate(project.end_date);
        const isPastDue = this.isPastDue(project.end_date);
        
        // Ensure project has all required properties
        project.stages = project.stages || [];
        project.team_members = project.team_members || [];
        
        // Create project modal HTML
        return `
            <div class="project_brief_modal_container">
                <div class="project_brief_modal_header">
                    <h3 class="project_brief_modal_title">
                        <i class="fas fa-building"></i>
                        ${this.escapeHtml(project.title || 'Project Details')}
                    </h3>
                    <button class="project_brief_modal_close">&times;</button>
                </div>
                
                <div class="project_brief_modal_content">
                    ${project.description ? `
                    <!-- Description Section -->
                    <div class="project_brief_card">
                        <div class="project_brief_card_header">
                            <i class="fas fa-align-left"></i> Description
                        </div>
                        <div class="project_brief_card_content">
                            <div class="project_brief_description">
                                ${project.description}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Project Information Section -->
                    <div class="project_brief_card">
                        <div class="project_brief_card_header">
                            <i class="fas fa-project-diagram"></i> Project Information
                        </div>
                        <div class="project_brief_card_content">
                            <div class="project_brief_info_grid">
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="project_brief_info_label">Project Title</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.title || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-user-check"></i></div>
                                    <div class="project_brief_info_label">Assigned By</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.created_by_name || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-calendar-plus"></i></div>
                                    <div class="project_brief_info_label">Start Date</div>
                                    <div class="project_brief_info_value">${startDate}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-calendar-times"></i></div>
                                    <div class="project_brief_info_label">End Date</div>
                                    <div class="project_brief_info_value">
                                        ${endDate}
                                        ${isPastDue ? '<span class="project_brief_overdue">(Overdue)</span>' : ''}
                                    </div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-user-tie"></i></div>
                                    <div class="project_brief_info_label">Project Assigned</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.assigned_to_name || 'Not assigned')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-tag"></i></div>
                                    <div class="project_brief_info_label">Status</div>
                                    <div class="project_brief_info_value">
                                        <span class="project_brief_status_badge ${project.status || 'pending'}">${this.formatStatus(project.status)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Client Information Section -->
                    <div class="project_brief_card">
                        <div class="project_brief_card_header">
                            <i class="fas fa-user"></i> Client Information
                        </div>
                        <div class="project_brief_card_content">
                            <div class="project_brief_info_grid">
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-id-card"></i></div>
                                    <div class="project_brief_info_label">Client Name</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.client_name || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-phone"></i></div>
                                    <div class="project_brief_info_label">Contact Number</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.contact_number || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="project_brief_info_label">Client Address</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.client_address || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-map-pin"></i></div>
                                    <div class="project_brief_info_label">Project Location</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.project_location || 'Not specified')}</div>
                                </div>
                                
                                <div class="project_brief_info_item">
                                    <div class="project_brief_info_icon"><i class="fas fa-ruler-combined"></i></div>
                                    <div class="project_brief_info_label">Plot Area</div>
                                    <div class="project_brief_info_value">${this.escapeHtml(project.plot_area || 'Not specified')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Team Section -->
                    <div class="project_brief_card">
                        <div class="project_brief_card_header">
                            <i class="fas fa-users"></i> Project Team
                        </div>
                        <div class="project_brief_card_content">
                            ${this.renderTeamMembers(project.team_members)}
                        </div>
                    </div>
                    
                    <!-- Stages Section -->
                    <div class="project_brief_card">
                        <div class="project_brief_card_header">
                            <i class="fas fa-tasks"></i> Project Stages
                        </div>
                        <div class="project_brief_card_content">
                            ${this.renderStagesList(project.stages)}
                        </div>
                    </div>
                    
                    <div class="project_brief_actions">
                        <a href="project-details.php?id=${project.id}" class="project_brief_view_full_btn">
                            <i class="fas fa-external-link-alt"></i> View Full Project
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    // Render the list of stages
    renderStagesList(stages) {
        if (stages.length === 0) {
            return '<div class="project_brief_empty_state">No stages available for this project.</div>';
        }
        
        return `
            <div class="project_brief_stages_list">
                ${stages.map(stage => {
                    const startDate = this.formatDate(stage.start_date);
                    const endDate = this.formatDate(stage.end_date);
                    const isPastDue = this.isPastDue(stage.end_date);
                    
                    // Check if stage has substages
                    const hasSubstages = stage.substages && stage.substages.length > 0;
                    
                    return `
                        <div class="project_brief_stage_item" data-stage-id="${stage.id}">
                            <div class="project_brief_stage_header">
                                <h5 class="project_brief_stage_title">
                                    Stage ${stage.stage_number}: ${this.escapeHtml(stage.title)}
                                    ${hasSubstages ? `<span class="substage_count_badge">${stage.substages.length}</span>` : ''}
                                </h5>
                                <div class="project_brief_status_badge ${stage.status}">${this.formatStatus(stage.status)}</div>
                            </div>
                            
                            <div class="project_brief_stage_meta">
                                <span><i class="far fa-calendar-alt"></i> ${startDate} - ${endDate} ${isPastDue ? '<span class="overdue">(Overdue)</span>' : ''}</span>
                                <span><i class="far fa-user"></i> ${stage.assigned_to_name || 'Unassigned'}</span>
                            </div>
                            
                            <div class="project_brief_stage_actions">
                                <button class="project_brief_stage_view_btn" data-stage-id="${stage.id}">
                                    <i class="fas fa-external-link-alt"></i> View Details
                                </button>
                                <button class="project_brief_stage_action_btn chat" title="Chat" data-stage-id="${stage.id}">
                                    <i class="far fa-comment-alt"></i>
                                </button>
                                <button class="project_brief_stage_action_btn activity" title="Activity Log" data-stage-id="${stage.id}">
                                    <i class="fas fa-history"></i>
                                </button>
                                ${hasSubstages ? `
                                <button class="project_brief_substage_toggle_btn" aria-label="Toggle substages">
                                    <i class="fas fa-chevron-down"></i>
                                </button>` : ''}
                            </div>
                            
                            ${hasSubstages ? `
                            <div class="project_brief_substages_container">
                                <div class="project_brief_substages_list">
                                    ${stage.substages.map(substage => {
                                        return `
                                            <div class="project_brief_substage_item">
                                                <div class="project_brief_substage_title">
                                                    <span class="substage_number">Substage ${substage.substage_number}</span>
                                                    <span class="substage_title">${this.escapeHtml(substage.title)}</span>
                                                </div>
                                                <div class="project_brief_substage_meta">
                                                    <div class="project_brief_substage_date">
                                                        <i class="far fa-calendar-alt"></i> ${this.formatDate(substage.end_date)}
                                                        ${this.isPastDue(substage.end_date) ? '<span class="overdue">(Overdue)</span>' : ''}
                                                    </div>
                                                    <div class="project_brief_substage_assignee">
                                                        <i class="far fa-user"></i> ${substage.assigned_to_name || 'Unassigned'}
                                                    </div>
                                                </div>
                                                <div class="project_brief_substage_footer">
                                                    <div class="project_brief_substage_status">
                                                        <span class="project_brief_status_badge ${substage.status}">${this.formatStatus(substage.status)}</span>
                                                    </div>
                                                    <div class="project_brief_substage_actions">
                                                        <button class="project_brief_substage_action_btn chat" title="Chat" data-stage-id="${stage.id}" data-substage-id="${substage.id}">
                                                            <i class="far fa-comment-alt"></i>
                                                        </button>
                                                        <button class="project_brief_substage_action_btn activity" title="Activity Log" data-stage-id="${stage.id}" data-substage-id="${substage.id}">
                                                            <i class="fas fa-history"></i>
                                                        </button>
                                                        <button class="project_brief_substage_action_btn files" title="View Files" data-stage-id="${stage.id}" data-substage-id="${substage.id}">
                                                            <i class="far fa-file-alt"></i>
                                                        </button>
                                                        <button class="project_brief_substage_view_btn" data-stage-id="${stage.id}" data-substage-id="${substage.id}">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="project_brief_substage_files_container">
                                                    <div class="files_loading">
                                                        <i class="fas fa-spinner fa-spin"></i> Loading files...
                                                    </div>
                                                    <div class="files_content"></div>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Render team members
    renderTeamMembers(teamMembers) {
        // Check if teamMembers exists and is a non-empty array
        if (!teamMembers || !Array.isArray(teamMembers) || teamMembers.length === 0) {
            return '<div class="project_brief_empty_state">No team members assigned to this project.</div>';
        }

        // Group team members to avoid duplicates
        const uniqueMembers = {};
        teamMembers.forEach(member => {
            if (!member || !member.name) return;
            
            // Use member ID as key
            const key = member.id || member.name;
            if (!uniqueMembers[key]) {
                uniqueMembers[key] = {
                    ...member,
                    stages: [],
                    substages: {},
                    primaryStage: member.stage_number || 0,
                    isProjectOwner: member.role === 'Project'
                };
            }
            
            // Add stage information if available
            if (member.stage_number) {
                const stageKey = `stage_${member.stage_number}`;
                
                // Add to stages list if not already there
                if (!uniqueMembers[key].stages.includes(member.stage_number)) {
                    uniqueMembers[key].stages.push(member.stage_number);
                }
                
                // Update primary stage to the lowest stage number
                if (member.stage_number < uniqueMembers[key].primaryStage || uniqueMembers[key].primaryStage === 0) {
                    uniqueMembers[key].primaryStage = member.stage_number;
                }
                
                // Initialize substages array if needed
                if (!uniqueMembers[key].substages[stageKey]) {
                    uniqueMembers[key].substages[stageKey] = [];
                }
                
                // Add substage if available
                if (member.substage_number) {
                    uniqueMembers[key].substages[stageKey].push(member.substage_number);
                }
            }
        });
        
        // Convert to array and sort by primary stage (project owners first, then by stage number)
        const sortedMembers = Object.values(uniqueMembers).sort((a, b) => {
            // Project owners go first
            if (a.isProjectOwner && !b.isProjectOwner) return -1;
            if (!a.isProjectOwner && b.isProjectOwner) return 1;
            
            // Then sort by primary stage
            return a.primaryStage - b.primaryStage;
        });
        
        return `
            <div class="project_brief_team_list">
                ${sortedMembers.map(member => {
                    // Get member initials
                    const initials = this.getInitials(member.name);
                    
                    // Create avatar from initials or profile picture
                    const avatar = member.profile_picture 
                        ? `<img src="${member.profile_picture}" alt="${this.escapeHtml(member.name)}" class="profile-image">`
                        : initials;

                    // Determine the primary role
                    const primaryRole = member.role || 'Team Member';
                    
                    // Format and deduplicate the stages list
                    let stageText = '';
                    let substagesHtml = '';
                    
                    if (member.stages && member.stages.length > 0) {
                        // Sort stages numerically
                        member.stages.sort((a, b) => a - b);
                        
                        // Create comma-separated list of stages without duplicates
                        const uniqueStages = [...new Set(member.stages)].map(stageNum => `Stage ${stageNum}`);
                        
                        stageText = `<div class="project_brief_team_member_stage">
                            <span>Assigned: ${uniqueStages.join(', ')}</span>
                            ${Object.keys(member.substages).length > 0 ? 
                              `<button class="substage_toggle_btn" aria-label="Toggle substages">
                                <i class="fas fa-chevron-down"></i>
                              </button>` : 
                              ''}
                        </div>`;
                        
                        // Create substages content if available
                        if (Object.keys(member.substages).length > 0) {
                            const substageItems = [];
                            
                            // For each stage with substages, sorted by stage number
                            Object.keys(member.substages)
                                .sort((a, b) => {
                                    const stageNumA = parseInt(a.replace('stage_', ''));
                                    const stageNumB = parseInt(b.replace('stage_', ''));
                                    return stageNumA - stageNumB;
                                })
                                .forEach(stageKey => {
                                    const substageNums = member.substages[stageKey];
                                    if (substageNums.length > 0) {
                                        const stageNumber = stageKey.replace('stage_', '');
                                        // Sort substages numerically
                                        substageNums.sort((a, b) => a - b);
                                        
                                        // Find the stage in project.stages to get substage titles
                                        const findStageWithSubstages = () => {
                                            // We need to access project stages from here
                                            if (this.currentProjectId && window.projectBriefModal) {
                                                const project = window.projectBriefModal.currentProject;
                                                if (project && project.stages) {
                                                    // Find the stage with matching stage number
                                                    const stage = project.stages.find(s => s.stage_number == stageNumber);
                                                    if (stage && stage.substages) {
                                                        return stage.substages;
                                                    }
                                                }
                                            }
                                            return null;
                                        };
                                        
                                        const substages = findStageWithSubstages();
                                        
                                        // Format substage display (with titles if available)
                                        let substageDisplay = '';
                                        substageNums.forEach(subNum => {
                                            if (substages) {
                                                const substage = substages.find(s => s.substage_number == subNum);
                                                if (substage && substage.title) {
                                                    substageDisplay += `<div class="substage_line">Substage ${subNum} - ${substage.title}</div>`;
                                                    return;
                                                }
                                            }
                                            substageDisplay += `<div class="substage_line">Substage ${subNum}</div>`;
                                        });
                                        
                                        // Add formatted substage item
                                        substageItems.push(`
                                            <div class="substage_item">
                                                <span class="substage_stage">Stage ${stageNumber}</span>
                                                <div class="substage_numbers">${substageDisplay}</div>
                                            </div>
                                        `);
                                    }
                                });
                            
                            if (substageItems.length > 0) {
                                substagesHtml = `
                                    <div class="project_brief_team_member_substages">
                                        ${substageItems.join('')}
                                    </div>
                                `;
                            }
                        }
                    }
                        
                    return `
                        <div class="project_brief_team_member">
                            <div class="project_brief_team_member_avatar ${primaryRole === 'Project' ? 'project_role' : ''}">${avatar}</div>
                            <div class="project_brief_team_member_info">
                                <div class="project_brief_team_member_name">${this.escapeHtml(member.name)}</div>
                                ${stageText}
                                ${substagesHtml}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Show the modal
    showModal(modalHtml) {
        // Create modal overlay
        this.modalOverlay = document.createElement('div');
        this.modalOverlay.className = 'project_brief_modal_overlay';
        this.modalOverlay.innerHTML = modalHtml;
        document.body.appendChild(this.modalOverlay);
        
        // Reference to the modal container
        this.modalContainer = this.modalOverlay.querySelector('.project_brief_modal_container');
        
        // Prevent closing when clicking on the modal content
        this.modalContainer.addEventListener('click', e => {
            e.stopPropagation();
        });
        
        this.isOpen = true;
    }

    // Setup modal event listeners
    setupModalEventListeners() {
        // Close modal when clicking on the close button
        const closeBtn = this.modalContainer.querySelector('.project_brief_modal_close');
        closeBtn.addEventListener('click', () => {
            this.closeModal();
        });
        
        // Close modal when clicking on the overlay (outside the modal)
        this.modalOverlay.addEventListener('click', (e) => {
            if (e.target === this.modalOverlay) {
                this.closeModal();
            }
        });
        
        // Handle stage view buttons
        const stageViewButtons = this.modalContainer.querySelectorAll('.project_brief_stage_view_btn');
        stageViewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const stageId = button.dataset.stageId;
                if (stageId && this.currentProjectId) {
                    // Close this modal
                    this.closeModal();
                    
                    // Open the stage detail modal
                    if (window.stageDetailModal) {
                        window.stageDetailModal.openStageModal(this.currentProjectId, stageId);
                    } else {
                        // Initialize if not already done
                        window.stageDetailModal = new StageDetailModal();
                        window.stageDetailModal.openStageModal(this.currentProjectId, stageId);
                    }
                }
            });
        });
        
        // Handle substage view buttons
        const substageViewButtons = this.modalContainer.querySelectorAll('.project_brief_substage_view_btn');
        substageViewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const stageId = button.dataset.stageId;
                const substageId = button.dataset.substageId;
                if (stageId && substageId && this.currentProjectId) {
                    // Close this modal
                    this.closeModal();
                    
                    // Open the stage detail modal with focus on the specific substage
                    if (window.stageDetailModal) {
                        window.stageDetailModal.openStageModal(this.currentProjectId, stageId, substageId);
                    } else {
                        // Initialize if not already done
                        window.stageDetailModal = new StageDetailModal();
                        window.stageDetailModal.openStageModal(this.currentProjectId, stageId, substageId);
                    }
                }
            });
        });
        
        // Handle stage substage toggle buttons
        const stageSubstageToggleButtons = this.modalContainer.querySelectorAll('.project_brief_substage_toggle_btn');
        stageSubstageToggleButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                // Toggle the expanded class on the button
                button.classList.toggle('expanded');
                // Find the substages container
                const stageItem = button.closest('.project_brief_stage_item');
                const substagesContainer = stageItem.querySelector('.project_brief_substages_container');
                if (substagesContainer) {
                    substagesContainer.classList.toggle('show');
                }
            });
        });
        
        // Handle team member substage toggle buttons
        const substageToggleButtons = this.modalContainer.querySelectorAll('.substage_toggle_btn');
        substageToggleButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                // Toggle the expanded class on the button
                button.classList.toggle('expanded');
                // Find the substages container
                const teamMember = button.closest('.project_brief_team_member');
                const substagesContainer = teamMember.querySelector('.project_brief_team_member_substages');
                if (substagesContainer) {
                    substagesContainer.classList.toggle('show');
                }
            });
        });
        
        // Handle stage action buttons (chat and activity)
        const stageActionButtons = this.modalContainer.querySelectorAll('.project_brief_stage_action_btn');
        stageActionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const stageId = button.dataset.stageId;
                if (stageId) {
                    // Different action based on button class
                    if (button.classList.contains('chat')) {
                        console.log('Stage Chat clicked for stage ID:', stageId);
                        // Implementation will be added later
                        alert('Stage chat functionality will be implemented soon.');
                    } else if (button.classList.contains('activity')) {
                        console.log('Stage Activity Log clicked for stage ID:', stageId);
                        // Implementation will be added later
                        alert('Stage activity log functionality will be implemented soon.');
                    }
                }
            });
        });
        
        // Handle substage action buttons (chat, activity and files)
        const substageActionButtons = this.modalContainer.querySelectorAll('.project_brief_substage_action_btn');
        substageActionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const stageId = button.dataset.stageId;
                const substageId = button.dataset.substageId;
                
                if (!stageId || !substageId) {
                    console.error('Missing stageId or substageId', { stageId, substageId, button });
                    return;
                }
                
                // Different action based on button class
                if (button.classList.contains('chat')) {
                    console.log('Substage Chat clicked for stage ID:', stageId, 'substage ID:', substageId);
                    // Implementation will be added later
                    alert('Substage chat functionality will be implemented soon.');
                } else if (button.classList.contains('activity')) {
                    console.log('Substage Activity Log clicked for stage ID:', stageId, 'substage ID:', substageId);
                    // Implementation will be added later
                    alert('Substage activity log functionality will be implemented soon.');
                } else if (button.classList.contains('files')) {
                    console.log('Substage Files clicked for stage ID:', stageId, 'substage ID:', substageId);
                    
                    // Find the substage item
                    const substageItem = button.closest('.project_brief_substage_item');
                    if (!substageItem) {
                        console.error('Could not find parent substage item', button);
                        return;
                    }
                    
                    // Find files container
                    const filesContainer = substageItem.querySelector('.project_brief_substage_files_container');
                    if (filesContainer) {
                        // Toggle files container visibility
                        const isVisible = filesContainer.classList.toggle('show');
                        console.log('Toggling files container visibility:', isVisible);
                        
                        // Fetch files if container is now visible and hasn't been loaded yet
                        if (isVisible && !filesContainer.dataset.loaded) {
                            console.log('Loading files for first time');
                            this.fetchSubstageFiles(
                                this.currentProjectId, 
                                stageId, 
                                substageId, 
                                filesContainer
                            );
                            filesContainer.dataset.loaded = 'true';
                        }
                        
                        // Toggle button style to indicate active state
                        button.classList.toggle('active', isVisible);
                    } else {
                        console.error('Could not find files container in substage item', substageItem);
                    }
                }
            });
        });

        // Add event delegation for file send buttons
        document.addEventListener('click', function(e) {
            // Check if the clicked element is a send button
            if (e.target && (e.target.classList.contains('project_brief_substage_file_btn') && 
                             e.target.classList.contains('send') || 
                             (e.target.parentElement && 
                              e.target.parentElement.classList.contains('project_brief_substage_file_btn') && 
                              e.target.parentElement.classList.contains('send')))) {
                
                // Get the button element (could be the icon or the button itself)
                const sendBtn = e.target.classList.contains('send') ? e.target : e.target.parentElement;
                
                // Get the file item
                const fileItem = sendBtn.closest('.project_brief_substage_file_item');
                if (fileItem) {
                    const fileId = fileItem.dataset.fileId;
                    if (fileId) {
                        showSendForApprovalPopup(parseInt(fileId));
                    }
                }
            }
        });
    }

    // Close the modal
    closeModal() {
        if (this.modalOverlay) {
            this.modalOverlay.remove();
            this.modalOverlay = null;
            this.modalContainer = null;
            this.isOpen = false;
            
            // Remove any loading indicators that might be present on the page
            const loadingIndicators = document.querySelectorAll('.loading-indicator, .loading-overlay, .file_upload_overlay');
            loadingIndicators.forEach(indicator => indicator.remove());
            
            // Also clean up any file upload form containers
            const fileUploadForms = document.querySelectorAll('.substage_file_upload_form_container');
            fileUploadForms.forEach(form => form.remove());
            
            // Check if there's any calendar modal open and make sure it's visible
            const calendarModal = document.querySelector('.pr-calendar-modal-overlay');
            if (calendarModal) {
                calendarModal.style.display = 'flex';
            }
            
            // Ensure body scroll is enabled
            document.body.style.overflow = '';
            
            // Dispatch an event that the modal was closed
            // This allows other components to respond appropriately
            document.dispatchEvent(new CustomEvent('projectBriefModalClosed'));
            
            console.log('Project brief modal closed and cleanup completed');
        }
    }

    // Helper functions
    formatDate(dateString) {
        if (!dateString) return 'Not specified';
        
        try {
            const date = new Date(dateString);
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                return 'Invalid date';
            }
            
            // Format date with time: Jan 1, 2023 2:30 PM
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            
            return date.toLocaleDateString('en-US', options);
        } catch (e) {
            console.error('Error formatting date:', e);
            return dateString; // Return original string if there's an error
        }
    }

    formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'Not set';
        const dateTime = new Date(dateTimeString);
        return dateTime.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatStatus(status) {
        if (!status) return 'Not set';
        return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    getInitials(name) {
        if (!name || typeof name !== 'string') return 'NA';
        return name.split(' ')
            .filter(part => part.trim().length > 0)
            .map(word => word.charAt(0))
            .join('')
            .substring(0, 2)
            .toUpperCase() || 'NA';
    }

    isPastDue(dateString) {
        if (!dateString) return false;
        const dueDate = new Date(dateString);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return dueDate < today;
    }

    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Show loading indicator
    showLoadingIndicator() {
        // Create modal overlay with loading spinner
        this.modalOverlay = document.createElement('div');
        this.modalOverlay.className = 'project_brief_modal_overlay loading-indicator';
        this.modalOverlay.innerHTML = `
            <div class="project_brief_modal_container" style="width: auto; padding: 30px;">
                <div style="text-align: center;">
                    <div class="project_brief_loading_spinner">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                    </div>
                    <div style="margin-top: 15px; font-size: 16px; color: #555;">
                        Loading project details...
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(this.modalOverlay);
    }
    
    // Show error modal
    showErrorModal(errorMessage) {
        // Remove existing modal if present
        if (this.modalOverlay) {
            this.modalOverlay.remove();
        }
        
        // Create error modal
        this.modalOverlay = document.createElement('div');
        this.modalOverlay.className = 'project_brief_modal_overlay';
        this.modalOverlay.innerHTML = `
            <div class="project_brief_modal_container" style="width: auto; max-width: 400px; padding: 30px;">
                <div style="text-align: center;">
                    <div style="color: #ef4444; font-size: 40px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 10px; color: #333;">
                        Error Loading Project
                    </div>
                    <div style="margin-bottom: 20px; color: #555;">
                        ${this.escapeHtml(errorMessage)}
                    </div>
                    <button class="project_brief_modal_close_btn" style="background-color: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        Close
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(this.modalOverlay);
        
        // Add close button event listener
        const closeBtn = this.modalOverlay.querySelector('.project_brief_modal_close_btn');
        closeBtn.addEventListener('click', () => {
            this.closeModal();
        });
    }

    // Fetch substage files
    async fetchSubstageFiles(projectId, stageId, substageId, container) {
        try {
            const fileLoading = container.querySelector('.files_loading');
            const fileContent = container.querySelector('.files_content');
            
            // Show loading indicator
            fileLoading.style.display = 'flex';
            fileContent.innerHTML = '';
            
            // Remove any existing buttons to prevent duplicates
            const existingBtnContainer = container.querySelector('.floating_upload_btn_container');
            if (existingBtnContainer) {
                existingBtnContainer.remove();
            }
            
            // Fetch files from the server
            const response = await fetch(`fetch_substage_attachments.php?project_id=${projectId}&stage_id=${stageId}&substage_id=${substageId}`);
            const data = await response.json();
            
            // Hide loading indicator
            fileLoading.style.display = 'none';
            
            if (data.success) {
                if (data.files && data.files.length > 0) {
                    // Render files - since files exist, don't show the floating + button but add one at the bottom
                    const filesHtml = data.files.map(file => {
                        return `
                            <div class="project_brief_substage_file_item" data-file-id="${file.id}">
                                <div class="project_brief_substage_file_icon">
                                    <i class="${file.file_icon}"></i>
                                </div>
                                <div class="project_brief_substage_file_details">
                                    <div class="project_brief_substage_file_name">${this.escapeHtml(file.file_name)}</div>
                                    <div class="project_brief_substage_file_meta">
                                        <span>${file.file_size_formatted}</span>
                                        <span>Uploaded by: ${this.escapeHtml(file.uploaded_by_name || 'Unknown')}</span>
                                        <span>${file.uploaded_at_formatted}</span>
                                        ${this.getStatusBadge(file)}
                                    </div>
                                </div>
                                <div class="project_brief_substage_file_actions">
                                    <button class="project_brief_substage_file_btn send" title="Send for Approval">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <a href="${file.file_path}" class="project_brief_substage_file_download" title="Download" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    // Add a "Add More Files" button at the bottom
                    const addMoreButtonHtml = `
                        <div class="add_more_files_container">
                            <button class="add_more_files_btn" data-stage-id="${stageId}" data-substage-id="${substageId}">
                                <i class="fas fa-plus"></i> Add More Files
                            </button>
                        </div>
                    `;
                    
                    fileContent.innerHTML = filesHtml + addMoreButtonHtml;
                    
                    // Add event listeners for file action buttons
                    const fileActionButtons = fileContent.querySelectorAll('.project_brief_substage_file_btn');
                    fileActionButtons.forEach(button => {
                        button.addEventListener('click', (e) => {
                            e.preventDefault();
                            const fileItem = button.closest('.project_brief_substage_file_item');
                            const fileId = fileItem ? fileItem.dataset.fileId : null;
                            
                            if (button.classList.contains('send') && fileId) {
                                // Remove this placeholder alert
                                // alert('Send file functionality will be implemented soon.');
                                // Instead, directly call the function to show the manager selection popup
                                showSendForApprovalPopup(parseInt(fileId));
                            }
                        });
                    });
                    
                    // Add event listener for the "Add More Files" button
                    const addMoreBtn = fileContent.querySelector('.add_more_files_btn');
                    if (addMoreBtn) {
                        addMoreBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.openFileUploadModal(projectId, stageId, substageId, container);
                        });
                    }
                } else {
                    // No files found - show the + button
                    // Add floating upload button container
                    const floatingBtnContainer = document.createElement('div');
                    floatingBtnContainer.className = 'floating_upload_btn_container';
                    floatingBtnContainer.innerHTML = `
                        <button class="floating_upload_btn" title="Upload New File" data-stage-id="${stageId}" data-substage-id="${substageId}" id="upload_btn_${substageId}">
                            <i class="fas fa-plus"></i>
                        </button>
                    `;
                    container.appendChild(floatingBtnContainer);
                    
                    // Add event listener for floating upload button
                    const floatingUploadBtn = floatingBtnContainer.querySelector('.floating_upload_btn');
                    if (floatingUploadBtn) {
                        console.log('Adding click event listener to upload button', floatingUploadBtn);
                        
                        // First remove any existing event listeners
                        const newBtn = floatingUploadBtn.cloneNode(true);
                        floatingUploadBtn.parentNode.replaceChild(newBtn, floatingUploadBtn);
                        
                        newBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation(); // Prevent event bubbling
                            console.log('Upload button clicked for substage:', substageId);
                            
                            this.openFileUploadModal(projectId, stageId, substageId, container);
                        });
                    }
                    
                    // No files found
                    fileContent.innerHTML = `
                        <div class="project_brief_substage_file_empty">
                            No files attached to this substage.
                            <button class="substage_file_upload_btn" data-stage-id="${stageId}" data-substage-id="${substageId}">
                                <i class="fas fa-plus"></i> Add File
                            </button>
                        </div>
                    `;
                    
                    // Add event listener to the "Add File" button in the empty state
                    const uploadBtn = fileContent.querySelector('.substage_file_upload_btn');
                    if (uploadBtn) {
                        uploadBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.openFileUploadModal(projectId, stageId, substageId, container);
                        });
                    }
                }
            } else {
                // Error fetching files
                fileContent.innerHTML = `<div class="project_brief_substage_file_empty">Error loading files: ${data.message || 'Unknown error'}</div>`;
            }
        } catch (error) {
            console.error('Error fetching substage files:', error);
            const fileContent = container.querySelector('.files_content');
            const fileLoading = container.querySelector('.files_loading');
            fileLoading.style.display = 'none';
            fileContent.innerHTML = `<div class="project_brief_substage_file_empty">Error loading files: ${error.message}</div>`;
        }
    }

    // Helper method to open the file upload modal
    openFileUploadModal(projectId, stageId, substageId, container) {
        console.log('DEBUG: Opening file upload modal', { projectId, stageId, substageId });
        
        // Create overlay first to prevent interaction with other elements
        const overlay = document.createElement('div');
        overlay.className = 'file_upload_overlay loading-indicator';
        document.body.appendChild(overlay);
        
        // Create form container
        const formContainer = document.createElement('div');
        formContainer.className = 'substage_file_upload_form_container';
        formContainer.innerHTML = `
            <form class="substage_file_upload_form" enctype="multipart/form-data">
                <h3>Upload File to Substage</h3>
                <input type="hidden" name="substage_id" value="${substageId}">
                <div class="form_group">
                    <label for="file_name_${substageId}">File Name</label>
                    <input type="text" name="file_name" id="file_name_${substageId}" class="form_control" required>
                </div>
                <div class="form_group">
                    <label for="file_${substageId}">Choose File</label>
                    <input type="file" name="file" id="file_${substageId}" class="form_control" required>
                </div>
                <div class="form_group_buttons">
                    <button type="button" class="upload_cancel_btn">Cancel</button>
                    <button type="submit" class="upload_submit_btn">Upload</button>
                </div>
            </form>
        `;
        document.body.appendChild(formContainer);
        
        // Add event listener for cancel button
        const cancelBtn = formContainer.querySelector('.upload_cancel_btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                formContainer.remove();
                overlay.remove();
            });
        }
        
        // Add event listener to close form when clicking outside
        overlay.addEventListener('click', (e) => {
            formContainer.remove();
            overlay.remove();
        });
        
        // Prevent clicks inside form from closing it
        formContainer.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Add event listener for form submission
        const uploadForm = formContainer.querySelector('.substage_file_upload_form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(uploadForm);
                const submitBtn = uploadForm.querySelector('.upload_submit_btn');
                const cancelBtn = uploadForm.querySelector('.upload_cancel_btn');
                
                // Detailed console logging for form data
                console.log('DEBUG: Form submission started');
                console.log('DEBUG: substage_id =', formData.get('substage_id'));
                console.log('DEBUG: file_name =', formData.get('file_name'));
                
                const file = formData.get('file');
                if (file) {
                    console.log('DEBUG: file provided', {
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        lastModified: file.lastModified
                    });
                } else {
                    console.error('DEBUG: No file in form data');
                }
                
                // Disable buttons while uploading
                if (submitBtn) submitBtn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                
                try {
                    // Show uploading indicator
                    const progressDiv = document.createElement('div');
                    progressDiv.className = 'upload_progress';
                    progressDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                    uploadForm.appendChild(progressDiv);
                    
                    console.log('DEBUG: Starting file upload to server');
                    // Upload the file
                    const response = await fetch('substage_file_uploader.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Log response details before parsing JSON
                    console.log('DEBUG: Server response status:', response.status);
                    console.log('DEBUG: Server response headers:', 
                        [...response.headers.entries()].reduce((obj, [key, val]) => {
                            obj[key] = val;
                            return obj;
                        }, {})
                    );
                    
                    // Get raw response text for debugging
                    const responseText = await response.text();
                    console.log('DEBUG: Raw server response:', responseText);
                    
                    // Try to parse the response as JSON
                    let result;
                    try {
                        result = JSON.parse(responseText);
                        console.log('DEBUG: Parsed JSON response:', result);
                    } catch (jsonError) {
                        console.error('DEBUG: Error parsing JSON response:', jsonError);
                        throw new Error('Invalid JSON response from server: ' + responseText.substring(0, 200));
                    }
                    
                    if (result.success) {
                        console.log('DEBUG: File upload successful', result);
                        // Show success message briefly
                        formContainer.innerHTML = `
                            <div class="upload_success" style="text-align: center; padding: 20px;">
                                <i class="fas fa-check-circle" style="font-size: 32px; color: #10b981; margin-bottom: 10px;"></i>
                                <p style="margin-bottom: 15px;">File uploaded successfully!</p>
                            </div>
                        `;
                        
                        // Remove form and overlay after 1.5 seconds
                        setTimeout(() => {
                            formContainer.remove();
                            overlay.remove();
                            
                            // Reload the files
                            this.fetchSubstageFiles(projectId, stageId, substageId, container);
                        }, 1500);
                    } else {
                        // Show error message
                        console.error('DEBUG: File upload failed with server error:', result.message);
                        const errorMsg = result.message || 'An error occurred during upload';
                        
                        formContainer.innerHTML = `
                            <div class="upload_error" style="text-align: center; padding: 20px;">
                                <i class="fas fa-exclamation-circle" style="font-size: 32px; color: #ef4444; margin-bottom: 10px;"></i>
                                <p style="margin-bottom: 15px;">${errorMsg}</p>
                                <button class="try_again_btn">Try Again</button>
                            </div>
                        `;
                        
                        // Add event listener for try again button
                        const tryAgainBtn = formContainer.querySelector('.try_again_btn');
                        if (tryAgainBtn) {
                            tryAgainBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                formContainer.remove();
                                overlay.remove();
                                this.openFileUploadModal(projectId, stageId, substageId, container);
                            });
                        }
                    }
                } catch (error) {
                    console.error('DEBUG: Exception during file upload:', error);
                    console.error('DEBUG: Error stack trace:', error.stack);
                    
                    formContainer.innerHTML = `
                        <div class="upload_error" style="text-align: center; padding: 20px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 32px; color: #ef4444; margin-bottom: 10px;"></i>
                            <p style="margin-bottom: 15px;">Error uploading file: ${error.message}</p>
                            <button class="try_again_btn">Try Again</button>
                        </div>
                    `;
                    
                    // Add event listener for try again button
                    const tryAgainBtn = formContainer.querySelector('.try_again_btn');
                    if (tryAgainBtn) {
                        tryAgainBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            formContainer.remove();
                            overlay.remove();
                            this.openFileUploadModal(projectId, stageId, substageId, container);
                        });
                    }
                }
            });
        }
    }

    // Helper function to generate the status badge HTML with correct display text
    getStatusBadge(file) {
        if (!file.status) return '';
        
        let statusText = file.status;
        let statusClass = file.status;
        
        // Map statuses to display text
        if (file.status === 'sent_for_approval') {
            statusText = 'Sent for Approval';
            statusClass = 'sent_for_approval';
        } else if (file.status === 'approved') {
            statusText = 'Approved';
        } else if (file.status === 'rejected') {
            statusText = 'Rejected';
        } else if (file.status === 'pending') {
            statusText = 'Pending';
        }
        
        return `<span class="file_status ${statusClass}">${statusText}</span>`;
    }
}

// Initialize the project brief modal
document.addEventListener('DOMContentLoaded', () => {
    window.projectBriefModal = new ProjectBriefModal();
});

/**
 * Shows the send for approval popup with list of senior managers
 * @param {number} fileId - ID of the file to send for approval
 */
function showSendForApprovalPopup(fileId) {
    console.log('Opening send for approval popup for file ID:', fileId);
    
    // First fetch the senior managers
    fetch('get_senior_managers.php')
        .then(response => {
            console.log('Senior managers response status:', response.status);
            return response.text().then(text => {
                try {
                    // Try to parse as JSON
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing response as JSON:', e);
                    console.log('Raw response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Senior managers data:', data);
            
            if (!data || !data.success) {
                throw new Error(data?.message || 'Unknown error fetching managers');
            }
            
            if (!data.managers || !Array.isArray(data.managers) || data.managers.length === 0) {
                console.error('No managers found. Debug info:', data.debug);
                
                // Show more helpful error with debug info
                let errorMsg = 'No managers found to send approval request to.\n\n';
                
                if (data.debug) {
                    if (data.debug.user_types) {
                        errorMsg += 'Available user types: ' + data.debug.user_types.join(', ') + '\n';
                    }
                    if (data.debug.user_roles) {
                        errorMsg += 'Available user roles: ' + data.debug.user_roles.join(', ') + '\n';
                    }
                    if (data.debug.found_rows !== undefined) {
                        errorMsg += 'Query found ' + data.debug.found_rows + ' users.';
                    }
                }
                
                alert(errorMsg);
                return;
            }
            
            // Create the overlay and popup container
            const overlay = document.createElement('div');
            overlay.className = 'file_upload_overlay';
            overlay.id = 'approvalSendOverlay';
            
            const popupContainer = document.createElement('div');
            popupContainer.className = 'substage_file_upload_form_container';
            
            // Create popup content
            let popupContent = `
                <h3>Send for Approval</h3>
                <div class="form_group">
                    <label>Select Manager</label>
                    <select id="managerSelect" class="form_control">
                        <option value="">-- Select a Manager --</option>
            `;
            
            // Add manager options
            data.managers.forEach(manager => {
                let roleInfo = '';
                if (manager.type || manager.role) {
                    roleInfo = ' (' + (manager.type || manager.role) + ')';
                }
                popupContent += `<option value="${manager.id}">${manager.name}${roleInfo}</option>`;
            });
            
            popupContent += `
                    </select>
                </div>
                <div class="form_group_buttons">
                    <button id="cancelSendBtn" class="upload_cancel_btn">Cancel</button>
                    <button id="sendFileBtn" class="upload_submit_btn">Send</button>
                </div>
            `;
            
            popupContainer.innerHTML = popupContent;
            overlay.appendChild(popupContainer);
            document.body.appendChild(overlay);
            
            // Setup event listeners
            document.getElementById('cancelSendBtn').addEventListener('click', () => {
                document.body.removeChild(overlay);
            });
            
            document.getElementById('sendFileBtn').addEventListener('click', () => {
                const managerId = document.getElementById('managerSelect').value;
                if (!managerId) {
                    alert('Please select a manager');
                    return;
                }
                
                sendFileForApproval(fileId, managerId, overlay);
            });
        })
        .catch(error => {
            console.error('Error fetching managers:', error);
            alert('Failed to load managers: ' + error.message);
        });
}

/**
 * Sends the file for approval to the selected manager
 * @param {number} fileId - ID of the file
 * @param {number} managerId - ID of the selected manager
 * @param {HTMLElement} overlay - The overlay element to remove after success
 */
function sendFileForApproval(fileId, managerId, overlay) {
    // Send the API request
    fetch('update_modal_file.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            file_id: fileId,
            manager_id: managerId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the overlay
            document.body.removeChild(overlay);
            
            // Show success message
            alert('File sent for approval successfully');
            
            // Update the file status in the UI
            const fileItem = document.querySelector(`.project_brief_substage_file_item[data-file-id="${fileId}"]`);
            if (fileItem) {
                const statusSpan = fileItem.querySelector('.file_status');
                if (statusSpan) {
                    statusSpan.textContent = 'Sent for Approval';
                    statusSpan.className = 'file_status sent_for_approval';
                }
                
                // Disable the send button
                const sendBtn = fileItem.querySelector('.project_brief_substage_file_btn.send');
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.style.opacity = '0.5';
                    sendBtn.style.cursor = 'not-allowed';
                }
            }
        } else {
            alert('Failed to send file for approval: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error sending file for approval:', error);
        alert('Failed to send file for approval: ' + error.message);
    });
} 