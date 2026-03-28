/* ============================================
   custom.js — Manage Policies Logic
   ============================================ */

(function () {
    const listContainer = document.getElementById('hrPolicyList');
    const manageListView = document.getElementById('manageListView');
    const manageEditView = document.getElementById('manageEditView');
    
    // Form elements
    const editForm = document.getElementById('hrEditPolicyForm');
    const inputId = document.getElementById('editPolicyId');
    const inputHeading = document.getElementById('editPolicyHeading');
    const inputShortDesc = document.getElementById('editPolicyShortDesc');
    const inputLongDesc = document.getElementById('editPolicyLongDesc');
    const btnBack = document.getElementById('btnBackToList');
    const btnSave = document.getElementById('hrBtnSaveChanges');

    if (!listContainer || !manageListView || !manageEditView) return;

    let currentPolicies = [];

    // ── Fetch Policies ──────────────────────────
    async function fetchPolicies() {
        listContainer.innerHTML = '<div class="hr-policy-list-loader"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading policies...</div>';
        try {
            const res = await fetch('api/get_policies.php?v=' + Date.now());
            const data = await res.json();
            if (data.success) {
                currentPolicies = data.data;
                renderList();
            } else {
                listContainer.innerHTML = '<div class="hr-policy-list-loader" style="color:#ef4444;">Failed to load.</div>';
            }
        } catch (e) {
            console.error(e);
            listContainer.innerHTML = '<div class="hr-policy-list-loader" style="color:#ef4444;">Connection error.</div>';
        }
    }

    // ── Render List ─────────────────────────────
    function renderList() {
        listContainer.innerHTML = '';
        if (currentPolicies.length === 0) {
            listContainer.innerHTML = '<div class="hr-policy-list-loader">No policies found. Create one in the Policy tab.</div>';
            return;
        }

        currentPolicies.forEach((policy) => {
            const dateStr = new Date(policy.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            const item = document.createElement('div');
            item.className = 'hr-policy-item';
            item.innerHTML = `
                <div class="hr-policy-item-content">
                    <div class="hr-policy-item-title">${policy.heading}</div>
                    <div class="hr-policy-item-desc">${policy.short_desc}</div>
                    <div class="hr-policy-item-meta">
                        <i class="fa-regular fa-calendar" style="margin-right:3px;"></i> Last updated: ${dateStr}
                        ${policy.is_active == 1 ? '<span class="hr-policy-item-badge">Active</span>' : ''}
                    </div>
                </div>
                <div class="hr-policy-item-actions">
                    <button class="hr-btn-edit" data-id="${policy.id}">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="hr-btn-delete" data-id="${policy.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>
            `;
            listContainer.appendChild(item);
        });

        // Add event listeners for edit buttons
        document.querySelectorAll('.hr-btn-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                openEditView(id);
            });
        });

        // Add event listeners for delete buttons
        document.querySelectorAll('.hr-btn-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                if (confirm('Are you absolutely sure you want to permanently delete this policy? This cannot be undone.')) {
                    const originalHTML = e.currentTarget.innerHTML;
                    e.currentTarget.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    e.currentTarget.disabled = true;

                    try {
                        const res = await fetch('api/delete_policy.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id })
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            if(window.showToast) window.showToast('Policy deleted successfully!');
                            fetchPolicies(); // refresh list
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        if(window.showToast) window.showToast('Failed to delete. Please try again.', 'error');
                        e.currentTarget.innerHTML = originalHTML;
                        e.currentTarget.disabled = false;
                    }
                }
            });
        });
    }

    // ── Open Edit View ──────────────────────────
    function openEditView(id) {
        const policy = currentPolicies.find(p => p.id == id);
        if(!policy) return;

        inputId.value = policy.id;
        inputHeading.value = policy.heading;
        inputShortDesc.value = policy.short_desc;
        inputLongDesc.value = policy.long_desc;

        manageListView.style.display = 'none';
        manageEditView.style.display = 'block';
    }

    // ── Close Edit View ──────────────────────────
    btnBack.addEventListener('click', () => {
        manageEditView.style.display = 'none';
        manageListView.style.display = 'block';
    });

    // ── Save Changes ─────────────────────────────
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = inputId.value;
        const heading = inputHeading.value.trim();
        const shortDesc = inputShortDesc.value.trim();
        const longDesc = inputLongDesc.value.trim();

        const originalHTML = btnSave.innerHTML;
        btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btnSave.disabled = true;

        try {
            const res = await fetch('api/update_policy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, heading, shortDesc, longDesc })
            });
            const data = await res.json();
            
            if (data.success) {
                if(window.showToast) window.showToast('Policy updated successfully!');
                // Close and refresh
                manageEditView.style.display = 'none';
                manageListView.style.display = 'block';
                fetchPolicies();
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            console.error(err);
            if(window.showToast) window.showToast('Failed to update. Please try again.', 'error');
        } finally {
            btnSave.innerHTML = originalHTML;
            btnSave.disabled = false;
        }
    });

    // Make fetchPolicies globally available so tabs.js can trigger it if needed
    // or simply trigger Once on load.
    // Also attach to the tab toggle to refresh when clicking "Manage Policies" tab.
    const customTabBtn = document.getElementById('hrToggleCustom');
    if(customTabBtn) {
        customTabBtn.addEventListener('click', () => {
             // Refresh list whenever tab is opened
             manageEditView.style.display = 'none';
             manageListView.style.display = 'block';
             fetchPolicies();
        });
    }

    // Initial fetch just to have data ready
    fetchPolicies();

})();
