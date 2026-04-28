<?php
session_start();
require_once 'config.php';

// 1. Basic Auth Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Login required');
}

$action = $_GET['action'] ?? 'view';
$id = (int)($_GET['id'] ?? 0);
$category = $_GET['category'] ?? 'policy';
$category = strtolower(trim((string)$category));

// Normalize common aliases to supported categories.
$categoryMap = [
    'policies' => 'policy',
    'hr-policy' => 'policy',
    'hr-policies' => 'policy',
    'salary-slip' => 'salary',
    'salary-slips' => 'salary',
    'offer-letter' => 'offer',
    'appraisal-letter' => 'appraisal',
    'experience-letter' => 'experience',
    'confiedential' => 'confidential',
    'confiedential-document' => 'confidential',
    'confidential-document' => 'confidential'
];

if (isset($categoryMap[$category])) {
    $category = $categoryMap[$category];
}
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'User';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $document = null;
    $uploadDir = 'uploads/hr_documents/'; // Default

    // 2. Fetch based on category and check ownership
    switch ($category) {
        case 'policy':
            $stmt = $pdo->prepare("SELECT filename, original_name, file_type as mime FROM hr_documents WHERE id = ? AND status = 'published'");
            $stmt->execute([$id]);
            break;
        case 'salary':
            $stmt = $pdo->prepare("SELECT filename, original_name, user_id FROM salary_slips WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'offer':
            $stmt = $pdo->prepare("SELECT file_name as filename, original_name, user_id FROM offer_letters WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'appraisal':
            $stmt = $pdo->prepare("SELECT filename, original_name, user_id FROM appraisals WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'experience':
            $stmt = $pdo->prepare("SELECT filename, original_name, user_id FROM experience_letters WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'confidential':
            $stmt = $pdo->prepare("SELECT file_stored_name as filename, file_original_name as original_name, employee_id as user_id, file_mime as mime FROM employee_confiedential_documents WHERE id = ?");
            $stmt->execute([$id]);
            break;
        default:
            exit('Invalid category');
    }

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        exit('Document not found or access denied');
    }

    // 3. Authorization Check: Must be HR OR the owner of the document
    if ($category === 'confidential') {
        if ($userRole !== 'admin' && $userRole !== 'Manager' && $document['user_id'] != $userId && $userRole !== 'HR') {
            http_response_code(403);
            exit('Unauthorized access to this document');
        }
    } else if ($category !== 'policy' && $userRole !== 'HR' && $document['user_id'] != $userId) {
        http_response_code(403);
        exit('Unauthorized access to this document');
    }

    // 4. File Path Resolution
    if ($category === 'confidential') {
        $filePath = 'uploads/employee_confiedential_documents/' . $document['user_id'] . '/' . $document['filename'];
    } else {
        $filePath = $uploadDir . $document['filename'];
        if (!file_exists($filePath)) {
            // Try alternate location if not found in HR dir
            $altPath = 'uploads/career_documents/' . $document['filename'];
            $filePath = file_exists($altPath) ? $altPath : $filePath;
        }
    }

    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File not found on server');
    }

    // 5. Output File
    $mime = $document['mime'] ?? 'application/pdf';
    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($filePath));
    
    $disposition = ($action === 'download') ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . $document['original_name'] . '"');
    
    if (ob_get_level()) ob_end_clean();
    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}