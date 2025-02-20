class SimpleChat {
    constructor() {
        this.currentReceiver = null;
        this.messageUpdateInterval = null;
        this.activeTab = 'chats'; // Default tab
        
        // Cache DOM elements
        this.chatBody = document.getElementById('chatBody');
        this.messageBox = document.getElementById('messageBox');
        this.messageInput = document.getElementById('messageInput');
        this.chatTabs = document.querySelectorAll('.chat-tab');
        
        // Initially hide message box
        if (this.messageBox) {
            this.messageBox.style.display = 'none';
        }
        
        this.setupEventListeners();
        this.loadChats(); // Load chats by default
    }
    
    setupEventListeners() {
        // Add tab click listeners
        this.chatTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabType = e.target.getAttribute('data-tab');
                this.switchTab(tabType);
            });
        });
        
        // Existing message input listener
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const content = this.messageInput.value.trim();
                if (content) {
                    this.sendMessage(content);
                }
            }
        });
        
        // Add file input listener
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.sendFile(file);
                }
            });
        }
    }
    
    switchTab(tabType) {
        // Update active tab
        this.activeTab = tabType;
        
        // Update tab UI
        this.chatTabs.forEach(tab => {
            tab.classList.remove('active');
            if (tab.getAttribute('data-tab') === tabType) {
                tab.classList.add('active');
            }
        });
        
        // Show/hide create group button
        const createGroupBtn = document.querySelector('.create-group-btn');
        if (createGroupBtn) {
            createGroupBtn.style.display = tabType === 'groups' ? 'flex' : 'none';
        }
        
        // Clear any existing intervals
        if (this.messageUpdateInterval) {
            clearInterval(this.messageUpdateInterval);
        }
        
        // Load appropriate content
        if (tabType === 'chats') {
            this.loadChats();
        } else {
            this.loadGroups();
        }
    }
    
    async loadChats() {
        // Hide message box when returning to chat list
        if (this.messageBox) {
            this.messageBox.style.display = 'none';
        }
        
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_users'
                })
            });
            
            const data = await response.json();
            this.renderUserList(data.users);
            
        } catch (error) {
            console.error('Error loading chats:', error);
            this.showError('Failed to load chats');
        }
    }
    
    async loadUsers() {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_users'
                })
            });
            
            const data = await response.json();
            this.renderUserList(data.users);
            this.updateUnreadBadge(data.total_unread);
            
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Failed to load users');
        }
    }
    
    async updateUnreadCount() {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_users'
                })
            });
            
            const data = await response.json();
            this.updateUnreadBadge(data.total_unread);
        } catch (error) {
            console.error('Error updating unread count:', error);
        }
    }
    
    updateUnreadBadge(count) {
        if (this.unreadBadge) {
            if (count > 0) {
                this.unreadBadge.textContent = count;
                this.unreadBadge.style.display = 'flex';
            } else {
                this.unreadBadge.textContent = '0';
                this.unreadBadge.style.display = 'none';
            }
        }
    }
    
    async loadMessages(receiverId) {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_messages',
                    other_user_id: receiverId
                })
            });
            
            const messages = await response.json();
            this.renderMessages(messages);
            
            // Mark messages as read
            await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mark_read',
                    sender_id: receiverId
                })
            });
            
        } catch (error) {
            console.error('Error loading messages:', error);
            this.showError('Failed to load messages');
        }
    }
    
    async sendMessage(content) {
        if (!this.currentReceiver) {
            this.showError('No recipient selected');
            return;
        }
        
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: this.isGroup ? 'send_group_message' : 'send_message',
                    [this.isGroup ? 'group_id' : 'receiver_id']: this.currentReceiver,
                    message: content
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.messageInput.value = '';
                if (this.isGroup) {
                    this.loadGroupMessages(this.currentReceiver);
                } else {
                    this.loadMessages(this.currentReceiver);
                }
            }
            
        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('Failed to send message');
        }
    }
    
    renderUserList(users) {
        const container = document.createElement('div');
        container.className = 'user-list';
        
        users.forEach(user => {
            const userElement = document.createElement('div');
            userElement.className = 'user-item';
            userElement.onclick = () => this.selectUser(user.id, user.username);
            
            // Format punch in time if exists
            const punchInTime = user.punch_in_time ? 
                new Date(user.punch_in_time).toLocaleTimeString([], { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                }) : '';
            
            // Get status text and class
            const statusClass = user.attendance_status === 'online' ? 'online' : 'offline';
            const statusText = user.attendance_status === 'online' ? 
                `Online (Since ${punchInTime})` : 
                (user.punch_in_time ? `Last seen at ${punchInTime}` : 'Offline');
            
            userElement.innerHTML = `
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                    <span class="status-indicator ${statusClass}"></span>
                    ${user.unread_count > 0 ? '<span class="unread-dot"></span>' : ''}
                </div>
                <div class="user-info">
                    <div class="user-name">
                        ${this.escapeHtml(user.username)}
                        ${user.unread_count > 0 ? `<span class="unread-count">(${user.unread_count})</span>` : ''}
                    </div>
                    <div class="user-status ${statusClass}">
                        ${statusText}
                    </div>
                </div>
            `;
            
            container.appendChild(userElement);
        });
        
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
    }
    
    selectUser(userId, username) {
        this.currentReceiver = userId;
        this.currentReceiverName = username; // Store username
        this.isGroup = false;
        
        // Show message box
        if (this.messageBox) {
            this.messageBox.style.display = 'flex';
        }
        
        // Change view to messages and show header
        const container = document.createElement('div');
        container.className = 'chat-view';
        
        // Add chat header
        const header = document.createElement('div');
        header.className = 'chat-header';
        header.innerHTML = `
            <div class="back-button">
                <i class="fas fa-arrow-left"></i>
            </div>
            <div class="chat-user-info">
                <div class="chat-user-name">${this.escapeHtml(username)}</div>
            </div>
        `;
        
        // Add messages container
        const messagesContainer = document.createElement('div');
        messagesContainer.className = 'messages-container';
        
        container.appendChild(header);
        container.appendChild(messagesContainer);
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
        
        // Add back button listener
        const backButton = header.querySelector('.back-button');
        backButton.addEventListener('click', () => {
            this.loadUsers();
            if (this.messageUpdateInterval) {
                clearInterval(this.messageUpdateInterval);
            }
        });
        
        this.loadMessages(userId);
        this.updateUnreadCount(); // Refresh unread count after loading messages
        
        // Start periodic message updates
        if (this.messageUpdateInterval) {
            clearInterval(this.messageUpdateInterval);
        }
        this.messageUpdateInterval = setInterval(() => {
            this.loadMessages(userId);
        }, 5000);
    }
    
    renderMessages(messages) {
        const container = this.chatBody.querySelector('.messages-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        messages.forEach(msg => {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${msg.sender_id == window.currentUserId ? 'sent' : 'received'}`;
            
            let contentHtml = '';
            if (msg.file_url) {
                const fileExtension = msg.file_url.split('.').pop().toLowerCase();
                const fileUrl = msg.file_url.startsWith('/') ? msg.file_url : '/' + msg.file_url;
                
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    contentHtml = `
                        <img src="${fileUrl}" alt="Image" class="message-image">
                        <a href="api/chat/handle_file.php?file_id=${msg.id}" class="file-download" download>
                            <i class="fas fa-download"></i> Download
                        </a>`;
                } else {
                    contentHtml = `
                        <div class="file-attachment">
                            <i class="fas fa-file"></i>
                            <span>${msg.original_filename || 'Attachment'}</span>
                            <a href="api/chat/handle_file.php?file_id=${msg.id}" class="file-download" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>`;
                }
            } else {
                contentHtml = `<div class="message-text">${this.escapeHtml(msg.message)}</div>`;
            }
            
            messageElement.innerHTML = `
                <div class="message-content">
                    ${contentHtml}
                    <div class="message-time">${this.formatTime(msg.created_at)}</div>
                </div>
            `;
            
            container.appendChild(messageElement);
        });
        
        this.scrollToBottom();
    }
    
    scrollToBottom() {
        this.chatBody.scrollTop = this.chatBody.scrollHeight;
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    formatTime(timestamp) {
        return new Date(timestamp).toLocaleString();
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    }
    
    async createNewGroup() {
        const { value: formValues } = await Swal.fire({
            title: 'Create New Group',
            html: `
                <input id="groupName" class="swal2-input" placeholder="Group Name">
                <div id="memberSelection" class="member-selection">
                    <div class="member-list"></div>
                </div>
            `,
            didOpen: async () => {
                // Load users for selection
                const response = await fetch('api/chat/simple_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'get_users' })
                });
                const data = await response.json();
                
                const memberList = document.querySelector('.member-list');
                data.users.forEach(user => {
                    const memberItem = document.createElement('div');
                    memberItem.className = 'member-item';
                    memberItem.innerHTML = `
                        <input type="checkbox" id="user_${user.id}" value="${user.id}">
                        <label for="user_${user.id}">${this.escapeHtml(user.username)}</label>
                    `;
                    memberList.appendChild(memberItem);
                });
            },
            preConfirm: () => {
                const groupName = document.getElementById('groupName').value;
                const selectedMembers = Array.from(document.querySelectorAll('.member-item input:checked'))
                    .map(input => parseInt(input.value));
                
                if (!groupName.trim() || selectedMembers.length === 0) {
                    Swal.showValidationMessage('Please enter group name and select members');
                    return false;
                }
                
                return { groupName, selectedMembers };
            }
        });

        if (formValues) {
            try {
                const response = await fetch('api/chat/simple_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'create_group',
                        group_name: formValues.groupName,
                        member_ids: JSON.stringify(formValues.selectedMembers)
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    // Clear current chat body
                    this.chatBody.innerHTML = '';
                    
                    // Load and display groups
                    await this.loadGroups();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Group created successfully!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            } catch (error) {
                console.error('Error creating group:', error);
                this.showError('Failed to create group');
            }
        }
    }
    
    async loadGroups() {
        // Hide message box when returning to group list
        if (this.messageBox) {
            this.messageBox.style.display = 'none';
        }
        
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_groups' })
            });
            
            const groups = await response.json();
            
            // Clear existing content
            this.chatBody.innerHTML = '';
            
            // Create groups container if there are groups
            if (groups && groups.length > 0) {
                const container = document.createElement('div');
                container.className = 'group-list';
                
                groups.forEach(group => {
                    const groupElement = document.createElement('div');
                    groupElement.className = 'group-item';
                    groupElement.onclick = () => this.selectGroup(group.id, group.name);
                    
                    groupElement.innerHTML = `
                        <div class="group-avatar">
                            <i class="fas fa-users"></i>
                            ${group.unread_count > 0 ? '<span class="unread-dot"></span>' : ''}
                        </div>
                        <div class="group-info">
                            <div class="group-name">
                                ${this.escapeHtml(group.name)}
                                ${group.unread_count > 0 ? `<span class="unread-count">(${group.unread_count})</span>` : ''}
                            </div>
                            <div class="group-role">${group.role}</div>
                        </div>
                    `;
                    
                    container.appendChild(groupElement);
                });
                
                this.chatBody.appendChild(container);
            } else {
                // Show message when no groups exist
                const noGroupsMessage = document.createElement('div');
                noGroupsMessage.className = 'no-groups-message';
                noGroupsMessage.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No groups yet</p>
                        <button class="create-group-btn" onclick="window.chat.createNewGroup()">
                            Create New Group
                        </button>
                    </div>
                `;
                this.chatBody.appendChild(noGroupsMessage);
            }
        } catch (error) {
            console.error('Error loading groups:', error);
            this.showError('Failed to load groups');
        }
    }
    
    async selectGroup(groupId, groupName) {
        this.currentReceiver = groupId;
        this.currentReceiverName = groupName;
        this.isGroup = true;
        
        // Show message box
        if (this.messageBox) {
            this.messageBox.style.display = 'flex';
        }
        
        // Change view to messages and show header
        const container = document.createElement('div');
        container.className = 'chat-view';
        
        // Add chat header with group info
        const header = document.createElement('div');
        header.className = 'chat-header';
        header.innerHTML = `
            <div class="back-button">
                <i class="fas fa-arrow-left"></i>
            </div>
            <div class="chat-user-info">
                <div class="chat-user-name">
                    <i class="fas fa-users"></i> ${this.escapeHtml(groupName)}
                </div>
            </div>
            <div class="group-actions">
                <div class="group-members-btn" onclick="window.chat.showGroupMembers(${groupId})">
                    <i class="fas fa-user-friends"></i>
                </div>
            </div>
        `;
        
        // Add messages container
        const messagesContainer = document.createElement('div');
        messagesContainer.className = 'messages-container';
        
        container.appendChild(header);
        container.appendChild(messagesContainer);
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
        
        // Add back button listener
        const backButton = header.querySelector('.back-button');
        backButton.addEventListener('click', () => {
            this.loadGroups();
            if (this.messageUpdateInterval) {
                clearInterval(this.messageUpdateInterval);
            }
        });
        
        await this.loadGroupMessages(groupId);
        
        // Start periodic message updates
        if (this.messageUpdateInterval) {
            clearInterval(this.messageUpdateInterval);
        }
        this.messageUpdateInterval = setInterval(() => {
            this.loadGroupMessages(groupId);
        }, 5000);
    }
    
    async loadGroupMessages(groupId) {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_group_messages',
                    group_id: groupId
                })
            });
            
            const messages = await response.json();
            this.renderGroupMessages(messages);
            
        } catch (error) {
            console.error('Error loading group messages:', error);
            this.showError('Failed to load group messages');
        }
    }
    
    renderGroupMessages(messages) {
        const container = this.chatBody.querySelector('.messages-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        messages.forEach(msg => {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${msg.sender_id == window.currentUserId ? 'sent' : 'received'}`;
            
            messageElement.innerHTML = `
                <div class="message-content">
                    ${msg.sender_id != window.currentUserId ? 
                        `<div class="message-sender">${this.escapeHtml(msg.sender_name)}</div>` : ''}
                    <div class="message-text">${this.escapeHtml(msg.message)}</div>
                    <div class="message-time">${this.formatTime(msg.created_at)}</div>
                </div>
            `;
            
            container.appendChild(messageElement);
        });
        
        this.scrollToBottom();
    }
    
    async showGroupMembers(groupId) {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_group_members',
                    group_id: groupId
                })
            });
            
            const members = await response.json();
            
            Swal.fire({
                title: 'Group Members',
                html: `
                    <div class="group-members-list">
                        ${members.map(member => `
                            <div class="group-member-item">
                                <div class="member-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="member-info">
                                    <div class="member-name">${this.escapeHtml(member.username)}</div>
                                    <div class="member-role">${member.role}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `,
                showCloseButton: true,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Error loading group members:', error);
            this.showError('Failed to load group members');
        }
    }
    
    async editGroup(groupId, currentName) {
        const { value: newName } = await Swal.fire({
            title: 'Edit Group Name',
            input: 'text',
            inputValue: currentName,
            showCancelButton: true,
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Group name cannot be empty!';
                }
            }
        });

        if (newName) {
            try {
                const response = await fetch('api/chat/simple_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'edit_group',
                        group_id: groupId,
                        group_name: newName.trim()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.loadGroups();
                    Swal.fire({
                        icon: 'success',
                        title: 'Group Updated',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (error) {
                console.error('Error updating group:', error);
                this.showError('Failed to update group');
            }
        }
    }
    
    async deleteGroup(groupId, groupName) {
        try {
            const result = await Swal.fire({
                title: 'Delete Group?',
                text: `Are you sure you want to delete "${groupName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                const response = await fetch('api/chat/simple_chat.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_group',
                        group_id: groupId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Group has been deleted.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Reload groups list
                    this.loadGroups();
                } else {
                    throw new Error(data.error || 'Failed to delete group');
                }
            }
        } catch (error) {
            console.error('Error deleting group:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to delete group: ' + error.message
            });
        }
    }
    
    renderGroupList(groups) {
        const container = document.createElement('div');
        container.className = 'group-list';
        
        groups.forEach(group => {
            const groupElement = document.createElement('div');
            groupElement.className = 'group-item';
            
            const groupContent = document.createElement('div');
            groupContent.className = 'group-content';
            groupContent.onclick = () => this.selectGroup(group.id, group.name);
            
            groupContent.innerHTML = `
                <div class="group-avatar">
                    <i class="fas fa-users"></i>
                    ${group.unread_count > 0 ? '<span class="unread-dot"></span>' : ''}
                </div>
                <div class="group-info">
                    <div class="group-name">
                        ${this.escapeHtml(group.name)}
                        ${group.unread_count > 0 ? `<span class="unread-count">(${group.unread_count})</span>` : ''}
                    </div>
                    <div class="group-role">${group.role}</div>
                </div>
            `;
            
            groupElement.appendChild(groupContent);
            
            if (group.role === 'admin') {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'group-actions';
                
                // Add view members button
                const viewMembersBtn = document.createElement('button');
                viewMembersBtn.className = 'group-action-btn view-members';
                viewMembersBtn.innerHTML = '<i class="fas fa-users"></i>';
                viewMembersBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.viewGroupMembers(group.id, group.name);
                };
                
                // Existing edit and delete buttons
                const editBtn = document.createElement('button');
                editBtn.className = 'group-action-btn edit';
                editBtn.innerHTML = '<i class="fas fa-edit"></i>';
                editBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.editGroup(group.id, group.name);
                };
                
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'group-action-btn delete';
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                deleteBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.deleteGroup(group.id, group.name);
                };
                
                actionsDiv.appendChild(viewMembersBtn);
                actionsDiv.appendChild(editBtn);
                actionsDiv.appendChild(deleteBtn);
                groupElement.appendChild(actionsDiv);
            }
            
            container.appendChild(groupElement);
        });
        
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
    }
    
    async sendFile(file) {
        if (!this.currentReceiver) {
            this.showError('No recipient selected');
            return;
        }

        // Check file size (e.g., 10MB limit)
        const maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if (file.size > maxSize) {
            this.showError('File size exceeds 10MB limit');
            return;
        }

        const formData = new FormData();
        formData.append('action', this.isGroup ? 'send_group_file' : 'send_file');
        formData.append('file', file);
        formData.append(this.isGroup ? 'group_id' : 'receiver_id', this.currentReceiver);

        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                if (this.isGroup) {
                    this.loadGroupMessages(this.currentReceiver);
                } else {
                    this.loadMessages(this.currentReceiver);
                }
            }
        } catch (error) {
            console.error('Error sending file:', error);
            this.showError('Failed to send file');
        }
    }
    
    async viewGroupMembers(groupId, groupName) {
        try {
            const response = await fetch('api/chat/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_group_members',
                    group_id: groupId
                })
            });

            const data = await response.json();
            if (data.success) {
                // Show members in a SweetAlert modal
                const membersHtml = data.members.map(member => `
                    <div class="group-member">
                        <div class="member-avatar">
                            ${member.profile_picture ? 
                                `<img src="${member.profile_picture}" alt="${member.username}">` : 
                                `<div class="avatar-placeholder">${member.username.charAt(0)}</div>`
                            }
                        </div>
                        <div class="member-info">
                            <span class="member-name">${member.username}</span>
                            <span class="member-role">${member.role}</span>
                        </div>
                    </div>
                `).join('');

                Swal.fire({
                    title: `${groupName} - Members`,
                    html: `<div class="group-members-list">${membersHtml}</div>`,
                    customClass: {
                        container: 'group-members-modal',
                        popup: 'group-members-popup'
                    },
                    width: '400px'
                });
            }
        } catch (error) {
            console.error('Error fetching group members:', error);
            this.showError('Failed to load group members');
        }
    }
} 