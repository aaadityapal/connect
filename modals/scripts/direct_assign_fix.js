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
}); 