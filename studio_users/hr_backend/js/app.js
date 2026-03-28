/* ============================================
   app.js — Component Loader & Bootstrapper
   ============================================ */

document.addEventListener('DOMContentLoaded', async () => {
    // Load all sections in order
    await loadSection('hr-header', 'sections/header.html');
    await loadSection('hr-tabs',   'sections/tabs.html');
    await loadSection('hr-form',   'sections/form.html');
    await loadSection('hr-notice', 'sections/notice.html');
    await loadSection('hr-custom', 'sections/custom.html');
    await loadSection('hr-toast',  'sections/toast.html');

    // Load JS after HTML is injected
    loadScript('js/header.js');
    loadScript('js/toast.js');
    loadScript('js/form.js');    // uses showToast(), load after toast.js
    loadScript('js/notice.js'); // uses showToast(), load after toast.js
    loadScript('js/custom.js'); // uses showToast(), load after toast.js
    loadScript('js/tabs.js');   // must load last — controls hr-form & hr-notice visibility
});

async function loadSection(containerId, path) {
    try {
        // Appended ?v= parameter to bypass stubborn Chrome static cache
        const res = await fetch(path + '?v=' + new Date().getTime());
        if (res.ok) {
            document.getElementById(containerId).innerHTML = await res.text();
        } else {
            console.error(`[app.js] Could not load: ${path}`);
        }
    } catch (err) {
        console.error(`[app.js] Error loading ${path}:`, err);
    }
}

function loadScript(src) {
    const script = document.createElement('script');
    // Appended ?v= parameter to bypass stubborn Chrome JS cache
    script.src = src + '?v=' + new Date().getTime();
    document.body.appendChild(script);
}
