export function renderMetrics(data) {
    try {
        console.log("Updating metrics with data count:", data.length);
        
        const submittedData = data.filter(d => d.status === 'Submitted');
        const pendingData   = data.filter(d => d.status === 'Pending');
        const approvedData  = data.filter(d => d.status === 'Approved');
        const rejectedData  = data.filter(d => d.status === 'Rejected');
        const expiredData   = data.filter(d => d.status === 'Expired');

        // Helper to sum hours
        const sumH = (arr) => arr.reduce((sum, d) => {
            let h = parseFloat(d.otHours);
            return sum + (isNaN(h) ? 0 : h);
        }, 0);

        const subH = sumH(submittedData);
        const penH = sumH(pendingData);
        const appH = sumH(approvedData);
        const rejH = sumH(rejectedData);
        const expH = sumH(expiredData);

        const elSubmitted = document.getElementById('metric-submitted');
        const elPending = document.getElementById('metric-pending');
        const elHours = document.getElementById('metric-hours');
        const elRejected = document.getElementById('metric-rejected');
        const elApproved = document.getElementById('metric-approved');
        const elExpired = document.getElementById('metric-expired');

        // Main counts
        if (elSubmitted) elSubmitted.innerHTML = `${submittedData.length} <small style="font-size: 0.8rem; opacity: 0.6;">(${subH.toFixed(1)}h)</small>`;
        if (elPending)   elPending.innerHTML   = `${pendingData.length} <small style="font-size: 0.8rem; opacity: 0.6;">(${penH.toFixed(1)}h)</small>`;
        if (elHours)     elHours.innerHTML     = `${appH.toFixed(1)}<small>h</small>`;
        if (elRejected)  elRejected.innerHTML  = `${rejectedData.length} <small style="font-size: 0.8rem; opacity: 0.6;">(${rejH.toFixed(1)}h)</small>`;
        if (elApproved)  elApproved.innerHTML  = `${approvedData.length} <small style="font-size: 0.8rem; opacity: 0.6;">(${appH.toFixed(1)}h)</small>`;
        if (elExpired)   elExpired.innerHTML   = `${expiredData.length} <small style="font-size: 0.8rem; opacity: 0.6;">(${expH.toFixed(1)}h)</small>`;

        // Force a re-run of animations
        animateMetrics();
    } catch (error) {
        console.error("Error updating metrics:", error);
    }
}   

function animateMetrics() {
    document.querySelectorAll('.metric-value').forEach(el => {
        // If it's a direct hour count (mc-cyan), handle normally
        if (el.closest('.mc-cyan')) {
            const raw = parseFloat(el.textContent);
            if (isNaN(raw) || raw === 0) return;
            const duration = 1000;
            const startTime = performance.now();
            function step(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.innerHTML = (eased * raw).toFixed(1) + '<small>h</small>';
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
            return;
        }

        // For counts with brackets, only animate the main count
        const firstValue = parseInt(el.textContent);
        if (isNaN(firstValue) || firstValue === 0) return;

        const duration = 1000;
        const startTime = performance.now();

        // Simpler way: identify the bracket HTML and keep it static
        const bracketHTML = el.querySelector('small') ? el.querySelector('small').outerHTML : '';

        function step(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const currentCount = Math.floor(eased * firstValue);
            el.innerHTML = `${currentCount} ${bracketHTML}`;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });
}

export function initMetrics() {
    // Initial animation setup
    const cards = document.querySelectorAll('.metric-card');
    cards.forEach(card => {
        card.addEventListener('animationend', () => {
            if (!card.classList.contains('is-mounted')) {
                card.classList.add('is-mounted');
            }
        }, { once: true });
    });
}
