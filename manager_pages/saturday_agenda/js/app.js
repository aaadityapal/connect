document.addEventListener('DOMContentLoaded', () => {
    const userList = document.getElementById('userList');
    const userSearch = document.getElementById('userSearch');
    const selectAll = document.getElementById('selectAllUsers');
    const clearBtn = document.getElementById('clearSelectionBtn');
    const status = document.getElementById('selectionStatus');
    const tplName = document.getElementById('tplName');
    const tplDate = document.getElementById('tplDate');
    const tplTime = document.getElementById('tplTime');
    const tplDay = document.getElementById('tplDay');
    const tplReach = document.getElementById('tplReach');
    const tplFrom = document.getElementById('tplFrom');
    const tplTo = document.getElementById('tplTo');
    const tplPreview = document.getElementById('tplPreview');
    const uploadCard = document.getElementById('uploadCard');
    const chooseFileBtn = document.getElementById('chooseFileBtn');
    const agendaPdfInput = document.getElementById('agendaPdfInput');
    const selectedFileName = document.getElementById('selectedFileName');
    const sendSelectedBtn = document.getElementById('sendSelectedBtn');
    const sendLoader = document.getElementById('sendLoader');
    const resultModal = document.getElementById('resultModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalList = document.getElementById('modalList');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const viewArchiveBtn = document.getElementById('viewArchiveBtn');
    const archiveModal = document.getElementById('archiveModal');
    const closeArchiveBtn = document.getElementById('closeArchiveBtn');
    const archiveMessage = document.getElementById('archiveMessage');
    const archiveList = document.getElementById('archiveList');

    let users = [];
    let selectedIds = new Set();

    function getIstNow() {
        const now = new Date();
        const istOffsetMs = 5.5 * 60 * 60 * 1000;
        const localOffsetMs = now.getTimezoneOffset() * 60 * 1000;
        return new Date(now.getTime() + istOffsetMs + localOffsetMs);
    }

    function getFourthSaturday(year, monthIndex) {
        const firstDay = new Date(year, monthIndex, 1);
        const offset = (6 - firstDay.getDay() + 7) % 7;
        const fourthSaturdayDate = 1 + offset + 21;
        return new Date(year, monthIndex, fourthSaturdayDate);
    }

    function formatDate(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function buildMessage() {
        const name = (tplName && tplName.value.trim()) || 'Team Member';
        const date = formatDate(tplDate ? tplDate.value : '');
        const time = (tplTime && tplTime.value.trim()) || '09:00';
        const day = (tplDay && tplDay.value.trim()) || 'Saturday';
        const reach = (tplReach && tplReach.value.trim()) || '09:00';
        const from = (tplFrom && tplFrom.value.trim()) || '10:00';
        const to = (tplTo && tplTo.value.trim()) || '12:00';

        return `Hello ${name}, 👋\n\n` +
            `The 4th Saturday Meeting will be held as per the details below:\n\n` +
            `📅 Date: ${date || 'TBD'}\n` +
            `⏰ Time: ${time}\n` +
            `📆 Day: ${day}\n\n` +
            `Kindly check the attached PDF and ensure availability as per the schedule.\n\n` +
            `The site team is expected to reach the office by ${reach} and conduct prior management-level intimation to clients for your non-availability between ${from} to ${to}.\n\n` +
            `– HR Dept.\n` +
            `– ArchitectsHive`;
    }

    function updatePreview() {
        if (!tplPreview) return;
        tplPreview.value = buildMessage();
    }

    function renderList(filter = '') {
        if (!userList) return;
        const term = filter.trim().toLowerCase();
        const filtered = users.filter(user => {
            const haystack = `${user.name} ${user.dept}`.toLowerCase();
            return haystack.includes(term);
        });

        if (filtered.length === 0) {
            userList.innerHTML = '<div class="empty-state">No matching users.</div>';
            return;
        }

        userList.innerHTML = filtered.map(user => {
            const initials = user.name.split(' ').map(part => part[0]).slice(0, 2).join('') || 'U';
            const checked = selectedIds.has(user.id) ? 'checked' : '';
            return `
                <label class="user-card">
                    <div class="user-meta">
                        <div class="user-avatar">${initials}</div>
                        <div class="user-info">
                            <div class="user-name">${user.name}</div>
                            <div class="user-sub">${user.dept} · ${user.phone}</div>
                        </div>
                    </div>
                    <input type="checkbox" data-user-id="${user.id}" ${checked} />
                </label>
            `;
        }).join('');

        userList.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', (event) => {
                const id = Number(event.target.dataset.userId);
                if (event.target.checked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
                syncControls();
            });
        });
    }

    function syncControls() {
        const count = selectedIds.size;
        if (status) {
            status.textContent = `${count} selected`;
        }
        if (selectAll) {
            selectAll.checked = count > 0 && count === users.length;
            selectAll.indeterminate = count > 0 && count < users.length;
        }

        if (tplName) {
            if (count > 0) {
                const first = users.find(user => selectedIds.has(user.id));
                if (first) {
                    tplName.value = first.name;
                }
            }
        }
        updatePreview();
    }

    if (userSearch) {
        userSearch.addEventListener('input', (event) => {
            renderList(event.target.value);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', (event) => {
            selectedIds = event.target.checked
                ? new Set(users.map(user => user.id))
                : new Set();
            renderList(userSearch ? userSearch.value : '');
            syncControls();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            selectedIds.clear();
            renderList(userSearch ? userSearch.value : '');
            syncControls();
        });
    }

    async function fetchUsers() {
        if (!userList) return;
        userList.innerHTML = '<div class="empty-state">Loading users...</div>';
        try {
            const response = await fetch('api/users.php');
            const data = await response.json();
            if (!data.success || !Array.isArray(data.users)) {
                userList.innerHTML = '<div class="empty-state">Failed to load users.</div>';
                return;
            }

            users = data.users.map(user => {
                return {
                    id: Number(user.id),
                    name: user.username || 'Unknown',
                    dept: user.department || user.designation || user.role || 'General',
                    phone: user.phone || 'No phone'
                };
            });
            selectedIds.clear();
            renderList(userSearch ? userSearch.value : '');
            syncControls();
        } catch (error) {
            userList.innerHTML = '<div class="empty-state">Failed to load users.</div>';
        }
    }

    function buildSendPayload() {
        const file = agendaPdfInput && agendaPdfInput.files ? agendaPdfInput.files[0] : null;
        if (!file) {
            alert('Please choose a PDF file first.');
            return null;
        }

        if (selectedIds.size === 0) {
            alert('Please select at least one user.');
            return null;
        }

        const formData = new FormData();
        formData.append('pdf_file', file);
        formData.append('meeting_date', formatDate(tplDate ? tplDate.value : ''));
        formData.append('meeting_time', (tplTime && tplTime.value) || '');
        formData.append('meeting_day', (tplDay && tplDay.value) || '');
        formData.append('reach_by', (tplReach && tplReach.value) || '');
        formData.append('na_from', (tplFrom && tplFrom.value) || '');
        formData.append('na_to', (tplTo && tplTo.value) || '');

        Array.from(selectedIds).forEach(id => {
            formData.append('user_ids[]', String(id));
        });

        return formData;
    }

    async function sendWhatsApp() {
        const formData = buildSendPayload();
        if (!formData) return;

        if (sendLoader) {
            sendLoader.classList.add('active');
        }
        if (sendSelectedBtn) {
            sendSelectedBtn.disabled = true;
        }

        try {
            const response = await fetch('api/send_whatsapp.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            showResultModal(data);
        } catch (error) {
            showResultModal({
                success: false,
                message: 'Failed to send WhatsApp messages.'
            });
        } finally {
            if (sendLoader) {
                sendLoader.classList.remove('active');
            }
            if (sendSelectedBtn) {
                sendSelectedBtn.disabled = false;
            }
        }
    }

    function showResultModal(data) {
        if (!resultModal) return;
        if (modalTitle) {
            modalTitle.textContent = data.success ? 'Messages Sent' : 'Send Failed';
        }
        if (modalSubtitle) {
            const sent = data.sentCount || 0;
            const failed = data.failedCount || 0;
            modalSubtitle.textContent = data.success
                ? `Sent ${sent} · Failed ${failed}`
                : 'No messages were sent.';
        }
        if (modalMessage) {
            modalMessage.textContent = data.message || 'Request completed.';
        }
        if (modalList) {
            if (Array.isArray(data.logs) && data.logs.length > 0) {
                modalList.innerHTML = data.logs.map(log => {
                    const status = (log.status || '').toLowerCase();
                    const badgeClass = status === 'ok' ? 'ok' : 'fail';
                    return `
                        <div class="modal-row">
                            <span>${log.user || 'Unknown'}</span>
                            <span class="modal-badge ${badgeClass}">${log.status || 'FAIL'}</span>
                        </div>
                    `;
                }).join('');
            } else {
                modalList.innerHTML = '';
            }
        }
        resultModal.classList.add('active');
    }

    function closeResultModal() {
        if (!resultModal) return;
        resultModal.classList.remove('active');
    }

    function closeArchiveModal() {
        if (!archiveModal) return;
        archiveModal.classList.remove('active');
    }

    async function openArchiveModal() {
        if (!archiveModal) return;
        archiveModal.classList.add('active');
        if (archiveMessage) {
            archiveMessage.textContent = 'Loading archives...';
        }
        if (archiveList) {
            archiveList.innerHTML = '';
        }

        try {
            const response = await fetch('api/archives.php');
            const data = await response.json();
            if (!data.success) {
                if (archiveMessage) {
                    archiveMessage.textContent = data.message || 'Failed to load archives.';
                }
                return;
            }

            if (!Array.isArray(data.archives) || data.archives.length === 0) {
                if (archiveMessage) {
                    archiveMessage.textContent = 'No archived PDFs yet.';
                }
                return;
            }

            if (archiveMessage) {
                archiveMessage.textContent = '';
            }

            archiveList.innerHTML = data.archives.map(group => {
                const items = Array.isArray(group.items) ? group.items : [];
                const rows = items.map(item => {
                    return `
                        <div class="archive-item">
                            <span>${item.label || item.filename}</span>
                            <a class="archive-link" href="${item.url}" target="_blank" rel="noopener">View</a>
                        </div>
                    `;
                }).join('');
                return `
                    <div class="archive-group">
                        <div class="archive-title">${group.month || 'Archive'}</div>
                        ${rows}
                    </div>
                `;
            }).join('');
        } catch (error) {
            if (archiveMessage) {
                archiveMessage.textContent = 'Failed to load archives.';
            }
        }
    }

    function triggerFileDialog() {
        if (agendaPdfInput) {
            agendaPdfInput.click();
        }
    }

    if (chooseFileBtn) {
        chooseFileBtn.addEventListener('click', triggerFileDialog);
    }

    if (uploadCard) {
        uploadCard.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            triggerFileDialog();
        });
    }

    if (agendaPdfInput) {
        agendaPdfInput.addEventListener('change', () => {
            if (!selectedFileName) return;
            const file = agendaPdfInput.files && agendaPdfInput.files[0];
            selectedFileName.textContent = file ? file.name : 'No file selected';
        });
    }

    [tplName, tplDate, tplTime, tplDay, tplReach, tplFrom, tplTo].forEach(input => {
        if (!input) return;
        input.addEventListener('input', updatePreview);
    });

    if (sendSelectedBtn) {
        sendSelectedBtn.addEventListener('click', () => sendWhatsApp());
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeResultModal);
    }

    if (resultModal) {
        resultModal.addEventListener('click', (event) => {
            if (event.target === resultModal) {
                closeResultModal();
            }
        });
    }

    if (viewArchiveBtn) {
        viewArchiveBtn.addEventListener('click', openArchiveModal);
    }

    if (closeArchiveBtn) {
        closeArchiveBtn.addEventListener('click', closeArchiveModal);
    }

    if (archiveModal) {
        archiveModal.addEventListener('click', (event) => {
            if (event.target === archiveModal) {
                closeArchiveModal();
            }
        });
    }

    fetchUsers();
    syncControls();
    if (tplDay && !tplDay.value) {
        tplDay.value = 'Saturday';
    }
    if (tplTime && !tplTime.value) {
        tplTime.value = '10:00';
    }
    if (tplReach && !tplReach.value) {
        tplReach.value = '11:30';
    }
    if (tplFrom && !tplFrom.value) {
        tplFrom.value = '11:00';
    }
    if (tplTo && !tplTo.value) {
        tplTo.value = '15:00';
    }
    if (tplDate && !tplDate.value) {
        const istNow = getIstNow();
        const target = getFourthSaturday(istNow.getFullYear(), istNow.getMonth());
        const yyyy = target.getFullYear();
        const mm = String(target.getMonth() + 1).padStart(2, '0');
        const dd = String(target.getDate()).padStart(2, '0');
        tplDate.value = `${yyyy}-${mm}-${dd}`;
    }
    updatePreview();
});
