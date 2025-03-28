// Dashboard Project Integration Script
document.addEventListener('DOMContentLoaded', function() {
    // Find project buttons in the dashboard
    const addProjectButtons = document.querySelectorAll('.add-project-btn, .add-project-btn-minimal');
    
    // When any add project button is clicked
    addProjectButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the modal
            const projectModal = document.getElementById('projectModal');
            
            // Display the modal
            if (projectModal) {
                projectModal.style.display = 'flex';
                setTimeout(() => {
                    projectModal.classList.add('active');
                }, 10);
                document.body.style.overflow = 'hidden';
                
                // Check if initializeProjectTitleAutocomplete exists and call it
                if (typeof initializeProjectTitleAutocomplete === 'function') {
                    initializeProjectTitleAutocomplete();
                }
                
                // Attempt to initialize project form if the function exists
                if (typeof initializeProjectForm === 'function') {
                    initializeProjectForm();
                }
            }
        });
    });
}); 