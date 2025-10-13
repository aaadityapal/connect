/**
 * Stage Detail Modal JavaScript
 * This file handles the functionality of the modal that appears when a user clicks
 * on a stage or substage in the project calendar modal
 */

// Make sure StageChat is loaded
if (typeof StageChat === 'undefined') {
    // Create a script element to load stage-chat.js if not already loaded
    const chatScript = document.createElement('script');
    chatScript.src = 'assets/js/stage-chat.js';
    chatScript.onload = function() {
        console.log('StageChat script loaded');
        if (!window.stageChat) {
            window.stageChat = new StageChat();
        }
    };
    document.head.appendChild(chatScript);
}

class StageDetailModal {
    constructor() {
        this.modalOverlay = null;
        this.modalContainer = null;
        this.currentStageId = null;
        this.currentSubstageId = null;
        this.isOpen = false;
        this.currentUserId = this.getCurrentUserId();
        this.isAdmin = this.isAdminUser();
        this.initEventListeners();
        this.injectStyles();
    }

    // Inject CSS styles for the modal components
    injectStyles() {
        // Check if styles are already injected
        if (document.getElementById('stage-detail-modal-styles')) {
            return;
        }
        
        // Create style element
        const styleEl = document.createElement('style');
        styleEl.id = 'stage-detail-modal-styles';
        
        // Define styles
        styleEl.textContent = `
            .stage_detail_modal_overlay {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                background-color: rgba(0, 0, 0, 0.5) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                z-index: 9999 !important;
                padding: 20px !important;
                overflow-y: auto !important;
                width: 100% !important;
                height: 100% !important;
            }
            
            .stage_detail_modal_container {
                background-color: white !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
                width: 100% !important;
                max-width: 900px !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
                position: relative !important;
                margin: 0 auto !important;
                transform: none !important;
                left: auto !important;
                top: auto !important;
            }
            
            .stage_detail_modal_header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                border-bottom: 1px solid #e2e8f0;
                background-color: #f8fafc;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            
            .stage_detail_modal_title {
                font-size: 18px;
                font-weight: 600;
                color: #334155;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .stage_detail_modal_title i {
                color: #3b82f6;
            }
            
            .stage_detail_modal_close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border-radius: 50%;
            }
            
            .stage_detail_modal_close:hover {
                background-color: #f1f5f9;
                color: #ef4444;
            }
            
            .stage_detail_modal_content {
                padding: 20px;
            }
            
            .stage_detail_breadcrumbs {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                color: #64748b;
            }
            
            .stage_detail_breadcrumbs a {
                color: #3b82f6;
                text-decoration: none;
            }
            
            .stage_detail_breadcrumbs a:hover {
                text-decoration: underline;
            }
            
            .stage_detail_breadcrumbs i {
                font-size: 10px;
                color: #94a3b8;
            }
            
            /* Responsive adjustments */
            @media (max-width: 768px) {
                .stage_detail_modal_container {
                    max-width: 100%;
                    max-height: 100vh;
                    border-radius: 0;
                }
                
                .stage_detail_modal_overlay {
                    padding: 0;
                }
            }
            
            .stage_detail_status_select select {
                padding: 5px 8px;
                border-radius: 4px;
                border: 1px solid #ddd;
                background-color: white;
                font-size: 14px;
                min-width: 150px;
                color: #333;
            }
            
            .stage_detail_status_select select:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            }
            
            .stage_detail_status_select select option[value="not_started"] {
                background-color: #f1f5f9;
                color: #64748b;
            }
            
            .stage_detail_status_select select option[value="in_progress"] {
                background-color: #eff6ff;
                color: #3b82f6;
            }
            
            .stage_detail_status_select select option[value="in_review"] {
                background-color: #fff7ed;
                color: #f59e0b;
            }
            
            .stage_detail_status_select select option[value="completed"] {
                background-color: #ecfdf5;
                color: #10b981;
            }
            
            .stage_detail_status_select select option[value="on_hold"] {
                background-color: #fef2f2;
                color: #ef4444;
            }
            
            /* Project Overview Section Styles */
            .project_overview_section {
                margin-bottom: 25px;
                background-color: #f8fafc;
                border-radius: 8px;
                padding: 20px;
                border: 1px solid #e2e8f0;
            }
            
            .project_overview_section_title {
                font-size: 16px;
                font-weight: 600;
                color: #334155;
                margin: 0 0 15px 0;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 10px;
            }
            
            .project_overview_info {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 15px;
            }
            
            .project_overview_info_item {
                margin-bottom: 12px;
            }
            
            .project_overview_info_label {
                display: block;
                font-size: 12px;
                color: #64748b;
                margin-bottom: 4px;
                font-weight: 500;
            }
            
            .project_overview_info_value {
                display: block;
                font-size: 14px;
                color: #1e293b;
                font-weight: 500;
            }
            
            .project_overview_date_overdue {
                color: #ef4444;
                font-size: 12px;
                margin-left: 5px;
                font-weight: 500;
            }
            
            .project_overview_team_section {
                margin-top: 20px;
            }
            
            .project_overview_team_members {
                margin-top: 15px;
            }
            
            /* Team members styles */
            .stage_detail_team_section {
                margin-top: 20px;
            }
            
            .stage_detail_team_members {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-top: 10px;
            }
            
            .stage_detail_team_member {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 15px;
                background-color: #f8fafc;
                border-radius: 8px;
                transition: all 0.2s ease;
                min-width: 200px;
                width: calc(50% - 15px);
                box-sizing: border-box;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            
            .stage_detail_team_member:hover {
                background-color: #f1f5f9;
                transform: translateY(-2px);
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            }
            
            .stage_detail_team_member_avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background-color: #3b82f6;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 14px;
                flex-shrink: 0;
                overflow: hidden;
            }
            
            .stage_detail_team_member_avatar .profile-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .stage_detail_team_member_info {
                display: flex;
                flex-direction: column;
                width: 100%;
                overflow: hidden;
            }
            
            .stage_detail_team_member_name {
                font-weight: 500;
                font-size: 14px;
                color: #1e293b;
                margin-bottom: 5px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .stage_detail_team_member_roles {
                display: flex;
                flex-direction: column;
                gap: 2px;
                width: 100%;
            }
            
            .stage_detail_team_member_role {
                font-size: 12px;
                color: #64748b;
                margin-top: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.4;
            }
            
            .stage_role_container {
                margin-top: 2px;
            }
            
            .stage_role_header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 12px;
                color: #64748b;
            }
            
            .stage_role_header span {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .substage_role {
                padding: 3px 0;
                font-size: 12px;
                color: #4b5563;
                white-space: normal;
                word-break: break-word;
                line-height: 1.4;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }
            
            /* Media queries for responsive layout */
            @media (max-width: 768px) {
                .stage_detail_team_member {
                    width: 100%;
                }
            }
            
            /* File upload styles */
            .stage_detail_section_header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            
            .stage_detail_upload_btn,
            .substage_detail_upload_btn {
                background-color: #3b82f6;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 6px 12px;
                font-size: 14px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s ease;
            }
            
            .stage_detail_upload_btn:hover,
            .substage_detail_upload_btn:hover {
                background-color: #2563eb;
            }
            
            .stage_detail_file_upload_form,
            .substage_detail_file_upload_form {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #475569;
                font-size: 14px;
            }
            
            .form-group input[type="text"] {
                width: 100%;
                padding: 8px 10px;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .form-group input[type="file"] {
                width: 100%;
                padding: 6px;
                border: 1px dashed #cbd5e1;
                border-radius: 4px;
                background-color: #f8fafc;
            }
            
            .form-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 15px;
            }
            
            .cancel-upload-btn {
                background-color: #f1f5f9;
                color: #475569;
                border: none;
                border-radius: 4px;
                padding: 6px 12px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .upload-file-btn {
                background-color: #3b82f6;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 6px 12px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .cancel-upload-btn:hover {
                background-color: #e2e8f0;
            }
            
            .upload-file-btn:hover {
                background-color: #2563eb;
            }
            
            /* Project Overview Toggle Styles */
            .project_overview_section_header {
                background-color: #f8fafc;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                border: 1px solid #e2e8f0;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .project_overview_section_header:hover {
                background-color: #f1f5f9;
            }
            
            .project_overview_section_toggle {
                display: flex;
                align-items: center;
                font-size: 15px;
                color: #334155;
                margin: 0;
                font-weight: 500;
            }
            
            .project_overview_toggle_icon {
                margin-right: 10px;
                font-size: 12px;
                transition: transform 0.2s ease;
            }
            
            .project_overview_toggle_icon.expanded {
                transform: rotate(90deg);
            }
        `;
        
        // Add styles to document
        document.head.appendChild(styleEl);
    }

    // Get the current user ID from a data attribute or session
    getCurrentUserId() {
        // Try to get from data attribute on body if available
        const userIdAttribute = document.body.getAttribute('data-user-id');
        if (userIdAttribute) {
            return userIdAttribute;
        }
        
        // Default to null if not available (will show all stages/substages)
        return null;
    }

    // Check if a substage is assigned to the current user
    isSubstageAssignedToCurrentUser(substage) {
        // If no current user ID, return false
        if (!this.currentUserId) {
            return false;
        }
        
        // Check if the substage has an assigned user (handle both project and task substages)
        const assignedTo = substage.assigned_to || substage.assignee_id;
        if (!assignedTo) {
            return false;
        }
        
        // Convert both values to strings for comparison
        const currentUserIdStr = this.currentUserId.toString();
        const assignedToIdStr = assignedTo.toString();
        
        // Handle comma-separated list of IDs
        if (assignedToIdStr.includes(',')) {
            return assignedToIdStr.split(',').some(id => 
                id.toString().trim() === currentUserIdStr);
        }
        
        // Simple ID comparison
        return assignedToIdStr === currentUserIdStr;
    }

    // Get the current user role from a data attribute or session
    getCurrentUserRole() {
        // Try to get from data attribute on body if available
        const userRoleAttribute = document.body.getAttribute('data-user-role');
        if (userRoleAttribute) {
            return userRoleAttribute;
        }
        
        // Default to regular user if not available
        return 'user';
    }

