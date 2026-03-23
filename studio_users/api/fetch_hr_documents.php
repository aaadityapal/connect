<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    // 1. Fetch Company-wide Policies
    $sql = "(SELECT 
        d.id,
        'HR Policy' as type,
        d.filename,
        d.original_name as name,
        d.upload_date,
        d.file_size,
        d.file_type as extension,
        CASE 
            WHEN da.acknowledged_at IS NOT NULL THEN 'Acknowledged'
            ELSE 'Pending'
        END as acknowledgment_status
    FROM hr_documents d
    LEFT JOIN document_acknowledgments da ON d.id = da.document_id AND da.user_id = ?
    WHERE d.status = 'published')

    UNION ALL

    (SELECT id, 'Salary Slip' as type, filename, original_name as name, upload_date, file_size, SUBSTRING_INDEX(filename, '.', -1) as extension, status as acknowledgment_status
     FROM salary_slips WHERE user_id = ?)

    UNION ALL

    (SELECT id, 'Offer Letter' as type, file_name as filename, original_name as name, upload_date, file_size, SUBSTRING_INDEX(file_name, '.', -1) as extension, status as acknowledgment_status
     FROM offer_letters WHERE user_id = ?)

    UNION ALL

    (SELECT id, 'Appraisal' as type, filename, original_name as name, upload_date, file_size, SUBSTRING_INDEX(filename, '.', -1) as extension, status as acknowledgment_status
     FROM appraisals WHERE user_id = ?)

    UNION ALL

    (SELECT id, 'Experience Letter' as type, filename, original_name as name, upload_date, file_size, SUBSTRING_INDEX(filename, '.', -1) as extension, status as acknowledgment_status
     FROM experience_letters WHERE user_id = ?)

    ORDER BY upload_date DESC";

    $stmt = $pdo->prepare($sql);
    $userId = $_SESSION['user_id'];
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $hrDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format file sizes
    foreach ($hrDocs as &$doc) {
        $size = $doc['file_size'];
        if ($size < 1024) $doc['formatted_size'] = $size . ' B';
        elseif ($size < 1048576) $doc['formatted_size'] = round($size/1024, 2) . ' KB';
        else $doc['formatted_size'] = round($size/1048576, 2) . ' MB';
        
        // Normalize status capitalization
        $doc['acknowledgment_status'] = ucfirst(strtolower($doc['acknowledgment_status']));
    }
    
    echo json_encode([
        'status' => 'success',
        'hr_documents' => $hrDocs
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
