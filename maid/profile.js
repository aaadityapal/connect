// Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function () {
    console.log('Profile page loaded');

    // Profile Picture Upload Functionality
    const profileAvatar = document.getElementById('profileAvatar');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const avatarImage = document.getElementById('avatarImage');

    // Click on avatar to trigger file input
    if (profileAvatar && profilePictureInput) {
        profileAvatar.addEventListener('click', function () {
            profilePictureInput.click();
        });

        // Handle file selection
        profilePictureInput.addEventListener('change', function (e) {
            const file = e.target.files[0];

            if (!file) {
                return;
            }

            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showNotification('Please select a valid image file (JPG, PNG, GIF, or WebP)', 'error');
                return;
            }

            // Validate file size (5MB max)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                showNotification('File size must be less than 5MB', 'error');
                return;
            }

            // Preview image before upload
            const reader = new FileReader();
            reader.onload = function (event) {
                // Show preview and confirm upload
                customConfirm('Upload this image as your profile picture?', (confirmed) => {
                    if (confirmed) {
                        uploadProfilePicture(file, event.target.result);
                    }
                }, 'Confirm Upload');
            };
            reader.readAsDataURL(file);
        });
    }

    // Upload profile picture function
    function uploadProfilePicture(file, previewUrl) {
        const formData = new FormData();
        formData.append('profile_picture', file);

        // Show loading indicator
        showUploadProgress();

        // Send AJAX request
        fetch('api_upload_profile_picture.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                hideUploadProgress();

                if (data.success) {
                    // Update avatar image
                    updateAvatarImage(previewUrl);
                    showNotification('Profile picture updated successfully!', 'success');

                    // Reload page after 1.5 seconds to show updated image
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to upload profile picture', 'error');
                }
            })
            .catch(error => {
                hideUploadProgress();
                console.error('Upload error:', error);
                showNotification('An error occurred while uploading', 'error');
            });
    }

    // Show upload progress indicator
    function showUploadProgress() {
        const avatarContainer = document.querySelector('.avatar-container');
        if (!avatarContainer) return;

        const progressDiv = document.createElement('div');
        progressDiv.className = 'upload-progress';
        progressDiv.innerHTML = '<div class="spinner"></div>';
        avatarContainer.appendChild(progressDiv);
    }

    // Hide upload progress indicator
    function hideUploadProgress() {
        const progressDiv = document.querySelector('.upload-progress');
        if (progressDiv) {
            progressDiv.remove();
        }
    }

    // Update avatar image with preview
    function updateAvatarImage(imageUrl) {
        if (avatarImage) {
            avatarImage.src = imageUrl;
        } else {
            // If no image exists, convert initials avatar to image avatar
            const avatar = document.getElementById('profileAvatar');
            if (avatar) {
                avatar.className = 'avatar avatar-image';
                avatar.innerHTML = `
                    <img src="${imageUrl}" alt="Profile Picture" id="avatarImage">
                    <div class="avatar-overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        <span>Change</span>
                    </div>
                `;
            }
        }
    }

    // Show notification
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotification = document.querySelector('.profile-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `profile-notification ${type}`;
        notification.textContent = message;

        // Add to page
        document.body.appendChild(notification);

        // Add styles if not already added
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                .profile-notification {
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-size: 0.9rem;
                    font-weight: 500;
                    z-index: 1000;
                    animation: slideDown 0.3s ease;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                .profile-notification.success {
                    background: #10b981;
                    color: white;
                }
                .profile-notification.error {
                    background: #ef4444;
                    color: white;
                }
                .profile-notification.info {
                    background: #3b82f6;
                    color: white;
                }
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateX(-50%) translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(-50%) translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Handle logout button click with confirmation
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Show confirmation dialog
            customConfirm('Are you sure you want to logout?', (confirmed) => {
                if (confirmed) {
                    window.location.href = '../logout.php';
                }
            }, 'Confirm Logout');
        });
    }

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add ripple effect to menu items
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function (e) {
            // Skip ripple for logout button (already has confirmation)
            if (this.id === 'logoutBtn') return;

            // Create ripple element
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');

            // Add ripple styles dynamically
            if (!document.getElementById('ripple-styles')) {
                const style = document.createElement('style');
                style.id = 'ripple-styles';
                style.textContent = `
                    .menu-item {
                        position: relative;
                        overflow: hidden;
                    }
                    .ripple {
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.3);
                        transform: scale(0);
                        animation: ripple-animation 0.6s ease-out;
                        pointer-events: none;
                    }
                    @keyframes ripple-animation {
                        to {
                            transform: scale(4);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }

            this.appendChild(ripple);

            // Remove ripple after animation
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Log profile view (optional analytics)
    console.log('Profile viewed at:', new Date().toLocaleString());

    // HR Documents download functionality
    const downloadButtons = document.querySelectorAll('.download-btn');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const docId = this.getAttribute('data-doc-id');
            const filename = this.getAttribute('data-filename');
            const originalName = this.getAttribute('data-original-name');

            // Add loading state
            const originalHTML = this.innerHTML;
            this.innerHTML = '<div style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>';
            this.disabled = true;

            // Download the file
            const downloadUrl = `../uploads/hr_documents/${filename}`;

            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = originalName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Reset button after a short delay
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.disabled = false;
                showNotification('Document downloaded successfully', 'success');
            }, 800);
        });
    });
});

// Handle visibility change (when user switches tabs)
document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
        console.log('Profile page hidden');
    } else {
        console.log('Profile page visible');
    }
});
