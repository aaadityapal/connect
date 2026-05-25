<?php
// whatsapp_sales_api/pricing/send_pricing_followup.php

require_once __DIR__ . '/../helper.php';

$message = '';
$status = '';
$conn = getDBConnection();

// Fetch leads for the dropdown
$leads = [];
$sql = "SELECT id, name, whatsapp_number, project_type, location, plan_name, total_price 
        FROM pricing_share_leads 
        ORDER BY id DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['to'] ?? '';
    $name = $_POST['name'] ?? '';
    $planType = $_POST['plan_type'] ?? '';
    $price = $_POST['price'] ?? '';

    // Basic validation
    if (!empty($to) && !empty($name) && !empty($planType) && !empty($price)) {

        // Prepare template components
        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $name
                    ],
                    [
                        'type' => 'text',
                        'text' => $planType
                    ],
                    [
                        'type' => 'text',
                        'text' => $price
                    ]
                ]
            ]
        ];

        // Send template message
        $templateName = 'client_pricing_followup';
        $result = sendSalesWhatsAppMessage($to, $templateName, 'en_US', $components);

        if ($result['success']) {
            $status = '<div class="success">Pricing Follow-up Message Sent Successfully! <br>Message ID: ' . $result['response']['messages'][0]['id'] . '</div>';
        } else {
            $status = '<div class="error">Error Sending Message: <pre>' . print_r($result['error'], true) . '</pre></div>';
        }

    } else {
        $status = '<div class="error">Please fill in all fields (Name, Number, Plan Type, Price).</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Pricing Follow-up - Sales WhatsApp</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        h2 {
            text-align: center;
            color: #128C7E;
            /* WhatsApp Green */
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 1.2rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        input:focus,
        select:focus {
            border-color: #25D366;
            outline: none;
        }

        button {
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

        button:hover {
            background-color: #128C7E;
        }

        .status-message {
            margin-bottom: 1rem;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }

        .note {
            font-size: 0.85rem;
            color: #666;
            margin-top: 1rem;
            text-align: center;
        }
    </style>
    <script>
        function populateFields(select) {
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                document.getElementById('name').value = selectedOption.getAttribute('data-name');
                document.getElementById('to').value = selectedOption.getAttribute('data-phone');
                document.getElementById('plan_type').value = selectedOption.getAttribute('data-plan');
                document.getElementById('price').value = selectedOption.getAttribute('data-price');
            } else {
                // Clear fields if specific user wants that, but usually keeping previous input is safer or just do nothing.
                // For now, let's clear to allow manual entry easily if "Select Client" is chosen
                document.getElementById('name').value = '';
                document.getElementById('to').value = '91';
                document.getElementById('plan_type').value = '';
                document.getElementById('price').value = '';
            }
        }
    </script>
</head>

<body>

    <div class="container">
        <h2>Send Pricing Follow-up</h2>

        <div class="status-message">
            <?php echo $status; ?>
        </div>

        <form method="POST">
            <label for="client_select">Select Client (Optional):</label>
            <select id="client_select" onchange="populateFields(this)">
                <option value="">-- Select a Client --</option>
                <?php foreach ($leads as $lead): ?>
                    <option value="<?php echo htmlspecialchars($lead['id']); ?>"
                        data-name="<?php echo htmlspecialchars($lead['name']); ?>"
                        data-phone="<?php echo htmlspecialchars($lead['whatsapp_number']); ?>"
                        data-plan="<?php echo htmlspecialchars($lead['plan_name']); ?>"
                        data-price="<?php echo htmlspecialchars($lead['total_price']); ?>">
                        <?php echo htmlspecialchars($lead['name']) . ' (' . htmlspecialchars($lead['location']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="name">Client Name ({{1}}):</label>
            <input type="text" id="name" name="name" placeholder="e.g. John Doe" required>

            <label for="to">WhatsApp Number:</label>
            <input type="text" id="to" name="to" placeholder="e.g. 919876543210" value="91" required>

            <label for="plan_type">Plan Type ({{2}}):</label>
            <input type="text" id="plan_type" name="plan_type" placeholder="e.g. Premium Plan" required>

            <label for="price">Price ({{3}}):</label>
            <input type="text" id="price" name="price" placeholder="e.g. ₹5,000" required>

            <button type="submit">Send Message</button>
        </form>

        <p class="note">This will send the <strong>client_pricing_followup</strong> template.</p>
    </div>

</body>

</html>