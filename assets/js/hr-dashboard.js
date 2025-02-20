document.addEventListener('DOMContentLoaded', function() {
    // Initialize stat boxes with dynamic data
    initializeStatBoxes();
    
    // Add hover effects for stat boxes
    const statBoxes = document.querySelectorAll('.stat-box');
    statBoxes.forEach(box => {
        box.addEventListener('mouseover', function() {
            this.style.cursor = 'pointer';
        });
    });
});

function initializeStatBoxes() {
    // You can add AJAX calls here to fetch real-time data
    // Example:
    // fetchEmployeeStats().then(data => updateStatBoxes(data));
}

function updateStatBoxes(data) {
    // Update stat boxes with real data
    // This is where you would update the numbers based on your backend data
} 