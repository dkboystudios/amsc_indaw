<?php
// Start output buffering and session checks
ob_start();
header('Content-Type: application/json');

require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$mappingID = filter_input(INPUT_POST, 'mapping_id', FILTER_VALIDATE_INT);
$sectionID = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);

if (!$mappingID) {
    echo json_encode(['success' => false, 'error' => 'Invalid mapping ID.']);
    exit;
}

try {
    $sql = "DELETE FROM block_section_students WHERE ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepared statement error.");
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $mappingID);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($sectionID) {
        $updateCountSql = "UPDATE sections SET CurrentEnrolled = (SELECT COUNT(*) FROM block_section_students WHERE SectionID = ?) WHERE ID = ?";
        $upStmt = mysqli_prepare($conn, $updateCountSql);
        if ($upStmt) {
            mysqli_stmt_bind_param($upStmt, 'ii', $sectionID, $sectionID);
            mysqli_stmt_execute($upStmt);
            mysqli_stmt_close($upStmt);
        }
    }

    logActivity($conn, $userID, 'Student Removed from Section', "Removed student mapping ID {$mappingID} from section via AJAX.");
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>