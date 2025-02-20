<?php
// Add this function at the top of your PHP section
function numberToWords($number) {
    $ones = array(
        0 => "", 1 => "one", 2 => "two", 3 => "three", 4 => "four", 
        5 => "five", 6 => "six", 7 => "seven", 8 => "eight", 9 => "nine",
        10 => "ten", 11 => "eleven", 12 => "twelve", 13 => "thirteen", 
        14 => "fourteen", 15 => "fifteen", 16 => "sixteen", 17 => "seventeen",
        18 => "eighteen", 19 => "nineteen"
    );
    $tens = array(
        2 => "twenty", 3 => "thirty", 4 => "forty", 5 => "fifty",
        6 => "sixty", 7 => "seventy", 8 => "eighty", 9 => "ninety"
    );
    $hundreds = array(
        "hundred", "thousand", "lakh", "crore"
    );

    if ($number == 0) return "zero";

    $words = "";

    if ($number >= 10000000) { // Crores
        $words .= numberToWords(floor($number/10000000)) . " crore ";
        $number = $number%10000000;
    }
    
    if ($number >= 100000) { // Lakhs
        $words .= numberToWords(floor($number/100000)) . " lakh ";
        $number = $number%100000;
    }
    
    if ($number >= 1000) { // Thousands
        $words .= numberToWords(floor($number/1000)) . " thousand ";
        $number = $number%1000;
    }
    
    if ($number >= 100) {
        $words .= numberToWords(floor($number/100)) . " hundred ";
        $number = $number%100;
    }

    if ($number > 0) {
        if ($number < 20) 
            $words .= $ones[$number];
        else {
            $words .= $tens[floor($number/10)];
            if ($number%10) $words .= "-" . $ones[$number%10];
        }
    }

    return ucwords(trim($words));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get current year and month for reference number
    $year = date("Y");
    $month = date("m");
    $refNo = "AH/$year/$month/001";

    // Get all form inputs
    $date = htmlspecialchars($_POST['date']);
    $clientName = htmlspecialchars($_POST['clientName']);
    $fatherName = htmlspecialchars($_POST['fatherName']);
    $permanentAddress = htmlspecialchars($_POST['permanentAddress']);
    $siteAddress = htmlspecialchars($_POST['siteAddress']);
    $mobile = htmlspecialchars($_POST['mobile']);
    $email = htmlspecialchars($_POST['email']);
    
    // Handle subject field with custom option
    $subject = $_POST['subject'];
    if ($subject === 'custom') {
        $subject = htmlspecialchars($_POST['customSubject']);
    }

    $project = htmlspecialchars($_POST['project']);
    $scopeOfWork = htmlspecialchars($_POST['scopeOfWork']);

    $squareFeet = htmlspecialchars($_POST['squareFeet']);
    $ratePerSqFt = 40; // You can adjust this rate as needed
    $basicServiceCharge = $squareFeet * $ratePerSqFt;
    
    // Calculate site visits based on square feet
    $siteVisits = 3; // Base visits for up to 1000 sq ft
    if ($squareFeet > 1000) {
        $additionalArea = $squareFeet - 1000;
        $siteVisits += ceil($additionalArea / 500); // Add 1 visit per 500 sq ft
    }

    // Process room selections and generate room configuration sentence
    $bedrooms = isset($_POST['bedrooms']) ? $_POST['bedrooms'] : [];
    $commonAreas = isset($_POST['commonAreas']) ? $_POST['commonAreas'] : [];
    $extraRooms = isset($_POST['extraRooms']) ? $_POST['extraRooms'] : [];

    // Generate room configuration sentence (without the introductory line)
    $roomConfigSentence = "";

    // Add bedrooms to sentence
    $bedroomParts = [];
    foreach($bedrooms as $bedroom) {
        $count = $_POST[str_replace(' ', '_', $bedroom) . '_count'] ?? 1; // Get the count for each bedroom type
        $bedroomParts[] = "$count " . htmlspecialchars($bedroom) . ($count > 1 ? 's' : ''); // Pluralize if count > 1
    }
    if (!empty($bedroomParts)) {
        $roomConfigSentence .= implode(", ", $bedroomParts);
    }

    // Add common areas to sentence
    if (!empty($commonAreas)) {
        if (!empty($bedroomParts)) {
            $roomConfigSentence .= ", ";
        }
        $roomConfigSentence .= implode(", ", $commonAreas);
    }

    // Add extra rooms to sentence
    $extraRoomParts = [];
    foreach($extraRooms as $roomId) {
        $roomName = htmlspecialchars($_POST['extra_room_name_' . $roomId]);
        $roomCount = $_POST['extra_room_count_' . $roomId];
        $extraRoomParts[] = "$roomCount " . $roomName . ($roomCount > 1 ? 's' : '');
    }
    if (!empty($extraRoomParts)) {
        if (!empty($bedroomParts) || !empty($commonAreas)) {
            $roomConfigSentence .= ", ";
        }
        $roomConfigSentence .= "and additional spaces including " . implode(", ", $extraRoomParts);
    }

    $roomConfigSentence .= ".";

    // Get consultancy fees and calculate final amount
    $consultancyFees = floatval(htmlspecialchars($_POST['consultancyFees']));
    $discountPercentage = isset($_POST['discountPercentage']) ? floatval(htmlspecialchars($_POST['discountPercentage'])) : 0;
    $discountAmount = ($consultancyFees * $discountPercentage) / 100;
    $finalAmount = $consultancyFees - $discountAmount;

    // Convert final amount to words
    $feesInWords = numberToWords($finalAmount);

    // Then process the services
    $services = isset($_POST['services']) ? $_POST['services'] : [];
    $selectedServices = '';

    if (!empty($services)) {
        $selectedServices = "
        <style>
            .services-list {
                list-style: none;
                counter-reset: item;
                padding-left: 30px;
            }
            .services-list li {
                font-size: 15px;
                counter-increment: item;
                margin-bottom: 8 px;
                padding-left: 2.5em;
                text-align: justify;
                text-justify: inter-word;
                position: relative;
                line-height: 1.6;
            }
            .services-list li:before {
                content: counter(item) '.';
                font-weight: bold;
                margin-right: 12px;
                position: absolute;
                left: 0px;
            }
            /* Page break classes */
            .page-break-after {
                page-break-after: always;
            }
            .page-break-before {
                page-break-before: always;
                margin-top: 40px;
                padding-top: 20px;
            }
            .avoid-break {
                page-break-inside: avoid;
            }
            /* Logo styling for all pages */
            .logo-section {
                text-align: right;
                margin-bottom: 30px;
                position: relative;
            }
            .logo-section img {
                max-width: 200px;
                height: auto;
            }
            /* Second page content */
            .second-page-content {
                margin-top: 40px;
            }
            /* Consultancy fees section styling */
            .consultancy-fees {
                margin-top: 40px;
            }
            .fees-list {
                margin-bottom: 8px;
            }
            .account-details {
                margin-top: 15px;
                background-color: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
            }
            /* Print styles for logo */
            @media print {
                .logo-section {
                    position: running(logo);
                }
                @page {
                    @top-right {
                        content: element(logo);
                    }
                }
            }
        </style>";
        
        $selectedServices .= "<ol class='services-list'>";
        foreach($services as $index => $service) {
            // Add page break before the third point
            if ($index === 2) {
                $selectedServices .= "</ol>";
                $selectedServices .= "<div class='footer' style='text-align: left;'><p> Page No. | 1 </p></div>";
                $selectedServices .= "<hr style='border: none; height: 4px; background-color: blue; -webkit-print-color-adjust: exact; print-color-adjust: exact;'>";
                $selectedServices .= "<div class='footer-2' style=' text-align: left; padding-left: 240px;'><p>F-52, First Floor, Near Gurudwara, Madhu Vihar, <br>I.P. Extension, Delhi-110092 Phone: 9958600397, 7503468992, <br>Email: info@architectshive.com Website: www.architectshive.com</p></div>";
                $selectedServices .= "<div class='page-break-before'>";
                $selectedServices .= "<div class='logo-section'><img src='arch (1).png' alt='Company Logo'></div>";
                $selectedServices .= "<ol class='services-list' style='counter-reset: item " . ($index) . ";'>";
            }
            
            // Handle dynamic services
            if ($service === 'project_area_config') {
                $service = "$project having $roomConfigSentence";
            } else if ($service === 'site_visits') {
                $visitsCount = isset($_POST['visits_count']) ? (int)$_POST['visits_count'] : 3; // Define visits count
                $service = "$visitsCount Nos. Site Visits shall be provided. For Extra Site Visits please visit on www.architectshive.com/bookappointments and book site visits with Architect / Structure / Designer / Interior Designer.";
            }
            
            $selectedServices .= "<li>" . htmlspecialchars($service) . "</li>";
        }
        $selectedServices .= "</ol>";
        
        if (count($services) > 2) {
            $selectedServices .= "</div>";
        }

        // Add consultancy fees section to the second page without page break
        $selectedServices .= "
        <div class='consultancy-fees'>
            <h4>Consultancy Fees:</h4>
            <div class='fees-list'>
                <ol style='list-style: none; counter-reset: item; padding-left: 30px;'>
                    <li style='font-size: 15px; counter-increment: item; margin-bottom: 6px; padding-left: 2.5em; text-align: justify; text-justify: inter-word; position: relative;'>
                        <span style='font-weight: bold; position: absolute; left: 0px;'>1.</span>
                        Consultancy fees shall be charged over the above-mentioned designing area at INR $consultancyFees/-" . 
                        ($discountPercentage > 0 ? " Discount Applied  $discountPercentage%, making the total consultancy fees INR $finalAmount/-" : "") .
                        " (Rupees $feesInWords Only).
                    </li>
                    <li style='font-size: 15px; counter-increment: item; margin-bottom: 6px; padding-left: 2.5em; text-align: justify; text-justify: inter-word; position: relative;'>
                        <span style='font-weight: bold; position: absolute; left: 0px;'>2.</span>
                        GST shall be applicable as per Govt. Norms included in Consultancy Fees.
                    </li>
                    <li style='font-size: 15px; counter-increment: item; margin-bottom: 6px; padding-left: 2.5em; text-align: justify; text-justify: inter-word; position: relative;'>
                        <span style='font-weight: bold; position: absolute; left: 0px;'>3.</span>
                        Kindly Refer to the Accounts Detail:
                        <div class='account-details'>
                            <div style='margin-bottom: 8px;'><strong>Account Holder Name:</strong> <span style='margin-left: 12px;'>Prabhat Arya</span></div>
                            <div style='margin-bottom: 8px;'><strong>Account Number:</strong> <span style='margin-left: 50px;'>03101614505</span></div>
                            <div style='margin-bottom: 8px;'><strong>IFSC Code:</strong> <span style='margin-left: 98px;'>ICIC0000031</span></div>
                            <div style='margin-bottom: 8px;'><strong>Bank Name:</strong> <span style='margin-left: 91px;'>ICICI Bank, Sector 18 Noida, Branch.</span></div>
                            <div style='margin-bottom: 8px;'><strong>GPay Number:</strong> <span style='margin-left: 72px;'>9958600397</span></div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>";
    }

    // Payment schedule calculations
    $stage1Payment = $finalAmount * 0.20; // 20%
    $stage2Payment = $finalAmount * 0.30; // 30%
    $stage3Payment = $finalAmount * 0.30; // 30%
    $stage4Payment = $finalAmount * 0.20; // 20%

    // Format the date
    $dateObj = new DateTime($_POST['date']);
    $date = $dateObj->format('d-M-Y'); // This will format date as DD-MMM-YYYY

    // Update dynamic content for Site Visits and Design Options in the agreement template
    $designOptionsCount = isset($_POST['design_count']) ? (int)$_POST['design_count'] : 2; // Default to 2 if not set
    $siteVisitsCount = isset($_POST['visits_count']) ? (int)$_POST['visits_count'] : 3; // Default to 3 if not set

    // Initialize and generate payment schedule table BEFORE creating agreement template
    $paymentScheduleTable = "<div class='page-break-before'>
        <div class='logo-section'>
            <img src='arch (1).png' alt='Company Logo'>
        </div>
        <h4>Payment Schedule</h4>
        <table class='payment-table' style='border-collapse: collapse; width: 100%;'>
            <thead>
                <tr>
                    <th style='border: 1px solid black; width: 5%; font-size: 13px;'>S.No.</th>
                    <th style='border: 1px solid black; width: 35%; font-size: 13px;'>Stage</th>
                    <th style='border: 1px solid black; width: 60%; font-size: 13px;'>Deliverables</th>
                </tr>
            </thead>
            <tbody>";

    // Get the payment stages and substages from the form
    $stageNames = $_POST['stage_name'] ?? [];
    $stagePercentages = $_POST['stage_percentage'] ?? [];
    $substageNames = $_POST['substage_name'] ?? [];
    $substagePercentages = $_POST['substage_percentage'] ?? [];

    // Track current substage index
    $currentSubstageIndex = 0;

    // Combine stages and percentages
    for ($i = 0; $i < count($stageNames); $i++) {
        $stageName = htmlspecialchars($stageNames[$i]);
        $percentage = floatval($stagePercentages[$i]);
        $amount = ($finalAmount * $percentage) / 100;
        
        $paymentScheduleTable .= "
            <tr>
                <td style='border: 1px solid black; text-align: center; font-size: 15px;'>" . ($i + 1) . "</td>
                <td style='border: 1px solid black; font-size: 15px;'>" . $stageName . " (₹" . number_format($amount, 2) . "/-)</td>
                <td style='border: 1px solid black; font-size: 15px;'>";

        // Count substages for this stage
        $substageCount = 0;
        while (isset($substageNames[$currentSubstageIndex + $substageCount])) {
            $substageCount++;
        }

        if ($substageCount > 0) {
            // Add substages as bullet points
            $paymentScheduleTable .= "
                <ul style='list-style-type: none; padding-left: 0; margin: 0;'>";
            
            for ($j = 0; $j < $substageCount; $j++) {
                $substageName = htmlspecialchars($substageNames[$currentSubstageIndex]);
                $substagePercentage = floatval($substagePercentages[$currentSubstageIndex]);
                $substageAmount = ($amount * $substagePercentage) / 100;
                
                $paymentScheduleTable .= "
                    <li style='font-size: 15px;'>• " . $substageName . " (₹" . number_format($substageAmount, 2) . "/-)</li>";
                
                $currentSubstageIndex++;
            }
            
            $paymentScheduleTable .= "
                </ul>";
        } else {
            // Add default deliverables based on stage number
            switch ($i) {
                case 0:
                    $paymentScheduleTable .= "
                        <ul style='list-style-type: none; padding-left: 0; margin: 0;'>
                            <li style='font-size: 15px;'>• Advance</li>
                        </ul>";
                    break;
                case 1:
                    $paymentScheduleTable .= "
                        <ul style='list-style-type: none; padding-left: 0; margin: 0;'>
                            <li style='font-size: 15px;'>• Two options of Layout (Conceptual Design) & Furniture</li>
                            <li style='font-size: 15px;'>• Layout as per client's requirement incorporating Vastu.</li>
                            <li style='font-size: 15px;'>• Concept PPT for Interior options as per Clients Requirement.</li>
                            <li style='font-size: 15px;'>• Finalization of 3D View for Bedroom - 1 Nos.</li>
                            <li style='font-size: 15px;'>• False Ceiling drawing for Bedroom, Kitchen and Living Area to start the work over site.</li>
                        </ul>";
                    break;
                case 2:
                    $paymentScheduleTable .= "
                        <ul style='list-style-type: none; padding-left: 0; margin: 0;'>
                            <li style='font-size: 15px;'>• After Finalization of Interior 3D views of Lobby, Kitchen.</li>
                            <li style='font-size: 15px;'>• After Finalization of Working drawings for flooring.</li>
                            <li style='font-size: 15px;'>• Electrical Drawing.</li>
                            <li style='font-size: 15px;'>• False Ceiling Drawing for living area</li>
                            <li style='font-size: 15px;'>• Flooring Drawing</li>
                            <li style='font-size: 15px;'>• Plumbing drawing</li>
                            <li style='font-size: 15px;'>• Water Supply drawing</li>
                            <li style='font-size: 15px;'>• Wall Panelling detail of 1 No. Bedroom and Kitchen Details.</li>
                        </ul>";
                    break;
                case 3:
                    $paymentScheduleTable .= "
                        <ul style='list-style-type: none; padding-left: 0; margin: 0;'>
                            <li style='font-size: 15px;'>• After finalization of Interior 3 D view for kids bedroom and prayer room.</li>
                            <li style='font-size: 15px;'>• False Ceiling Drawing(All other Rooms)</li>
                            <li style='font-size: 15px;'>• Toilet Detail (Tiles Placement)</li>
                        </ul>";
                    break;
                default:
                    $paymentScheduleTable .= "
                        <ul style='list-style-type: none; padding-left: 0; margin: 0;'>
                            <li style='font-size: 15px;'>• Detail Drawing for Toilet and 3D view.</li>
                            <li style='font-size: 15px;'>• Wall Panelling details of Prayer Room & Kids Bedroom.</li>
                            <li style='font-size: 15px;'>• Toilet Detail</li>
                            <li style='font-size: 15px;'>• Compiled PDF for all drawings</li>
                            <li style='font-size: 15px;'>• Remaining site visits, if any.</li>
                        </ul>";
            }
        }

        $paymentScheduleTable .= "</td></tr>";
    }

    $paymentScheduleTable .= "
            </tbody>
        </table>
    </div>";

    // Now create the agreement template with the payment schedule table
    $agreementTemplate = "
    <div class='agreement-container'>
        <div class='logo-section'>
            <img src='arch (1).png' alt='Company Logo'>
        </div>

        <div class='client-info-section'>
            <div class='info-row'>
                <div class='info-label'><strong><i>Ref. No.:</i></strong></div>
                <div class='info-value'><i>$refNo</i></div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong><i>Date:</i></strong></div>
                <div class='info-value'><i>$date</i></div>
            </div>
            <br>
            <div class='info-row'>
                <div class='info-label'><strong>Client:</strong></div>
                <div class='info-value'>$clientName</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Father's Name:</strong></div>
                <div class='info-value'>$fatherName</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Permanent Address:</strong></div>
                <div class='info-value'>$permanentAddress</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Mobile No.:</strong></div>
                <div class='info-value'>$mobile</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>E-mail ID:</strong></div>
                <div class='info-value'>$email</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Subject:</strong></div>
                <div class='info-value'>$subject</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Site Address:</strong></div>
                <div class='info-value'>$siteAddress</div>
            </div>
            <div class='info-row'>
                <div class='info-label'><strong>Project:</strong></div>
                <div class='info-value'>$project</div>
            </div>
        </div>

        <div class='agreement-section'>
            <h4 class='section-title'>Scope of Work:</h4>
            
            <div class='content-list'>
                <div class='list-item'>
                    <span class='number'>1.</span>
                    <span class='text'>Design Options: <strong>$designOptionsCount</strong> Nos. Concept Drawings</span>
                </div>

                <div class='list-item'>
                    <span class='number'>2.</span>
                    <span class='text'>Site Visits: <strong>$siteVisitsCount</strong> Nos.</span>
                </div>

                <div class='list-item'>
                    <span class='number'>3.</span>
                    <span class='text'>Concept PPT For Reference of Interior & Exterior of the Building</span>
                </div>

                <div class='list-item'>
                    <span class='number'>4.</span>
                    <span class='text'>As per Accurate Measurements</span>
                </div>

                <div class='list-item'>
                    <span class='number'>5.</span>
                    <span class='text'>Design By Architects Only</span>
                </div>

                <div class='list-item'>
                    <span class='number'>6.</span>
                    <span class='text'>Following shall be provided in Package:</span>
                </div>

                <div class='sub-list'>
                    <div class='sub-item'>
                        <span class='letter'>a)</span>
                        <span class='text'>Architectural Drawings</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>b)</span>
                        <span class='text'>Structural Drawings</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>c)</span>
                        <span class='text'>Electrical Drawings</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>d)</span>
                        <span class='text'>Plumbing Drawings</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>e)</span>
                        <span class='text'>Water Supply Drawings</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>f)</span>
                        <span class='text'>Staircase Details</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>g)</span>
                        <span class='text'>Door Window Details</span>
                    </div>
                    <div class='sub-item'>
                        <span class='letter'>h)</span>
                        <span class='text'>Finishing Schedule</span>
                    </div>
                </div>
            </div>
        </div>

        <h4>Services by M/S ArchitectsHive:</h4>
        $selectedServices
        
        <h4>Notes:</h4>
        <ul style='list-style-type: none; padding-left: 30px;'>
            <li style='margin-bottom: 8px; text-align: justify; text-justify: inter-word; font-size: 15px; padding-left: 2.5em; text-indent: -2.5em;'>
                <strong style='font-size: 15px;'>1.</strong>&nbsp;&nbsp;&nbsp;&nbsp;All payments should be made through Bank Transfer, NEFT, RTGS, UPI or Cheque only. Cash payments are strictly not accepted.
            </li>
            <li style='margin-bottom: 8px; text-align: justify; text-justify: inter-word; font-size: 15px; padding-left: 2.5em; text-indent: -2.5em;'>
                <strong style='font-size: 15px;'>2.</strong>&nbsp;&nbsp;&nbsp;&nbsp;Please ensure to share payment screenshots/receipts immediately after making the payment for our records.
            </li>
            <li style='margin-bottom: 8px; text-align: justify; text-justify: inter-word; font-size: 15px; padding-left: 2.5em; text-indent: -2.5em;'>
                <strong style='font-size: 15px;'>3.</strong>&nbsp;&nbsp;&nbsp;Drawings and designs will be shared only after receipt and clearance of respective stage payments.
            </li>
            <li style='margin-bottom: 8px; text-align: justify; text-justify: inter-word; font-size: 15px; padding-left: 2.5em; text-indent: -2.5em;'>
                <strong style='font-size: 15px;'>4.</strong>&nbsp;&nbsp;&nbsp;&nbsp;GST invoices will be generated only after payment clearance and receipt confirmation.
            </li>
            <li style='margin-bottom: 8px; text-align: justify; text-justify: inter-word; font-size: 15px; padding-left: 2.5em; text-indent: -2.5em;'>
                <strong style='font-size: 15px;'>5.</strong>&nbsp;&nbsp;&nbsp;&nbsp;Any delay in payments may result in postponement of project timeline and delivery schedules.
            </li>
        </ul>
        <br>

        <div class='footer' style='text-align: left;'>
            <p> Page No. | 2 </p>
        </div>
        <hr style='border: none; height: 4px; background-color: blue; -webkit-print-color-adjust: exact; print-color-adjust: exact;'>
        <div class='footer' style='text-align: left; padding-left: 240px;'>
            <p>F-52, First Floor, Near Gurudwara, Madhu Vihar, <br>I.P. Extension, Delhi-110092 Phone: 9958600397, 7503468992, <br>Email: info@architectshive.com Website: www.architectshive.com</p>
        </div>
        <div class='page-break-before'>
            <div class='logo-section'>
                <img src='arch (1).png' alt='Company Logo'>
            </div>
            <h4>Payment Schedule</h4>
            $paymentScheduleTable
        </div>
        
        <div style='margin-top: 30px; font-size: 15px;'>
            <p>We ensure for the best services for your project.</p>
            
            <p style='margin-top: 20px;'>
                Regards,<br>
                For M/S ArchitectsHive
            </p>
            <br>
            <br>
            <p style='margin-top: 30px;'>
                Gunjan Anand Sehgal<br>
                <span style='font-style: italic;'>Sr. Manager (Client Relations)</span>
            </p>
        </div>
    </div>";

    // Add logo at the bottom of each page
    $selectedServices .= "
    <style>
        .footer-logo {
            text-align: right;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .footer-logo img {
            max-width: 200px;
            height: auto;
        }
        @media print {
            .footer-logo {
                position: running(footer-logo);
            }
            @page {
                @bottom-right {
                    content: element(footer-logo);
                }
            }
            .footer {
                bottom: 0;
                width: 100%;
                text-align: right;
                padding: 10px;
                font-size: 12px;
            }
        }
    </style>
    <div class='footer-logo'>
        <img src='arch (1).png' alt='Company Logo'>
    </div>";

    // Include the footer HTML in the main template
    $agreementTemplate .= "
        <br>
        <br>
        <div class='footer' style='text-align: left; padding-left: 30px;'>
            <p> Page No. | 3 </p>
        </div>
        <hr style='border: none; height: 4px; background-color: blue; -webkit-print-color-adjust: exact; print-color-adjust: exact;'>
        <div class='footer' style='text-align: left; padding-left: 360px; '>
            <p>F-52, First Floor, Near Gurudwara, Madhu Vihar, <br>I.P. Extension, Delhi-110092 Phone: 9958600397, 7503468992, <br>Email: info@architectshive.com Website: www.architectshive.com</p>
        </div>";

    // Output the generated agreement
    echo "<!DOCTYPE html>
          <html>
          <head>
            <style>
                .agreement-container {
                    max-width: 900px;
                    margin: 0 auto;
                    padding: 40px;
                    font-family: Arial, sans-serif;
                }

                /* Logo Section */
                .logo-section {
                    text-align: right;
                    margin-bottom: 30px;
                }

                .logo-section img {
                    max-width: 200px;
                    height: auto;
                }

                /* Client Info Section */
                .client-info-section {
                    margin-top: 20px;
                }

                .info-row {
                    display: flex;
                    margin-bottom: 4px;
                    align-items: flex-start;
                }

                .info-label {
                    flex: 0 0 140px;
                    font-weight: normal;
                    color: #000;
                    padding-right: 10px;
                }

                .info-value {
                    flex: 1;
                    color: #000;
                    white-space: normal;
                }

                /* Adjust spacing for better readability */
                .client-info-section {
                    line-height: 1.3;
                    font-size: 14px;
                }

                .agreement-section {
                    margin: 30px 0;
                }

                .section-title {
                    font-weight: bold;
                    margin-bottom: 20px;
                }

                .content-list {
                    padding-left: 31px;
                }

                .list-item {
                    display: flex;
                    margin-bottom: 6px;
                }

                .number {
                    font-weight: 600;
                    font-size: 15px;
                    margin-right: 12px;
                    width: 30px;
                    flex-shrink: 0;
                }

                .text {
                    flex: 1;
                    font-size: 15px;
                }

                .sub-list {
                    padding-left: 45px;
                    font-weight: 600;
                }

                .sub-item {
                    display: flex;
                    margin-bottom: 6px;
                }

                .letter {
                    width: 25px;
                    flex-shrink: 0;
                }

                strong {
                    font-weight: 600;
                }

                /* A4 Page Setup */
                .a4-container {
                    background: #f0f0f0;
                    padding: 20px;
                    min-height: 100vh;
                }

                .page {
                    background: white;
                    width: 210mm;
                    min-height: 297mm;
                    padding: 20mm;
                    margin: 0 auto;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }

                .agreement-content {
                    font-size: 12pt;
                    line-height: 1.5;
                }

                /* Print Specific Styles */
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                    }

                    .a4-container {
                        background: none;
                        padding: 0;
                    }

                    .page {
                        width: 210mm;
                        height: 297mm;
                        padding: 20mm;
                        margin: 0;
                        box-shadow: none;
                        page-break-after: always;
                    }

                    /* Ensure proper page breaks */
                    .section {
                        page-break-inside: avoid;
                    }

                    /* Hide unnecessary elements when printing */
                    @page {
                        size: A4;
                        margin: 0;
                    }
                }
            </style>
          </head>
          <body>
            <div class='a4-container'>
                <div class='page'>
                    <div class='agreement-content'>
                        $agreementTemplate
                    </div>
                </div>
            </div>
          </body>
          </html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agreement Generator</title>
    <style>
        @page {
            size: A4;
            margin: 2.54cm; /* Standard 1-inch margin */
        }

        body {
            width: 21cm;    /* A4 width */
            min-height: 29.7cm; /* A4 height */
            margin: 0 auto;
            padding: 2.54cm;
            background: white;
            font-size: 12pt;
            line-height: 1.5;
        }

        /* Print-specific styles */
        @media print {
            body {
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .page-break {
                page-break-before: always;
            }

            /* Ensure headers stay at top of new pages */
            h4 {
                page-break-after: avoid;
            }

            /* Keep list items together where possible */
            .list-item {
                page-break-inside: avoid;
            }

            /* Keep sub-lists with their parent items */
            .sub-list {
                page-break-inside: avoid;
            }
        }

        /* Existing styles remain the same */
        .agreement-section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 15px;
        }

        .content-list {
            padding-left: 40px;
        }

        .list-item, .sub-item {
            margin-bottom: 8px;
        }

        /* Add these styles to your existing CSS */
        .payment-stage {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }

        .stage-main {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .substages-container {
            margin-left: 30px;
            border-left: 2px solid #ddd;
            padding-left: 15px;
        }

        .substage {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }

        .btn-add-substage {
            background: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn-remove-substage {
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
        }

        .substage-warning {
            margin-top: 5px;
            font-size: 0.9em;
        }

        .substage input[type="text"] {
            flex: 2;
        }

        .substage input[type="number"] {
            flex: 1;
            max-width: 80px;
        }

        .substage-amount {
            min-width: 100px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="arch (1).png" alt="Company Logo">
    </div>
    <h2>Generate Agreement</h2>
    <form method="post">
        <div class="form-group">
            <label for="date">Date:</label>
            <input type="date" id="date" name="date" required>
        </div>

        <div class="form-group">
            <label for="clientName">Client Name:</label>
            <input type="text" id="clientName" name="clientName" required>
        </div>

        <div class="form-group">
            <label for="fatherName">Father's Name:</label>
            <input type="text" id="fatherName" name="fatherName" required>
        </div>

        <div class="form-group">
            <label for="permanentAddress">Permanent Address:</label>
            <textarea id="permanentAddress" name="permanentAddress" required></textarea>
        </div>

        <div class="form-group">
            <label for="siteAddress">Site Address:</label>
            <textarea id="siteAddress" name="siteAddress" required></textarea>
        </div>

        <div class="form-group">
            <label for="mobile">Mobile Number:</label>
            <input type="tel" id="mobile" name="mobile" required>
        </div>

        <div class="form-group">
            <label for="email">Email ID:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="subject">Subject:</label>
            <select id="subject" name="subject" onchange="toggleCustomSubject()" required>
                <option value="">Select Subject</option>
                <option value="Consultancy Fees Quotation for Architecture Project">Consultancy Fees Quotation for Architecture Project</option>
                <option value="Consultancy Fees Quotation for Interior Project">Consultancy Fees Quotation for Interior Project</option>
                <option value="Consultancy Fees Quotation for Architecture & Interior Project">Consultancy Fees Quotation for Architecture & Interior Project</option>
                <option value="custom">Other (Custom)</option>
            </select>
            <input type="text" id="customSubject" name="customSubject" 
                   style="display: none; margin-top: 10px;" 
                   placeholder="Enter custom subject">
        </div>

        <div class="form-group">
            <label for="projectType">Project Type:</label>
            <select id="projectType" name="projectType" onchange="toggleProjectFields()" required>
                <option value="">Select Project Type</option>
                <option value="architecture">Planning and Architecture Designing</option>
                <option value="interior">Planning and Interior Designing</option>
                <option value="custom">Custom Project Description</option>
            </select>

            <div id="projectFields" style="display: none; margin-top: 10px;">
                <div class="project-input-group">
                    <input type="number" id="floorCount" name="floorCount" min="1" placeholder="Number of Floors" style="width: 150px;">
                    <span>floors for building at</span>
                    <span class="site-address-placeholder"></span>
                    <span>having area</span>
                    <input type="number" id="areaValue" name="areaValue" min="1" placeholder="Area" style="width: 150px;">
                    <select id="areaUnit" name="areaUnit">
                        <option value="Sq.Ft.">Sq.Ft.</option>
                        <option value="Sq.Mtr.">Sq.Mtr.</option>
                        <option value="Sq.Yd.">Sq.Yd.</option>
                    </select>
                </div>
            </div>

            <div id="customProjectField" style="display: none; margin-top: 10px;">
                <input type="text" id="project" name="project" placeholder="Enter custom project description">
            </div>
        </div>

        <div class="form-group">
            <label for="scopeOfWork"><strong>Scope of Work:</strong></label>
            <div class="scope-options">
                <div class="scope-item">
                    <input type="checkbox" id="designOption" name="scope_options[]" value="design">
                    <label for="designOption">Design Options:</label>
                    <input type="number" id="designCount" name="design_count" min="1" value="2" class="number-input bold-number"> <span class="number-label">Nos. Concept Drawings</span>
                </div>

                <div class="scope-item">
                    <input type="checkbox" id="siteVisits" name="scope_options[]" value="visits">
                    <label for="siteVisits">Site Visits:</label>
                    <input type="number" id="visitsCount" name="visits_count" min="1" value="3" class="number-input bold-number"> <span class="number-label">Nos.</span>
                </div>

                <div class="scope-item">
                    <input type="checkbox" id="conceptPPT" name="scope_options[]" value="ppt">
                    <label>Concept PPT For Reference of Interior & Exterior of the Building</label>
                </div>

                <div class="scope-item">
                    <input type="checkbox" id="accurateMeasurements" name="scope_options[]" value="measurements">
                    <label>As per Accurate Measurements</label>
                </div>

                <div class="scope-item">
                    <input type="checkbox" id="architectsOnly" name="scope_options[]" value="architects">
                    <label>Design By Architects Only</label>
                </div>

                <div class="scope-item">
                    <input type="checkbox" id="packageDetails" name="scope_options[]" value="package">
                    <label>Following shall be provided in Package:</label>
                    <div class="package-options indent">
                        <div>
                            <input type="checkbox" id="architectural" name="drawings[]" value="Architectural Drawings">
                            <label for="architectural">Architectural Drawings</label>
                        </div>
                        <div>
                            <input type="checkbox" id="structural" name="drawings[]" value="Structural Drawings">
                            <label for="structural">Structural Drawings</label>
                        </div>
                        <div>
                            <input type="checkbox" id="electrical" name="drawings[]" value="Electrical Drawings">
                            <label for="electrical">Electrical Drawings</label>
                        </div>
                        <div>
                            <input type="checkbox" id="plumbing" name="drawings[]" value="Plumbing Drawings">
                            <label for="plumbing">Plumbing Drawings</label>
                        </div>
                        <div>
                            <input type="checkbox" id="waterSupply" name="drawings[]" value="Water Supply Drawings">
                            <label for="waterSupply">Water Supply Drawings</label>
                        </div>
                        <div>
                            <input type="checkbox" id="staircase" name="drawings[]" value="Staircase Details">
                            <label for="staircase">Staircase Details</label>
                        </div>
                        <div>
                            <input type="checkbox" id="doorWindow" name="drawings[]" value="Door Window Details">
                            <label for="doorWindow">Door Window Details</label>
                        </div>
                        <div>
                            <input type="checkbox" id="finishing" name="drawings[]" value="Finishing Schedule">
                            <label for="finishing">Finishing Schedule</label>
                        </div>
                    </div>
                </div>
            </div>
            <textarea id="scopeOfWork" name="scopeOfWork" readonly class="scope-textarea"></textarea>
        </div>

        <div class="form-group">
            <label for="squareFeet"><strong>Plot/Building Area (Square Feet):</strong></label>
            <div class="area-input">
                <input type="number" 
                       id="squareFeet" 
                       name="squareFeet" 
                       required 
                       min="100" 
                       step="1"
                       placeholder="Enter area in square feet">
                <span class="unit">sq.ft</span>
            </div>
            <div id="areaBasedCharges" class="area-charges"></div>
        </div>

        <div class="form-group">
            <label><strong>Services by M/S ArchitectsHive:</strong></label>
            <div class="services-checklist">
                <div class="checkbox-group">
                    <input type="checkbox" id="service1" name="services[]" value="Overall Architectural changes shall be in the same in Scope, if Any.">
                    <label for="service1">Overall Architectural changes shall be in the same in Scope, if Any.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service2" name="services[]" value="project_area_config" data-dynamic="true">
                    <label for="service2">[Project Type] [Area] having [Room Configuration], as per client requirement.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service3" name="services[]" value="site_visits" data-dynamic="true">
                    <label for="service3">[X] Nos. Site Visits shall be provided. For Extra Site Visits please visit on www.architectshive.com/bookappointments and book site visits with Architect / Structure / Designer / Interior Designer.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service4" name="services[]" value="Drawings shall be provided as per the Payment Plan mention below.">
                    <label for="service4">Drawings shall be provided as per the Payment Plan mention below.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service5" name="services[]" value="Only PDF (Soft Copy) shall be shared via Mail or Whatsapp to reduce the usage of paper and to save enviroment.">
                    <label for="service5">Only PDF (Soft Copy) shall be shared via Mail or Whatsapp to reduce the usage of paper and to save enviroment.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service6" name="services[]" value="Drawings shall be provided in Feet Inches as per the accurate measurements oversite.">
                    <label for="service6">Drawings shall be provided in Feet Inches as per the accurate measurements oversite.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service7" name="services[]" value="Material Suggestion and budget specific suggestions to execute the design as per the clients's requirement.">
                    <label for="service7">Material Suggestion and budget specific suggestions to execute the design as per the clients's requirement.</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="service8" name="services[]" value="Audio and Video call assistance throughout the duration of the project.">
                    <label for="service8">Audio and Video call assistance throughout the duration of the project.</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="consultancyFees">Consultancy Fees:</label>
            <input type="number" id="consultancyFees" name="consultancyFees" required>
        </div>

        <div class="form-group">
            <label for="discountPercentage">Discount Percentage:</label>
            <input type="number" id="discountPercentage" name="discountPercentage" min="0" max="100" step="0.01" placeholder="Enter discount percentage">
        </div>

        <div class="form-group">
            <label>Final Amount:</label>
            <p id="finalAmount" style="font-weight: bold;"></p>
        </div>

        <div class="form-group">
            <label><strong>Room Configuration:</strong></label>
            
            <div class="room-section">
                <h4>Bedrooms</h4>
                <div class="checkbox-group">
                    <input type="checkbox" id="masterBedroom" name="bedrooms[]" value="Master Bedroom">
                    <label for="masterBedroom">Master Bedroom</label>
                    <input type="number" name="Master_Bedroom_count" min="1" value="1" class="room-count">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="commonBedroom" name="bedrooms[]" value="Common Bedroom">
                    <label for="commonBedroom">Common Bedroom</label>
                    <input type="number" name="Common_Bedroom_count" min="1" value="1" class="room-count">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="kidsBedroom" name="bedrooms[]" value="Kids Bedroom">
                    <label for="kidsBedroom">Kids Bedroom</label>
                    <input type="number" name="Kids_Bedroom_count" min="1" value="1" class="room-count">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="servantsRoom" name="bedrooms[]" value="Servants Room">
                    <label for="servantsRoom">Servants Room</label>
                    <input type="number" name="Servants_Room_count" min="1" value="1" class="room-count">
                </div>
            </div>
            
            <div class="room-section">
                <h4>Common Areas</h4>
                <div class="checkbox-group">
                    <input type="checkbox" id="diningRoom" name="commonAreas[]" value="Dining Room">
                    <label for="diningRoom">Dining Room</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="kitchen" name="commonAreas[]" value="Kitchen">
                    <label for="kitchen">Kitchen</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="livingArea" name="commonAreas[]" value="Living Area">
                    <label for="livingArea">Living Area</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="hall" name="commonAreas[]" value="Hall">
                    <label for="hall">Hall</label>
                </div>
            </div>
            
            <div class="room-section">
                <h4>Extra Rooms</h4>
                <div id="extraRoomsContainer">
                    <!-- Extra rooms will be added here -->
                </div>
                <button type="button" id="addExtraRoom" class="btn-add-room">+ Add Extra Room</button>
            </div>
        </div>

        <div class="form-group">
            <label><strong>Payment Stages:</strong></label>
            <div id="paymentStagesContainer">
                <!-- Default payment stage -->
                <div class="payment-stage">
                    <div class="stage-main">
                        <input type="text" name="stage_name[]" placeholder="Stage Description" required>
                        <input type="number" name="stage_percentage[]" min="0" max="100" step="0.01" placeholder="Percentage" required>
                        <span class="stage-amount"></span>
                        <button type="button" class="btn-add-substage" onclick="addSubStage(this)">+ Sub-stage</button>
                        <button type="button" class="btn-remove-stage" onclick="removePaymentStage(this)">×</button>
                    </div>
                    <div class="substages-container"></div>
                </div>
            </div>
            <div class="payment-stages-footer">
                <button type="button" id="addPaymentStage" class="btn-add-stage">+ Add Payment Stage</button>
                <span id="totalPercentage">Total: 0%</span>
                <span id="percentageWarning" class="warning-text"></span>
            </div>
        </div>

        <input type="submit" value="Generate Agreement">
    </form>

    <script>
        // Keep track of the order in which checkboxes are selected
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        let selectionOrder = [];

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectionOrder.push(this.value);
                } else {
                    selectionOrder = selectionOrder.filter(item => item !== this.value);
                }
                updateServiceOrder();
                updateTotalCharges();
            });
        });

        function updateServiceOrder() {
            const checkedServices = selectionOrder.filter(service => 
                document.querySelector(`input[value="${service}"]`).checked
            );
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const index = checkedServices.indexOf(checkbox.value);
                    checkbox.setAttribute('data-order', index);
                }
            });
        }

        // Amount to words conversion
        function numberToWords(number) {
            const ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
            const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
            const teens = ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
            
            function convertGroup(n) {
                let word = '';
                
                if (n >= 100) {
                    word += ones[Math.floor(n / 100)] + ' hundred ';
                    n %= 100;
                }
                
                if (n >= 20) {
                    word += tens[Math.floor(n / 10)] + ' ';
                    n %= 10;
                } else if (n >= 10) {
                    word += teens[n - 10] + ' ';
                    return word;
                }
                
                if (n > 0) {
                    word += ones[n] + ' ';
                }
                
                return word;
            }
            
            if (number === 0) return 'zero';
            
            let words = '';
            
            if (number >= 10000000) {
                words += convertGroup(Math.floor(number / 10000000)) + 'crore ';
                number %= 10000000;
            }
            
            if (number >= 100000) {
                words += convertGroup(Math.floor(number / 100000)) + 'lakh ';
                number %= 100000;
            }
            
            if (number >= 1000) {
                words += convertGroup(Math.floor(number / 1000)) + 'thousand ';
                number %= 1000;
            }
            
            words += convertGroup(number);
            
            return words.trim();
        }

        document.getElementById('consultancyFees').addEventListener('input', function() {
            const amount = this.value;
            const wordsDiv = document.getElementById('amountInWords');
            if (amount > 0) {
                wordsDiv.textContent = `Rupees ${numberToWords(parseInt(amount))} only`;
            } else {
                wordsDiv.textContent = '';
            }
        });

        document.getElementById('squareFeet').addEventListener('input', function() {
            const area = parseFloat(this.value) || 0;
            updateServiceCharges(area);
        });

        function updateServiceCharges(area) {
            const checkboxes = document.querySelectorAll('.services-checklist input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                const rate = checkbox.dataset.rate;
                if (rate) {
                    const charge = area * parseFloat(rate);
                    const label = checkbox.nextElementSibling;
                    label.innerHTML = `${checkbox.value} (₹${charge.toLocaleString('en-IN')}/-)`
                }
            });
        }

        function updateTotalCharges() {
            const area = parseFloat(document.getElementById('squareFeet').value) || 0;
            let total = 0;
            
            document.querySelectorAll('.services-checklist input[type="checkbox"]:checked').forEach(checkbox => {
                const rate = checkbox.dataset.rate;
                if (rate) {
                    total += area * parseFloat(rate);
                }
            });
            
            document.getElementById('consultancyFees').value = total;
            document.getElementById('consultancyFees').dispatchEvent(new Event('input'));
        }

        // Add extra room functionality
        let extraRoomCount = 0;

        document.getElementById('addExtraRoom').addEventListener('click', function() {
            const container = document.getElementById('extraRoomsContainer');
            extraRoomCount++;
            
            const roomDiv = document.createElement('div');
            roomDiv.className = 'extra-room-entry';
            roomDiv.innerHTML = `
                <input type="text" name="extra_room_name_${extraRoomCount}" 
                       placeholder="Room Name" required>
                <input type="number" name="extra_room_count_${extraRoomCount}" 
                       min="1" value="1" class="room-count">
                <button type="button" class="btn-remove-room" 
                        onclick="removeExtraRoom(this)">×</button>
                <input type="hidden" name="extraRooms[]" value="${extraRoomCount}">
            `;
            
            container.appendChild(roomDiv);
        });

        function removeExtraRoom(button) {
            button.parentElement.remove();
        }

        // Update site visits calculation when square feet changes
        document.getElementById('squareFeet').addEventListener('input', function() {
            const area = parseFloat(this.value) || 0;
            const baseVisits = 3;
            let totalVisits = baseVisits;
            
            if (area > 1000) {
                const additionalArea = area - 1000;
                totalVisits += Math.ceil(additionalArea / 500);
            }
            
            document.getElementById('siteVisitsCount').textContent = totalVisits;
        });

        function toggleCustomSubject() {
            const subjectSelect = document.getElementById('subject');
            const customSubject = document.getElementById('customSubject');
            
            if (subjectSelect.value === 'custom') {
                customSubject.style.display = 'block';
                customSubject.required = true;
            } else {
                customSubject.style.display = 'none';
                customSubject.required = false;
            }
        }

        function toggleProjectFields() {
            const projectType = document.getElementById('projectType').value;
            const projectFields = document.getElementById('projectFields');
            const customProjectField = document.getElementById('customProjectField');
            
            if (projectType === 'architecture' || projectType === 'interior') {
                projectFields.style.display = 'block';
                customProjectField.style.display = 'none';
                
                // Update site address placeholder with current site address value
                const siteAddress = document.getElementById('siteAddress').value;
                document.querySelector('.site-address-placeholder').textContent = 
                    siteAddress ? `(${siteAddress})` : '(mentioned address)';
                
                // Add event listener to site address field to update placeholder
                document.getElementById('siteAddress').addEventListener('input', function() {
                    document.querySelector('.site-address-placeholder').textContent = 
                        this.value ? `(${this.value})` : '(mentioned address)';
                });
            } else if (projectType === 'custom') {
                projectFields.style.display = 'none';
                customProjectField.style.display = 'block';
            } else {
                projectFields.style.display = 'none';
                customProjectField.style.display = 'none';
            }
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const projectType = document.getElementById('projectType').value;
            const projectInput = document.getElementById('project');
            
            if (projectType === 'architecture' || projectType === 'interior') {
                const floorCount = document.getElementById('floorCount').value;
                const areaValue = document.getElementById('areaValue').value;
                const areaUnit = document.getElementById('areaUnit').value;
                const siteAddress = document.getElementById('siteAddress').value;
                const typeText = projectType === 'architecture' ? 
                    'Planning and Architecture Designing' : 
                    'Planning and Interior Designing';
                
                projectInput.value = `${typeText} of ${floorCount} floors for building at ${siteAddress} having area ${areaValue} ${areaUnit}`;
            }
        });

        function updateScopeOfWork() {
            let scope = "";
            let count = 1;

            // Design Options
            if (document.getElementById('designOption').checked) {
                scope += `${count}. Design Options: <strong>${document.getElementById('designCount').value}</strong> Nos. Concept Drawings\r\n\r\n`;
                count++;
            }

            // Site Visits
            if (document.getElementById('siteVisits').checked) {
                scope += `${count}. Site Visits: <strong>${document.getElementById('visitsCount').value}</strong> Nos.\r\n\r\n`;
                count++;
            }

            // Concept PPT
            if (document.getElementById('conceptPPT').checked) {
                scope += `${count}. Concept PPT For Reference of Interior & Exterior of the Building\r\n\r\n`;
                count++;
            }

            // Accurate Measurements
            if (document.getElementById('accurateMeasurements').checked) {
                scope += `${count}. As per Accurate Measurements\r\n\r\n`;
                count++;
            }

            // Architects Only
            if (document.getElementById('architectsOnly').checked) {
                scope += `${count}. Design By Architects Only\r\n\r\n`;
                count++;
            }

            // Package Details
            if (document.getElementById('packageDetails').checked) {
                scope += `${count}. Following shall be provided in Package:\r\n`;
                const drawings = document.querySelectorAll('input[name="drawings[]"]:checked');
                drawings.forEach((drawing, index) => {
                    scope += `   ${String.fromCharCode(97 + index)}) ${drawing.value}\r\n`;
                });
                if (drawings.length > 0) {
                    scope += "\r\n";
                }
                count++;
            }

            // Remove extra newline at the end
            scope = scope.replace(/\r\n\r\n$/, '');
            
            document.getElementById('scopeOfWork').value = scope;
        }

        // Add event listeners to all checkboxes and number inputs
        document.querySelectorAll('.scope-options input').forEach(input => {
            input.addEventListener('change', updateScopeOfWork);
        });

        document.querySelectorAll('.number-input').forEach(input => {
            input.addEventListener('input', updateScopeOfWork);
        });

        function updateDynamicServices() {
            // Update Project Type and Area Configuration
            const projectType = document.getElementById('projectType').value;
            const area = document.getElementById('squareFeet').value;
            const areaUnit = 'sq.ft'; // or get from your area unit selector if available
            
            // Get room configuration
            const roomConfig = getRoomConfiguration(); // You'll need to implement this function
            
            // Get site visits count
            const siteVisits = calculateSiteVisits(area); // You'll need to implement this function
            
            // Update labels
            document.querySelector('label[for="service2"]').textContent = 
                `${projectType} ${area} ${areaUnit} having ${roomConfig}, as per client requirement.`;
            
            document.querySelector('label[for="service3"]').textContent = 
                `${siteVisits} Nos. Site Visits shall be provided. For Extra Site Visits please visit on www.architectshive.com/bookappointments and book site visits with Architect / Structure / Designer / Interior Designer.`;
        }

        // Add event listeners to update dynamic content
        document.getElementById('projectType').addEventListener('change', updateDynamicServices);
        document.getElementById('squareFeet').addEventListener('input', updateDynamicServices);
        // Add more event listeners for room configuration changes

        document.getElementById('discountPercentage').addEventListener('input', function() {
            const percentage = parseFloat(this.value) || 0;
            const consultancyFees = parseFloat(document.getElementById('consultancyFees').value) || 0;
            const discountNote = document.getElementById('discountNote');
            
            if (percentage > 0 && consultancyFees > 0) {
                const discountAmount = (consultancyFees * percentage) / 100;
                const finalAmount = consultancyFees - discountAmount;
                discountNote.textContent = `A discount of ${percentage}% (₹${discountAmount.toFixed(2)}/-) is applied, making the final amount ₹${finalAmount.toFixed(2)}/-`;
            } else {
                discountNote.textContent = '';
            }
        });

        // Update discount calculation when consultancy fees change
        document.getElementById('consultancyFees').addEventListener('input', function() {
            document.getElementById('discountPercentage').dispatchEvent(new Event('input'));
        });

        document.getElementById('consultancyFees').addEventListener('input', updateFinalAmount);
        document.getElementById('discountPercentage').addEventListener('input', updateFinalAmount);

        function updateFinalAmount() {
            const fees = parseFloat(document.getElementById('consultancyFees').value) || 0;
            const discountPercentage = parseFloat(document.getElementById('discountPercentage').value) || 0;
            const discountAmount = (fees * discountPercentage) / 100;
            const finalAmount = fees - discountAmount;

            document.getElementById('finalAmount').textContent = `Final Amount: ₹${finalAmount.toFixed(2)} (${numberToWords(finalAmount)} only)`;
        }

        // Initialize payment stages functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addStageBtn = document.getElementById('addPaymentStage');
            const container = document.getElementById('paymentStagesContainer');
            
            addStageBtn.addEventListener('click', addPaymentStage);
            updateTotalPercentage(); // Initial calculation
        });

        function addPaymentStage() {
            const container = document.getElementById('paymentStagesContainer');
            const newStage = document.createElement('div');
            newStage.className = 'payment-stage';
            newStage.innerHTML = `
                <div class="stage-main">
                    <input type="text" name="stage_name[]" placeholder="Stage Description" required>
                    <input type="number" name="stage_percentage[]" min="0" max="100" step="0.01" placeholder="Percentage" required>
                    <span class="stage-amount"></span>
                    <button type="button" class="btn-add-substage" onclick="addSubStage(this)">+ Sub-stage</button>
                    <button type="button" class="btn-remove-stage" onclick="removePaymentStage(this)">×</button>
                </div>
                <div class="substages-container"></div>
            `;
            container.appendChild(newStage);
            
            // Add event listener to new percentage input
            const percentageInput = newStage.querySelector('input[name="stage_percentage[]"]');
            percentageInput.addEventListener('input', updateTotalPercentage);
        }

        function addSubStage(button) {
            const stageDiv = button.closest('.payment-stage');
            const subStagesContainer = stageDiv.querySelector('.substages-container');
            const newSubStage = document.createElement('div');
            newSubStage.className = 'substage';
            newSubStage.innerHTML = `
                <input type="text" name="substage_name[]" placeholder="Sub-stage Description" required>
                <input type="number" name="substage_percentage[]" min="0" max="100" step="0.01" placeholder="Percentage of Stage" required>
                <span class="substage-amount"></span>
                <button type="button" class="btn-remove-substage" onclick="removeSubStage(this)">×</button>
            `;
            subStagesContainer.appendChild(newSubStage);
            
            // Add event listener to new sub-stage percentage input
            const percentageInput = newSubStage.querySelector('input[name="substage_percentage[]"]');
            percentageInput.addEventListener('input', () => updateSubStagePercentages(stageDiv));
        }

        function removeSubStage(button) {
            const subStage = button.closest('.substage');
            const stageDiv = subStage.closest('.payment-stage');
            subStage.remove();
            updateSubStagePercentages(stageDiv);
        }

        function updateSubStagePercentages(stageDiv) {
            const stagePercentage = parseFloat(stageDiv.querySelector('input[name="stage_percentage[]"]').value) || 0;
            const stageAmount = (stagePercentage * parseFloat(document.getElementById('consultancyFees').value) || 0) / 100;
            const subStages = stageDiv.querySelectorAll('.substage');
            
            let totalSubPercentage = 0;
            subStages.forEach(subStage => {
                const subPercentage = parseFloat(subStage.querySelector('input[name="substage_percentage[]"]').value) || 0;
                totalSubPercentage += subPercentage;
                
                // Calculate and display sub-stage amount
                const subAmount = (stageAmount * subPercentage) / 100;
                subStage.querySelector('.substage-amount').textContent = `₹${subAmount.toFixed(2)}`;
            });
            
            // Add warning if sub-stage percentages don't add up to 100%
            const warningElement = stageDiv.querySelector('.substage-warning') || 
                (() => {
                    const warning = document.createElement('div');
                    warning.className = 'substage-warning';
                    stageDiv.appendChild(warning);
                    return warning;
                })();
            
            if (totalSubPercentage !== 100 && subStages.length > 0) {
                warningElement.textContent = `Sub-stages total: ${totalSubPercentage}% (should be 100%)`;
                warningElement.style.color = '#ff4444';
            } else {
                warningElement.textContent = '';
            }
        }

        function removePaymentStage(button) {
            button.parentElement.remove();
            updateTotalPercentage();
        }

        function updateTotalPercentage() {
            const percentageInputs = document.getElementsByName('stage_percentage[]');
            const totalPercentageSpan = document.getElementById('totalPercentage');
            const warningSpan = document.getElementById('percentageWarning');
            const consultancyFees = parseFloat(document.getElementById('consultancyFees').value) || 0;
            
            let total = 0;
            percentageInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
                
                // Update individual stage amount
                const stageAmount = (parseFloat(input.value) || 0) * consultancyFees / 100;
                const amountSpan = input.nextElementSibling;
                amountSpan.textContent = `₹${stageAmount.toFixed(2)}`;
            });

            totalPercentageSpan.textContent = `Total: ${total.toFixed(2)}%`;
            
            if (total > 100) {
                warningSpan.textContent = 'Total percentage exceeds 100%';
                totalPercentageSpan.style.color = '#ff4444';
            } else if (total < 100) {
                warningSpan.textContent = 'Total percentage is less than 100%';
                totalPercentageSpan.style.color = '#ff4444';
            } else {
                warningSpan.textContent = '';
                totalPercentageSpan.style.color = '#4CAF50';
            }
        }

        // Add event listener to consultancy fees input to update stage amounts
        document.getElementById('consultancyFees').addEventListener('input', updateTotalPercentage);
    </script>

    <style>
        .project-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .project-input-group input,
        .project-input-group select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .site-address-placeholder {
            color: #666;
            font-style: italic;
        }
        .scope-options {
            margin-bottom: 15px;
        }
        .scope-item {
            margin: 10px 0;
        }
        .number-input {
            width: 60px;
            margin: 0 5px;
        }
        .package-options {
            margin-left: 24px;
            margin-top: 8px;
        }
        .package-options > div {
            margin-bottom: 6px;
        }
        #scopeOfWork {
            white-space: pre-line;
            min-height: 200px;
            line-height: 1.8;
            padding: 15px;
        }

        .scope-textarea {
            white-space: pre-wrap;
            min-height: 300px;
            width: 100%;
            line-height: 2.5;
            padding: 20px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
            resize: vertical;
        }

        /* Optional: Style the points differently */
        .scope-textarea {
            counter-reset: item;
        }

        .discount-input {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .discount-input label {
            font-weight: normal;
        }
        
        .discount-input input {
            width: 100px;
        }
        
        .percentage-symbol {
            color: #666;
        }
        
        .discount-note {
            margin-top: 5px;
            color: #666;
            font-style: italic;
        }

        .payment-table,
        .payment-table th,
        .payment-table td,
        .payment-table li {
            font-size: 15px;
        }

        .fees-section {
            margin: 20px 0;
        }

        .fees-section h3, 
        .fees-section h4 {
            margin-bottom: 15px;
        }

        .fees-list {
            margin-bottom: 20px;
        }

        .fees-list ol {
            margin: 0;
            padding-left: 30px;
        }

        .fees-list li {
            margin-bottom: 12px;
        }

        .fees-list ul {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .fees-list ul li {
            padding-left: 0;
            line-height: 1.6;
        }

        .scope-section {
            margin: 30px 0;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .scope-content {
            padding-left: 30px;
        }

        .scope-item {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .item-number {
            min-width: 25px;
            margin-right: 10px;
        }

        .item-text {
            flex: 1;
        }

        .package-list {
            margin-top: 10px;
            margin-left: 33px;
        }

        .package-item {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
        }

        .package-item span {
            min-width: 20px;
            margin-right: 8px;
        }

        strong {
            font-weight: 600;
        }

        .agreement-section {
            margin: 30px 0;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .content-list {
            padding-left: 40px;
        }

        .list-item {
            display: flex;
            margin-bottom: 12px;
        }

        .number {
            width: 30px;
            flex-shrink: 0;
        }

        .text {
            flex: 1;
        }

        .sub-list {
            padding-left: 30px;
        }

        .sub-item {
            display: flex;
            margin-bottom: 8px;
        }

        .letter {
            width: 25px;
            flex-shrink: 0;
        }

        strong {
            font-weight: 600;
        }

        .payment-stage {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .payment-stage input[type="text"] {
            flex: 2;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .payment-stage input[type="number"] {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-width: 100px;
        }

        .stage-amount {
            min-width: 120px;
            color: #666;
        }

        .btn-remove-stage {
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add-stage {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .payment-stages-footer {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 10px;
        }

        .warning-text {
            color: #ff4444;
            font-size: 0.9em;
        }

        #totalPercentage {
            font-weight: bold;
        }

        .stage-main {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .substage {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }

        .substage-amount {
            min-width: 120px;
            color: #666;
        }

        .btn-add-substage {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-remove-substage {
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .substage-warning {
            margin-top: 5px;
            font-size: 0.9em;
        }

        .substages-container {
            margin-left: 30px;
            border-left: 2px solid #ddd;
            padding-left: 15px;
        }
    </style>
</head>
<body>
