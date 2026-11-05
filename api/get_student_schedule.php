<?php
require_once '../config/db.php';
require_once '../config/session_check.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'schedules' => []
];

try {
    $studentID = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    if (!$studentID) {
        throw new Exception("Invalid student ID reference.");
    }

    // 1. Fetch Active Academic Term
    $termQuery = mysqli_query($conn, "SELECT ID FROM academic_years WHERE IsActive = 1 LIMIT 1");
    $activeTerm = mysqli_fetch_assoc($termQuery);
    $activeYearID = $activeTerm['ID'] ?? 0;

    if (!$activeYearID) {
        throw new Exception("No active academic term found.");
    }

    // 2. Fetch Enrolled Classes & Schedules for the Student for the Active Term
    $query = "
        SELECT cl.ID as ClassID, cl.ClassName, s.SubjectCode, s.SubjectTitle
        FROM enrollments e
        JOIN classes cl ON e.ClassID = cl.ID
        JOIN curriculum_subjects cs ON cl.CurriculumSubjectID = cs.ID
        JOIN subjects s ON cs.SubjectID = s.ID
        WHERE e.StudentID = ? AND cl.AcademicYearID = ? AND e.Status IN ('Draft', 'Enrolled')
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $studentID, $activeYearID);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $enrolledClasses = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $classID = $row['ClassID'];

        // Fetch schedules for each class
        $schedQuery = mysqli_prepare($conn, "SELECT * FROM class_schedules WHERE ClassID = ?");
        mysqli_stmt_bind_param($schedQuery, 'i', $classID);
        mysqli_stmt_execute($schedQuery);
        $schedRes = mysqli_stmt_get_result($schedQuery);
        
        $schedTextArr = [];
        $roomArr = [];
        while ($sRow = mysqli_fetch_assoc($schedRes)) {
            $schedTextArr[] = $sRow['DayOfWeek'] . ' ' . date('h:i A', strtotime($sRow['StartTime'])) . '-' . date('h:i A', strtotime($sRow['EndTime']));
            if (!empty($sRow['RoomAssignment'])) {
                $roomArr[] = trim($sRow['RoomAssignment']);
            }
        }
        mysqli_stmt_close($schedQuery);

        $row['ScheduleStr'] = !empty($schedTextArr) ? implode(' | ', $schedTextArr) : 'Schedule TBA';
        
        // Deduplicate rooms or join with a slash if multiple rooms exist
        $uniqueRooms = array_unique($roomArr);
        $row['RoomAssignment'] = !empty($uniqueRooms) ? implode(' / ', $uniqueRooms) : 'TBA';

        $enrolledClasses[] = $row;
    }
    mysqli_stmt_close($stmt);

    $response['success'] = true;
    $response['schedules'] = $enrolledClasses;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;