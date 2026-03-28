function initFilters() {
    // 1. Sync Date Filter Logic
    const now = new Date();
    const currentMonth = now.toLocaleString('default', { month: 'long' });
    const currentYear = now.getFullYear().toString();
    const monthDropdown = document.getElementById('month-dropdown');
    const yearDropdown = document.getElementById('year-dropdown');

    if (monthDropdown) {
        const monthSelectedSpan = monthDropdown.querySelector('.selected-value');
        if (monthSelectedSpan) monthSelectedSpan.textContent = currentMonth;
        monthDropdown.querySelectorAll('li').forEach(opt => {
            opt.classList.toggle('selected', opt.textContent.trim() === currentMonth);
        });
    }

    if (yearDropdown) {
        const yearSelectedSpan = yearDropdown.querySelector('.selected-value');
        if (yearSelectedSpan) yearSelectedSpan.textContent = currentYear;
        
        const yearList = yearDropdown.querySelector('.dropdown-options');
        let yearFound = false;
        yearDropdown.querySelectorAll('li').forEach(opt => {
            const isMatch = opt.textContent.trim() === currentYear;
            opt.classList.toggle('selected', isMatch);
            if(isMatch) yearFound = true;
        });
        if (!yearFound && yearList) {
            const newLi = document.createElement('li');
            newLi.className = 'selected';
            newLi.textContent = currentYear;
            yearList.insertBefore(newLi, yearList.firstChild);
        }
    }

    // 2. Dropdown Toggle Logic
    const dropdowns = document.querySelectorAll('.custom-dropdown');
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-trigger');
        const selectedText = dropdown.querySelector('.selected-value');
        const options = dropdown.querySelectorAll('.dropdown-options li');

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdowns.forEach(d => { if (d !== dropdown) d.classList.remove('open'); });
            dropdown.classList.toggle('open');
        });

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                selectedText.textContent = option.textContent;
                dropdown.classList.remove('open');
            });
        });
    });

    document.addEventListener('click', () => {
        dropdowns.forEach(d => d.classList.remove('open'));
    });

    // 3. Apply Filter Logic
    const btn = document.getElementById('apply-filters');
    if (btn) {
        // Inject keyframes for spinning if not exists
        if (!document.getElementById('spin-keyframe')) {
            const style = document.createElement('style');
            style.id = 'spin-keyframe';
            style.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
            document.head.appendChild(style);
        }

        btn.addEventListener('click', () => {
            const icon = btn.querySelector('i');
            
            // Fixed logic to capture text node without breaking since btn does not have a span
            const originalClass = icon.className;
            const originalText = btn.lastChild.textContent;

            icon.className = 'ph ph-circle-notch';
            icon.style.animation = 'spin 0.7s linear infinite';
            btn.lastChild.textContent = ' Filtering…';
            btn.disabled = true;

            const subtitle = document.getElementById('table-subtitle');
            const monthVal = document.querySelector('#month-dropdown .selected-value')?.textContent || '';
            const yearVal = document.querySelector('#year-dropdown .selected-value')?.textContent || '';

            setTimeout(() => {
                icon.className = originalClass;
                icon.style.animation = '';
                btn.lastChild.textContent = originalText;
                btn.disabled = false;
                
                if (subtitle && monthVal && yearVal) {
                    subtitle.textContent = `All requests for ${monthVal} ${yearVal}`;
                }
                
                // Trigger live fetch
                if (typeof fetchTableData === 'function') {
                    fetchTableData();
                }
            }, 700);
        });
    }
}
