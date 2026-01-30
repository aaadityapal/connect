# Production Cron Jobs Setup

## Server Timezone: UTC
## Target Timezone: IST (UTC+5:30)

---

## Punch-In Notifications (Admin Summaries)

### IST Schedule:
- **09:00 AM** - First reminder (Field team)
- **09:30 AM** - Second reminder (Both teams)
- **12:00 PM** - Third and final reminder (Both teams)

### UTC Cron Jobs (for production):

```bash
# 09:00 AM IST = 03:30 AM UTC
30 3 * * * /usr/bin/php /path/to/whatsapp/cron_scheduled_admin_summary.php field >> /path/to/whatsapp/cron.log 2>&1

# 09:30 AM IST = 04:00 AM UTC
0 4 * * * /usr/bin/php /path/to/whatsapp/cron_scheduled_admin_summary.php both >> /path/to/whatsapp/cron.log 2>&1

# 12:00 PM IST = 06:30 AM UTC
30 6 * * * /usr/bin/php /path/to/whatsapp/cron_scheduled_admin_summary.php both >> /path/to/whatsapp/cron.log 2>&1
```

---

## Punch-Out Notifications (With PDF Attachments)

### IST Schedule:
- **06:30 PM** - First summary (Both teams)
- **09:00 PM** - Final summary (Both teams)

### UTC Cron Jobs (for production):

```bash
# 06:30 PM IST = 01:00 PM UTC
0 13 * * * /usr/bin/php /path/to/whatsapp/cron_punchout_summary.php both >> /path/to/whatsapp/cron.log 2>&1

# 09:00 PM IST = 03:30 PM UTC
30 15 * * * /usr/bin/php /path/to/whatsapp/cron_punchout_summary.php both >> /path/to/whatsapp/cron.log 2>&1
```

---

## Complete Crontab for Production

Copy and paste this into your production server's crontab:

```bash
# WhatsApp Attendance Notifications - Conneqts.io
# Server Timezone: UTC | Target: IST (UTC+5:30)

# === PUNCH-IN SUMMARIES ===
# 09:00 AM IST - Field team first reminder
30 3 * * * /usr/bin/php /home/username/public_html/whatsapp/cron_scheduled_admin_summary.php field >> /home/username/public_html/whatsapp/cron.log 2>&1

# 09:30 AM IST - Both teams second reminder
0 4 * * * /usr/bin/php /home/username/public_html/whatsapp/cron_scheduled_admin_summary.php both >> /home/username/public_html/whatsapp/cron.log 2>&1

# 12:00 PM IST - Both teams final reminder
30 6 * * * /usr/bin/php /home/username/public_html/whatsapp/cron_scheduled_admin_summary.php both >> /home/username/public_html/whatsapp/cron.log 2>&1

# === PUNCH-OUT SUMMARIES (WITH PDF) ===
# 06:30 PM IST - First summary with PDF
0 13 * * * /usr/bin/php /home/username/public_html/whatsapp/cron_punchout_summary.php both >> /home/username/public_html/whatsapp/cron.log 2>&1

# 09:00 PM IST - Final summary with PDF
30 15 * * * /usr/bin/php /home/username/public_html/whatsapp/cron_punchout_summary.php both >> /home/username/public_html/whatsapp/cron.log 2>&1
```

---

## How to Install on Production Server

1. **SSH into your server:**
   ```bash
   ssh username@conneqts.io
   ```

2. **Edit crontab:**
   ```bash
   crontab -e
   ```

3. **Paste the cron jobs** from above (update paths if needed)

4. **Save and exit** (`:wq` in vim)

5. **Verify crontab:**
   ```bash
   crontab -l
   ```

---

## Testing

### Test Punch-In Summary:
```bash
php /path/to/whatsapp/cron_scheduled_admin_summary.php both
```

### Test Punch-Out Summary:
```bash
php /path/to/whatsapp/cron_punchout_summary.php both
```

### Monitor Logs:
```bash
tail -f /path/to/whatsapp/cron.log
tail -f /path/to/whatsapp/whatsapp.log
```

---

## Summary Table

| Notification Type | IST Time | UTC Time | Cron Expression | Teams |
|------------------|----------|----------|-----------------|-------|
| Punch-In #1 | 09:00 AM | 03:30 AM | `30 3 * * *` | Field |
| Punch-In #2 | 09:30 AM | 04:00 AM | `0 4 * * *` | Both |
| Punch-In #3 | 12:00 PM | 06:30 AM | `30 6 * * *` | Both |
| Punch-Out #1 | 06:30 PM | 01:00 PM | `0 13 * * *` | Both |
| Punch-Out #2 | 09:00 PM | 03:30 PM | `30 15 * * *` | Both |

---

**Total Cron Jobs:** 5  
**Updated:** January 30, 2026  
**Made With Love By Aditya**
