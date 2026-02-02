# Late Punch Alert System

## 📋 Overview

This system sends **individual WhatsApp alerts** to admins when employees punch in or out **after** the scheduled summary times.

---

## 🎯 Alert Types

### 1️⃣ **Late Punch-In Alerts**
- **Trigger**: Employee punches in **after 10:45 AM**
- **Alert Time**: **2:30 PM IST**
- **Template**: `employee_punchin_alert`
- **Message Format**:
  ```
  Hello Admin,

  📌 Punch-In Notification

  Employee {{1}} has successfully punched in today.

  🕒 Time: {{2}}
  📅 Date: {{3}}

  This is an auto-generated update for your records.

  — Team Conneqts
  ```

### 2️⃣ **Late Punch-Out Alerts**
- **Trigger**: Employee punches out **after 9:00 PM**
- **Alert Time**: **11:00 PM IST**
- **Template**: `employee_punchout_alert`
- **Message Format**:
  ```
  Hello Admin,

  📌 Punch-Out Notification

  Employee {{1}} has successfully punched out today.

  🕒 Time: {{2}}
  📅 Date: {{3}}

  This is an auto-generated update for your records.

  — Team Conneqts
  ```

---

## ⏰ Complete Daily Schedule

| Time (IST) | Notification Type | Description |
|------------|------------------|-------------|
| **10:45 AM** | Punch-In Summary | Bulk summary of all punch-ins |
| **2:30 PM** | Late Punch-In Alerts | Individual alerts for punch-ins after 10:45 AM |
| **9:00 PM** | Punch-Out Summary | Bulk summary with PDF |
| **11:00 PM** | Late Punch-Out Alerts | Individual alerts for punch-outs after 9:00 PM |

---

## 📁 Files Created

### **Cron Scripts**
1. `cron_late_punchin_alerts.php` - Sends late punch-in alerts at 2:30 PM
2. `cron_late_punchout_alerts.php` - Sends late punch-out alerts at 11:00 PM

### **Test Scripts**
1. `test_late_punchin_alerts.php` - Test late punch-in alerts manually
2. `test_late_punchout_alerts.php` - Test late punch-out alerts manually

---

## 🔧 Production Cron Jobs Setup

Add these **2 new cron jobs** to your production server:

```bash
# Late Punch-In Alerts at 2:30 PM IST (9:00 AM UTC)
0 9 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_late_punchin_alerts.php

# Late Punch-Out Alerts at 11:00 PM IST (5:30 PM UTC)
30 17 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_late_punchout_alerts.php
```

### **Complete Cron Setup (All 4 Jobs)**

```bash
# 1. Punch-In Summary at 10:45 AM IST (5:15 AM UTC)
15 5 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_scheduled_admin_summary.php both

# 2. Late Punch-In Alerts at 2:30 PM IST (9:00 AM UTC)
0 9 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_late_punchin_alerts.php

# 3. Punch-Out Summary at 9:00 PM IST (3:30 PM UTC)
30 15 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_punchout_summary.php both

# 4. Late Punch-Out Alerts at 11:00 PM IST (5:30 PM UTC)
30 17 * * * /usr/local/bin/php /home/newblogs/public_html/whatsapp/cron_late_punchout_alerts.php
```

---

## 🧪 Testing

### **Test Late Punch-In Alerts**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp
php test_late_punchin_alerts.php
```

### **Test Late Punch-Out Alerts**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp
php test_late_punchout_alerts.php
```

Both test scripts will:
1. Show you how many late punches were found
2. Display the employee details
3. Ask for confirmation before sending
4. Send the alerts if you confirm

---

## 📊 How It Works

### **Late Punch-In Flow**

```
10:45 AM → Regular punch-in summary sent
          ↓
Employee punches in at 11:30 AM
          ↓
2:30 PM → Cron runs
          ↓
Finds punch-ins between 10:45 AM - 2:30 PM
          ↓
Sends individual alert to all admins for each employee
```

### **Late Punch-Out Flow**

```
9:00 PM → Regular punch-out summary sent
         ↓
Employee punches out at 10:15 PM
         ↓
11:00 PM → Cron runs
         ↓
Finds punch-outs between 9:00 PM - 11:00 PM
         ↓
Sends individual alert to all admins for each employee
```

---

## 💡 Example Scenarios

### **Scenario 1: Late Punch-In**
- **10:45 AM**: Summary sent (shows 20 employees punched in)
- **11:30 AM**: John Doe punches in
- **12:15 PM**: Jane Smith punches in
- **2:30 PM**: Admins receive 2 individual alerts:
  - Alert for John Doe (11:30 AM)
  - Alert for Jane Smith (12:15 PM)

### **Scenario 2: Late Punch-Out**
- **9:00 PM**: Summary sent with PDF (shows 18 employees punched out)
- **9:45 PM**: Mike Ross punches out
- **10:30 PM**: Rachel Zane punches out
- **11:00 PM**: Admins receive 2 individual alerts:
  - Alert for Mike Ross (9:45 PM)
  - Alert for Rachel Zane (10:30 PM)

---

## 🔍 Logging

All alerts are logged to: `/whatsapp/cron_alerts.log`

Example log entries:
```
[2026-02-02 14:30:00] ===== Late Punch-In Alerts Started =====
[2026-02-02 14:30:01] Alert sent to Admin HR for John Doe punch-in at 11:30 AM
[2026-02-02 14:30:02] Alert sent to Admin Manager for John Doe punch-in at 11:30 AM
[2026-02-02 14:30:03] Late punch-in alerts completed. Total alerts sent: 4 (for 2 employees to 2 admins)
[2026-02-02 14:30:03] ===== Late Punch-In Alerts Completed =====
```

---

## ⚠️ Important Notes

1. **Multiple Admins**: Each late punch triggers alerts to **ALL active admins**
2. **Time Window**: 
   - Punch-in: Catches punches from 10:45 AM to 2:30 PM
   - Punch-out: Catches punches from 9:00 PM to 11:00 PM
3. **No Duplicates**: Each employee is reported once per alert cycle
4. **Template Requirements**: Ensure templates are approved in WhatsApp Business Manager

---

## 📱 WhatsApp Templates Required

Make sure these templates are created and approved:

| Template Name | Variables | Status |
|---------------|-----------|--------|
| `employee_punchin_alert` | {{1}} Name, {{2}} Time, {{3}} Date | ⚠️ Need to create |
| `employee_punchout_alert` | {{1}} Name, {{2}} Time, {{3}} Date | ⚠️ Need to create |

---

**Created**: February 2, 2026  
**Author**: Aditya
