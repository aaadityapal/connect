// Add this at the beginning of your chat.js file
let chatUsers = [];
let lastCheckTime = new Date().toISOString();
let messageCheckInterval = null;
let currentEmojiCategory = 'smileys';
let emojis = {};
let searchResults = [];
let currentSearchIndex = -1;
const MESSAGE_CHECK_FREQUENCY = 3000; // Check for new messages every 3 seconds
let processedMessageIds = new Set(); // Track processed message IDs

// Function to fetch users with Active status
function fetchChatUsers() {
    fetch('fetch_chat_users.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Only Active status users are returned from the backend
                chatUsers = data.users;
                updateUsersList(chatUsers);
            } else {
                console.error('Failed to fetch users:', data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching users:', error);
        });
}

// Function to update users list in the UI
function updateUsersList(users) {
    console.log('Updating users list with:', users); // Log users data
    const usersList = document.querySelector('.chat-users-list');
    if (!usersList) {
        console.error('Chat users list element not found');
        return;
    }

    // Store users in chatUsers array
    chatUsers = users; // Make sure we update the global chatUsers array

    usersList.innerHTML = '';

    // Only Active users are displayed here as they are filtered by the backend
    users.forEach(user => {
        console.log('Processing user:', user); // Log each user being processed
        const userItem = `
            <div class="chat-user-item" data-user-id="${user.id}">
                <div class="user-avatar">
                    <img src="${user.avatar || 'assets/default-avatar.png'}" alt="${user.username}">
                    <span class="status-indicator ${user.status || 'offline'}"></span>
                </div>
                <div class="user-info">
                    <div class="user-name">${user.username}</div>
                    <div class="message-preview">Click to start conversation</div>
                </div>
                <div class="message-metadata">
                    <div class="message-time"></div>
                </div>
            </div>
        `;
        usersList.innerHTML += userItem;
    });

    // Add click handlers to chat items
    document.querySelectorAll('.chat-user-item').forEach(item => {
        item.addEventListener('click', function() {
            const userId = this.dataset.userId;
            console.log('Chat item clicked, user ID:', userId); // Log click event
            
            // Find user in the chatUsers array
            const user = chatUsers.find(u => u.id.toString() === userId.toString());
            console.log('Found user data:', user); // Log found user data
            
            if (user) {
                const chatUser = {
                    id: user.id,
                    username: user.username,
                    avatar: user.avatar || 'assets/default-avatar.png',
                    status: user.status || 'offline'
                };
                console.log('Created chat user object:', chatUser); // Log chat user object
                
                // Update UI to show selected chat
                document.querySelectorAll('.chat-user-item').forEach(i => {
                    i.classList.remove('selected');
                });
                this.classList.add('selected');

                openChat(chatUser);
            } else {
                console.error('User not found in chatUsers array. Available users:', chatUsers);
            }
        });
    });
}

// Chat Interface Functions
function toggleChatInterface() {
    const chatInterface = document.querySelector('.floating-chat-interface');
    chatInterface.classList.toggle('active');
    
    if (chatInterface.classList.contains('active')) {
        document.querySelector('.chat-notification-bubble').textContent = '0';
        document.querySelector('.chat-users-list').style.display = 'block';
        document.querySelector('.chat-conversation').style.display = 'none';
        fetchChatUsers();
        startMessageChecking();
    } else {
        stopMessageChecking();
    }
}

// Simulate receiving a new message
function simulateNewMessage() {
    const notificationBubble = document.querySelector('.chat-notification-bubble');
    const currentCount = parseInt(notificationBubble.textContent);
    notificationBubble.textContent = currentCount + 1;
    
    // Add bounce animation
    notificationBubble.style.transform = 'scale(1.2)';
    setTimeout(() => {
        notificationBubble.style.transform = 'scale(1)';
    }, 200);
}

