// Greeting Module - Handles greeting display, time update, and punch-in functionality

class GreetingManager {
    constructor() {
        this.usernameElement = document.getElementById('username');
        this.greetingMsgElement = document.getElementById('greeting-msg');
        this.dateElement = document.getElementById('date-info');
        this.timeElement = document.getElementById('time-info');
        this.punchBtn = document.getElementById('punchInBtn');
        
        this.init();
    }

    init() {
        // Set initial values
        this.updateUsername();
        this.updateDateTime();
        
        // Update time every second
        setInterval(() => this.updateDateTime(), 1000);
        
        // Setup punch-in button
        this.setupPunchButton();
        
        // Update punch button state on load
        this.updatePunchButtonState();
    }

    /**
     * Get greeting text based on current IST time
     */
    getGreeting() {
        const now = new Date();
        const istTimeString = new Intl.DateTimeFormat('en-IN', {
            hour: '2-digit',
            hour12: false,
            timeZone: 'Asia/Kolkata'
        }).format(now);
        const hour = parseInt(istTimeString);

        if (hour < 12) {
            return 'Good Morning';
        } else if (hour < 18) {
            return 'Good Afternoon';
        } else {
            return 'Good Evening';
        }
    }

    /**
     * Get current time in IST (UTC+5:30)
     */
    getISTTime() {
        const now = new Date();
        // Use Intl API to get IST formatted time
        const formatter = new Intl.DateTimeFormat('en-IN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Kolkata'
        });
        
        // Parse the formatted date string
        const parts = formatter.formatToParts(now);
        const istDate = new Date(
            parseInt(parts[4].value),
            parseInt(parts[2].value) - 1,
            parseInt(parts[0].value),
            parseInt(parts[6].value),
            parseInt(parts[8].value),
            parseInt(parts[10].value)
        );
        
        return istDate;
        return istTime;
    }

    /**
     * Format date according to IST
     */
    formatISTDate(date) {
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: 'Asia/Kolkata'
        };

        return new Intl.DateTimeFormat('en-IN', options).format(date);
    }

    /**
     * Format time according to IST
     */
    formatISTTime(date) {
        const options = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata'
        };

        return new Intl.DateTimeFormat('en-IN', options).format(date);
    }

    /**
     * Update username display
     */
    updateUsername() {
        // Get username from session (passed via window.currentUsername) or use default
        const username = window.currentUsername || 'User';
        
        if (this.usernameElement) {
            this.usernameElement.textContent = username;
        }
    }

    /**
     * Update date and time display with IST
     */
    updateDateTime() {
        const now = new Date();
        const greeting = this.getGreeting();
        const istDate = this.formatISTDate(now);
        const istTime = this.formatISTTime(now);
        
        // Update greeting message
        if (this.greetingMsgElement) {
            this.greetingMsgElement.textContent = greeting;
        }
        
        // Update date
        if (this.dateElement) {
            this.dateElement.textContent = istDate;
        }
        
        // Update time
        if (this.timeElement) {
            this.timeElement.textContent = istTime + ' IST';
        }
    }

    /**
     * Setup punch-in button functionality
     */
    setupPunchButton() {
        if (!this.punchBtn) return;

        // Modal will handle the punch-in click
        // Update button state when punch-in/out events occur
        document.addEventListener('punchInSuccess', () => {
            this.updatePunchButtonState();
        });

        document.addEventListener('punchOutSuccess', () => {
            this.updatePunchButtonState();
        });

        // Listen for external button state update requests
        document.addEventListener('updatePunchButtonState', () => {
            this.updatePunchButtonState();
        });
    }

    /**
     * Update punch button state based on whether already punched in
     */
    updatePunchButtonState() {
        if (!this.punchBtn) return;
        
        const lastPunchIn = localStorage.getItem('lastPunchIn');
        const today = new Date().toLocaleDateString('en-IN');

        if (lastPunchIn) {
            try {
                const punchData = JSON.parse(lastPunchIn);
                const punchDate = punchData.date;

                // Check if punch-in is from today AND punch-out hasn't happened
                if (punchDate === today && !punchData.punchOutTime) {
                    // User is punched in - show punch out state
                    this.punchBtn.disabled = false;
                    this.punchBtn.style.opacity = '1';
                    this.punchBtn.style.cursor = 'pointer';
                    this.punchBtn.innerHTML = '<i data-feather="log-out"></i><span>Punch Out</span>';
                    this.punchBtn.title = 'Click to punch out';
                } else if (punchDate === today && punchData.punchOutTime) {
                    // User already punched in and out today
                    this.punchBtn.disabled = true;
                    this.punchBtn.style.opacity = '0.6';
                    this.punchBtn.style.cursor = 'not-allowed';
                    this.punchBtn.innerHTML = '<i data-feather="check"></i><span>Punched In & Out</span>';
                    this.punchBtn.title = 'Attendance complete for today';
                } else {
                    // Old punch data from previous day
                    this.resetPunchButton();
                }
            } catch (e) {
                console.error('Error parsing punch data:', e);
                this.resetPunchButton();
            }
        } else {
            // No punch data found
            this.resetPunchButton();
        }

        // Re-initialize feather icons if available
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    /**
     * Reset punch button to initial state
     */
    resetPunchButton() {
        this.punchBtn.disabled = false;
        this.punchBtn.style.opacity = '1';
        this.punchBtn.style.cursor = 'pointer';
        this.punchBtn.innerHTML = '<i data-feather="log-in"></i><span>Punch In</span>';
        this.punchBtn.title = 'Click to punch in';
    }
}

// Initialize greeting manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new GreetingManager();
});
