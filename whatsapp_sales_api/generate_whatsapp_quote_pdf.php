<?php
/**
 * WhatsApp Bot – Quotation PDF Generator
 * 
 * Called internally by webhook.php when a user completes the Get Quote flow.
 * Reads session variables, calculates the price using the same priceConfig as
 * the plans/ pages, generates a professional PDF and sends it via WhatsApp.
 */

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/WhatsAppClient.php';

/**
 * Main entry point – call this from webhook.php after a "Database Save" node
 * for the Get Quote flow.
 *
 * @param string $toPhone   WhatsApp phone number (E.164 or local)
 * @param array  $vars      Session variables collected by the bot
 * @return bool             True on success
 */
function generateAndSendQuotePDF(string $toPhone, array $vars): bool
{
    logSalesDebug("generateAndSendQuotePDF: Starting for $toPhone");

    // ------------------------------------------------------------------ //
    // 1. Parse collected session variables
    // ------------------------------------------------------------------ //
    $personalInfo  = $vars['personalInfo']  ?? '';   // "Name, Email, Location"
    $plotSize      = (int)($vars['plotSize'] ?? 0);  // Sq.Ft.
    $extraFloors   = $vars['extraFloors']   ?? 'None';
    $elevationRaw  = $vars['elevationDesign'] ?? '';  // "Front" / "Rear" / "Side 1" / "Side 2"
    $requiresVastu = strtolower($vars['requiresVastu'] ?? 'no');
    $siteVisitsRaw = $vars['extraSiteVisits'] ?? 'None';

    // Parse name/email/location from single field
    $infoParts = array_map('trim', explode(',', $personalInfo, 3));
    $clientName     = $infoParts[0] ?? 'Valued Customer';
    $clientEmail    = $infoParts[1] ?? '';
    $clientLocation = $infoParts[2] ?? 'N/A';

    // ------------------------------------------------------------------ //
    // 2. Determine plan tier from plot size
    // ------------------------------------------------------------------ //
    $planTier = getPlanTier($plotSize);

    // ------------------------------------------------------------------ //
    // 3. Map bot answers → priceConfig keys (match plans/ pages exactly)
    // ------------------------------------------------------------------ //

    // Floor: "None" | "1 Floor" | "2 Floors" | "3 Floors" | "4 Floors"
    $floorKey = mapFloor($extraFloors);

    // Elevation: The bot stores one choice per step, collect via comma list if needed
    $elevationKeys = mapElevation($elevationRaw);

    // Vastu: "Yes" → "Vastu", "No" → "Not Required"
    $vastuKey = ($requiresVastu === 'yes') ? 'Vastu' : 'Not Required';

    // Site visits: "None" | "5 Visits" | "10 Visits" | "15 Visits" | "20 Visits"
    $visitsKey = mapVisits($siteVisitsRaw);

    // ------------------------------------------------------------------ //
    // 4. Calculate price using the same config as the plan pages
    // ------------------------------------------------------------------ //
    $pricing    = getPlanPricing($planTier);
    $totalPrice = calculateQuotePrice($pricing, $floorKey, $elevationKeys, $vastuKey, $visitsKey);

    $planName = "Architectural Design Package ({$planTier})";

    // ------------------------------------------------------------------ //
    // 5. Build a text-based quotation summary and send it directly first
    // ------------------------------------------------------------------ //
    $client = new SalesWhatsAppClient();

    $summaryLines = [
        "🏗️ *Your Architecture Quotation*",
        "──────────────────────────",
        "👤 *Name:* {$clientName}",
        "📍 *Location:* {$clientLocation}",
        "",
        "📏 *Plot Size:* {$plotSize} Sq.Ft. ({$planTier})",
        "🏢 *Extra Floors:* {$floorKey}  (+₹" . number_format($pricing['floor'][$floorKey] ?? 0, 2) . ")",
        "🏛️ *Elevation Design:* " . (empty($elevationKeys) ? 'None' : implode(', ', $elevationKeys)) . "  (+₹" . number_format(array_sum(array_map(fn($k) => $pricing['elevation'][$k] ?? 0, $elevationKeys)), 2) . ")",
        "☯️ *Vastu Compliance:* {$vastuKey}  (+₹" . number_format($pricing['vastu'][$vastuKey] ?? 0, 2) . ")",
        "🚗 *Extra Site Visits:* {$visitsKey}  (+₹" . number_format($pricing['visits'][$visitsKey] ?? 0, 2) . ")",
        "",
        "──────────────────────────",
        "💰 *Base Package Price:* ₹" . number_format($pricing['base'], 2),
        "✅ *Total Estimate:* ₹" . number_format($totalPrice, 2),
        "──────────────────────────",
        "",
        "_This is an estimated quotation. Final pricing may vary based on site inspection._",
        "",
        "📞 Our team will contact you shortly to confirm the details and schedule a site visit!"
    ];

    $summaryMsg = implode("\n", $summaryLines);
    $client->sendMessage($toPhone, $summaryMsg);
    logSalesDebug("generateAndSendQuotePDF: Text quotation sent to $toPhone");

    // ------------------------------------------------------------------ //
    // 6. Try to generate & send a PDF (requires FPDF libs present)
    // ------------------------------------------------------------------ //
    try {
        $pdfPath = buildQuotePDF(
            $clientName,
            $toPhone,
            $clientEmail,
            $clientLocation,
            $plotSize,
            $planTier,
            $planName,
            $floorKey,
            $elevationKeys,
            $vastuKey,
            $visitsKey,
            $pricing,
            $totalPrice
        );

        if ($pdfPath && file_exists($pdfPath)) {
            // Upload to WhatsApp Media API
            $mediaId = uploadSalesMedia($pdfPath);
            if ($mediaId) {
                $caption = "Hi {$clientName}! 👋\n\nPlease find attached your personalised *Architectural Quotation* from ArchitectsHive.\n\nTotal Estimate: *₹" . number_format($totalPrice, 2) . "*\n\nOur team will be in touch shortly! 🤝";
                $waResult = sendSalesWhatsAppDocument($toPhone, $mediaId, 'ArchitectsHive_Quotation.pdf', $caption);
                if ($waResult['success']) {
                    logSalesDebug("generateAndSendQuotePDF: PDF sent successfully to $toPhone");
                } else {
                    logSalesDebug("generateAndSendQuotePDF: PDF send failed: " . json_encode($waResult));
                }
            }

            // Also notify admin
            $adminNumbers = ['917503468992'];
            foreach ($adminNumbers as $adminPhone) {
                $adminMsg = "🔔 *New Quotation Lead*\n\n"
                    . "👤 Name: {$clientName}\n"
                    . "📱 Phone: {$toPhone}\n"
                    . "📍 Location: {$clientLocation}\n"
                    . "📏 Plot: {$plotSize} Sq.Ft.\n"
                    . "💰 Quoted: ₹" . number_format($totalPrice, 2);
                $client->sendMessage($adminPhone, $adminMsg);
            }
        }
    } catch (Exception $e) {
        logSalesDebug("generateAndSendQuotePDF: PDF generation failed: " . $e->getMessage());
        // Text quote already sent above – this is a soft failure
    }

    return true;
}

