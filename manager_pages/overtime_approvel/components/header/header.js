export function initHeader() {
    const dateDisplay = document.getElementById('current-date-display') || document.querySelector('.date-info-box > div:nth-child(2)');
    if (dateDisplay) {
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        dateDisplay.innerText = new Date().toLocaleDateString('en-US', options);
    }
}
