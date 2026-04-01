<!-- Travel Expense Action Modal Placeholder -->
<div id="expenseActionModal" class="modal-wrapper" style="display: none;">
    <div class="modal-content">
        <header class="modal-header">
            <h3>Approve/Reject Claim</h3>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </header>
        <div class="modal-body">
            <p>This is a placeholder for the action modal.</p>
        </div>
    </div>
</div>

<style>
/* Basic modal styles for UI preview */
.modal-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: var(--surface);
    padding: 2rem;
    border-radius: var(--radius-lg);
    width: 400px;
    max-width: 90%;
}
</style>
