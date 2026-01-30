# Admin Daily Summary Notifications

Send daily punch-in summaries to admins via WhatsApp.

## Setup

1. **Create the table:**
   ```
   http://localhost/connect/whatsapp/create_admin_notifications_table.php
   ```

2. **Add admin phone numbers:**
   ```
   http://localhost/connect/whatsapp/manage_admin_notifications.php
   ```

3. **Test the notifications:**
   ```
   http://localhost/connect/whatsapp/test_admin_summary.php
   ```
   
   **Test scheduled notifications:**
   ```
   http://localhost/connect/whatsapp/test_scheduled_notifications.php
   ```

4. **Setup scheduled cron jobs:**

   **Field Team Notifications:**
   ```bash
   # 09:00 AM - First reminder
   0 9 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php field >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   
   # 09:30 AM - Second reminder (both teams)
   30 9 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   
   # 12:00 PM - Third and final reminder (both teams)
   0 12 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   ```

   **Studio Team Notifications:**
   ```bash
   # Studio team gets reminders at 09:30 AM and 12:00 PM (same as "both" above)
   ```

   **Punch-Out Summary Notifications (Both Teams):**
   ```bash
   # 06:30 PM - First summary (with PDF attachment)
   30 18 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_punchout_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1

   # 09:00 PM - Final summary (with PDF attachment)
   0 21 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_punchout_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   ```

## Team Configuration

The system categorizes employees by role:
- **Field Team**: Site Supervisor, Site Coordinator, Purchase Manager
- **Studio Team**: All other roles

To modify which roles belong to which team, edit `send_punch_notification.php` line ~360.

## WhatsApp Templates Used

### Punch-In Templates:
- `admin_studioteam_punchin_late_summary` - Studio team punch-in summary
- `admin_fieldteam_punchin_late_summary` - Field team punch-in summary

### Punch-Out Templates (with PDF attachment):
- `admin_punchout_summary_studio` - Studio team punch-out summary with work reports PDF
- `admin_punchout_summary_field` - Field team punch-out summary with work reports PDF

**Template Parameters:**
- Punch-In: `{{1}}` Time, `{{2}}` On-time list, `{{3}}` Late list
- Punch-Out: `{{1}}` Date, `{{2}}` Summary text
- Punch-Out Header: PDF document attachment with detailed work reports

## PDF Reports

Punch-out summaries include a professional PDF attachment containing:
- Company branding (Conneqts)
- Date and team information
- Table with: S.No, Employee Name, Punch-Out Time, Work Report
- Automated footer with timestamp

PDFs are stored in: `/uploads/punchout_summaries/`

## Testing

**Test Punch-In Summaries:**
```
http://localhost/connect/whatsapp/test_scheduled_notifications.php
```

**Test Punch-Out Summaries (with PDF):**
```
http://localhost/connect/whatsapp/test_punchout_summary_with_pdf.php
```

