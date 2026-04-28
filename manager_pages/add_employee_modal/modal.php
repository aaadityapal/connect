<?php
// Add Employee modal markup (UI only)

// Build the correct root-relative URL for the username-check API
// __DIR__ gives the filesystem path to this folder; strip DOCUMENT_ROOT to get the URL path
$_ae_docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$_ae_apiUrl   = str_replace($_ae_docRoot, '', __DIR__) . '/check_username.php';
// Normalise to forward slashes (Windows safety)
$_ae_apiUrl   = str_replace('\\', '/', $_ae_apiUrl);
?>
<script>window.AE_CHECK_USERNAME_URL = '<?= htmlspecialchars($_ae_apiUrl, ENT_QUOTES) ?>';</script>
<div id="addEmployeeModal" class="ae-modal" aria-hidden="true">
    <div class="ae-modal__backdrop" data-ae-close="true"></div>
    <div class="ae-modal__panel" role="dialog" aria-modal="true" aria-labelledby="aeModalTitle">
        <button type="button" class="ae-modal__close" data-ae-close="true" aria-label="Close">
            <i data-lucide="x"></i>
        </button>
        <div class="ae-modal__content">
            <div class="ae-modal__hero">
                <div class="ae-hero__badge">New Hire</div>
                <h2 id="aeModalTitle">Add Employee</h2>
                <p>Set up the core details now. You can complete the rest later.</p>
            </div>
            <form class="ae-form" autocomplete="off" method="post" action="../add_employee_modal/add_employee_handler.php">
                <div class="ae-grid">
                    <label class="ae-field" id="aeUsernameField">
                        <span>Username</span>
                        <input type="text" name="username" id="aeUsernameInput" placeholder="e.g. ananya.rao" required autocomplete="off" />
                        <small class="ae-field__status" id="aeUsernameStatus" aria-live="polite"></small>
                    </label>
                    <label class="ae-field">
                        <span>Email</span>
                        <input type="email" name="email" placeholder="name@company.com" required />
                    </label>
                    <label class="ae-field">
                        <span>Phone</span>
                        <input type="tel" name="phone" placeholder="+91 98765 43210" />
                    </label>
                    <label class="ae-field">
                        <span>Role</span>
                        <select name="role" id="aeRoleSelect" required>
                            <option value="">Select role</option>
                        </select>
                    </label>
                    <label class="ae-field ae-field--full is-hidden" id="aeCustomRoleField">
                        <span>Custom role</span>
                        <input type="text" name="role_custom" id="aeCustomRoleInput" placeholder="Type custom role" />
                        <small class="ae-field__hint">Use this only if the role is not listed above.</small>
                    </label>
                    <label class="ae-field">
                        <span>Reporting manager</span>
                        <select name="reporting_manager" id="aeManagerSelect">
                            <option value="">Select manager</option>
                        </select>
                    </label>
                    <label class="ae-field">
                        <span>Joining date</span>
                        <input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>" required />
                    </label>
                    <label class="ae-field">
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Set a temporary password" required />
                    </label>
                    <label class="ae-field ae-field--full">
                        <span>Department</span>
                        <input type="text" name="department" placeholder="Design / Sales / Operations" />
                    </label>
                </div>
                <div class="ae-form__footer">
                    <button type="button" class="ae-btn ae-btn--ghost" data-ae-close="true">Cancel</button>
                    <button type="submit" class="ae-btn ae-btn--primary">
                        Save Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