    // Check if current user has admin privileges
    isAdminUser() {
        const role = this.getCurrentUserRole();
        return ['admin', 'HR', 'Senior Manager (Studio)'].includes(role);
    }

    initEventListeners() {
        // Add event delegation to listen for clicks on stage/substage items in the calendar modal
        document.addEventListener('click', (e) => {
            // Check if click was on a stage or substage item in the calendar modal
            const stageItem = e.target.closest('.pr-calendar-modal-item.stage-item');
            const substageItem = e.target.closest('.pr-calendar-modal-item.substage-item');
            
            // Don't open the modal if clicking on a toggle button
            if (e.target.closest('.pr-calendar-toggle-btn')) {
                return;
            }
            
            if (stageItem) {
                const projectId = stageItem.dataset.projectId;
                const stageId = stageItem.dataset.stageId;
                if (projectId && stageId) {
                    e.stopPropagation(); // Prevent the calendar modal from being closed
                    this.openStageModal(projectId, stageId);
                }
            } else if (substageItem) {
                const projectId = substageItem.dataset.projectId;
                const stageId = substageItem.dataset.stageId;
                const substageId = substageItem.dataset.substageId;
                if (projectId && stageId && substageId) {
                    e.stopPropagation(); // Prevent the calendar modal from being closed
                    this.openSubstageModal(projectId, stageId, substageId);
                }
            }
        });
    }