// Optional: Simulate receiving messages periodically
setInterval(() => {
    if (!document.querySelector('.floating-chat-interface').classList.contains('active')) {
        simulateNewMessage();
    }
}, 30000); // Simulate new message every 30 seconds

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add click events to chat users
    document.querySelectorAll('.chat-user-item').forEach(item => {
        item.addEventListener('click', function() {
            // Open individual chat functionality would go here
            console.log('Chat selected:', this.querySelector('.user-name').textContent);
            
            // Remove unread count
            const unreadBadge = this.querySelector('.unread-count');
            if (unreadBadge) {
                unreadBadge.style.display = 'none';
            }
        });
    });
    
    // Search functionality 
    const searchInput = document.querySelector('.search-container input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            document.querySelectorAll('.chat-user-item').forEach(item => {
                const name = item.querySelector('.user-name').textContent.toLowerCase();
                const preview = item.querySelector('.message-preview').textContent.toLowerCase();
                
                if (name.includes(query) || preview.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
    // Add this to the end of assets/js/chat.js
document.addEventListener('DOMContentLoaded', function() {
    // Set up search functionality
    const searchInput = document.querySelector('.search-container input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            console.log('Searching for:', query); // Debug log
            
            const chatItems = document.querySelectorAll('.chat-user-item');
            console.log('Found', chatItems.length, 'chat items'); // Debug log
            
            chatItems.forEach(item => {
                const nameElement = item.querySelector('.user-name');
                const previewElement = item.querySelector('.message-preview');
                
                if (!nameElement || !previewElement) {
                    console.warn('Chat item missing name or preview element', item);
                    return;
                }
                
                const name = nameElement.textContent.toLowerCase();
                const preview = previewElement.textContent.toLowerCase();
                
                if (name.includes(query) || preview.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    } else {
        console.error('Search input element not found!');
    }
    
    // Add click handlers to chat items
    document.querySelectorAll('.chat-user-item').forEach(item => {
        item.addEventListener('click', function() {
            console.log('Chat selected:', this.querySelector('.user-name').textContent);
            
            // Highlight selected chat
            document.querySelectorAll('.chat-user-item').forEach(i => {
                i.classList.remove('selected');
            });
            this.classList.add('selected');
            
            // Remove unread indicator
            const unread = this.querySelector('.unread-count');
            if (unread) {
                unread.style.display = 'none';
            }
        });
    });
});

// Add this to your existing JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const chatUsersList = document.querySelector('.chat-users-list');
    const chatConversation = document.querySelector('.chat-conversation');
    const backButton = document.querySelector('.back-button');
    const contactName = document.querySelector('.contact-name');
    const contactAvatar = document.querySelector('.contact-avatar img');
    const chatMessages = document.querySelector('.chat-messages');
    const messageInput = document.querySelector('.message-input');
    const sendButton = document.querySelector('.send-button');
    
    // Conversation data - stores messages for each user
    const conversations = {};
    
    // Current selected user
    let currentUser = null;
    
    // Add click event to user items
    document.querySelectorAll('.chat-user-item').forEach(item => {
        item.addEventListener('click', function() {
            // Get user info
            const userName = this.querySelector('.user-name').textContent;
            const userAvatar = this.querySelector('.user-avatar img').src;
            const userId = this.getAttribute('data-user-id') || userName.replace(/\s+/g, '-').toLowerCase();
            
            // Clear unread count
            const unreadBadge = this.querySelector('.unread-count');
            if (unreadBadge) {
                unreadBadge.style.display = 'none';
            }
            
            // Open chat with this user
            openChat(userId, userName, userAvatar);
        });
    });
    
    // Back button event
    if (backButton) {
        backButton.addEventListener('click', function() {
            closeChat();
        });
    }
    
    // Send button event
    if (sendButton) {
        sendButton.addEventListener('click', function() {
            sendMessage();
        });
    }
    
    // Enter key to send message
    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
    
    // Function to open chat with a specific user
    function openChat(userId, userName, userAvatar) {
        console.log('Opening chat with user:', { userId, userName, userAvatar }); // Log incoming user data
        
        if (!user || !user.id) {
            console.error('Invalid user object provided to openChat:', user);
            return;
        }

        currentChatUser = user;
        
        // Get required elements
        const chatUsersList = document.querySelector('.chat-users-list');
        const chatConversation = document.querySelector('.chat-conversation');
        const contactName = document.querySelector('.contact-name');
        const contactAvatar = document.querySelector('.contact-avatar img');
        const contactStatus = document.querySelector('.contact-status');
        const messageInput = document.querySelector('.message-input');
        
        // Log DOM elements availability
        console.log('DOM elements found:', {
            chatUsersList: !!chatUsersList,
            chatConversation: !!chatConversation,
            contactName: !!contactName,
            contactAvatar: !!contactAvatar,
            contactStatus: !!contactStatus,
            messageInput: !!messageInput
        });
        
        // Update conversation header
        if (contactName) contactName.textContent = user.username;
        if (contactAvatar) contactAvatar.src = user.avatar || 'assets/default-avatar.png';
        if (contactStatus) contactStatus.textContent = user.status || 'offline';
        
        // Show conversation, hide users list
        if (chatUsersList) chatUsersList.style.display = 'none';
        if (chatConversation) chatConversation.style.display = 'flex';
        
        // Load messages
        console.log('Fetching messages for user ID:', user.id); // Log before fetching messages
        fetchMessages(user.id);
        
        // Focus on message input
        if (messageInput) {
            messageInput.focus();
        } else {
            console.error('Message input element not found');
        }
    }
    
    // Function to close chat and go back to user list
    function closeChat() {
        chatConversation.style.display = 'none';
        chatUsersList.style.display = 'block';
        currentUser = null;
    }
    
    // Function to display messages for a specific user
    function displayMessages(userId) {
        chatMessages.innerHTML = '';
        
        // First time opening chat, add a default message
        if (conversations[userId].length === 0) {
            // Add default welcome message
            const now = new Date();
            const timeString = formatTime(now);
            
            conversations[userId].push({
                text: 'Hello! 👋 How can I help you today?',
                type: 'received',
                time: timeString
            });
        }
        
        // Display all messages
        conversations[userId].forEach(message => {
            const messageElement = createMessageElement(message, false);
            chatMessages.appendChild(messageElement);
        });
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Function to create a message element
    function createMessageElement(message, isOwn) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isOwn ? 'sent' : 'received'}`;
        messageDiv.dataset.messageId = message.id;
        
        let messageContent = `
            ${!isOwn ? `
                <div class="message-sender">
                    <img src="${message.sender_avatar || 'assets/default-avatar.png'}" alt="" class="sender-avatar">
                </div>
            ` : ''}
            <div class="message-content">
                <div class="message-text">${message.message}</div>
                <div class="message-info">
                    <span class="message-time">${formatMessageTime(message.sent_at)}</span>
                    <div class="message-actions">
                        <button class="reaction-button" onclick="showReactionPicker(this, '${message.id}')">
                            <i class="far fa-smile"></i>
                        </button>
                        <button class="forward-message" onclick="forwardMessage('${message.id}', '${message.message}')">
                            <i class="fas fa-share"></i>
                        </button>
                        ${isOwn ? `
                            <button class="delete-message" onclick="deleteMessage('${message.id}', this.closest('.message'))">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="message-reactions" data-message-id="${message.id}"></div>
            </div>
        `;
        
        messageDiv.innerHTML = messageContent;
        
        // Add hover effect for message actions
        messageDiv.addEventListener('mouseenter', () => {
            messageDiv.querySelector('.message-actions').style.opacity = '1';
        });
        
        messageDiv.addEventListener('mouseleave', () => {
            messageDiv.querySelector('.message-actions').style.opacity = '0';
        });

        // Load existing reactions
        loadMessageReactions(message.id);
        
        return messageDiv;
    }
    
    // Function to send a message
    function sendMessage() {
        if (!currentChatUser) {
            console.error('No chat user selected');
            return;
        }
        
        const messageInput = document.querySelector('.message-input');
        const message = messageInput.value.trim();
        
        if (!message) return;
        
        // Clear input immediately for better UX
        messageInput.value = '';
        
        // Create a temporary message element
        const tempMessage = createMessageElement({
            id: 'temp_' + Date.now(),
            sender_id: parseInt(document.body.dataset.userId),
            sender_name: 'You',
            message: message,
            sent_at: new Date().toISOString()
        }, true);
        
        // Add temporary message to chat
        const chatMessages = document.querySelector('.chat-messages');
        chatMessages.appendChild(tempMessage);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Send message to server
        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                receiver_id: currentChatUser.id,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Replace temporary message with actual message
                tempMessage.remove();
                const messageElement = createMessageElement(data.message, true);
                chatMessages.appendChild(messageElement);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                // Show error and remove temporary message
                console.error('Failed to send message:', data.error);
                tempMessage.classList.add('error');
                tempMessage.querySelector('.message-text').innerHTML += 
                    '<br><small class="error-text">Failed to send. Please try again.</small>';
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            tempMessage.classList.add('error');
            tempMessage.querySelector('.message-text').innerHTML += 
                '<br><small class="error-text">Failed to send. Please try again.</small>';
        });
    }
    
    // Function to simulate a reply
    function simulateReply(userId) {
        if (currentUser && currentUser.id === userId) {
            // Get current time
            const now = new Date();
            const timeString = formatTime(now);
            
            // Generate reply based on last message
            const lastMessage = conversations[userId][conversations[userId].length - 1];
            let replyText = 'I understand. How else can I help you?';
            
            // Simple response logic
            if (lastMessage.text.toLowerCase().includes('hello') || 
                lastMessage.text.toLowerCase().includes('hi')) {
                replyText = 'Hello! How can I assist you today?';
            } else if (lastMessage.text.toLowerCase().includes('help')) {
                replyText = 'I\'d be happy to help. What do you need assistance with?';
            } else if (lastMessage.text.toLowerCase().includes('thank')) {
                replyText = 'You\'re welcome! Let me know if you need anything else.';
            }
            
            // Add message to conversation
            const message = {
                text: replyText,
                type: 'received',
                time: timeString
            };
            
            conversations[userId].push(message);
            
            // Display received message
            const messageElement = createMessageElement(message, false);
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Helper function to format time
    function formatTime(date) {
        let hours = date.getHours();
        let minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        minutes = minutes < 10 ? '0' + minutes : minutes;
        
        return hours + ':' + minutes + ' ' + ampm;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const emojiButton = document.querySelector('.emoji-button');
    const emojiPicker = document.querySelector('.emoji-picker');
    const attachmentButton = document.querySelector('.attachment-button');
    const messageInput = document.querySelector('.message-input');
    const sendButton = document.querySelector('.send-button');
    
    // Toggle emoji picker
    if (emojiButton) {
        emojiButton.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleEmojiPicker();
        });
    }
    
    // Close emoji picker when clicking outside
    document.addEventListener('click', function(e) {
        if (emojiPicker && emojiPicker.style.display !== 'none') {
            if (!emojiPicker.contains(e.target) && e.target !== emojiButton) {
                emojiPicker.style.display = 'none';
            }
        }
    });
    
    // Select emoji
    const emojiItems = document.querySelectorAll('.emoji-item');
    emojiItems.forEach(item => {
        item.addEventListener('click', function() {
            // Insert emoji at cursor position
            const emoji = this.textContent;
            insertEmoji(emoji);
            messageInput.focus();
        });
    });
    
    // Switch between emoji categories
    const categoryButtons = document.querySelectorAll('.category-button');
    const emojiGrids = document.querySelectorAll('.emoji-grid');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active button
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show selected category
            emojiGrids.forEach(grid => {
                if (grid.getAttribute('data-category') === category) {
                    grid.style.display = 'grid';
                } else {
                    grid.style.display = 'none';
                }
            });
        });
    });
    
    // File attachment functionality
    if (attachmentButton) {
        attachmentButton.addEventListener('click', function() {
            // Create attachment modal if it doesn't exist
            let attachmentModal = document.querySelector('.attachment-modal');
            
            if (!attachmentModal) {
                attachmentModal = document.createElement('div');
                attachmentModal.className = 'attachment-modal';
                
                attachmentModal.innerHTML = `
                    <div class="attachment-content">
                        <div class="attachment-header">
                            <h3>Share</h3>
                            <button class="close-attachment">&times;</button>
                        </div>
                        <div class="attachment-options">
                            <div class="attachment-option" data-type="document">
                                <div class="option-icon documents">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="option-label">Document</div>
                            </div>
                            <div class="attachment-option" data-type="photo">
                                <div class="option-icon photos">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="option-label">Photos</div>
                            </div>
                            <div class="attachment-option" data-type="camera">
                                <div class="option-icon camera">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <div class="option-label">Camera</div>
                            </div>
                            <div class="attachment-option" data-type="video">
                                <div class="option-icon videos">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="option-label">Video</div>
                            </div>
                            <div class="attachment-option" data-type="audio">
                                <div class="option-icon audio">
                                    <i class="fas fa-microphone"></i>
                                </div>
                                <div class="option-label">Audio</div>
                            </div>
                            <div class="attachment-option" data-type="location">
                                <div class="option-icon location">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="option-label">Location</div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(attachmentModal);
                
                // Close button functionality
                const closeBtn = attachmentModal.querySelector('.close-attachment');
                closeBtn.addEventListener('click', function() {
                    attachmentModal.classList.remove('show');
                });
                
                // Attachment options functionality
                const options = attachmentModal.querySelectorAll('.attachment-option');
                options.forEach(option => {
                    option.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        
                        if (type === 'document' || type === 'photo' || type === 'video' || type === 'audio') {
                            // Create a file input
                            const fileInput = document.createElement('input');
                            fileInput.type = 'file';
                            
                            // Set accepted file types
                            if (type === 'photo') {
                                fileInput.accept = 'image/*';
                            } else if (type === 'video') {
                                fileInput.accept = 'video/*';
                            } else if (type === 'audio') {
                                fileInput.accept = 'audio/*';
                            }
                            
                            // Trigger file selection
                            fileInput.click();
                            
                            fileInput.addEventListener('change', function() {
                                if (this.files.length > 0) {
                                    const fileName = this.files[0].name;
                                    attachmentModal.classList.remove('show');
                                    
                                    // Show file name in message input
                                    messageInput.value = `File: ${fileName}`;
                                    messageInput.setAttribute('data-file', 'true');
                                    messageInput.focus();
                                }
                            });
                        } else if (type === 'camera') {
                            alert('Camera access requested');
                            attachmentModal.classList.remove('show');
                        } else if (type === 'location') {
                            alert('Location access requested');
                            attachmentModal.classList.remove('show');
                        }
                    });
                });
            }
            
            // Show the modal
            attachmentModal.classList.add('show');
        });
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const attachmentModal = document.querySelector('.attachment-modal');
            if (attachmentModal && attachmentModal.classList.contains('show')) {
                if (!attachmentModal.querySelector('.attachment-content').contains(e.target) && e.target !== attachmentButton) {
                    attachmentModal.classList.remove('show');
                }
            }
        });
    }
    
    // Helper function to insert text at cursor position
    function insertEmoji(emoji) {
        const messageInput = document.querySelector('.message-input');
        const cursorPos = messageInput.selectionStart;
        const textBefore = messageInput.value.substring(0, cursorPos);
        const textAfter = messageInput.value.substring(cursorPos);
        
        messageInput.value = textBefore + emoji + textAfter;
        messageInput.selectionStart = messageInput.selectionEnd = cursorPos + emoji.length;
        messageInput.focus();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Get search elements
    const searchToggleBtn = document.querySelector('.search-toggle-btn');
    const searchBar = document.querySelector('.chat-conversation-search');
    const searchBackBtn = document.querySelector('.search-back');
    const searchInput = document.querySelector('.conversation-search-input');
    const searchClearBtn = document.querySelector('.search-clear');
    const resultsCount = document.querySelector('.results-count');
    const searchUpBtn = document.querySelector('.search-up-btn');
    const searchDownBtn = document.querySelector('.search-down-btn');
    
    // Search state
    let searchResults = [];
    let currentResult = -1;
    
    // Toggle search bar
    searchToggleBtn.addEventListener('click', function() {
        searchBar.style.display = searchBar.style.display === 'none' ? 'flex' : 'none';
        if (searchBar.style.display === 'flex') {
            searchInput.focus();
        } else {
            // Clear search when hiding
            clearSearch();
        }
    });
    
    // Hide search bar when back button is clicked
    searchBackBtn.addEventListener('click', function() {
        searchBar.style.display = 'none';
        clearSearch();
    });
    
    // Clear search input and results
    searchClearBtn.addEventListener('click', function() {
        searchInput.value = '';
        clearSearch();
        searchInput.focus();
    });
    
    // Handle search input
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length > 0) {
            performSearch(query);
        } else {
            clearSearch();
        }
    });
    
    // Navigate to previous result
    searchUpBtn.addEventListener('click', function() {
        if (searchResults.length > 0) {
            currentResult = (currentResult - 1 + searchResults.length) % searchResults.length;
            navigateToResult(currentResult);
        }
    });
    
    // Navigate to next result
    searchDownBtn.addEventListener('click', function() {
        if (searchResults.length > 0) {
            currentResult = (currentResult + 1) % searchResults.length;
            navigateToResult(currentResult);
        }
    });
    
    // Function to perform search
    function performSearch(query) {
        if (!query.trim()) {
            clearSearch();
            return;
        }

        const chatMessages = document.querySelector('.chat-messages');
        if (!chatMessages) {
            console.error('Chat messages container not found');
            return;
        }

        clearSearch(); // Clear previous search results
        
        const messageElements = chatMessages.querySelectorAll('.message');
        console.log('Found messages:', messageElements.length); // Debug log
        
        // Find matches
        messageElements.forEach(element => {
            const messageText = element.querySelector('.message-text');
            if (!messageText) return;
            
            const text = messageText.textContent.toLowerCase();
            const searchQuery = query.toLowerCase();
            
            if (text.includes(searchQuery)) {
                searchResults.push(element);
                
                // Highlight text using mark.js
                const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
                const highlightedText = messageText.textContent.replace(regex, '<mark class="message-highlight">$1</mark>');
                messageText.innerHTML = highlightedText;
            }
        });
        
        console.log('Search results:', searchResults.length); // Debug log
        
        // Update results count
        updateSearchResults();
        
        // Navigate to first result if available
        if (searchResults.length > 0) {
            currentSearchIndex = 0;
            navigateToResult(currentSearchIndex);
        }
    }
    
    // Function to clear search highlights and reset
    function clearSearch() {
        const chatMessages = document.querySelector('.chat-messages');
        if (!chatMessages) return;

        // Remove all highlights
        const messageElements = chatMessages.querySelectorAll('.message');
        messageElements.forEach(element => {
            const messageText = element.querySelector('.message-text');
            if (!messageText) return;
            
            // Remove highlight marks but preserve original text
            const originalText = messageText.textContent;
            messageText.innerHTML = originalText;
            
            // Remove current result highlight
            element.classList.remove('current-search-result');
        });
        
        searchResults = [];
        currentSearchIndex = -1;
        updateSearchResults();
    }
    
    // Function to navigate to a specific result
    function navigateToResult(index) {
        if (!searchResults.length || index < 0 || index >= searchResults.length) return;

        // Remove previous highlight class
        document.querySelectorAll('.message.current-search-result').forEach(el => {
            el.classList.remove('current-search-result');
        });

        // Add highlight class to current result
        const currentElement = searchResults[index];
        currentElement.classList.add('current-search-result');

        // Scroll the message into view
        currentElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        // Update results count display
        updateSearchResults();
    }
    
    // Helper function to escape regex special characters
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file-input');
    const messageInput = document.querySelector('.message-input');
    const sendButton = document.querySelector('.send-button');
    const chatMessages = document.querySelector('.chat-messages');
    
    // Selected files storage
    let selectedFiles = [];
    
    // Handle file selection
    fileInput.addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        selectedFiles = files;
        
        // Show file preview
        if (files.length > 0) {
            let previewContainer = document.querySelector('.file-preview-container');
            
            // Create container if it doesn't exist
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'file-preview-container';
                
                // Add scroll container for multiple files
                const scrollContainer = document.createElement('div');
                scrollContainer.className = 'file-preview-scroll';
                previewContainer.appendChild(scrollContainer);
                
                // Insert preview before input wrapper
                const inputWrapper = document.querySelector('.input-wrapper');
                inputWrapper.parentNode.insertBefore(previewContainer, inputWrapper);
            }
            
            const scrollContainer = previewContainer.querySelector('.file-preview-scroll');
            scrollContainer.innerHTML = ''; // Clear existing previews
            
            files.forEach(file => {
                const preview = createFilePreview(file);
                scrollContainer.appendChild(preview);
            });
        }
        
        // Clear file input
        fileInput.value = '';
    });
    
    // Create file preview element
    function createFilePreview(file) {
        const preview = document.createElement('div');
        preview.className = 'file-preview';
        
        const icon = getFileIcon(file.type);
        const size = formatFileSize(file.size);
        
        preview.innerHTML = `
            <i class="${icon}"></i>
            <span class="file-name">${file.name}</span>
            <span class="file-size">(${size})</span>
            <span class="remove-file">
                <i class="fas fa-times"></i>
            </span>
        `;
        
        // Remove file when clicking the remove button
        preview.querySelector('.remove-file').addEventListener('click', function() {
            selectedFiles = selectedFiles.filter(f => f !== file);
            preview.remove();
            
            // If no files left, remove the container
            if (selectedFiles.length === 0) {
                const container = document.querySelector('.file-preview-container');
                if (container) container.remove();
            }
        });
        
        return preview;
    }
    
    // Handle sending messages with files
    sendButton.addEventListener('click', function() {
        const text = messageInput.value.trim();
        
        // If there are files selected, send them
        if (selectedFiles.length > 0) {
            selectedFiles.forEach(file => {
                sendFileMessage(file);
            });
            
            // Clear selected files and remove previews
            selectedFiles = [];
            const previewContainer = document.querySelector('.file-preview-container');
            if (previewContainer) previewContainer.remove();
        }
        
        // If there's text, send it as a regular message
        if (text) {
            sendTextMessage(text);
            messageInput.value = '';
        }
    });
    
    // Function to send a file message
    function sendFileMessage(file) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message-sent';
        
        const icon = getFileIcon(file.type);
        const size = formatFileSize(file.size);
        
        messageDiv.innerHTML = `
            <div class="file-message">
                <i class="${icon}"></i>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${size}</div>
                </div>
            </div>
            <span class="message-time">${getCurrentTime()}</span>
        `;
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Helper functions
    function getFileIcon(fileType) {
        if (fileType.startsWith('image/')) return 'far fa-image';
        if (fileType.startsWith('video/')) return 'far fa-file-video';
        if (fileType.startsWith('audio/')) return 'far fa-file-audio';
        if (fileType.includes('pdf')) return 'far fa-file-pdf';
        if (fileType.includes('word')) return 'far fa-file-word';
        if (fileType.includes('excel')) return 'far fa-file-excel';
        return 'far fa-file';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
    }
    
    function sendTextMessage(text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message-sent';
        messageDiv.innerHTML = `
            <span class="message-text">${text}</span>
            <span class="message-time">${getCurrentTime()}</span>
        `;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

// Make sure this code is inside the DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    // Find the new group button
    const newGroupButton = document.querySelector('.new-group-button');
    
    if (newGroupButton) {
        newGroupButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            Swal.fire({
                title: '<span style="color: #008069">Create New Group</span>',
                html: `
                    <div class="group-creation-form">
                        <div class="group-name-input">
                            <i class="fas fa-users"></i>
                            <input type="text" id="groupName" class="swal2-input" placeholder="Group name">
                        </div>
                        <div class="group-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="memberSearch" class="swal2-input" placeholder="Search for participants">
                        </div>
                        <div class="selected-members"></div>
                        <div class="member-list">
                            ${generateUserList()}
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Create Group',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#008069',
                customClass: {
                    popup: 'group-creation-popup',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                },
                didOpen: () => {
                    // Initialize search functionality
                    const searchInput = document.getElementById('memberSearch');
                    const memberList = document.querySelector('.member-list');
                    const selectedMembers = new Set();

                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const query = this.value.toLowerCase();
                            const members = memberList.querySelectorAll('.member-item');
                            
                            members.forEach(member => {
                                const name = member.querySelector('.member-name').textContent.toLowerCase();
                                member.style.display = name.includes(query) ? 'flex' : 'none';
                            });
                        });
                    }

                    // Add click handlers for member selection
                    if (memberList) {
                        memberList.querySelectorAll('.member-item').forEach(member => {
                            member.addEventListener('click', function() {
                                const userId = this.dataset.userId;
                                const userName = this.querySelector('.member-name').textContent;
                                const userAvatar = this.querySelector('.member-avatar img').src;

                                if (this.classList.contains('selected')) {
                                    // Remove member
                                    this.classList.remove('selected');
                                    selectedMembers.delete(userId);
                                    document.querySelector(`.selected-member[data-user-id="${userId}"]`)?.remove();
                                } else {
                                    // Add member
                                    this.classList.add('selected');
                                    selectedMembers.add(userId);
                                    
                                    const selectedMembersContainer = document.querySelector('.selected-members');
                                    const memberChip = document.createElement('div');
                                    memberChip.className = 'selected-member';
                                    memberChip.dataset.userId = userId;
                                    memberChip.innerHTML = `
                                        <img src="${userAvatar}" alt="${userName}">
                                        <span>${userName}</span>
                                        <i class="fas fa-times remove-member"></i>
                                    `;
                                    
                                    memberChip.querySelector('.remove-member').addEventListener('click', (e) => {
                                        e.stopPropagation();
                                        selectedMembers.delete(userId);
                                        memberChip.remove();
                                        member.classList.remove('selected');
                                    });
                                    
                                    selectedMembersContainer.appendChild(memberChip);
                                }
                            });
                        });
                    }
                },
                preConfirm: () => {
                    const groupName = document.getElementById('groupName').value.trim();
                    const selectedMembers = Array.from(document.querySelectorAll('.member-item.selected')).map(member => ({
                        id: member.dataset.userId,
                        name: member.querySelector('.member-name').textContent
                    }));

                    if (!groupName) {
                        Swal.showValidationMessage('Please enter a group name');
                        return false;
                    }

                    if (selectedMembers.length < 2) {
                        Swal.showValidationMessage('Please select at least 2 members');
                        return false;
                    }

                    return { groupName, members: selectedMembers };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    createGroup(result.value.groupName, result.value.members);
                }
            });
        });
    } else {
        console.error('New group button not found');
    }
});