// ------------------------------------------------------------------ //
// Helpers
// ------------------------------------------------------------------ //

function getPlanTier(int $sqft): string
{
    if ($sqft <= 500)  return 'Upto 500 Sq.Ft.';
    if ($sqft <= 1000) return '501-1000 Sq.Ft.';
    if ($sqft <= 1500) return '1001-1500 Sq.Ft.';
    if ($sqft <= 2000) return '1501-2000 Sq.Ft.';
    if ($sqft <= 2500) return '2001-2500 Sq.Ft.';
    if ($sqft <= 3000) return '2501-3000 Sq.Ft.';
    if ($sqft <= 3500) return '3001-3500 Sq.Ft.';
    if ($sqft <= 4000) return '3501-4000 Sq.Ft.';
    if ($sqft <= 4500) return '4001-4500 Sq.Ft.';
    if ($sqft <= 5000) return '4501-5000 Sq.Ft.';
    return '5000+ Sq.Ft.';
}

/**
 * Pricing configs matching the JavaScript priceConfig objects in plans/ pages.
 */
function getPlanPricing(string $tier): array
{
    // Common add-ons are the same across tiers; base price varies.
    $bases = [
        'Upto 500 Sq.Ft.'    => 7499.00,
        '501-1000 Sq.Ft.'    => 9999.00,
        '1001-1500 Sq.Ft.'   => 13999.00,
        '1501-2000 Sq.Ft.'   => 17999.00,
        '2001-2500 Sq.Ft.'   => 21999.00,
        '2501-3000 Sq.Ft.'   => 25999.00,
        '3001-3500 Sq.Ft.'   => 29999.00,
        '3501-4000 Sq.Ft.'   => 33999.00,
        '4001-4500 Sq.Ft.'   => 37999.00,
        '4501-5000 Sq.Ft.'   => 41999.00,
        '5000+ Sq.Ft.'       => 49999.00,
    ];

    $floorPricePerFloor = [
        'Upto 500 Sq.Ft.'    => 6000.00,
        '501-1000 Sq.Ft.'    => 8000.00,
        '1001-1500 Sq.Ft.'   => 10000.00,
        '1501-2000 Sq.Ft.'   => 12000.00,
        '2001-2500 Sq.Ft.'   => 14000.00,
        '2501-3000 Sq.Ft.'   => 16000.00,
        '3001-3500 Sq.Ft.'   => 18000.00,
        '3501-4000 Sq.Ft.'   => 20000.00,
        '4001-4500 Sq.Ft.'   => 22000.00,
        '4501-5000 Sq.Ft.'   => 24000.00,
        '5000+ Sq.Ft.'       => 28000.00,
    ];

    $fpp = $floorPricePerFloor[$tier] ?? 8000.00;
    $elevationPrice = ($bases[$tier] ?? 9999) <= 9999 ? 5500.00 : 6000.00;

    return [
        'base' => $bases[$tier] ?? 9999.00,
        'floor' => [
            'None'    => 0.00,
            '1 Floor' => $fpp * 1,
            '2 Floors'=> $fpp * 2,
            '3 Floors'=> $fpp * 3,
            '4 Floors'=> $fpp * 4,
        ],
        'elevation' => [
            'Front'  => $elevationPrice,
            'Rear'   => $elevationPrice,
            'Side 1' => $elevationPrice,
            'Side 2' => $elevationPrice,
        ],
        'vastu' => [
            'Not Required'    => 0.00,
            'Vastu'           => 1250.00,
            'Scientific Vastu'=> 12000.00,
        ],
        'visits' => [
            'None'           => 0.00,
            '5 Site Visits'  => 8250.00,
            '10 Site Visits' => 16000.00,
            '15 Site Visits' => 23250.00,
            '20 Site Visits' => 30000.00,
        ],
    ];
}

