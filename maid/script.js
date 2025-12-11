document.addEventListener('DOMContentLoaded', () => {
    const timeDisplay = document.querySelector('.time');
    const dateDisplay = document.querySelector('.date');
    const punchInBtn = document.getElementById('punchInBtn');
    const punchOutBtn = document.getElementById('punchOutBtn');
    const statusText = document.getElementById('statusText');

    function updateDateTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const timeString = `${hours}:${minutes}`;
        timeDisplay.textContent = timeString;

        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        dateDisplay.textContent = now.toLocaleDateString('en-US', options);
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();

    // Check Local Storage for state logic consistent with punch-modal.js
    function checkPunchStatus() {
        const lastPunchIn = localStorage.getItem('lastPunchIn');
        let isPunchedIn = false;

        if (lastPunchIn) {
            try {
                const punchData = JSON.parse(lastPunchIn);
                const today = new Date().toLocaleDateString('en-IN');
                // Note: punch-modal.js uses en-IN format for date comparison

                if (punchData.date === today && !punchData.punchOutTime) {
                    isPunchedIn = true;
                }
            } catch (e) {
                console.error("Error parsing auth data", e);
            }
        }
        updateUI(isPunchedIn);
    }

    function updateUI(isPunchedIn, punchTime = null) {
        if (isPunchedIn) {
            punchInBtn.style.display = 'none';
            punchOutBtn.style.display = 'flex';
            // Also add event listener for punch out if needed, but punch-modal handles the click on punchOutBtn too?
            // punch-modal.js logic:
            // if (punchBtn) punchBtn.addEventListener('click', () => this.openModal());
            // It only attaches to 'punchInBtn'.
            // Wait, punch-modal.js:
            // const punchBtn = document.getElementById('punchInBtn');
            // It does NOT attach to punchOutBtn in setupEventListeners?
            // Let's check punch-modal.js content again.
            // check lines 262-268 of punch-modal.js
            // It ONLY attaches to punchInBtn.

            // If I hide punchInBtn and show punchOutBtn, and punchOutBtn has NO listener, then nothing happens.
            // I need to make sure punchOutBtn ALSO opens the modal!

            // Correction: punch-modal.js determines if it's punch in or out based on STATE, not which button clicked.
            // But if I click punchOutBtn, I need to trigger openModal().
        } else {
            punchInBtn.style.display = 'flex';
            punchOutBtn.style.display = 'none';
            statusText.textContent = "You are currently punched out.";
            statusText.style.color = "var(--text-muted)";
            statusText.style.borderColor = "#e5e7eb";
        }

        // Status text update for punch in
        if (isPunchedIn) {
            if (punchTime) {
                statusText.textContent = `You are currently punched in at ${punchTime}.`;
            } else {
                statusText.textContent = "You are currently punched in.";
            }
            statusText.style.color = "var(--success-color)";
            statusText.style.borderColor = "var(--success-color)";
        }
    }

    // Listen for custom events from PunchInModal
    document.addEventListener('punchInSuccess', (e) => {
        console.log('Punch In Success Event:', e.detail);
        const punchTime = e.detail.time || null;
        updateUI(true, punchTime);
    });

    document.addEventListener('punchOutSuccess', (e) => {
        console.log('Punch Out Success Event:', e.detail);
        updateUI(false);
    });

    // Add click listener for Punch Out button to open modal too
    if (punchOutBtn) {
        punchOutBtn.addEventListener('click', () => {
            // We need access to the modal instance or just trigger the click on punchInBtn if that works?
            // Or better, trigger the modal open logic.
            // Since PunchInModal is not exposed globally, we can't call methods on it easily unless we assigned it to window.
            // punch-modal.js: creates `new PunchInModal()` but doesn't assign to variable.
            // BUT, punch-modal.js checks `isPunchedIn` state internally.
            // If we just trigger a click on `punchInBtn`, it will open the modal.
            // BUT `punchInBtn` is hidden!
            // Clicking a hidden element might not work or is hacky.

            // Solution: Modify maid/punch-modal.js to ALSO listen to punchOutBtn.
            // I already wrote punch-modal.js. I should update it or find a workaround.
            // Workaround: Trigger the click on punchInBtn even if hidden? Yes, programmatic click works on hidden elements usually.

            if (punchInBtn) punchInBtn.click();
        });
    }

    // Check initial status
    // Check initial status via PunchInModal events, do not check manually here to avoid race conditions
    // checkPunchStatus(); // Logic moved to punch-modal.js which will emit events
});
