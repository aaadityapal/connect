<?php
// send_meeting_whatsapp.php
// page to broadcast a meeting schedule template via WhatsApp to active users

require_once 'includes/db_connect.php';
require_once __DIR__ . '/whatsapp/WhatsAppService.php';

$wa = new WhatsAppService();
$results = [];

// load active users for selection (performed regardless of POST)
$availableUsers = [];
$userSql = "SELECT id, username, phone FROM users WHERE status = 'active' AND phone IS NOT NULL AND phone != '' ORDER BY username";
$userRes = $conn->query($userSql);
if ($userRes) {
    while ($u = $userRes->fetch_assoc()) {
        $availableUsers[] = $u;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // gather parameters from form
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $day = $_POST['day'] ?? '';
    $arrival = $_POST['arrival'] ?? '';
    $not_start = $_POST['not_start'] ?? '';
    $not_end = $_POST['not_end'] ?? '';
    $pdf_url = $_POST['pdf_url'] ?? '';
    $pdf_filename = $_POST['pdf_filename'] ?? 'schedule.pdf';
    // if a relative path was provided, convert to full URL assuming same host
    if (!empty($pdf_url) && !preg_match('#^https?://#i', $pdf_url)) {
        // assemble base URL
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $pdf_url = "https://{$host}{$path}/" . ltrim($pdf_url, '/\\');
    }

    // determine which users were selected
    $selectedIds = $_POST['users'] ?? [];
    if (!empty($selectedIds)) {
        foreach ($availableUsers as $user) {
            if (!in_array($user['id'], $selectedIds)) {
                continue;
            }
            // prepare parameter list; first param is name
            $params = [
                $user['username'],
                $date,
                $time,
                $day,
                $arrival,
                $not_start,
                $not_end
            ];

            $phone = preg_replace('/[^0-9]/', '', $user['phone']);
            $resp = $wa->sendTemplateMessageWithDocument($phone, 'meeting_schedule_notification', 'en_US', $params, $pdf_url, $pdf_filename);
            $results[] = [
                'user' => $user['username'],
                'phone' => $phone,
                'success' => $resp['success'],
                'response' => $resp['response'] ?? ''
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Meeting WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="max-w-3xl mx-auto py-8">
        <h1 class="text-2xl font-semibold mb-6 text-center">Broadcast Meeting Schedule</h1>
        <form method="post" class="space-y-4 bg-white p-6 rounded shadow">
            <!-- user selection -->
            <div>
                <label class="block font-medium mb-2">Select recipients</label>
                <div class="flex items-center mb-2">
                    <input type="checkbox" id="select_all" class="mr-2" />
                    <label for="select_all" class="text-sm">Select / deselect all</label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto border p-2">
                    <?php foreach ($availableUsers as $u): ?>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="users[]" value="<?php echo $u['id']; ?>" class="user-checkbox mr-2" />
                                <?php echo htmlspecialchars($u['username']); ?> (<?php echo htmlspecialchars($u['phone']); ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium">Date</label>
                    <input type="date" name="date" required class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block font-medium">Time</label>
                    <input type="text" name="time" placeholder="e.g. 10:00 AM" required class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block font-medium">Day</label>
                    <input type="text" name="day" placeholder="e.g. Saturday" required class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block font-medium">Site team arrival by</label>
                    <input type="text" name="arrival" placeholder="e.g. 8:30 AM" class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block font-medium">Unavailable from</label>
                    <input type="text" name="not_start" placeholder="e.g. 1:00 PM" class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block font-medium">Unavailable to</label>
                    <input type="text" name="not_end" placeholder="e.g. 5:00 PM" class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label class="block font-medium">PDF Location (optional)</label>
                    <input type="text" name="pdf_url" value="Meeting Agenda.pdf" placeholder="relative or absolute URL to PDF" class="mt-1 block w-full border-gray-300 rounded" />
                    <p class="text-xs text-gray-500 mt-1">If the file is stored locally, supply the relative filename; otherwise provide a full HTTPS URL.</p>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label class="block font-medium">PDF Filename</label>
                    <input type="text" name="pdf_filename" value="schedule.pdf" class="mt-1 block w-full border-gray-300 rounded" />
                </div>
            </div>
            <div class="text-center">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Send WhatsApp</button>
            </div>
        </form>

        <?php if (!empty($results)): ?>
        <script>
            document.getElementById('select_all').addEventListener('change', function() {
                var checked = this.checked;
                document.querySelectorAll('.user-checkbox').forEach(function(cb) {
                    cb.checked = checked;
                });
            });
        </script>
        <div class="mt-8 bg-white p-6 rounded shadow">
            <h2 class="text-xl font-semibold mb-4">Send Results</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">User</th>
                        <th class="px-4 py-2 text-left">Phone</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Response</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td class="px-4 py-2"><?php echo htmlspecialchars($r['user']); ?></td>
                        <td class="px-4 py-2"><?php echo htmlspecialchars($r['phone']); ?></td>
                        <td class="px-4 py-2"><?php echo $r['success'] ? '<span class="text-green-600">OK</span>' : '<span class="text-red-600">FAIL</span>'; ?></td>
                        <td class="px-4 py-2"><pre class="whitespace-pre-wrap text-xs"><?php echo htmlspecialchars($r['response']); ?></pre></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
