const state = {
    currentDate: new Date(),
    tasks: [],
    currentSite: 'site1'
};

// Helper to get date string relative to current month
// dayOffset: 1 = 1st day of current month
function getRelDate(dayOffset) {
    const d = new Date();
    d.setDate(dayOffset);
    return formatDate(d);
}

document.addEventListener('DOMContentLoaded', () => {
    init();
});

function init() {
    // Event Listeners
    document.getElementById('prevMonth').addEventListener('click', () => changeMonth(-1));
    document.getElementById('nextMonth').addEventListener('click', () => changeMonth(1));
    document.getElementById('todayBtn').addEventListener('click', () => {
        state.currentDate = new Date();
        render();
    });

    document.getElementById('addTaskBtn').addEventListener('click', () => openModal());
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);

    document.getElementById('taskForm').addEventListener('submit', handleFormSubmit);

    // Image Upload Listener
    const fileInput = document.getElementById('taskImages');
    if (fileInput) {
        fileInput.addEventListener('change', handleImageSelect);
    }

    // Site Selector Listener - Load projects from API
    const siteSelect = document.getElementById('siteSelect');
    if (siteSelect) {
        loadProjectsFromAPI().then(() => {
            // After projects load, render the calendar
            render();
        });
        loadUsersForAssignee(); // Load users for assignee dropdown
        siteSelect.addEventListener('change', (e) => {
            state.currentSite = e.target.value;
            render(); // Re-render with new site data
        });
    } else {
        // Fallback if siteSelect doesn't exist
        render();
    }

    // Initial Load removed - now called after projects load
}

async function loadProjectsFromAPI() {
    try {
        const response = await fetch('site/get_projects.php');
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            const siteSelect = document.getElementById('siteSelect');
            siteSelect.innerHTML = ''; // Clear loading option
            
            result.data.forEach((project) => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.title;
                siteSelect.appendChild(option);
            });
            
            // Set first project as default
            if (result.data.length > 0) {
                state.currentSite = result.data[0].id;
                siteSelect.value = state.currentSite;
            }
            
            // Store projects for later use
            window.projectsData = result.data;
        } else {
            document.getElementById('siteSelect').innerHTML = '<option value="">No construction projects found</option>';
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        document.getElementById('siteSelect').innerHTML = '<option value="">Error loading projects</option>';
    }
}

