document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const chatIcon = document.getElementById('chatIcon');
    const chatPopup = document.getElementById('chatPopup');
    const closeBtn = document.getElementById('closeBtn');
    const backBtn = document.getElementById('backBtn');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatBody = document.getElementById('chatBody');
    const chatContacts = document.getElementById('chatContacts');
    const chatTitle = document.getElementById('chatTitle');
    const searchInput = document.getElementById('searchInput');
    
    // Sample user data
    const users = [
        {
            id: 1,
            name: 'Sarah Johnson',
            avatar: 'https://randomuser.me/api/portraits/women/1.jpg',
            preview: 'Hi, how are you doing?',
            time: '10:30 AM',
            unread: 2
        },
        {
            id: 2,
            name: 'John Smith',
            avatar: 'https://randomuser.me/api/portraits/men/1.jpg',
            preview: 'Can we meet tomorrow?',
            time: '9:45 AM',
            unread: 0
        },
        {
            id: 3,
            name: 'Emily Wilson',
            avatar: 'https://randomuser.me/api/portraits/women/2.jpg',
            preview: 'Thanks for your help!',
            time: 'Yesterday',
            unread: 0
        },
        {
            id: 4,
            name: 'Michael Brown',
            avatar: 'https://randomuser.me/api/portraits/men/2.jpg',
            preview: 'I sent you the files',
            time: 'Yesterday',
            unread: 1
        },
        {
            id: 5,
            name: 'Jessica Taylor',
            avatar: 'https://randomuser.me/api/portraits/women/3.jpg',
            preview: 'Let me know when you are free',
            time: 'Tuesday',
            unread: 0
        }
    ];
    
    // Conversation history (keyed by user ID)
    const conversations = {};
    
    // Current selected user
    let currentUser = null;
    
    // Initialize contact list
    function initContactList() {
        chatContacts.innerHTML = '';
        
        users.forEach(user => {
            const contactElem = document.createElement('div');
            contactElem.className = 'contact-item';
            contactElem.setAttribute('data-id', user.id);
            
            const badgeHTML = user.unread ? `<div class="contact-badge">${user.unread}</div>` : '';
            
            contactElem.innerHTML = `
                <div class="contact-avatar" style="background-image: url('${user.avatar}')"></div>
                <div class="contact-info">
                    <div class="contact-name">${user.name}</div>
                    <div class="contact-preview">${user.preview}</div>
                </div>
                <div class="contact-meta">
                    <div class="contact-time">${user.time}</div>
                    ${badgeHTML}
                </div>
            `;
            
            contactElem.addEventListener('click', () => openChat(user));
            
            chatContacts.appendChild(contactElem);
        });
    }
    
    // Open chat with a specific user
    function openChat(user) {
        currentUser = user;
        
        // Update header with user info
        chatTitle.innerHTML = `
            <div class="user-info">
                <div class="user-avatar" style="background-image: url('${user.avatar}')"></div>
                <div class="user-details">
                    <h3>${user.name}</h3>
                    <div class="online-status">online</div>
                </div>
            </div>
        `;
        
        // Switch to chat view
        chatPopup.classList.add('in-chat');
        
        // Clear and load messages
        chatBody.innerHTML = '';
        
        // Check if we have conversation history
        if (!conversations[user.id]) {
            // Initialize conversation
            conversations[user.id] = [];
            
            // Add welcome message after a small delay
            setTimeout(() => {
                addMessage('Hello! 👋 How can I help you today?', 'received', user.id);
            }, 500);
        } else {
            // Load existing conversation
            conversations[user.id].forEach(msg => {
                const messageDiv = createMessageElement(msg.text, msg.type, msg.time);
                chatBody.appendChild(messageDiv);
            });
        }
        
        // Reset unread count
        const contactElem = document.querySelector(`.contact-item[data-id="${user.id}"]`);
        const badgeElem = contactElem.querySelector('.contact-badge');
        if (badgeElem) {
            badgeElem.remove();
        }
        
        // Update user data
        const userIndex = users.findIndex(u => u.id === user.id);
        if (userIndex !== -1) {
            users[userIndex].unread = 0;
        }
        
        // Focus on input
        setTimeout(() => messageInput.focus(), 300);
    }
    
    // Go back to contact list
    function backToContacts() {
        chatPopup.classList.remove('in-chat');
        chatTitle.textContent = 'Chats';
        currentUser = null;
    }
    
    // Make chat icon draggable
    let isDragging = false;
    let offsetX, offsetY;
    
    chatIcon.addEventListener('mousedown', function(e) {
        isDragging = true;
        offsetX = e.clientX - chatIcon.getBoundingClientRect().left;
        offsetY = e.clientY - chatIcon.getBoundingClientRect().top;
        chatIcon.style.cursor = 'grabbing';
    });
    
    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        
        const x = e.clientX - offsetX;
        const y = e.clientY - offsetY;
        
        // Keep the icon within the viewport
        const maxX = window.innerWidth - chatIcon.offsetWidth;
        const maxY = window.innerHeight - chatIcon.offsetHeight;
        
        chatIcon.style.left = Math.min(Math.max(0, x), maxX) + 'px';
        chatIcon.style.right = 'auto';
        chatIcon.style.top = Math.min(Math.max(0, y), maxY) + 'px';
        chatIcon.style.bottom = 'auto';
    });
    
    document.addEventListener('mouseup', function() {
        if (isDragging) {
            isDragging = false;
            chatIcon.style.cursor = 'pointer';
        }
    });
    
    // Toggle chat popup
    chatIcon.addEventListener('click', function(e) {
        if (!isDragging) {
            const isActive = chatPopup.classList.contains('active');
            
            if (isActive) {
                chatPopup.classList.remove('active');
            } else {
                chatPopup.classList.add('active');
                
                // Initialize contact list on first open
                if (chatContacts.children.length === 0) {
                    initContactList();
                }
            }
        }
    });
    
    // Back button functionality
    backBtn.addEventListener('click', backToContacts);
    
    // Close button functionality
    closeBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        chatPopup.classList.remove('active');
    });
    
    // Send message functionality
    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        document.querySelectorAll('.contact-item').forEach(item => {
            const name = item.querySelector('.contact-name').textContent.toLowerCase();
            const preview = item.querySelector('.contact-preview').textContent.toLowerCase();
            
            if (name.includes(query) || preview.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    function sendMessage() {
        if (!currentUser) return;
        
        const message = messageInput.value.trim();
        if (message) {
            addMessage(message, 'sent', currentUser.id);
            messageInput.value = '';
            
            // Update contact preview
            updateContactPreview(currentUser.id, message);
            
            // Simulate response with typing indicator
            showTypingIndicator();
            
            setTimeout(function() {
                removeTypingIndicator();
                
                // Choose from multiple responses for variety
                const responses = [
                    "Thanks for your message! I'll look into this for you.",
                    "I understand. Let me check and get back to you shortly.",
                    "Got it! I'm working on this now.",
                    "Thanks for reaching out! Our team will respond soon."
                ];
                
                const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                addMessage(randomResponse, 'received', currentUser.id);
                
                // Update contact preview
                updateContactPreview(currentUser.id, randomResponse, true);
            }, 1500);
        }
    }
    
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message received typing-indicator';
        typingDiv.innerHTML = `
            <div class="message-content">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        chatBody.appendChild(typingDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }
    
    function removeTypingIndicator() {
        const typingIndicator = document.querySelector('.typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    function createMessageElement(text, type, time) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        // Add checkmarks for sent messages (WhatsApp style)
        let checkmark = type === 'sent' ? '<span class="checkmark">✓✓</span>' : '';
        
        // Add avatar for received messages
        let avatarHTML = '';
        if (type === 'received' && currentUser) {
            avatarHTML = `<img class="message-avatar" src="${currentUser.avatar}" alt="">`;
        }
        
        messageDiv.innerHTML = `
            ${avatarHTML}
            <div class="message-wrapper">
                <div class="message-content">
                    <p>${text}</p>
                    <div class="message-meta">
                        <span class="message-time">${time}</span>
                        ${checkmark}
                    </div>
                </div>
            </div>
        `;
        
        return messageDiv;
    }
    
    function addMessage(text, type, userId) {
        const now = new Date();
        const time = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
        
        // Create message element
        const messageDiv = createMessageElement(text, type, time);
        chatBody.appendChild(messageDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
        
        // Save to conversation history
        if (!conversations[userId]) {
            conversations[userId] = [];
        }
        
        conversations[userId].push({
            text: text,
            type: type,
            time: time
        });
    }
    
    function updateContactPreview(userId, text, isReceived = false) {
        const contactElem = document.querySelector(`.contact-item[data-id="${userId}"]`);
        if (contactElem) {
            const previewElem = contactElem.querySelector('.contact-preview');
            const timeElem = contactElem.querySelector('.contact-time');
            
            // Update preview text
            previewElem.textContent = text;
            
            // Update time
            const now = new Date();
            timeElem.textContent = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
            
            // If it's a received message and we're not viewing this chat
            if (isReceived && currentUser && currentUser.id !== userId) {
                let badgeElem = contactElem.querySelector('.contact-badge');
                
                if (!badgeElem) {
                    badgeElem = document.createElement('div');
                    badgeElem.className = 'contact-badge';
                    contactElem.querySelector('.contact-meta').appendChild(badgeElem);
                    badgeElem.textContent = '1';
                } else {
                    badgeElem.textContent = parseInt(badgeElem.textContent) + 1;
                }
                
                // Update user data
                const userIndex = users.findIndex(u => u.id === userId);
                if (userIndex !== -1) {
                    users[userIndex].unread = parseInt(badgeElem.textContent);
                }
            }
            
            // Move this contact to the top of the list
            if (contactElem.previousElementSibling) {
                chatContacts.insertBefore(contactElem, chatContacts.firstChild);
            }
        }
    }
}); 