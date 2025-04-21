<?php
// Start the session
session_start();

// Set a test user ID in session if not already set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 21;
    $_SESSION['user_role'] = 'user';
    $_SESSION['username'] = 'Test User';
}

// Show the session values
echo "<pre>";
echo "SESSION:\n";
var_dump($_SESSION);
echo "</pre>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat User Test</title>
    <!-- Set user meta tags -->
    <meta name="user-id" content="<?php echo $_SESSION['user_id']; ?>">
    <meta name="user-name" content="<?php echo $_SESSION['username']; ?>">
    <meta name="user-role" content="<?php echo $_SESSION['user_role']; ?>">
    
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; }
        #results { margin-top: 20px; background: #f9f9f9; padding: 10px; }
    </style>
</head>
<body>
    <h1>Chat User Test</h1>
    
    <div class="test-section">
        <h2>Meta Tags</h2>
        <p>user-id: <span id="meta-user-id"></span></p>
        <p>user-name: <span id="meta-user-name"></span></p>
        <p>user-role: <span id="meta-user-role"></span></p>
    </div>
    
    <div class="test-section">
        <h2>Test Messages</h2>
        <div id="test-messages"></div>
    </div>
    
    <div id="results"></div>
    
    <!-- Include the stage-chat.js script -->
    <script src="assets/js/stage-chat.js"></script>
    
    <script>
        // Display meta tag values
        document.getElementById('meta-user-id').textContent = 
            document.querySelector('meta[name="user-id"]')?.getAttribute('content') || 'Not set';
        document.getElementById('meta-user-name').textContent = 
            document.querySelector('meta[name="user-name"]')?.getAttribute('content') || 'Not set';
        document.getElementById('meta-user-role').textContent = 
            document.querySelector('meta[name="user-role"]')?.getAttribute('content') || 'Not set';
        
        // Create test messages
        const testMessages = [
            { id: 1, user_id: 21, message: "This is a message from current user", timestamp: new Date(), user_name: "Test User" },
            { id: 2, user_id: 22, message: "This is a message from another user", timestamp: new Date(), user_name: "Other User" },
            { id: 3, user_id: "21", message: "This is a string-ID message", timestamp: new Date(), user_name: "String ID User" },
        ];
        
        // Create a container for test messages
        const messagesContainer = document.getElementById('test-messages');
        
        // Initialize the StageChat
        const chat = new StageChat();
        
        // Display current user ID from the chat object
        const results = document.getElementById('results');
        results.innerHTML = `
            <h2>StageChat Values</h2>
            <p>currentUserId: ${chat.currentUserId} (type: ${typeof chat.currentUserId})</p>
            <p>isAdmin: ${chat.isAdmin}</p>
        `;
        
        // Render test messages
        testMessages.forEach(message => {
            chat.renderMessage(messagesContainer, message);
        });
        
        // Add edit checkboxes
        chat.addCheckboxesToMessages();
    </script>
</body>
</html> 