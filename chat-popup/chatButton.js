class ChatButton {
    static init(targetElement) {
        // Load required CSS
        this.loadStyles();
        
        // Create and append chat button HTML
        const chatHTML = `
            <div class="chat-icon" id="chatIcon">
                <i class="fab fa-whatsapp fa-lg"></i>
            </div>
            
            <div class="chat-popup" id="chatPopup">
                <div class="chat-header">
                    <div class="back-btn" id="backBtn">
                        <i class="fas fa-arrow-left"></i>
                    </div>
                    <div id="chatTitle">Chats</div>
                    <div class="close-btn" id="closeBtn">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
                
                <div class="search-bar">
                    <input type="text" placeholder="Search or start new chat" id="searchInput">
                </div>
                
                <div class="chat-contacts" id="chatContacts"></div>
                
                <div class="chat-body" id="chatBody"></div>
                
                <div class="chat-footer">
                    <input type="text" id="messageInput" placeholder="Type a message">
                    <button id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;

        // Append chat HTML to target element or body
        const container = targetElement || document.body;
        container.insertAdjacentHTML('beforeend', chatHTML);

        // Initialize chat functionality
        this.initChat();
    }

    static loadStyles() {
        // Add Font Awesome if not already present
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement('link');
            fontAwesome.rel = 'stylesheet';
            fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(fontAwesome);
        }

        // Add chat styles if not already present
        if (!document.querySelector('link[href*="popup.css"]')) {
            const chatStyles = document.createElement('link');
            chatStyles.rel = 'stylesheet';
            chatStyles.href = 'chat-popup/popup.css';  // Updated path
            document.head.appendChild(chatStyles);
        }
    }

    static initChat() {
        // Load the chat functionality
        const chatScript = document.createElement('script');
        chatScript.src = 'chat-popup/popup.js';  // Updated path
        document.body.appendChild(chatScript);
    }
} 