// Chat Interface Functions
function toggleChatInterface() {
    const chatInterface = document.querySelector('.floating-chat-interface');
    chatInterface.classList.toggle('active');
    
    // Reset to user list view when closing/opening
    if (chatInterface.classList.contains('active')) {
        document.querySelector('.chat-notification-bubble').textContent = '0';
        document.querySelector('.chat-users-list').style.display = 'block';
        document.querySelector('.chat-conversation').style.display = 'none';
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
        // Save current user
        currentUser = {
            id: userId,
            name: userName,
            avatar: userAvatar
        };
        
        // Update header with user info
        contactName.textContent = userName;
        contactAvatar.src = userAvatar;
        
        // Initialize conversation if needed
        if (!conversations[userId]) {
            conversations[userId] = [];
        }
        
        // Display messages for this user
        displayMessages(userId);
        
        // Show chat conversation, hide user list
        chatUsersList.style.display = 'none';
        chatConversation.style.display = 'flex';
        
        // Focus input field
        messageInput.focus();
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
            const messageElement = createMessageElement(message);
            chatMessages.appendChild(messageElement);
        });
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Function to create a message element
    function createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-${message.type}`;
        
        messageDiv.innerHTML = `
            <span class="message-text">${message.text}</span>
            <span class="message-time">${message.time}</span>
        `;
        
        return messageDiv;
    }
    
    // Function to send a message
    function sendMessage() {
        if (!currentUser) return;
        
        const text = messageInput.value.trim();
        if (!text) return;
        
        // Clear input
        messageInput.value = '';
        
        // Get current time
        const now = new Date();
        const timeString = formatTime(now);
        
        // Add message to conversation
        const message = {
            text: text,
            type: 'sent',
            time: timeString
        };
        
        conversations[currentUser.id].push(message);
        
        // Display sent message
        const messageElement = createMessageElement(message);
        chatMessages.appendChild(messageElement);
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Simulate reply after 1-2 seconds
        setTimeout(() => {
            simulateReply(currentUser.id);
        }, 1000 + Math.random() * 1000);
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
            const messageElement = createMessageElement(message);
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
            emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'flex' : 'none';
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
            insertAtCursor(messageInput, emoji);
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
    function insertAtCursor(input, text) {
        const startPos = input.selectionStart;
        const endPos = input.selectionEnd;
        const before = input.value.substring(0, startPos);
        const after = input.value.substring(endPos, input.value.length);
        
        input.value = before + text + after;
        input.selectionStart = input.selectionEnd = startPos + text.length;
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
        clearSearch();
        
        const chatMessages = document.querySelector('.chat-messages');
        const messageElements = chatMessages.querySelectorAll('.message-sent, .message-received');
        
        // Find matches
        messageElements.forEach(element => {
            const messageText = element.querySelector('.message-text');
            if (!messageText) return;
            
            const text = messageText.textContent;
            if (text.toLowerCase().includes(query.toLowerCase())) {
                searchResults.push(element);
                
                // Highlight text
                const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
                messageText.innerHTML = text.replace(regex, '<span class="message-highlight">$1</span>');
            }
        });
        
        // Update results count
        updateResultsCount();
        
        // Navigate to first result if available
        if (searchResults.length > 0) {
            currentResult = 0;
            navigateToResult(currentResult);
        }
    }
    
    // Function to clear search highlights and reset
    function clearSearch() {
        const chatMessages = document.querySelector('.chat-messages');
        const messageElements = chatMessages.querySelectorAll('.message-sent, .message-received');
        
        messageElements.forEach(element => {
            const messageText = element.querySelector('.message-text');
            if (!messageText) return;
            
            // Remove highlights
            const text = messageText.textContent;
            messageText.innerHTML = text;
        });
        
        searchResults = [];
        currentResult = -1;
        updateResultsCount();
    }
    
    // Function to update results count display
    function updateResultsCount() {
        if (searchResults.length > 0) {
            resultsCount.textContent = `${currentResult + 1}/${searchResults.length}`;
        } else {
            resultsCount.textContent = '0/0';
        }
    }
    
    // Function to navigate to a specific result
    function navigateToResult(index) {
        if (index >= 0 && index < searchResults.length) {
            // Scroll to the message
            const element = searchResults[index];
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            // Update results count
            updateResultsCount();
        }
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