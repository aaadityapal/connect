<?php
// whatsapp_sales_api/wishes/send_shivratri_wish.php

require_once __DIR__ . '/../helper.php';

$message = '';
$status = '';
date_default_timezone_set('Asia/Kolkata');
$defaultImageLink = 'https://raw.githubusercontent.com/aaadityapal/webp/refs/heads/main/WhatsApp%20Image%202026-02-14%20at%2010.42.33%20AM.jpeg';
$bulkReport = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'single';
    $imageLink = trim($_POST['wish_image_link'] ?? '');
    $scheduledTimeInput = $_POST['scheduled_time'] ?? '';

    // Validate Scheduled Time if provided
    $scheduledTime = null;
    if (!empty($scheduledTimeInput)) {
        // Convert datetime-local to MySQL DATETIME format
        $timestamp = strtotime($scheduledTimeInput);
        if ($timestamp !== false) {
            $scheduledTime = date('Y-m-d H:i:s', $timestamp);
        } else {
            $status = '<div class="error">Invalid Scheduled Time Format.</div>';
            $imageLink = ''; // force skip processing if invalid time
        }
    }

    if (!empty($imageLink)) {
        // Common template details
        $templateName = 'mahashivratri_2026_wishing';
        $languageCode = 'en_US';

        if ($mode === 'single') {
            $to = $_POST['to'] ?? '';
            $name = $_POST['name'] ?? '';

            if (!empty($to) && !empty($name)) {
                if ($scheduledTime) {
                    // Start Scheduling
                    $result = scheduleWish($to, $name, $scheduledTime, $templateName, $imageLink);
                    if ($result) {
                        $status = '<div class="success">Shivratri Wish Scheduled for ' . htmlspecialchars($scheduledTime) . '!</div>';
                    } else {
                        $status = '<div class="error">Failed to Schedule Wish. Database Error.</div>';
                    }
                } else {
                    // Send Immediately
                    $result = sendWish($to, $name, $imageLink, $templateName, $languageCode);
                    if ($result['success']) {
                        $status = '<div class="success">Shivratri Wish Sent Successfully!<br>Message ID: ' . $result['response']['messages'][0]['id'] . '</div>';
                    } else {
                        $status = '<div class="error">Error Sending Message: <pre>' . print_r($result['error'], true) . '</pre></div>';
                    }
                }
            } else {
                $status = '<div class="error">Please provide Name and WhatsApp Number.</div>';
            }

        } elseif ($mode === 'bulk') {
            $bulkData = $_POST['bulk_data'] ?? '';
            $lines = explode("\n", $bulkData);

            $successCount = 0;
            $failCount = 0;
            $reportRows = '';

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                // Expected format: Name, Number
                $parts = explode(',', $line);
                if (count($parts) >= 2) {
                    $bName = trim($parts[0]);
                    $bNumber = trim($parts[1]);

                    if (!empty($bName) && !empty($bNumber)) {
                        if ($scheduledTime) {
                            $res = scheduleWish($bNumber, $bName, $scheduledTime, $templateName, $imageLink);
                            if ($res) {
                                $successCount++;
                                $reportRows .= "<tr><td>{$bName}</td><td>{$bNumber}</td><td class='text-primary'>Scheduled</td></tr>";
                            } else {
                                $failCount++;
                                $reportRows .= "<tr><td>{$bName}</td><td>{$bNumber}</td><td class='text-danger'>Schedule Failed</td></tr>";
                            }
                        } else {
                            $result = sendWish($bNumber, $bName, $imageLink, $templateName, $languageCode);

                            if ($result['success']) {
                                $successCount++;
                                $reportRows .= "<tr><td>{$bName}</td><td>{$bNumber}</td><td class='text-success'>Sent</td></tr>";
                            } else {
                                $failCount++;
                                $err = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Error';
                                $reportRows .= "<tr><td>{$bName}</td><td>{$bNumber}</td><td class='text-danger'>Failed: {$err}</td></tr>";
                            }
                            // Small delay to be safe
                            usleep(200000); // 0.2 seconds
                        }
                    }
                } else {
                    $reportRows .= "<tr><td>" . htmlspecialchars($line) . "</td><td>-</td><td class='text-warning'>Invalid Format</td></tr>";
                }
            }

            $actionLabel = $scheduledTime ? "Scheduling" : "Sending";
            $status = "<div class='info'>Bulk $actionLabel Complete. Success: <strong>$successCount</strong>, Failed: <strong>$failCount</strong></div>";
            $bulkReport = "<table class='report-table'><thead><tr><th>Name</th><th>Number</th><th>Status</th></tr></thead><tbody>$reportRows</tbody></table>";
        }
    }
}

function sendWish($to, $name, $imageLink, $templateName, $languageCode)
{
    $components = [
        [
            'type' => 'header',
            'parameters' => [
                [
                    'type' => 'image',
                    'image' => [
                        'link' => $imageLink
                    ]
                ]
            ]
        ],
        [
            'type' => 'body',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $name
                ]
            ]
        ]
    ];

    return sendSalesWhatsAppMessage($to, $templateName, $languageCode, $components);
}

