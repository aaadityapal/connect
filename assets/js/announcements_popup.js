class AnnouncementManager {
    constructor() {
        this.popup = document.getElementById('announcementPopup');
        this.isMinimized = false;
        this.initializeEventListeners();
        this.checkNewAnnouncements();
        this.setupExpandButtons();
        this.setupFilters();
    }

    initializeEventListeners() {
        // Close popup when clicking outside
        this.popup.addEventListener('click', (e) => {
            if (e.target === this.popup) {
                this.closePopup();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.popup.style.display === 'flex') {
                this.closePopup();
            }
        });

        // Setup expand/collapse functionality
        this.setupExpandButtons();
    }

    setupExpandButtons() {
        document.querySelectorAll('.expand-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const content = e.target.closest('.announcement-content');
                content.classList.toggle('expanded');
                e.target.textContent = content.classList.contains('expanded') ? 'Show Less' : 'Show More';
            });
        });
    }

    setupFilters() {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                e.target.classList.add('active');

                const filter = e.target.dataset.filter;
                this.filterAnnouncements(filter);
            });
        });
    }

    filterAnnouncements(filter) {
        const items = document.querySelectorAll('.announcement-item');
        items.forEach(item => {
            if (filter === 'all' || item.dataset.priority === filter) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    showPopup() {
        this.popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        this.isMinimized = false;
        this.updatePopupState();
    }

    closePopup() {
        this.popup.style.display = 'none';
        document.body.style.overflow = '';
        localStorage.setItem('lastAnnouncementSeen', new Date().toISOString());
    }

    minimizePopup() {
        this.isMinimized = !this.isMinimized;
        this.updatePopupState();
    }

    updatePopupState() {
        const content = this.popup.querySelector('.announcement-content');
        if (this.isMinimized) {
            content.classList.add('minimized');
        } else {
            content.classList.remove('minimized');
        }
    }

    async checkNewAnnouncements() {
        const lastSeen = localStorage.getItem('lastAnnouncementSeen');
        if (!lastSeen) {
            this.showPopup();
            return;
        }

        try {
            const response = await fetch('check_announcements.php');
            const data = await response.json();
            
            if (data.hasNew) {
                this.showPopup();
                // Add animation to new announcements
                document.querySelectorAll('.announcement-item.new').forEach(item => {
                    item.classList.add('highlight-new');
                });
            }
        } catch (error) {
            console.error('Error checking announcements:', error);
        }

        // Check every 5 minutes
        setTimeout(() => this.checkNewAnnouncements(), 5 * 60 * 1000);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize popup functionality only if popup exists
    initializeAnnouncementPopup();
});

function initializeAnnouncementPopup() {
    const popup = document.getElementById('announcementPopup');
    
    // Only proceed if popup exists
    if (!popup) return;
    
    // Close popup when clicking outside
    popup.addEventListener('click', function(e) {
        if (e.target === popup) {
            closeAnnouncementPopup();
        }
    });

    // Close popup with escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup.style.display === 'flex') {
            closeAnnouncementPopup();
        }
    });

    // Initialize filter buttons if they exist
    const filterButtons = document.querySelectorAll('.filter-btn');
    if (filterButtons.length > 0) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const priority = this.getAttribute('data-filter');
                filterAnnouncements(priority);
            });
        });
    }
}

function closeAnnouncementPopup() {
    const popup = document.getElementById('announcementPopup');
    if (popup) {
        popup.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function minimizeAnnouncementPopup() {
    window.announcementManager.minimizePopup();
}

function showAnnouncements() {
    const popup = document.getElementById('announcementPopup');
    if (popup) {
        popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function filterAnnouncements(priority) {
    const items = document.querySelectorAll('.announcement-item');
    if (!items.length) return;

    items.forEach(item => {
        if (priority === 'all' || item.classList.contains(`priority-${priority}`)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });

    // Update active state of filter buttons
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-filter') === priority);
    });
}

// Export functions for global use
window.closeAnnouncementPopup = closeAnnouncementPopup;
window.showAnnouncements = showAnnouncements; 