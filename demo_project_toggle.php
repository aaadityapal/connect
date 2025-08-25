<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Toggle Demo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .demo-container { max-width: 800px; margin: 0 auto; }
        .project-breakdown-card { 
            background: white; 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin: 1rem 0;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .project-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1rem; 
        }
        .project-title-section h4 { margin: 0; color: #1e293b; }
        .project-details-preview { color: #64748b; font-size: 0.85rem; }
        .project-actions { display: flex; align-items: center; gap: 12px; }
        .project-efficiency { 
            background: #10b981; 
            color: white; 
            padding: 0.5rem 1rem; 
            border-radius: 12px; 
            font-weight: 700; 
        }
        .toggle-details-btn { 
            background: #3b82f6; 
            color: white; 
            border: none; 
            border-radius: 50%; 
            width: 36px; 
            height: 36px; 
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .toggle-details-btn:hover { background: #2563eb; transform: scale(1.1); }
        .toggle-details-btn.active { background: #10b981; }
        .project-stats { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 1rem; 
            margin-bottom: 1rem; 
        }
        .project-stat { text-align: center; }
        .project-stat-label { font-size: 0.8rem; color: #64748b; }
        .project-stat-value { font-size: 1.1rem; font-weight: 600; color: #1e293b; }
        .project-details { 
            margin-top: 1rem; 
            padding-top: 1rem; 
            border-top: 1px solid #e2e8f0; 
            display: none;
        }
        .loading-stages { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 2rem; 
            gap: 12px; 
        }
        .spinner { 
            width: 20px; 
            height: 20px; 
            border: 2px solid #e2e8f0; 
            border-top: 2px solid #3b82f6; 
            border-radius: 50%; 
            animation: spin 1s linear infinite; 
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .stage-card { 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            margin: 1rem 0;
            overflow: hidden;
        }
        .stage-header { 
            padding: 1rem; 
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); 
            border-bottom: 1px solid #e2e8f0;
        }
        .stage-title { margin: 0 0 0.5rem 0; color: #1e293b; }
        .status-completed { background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; }
        .substage-card { padding: 1rem; border-bottom: 1px solid #e2e8f0; }
        .substage-card:last-child { border-bottom: none; }
        .substage-title { margin: 0 0 0.5rem 0; color: #1e293b; font-size: 0.95rem; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .demo-header { text-align: center; margin-bottom: 2rem; }
        .demo-header h1 { color: #1e293b; }
        .demo-controls { background: white; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="demo-container">
        <div class="demo-header">
            <h1><i class="fas fa-toggle-on"></i> Project Toggle Functionality Demo</h1>
            <p>Testing the expandable project cards with stages and substages</p>
        </div>
        
        <div class="demo-controls">
            <label for="userSelect">Select User:</label>
            <select id="userSelect">
                <option value="1">Test User (ID: 1)</option>
                <option value="2">Demo User (ID: 2)</option>
            </select>
            <button onclick="testAllFunctions()" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px;">Test All Functions</button>
        </div>
        
        <div id="test-results"></div>
        
        <!-- Demo Project Card -->
        <div class="project-breakdown-card high-efficiency">
            <div class="project-header">
                <div class="project-title-section">
                    <h4 class="project-title">Sample Project - Office Complex</h4>
                    <div class="project-details-preview">3 stages • 8 substages</div>
                </div>
                <div class="project-actions">
                    <div class="project-efficiency">85%</div>
                    <button class="toggle-details-btn" onclick="toggleProjectDetails(123)" data-project-id="123">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="project-stats">
                <div class="project-stat">
                    <div class="project-stat-label">Total Tasks:</div>
                    <div class="project-stat-value">12</div>
                </div>
                <div class="project-stat">
                    <div class="project-stat-label">Completed:</div>
                    <div class="project-stat-value">10</div>
                </div>
                <div class="project-stat">
                    <div class="project-stat-label">On-Time:</div>
                    <div class="project-stat-value">8</div>
                </div>
                <div class="project-stat">
                    <div class="project-stat-label">Progress:</div>
                    <div class="project-stat-value">83%</div>
                </div>
            </div>
            <div class="project-details" id="project-details-123">
                <div class="loading-stages" id="loading-stages-123" style="display: none;">
                    <div class="spinner"></div>
                    <span>Loading stages and substages...</span>
                </div>
                <div class="stages-container" id="stages-container-123">
                    <!-- Sample stages will be loaded here -->
                    <div class="stage-card">
                        <div class="stage-header">
                            <h5 class="stage-title">Stage 1 - Foundation</h5>
                            <span class="status-completed">completed</span>
                        </div>
                        <div class="substage-card">
                            <h6 class="substage-title">Site Preparation</h6>
                            <p>Status: Completed • Drawing: FND-001</p>
                        </div>
                        <div class="substage-card">
                            <h6 class="substage-title">Foundation Excavation</h6>
                            <p>Status: Completed • Drawing: FND-002</p>
                        </div>
                    </div>
                    
                    <div class="stage-card">
                        <div class="stage-header">
                            <h5 class="stage-title">Stage 2 - Structure</h5>
                            <span class="status-completed">in-progress</span>
                        </div>
                        <div class="substage-card">
                            <h6 class="substage-title">Column Construction</h6>
                            <p>Status: In Progress • Drawing: STR-001</p>
                        </div>
                        <div class="substage-card">
                            <h6 class="substage-title">Beam Installation</h6>
                            <p>Status: Pending • Drawing: STR-002</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to toggle project details (same as in main file)
        function toggleProjectDetails(projectId) {
            const detailsContainer = document.getElementById(`project-details-${projectId}`);
            const toggleButton = document.querySelector(`[data-project-id="${projectId}"]`);
            const loadingContainer = document.getElementById(`loading-stages-${projectId}`);
            const stagesContainer = document.getElementById(`stages-container-${projectId}`);
            
            console.log('Toggle called for project:', projectId);
            console.log('Elements found:', {
                detailsContainer: !!detailsContainer,
                toggleButton: !!toggleButton,
                loadingContainer: !!loadingContainer,
                stagesContainer: !!stagesContainer
            });
            
            if (!detailsContainer) {
                console.error(`Project details container not found for project ID: ${projectId}`);
                return;
            }
            
            if (!toggleButton) {
                console.error(`Toggle button not found for project ID: ${projectId}`);
                return;
            }
            
            if (detailsContainer.style.display === 'none' || detailsContainer.style.display === '') {
                // Show details
                detailsContainer.style.display = 'block';
                toggleButton.innerHTML = '<i class="fas fa-chevron-up"></i>';
                toggleButton.classList.add('active');
                console.log('Project details shown');
                
                // Simulate loading if stages container exists
                if (stagesContainer && !stagesContainer.hasAttribute('data-loaded')) {
                    simulateLoading(projectId);
                }
            } else {
                // Hide details
                detailsContainer.style.display = 'none';
                toggleButton.innerHTML = '<i class="fas fa-chevron-down"></i>';
                toggleButton.classList.remove('active');
                console.log('Project details hidden');
            }
        }
        
        function simulateLoading(projectId) {
            const loadingContainer = document.getElementById(`loading-stages-${projectId}`);
            const stagesContainer = document.getElementById(`stages-container-${projectId}`);
            
            if (loadingContainer && stagesContainer) {
                loadingContainer.style.display = 'flex';
                stagesContainer.style.display = 'none';
                
                setTimeout(() => {
                    loadingContainer.style.display = 'none';
                    stagesContainer.style.display = 'block';
                    stagesContainer.setAttribute('data-loaded', 'true');
                }, 1500);
            }
        }
        
        function testAllFunctions() {
            const resultsDiv = document.getElementById('test-results');
            let results = '<div class="success"><h3>🧪 Function Tests Results:</h3>';
            
            // Test 1: Toggle function exists
            if (typeof toggleProjectDetails === 'function') {
                results += '<p>✅ toggleProjectDetails function: EXISTS</p>';
            } else {
                results += '<p>❌ toggleProjectDetails function: NOT FOUND</p>';
            }
            
            // Test 2: User selector exists
            const userSelect = document.getElementById('userSelect');
            if (userSelect) {
                results += '<p>✅ userSelect element: FOUND</p>';
                results += `<p>📋 Selected user ID: ${userSelect.value}</p>`;
            } else {
                results += '<p>❌ userSelect element: NOT FOUND</p>';
            }
            
            // Test 3: Project elements exist
            const projectDetails = document.getElementById('project-details-123');
            const toggleButton = document.querySelector('[data-project-id="123"]');
            
            if (projectDetails) {
                results += '<p>✅ Project details container: FOUND</p>';
            } else {
                results += '<p>❌ Project details container: NOT FOUND</p>';
            }
            
            if (toggleButton) {
                results += '<p>✅ Toggle button: FOUND</p>';
            } else {
                results += '<p>❌ Toggle button: NOT FOUND</p>';
            }
            
            results += '<p>🎯 <strong>Click the toggle button above to test functionality!</strong></p>';
            results += '</div>';
            
            resultsDiv.innerHTML = results;
        }
        
        // Auto-run test on page load
        window.addEventListener('load', testAllFunctions);
    </script>
</body>
</html>