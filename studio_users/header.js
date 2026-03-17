document.addEventListener("DOMContentLoaded", () => {
    // --- Custom Header Time Logic ---
    function updateHeaderTime() {
        const now = new Date();

        // Timeline & Greeting logic
        let hoursRaw = now.getHours();
        const headerEl = document.querySelector('.dh-nav-header');
        const greetingTextEl = document.querySelector('.dh-greeting-text');

        let themeClass = '';
        let greeting = 'Good ';

        if (hoursRaw >= 5 && hoursRaw < 12) {
            themeClass = 'dh-theme-morning';
            greeting += 'Morning ,';
        } else if (hoursRaw >= 12 && hoursRaw < 17) {
            themeClass = 'dh-theme-afternoon';
            greeting += 'Afternoon ,';
        } else if (hoursRaw >= 17 && hoursRaw < 20) {
            themeClass = 'dh-theme-evening';
            greeting += 'Evening ,';
        } else {
            themeClass = 'dh-theme-night';
            greeting += 'Evening ,';
        }

        // Festival logic overrides timeline
        const currentMonth = now.getMonth() + 1;
        const currentDate = now.getDate();

        if (currentMonth === 12 && currentDate === 25) {
            themeClass = 'dh-theme-christmas';
            greeting = 'Merry Christmas ,';
        } else if (currentMonth === 1 && currentDate === 26) {
            themeClass = 'dh-theme-republic';
            greeting = 'Happy Republic Day ,';
        } else if (currentMonth === 10 && currentDate >= 29) { // Example for Diwali timeframe
            themeClass = 'dh-theme-diwali';
            greeting = 'Happy Diwali ,';
        }

        // Holi theme — RE-ENABLE NEXT YEAR (March 3rd to 25th)
        // } else if (currentMonth === 3 && currentDate >= 3 && currentDate <= 25) {
        //     themeClass = 'dh-theme-holi';
        //     greeting = 'Happy Holi ,';
        // }

        if (greetingTextEl && greetingTextEl.textContent !== greeting) {
            greetingTextEl.textContent = greeting;
        }

        if (headerEl && !headerEl.classList.contains(themeClass)) {
            // Remove previous theme classes
            headerEl.className = headerEl.className.replace(/\bdh-theme-\S+/g, '').trim();
            headerEl.classList.add(themeClass);

            // Dynamically load festivals.css ONLY if a festival theme is currently active
            const isFestival = ['dh-theme-christmas', 'dh-theme-republic', 'dh-theme-diwali', 'dh-theme-holi'].includes(themeClass);
            let festivalLink = document.getElementById('festival-css-link');

            if (isFestival && !festivalLink) {
                festivalLink = document.createElement('link');
                festivalLink.id = 'festival-css-link';
                festivalLink.rel = 'stylesheet';
                festivalLink.href = 'festivals.css';
                document.head.appendChild(festivalLink);
            } else if (!isFestival && festivalLink) {
                festivalLink.remove();
            }
        }

        let hours = hoursRaw;
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;

        const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        const currentTimeEl = document.getElementById('currentTime');
        if (currentTimeEl) currentTimeEl.textContent = timeStr;

        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayName = days[now.getDay()];
        const year = now.getFullYear();
        let month = now.getMonth() + 1;
        let date = now.getDate();

        month = month < 10 ? '0' + month : month;
        date = date < 10 ? '0' + date : date;

        const dateStr = dayName + ', ' + year + '-' + month + '-' + date;
        const currentDateEl = document.getElementById('currentDate');
        if (currentDateEl) currentDateEl.textContent = dateStr;
    }

    setInterval(updateHeaderTime, 1000);
    updateHeaderTime();

    // Profile Dropdown Logic
    const profileAvatarBtn = document.getElementById('profileAvatarBtn');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');
    const profileDropdownContainer = document.getElementById('profileDropdownContainer');

    if (profileAvatarBtn && profileDropdownMenu) {
        profileAvatarBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent closing immediately when clicking the avatar
            profileDropdownMenu.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (profileDropdownContainer && !profileDropdownContainer.contains(e.target)) {
                profileDropdownMenu.classList.remove('active');
            }
        });

        // Prevent closing when clicking inside the dropdown menu itself
        profileDropdownMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
});
