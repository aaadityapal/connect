// app.js - Main entry point
import { initHeader } from './components/header/header.js';
import { initFilters } from './components/filters/filters.js';
import { initMetrics } from './components/metrics/metrics.js';
import { initTable } from './components/table/table.js';

// Function to dynamically load HTML components
async function loadComponent(mountId, componentUrl) {
    try {
        // Add cache buster for development
        const response = await fetch(`${componentUrl}?v=${new Date().getTime()}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const html = await response.text();
        document.getElementById(mountId).innerHTML = html;
        return true;
    } catch (e) {
        console.error(`Failed to load component from \${componentUrl}:`, e);
        document.getElementById(mountId).innerHTML = `<p class="text-red-500">Error loading component</p>`;
        return false;
    }
}

// Bootstrap Application
async function mountApp() {
    console.log("Loading dashboard components...");

    // Mount all HTML structurally 
    await Promise.all([
        loadComponent('header-mount', 'components/header/header.html'),
        loadComponent('filters-mount', 'components/filters/filters.html'),
        loadComponent('metrics-mount', 'components/metrics/metrics.html'),
        loadComponent('table-mount', 'components/table/table.html')
    ]);

    // Initialize JavaScript logic for all mounted views
    initHeader();
    initFilters();
    initMetrics();
    initTable();
    
    console.log("Dashboard fully mounted and initialized.");
}

// Start app on load
document.addEventListener('DOMContentLoaded', mountApp);
