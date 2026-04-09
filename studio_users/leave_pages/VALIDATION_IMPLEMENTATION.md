# Leave Balance Validation & Warning System - Implementation Guide

## Overview
This implementation adds intelligent leave balance validation with a priority-based system for automatic leave type selection. It prevents users from submitting invalid leave requests and warns them when unpaid leaves will be used.

---

## 🎯 Features Implemented

### 1. **Leave Validation Logic**
The system validates leave requests based on leave type category:

#### Special Leave Types (Direct Validation)
- **Short Leave**
- **Sick Leave**
- **Paternity Leave**
- **Maternity Leave**

❌ **These leaves cannot fall back to other types.** Direct balance check only.

#### Flexible Leave Types (Priority-Based)
- **Compensation Leave**
- **Casual Leave**
- **Unpaid Leave**

✅ **These leaves follow a priority chain:**

```
Requested Days = 5
Available Balance:
  - Compensation Leave: 2 days
  - Casual Leave: 1 day
  - Unpaid Leave: ∞ (unlimited)

Priority Flow:
1. Use 2 days from Compensation Leave
2. Use 1 day from Casual Leave
3. Use 2 days as Unpaid Leave ⚠️ Warning shown!
```

---

## 📋 Validation Flow

### Step 1: Balance Fetching
```javascript
const fetchCurrentBalances = async () => {
    // Fetches leave balances from API
    // Stores in currentLeaveBalances object
    // Format: { 'Leave Type': balance_value }
}
```

### Step 2: Leave Category Identification
```javascript
const getLeaveCategory = (leaveType) => {
    // Identifies the category of leave:
    // 'short', 'sick', 'paternity', 'maternity', 
    // 'compensation', 'casual', 'unpaid', 'other'
}
```

### Step 3: Validation & Warning Generation
```javascript
const validateLeaveBalance = (selectedDates) => {
    // For each leave type:
    //   1. Check if it's a special type (direct validation)
    //   2. If special type: check if balance is sufficient
    //   3. If not special: apply priority logic
    //   4. Generate warnings if unpaid leave will be used
    
    // Returns: { warnings: [], errors: [] }
}
```

### Step 4: User Confirmation
```
If errors exist:
  ❌ Show error modal (Cannot submit)

If warnings exist:
  ⚠️  Show warning modal with details
  → User can Cancel or Confirm & Submit

If no issues:
  ✅ Submit directly
```

---

## 🚨 Warning Messages

### Error (Insufficient Balance)
```
❌ Validation Errors:

• ⚠️ Insufficient Sick Leave balance. 
  You have 0 days but requesting 2 days.
```

### Warning (Unpaid Leave Usage)
```
⚠️ Warning - Please Review:

• ⚠️ You'll use 2 unpaid leave day(s) for your 
  Casual Leave request!

✅ Click "Confirm & Submit" to proceed anyway.
```

### Info (Casual Leave Usage)
```
⚠️ Warning - Please Review:

• ℹ️ Your request will use 2 Casual Leave day(s).

✅ Click "Confirm & Submit" to proceed anyway.
```

---

## 🔧 Code Changes

### File: `js/main.js`
**Lines 715-936** - Added validation system

**Key Functions Added:**
1. `fetchCurrentBalances()` - Fetch balances on page load
2. `getLeaveCategory()` - Identify leave type category
3. `validateLeaveBalance()` - Main validation logic
4. `showValidationWarning()` - Display error/warning
5. `createWarningModal()` - Build warning UI
6. `showWarningModal()` - Show warning to user
7. `confirmWarningSubmit()` - Handle user confirmation
8. `performFormSubmission()` - Execute actual submission

**Form Submit Handler Modified:**
- Added pre-submission validation check
- Integrated warning modal workflow
- Waits for user confirmation before proceeding

### File: `css/style.css`
**Line 1210** - Added warning icon styling

```css
.nlr-modal-icon.warning { background: #fef3c7; color: #d97706; }
```

---

## 🎨 UI Components

### Warning Modal
- **Position:** Fixed, centered overlay
- **Icon:** Amber warning triangle (48x48px)
- **Title:** "Leave Balance Warning" (Amber color)
- **Message:** Pre-formatted text showing warning details
- **Buttons:**
  - Cancel (Gray) - Closes modal, prevents submission
  - Confirm & Submit (Amber) - Proceeds with submission

### Styling
- Smooth animations and transitions
- Responsive design (works on all screen sizes)
- Consistent with existing design system
- Uses CSS variables for theming

---

## 📊 Data Flow Diagram

