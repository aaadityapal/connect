document.addEventListener('DOMContentLoaded', () => {
    const projectPermissionsContainer = document.getElementById('projectPermissionsContainer');
    const searchInput = document.getElementById('userSearch');
    const saveBtn = document.getElementById('savePermissionsBtn');
    const loader = document.getElementById('loaderOverlay');
    const toast = document.getElementById('toast');

    let allRows = [];
    let stateMap = {};

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showToast(message, type = 'success') {
        if (!toast) {
            if (typeof window.showUiNotice === 'function') {
                window.showUiNotice(message, type === 'success' ? 'Success' : 'Error');
            } else {
                alert(message);
            }
            return;
        }
        toast.textContent = message;
        toast.style.background = type === 'success' ? '#1e293b' : '#ef4444';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function renderRows(users) {
        if (!projectPermissionsContainer) {
            return;
        }

        if (!users.length) {
            projectPermissionsContainer.innerHTML = '<div class="empty-box">No active users found.</div>';
            return;
        }

        projectPermissionsContainer.innerHTML = users.map((user) => {
            const uid = Number(user.id) || 0;
            const rowState = stateMap[uid] || { can_upload: 0, can_delete: 0 };
            const updatedAt = user.updated_at ? escapeHtml(user.updated_at) : 'Never';
            return `
                <div class="user-perm-card" data-search="${escapeHtml(`${user.username} ${user.email} ${user.role}`.toLowerCase())}">
                    <div class="user-perm-meta">
                        <p class="user-perm-name">${escapeHtml(user.username)}</p>
                        <p class="user-perm-sub">${escapeHtml(user.email)}</p>
                        <span class="pill-role"><i data-lucide="badge-check" style="width:12px;height:12px;"></i>${escapeHtml(user.role || 'N/A')}</span>
                        <p class="user-perm-sub" style="margin-top:0.4rem;">Updated: ${updatedAt}</p>
                    </div>
                    <div class="user-perm-right">
                        <label class="perm-switch-row" title="Allow upload document">
                            <span>Upload</span>
                            <span class="switch">
                                <input type="checkbox" class="perm-upload" data-user-id="${uid}" ${Number(rowState.can_upload) === 1 ? 'checked' : ''}>
                                <span class="slider"></span>
                            </span>
                        </label>
                        <label class="perm-switch-row" title="Allow delete/hide document">
                            <span>Delete</span>
                            <span class="switch">
                                <input type="checkbox" class="perm-delete" data-user-id="${uid}" ${Number(rowState.can_delete) === 1 ? 'checked' : ''}>
                                <span class="slider"></span>
                            </span>
                        </label>
                    </div>
                </div>
            `;
        }).join('');

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    async function loadPermissions() {
        try {
            const response = await fetch('api/get_confiedential_doc_permissions.php');
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to load permissions');
            }

            allRows = Array.isArray(data.users)
                ? data.users.filter((u) => String(u.status || 'active').toLowerCase() === 'active' || !('status' in u))
                : [];
            stateMap = {};
            allRows.forEach((u) => {
                const uid = Number(u.id) || 0;
                if (uid > 0) {
                    stateMap[uid] = {
                        can_upload: Number(u.can_upload || 0) === 1 ? 1 : 0,
                        can_delete: Number(u.can_delete || 0) === 1 ? 1 : 0
                    };
                }
            });
            renderRows(allRows);
        } catch (err) {
            projectPermissionsContainer.innerHTML = '<div class="empty-box">Failed to load data.</div>';
            showToast(err instanceof Error ? err.message : 'Failed to load permissions', 'error');
        }
    }

    function collectPayload() {
        const uploadMap = {};
        document.querySelectorAll('.perm-upload').forEach((checkbox) => {
            uploadMap[String(checkbox.dataset.userId || '')] = checkbox.checked ? 1 : 0;
        });

        const permissions = [];
        document.querySelectorAll('.perm-delete').forEach((checkbox) => {
            const userId = String(checkbox.dataset.userId || '');
            if (!/^\d+$/.test(userId)) {
                return;
            }
            permissions.push({
                user_id: Number(userId),
                can_upload: uploadMap[userId] || 0,
                can_delete: checkbox.checked ? 1 : 0
            });
        });

        return permissions;
    }

    searchInput?.addEventListener('input', () => {
        const term = (searchInput.value || '').trim().toLowerCase();
        document.querySelectorAll('#projectPermissionsContainer .user-perm-card[data-search]').forEach((row) => {
            const hay = row.getAttribute('data-search') || '';
            row.style.display = term === '' || hay.includes(term) ? '' : 'none';
        });
    });

    saveBtn?.addEventListener('click', async () => {
        const permissions = collectPayload();
        if (loader) loader.style.display = 'flex';
        saveBtn.disabled = true;

        try {
            const response = await fetch('api/save_confiedential_doc_permissions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ permissions })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save permissions');
            }

            showToast('Permissions saved successfully.', 'success');
            loadPermissions();
        } catch (err) {
            showToast(err instanceof Error ? err.message : 'Failed to save permissions', 'error');
        } finally {
            if (loader) loader.style.display = 'none';
            saveBtn.disabled = false;
        }
    });

    loadPermissions();
});
