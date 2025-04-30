// Site Updates JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar
    const toggleButton = document.getElementById('toggle-sidebar');
    const leftPanel = document.querySelector('.left-panel');
    const mainContent = document.querySelector('.main-content');
    
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            leftPanel.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Save the state to localStorage
            const isCollapsed = leftPanel.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
        
        // Check localStorage for saved state
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            leftPanel.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    }
    
    // Form submission for travel expenses
    const expenseForm = document.getElementById('expense-form');
    if (expenseForm) {
        expenseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const date = document.getElementById('expense-date').value;
            const type = document.getElementById('expense-type').value;
            const amount = document.getElementById('expense-amount').value;
            const description = document.getElementById('expense-description').value;
            
            if (!date || !type || !amount || !description) {
                showAlert('Please fill all required fields', 'error');
                return;
            }
            
            // Here you would normally send the data to the server
            // For demonstration, we'll just show a success message
            showAlert('Expense submitted successfully!', 'success');
            expenseForm.reset();
            
            // In a real application, you would refresh the expenses table
            // fetchExpenses();
        });
    }
    
    // Initialize any datepickers
    initializeDatepickers();
});

// Helper function to show alerts
function showAlert(message, type) {
    // Check if SweetAlert2 is available
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            text: message,
            icon: type,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    } else {
        // Fallback to regular alert
        alert(message);
    }
}

// Initialize datepickers if needed
function initializeDatepickers() {
    // Check if flatpickr is available and there are datepicker elements
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            maxDate: 'today'
        });
    }
}

// Function to load site updates (could be fetched from an API)
function loadSiteUpdates() {
    const updatesContainer = document.querySelector('.update-cards');
    if (!updatesContainer) return;
    
    // In a real application, you would fetch this data from the server
    // For now, we'll use sample data
    const updates = [
        {
            title: 'New Project Management System',
            date: '2023-06-15',
            content: 'We have implemented a new project management system to better track tasks and deadlines.'
        },
        {
            title: 'Office Renovation',
            date: '2023-07-22',
            content: 'The office renovation will begin next month. Please prepare for temporary relocation.'
        },
        {
            title: 'Holiday Schedule',
            date: '2023-08-05',
            content: 'The holiday schedule for the upcoming festival season has been published.'
        }
    ];
    
    // Clear existing content
    updatesContainer.innerHTML = '';
    
    // Add updates to container
    updates.forEach(update => {
        const card = document.createElement('div');
        card.className = 'update-card';
        
        const formattedDate = new Date(update.date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        card.innerHTML = `
            <div class="update-title">${update.title}</div>
            <div class="update-date">${formattedDate}</div>
            <div class="update-content">${update.content}</div>
        `;
        
        updatesContainer.appendChild(card);
    });
} 