function mapFloor(string $raw): string
{
    // Exact match against the button labels stored in bot session variables
    $map = [
        'None'     => 'None',
        '1 Floor'  => '1 Floor',
        '2 Floors' => '2 Floors',
        '3 Floors' => '3 Floors',
        '4 Floors' => '4 Floors',
    ];
    return $map[trim($raw)] ?? 'None';
}

function mapElevation(string $raw): array
{
    // Exact match: bot stores the button label verbatim
    $valid = ['Front', 'Rear', 'Side 1', 'Side 2'];
    $raw   = trim($raw);
    return in_array($raw, $valid, true) ? [$raw] : [];
}

function mapVisits(string $raw): string
{
    // Bot stores "5 Visits", "10 Visits", etc. — map to pricing table keys
    $map = [
        'None'      => 'None',
        '5 Visits'  => '5 Site Visits',
        '10 Visits' => '10 Site Visits',
        '15 Visits' => '15 Site Visits',
        '20 Visits' => '20 Site Visits',
    ];
    return $map[trim($raw)] ?? 'None';
}

function calculateQuotePrice(array $pricing, string $floorKey, array $elevationKeys, string $vastuKey, string $visitsKey): float
{
    $total = $pricing['base'];
    $total += $pricing['floor'][$floorKey] ?? 0;
    foreach ($elevationKeys as $ek) {
        $total += $pricing['elevation'][$ek] ?? 0;
    }
    $total += $pricing['vastu'][$vastuKey] ?? 0;
    $total += $pricing['visits'][$visitsKey] ?? 0;
    return $total;
}

/**
 * Generate a clean quotation PDF using FPDF (no template required).
 */
