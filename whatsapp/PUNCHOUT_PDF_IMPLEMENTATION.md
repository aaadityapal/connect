# Punch-Out Summary with PDF Attachments - Implementation Guide

## Overview

This document explains the implementation of the enhanced punch-out summary notification system that sends professional PDF reports to admins via WhatsApp.

## What Changed

### 1. New WhatsApp Templates

**Previous Templates:**
- `admin_studioteam_punchout_summary`
- `admin_fieldteam_punchout_summary`

**New Templates:**
- `admin_punchout_summary_studio`
- `admin_punchout_summary_field`

### 2. Template Structure

Both new templates follow this format:

```
Hello Admin,

Punch-out summary for {{1}} (Studio Team / Field Team).

{{2}}

This is an automated attendance notification.

— Team Conneqts
```

**Header Component:** PDF document attachment

**Body Parameters:**
- `{{1}}` - Date (formatted as "January 30, 2026")
- `{{2}}` - Summary text (e.g., "Total employees punched out: 5. Please see attached PDF for detailed work reports.")

## Files Modified/Created

### Created Files:

1. **`generate_punchout_summary_pdf.php`**
   - Generates professional PDF reports with work reports
   - Uses TCPDF library
   - Creates branded documents with company logo and styling

2. **`test_punchout_summary_with_pdf.php`**
   - Test script to verify PDF generation and WhatsApp sending
   - Shows generated PDFs and logs

### Modified Files:

1. **`WhatsAppService.php`**
   - Added `sendTemplateMessageWithDocument()` method
   - Supports sending template messages with PDF attachments via Meta Cloud API

2. **`send_punch_notification.php`**
   - Updated `sendScheduledPunchOutSummary()` function
   - Added `getPunchOutDataByTeam()` function
   - Now generates PDFs and attaches them to WhatsApp messages

3. **`README.md`**
   - Updated documentation with new template information
   - Added PDF report details

## PDF Report Features

### Professional Design:
- **Company Branding**: "CONNEQTS" header with blue color scheme
- **Clear Title**: "Daily Punch-Out Summary" with team name
- **Date Information**: Full date and generation time
- **Summary Box**: Total employees count

### Table Structure:
| S.No | Employee Name | Punch-Out Time | Work Report |
|------|---------------|----------------|-------------|
| 1    | John Doe      | 06:30 PM       | Completed site inspection... |
| 2    | Jane Smith    | 07:00 PM       | Finished design mockups... |

### Additional Features:
- Automatic page breaks for long reports
- Alternating row colors for readability
- Professional footer with copyright
- Multi-line work report support
- Clean, modern typography

## How It Works

### Step-by-Step Flow:

1. **Cron Job Triggers** (e.g., 6:20 PM, 7:15 PM, 9:00 PM)
   ```bash
   /usr/bin/php cron_punchout_summary.php both
   ```

2. **Fetch Punch-Out Data**
   - Queries attendance table for today's punch-outs
   - Filters by team type (Studio or Field)
   - Retrieves: username, punch_out time, work_report

3. **Generate PDF**
   - Calls `generatePunchOutSummaryPDF()`
   - Creates professional PDF with TCPDF
   - Saves to `/uploads/punchout_summaries/`
   - Returns public URL for WhatsApp

4. **Send WhatsApp Message**
   - Uses `sendTemplateMessageWithDocument()`
   - Sends to all active admins
   - Includes PDF as header attachment
   - Template body shows date and summary

5. **Logging**
   - Logs success/failure to `whatsapp.log`
   - Logs cron execution to `cron.log`

## Meta WhatsApp Cloud API Integration

### Document Attachment Format:

```json
{
  "messaging_product": "whatsapp",
  "to": "919876543210",
  "type": "template",
  "template": {
    "name": "admin_punchout_summary_studio",
    "language": {"code": "en_US"},
    "components": [
      {
        "type": "header",
        "parameters": [
          {
            "type": "document",
            "document": {
              "link": "https://conneqts.io/uploads/punchout_summaries/PunchOut_Summary_Studio_2026-01-30_182000.pdf",
              "filename": "PunchOut_Summary_Studio_2026-01-30_182000.pdf"
            }
          }
        ]
      },
      {
        "type": "body",
        "parameters": [
          {"type": "text", "text": "January 30, 2026"},
          {"type": "text", "text": "Total employees punched out: 5. Please see attached PDF for detailed work reports."}
        ]
      }
    ]
  }
}
```

## Template Setup in Meta Business Manager

### Required Steps:

1. **Create Template in Meta Business Manager**
   - Name: `admin_punchout_summary_studio`
   - Category: Utility
   - Language: English (US)

2. **Template Content:**
   ```
   Hello Admin,

   Punch-out summary for {{1}} (Studio Team).

   {{2}}

   This is an automated attendance notification.

   — Team Conneqts
   ```

