<?php
session_start();
require_once 'config.php';

// Fetch discussions with project info
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.title,
        d.project_id,
        d.created_at,
        p.project_name,
        u.username as created_by,
        (SELECT COUNT(*) FROM discussion_replies WHERE discussion_id = d.id) as replies_count
    FROM discussions d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN projects p ON d.project_id = p.id
    ORDER BY d.created_at DESC
");

$stmt->execute();
$discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get projects for dropdown
$stmt = $pdo->query("SELECT id, project_name FROM projects WHERE status != 'completed' ORDER BY project_name");
$projects = $stmt->fetchAll();

// Add this function if it doesn't exist
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Add this after your existing query
// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$params = [];

if (!empty($search)) {
    $searchCondition = "AND (d.title LIKE ? OR p.project_name LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Modified query to include search
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.title,
        d.project_id,
        d.created_at,
        p.project_name,
        u.username as created_by,
        (SELECT COUNT(*) FROM discussion_replies WHERE discussion_id = d.id) as replies_count
    FROM discussions d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN projects p ON d.project_id = p.id
    WHERE 1=1 $searchCondition
    ORDER BY d.created_at DESC
");

$stmt->execute($params);
$discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Discussions - ArchitectsHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .discussions-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .discussions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            width: 100%;
            flex-wrap: wrap;
            gap: 15px;
        }

        .add-discussion-btn {
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-discussion-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
        }

        .discussion-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .discussion-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .discussion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .project-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .discussion-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px 0;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            margin: 20px auto;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
        }

        .close-modal {
            position: fixed;
            top: 10px;
            right: calc(50% - 240px);
            background: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 3;
        }

        .discussion-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 100px;
            max-height: 200px;
        }

        .replies-count {
            background: #f5f5f5;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .view-discussion-btn {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-discussion-btn:hover {
            text-decoration: underline;
        }

        .no-discussions {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }

        .no-discussions p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .search-container {
            margin-bottom: 30px;
            width: 100%;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .search-input-wrapper {
            display: flex;
            flex: 1;
            position: relative;
            max-width: 100%;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px;
            padding-right: 50px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #c82333;
        }

        .clear-search {
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .clear-search:hover {
            color: #dc3545;
        }

        .clear-search::before {
            content: '×';
            font-size: 1.2rem;
        }

        /* Add responsive styles */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input-wrapper {
                max-width: 100%;
            }

            .clear-search {
                text-align: center;
            }
        }

        /* Add this if you want to show a "no results" message */
        .no-results {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }

        .no-results p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .file-upload-container {
            margin-top: 10px;
        }

        .file-upload-box {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-box:hover {
            border-color: #dc3545;
        }

        .file-upload-box i {
            font-size: 2rem;
            color: #666;
            margin-bottom: 10px;
        }

        .file-upload-box p {
            margin: 0;
            color: #666;
        }

        .file-types {
            font-size: 0.8rem;
            color: #999;
            display: block;
            margin-top: 5px;
        }

        .file-input {
            display: none;
        }

        .file-list {
            margin-top: 15px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .file-item i {
            margin-right: 8px;
            color: #666;
        }

        .file-item .remove-file {
            margin-left: auto;
            color: #dc3545;
            cursor: pointer;
        }

        .attachment-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
        }

        .attachment-container {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background: white;
            border-radius: 4px;
            margin-bottom: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .attachment-item i {
            margin-right: 8px;
            color: #666;
        }

        .attachment-item a {
            color: #333;
            text-decoration: none;
        }

        .attachment-item a:hover {
            color: #dc3545;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .file-item .file-info {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-item .file-name {
            font-size: 0.9rem;
            color: #333;
        }

        .file-item .file-size {
            font-size: 0.8rem;
            color: #666;
        }

        .file-item .file-status {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-item .remove-file {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }

        .file-item .file-progress {
            width: 100px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .file-item .progress-bar {
            height: 100%;
            background: #28a745;
            width: 0;
            transition: width 0.3s ease;
        }

        .upload-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            display: none;
        }

        .upload-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .upload-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .submit-btn-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .file-count {
            color: #666;
            font-size: 0.9rem;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-right: 40px; /* Make space for close button */
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            margin: 50px auto;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            z-index: 1;
        }

        .add-discussion-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-discussion-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
        }

        .add-discussion-btn:disabled {
            background: #e9ecef;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .discussion-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Remove the previous submit-btn-wrapper styles since we moved the button */
        .submit-btn-wrapper {
            display: none;
        }

        /* Add responsive styles for the modal header */
        @media (max-width: 480px) {
            .modal-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .add-discussion-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Add smooth scrollbar for the modal content */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Ensure the header stays at the top */
        .modal-header {
            position: sticky;
            top: 0;
            background: white;
            padding: 0 0 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            z-index: 2;
        }

        /* Update form group spacing */
        .form-group {
            margin-bottom: 15px;
        }

        /* Make textarea shorter by default */
        .form-group textarea {
            min-height: 100px;
            max-height: 200px;
        }

        /* Ensure close button stays visible */
        .close-modal {
            position: fixed;
            top: 10px;
            right: calc(50% - 240px);
            background: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 3;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                margin: 10px auto;
                padding: 20px;
            }

            .close-modal {
                right: 20px;
            }
        }

        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .submit-btn-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .add-discussion-btn {
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-discussion-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
        }

        .add-discussion-btn:disabled {
            background: #e9ecef;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .header-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .header-left h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 6px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .back-btn:hover {
            background: #e9ecef;
            color: #dc3545;
            transform: translateX(-2px);
        }

        /* Update responsive styles */
        @media (max-width: 768px) {
            .discussions-header {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            
            .header-left {
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="discussions-container">
        <div class="discussions-header">
            <div class="header-left">
                <h1><i class="fas fa-comments"></i> Project Discussions</h1>
                <?php
                // Check user role and set appropriate dashboard link
                $dashboardLink = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' 
                    ? 'admin_dashboard.php' 
                    : 'employee_dashboard.php';
                ?>
                <a href="<?php echo $dashboardLink; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <button class="add-discussion-btn" onclick="openDiscussionModal()">
                <i class="fas fa-plus"></i> Add Discussion
            </button>
        </div>

        <div class="search-container">
            <form action="" method="GET" class="search-form">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search discussions by project title..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="search-input"
                    >
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                    <a href="discussions.php" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="discussions-list">
            <?php if (empty($discussions)): ?>
                <div class="no-results">
                    <?php if (!empty($search)): ?>
                        <p>No discussions found for "<?php echo htmlspecialchars($search); ?>". Try a different search term.</p>
                    <?php else: ?>
                        <p>No discussions found. Be the first to start a discussion!</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($discussions as $discussion): ?>
                    <div class="discussion-card">
                        <div class="discussion-header">
                            <div>
                                <h2><?php echo htmlspecialchars($discussion['title']); ?></h2>
                                <span class="project-badge">
                                    <i class="fas fa-project-diagram"></i>
                                    <?php echo htmlspecialchars($discussion['project_name']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="discussion-meta">
                            <span>
                                <i class="fas fa-user"></i> 
                                Started by <?php echo htmlspecialchars($discussion['created_by']); ?>
                            </span>
                            <span>
                                <i class="fas fa-clock"></i> 
                                <?php echo timeAgo($discussion['created_at']); ?>
                            </span>
                            <span class="replies-count">
                                <i class="fas fa-reply"></i> 
                                <?php echo $discussion['replies_count']; ?> replies
                            </span>
                            <a href="discussion_detail.php?id=<?php echo $discussion['id']; ?>" 
                               class="view-discussion-btn">
                                View Discussion <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Discussion Modal -->
    <div id="discussionModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDiscussionModal()">&times;</span>
            
            <!-- Simplified header -->
            <div class="modal-header">
                <h2>Start New Discussion</h2>
            </div>

            <form class="discussion-form" id="discussionForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="project">Select Project</label>
                    <select id="project" name="project_id" required>
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Discussion Title</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="message">Initial Message</label>
                    <textarea id="message" name="message" required></textarea>
                </div>

                <div class="form-group">
                    <label for="files">Attach Files (Optional)</label>
                    <div class="file-upload-container">
                        <input type="file" id="files" name="files[]" multiple class="file-input">
                        <div class="file-upload-box">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Drag & drop files here or click to browse</p>
                            <span class="file-types">Supported files: Documents, Images, PDFs, etc.</span>
                        </div>
                    </div>
                    <div id="fileList" class="file-list"></div>
                    <div id="uploadStatus" class="upload-status"></div>
                </div>

                <!-- Add submit button back to bottom -->
                <div class="submit-btn-wrapper">
                    <button type="submit" class="add-discussion-btn">
                        <i class="fas fa-plus"></i> Create Discussion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDiscussionModal() {
            document.getElementById('discussionModal').style.display = 'block';
        }

        function closeDiscussionModal() {
            document.getElementById('discussionModal').style.display = 'none';
            document.getElementById('discussionForm').reset();
            document.getElementById('fileList').innerHTML = '';
            document.getElementById('uploadStatus').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('discussionModal');
            if (event.target == modal) {
                closeDiscussionModal();
            }
        }

        // Form submission
        document.getElementById('discussionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;

            fetch('create_discussion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const uploadStatus = document.getElementById('uploadStatus');
                    uploadStatus.className = 'upload-status success';
                    uploadStatus.textContent = 'Discussion created successfully!';
                    uploadStatus.style.display = 'block';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Error creating discussion');
                }
            })
            .catch(error => {
                const uploadStatus = document.getElementById('uploadStatus');
                uploadStatus.className = 'upload-status error';
                uploadStatus.textContent = error.message;
                uploadStatus.style.display = 'block';
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        let uploadedFiles = [];

        document.querySelector('.file-upload-box').addEventListener('click', () => {
            document.querySelector('.file-input').click();
        });

        document.querySelector('.file-input').addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        // Drag and drop handlers
        document.querySelector('.file-upload-box').addEventListener('dragover', (e) => {
            e.preventDefault();
            e.currentTarget.classList.add('dragover');
        });

        document.querySelector('.file-upload-box').addEventListener('dragleave', (e) => {
            e.currentTarget.classList.remove('dragover');
        });

        document.querySelector('.file-upload-box').addEventListener('drop', (e) => {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            const fileList = document.getElementById('fileList');
            const uploadStatus = document.getElementById('uploadStatus');
            uploadStatus.className = 'upload-status';
            uploadStatus.style.display = 'none';

            [...files].forEach(file => {
                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    uploadStatus.className = 'upload-status error';
                    uploadStatus.textContent = `${file.name} is too large. Maximum file size is 10MB.`;
                    uploadStatus.style.display = 'block';
                    return;
                }

                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                
                const icon = document.createElement('i');
                icon.className = getFileIcon(file.type);
                
                const fileDetails = document.createElement('div');
                fileDetails.innerHTML = `
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                `;
                
                const fileStatus = document.createElement('div');
                fileStatus.className = 'file-status';
                
                const progress = document.createElement('div');
                progress.className = 'file-progress';
                progress.innerHTML = '<div class="progress-bar"></div>';
                
                const removeBtn = document.createElement('i');
                removeBtn.className = 'fas fa-times remove-file';
                removeBtn.onclick = () => {
                    fileItem.remove();
                    updateFileCount();
                };
                
                fileInfo.appendChild(icon);
                fileInfo.appendChild(fileDetails);
                fileStatus.appendChild(progress);
                fileStatus.appendChild(removeBtn);
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(fileStatus);
                fileList.appendChild(fileItem);

                // Simulate upload progress
                simulateUploadProgress(progress.querySelector('.progress-bar'));
                uploadedFiles.push(file);
                updateFileCount();
            });
        }

        function simulateUploadProgress(progressBar) {
            let progress = 0;
            const interval = setInterval(() => {
                progress += 5;
                progressBar.style.width = `${progress}%`;
                if (progress >= 100) {
                    clearInterval(interval);
                    progressBar.parentElement.innerHTML = '<i class="fas fa-check" style="color: #28a745;"></i>';
                    document.getElementById('uploadStatus').className = 'upload-status success';
                    document.getElementById('uploadStatus').textContent = 'Files uploaded successfully!';
                    document.getElementById('uploadStatus').style.display = 'block';
                }
            }, 50);
        }

        function updateFileCount() {
            const fileCount = document.querySelectorAll('.file-item').length;
            const countDisplay = document.querySelector('.file-count');
            if (countDisplay) {
                countDisplay.textContent = `${fileCount} file${fileCount !== 1 ? 's' : ''} selected`;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return 'fas fa-image';
            if (fileType.includes('pdf')) return 'fas fa-file-pdf';
            if (fileType.includes('word')) return 'fas fa-file-word';
            if (fileType.includes('excel')) return 'fas fa-file-excel';
            if (fileType.includes('zip') || fileType.includes('rar')) return 'fas fa-file-archive';
            if (fileType.includes('video')) return 'fas fa-file-video';
            return 'fas fa-file';
        }
    </script>
</body>
</html>