// Helper function to generate user list HTML
function generateUserList() {
    // This is sample data - replace with your actual user data
    const users = [
        { id: '1', name: 'Sarah Johnson', avatar: 'assets/default-avatar.png', status: 'online' },
        { id: '2', name: 'John Smith', avatar: 'assets/default-avatar.png', status: 'offline' },
        { id: '3', name: 'Emily Wilson', avatar: 'assets/default-avatar.png', status: 'online' },
        { id: '4', name: 'Michael Brown', avatar: 'assets/default-avatar.png', status: 'offline' },
        { id: '5', name: 'Jessica Taylor', avatar: 'assets/default-avatar.png', status: 'online' }
    ];

    return users.map(user => `
        <div class="member-item" data-user-id="${user.id}">
            <div class="member-avatar">
                <img src="${user.avatar}" alt="${user.name}">
                <span class="status-dot ${user.status}"></span>
            </div>
            <div class="member-info">
                <div class="member-name">${user.name}</div>
                <div class="member-status">${user.status}</div>
            </div>
            <div class="member-select">
                <i class="fas fa-check"></i>
            </div>
        </div>
    `).join('');
}

// Function to handle group creation
function createGroup(groupName, members) {
    const chatUsersList = document.querySelector('.chat-users-list');
    const groupItem = document.createElement('div');
    groupItem.className = 'chat-user-item';
    groupItem.innerHTML = `
        <div class="user-avatar">
            <i class="fas fa-users"></i>
        </div>
        <div class="user-info">
            <div class="user-name">${groupName}</div>
            <div class="message-preview">${members.length} participants</div>
        </div>
    `;

    // Add click event to open group chat
    groupItem.addEventListener('click', function() {
        const chatUsersList = document.querySelector('.chat-users-list');
        const chatConversation = document.querySelector('.chat-conversation');
        
        // Update conversation header
        const contactName = document.querySelector('.contact-name');
        const contactStatus = document.querySelector('.contact-status');
        
        // Update header with edit button
        const chatHeader = document.querySelector('.chat-conversation-header');
        chatHeader.innerHTML = `
            <button class="back-button">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="chat-contact-info">
                <div class="contact-avatar">
                    <div class="group-avatar"><i class="fas fa-users"></i></div>
                </div>
                <div class="contact-details">
                    <div class="contact-name">${groupName}</div>
                    <div class="contact-status">${members.length} participants</div>
                </div>
            </div>
            <div class="group-actions">
                <button class="edit-group-btn" onclick="editGroup('${groupName}', ${JSON.stringify(members).replace(/"/g, '&quot;')})">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        `;

        // Re-attach back button event listener
        chatHeader.querySelector('.back-button').addEventListener('click', function() {
            chatConversation.style.display = 'none';
            chatUsersList.style.display = 'block';
        });
        
        // Show the members list in the conversation
        const chatMessages = document.querySelector('.chat-messages');
        chatMessages.innerHTML = `
            <div class="group-info-message">
                <div class="group-members-header">
                    <i class="fas fa-users"></i>
                    <span>Group Members</span>
                </div>
                <div class="group-members-list">
                    ${members.map(member => `
                        <div class="group-member">
                            <div class="member-avatar">
                                <img src="assets/default-avatar.png" alt="${member.name}">
                            </div>
                            <div class="member-name">${member.name}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        chatUsersList.style.display = 'none';
        chatConversation.style.display = 'flex';
    });

    chatUsersList.insertBefore(groupItem, chatUsersList.firstChild);

    Swal.fire({
        icon: 'success',
        title: 'Group Created!',
        text: `${groupName} has been created with ${members.length} participants`,
        timer: 2000,
        showConfirmButton: false
    });
}

