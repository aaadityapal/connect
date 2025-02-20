// Test file for debugging substage validation

function testSubstageValidation() {
    console.group('🧪 Testing Substage Validation');
    
    // Test data structure
    const mockStageBlock = document.createElement('div');
    mockStageBlock.className = 'stage-block';
    mockStageBlock.innerHTML = `
        <textarea class="stage-description">Test Stage</textarea>
        <select class="stage-assignee"><option value="1">Test Assignee</option></select>
        <input type="datetime-local" class="stage-start-date" value="2024-03-14T10:00">
        <input type="datetime-local" class="stage-due-date" value="2024-03-15T10:00">
        <div class="substage-block">
            <select class="substage-drawing-type">
                <option value="">Select Drawing Type</option>
                <!-- Will be populated by updateDrawingOptions -->
            </select>
            <select class="substage-assignee"><option value="1">Test Assignee</option></select>
            <input type="datetime-local" class="substage-start-date" value="2024-03-14T10:00">
            <input type="datetime-local" class="substage-due-date" value="2024-03-15T10:00">
        </div>
    `;

    document.body.appendChild(mockStageBlock);

    // Test Cases
    try {
        console.group('Test Case 1: Empty Drawing Type');
        const result1 = validateSubstage(mockStageBlock.querySelector('.substage-block'), 0, 0);
        console.log('❌ Test should have thrown an error for empty drawing type');
    } catch (error) {
        console.log('✅ Correctly caught empty drawing type:', error.message);
    }
    console.groupEnd();

    // Populate drawing type and test again
    const drawingTypeSelect = mockStageBlock.querySelector('.substage-drawing-type');
    drawingTypeSelect.innerHTML = drawingOptions.architecture;
    drawingTypeSelect.value = 'concept_plan';

    try {
        console.group('Test Case 2: Valid Drawing Type');
        const result2 = validateSubstage(mockStageBlock.querySelector('.substage-block'), 0, 0);
        console.log('✅ Valid substage data:', result2);
    } catch (error) {
        console.log('❌ Unexpected error with valid data:', error.message);
    }
    console.groupEnd();

    // Test drawing options update
    console.group('Test Case 3: Drawing Options Update');
    console.log('Initial options count:', drawingTypeSelect.options.length);
    updateDrawingOptions('interior');
    console.log('Updated options count:', drawingTypeSelect.options.length);
    console.log('Options updated successfully:', drawingTypeSelect.innerHTML.includes('concept_board'));
    console.groupEnd();

    // Clean up
    document.body.removeChild(mockStageBlock);
    console.groupEnd();
}

// Validation helper function
function validateSubstage(substageBlock, substageIndex, stageIndex) {
    console.group(`Validating Substage ${substageIndex + 1} in Stage ${stageIndex + 1}`);
    
    const drawingTypeSelect = substageBlock.querySelector('.substage-drawing-type');
    console.log('Drawing Type Select Element:', drawingTypeSelect);
    console.log('Drawing Type Value:', drawingTypeSelect?.value);
    
    const assigneeSelect = substageBlock.querySelector('.substage-assignee');
    console.log('Assignee Select Element:', assigneeSelect);
    console.log('Assignee Value:', assigneeSelect?.value);
    
    const startDate = substageBlock.querySelector('.substage-start-date');
    console.log('Start Date Element:', startDate);
    console.log('Start Date Value:', startDate?.value);
    
    const dueDate = substageBlock.querySelector('.substage-due-date');
    console.log('Due Date Element:', dueDate);
    console.log('Due Date Value:', dueDate?.value);

    if (!drawingTypeSelect || !drawingTypeSelect.value) {
        throw new Error(`Error: Substage ${substageIndex + 1} in Stage ${stageIndex + 1} is incomplete.\nMissing: title`);
    }

    const substageData = {
        title: drawingTypeSelect.value,
        assignee: assigneeSelect?.value,
        startDate: startDate?.value,
        dueDate: dueDate?.value
    };

    console.log('Validated Substage Data:', substageData);
    console.groupEnd();
    return substageData;
}

// DOM Ready handler
document.addEventListener('DOMContentLoaded', () => {
    // Add test button to page
    const testButton = document.createElement('button');
    testButton.textContent = 'Run Substage Validation Tests';
    testButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 10px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        z-index: 9999;
    `;
    testButton.onclick = testSubstageValidation;
    document.body.appendChild(testButton);
});

// Helper function to log DOM structure
function logDOMStructure(element, indent = 0) {
    console.log(' '.repeat(indent) + '└─', element.tagName.toLowerCase(), {
        class: element.className,
        id: element.id,
        value: element.value
    });
    
    Array.from(element.children).forEach(child => {
        logDOMStructure(child, indent + 2);
    });
}

// Add this to your test file
window.debugSubstageStructure = function() {
    console.group('🔍 Debugging Substage Structure');
    
    const stages = document.querySelectorAll('.stage-block');
    console.log(`Found ${stages.length} stages`);
    
    stages.forEach((stage, stageIndex) => {
        console.group(`Stage ${stageIndex + 1}`);
        
        const substages = stage.querySelectorAll('.substage-block');
        console.log(`Found ${substages.length} substages`);
        
        substages.forEach((substage, substageIndex) => {
            console.group(`Substage ${substageIndex + 1}`);
            logDOMStructure(substage);
            
            const drawingType = substage.querySelector('.substage-drawing-type');
            console.log('Drawing Type Element:', drawingType);
            console.log('Drawing Type Value:', drawingType?.value);
            console.log('Drawing Type Options:', drawingType?.innerHTML);
            
            console.groupEnd();
        });
        
        console.groupEnd();
    });
    
    console.groupEnd();
}; 