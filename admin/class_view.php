<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Class Schedule & Roster - AMSC InnDaw";

// Fetch Class ID from Query Parameter
$classId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: 0);

if (!$classId) {
    redirectWithMessage('error', 'Invalid class reference.', 'classes');
}

// Helper day expander function for PHP conflict checks
function expandDaysPHP($dayStr) {
    $days = [];
    $dayStr = strtoupper(trim($dayStr));
    if (str_contains($dayStr, 'MON') || (str_contains($dayStr, 'M') && !str_contains($dayStr, 'TH'))) $days[] = 'MON';
    if (str_contains($dayStr, 'TUE') || (str_contains($dayStr, 'T') && !str_contains($dayStr, 'TH'))) $days[] = 'TUE';
    if (str_contains($dayStr, 'WED') || str_contains($dayStr, 'W')) $days[] = 'WED';
    if (str_contains($dayStr, 'THU') || str_contains($dayStr, 'TH')) $days[] = 'THU';
    if (str_contains($dayStr, 'FRI') || str_contains($dayStr, 'F')) $days[] = 'FRI';
    if (str_contains($dayStr, 'SAT') || str_contains($dayStr, 'S')) $days[] = 'SAT';
    if (str_contains($dayStr, 'SUN') || str_contains($dayStr, 'A')) $days[] = 'SUN';
    return array_unique($days);
}

// Fetch current class AcademicYearID for conflict checking scope
$termCheckQ = mysqli_query($conn, "SELECT AcademicYearID FROM classes WHERE ID = $classId LIMIT 1");
$termCheckRow = mysqli_fetch_assoc($termCheckQ);
$currentClassYearID = $termCheckRow['AcademicYearID'] ?? 0;

