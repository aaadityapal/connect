/**
 * Direct Assignment Fix Script - ULTRA AGGRESSIVE Version
 * This script provides a direct, aggressive fix for the dropdown assignment issue
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Loading ULTRA AGGRESSIVE direct assignment fix script...');
    
    // Wait for the project form to be completely loaded
    const maxAttempts = 20;
    let attempts = 0;
    
    const checkInterval = setInterval(function() {
        attempts++;
        const form = document.getElementById('createProjectForm');
        
        if (form || attempts >= maxAttempts) {
            clearInterval(checkInterval);
            if (form) {
                console.log('Form found, initializing ULTRA AGGRESSIVE direct assignment fix');
                initializeDirectFix();
            } else {
                console.warn('Form not found after maximum attempts');
            }
        }
    }, 250);
    
    function initializeDirectFix() {
        // Override the original selectProject function
        if (typeof window.originalSelectProject !== 'function') {
            window.originalSelectProject = window.selectProject;
        }
        
        // Create the new version with direct assignment
        window.selectProject = async function(projectId, ensureVisible = false) {
            try {
                console.log('ULTRA FIX: SelectProject called with ID:', projectId);
                
                // First, call the original to set up everything normally
                await window.originalSelectProject(projectId, ensureVisible);
                
                // ULTRA AGGRESSIVE: Make multiple attempts with increasing delays
                const delays = [500, 1000, 2000, 3000];
                for (const delay of delays) {
                    setTimeout(async () => {
                        console.log(`ULTRA FIX: Attempt at ${delay}ms`);
                        await forceAssignments(projectId);
                    }, delay);
                }
                
                return true;
            } catch (error) {
                console.error('ULTRA FIX: Error in selectProject override:', error);
                return false;
            }
        };
        
        // Function to force assignments directly
        async function forceAssignments(projectId) {
            try {
                // Get project data directly from our fixed endpoint
                const response = await fetch(`api/get_project_details_fixed.php?id=${projectId}`);
                const result = await response.json();
                
                if (result.status !== 'success' || !result.data) {
                    throw new Error('Failed to get project details');
                }
                
                const project = result.data;
                console.log('ULTRA FIX: Project data for direct fix:', project);
                
                // === ULTRA AGGRESSIVE APPROACH ===
                // Instead of trying to modify existing dropdowns, completely rebuild them
                
                // Force the main project assignment
                if (project.assigned_to && project.assigned_to !== '0') {
                    rebuildSelectWithValue('assignTo', project.assigned_to.toString());
                }
                
                // Force stage assignments
                if (project.stages && project.stages.length > 0) {
                    project.stages.forEach((stage, index) => {
                        const stageNum = index + 1;
                        if (stage.assigned_to && stage.assigned_to !== '0') {
                            rebuildSelectWithValue(`assignTo${stageNum}`, stage.assigned_to.toString());
                        }
                        
                        // Force substage assignments
                        if (stage.substages && stage.substages.length > 0) {
                            stage.substages.forEach((substage, subIndex) => {
                                const substageNum = subIndex + 1;
                                if (substage.assigned_to && substage.assigned_to !== '0') {
                                    rebuildSelectWithValue(
                                        `substageAssignTo${stageNum}_${substageNum}`, 
                                        substage.assigned_to.toString()
                                    );
                                }
                            });
                        }
                    });
                }
                
                console.log('ULTRA FIX: Finished applying ULTRA AGGRESSIVE fixes');
            } catch (error) {
                console.error('ULTRA FIX: Error in forceAssignments:', error);
            }
        }
        
        // ULTRA AGGRESSIVE: Completely rebuild select dropdown with our value
        function rebuildSelectWithValue(selectId, targetValue) {
            const select = document.getElementById(selectId);
            if (!select) {
                console.warn(`ULTRA FIX: Select element "${selectId}" not found`);
                return false;
            }
            
            console.log(`ULTRA FIX: REBUILDING "${selectId}" with target value "${targetValue}"`);
            
            // Get current options to preserve them
            const options = Array.from(select.options);
            
            // Create a new select element
            const newSelect = document.createElement('select');
            newSelect.id = selectId;
            newSelect.name = select.name;
            newSelect.className = select.className;
            
            // Add the unassigned option first
            const unassignedOption = document.createElement('option');
            unassignedOption.value = '0';
            unassignedOption.text = 'Unassigned';
            newSelect.appendChild(unassignedOption);
            
            // Add target option if it doesn't exist in original options
            let targetExists = false;
            for (const opt of options) {
                if (opt.value === targetValue) {
                    targetExists = true;
                    break;
                }
            }
            
            if (!targetExists) {
                const targetOption = document.createElement('option');
                targetOption.value = targetValue;
                targetOption.text = `User ID ${targetValue} (forced)`;
                targetOption.className = 'forced-option';
                newSelect.appendChild(targetOption);
            }
            
            // Add all original options (except Unassigned which we already added)
            for (const opt of options) {
                if (opt.value !== '0') {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.text = opt.text;
                    newSelect.appendChild(option);
                }
            }
            
            // Set the target value directly on the new select
            newSelect.value = targetValue;
            
            // Replace the old select with the new one
            select.parentNode.replaceChild(newSelect, select);
            
            // Apply visual styling
            newSelect.style.backgroundColor = '#fff3cd';
            newSelect.style.border = '2px solid #ffc107';
            
            // Reset styling after a delay
            setTimeout(() => {
                newSelect.style.transition = 'all 1s ease';
                newSelect.style.backgroundColor = '';
                newSelect.style.border = '';
            }, 2000);
            
            // Add event listeners as needed (you might need to re-add these)
            if (selectId.startsWith('assignTo') && !selectId.includes('substage')) {
                const stageNum = selectId.replace('assignTo', '');
                if (stageNum) {
                    newSelect.onchange = function() { 
                        if (typeof handleStageAssignChange === 'function') {
                            handleStageAssignChange(stageNum); 
                        }
                    };
                }
            } else if (selectId.includes('substageAssignTo')) {
                newSelect.onchange = function() {
                    if (typeof handleSubstageAssignChange === 'function') {
                        handleSubstageAssignChange(this);
                    }
                };
            }
            
            console.log(`ULTRA FIX: Rebuild complete, value is now "${newSelect.value}"`);
            
            return newSelect.value === targetValue;
        }
        
        // Add styles for forced options
        const style = document.createElement('style');
        style.textContent = `
            .forced-option {
                background-color: #fff3cd !important;
                font-weight: bold !important;
                color: #856404 !important;
                font-style: italic !important;
                text-shadow: 0 0 1px rgba(0,0,0,0.2) !important;
            }
        `;
        document.head.appendChild(style);
        
        console.log('ULTRA FIX: Ultra aggressive direct assignment fix initialized');
    }

    // Function to fix substage rendering
    function fixSubstageRendering() {
        console.log('Running enhanced substage rendering fix');
        
        // Get all stage blocks
        const stageBlocks = document.querySelectorAll('.stage-block');
        
        stageBlocks.forEach((stage, stageIndex) => {
            const stageNum = stageIndex + 1;
            
            // Get substages container
            const substagesContainer = stage.querySelector('.form-substages-container');
            if (!substagesContainer) return;
            
            // Ensure container has proper styling for flexible content
            substagesContainer.style.display = 'block';
            substagesContainer.style.position = 'relative';
            substagesContainer.style.width = '95%';
            substagesContainer.style.marginLeft = 'auto';
            substagesContainer.style.marginRight = 'auto';
            substagesContainer.style.paddingBottom = '50px';
            substagesContainer.style.maxHeight = 'none';
            substagesContainer.style.height = 'auto';
            substagesContainer.style.overflow = 'visible';
            
            // Get all substage blocks in this container
            const substageBlocks = substagesContainer.querySelectorAll('.substage-block');
            const substageCount = substageBlocks.length;
            
            // Set container height dynamically based on content
            substagesContainer.style.minHeight = (300 + (substageCount * 70)) + 'px';
            
            // Assign unique IDs and fix positioning
            substageBlocks.forEach((block, index) => {
                const uniqueId = `substage-${stageNum}-${index+1}`;
                block.id = uniqueId;
                
                // Add unique class for easy targeting
                block.classList.add(`substage-num-${index+1}`);
                
                // Apply critical fixes
                block.style.position = 'relative';
                block.style.display = 'block';
                block.style.width = '100%';
                block.style.boxSizing = 'border-box';
                block.style.clear = 'both';
                block.style.marginBottom = '30px';
                block.style.minHeight = '250px';
                block.style.height = 'auto';
                block.style.overflow = 'visible';
                
                // For better visual grouping, add dividers between groups of 3-4 substages
                if (index > 0 && index % 3 === 0) {
                    block.style.marginTop = '40px';
                    block.style.paddingTop = '20px';
                    block.style.borderTop = '1px dashed #e9ecef';
                }
            });
            
            // Fix the Add Substage button positioning
            const addSubstageBtn = stage.querySelector('.add-substage-btn');
            if (addSubstageBtn) {
                // Move the button outside the substages container to ensure proper position
                if (substagesContainer.parentNode) {
                    substagesContainer.parentNode.appendChild(addSubstageBtn);
                }
                
                // Apply aggressive styling fixes to ensure visibility and proper position
                addSubstageBtn.style.display = 'flex';
                addSubstageBtn.style.width = '95%';
                addSubstageBtn.style.margin = '30px auto 20px auto';
                addSubstageBtn.style.position = 'relative';
                addSubstageBtn.style.clear = 'both';
                addSubstageBtn.style.zIndex = '50';
            }
            
            // Ensure stage has enough space based on substage count
            stage.style.marginBottom = `${Math.max(60, 40 + (substageCount * 15))}px`;
            stage.style.paddingBottom = '30px';
        });
    }
    
    // Watch for changes to the DOM that might affect substage rendering
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && 
                (mutation.target.classList.contains('form-substages-container') || 
                 mutation.target.classList.contains('substage-block'))) {
                fixSubstageRendering();
            }
        });
    });
    
    // Configure and start the observer
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Run the fix when any substage is added or removed
    document.addEventListener('DOMNodeInserted', function(e) {
        if (e.target.classList && e.target.classList.contains('substage-block')) {
            setTimeout(fixSubstageRendering, 100);
        }
    });
    
    // Also run the fix after page load
    window.addEventListener('load', fixSubstageRendering);
    
    // Run the fix after any AJAX operation completes
    var originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = function() {
        var xhr = new originalXHR();
        xhr.addEventListener('load', function() {
            setTimeout(fixSubstageRendering, 200);
        });
        return xhr;
    };

    // Fix substage heights and button positioning
    function emergencySubstageHeightFix() {
        console.log('Running emergency substage height fix');
        
        // Get all stage blocks
        const stageBlocks = document.querySelectorAll('.stage-block');
        
        stageBlocks.forEach((stage, stageIndex) => {
            const stageNum = stageIndex + 1;
            
            // Fix the Add Substage button - move it outside any substage block
            const addSubstageBtn = stage.querySelector('.add-substage-btn');
            
            // Fix the substages container
            const substagesContainer = stage.querySelector('.form-substages-container');
            if (substagesContainer) {
                const substageBlocks = substagesContainer.querySelectorAll('.substage-block');
                const substageCount = substageBlocks.length;
                
                // Set dynamic height based on number of substages
                const baseHeight = 300;
                const heightPerSubstage = 50;
                const dynamicHeight = baseHeight + (substageCount * heightPerSubstage);
                
                // Fix container height and styling
                substagesContainer.style.display = 'block';
                substagesContainer.style.position = 'relative';
                substagesContainer.style.minHeight = dynamicHeight + 'px';
                substagesContainer.style.height = 'auto';
                substagesContainer.style.overflow = 'visible';
                substagesContainer.style.marginBottom = '60px';
                substagesContainer.style.paddingBottom = (50 + (substageCount * 10)) + 'px';
                
                // Apply progressive spacing to substage blocks
                substageBlocks.forEach((block, index) => {
                    // Calculate progressive margin based on position
                    const progressiveMargin = 25 + (Math.floor(index / 3) * 15);
                    
                    // Ensure each substage has proper height and spacing
                    block.style.position = 'relative';
                    block.style.display = 'block';
                    block.style.width = '100%';
                    block.style.boxSizing = 'border-box';
                    block.style.marginBottom = progressiveMargin + 'px';
                    block.style.minHeight = '250px';
                    block.style.height = 'auto';
                    block.style.overflow = 'visible';
                    block.style.clear = 'both';
                    
                    // Apply additional styling for better spacing and visual hierarchy
                    if (index > 0) {
                        block.style.borderTop = '1px dashed #e9ecef';
                        block.style.paddingTop = '15px';
                    }
                    
                    // Special handling for every 4th substage - create visual grouping
                    if (index % 4 === 0 && index > 0) {
                        block.style.marginTop = '40px';
                        block.style.paddingTop = '25px';
                        block.style.borderTop = '2px dashed #ced4da';
                    }
                });
                
                // Ensure proper stage spacing based on substage count
                stage.style.marginBottom = (60 + (substageCount * 10)) + 'px';
                stage.style.paddingBottom = '30px';
            }
            
            // Fix the Add Substage button - ensure it's outside and below the container
            if (addSubstageBtn && substagesContainer) {
                // Ensure the button is a direct child of the stage
                if (addSubstageBtn.parentNode !== stage) {
                    stage.appendChild(addSubstageBtn);
                }
                
                // Calculate the position based on the substage container
                const containerRect = substagesContainer.getBoundingClientRect();
                const stageRect = stage.getBoundingClientRect();
                
                // Apply comprehensive styling to fix button position
                addSubstageBtn.style.position = 'relative';
                addSubstageBtn.style.display = 'flex';
                addSubstageBtn.style.alignItems = 'center';
                addSubstageBtn.style.justifyContent = 'center';
                addSubstageBtn.style.clear = 'both';
                addSubstageBtn.style.width = '95%';
                addSubstageBtn.style.margin = '20px auto 30px auto';
                addSubstageBtn.style.zIndex = '100';
                
                // Ensure the button is visually separated from the last substage
                if (substagesContainer.querySelector('.substage-block')) {
                    addSubstageBtn.style.marginTop = '40px';
                    addSubstageBtn.style.boxShadow = '0 -2px 5px rgba(0,0,0,0.03)';
                }
            }
        });
    }
    
    // Run the fix on page load
    emergencySubstageHeightFix();
    
    // Run the fix when DOM changes
    const observer2 = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                emergencySubstageHeightFix();
            }
        });
    });
    
    // Start observing
    observer2.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Also run fix when a substage is added
    document.addEventListener('click', function(e) {
        if (e.target && 
            (e.target.classList.contains('add-substage-btn') || 
             e.target.closest('.add-substage-btn'))) {
            // Wait for DOM to update
            setTimeout(emergencySubstageHeightFix, 300);
        }
    });
    
    // Monkey patch the addSubstage function to ensure our fix runs
    if (typeof window.addSubstage === 'function') {
        const originalAddSubstage = window.addSubstage;
        window.addSubstage = function(stageNum) {
            const result = originalAddSubstage(stageNum);
            
            // Apply multiple fixes with increasing delays
            [100, 300, 600, 1000].forEach(delay => {
                setTimeout(() => {
                    emergencySubstageHeightFix();
                    fixSubstageRendering();
                }, delay);
            });
            
            return result;
        };
    }

    // Add a new advanced rendering fix that runs on a timer
    function setupAdvancedRenderingFix() {
        // Run an aggressive fix every second for the first 10 seconds after page load
        let fixCount = 0;
        const maxFixes = 10;
        
        const fixInterval = setInterval(() => {
            fixCount++;
            emergencySubstageHeightFix();
            fixSubstageRendering();
            
            if (fixCount >= maxFixes) {
                clearInterval(fixInterval);
                console.log('Completed timed substage rendering fixes');
            }
        }, 1000);
        
        // When adding new substages, apply fixes with increasing delays
        const origAddSubstage = window.addSubstage;
        if (typeof origAddSubstage === 'function') {
            window.addSubstage = function(stageNum) {
                const result = origAddSubstage(stageNum);
                
                // Apply multiple fixes with increasing delays
                [100, 300, 600, 1000].forEach(delay => {
                    setTimeout(() => {
                        emergencySubstageHeightFix();
                        fixSubstageRendering();
                    }, delay);
                });
                
                return result;
            };
        }
    }

    // Initialize the advanced fix system
    window.addEventListener('load', setupAdvancedRenderingFix);

    // Substage Height & Position Fix
    // This function corrects the height and positioning of substages, particularly when there are 5 or more
    function fixSubstageLayout() {
        // Find all stage blocks
        const stageBlocks = document.querySelectorAll('.stage-block');
        
        // Process each stage block
        stageBlocks.forEach(stage => {
            const substages = stage.querySelectorAll('.substage-block');
            const substageContainer = stage.querySelector('.form-substages-container');
            const addSubstageBtn = stage.querySelector('.add-substage-btn');
            
            if (!substageContainer || !addSubstageBtn) return;
            
            // Reset any previously applied styles
            substageContainer.style.height = '';
            substageContainer.style.minHeight = '';
            addSubstageBtn.style.position = '';
            addSubstageBtn.style.marginTop = '';
            
            // If we have 4 or more substages, apply enhanced layout
            if (substages.length >= 4) {
                // Calculate the total height needed
                let totalHeight = 0;
                substages.forEach(substage => {
                    totalHeight += substage.offsetHeight + 30; // Adding margin
                });
                
                // Set container min-height to ensure it contains all substages
                substageContainer.style.minHeight = (totalHeight + 60) + 'px';
                substageContainer.style.overflow = 'visible';
                substageContainer.style.height = 'auto';
                
                // Position the Add Substage button properly
                addSubstageBtn.style.position = 'relative';
                addSubstageBtn.style.marginTop = '30px';
                
                // Apply special styling to the 5th substage and beyond
                if (substages.length >= 5) {
                    substages.forEach((substage, index) => {
                        if (index >= 4) {
                            substage.style.position = 'relative';
                            substage.style.marginTop = '30px';
                            substage.style.display = 'block';
                            substage.style.clear = 'both';
                        }
                    });
                    
                    // Ensure the stage block is tall enough
                    stage.style.paddingBottom = '40px';
                    stage.style.marginBottom = '40px';
                }
            }
        });
    }

    // Apply the fix after page load, after DOM updates, and when images load
    document.addEventListener('DOMContentLoaded', function() {
        // Initial fix application
        fixSubstageLayout();
        
        // Set up a MutationObserver to detect when substages are added or removed
        const stagesContainer = document.querySelector('.stages-container');
        if (stagesContainer) {
            const observer = new MutationObserver(function(mutations) {
                fixSubstageLayout();
            });
            
            observer.observe(stagesContainer, { 
                childList: true, 
                subtree: true, 
                attributes: true, 
                attributeFilter: ['style', 'class'] 
            });
        }
        
        // Apply fix after any ajax completions (for dynamically loaded content)
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ajaxComplete(function() {
                setTimeout(fixSubstageLayout, 100);
            });
        }
        
        // Listen for window resize events
        window.addEventListener('resize', function() {
            fixSubstageLayout();
        });
        
        // Apply fix when images load (which can affect layout)
        window.addEventListener('load', function() {
            fixSubstageLayout();
        });
        
        // Handle click on the add substage button
        document.addEventListener('click', function(e) {
            if (e.target.matches('.add-substage-btn') || e.target.closest('.add-substage-btn')) {
                // Wait for the new substage to be added to the DOM
                setTimeout(fixSubstageLayout, 100);
            }
        });
    });

    // Emergency fix function that can be called directly when needed
    function emergencySubstageHeightFix() {
        console.log("Applying emergency substage height fix");
        fixSubstageLayout();
        
        // Force recalculation of all substage containers
        const substageContainers = document.querySelectorAll('.form-substages-container');
        substageContainers.forEach(container => {
            const substages = container.querySelectorAll('.substage-block');
            let maxHeight = 0;
            
            // Find the tallest substage
            substages.forEach(substage => {
                substage.style.height = 'auto';
                const height = substage.offsetHeight;
                if (height > maxHeight) maxHeight = height;
            });
            
            // Apply progressive spacing based on the number of substages
            const totalSubstages = substages.length;
            if (totalSubstages >= 5) {
                container.style.paddingBottom = (40 + (totalSubstages - 4) * 15) + 'px';
                
                // Apply progressive margins to substage blocks
                substages.forEach((substage, index) => {
                    if (index >= 4) {
                        const progressiveMargin = 25 + (index - 4) * 5;
                        substage.style.marginBottom = progressiveMargin + 'px';
                    }
                });
            }
            
            // Ensure the Add Substage button is correctly positioned
            const parentStage = container.closest('.stage-block');
            if (parentStage) {
                const addBtn = parentStage.querySelector('.add-substage-btn');
                if (addBtn) {
                    addBtn.style.position = 'relative';
                    addBtn.style.zIndex = '10';
                    addBtn.style.display = 'flex';
                    addBtn.style.marginTop = '30px';
                    addBtn.style.marginBottom = '20px';
                }
            }
        });
    }

    // Expose the emergency fix to the global scope
    window.emergencySubstageHeightFix = emergencySubstageHeightFix;
}); 