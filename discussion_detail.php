<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get discussion details and messages
$discussion_id = $_GET['id'] ?? 0;

// Fetch discussion details
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        p.project_name,
        u.username as created_by
    FROM discussions d
    JOIN projects p ON d.project_id = p.id
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$discussion_id]);
$discussion = $stmt->fetch();

// Fetch all replies
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        u.username
    FROM discussion_replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.discussion_id = ?
    ORDER BY r.created_at ASC
");
$stmt->execute([$discussion_id]);
$replies = $stmt->fetchAll();

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    // For very recent messages (less than 1 hour), show exact time
    if ($diff->h < 1) {
        if ($diff->i < 1) {
            return 'Just now';
        }
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    
    // For today's messages, show "Today at HH:MM"
    if ($now->format('Y-m-d') === $ago->format('Y-m-d')) {
        return 'Today at ' . $ago->format('H:i');
    }
    
    // For yesterday's messages
    $yesterday = clone $now;
    $yesterday->modify('-1 day');
    if ($yesterday->format('Y-m-d') === $ago->format('Y-m-d')) {
        return 'Yesterday at ' . $ago->format('H:i');
    }
    
    // For messages within the last week, show day name and time
    if ($diff->days < 7) {
        return $ago->format('l') . ' at ' . $ago->format('H:i');
    }
    
    // For older messages, show full date and time
    return $ago->format('M j, Y') . ' at ' . $ago->format('H:i');
}

