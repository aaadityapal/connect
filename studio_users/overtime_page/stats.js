function initStats() {
    const values = document.querySelectorAll('.stat-value[data-value]');
    values.forEach(el => {
        const target = parseFloat(el.dataset.value) || 0;
        if (target === 0) return; // skip zeros
        let start = 0;
        const duration = 900;
        const step = (timestamp) => {
            if (!start) start = timestamp;
            const progress = Math.min((timestamp - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = (target % 1 === 0)
                ? Math.floor(eased * target)
                : (eased * target).toFixed(1);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
}