```
User Submits Form
        ↓
[Validation Check]
        ↓
    ┌───────────────────┐
    │ Has Errors?       │
    └───────────────────┘
         ↙ Yes      ↘ No
   [Show Error]   [Check Warnings]
        ↓              ↓
      ❌          ┌────────────┐
                  │ Has Warn?  │
                  └────────────┘
                    ↙ Yes   ↘ No
              [Show Warning] [Submit]
                    ↓           ↓
              [User Clicks]    ✅
                Cancel/Confirm
                    ↓
           ┌────────┴────────┐
           │ Confirm?        │
           └─────────────────┘
             ↙ Yes      ↘ No
           [Submit]    [Cancel]
             ↓            ↓
            ✅            ❌
```

---

## 🔄 Priority Logic Algorithm

```javascript
Algorithm: Flexible Leave Priority Assignment

Input: Requested Leave Days (D), Balance { Comp, Casual, Unpaid }

1. remaining = D
2. usedFromComp = 0
3. usedFromCasual = 0
4. usedFromUnpaid = 0

4. IF category == 'compensation' THEN
     used = MIN(compBalance, remaining)
     usedFromComp = used
     remaining -= used
   END IF

5. IF remaining > 0 THEN
     used = MIN(casualBalance, remaining)
     usedFromCasual = used
     remaining -= used
   END IF

6. IF remaining > 0 THEN
     usedFromUnpaid = remaining
     Generate WARNING!
   END IF

7. IF usedFromUnpaid == 0 THEN
     No warnings OR info message only
   ELSE
     Generate WARNING with unpaid days
   END IF

Output: { usedFromComp, usedFromCasual, usedFromUnpaid, warnings }
```

---

## 💾 Session Flow

### On Page Load
1. Fetch leave balances from API
2. Store in `currentLeaveBalances` object
3. Ready for form submission

### On Form Submit
1. Collect selected dates and leave types
2. Run `validateLeaveBalance()` check
3. Display appropriate warning/error
4. If warning: wait for user confirmation
5. On confirm: proceed with submission
6. After submission: refresh leave bank display

---

## 🧪 Test Cases

### Test 1: Direct Leave Type with Insufficient Balance
- **Leave Type:** Sick Leave
- **Requested:** 3 days
- **Balance:** 1 day
- **Expected:** ❌ Error modal (Cannot submit)

### Test 2: Flexible Leave with Partial Compensation
- **Leave Type:** Casual Leave
- **Requested:** 5 days
- **Balance:** Comp: 3, Casual: 1, Unpaid: ∞
- **Expected:** ⚠️ Warning modal (2 unpaid days will be used)

### Test 3: Flexible Leave with Full Coverage
- **Leave Type:** Casual Leave
- **Requested:** 2 days
- **Balance:** Comp: 5, Casual: 5, Unpaid: ∞
- **Expected:** ✅ Submit directly (no warnings)

### Test 4: Multiple Leave Types Mixed
- **Leaves:** 2 days Casual + 1 day Sick
- **Balance:** Casual: 1, Sick: 0, Comp: 5
- **Expected:** ❌ Error (Sick leave insufficient)

---

## 📝 Leave Type Categories

| Category | Leave Types | Validation |
|----------|-------------|-----------|
| **short** | Short Leave | Direct only |
| **sick** | Sick Leave | Direct only |
| **paternity** | Paternity Leave | Direct only |
| **maternity** | Maternity Leave | Direct only |
| **compensation** | Compensation Leave, Comp Off | Priority chain (1st) |
| **casual** | Casual Leave | Priority chain (2nd) |
| **unpaid** | Unpaid Leave | Priority chain (3rd) |

---

## 🔗 API Dependency

**API Endpoint:** `../api/get_leave_balances.php`

**Parameters:**
- `year` (int): Year
- `month` (int): Month index (0-11)

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "leave_type": "Casual Leave",
      "remaining_balance": 12,
      "is_locked": false
    },
    {
      "leave_type": "Sick Leave",
      "remaining_balance": 5,
      "is_locked": false
    },
    ...
  ]
}
```

---

## 🎯 Future Enhancements

1. **Advanced Calculations:** Consider half-day and short leave hours
2. **Pending Requests:** Account for pending leave in balance calculations
3. **Department Rules:** Apply department-specific validation rules
4. **Email Notifications:** Notify managers about unpaid leave usage
5. **Audit Trail:** Log all validation decisions and user confirmations

---

## 📞 Support

For issues or questions about the validation system:
1. Check browser console for debug logs (prefixed with 📊, 🔍, ⚠️)
2. Verify API responses in Network tab
3. Test with sample leave data to reproduce issues

---

**Last Updated:** 8 April 2026
**Version:** 1.0
**Status:** ✅ Production Ready
