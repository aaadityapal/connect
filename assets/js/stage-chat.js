/**
 * Stage Chat JavaScript
 * This file handles the functionality of the chat box that appears when a user clicks
 * on the chat button in a stage or substage
 */

class StageChat {
    constructor() {
        this.chatBoxes = {};
        // Ensure user meta tags
        this.ensureUserMetaTags();
        this.currentUserId = this.getCurrentUserId();
        this.currentUserName = this.getCurrentUserName();
        this.currentUserInitials = this.getInitials(this.currentUserName);
        this.isAdmin = this.isAdminUser();
        this.loadFontAwesome();
        this.injectStyles();
        
        console.log('StageChat initialized with currentUserId:', this.currentUserId, 'Type:', typeof this.currentUserId);
        console.log('Is admin:', this.isAdmin, 'Type:', typeof this.isAdmin);
    }

    // Load Font Awesome if not already loaded
    loadFontAwesome() {
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement('link');
            fontAwesome.rel = 'stylesheet';
            fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(fontAwesome);
            
            console.log('Font Awesome loaded by StageChat');
        }
    }

    // Inject CSS styles for the chat components
    injectStyles() {
        // Check if styles are already injected
        if (document.getElementById('stage-chat-styles')) {
            return;
        }
        
        // Create style element
        const styleEl = document.createElement('style');
        styleEl.id = 'stage-chat-styles';
        
        // Define styles
        styleEl.textContent = `
            /* Basic chat box styles */
            .stage-chat-box {
                position: fixed;
                bottom: 100px;
                right: 100px;
                width: 320px;
                height: 400px;
                background-color: #fff;
                border-radius: 12px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-direction: column;
                z-index: 100000;
                overflow: hidden;
                transition: all 0.3s ease;
                resize: both;
                min-width: 280px;
                min-height: 250px;
                max-width: 500px;
                max-height: 600px;
            }
            
            .stage-chat-box.minimized {
                height: 48px !important;
                min-height: 48px;
                resize: none;
            }
            
            /* Resize handle styles */
            .stage-chat-resize-handle {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 20px;
                height: 20px;
                cursor: nwse-resize;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16L22 22"></path><path d="M8 16L22 2"></path><path d="M16 8L22 2"></path></svg>');
                background-repeat: no-repeat;
                background-position: bottom right;
                background-size: 16px;
                opacity: 0.5;
            }
            
            .stage-chat-box.minimized .stage-chat-resize-handle {
                display: none;
            }
            
            /* Indicator styles */
            .stage-chat-indicator {
                position: absolute;
                top: 10px;
                left: 10px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background-color: #10b981;
                box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
            }
            
            /* Toast notification styles */
            .stage-chat-toast {
                position: fixed;
                bottom: 20px;
                left: 20px;
                background-color: #fff;
                border-radius: 6px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
                padding: 12px 16px;
                z-index: 100002;
                max-width: 300px;
                animation: stage-chat-toast-in 0.3s ease forwards;
            }
            
            .stage-chat-toast.error {
                border-left: 4px solid #ef4444;
            }
            
            .stage-chat-toast.fade-out {
                animation: stage-chat-toast-out 0.3s ease forwards;
            }
            
            .stage-chat-toast-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .stage-chat-toast-content i {
                color: #ef4444;
                font-size: 16px;
            }
            
            .stage-chat-toast-content span {
                font-size: 14px;
                color: #333;
            }
            
            /* Error message styles */
            .stage-chat-error {
                background-color: #fee2e2;
                border-left: 4px solid #ef4444;
                padding: 10px 12px;
                margin: 10px 0;
                border-radius: 6px;
                animation: stage-chat-error-in 0.3s ease forwards;
                max-width: 90%;
                align-self: center;
            }
            
            .stage-chat-error.fade-out {
                animation: stage-chat-error-out 0.3s ease forwards;
            }
            
            .stage-chat-error-content {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                color: #b91c1c;
            }
            
            .stage-chat-error-content i {
                font-size: 14px;
            }
            
            /* Animation keyframes */
            @keyframes stage-chat-toast-in {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes stage-chat-toast-out {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(20px); }
            }
            
            @keyframes stage-chat-error-in {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes stage-chat-error-out {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(10px); }
            }
            
            /* Header styles */
            .stage-chat-header {
                background-color: #3b82f6;
                color: white;
                padding: 12px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: move;
                border-top-left-radius: 12px;
                border-top-right-radius: 12px;
            }
            
            /* Substage chat header - different color */
            .stage-chat-header.substage-chat {
                background-color: #10b981;
                position: relative;
            }
            
            .stage-chat-header.substage-chat::before {
                content: '⚬';
                position: absolute;
                right: 8px;
                top: 2px;
                font-size: 10px;
                opacity: 0.6;
            }
            
            .stage-chat-header.dragging {
                cursor: grabbing;
                opacity: 0.9;
            }
            
            .stage-chat-title {
                font-weight: 600;
                font-size: 14px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                flex: 1;
            }
            
            .stage-chat-actions {
                display: flex;
                gap: 8px;
            }
            
            .stage-chat-action-btn {
                background: none;
                border: none;
                color: white;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                cursor: pointer;
                border-radius: 50%;
                transition: all 0.2s ease;
            }
            
            .stage-chat-action-btn:hover {
                background-color: rgba(255, 255, 255, 0.2);
            }
            
            /* Messages container styles */
            .stage-chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background-color: #f8fafc;
            }
            
            .stage-chat-message {
                display: flex;
                max-width: 85%;
                position: relative;
            }
            
            .stage-chat-message.outgoing {
                align-self: flex-end;
                flex-direction: row-reverse;
            }
            
            /* Avatar column styling */
            .stage-chat-avatar-column {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .stage-chat-message.outgoing .stage-chat-avatar-column {
                margin-left: 10px;
                margin-right: 0;
            }
            
            /* Content column styling */
            .stage-chat-content-column {
                flex: 1;
            }
            
            .stage-chat-message.outgoing .stage-chat-content-column {
                text-align: right;
            }
            
            .stage-chat-message-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background-color: #3b82f6;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 600;
                flex-shrink: 0;
                overflow: hidden;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-avatar {
                background-color: #10b981;
            }
            
            .stage-chat-avatar-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .stage-chat-message-content {
                background-color: white;
                padding: 10px 14px;
                padding-top: 25px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                font-size: 13px;
                line-height: 1.4;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                position: relative !important;
            }
            
            .stage-chat-sender {
                font-size: 11px;
                margin-top: 6px;
                font-weight: 500;
                color: #64748b;
            }
            
            .stage-chat-message.outgoing .stage-chat-sender {
                color: #e0e7ff;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-content {
                background-color: #3b82f6;
                color: white;
                border-color: #3b82f6;
            }
            
            .stage-chat-sending {
                margin-top: 5px;
                font-size: 11px;
                opacity: 0.8;
            }
            
            .stage-chat-message-meta {
                font-size: 11px;
                color: #64748b;
                margin-top: 4px;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-meta {
                color: #e0e7ff;
            }
            
            /* Message action buttons */
            .stage-chat-message-actions {
                display: flex;
                gap: 8px;
                position: absolute;
                top: 0;
                right: 0;
                opacity: 1;
                z-index: 999999;
                background-color: #f3f4f6;
                border-radius: 0 12px 0 8px;
                padding: 5px 8px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
                border: 1px solid #e5e7eb;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-actions {
                background-color: #3b82f6;
                border-color: #2563eb;
            }
            
            .stage-chat-message-action {
                background: none;
                border: none;
                width: 28px;
                height: 28px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 16px;
                transition: all 0.2s ease;
                color: #3b82f6;
                background-color: white;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-action {
                color: white;
                background-color: #2563eb;
            }
            
            .stage-chat-message-action:hover {
                transform: translateY(-2px);
                background-color: #f8fafc;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-action:hover {
                background-color: #1d4ed8;
            }
            
            .stage-chat-message-action.delete-message {
                color: #ef4444;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-action.delete-message {
                color: #fff;
            }
            
            .stage-chat-message-action.delete-message:hover {
                color: #dc2626;
            }
            
            /* Input styles */
            .stage-chat-input-container {
                padding: 12px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            
            .stage-chat-input {
                flex: 1;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 8px 12px;
                font-size: 13px;
                outline: none;
                transition: all 0.2s ease;
            }
            
            .stage-chat-input:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
            }
            
            .stage-chat-send-btn {
                background-color: #3b82f6;
                color: white;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            
            .stage-chat-send-btn:hover {
                background-color: #2563eb;
                transform: translateY(-2px);
                box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
            }
            
            .stage-chat-send-btn:disabled {
                background-color: #cbd5e1;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            /* Empty state styles */
            .stage-chat-empty {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                color: #94a3b8;
                text-align: center;
                padding: 20px;
            }
            
            .stage-chat-empty i {
                font-size: 32px;
                margin-bottom: 12px;
                opacity: 0.5;
            }
            
            .stage-chat-empty-text {
                font-size: 14px;
                max-width: 200px;
                line-height: 1.4;
            }
            
            /* Checkbox styles */
            .stage-chat-message-checkbox {
                margin-top: 5px;
                width: 16px;
                height: 16px;
                cursor: pointer;
            }
        `;
        
        // Append style element to document head
        document.head.appendChild(styleEl);
    }

    // Get current user ID from the document
    getCurrentUserId() {
        // Try to get user ID from meta tag
        const userIdElement = document.querySelector('meta[name="user-id"]');
        let userId = userIdElement ? userIdElement.getAttribute('content') : null;
        
        // If not found in meta tag, try to get from a global variable
        if (!userId && typeof window.currentUserId !== 'undefined') {
            userId = window.currentUserId;
        }
        
        // If still not found, try to get from data attribute on body
        if (!userId) {
            const bodyUserIdAttr = document.body.getAttribute('data-user-id');
            if (bodyUserIdAttr) {
                userId = bodyUserIdAttr;
            }
        }
        
        console.log('Raw user ID retrieved:', userId, 'Type:', typeof userId);
        return userId;
    }

    // Get current user name from the document
    getCurrentUserName() {
        const userNameElement = document.querySelector('meta[name="user-name"]');
        return userNameElement ? userNameElement.getAttribute('content') : 'User';
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

    // Get initials from name
    getInitials(name) {
        if (!name || typeof name !== 'string') return 'U';
        return name.split(' ')
            .filter(part => part.trim().length > 0)
            .map(word => word.charAt(0))
            .join('')
            .substring(0, 2)
            .toUpperCase() || 'U';
    }

    // Format date time
    formatDateTime(dateTimeString) {
        if (!dateTimeString) return '';
        
        const now = new Date();
        const dateTime = new Date(dateTimeString);
        
        // Check if it's today
        if (dateTime.toDateString() === now.toDateString()) {
            // Today - show only time
            return 'Today, ' + dateTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
        } 
        
        // Check if it's yesterday
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (dateTime.toDateString() === yesterday.toDateString()) {
            // Yesterday - show "Yesterday" and time
            return 'Yesterday, ' + dateTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
        }
        
        // Check if it's within the last 7 days
        const lastWeek = new Date(now);
        lastWeek.setDate(now.getDate() - 6);
        if (dateTime >= lastWeek) {
            // Within last week - show day name and time
            return dateTime.toLocaleDateString('en-US', { 
                weekday: 'long'
            }) + ', ' + dateTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
        }
        
        // More than a week ago - show full date and time
        return dateTime.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: now.getFullYear() !== dateTime.getFullYear() ? 'numeric' : undefined
        }) + ', ' + dateTime.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit'
        });
    }

    // Open chat box
    openChat(projectId, stageId, stageName, sourceElement = null, substageId = null, substageName = null) {
        // Generate a unique ID for this chat - different for stage vs substage
        const chatId = substageId ? `substage_${substageId}` : `stage_${stageId}`;
        
        // Determine the chat title
        let chatTitle = stageName || 'Stage Chat';
        if (substageId && substageName) {
            chatTitle = `${substageName} (Substage)`;
        }
        
        // If chat already exists, just show it
        if (this.chatBoxes[chatId]) {
            const existingChat = this.chatBoxes[chatId];
            existingChat.element.style.display = 'flex';
            existingChat.element.classList.remove('minimized');
            
            // Ensure it's on top by setting a higher z-index
            existingChat.element.style.zIndex = 100000;
            
            // Mark messages as read
            this.markMessagesAsRead(projectId, stageId, substageId);
            
            // Hide notification counter in the stage/substage detail modal
            this.resetNotificationCounter(stageId, substageId);
            
            return;
        }
        
        // Create chat box
        const chatBox = document.createElement('div');
        chatBox.className = 'stage-chat-box';
        chatBox.id = `chat_box_${chatId}`;
        
        // Position the chat box - use the source element position if available
        let initialLeft = null;
        let initialTop = null;
        
        if (sourceElement) {
            // Get the position of the button that triggered the chat
            const rect = sourceElement.getBoundingClientRect();
            initialLeft = rect.left - 160; // Center the chat box on the button
            initialTop = window.innerHeight - rect.top - 450; // Position above the button
            
            // Make sure it's within the viewport
            if (initialLeft < 20) initialLeft = 20;
            if (initialLeft > window.innerWidth - 340) initialLeft = window.innerWidth - 340;
            if (initialTop < 20) initialTop = 20;
            
            chatBox.style.left = `${initialLeft}px`;
            chatBox.style.top = `${initialTop}px`;
            chatBox.style.right = 'auto';
            chatBox.style.bottom = 'auto';
        } else {
            // Default positioning if no source element
            const existingChatCount = Object.keys(this.chatBoxes).length;
            const offsetX = 30 * existingChatCount;
            const offsetY = 30 * existingChatCount;
            chatBox.style.right = `${100 + offsetX}px`;
            chatBox.style.bottom = `${100 + offsetY}px`;
        }
        
        // Initial chat HTML structure
        chatBox.innerHTML = `
            <div class="stage-chat-header ${substageId ? 'substage-chat' : ''}">
                <div class="stage-chat-indicator"></div>
                <div class="stage-chat-title">${chatTitle}</div>
                <div class="stage-chat-actions">
                    <button class="stage-chat-action-btn edit-mode-btn" title="Edit Messages">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="stage-chat-action-btn delete-mode-btn" title="Delete Messages">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="stage-chat-action-btn minimize-btn" title="Minimize">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="stage-chat-action-btn close-btn" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="stage-chat-edit-toolbar" style="display: none; padding: 8px; background-color: #f3f4f6; border-bottom: 1px solid #e5e7eb;">
                <button class="stage-chat-edit-confirm-btn" style="background-color: #3b82f6; color: white; border: none; padding: 5px 10px; border-radius: 4px; margin-right: 10px; cursor: pointer;">
                    Confirm
                </button>
                <button class="stage-chat-edit-cancel-btn" style="background-color: #64748b; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                    Cancel
                </button>
            </div>
            <div class="stage-chat-delete-toolbar" style="display: none; padding: 8px; background-color: #fee2e2; border-bottom: 1px solid #e5e7eb;">
                <button class="stage-chat-delete-confirm-btn" style="background-color: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 4px; margin-right: 10px; cursor: pointer;">
                    Delete Selected
                </button>
                <button class="stage-chat-delete-cancel-btn" style="background-color: #64748b; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                    Cancel
                </button>
            </div>
            <div class="stage-chat-messages">
                <div class="stage-chat-empty">
                    <i class="far fa-comments"></i>
                    <div class="stage-chat-empty-text">No messages yet. Start the conversation!</div>
                </div>
            </div>
            <div class="stage-chat-input-container">
                <input type="text" class="stage-chat-input" placeholder="Type a message...">
                <button class="stage-chat-send-btn" disabled>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="stage-chat-resize-handle"></div>
        `;
        
        document.body.appendChild(chatBox);
        
        // Make chat draggable
        this.makeChatDraggable(chatBox);
        
        // Set up event listeners
        this.setupChatEventListeners(chatBox, projectId, stageId, substageId);
        
        // Store reference to this chat box
        this.chatBoxes[chatId] = {
            element: chatBox,
            projectId: projectId,
            stageId: stageId,
            substageId: substageId,
            messages: []
        };
        
        // Load existing messages
        this.loadChatMessages(projectId, stageId, substageId);
        
        // Mark messages as read
        this.markMessagesAsRead(projectId, stageId, substageId);
        
        // Hide notification counter in the stage/substage detail modal
        this.resetNotificationCounter(stageId, substageId);
    }

    // Make the chat box draggable
    makeChatDraggable(chatBox) {
        const header = chatBox.querySelector('.stage-chat-header');
        
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        
        header.onmousedown = dragMouseDown;
        
        function dragMouseDown(e) {
            e.preventDefault();
            // Get the mouse cursor position at startup
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            // Call a function whenever the cursor moves
            document.onmousemove = elementDrag;
            
            // Add a class to indicate dragging state
            header.classList.add('dragging');
        }
        
        function elementDrag(e) {
            e.preventDefault();
            // Calculate the new cursor position
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            // Set the element's new position
            chatBox.style.top = (chatBox.offsetTop - pos2) + "px";
            chatBox.style.left = (chatBox.offsetLeft - pos1) + "px";
            // Remove right/bottom positioning to allow proper dragging
            chatBox.style.right = 'auto';
            chatBox.style.bottom = 'auto';
        }
        
        function closeDragElement() {
            // Stop moving when mouse button is released
            document.onmouseup = null;
            document.onmousemove = null;
            
            // Remove dragging class
            header.classList.remove('dragging');
        }
    }

    // Set up event listeners for a chat box
    setupChatEventListeners(chatBox, projectId, stageId, substageId = null) {
        const header = chatBox.querySelector('.stage-chat-header');
        const minimizeBtn = chatBox.querySelector('.minimize-btn');
        const closeBtn = chatBox.querySelector('.close-btn');
        const inputField = chatBox.querySelector('.stage-chat-input');
        const sendBtn = chatBox.querySelector('.stage-chat-send-btn');
        
        // Edit mode button
        const editModeBtn = chatBox.querySelector('.edit-mode-btn');
        const editToolbar = chatBox.querySelector('.stage-chat-edit-toolbar');
        const editConfirmBtn = chatBox.querySelector('.stage-chat-edit-confirm-btn');
        const editCancelBtn = chatBox.querySelector('.stage-chat-edit-cancel-btn');
        
        // Delete mode button
        const deleteModeBtn = chatBox.querySelector('.delete-mode-btn');
        const deleteToolbar = chatBox.querySelector('.stage-chat-delete-toolbar');
        const deleteConfirmBtn = chatBox.querySelector('.stage-chat-delete-confirm-btn');
        const deleteCancelBtn = chatBox.querySelector('.stage-chat-delete-cancel-btn');
        
        // Debug button presence
        console.log('Setup listeners - Edit button found:', !!editModeBtn);
        console.log('Setup listeners - Delete button found:', !!deleteModeBtn);
        
        // Toggle minimize on header click
        header.addEventListener('click', (e) => {
            // Only minimize if clicking the minimize button
            if (e.target === minimizeBtn || 
                minimizeBtn.contains(e.target)) {
                chatBox.classList.toggle('minimized');
            }
        });
        
        // Close button
        closeBtn.addEventListener('click', () => {
            chatBox.style.display = 'none';
        });
        
        // Enable/disable send button based on input
        inputField.addEventListener('input', () => {
            sendBtn.disabled = inputField.value.trim() === '';
        });
        
        // Send message on button click
        sendBtn.addEventListener('click', () => {
            this.sendMessage(projectId, stageId, inputField.value.trim(), substageId);
            inputField.value = '';
            sendBtn.disabled = true;
        });
        
        // Send message on Enter key
        inputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && inputField.value.trim() !== '') {
                this.sendMessage(projectId, stageId, inputField.value.trim(), substageId);
                inputField.value = '';
                sendBtn.disabled = true;
            }
        });
        
        // Bring to front when clicking anywhere on the chat
        chatBox.addEventListener('mousedown', () => {
            this.bringChatToFront(chatBox);
        });
        
        // Edit mode functionality
        if (editModeBtn) {
            editModeBtn.addEventListener('click', (e) => {
                console.log('Edit mode button clicked');
                e.stopPropagation(); // Prevent header click from interfering
                this.toggleEditMode(chatBox, true);
            });
        } else {
            console.error('Edit mode button not found!');
        }
        
        if (editConfirmBtn) {
            editConfirmBtn.addEventListener('click', (e) => {
                console.log('Edit confirm button clicked');
                e.stopPropagation();
                this.handleEditConfirm(chatBox, projectId, stageId, substageId);
            });
        }
        
        if (editCancelBtn) {
            editCancelBtn.addEventListener('click', (e) => {
                console.log('Edit cancel button clicked');
                e.stopPropagation();
                this.toggleEditMode(chatBox, false);
            });
        }
        
        // Delete mode functionality
        if (deleteModeBtn) {
            deleteModeBtn.addEventListener('click', (e) => {
                console.log('Delete mode button clicked');
                e.stopPropagation(); // Prevent header click from interfering
                this.toggleDeleteMode(chatBox, true);
            });
        } else {
            console.error('Delete mode button not found!');
        }
        
        if (deleteConfirmBtn) {
            deleteConfirmBtn.addEventListener('click', (e) => {
                console.log('Delete confirm button clicked');
                e.stopPropagation();
                this.handleDeleteConfirm(chatBox, projectId, stageId, substageId);
            });
        }
        
        if (deleteCancelBtn) {
            deleteCancelBtn.addEventListener('click', (e) => {
                console.log('Delete cancel button clicked');
                e.stopPropagation();
                this.toggleDeleteMode(chatBox, false);
            });
        }
    }

    // Add checkboxes to messages for edit/delete mode
    addCheckboxesToMessages() {
        const messages = document.querySelectorAll('.stage-chat-message');
        
        console.log('Current user ID (in checkboxes):', this.currentUserId, 'Type:', typeof this.currentUserId);
        console.log('Is admin (in checkboxes):', this.isAdmin, 'Type:', typeof this.isAdmin);
        
        messages.forEach(message => {
            // Get message user ID from dataset
            const messageUserId = message.dataset.userId;
            
            // Convert both IDs to integers for comparison
            const currentUserIdInt = parseInt(this.currentUserId) || 0;
            const messageUserIdInt = parseInt(messageUserId) || 0;
            
            console.log('Message user ID:', messageUserId, '(Parsed:', messageUserIdInt, ')', 
                        'Current user ID:', this.currentUserId, '(Parsed:', currentUserIdInt, ')', 
                        'Comparison result:', currentUserIdInt === messageUserIdInt);
            
            // Check if message can be edited/deleted by current user (own messages or admin)
            if (currentUserIdInt === messageUserIdInt || this.isAdmin) {
                const avatarColumn = message.querySelector('.stage-chat-avatar-column');
                
                // Check if checkbox already exists
                if (!message.querySelector('.message-checkbox')) {
                    // Create checkbox
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'message-checkbox';
                    checkbox.dataset.messageId = message.dataset.messageId;
                    
                    // Add checkbox to avatar column
                    if (avatarColumn) {
                        avatarColumn.appendChild(checkbox);
                    }
                    
                    console.log('Added checkbox to message:', message.dataset.messageId, 'User:', messageUserId);
                }
            } else {
                console.log('Skipping checkbox for message:', message.dataset.messageId, 'User:', messageUserId, '(not owner or admin)');
            }
        });
    }
    
    // Remove checkboxes from messages
    removeCheckboxesFromMessages(chatBox) {
        console.log('Removing all checkboxes');
        
        const messagesContainer = chatBox.querySelector('.stage-chat-messages');
        const checkboxes = messagesContainer.querySelectorAll('.message-checkbox');
        
        console.log(`Found ${checkboxes.length} checkboxes to remove`);
        
        checkboxes.forEach(checkbox => {
            const messageId = checkbox.dataset.messageId;
            console.log(`Removing checkbox from message ${messageId}`);
            checkbox.remove();
        });
        
        // Reset background colors
        const messages = messagesContainer.querySelectorAll('.stage-chat-message');
        messages.forEach(message => {
            message.style.backgroundColor = '';
        });
    }
    
    // Toggle edit mode
    toggleEditMode(chatBox, enable) {
        console.log(`Toggle edit mode: ${enable}`);
        
        const editToolbar = chatBox.querySelector('.stage-chat-edit-toolbar');
        const deleteToolbar = chatBox.querySelector('.stage-chat-delete-toolbar');
        const messagesContainer = chatBox.querySelector('.stage-chat-messages');
        const inputContainer = chatBox.querySelector('.stage-chat-input-container');
        
        // Log what we found for debugging
        console.log('Edit toolbar found:', !!editToolbar);
        console.log('Delete toolbar found:', !!deleteToolbar);
        console.log('Messages container found:', !!messagesContainer);
        console.log('Input container found:', !!inputContainer);
        
        // Turn off delete mode if it's active
        if (deleteToolbar) {
            deleteToolbar.style.display = 'none';
        }
        
        if (enable) {
            // Check if we have messages
            const messageCount = messagesContainer ? messagesContainer.querySelectorAll('.stage-chat-message').length : 0;
            console.log(`Found ${messageCount} messages before adding checkboxes`);
            
            // Show edit toolbar
            if (editToolbar) {
                editToolbar.style.display = 'block';
                console.log('Showed edit toolbar');
            } else {
                console.error('Edit toolbar not found!');
            }
            
            // Hide input container
            if (inputContainer) {
                inputContainer.style.display = 'none';
                console.log('Hid input container');
            }
            
            // First make sure we can access the messages
            if (messagesContainer) {
                // Force set some styles on each message to ensure checkboxes can be added
                const messages = messagesContainer.querySelectorAll('.stage-chat-message');
                messages.forEach(message => {
                    message.style.position = 'relative';
                    
                    // Make sure each message has an avatar column
                    let avatarColumn = message.querySelector('.stage-chat-avatar-column');
                    if (!avatarColumn) {
                        console.log('Creating missing avatar column for a message');
                        
                        // Try to find an avatar element directly
                        const avatar = message.querySelector('.stage-chat-message-avatar');
                        
                        if (avatar && avatar.parentNode) {
                            // Create wrapper column
                            avatarColumn = document.createElement('div');
                            avatarColumn.className = 'stage-chat-avatar-column';
                            avatarColumn.style.cssText = 'display: flex; flex-direction: column; align-items: center; margin-right: 10px;';
                            
                            // Move avatar into column
                            const parent = avatar.parentNode;
                            parent.insertBefore(avatarColumn, avatar);
                            avatarColumn.appendChild(avatar);
                        }
                    }
                });
                
                // Add checkboxes to messages
                this.addCheckboxesToMessages();
            } else {
                console.error('Messages container not found!');
            }
        } else {
            // Hide edit toolbar
            if (editToolbar) {
                editToolbar.style.display = 'none';
            }
            
            // Show input container
            if (inputContainer) {
                inputContainer.style.display = 'flex';
            }
            
            // Remove checkboxes from messages
            this.removeCheckboxesFromMessages(chatBox);
        }
    }
    
    // Toggle delete mode
    toggleDeleteMode(chatBox, enable) {
        console.log(`Toggle delete mode: ${enable}`);
        
        const editToolbar = chatBox.querySelector('.stage-chat-edit-toolbar');
        const deleteToolbar = chatBox.querySelector('.stage-chat-delete-toolbar');
        const messagesContainer = chatBox.querySelector('.stage-chat-messages');
        const inputContainer = chatBox.querySelector('.stage-chat-input-container');
        
        // Turn off edit mode if it's active
        editToolbar.style.display = 'none';
        
        if (enable) {
            // Show delete toolbar
            deleteToolbar.style.display = 'block';
            // Hide input container
            inputContainer.style.display = 'none';
            // Add checkboxes to messages
            this.addCheckboxesToMessages();
        } else {
            // Hide delete toolbar
            deleteToolbar.style.display = 'none';
            // Show input container
            inputContainer.style.display = 'flex';
            // Remove checkboxes from messages
            this.removeCheckboxesFromMessages(chatBox);
        }
    }
    
    // Handle edit confirm
    handleEditConfirm(chatBox, projectId, stageId, substageId) {
        const messagesContainer = chatBox.querySelector('.stage-chat-messages');
        const checkedBoxes = messagesContainer.querySelectorAll('.message-checkbox:checked');
        
        if (checkedBoxes.length > 0) {
            // Get message IDs and elements
            const selectedMessages = [];
            
            checkedBoxes.forEach(checkbox => {
                const messageId = checkbox.dataset.messageId;
                const messageElement = messagesContainer.querySelector(`.stage-chat-message[data-message-id="${messageId}"]`);
                
                if (messageElement) {
                    const messageContent = messageElement.querySelector('.stage-chat-content-column .stage-chat-message-content');
                    // Get the message text (without the sender name which is in a child div)
                    const messageText = messageContent.childNodes[0].nodeValue.trim();
                    
                    selectedMessages.push({
                        id: messageId,
                        element: messageElement,
                        contentElement: messageContent,
                        text: messageText
                    });
                }
            });
            
            console.log('Edit messages:', selectedMessages);
            
            // Currently only support editing one message at a time
            if (selectedMessages.length > 1) {
                alert('Currently only one message can be edited at a time. Please select just one message.');
                return;
            }
            
            const messageToEdit = selectedMessages[0];
            
            // Create an editable input
            const editInput = document.createElement('textarea');
            editInput.className = 'stage-chat-edit-input';
            editInput.value = messageToEdit.text;
            editInput.style.width = '100%';
            editInput.style.minHeight = '60px';
            editInput.style.marginBottom = '10px';
            
            // Create save and cancel buttons
            const editActionsDiv = document.createElement('div');
            editActionsDiv.className = 'stage-chat-edit-actions';
            editActionsDiv.style.display = 'flex';
            editActionsDiv.style.justifyContent = 'flex-end';
            editActionsDiv.style.gap = '10px';
            
            const saveButton = document.createElement('button');
            saveButton.textContent = 'Save';
            saveButton.className = 'stage-chat-edit-save-btn';
            saveButton.style.padding = '5px 10px';
            saveButton.style.backgroundColor = '#4CAF50';
            saveButton.style.color = 'white';
            saveButton.style.border = 'none';
            saveButton.style.borderRadius = '3px';
            saveButton.style.cursor = 'pointer';
            
            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel';
            cancelButton.className = 'stage-chat-edit-cancel-btn';
            cancelButton.style.padding = '5px 10px';
            cancelButton.style.backgroundColor = '#f44336';
            cancelButton.style.color = 'white';
            cancelButton.style.border = 'none';
            cancelButton.style.borderRadius = '3px';
            cancelButton.style.cursor = 'pointer';
            
            editActionsDiv.appendChild(cancelButton);
            editActionsDiv.appendChild(saveButton);
            
            // Store original content to restore if needed
            const originalContent = messageToEdit.contentElement.innerHTML;
            
            // Replace message content with editable input and buttons
            messageToEdit.contentElement.innerHTML = '';
            messageToEdit.contentElement.appendChild(editInput);
            messageToEdit.contentElement.appendChild(editActionsDiv);
            
            // Focus the input
            editInput.focus();
            
            // Handle save click
            saveButton.addEventListener('click', async () => {
                const newContent = editInput.value.trim();
                
                if (newContent === '') {
                    alert('Message cannot be empty');
                    return;
                }
                
                try {
                    // Show loading indicator
                    messageToEdit.contentElement.innerHTML = '<div style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Updating...</div>';
                    
                    // Update the message on the server
                    const result = await this.editMessage(messageToEdit.id, newContent);
                    
                    if (result.success) {
                        // Update the message in the UI
                        messageToEdit.contentElement.innerHTML = `
                            ${newContent}
                            <div class="stage-chat-sender">${messageToEdit.element.querySelector('.stage-chat-sender').textContent}</div>
                        `;
                        console.log('Message updated successfully');
                    } else {
                        // Restore original content and show error
                        messageToEdit.contentElement.innerHTML = originalContent;
                        alert('Failed to update message: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error updating message:', error);
                    // Restore original content
                    messageToEdit.contentElement.innerHTML = originalContent;
                    alert('Error updating message: ' + error.message);
                } finally {
                    // Exit edit mode
                    this.toggleEditMode(chatBox, false);
                }
            });
            
            // Handle cancel click
            cancelButton.addEventListener('click', () => {
                // Restore original content
                messageToEdit.contentElement.innerHTML = originalContent;
                // Exit edit mode
                this.toggleEditMode(chatBox, false);
            });
        } else {
            alert('Please select at least one message to edit.');
        }
    }
    
    // Handle delete confirm
    handleDeleteConfirm(chatBox, projectId, stageId, substageId) {
        const messagesContainer = chatBox.querySelector('.stage-chat-messages');
        const checkedBoxes = messagesContainer.querySelectorAll('.message-checkbox:checked');
        
        if (checkedBoxes.length > 0) {
            // Get message IDs
            const messageIds = Array.from(checkedBoxes).map(checkbox => checkbox.dataset.messageId);
            
            console.log('Delete messages with IDs:', messageIds);
            
            if (confirm(`Are you sure you want to delete ${messageIds.length} message(s)?`)) {
                const deletePromises = [];
                
                // Create a loading overlay
                const loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'stage-chat-loading-overlay';
                loadingOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.7); display: flex; justify-content: center; align-items: center; z-index: 1000;';
                loadingOverlay.innerHTML = '<div style="text-align: center;"><i class="fas fa-spinner fa-spin"></i><div>Deleting messages...</div></div>';
                
                // Add overlay to chat box
                chatBox.style.position = 'relative';
                chatBox.appendChild(loadingOverlay);
                
                // Delete each message
                messageIds.forEach(messageId => {
                    const message = messagesContainer.querySelector(`.stage-chat-message[data-message-id="${messageId}"]`);
                    
                    if (message) {
                        // Add to delete promises
                        deletePromises.push(
                            this.deleteMessage(messageId)
                                .then(result => {
                                    if (result.success) {
                                        // Remove message element when successful
                                        message.remove();
                                        return { messageId, success: true };
                                    } else {
                                        return { messageId, success: false, error: result.message || 'Unknown error' };
                                    }
                                })
                                .catch(error => {
                                    return { messageId, success: false, error: error.message };
                                })
                        );
                    }
                });
                
                // Wait for all deletions to complete
                Promise.all(deletePromises)
                    .then(results => {
                        // Remove loading overlay
                        chatBox.removeChild(loadingOverlay);
                        
                        // Check for errors
                        const failures = results.filter(result => !result.success);
                        if (failures.length > 0) {
                            const errorMessages = failures.map(failure => `Message ${failure.messageId}: ${failure.error}`).join('\n');
                            alert(`Some messages could not be deleted:\n${errorMessages}`);
                        } else {
                            console.log('All messages deleted successfully');
                        }
                        
                        // Check if all messages were deleted and update UI appropriately
                        if (messagesContainer.querySelectorAll('.stage-chat-message').length === 0) {
                            // Show empty state message instead of closing the chat box
                            messagesContainer.innerHTML = `
                                <div class="stage-chat-empty">
                                    <i class="far fa-comments"></i>
                                    <div class="stage-chat-empty-text">No messages yet. Start the conversation!</div>
                                </div>
                            `;
                        }
                        
                        // Exit delete mode but keep chat box open
                        this.toggleDeleteMode(chatBox, false);
                        
                        // Make sure the chat box remains visible
                        chatBox.style.display = 'flex';
                    });
            }
        } else {
            alert('Please select at least one message to delete.');
        }
    }

    // Open substage chat
    openSubstageChat(projectId, stageId, substageId, substageName, sourceElement = null) {
        const stageName = `Stage ${stageId}`; // Default stage name
        this.openChat(projectId, stageId, stageName, sourceElement, substageId, substageName);
    }

    // Load chat messages from server
    async loadChatMessages(projectId, stageId, substageId = null) {
        // Generate chat ID - different for stage vs substage
        const chatId = substageId ? `substage_${substageId}` : `stage_${stageId}`;
        const chatBox = this.chatBoxes[chatId]?.element;
        
        if (!chatBox) return;
        
        try {
            const messagesContainer = chatBox.querySelector('.stage-chat-messages');
            
            // Show loading state
            messagesContainer.innerHTML = `
                <div class="stage-chat-empty">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div class="stage-chat-empty-text">Loading messages...</div>
                </div>
            `;
            
            // Build the URL with appropriate parameters
            let url = `get_stage_chat_messages.php?project_id=${projectId}&stage_id=${stageId}`;
            if (substageId) {
                url += `&substage_id=${substageId}`;
            }
            
            // Fetch messages from server
            const response = await fetch(url);
            
            // Get text response first to handle any potential parsing issues
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (error) {
                console.error('Error parsing JSON response:', error);
                console.error('Raw response:', responseText);
                
                messagesContainer.innerHTML = `
                    <div class="stage-chat-empty">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="stage-chat-empty-text">Invalid response from server. Please try again later.</div>
                    </div>
                `;
                return;
            }
            
            if (data.success && Array.isArray(data.messages)) {
                this.chatBoxes[chatId].messages = data.messages;
                
                // Render messages
                if (data.messages.length > 0) {
                    messagesContainer.innerHTML = '';
                    data.messages.forEach(message => {
                        this.renderMessage(messagesContainer, message);
                    });
                    
                    // Scroll to bottom
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                } else {
                    // Show empty state
                    messagesContainer.innerHTML = `
                        <div class="stage-chat-empty">
                            <i class="far fa-comments"></i>
                            <div class="stage-chat-empty-text">No messages yet. Start the conversation!</div>
                        </div>
                    `;
                }
            } else {
                // Show error
                messagesContainer.innerHTML = `
                    <div class="stage-chat-empty">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="stage-chat-empty-text">Error: ${data.message || 'Unknown error'}</div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading chat messages:', error);
            
            const messagesContainer = chatBox.querySelector('.stage-chat-messages');
            messagesContainer.innerHTML = `
                <div class="stage-chat-empty">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="stage-chat-empty-text">Error: ${error.message || 'Unknown error'}</div>
                </div>
            `;
        }
    }

    // Render a single chat message
    renderMessage(messagesContainer, message) {
        // Check if the message is from the current user using numeric comparison
        const messageUserId = parseInt(message.user_id) || 0;
        const currentUserId = parseInt(this.currentUserId) || 0;
        const isCurrentUser = messageUserId === currentUserId;
        
        console.log(`Rendering message: ID=${message.id}, user_id=${message.user_id} (${typeof message.user_id}), 
            parsed message ID: ${messageUserId}, 
            current user=${this.currentUserId} (${typeof this.currentUserId}), 
            parsed current ID: ${currentUserId},
            isCurrentUser=${isCurrentUser}`);
        
        // Get user initials from the message data
        const userInitials = this.getInitials(message.user_name || 'Unknown');
        
        // Determine profile picture or initials
        let avatarContent = '';
        if (message.profile_picture) {
            avatarContent = `<img src="${message.profile_picture}" alt="${userInitials}" class="stage-chat-avatar-img">`;
        } else {
            avatarContent = userInitials;
        }
        
        // Create message element
        const messageElement = document.createElement('div');
        messageElement.className = `stage-chat-message ${isCurrentUser ? 'outgoing' : ''}`;
        messageElement.dataset.messageId = message.id || '';
        messageElement.dataset.userId = String(message.user_id) || '';
        messageElement.style.position = 'relative'; // Ensure position is set for checkbox positioning
        
        // Set the message HTML with avatar-column structure for checkbox placement
        messageElement.innerHTML = `
            <div class="stage-chat-avatar-column" style="display: flex; flex-direction: column; align-items: center; margin-right: 10px;">
                <div class="stage-chat-message-avatar">${avatarContent}</div>
                <!-- Checkbox will be added here when in edit/delete mode -->
            </div>
            <div class="stage-chat-content-column" style="flex: 1;">
                <div class="stage-chat-message-content">
                    ${message.message || ''}
                    <div class="stage-chat-sender">${message.user_name || 'Unknown'}</div>
                </div>
                <div class="stage-chat-message-meta">
                    ${this.formatDateTime(message.timestamp)}
                    ${message.edited ? '<span class="stage-chat-edited-indicator" style="margin-left: 5px; font-style: italic; opacity: 0.7;">(edited)</span>' : ''}
                </div>
            </div>
        `;
        
        // Append the message to the container
        messagesContainer.appendChild(messageElement);
        
        // For debugging
        console.log('Rendered message:', message.id, 'User ID:', message.user_id, 'Dataset userId:', messageElement.dataset.userId);
    }

    // Send a message to the server
    async sendMessage(projectId, stageId, content, substageId = null) {
        // Generate chat ID - different for stage vs substage
        const chatId = substageId ? `substage_${substageId}` : `stage_${stageId}`;
        const chatBox = this.chatBoxes[chatId]?.element;
        
        if (!chatBox || !content.trim()) return;
        
        try {
            // Show loading state but keep existing messages visible
            const messagesContainer = chatBox.querySelector('.stage-chat-messages');
            
            // Create loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'stage-chat-message outgoing';
            loadingIndicator.innerHTML = `
                <div class="stage-chat-avatar-column" style="display: flex; flex-direction: column; align-items: center; margin-right: 10px;">
                    <div class="stage-chat-message-avatar">${this.currentUserInitials}</div>
                </div>
                <div class="stage-chat-content-column" style="flex: 1;">
                    <div class="stage-chat-message-content">
                        ${content}
                        <div class="stage-chat-sending">
                            <small><i class="fas fa-spinner fa-spin"></i> Sending...</small>
                        </div>
                        <div class="stage-chat-sender">${this.currentUserName || 'You'}</div>
                    </div>
                    <div class="stage-chat-message-meta">${this.formatDateTime(new Date())}</div>
                </div>
            `;
            messagesContainer.appendChild(loadingIndicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Prepare the data to send
            const messageData = {
                project_id: projectId,
                stage_id: stageId,
                content: content
            };
            
            // Add substage_id if provided
            if (substageId) {
                messageData.substage_id = substageId;
            }
            
            // Send message to server
            const response = await fetch('send_stage_chat_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(messageData)
            });
            
            // Get text response first to handle any potential parsing issues
            const responseText = await response.text();
            
            // Try to parse as JSON
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (error) {
                console.error('Error parsing JSON response:', error);
                console.error('Raw response:', responseText);
                
                // Remove loading indicator
                messagesContainer.removeChild(loadingIndicator);
                
                // Show error message
                const errorElement = document.createElement('div');
                errorElement.className = 'stage-chat-error';
                errorElement.innerHTML = `
                    <div class="stage-chat-error-content">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Invalid response from server. Please try again.</span>
                    </div>
                `;
                messagesContainer.appendChild(errorElement);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                
                // Remove error after 3 seconds
                setTimeout(() => {
                    if (messagesContainer.contains(errorElement)) {
                        messagesContainer.removeChild(errorElement);
                    }
                }, 3000);
                
                return;
            }
            
            // Remove loading indicator
            if (messagesContainer.contains(loadingIndicator)) {
                messagesContainer.removeChild(loadingIndicator);
            }
            
            if (responseData.success) {
                console.log('Message sent successfully:', responseData);
                // If server returns message data directly, render it instead of reloading
                if (responseData.message_data) {
                    this.renderMessage(messagesContainer, responseData.message_data);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                } else {
                    // Reload all messages if no message data returned
                    await this.loadChatMessages(projectId, stageId, substageId);
                }
            } else {
                // Show error message
                const errorElement = document.createElement('div');
                errorElement.className = 'stage-chat-error';
                errorElement.innerHTML = `
                    <div class="stage-chat-error-content">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>${responseData.message || 'Failed to send message'}</span>
                    </div>
                `;
                messagesContainer.appendChild(errorElement);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                
                // Remove error after 3 seconds
                setTimeout(() => {
                    if (messagesContainer.contains(errorElement)) {
                        messagesContainer.removeChild(errorElement);
                    }
                }, 3000);
            }
        } catch (error) {
            console.error('Error sending chat message:', error);
            
            // Show error in the chat
            const messagesContainer = chatBox.querySelector('.stage-chat-messages');
            const errorElement = document.createElement('div');
            errorElement.className = 'stage-chat-error';
            errorElement.innerHTML = `
                <div class="stage-chat-error-content">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Error: ${error.message || 'Unknown error'}</span>
                </div>
            `;
            messagesContainer.appendChild(errorElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Remove error after 3 seconds
            setTimeout(() => {
                if (messagesContainer.contains(errorElement)) {
                    messagesContainer.removeChild(errorElement);
                }
            }, 3000);
        }
    }

    // Bring a chat box to the front
    bringChatToFront(chatBox) {
        const chatBoxes = Object.values(this.chatBoxes).map(chat => chat.element);
        chatBoxes.forEach(box => {
            if (box !== chatBox) {
                box.style.zIndex = 'auto';
            }
        });
        chatBox.style.zIndex = 100000;
    }

    // Mark messages as read when opening chat
    async markMessagesAsRead(projectId, stageId, substageId = null) {
        try {
            // Build query params
            const params = new URLSearchParams({
                project_id: projectId,
                stage_id: stageId
            });
            
            if (substageId) {
                params.append('substage_id', substageId);
            }
            
            // Call API to mark messages as read
            await fetch('mark_chat_messages_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project_id: projectId,
                    stage_id: stageId,
                    substage_id: substageId
                })
            });
            
            // Reset notification counter
            this.resetNotificationCounter(stageId, substageId);
        } catch (error) {
            console.error('Error marking messages as read:', error);
        }
    }

    // Reset the notification counter in the modal
    resetNotificationCounter(stageId, substageId = null) {
        // Determine counter ID
        const counterId = substageId 
            ? `chat-counter-substage-${substageId}` 
            : `chat-counter-stage-${stageId}`;
        
        // Find and hide counter
        const counterElement = document.getElementById(counterId);
        if (counterElement) {
            counterElement.textContent = '0';
            counterElement.classList.add('hidden');
        }
    }

    // Get selected messages for edit/delete mode
    getSelectedMessages() {
        const selectedMessages = [];
        const checkboxes = document.querySelectorAll('.message-checkbox:checked');
        
        console.log('Found checked checkboxes:', checkboxes.length);
        
        checkboxes.forEach(checkbox => {
            const messageId = checkbox.dataset.messageId;
            const messageElement = document.querySelector(`.stage-chat-message[data-message-id="${messageId}"]`);
            
            if (messageElement) {
                const messageText = messageElement.querySelector('.stage-chat-message-text')?.textContent.trim();
                
                selectedMessages.push({
                    id: messageId,
                    element: messageElement,
                    text: messageText
                });
                
                console.log('Selected message:', messageId, messageText);
            }
        });
        
        return selectedMessages;
    }

    // Setup edit mode handlers
    setupEditMode() {
        const editButton = document.getElementById('edit-messages-btn');
        const deleteButton = document.getElementById('delete-messages-btn');
        const cancelButton = document.getElementById('cancel-edit-btn');
        const editControls = document.getElementById('edit-controls');
        const actionsBar = document.getElementById('chat-actions-bar');
        
        // Toggle edit mode
        editButton.addEventListener('click', () => {
            this.isEditMode = true;
            editControls.classList.remove('d-none');
            actionsBar.classList.add('d-none');
            this.showCheckboxes();
        });
        
        // Cancel edit mode
        cancelButton.addEventListener('click', () => {
            this.isEditMode = false;
            editControls.classList.add('d-none');
            actionsBar.classList.remove('d-none');
            this.hideCheckboxes();
        });
        
        // Delete selected messages
        deleteButton.addEventListener('click', async () => {
            const selectedMessages = this.getSelectedMessages();
            console.log('Attempting to delete messages:', selectedMessages);
            
            if (selectedMessages.length === 0) {
                alert('No messages selected.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedMessages.length} message(s)?`)) {
                try {
                    for (const message of selectedMessages) {
                        await this.deleteMessage(message.id);
                        message.element.remove();
                    }
                    
                    // Exit edit mode after deletion
                    cancelButton.click();
                } catch (error) {
                    console.error('Error deleting messages:', error);
                    alert('Failed to delete some messages. Please try again.');
                }
            }
        });
    }
    
    // Show checkboxes for all messages that can be edited
    showCheckboxes() {
        document.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.style.display = 'block';
        });
    }
    
    // Hide all checkboxes
    hideCheckboxes() {
        document.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.style.display = 'none';
            checkbox.checked = false;
        });
    }
    
    // Delete a message via API
    async deleteMessage(messageId) {
        try {
            const response = await fetch(`api/chat/delete-message.php?id=${messageId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to delete message: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error deleting message:', error);
            throw error;
        }
    }
    
    // Edit a message via API
    async editMessage(messageId, newContent) {
        try {
            const response = await fetch(`api/chat/edit-message.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: messageId,
                    content: newContent
                })
            });
            
            if (!response.ok) {
                throw new Error(`Failed to edit message: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error editing message:', error);
            throw error;
        }
    }

    // Ensure user meta tags are present
    ensureUserMetaTags() {
        // Check if we need to get user data from the server
        if (!document.querySelector('meta[name="user-id"]')) {
            console.log('User meta tags not found, attempting to retrieve user data');
            
            // Try to get user data from a global variable
            if (typeof window.currentUser !== 'undefined') {
                const user = window.currentUser;
                
                // Create meta tags for user information
                if (user.id) {
                    this.createMetaTag('user-id', user.id);
                }
                if (user.name) {
                    this.createMetaTag('user-name', user.name);
                }
                if (user.role) {
                    this.createMetaTag('user-role', user.role);
                }
            } else {
                // Fallback: Try to get from PHP session via AJAX
                this.fetchUserDataFromServer();
            }
        }
    }
    
    // Create a meta tag
    createMetaTag(name, content) {
        const meta = document.createElement('meta');
        meta.name = name;
        meta.content = content;
        document.head.appendChild(meta);
        console.log(`Created meta tag: ${name}=${content}`);
    }
    
    // Fetch user data from server
    fetchUserDataFromServer() {
        // Use AJAX to get user data from the server
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_current_user.php', true);
        xhr.onreadystatechange = () => {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success && response.user) {
                        // Create meta tags
                        if (response.user.id) {
                            this.createMetaTag('user-id', response.user.id);
                        }
                        if (response.user.name) {
                            this.createMetaTag('user-name', response.user.name);
                        }
                        if (response.user.role) {
                            this.createMetaTag('user-role', response.user.role);
                        }
                        
                        // Update current user properties
                        this.currentUserId = response.user.id || this.currentUserId;
                        this.currentUserName = response.user.name || this.currentUserName;
                        this.currentUserInitials = this.getInitials(this.currentUserName);
                        this.isAdmin = response.user.role === 'admin' || this.isAdmin;
                        
                        console.log('Updated user data from server:', response.user);
                    }
                } catch (error) {
                    console.error('Error parsing user data:', error);
                }
            }
        };
        xhr.send();
    }
}