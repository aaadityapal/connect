class ChatManager {
    constructor() {
        this.ws = null;
        this.conversations = [];
        this.currentConversation = null;
        this.messageCallbacks = new Set();
        this.connected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        // Cache DOM elements
        this.chatBody = document.getElementById('chatBody');
        this.messageInput = document.getElementById('messageInput');
        this.chatContainer = document.getElementById('chatContainer');
        
        this.initializeWebSocket();
        this.loadConversations();
        this.setupEventListeners();
    }
    
    initializeWebSocket() {
        try {
            this.ws = new WebSocket('ws://localhost:8080');
            
            this.ws.onopen = () => {
                console.log('WebSocket Connected');
                this.connected = true;
                this.reconnectAttempts = 0;
                
                // Authenticate WebSocket connection
                this.ws.send(JSON.stringify({
                    type: 'auth',
                    user_id: window.currentUserId // Make sure this is set in your PHP
                }));
            };
            
            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            };
            
            this.ws.onclose = () => {
                this.connected = false;
                this.handleDisconnect();
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket Error:', error);
            };
            
        } catch (error) {
            console.error('WebSocket initialization failed:', error);
        }
    }
    
    handleDisconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            console.log(`Attempting to reconnect... (${this.reconnectAttempts + 1}/${this.maxReconnectAttempts})`);
            setTimeout(() => {
                this.reconnectAttempts++;
                this.initializeWebSocket();
            }, 5000); // Try to reconnect every 5 seconds
        } else {
            console.error('Max reconnection attempts reached');
            Swal.fire({
                icon: 'error',
                title: 'Connection Lost',
                text: 'Unable to connect to chat server. Please refresh the page.',
            });
        }
    }
    
    async loadConversations() {
        try {
            const response = await fetch('api/chat/handle_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_conversations'
                })
            });
            
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.conversations = data;
            this.renderConversationList();
            
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showError('Failed to load conversations');
        }
    }
    
    async loadMessages(conversationId, limit = 50, offset = 0) {
        try {
            const response = await fetch('api/chat/handle_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_messages',
                    conversation_id: conversationId,
                    limit: limit,
                    offset: offset
                })
            });
            
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            
            return data;
            
        } catch (error) {
            console.error('Error loading messages:', error);
            this.showError('Failed to load messages');
            return [];
        }
    }
    
    async sendMessage(content, type = 'text') {
        if (!this.currentConversation) {
            this.showError('No conversation selected');
            return;
        }
        
        try {
            const response = await fetch('api/chat/handle_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_message',
                    conversation_id: this.currentConversation,
                    content: content,
                    type: type
                })
            });
            
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Clear input after successful send
            this.messageInput.value = '';
            
        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('Failed to send message');
        }
    }
    
    renderConversationList() {
        const container = document.createElement('div');
        container.className = 'conversation-list';
        
        this.conversations.forEach(conv => {
            const convElement = document.createElement('div');
            convElement.className = 'conversation-item';
            convElement.onclick = () => this.selectConversation(conv.id);
            
            convElement.innerHTML = `
                <div class="conversation-avatar">
                    <i class="fas ${conv.type === 'group' ? 'fa-users' : 'fa-user'}"></i>
                </div>
                <div class="conversation-info">
                    <div class="conversation-name">${this.escapeHtml(conv.name || 'Chat')}</div>
                    <div class="conversation-preview">
                        ${conv.unread_count > 0 ? 
                            `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                    </div>
                </div>
            `;
            
            container.appendChild(convElement);
        });
        
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
    }
    
    async selectConversation(conversationId) {
        this.currentConversation = conversationId;
        const messages = await this.loadMessages(conversationId);
        this.renderMessages(messages);
    }
    
    renderMessages(messages) {
        const container = document.createElement('div');
        container.className = 'messages-container';
        
        messages.forEach(msg => {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${msg.sender_id == window.currentUserId ? 'sent' : 'received'}`;
            
            messageElement.innerHTML = `
                <div class="message-content">
                    <div class="message-sender">${this.escapeHtml(msg.sender_name)}</div>
                    <div class="message-text">${this.escapeHtml(msg.content)}</div>
                    <div class="message-time">${this.formatTime(msg.sent_at)}</div>
                </div>
            `;
            
            container.appendChild(messageElement);
        });
        
        this.chatBody.innerHTML = '';
        this.chatBody.appendChild(container);
        this.scrollToBottom();
    }
    
    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'message':
                if (data.conversation_id === this.currentConversation) {
                    this.appendMessage(data);
                }
                this.updateConversationPreview(data);
                break;
                
            case 'status':
                this.updateMessageStatus(data);
                break;
        }
    }
    
    appendMessage(messageData) {
        const container = this.chatBody.querySelector('.messages-container');
        if (!container) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `message ${messageData.sender_id == window.currentUserId ? 'sent' : 'received'}`;
        
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-sender">${this.escapeHtml(messageData.sender_name)}</div>
                <div class="message-text">${this.escapeHtml(messageData.content)}</div>
                <div class="message-time">Just now</div>
            </div>
        `;
        
        container.appendChild(messageElement);
        this.scrollToBottom();
    }
    
    setupEventListeners() {
        // Send message on Enter
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const content = this.messageInput.value.trim();
                if (content) {
                    this.sendMessage(content);
                }
            }
        });
        
        // Handle file drops
        this.chatBody.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
        
        this.chatBody.addEventListener('drop', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const files = Array.from(e.dataTransfer.files);
            for (const file of files) {
                await this.handleFileUpload(file);
            }
        });
    }
    
    async handleFileUpload(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'upload_file');
        formData.append('conversation_id', this.currentConversation);
        
        try {
            const response = await fetch('api/chat/handle_chat.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Send message with file URL
            await this.sendMessage(data.file_url, 'file');
            
        } catch (error) {
            console.error('Error uploading file:', error);
            this.showError('Failed to upload file');
        }
    }
    
    // Utility methods
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
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
} 