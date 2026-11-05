<?php
// Start output buffering and session checks
ob_start();
header('Content-Type: application/json');

require_once '../config/db.php';
require_once '../config/session_check.php';

$sectionID = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);

if (!$sectionID) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT bss.ID as MappingID, st.StudentNumber, a.FirstName, a.LastName 
            FROM block_section_students bss
            INNER JOIN students st ON bss.StudentID = st.StudentID
            INNER JOIN applicants a ON st.ApplicantID = a.ApplicantID
            WHERE bss.SectionID = ?";
            
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepared statement error.");
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $sectionID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode($students);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>