// Add this new function to handle group editing
function editGroup(groupName, members) {
    Swal.fire({
        title: 'Edit Group',
        html: `
            <div class="group-edit-form">
                <div class="group-name-input">
                    <i class="fas fa-users"></i>
                    <input type="text" id="editGroupName" class="swal2-input" value="${groupName}" placeholder="Group name">
                </div>
                <div class="group-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="editMemberSearch" class="swal2-input" placeholder="Search for participants">
                </div>
                <div class="selected-members">
                    ${members.map(member => `
                        <div class="selected-member" data-user-id="${member.id}">
                            <img src="assets/default-avatar.png" alt="${member.name}">
                            <span>${member.name}</span>
                            <i class="fas fa-times remove-member"></i>
                        </div>
                    `).join('')}
                </div>
                <div class="member-list">
                    ${generateUserList()}
                </div>
                <div class="group-actions-footer">
                    <button class="delete-group-btn" onclick="deleteGroup()">
                        <i class="fas fa-trash"></i> Delete Group
                    </button>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        confirmButtonColor: '#008069',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'group-edit-popup'
        },
        didOpen: () => {
            // Initialize member selection and search functionality
            initializeGroupEditFunctionality(members);
        },
        preConfirm: () => {
            const newGroupName = document.getElementById('editGroupName').value.trim();
            const updatedMembers = Array.from(document.querySelectorAll('.selected-member')).map(member => ({
                id: member.dataset.userId,
                name: member.querySelector('span').textContent
            }));

            if (!newGroupName) {
                Swal.showValidationMessage('Please enter a group name');
                return false;
            }

            if (updatedMembers.length < 2) {
                Swal.showValidationMessage('Group must have at least 2 members');
                return false;
            }

            return { groupName: newGroupName, members: updatedMembers };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateGroup(result.value.groupName, result.value.members);
        }
    });
}

// Function to initialize group edit functionality
function initializeGroupEditFunctionality(currentMembers) {
    const searchInput = document.getElementById('editMemberSearch');
    const memberList = document.querySelector('.member-list');
    const selectedMembersContainer = document.querySelector('.selected-members');

    // Handle member removal
    selectedMembersContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-member')) {
            const memberChip = e.target.closest('.selected-member');
            if (memberChip) {
                memberChip.remove();
            }
        }
    });

    // Handle member search
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const members = memberList.querySelectorAll('.member-item');
            
            members.forEach(member => {
                const name = member.querySelector('.member-name').textContent.toLowerCase();
                member.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }
}

// Function to update group
function updateGroup(newGroupName, updatedMembers) {
    // Update group name and members in the UI
    const contactName = document.querySelector('.contact-name');
    const contactStatus = document.querySelector('.contact-status');
    const membersList = document.querySelector('.group-members-list');

    contactName.textContent = newGroupName;
    contactStatus.textContent = `${updatedMembers.length} participants`;

    membersList.innerHTML = updatedMembers.map(member => `
        <div class="group-member">
            <div class="member-avatar">
                <img src="assets/default-avatar.png" alt="${member.name}">
            </div>
            <div class="member-name">${member.name}</div>
        </div>
    `).join('');

    // Show success message
    Swal.fire({
        icon: 'success',
        title: 'Group Updated!',
        text: 'Group information has been updated successfully',
        timer: 2000,
        showConfirmButton: false
    });
}

// Function to delete group
function deleteGroup() {
    Swal.fire({
        title: 'Delete Group?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Close the group chat and return to chat list
            const chatUsersList = document.querySelector('.chat-users-list');
            const chatConversation = document.querySelector('.chat-conversation');
            
            chatConversation.style.display = 'none';
            chatUsersList.style.display = 'block';

            // Remove the group from the chat list
            const groupName = document.querySelector('.contact-name').textContent;
            const groupItem = Array.from(chatUsersList.querySelectorAll('.chat-user-item'))
                .find(item => item.querySelector('.user-name').textContent === groupName);
            
            if (groupItem) {
                groupItem.remove();
            }

            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'The group has been deleted',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

let currentChatUser = null;

function fetchMessages(userId) {
    console.log('Fetching messages for user ID:', userId);

    if (!userId) {
        console.error('No user ID provided for fetching messages');
        return;
    }

    const chatMessages = document.querySelector('.chat-messages');
    if (!chatMessages) {
        console.error('Chat messages container not found');
        return;
    }

    // Show loading state
    chatMessages.innerHTML = '<div class="loading-messages">Loading messages...</div>';

    // Add timestamp to prevent caching
    const timestamp = new Date().getTime();
    const url = `fetch_messages.php?user_id=${userId}&t=${timestamp}`;
    
    console.log('Fetching messages from:', url);
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Messages data received:', data);
            if (data && data.success) {
                displayMessages(data.messages);
            } else {
                console.error('Failed to fetch messages:', data ? data.error : 'No data received');
                chatMessages.innerHTML = '<div class="no-messages">No messages yet. Start a conversation!</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching messages:', error);
            chatMessages.innerHTML = `<div class="error-messages">Failed to load messages: ${error.message}</div>`;
        });
}

function displayMessages(messages) {
    const messagesContainer = document.querySelector('.chat-messages');
    messagesContainer.innerHTML = '';
    
    if (!messages || messages.length === 0) {
        messagesContainer.innerHTML = '<div class="no-messages">No messages yet. Start a conversation!</div>';
        return;
    }
    
    // Add all message IDs to the processed set
    messages.forEach(message => {
        processedMessageIds.add(message.id);
    });
    
    messages.forEach(message => {
        const isOwn = message.sender_id === parseInt(document.body.dataset.userId);
        const messageElement = createMessageElement(message, isOwn);
        messagesContainer.appendChild(messageElement);
    });
    
    // Scroll to bottom
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function createMessageElement(message, isOwn) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isOwn ? 'sent' : 'received'}`;
    messageDiv.dataset.messageId = message.id;
    
    let messageContent = `
        ${!isOwn ? `
            <div class="message-sender">
                <img src="${message.sender_avatar || 'assets/default-avatar.png'}" alt="" class="sender-avatar">
            </div>
        ` : ''}
        <div class="message-content">
            <div class="message-text">${message.message}</div>
            <div class="message-info">
                <span class="message-time">${formatMessageTime(message.sent_at)}</span>
                <div class="message-actions">
                    <button class="reaction-button" onclick="showReactionPicker(this, '${message.id}')">
                        <i class="far fa-smile"></i>
                    </button>
                    <button class="forward-message" onclick="forwardMessage('${message.id}', '${message.message}')">
                        <i class="fas fa-share"></i>
                    </button>
                    ${isOwn ? `
                        <button class="delete-message" onclick="deleteMessage('${message.id}', this.closest('.message'))">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            <div class="message-reactions" data-message-id="${message.id}"></div>
        </div>
    `;
    
    messageDiv.innerHTML = messageContent;
    
    // Add hover effect for message actions
    messageDiv.addEventListener('mouseenter', () => {
        messageDiv.querySelector('.message-actions').style.opacity = '1';
    });
    
    messageDiv.addEventListener('mouseleave', () => {
        messageDiv.querySelector('.message-actions').style.opacity = '0';
    });

    // Load existing reactions
    loadMessageReactions(message.id);
    
    return messageDiv;
}

function sendMessage() {
    if (!currentChatUser) {
        console.error('No chat user selected');
        return;
    }
    
    const messageInput = document.querySelector('.message-input');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    // Clear input immediately for better UX
    messageInput.value = '';
    
    // Create a temporary message element
    const tempMessage = createMessageElement({
        id: 'temp_' + Date.now(),
        sender_id: parseInt(document.body.dataset.userId),
        sender_name: 'You',
        message: message,
        sent_at: new Date().toISOString()
    }, true);
    
    // Add temporary message to chat
    const chatMessages = document.querySelector('.chat-messages');
    chatMessages.appendChild(tempMessage);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Send message to server
    fetch('send_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            receiver_id: currentChatUser.id,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Replace temporary message with actual message
            tempMessage.remove();
            const messageElement = createMessageElement(data.message, true);
            chatMessages.appendChild(messageElement);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            // Show error and remove temporary message
            console.error('Failed to send message:', data.error);
            tempMessage.classList.add('error');
            tempMessage.querySelector('.message-text').innerHTML += 
                '<br><small class="error-text">Failed to send. Please try again.</small>';
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        tempMessage.classList.add('error');
        tempMessage.querySelector('.message-text').innerHTML += 
            '<br><small class="error-text">Failed to send. Please try again.</small>';
    });
}

function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Add event listeners
document.querySelector('.send-button').addEventListener('click', sendMessage);
document.querySelector('.message-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});

// Function to start checking for new messages
function startMessageChecking() {
    console.log('Starting real-time message checking');
    // Clear any existing interval
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
    }
    
    // Check immediately
    checkNewMessages();
    
    // Then check every MESSAGE_CHECK_FREQUENCY milliseconds
    messageCheckInterval = setInterval(checkNewMessages, MESSAGE_CHECK_FREQUENCY);
}

// Function to stop checking for new messages
function stopMessageChecking() {
    console.log('Stopping message checking');
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
        messageCheckInterval = null;
    }
}

// Function to check for new messages
function checkNewMessages() {
    if (!lastCheckTime) return;

    // Get the highest message ID we've processed
    let lastMessageId = 0;
    processedMessageIds.forEach(id => {
        const numId = parseInt(id);
        if (!isNaN(numId) && numId > lastMessageId) {
            lastMessageId = numId;
        }
    });

    fetch(`check_new_messages.php?last_check=${encodeURIComponent(lastCheckTime)}&last_message_id=${lastMessageId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update last check time
                lastCheckTime = data.timestamp;
                console.log('Checked for new messages at:', lastCheckTime);

                // Filter out already processed messages
                const newMessages = data.new_messages ? data.new_messages.filter(msg => !processedMessageIds.has(msg.id)) : [];
                
                if (newMessages.length > 0) {
                    console.log('New messages received:', newMessages.length);
                    
                    // Add new message IDs to processed set
                    newMessages.forEach(msg => processedMessageIds.add(msg.id));
                    
                    // Handle the new messages
                    handleNewMessages(newMessages);
                }

                // Update unread counts
                if (data.unread_counts) {
                    updateUnreadCounts(data.unread_counts);
                    updateTotalUnreadCount();
                }
            } else {
                console.error('Error checking messages:', data.error);
            }
        })
        .catch(error => console.error('Error checking messages:', error));
}