    async openStageModal(projectId, stageId) {
        this.currentStageId = stageId;
        this.currentSubstageId = null;
        
        try {
            const data = await this.fetchStageData(projectId, stageId);
            if (data.success) {
                // Fetch team members for the project
                const teamData = await this.fetchProjectTeam(projectId);
                
                // Initialize team_members array if it doesn't exist
                data.project.team_members = teamData.success ? teamData.team_members : [];
                
                // Ensure we include the stage's assigned user in the team members list
                if (data.stage.assigned_to && data.stage.assigned_to_name) {
                    // Check if the user is already in the team members list
                    const userExists = data.project.team_members.some(member => 
                        member.id === data.stage.assigned_to || member.name === data.stage.assigned_to_name
                    );
                    
                    // Add user to team members if not already present
                    if (!userExists) {
                        // Try to fetch the profile picture
                        const profilePicture = await this.fetchUserProfilePicture(data.stage.assigned_to);
                        
                        data.project.team_members.push({
                            id: data.stage.assigned_to,
                            name: data.stage.assigned_to_name,
                            role: 'Stage Assigned',
                            stage_number: data.stage.stage_number || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include project owner if available
                if (data.project.assigned_to && data.project.assigned_to_name) {
                    const projectOwnerExists = data.project.team_members.some(member => 
                        member.id === data.project.assigned_to || member.name === data.project.assigned_to_name
                    );
                    
                    if (!projectOwnerExists) {
                        // Try to fetch the profile picture
                        const profilePicture = await this.fetchUserProfilePicture(data.project.assigned_to);
                        
                        data.project.team_members.push({
                            id: data.project.assigned_to,
                            name: data.project.assigned_to_name,
                            role: 'Project Assigned',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Also include substage assigned users
                if (data.stage.substages && data.stage.substages.length > 0) {
                    for (const substage of data.stage.substages) {
                        if (substage.assigned_to && substage.assigned_to_name) {
                            const substageUserExists = data.project.team_members.some(member => 
                                member.id === substage.assigned_to || member.name === substage.assigned_to_name
                            );
                            
                            if (!substageUserExists) {
                                // Try to fetch the profile picture
                                const profilePicture = await this.fetchUserProfilePicture(substage.assigned_to);
                                
                                data.project.team_members.push({
                                    id: substage.assigned_to,
                                    name: substage.assigned_to_name,
                                    role: 'Substage Assigned',
                                    stage_number: data.stage.stage_number || '',
                                    substage_number: substage.substage_number || '',
                                    substage_title: substage.title || '',
                                    profile_picture: profilePicture
                                });
                            }
                        }
                    }
                }
                
                // Ensure client information is available
                data.project.client_name = data.project.client_name || 'Not specified';
                data.project.client_address = data.project.client_address || 'Not specified';
                data.project.project_location = data.project.project_location || 'Not specified';
                data.project.plot_area = data.project.plot_area || 'Not specified';
                data.project.contact_number = data.project.contact_number || 'Not specified';
                
                this.renderStageModal(data.stage, data.project);
            } else {
                console.error('Error fetching stage data:', data.message);
            }
        } catch (error) {
            console.error('Error fetching stage data:', error);
        }
    }

    async fetchProjectTeam(projectId) {
        try {
            // Try to fetch from the API endpoint
            const response = await fetch('get_project_team.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    include_profile_pictures: true
                })
            });
            
            // Check if the request was successful
            if (response.ok) {
                return await response.json();
            } else {
                // If the API doesn't exist or returns an error, we'll create a fallback
                console.warn('Project team API not available, using fallback');
                return {
                    success: false,
                    message: 'API not available',
                    team_members: []
                };
            }
        } catch (error) {
            console.error('Error fetching project team:', error);
            return {
                success: false,
                message: 'Failed to fetch project team members',
                team_members: []
            };
        }
    }

    async openSubstageModal(projectId, stageId, substageId) {
        this.currentStageId = stageId;
        this.currentSubstageId = substageId;
        
        try {
            const data = await this.fetchSubstageData(projectId, stageId, substageId);
            if (data.success) {
                // Fetch team members for the project
                const teamData = await this.fetchProjectTeam(projectId);
                
                // Initialize team_members array if it doesn't exist
                data.project.team_members = teamData.success ? teamData.team_members : [];
                
                // Ensure we include the substage's assigned user in the team members list
                if (data.substage.assigned_to && data.substage.assigned_to_name) {
                    // Check if the user is already in the team members list
                    const userExists = data.project.team_members.some(member => 
                        member.id === data.substage.assigned_to || member.name === data.substage.assigned_to_name
                    );
                    
                    // Add user to team members if not already present
                    if (!userExists) {
                        // Try to fetch the profile picture
                        const profilePicture = await this.fetchUserProfilePicture(data.substage.assigned_to);
                        
                        data.project.team_members.push({
                            id: data.substage.assigned_to,
                            name: data.substage.assigned_to_name,
                            role: 'Substage Assigned',
                            stage_number: data.stage.stage_number || '',
                            substage_number: data.substage.substage_number || '',
                            substage_title: data.substage.title || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include stage owner if available
                if (data.stage.assigned_to && data.stage.assigned_to_name) {
                    const stageOwnerExists = data.project.team_members.some(member => 
                        member.id === data.stage.assigned_to || member.name === data.stage.assigned_to_name
                    );
                    
                    if (!stageOwnerExists) {
                        // Try to fetch the profile picture
                        const profilePicture = await this.fetchUserProfilePicture(data.stage.assigned_to);
                        
                        data.project.team_members.push({
                            id: data.stage.assigned_to,
                            name: data.stage.assigned_to_name,
                            role: 'Stage Assigned',
                            stage_number: data.stage.stage_number || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include project owner if available
                if (data.project.assigned_to && data.project.assigned_to_name) {
                    const projectOwnerExists = data.project.team_members.some(member => 
                        member.id === data.project.assigned_to || member.name === data.project.assigned_to_name
                    );
                    
                    if (!projectOwnerExists) {
                        // Try to fetch the profile picture
                        const profilePicture = await this.fetchUserProfilePicture(data.project.assigned_to);
                        
                        data.project.team_members.push({
                            id: data.project.assigned_to,
                            name: data.project.assigned_to_name,
                            role: 'Project Assigned',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Ensure client information is available
                data.project.client_name = data.project.client_name || 'Not specified';
                data.project.client_address = data.project.client_address || 'Not specified';
                data.project.project_location = data.project.project_location || 'Not specified';
                data.project.plot_area = data.project.plot_area || 'Not specified';
                data.project.contact_number = data.project.contact_number || 'Not specified';
                
                this.renderSubstageModal(data.substage, data.stage, data.project);
            } else {
                console.error('Error fetching substage data:', data.message);
            }
        } catch (error) {
            console.error('Error fetching substage data:', error);
        }
    }

    async fetchStageData(projectId, stageId) {
        // Fetch stage data from server
        const response = await fetch('get_stage_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: projectId,
                stage_id: stageId
            })
        });

        const data = await response.json();
        
        // If current user ID is set, check if user has access to this stage
        if (this.currentUserId && data.success) {
            const stage = data.stage;
            // Allow access if stage is assigned to current user or user has an assigned substage
            const hasAssignedSubstage = stage.substages && stage.substages.some(
                substage => substage.assigned_to == this.currentUserId
            );
            
            if (stage.assigned_to != this.currentUserId && !hasAssignedSubstage) {
                return {
                    success: false,
                    message: "You don't have access to this stage."
                };
            }
        }
        
        // Check for unread messages if data fetched successfully
        if (data.success) {
            this.updateChatNotificationCounter(stageId);
        }
        
        return data;
    }

    async fetchSubstageData(projectId, stageId, substageId) {
        // Fetch substage data from server
        const response = await fetch('get_substage_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: projectId,
                stage_id: stageId,
                substage_id: substageId
            })
        });

        const data = await response.json();
        
        // If current user ID is set, check if user has access to this substage
        if (this.currentUserId && data.success) {
            const substage = data.substage;
            const stage = data.stage;
            
            // Allow access if substage or parent stage is assigned to current user
            if (substage.assigned_to != this.currentUserId && stage.assigned_to != this.currentUserId) {
                return {
                    success: false,
                    message: "You don't have access to this substage."
                };
            }
        }
        
        // Check for unread messages if data fetched successfully
        if (data.success) {
            this.updateChatNotificationCounter(stageId, substageId);
        }
        
        return data;
    }

    renderStageModal(stage, project) {
        // Create modal HTML with stage data
        const modalHtml = this.createStageModalHtml(stage, project);
        this.showModal(modalHtml);
        this.setupModalEventListeners();
    }

    renderSubstageModal(substage, stage, project) {
        // Create modal HTML with substage data
        const modalHtml = this.createSubstageModalHtml(substage, stage, project);
        this.showModal(modalHtml);
        this.setupModalEventListeners();
    }

    createStageModalHtml(stage, project) {
        const isPastDue = this.isPastDue(stage.end_date);
        const overdueMark = isPastDue ? '<span class="stage_detail_date_overdue">(Overdue)</span>' : '';
        
        return `
            <div class="stage_detail_modal_container">
                <div class="stage_detail_modal_header">
                    <div class="stage_detail_modal_title">
                        <i class="fas fa-tasks"></i>
                        Stage ${stage.stage_number || ''}
                    </div>
                    <div class="stage_detail_header_actions">
                        <button class="stage_detail_action_btn chat-btn" title="Chat" data-stage-id="${stage.id}">
                            <i class="fas fa-comments"></i>
                            <span class="chat-notification-counter" id="chat-counter-stage-${stage.id}">0</span>
                        </button>
                        <button class="stage_detail_action_btn activity-btn" title="Activity Log">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="stage_detail_modal_close">&times;</button>
                    </div>
                </div>
                <div class="stage_detail_modal_content">
                    <!-- Breadcrumbs -->
                    <div class="stage_detail_breadcrumbs">
                        <a href="#" class="project-link" data-project-id="${project.id}">${this.escapeHtml(project.title)}</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Stage ${stage.stage_number || ''}</span>
                    </div>
                    
                    <!-- Project Overview Section - Collapsible -->
                    <div class="project_overview_section_header">
                        <h4 class="project_overview_section_toggle">
                            <i class="fas fa-chevron-right project_overview_toggle_icon"></i>
                            <span>Show Project Overview</span>
                        </h4>
                    </div>
                    
                    <!-- Project Information Section -->
                    <div class="project_overview_section" style="display: none;">
                        <h4 class="project_overview_section_title"><i class="fas fa-project-diagram"></i> Project Information</h4>
                        <div class="project_overview_info">
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-file-signature"></i> Project Title</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.title) || 'Untitled Project'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-user-plus"></i> Assigned By</span>
                                <span class="project_overview_info_value">${project.created_by_name || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-calendar-plus"></i> Start Date</span>
                                <span class="project_overview_info_value">${this.formatDateTime(project.start_date) || 'Not set'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-calendar-check"></i> End Date</span>
                                <span class="project_overview_info_value">
                                    ${this.formatDateTime(project.end_date) || 'Not set'} 
                                    ${this.isPastDue(project.end_date) ? '<span class="project_overview_date_overdue">(Overdue)</span>' : ''}
                                </span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-user-shield"></i> Project Assigned</span>
                                <span class="project_overview_info_value">${project.assigned_to_name || 'Unassigned'}</span>
                            </div>
                        </div>
                        
                        <!-- Client Information -->
                        <h4 class="project_overview_section_title" style="margin-top: 20px;"><i class="fas fa-user-tie"></i> Client Information</h4>
                        <div class="project_overview_info">
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-id-card"></i> Client Name</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.client_name) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-phone"></i> Contact Number</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.contact_number) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-map-marker-alt"></i> Client Address</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.client_address) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-map-pin"></i> Project Location</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.project_location) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-ruler-combined"></i> Plot Area</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.plot_area) || 'Not specified'}</span>
                            </div>
                        </div>
                        
                        <!-- Project Team Section -->
                        <div class="project_overview_team_section">
                            <h4 class="project_overview_section_title"><i class="fas fa-users"></i> Project Team</h4>
                            <div class="project_overview_team_members">
                                ${this.renderTeamMembers(project.team_members || [])}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Basic Info Section -->
                    <div class="stage_detail_section">
                        <h4 class="stage_detail_section_title"><i class="fas fa-info-circle"></i> Stage Information</h4>
                        <div class="stage_detail_info">
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-tasks"></i> Status</span>
                                <span class="stage_detail_status_badge ${stage.status}">${this.formatStatus(stage.status)}</span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-calendar-plus"></i> Start Date</span>
                                <span class="stage_detail_info_value">${this.formatDateTime(stage.start_date) || 'Not set'}</span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-calendar-check"></i> Due Date</span>
                                <span class="stage_detail_info_value">
                                    ${this.formatDateTime(stage.end_date) || 'Not set'} ${overdueMark}
                                </span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-user"></i> Assigned To</span>
                                <span class="stage_detail_info_value">
                                    ${stage.assigned_to_profile ? 
                                    `<img src="${stage.assigned_to_profile}" alt="${stage.assigned_to_name}" class="user-avatar-small" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 5px; vertical-align: middle;">` : 
                                    `<span class="user-initials-small" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3b82f6; color: white; border-radius: 50%; font-size: 10px; margin-right: 5px; vertical-align: middle;">${this.getInitials(stage.assigned_to_name)}</span>`}
                                    ${stage.assigned_to_name || 'Unassigned'}
                                </span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-user-plus"></i> Assigned By</span>
                                <span class="stage_detail_info_value">
                                    ${stage.created_by_profile ? 
                                    `<img src="${stage.created_by_profile}" alt="${stage.created_by_name}" class="user-avatar-small" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 5px; vertical-align: middle;">` : 
                                    `<span class="user-initials-small" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #10b981; color: white; border-radius: 50%; font-size: 10px; margin-right: 5px; vertical-align: middle;">${this.getInitials(stage.created_by_name)}</span>`}
                                    ${stage.created_by_name || 'Not specified'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Substages Section -->
                    <div class="stage_detail_section">
                        <h4 class="stage_detail_section_title"><i class="fas fa-list"></i> Substages</h4>
                        ${this.renderSubstagesList(stage.substages || [])}
                    </div>
                    
                    <!-- Files Section -->
                    <div class="stage_detail_section">
                        <div class="stage_detail_section_header">
                            <h4 class="stage_detail_section_title files"><i class="fas fa-paperclip"></i> Attached Files</h4>
                            <button class="stage_detail_upload_btn" data-stage-id="${stage.id}">
                                <i class="fas fa-upload"></i> Upload File
                            </button>
                        </div>
                        <div class="stage_detail_file_upload_form" style="display: none;">
                            <form id="stageFileUploadForm">
                                <div class="form-group">
                                    <label for="stageFileInput">Select File</label>
                                    <input type="file" id="stageFileInput" name="file" required>
                                </div>
                                <div class="form-group">
                                    <label for="stageFileName">File Name</label>
                                    <input type="text" id="stageFileName" name="file_name" placeholder="Enter file name" required>
                                </div>
                                <input type="hidden" name="stage_id" value="${stage.id}">
                                <div class="form-actions">
                                    <button type="button" class="cancel-upload-btn">Cancel</button>
                                    <button type="submit" class="upload-file-btn">Upload</button>
                                </div>
                            </form>
                        </div>
                        <div id="stageFilesContainer">
                            ${this.renderFilesList(stage.files || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    createSubstageModalHtml(substage, stage, project) {
        const isPastDue = this.isPastDue(substage.end_date);
        const overdueMark = isPastDue ? '<span class="stage_detail_date_overdue">(Overdue)</span>' : '';
        
        // Create appropriate status options based on user role
        let statusOptions = '';
        
        // Basic options for all users
        statusOptions += `
            <option value="not_started" ${substage.status === 'not_started' ? 'selected' : ''}>Not Started</option>
            <option value="in_progress" ${substage.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
            <option value="completed" ${substage.status === 'completed' ? 'selected' : ''}>Completed</option>
        `;
        
        // Additional options only for admin users
        if (this.isAdmin) {
            statusOptions += `
                <option value="in_review" ${substage.status === 'in_review' ? 'selected' : ''}>In Review</option>
                <option value="on_hold" ${substage.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
            `;
        } else {
            // For non-admin users who have a substage with these statuses, show disabled option
            if (substage.status === 'in_review' || substage.status === 'on_hold') {
                statusOptions += `
                    <option value="${substage.status}" selected disabled>${this.formatStatus(substage.status)} (Set by Admin)</option>
                `;
            }
        }
        
        return `
            <div class="stage_detail_modal_container">
                <div class="stage_detail_modal_header">
                    <div class="stage_detail_modal_title">
                        <i class="fas fa-tasks"></i>
                        ${this.escapeHtml(substage.title || `Substage ${substage.substage_number || ''}`)}
                    </div>
                    <div class="stage_detail_header_actions">
                        <button class="stage_detail_action_btn chat-btn" title="Chat" data-substage-id="${substage.id}">
                            <i class="fas fa-comments"></i>
                            <span class="chat-notification-counter" id="chat-counter-substage-${substage.id}">0</span>
                        </button>
                        <button class="stage_detail_action_btn activity-btn" title="Activity Log">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="stage_detail_modal_close">&times;</button>
                    </div>
                </div>
                <div class="stage_detail_modal_content">
                    <!-- Breadcrumbs -->
                    <div class="stage_detail_breadcrumbs">
                        <a href="#" class="project-link" data-project-id="${project.id}">${this.escapeHtml(project.title)}</a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="#" class="stage-link" data-stage-id="${stage.id}">Stage ${stage.stage_number || ''}</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>${this.escapeHtml(substage.title || `Substage ${substage.substage_number || ''}`)}</span>
                    </div>
                    
                    <!-- Project Overview Section - Collapsible -->
                    <div class="project_overview_section_header">
                        <h4 class="project_overview_section_toggle">
                            <i class="fas fa-chevron-right project_overview_toggle_icon"></i>
                            <span>Show Project Overview</span>
                        </h4>
                    </div>
                    
                    <!-- Project Information Section -->
                    <div class="project_overview_section" style="display: none;">
                        <h4 class="project_overview_section_title"><i class="fas fa-project-diagram"></i> Project Information</h4>
                        <div class="project_overview_info">
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-file-signature"></i> Project Title</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.title) || 'Untitled Project'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-user-plus"></i> Assigned By</span>
                                <span class="project_overview_info_value">${project.created_by_name || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-calendar-plus"></i> Start Date</span>
                                <span class="project_overview_info_value">${this.formatDateTime(project.start_date) || 'Not set'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-calendar-check"></i> End Date</span>
                                <span class="project_overview_info_value">
                                    ${this.formatDateTime(project.end_date) || 'Not set'} 
                                    ${this.isPastDue(project.end_date) ? '<span class="project_overview_date_overdue">(Overdue)</span>' : ''}
                                </span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-user-shield"></i> Project Assigned</span>
                                <span class="project_overview_info_value">${project.assigned_to_name || 'Unassigned'}</span>
                            </div>
                        </div>
                        
                        <!-- Client Information -->
                        <h4 class="project_overview_section_title" style="margin-top: 20px;"><i class="fas fa-user-tie"></i> Client Information</h4>
                        <div class="project_overview_info">
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-id-card"></i> Client Name</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.client_name) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-phone"></i> Contact Number</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.contact_number) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-map-marker-alt"></i> Client Address</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.client_address) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-map-pin"></i> Project Location</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.project_location) || 'Not specified'}</span>
                            </div>
                            <div class="project_overview_info_item">
                                <span class="project_overview_info_label"><i class="fas fa-ruler-combined"></i> Plot Area</span>
                                <span class="project_overview_info_value">${this.escapeHtml(project.plot_area) || 'Not specified'}</span>
                            </div>
                        </div>
                        
                        <!-- Project Team Section -->
                        <div class="project_overview_team_section">
                            <h4 class="project_overview_section_title"><i class="fas fa-users"></i> Project Team</h4>
                            <div class="project_overview_team_members">
                                ${this.renderTeamMembers(project.team_members || [])}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Basic Info Section -->
                    <div class="stage_detail_section">
                        <h4 class="stage_detail_section_title"><i class="fas fa-info-circle"></i> Substage Information</h4>
                        <div class="stage_detail_info">
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-tasks"></i> Status</span>
                                <div class="stage_detail_status_select">
                                    <select class="substage-status-select" data-substage-id="${substage.id}">
                                        ${statusOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-calendar-plus"></i> Start Date</span>
                                <span class="stage_detail_info_value">${this.formatDateTime(substage.start_date) || 'Not set'}</span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-calendar-check"></i> Due Date</span>
                                <span class="stage_detail_info_value">
                                    ${this.formatDateTime(substage.end_date) || 'Not set'} ${overdueMark}
                                </span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-user"></i> Assigned To</span>
                                <span class="stage_detail_info_value">
                                    ${substage.assigned_to_profile ? 
                                    `<img src="${substage.assigned_to_profile}" alt="${substage.assigned_to_name}" class="user-avatar-small" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 5px; vertical-align: middle;">` : 
                                    `<span class="user-initials-small" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3b82f6; color: white; border-radius: 50%; font-size: 10px; margin-right: 5px; vertical-align: middle;">${this.getInitials(substage.assigned_to_name)}</span>`}
                                    ${substage.assigned_to_name || 'Unassigned'}
                                </span>
                            </div>
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-user-plus"></i> Assigned By</span>
                                <span class="stage_detail_info_value">
                                    ${substage.created_by_profile ? 
                                    `<img src="${substage.created_by_profile}" alt="${substage.created_by_name}" class="user-avatar-small" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 5px; vertical-align: middle;">` : 
                                    `<span class="user-initials-small" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #10b981; color: white; border-radius: 50%; font-size: 10px; margin-right: 5px; vertical-align: middle;">${this.getInitials(substage.created_by_name)}</span>`}
                                    ${substage.created_by_name || 'Not specified'}
                                </span>
                            </div>
                            ${substage.drawing_number ? `
                            <div class="stage_detail_info_item">
                                <span class="stage_detail_info_label"><i class="fas fa-pencil-ruler"></i> Drawing Number</span>
                                <span class="stage_detail_info_value">${this.escapeHtml(substage.drawing_number)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Files Section -->
                    <div class="stage_detail_section">
                        <div class="stage_detail_section_header">
                            <h4 class="stage_detail_section_title files"><i class="fas fa-paperclip"></i> Attached Files</h4>
                            <button class="substage_detail_upload_btn" data-substage-id="${substage.id}">
                                <i class="fas fa-upload"></i> Upload File
                            </button>
                        </div>
                        <div class="substage_detail_file_upload_form" style="display: none;">
                            <form id="substageFileUploadForm">
                                <div class="form-group">
                                    <label for="substageFileInput">Select File</label>
                                    <input type="file" id="substageFileInput" name="file" required>
                                </div>
                                <div class="form-group">
                                    <label for="substageFileName">File Name</label>
                                    <input type="text" id="substageFileName" name="file_name" placeholder="Enter file name" required>
                                </div>
                                <input type="hidden" name="substage_id" value="${substage.id}">
                                <div class="form-actions">
                                    <button type="button" class="cancel-upload-btn">Cancel</button>
                                    <button type="submit" class="upload-file-btn">Upload</button>
                                </div>
                            </form>
                        </div>
                        <div id="substageFilesContainer">
                            ${this.renderFilesList(substage.files || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderTasksList(tasks) {
        if (!tasks.length) {
            return '<div class="stage_detail_task_list"><p>No tasks found.</p></div>';
        }
        
        return `
            <div class="stage_detail_task_list">
                ${tasks.map(task => `
                    <div class="stage_detail_task_item" data-task-id="${task.id}">
                        <div class="stage_detail_task_checkbox ${task.status === 'completed' ? 'completed' : ''}">
                            ${task.status === 'completed' ? '<i class="fas fa-check"></i>' : ''}
                        </div>
                        <div class="stage_detail_task_content">
                            <div class="stage_detail_task_title">${this.escapeHtml(task.title)}</div>
                            ${task.description ? `<div class="stage_detail_task_description">${this.escapeHtml(task.description)}</div>` : ''}
                            <div class="stage_detail_task_meta">
                                <div class="stage_detail_task_date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>${this.formatDate(task.due_date) || 'No due date'}</span>
                                </div>
                                ${task.assigned_to ? `
                                <div class="stage_detail_task_assignee">
                                    <div class="stage_detail_task_assignee_avatar">${this.getInitials(task.assigned_to_name)}</div>
                                    <span>${this.escapeHtml(task.assigned_to_name)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderFilesList(files) {
        if (!files || !files.length) {
            return '<div class="stage_detail_empty_state">No files attached.</div>';
        }
        
        return `
            <div class="stage_detail_files_list">
                ${files.map(file => {
                    // Handle both file structures (stage_files and substage_files)
                    const fileId = file.id;
                    const fileName = file.original_name || file.file_name;
                    const fileType = file.file_type || this.getFileExtension(file.file_name);
                    const fileSize = file.file_size || 0;
                    
                    return `
                        <div class="stage_detail_file_item" data-file-id="${fileId}">
                            <div class="stage_detail_file_icon ${this.getFileIconClass(fileName)}">
                                <i class="fas ${this.getFileIcon(fileName)}"></i>
                            </div>
                            <div class="stage_detail_file_info">
                                <div class="stage_detail_file_name">${this.escapeHtml(fileName)}</div>
                                <div class="stage_detail_file_meta">
                                    <div class="stage_detail_file_size">
                                        ${this.formatFileSize(fileSize)}
                                    </div>
                                    <div class="stage_detail_file_actions">
                                        <button class="stage_detail_file_download" data-file-id="${fileId}" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderSubstagesList(substages) {
        if (!substages || substages.length === 0) {
            return `<div class="stage_detail_empty_state">No substages found for this stage.</div>`;
        }
        
        // Filter substages to only show those assigned to current user or unassigned
        const filteredSubstages = this.currentUserId ? 
            substages.filter(substage => 
                !substage.assigned_to || 
                substage.assigned_to == this.currentUserId
            ) : substages;
            
        if (filteredSubstages.length === 0) {
            return `<div class="stage_detail_empty_state">No substages assigned to you.</div>`;
        }
        
        return `
            <div class="stage_detail_substages_list">
                ${filteredSubstages.map(substage => {
                    const isPastDue = this.isPastDue(substage.end_date);
                    const overdueMark = isPastDue ? '<span class="overdue">(Overdue)</span>' : '';
                    
                    return `
                        <div class="stage_detail_substage_item" data-substage-id="${substage.id}">
                            <div class="stage_detail_substage_header">
                                <h5 class="stage_detail_substage_title">${this.escapeHtml(substage.title || `Substage ${substage.substage_number}`)}</h5>
                                <span class="stage_detail_status_badge ${substage.status}">${this.formatStatus(substage.status)}</span>
                            </div>
                            <div class="stage_detail_substage_meta">
                                <span>
                                    <i class="fas fa-user"></i>Assigned To:
                                    ${substage.assigned_to_profile ? 
                                    `<img src="${substage.assigned_to_profile}" alt="${substage.assigned_to_name}" class="user-avatar-small" style="width: 18px; height: 18px; border-radius: 50%; margin-right: 2px; vertical-align: middle;">` : 
                                    ''}
                                    ${substage.assigned_to_name || 'Unassigned'}
                                </span>
                                <span>
                                    <i class="fas fa-user-plus"></i>Assigned By:
                                    ${substage.created_by_profile ? 
                                    `<img src="${substage.created_by_profile}" alt="${substage.created_by_name}" class="user-avatar-small" style="width: 18px; height: 18px; border-radius: 50%; margin-right: 2px; vertical-align: middle;">` : 
                                    ''}
                                    ${substage.created_by_name || 'Unknown'}
                                </span>
                                <span><i class="fas fa-calendar"></i> Due: ${this.formatDateTime(substage.end_date) || 'Not set'} ${overdueMark}</span>
                                ${substage.drawing_number ? `<span><i class="fas fa-file-alt"></i> Drawing: ${substage.drawing_number}</span>` : ''}
                            </div>
                            <div class="stage_detail_substage_actions">
                                <button class="stage_detail_substage_view_btn" data-substage-id="${substage.id}">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    showModal(modalHtml) {
        // Remove any existing modal first
        if (this.modalOverlay) {
            document.body.removeChild(this.modalOverlay);
            this.modalOverlay = null;
        }
        
        // Create modal overlay with proper centering
        this.modalOverlay = document.createElement('div');
        this.modalOverlay.className = 'stage_detail_modal_overlay';
        this.modalOverlay.style.position = 'fixed';
        this.modalOverlay.style.top = '0';
        this.modalOverlay.style.left = '0';
        this.modalOverlay.style.right = '0';
        this.modalOverlay.style.bottom = '0';
        this.modalOverlay.style.display = 'flex';
        this.modalOverlay.style.alignItems = 'center';
        this.modalOverlay.style.justifyContent = 'center';
        this.modalOverlay.style.zIndex = '9999';
        this.modalOverlay.style.width = '100%';
        this.modalOverlay.style.height = '100%';
        this.modalOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        this.modalOverlay.style.overflow = 'auto';
        this.modalOverlay.style.padding = '20px';
        
        // Add the HTML content
        this.modalOverlay.innerHTML = modalHtml;
        
        // Append to body
        document.body.appendChild(this.modalOverlay);
        
        // Reference to the modal container
        this.modalContainer = this.modalOverlay.querySelector('.stage_detail_modal_container');
        
        // Ensure the container is properly styled
        if (this.modalContainer) {
            this.modalContainer.style.margin = '0 auto';
            this.modalContainer.style.position = 'relative';
            this.modalContainer.style.transform = 'none';
            this.modalContainer.style.maxWidth = '900px';
            this.modalContainer.style.width = '100%';
            this.modalContainer.style.maxHeight = '90vh';
        }
        
        // Prevent closing when clicking on the modal content
        this.modalContainer.addEventListener('click', e => {
            e.stopPropagation();
        });
        
        // Disable body scroll when modal is open
        document.body.style.overflow = 'hidden';
        
        this.isOpen = true;
    }

    setupModalEventListeners() {
        // Close modal when clicking on the close button
        const closeBtn = this.modalContainer.querySelector('.stage_detail_modal_close');
        closeBtn.addEventListener('click', () => {
            this.closeModal();
        });
        
        // Close modal when clicking on the overlay (outside the modal)
        this.modalOverlay.addEventListener('click', (e) => {
            if (e.target === this.modalOverlay) {
                this.closeModal();
            }
        });
        
        // Handle chat button click for stages and substages
        const chatBtn = this.modalContainer.querySelector('.stage_detail_action_btn.chat-btn');
        if (chatBtn) {
            chatBtn.addEventListener('click', () => {
                // Reset notification counter when opening chat
                const chatCounter = chatBtn.querySelector('.chat-notification-counter');
                if (chatCounter) {
                    chatCounter.textContent = '0';
                    chatCounter.classList.add('hidden');
                }
                
                // Check if we're in a stage or substage modal
                if (this.currentSubstageId) {
                    // We're in a substage modal
                    const projectId = this.modalContainer.querySelector('.project-link')?.dataset.projectId;
                    const stageId = this.modalContainer.querySelector('.stage-link')?.dataset.stageId;
                    const substageTitle = this.modalContainer.querySelector('.stage_detail_modal_title')?.textContent.trim();
                    
                    if (projectId && stageId && this.currentSubstageId) {
                        // Initialize stage chat if needed
                        if (!window.stageChat) {
                            window.stageChat = new StageChat();
                        }
                        
                        // Open substage chat
                        window.stageChat.openSubstageChat(
                            projectId, 
                            stageId, 
                            this.currentSubstageId, 
                            substageTitle,
                            chatBtn // Pass the button as the source element for positioning
                        );
                    }
                } else {
                    // We're in a stage modal
                    const projectId = this.modalContainer.querySelector('.project-link')?.dataset.projectId;
                    
                    // Get the stage number/title from the modal title instead of using the ID
                    const stageTitle = this.modalContainer.querySelector('.stage_detail_modal_title')?.textContent.trim();
                    
                    if (projectId && this.currentStageId) {
                        // Initialize stage chat if needed
                        if (!window.stageChat) {
                            window.stageChat = new StageChat();
                        }
                        
                        // Open stage chat with the proper title from the modal
                        window.stageChat.openChat(
                            projectId, 
                            this.currentStageId, 
                            stageTitle, // Use the actual stage title from the modal
                            chatBtn, // Pass the button as the source element for positioning
                            null,
                            null
                        );
                    }
                }
            });
        }
        
        // Handle project title click to return to calendar overlay
        const projectLink = this.modalContainer.querySelector('.project-link');
        if (projectLink) {
            projectLink.addEventListener('click', (e) => {
                e.preventDefault();
                const projectId = projectLink.dataset.projectId;
                if (projectId) {
                    // Close current modal
                    this.closeModal();
                    
                    // Open the project brief modal instead of showing calendar overlay
                    if (window.projectBriefModal) {
                        // Use the existing instance of ProjectBriefModal
                        window.projectBriefModal.openProjectModal(projectId);
                    } else {
                        // If for some reason the global instance isn't available, try to create one
                        try {
                            // Check if ProjectBriefModal class exists
                            if (typeof ProjectBriefModal === 'function') {
                                const modal = new ProjectBriefModal();
                                modal.openProjectModal(projectId);
                            } else {
                                console.error('ProjectBriefModal class not found');
                                // Fallback to default behavior
                                window.location.href = `project-details.php?id=${projectId}`;
                            }
                        } catch (error) {
                            console.error('Error opening project brief modal:', error);
                            // Fallback to default behavior
                            window.location.href = `project-details.php?id=${projectId}`;
                        }
                    }
                }
            });
        }
        
        // Handle breadcrumb stage navigation (for substage modal)
        const stageLink = this.modalContainer.querySelector('.stage-link');
        if (stageLink) {
            stageLink.addEventListener('click', (e) => {
                e.preventDefault();
                const stageId = stageLink.dataset.stageId;
                if (stageId) {
                    // Get the project ID from the project link in breadcrumb
                    const projectLink = this.modalContainer.querySelector('.project-link');
                    const projectId = projectLink?.dataset.projectId;
                    
                    if (projectId) {
                        // Don't close the current modal - just update the content
                        // This keeps us in the stage_detail_modal_overlay
                        this.loadStageContent(projectId, stageId);
                    }
                }
            });
        }

        // Handle substage view buttons
        const substageViewButtons = this.modalContainer.querySelectorAll('.stage_detail_substage_view_btn');
        substageViewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const substageId = button.dataset.substageId;
                if (substageId) {
                    // Get the project ID from the breadcrumb
                    const projectLink = this.modalContainer.querySelector('.project-link');
                    const projectId = projectLink?.dataset.projectId;
                    
                    if (projectId && this.currentStageId && substageId) {
                        // Load the substage content without closing the modal
                        this.loadSubstageContent(projectId, this.currentStageId, substageId);
                    }
                }
            });
        });
        
        // Handle substage status change
        const statusSelect = this.modalContainer.querySelector('.substage-status-select');
        if (statusSelect) {
            statusSelect.addEventListener('change', () => {
                const substageId = statusSelect.dataset.substageId;
                const newStatus = statusSelect.value;
                if (substageId && newStatus) {
                    this.updateSubstageStatus(substageId, newStatus);
                }
            });
        }
        
        // Handle file download clicks
        const fileDownloadButtons = this.modalContainer.querySelectorAll('.stage_detail_file_download');
        fileDownloadButtons.forEach(button => {
            button.addEventListener('click', () => {
                const fileId = button.dataset.fileId;
                if (fileId) {
                    this.downloadFile(fileId);
                }
            });
        });
        
        // Handle file upload for stages
        const stageUploadBtn = this.modalContainer.querySelector('.stage_detail_upload_btn');
        if (stageUploadBtn) {
            stageUploadBtn.addEventListener('click', () => {
                const stageId = stageUploadBtn.dataset.stageId;
                const uploadForm = this.modalContainer.querySelector('.stage_detail_file_upload_form');
                
                // Toggle form visibility
                if (uploadForm.style.display === 'none') {
                    uploadForm.style.display = 'block';
                } else {
                    uploadForm.style.display = 'none';
                }
            });
            
            // Handle cancel button click
            const cancelStageUploadBtn = this.modalContainer.querySelector('#stageFileUploadForm .cancel-upload-btn');
            if (cancelStageUploadBtn) {
                cancelStageUploadBtn.addEventListener('click', () => {
                    const uploadForm = this.modalContainer.querySelector('.stage_detail_file_upload_form');
                    uploadForm.style.display = 'none';
                });
            }
            
            // Handle form submission
            const stageUploadForm = this.modalContainer.querySelector('#stageFileUploadForm');
            if (stageUploadForm) {
                stageUploadForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.uploadStageFile(stageUploadForm);
                });
            }
        }
        
        // Handle file upload for substages
        const substageUploadBtn = this.modalContainer.querySelector('.substage_detail_upload_btn');
        if (substageUploadBtn) {
            substageUploadBtn.addEventListener('click', () => {
                const substageId = substageUploadBtn.dataset.substageId;
                
                // Check if the substage is assigned to the current user
                let isAssignedToCurrentUser = false;
                
                // Find the substage in the current data
                if (this.currentSubstage && this.currentSubstage.id == substageId) {
                    isAssignedToCurrentUser = this.isSubstageAssignedToCurrentUser(this.currentSubstage);
                } else if (this.currentStage && this.currentStage.substages) {
                    // Look for the substage in the current stage's substages
                    const substage = this.currentStage.substages.find(s => s.id == substageId);
                    if (substage) {
                        isAssignedToCurrentUser = this.isSubstageAssignedToCurrentUser(substage);
                    }
                }
                
                // If not assigned, show an error and return
                if (!isAssignedToCurrentUser) {
                    alert('This substage is not assigned to you. You cannot upload files to it.');
                    return;
                }
                
                const uploadForm = this.modalContainer.querySelector('.substage_detail_file_upload_form');
                
                // Toggle form visibility
                if (uploadForm.style.display === 'none') {
                    uploadForm.style.display = 'block';
                } else {
                    uploadForm.style.display = 'none';
                }
            });
            
            // Handle cancel button click
            const cancelSubstageUploadBtn = this.modalContainer.querySelector('#substageFileUploadForm .cancel-upload-btn');
            if (cancelSubstageUploadBtn) {
                cancelSubstageUploadBtn.addEventListener('click', () => {
                    const uploadForm = this.modalContainer.querySelector('.substage_detail_file_upload_form');
                    uploadForm.style.display = 'none';
                });
            }
            
            // Handle form submission
            const substageUploadForm = this.modalContainer.querySelector('#substageFileUploadForm');
            if (substageUploadForm) {
                substageUploadForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.uploadSubstageFile(substageUploadForm);
                });
            }
        }
        
        // Handle team member substage toggles
        const stageRoleToggles = this.modalContainer.querySelectorAll('.stage_role_toggle');
        stageRoleToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                const memberId = toggle.dataset.memberId;
                const stageIndex = toggle.dataset.stageIndex;
                
                // Toggle arrow
                toggle.classList.toggle('expanded');
                
                // Find corresponding substages container and toggle it
                const container = this.modalContainer.querySelector(`.substages_container[data-member-id="${memberId}"][data-stage-index="${stageIndex}"]`);
                if (container) {
                    container.classList.toggle('expanded');
                }
            });
        });
        
        // Handle project overview toggle in substage view
        const projectOverviewToggle = this.modalContainer.querySelector('.project_overview_section_header');
        if (projectOverviewToggle) {
            projectOverviewToggle.addEventListener('click', () => {
                const overviewSection = this.modalContainer.querySelector('.project_overview_section');
                const toggleIcon = this.modalContainer.querySelector('.project_overview_toggle_icon');
                const toggleText = projectOverviewToggle.querySelector('span');
                
                if (overviewSection.style.display === 'none') {
                    overviewSection.style.display = 'block';
                    toggleIcon.classList.add('expanded');
                    toggleText.textContent = 'Hide Project Overview';
                } else {
                    overviewSection.style.display = 'none';
                    toggleIcon.classList.remove('expanded');
                    toggleText.textContent = 'Show Project Overview';
                }
            });
        }

        // Handle action buttons (chat, activities)
        const actionBtns = this.modalContainer.querySelectorAll('.stage_detail_action_btn');
        actionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (btn.classList.contains('chat')) {
                    // Open the stage chat
                    if (window.stageChat) {
                        const stageName = this.currentStage ? `Stage ${this.currentStage.stage_number}: ${this.currentStage.title}` : 'Stage Chat';
                        window.stageChat.openChat(this.currentProjectId, this.currentStageId, stageName, btn);
                    } else {
                        // If stageChat is not initialized yet, do it now
                        window.stageChat = new StageChat();
                        const stageName = this.currentStage ? `Stage ${this.currentStage.stage_number}: ${this.currentStage.title}` : 'Stage Chat';
                        window.stageChat.openChat(this.currentProjectId, this.currentStageId, stageName, btn);
                    }
                } else if (btn.classList.contains('activity')) {
                    alert('Stage activity log functionality will be implemented soon.');
                }
            });
        });

        // Handle substage action buttons
        const substageActionBtns = this.modalContainer.querySelectorAll('.substage_detail_action_btn');
        substageActionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const substageId = btn.closest('[data-substage-id]').dataset.substageId;
                
                if (btn.classList.contains('chat')) {
                    // Find the substage data to get the title
                    const substageElement = btn.closest('[data-substage-id]');
                    const substageTitle = substageElement ? substageElement.querySelector('.substage_title')?.textContent.trim() : 'Substage Chat';
                    
                    // Open the substage chat
                    if (window.stageChat) {
                        window.stageChat.openSubstageChat(this.currentProjectId, this.currentStageId, substageId, substageTitle, btn);
                    } else {
                        // If stageChat is not initialized yet, do it now
                        window.stageChat = new StageChat();
                        window.stageChat.openSubstageChat(this.currentProjectId, this.currentStageId, substageId, substageTitle, btn);
                    }
                } else if (btn.classList.contains('activity')) {
                    alert('Substage activity log functionality will be implemented soon.');
                }
            });
        });
    }