3. **Add Header Component:**
   - Type: Document
   - This allows PDF attachment

4. **Submit for Approval**
   - Wait for Meta approval (usually 24-48 hours)

5. **Repeat for Field Team Template**
   - Name: `admin_punchout_summary_field`
   - Same structure, just change "Studio Team" to "Field Team"

## Testing

### Local Testing:

1. **Access Test Page:**
   ```
   http://localhost/connect/whatsapp/test_punchout_summary_with_pdf.php
   ```

2. **What It Tests:**
   - Fetches today's punch-out data
   - Generates PDFs for both teams
   - Sends WhatsApp messages to admins
   - Displays generated PDFs
   - Shows recent logs

3. **Expected Output:**
   - ✓ Field team summary sent successfully
   - ✓ Studio team summary sent successfully
   - List of generated PDF files
   - Recent log entries

### Manual Testing:

```bash
# Test from command line
php /path/to/whatsapp/cron_punchout_summary.php both
```

## File Storage

### PDF Files Location:
```
/uploads/punchout_summaries/
```

### File Naming Convention:
```
PunchOut_Summary_{TeamType}_{Date}_{Time}.pdf

Example:
PunchOut_Summary_Studio_2026-01-30_182000.pdf
PunchOut_Summary_Field_2026-01-30_191500.pdf
```

### Public URL:
```
https://conneqts.io/uploads/punchout_summaries/PunchOut_Summary_Studio_2026-01-30_182000.pdf
```

## Error Handling

### Scenarios Handled:

1. **No Punch-Outs Today**
   - Sends message: "No punch-outs recorded yet for today."
   - No PDF generated

2. **PDF Generation Fails**
   - Logs error to `whatsapp.log`
   - Returns false, doesn't send WhatsApp message

3. **WhatsApp API Fails**
   - Logs error with admin phone number
   - Continues to next admin
   - Reports success count at end

4. **No Active Admins**
   - Logs warning
   - Returns false

## Production Deployment

### 1. Update Cron Jobs (UTC Timezone):

```bash
# 06:20 PM IST = 12:50 PM UTC
50 12 * * * /usr/bin/php /path/to/cron_punchout_summary.php both >> /path/to/cron.log 2>&1

# 07:15 PM IST = 01:45 PM UTC
45 13 * * * /usr/bin/php /path/to/cron_punchout_summary.php both >> /path/to/cron.log 2>&1

# 09:00 PM IST = 03:30 PM UTC
30 15 * * * /usr/bin/php /path/to/cron_punchout_summary.php both >> /path/to/cron.log 2>&1
```

### 2. Ensure Directory Permissions:

```bash
mkdir -p /path/to/uploads/punchout_summaries
chmod 777 /path/to/uploads/punchout_summaries
```

### 3. Verify BASE_URL:

In `generate_punchout_summary_pdf.php`:
```php
define('BASE_URL', 'https://conneqts.io');
```

### 4. Test Templates Approved:

- Verify both templates are approved in Meta Business Manager
- Test with real admin phone numbers

## Monitoring

### Check Logs:

```bash
# WhatsApp API logs
tail -f /path/to/whatsapp/whatsapp.log

# Cron execution logs
tail -f /path/to/whatsapp/cron.log
```

### Verify PDFs:

```bash
# List recent PDFs
ls -lht /path/to/uploads/punchout_summaries/ | head -20
```

### Check Admin Notifications Table:

```sql
SELECT * FROM admin_notifications WHERE is_active = 1;
```

## Troubleshooting

### Issue: PDFs not generating

**Check:**
- TCPDF library exists at `/tcpdf/tcpdf.php`
- Directory `/uploads/punchout_summaries/` exists and is writable
- Check `whatsapp.log` for errors

### Issue: WhatsApp messages not sending

**Check:**
- Templates approved in Meta Business Manager
- Template names match exactly
- Admin phone numbers in correct format (e.g., 919876543210)
- WhatsApp API token is valid

### Issue: PDF URL not accessible

**Check:**
- BASE_URL is correct
- PDF directory is publicly accessible
- File permissions are correct (644 for files, 755 for directories)

## Benefits

### For Admins:
- ✅ Clean, professional PDF reports
- ✅ Easy to review work reports offline
- ✅ Can forward/share PDF easily
- ✅ Better record keeping

### For System:
- ✅ Reduced message length (no long text in WhatsApp)
- ✅ Better formatting and readability
- ✅ Archived reports for future reference
- ✅ Professional appearance

## Future Enhancements

Potential improvements:
1. Add charts/graphs to PDF (attendance trends)
2. Include photos from work reports
3. Email PDF as backup
4. Weekly/monthly summary PDFs
5. Customizable PDF templates
6. Export to Excel option

---

**Implementation Date:** January 30, 2026  
**Version:** 2.0  
**Author:** Conneqts Development Team