// Function to handle new messages
function handleNewMessages(newMessages) {
    if (!newMessages || !newMessages.length) return;

    const chatMessages = document.querySelector('.chat-messages');
    const isAtBottom = chatMessages && 
        (chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100);

    newMessages.forEach(message => {
        // Skip if this message has already been processed
        if (document.querySelector(`.message[data-message-id="${message.id}"]`)) {
            console.log('Skipping already displayed message:', message.id);
            return;
        }
        
        // If chat is open with this sender, add message to chat
        if (currentChatUser && message.sender_id === parseInt(currentChatUser.id)) {
            console.log('Adding new message to current chat:', message);
            const messageElement = createMessageElement(message, false);
            if (chatMessages) {
                chatMessages.appendChild(messageElement);
                
                // Play notification sound
                playMessageSound();
                
                // Mark message as read
                markMessageAsRead(message.id);
            }
        } else {
            // Update unread count and show notification
            updateUserUnreadCount(message.sender_id);
            showMessageNotification(message);
        }
    });

    // If was scrolled to bottom, keep it at bottom
    if (isAtBottom && chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Update total unread count
    updateTotalUnreadCount();
}

// Function to update unread counts in user list
function updateUnreadCounts(unreadCounts) {
    document.querySelectorAll('.chat-user-item').forEach(item => {
        const userId = item.dataset.userId;
        const unreadCount = unreadCounts[userId] || 0;
        const unreadBadge = item.querySelector('.unread-count');
        
        if (unreadCount > 0) {
            if (!unreadBadge) {
                const badge = document.createElement('div');
                badge.className = 'unread-count';
                badge.textContent = unreadCount;
                item.querySelector('.message-metadata').appendChild(badge);
            } else {
                unreadBadge.textContent = unreadCount;
            }
        } else if (unreadBadge) {
            unreadBadge.remove();
        }
    });
}

// Function to show message notification
function showMessageNotification(message) {
    // Check if browser supports notifications
    if (!("Notification" in window)) return;

    // Check if permission is granted
    if (Notification.permission === "granted") {
        createNotification(message);
    }
    // If not denied, request permission
    else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                createNotification(message);
            }
        });
    }
}