    // New method to load stage content without closing the modal
    async loadStageContent(projectId, stageId) {
        this.currentStageId = stageId;
        this.currentSubstageId = null;
        
        try {
            // Show loading indicator within the modal
            this.showLoadingIndicator();
            
            const data = await this.fetchStageData(projectId, stageId);
            if (data.success) {
                // Fetch team members for the project
                const teamData = await this.fetchProjectTeam(projectId);
                
                // Initialize team_members array if it doesn't exist
                data.project.team_members = teamData.success ? teamData.team_members : [];
                
                // Ensure we include the stage's assigned user in the team members list
                if (data.stage.assigned_to && data.stage.assigned_to_name) {
                    // Try to fetch the profile picture
                    const profilePicture = await this.fetchUserProfilePicture(data.stage.assigned_to);
                    
                    // Add user to team members if not already present with this role
                    let userExistsWithRole = false;
                    for (const member of data.project.team_members) {
                        if (member.id === data.stage.assigned_to && 
                            member.role === 'Stage Assigned' && 
                            member.stage_number === data.stage.stage_number) {
                            userExistsWithRole = true;
                            break;
                        }
                    }
                    
                    if (!userExistsWithRole) {
                        data.project.team_members.push({
                            id: data.stage.assigned_to,
                            name: data.stage.assigned_to_name,
                            role: 'Stage Assigned',
                            stage_number: data.stage.stage_number || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include project owner if available
                if (data.project.assigned_to && data.project.assigned_to_name) {
                    // Try to fetch the profile picture
                    const profilePicture = await this.fetchUserProfilePicture(data.project.assigned_to);
                    
                    let projectOwnerExists = false;
                    for (const member of data.project.team_members) {
                        if (member.id === data.project.assigned_to && member.role === 'Project Assigned') {
                            projectOwnerExists = true;
                            break;
                        }
                    }
                    
                    if (!projectOwnerExists) {
                        data.project.team_members.push({
                            id: data.project.assigned_to,
                            name: data.project.assigned_to_name,
                            role: 'Project Assigned',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Also include substage assigned users
                if (data.stage.substages && data.stage.substages.length > 0) {
                    for (const substage of data.stage.substages) {
                        if (substage.assigned_to && substage.assigned_to_name) {
                            // Try to fetch the profile picture
                            const profilePicture = await this.fetchUserProfilePicture(substage.assigned_to);
                            
                            let substageUserExists = false;
                            for (const member of data.project.team_members) {
                                if (member.id === substage.assigned_to && 
                                    member.role === 'Substage Assigned' && 
                                    member.substage_number === substage.substage_number) {
                                    substageUserExists = true;
                                    break;
                                }
                            }
                            
                            if (!substageUserExists) {
                                data.project.team_members.push({
                                    id: substage.assigned_to,
                                    name: substage.assigned_to_name,
                                    role: 'Substage Assigned',
                                    stage_number: data.stage.stage_number || '',
                                    substage_number: substage.substage_number || '',
                                    substage_title: substage.title || '',
                                    profile_picture: profilePicture
                                });
                            }
                        }
                    }
                }
                
                // Ensure client information is available
                data.project.client_name = data.project.client_name || 'Not specified';
                data.project.client_address = data.project.client_address || 'Not specified';
                data.project.project_location = data.project.project_location || 'Not specified';
                data.project.plot_area = data.project.plot_area || 'Not specified';
                data.project.contact_number = data.project.contact_number || 'Not specified';
                
                // Create modal HTML with stage data
                const modalHtml = this.createStageModalHtml(data.stage, data.project);
                
                // Update the modal content (not replacing the entire modal)
                this.updateModalContent(modalHtml);
                
                // Re-setup event listeners for the new content
                this.setupModalEventListeners();
            } else {
                console.error('Error fetching stage data:', data.message);
                this.showNotification('Error', data.message || 'Error loading stage data', 'error');
            }
        } catch (error) {
            console.error('Error fetching stage data:', error);
            this.showNotification('Error', 'An unexpected error occurred', 'error');
        }
    }

    // New method to load substage content without closing the modal
    async loadSubstageContent(projectId, stageId, substageId) {
        this.currentStageId = stageId;
        this.currentSubstageId = substageId;
        
        try {
            // Show loading indicator within the modal
            this.showLoadingIndicator();
            
            const data = await this.fetchSubstageData(projectId, stageId, substageId);
            if (data.success) {
                // Fetch team members for the project
                const teamData = await this.fetchProjectTeam(projectId);
                
                // Initialize team_members array if it doesn't exist
                data.project.team_members = teamData.success ? teamData.team_members : [];
                
                // Ensure we include the substage's assigned user in the team members list
                if (data.substage.assigned_to && data.substage.assigned_to_name) {
                    // Try to fetch the profile picture
                    const profilePicture = await this.fetchUserProfilePicture(data.substage.assigned_to);
                    
                    let userExistsWithRole = false;
                    for (const member of data.project.team_members) {
                        if (member.id === data.substage.assigned_to && 
                            member.role === 'Substage Assigned' && 
                            member.substage_number === data.substage.substage_number) {
                            userExistsWithRole = true;
                            break;
                        }
                    }
                    
                    if (!userExistsWithRole) {
                        data.project.team_members.push({
                            id: data.substage.assigned_to,
                            name: data.substage.assigned_to_name,
                            role: 'Substage Assigned',
                            stage_number: data.stage.stage_number || '',
                            substage_number: data.substage.substage_number || '',
                            substage_title: data.substage.title || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include stage owner if available
                if (data.stage.assigned_to && data.stage.assigned_to_name) {
                    // Try to fetch the profile picture
                    const profilePicture = await this.fetchUserProfilePicture(data.stage.assigned_to);
                    
                    let stageOwnerExists = false;
                    for (const member of data.project.team_members) {
                        if (member.id === data.stage.assigned_to && 
                            member.role === 'Stage Assigned' && 
                            member.stage_number === data.stage.stage_number) {
                            stageOwnerExists = true;
                            break;
                        }
                    }
                    
                    if (!stageOwnerExists) {
                        data.project.team_members.push({
                            id: data.stage.assigned_to,
                            name: data.stage.assigned_to_name,
                            role: 'Stage Assigned',
                            stage_number: data.stage.stage_number || '',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Include project owner if available
                if (data.project.assigned_to && data.project.assigned_to_name) {
                    // Try to fetch the profile picture
                    const profilePicture = await this.fetchUserProfilePicture(data.project.assigned_to);
                    
                    let projectOwnerExists = false;
                    for (const member of data.project.team_members) {
                        if (member.id === data.project.assigned_to && member.role === 'Project Assigned') {
                            projectOwnerExists = true;
                            break;
                        }
                    }
                    
                    if (!projectOwnerExists) {
                        data.project.team_members.push({
                            id: data.project.assigned_to,
                            name: data.project.assigned_to_name,
                            role: 'Project Assigned',
                            profile_picture: profilePicture
                        });
                    }
                }
                
                // Ensure client information is available
                data.project.client_name = data.project.client_name || 'Not specified';
                data.project.client_address = data.project.client_address || 'Not specified';
                data.project.project_location = data.project.project_location || 'Not specified';
                data.project.plot_area = data.project.plot_area || 'Not specified';
                data.project.contact_number = data.project.contact_number || 'Not specified';
                
                // Create modal HTML with substage data
                const modalHtml = this.createSubstageModalHtml(data.substage, data.stage, data.project);
                
                // Update the modal content (not replacing the entire modal)
                this.updateModalContent(modalHtml);
                
                // Re-setup event listeners for the new content
                this.setupModalEventListeners();
            } else {
                console.error('Error fetching substage data:', data.message);
                this.showNotification('Error', data.message || 'Error loading substage data', 'error');
            }
        } catch (error) {
            console.error('Error fetching substage data:', error);
            this.showNotification('Error', 'An unexpected error occurred', 'error');
        }
    }

    // Helper method to update modal content without closing it
    updateModalContent(newHtml) {
        if (!this.modalContainer) return;
        
        // Parse the HTML string into a DOM element
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newHtml;
        const newContent = tempDiv.firstElementChild;
        
        // Replace the entire contents of the modal container
        this.modalContainer.innerHTML = newContent.innerHTML;
    }

    // Helper method to show a loading indicator within the modal
    showLoadingIndicator() {
        if (!this.modalContainer) return;
        
        // Create a simple loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'stage_detail_loading';
        loadingDiv.innerHTML = `
            <div class="stage_detail_loading_spinner"></div>
            <div class="stage_detail_loading_text">Loading...</div>
        `;
        
        // Add loading indicator styles if needed
        if (!document.getElementById('stage-detail-loading-styles')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'stage-detail-loading-styles';
            styleEl.textContent = `
                .stage_detail_loading {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(255, 255, 255, 0.8);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    z-index: 10;
                }
                
                .stage_detail_loading_spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3b82f6;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                
                .stage_detail_loading_text {
                    margin-top: 10px;
                    font-size: 14px;
                    color: #333;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(styleEl);
        }
        
        // Append loading indicator to the modal content
        const modalContent = this.modalContainer.querySelector('.stage_detail_modal_content');
        if (modalContent) {
            modalContent.style.position = 'relative';
            modalContent.appendChild(loadingDiv);
        }
    }

    closeModal() {
        if (this.modalOverlay) {
            document.body.removeChild(this.modalOverlay);
            this.modalOverlay = null;
            this.modalContainer = null;
            this.isOpen = false;
            
            // Re-enable body scroll
            document.body.style.overflow = '';
        }
    }

    async updateTaskStatus(taskId, isCompleted) {
        try {
            const response = await fetch('update_tas_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    status: isCompleted ? 'completed' : 'pending'
                })
            });
            
            const data = await response.json();
            if (!data.success) {
                console.error('Error updating task status:', data.message);
            }
        } catch (error) {
            console.error('Error updating task status:', error);
        }
    }

    downloadFile(fileId) {
        // Figure out if this is for a stage or substage file based on current modal state
        const endpoint = this.currentSubstageId ? 'download_file.php?substage_file_id=' + fileId : 'download_file.php?stage_file_id=' + fileId;
        window.open(endpoint, '_blank');
    }

    // Helper functions
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    formatDateTime(dateTimeString) {
        if (!dateTimeString) return '';
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    formatStatus(status) {
        if (!status) return 'Unknown';
        return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }
    
    formatFileSize(bytes) {
        if (!bytes) return '0 Bytes';
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    getFileIcon(filename) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        
        const iconMap = {
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'xls': 'fa-file-excel',
            'xlsx': 'fa-file-excel',
            'ppt': 'fa-file-powerpoint',
            'pptx': 'fa-file-powerpoint',
            'jpg': 'fa-file-image',
            'jpeg': 'fa-file-image',
            'png': 'fa-file-image',
            'gif': 'fa-file-image',
            'zip': 'fa-file-archive',
            'rar': 'fa-file-archive',
            'txt': 'fa-file-alt'
        };
        
        return iconMap[ext] || 'fa-file';
    }
    
    getInitials(name) {
        if (!name) return '';
        return name.split(' ')
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase()
            .substring(0, 2);
    }
    
    getCurrentUserInitials() {
        // This should be replaced with actual username from the system
        return 'ME';
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
    
    getFileIconClass(filename) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        
        const classMap = {
            'pdf': 'pdf',
            'doc': 'doc',
            'docx': 'doc',
            'xls': 'xls',
            'xlsx': 'xls',
            'jpg': 'image',
            'jpeg': 'image',
            'png': 'image',
            'gif': 'image'
        };
        
        return classMap[ext] || '';
    }

    async updateSubstageStatus(substageId, status) {
        try {
            // Show loading indicator
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Updating status...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
            
            const response = await fetch('update_substage_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    substage_id: substageId,
                    status: status
                })
            });
            
            // Check if the response is valid JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Invalid response format. Expected JSON but got:', await response.text());
                this.showNotification('Error', 'Invalid response format from server', 'error');
                return;
            }
            
            try {
                const data = await response.json();
                if (data.success) {
                    // Show a success notification
                    this.showNotification('Success', data.message || 'Substage status updated successfully', 'success');
                } else {
                    console.error('Error updating substage status:', data.message);
                    this.showNotification('Error', data.message || 'Failed to update status', 'error');
                }
            } catch (jsonError) {
                console.error('JSON parsing error:', jsonError);
                // Try to get the raw response to debug
                const rawResponse = await response.text();
                console.error('Raw response:', rawResponse);
                this.showNotification('Error', 'Invalid response from server', 'error');
            }
            
        } catch (error) {
            console.error('Network error updating substage status:', error);
            this.showNotification('Error', 'Network error occurred', 'error');
        } finally {
            // Close loading indicator if it was shown
            if (typeof Swal !== 'undefined' && Swal.isLoading()) {
                Swal.close();
            }
        }
    }

    showNotification(title, message, type = 'info') {
        // Check if SweetAlert2 is available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: message,
                icon: type,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            // Fallback to alert if SweetAlert2 is not available
            alert(`${title}: ${message}`);
        }
    }

    // Upload file to a stage
    async uploadStageFile(form) {
        try {
            // Show loading indicator
            this.showNotification('Uploading', 'Uploading file, please wait...', 'info');
            
            const formData = new FormData(form);
            const fileInput = form.querySelector('input[type="file"]');
            
            // Auto-fill the file name field if empty
            const fileNameInput = form.querySelector('input[name="file_name"]');
            if (!fileNameInput.value && fileInput.files[0]) {
                const originalFileName = fileInput.files[0].name;
                fileNameInput.value = originalFileName.split('.').slice(0, -1).join('.'); // Remove extension
                formData.set('file_name', fileNameInput.value);
            }
            
            // Submit form via AJAX
            const response = await fetch('upload_stage_file.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                this.showNotification('Success', 'File uploaded successfully', 'success');
                
                // Hide form and reset it
                form.reset();
                const uploadForm = this.modalContainer.querySelector('.stage_detail_file_upload_form');
                uploadForm.style.display = 'none';
                
                // Refresh the files list
                await this.refreshStageFiles();
            } else {
                this.showNotification('Error', data.message || 'Failed to upload file', 'error');
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            this.showNotification('Error', 'An unexpected error occurred', 'error');
        }
    }
    
    // Upload file to a substage
    async uploadSubstageFile(form) {
        try {
            console.log('Starting substage file upload process');
            
            const formData = new FormData(form);
            const substageId = formData.get('substage_id');
            
            // Check if the substage is assigned to the current user
            let isAssignedToCurrentUser = false;
            
            // Find the substage in the current data
            if (this.currentSubstage && this.currentSubstage.id == substageId) {
                isAssignedToCurrentUser = this.isSubstageAssignedToCurrentUser(this.currentSubstage);
            } else if (this.currentStage && this.currentStage.substages) {
                // Look for the substage in the current stage's substages
                const substage = this.currentStage.substages.find(s => s.id == substageId);
                if (substage) {
                    isAssignedToCurrentUser = this.isSubstageAssignedToCurrentUser(substage);
                }
            }
            
            // If not assigned, show an error and return
            if (!isAssignedToCurrentUser) {
                this.showNotification('Error', 'This substage is not assigned to you. You cannot upload files to it.', 'error');
                return;
            }
            
            // Show loading indicator
            this.showNotification('Uploading', 'Uploading file, please wait...', 'info');
            
            const fileInput = form.querySelector('input[type="file"]');
            
            // Basic client-side validation
            if (!fileInput.files || fileInput.files.length === 0) {
                console.error('No file selected');
                this.showNotification('Error', 'Please select a file to upload', 'error');
                return;
            }
            
            const file = fileInput.files[0];
            
            // Check file size
            if (file.size <= 0) {
                console.error('File is empty (0 bytes)');
                this.showNotification('Error', 'The selected file is empty (0 bytes)', 'error');
                return;
            }
            
            // Log form data
            console.log('Substage ID:', formData.get('substage_id'));
            console.log('File name:', formData.get('file_name'));
            console.log('File object:', file);
            console.log('File size:', file.size, 'bytes');
            
            // Auto-fill the file name field if empty
            const fileNameInput = form.querySelector('input[name="file_name"]');
            if (!fileNameInput.value && fileInput.files[0]) {
                const originalFileName = fileInput.files[0].name;
                fileNameInput.value = originalFileName.split('.').slice(0, -1).join('.'); // Remove extension
                formData.set('file_name', fileNameInput.value);
                console.log('Auto-filled file name:', fileNameInput.value);
            }
            
            console.log('Making fetch request to substage_file_uploader.php');
            
            // Submit form via AJAX
            const response = await fetch('substage_file_uploader.php', {
                method: 'POST',
                body: formData
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', [...response.headers.entries()]);
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                // Show success message
                this.showNotification('Success', 'File uploaded successfully', 'success');
                
                // Hide form and reset it
                form.reset();
                const uploadForm = this.modalContainer.querySelector('.substage_detail_file_upload_form');
                uploadForm.style.display = 'none';
                
                // Refresh the files list
                await this.refreshSubstageFiles();
            } else {
                console.error('File upload failed:', data.message);
                this.showNotification('Error', data.message || 'Failed to upload file', 'error');
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            this.showNotification('Error', 'An unexpected error occurred', 'error');
        }
    }
    
    // Refresh the list of stage files
    async refreshStageFiles() {
        if (!this.currentStageId) return;
        
        try {
            const response = await fetch('get_stage_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    stage_id: this.currentStageId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const filesContainer = this.modalContainer.querySelector('#stageFilesContainer');
                filesContainer.innerHTML = this.renderFilesList(data.files || []);
                
                // Re-attach download event listeners
                this.setupFileDownloadListeners();
            }
        } catch (error) {
            console.error('Error refreshing stage files:', error);
        }
    }
    
    // Refresh the list of substage files
    async refreshSubstageFiles() {
        if (!this.currentSubstageId) return;
        
        try {
            const response = await fetch('get_substage_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    substage_id: this.currentSubstageId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const filesContainer = this.modalContainer.querySelector('#substageFilesContainer');
                filesContainer.innerHTML = this.renderFilesList(data.files || []);
                
                // Re-attach download event listeners
                this.setupFileDownloadListeners();
            }
        } catch (error) {
            console.error('Error refreshing substage files:', error);
        }
    }
    
    // Setup file download listeners for the newly added files
    setupFileDownloadListeners() {
        const fileDownloadButtons = this.modalContainer.querySelectorAll('.stage_detail_file_download');
        fileDownloadButtons.forEach(button => {
            button.addEventListener('click', () => {
                const fileId = button.dataset.fileId;
                if (fileId) {
                    this.downloadFile(fileId);
                }
            });
        });
    }

    // Helper to get file extension from filename
    getFileExtension(filename) {
        if (!filename) return '';
        return filename.split('.').pop().toLowerCase();
    }

    renderTeamMembers(teamMembers) {
        if (!teamMembers || teamMembers.length === 0) {
            return '<div class="stage_detail_empty_state">No team members found.</div>';
        }
        
        // Group team members by their ID to handle multiple roles per user
        const groupedMembers = {};
        
        // First, group members by ID
        teamMembers.forEach(member => {
            if (!groupedMembers[member.id]) {
                groupedMembers[member.id] = {
                    id: member.id,
                    name: member.name,
                    profile_picture: member.profile_picture,
                    roles: [],
                    stageRoles: {},
                    hasSubstages: false,
                    lowestStageNumber: member.stage_number ? parseInt(member.stage_number) : 9999 // Default high number for sorting
                };
            }
            
            // Track the lowest stage number for this member (for sorting)
            if (member.stage_number && parseInt(member.stage_number) < groupedMembers[member.id].lowestStageNumber) {
                groupedMembers[member.id].lowestStageNumber = parseInt(member.stage_number);
            }
            
            // Format role with stage or substage number if available
            let roleDisplay = member.role;
            if (member.role === 'Stage Assigned' && member.stage_number) {
                roleDisplay = `Stage ${member.stage_number}`;
                
                // Track this as a stage role
                if (!groupedMembers[member.id].stageRoles[member.stage_number]) {
                    groupedMembers[member.id].stageRoles[member.stage_number] = {
                        display: roleDisplay,
                        substages: [],
                        stageNumber: parseInt(member.stage_number)
                    };
                }
            } else if (member.role === 'Substage Assigned') {
                if (member.stage_number && member.substage_number) {
                    // Format role display for substages
                    let substageDisplay = `Substage ${member.stage_number}.${member.substage_number}`;
                    
                    // Add title if available
                    if (member.substage_title) {
                        substageDisplay = `${substageDisplay} - ${member.substage_title}`;
                    }
                    
                    roleDisplay = substageDisplay;
                    
                    // Track this as a substage under the corresponding stage
                    if (!groupedMembers[member.id].stageRoles[member.stage_number]) {
                        groupedMembers[member.id].stageRoles[member.stage_number] = {
                            display: `Stage ${member.stage_number}`,
                            substages: [],
                            stageNumber: parseInt(member.stage_number)
                        };
                    }
                    
                    groupedMembers[member.id].stageRoles[member.stage_number].substages.push({
                        display: roleDisplay,
                        stage_number: member.stage_number,
                        substage_number: member.substage_number,
                        substage_title: member.substage_title || ''
                    });
                    
                    groupedMembers[member.id].hasSubstages = true;
                } else if (member.substage_title) {
                    roleDisplay = `Substage - ${member.substage_title}`;
                }
            } else if (member.role === 'Project Assigned') {
                roleDisplay = 'Project';
                // Set lowest stage number to 0 for project owners (to appear first)
                groupedMembers[member.id].lowestStageNumber = 0;
            }
            
            // Add this role to the user's roles array (only if not a substage under a stage we already track)
            if (!(member.role === 'Substage Assigned' && member.stage_number && member.substage_number)) {
                groupedMembers[member.id].roles.push(roleDisplay);
            }
        });
        
        // Convert to array and sort by stage number
        const sortedMembers = Object.values(groupedMembers).sort((a, b) => {
            return a.lowestStageNumber - b.lowestStageNumber;
        });
        
        // Now render each user with all their roles
        return `
            <div class="stage_detail_team_members">
                ${sortedMembers.map(member => {
                    // Add styles for toggle button and collapsible substages
                    const toggleButtonStyles = `
                        <style>
                            .stage_role_toggle {
                                cursor: pointer;
                                margin-left: 5px;
                                color: #3b82f6;
                                transition: transform 0.2s ease;
                                flex-shrink: 0;
                            }
                            
                            .stage_role_toggle.expanded {
                                transform: rotate(180deg);
                            }
                            
                            .substages_container {
                                margin-left: 12px;
                                padding-left: 8px;
                                border-left: 2px solid #e2e8f0;
                                margin-top: 4px;
                                overflow: hidden;
                                max-height: 0;
                                transition: max-height 0.3s ease;
                                width: 100%;
                            }
                            
                            .substages_container.expanded {
                                max-height: 500px;
                            }
                            
                            .substage_role {
                                color: #4b5563;
                                font-size: 12px;
                                padding: 3px 0;
                                white-space: normal;
                                word-break: break-word;
                            }
                            
                            .substage_title {
                                font-weight: 500;
                                color: #1e293b;
                            }
                        </style>
                    `;
                    
                    // Render regular roles and the roles with substages differently
                    const regularRoles = member.roles.filter(role => !role.includes('Stage') || role === 'Project');
                    
                    // Sort stage roles by stage number
                    const sortedStageRoles = Object.values(member.stageRoles).sort((a, b) => {
                        return a.stageNumber - b.stageNumber;
                    });
                    
                    return `
                    ${member.hasSubstages ? toggleButtonStyles : ''}
                    <div class="stage_detail_team_member">
                        <div class="stage_detail_team_member_avatar">
                            ${member.profile_picture ? 
                              `<img src="${this.escapeHtml(member.profile_picture)}" alt="${this.escapeHtml(member.name)}" class="profile-image">` : 
                              this.getInitials(member.name)}
                        </div>
                        <div class="stage_detail_team_member_info">
                            <div class="stage_detail_team_member_name">${this.escapeHtml(member.name)}</div>
                            <div class="stage_detail_team_member_roles">
                                ${regularRoles.map(role => 
                                    `<div class="stage_detail_team_member_role">${this.escapeHtml(role)}</div>`
                                ).join('')}
                                
                                ${sortedStageRoles.map((stageRole, index) => `
                                    <div class="stage_detail_team_member_role stage_role_container">
                                        <div class="stage_role_header">
                                            <span>${this.escapeHtml(stageRole.display)}</span>
                                            ${stageRole.substages.length > 0 ? 
                                                `<i class="fas fa-chevron-down stage_role_toggle" data-member-id="${member.id}" data-stage-index="${index}"></i>` : 
                                                ''}
                                        </div>
                                        ${stageRole.substages.length > 0 ? `
                                            <div class="substages_container" data-member-id="${member.id}" data-stage-index="${index}">
                                                ${stageRole.substages.map(substage => `
                                                    <div class="stage_detail_team_member_role substage_role">
                                                        ${this.escapeHtml(substage.display)}
                                                    </div>
                                                `).join('')}
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    async fetchUserProfilePicture(userId) {
        if (!userId) return null;
        
        try {
            const response = await fetch('get_user_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.profile_picture) {
                    return data.profile_picture;
                }
            }
            return null;
        } catch (error) {
            console.error('Error fetching user profile picture:', error);
            return null;
        }
    }

    // New method to check for unread messages and update the notification counter
    async updateChatNotificationCounter(stageId, substageId = null) {
        try {
            // Build query parameters
            const params = new URLSearchParams({
                stage_id: stageId
            });
            
            if (substageId) {
                params.append('substage_id', substageId);
            }
            
            // Fetch unread message count
            const response = await fetch(`get_unread_messages_count.php?${params.toString()}`);
            const data = await response.json();
            
            if (data.success) {
                // Determine the counter element ID based on whether this is a stage or substage
                const counterId = substageId 
                    ? `chat-counter-substage-${substageId}` 
                    : `chat-counter-stage-${stageId}`;
                
                // Update the counter when the modal is opened
                setTimeout(() => {
                    const counterElement = document.getElementById(counterId);
                    if (counterElement && data.unread_count > 0) {
                        counterElement.textContent = data.unread_count;
                        counterElement.classList.remove('hidden');
                    } else if (counterElement) {
                        counterElement.classList.add('hidden');
                    }
                }, 300); // Small delay to ensure modal is rendered
            }
        } catch (error) {
            console.error('Error fetching unread message count:', error);
        }
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.stageDetailModal = new StageDetailModal();
}); 