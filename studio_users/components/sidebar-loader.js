/**
 * =====================================================
 * SIDEBAR LOADER — components/sidebar-loader.js
 * =====================================================
 * Usage: Add this ONE script tag to any page:
 *
 *   <script src="components/sidebar-loader.js"></script>
 *
 * The script will:
 *   1. Inject components/sidebar.css into <head>
 *   2. Fetch the sidebar HTML fragment
 *   3. Inject it into <div id="sidebar-mount"> or before <main>
 *   4. Auto-highlight the active nav item by URL
 *   5. Set up toggle, mobile, and resize behavior
 *   6. Run lucide.createIcons()
 * =====================================================
 */

(function () {
    // ─── Config ───────────────────────────────────────────
    // Path to the sidebar HTML fragment (relative to the page loading this script)
    const basePath = window.SIDEBAR_BASE_PATH || '';
    const SIDEBAR_PATH = basePath + 'components/sidebar.php';

    // ─── Helpers ──────────────────────────────────────────
    function highlightActiveItem() {
        let currentFile = window.location.pathname.split('/').pop();
        if (!currentFile || currentFile === '') {
            currentFile = 'index';
        }
        let currentPage = currentFile.replace(/\.html$|\.php$/, '');

        if (window.location.pathname.includes('/profile/')) {
            currentPage = 'profile';
        }
        
        if (window.location.pathname.includes('attendance_recrds')) {
            currentPage = 'worksheet';
        }

        document.querySelectorAll('#appSidebar .menu-item[data-page]').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.page === currentPage) {
                item.classList.add('active');
            }
        });
    }

    function setupToggle() {
        const sidebar     = document.getElementById('appSidebar');
        const toggleBtn   = document.getElementById('sidebarToggleBtn');
        const mobileBtn   = document.getElementById('mobileMenuBtn');
        const overlay     = document.getElementById('mobileSidebarOverlay');

        if (!sidebar || !toggleBtn) return;

        // ── Desktop collapse/expand ──
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
        });

        // ── Mobile open ──
        function openMobile() {
            sidebar.classList.add('mobile-open');
            if (overlay) overlay.classList.add('active');
            if (mobileBtn) mobileBtn.innerHTML = '<i data-lucide="x" style="width:18px;height:18px;"></i>';
            if (window.lucide) lucide.createIcons();
        }

        // ── Mobile close ──
        function closeMobile() {
            sidebar.classList.remove('mobile-open');
            if (overlay) overlay.classList.remove('active');
            if (mobileBtn) mobileBtn.innerHTML = '<i data-lucide="menu" style="width:18px;height:18px;"></i>';
            if (window.lucide) lucide.createIcons();
        }

        if (mobileBtn) {
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.contains('mobile-open') ? closeMobile() : openMobile();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => closeMobile());
        }

        // ── Responsive resize ──
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
            } else {
                sidebar.classList.add('collapsed');
                closeMobile();
            }
        }

        window.addEventListener('resize', handleResize);
        handleResize(); // Run once on load
    }

    // ─── Main: Fetch → Inject → Init ──────────────────────
    function loadSidebar() {
        // Step 1: Inject sidebar CSS into <head>
        if (!document.getElementById('sidebar-css')) {
            const link = document.createElement('link');
            link.id   = 'sidebar-css';
            link.rel  = 'stylesheet';
            // Resolve path relative to this script file
            const scriptSrc = document.currentScript
                ? document.currentScript.src
                : document.querySelector('script[src*="sidebar-loader"]').src;
            const base = scriptSrc.substring(0, scriptSrc.lastIndexOf('/') + 1);
            link.href = base + 'sidebar.css';
            document.head.appendChild(link);
        }

        // Find mount point: <div id="sidebar-mount"> or fall back to prepending to body
        const mount = document.getElementById('sidebar-mount') || document.body;

        fetch(SIDEBAR_PATH)
            .then(res => {
                if (!res.ok) throw new Error(`Sidebar fetch failed: ${res.status}`);
                return res.text();
            })
            .then(html => {
                // Inject sidebar HTML
                if (document.getElementById('sidebar-mount')) {
                    mount.innerHTML = html;
                } else {
                    // No mount point — inject before <main> or at top of body
                    const main = document.querySelector('main') || document.querySelector('.main-content');
                    if (main) {
                        main.insertAdjacentHTML('beforebegin', html);
                    } else {
                        document.body.insertAdjacentHTML('afterbegin', html);
                    }
                }

                // Highlight active menu item
                highlightActiveItem();

                // Set up all sidebar interactions
                setupToggle();

                // Render Lucide icons (sidebar icons)
                if (window.lucide) {
                    lucide.createIcons();
                } else {
                    console.warn('[SidebarLoader] Lucide not loaded yet. Add the Lucide script before sidebar-loader.js');
                }
            })
            .catch(err => {
                console.error('[SidebarLoader] Could not load sidebar:', err);
            });
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSidebar);
    } else {
        loadSidebar();
    }

})();