function buildQuotePDF(
    string $name, string $phone, string $email, string $location,
    int $sqft, string $tier, string $planName,
    string $floorKey, array $elevationKeys,
    string $vastuKey, string $visitsKey,
    array $pricing, float $totalPrice
): ?string {
    $fpdfPath = dirname(__DIR__) . '/libs/fpdf.php';
    if (!file_exists($fpdfPath)) {
        logSalesDebug("buildQuotePDF: FPDF not found at $fpdfPath. Skipping PDF.");
        return null;
    }

    require_once $fpdfPath;

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // --- Header ---
    $pdf->SetFillColor(26, 26, 26);
    $pdf->Rect(0, 0, 210, 40, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetY(12);
    $pdf->Cell(0, 10, 'ArchitectsHive', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, 'Architectural Quotation', 0, 1, 'C');

    // --- Client Info ---
    $pdf->SetY(50);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetFillColor(231, 76, 60);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, '  Client Details', 0, 1, 'L', true);

    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetFillColor(248, 248, 248);

    $clientRows = [
        ['Name',     $name],
        ['Phone',    $phone],
        ['Email',    $email ?: 'N/A'],
        ['Location', $location],
        ['Plot Size',"$sqft Sq.Ft. ($tier)"],
    ];
    foreach ($clientRows as $i => [$label, $value]) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 7, "  $label:", 0, 0, 'L', true);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(120, 7, "  $value", 0, 1, 'L', true);
    }

    $pdf->Ln(5);

    // --- Pricing Breakdown ---
    $pdf->SetFillColor(231, 76, 60);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, '  Pricing Breakdown', 0, 1, 'L', true);

    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(26, 26, 26);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(110, 7, '  Item', 0, 0, 'L', true);
    $pdf->Cell(70,  7, 'Amount (INR)', 0, 1, 'R', true);

    $lineItems = [
        ["Base Package – $planName",              $pricing['base']],
        ["Extra Floors – $floorKey",              $pricing['floor'][$floorKey] ?? 0],
    ];
    foreach ($elevationKeys as $ek) {
        $lineItems[] = ["Elevation – $ek", $pricing['elevation'][$ek] ?? 0];
    }
    if (empty($elevationKeys)) {
        $lineItems[] = ['Elevation Design', 0];
    }
    $lineItems[] = ["Vastu – $vastuKey",   $pricing['vastu'][$vastuKey] ?? 0];
    $lineItems[] = ["Site Visits – $visitsKey", $pricing['visits'][$visitsKey] ?? 0];

    $pdf->SetTextColor(50, 50, 50);
    foreach ($lineItems as $i => [$desc, $amount]) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(110, 7, "  $desc", 0, 0, 'L', true);
        $pdf->SetFont('Arial', $amount > 0 ? '' : 'I', 10);
        $pdf->Cell(70, 7, ($amount > 0 ? '+' : '') . 'Rs.' . number_format($amount, 2), 0, 1, 'R', true);
    }

    // Total row
    $pdf->SetFillColor(26, 26, 26);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(110, 9, '  TOTAL ESTIMATE', 0, 0, 'L', true);
    $pdf->Cell(70,  9, 'Rs.' . number_format($totalPrice, 2), 0, 1, 'R', true);

    $pdf->Ln(6);

    // --- Notes ---
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->MultiCell(0, 5,
        "* This is an estimated quotation only. Actual pricing may vary based on site inspection, structural complexity, and specific customization requirements.\n" .
        "* GST will be applicable as per government norms.\n" .
        "* Valid for 30 days from the date of issue.",
        0, 'L'
    );

    $pdf->Ln(4);

    // --- Footer ---
    $pdf->SetFillColor(26, 26, 26);
    $pdf->Rect(0, 277, 210, 20, 'F');
    $pdf->SetY(280);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, 'ArchitectsHive  |  www.architectshive.com  |  Generated: ' . date('d M Y'), 0, 0, 'C');

    // --- Save ---
    $outputDir = dirname(__DIR__) . '/uploads/pricing_pdfs/';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $filename   = 'wa_quote_' . $cleanPhone . '_' . time() . '.pdf';
    $outputPath = $outputDir . $filename;
    $pdf->Output('F', $outputPath);

    logSalesDebug("buildQuotePDF: Saved to $outputPath");
    return $outputPath;
}