function scheduleWish($to, $name, $scheduledTime, $templateName, $imageLink)
{
    $conn = getDBConnection();
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO scheduled_wishes (whatsapp_number, name, scheduled_time, template_name, image_link, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssss", $to, $name, $scheduledTime, $templateName, $imageLink);

    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Shivratri Wish - Sales WhatsApp</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding-top: 40px;
        }

        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }

        h2 {
            text-align: center;
            color: #128C7E;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 0.9em;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }

        input[type="text"],
        input[type="datetime-local"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 1.2rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        textarea {
            font-family: monospace;
            min-height: 150px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            border-color: #25D366;
            outline: none;
        }

        button.submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #25D366;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button.submit-btn:hover {
            background-color: #128C7E;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
            margin-bottom: 1rem;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
            margin-bottom: 1rem;
        }

        .info {
            background-color: #e7f3fe;
            color: #31708f;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #bce8f1;
            margin-bottom: 1rem;
        }

        .alert-info {
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
            margin-bottom: 15px;
            padding: 10px;
            font-size: 0.9em;
            color: #444;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .tab-btn {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }

        .tab-btn.active {
            color: #128C7E;
            border-bottom: 3px solid #128C7E;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .report-table th {
            background-color: #f2f2f2;
        }

        .text-success {
            color: #155724;
            font-weight: bold;
        }

        .text-danger {
            color: #721c24;
            font-weight: bold;
        }

        .text-warning {
            color: #856404;
        }

        .text-primary {
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Send Shivratri Wish 2026</h2>
        <div class="subtitle">Template: <strong>mahashivratri_2026_wishing</strong></div>
        <div class="status-message"><?php echo $status; ?></div>
        <div class="alert-info"><strong>Note:</strong> Required Image Link. Ensure the link is public and direct (ending
            in .jpg/.png).</div>

        <form method="POST" id="mainForm">
            <label for="wish_image_link">Image Link (Header):</label>
            <input type="text" id="wish_image_link" name="wish_image_link" placeholder="https://example.com/image.jpg"
                required
                value="<?php echo isset($_POST['wish_image_link']) ? htmlspecialchars($_POST['wish_image_link']) : $defaultImageLink; ?>"
                oninput="previewImage()">
            <div id="image_preview_container" style="display:none; margin-bottom: 1.2rem; text-align: center;"><img
                    id="image_preview" src="" alt="Image Preview"
                    style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #ddd;"></div>

            <label for="scheduled_time">Schedule Time (Optional - Leave blank to send immediately):</label>
            <input type="datetime-local" id="scheduled_time" name="scheduled_time"
                value="<?php echo isset($_POST['scheduled_time']) ? htmlspecialchars($_POST['scheduled_time']) : ''; ?>">

            <div class="tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('single')">Single Contact</button>
                <button type="button" class="tab-btn" onclick="switchTab('bulk')">Bulk Send</button>
            </div>
            <input type="hidden" name="mode" id="mode_input" value="<?php echo htmlspecialchars($mode ?? 'single'); ?>">

            <div id="single-tab" class="tab-content active">
                <label for="name">Client Name ({{1}}):</label>
                <input type="text" id="name" name="name" placeholder="e.g. John Doe"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                <label for="to">WhatsApp Number:</label>
                <input type="text" id="to" name="to" placeholder="e.g. 919876543210"
                    value="<?php echo isset($_POST['to']) ? htmlspecialchars($_POST['to']) : '91'; ?>">
            </div>

            <div id="bulk-tab" class="tab-content">
                <label for="bulk_data">Paste Contacts (Name, Number):</label>
                <div style="font-size: 0.8rem; color: #666; margin-bottom: 5px;">Format: One per line. Comma separated.
                    Example: <br><code>Aditya, 919876543210</code><br><code>Rahul, 918765432109</code></div>
                <textarea id="bulk_data" name="bulk_data"
                    placeholder="Name, Number"><?php echo isset($_POST['bulk_data']) ? htmlspecialchars($_POST['bulk_data']) : ''; ?></textarea>
            </div>

            <button type="submit" class="submit-btn" style="margin-top: 10px;">Send / Schedule Wish</button>
        </form>

        <?php if (!empty($bulkReport)): ?>
            <div style="margin-top: 20px;">
                <h4>Report:</h4><?php echo $bulkReport; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function previewImage() {
            const url = document.getElementById('wish_image_link').value;
            const previewContainer = document.getElementById('image_preview_container');
            const previewImage = document.getElementById('image_preview');
            if (url) {
                previewImage.src = url;
                previewContainer.style.display = 'block';
                previewImage.onerror = function () { previewContainer.style.display = 'none'; };
            } else {
                previewContainer.style.display = 'none';
            }
        }
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            if (tab === 'single') {
                document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
                document.getElementById('single-tab').classList.add('active');
            } else {
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
                document.getElementById('bulk-tab').classList.add('active');
            }
            document.getElementById('mode_input').value = tab;
            if (tab === 'single') {
                document.getElementById('name').setAttribute('required', 'required');
                document.getElementById('to').setAttribute('required', 'required');
                document.getElementById('bulk_data').removeAttribute('required');
            } else {
                document.getElementById('name').removeAttribute('required');
                document.getElementById('to').removeAttribute('required');
                document.getElementById('bulk_data').setAttribute('required', 'required');
            }
        }
        window.onload = function () {
            previewImage();
            const currentMode = "<?php echo htmlspecialchars($mode ?? 'single'); ?>";
            switchTab(currentMode);
        };
    </script>
</body>

</html>