// Function to create notification
function createNotification(message) {
    const notification = new Notification("New Message", {
        body: `${message.sender_name}: ${message.message}`,
        icon: message.sender_avatar
    });

    notification.onclick = function() {
        window.focus();
        // Find and click the user's chat item to open the conversation
        const userItem = Array.from(document.querySelectorAll('.chat-user-item'))
            .find(item => item.dataset.userId === message.sender_id.toString());
        if (userItem) userItem.click();
    };
}

// Function to mark message as read
function markMessageAsRead(messageId) {
    fetch('mark_message_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message_id: messageId })
    }).catch(error => console.error('Error marking message as read:', error));
}

// Update the toggleChatInterface function
function toggleChatInterface() {
    const chatInterface = document.querySelector('.floating-chat-interface');
    chatInterface.classList.toggle('active');
    
    if (chatInterface.classList.contains('active')) {
        document.querySelector('.chat-notification-bubble').textContent = '0';
        document.querySelector('.chat-users-list').style.display = 'block';
        document.querySelector('.chat-conversation').style.display = 'none';
        fetchChatUsers();
        startMessageChecking(); // Start real-time message checking
    } else {
        stopMessageChecking(); // Stop checking when chat is closed
    }
}

// Function to handle file selection
function handleFileSelect(event) {
    const files = event.target.files;
    if (!files.length) return;

    const file = files[0];
    uploadFile(file);
}

// Function to upload file
function uploadFile(file) {
    // Show loading state
    const loadingMessage = createLoadingMessage();
    document.querySelector('.chat-messages').appendChild(loadingMessage);

    const formData = new FormData();
    formData.append('file', file);

    fetch('chat_file_upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading message
        loadingMessage.remove();

        if (data.success) {
            // Send message with file
            sendMessageWithFile(data.file);
        } else {
            console.error('File upload failed:', data.error);
            showErrorMessage('Failed to upload file: ' + data.error);
        }
    })
    .catch(error => {
        loadingMessage.remove();
        console.error('Error uploading file:', error);
        showErrorMessage('Error uploading file');
    });
}

// Function to create loading message element
function createLoadingMessage() {
    const div = document.createElement('div');
    div.className = 'message sent loading';
    div.innerHTML = `
        <div class="message-content">
            <div class="message-text">
                <i class="fas fa-spinner fa-spin"></i> Uploading file...
            </div>
        </div>
    `;
    return div;
}

// Function to send message with file
function sendMessageWithFile(file) {
    if (!currentChatUser) return;

    fetch('send_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            receiver_id: currentChatUser.id,
            message: `Sent a file: ${file.name}`,
            file_id: file.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageElement = createFileMessageElement(data.message, file, true);
            document.querySelector('.chat-messages').appendChild(messageElement);
            scrollToBottom();
        } else {
            console.error('Failed to send file message:', data.error);
        }
    })
    .catch(error => {
        console.error('Error sending file message:', error);
    });
}

// Function to create file message element
function createFileMessageElement(message, file, isOwn) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isOwn ? 'sent' : 'received'}`;

    let filePreview = '';
    const isImage = file.type.startsWith('image/');

    if (isImage) {
        filePreview = `
            <div class="file-preview">
                <img src="${file.url}" alt="${file.name}" class="message-image">
            </div>
        `;
    } else {
        filePreview = `
            <div class="file-attachment">
                <i class="fas ${getFileIcon(file.type)}"></i>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <a href="${file.url}" download="${file.name}" class="file-download">
                    <i class="fas fa-download"></i>
                </a>
            </div>
        `;
    }

    messageDiv.innerHTML = `
        ${!isOwn ? `<div class="message-sender">${message.sender_name}</div>` : ''}
        <div class="message-content">
            ${filePreview}
            <div class="message-time">${formatMessageTime(message.sent_at)}</div>
        </div>
    `;

    return messageDiv;
}

// Helper function to get file icon
function getFileIcon(fileType) {
    if (fileType.startsWith('image/')) return 'fa-image';
    if (fileType.startsWith('video/')) return 'fa-video';
    if (fileType.startsWith('audio/')) return 'fa-music';
    if (fileType.includes('pdf')) return 'fa-file-pdf';
    if (fileType.includes('word')) return 'fa-file-word';
    if (fileType.includes('excel')) return 'fa-file-excel';
    return 'fa-file';
}

// Helper function to format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Add event listener for file input
document.querySelector('#file-input').addEventListener('change', handleFileSelect);

// Function to initialize emoji picker
function initializeEmojiPicker() {
    // Fetch emojis
    fetch('fetch_emojis.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                emojis = data.emojis;
                populateEmojiGrid(currentEmojiCategory);
            }
        })
        .catch(error => console.error('Error fetching emojis:', error));

    // Add event listeners for emoji category buttons
    document.querySelectorAll('.category-button').forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            switchEmojiCategory(category);
        });
    });
}

// Function to switch emoji category
function switchEmojiCategory(category) {
    // Update active category button
    document.querySelectorAll('.category-button').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.category === category) {
            btn.classList.add('active');
        }
    });

    currentEmojiCategory = category;
    populateEmojiGrid(category);
}

// Function to populate emoji grid
function populateEmojiGrid(category) {
    const grid = document.querySelector(`.emoji-grid[data-category="${category}"]`);
    if (!grid || !emojis[category]) return;

    grid.innerHTML = '';
    emojis[category].forEach(emoji => {
        const emojiSpan = document.createElement('span');
        emojiSpan.className = 'emoji-item';
        emojiSpan.textContent = emoji;
        emojiSpan.addEventListener('click', () => insertEmoji(emoji));
        grid.appendChild(emojiSpan);
    });

    // Show current category grid, hide others
    document.querySelectorAll('.emoji-grid').forEach(g => {
        g.style.display = g.dataset.category === category ? 'grid' : 'none';
    });
}

// Function to toggle emoji picker
function toggleEmojiPicker() {
    const emojiPicker = document.querySelector('.emoji-picker');
    const isVisible = emojiPicker.style.display === 'flex';
    
    emojiPicker.style.display = isVisible ? 'none' : 'flex';
    
    if (!isVisible) {
        // Initialize emoji picker if it's being shown
        populateEmojiGrid(currentEmojiCategory);
    }
}

// Initialize emoji picker when chat interface is loaded
document.addEventListener('DOMContentLoaded', initializeEmojiPicker);

// Function to handle search
function searchMessages(query) {
    if (!currentChatUser || !query.trim()) {
        clearSearch();
        return;
    }

    fetch(`search_messages.php?query=${encodeURIComponent(query)}&chat_with=${currentChatUser.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                searchResults = data.messages;
                currentSearchIndex = -1;
                updateSearchResults();
                if (searchResults.length > 0) {
                    navigateSearch('next');
                }
            } else {
                console.error('Search failed:', data.error);
            }
        })
        .catch(error => console.error('Error searching messages:', error));
}

