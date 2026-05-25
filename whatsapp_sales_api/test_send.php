<?php
// whatsapp_sales_api/test_send.php

require_once __DIR__ . '/helper.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'text';
    $to = $_POST['to'];

    // Basic validation
    if (!empty($to)) {
        if ($action === 'text') {
            $msg = $_POST['message'];
            if (!empty($msg)) {
                $result = sendSalesWhatsAppText($to, $msg);
                if ($result['success']) {
                    $status = '<div class="success">Text Message Sent! ID: ' . $result['response']['messages'][0]['id'] . '</div>';
                } else {
                    $status = '<div class="error">Error: ' . print_r($result['error'], true) . '</div>';
                }
            }
        } elseif ($action === 'template') {
            $templateName = $_POST['template_name'];
            if (!empty($templateName)) {
                // For testing, we use empty components or simple ones if needed
                // Currently only standard templates without variables handled here for simplicity
                // unless we enhance the form.
                // But for "share_pricing_pdf", it needs complex components (header document).
                // Let's just try sending a simple "hello_world" or the specific one with dummy data if requested.

                $components = [];
                // If specific template logic is needed for test, add here.

                $result = sendSalesWhatsAppMessage($to, $templateName, 'en_US', $components);
                if ($result['success']) {
                    $status = '<div class="success">Template Message Sent! ID: ' . $result['response']['messages'][0]['id'] . '</div>';
                } else {
                    $status = '<div class="error">Error: ' . print_r($result['error'], true) . '</div>';
                }
            }
        } elseif ($action === 'document') {
            // Handle file upload
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['pdf_file']['tmp_name'];
                $fileName = $_FILES['pdf_file']['name'];

                // Upload Media
                $mediaId = uploadSalesMedia($tmpName, 'application/pdf');

                if ($mediaId) {
                    $caption = $_POST['caption'] ?? 'Here is your document';
                    $result = sendSalesWhatsAppDocument($to, $mediaId, $fileName, $caption);

                    if ($result['success']) {
                        $status = '<div class="success">Document Sent! ID: ' . $result['response']['messages'][0]['id'] . '</div>';
                    } else {
                        $status = '<div class="error">Error Sending Document: ' . print_r($result['error'], true) . '</div>';
                    }
                } else {
                    $status = '<div class="error">Error Uploading Media to WhatsApp</div>';
                }
            } else {
                $status = '<div class="error">Please select a valid PDF file.</div>';
            }
        }
    } else {
        $status = '<div class="error">Please enter a phone number.</div>';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Test Sales WhatsApp Send</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #f4f6f8;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        input[type="text"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #25D366;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
        }

        button:hover {
            background-color: #128C7E;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            background: #eee;
            color: #333;
            flex: 1;
            text-align: center;
        }

        .tab-btn.active {
            background: #25D366;
            color: white;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }
    </style>
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName + '-form').classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
    </script>
</head>

<body>
    <div class="container">
        <h2>Sales WhatsApp Tester</h2>
        <p><strong>Phone ID:</strong> <?php echo SALES_WHATSAPP_PHONE_NUMBER_ID; ?></p>

        <?php echo $status; ?>

        <div class="tab-buttons">
            <button id="text-tab" class="tab-btn active" onclick="showTab('text')">Text Message</button>
            <button id="template-tab" class="tab-btn" onclick="showTab('template')">Template</button>
            <button id="document-tab" class="tab-btn" onclick="showTab('document')">Document (PDF)</button>
        </div>

        <form method="POST" id="text-form" class="form-section active">
            <input type="hidden" name="action" value="text">
            <label>To Phone Number (e.g., 919999999999):</label>
            <input type="text" name="to" required placeholder="91..." value="91">
            <label>Message:</label>
            <textarea name="message" rows="4" required>Hello from ArchitectsHive Sales!</textarea>
            <button type="submit">Send Text</button>
        </form>

        <form method="POST" id="template-form" class="form-section">
            <input type="hidden" name="action" value="template">
            <label>To Phone Number (e.g., 919999999999):</label>
            <input type="text" name="to" required placeholder="91..." value="91">
            <label>Template Name:</label>
            <input type="text" name="template_name" value="hello_world" required placeholder="e.g. hello_world">
            <button type="submit">Send Template</button>
        </form>

        <form method="POST" id="document-form" class="form-section" enctype="multipart/form-data">
            <input type="hidden" name="action" value="document">
            <label>To Phone Number (e.g., 919999999999):</label>
            <input type="text" name="to" required placeholder="91..." value="91">
            <label>Select PDF File:</label>
            <input type="file" name="pdf_file" accept="application/pdf" required>
            <label>Caption:</label>
            <input type="text" name="caption" value="Sent via Sales API Tester">
            <button type="submit">Send Document</button>
        </form>
    </div>
</body>

</html>