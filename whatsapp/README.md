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
   # 08:50 AM - First reminder
   50 8 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php field >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   
   # 09:15 AM - Second reminder (both teams)
   15 9 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   
   # 10:00 AM - Third reminder (both teams)
   0 10 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   ```

   **Studio Team Notifications:**
   ```bash
   # 10:30 AM - Final reminder
   30 10 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_scheduled_admin_summary.php studio >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   ```

   **Punch-Out Summary Notifications (Both Teams):**
   ```bash
   # 06:20 PM - First summary
   20 18 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_punchout_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1

   # 07:15 PM - Second summary
   15 19 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_punchout_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1

   # 09:00 PM - Final summary
   0 21 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_punchout_summary.php both >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
   ```

## Team Configuration

The system categorizes employees by role:
- **Field Team**: Site Supervisor, Site Coordinator, Purchase Manager
- **Studio Team**: All other roles

To modify which roles belong to which team, edit `send_punch_notification.php` line ~360.