// Function to update search results count
function updateSearchResults() {
    const resultsCount = document.querySelector('.results-count');
    if (searchResults.length > 0) {
        resultsCount.textContent = `${currentSearchIndex + 1}/${searchResults.length}`;
    } else {
        resultsCount.textContent = '0/0';
    }
}

// Function to navigate search results
function navigateSearch(direction) {
    if (searchResults.length === 0) return;

    if (direction === 'next') {
        currentSearchIndex = (currentSearchIndex + 1) % searchResults.length;
    } else {
        currentSearchIndex = (currentSearchIndex - 1 + searchResults.length) % searchResults.length;
    }

    highlightSearchResult(searchResults[currentSearchIndex]);
    updateSearchResults();
}

// Function to highlight search result
function highlightSearchResult(message) {
    // Remove previous highlights
    document.querySelectorAll('.message-text.highlighted').forEach(el => {
        el.classList.remove('highlighted');
    });

    // Find and highlight the message
    const messages = document.querySelectorAll('.message');
    messages.forEach(messageEl => {
        const textEl = messageEl.querySelector('.message-text');
        if (textEl && textEl.textContent.includes(message.message)) {
            textEl.classList.add('highlighted');
            messageEl.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    });
}

// Function to clear search
function clearSearch() {
    searchResults = [];
    currentSearchIndex = -1;
    updateSearchResults();
    
    // Remove all highlights
    document.querySelectorAll('.message-text.highlighted').forEach(el => {
        el.classList.remove('highlighted');
    });
}

// Add these event listeners to your existing code
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.conversation-search-input');
    const searchUpBtn = document.querySelector('.search-up-btn');
    const searchDownBtn = document.querySelector('.search-down-btn');
    const searchClearBtn = document.querySelector('.search-clear');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            searchMessages(this.value);
        }, 300));
    }

    if (searchUpBtn) {
        searchUpBtn.addEventListener('click', () => navigateSearch('prev'));
    }

    if (searchDownBtn) {
        searchDownBtn.addEventListener('click', () => navigateSearch('next'));
    }

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearSearch();
        });
    }
});

// Helper function to debounce search input
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Function to handle message deletion
function deleteMessage(messageId, messageElement) {
    Swal.fire({
        title: 'Delete Message?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove message from UI with fade out animation
                    messageElement.style.opacity = '0';
                    setTimeout(() => {
                        messageElement.remove();
                    }, 300);
                } else {
                    showErrorMessage(data.error || 'Failed to delete message');
                }
            })
            .catch(error => {
                console.error('Error deleting message:', error);
                showErrorMessage('Error deleting message');
            });
        }
    });
}

// Add error message function if not already present
function showErrorMessage(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        timer: 3000,
        showConfirmButton: false
    });
}

// Add this function to handle message forwarding
function forwardMessage(messageId, messageText) {
    Swal.fire({
        title: 'Forward Message',
        html: `
            <div class="forward-message-form">
                <div class="forward-message-preview">
                    <div class="preview-label">Message:</div>
                    <div class="preview-text">${messageText}</div>
                </div>
                <div class="forward-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="forwardSearch" class="swal2-input" placeholder="Search users">
                </div>
                <div class="forward-users-list">
                    ${generateForwardUsersList()}
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Forward',
        confirmButtonColor: '#008069',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'forward-message-popup'
        },
        didOpen: () => {
            initializeForwardFunctionality();
        },
        preConfirm: () => {
            const selectedUsers = Array.from(document.querySelectorAll('.forward-user-item.selected'))
                .map(item => item.dataset.userId);

            if (selectedUsers.length === 0) {
                Swal.showValidationMessage('Please select at least one user');
                return false;
            }

            return { users: selectedUsers };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            sendForwardedMessage(messageId, result.value.users);
        }
    });
}

// Function to generate users list for forwarding
function generateForwardUsersList() {
    return chatUsers.map(user => `
        <div class="forward-user-item" data-user-id="${user.id}">
            <div class="user-avatar">
                <img src="${user.avatar}" alt="${user.username}">
                <span class="status-indicator ${user.status}"></span>
            </div>
            <div class="user-info">
                <div class="user-name">${user.username}</div>
            </div>
            <div class="user-select">
                <i class="fas fa-check"></i>
            </div>
        </div>
    `).join('');
}

// Function to initialize forward functionality
function initializeForwardFunctionality() {
    const searchInput = document.getElementById('forwardSearch');
    const usersList = document.querySelector('.forward-users-list');

    // Handle search
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const users = usersList.querySelectorAll('.forward-user-item');
            
            users.forEach(user => {
                const name = user.querySelector('.user-name').textContent.toLowerCase();
                user.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }

    // Handle user selection
    if (usersList) {
        usersList.querySelectorAll('.forward-user-item').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.toggle('selected');
            });
        });
    }
}

// Function to send forwarded message
function sendForwardedMessage(messageId, userIds) {
    fetch('forward_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message_id: messageId,
            user_ids: userIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Message Forwarded!',
                text: `Message forwarded to ${userIds.length} user${userIds.length > 1 ? 's' : ''}`,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            showErrorMessage(data.error || 'Failed to forward message');
        }
    })
    .catch(error => {
        console.error('Error forwarding message:', error);
        showErrorMessage('Error forwarding message');
    });
}

// Add these new functions for reaction handling
function showReactionPicker(button, messageId) {
    const reactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    
    const picker = document.createElement('div');
    picker.className = 'reaction-picker';
    picker.innerHTML = reactions.map(emoji => 
        `<span class="reaction-emoji" data-emoji="${emoji}">${emoji}</span>`
    ).join('');
    
    // Position the picker above the button
    const buttonRect = button.getBoundingClientRect();
    picker.style.bottom = `${window.innerHeight - buttonRect.top + 10}px`;
    picker.style.left = `${buttonRect.left}px`;
    
    // Add click handlers
    picker.addEventListener('click', (e) => {
        const emoji = e.target.dataset.emoji;
        if (emoji) {
            saveReaction(messageId, emoji);
            picker.remove();
        }
    });
    
    // Remove existing pickers
    document.querySelectorAll('.reaction-picker').forEach(p => p.remove());
    
    // Add to document
    document.body.appendChild(picker);
    
    // Close picker when clicking outside
    document.addEventListener('click', function closeReactionPicker(e) {
        if (!picker.contains(e.target) && !button.contains(e.target)) {
            picker.remove();
            document.removeEventListener('click', closeReactionPicker);
        }
    });
}

function saveReaction(messageId, reaction) {
    fetch('save_reaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message_id: messageId,
            reaction: reaction
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMessageReactions(messageId);
        } else {
            showErrorMessage(data.error || 'Failed to save reaction');
        }
    })
    .catch(error => {
        console.error('Error saving reaction:', error);
        showErrorMessage('Error saving reaction');
    });
}

function loadMessageReactions(messageId) {
    fetch(`get_reactions.php?message_id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateReactionsDisplay(messageId, data.reactions);
            }
        })
        .catch(error => console.error('Error loading reactions:', error));
}

