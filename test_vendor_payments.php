<?php
session_start();
require_once 'config/db_connect.php';

// Simple Auth Check bypass for testing or use session
if (!isset($_SESSION['user_id'])) {
    die("Please log in to the main dashboard first.");
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Test Vendor Payments</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
        }

        .vendor-list {
            margin-bottom: 20px;
        }

        .vendor-item {
            padding: 10px;
            border: 1px solid #ccc;
            margin-bottom: 5px;
            cursor: pointer;
            background: #f9f9f9;
        }

        .vendor-item:hover {
            background: #eee;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #333;
            color: white;
        }
    </style>
</head>

<body>
    <h1>Test Page: Vendor Payment Records</h1>
    <p>This page tests the backend query logic directly, bypassing any dashboard CSS limitations.</p>

    <div style="display: flex; gap: 20px;">
        <div style="width: 300px; height: 80vh; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">
            <h3>Select Vendor</h3>
            <?php
            // Get vendors with record counts
            $q = "
                SELECT v.vendor_id, v.vendor_full_name, 
                (SELECT COUNT(*) FROM tbl_payment_entry_line_items_detail WHERE recipient_id_reference = v.vendor_id) as count_linked,
                 (SELECT COUNT(*) FROM tbl_payment_entry_line_items_detail WHERE recipient_name_display LIKE v.vendor_full_name AND (recipient_id_reference = 0 OR recipient_id_reference IS NULL)) as count_text
                FROM pm_vendor_registry_master v
                HAVING (count_linked + count_text) > 0
                ORDER BY (count_linked + count_text) DESC
            ";
            $stmt = $pdo->query($q);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $total = $row['count_linked'] . ' (ID) + ' . $row['count_text'] . ' (Name)';
                echo "<div class='vendor-item' onclick='loadPayments({$row['vendor_id']})'>";
                echo "<b>{$row['vendor_full_name']}</b> (ID: {$row['vendor_id']})<br>";
                echo "<small>Records: $total</small>";
                echo "</div>";
            }
            ?>
        </div>

        <div style="flex: 1;">
            <h3>Results for Vendor ID: <span id="vId">None</span></h3>
            <div id="results">Select a vendor...</div>
        </div>
    </div>

    <script>
        async function loadPayments(id) {
            document.getElementById('vId').textContent = id;
            document.getElementById('results').innerHTML = 'Loading...';

            try {
                const res = await fetch(`fetch_vendor_payment_records.php?vendor_id=${id}`);
                const json = await res.json();

                if (!json.success) {
                    document.getElementById('results').innerHTML = `<p style='color:red'>Error: ${json.message}</p>`;
                    return;
                }

                let html = `<b>Total Records Returned: ${json.data.length}</b>`;
                if (json.data.length === 0) {
                    html += "<p>No records found.</p>";
                } else {
                    html += `<table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Amount</th>
                            <th>Recipient Name (in DB)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;
                    json.data.forEach((r, i) => {
                        html += `<tr>
                        <td>${i + 1}</td>
                        <td>${r.payment_date_logged}</td>
                        <td>${r.project_name}</td>
                        <td>${r.line_item_amount}</td>
                        <td>${r.recipient_name_display}</td>
                        <td>${r.entry_status_current}</td>
                    </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                document.getElementById('results').innerHTML = html;

            } catch (e) {
                document.getElementById('results').innerHTML = `<p style='color:red'>Fetch Error: ${e.message}</p>`;
            }
        }
    </script>
</body>

</html>