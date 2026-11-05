<?php
require_once '../config/db.php';
require_once '../config/session_check.php';

header('Content-Type: application/json');

$staffID = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
$academicYearID = filter_input(INPUT_GET, 'academic_year', FILTER_VALIDATE_INT) ?: 1;
$semester = trim($_GET['semester'] ?? '1st Semester');

if (!$staffID) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff reference.']);
    exit;
}

$loads = [];
$totalUnits = 0;

$sql = "SELECT c.ID as ClassID, c.ClassName, s.SubjectCode, s.SubjectTitle, cs.Units as TotalUnits,
               GROUP_CONCAT(CONCAT(csch.DayOfWeek, ' ', TIME_FORMAT(csch.StartTime, '%h:%i %p'), ' - ', TIME_FORMAT(csch.EndTime, '%h:%i %p'), ' (', csch.RoomAssignment, ')') SEPARATOR '; ') as ScheduleDisplay
        FROM classes c
        INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
        INNER JOIN subjects s ON cs.SubjectID = s.ID
        LEFT JOIN class_schedules csch ON c.ID = csch.ClassID
        WHERE c.InstructorID = ? AND c.AcademicYearID = ? AND cs.Semester = ?
        GROUP BY c.ID";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iis", $staffID, $academicYearID, $semester);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $loads[] = $row;
        $totalUnits += floatval($row['TotalUnits']);
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true, 
        'loads' => $loads, 
        'totalUnits' => $totalUnits
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database query preparation failed.'
    ]);
}