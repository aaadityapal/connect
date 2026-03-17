/**
 * =====================================================
 * SCHEDULE LOADER — components/schedule-loader.js
 * =====================================================
 * Steps:
 *   1. Injects components/schedule.css into <head>
 *   2. Fetches components/my-schedule.html
 *      → injects into #my-schedule-mount
 *   3. Fetches components/team-schedule.html
 *      → injects into #team-schedule-mount
 *   4. Loads components/schedule-timeline.js
 *   5. Calls ScheduleTimeline.init() once both
 *      HTML fragments are in the DOM
 * =====================================================
 */

(function () {
    'use strict';

    const BASE = (function () {
        const scripts = document.querySelectorAll('script[src]');
        for (const s of scripts) {
            if (s.src.includes('schedule-loader')) {
                return s.src.replace('schedule-loader.js', '');
            }
        }
        return 'components/';
    })();

    /** Inject CSS stylesheet into <head> (idempotent) */
    function injectCSS(href) {
        if (document.querySelector(`link[href="${href}"]`)) return;
        const link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    /** Fetch an HTML fragment and inject into a mount element */
    function loadFragment(mountId, file) {
        return new Promise((resolve, reject) => {
            const mount = document.getElementById(mountId);
            if (!mount) {
                console.warn(`[ScheduleLoader] Mount point #${mountId} not found.`);
                return resolve();   // non-fatal — page may not use this widget
            }
            fetch(BASE + file)
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status} loading ${file}`);
                    return r.text();
                })
                .then(html => {
                    mount.innerHTML = html;
                    resolve();
                })
                .catch(err => {
                    console.error('[ScheduleLoader]', err);
                    reject(err);
                });
        });
    }

    /** Dynamically load a JS file (returns Promise) */
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) return resolve();
            const s = document.createElement('script');
            s.src = src;
            s.onload  = resolve;
            s.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            document.body.appendChild(s);
        });
    }

    /** Main boot sequence */
    async function boot() {
        try {
            // Step 1: Inject schedule CSS
            injectCSS(BASE + 'schedule.css');

            // Step 2: Load both HTML fragments in parallel
            await Promise.all([
                loadFragment('my-schedule-mount',   'my-schedule.html'),
                loadFragment('team-schedule-mount', 'team-schedule.html'),
            ]);

            // Step 3: Load the timeline engine JS
            await loadScript(BASE + 'schedule-timeline.js');

            // Step 4: Initialise both timelines
            if (window.ScheduleTimeline && window.ScheduleTimeline.init) {
                window.ScheduleTimeline.init();
            } else {
                console.error('[ScheduleLoader] ScheduleTimeline.init not available.');
            }
        } catch (err) {
            console.error('[ScheduleLoader] Boot failed:', err);
        }
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
