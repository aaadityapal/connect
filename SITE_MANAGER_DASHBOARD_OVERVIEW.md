# Site Manager Dashboard - System Overview

## 📋 **File Information**

- **File**: `site_manager_dashboard.php`
- **Size**: 4,869 lines (195 KB)
- **Purpose**: Complete dashboard for Site Managers with attendance tracking
- **Type**: Full-featured management dashboard

---

## 🎯 **System Purpose**

The **Site Manager Dashboard** is a comprehensive management interface designed for:
- Site Managers
- Senior Managers (Site)
- Site Coordinators
- Senior Managers (Purchase)
- Purchase Managers

---

## 🏗️ **Main Components**

### **1. Attendance/Punch System**
- **Punch In/Out Button**: Prominent button in greeting section
- **Camera Modal**: Takes selfie for punch in/out
- **Work Report**: Required for punch out
- **Geofencing**: Validates location
- **Outside Location Reason**: Required if outside geofence
- **Backend**: Uses `process_punch.php` for processing

### **2. Project Management**
- View active projects
- Track project progress
- Monitor budgets
- Manage supervisors
- Project status tracking (Progress, Pending, Hold, Completed)

### **3. Dashboard Features**
- **Stats Grid**:
  - Active Projects: 8
  - Active Sites: 12
  - Supervisors: 24
  - Workers: 356
  - Open Issues: 15

- **Recent Activities**: Timeline of recent events
- **Tasks**: Task list with priorities
- **Notifications**: Dropdown notification system
- **Profile**: User profile management

### **4. UI/UX Features**
- **Dynamic Greeting**: Changes based on time of day
- **Diwali Theme**: Decorative elements (diyas, firecrackers, sparkles)
- **Responsive Design**: Mobile and desktop support
- **Dark/Light Theme**: Theme toggle
- **Animations**: Smooth transitions and effects

---

## 🔄 **Punch In/Out Flow**

### **Frontend Flow:**
```
User clicks "Punch In" button → Camera modal opens → 
User takes selfie → Photo captured → 
(If punch out: Work report required) →
(If outside geofence: Reason required) →
Data sent to process_punch.php → 
Database updated → Success message
```

### **Key JavaScript Variables:**
```javascript
let isPunchedIn = false;  // Tracks punch status
const punchButton = document.getElementById('punchButton');
const confirmPunchBtn = document.getElementById('confirmPunchBtn');
```

### **Data Sent to Backend:**
```javascript
formData.append('punch_type', isPunchedIn ? 'out' : 'in');
formData.append('photo', capturedPhotoData);
formData.append('latitude', position.coords.latitude);
formData.append('longitude', position.coords.longitude);
formData.append('address', address);
formData.append('within_geofence', withinGeofence);
formData.append('distance_from_geofence', distance);

// For punch out:
formData.append('work_report', workReportText.value);
formData.append('punch_out_work_report', workReportText.value);

// If outside geofence:
if (isPunchedIn) {
    formData.append('punch_out_outside_reason', outsideLocationReason.value);
} else {
    formData.append('punch_in_outside_reason', outsideLocationReason.value);
}
```

### **Backend Processing:**
- **File**: `process_punch.php` (in root directory)
- **Also exists**: `api/process_punch.php`
- Handles both punch in and punch out
- Saves to `attendance` table
- Validates geofencing
- Processes photos
- Stores work reports

---

## 📊 **System Comparison**

| Feature | Main System | Maid | Sales | **Site Manager** |
|---------|-------------|------|-------|------------------|
| **File** | `ajax_handlers/submit_attendance.php` | `maid/api_punch_in.php` | `sales/api_punch_in.php` | **`process_punch.php`** |
| **Interface** | Simple dashboard | Mobile app | CRM dashboard | **Full management dashboard** |
| **Punch Method** | Single file (in/out) | Separate files | Separate files | **Single file (in/out)** |
| **Work Report** | Optional | Mandatory (20 words) | Mandatory (20 words) | **Mandatory for punch out** |
| **Geofencing** | ✅ | ✅ | ✅ | ✅ |
| **Photo** | ✅ | ✅ | ✅ | ✅ |
| **WhatsApp Notifications** | ✅ | ✅ | ✅ | **❌ NOT Implemented** |
| **Project Management** | ❌ | ❌ | ❌ | **✅ Yes** |
| **Team Management** | ❌ | ❌ | ❌ | **✅ Yes** |

---

## ⚠️ **Current Status**

### **✅ What's Working:**
1. ✅ Complete dashboard interface
2. ✅ Punch in/out functionality
3. ✅ Camera/selfie capture
4. ✅ Geofencing validation
5. ✅ Work report requirement
6. ✅ Outside location reason
7. ✅ Project management UI
8. ✅ Stats and analytics
9. ✅ Notification system
10. ✅ Responsive design

### **❌ What's Missing:**
1. ❌ **WhatsApp Notifications** (NOT implemented)
2. ❌ Real-time project data (currently static demo data)
3. ❌ Database integration for projects
4. ❌ Live stats updates

---

## 🎨 **UI/UX Design**

### **Design Features:**
- **Professional**: Clean, modern management interface
- **Desktop-First**: Optimized for managers using laptops
- **Responsive**: Works on mobile too
- **Themed**: Diwali decorations with animations
- **Icon-Rich**: Font Awesome icons throughout

### **Color Scheme:**
```css
--primary-color: #0d6efd;
--success-color: #28a745;
--danger-color: #dc3545;
--warning-color: #ffc107;
--info-color: #17a2b8;
--secondary-color: #6c757d;
```

### **Special Effects:**
- **Diwali Decorations**:
  - Animated diyas (oil lamps)
  - Firecracker animations
  - Sparkling effects
  - Glowing animations

