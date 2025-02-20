class ChatManager {
    constructor() {
        // Wait for DOM to be ready before initializing
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initialize());
        } else {
            this.initialize();
        }
        this.apiBasePath = '/hr/dashboard/handlers'; // Adjust this if necessary
    }

    initialize() {
        // Initialize DOM elements with null checks
        this.chatContainer = document.getElementById('chatContainer');
        this.chatList = document.getElementById('chatList');
        this.welcomeScreen = document.getElementById('welcomeScreen');
        this.activeChat = document.getElementById('activeChat');
        this.messageInput = document.getElementById('messageInput');
        this.chatSearch = document.getElementById('chatSearch');

        // Only create user suggestions if messageInput exists
        if (this.messageInput) {
            this.userSuggestions = document.createElement('div');
            this.userSuggestions.className = 'user-suggestions';
            this.messageInput.parentNode.appendChild(this.userSuggestions);
        }

        // Only bind events if required elements exist
        if (this.chatSearch && this.messageInput) {
            this.bindEvents();
        }
        
        // Load initial chats if chatList exists
        if (this.chatList) {
            this.loadChats();
        }
    }

    bindEvents() {
        // Search functionality
        if (this.chatSearch) {
            this.chatSearch.addEventListener('input', (e) => {
                this.handleSearchInputChange(e);
            });
        }

        // Message input
        if (this.messageInput) {
            this.messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendMessage();
                }
            });

            // Add message input event listener for @ mentions
            this.messageInput.addEventListener('input', (e) => {
                this.handleInputChange(e);
            });
        }

        // Close suggestions on click outside
        if (this.userSuggestions) {
            document.addEventListener('click', (e) => {
                if (!this.messageInput?.contains(e.target) && !this.userSuggestions.contains(e.target)) {
                    this.userSuggestions.style.display = 'none';
                }
            });
        }
    }

    async loadChats() {
        try {
            const response = await fetch(`${this.apiBasePath}/fetch_chats.php`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'include' // This ensures cookies/session are sent
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.status === 'success') {
                if (!data.data || data.data.length === 0) {
                    // Show empty state
                    this.chatList.innerHTML = `
                        <div class="empty-chat-list">
                            <i class="fas fa-comments"></i>
                            <p>No chats yet</p>
                            <button onclick="window.chat.startNewChat()" class="start-chat-btn">
                                Start a new chat
                            </button>
                        </div>
                    `;
                } else {
                    this.renderChatList(data.data);
                }
            } else {
                throw new Error(data.message || 'Failed to load chats');
            }
        } catch (error) {
            console.error('Error loading chats:', error);
            this.chatList.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${error.message || 'Unable to load chats'}</p>
                    <button onclick="window.chat.loadChats()" class="retry-btn">
                        Retry
                    </button>
                </div>
            `;
        }
    }

    renderChatList(chats) {
        this.chatList.innerHTML = chats.map(chat => `
            <div class="chat-item" data-chat-id="${chat.id}">
                <div class="contact-avatar">
                    <img src="${chat.avatar}" alt="${chat.name}">
                </div>
                <div class="chat-info">
                    <h4>${chat.name}</h4>
                    <p>${chat.lastMessage}</p>
                </div>
                <div class="chat-meta">
                    <span class="time">${chat.lastMessageTime}</span>
                    ${chat.unreadCount ? `<span class="unread-count">${chat.unreadCount}</span>` : ''}
                </div>
            </div>
        `).join('');

        // Add click handlers
        this.chatList.querySelectorAll('.chat-item').forEach(item => {
            item.addEventListener('click', () => {
                this.openChat(item.dataset.chatId);
            });
        });
    }

    async openChat(chatId) {
        try {
            const response = await fetch(`/api/chats/${chatId}`);
            const chatData = await response.json();
            
            // Update UI
            this.welcomeScreen.style.display = 'none';
            this.activeChat.style.display = 'flex';
            
            // Update chat header
            document.getElementById('activeChatName').textContent = chatData.name;
            document.getElementById('activeChatStatus').textContent = chatData.status;
            document.getElementById('activeChatAvatar').src = chatData.avatar;

            // Load messages
            this.loadMessages(chatId);
        } catch (error) {
            console.error('Error opening chat:', error);
        }
    }

    async loadMessages(chatId) {
        try {
            const response = await fetch(`/api/chats/${chatId}/messages`);
            const messages = await response.json();
            this.renderMessages(messages);
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    renderMessages(messages) {
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.innerHTML = messages.map(message => `
            <div class="message ${message.sent ? 'sent' : 'received'}">
                <div class="message-content">${message.content}</div>
                <div class="message-meta">
                    <span class="time">${message.time}</span>
                    ${message.sent ? `
                        <span class="status">
                            <i class="fas fa-check${message.read ? '-double' : ''} ${message.read ? 'read' : ''}"></i>
                        </span>
                    ` : ''}
                </div>
            </div>
        `).join('');

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    async sendMessage() {
        const content = this.messageInput.value.trim();
        if (!content || !this.activeChat) return;

        try {
            const response = await fetch(`/api/chats/${this.activeChat}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ content })
            });

            const result = await response.json();
            if (result.success) {
                this.messageInput.value = '';
                this.loadMessages(this.activeChat);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }

    searchChats(query) {
        const items = this.chatList.querySelectorAll('.chat-item');
        items.forEach(item => {
            const name = item.querySelector('h4').textContent.toLowerCase();
            const visible = name.includes(query.toLowerCase());
            item.style.display = visible ? 'flex' : 'none';
        });
    }

    async handleInputChange(e) {
        const input = e.target;
        const value = input.value;
        const lastWord = value.split(' ').pop();

        if (lastWord.startsWith('@') && lastWord.length > 1) {
            // Get cursor position
            const cursorPosition = input.selectionStart;
            const textBeforeCursor = value.substring(0, cursorPosition);
            const words = textBeforeCursor.split(' ');
            const searchWord = words[words.length - 1];

            if (searchWord.startsWith('@')) {
                const searchTerm = searchWord.substring(1);
                await this.showUserSuggestions(searchTerm);
            }
        } else {
            this.userSuggestions.style.display = 'none';
        }
    }

    async handleSearchInputChange(e) {
        const input = e.target;
        const value = input.value;

        if (value.startsWith('@')) {
            const searchTerm = value.substring(1);
            await this.showUserSuggestions(searchTerm);
        } else {
            this.userSuggestions.style.display = 'none';
            // Your existing chat search logic here
            this.searchChats(value);
        }
    }

    async showUserSuggestions(searchTerm) {
        try {
            const response = await fetch(`${this.apiBasePath}/fetch_users.php`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();

            if (data.status === 'success' && data.users) {
                const filteredUsers = data.users.filter(user => 
                    user.username.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    user.designation.toLowerCase().includes(searchTerm.toLowerCase())
                );

                if (filteredUsers.length > 0) {
                    this.renderUserSuggestions(filteredUsers, true);
                } else {
                    this.userSuggestions.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error fetching user suggestions:', error);
            this.userSuggestions.style.display = 'none';
        }
    }

    renderUserSuggestions(users, isSearch = false) {
        this.userSuggestions.innerHTML = users.map(user => `
            <div class="user-suggestion-item" data-user-id="${user.id}" data-username="${user.username}">
                <div class="user-suggestion-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-suggestion-info">
                    <div class="user-suggestion-name">${user.username}</div>
                    <div class="user-suggestion-designation">${user.designation}</div>
                </div>
            </div>
        `).join('');

        this.userSuggestions.style.display = 'block';

        // Add click handlers for suggestions
        this.userSuggestions.querySelectorAll('.user-suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                if (isSearch) {
                    this.selectUserFromSearch(item.dataset.username);
                } else {
                    this.insertUserMention(item.dataset.username);
                }
            });
        });
    }

    selectUserFromSearch(username) {
        // Start a new chat or show existing chat with selected user
        this.searchInput.value = username;
        this.userSuggestions.style.display = 'none';
        
        // Here you would typically:
        // 1. Check if a chat exists with this user
        // 2. If it exists, open it
        // 3. If it doesn't exist, create a new chat
        
        // For now, let's just filter the chat list
        this.searchChats(username);
    }

    insertUserMention(username) {
        const input = this.messageInput;
        const value = input.value;
        const cursorPosition = input.selectionStart;
        const textBeforeCursor = value.substring(0, cursorPosition);
        const textAfterCursor = value.substring(cursorPosition);
        const words = textBeforeCursor.split(' ');
        const lastWord = words[words.length - 1];

        if (lastWord.startsWith('@')) {
            words[words.length - 1] = `@${username}`;
            const newText = words.join(' ') + textAfterCursor;
            input.value = newText;
            input.setSelectionRange(
                textBeforeCursor.length - lastWord.length + username.length + 1,
                textBeforeCursor.length - lastWord.length + username.length + 1
            );
        }

        this.userSuggestions.style.display = 'none';
    }
}

// Initialize chat only when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    window.chat = new ChatManager();
}); 