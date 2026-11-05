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

$sectionID = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

if (!$sectionID || !$studentID) {
    echo json_encode(['success' => false, 'error' => 'Invalid section or student selected.']);
    exit;
}

try {
    $sql = "INSERT INTO block_section_students (SectionID, StudentID) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepared statement error.");
    }
    
    mysqli_stmt_bind_param($stmt, 'ii', $sectionID, $studentID);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update CurrentEnrolled count in sections table
    $updateCountSql = "UPDATE sections SET CurrentEnrolled = (SELECT COUNT(*) FROM block_section_students WHERE SectionID = ?) WHERE ID = ?";
    $upStmt = mysqli_prepare($conn, $updateCountSql);
    if ($upStmt) {
        mysqli_stmt_bind_param($upStmt, 'ii', $sectionID, $sectionID);
        mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);
    }

    logActivity($conn, $userID, 'Student Added to Section', "Added student ID {$studentID} to section ID {$sectionID} via AJAX.");
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>