### **Animations:**
```css
@keyframes diyaGlow { ... }
@keyframes flameFlicker { ... }
@keyframes firecrackerExplode { ... }
@keyframes sparkleTwinkle { ... }
```

---

## 📁 **File Structure**

### **Main File:**
- `site_manager_dashboard.php` (4,869 lines)

### **CSS Dependencies:**
```html
<link rel="stylesheet" href="css/manager/dashboard.css">
<link rel="stylesheet" href="css/manager/site-overview.css">
<link rel="stylesheet" href="css/manager/calendar-stats.css">
<link rel="stylesheet" href="css/manager/calendar-event-modal.css">
<link rel="stylesheet" href="css/supervisor/new-travel-expense-modal.css">
<link rel="stylesheet" href="css/manager/event-details-modal.css">
```

### **Backend Files:**
- `process_punch.php` - Main punch processing
- `api/process_punch.php` - Alternative API endpoint

---

## 🔐 **Access Control**

### **Allowed Roles:**
```php
$allowed_roles = [
    'Site Manager',
    'Senior Manager (Site)',
    'Site Coordinator',
    'Senior Manager (Purchase)',
    'Purchase Manager'
];
```

### **Authentication:**
- Session-based authentication
- Role-based access control
- Redirects to login if not authenticated
- Redirects if wrong role

---

## 📊 **Data Structure**

### **Projects Array:**
```php
$projects = [
    [
        'id' => 1,
        'title' => 'Residential Tower - Phase 1',
        'location' => 'Mumbai, Maharashtra',
        'status' => 'progress',
        'progress' => 68,
        'budget' => '₹4.2 Cr',
        'start_date' => '2023-07-15',
        'end_date' => '2024-03-30',
        'supervisors' => [...]
    ],
    // ... more projects
];
```

### **Activities Array:**
```php
$activities = [
    [
        'id' => 1,
        'title' => 'Budget approved for Commercial Complex',
        'time' => '2 hours ago',
        'user' => 'Finance Department',
        'icon' => 'money-bill-wave',
        'color' => 'success'
    ],
    // ... more activities
];
```

### **Tasks Array:**
```php
$tasks = [
    [
        'id' => 1,
        'title' => 'Review budget for Q2',
        'due' => 'Today',
        'priority' => 'high'
    ],
    // ... more tasks
];
```

---

## 🚀 **Punch System Details**

### **Punch Button States:**
```javascript
// Initial state
<button class="punch-button" id="punchButton">
    <i class="fas fa-sign-in-alt"></i> Punch In
</button>

// After punch in
<button class="punch-button" id="punchButton">
    <i class="fas fa-sign-out-alt"></i> Punch Out
</button>
```

### **Camera Modal:**
```html
<h3 class="camera-title" id="cameraTitle">
    Take Selfie for Punch In
</h3>
<!-- Changes to "Take Selfie for Punch Out" when punched in -->
```

### **Work Report:**
```javascript
// Show work report only for punch out
workReportContainer.style.display = isPunchedIn ? 'block' : 'none';
```

### **Validation:**
```javascript
// Work report validation for punch out
if (isPunchedIn && workReportContainer.style.display === 'block') {
    if (!workReportText.value.trim()) {
        showNotification('Please enter your work report before punching out', 'warning');
        return;
    }
}
```

---

## 🔍 **Key Differences from Other Systems**

### **1. Single File for Both Punch In/Out**
Unlike maid and sales systems which have separate `api_punch_in.php` and `api_punch_out.php`, the site manager dashboard uses a single `process_punch.php` file that handles both operations based on the `punch_type` parameter.

### **2. Integrated Dashboard**
The punch system is embedded within a full management dashboard, not a standalone attendance page.

### **3. Project Management**
Unique to this system - includes project tracking, team management, and budget monitoring.

### **4. Role-Based Access**
Supports multiple management roles, not just a single user type.

### **5. Static Demo Data**
Currently uses hardcoded arrays for projects, activities, and tasks instead of database queries.

---

## 📝 **Recommendations**

### **1. Implement WhatsApp Notifications** ⭐ **HIGH PRIORITY**
The site manager dashboard is missing WhatsApp notifications that are implemented in other systems.

**Implementation Steps:**
1. Add WhatsApp notification code to `process_punch.php`
2. Call `sendPunchNotification()` after successful punch in
3. Call `sendPunchOutNotification()` after successful punch out
4. Use same templates as other systems

### **2. Database Integration**
Replace static arrays with database queries:
- Projects from `projects` table
- Activities from activity log
- Tasks from task management system
- Real-time stats

### **3. Consolidate Backend Files**
Decide between `process_punch.php` and `api/process_punch.php`:
- Keep one as primary
- Make other redirect or deprecate
- Update all references

### **4. Add Real-Time Updates**
- WebSocket or polling for live stats
- Real-time notifications
- Live project status updates

---

## 🎯 **Summary**

The **Site Manager Dashboard** is a **comprehensive management interface** with:

✅ **Complete attendance system** (punch in/out)
✅ **Project management** (unique feature)
✅ **Team oversight** (supervisors, workers)
✅ **Stats and analytics**
✅ **Professional UI/UX** (with Diwali theme)
✅ **Role-based access control**

❌ **Missing WhatsApp notifications** (should be implemented)
❌ **Static demo data** (should be database-driven)

---

## 🚀 **Next Steps**

**Would you like me to:**
1. ✅ **Implement WhatsApp notifications** for the site manager punch system?
2. ✅ **Review and update** `process_punch.php` to add notifications?
3. ✅ **Ensure consistency** with other attendance systems?

---

**Created**: 2026-01-27  
**Version**: 1.0  
**Status**: ✅ Analysis Complete
