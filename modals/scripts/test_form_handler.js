// Test file to diagnose JSON errors
document.addEventListener('DOMContentLoaded', function() {
    // Add test button to the form
    const form = document.getElementById('createProjectForm');
    const testButton = document.createElement('button');
    testButton.type = 'button';
    testButton.className = 'btn-secondary';
    testButton.innerHTML = 'Test Form Data';
    testButton.style.marginRight = '10px';
    
    // Insert test button before submit button
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.parentNode.insertBefore(testButton, submitButton);

    // Add test handler
    testButton.addEventListener('click', async function() {
        try {
            console.group('Form Data Test');
            
            // 1. Test basic form fields
            console.log('Testing basic form fields...');
            const basicData = {
                projectId: currentProjectId,
                projectTitle: document.getElementById('projectTitle')?.value,
                projectDescription: document.getElementById('projectDescription')?.value,
                projectType: document.getElementById('projectType')?.value,
                projectCategory: document.getElementById('projectCategory')?.value,
                startDate: document.getElementById('startDate')?.value,
                dueDate: document.getElementById('dueDate')?.value,
                assignTo: document.getElementById('assignTo')?.value
            };
            console.log('Basic form data:', basicData);

            // 2. Test stages
            console.log('\nTesting stages...');
            const stageBlocks = document.querySelectorAll('.stage-block');
            stageBlocks.forEach((stage, index) => {
                const stageNum = stage.dataset.stage;
                console.log(`\nStage ${stageNum} raw data:`, {
                    assignTo: document.getElementById(`assignTo${stageNum}`)?.value,
                    startDate: document.getElementById(`startDate${stageNum}`)?.value,
                    dueDate: document.getElementById(`dueDate${stageNum}`)?.value
                });
            });

            // 3. Test substages
            console.log('\nTesting substages...');
            stageBlocks.forEach((stage) => {
                const stageNum = stage.dataset.stage;
                const substages = stage.querySelectorAll('.substage-block');
                substages.forEach((substage) => {
                    const substageNum = substage.dataset.substage;
                    console.log(`\nSubstage ${stageNum}_${substageNum} raw data:`, {
                        title: document.getElementById(`substageTitle${stageNum}_${substageNum}`)?.value,
                        assignTo: document.getElementById(`substageAssignTo${stageNum}_${substageNum}`)?.value,
                        startDate: document.getElementById(`substageStartDate${stageNum}_${substageNum}`)?.value,
                        dueDate: document.getElementById(`substageDueDate${stageNum}_${substageNum}`)?.value
                    });
                });
            });

            // 4. Test data cleaning
            console.log('\nTesting data cleaning...');
            const cleanData = {
                ...basicData,
                stages: Array.from(stageBlocks).map(stage => {
                    const stageNum = stage.dataset.stage;
                    return {
                        id: stage.dataset.stageId ? parseInt(stage.dataset.stageId) : null,
                        stage_number: parseInt(stageNum),
                        assignTo: cleanString(document.getElementById(`assignTo${stageNum}`)?.value),
                        startDate: document.getElementById(`startDate${stageNum}`)?.value,
                        dueDate: document.getElementById(`dueDate${stageNum}`)?.value,
                        substages: Array.from(stage.querySelectorAll('.substage-block')).map(substage => {
                            const substageNum = substage.dataset.substage;
                            return {
                                id: substage.dataset.substageId ? parseInt(substage.dataset.substageId) : null,
                                substage_number: parseInt(substageNum),
                                title: cleanString(document.getElementById(`substageTitle${stageNum}_${substageNum}`)?.value),
                                assignTo: cleanString(document.getElementById(`substageAssignTo${stageNum}_${substageNum}`)?.value),
                                startDate: document.getElementById(`substageStartDate${stageNum}_${substageNum}`)?.value,
                                dueDate: document.getElementById(`substageDueDate${stageNum}_${substageNum}`)?.value
                            };
                        })
                    };
                })
            };
            console.log('Clean data:', cleanData);

            // 5. Test JSON conversion
            console.log('\nTesting JSON conversion...');
            const jsonString = JSON.stringify(cleanData, null, 2);
            console.log('JSON string:', jsonString);

            // 6. Test JSON parsing
            console.log('\nTesting JSON parsing...');
            const parsedBack = JSON.parse(jsonString);
            console.log('Parsed back successfully:', !!parsedBack);

            // 7. Check for HTML tags
            console.log('\nChecking for HTML tags...');
            const htmlTagCheck = /<[^>]*>/g.test(jsonString);
            console.log('Contains HTML tags:', htmlTagCheck);
            if (htmlTagCheck) {
                console.warn('HTML tags found in JSON string!');
            }

            console.groupEnd();
            
            // Show success message if all tests pass
            alert('Form data test completed. Check console for details.');

        } catch (error) {
            console.error('Test Error:', error);
            alert('Error in form data test. Check console for details.');
        }
    });

    // Helper function to clean strings
    function cleanString(str) {
        if (!str) return '';
        return String(str)
            .replace(/<[^>]*>/g, '')  // Remove HTML tags
            .replace(/[^\w\s-.,]/g, '') // Only allow safe characters
            .trim();
    }
}); 