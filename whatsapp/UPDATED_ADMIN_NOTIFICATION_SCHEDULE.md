# Updated Admin Notification Schedule

## 📅 New Schedule (Effective Immediately)

The admin WhatsApp notification system has been simplified to **2 notifications per day**:

### 1️⃣ **Punch-In Summary**
- **Time**: 10:45 AM IST
- **Teams**: Both Studio and Field teams
- **Content**: 
  - On-time punch-ins with times
  - Late punch-ins with delay minutes
- **Template**: 
  - `admin_studioteam_punchin_late_summary`
  - `admin_fieldteam_punchin_late_summary`

### 2️⃣ **Punch-Out Summary**
- **Time**: 9:00 PM IST
- **Teams**: Both Studio and Field teams
- **Content**: 
  - Professional PDF with all punch-out times
  - Work reports from each employee
  - Total count summary
- **Template**: 
  - `admin_punchout_summary_studio`
  - `admin_punchout_summary_field`
- **Attachment**: PDF report

---

## 🔧 Production Cron Jobs Setup

### For Your Production Server (UTC Timezone)

```bash
# Punch-In Summary at 10:45 AM IST (5:15 AM UTC)
15 5 * * * /usr/bin/php /path/to/whatsapp/cron_scheduled_admin_summary.php both

# Punch-Out Summary at 9:00 PM IST (3:30 PM UTC)
30 15 * * * /usr/bin/php /path/to/whatsapp/cron_punchout_summary.php both
```

### For Local Testing (IST Timezone)

```bash
# Punch-In Summary at 10:45 AM IST
45 10 * * * /usr/bin/php /path/to/whatsapp/cron_scheduled_admin_summary.php both

# Punch-Out Summary at 9:00 PM IST
0 21 * * * /usr/bin/php /path/to/whatsapp/cron_punchout_summary.php both
```

---

## 📊 Daily Flow

```
10:45 AM → Punch-In Summary sent to all admins
          ├─ Studio Team summary
          └─ Field Team summary

09:00 PM → Punch-Out Summary + PDF sent to all admins
          ├─ Studio Team summary with PDF
          └─ Field Team summary with PDF
```

---

## ⚙️ What Changed

### ❌ **Removed Schedules**
- ~~9:00 AM - Field team punch-in~~
- ~~9:30 AM - Both teams punch-in~~
- ~~12:00 PM - Both teams punch-in~~
- ~~6:30 PM - Both teams punch-out~~

### ✅ **New Schedules**
- **10:45 AM** - Single punch-in summary for both teams
- **9:00 PM** - Single punch-out summary for both teams

---

## 🎯 Benefits

1. **Less Notification Fatigue** - Admins receive fewer messages
2. **Better Timing** - 10:45 AM gives employees time to punch in
3. **End of Day Report** - 9:00 PM captures all punch-outs for the day
4. **Simplified Management** - Only 2 cron jobs to maintain

---

## 📝 Files Modified

- `/whatsapp/cron_scheduled_admin_summary.php` - Updated documentation
- `/whatsapp/cron_punchout_summary.php` - Updated documentation

**Note**: The actual PHP logic remains the same, only the schedule documentation and cron timing have changed.

---

## 🚀 Next Steps

1. **Update Production Cron Jobs**:
   - Remove old cron entries
   - Add the 2 new cron entries shown above

2. **Test the Schedule**:
   ```bash
   # Test punch-in summary
   php /path/to/whatsapp/cron_scheduled_admin_summary.php both
   
   # Test punch-out summary
   php /path/to/whatsapp/cron_punchout_summary.php both
   ```

3. **Monitor Logs**:
   - Check `/whatsapp/cron.log` for execution logs
   - Check `/whatsapp/whatsapp.log` for API responses

---

**Last Updated**: February 2, 2026
**Updated By**: Aditya
