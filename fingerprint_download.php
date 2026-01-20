<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

$fileId = $_GET['file_id'] ?? '';

if (empty($fileId)) {
    die(json_encode(['success' => false, 'message' => 'File ID is required']));
}

try {
    // Get file details with project info
    $stmt = $pdo->prepare("
        SELECT sf.*,
               p.title as project_title
        FROM substage_files sf
        JOIN project_substages ps ON sf.substage_id = ps.id
        JOIN project_stages pst ON ps.stage_id = pst.id
        JOIN projects p ON pst.project_id = p.id
        WHERE sf.id = ? AND sf.deleted_at IS NULL
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new Exception('File not found');
    }

    $filePath = $file['file_path'];

    if (!file_exists($filePath)) {
        throw new Exception('File does not exist on server');
    }

    // Get the real extension from the file on disk to ensure correctness
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

    // Get the user-friendly name
    $originalName = basename($file['file_name']);

    // Smart name extraction: ONLY strip extension if it matches the real one
    // This fixes issues where names like "G.F ELECTRICAL" were being truncated to "G"
    $dbExtension = pathinfo($originalName, PATHINFO_EXTENSION);

    if (strtolower($dbExtension) === strtolower($extension)) {
        // Extensions match, so it's safe to strip it
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
    } else {
        // Extensions don't match (or DB name has no extension), so the dot is part of the name
        $nameWithoutExt = $originalName;
    }

    // Get and sanitize project title
    $projectTitle = $file['project_title'] ?? 'Project';
    // Remove characters that are illegal in filenames
    $safeProjectTitle = preg_replace('/[^a-zA-Z0-9\s_\-\(\)\.]/', '', $projectTitle); // Added dot to allowed chars just in case
    $safeProjectTitle = trim($safeProjectTitle);

    // Generate filename: Project Name_File Name.ext
    $finalFilename = $safeProjectTitle . '_' . $nameWithoutExt . '.' . $extension;

    // Determine the correct MIME type
    $mimeType = 'application/octet-stream'; // Default
    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($filePath);
    }

    // Set headers for download with clean filename and correct content type
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $finalFilename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>