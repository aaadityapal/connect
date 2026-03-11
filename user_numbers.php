<?php
// user_numbers.php
// displays a list of user ids and usernames with a simple styled UI

require_once 'includes/db_connect.php';

// fetch all active users from the `users` table including phone
$sql = "SELECT id, username, phone FROM users WHERE status = 'active' ORDER BY username";
$result = $conn->query($sql);

$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <!-- Tailwind CDN for quick styling -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="max-w-4xl mx-auto py-8">
        <h1 class="text-3xl font-semibold mb-6 text-center">Users</h1>
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WhatsApp</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!empty($user['phone'])): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/','', $user['phone']); ?>" target="_blank" class="text-green-500 hover:text-green-700">
                                    <!-- Font Awesome WhatsApp icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.868-2.03-.967-.272-.099-.47-.148-.668.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.075-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.019-.458.13-.606.134-.133.298-.347.446-.52.149-.173.198-.297.298-.497.099-.198.05-.372-.025-.521-.075-.149-.668-1.611-.916-2.203-.242-.579-.487-.5-.668-.51-.173-.011-.372-.013-.57-.013s-.521.075-.792.372c-.272.297-1.04 1.016-1.04 2.479s1.064 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.26.489 1.691.625.71.226 1.357.194 1.87.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.289.173-1.414-.074-.124-.272-.198-.57-.347z" />
                                        <path d="M20.52 3.48A10.025 10.025 0 0012.002 2C6.486 2 2 6.486 2 12.002a9.965 9.965 0 001.523 5.099L2 22l4.941-1.573A9.969 9.969 0 0012.002 22c5.516 0 10.016-4.486 10.516-9.998a9.945 9.945 0 00-2-6.522z"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
