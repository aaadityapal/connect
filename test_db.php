<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        /* Chat icon styles */
        #chat-icon {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #25D366; /* WhatsApp green */
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s, transform 0.3s;
            font-size: 24px; /* Larger icon */
        }
        #chat-icon:hover {
            background-color: #128C7E; /* Darker green on hover */
            transform: scale(1.1);
        }
        /* Chatbox styles */
        #chatbox {
            display: none;
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 320px;
            border: 1px solid #ccc;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        /* Chatbox header styles */
        .chatbox-header {
            background-color: #25D366; /* WhatsApp green */
            color: white;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .close-btn {
            cursor: pointer;
            font-weight: bold;
            color: white;
        }
        /* User list styles */
        .user {
            display: flex;
            align-items: center;
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .user:hover {
            background-color: #f0f0f0;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            background-color: #ccc; /* Placeholder for user avatar */
        }
        /* Chat content styles */
        .chat-content {
            padding: 10px;
            max-height: 300px;
            overflow-y: auto;
            background-color: #f9f9f9;
        }
        .message {
            margin: 5px 0;
            padding: 10px;
            border-radius: 10px;
            max-width: 70%;
            position: relative;
        }
        .message.user {
            background-color: #dcf8c6; /* Light green for user messages */
            align-self: flex-end;
            margin-left: auto;
        }
        .message.other {
            background-color: #ffffff; /* White for other messages */
            align-self: flex-start;
        }
    </style>
</head>
<body>
    <h1>Hello World</h1>
    <div id="chat-icon">💬</div>
    <div id="chatbox">
        <div class="chatbox-header">
            <span id="chat-user-name">Chat User</span>
            <span class="close-btn" onclick="toggleChatbox()">✖</span>
        </div>
        <div class="chat-content" id="chat-content">
            <!-- Chat messages will be displayed here -->
        </div>
        <div class="user" onclick="openChat('User 1')">
            <div class="user-avatar"></div>
            <div>User 1</div>
        </div>
        <div class="user" onclick="openChat('User 2')">
            <div class="user-avatar"></div>
            <div>User 2</div>
        </div>
        <div class="user" onclick="openChat('User 3')">
            <div class="user-avatar"></div>
            <div>User 3</div>
        </div>
        <!-- Add more users as needed -->
    </div>

    <script>
        // Toggle chatbox visibility
        document.getElementById('chat-icon').onclick = function() {
            toggleChatbox();
        };

        function toggleChatbox() {
            var chatbox = document.getElementById('chatbox');
            chatbox.style.display = chatbox.style.display === 'none' || chatbox.style.display === '' ? 'block' : 'none';
        }

        // Function to open chat for a specific user
        function openChat(user) {
            document.getElementById('chat-user-name').innerText = user;
            var chatContent = document.getElementById('chat-content');
            chatContent.innerHTML = ''; // Clear previous chat content

            // Simulate chat messages
            chatContent.innerHTML += '<div class="message other"><strong>' + user + ':</strong> Hello!</div>';
            chatContent.innerHTML += '<div class="message user"><strong>You:</strong> Hi there!</div>';
            chatContent.innerHTML += '<div class="message other"><strong>' + user + ':</strong> How are you?</div>';
            chatContent.innerHTML += '<div class="message user"><strong>You:</strong> I am good, thanks!</div>';
            // Here you can implement the logic to load actual chat messages
        }
    </script>
</body>
</html>