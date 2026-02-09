/**
 * Shared Sidebar Component
 * Injects the sidebar HTML and handles active state highlighting.
 */

function renderSidebar(activePage = 'new-project') {
    // Determine active states
    const isActive = (page) => activePage === page ? 'active' : '';

    const sidebarHTML = `
        <div class="side-panel-content">
            <!-- Logo & Branding -->
            <div class="brand-section">
                <div class="brand-logo">
                    <i class="fas fa-building"></i>
                </div>
                <h2>ArchitectsHive</h2>
                <p class="brand-tagline">Building Dreams Together</p>
            </div>

            <!-- Navigation Menu -->
            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="dashboard.html" class="nav-link ${isActive('dashboard')}">
                            <i class="fas fa-th-large"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link ${isActive('view-projects')}">
                            <i class="fas fa-project-diagram"></i>
                            <span>View Projects</span>
                        </a>
                    </li>
                    <li>
                        <a href="index.html" class="nav-link ${isActive('new-project')}">
                            <i class="fas fa-plus-square"></i>
                            <span>New Project</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link ${isActive('payments')}">
                            <i class="fas fa-credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    <li>
                        <a href="reminders.html" class="nav-link ${isActive('reminders')}">
                            <i class="fas fa-bell"></i>
                            <span>Reminders</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link ${isActive('clients')}">
                            <i class="fas fa-users"></i>
                            <span>Clients</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link ${isActive('settings')}">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                     <li>
                        <a href="#" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Page Specific Sidebar Extras (injected here) -->
            <div id="sidebar-extras"></div>

            <!-- Help Section -->
            <div class="help-section">
                <h3><i class="fas fa-question-circle"></i> Need Help?</h3>
                <div class="contact-links">
                    <a href="tel:+919876543210" class="contact-link">
                        <i class="fas fa-phone"></i>
                        +91 98765 43210
                    </a>
                    <a href="mailto:support@architectshive.com" class="contact-link">
                        <i class="fas fa-envelope"></i>
                        support@architectshive.com
                    </a>
                </div>
            </div>
        </div>
    `;

    // Find the placeholder or create the aside element
    let sidePanel = document.getElementById('sidePanel');
    if (!sidePanel) {
        sidePanel = document.createElement('aside');
        sidePanel.className = 'side-panel';
        sidePanel.id = 'sidePanel';
        // Insert as first child of layout wrapper
        const wrapper = document.querySelector('.layout-wrapper');
        if (wrapper) {
            wrapper.prepend(sidePanel);
        }
    }

    sidePanel.innerHTML = sidebarHTML;
}