// -------------------------------------------------------------------------
// POST ACTION HANDLERS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        // 1. UPDATE CLASS DETAILS
        if ($action === 'update_class_details') {
            $className      = trim($_POST['class_name'] ?? '');
            $currSubID      = filter_input(INPUT_POST, 'curriculum_subject_id', FILTER_VALIDATE_INT);
            $instructorID   = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
            $maxCapacity    = filter_input(INPUT_POST, 'max_capacity', FILTER_VALIDATE_INT) ?: 40;
            $isEnrollOpen   = isset($_POST['is_enrollment_open']) ? 1 : 0;
            $isPetition     = isset($_POST['is_petition']) ? 1 : 0;

            if (empty($className) || !$currSubID) {
                throw new Exception("Please provide all required class information.");
            }

            if ($instructorID === false) {
                $instructorID = NULL;
            }

            $stmt = mysqli_prepare($conn, "UPDATE classes SET ClassName = ?, CurriculumSubjectID = ?, InstructorID = ?, MaxCapacity = ?, IsEnrollmentOpen = ?, IsPetition = ?, UpdatedAt = NOW() WHERE ID = ?");
            mysqli_stmt_bind_param($stmt, 'siiiiii', $className, $currSubID, $instructorID, $maxCapacity, $isEnrollOpen, $isPetition, $classId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Class Information Updated', "Updated details for class ID #{$classId} ({$className}).");
            redirectWithMessage('success', 'Class information updated successfully.', "class_view?id={$classId}&");
        }

        // 2. DELETE CLASS
        elseif ($action === 'delete_class') {
            $stmt = mysqli_prepare($conn, "DELETE FROM classes WHERE ID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $classId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Class Deleted', "Deleted class ID #{$classId}.");
            redirectWithMessage('success', 'Class deleted successfully.', 'classes?');
        }

        // 3. SAVE / UPDATE SCHEDULE SESSION
        elseif ($action === 'save_schedule') {
            $schedID     = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
            $description = trim($_POST['description'] ?? 'Lecture');
            $dayOfWeek   = trim($_POST['day_of_week'] ?? '');
            $startTime   = trim($_POST['start_time'] ?? '');
            $endTime     = trim($_POST['end_time'] ?? '');
            $room        = trim($_POST['room_assignment'] ?? 'TBA');

            if (empty($dayOfWeek) || empty($startTime) || empty($endTime)) {
                throw new Exception("Days of week, start time, and end time are required.");
            }

            if ($schedID) {
                $stmt = mysqli_prepare($conn, "UPDATE class_schedules SET Description = ?, DayOfWeek = ?, StartTime = ?, EndTime = ?, RoomAssignment = ?, UpdatedAt = NOW() WHERE ID = ? AND ClassID = ?");
                mysqli_stmt_bind_param($stmt, 'sssssii', $description, $dayOfWeek, $startTime, $endTime, $room, $schedID, $classId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID ?? 0, 'Class Schedule Updated', "Updated schedule #{$schedID} for class #{$classId}.");
                redirectWithMessage('success', 'Schedule session updated successfully.', "class_view?id={$classId}&");
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO class_schedules (ClassID, Description, DayOfWeek, StartTime, EndTime, RoomAssignment) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isssss', $classId, $description, $dayOfWeek, $startTime, $endTime, $room);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID ?? 0, 'Class Schedule Added', "Added schedule session to class #{$classId}.");
                redirectWithMessage('success', 'Schedule session added successfully.', "class_view?id={$classId}&");
            }
        }

        // 4. DELETE SCHEDULE SESSION
        elseif ($action === 'delete_schedule') {
            $schedID = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
            if (!$schedID) throw new Exception("Invalid schedule reference.");

            $stmt = mysqli_prepare($conn, "DELETE FROM class_schedules WHERE ID = ? AND ClassID = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $schedID, $classId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Class Schedule Removed', "Removed schedule #{$schedID} from class #{$classId}.");
            redirectWithMessage('success', 'Schedule session removed.', "class_view?id={$classId}&");
        }

        // 5. ENROLL STUDENT TO CLASS (WITH SCHEDULE CONFLICT CHECK)
        elseif ($action === 'enroll_student') {
            $studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            if (!$studentID) throw new Exception("Please select a valid student.");

            $checkStmt = mysqli_prepare($conn, "SELECT ID FROM enrollments WHERE StudentID = ? AND ClassID = ?");
            mysqli_stmt_bind_param($checkStmt, 'ii', $studentID, $classId);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                mysqli_stmt_close($checkStmt);
                throw new Exception("Student is already enrolled in this class.");
            }
            mysqli_stmt_close($checkStmt);

            // Check capacity
            $capCheck = mysqli_query($conn, "
                SELECT c.MaxCapacity, (SELECT COUNT(*) FROM enrollments WHERE ClassID = c.ID AND Status IN ('Draft', 'Enrolled')) as EnrolledCount 
                FROM classes c WHERE c.ID = $classId
            ");
            $capRow = mysqli_fetch_assoc($capCheck);
            if ($capRow['EnrolledCount'] >= $capRow['MaxCapacity']) {
                throw new Exception("Class has reached its maximum student capacity.");
            }

            // SCHEDULE CONFLICT CHECK FOR ENROLLMENT
            // Fetch target class schedules
            $targetSchedules = [];
            $tSchQ = mysqli_query($conn, "SELECT * FROM class_schedules WHERE ClassID = $classId");
            while ($ts = mysqli_fetch_assoc($tSchQ)) { $targetSchedules[] = $ts; }

            // Fetch student's other enrolled class schedules for the same term
            $studentSchedQuery = "
                SELECT cs.*, cl.ClassName 
                FROM enrollments e 
                JOIN classes cl ON e.ClassID = cl.ID 
                JOIN class_schedules cs ON cl.ID = cs.ClassID 
                WHERE e.StudentID = ? AND e.Status IN ('Draft', 'Enrolled') AND cl.AcademicYearID = ? AND cl.ID != ?
            ";
            $ssStmt = mysqli_prepare($conn, $studentSchedQuery);
            mysqli_stmt_bind_param($ssStmt, 'iii', $studentID, $currentClassYearID, $classId);
            mysqli_stmt_execute($ssStmt);
            $ssRes = mysqli_stmt_get_result($ssStmt);
            $existingSchedules = [];
            while ($es = mysqli_fetch_assoc($ssRes)) { $existingSchedules[] = $es; }
            mysqli_stmt_close($ssStmt);

            // Perform overlap check
            foreach ($targetSchedules as $ts) {
                $days1 = expandDaysPHP($ts['DayOfWeek']);
                foreach ($existingSchedules as $es) {
                    $days2 = expandDaysPHP($es['DayOfWeek']);
                    $commonDays = array_intersect($days1, $days2);
                    if (!empty($commonDays)) {
                        if ($ts['StartTime'] < $es['EndTime'] && $es['StartTime'] < $ts['EndTime']) {
                            throw new Exception("Schedule conflict detected! This student already has a class ('{$es['ClassName']}') during this time slot.");
                        }
                    }
                }
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO enrollments (StudentID, ClassID, Status, EnrolledOn) VALUES (?, ?, 'Enrolled', NOW())");
            mysqli_stmt_bind_param($stmt, 'ii', $studentID, $classId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Student Enrolled in Class', "Enrolled student #{$studentID} into class #{$classId}.");
            redirectWithMessage('success', 'Student successfully enrolled in class.', "class_view?id={$classId}&");
        }

        // 6. REMOVE, DROP, OR WITHDRAW STUDENT
        elseif ($action === 'remove_student') {
            $enrollmentID = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
            $mode = trim($_POST['mode'] ?? 'remove'); // 'remove', 'drop', or 'withdraw'
            if (!$enrollmentID) throw new Exception("Invalid enrollment reference.");

            // Verify status first
            $stCheck = mysqli_prepare($conn, "SELECT Status FROM enrollments WHERE ID = ? AND ClassID = ?");
            mysqli_stmt_bind_param($stCheck, 'ii', $enrollmentID, $classId);
            mysqli_stmt_execute($stCheck);
            $stRes = mysqli_stmt_get_result($stCheck);
            $enRow = mysqli_fetch_assoc($stRes);
            mysqli_stmt_close($stCheck);

            if (!$enRow) throw new Exception("Enrollment record not found.");

            if ($enRow['Status'] === 'Enrolled' && $mode === 'remove') {
                throw new Exception("Enrolled students cannot be removed directly. Use 'Drop' or 'Withdraw' instead.");
            }

            if ($mode === 'drop' || $mode === 'withdraw') {
                $newStatus = ($mode === 'drop') ? 'Dropped' : 'Withdrawn';
                $stmt = mysqli_prepare($conn, "UPDATE enrollments SET Status = ?, UpdatedAt = NOW() WHERE ID = ? AND ClassID = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $enrollmentID, $classId);
            } else {
                // Delete draft
                $stmt = mysqli_prepare($conn, "DELETE FROM enrollments WHERE ID = ? AND ClassID = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $enrollmentID, $classId);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Student Status Updated', "Updated student enrollment status ({$mode}) for enrollment #{$enrollmentID}.");
            redirectWithMessage('success', 'Student status updated successfully.', "class_view?id={$classId}&");
        }

        // 6.5. RE-ENROLL DROPPED OR WITHDRAWN STUDENT (WITH SCHEDULE CONFLICT CHECK)
        elseif ($action === 're_enroll_student') {
            $enrollmentID = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
            if (!$enrollmentID) throw new Exception("Invalid enrollment reference.");

            // Check current status and get StudentID of the enrollment
            $stCheck = mysqli_prepare($conn, "SELECT Status, StudentID FROM enrollments WHERE ID = ? AND ClassID = ?");
            mysqli_stmt_bind_param($stCheck, 'ii', $enrollmentID, $classId);
            mysqli_stmt_execute($stCheck);
            $stRes = mysqli_stmt_get_result($stCheck);
            $enRow = mysqli_fetch_assoc($stRes);
            mysqli_stmt_close($stCheck);

            if (!$enRow) throw new Exception("Enrollment record not found.");

            $studentID = $enRow['StudentID'];
            $currentStatus = strtolower(trim($enRow['Status']));
            if ($currentStatus !== 'dropped' && $currentStatus !== 'withdrawn') {
                throw new Exception("Only dropped or withdrawn students can be re-enrolled.");
            }

            // Check class capacity before re-enrolling
            $capCheck = mysqli_query($conn, "
                SELECT c.MaxCapacity, (SELECT COUNT(*) FROM enrollments WHERE ClassID = c.ID AND Status IN ('Draft', 'Enrolled')) as EnrolledCount 
                FROM classes c WHERE c.ID = $classId
            ");
            $capRow = mysqli_fetch_assoc($capCheck);
            if ($capRow['EnrolledCount'] >= $capRow['MaxCapacity']) {
                throw new Exception("Class has reached its maximum student capacity. Cannot re-enroll.");
            }

            // SCHEDULE CONFLICT CHECK FOR RE-ENROLLMENT
            $targetSchedules = [];
            $tSchQ = mysqli_query($conn, "SELECT * FROM class_schedules WHERE ClassID = $classId");
            while ($ts = mysqli_fetch_assoc($tSchQ)) { $targetSchedules[] = $ts; }

            $studentSchedQuery = "
                SELECT cs.*, cl.ClassName 
                FROM enrollments e 
                JOIN classes cl ON e.ClassID = cl.ID 
                JOIN class_schedules cs ON cl.ID = cs.ClassID 
                WHERE e.StudentID = ? AND e.Status IN ('Draft', 'Enrolled') AND cl.AcademicYearID = ? AND cl.ID != ?
            ";
            $ssStmt = mysqli_prepare($conn, $studentSchedQuery);
            mysqli_stmt_bind_param($ssStmt, 'iii', $studentID, $currentClassYearID, $classId);
            mysqli_stmt_execute($ssStmt);
            $ssRes = mysqli_stmt_get_result($ssStmt);
            $existingSchedules = [];
            while ($es = mysqli_fetch_assoc($ssRes)) { $existingSchedules[] = $es; }
            mysqli_stmt_close($ssStmt);

            foreach ($targetSchedules as $ts) {
                $days1 = expandDaysPHP($ts['DayOfWeek']);
                foreach ($existingSchedules as $es) {
                    $days2 = expandDaysPHP($es['DayOfWeek']);
                    $commonDays = array_intersect($days1, $days2);
                    if (!empty($commonDays)) {
                        if ($ts['StartTime'] < $es['EndTime'] && $es['StartTime'] < $ts['EndTime']) {
                            throw new Exception("Schedule conflict detected! This student has an overlapping class ('{$es['ClassName']}') during this time slot.");
                        }
                    }
                }
            }

            // Update status back to 'Enrolled'
            $stmt = mysqli_prepare($conn, "UPDATE enrollments SET Status = 'Enrolled', UpdatedAt = NOW() WHERE ID = ? AND ClassID = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $enrollmentID, $classId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Student Re-enrolled', "Re-enrolled student from enrollment #{$enrollmentID} back into class #{$classId}.");
            redirectWithMessage('success', 'Student successfully re-enrolled into class.', "class_view?id={$classId}&");
        }

        // 7. REASSIGN STUDENT TO ANOTHER CLASS OF THE SAME SUBJECT (WITH SCHEDULE CONFLICT CHECK)
        elseif ($action === 'reassign_student') {
            $enrollmentID = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
            $newClassID   = filter_input(INPUT_POST, 'new_class_id', FILTER_VALIDATE_INT);
            $studentID    = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

            if (!$enrollmentID || !$newClassID || !$studentID) {
                throw new Exception("Invalid reassignment parameters.");
            }

            // Check if target class has capacity
            $capCheck = mysqli_query($conn, "
                SELECT c.MaxCapacity, c.AcademicYearID, (SELECT COUNT(*) FROM enrollments WHERE ClassID = c.ID AND Status IN ('Draft', 'Enrolled')) as EnrolledCount 
                FROM classes c WHERE c.ID = $newClassID
            ");
            $capRow = mysqli_fetch_assoc($capCheck);
            if (!$capRow) throw new Exception("Target class not found.");

            if ($capRow['EnrolledCount'] >= $capRow['MaxCapacity']) {
                throw new Exception("Target class has reached its maximum student capacity.");
            }
            $targetYearID = $capRow['AcademicYearID'];

            // SCHEDULE CONFLICT CHECK FOR REASSIGNMENT TO NEW CLASS
            $targetSchedules = [];
            $tSchQ = mysqli_query($conn, "SELECT * FROM class_schedules WHERE ClassID = $newClassID");
            while ($ts = mysqli_fetch_assoc($tSchQ)) { $targetSchedules[] = $ts; }

            $studentSchedQuery = "
                SELECT cs.*, cl.ClassName 
                FROM enrollments e 
                JOIN classes cl ON e.ClassID = cl.ID 
                JOIN class_schedules cs ON cl.ID = cs.ClassID 
                WHERE e.StudentID = ? AND e.Status IN ('Draft', 'Enrolled') AND cl.AcademicYearID = ? AND cl.ID != ?
            ";
            $ssStmt = mysqli_prepare($conn, $studentSchedQuery);
            mysqli_stmt_bind_param($ssStmt, 'iii', $studentID, $targetYearID, $classId);
            mysqli_stmt_execute($ssStmt);
            $ssRes = mysqli_stmt_get_result($ssStmt);
            $existingSchedules = [];
            while ($es = mysqli_fetch_assoc($ssRes)) { $existingSchedules[] = $es; }
            mysqli_stmt_close($ssStmt);

            foreach ($targetSchedules as $ts) {
                $days1 = expandDaysPHP($ts['DayOfWeek']);
                foreach ($existingSchedules as $es) {
                    $days2 = expandDaysPHP($es['DayOfWeek']);
                    $commonDays = array_intersect($days1, $days2);
                    if (!empty($commonDays)) {
                        if ($ts['StartTime'] < $es['EndTime'] && $es['StartTime'] < $ts['EndTime']) {
                            throw new Exception("Schedule conflict detected in target section! Student has an overlapping class ('{$es['ClassName']}').");
                        }
                    }
                }
            }

            // Update enrollment ClassID
            $stmt = mysqli_prepare($conn, "UPDATE enrollments SET ClassID = ?, UpdatedAt = NOW() WHERE ID = ? AND StudentID = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $newClassID, $enrollmentID, $studentID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Student Reassigned', "Reassigned student #{$studentID} from class #{$classId} to class #{$newClassID}.");
            redirectWithMessage('success', 'Student successfully reassigned to new class section.', "class_view?id={$classId}&");
        }
    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), "class_view?id={$classId}&");
    }
}

// -------------------------------------------------------------------------
// FETCH CLASS DETAILS, INSTRUCTOR, SCHEDULES & ROSTER
// -------------------------------------------------------------------------

$classQuery = "
    SELECT c.*, 
           cs.ID AS CurriculumSubjectID, cs.YearLevel, cs.Semester AS SubSemester, cs.Units,
           s.ID AS SubjectID, s.SubjectCode, s.SubjectTitle,
           ay.YearCode AS AYCode, ay.Semester AS AYSemester, CONCAT('AY ', ay.YearCode, ', ', ay.Semester) AS AcademicTerm,
           sm.StaffID, sm.EmployeeNumber, sm.FirstName AS InstFirst, sm.MiddleName AS InstMiddle, sm.LastName AS InstLast, sm.ExtName AS InstExt, sm.ProfilePicture AS InstPhoto, sm.PositionTitle, sm.ContactNumber, sm.Gender AS InstGender
    FROM classes c
    JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    LEFT JOIN academic_years ay ON c.AcademicYearID = ay.ID
    LEFT JOIN staff_members sm ON c.InstructorID = sm.StaffID
    WHERE c.ID = $classId
    LIMIT 1
";
$classRes = mysqli_query($conn, $classQuery);
$classData = mysqli_fetch_assoc($classRes);

if (!$classData) {
    redirectWithMessage('error', 'Class record not found.', 'classes');
}

$subjectID = $classData['SubjectID'];
$curSubjectID = $classData['CurriculumSubjectID'];
$academicYearID = $classData['AcademicYearID'];

// 2. Fetch Weekly Schedules
$schedQuery = "SELECT * FROM class_schedules WHERE ClassID = $classId ORDER BY ID ASC";
$schedRes = mysqli_query($conn, $schedQuery);
$schedules = [];
while ($sc = mysqli_fetch_assoc($schedRes)) {
    $schedules[] = $sc;
}

// 3. Fetch Enrolled Students Roster with Profile Pictures & Applicant details
$rosterQuery = "
    SELECT e.ID AS EnrollmentID, e.Status, e.EnrolledOn,
           st.StudentID, st.StudentNumber, st.EnrollmentStatus AS StudentGlobalStatus,
           a.FirstName, a.MiddleName, a.LastName, a.ExtName, a.Gender, a.ProfilePicture, a.ContactNumber, u.Email AS EmailAddress, a.BirthDate, a.Address,
           p.ProgramCode, p.ProgramName
    FROM enrollments e
    JOIN students st ON e.StudentID = st.StudentID
    JOIN applicants a ON st.ApplicantID = a.ApplicantID
    LEFT JOIN programs p ON st.ProgramID = p.ID
    JOIN users u ON a.UserID = u.ID
    WHERE e.ClassID = $classId
    ORDER BY a.LastName ASC
";
$rosterRes = mysqli_query($conn, $rosterQuery);
$rosterList = [];
while ($rRow = mysqli_fetch_assoc($rosterRes)) {
    $rosterList[] = $rRow;
}
$currentEnrolledCount = count($rosterList);

// 4. Fetch Alternative Classes for Reassignment (Same Subject, Same Academic Year, excluding current class)
$altClassesQuery = "
    SELECT c.ID, c.ClassName, c.MaxCapacity, 
           (SELECT COUNT(*) FROM enrollments WHERE ClassID = c.ID AND Status IN ('Draft', 'Enrolled')) as EnrolledCount,
           sm.LastName AS InstLast
    FROM classes c
    JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
    LEFT JOIN staff_members sm ON c.InstructorID = sm.StaffID
    WHERE cs.SubjectID = ? AND c.AcademicYearID = ? AND c.ID != ?
";
$altStmt = mysqli_prepare($conn, $altClassesQuery);
mysqli_stmt_bind_param($altStmt, 'iii', $subjectID, $academicYearID, $classId);
mysqli_stmt_execute($altStmt);
$altRes = mysqli_stmt_get_result($altStmt);
$alternativeClasses = [];
while ($ac = mysqli_fetch_assoc($altRes)) {
    $alternativeClasses[] = $ac;
}
mysqli_stmt_close($altStmt);

// Subject Pool & Instructor Pool for Modals
$subjectPoolRes = mysqli_query($conn, "
    SELECT cs.ID AS CurriculumSubjectID, s.SubjectCode, s.SubjectTitle, cs.YearLevel, cs.Semester
    FROM curriculum_subjects cs
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    ORDER BY s.SubjectCode ASC
");
$subjectPool = [];
while ($sp = mysqli_fetch_assoc($subjectPoolRes)) { $subjectPool[] = $sp; }

$instructorPoolRes = mysqli_query($conn, "
    SELECT DISTINCT sm.StaffID, sm.EmployeeNumber, sm.FirstName, sm.LastName, sm.PositionTitle
    FROM staff_members sm
    JOIN staff_designations sd ON sm.StaffID = sd.StaffID
    WHERE sd.AppointmentType = 'Academic' AND sm.IsActive = 1
    ORDER BY sm.LastName ASC
");
$instructorPool = [];
while ($ip = mysqli_fetch_assoc($instructorPoolRes)) { $instructorPool[] = $ip; }

$availableStudentsRes = mysqli_query($conn, "
    SELECT st.StudentID, st.StudentNumber, a.FirstName, a.LastName, p.ProgramCode
    FROM students st
    JOIN applicants a ON st.ApplicantID = a.ApplicantID
    LEFT JOIN programs p ON st.ProgramID = p.ID
    WHERE st.StudentID NOT IN (SELECT StudentID FROM enrollments WHERE ClassID = $classId)
    ORDER BY a.LastName ASC
");
$availableStudents = [];
while ($asp = mysqli_fetch_assoc($availableStudentsRes)) { $availableStudents[] = $asp; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .schedule-container { display: flex; flex-direction: column; gap: 25px; }
        
        .class-info-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .class-info-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }

        .instructor-profile-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
        .instructor-avatar-lg { width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); }

        .capacity-badge { background-color: #dbeafe; color: #1e40af; font-weight: 700; font-size: 0.8rem; padding: 4px 10px; border-radius: 12px; display: inline-block; }
        .status-pill { font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 12px; display: inline-block; }
        .status-pill.open { background-color: #d1fae5; color: #065f46; }
        .status-pill.closed { background-color: #fee2e2; color: #991b1b; }

        .sessions-card, .roster-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .sessions-header, .roster-header { padding: 16px 20px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .sessions-header h3, .roster-header h3 { font-size: 1.05rem; color: var(--primary); margin: 0; display: flex; align-items: center; gap: 8px; }

        .schedule-table, .roster-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .schedule-table th, .schedule-table td, .roster-table th, .roster-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .schedule-table th, .roster-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .schedule-table tr:hover, .roster-table tr:hover { background-color: #f8fafc; }

        .student-roster-item { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .student-roster-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .student-roster-item:hover h4 { color: var(--primary-accent); text-decoration: underline; }

        .session-type-badge { font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 12px; display: inline-block; background-color: #f1f5f9; color: var(--primary); border: 1px solid var(--border-color); }
        .room-badge { background-color: #f1f5f9; color: var(--primary); font-weight: 700; font-size: 0.85rem; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color); display: inline-block; }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status.enrolled { background-color: #d1fae5; color: #065f46; }
        .badge-status.draft { background-color: #fef3c7; color: #b45309; }
        .badge-status.dropped, .badge-status.withdrawn { background-color: #fee2e2; color: #991b1b; }

        .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }

        /* Day Selector Toggle Buttons */
        .day-selector { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
        .day-btn { width: 36px; height: 36px; border-radius: 6px; border: 1px solid var(--border-color); background: #f8fafc; color: var(--text-color); font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .day-btn:hover { border-color: var(--primary); background: #e0f2fe; color: var(--primary); }
        .day-btn.active { background: var(--primary); color: #ffffff; border-color: var(--primary); box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25); }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 92%; max-width: 700px; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #f8fafc; }
        .modal-footer { padding: 14px 20px; background: #ffffff; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }

        /* Tabbed Modal Layout */
        .modal-tabs { display: flex; background: #e2e8f0; border-bottom: 1px solid var(--border-color); padding: 0 15px; }
        .modal-tab-btn { padding: 12px 18px; background: transparent; border: none; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s ease; }
        .modal-tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; }
        .modal-tab-pane { display: none; }
        .modal-tab-pane.active { display: block; }

        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Class Management View</h2>
                    <p class="text-muted">Configure class information, view instructor load, adjust weekly schedules, and manage student rosters.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="classes" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left"></i> Back to Classes
                    </a>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="schedule-container">

                <!-- 1. CLASS OVERVIEW & EDIT CARD -->
                <div class="class-info-card">
                    <div class="class-info-header">
                        <div>
                            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                                <span class="capacity-badge"><?= $currentEnrolledCount ?> / <?= htmlspecialchars($classData['MaxCapacity']) ?> Enrolled</span>
                                <span class="status-pill <?= ($classData['IsEnrollmentOpen'] == 1) ? 'open' : 'closed' ?>">
                                    <i class="fa-solid <?= ($classData['IsEnrollmentOpen'] == 1) ? 'fa-lock-open' : 'fa-lock' ?>"></i> 
                                    <?= ($classData['IsEnrollmentOpen'] == 1) ? 'Enrollment Open' : 'Enrollment Closed' ?>
                                </span>
                                <?php if ($classData['IsPetition'] == 1): ?>
                                    <span class="status-pill open" style="background: #fef3c7; color: #b45309;"><i class="fa-solid fa-file-signature"></i> Petition Class</span>
                                <?php endif; ?>
                            </div>
                            <h3 style="color: var(--primary); margin: 0 0 4px 0; font-size: 1.35rem;"><?= htmlspecialchars($classData['ClassName']) ?></h3>
                            <p class="text-muted" style="margin: 0; font-size: 0.92rem;">
                                <strong>Course:</strong> <?= htmlspecialchars($classData['SubjectCode'] ?? 'SUBJ') ?> - <?= htmlspecialchars_decode($classData['SubjectTitle'] ?? 'Subject Title') ?> (<?= htmlspecialchars($classData['Units'] ?? '3.0') ?> Units)
                            </p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openClassEditModal()">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Class Info
                            </button>
                            <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this entire class record and its schedules?');">
                                <input type="hidden" name="action" value="delete_class">
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete Class">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.88rem;">
                        <div><strong>Academic Term:</strong> <span><?= htmlspecialchars($classData['AcademicTerm'] ?? 'N/A') ?></span></div>
                        <div><strong>Year Level & Sem:</strong> <span><?= htmlspecialchars(($classData['YearLevel'] ?? '') . ' - ' . ($classData['SubSemester'] ?? '')) ?></span></div>
                        <div><strong>Class Reference ID:</strong> <span>CLS-<?= str_pad($classId, 4, '0', STR_PAD_LEFT) ?></span></div>
                    </div>
                </div>

                <!-- 2. INSTRUCTOR DISPLAY CARD -->
                <div class="instructor-profile-card">
                    <?php 
                        $instPhotoPath = !empty($classData['InstPhoto']) ? '../assets/uploads/profiles/' . htmlspecialchars($classData['InstPhoto']) : '../assets/images/default-avatar.png';
                        $instFullName = !empty($classData['InstLast']) ? htmlspecialchars("{$classData['InstFirst']} {$classData['InstMiddle']} {$classData['InstLast']} {$classData['InstExt']}") : 'Unassigned Instructor';
                    ?>
                    <img src="<?= $instPhotoPath ?>" alt="Instructor Photo" class="instructor-avatar-lg">
                    <div style="flex: 1;">
                        <span class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--primary);">Assigned Class Instructor</span>
                        <h4 style="margin: 2px 0 4px 0; color: var(--primary); font-size: 1.1rem;"><?= $instFullName ?></h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--text-muted); flex-wrap: wrap;">
                            <span><i class="fa-solid fa-id-card"></i> Emp No: <strong><?= htmlspecialchars($classData['EmployeeNumber'] ?? 'N/A') ?></strong></span>
                            <span><i class="fa-solid fa-user-tie"></i> Position: <strong><?= htmlspecialchars($classData['PositionTitle'] ?? 'Academic Staff') ?></strong></span>
                            <span><i class="fa-solid fa-phone"></i> Contact: <strong><?= htmlspecialchars($classData['ContactNumber'] ?? 'N/A') ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- 3. WEEKLY SCHEDULE SESSIONS TABLE SECTION -->
                <div class="sessions-card">
                    <div class="sessions-header">
                        <h3><i class="fa-solid fa-clock" style="color: var(--primary);"></i> Weekly Class Sessions & Room Allocations</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openSessionModal('add')">
                            <i class="fa-solid fa-plus"></i> Add Schedule Session
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Session Type</th>
                                    <th>Day(s) of Week</th>
                                    <th>Time Range</th>
                                    <th>Assigned Room</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($schedules)): ?>
                                    <?php foreach ($schedules as $sc): ?>
                                        <?php 
                                            $startTimeFmt = date('g:i A', strtotime($sc['StartTime']));
                                            $endTimeFmt = date('g:i A', strtotime($sc['EndTime']));
                                        ?>
                                        <tr>
                                            <td><span class="session-type-badge"><?= htmlspecialchars($sc['Description']) ?></span></td>
                                            <td><strong><?= htmlspecialchars($sc['DayOfWeek']) ?></strong></td>
                                            <td><?= $startTimeFmt ?> - <?= $endTimeFmt ?></td>
                                            <td><span class="room-badge"><i class="fa-solid fa-door-open"></i> <?= htmlspecialchars($sc['RoomAssignment'] ?? 'TBA') ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openSessionModal("edit", <?= json_encode($sc) ?>)' title="Edit Session">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Remove this schedule session?');" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_schedule">
                                                        <input type="hidden" name="schedule_id" value="<?= $sc['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove Session">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">No class schedules configured yet. Click "Add Schedule Session" above.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. ENROLLED STUDENTS ROSTER SECTION -->
                <div class="roster-card">
                    <div class="roster-header">
                        <h3><i class="fa-solid fa-user-graduate" style="color: var(--primary);"></i> Enrolled Student Roster</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAddStudentModal()">
                            <i class="fa-solid fa-user-plus"></i> Add Student to Class
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="roster-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID Number</th>
                                    <th>Degree Program</th>
                                    <th>Status</th>
                                    <th>Enrolled Date</th>
                                    <th>Actions / Reassign</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rosterList)): ?>
                                    <?php foreach ($rosterList as $std): ?>
                                        <?php 
                                            $stdPhoto = !empty($std['ProfilePicture']) ? '../assets/uploads/profiles/' . htmlspecialchars($std['ProfilePicture']) : '../assets/images/default-avatar.png';
                                            $stdFullName = htmlspecialchars("{$std['LastName']}, {$std['FirstName']} {$std['MiddleName']}");
                                            $statusLower = strtolower(trim($std['Status']));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="student-roster-item" onclick='openStudentInfoModal(<?= json_encode($std) ?>)'>
                                                    <img src="<?= $stdPhoto ?>" alt="Student Avatar" class="student-roster-avatar">
                                                    <div>
                                                        <h4 style="margin: 0; font-size: 0.92rem; color: var(--primary);"><?= $stdFullName ?></h4>
                                                        <span style="font-size: 0.75rem; color: var(--text-muted);">Click to view profile & schedule</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span style="color: var(--primary); font-weight: bold;"><?= htmlspecialchars($std['StudentNumber']) ?></span></td>
                                            <td><?= htmlspecialchars($std['ProgramName'] ?? 'Unspecified') ?></td>
                                            <td>
                                                <span class="badge-status <?= $statusLower ?>">
                                                    <?= htmlspecialchars($std['Status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($std['EnrolledOn'])) ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <!-- Conditional Actions Based on Enrollment Status -->
                                                    <?php if ($statusLower === 'draft'): ?>
                                                        <!-- Draft status: Can be removed entirely -->
                                                        <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Remove student draft from class?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="remove_student">
                                                            <input type="hidden" name="enrollment_id" value="<?= $std['EnrollmentID'] ?>">
                                                            <input type="hidden" name="mode" value="remove">
                                                            <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove Draft Student">
                                                                <i class="fa-solid fa-trash"></i> Remove
                                                            </button>
                                                        </form>
                                                    <?php elseif ($statusLower === 'dropped' || $statusLower === 'withdrawn'): ?>
                                                        <!-- Dropped / Withdrawn status: Can be Re-enrolled -->
                                                        <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Re-enroll this student back into the class?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="re_enroll_student">
                                                            <input type="hidden" name="enrollment_id" value="<?= $std['EnrollmentID'] ?>">
                                                            <button type="submit" class="btn btn-sm" style="background:#d1fae5; color:#065f46;" title="Re-enroll Student">
                                                                <i class="fa-solid fa-user-check"></i> Re-enroll
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Enrolled status: Can be Dropped or Withdrawn -->
                                                        <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Drop this student from the class?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="remove_student">
                                                            <input type="hidden" name="enrollment_id" value="<?= $std['EnrollmentID'] ?>">
                                                            <input type="hidden" name="mode" value="drop">
                                                            <button type="submit" class="btn btn-sm" style="background:#fef3c7; color:#b45309;" title="Drop Student">
                                                                Drop
                                                            </button>
                                                        </form>
                                                        <form action="class_view?id=<?= $classId ?>" method="POST" onsubmit="return confirm('Withdraw student from class?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="remove_student">
                                                            <input type="hidden" name="enrollment_id" value="<?= $std['EnrollmentID'] ?>">
                                                            <input type="hidden" name="mode" value="withdraw">
                                                            <button type="submit" class="btn btn-sm" style="background:#e2e8f0; color:#475569;" title="Withdraw Student">
                                                                Withdraw
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Reassign Button -->
                                                    <?php if (!empty($alternativeClasses)): ?>
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick='openReassignModal(<?= json_encode($std) ?>)' title="Reassign to another section">
                                                            <i class="fa-solid fa-right-left"></i> Reassign
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">No students enrolled in this class roster yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- MODAL: STUDENT PROFILE & SCHEDULE TABBED MODAL -->
    <div id="studentInfoModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 750px;">
            <div class="modal-header">
                <h3 id="studentModalTitle"><i class="fa-solid fa-user-graduate"></i> Student Profile & Schedule</h3>
                <button type="button" class="modal-close-btn" onclick="closeStudentInfoModal()">&times;</button>
            </div>
            <!-- Tab Navigation Header -->
            <div class="modal-tabs">
                <button type="button" class="modal-tab-btn active" onclick="switchStudentTab('profile')">
                    <i class="fa-solid fa-id-card"></i> Applicant & Student Info
                </button>
                <button type="button" class="modal-tab-btn" onclick="switchStudentTab('schedule'); loadStudentSchedule();">
                    <i class="fa-solid fa-calendar-days"></i> Class Schedule
                </button>
            </div>
            <div class="modal-body" style="min-height: 320px;">
                <!-- TAB 1: Profile Information -->
                <div id="tabPaneProfile" class="modal-tab-pane active">
                    <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px; background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <img id="modalStdPhoto" src="../assets/images/default-avatar.png" alt="Student Photo" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary);">
                        <div>
                            <h4 id="modalStdName" style="margin: 0 0 4px 0; color: var(--primary); font-size: 1.2rem;">Student Name</h4>
                            <p class="text-muted" style="margin: 0; font-size: 0.88rem;">Student Number: <strong id="modalStdNumber">---</strong></p>
                            <p class="text-muted" style="margin: 2px 0 0 0; font-size: 0.88rem;">Program: <strong id="modalStdProgram">---</strong></p>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9rem; background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div><strong>Gender:</strong> <span id="modalStdGender">---</span></div>
                        <div><strong>Birth Date:</strong> <span id="modalStdBirthDate">---</span></div>
                        <div><strong>Contact Number:</strong> <span id="modalStdContact">---</span></div>
                        <div><strong>Email Address:</strong> <span id="modalStdEmail">---</span></div>
                        <div style="grid-column: span 2;"><strong>Residential Address:</strong> <span id="modalStdAddress">---</span></div>
                    </div>
                </div>

                <!-- TAB 2: Class Schedule -->
                <div id="tabPaneSchedule" class="modal-tab-pane">
                    <div id="studentScheduleContainer">
                        <p class="text-muted" style="text-align: center; padding: 40px;">Loading student schedule...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStudentInfoModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL: REASSIGN STUDENT TO ANOTHER CLASS -->
    <div id="reassignModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-right-left"></i> Reassign Student to Section</h3>
                <button type="button" class="modal-close-btn" onclick="closeReassignModal()">&times;</button>
            </div>
            <form action="class_view?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="reassign_student">
                <input type="hidden" name="enrollment_id" id="reassignEnrollmentID">
                <input type="hidden" name="student_id" id="reassignStudentID">
                <div class="modal-body">
                    <p style="font-size: 0.9rem; margin-bottom: 15px;">
                        Reassigning student: <strong id="reassignStudentName"></strong> from current class to another section of the same subject.
                    </p>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Select Target Class Section *</label>
                        <select name="new_class_id" class="form-control" required>
                            <option value="">-- Choose Section --</option>
                            <?php foreach ($alternativeClasses as $ac): ?>
                                <option value="<?= $ac['ID'] ?>">
                                    <?= htmlspecialchars("{$ac['ClassName']} (Instructor: {$ac['InstLast']} | Capacity: {$ac['EnrolledCount']}/{$ac['MaxCapacity']})") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeReassignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Reassignment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 1: EDIT CLASS DETAILS -->
    <div id="classEditModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Class Information</h3>
                <button type="button" class="modal-close-btn" onclick="closeClassEditModal()">&times;</button>
            </div>
            <form action="class_view?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="update_class_details">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Class Name / Section *</label>
                        <input type="text" name="class_name" value="<?= htmlspecialchars($classData['ClassName']) ?>" class="form-control" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Assigned Subject *</label>
                        <select name="curriculum_subject_id" class="form-control" required>
                            <?php foreach ($subjectPool as $sp): ?>
                                <option value="<?= $sp['CurriculumSubjectID'] ?>" <?= ($sp['CurriculumSubjectID'] == $classData['CurriculumSubjectID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars_decode("{$sp['SubjectCode']} - {$sp['SubjectTitle']} ({$sp['YearLevel']}, {$sp['Semester']})") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Assigned Instructor (Academic Appointment)</label>
                        <select name="instructor_id" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($instructorPool as $ip): ?>
                                <option value="<?= $ip['StaffID'] ?>" <?= ($ip['StaffID'] == $classData['InstructorID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars("{$ip['LastName']}, {$ip['FirstName']} ({$ip['EmployeeNumber']})") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Max Student Capacity *</label>
                            <input type="number" name="max_capacity" value="<?= htmlspecialchars($classData['MaxCapacity']) ?>" class="form-control" min="5" max="150" required>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 10px; justify-content: flex-end;">
                            <label style="font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" name="is_enrollment_open" value="1" <?= ($classData['IsEnrollmentOpen'] == 1) ? 'checked' : '' ?>> Open Enrollment
                            </label>
                            <label style="font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" name="is_petition" value="1" <?= ($classData['IsPetition'] == 1) ? 'checked' : '' ?>> Petition Class
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeClassEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Details</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: ADD / EDIT SCHEDULE SESSION -->
    <div id="sessionModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="sessionModalTitle"><i class="fa-solid fa-clock"></i> Schedule Session</h3>
                <button type="button" class="modal-close-btn" onclick="closeSessionModal()">&times;</button>
            </div>
            <form action="class_view?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="schedule_id" id="modalScheduleID">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Session Description / Type *</label>
                        <input type="text" name="description" id="modalSchedDesc" class="form-control" placeholder="e.g. Lecture, Laboratory, Field Work" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px;">Day(s) of Week *</label>
                        <div class="day-selector">
                            <input type="hidden" name="day_of_week" id="modalSchedDaysInput" value="" required>
                            <button type="button" class="day-btn" data-day="A" title="Sunday" onclick="toggleDayButton(this, 'A')">A</button>
                            <button type="button" class="day-btn" data-day="M" title="Monday" onclick="toggleDayButton(this, 'M')">M</button>
                            <button type="button" class="day-btn" data-day="T" title="Tuesday" onclick="toggleDayButton(this, 'T')">T</button>
                            <button type="button" class="day-btn" data-day="W" title="Wednesday" onclick="toggleDayButton(this, 'W')">W</button>
                            <button type="button" class="day-btn" data-day="Th" title="Thursday" onclick="toggleDayButton(this, 'Th')">Th</button>
                            <button type="button" class="day-btn" data-day="F" title="Friday" onclick="toggleDayButton(this, 'F')">F</button>
                            <button type="button" class="day-btn" data-day="S" title="Saturday" onclick="toggleDayButton(this, 'S')">S</button>
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Start Time *</label>
                            <input type="time" name="start_time" id="modalSchedStart" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">End Time *</label>
                            <input type="time" name="end_time" id="modalSchedEnd" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Room Assignment</label>
                        <input type="text" name="room_assignment" id="modalSchedRoom" class="form-control" placeholder="e.g. Room 301, CIT Lab 1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSessionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Session</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: ENROLL STUDENT TO CLASS -->
    <div id="addStudentModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Enroll Student to Class</h3>
                <button type="button" class="modal-close-btn" onclick="closeAddStudentModal()">&times;</button>
            </div>
            <form action="class_view?id=<?= $classId ?>" method="POST">
                <input type="hidden" name="action" value="enroll_student">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Select Student from Database *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($availableStudents as $asp): ?>
                                <option value="<?= $asp['StudentID'] ?>">
                                    <?= htmlspecialchars("{$asp['LastName']}, {$asp['FirstName']} ({$asp['StudentNumber']}) - {$asp['ProgramCode']}") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        const classEditModal = document.getElementById('classEditModal');
        const sessionModal = document.getElementById('sessionModal');
        const addStudentModal = document.getElementById('addStudentModal');
        const studentInfoModal = document.getElementById('studentInfoModal');
        const reassignModal = document.getElementById('reassignModal');
        let currentSelectedStudentID = null;

        function openClassEditModal() { classEditModal.classList.add('active'); }
        function closeClassEditModal() { classEditModal.classList.remove('active'); }

        function openSessionModal(mode, sched = null) {
            document.querySelectorAll('#sessionModal .day-btn').forEach(btn => btn.classList.remove('active'));
            if (mode === 'edit' && sched) {
                document.getElementById('sessionModalTitle').innerHTML = `<i class="fa-solid fa-pen"></i> Edit Schedule Session`;
                document.getElementById('modalScheduleID').value = sched.ID;
                document.getElementById('modalSchedDesc').value = sched.Description || 'Lecture';
                document.getElementById('modalSchedStart').value = sched.StartTime || '';
                document.getElementById('modalSchedEnd').value = sched.EndTime || '';
                document.getElementById('modalSchedRoom').value = sched.RoomAssignment || '';
                document.getElementById('modalSchedDaysInput').value = sched.DayOfWeek || '';

                const daysStr = sched.DayOfWeek || '';
                ['A', 'M', 'T', 'W', 'Th', 'F', 'S'].forEach(d => {
                    if (daysStr.includes(d)) {
                        const b = document.querySelector(`#sessionModal .day-btn[data-day="${d}"]`);
                        if (b) b.classList.add('active');
                    }
                });
            } else {
                document.getElementById('sessionModalTitle').innerHTML = `<i class="fa-solid fa-clock"></i> Add Schedule Session`;
                document.getElementById('modalScheduleID').value = '';
                document.getElementById('modalSchedDesc').value = 'Lecture';
                document.getElementById('modalSchedStart').value = '';
                document.getElementById('modalSchedEnd').value = '';
                document.getElementById('modalSchedRoom').value = 'TBA';
                document.getElementById('modalSchedDaysInput').value = '';
            }
            sessionModal.classList.add('active');
        }
        function closeSessionModal() { sessionModal.classList.remove('active'); }

        function toggleDayButton(btn, dayLetter) {
            btn.classList.toggle('active');
            const selectorGroup = btn.closest('.day-selector');
            const order = ['A', 'M', 'T', 'W', 'Th', 'F', 'S'];
            let selected = [];
            order.forEach(d => {
                const b = selectorGroup.querySelector(`.day-btn[data-day="${d}"]`);
                if (b && b.classList.contains('active')) selected.push(d);
            });
            selectorGroup.querySelector('#modalSchedDaysInput').value = selected.join('');
        }

        function openAddStudentModal() { addStudentModal.classList.add('active'); }
        function closeAddStudentModal() { addStudentModal.classList.remove('active'); }

        function openReassignModal(std) {
            document.getElementById('reassignEnrollmentID').value = std.EnrollmentID;
            document.getElementById('reassignStudentID').value = std.StudentID;
            document.getElementById('reassignStudentName').textContent = `${std.LastName}, ${std.FirstName} (${std.StudentNumber})`;
            reassignModal.classList.add('active');
        }
        function closeReassignModal() { reassignModal.classList.remove('active'); }

        // Student Info & Schedule Tabbed Modal
        function openStudentInfoModal(std) {
            let months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            let birthday = 'N/A';
            if (std.BirthDate && std.BirthDate !== '0000-00-00') {
                const d = new Date(std.BirthDate);
                if (!isNaN(d.getTime())) {
                    birthday = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
                }
            }

            currentSelectedStudentID = std.StudentID;
            document.getElementById('modalStdName').textContent = `${std.LastName}, ${std.FirstName} ${std.MiddleName || ''}`;
            document.getElementById('modalStdNumber').textContent = std.StudentNumber || 'N/A';
            document.getElementById('modalStdProgram').textContent = std.ProgramName || 'N/A';
            document.getElementById('modalStdGender').textContent = std.Gender || 'N/A';
            document.getElementById('modalStdBirthDate').textContent = birthday || 'N/A';
            document.getElementById('modalStdContact').textContent = std.ContactNumber || 'N/A';
            document.getElementById('modalStdEmail').textContent = std.EmailAddress || 'N/A';
            document.getElementById('modalStdAddress').textContent = std.Address || 'N/A';
            
            const photoPath = std.ProfilePicture ? `../assets/uploads/profiles/${std.ProfilePicture}` : '../assets/images/default-avatar.png';
            document.getElementById('modalStdPhoto').src = photoPath;

            switchStudentTab('profile');
            studentInfoModal.classList.add('active');
        }
        function closeStudentInfoModal() { studentInfoModal.classList.remove('active'); }

        function switchStudentTab(tabName) {
            document.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.modal-tab-pane').forEach(p => p.classList.remove('active'));

            if (tabName === 'profile') {
                document.querySelector('.modal-tab-btn:nth-child(1)').classList.add('active');
                document.getElementById('tabPaneProfile').classList.add('active');
            } else {
                document.querySelector('.modal-tab-btn:nth-child(2)').classList.add('active');
                document.getElementById('tabPaneSchedule').classList.add('active');
            }
        }

        function loadStudentSchedule() {
            if (!currentSelectedStudentID) return;
            const container = document.getElementById('studentScheduleContainer');
            container.innerHTML = '<p class="text-muted" style="text-align: center; padding: 30px;">Loading student schedule...</p>';

            // Fetch student schedule via AJAX or preloaded JSON helper
            fetch(`../api/get_student_schedule?student_id=${currentSelectedStudentID}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.schedules || data.schedules.length === 0) {
                        container.innerHTML = '<p class="text-muted" style="text-align: center; padding: 30px;">No enrolled classes found for this student for the active term.</p>';
                        return;
                    }

                    let html = `
                        <table class="schedule-table" style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Class Name</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    data.schedules.forEach(s => {
                        html += `
                            <tr>
                                <td><strong>${s.SubjectCode}</strong></td>
                                <td>${s.ClassName}</td>
                                <td>${s.ScheduleStr}</td>
                                <td><span class="room-badge">${s.RoomAssignment || 'TBA'}</span></td>
                            </tr>
                        `;
                    });
                    html += `</tbody></table>`;
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = '<p class="text-danger" style="text-align: center; padding: 30px;">Failed to load schedule data.</p>';
                });
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>