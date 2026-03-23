<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Error: You are not logged in. Please log into the connect application first, then return to this page.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Request API Tester</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 800px; margin: auto; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        textarea { width: 100%; height: 200px; font-family: monospace; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        pre { background: #f8f9fa; padding: 15px; border: 1px solid #ddd; overflow-x: auto; border-radius: 4px; white-space: pre-wrap; }
        .error { color: #dc3545; }
    </style>
</head>
<body>

<div class="card">
    <h2>1. Setup Request Payload</h2>
    <p>Modify this JSON payload to match exactly what you are trying to submit.</p>
    <textarea id="payloadInput">
{
  "reason": "Testing the submission 500 error directly on production.",
  "approver_id": 1,
  "dates": [
    {
      "date": "2026-03-25",
      "type_id": 1,
      "type_name": "Casual Leave",
      "day_type": "Full Day"
    }
  ]
}
    </textarea>
</div>

<div class="card">
    <h2>2. Run Test</h2>
    <p>This will send it to <code>studio_users/api/save_leave_request.php</code> and capture raw output.</p>
    <button onclick="runTest()">Run Test Request</button>
</div>

<div class="card">
    <h2>3. Result & Error Output</h2>
    <p>The PHP error (or successful JSON output) will appear directly below.</p>
    <pre id="output">Waiting for test to run...</pre>
</div>

<script>
async function runTest() {
    const out = document.getElementById('output');
    out.innerText = "Sending request... Please wait.";
    
    let payload;
    try {
        payload = JSON.parse(document.getElementById('payloadInput').value);
    } catch (e) {
        out.innerText = "Error parsing your JSON payload above:\n" + e.message;
        return;
    }

    try {
        const response = await fetch('studio_users/api/save_leave_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const statusText = `HTTP Status: ${response.status} ${response.statusText}\n\n`;
        const text = await response.text();
        
        try {
            const json = JSON.parse(text);
            out.innerHTML = statusText + "<b style='color:green;'>Valid JSON Received:</b>\n" + JSON.stringify(json, null, 2);
        } catch (e) {
            out.innerHTML = statusText + "<b class='error'>Invalid JSON (Parse Error):</b> " + e.message + "\n\n<b>Raw Server Output (likely contains the PHP Error):</b>\n" + text;
        }
    } catch (err) {
        out.innerHTML = "<b class='error'>Fetch Error:</b> " + err.message;
    }
}
</script>

</body>
</html>