// Add this function at the top with other PHP functions
function getFileIcon($fileType) {
    if (strpos($fileType, 'image/') === 0) return 'fas fa-image';
    if (strpos($fileType, 'pdf') !== false) return 'fas fa-file-pdf';
    if (strpos($fileType, 'word') !== false) return 'fas fa-file-word';
    if (strpos($fileType, 'excel') !== false) return 'fas fa-file-excel';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) return 'fas fa-file-archive';
    if (strpos($fileType, 'video/') === 0) return 'fas fa-file-video';
    return 'fas fa-file';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion: <?php echo htmlspecialchars($discussion['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #ec4899;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --hover-bg: #f1f5f9;
        }

        body {
            background: var(--bg-color);
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text-primary);
            line-height: 1.7;
        }

        .discussion-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .discussion-header {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            border: 1px solid var(--border-color);
        }

        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-link:hover {
            background: var(--hover-bg);
            color: var(--primary);
            transform: translateX(-4px);
        }

        .discussion-title-section h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 1.5rem 0 1rem;
            line-height: 1.3;
        }

        .project-badge {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }

        .timeline-container {
            position: relative;
            padding: 2rem 0;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 2rem;
        }

        .timeline-marker {
            flex: 0 0 60px;
            position: relative;
        }

        .timeline-marker::before {
            content: '';
            width: 2px;
            background: linear-gradient(to bottom, var(--primary) 0%, var(--secondary) 100%);
            position: absolute;
            top: 0;
            bottom: -2rem;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0.3;
        }

        .timeline-marker::after {
            content: '';
            width: 12px;
            height: 12px;
            background: var(--primary);
            border-radius: 50%;
            position: absolute;
            top: 25px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
        }

        .timeline-content {
            flex: 1;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-left: 1.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .timeline-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }

        .timestamp-container {
            background: var(--hover-bg);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .timestamp-container:hover {
            background: var(--primary);
            color: white;
        }

        .timestamp-container:hover .timestamp,
        .timestamp-container:hover .exact-time {
            color: white;
        }

        .exact-time {
            font-size: 0.75rem;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .timestamp-container:hover .exact-time {
            opacity: 1;
            transform: translateY(0);
            display: block;
        }

        /* Reply Form Styling */
        .reply-box {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid var(--border-color);
        }

        .reply-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            resize: vertical;
            min-height: 120px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .reply-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .reply-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
            white-space: nowrap;
            margin-left: auto;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }

        .file-label {
            background: var(--hover-bg);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .file-label:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .attachment-container {
            background: var(--hover-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .attachment-item {
            background: var(--card-bg);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .attachment-item:hover {
            transform: translateX(4px);
            background: var(--hover-bg);
        }

        @media (max-width: 768px) {
            .discussion-container {
                padding: 1rem;
            }

            .timeline-marker {
                flex: 0 0 40px;
            }

            .timeline-content {
                margin-left: 1rem;
            }
        }

        /* File Upload and Attachment Styling */
        .reply-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            gap: 1.5rem;
        }

        .attachment-upload {
            position: relative;
            flex: 1;
            max-width: 70%;
        }

        .file-input {
            display: none;
        }

        .file-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8fafc;
            color: #475569;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px dashed #e2e8f0;
            width: 100%;
        }

        .file-label:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .file-label i {
            font-size: 1.1rem;
            color: #64748b;
        }

        /* Selected Files Display */
        .selected-files {
            margin-top: 1rem;
        }

        .selected-file-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .selected-file-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }

        .selected-file-item i {
            font-size: 1.1rem;
            color: #64748b;
        }

        .selected-file-item span {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #475569;
        }

        .remove-file {
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            color: #ef4444;
            transition: all 0.2s ease;
        }

        .remove-file:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        /* File Type Icons */
        .file-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #e2e8f0;
        }

        /* File size and type info */
        .file-info {
            font-size: 0.75rem;
            color: #64748b;
            margin-left: auto;
            padding-left: 1rem;
        }

        /* Progress bar for upload */
        .upload-progress {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .reply-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .attachment-upload {
                max-width: 100%;
                width: 100%;
            }

            .reply-btn {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="discussion-container">
        <div class="discussion-header">
            <div class="discussion-breadcrumb">
                <a href="discussions.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Discussions
                </a>
            </div>
            <div class="discussion-title-section">
                <h1><?php echo htmlspecialchars($discussion['title']); ?></h1>
                <div class="discussion-meta">
                    <span class="project-badge">
                        <i class="fas fa-project-diagram"></i>
                        <?php echo htmlspecialchars($discussion['project_name']); ?>
                    </span>
                    <span class="created-by">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($discussion['created_by']); ?>
                    </span>
                    <span class="created-at">
                        <i class="far fa-clock"></i>
                        <?php echo timeAgo($discussion['created_at']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="timeline-container">
            <?php foreach ($replies as $reply): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <div class="message-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="user-details">
                                <span class="username"><?php echo htmlspecialchars($reply['username']); ?></span>
                                <div class="timestamp-container">
                                    <span class="timestamp"><?php echo timeAgo($reply['created_at']); ?></span>
                                    <span class="exact-time" title="Exact time">
                                        <?php 
                                            $dateTime = new DateTime($reply['created_at']);
                                            echo $dateTime->format('M j, Y H:i:s'); 
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                        
                        <?php
                        // Fetch attachments for this reply
                        $stmt = $pdo->prepare("SELECT * FROM discussion_attachments WHERE reply_id = ?");
                        $stmt->execute([$reply['id']]);
                        $attachments = $stmt->fetchAll();
                        
                        if (!empty($attachments)): ?>
                        <div class="attachment-container">
                            <h4><i class="fas fa-paperclip"></i> Attachments</h4>
                            <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <i class="<?php echo getFileIcon($attachment['file_type']); ?>"></i>
                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                   target="_blank"
                                   download>
                                    <?php echo htmlspecialchars($attachment['file_name']); ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="reply-section">
            <form class="reply-form" id="replyForm" enctype="multipart/form-data">
                <div class="reply-box">
                    <textarea 
                        class="reply-input" 
                        name="message" 
                        placeholder="Add your reply..."
                        required></textarea>
                    
                    <div class="reply-actions">
                        <div class="attachment-upload">
                            <input type="file" id="replyFiles" name="files[]" multiple class="file-input">
                            <label for="replyFiles" class="file-label">
                                <i class="fas fa-paperclip"></i>
                                <span>Drop files here or click to attach</span>
                            </label>
                        </div>
                        <button type="submit" class="reply-btn">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </div>
                <div id="selectedFiles" class="selected-files"></div>
                <input type="hidden" name="discussion_id" value="<?php echo $discussion_id; ?>">
            </form>
        </div>
    </div>

    <script>
        document.getElementById('replyFiles').addEventListener('change', function(e) {
            const selectedFiles = document.getElementById('selectedFiles');
            selectedFiles.innerHTML = '';
            
            [...this.files].forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'selected-file-item';
                
                // File icon
                const fileIcon = document.createElement('div');
                fileIcon.className = 'file-icon';
                const icon = document.createElement('i');
                icon.className = getFileIconClass(file.type);
                fileIcon.appendChild(icon);
                
                // File name
                const fileName = document.createElement('span');
                fileName.textContent = file.name;
                
                // File info (size and type)
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                fileInfo.textContent = `${formatFileSize(file.size)} • ${file.type.split('/')[1].toUpperCase()}`;
                
                // Remove button
                const removeBtn = document.createElement('i');
                removeBtn.className = 'fas fa-times remove-file';
                removeBtn.onclick = () => {
                    fileItem.remove();
                    // Add animation before removal
                    fileItem.style.opacity = '0';
                    fileItem.style.transform = 'translateX(20px)';
                    setTimeout(() => fileItem.remove(), 300);
                };
                
                // Progress bar (optional, for upload visualization)
                const progressContainer = document.createElement('div');
                progressContainer.className = 'upload-progress';
                const progressBar = document.createElement('div');
                progressBar.className = 'progress-bar';
                progressBar.style.width = '0%';
                progressContainer.appendChild(progressBar);
                
                fileItem.appendChild(fileIcon);
                fileItem.appendChild(fileName);
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(removeBtn);
                fileItem.appendChild(progressContainer);
                selectedFiles.appendChild(fileItem);
            });
        });

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Enhanced file icon selection
        function getFileIconClass(fileType) {
            const icons = {
                'image': 'fa-image',
                'pdf': 'fa-file-pdf',
                'word': 'fa-file-word',
                'excel': 'fa-file-excel',
                'powerpoint': 'fa-file-powerpoint',
                'video': 'fa-file-video',
                'audio': 'fa-file-audio',
                'archive': 'fa-file-archive',
                'code': 'fa-file-code',
                'text': 'fa-file-alt'
            };

            for (const [type, icon] of Object.entries(icons)) {
                if (fileType.includes(type)) {
                    return `fas ${icon}`;
                }
            }
            return 'fas fa-file';
        }

        // Update form submission to handle files
        document.getElementById('replyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;

            fetch('add_reply.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    throw new Error(data.message || 'Error adding reply');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
