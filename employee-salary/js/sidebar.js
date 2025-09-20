/**
 * Sidebar Navigation JavaScript
 * Handles sidebar toggle functionality and responsive behavior
 */

class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.mainWrapper = document.getElementById('mainWrapper');
        this.sidebarOverlay = document.getElementById('sidebarOverlay');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.mobileToggle = document.getElementById('mobileToggle');
        this.floatingToggle = document.getElementById('floatingToggle');
        
        this.isMobile = window.innerWidth <= 992;
        this.isCollapsed = this.isMobile; // Start collapsed on mobile
        
        this.init();
    }
    
    init() {
        // Set initial state
        this.updateSidebarState();
        
        // Bind event listeners
        this.bindEvents();
        
        // Handle window resize
        window.addEventListener('resize', () => this.handleResize());
    }
    
    bindEvents() {
        // Desktop sidebar toggle
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        // Mobile sidebar toggle
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        // Floating toggle button
        if (this.floatingToggle) {
            this.floatingToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        // Overlay click to close sidebar on mobile
        if (this.sidebarOverlay) {
            this.sidebarOverlay.addEventListener('click', () => this.closeSidebar());
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (this.isMobile && 
                !this.sidebar.contains(e.target) && 
                !this.mobileToggle.contains(e.target) &&
                !this.isCollapsed) {
                this.closeSidebar();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && !this.isCollapsed) {
                this.closeSidebar();
            }
        });
    }
    
    toggleSidebar() {
        this.isCollapsed = !this.isCollapsed;
        this.updateSidebarState();
    }
    
    openSidebar() {
        this.isCollapsed = false;
        this.updateSidebarState();
    }
    
    closeSidebar() {
        this.isCollapsed = true;
        this.updateSidebarState();
    }
    
    updateSidebarState() {
        if (!this.sidebar || !this.mainWrapper) return;
        
        if (this.isMobile) {
            // Mobile behavior
            if (this.isCollapsed) {
                this.sidebar.classList.remove('mobile-active');
                this.sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                this.sidebar.classList.add('mobile-active');
                this.sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            this.mainWrapper.classList.add('expanded');
        } else {
            // Desktop behavior
            if (this.isCollapsed) {
                this.sidebar.classList.add('collapsed');
                this.mainWrapper.classList.add('expanded');
            } else {
                this.sidebar.classList.remove('collapsed');
                this.mainWrapper.classList.remove('expanded');
            }
            this.sidebar.classList.remove('mobile-active');
            this.sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Update toggle button icons and floating button visibility
        this.updateToggleIcons();
        this.updateFloatingButton();
    }
    
    updateFloatingButton() {
        if (!this.floatingToggle) return;
        
        // Show floating button only on desktop when sidebar is collapsed
        if (!this.isMobile && this.isCollapsed) {
            this.floatingToggle.classList.add('visible');
        } else {
            this.floatingToggle.classList.remove('visible');
        }
    }
    
    updateToggleIcons() {
        if (this.sidebarToggle) {
            const icon = this.sidebarToggle.querySelector('i');
            if (icon) {
                if (this.isCollapsed) {
                    icon.className = 'fas fa-bars';
                } else {
                    icon.className = 'fas fa-times';
                }
            }
        }
    }
    
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 992;
        
        // If switching between mobile and desktop
        if (wasMobile !== this.isMobile) {
            this.isCollapsed = this.isMobile;
            this.updateSidebarState();
        }
    }
    
    // Public methods for external use
    getSidebarState() {
        return {
            isCollapsed: this.isCollapsed,
            isMobile: this.isMobile
        };
    }
    
    setSidebarState(collapsed) {
        this.isCollapsed = collapsed;
        this.updateSidebarState();
    }
}

// Initialize sidebar when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar manager
    window.sidebarManager = new SidebarManager();
    
    // Add smooth transition class after initial load
    setTimeout(() => {
        document.body.classList.add('sidebar-transitions-enabled');
    }, 100);
});

// Navigation link handling
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't prevent default for actual navigation
            // Just handle active state
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
            
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to current nav item
            this.closest('.nav-item').classList.add('active');
        });
    });
});

// Utility functions for external use
window.SidebarUtils = {
    toggle: () => window.sidebarManager?.toggleSidebar(),
    open: () => window.sidebarManager?.openSidebar(),
    close: () => window.sidebarManager?.closeSidebar(),
    getState: () => window.sidebarManager?.getSidebarState()
};