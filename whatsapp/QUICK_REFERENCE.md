# Quick Reference: Punch-Out Summary with PDF

## 📋 Template Names

| Team | Old Template | New Template |
|------|-------------|--------------|
| Studio | `admin_studioteam_punchout_summary` | `admin_punchout_summary_studio` |
| Field | `admin_fieldteam_punchout_summary` | `admin_punchout_summary_field` |

## 📝 Template Format

```
Hello Admin,

Punch-out summary for {{1}} (Studio Team / Field Team).

{{2}}

This is an automated attendance notification.

— Team Conneqts
```

**Header:** PDF Document  
**{{1}}:** Date (e.g., "January 30, 2026")  
**{{2}}:** Summary (e.g., "Total employees punched out: 5. Please see attached PDF for detailed work reports.")

## 🔄 Complete Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    CRON JOB TRIGGERS                            │
│  6:20 PM, 7:15 PM, 9:00 PM (IST)                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│         cron_punchout_summary.php (both teams)                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│      sendScheduledPunchOutSummary($pdo, $date, $teamType)      │
│                                                                 │
│  1. Fetch active admins from admin_notifications table         │
│  2. Get punch-out data: getPunchOutDataByTeam()                │
│     - Queries attendance table                                  │
│     - Filters by team (Studio/Field)                           │
│     - Returns: username, punch_out, work_report                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│           generatePunchOutSummaryPDF()                          │
│                                                                 │
│  Creates professional PDF with:                                 │
│  • Company branding (CONNEQTS)                                 │
│  • Date and team information                                    │
│  • Summary box (total employees)                               │
│  • Table: S.No | Name | Time | Work Report                     │
│  • Professional footer                                          │
│                                                                 │
│  Saves to: /uploads/punchout_summaries/                        │
│  Returns: URL, file_path, file_name                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│    WhatsAppService->sendTemplateMessageWithDocument()          │
│                                                                 │
│  For each admin:                                                │
│  • Build Meta API request                                       │
│  • Header component: PDF document                              │
│  • Body parameters: date, summary text                         │
│  • Send via CURL to Meta Graph API                             │
│  • Log response                                                 │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   ADMIN RECEIVES MESSAGE                        │
│                                                                 │
│  WhatsApp Message:                                              │
│  ┌───────────────────────────────────────────┐                │
│  │ 📄 PunchOut_Summary_Studio_2026-01-30.pdf │                │
│  ├───────────────────────────────────────────┤                │
│  │ Hello Admin,                               │                │
│  │                                            │                │
│  │ Punch-out summary for January 30, 2026    │                │
│  │ (Studio Team).                             │                │
│  │                                            │                │
│  │ Total employees punched out: 5.            │                │
│  │ Please see attached PDF for detailed       │                │
│  │ work reports.                              │                │
│  │                                            │                │
│  │ This is an automated attendance            │                │
│  │ notification.                              │                │
│  │                                            │                │
│  │ — Team Conneqts                            │                │
│  └───────────────────────────────────────────┘                │
└─────────────────────────────────────────────────────────────────┘
```

## 📊 PDF Report Structure

```
╔═══════════════════════════════════════════════════════════════╗
║                        CONNEQTS                               ║
║              Attendance & Work Report System                  ║
║───────────────────────────────────────────────────────────────║
║                                                               ║
║              Daily Punch-Out Summary                          ║
║                    Studio Team                                ║
║              Thursday, January 30, 2026                       ║
║              Generated at: 06:20 PM                           ║
║                                                               ║
║───────────────────────────────────────────────────────────────║
║  Total Employees Punched Out: 5                              ║
║───────────────────────────────────────────────────────────────║
║                                                               ║
║  ┌────┬──────────────┬──────────────┬────────────────────┐  ║
║  │S.No│Employee Name │Punch-Out Time│   Work Report      │  ║
║  ├────┼──────────────┼──────────────┼────────────────────┤  ║
║  │ 1  │ John Doe     │  06:30 PM    │ Completed design   │  ║
║  │    │              │              │ mockups for client │  ║
║  ├────┼──────────────┼──────────────┼────────────────────┤  ║
║  │ 2  │ Jane Smith   │  07:00 PM    │ Finished site      │  ║
║  │    │              │              │ inspection report  │  ║
║  ├────┼──────────────┼──────────────┼────────────────────┤  ║
║  │ 3  │ Bob Johnson  │  07:15 PM    │ Updated project    │  ║
║  │    │              │              │ documentation      │  ║
║  └────┴──────────────┴──────────────┴────────────────────┘  ║
║                                                               ║
║  This is an automated report generated by Conneqts           ║
║  Attendance System                                            ║
║  © 2026 Team Conneqts. All rights reserved.                  ║
╚═══════════════════════════════════════════════════════════════╝
```

## 🗂️ File Structure

```
/connect/whatsapp/
├── WhatsAppService.php                    [MODIFIED]
│   └── + sendTemplateMessageWithDocument()
│
├── send_punch_notification.php            [MODIFIED]
│   ├── sendScheduledPunchOutSummary()     [UPDATED]
│   └── + getPunchOutDataByTeam()          [NEW]
│
├── generate_punchout_summary_pdf.php      [NEW]
│   └── generatePunchOutSummaryPDF()
│
├── cron_punchout_summary.php              [EXISTING]
│
├── test_punchout_summary_with_pdf.php     [NEW]
│
├── README.md                              [UPDATED]
└── PUNCHOUT_PDF_IMPLEMENTATION.md         [NEW]

/connect/uploads/
└── punchout_summaries/                    [NEW DIRECTORY]
    ├── PunchOut_Summary_Studio_2026-01-30_182000.pdf
    ├── PunchOut_Summary_Field_2026-01-30_182000.pdf
    └── ...
```

## 🧪 Testing

```bash
# Test URL
http://localhost/connect/whatsapp/test_punchout_summary_with_pdf.php

# Command Line
php /path/to/whatsapp/cron_punchout_summary.php both
```

## ⚙️ Setup Checklist

- [ ] Create templates in Meta Business Manager
  - [ ] `admin_punchout_summary_studio`
  - [ ] `admin_punchout_summary_field`
- [ ] Wait for template approval
- [ ] Create directory: `/uploads/punchout_summaries/`
- [ ] Set permissions: `chmod 777 /uploads/punchout_summaries/`
- [ ] Update BASE_URL in `generate_punchout_summary_pdf.php`
- [ ] Test with test script
- [ ] Update production cron jobs
- [ ] Monitor logs for first few runs

## 📞 Support

**Log Files:**
- `/whatsapp/whatsapp.log` - WhatsApp API responses
- `/whatsapp/cron.log` - Cron execution logs

**Database:**
- `admin_notifications` - Active admin phone numbers
- `attendance` - Punch-out data and work reports

**Key Functions:**
- `sendScheduledPunchOutSummary()` - Main orchestrator
- `getPunchOutDataByTeam()` - Data fetcher
- `generatePunchOutSummaryPDF()` - PDF generator
- `sendTemplateMessageWithDocument()` - WhatsApp sender
