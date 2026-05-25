# Sales WhatsApp API Integration

This folder contains a dedicated integration for the **Sales** WhatsApp Business account.

## Configuration
- **File**: `config.php`
- **Phone Number ID**: `892965963910715`
- **Business Account ID**: `2044076599773351`
- **Token**: Set as `SALES_WHATSAPP_ACCESS_TOKEN`. Ensure this is a permanent system user token.

## Template Requirement (CRITICAL)

To send the pricing PDF to users who haven't messaged you first (or outside the 24h window), you **MUST** create a template in the Meta WhatsApp Manager.

### Step 1: Create Template
1. Go to [WhatsApp Manager > Message Templates](https://business.facebook.com/wa/manage/message-templates/)
2. Create a new template:
   - **Category**: `Utility` or `Marketing`
   - **Name**: `a_hive_pricing_notification_v7`
   - **Language**: `English (US)` (`en_US`)

### Step 2: Configure Template Structure
- **Header**: Select **Media** -> **Document**.
- **Body**: 
  > Hello {{1}},
  > 
  > Your requested pricing document is attached to this message.
  > 
  > It contains the finalized details for your architectural design plan.
  > 
  > *Plan Type: {{3}}*
  > *Amount Payable: ₹{{2}}*
  > 
  > -Team A.Hive (Sales)

  (Variables: {{1}}=Name, {{2}}=Amount, {{3}}=Plan Name)

- **Footer**: `This is an automated message!`
- **Buttons**: Visit Website (URL: `https://architectshive.com` or dynamic if configured)

### Step 3: Publish
Submit for review. It usually takes a few seconds to get approved.

## Testing
- Use `test_send.php` (open in browser) to:
  - Send a simple text (only works if you messaged the bot recently).
  - Send the `share_pricing_pdf` template.
  - Upload and send a PDF document directly.

- Check `list_templates.php` to see all approved templates and their status.

## Webhook
- URL: `https://your-domain.ngrok-free.app/archweb/whatsapp_sales_api/webhook.php`
- Verify Token: `sales_secret_696969` (or whatever you set in config.php)

## Integration Usage
The main integration is in `api/generate_pricing_pdf.php`. It automatically uploads the generated PDF and sends it using the `share_pricing_pdf` template.