async function loadUsersForAssignee() {
    try {
        const response = await fetch('site/get_users.php');
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            const assignToSelect = document.getElementById('taskAssignTo');
            
            // Keep the default option
            assignToSelect.innerHTML = '<option value="">Select an assignee...</option>';
            
            result.data.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.username;
                option.setAttribute('data-user-id', user.id);
                option.textContent = user.username;
                assignToSelect.appendChild(option);
            });
            
            // Store users for later use
            window.usersData = result.data;
        } else {
            console.warn('No users found or error loading users');
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Image state
let currentTaskImages = [];

function handleImageSelect(e) {
    const files = e.target.files;
    if (!files.length) return;

    Array.from(files).forEach(file => {
        // Store file objects instead of base64
        currentTaskImages.push({
            name: file.name,
            file: file,
            type: file.type
        });
        renderImagePreviews();
    });

    // reset input
    e.target.value = '';
}

function renderImagePreviews() {
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = '';

    currentTaskImages.forEach((img, index) => {
        const div = document.createElement('div');
        div.className = 'preview-item';
        
        // Check if it's a file object or a URL string
        const imgSrc = img.file ? URL.createObjectURL(img.file) : img;
        
        div.innerHTML = `
            <img src="${imgSrc}" alt="Attachment">
            <button type="button" class="remove-btn" onclick="removeImage(${index})">&times;</button>
        `;
        container.appendChild(div);
    });
}

function removeImage(index) {
    currentTaskImages.splice(index, 1);
    renderImagePreviews();
}

// Ensure removeImage is global
window.removeImage = removeImage;

function changeMonth(delta) {
    state.currentDate.setMonth(state.currentDate.getMonth() + delta);
    render();
}

async function render() {
    updateHeader();
    await fetchTasks();
    renderCalendar();
}

function updateHeader() {
    const options = { month: 'long', year: 'numeric' };
    document.getElementById('currentMonthYear').textContent = state.currentDate.toLocaleDateString('en-US', options);
}

async function fetchTasks() {
    // Fetch tasks for the selected project/site from database
    if (!state.currentSite) {
        state.tasks = [];
        return;
    }
    
    try {
        const response = await fetch(`site/get_tasks.php?project_id=${state.currentSite}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            state.tasks = result.data;
        } else {
            state.tasks = [];
        }
    } catch (error) {
        console.error('Error fetching tasks:', error);
        state.tasks = [];
    }
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    const year = state.currentDate.getFullYear();
    const month = state.currentDate.getMonth();

    // First day of the month
    const firstDay = new Date(year, month, 1);
    // Last day of the month
    const lastDay = new Date(year, month + 1, 0);

    // JS getDay(): 0=Sun, 1=Mon. We want Mon start.
    let startDayIndex = firstDay.getDay() - 1;
    if (startDayIndex === -1) startDayIndex = 6; // Sunday

    const totalDays = lastDay.getDate();

    // Pad previous month
    for (let i = 0; i < startDayIndex; i++) {
        const cell = document.createElement('div');
        cell.className = 'calendar-day empty';
        grid.appendChild(cell);
    }

    // Render Actual Days
    for (let day = 1; day <= totalDays; day++) {
        const cell = document.createElement('div');
        cell.className = 'calendar-day';

        // Check if today
        const todayCheck = new Date();
        if (day === todayCheck.getDate() && month === todayCheck.getMonth() && year === todayCheck.getFullYear()) {
            cell.classList.add('today');
        }

        // Date Number
        const num = document.createElement('div');
        num.className = 'day-number';
        num.textContent = day;
        cell.appendChild(num);

        // Render Tasks for this day
        const currentDayDateStr = formatDate(new Date(year, month, day));
        // Also get today's date string for comparison
        const todayStr = formatDate(new Date());

        const dayTasks = state.tasks.filter(task => {
            return currentDayDateStr >= task.start_date && currentDayDateStr <= task.end_date;
        });

        // Sort tasks: put delayed/late/blocked first for visibility?
        // simple sort by status?
        dayTasks.sort((a, b) => {
            if (a.status === 'blocked') return -1;
            return 0;
        });

        dayTasks.forEach(task => {
            const chip = document.createElement('div');

            // Determine if delayed: End date is in past AND status is NOT completed
            let isDelayed = false;
            // Note: simple string comparison works for ISO YYYY-MM-DD
            if (task.status !== 'completed' && task.end_date < todayStr) {
                isDelayed = true;
            }

            chip.className = `task-chip ${task.status} ${isDelayed ? 'delayed' : ''}`;

            // Icon
            const iconSvg = getTaskIcon(task.title);
            chip.innerHTML = `${iconSvg} <span>${task.title}</span>`;

            // Tooltip for quick info
            chip.title = `${task.title}\nStatus: ${task.status}${isDelayed ? ' (DELAYED)' : ''}\n${task.end_date < todayStr ? `Due: ${task.end_date}` : ''}\n${task.description || ''}`;

            chip.addEventListener('click', (e) => {
                e.stopPropagation();
                openModal(null, task); // Edit mode
            });
            cell.appendChild(chip);
        });

        // Click on empty cell to add task starting that day
        cell.addEventListener('click', () => {
            openModal(new Date(year, month, day));
        });

        grid.appendChild(cell);
    }
}

function getTaskIcon(title) {
    const t = title.toLowerCase();
    // Simple SVG icons (14x14)
    if (t.includes('survey') || t.includes('staking')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/></svg>';
    if (t.includes('excavation') || t.includes('digging') || t.includes('shed')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6"/><path d="M3 7V3h4l8 8a2 2 0 0 1 0 2.8l-6.4 6.4a2 2 0 0 1-2.8 0L3 7z"/></svg>';
    if (t.includes('foundation') || t.includes('concrete') || t.includes('cement') || t.includes('slab')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 22h20"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="2" width="12" height="8" rx="2"/></svg>';
    if (t.includes('plumbing') || t.includes('water')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.74 5.88a5.81 5.81 0 0 1-8.21 8.21l-5.88-5.74a5.81 5.81 0 0 1 8.35-8.35z"/><path d="M11 13a6 6 0 0 0-6 6"/></svg>';
    if (t.includes('electrical') || t.includes('wiring') || t.includes('conduit')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
    if (t.includes('painting') || t.includes('glazing')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22v-8"/><path d="M5 2h14"/><path d="M22 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2"/><path d="M14 14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2"/></svg>';
    if (t.includes('delivery') || t.includes('material')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><line x1="16" y1="8" x2="20" y2="8"/><line x1="16" y1="16" x2="23" y2="16"/><path d="M16 12h4"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="10.5" cy="18.5" r="2.5"/></svg>';
    if (t.includes('fencing') || t.includes('column') || t.includes('frame')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 2v20"/><path d="M8 2v20"/><path d="M12 2v20"/><path d="M16 2v20"/><path d="M20 2v20"/><path d="M2 12h20"/><path d="M2 6h20"/><path d="M2 18h20"/></svg>';

    // Default
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
}

function openModal(date = null, task = null) {
    const modal = document.getElementById('taskModal');
    const form = document.getElementById('taskForm');
    const title = document.getElementById('modalTitle');

    // Reset form & images
    form.reset();
    document.getElementById('taskId').value = '';
    currentTaskImages = []; // reset images
    renderImagePreviews();

    if (task) {
        // Edit Mode
        title.textContent = 'Edit Task';
        modal.setAttribute('data-mode', 'edit');
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title;
        document.getElementById('taskStart').value = task.start_date;
        document.getElementById('taskEnd').value = task.end_date;
        document.getElementById('taskStatus').value = task.status;
        document.getElementById('taskDesc').value = task.description || '';
        document.getElementById('taskAssignTo').value = task.assign_to || '';

        // Load existing images if any
        if (task.images && Array.isArray(task.images)) {
            currentTaskImages = [...task.images];
            renderImagePreviews();
        }
    } else {
        // Add Mode
        title.textContent = 'Add New Task';
        modal.setAttribute('data-mode', 'add');
        if (date) {
            const dateStr = formatDate(date);
            document.getElementById('taskStart').value = dateStr;
            document.getElementById('taskEnd').value = dateStr;
        } else {
            const todayStr = formatDate(new Date());
            document.getElementById('taskStart').value = todayStr;
            document.getElementById('taskEnd').value = todayStr;
        }
    }

    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('taskModal').classList.remove('active');
}

function handleFormSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Add project ID
    data.project_id = state.currentSite;
    
    // Remove empty taskId for new tasks
    if (!data.id || data.id === '') {
        delete data.id;
    }

    // Upload images first if any exist
    if (currentTaskImages.length > 0) {
        uploadImages().then((uploadedImages) => {
            // Add uploaded image paths to data
            data.images = uploadedImages;
            // Send to database API
            saveTaskToDatabase(data);
        }).catch((error) => {
            alert('Error uploading images: ' + error);
        });
    } else {
        // No images to upload
        data.images = [];
        saveTaskToDatabase(data);
    }
}

async function uploadImages() {
    if (currentTaskImages.length === 0) {
        return [];
    }
    
    const formData = new FormData();
    
    // Add only file objects (new images)
    currentTaskImages.forEach((img) => {
        if (img.file) {
            formData.append('images[]', img.file);
        }
    });
    
    try {
        const response = await fetch('site/upload_images.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Return array of file paths
            return result.files.map(f => f.path);
        } else {
            throw new Error(result.error || 'Upload failed');
        }
    } catch (error) {
        throw error;
    }
}

async function saveTaskToDatabase(data) {
    try {
        console.log('Saving task data:', data); // Debug log
        
        const response = await fetch('site/save_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        console.log('API Response:', result); // Debug log

        if (result.success) {
            closeModal();
            render();
            alert('Task saved successfully!');
        } else {
            alert('Error saving task: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving task:', error);
        alert('Error saving task. Please check the console for details.');
    }
}

function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}
