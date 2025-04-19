/**
 * Stage Chat JavaScript
 * This file handles the functionality of the chat box that appears when a user clicks
 * on the chat button in a stage or substage
 */

class StageChat {
    constructor() {
        this.chatBoxes = {};
        this.currentUserId = this.getCurrentUserId();
        this.currentUserName = this.getCurrentUserName();
        this.currentUserInitials = this.getInitials(this.currentUserName);
        this.isAdmin = this.isAdminUser();
        this.injectStyles();
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
                gap: 10px;
                max-width: 85%;
            }
            
            .stage-chat-message.outgoing {
                align-self: flex-end;
                flex-direction: row-reverse;
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
            
            .stage-chat-avatar-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .stage-chat-message.outgoing .stage-chat-message-avatar {
                background-color: #10b981;
            }
            
            .stage-chat-message-content {
                background-color: white;
                padding: 10px 14px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                font-size: 13px;
                line-height: 1.4;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                position: relative;
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
        `;
        
        // Append style element to document head
        document.head.appendChild(styleEl);
    }

    // Get current user ID from the document
    getCurrentUserId() {
        const userIdElement = document.querySelector('meta[name="user-id"]');
        return userIdElement ? userIdElement.getAttribute('content') : null;
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
                    <button class="stage-chat-action-btn minimize-btn" title="Minimize">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="stage-chat-action-btn close-btn" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
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
        // Check if the message is from the current user
        const isCurrentUser = parseInt(message.user_id) === parseInt(this.currentUserId);
        
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
        messageElement.innerHTML = `
            <div class="stage-chat-message-avatar">${avatarContent}</div>
            <div class="stage-chat-message-content">
                ${message.message || ''}
                <div class="stage-chat-sender">${message.user_name || 'Unknown'}</div>
            </div>
            <div class="stage-chat-message-meta">${this.formatDateTime(message.timestamp)}</div>
        `;
        messagesContainer.appendChild(messageElement);
        
        // For debugging
        console.log('Rendering message:', message);
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
                <div class="stage-chat-message-avatar">${this.currentUserInitials}</div>
                <div class="stage-chat-message-content">
                    ${content}
                    <div class="stage-chat-sending">
                        <small><i class="fas fa-spinner fa-spin"></i> Sending...</small>
                    </div>
                    <div class="stage-chat-sender">${this.currentUserName || 'You'}</div>
                </div>
                <div class="stage-chat-message-meta">${this.formatDateTime(new Date())}</div>
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
}