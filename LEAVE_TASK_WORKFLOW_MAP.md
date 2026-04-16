# Leave Task Workflow Map

## Scope
This map covers leave-task assignment and reassignment flow across:
- `studio_users/api/save_leave_request.php`
- `manager_pages/leave_approval/api/update_leave_status.php`
- `studio_users/api/cron_leave_bot.php`
- `studio_users/api/extend_task_deadline.php`

---

## 1) Leave Apply (Employee -> Manager Task)

Source: `studio_users/api/save_leave_request.php`

### Input conditions
- Requires:
  - `dates`
  - `approver_id`
  - `reason`
- If missing, request fails.

### Leave row creation
- Inserts leave rows with:
  - `status = 'pending'`
  - `manager_action_by = approver_id`
  - `manager_approval` unset (pending)

### Task assignment behavior
- Conneqts Bot creates **manager-only** verification task.
- HR is **not** included at this stage.
- Activity log message:
  - "Conneqts Bot assigned you a Manager Leave Verification task ..."

---

## 2) Approval Action Gate (Manager/HR)

Source: `manager_pages/leave_approval/api/update_leave_status.php`

### Action rules
- HR cannot approve unless manager already approved.
- Manager reject path can finalize rejection.
- Manager approve path keeps flow open for HR stage.

### Transition tracking
- Captures previous manager status (`wasManagerApproved`) to detect first-time transition.

---

## 3) Stage-2 Assignment (Manager Approved -> HR Task)

Source: `manager_pages/leave_approval/api/update_leave_status.php`

### Trigger condition
Create HR task only when all are true:
1. Actor is manager (not admin/hr)
2. Action is `approve`
3. Manager was not already approved before this action

### Duplicate protection
- Uses marker in task description:
  - `[LEAVE_REQ_ID:<request_id>]`
- Before creating HR task, checks existing active task (not Completed/Cancelled).

### Notification text
- HR activity log message:
  - "Conneqts Bot assigned it to you: Manager approved ... Remaining approval is yours."

---

## 4) Nightly Reassignment (Cron)

Source: `studio_users/api/cron_leave_bot.php`

### Candidate leave set
Cron scans leaves where:
- `leave_request.status = 'pending'`
- User status is active
- Leave start date within last 60 days

### Action-state interpretation
Function: `hasTakenLeaveAction(value)`
- Considered acted only if value is:
  - `approved` or `rejected`
- Anything else is pending (null/empty/pending-like).

### Reassignment routing
- If manager approved and HR has not acted:
  - target HR users
- If manager has not acted:
  - target manager user
  - manager id fallback:
    - `manager_action_by`
    - if missing, `leave_approval_mapping.manager_id`

---

## 5) Do-Not-Reassign Conditions (Implemented)

Function: `hasActiveLeaveFollowUpTask(...)` in `studio_users/api/cron_leave_bot.php`

Cron **skips creating new follow-up** when active task already exists for same leave + assignee:
- Active means task status is not in `Completed`, `Cancelled`
- Match by either:
  - leave marker `[LEAVE_REQ_ID:<id>]`
  - legacy follow-up description pattern

### Extended-task guard (also implemented)
If existing active task is extended, cron also skips reassign:
- Checks task-level extension via:
  - `extension_count > 0`
  - non-empty `extension_history`
- Checks shared-task user-specific extension via:
  - `studio_task_user_meta.meta_key = 'extended_due_date'`

Result:
- If active pending task already exists -> no reassignment
- If extended task exists -> no reassignment

---

## 6) Condition Matrix

1. Manager pending, HR pending
- Assign/remind: Manager only
- Do not assign HR

2. Manager approved, HR pending
- Assign/remind: HR only

3. Manager rejected
- No HR approval stage
- No reassignment needed

4. Active follow-up task already exists for same leave + assignee
- Do not create new task

5. Existing follow-up task has extension (global or user-specific)
- Do not create new task

---

## 7) Practical Verification Checklist

1. Submit new leave
- Confirm only manager receives initial leave verification task.

2. Manager approves first time
- Confirm HR receives task with Conneqts wording.
- Confirm marker `[LEAVE_REQ_ID:<id>]` exists in created HR task description.

3. Run cron with pending manager task already present
- Confirm cron logs skip duplicate, no new task.

4. Extend existing pending follow-up task
- Confirm cron skips reassignment due to extension.

5. Manager still pending with no active task
- Confirm cron creates manager follow-up.

6. Manager approved but HR pending with no active HR task
- Confirm cron creates HR follow-up.
