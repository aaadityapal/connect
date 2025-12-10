# 🐛 Vendor Category Filter - Bug Fixes

## Date: 2025-12-10

---

## Issues Found

### **Bug #1: Auto-Selection of "PERMANENT LABOUR"**

**Problem:**
When opening the "Paid To" filter dropdown, "PERMANENT LABOUR" and other items were getting auto-selected incorrectly.

**Root Cause:**
In `purchase_manager_dashboard.php` (Line 2517), the code used `.includes()` for substring matching:

```javascript
// ❌ BUGGY CODE
if (selectedCategories && selectedCategories.includes(contractor.id.toString())) {
    checkbox.checked = true;
}
```

**Why This Failed:**
- If `selectedCategories = "1"` (user selected contractor with ID 1)
- Contractor with ID `"10"`, `"11"`, `"21"`, `"100"`, etc. would ALL match
- `"10".includes("1")` returns `true` ✗ (WRONG!)
- This caused multiple unrelated contractors to be auto-selected

**Fix Applied:**
Changed to exact ID matching using `split()` and `find()`:

```javascript
// ✅ FIXED CODE
if (selectedCategories) {
    const selectedIds = selectedCategories.split(',').map(id => id.trim());
    const isSelected = selectedIds.find(id => id === contractor.id.toString());
    
    if (isSelected) {
        checkbox.checked = true;
    }
}
```

**File:** `purchase_manager_dashboard.php` (Lines 2515-2529)

---

### **Bug #2: Wrong Data Displayed (HGW vs BCD)**

**Problem:**
When user selected "HGW" from "Supplier_sand_aggregate" category, the table showed data for "BCD" and other contractors in the same category.

**Root Cause:**

**Frontend Issue (Lines 1977-1999):**
The JavaScript was sending **category types** instead of **specific contractor IDs**:

```javascript
// ❌ BUGGY CODE
const selectedCategoryTypes = Array.from(vendorCategoryFilterOptions)
    .filter(opt => checkbox && checkbox.checked)
    .map(opt => opt.getAttribute('data-category-type'))  // Gets "Supplier_sand_aggregate"
    .join(',');

loadPaymentEntries(..., selectedCategoryTypes, ...);  // Sends category, not IDs
```

**Backend Issue (Lines 79-93, 203-217):**
The API was filtering by category types, matching ALL contractors in that category:

```php
// ❌ BUGGY CODE
$count_query .= "v.vendor_type_category IN ($placeholders)";
// This matches ALL vendors with category "Supplier_sand_aggregate"
```

**Why This Failed:**
1. User selects "HGW" (ID: 123, Category: "Supplier_sand_aggregate")
2. Frontend sends: `vendorCategory = "Supplier_sand_aggregate"`
3. Backend filters: `WHERE vendor_type_category = "Supplier_sand_aggregate"`
4. Result: Returns ALL contractors with that category (HGW, BCD, etc.) ✗ (WRONG!)

**Fix Applied:**

**Frontend Fix:**
Send specific contractor IDs instead of category types:

```javascript
// ✅ FIXED CODE
const selectedContractors = Array.from(vendorCategoryFilterOptions)
    .filter(opt => checkbox && checkbox.checked)
    .map(opt => opt.getAttribute('data-vendor-category'))  // Gets "123"
    .join(',');

entriesPaginationState.vendorCategory = selectedContractors;
loadPaymentEntries(..., selectedContractors, ...);  // Sends "123"
```

**Backend Fix:**
Filter by specific vendor_id or labour id:

```php
// ✅ FIXED CODE
$contractorIds = array_map('trim', explode(',', $vendorCategory));

if (!empty($contractorIds)) {
    $placeholders = implode(',', array_fill(0, count($contractorIds), '?'));
    $count_query .= " AND (";
    $count_query .= "v.vendor_id IN ($placeholders)";
    $count_query .= " OR lr.id IN ($placeholders)";
    $count_query .= " )";
    $count_params = array_merge($count_params, $contractorIds, $contractorIds);
}
```

**Files Modified:**
- `purchase_manager_dashboard.php` (Lines 1962-2000, 2033-2038)
- `get_payment_entries.php` (Lines 79-93, 203-217)

---

## Testing Instructions

### Test Case 1: Auto-Selection Bug
1. Open "Paid To" filter
2. Select "Aditya Kumar Pal" (ID: 1)
3. Click "Apply"
4. Reopen "Paid To" filter
5. **Expected:** Only "Aditya Kumar Pal" should be checked
6. **Previously:** "Aditya Kumar Pal" (ID: 1), "GHJ" (ID: 10), etc. were all checked

### Test Case 2: Wrong Data Display
1. Open "Paid To" filter
2. Select ONLY "HGW" from "Supplier_sand_aggregate"
3. Click "Apply"
4. **Expected:** Table shows only payments to HGW
5. **Previously:** Table showed payments to HGW, BCD, and all other "Supplier_sand_aggregate" contractors

### Test Case 3: Multiple Contractors
1. Select "HGW" (Supplier_sand_aggregate) AND "Aditya Kumar Pal" (Permanent Labour)
2. Click "Apply"
3. **Expected:** Table shows only payments to these 2 specific contractors
4. **Previously:** Would show all Supplier_sand_aggregate + all Permanent Labour

---

## Impact

✅ **Fixed:** Auto-selection of unrelated contractors  
✅ **Fixed:** Wrong data display when filtering by specific contractors  
✅ **Improved:** Filter now works with exact contractor IDs, not category types  
✅ **Enhanced:** State management properly stores and restores selected contractors  

---

## Technical Details

### Data Flow (Before Fix)
```
User selects "HGW" (ID: 123)
    ↓
Frontend extracts category: "Supplier_sand_aggregate"
    ↓
API receives: vendorCategory = "Supplier_sand_aggregate"
    ↓
SQL: WHERE vendor_type_category = "Supplier_sand_aggregate"
    ↓
Returns: ALL contractors in that category ❌
```

### Data Flow (After Fix)
```
User selects "HGW" (ID: 123)
    ↓
Frontend extracts ID: "123"
    ↓
API receives: vendorCategory = "123"
    ↓
SQL: WHERE vendor_id = 123 OR labour_id = 123
    ↓
Returns: ONLY HGW ✅
```

---

## Notes

- The fix maintains backward compatibility with existing filter state
- Multiple contractor selection still works correctly
- Pagination preserves selected contractors across pages
- Clear filter properly resets all selections