function updateReactionsDisplay(messageId, reactions) {
    const container = document.querySelector(`.message-reactions[data-message-id="${messageId}"]`);
    if (!container) return;

    // Group reactions by emoji
    const grouped = reactions.reduce((acc, r) => {
        if (!acc[r.reaction]) {
            acc[r.reaction] = [];
        }
        acc[r.reaction].push(r);
        return acc;
    }, {});

    container.innerHTML = Object.entries(grouped).map(([emoji, users]) => `
        <div class="reaction-group" title="${users.map(u => u.username).join(', ')}">
            <span class="reaction-emoji">${emoji}</span>
            <span class="reaction-count">${users.length}</span>
        </div>
    `).join('');
}

// Add back button functionality
document.addEventListener('DOMContentLoaded', function() {
    const backButton = document.querySelector('.back-button');
    if (backButton) {
        backButton.addEventListener('click', function() {
            const chatUsersList = document.querySelector('.chat-users-list');
            const chatConversation = document.querySelector('.chat-conversation');
            
            if (chatUsersList) chatUsersList.style.display = 'block';
            if (chatConversation) chatConversation.style.display = 'none';
            
            // Reset current chat user
            currentChatUser = null;
        });
    }
});

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Get required elements
    const sendButton = document.querySelector('.send-button');
    const messageInput = document.querySelector('.message-input');
    const fileInput = document.querySelector('#file-input');
    const chatBubbleButton = document.querySelector('.chat-bubble-button');

    // Add event listeners only if elements exist
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }

    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
    
    if (chatBubbleButton) {
        chatBubbleButton.addEventListener('click', toggleChatInterface);
    }

    // Initialize chat interface
    const chatInterface = document.querySelector('.floating-chat-interface');
    if (chatInterface) {
        fetchChatUsers(); // Initial fetch of users
        
        // If chat interface is already active, start message checking
        if (chatInterface.classList.contains('active')) {
            startMessageChecking();
        }
    }

    // Initialize other event listeners
    initializeChatEventListeners();
});

// Function to initialize all chat-related event listeners
function initializeChatEventListeners() {
    const searchToggleBtn = document.querySelector('.search-toggle-btn');
    const searchBar = document.querySelector('.chat-conversation-search');
    const searchInput = document.querySelector('.conversation-search-input');
    const searchBackBtn = document.querySelector('.search-back');
    const searchClearBtn = document.querySelector('.search-clear');
    const searchUpBtn = document.querySelector('.search-up-btn');
    const searchDownBtn = document.querySelector('.search-down-btn');

    // Search toggle functionality
    if (searchToggleBtn) {
        searchToggleBtn.addEventListener('click', function() {
            if (searchBar) {
                searchBar.style.display = searchBar.style.display === 'flex' ? 'none' : 'flex';
                if (searchBar.style.display === 'flex' && searchInput) {
                    searchInput.focus();
                    // Clear previous search when opening
                    searchInput.value = '';
                    clearSearch();
                }
            }
        });
    }

    // Search back button
    if (searchBackBtn) {
        searchBackBtn.addEventListener('click', function() {
            if (searchBar) {
                searchBar.style.display = 'none';
                clearSearch();
            }
        });
    }

    // Search input
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            searchMessages(this.value);
        }, 300));
    }

    // Clear search
    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                clearSearch();
                searchInput.focus();
            }
        });
    }

    // Search navigation
    if (searchUpBtn) {
        searchUpBtn.addEventListener('click', () => navigateSearch('prev'));
    }
    if (searchDownBtn) {
        searchDownBtn.addEventListener('click', () => navigateSearch('next'));
    }
}

// Function to handle attachment button click
function handleAttachmentClick() {
    // Create attachment modal if it doesn't exist
    let attachmentModal = document.querySelector('.attachment-modal');
    
    if (!attachmentModal) {
        attachmentModal = createAttachmentModal();
        document.body.appendChild(attachmentModal);
    }
    
    attachmentModal.classList.add('show');
}

// Function to create attachment modal
function createAttachmentModal() {
    const modal = document.createElement('div');
    modal.className = 'attachment-modal';
    // Your existing attachment modal HTML and event listeners
    // ...
    return modal;
}

function openChat(user) {
    if (!user || !user.id) {
        console.error('Invalid user object provided to openChat:', user);
        return;
    }

    currentChatUser = user;
    
    // Update UI elements
    const chatUsersList = document.querySelector('.chat-users-list');
    const chatConversation = document.querySelector('.chat-conversation');
    const contactName = document.querySelector('.contact-name');
    const contactAvatar = document.querySelector('.contact-avatar img');
    const contactStatus = document.querySelector('.contact-status');
    
    // Update conversation header
    if (contactName) contactName.textContent = user.username;
    if (contactAvatar) contactAvatar.src = user.avatar || 'assets/default-avatar.png';
    if (contactStatus) contactStatus.textContent = user.status || 'offline';
    
    // Show conversation, hide users list
    if (chatUsersList) chatUsersList.style.display = 'none';
    if (chatConversation) chatConversation.style.display = 'flex';
    
    // Load messages
    fetchMessages(user.id);

    // Reset unread count for this user
    resetUnreadCount(user.id);
}

// Function to update total unread count
function updateTotalUnreadCount() {
    const unreadCounts = document.querySelectorAll('.unread-count');
    let totalUnread = 0;
    
    unreadCounts.forEach(badge => {
        totalUnread += parseInt(badge.textContent) || 0;
    });
    
    const notificationBubble = document.querySelector('.chat-notification-bubble');
    if (notificationBubble) {
        notificationBubble.textContent = totalUnread;
        
        // Show/hide notification bubble
        if (totalUnread > 0) {
            notificationBubble.style.display = 'flex';
        } else {
            notificationBubble.style.display = 'none';
        }
    }
}

// Function to update unread count for a specific user
function updateUserUnreadCount(userId) {
    const userItem = document.querySelector(`.chat-user-item[data-user-id="${userId}"]`);
    if (!userItem) return;

    let unreadBadge = userItem.querySelector('.unread-count');
    if (!unreadBadge) {
        unreadBadge = document.createElement('div');
        unreadBadge.className = 'unread-count';
        userItem.querySelector('.message-metadata').appendChild(unreadBadge);
    }

    const currentCount = parseInt(unreadBadge.textContent) || 0;
    unreadBadge.textContent = currentCount + 1;
}

// Add this CSS to your stylesheet
const style = document.createElement('style');
style.textContent = `
    .message-highlight {
        background-color: #ffeb3b;
        padding: 2px;
        border-radius: 2px;
    }
    .current-search-result {
        border: 2px solid #2196f3;
    }
    .chat-conversation-search {
        background: #fff;
        padding: 8px;
        display: none;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #e0e0e0;
    }
    .results-count {
        margin-left: auto;
        color: #666;
        font-size: 12px;
    }
`;
document.head.appendChild(style);

document.querySelector('.search-toggle-btn')?.addEventListener('click', function() {
    console.log('Search toggle clicked');
    const searchBar = document.querySelector('.chat-conversation-search');
    console.log('Search bar found:', !!searchBar);
    console.log('Current display:', searchBar?.style.display);
});

// Add this CSS to your stylesheet
const searchStyles = document.createElement('style');
searchStyles.textContent = `
    .message-highlight {
        background-color: #fff3cd;
        padding: 2px;
        border-radius: 2px;
        color: #856404;
    }
    mark.message-highlight {
        background-color: #fff3cd;
        color: #856404;
        padding: 2px;
        border-radius: 2px;
        font-weight: bold;
    }
    .current-search-result {
        background-color: rgba(33, 150, 243, 0.1);
        border: 2px solid #2196f3;
        border-radius: 8px;
    }
    .chat-conversation-search {
        background: #fff;
        padding: 8px;
        display: none;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .results-count {
        margin-left: auto;
        color: #666;
        font-size: 12px;
        padding: 2px 8px;
        background: #f5f5f5;
        border-radius: 12px;
    }
`;
document.head.appendChild(searchStyles);

// Update the search input event listener
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.conversation-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const query = this.value.trim();
            console.log('Searching for:', query); // Debug log
            if (query) {
                performSearch(query);
            } else {
                clearSearch();
            }
        }, 300));
    }
});

// Add a function to play notification sound
function playMessageSound() {
    const audio = new Audio('assets/sounds/message.mp3'); // Add a message sound file
    audio.play().catch(error => console.log('Error playing sound:', error));
}

// Add function to reset unread count
function resetUnreadCount(userId) {
    const userItem = document.querySelector(`.chat-user-item[data-user-id="${userId}"]`);
    if (userItem) {
        const unreadBadge = userItem.querySelector('.unread-count');
        if (unreadBadge) {
            unreadBadge.remove();
        }
    }
}