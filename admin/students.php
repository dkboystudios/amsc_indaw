<?php
// Start output buffering immediately to prevent stray whitespace or warnings from breaking AJAX JSON responses
ob_start();
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Students Directory - AMSC InnDaw";

// Session Fallback for User/Admin ID
$userID = $userID ?? ($user['ID'] ?? 0);

// Get Active Academic Term & Global Enrollment Status
$aySql = "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1";
$ayRes = mysqli_query($conn, $aySql);
$currentTerm = mysqli_fetch_assoc($ayRes);

$activeTermID = $currentTerm['ID'] ?? 1;
$activeTerm = $currentTerm ? ('AY ' . $currentTerm['YearCode'] . ', ' . $currentTerm['Semester']) : 'N/A';
$enrollmentStatus = $currentTerm['EnrollmentStatus'] ?? 'Closed';
$isEnrollmentOpen = (strtolower(trim($enrollmentStatus)) === 'open');

// --- HANDLE POST ACTIONS (Shift Program, Delete Student, Send Birthday Greeting) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);

    // 1. Shift Student Program
    if ($action === 'shift_program') {
        $studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $newProgramID = filter_input(INPUT_POST, 'new_program_id', FILTER_VALIDATE_INT);

        if (!$isEnrollmentOpen) {
            redirectWithMessage('error', 'Program shifting is prohibited when Global Enrollment Status is closed.', 'students?');
        }

        if ($studentID && $newProgramID) {
            $shiftStmt = mysqli_prepare($conn, "UPDATE students SET ProgramID = ? WHERE StudentID = ?");
            mysqli_stmt_bind_param($shiftStmt, "ii", $newProgramID, $studentID);
            if (mysqli_stmt_execute($shiftStmt)) {
                logActivity($conn, $userID, 'Shift Student Program', "Shifted student ID #{$studentID} to Program ID #{$newProgramID}.");
                redirectWithMessage('success', 'Student degree program successfully updated.', 'students?');
            } else {
                redirectWithMessage('error', 'Failed to update student program.', 'students?');
            }
            mysqli_stmt_close($shiftStmt);
        }
    }

    // 2. Delete Student Record (Requires Admin Password Verification)
    if ($action === 'delete_student') {
        $studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $adminPassword = $_POST['admin_password'] ?? '';

        if (!$studentID || empty($adminPassword)) {
            redirectWithMessage('error', 'Student ID and password verification are required.', 'students?');
        }

        // Verify Admin Password (column is 'Password' in users table)
        $passStmt = mysqli_prepare($conn, "SELECT Password FROM users WHERE ID = ?");
        mysqli_stmt_bind_param($passStmt, "i", $userID);
        mysqli_stmt_execute($passStmt);
        $passRes = mysqli_stmt_get_result($passStmt);
        $adminData = mysqli_fetch_assoc($passRes);
        mysqli_stmt_close($passStmt);

        if (!$adminData || !password_verify($adminPassword, $adminData['Password'])) {
            redirectWithMessage('error', 'Authentication failed: Incorrect administrative password.', 'students?');
        }

        // Fetch Student Info & Applicant ID for Audit Logs & Cleanup
        $stInfoStmt = mysqli_prepare($conn, "SELECT s.StudentNumber, a.FirstName, a.LastName, a.ProfilePicture, a.UserID FROM students s INNER JOIN applicants a ON s.ApplicantID = a.ApplicantID WHERE s.StudentID = ?");
        mysqli_stmt_bind_param($stInfoStmt, "i", $studentID);
        mysqli_stmt_execute($stInfoStmt);
        $stInfo = mysqli_fetch_assoc(mysqli_stmt_get_result($stInfoStmt));
        mysqli_stmt_close($stInfoStmt);

        if ($stInfo) {
            mysqli_begin_transaction($conn);
            try {
                // Remove student profile picture if exists
                if (!empty($stInfo['ProfilePicture'])) {
                    $imgPath = '../assets/uploads/profiles/' . $stInfo['ProfilePicture'];
                    if (file_exists($imgPath)) unlink($imgPath);
                }

                // Delete Student Record (Cascade handles foreign keys)
                $delStStmt = mysqli_prepare($conn, "DELETE FROM students WHERE StudentID = ?");
                mysqli_stmt_bind_param($delStStmt, "i", $studentID);
                mysqli_stmt_execute($delStStmt);
                mysqli_stmt_close($delStStmt);

                if (!empty($stInfo['UserID'])) {
                    $delUserStmt = mysqli_prepare($conn, "DELETE FROM users WHERE ID = ?");
                    mysqli_stmt_bind_param($delUserStmt, "i", $stInfo['UserID']);
                    mysqli_stmt_execute($delUserStmt);
                    mysqli_stmt_close($delUserStmt);
                }

                mysqli_commit($conn);
                logActivity($conn, $userID, 'Student Deleted', "Permanently deleted student {$stInfo['FirstName']} {$stInfo['LastName']} ({$stInfo['StudentNumber']}) and associated user accounts.");
                redirectWithMessage('success', 'Student record and associated data permanently deleted.', 'students?');
            } catch (Exception $e) {
                mysqli_rollback($conn);
                redirectWithMessage('error', 'Error executing deletion: ' . $e->getMessage(), 'students?');
            }
        }
    }

    // 3. Send/Trigger Birthday Greeting to Student
    if ($action === 'send_birthday_greeting') {
        $studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        if ($studentID) {
            $stFetch = mysqli_prepare($conn, "SELECT a.FirstName, a.LastName FROM students s INNER JOIN applicants a ON s.ApplicantID = a.ApplicantID WHERE s.StudentID = ?");
            mysqli_stmt_bind_param($stFetch, "i", $studentID);
            mysqli_stmt_execute($stFetch);
            $stRes = mysqli_fetch_assoc(mysqli_stmt_get_result($stFetch));
            mysqli_stmt_close($stFetch);

            if ($stRes) {
                logActivity($conn, $userID, 'Birthday Greeting Sent', "Sent Happy Birthday greeting to student {$stRes['FirstName']} {$stRes['LastName']} (ID #{$studentID}).");
                redirectWithMessage('success', "Happy Birthday greeting successfully sent to {$stRes['FirstName']} {$stRes['LastName']}!", 'students?');
            } else {
                redirectWithMessage('error', 'Student not found for greeting.', 'students?');
            }
        }
    }
}

// --- AJAX DATA API FOR DOSSIER & HISTORICAL SCHEDULES ---
if (isset($_GET['ajax_action'])) {
    // Clear any previous output buffers so JSON isn't polluted
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $ajaxAction = $_GET['ajax_action'];

    if ($ajaxAction === 'get_student_dossier') {
        $studentID = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        $ayID = filter_input(INPUT_GET, 'academic_year_id', FILTER_VALIDATE_INT) ?: $activeTermID;

        // Fetch Student Main Profile
        $stSql = "SELECT s.*, a.*, p.ProgramCode, p.ProgramName, p.ID as ProgramID, u.Email 
                  FROM students s 
                  INNER JOIN applicants a ON s.ApplicantID = a.ApplicantID
                  INNER JOIN programs p ON s.ProgramID = p.ID 
                  LEFT JOIN users u ON a.UserID = u.ID 
                  WHERE s.StudentID = ?";
        $stStmt = mysqli_prepare($conn, $stSql);
        mysqli_stmt_bind_param($stStmt, "i", $studentID);
        mysqli_stmt_execute($stStmt);
        $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stStmt));
        mysqli_stmt_close($stStmt);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        // Fetch Total Cumulative Enrolled/Passed Units for Year Level Computation
        $cumUnitSql = "SELECT COALESCE(SUM(cs.Units), 0) as CumUnits 
                       FROM enrollments e 
                       INNER JOIN classes c ON e.ClassID = c.ID 
                       INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID 
                       WHERE e.StudentID = ? AND (e.Status = 'Enrolled' OR e.Status = 'Completed')";
        $cumStmt = mysqli_prepare($conn, $cumUnitSql);
        mysqli_stmt_bind_param($cumStmt, 'i', $studentID);
        mysqli_stmt_execute($cumStmt);
        $cumRes = mysqli_fetch_assoc(mysqli_stmt_get_result($cumStmt));
        $cumulativeUnits = floatval($cumRes['CumUnits'] ?? 0);
        mysqli_stmt_close($cumStmt);

        // Compute Year Level based on units
        $computedYearLevel = '1st Year';
        if ($cumulativeUnits > 120) {
            $computedYearLevel = '4th Year / Senior';
        } elseif ($cumulativeUnits > 80) {
            $computedYearLevel = '3rd Year';
        } elseif ($cumulativeUnits > 40) {
            $computedYearLevel = '2nd Year';
        }
        $student['ComputedYearLevel'] = $computedYearLevel;
        $student['CumulativeUnits'] = $cumulativeUnits;

        // Check if today is student's birthday
        $isStudentBirthday = false;
        if (!empty($student['BirthDate'])) {
            $today = date('m-d');
            $bday = date('m-d', strtotime($student['BirthDate']));
            if ($today === $bday) {
                $isStudentBirthday = true;
            }
        }
        $student['IsBirthdayToday'] = $isStudentBirthday;

        // Fetch Submitted Documents
        $docs = [];
        $docSql = "SELECT * FROM applicant_documents WHERE ApplicantID = ?";
        $docStmt = mysqli_prepare($conn, $docSql);
        if ($docStmt) {
            mysqli_stmt_bind_param($docStmt, "i", $student['ApplicantID']);
            mysqli_stmt_execute($docStmt);
            $docRes = mysqli_stmt_get_result($docStmt);
            while ($dRow = mysqli_fetch_assoc($docRes)) {
                $docs[] = $dRow;
            }
            mysqli_stmt_close($docStmt);
        }

        // Fetch Enrolled Classes / Schedule for Selected Academic Term
        $subjects = [];
        $totalUnits = 0;
        $subjSql = "SELECT c.ClassName, sub.SubjectCode, sub.SubjectTitle, cs.Units, 
                           GROUP_CONCAT(CONCAT(csch.DayOfWeek, ' ', TIME_FORMAT(csch.StartTime, '%h:%i %p'), ' - ', TIME_FORMAT(csch.EndTime, '%h:%i %p'), ' (', csch.RoomAssignment, ')') SEPARATOR '; ') AS ScheduleDisplay,
                           CONCAT(st.FirstName, ' ', st.LastName) AS InstructorName
                    FROM enrollments e
                    INNER JOIN classes c ON e.ClassID = c.ID
                    INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
                    INNER JOIN subjects sub ON cs.SubjectID = sub.ID
                    LEFT JOIN staff_members st ON c.InstructorID = st.StaffID
                    LEFT JOIN class_schedules csch ON c.ID = csch.ClassID
                    WHERE e.StudentID = ? AND c.AcademicYearID = ? AND e.Status = 'Enrolled'
                    GROUP BY c.ID";
        $subjStmt = mysqli_prepare($conn, $subjSql);
        if ($subjStmt) {
            mysqli_stmt_bind_param($subjStmt, "ii", $studentID, $ayID);
            mysqli_stmt_execute($subjStmt);
            $subjRes = mysqli_stmt_get_result($subjStmt);
            while ($sRow = mysqli_fetch_assoc($subjRes)) {
                $subjects[] = $sRow;
                $totalUnits += floatval($sRow['Units']);
            }
            mysqli_stmt_close($subjStmt);
        }

        echo json_encode([
            'success' => true,
            'student' => $student,
            'documents' => $docs,
            'subjects' => $subjects,
            'totalUnits' => $totalUnits
        ]);
        exit;
    }
}

// Clear any buffered output before rendering main page HTML
if (ob_get_level()) {
    ob_end_clean();
}

// --- PAGINATION, FILTERS & STUDENT LIST QUERY ---
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$programFilter = filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT);
$sortBy = trim($_GET['sort'] ?? 'name_asc');

$whereClauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $whereClauses[] = "(a.FirstName LIKE ? OR a.LastName LIKE ? OR s.StudentNumber LIKE ? OR u.Email LIKE ?)";
    $searchTerm = "%{$search}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $types .= "ssss";
}

if ($programFilter) {
    $whereClauses[] = "s.ProgramID = ?";
    $params[] = $programFilter;
    $types .= "i";
}

$whereString = implode(" AND ", $whereClauses);

// Count Total Records
$countSql = "SELECT COUNT(s.StudentID) AS total FROM students s INNER JOIN applicants a ON s.ApplicantID = a.ApplicantID LEFT JOIN users u ON a.UserID = u.ID WHERE {$whereString}";
$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($types)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$totalRecords = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0;
mysqli_stmt_close($countStmt);

$totalPages = max(1, ceil($totalRecords / $limit));

// Sort Orders
$orderBy = "a.LastName ASC, a.FirstName ASC";
if ($sortBy === 'name_desc') $orderBy = "a.LastName DESC, a.FirstName DESC";
if ($sortBy === 'id_asc') $orderBy = "s.StudentNumber ASC";
if ($sortBy === 'id_desc') $orderBy = "s.StudentNumber DESC";

// Fetch Student List with Enrolled Units for Current Term
$dataSql = "SELECT s.*, a.FirstName, a.MiddleName, a.LastName, a.ExtName, a.ContactNumber, a.ProfilePicture, a.BirthDate, p.ProgramCode, p.ProgramName, u.Email,
                   (SELECT COALESCE(SUM(cs.Units), 0) 
                    FROM enrollments e 
                    INNER JOIN classes c ON e.ClassID = c.ID 
                    INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID 
                    WHERE e.StudentID = s.StudentID AND c.AcademicYearID = {$activeTermID} AND e.Status = 'Enrolled') as EnrolledUnits
            FROM students s
            INNER JOIN applicants a ON s.ApplicantID = a.ApplicantID
            INNER JOIN programs p ON s.ProgramID = p.ID
            LEFT JOIN users u ON a.UserID = u.ID
            WHERE {$whereString}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $dataSql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$studentsList = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Compute cumulative units per student for directory view year level estimation
    $stID = $row['StudentID'];
    $cSumRes = mysqli_query($conn, "SELECT COALESCE(SUM(cs.Units), 0) as CU FROM enrollments e INNER JOIN classes c ON e.ClassID = c.ID INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID WHERE e.StudentID = $stID AND (e.Status = 'Enrolled' OR e.Status = 'Completed')");
    $cSumRow = mysqli_fetch_assoc($cSumRes);
    $totCumUnits = floatval($cSumRow['CU'] ?? 0);
    
    $yl = '1st Year';
    if ($totCumUnits > 120) $yl = '4th Year / Senior';
    elseif ($totCumUnits > 80) $yl = '3rd Year';
    elseif ($totCumUnits > 40) $yl = '2nd Year';
    
    $row['ComputedYearLevel'] = $yl;
    $row['CumulativeUnits'] = $totCumUnits;

    // Check if birthday today
    $isBday = false;
    if (!empty($row['BirthDate'])) {
        if (date('m-d') === date('m-d', strtotime($row['BirthDate']))) {
            $isBday = true;
        }
    }
    $row['IsBirthdayToday'] = $isBday;

    $studentsList[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch Programs for Shifting & Filter Dropdowns
$progRes = mysqli_query($conn, "SELECT ID, ProgramCode, ProgramName FROM programs WHERE IsActive = 1 ORDER BY ProgramCode ASC");
$degreePrograms = [];
while ($pRow = mysqli_fetch_assoc($progRes)) {
    $degreePrograms[] = $pRow;
}

// Fetch Academic Years for Modal Historical Term Selector
$ayRes = mysqli_query($conn, "SELECT ID, YearCode, Semester FROM academic_years ORDER BY ID DESC");
$academicYears = [];
while ($ayRow = mysqli_fetch_assoc($ayRes)) {
    $academicYears[] = $ayRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .students-container { display: flex; flex-direction: column; gap: 20px; }
        .filter-toolbar { display: flex; gap: 15px; background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); align-items: center; flex-wrap: wrap; }
        .filter-toolbar input, .filter-toolbar select { padding: 9px 14px; font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; }
        .filter-toolbar input { flex: 1; min-width: 250px; }
        .students-table-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .students-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .students-table th, .students-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .students-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .students-table tr:hover { background-color: #f8fafc; }
        .student-profile-cell { display: flex; align-items: center; gap: 12px; }
        .student-thumb { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); background-color: #e2e8f0; flex-shrink: 0; }
        .student-name-btn { font-weight: 600; color: var(--primary); background: none; border: none; padding: 0; cursor: pointer; text-align: left; }
        .student-name-btn:hover { text-decoration: underline; }
        .student-id-badge { font-size: 0.75rem; color: var(--primary); font-weight: bold; display: block; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 92%; max-width: 1000px; height: 90vh; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        
        .modal-profile-banner { display: flex; align-items: center; justify-content: space-between; gap: 20px; background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; flex-wrap: wrap; }
        .modal-avatar-large { width: 84px; height: 84px; border-radius: 50%; border: 3px solid var(--primary); object-fit: cover; background-color: #e2e8f0; flex-shrink: 0; }
        
        .modal-tabs { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); padding: 0 20px; overflow-x: auto; }
        .modal-tab-btn { padding: 12px 18px; background: transparent; border: none; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; white-space: nowrap; }
        .modal-tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; }
        .modal-body { flex: 1; padding: 24px; overflow-y: auto; background: #f8fafc; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .doc-review-card { background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 12px; }
        .doc-review-header { display: flex; justify-content: space-between; align-items: center; }
        .doc-review-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .doc-review-actions input { flex: 1; min-width: 200px; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; }

        .pdf-modal-card { background: #ffffff; width: 85%; max-width: 800px; height: 82vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; z-index: 2500; }
        .enrollment-alert-banner { padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .enrollment-alert-banner.open { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .enrollment-alert-banner.closed { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .current-program-badge {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Birthday Card Banner inside Modal */
        .birthday-card-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            color: #b45309;
            padding: 14px 18px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            font-weight: 600;
            display: none; /* toggled via JS */
        }
        .birthday-card-banner.active {
            display: flex;
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Enrolled Students Directory</h2>
                    <p class="text-muted">Manage all student records (regardless of enrollment status), review credentials, subjects, and academic standing.</p>
                </div>
                <div class="application-id-badge">
                    <span>Total Students:</span> <strong><?= $totalRecords ?></strong>
                </div>
            </div>

            <!-- Enrollment Status Alert Box -->
            <?php 
                $alertClass = $isEnrollmentOpen ? 'open' : 'closed';
                $statusIcon = $isEnrollmentOpen ? 'fa-circle-check' : 'fa-circle-xmark';
            ?>
            <div class="enrollment-alert-banner <?= $alertClass ?>">
                <i class="fa-solid <?= $statusIcon ?>"></i>
                <span>Global Enrollment Status for <strong><?= htmlspecialchars($activeTerm) ?></strong> is currently: <strong><?= htmlspecialchars($enrollmentStatus) ?></strong></span>
            </div>

            <div class="students-container">

                <!-- Search, Filter & Sort Toolbar -->
                <form method="GET" action="students?" class="filter-toolbar" id="filterForm">
                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, student ID, or email...">
                    
                    <select name="program_id" id="programFilter" onchange="this.form.submit()">
                        <option value="">All Degree Programs</option>
                        <?php foreach ($degreePrograms as $prog): ?>
                            <option value="<?= $prog['ID'] ?>" <?= $programFilter == $prog['ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prog['ProgramCode'] . ' - ' . $prog['ProgramName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="sort" id="sortSelect" onchange="this.form.submit()">
                        <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="id_asc" <?= $sortBy === 'id_asc' ? 'selected' : '' ?>>Student ID (Ascending)</option>
                        <option value="id_desc" <?= $sortBy === 'id_desc' ? 'selected' : '' ?>>Student ID (Descending)</option>
                    </select>
                </form>

                <!-- Students Table Card -->
                <div class="students-table-card">
                    <div style="overflow-x: auto;">
                        <table class="students-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student Name & ID</th>
                                    <th>Current Degree Program & Status</th>
                                    <th>Year Level & Units</th>
                                    <th>Contact Information</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($studentsList)): ?>
                                    <?php foreach ($studentsList as $st): 
                                        $fullName = trim("{$st['FirstName']} " . (!empty($st['MiddleName']) ? substr($st['MiddleName'], 0, 1) . '. ' : '') . "{$st['LastName']} " . ($st['ExtName'] ?? ''));
                                        $avatarImg = !empty($st['ProfilePicture']) ? '/amsc_indaw/assets/uploads/profiles/' . $st['ProfilePicture'] : $defaultProfilePhoto;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="student-profile-cell">
                                                    <img src="<?= htmlspecialchars($avatarImg) ?>" alt="Student Photo" class="student-thumb">
                                                    <div>
                                                        <button type="button" class="student-name-btn" onclick="openStudentModal(<?= $st['StudentID'] ?>)">
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </button>
                                                        <span class="student-id-badge">ID: <?= htmlspecialchars($st['StudentNumber'] ?? 'N/A') ?></span>
                                                        <?php if ($st['IsBirthdayToday']): ?>
                                                            <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; margin-top: 2px;"><i class="fa-solid fa-cake-candles"></i> Birthday Today!</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="current-program-badge"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($st['ProgramCode'] ?? 'N/A') ?></span><br>
                                                <span style="font-weight: 500; font-size: 0.85rem;"><?= htmlspecialchars($st['ProgramName'] ?? 'Unassigned') ?></span><br>
                                                <small class="text-muted">Status: <?= htmlspecialchars($st['EnrollmentStatus'] ?? 'Pre-Enrolled') ?></small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($st['ComputedYearLevel']) ?></strong><br>
                                                <small class="text-muted">Term Units: <?= floatval($st['EnrolledUnits']) ?> | Cum: <?= floatval($st['CumulativeUnits']) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($st['ContactNumber'] ?? 'N/A') ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($st['Email'] ?? 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openStudentModal(<?= $st['StudentID'] ?>)">
                                                        <i class="fa-solid fa-eye"></i> View Profile
                                                    </button>
                                                    <button type="button" class="btn btn-sm" style="background: #fee2e2; color: #dc2626;" onclick="openDeleteModal(<?= $st['StudentID'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No student records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pagination-footer" style="padding: 16px 20px; background-color: #f8fafc; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span class="text-muted" style="font-size: 0.85rem;">Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (Total: <?= $totalRecords ?> students)</span>
                        <div class="pagination-controls" style="display: flex; gap: 5px;">
                            <?php if ($page > 1): ?>
                                <a href="students?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&program_id=<?= $programFilter ?>&sort=<?= $sortBy ?>" class="btn btn-secondary btn-sm">Prev</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Prev</button>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="students?page=<?= $i ?>&search=<?= urlencode($search) ?>&program_id=<?= $programFilter ?>&sort=<?= $sortBy ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="students?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&program_id=<?= $programFilter ?>&sort=<?= $sortBy ?>" class="btn btn-secondary btn-sm">Next</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Next</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- STUDENT REVIEW & RECORD MODAL -->
    <div id="studentModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalStudentTitle"><i class="fa-solid fa-user-graduate"></i> Enrolled Student Record</h3>
                <button type="button" class="modal-close-btn" onclick="closeStudentModal()">&times;</button>
            </div>

            <!-- Modal Tabs -->
            <div class="modal-tabs">
                <button type="button" class="modal-tab-btn active" onclick="switchTab('profile')">Profile Details</button>
                <button type="button" class="modal-tab-btn" onclick="switchTab('documents')">Submitted Documents</button>
                <button type="button" class="modal-tab-btn" onclick="switchTab('subjects')">Enrolled Subjects & Schedule</button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="modalCurrentStudentID" value="">

                <!-- Birthday Greeting Card / Banner inside Modal (Appears if student's birthday matches today) -->
                <div id="birthdayCardBanner" class="birthday-card-banner">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fa-solid fa-cake-candles" style="font-size: 1.6rem; color: #d97706;"></i>
                        <div>
                            <span style="display: block; font-size: 1rem;">🎉 It's <span id="bdayStudentName">this student</span>'s Birthday Today! 🎂</span>
                            <span style="font-weight: normal; font-size: 0.85rem; color: #92400e;">Send warm birthday wishes on behalf of AMSC InnDaw.</span>
                        </div>
                    </div>
                    <form action="students?" method="POST">
                        <input type="hidden" name="action" value="send_birthday_greeting">
                        <input type="hidden" name="student_id" id="bdayActionStudentID" value="">
                        <button type="submit" class="btn btn-sm" style="background: #d97706; color: #ffffff; font-weight: bold;">
                            <i class="fa-solid fa-paper-plane"></i> Send Happy Birthday Greeting
                        </button>
                    </form>
                </div>
                
                <!-- Prominent Profile Banner -->
                <div class="modal-profile-banner">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <img id="detAvatar" src="<?= $defaultProfilePhoto ?>" alt="Student Photo" class="modal-avatar-large">
                        <div>
                            <h3 id="detName" style="margin: 0 0 4px 0; color: var(--primary);">Loading...</h3>
                            <p class="text-muted" style="margin: 0; font-size: 0.9rem;">Student Number: <strong id="detStudentId" style="color: var(--primary);">---</strong></p>
                            <p class="text-muted" style="margin: 2px 0 0 0; font-size: 0.85rem;">Current Degree Program: <span id="detProgram" style="font-weight: 600; color: var(--text-color);">---</span> (<span id="detEnrollmentStatus">---</span>)</p>
                            <p class="text-muted" style="margin: 2px 0 0 0; font-size: 0.85rem;">Computed Year Level: <strong id="detYearLevel" style="color: var(--primary);">---</strong> (Cum. Units: <span id="detCumUnits">---</span>)</p>
                        </div>
                    </div>

                    <!-- Program Shifting Box -->
                    <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-color); text-align: right;">
                        <label style="font-size: 0.8rem; font-weight: bold; display: block; margin-bottom: 4px; color: var(--primary);">Shift Degree Program:</label>
                        <?php if ($isEnrollmentOpen): ?>
                            <form action="students?" method="POST" style="display: flex; gap: 6px;" onsubmit="return confirm('Are you sure you want to shift this student to the selected program?');">
                                <input type="hidden" name="action" value="shift_program">
                                <input type="hidden" name="student_id" id="shiftStudentID" value="">
                                <select name="new_program_id" id="shiftProgramSelect" class="form-control" style="font-size: 0.85rem; padding: 4px 8px;" required>
                                    <?php foreach ($degreePrograms as $dp): ?>
                                        <option value="<?= $dp['ID'] ?>"><?= htmlspecialchars($dp['ProgramCode']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Shift</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 0.8rem;"><i class="fa-solid fa-lock"></i> Locked (Enrollment Closed)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab 1: Profile Details -->
                <div id="tabProfile" class="tab-pane active">
                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; color: var(--primary);">Official Student Record & Academic Standing</h4>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.95rem;">
                            <div><strong>Gender:</strong> <span id="detGender">---</span></div>
                            <div><strong>Birth Date:</strong> <span id="detBirthDate">---</span></div>
                            <div><strong>Contact Number:</strong> <span id="detContact">---</span></div>
                            <div><strong>Email Address:</strong> <span id="detEmail">---</span></div>
                            <div style="grid-column: span 2;"><strong>Address:</strong> <span id="detAddress">---</span></div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Submitted Documents -->
                <div id="tabDocuments" class="tab-pane">
                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; color: var(--primary);">Submitted Admission Documents</h4>
                        <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 15px;">Credentials uploaded during applicant registration.</p>
                        <div id="documentsListContainer">
                            <!-- Populated dynamically via AJAX -->
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Enrolled Subjects & Schedule -->
                <div id="tabSubjects" class="tab-pane">
                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h4 style="margin: 0; color: var(--primary);">Class Schedule & Subjects</h4>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <label style="font-size: 0.85rem; font-weight: bold;">Academic Term:</label>
                                <select id="dossierTermSelect" class="form-control" style="font-size: 0.85rem; padding: 4px 10px;" onchange="loadStudentDossierData(document.getElementById('modalCurrentStudentID').value)">
                                    <?php foreach ($academicYears as $ay): ?>
                                        <option value="<?= $ay['ID'] ?>" <?= $ay['ID'] == $activeTermID ? 'selected' : '' ?>>
                                            AY <?= htmlspecialchars($ay['YearCode']) ?>, <?= htmlspecialchars($ay['Semester']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Descriptive Title</th>
                                    <th>Units</th>
                                    <th>Schedule</th>
                                    <th>Instructor</th>
                                </tr>
                            </thead>
                            <tbody id="subjectsTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" style="text-align: right; font-weight: bold;">Total Term Units:</td>
                                    <td colspan="3" style="font-weight: bold; color: var(--primary);"><span id="totalUnitsSpan">0</span> Units</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ADMIN CONFIRMATION DELETE MODAL -->
    <div id="deleteStudentModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 450px; height: auto;">
            <div class="modal-header" style="background: #dc2626;">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirm Delete Student</h3>
                <button type="button" class="modal-close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body" style="background: #ffffff; padding: 20px;">
                <form action="students?" method="POST">
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="student_id" id="deleteStudentID" value="">

                    <p style="font-size: 0.9rem; color: var(--text-color); margin-top: 0;">
                        Are you sure you want to permanently delete <strong id="deleteStudentName"></strong> and all associated academic records?
                    </p>

                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: bold; display: block; margin-bottom: 6px;">
                            Enter Admin Password to Confirm:
                        </label>
                        <input type="password" name="admin_password" class="form-control" required placeholder="Your login password">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn btn-sm" style="background: #dc2626; color: #ffffff;">Confirm & Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NESTED PDF VIEWER MODAL -->
    <div id="pdfViewerModal" class="modal-overlay" style="z-index: 2500;">
        <div class="pdf-modal-card">
            <div class="modal-header">
                <h3 id="pdfModalTitle"><i class="fa-solid fa-file-pdf"></i> Document Viewer</h3>
                <button type="button" class="modal-close-btn" onclick="closePdfModal()">&times;</button>
            </div>
            <div style="flex: 1; background: #e2e8f0; display: flex; align-items: center; justify-content: center; position: relative;">
                <iframe id="pdfViewerIframe" src="" style="width: 100%; height: 100%; border: none;" title="PDF Viewer"></iframe>
            </div>
            <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 0.85rem;">AMSC InnDaw Secure Admin Document Preview</span>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-accent btn-sm" onclick="printPdfDocument()">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <a id="downloadPdfLink" href="#" download class="btn btn-primary btn-sm" style="text-decoration: none;">
                        <i class="fa-solid fa-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- INTERACTIVE IMAGE VIEWER MODAL (Zoom, Pan/Drag, Fullscreen) -->
    <div id="imageViewerModal" class="modal-overlay" style="z-index: 2500;">
        <div class="modal-card" style="max-width: 900px; height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <div class="modal-header">
                <h3 id="imageModalTitle"><i class="fa-solid fa-image"></i> Image Viewer</h3>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn btn-sm btn-primary" style="color: white; border-color: rgba(255,255,255,0.4);" onclick="zoomImage(0.2)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                    <button type="button" class="btn btn-sm btn-primary" style="color: white; border-color: rgba(255,255,255,0.4);" onclick="zoomImage(-0.2)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                    <button type="button" class="btn btn-sm btn-primary" style="color: white; border-color: rgba(255,255,255,0.4);" onclick="resetImageTransform()" title="Reset"><i class="fa-solid fa-rotate-left"></i></button>
                    <button type="button" class="btn btn-sm btn-primary" style="color: white; border-color: rgba(255,255,255,0.4);" onclick="toggleImageFullScreen()" title="Full Screen"><i class="fa-solid fa-expand"></i></button>
                    <button type="button" class="modal-close-btn" onclick="closeImageViewerModal()">&times;</button>
                </div>
            </div>
            <div id="imageContainer" style="flex: 1; background: #1e293b; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; cursor: grab; user-select: none;">
                <img id="pannableImage" src="" alt="Document Image" style="transition: transform 0.05s ease-out; transform-origin: center center; max-width: 100%; max-height: 100%; object-fit: contain;" draggable="false">
            </div>
            <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 0.85rem;">Use mouse wheel to zoom, click and drag to pan image.</span>
                <a id="downloadImageLink" href="#" download class="btn btn-primary btn-sm" style="text-decoration: none;">
                    <i class="fa-solid fa-download"></i> Download Image
                </a>
            </div>
        </div>
    </div>

    <?php include "../includes/snackbar.php"; ?>

    <script>
        const defaultAvatar = '<?= $defaultProfilePhoto ?>';

        function openStudentModal(studentId) {
            document.getElementById('modalCurrentStudentID').value = studentId;
            document.getElementById('shiftStudentID').value = studentId;
            document.getElementById('bdayActionStudentID').value = studentId;
            loadStudentDossierData(studentId);
            document.getElementById('studentModal').classList.add('active');
        }

        function closeStudentModal() {
            document.getElementById('studentModal').classList.remove('active');
        }

        function openDeleteModal(studentId, studentName) {
            document.getElementById('deleteStudentID').value = studentId;
            document.getElementById('deleteStudentName').textContent = studentName;
            document.getElementById('deleteStudentModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteStudentModal').classList.remove('active');
        }

        function loadStudentDossierData(studentId) {
            if (!studentId) return;
            const termID = document.getElementById('dossierTermSelect').value;
            fetch(`students?ajax_action=get_student_dossier&student_id=${studentId}&academic_year_id=${termID}`)
                .then(async res => {
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Non-JSON response received from server:", text);
                        throw new Error("Invalid JSON response from server.");
                    }
                })
                .then(data => {
                    if (data && data.success) {
                        const st = data.student;
                        const fullName = `${st.FirstName} ${st.MiddleName ? st.MiddleName + ' ' : ''}${st.LastName} ${st.ExtName || ''}`;
                        
                        document.getElementById('modalStudentTitle').innerHTML = `<i class="fa-solid fa-user-graduate"></i> Student Record: ${escapeHtml(fullName)}`;
                        document.getElementById('detName').textContent = fullName;
                        document.getElementById('detStudentId').textContent = st.StudentNumber || 'N/A';
                        document.getElementById('detProgram').textContent = st.ProgramName ? `${st.ProgramName} (${st.ProgramCode})` : 'Unassigned';
                        document.getElementById('detEnrollmentStatus').textContent = st.EnrollmentStatus || 'Pre-Enrolled';
                        document.getElementById('detYearLevel').textContent = st.ComputedYearLevel || '1st Year';
                        document.getElementById('detCumUnits').textContent = parseFloat(st.CumulativeUnits || 0).toFixed(1);
                        document.getElementById('detGender').textContent = st.Gender || 'N/A';
                        document.getElementById('detBirthDate').textContent = st.BirthDate || 'N/A';
                        document.getElementById('detContact').textContent = st.ContactNumber || 'N/A';
                        document.getElementById('detEmail').textContent = st.Email || 'N/A';
                        document.getElementById('detAddress').textContent = st.Address || 'N/A';
                        document.getElementById('detAvatar').src = st.ProfilePicture ? `/amsc_indaw/assets/uploads/profiles/${st.ProfilePicture}` : defaultAvatar;

                        // Check & Toggle Birthday Banner Card in Modal
                        const bdayBanner = document.getElementById('birthdayCardBanner');
                        if (st.IsBirthdayToday) {
                            document.getElementById('bdayStudentName').textContent = st.FirstName;
                            bdayBanner.classList.add('active');
                        } else {
                            bdayBanner.classList.remove('active');
                        }
                        
                        const shiftSelect = document.getElementById('shiftProgramSelect');
                        if (shiftSelect) shiftSelect.value = st.ProgramID || '';

                        // Render Documents (Supports both PDF and Image types with Zoom/Pan/Fullscreen)
                        const docContainer = document.getElementById('documentsListContainer');
                        if (data.documents && data.documents.length > 0) {
                            let docHtml = '';
                            data.documents.forEach(d => {
                                const fileUrl = `/amsc_indaw/assets/uploads/documents/${d.FilePath}`;
                                const fileExt = d.FilePath.split('.').pop().toLowerCase();
                                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(fileExt);
                                
                                const viewAction = isImage 
                                    ? `openImageViewerModal('${escapeHtml(d.DocumentType)}', '${fileUrl}')`
                                    : `openPdfModal('${escapeHtml(d.DocumentType)}', '${fileUrl}')`;
                                
                                const iconClass = isImage ? 'fa-file-image' : 'fa-file-pdf';
                                const iconColor = isImage ? 'color: #3b82f6;' : 'color: #ef4444;';
                                const btnText = isImage ? 'View Image' : 'View PDF';

                                docHtml += `
                                    <div class="doc-review-card">
                                        <div class="doc-review-header">
                                            <div>
                                                <strong>${escapeHtml(d.DocumentType)}</strong>
                                                <span class="text-muted" style="display: block; font-size: 0.8rem;"><i class="fa-solid ${iconClass}" style="${iconColor}"></i> ${escapeHtml(d.FilePath)}</span>
                                            </div>
                                            <button type="button" class="btn btn-accent btn-sm" onclick="${viewAction}">
                                                <i class="fa-solid fa-eye"></i> ${btnText}
                                            </button>
                                        </div>
                                        <div class="doc-review-actions">
                                            <input type="text" value="${escapeHtml(d.Remarks || 'No remarks')}" readonly style="background: #f8fafc;">
                                            <span style="background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: bold;">${escapeHtml(d.VerificationStatus || 'Pending')}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            docContainer.innerHTML = docHtml;
                        } else {
                            docContainer.innerHTML = `<p class="text-muted" style="text-align: center; padding: 15px;">No uploaded documents recorded for this student.</p>`;
                        }

                        // Render Enrolled Subjects
                        const subjBody = document.getElementById('subjectsTableBody');
                        if (data.subjects && data.subjects.length > 0) {
                            let subjHtml = '';
                            data.subjects.forEach(s => {
                                subjHtml += `
                                    <tr>
                                        <td><strong>${escapeHtml(s.SubjectCode)}</strong></td>
                                        <td>${escapeHtml(s.SubjectTitle)}</td>
                                        <td>${s.Units}</td>
                                        <td>${escapeHtml(s.ScheduleDisplay || 'TBA')}</td>
                                        <td>${escapeHtml(s.InstructorName || 'TBA')}</td>
                                    </tr>
                                `;
                            });
                            subjBody.innerHTML = subjHtml;
                            document.getElementById('totalUnitsSpan').textContent = parseFloat(data.totalUnits).toFixed(1);
                        } else {
                            subjBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No enrolled subjects found for selected term.</td></tr>`;
                            document.getElementById('totalUnitsSpan').textContent = '0';
                        }
                    } else {
                        console.error("API Error message:", data ? data.message : "Unknown error");
                    }
                })
                .catch(err => console.error("Error loading student dossier:", err));
        }

        function switchTab(tabName) {
            document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            const tabMap = { 'profile': 0, 'documents': 1, 'subjects': 2 };
            const index = tabMap[tabName];
            if (index !== undefined) {
                document.querySelectorAll('.modal-tab-btn')[index].classList.add('active');
                document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).classList.add('active');
            }
        }

        // PDF Modal Functions
        function openPdfModal(title, url) {
            document.getElementById('pdfModalTitle').innerHTML = `<i class="fa-solid fa-file-pdf"></i> ${title}`;
            document.getElementById('pdfViewerIframe').src = url;
            document.getElementById('downloadPdfLink').href = url;
            document.getElementById('pdfViewerModal').classList.add('active');
        }

        function closePdfModal() {
            document.getElementById('pdfViewerModal').classList.remove('active');
            document.getElementById('pdfViewerIframe').src = '';
        }

        function printPdfDocument() {
            const iframe = document.getElementById('pdfViewerIframe');
            if (iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        }

        // Image Viewer State & Functions (Zoom, Pan, Fullscreen)
        let imgScale = 1;
        let imgPosX = 0;
        let imgPosY = 0;
        let isDraggingImage = false;
        let startX = 0, startY = 0;

        const imgContainer = document.getElementById('imageContainer');
        const pannableImg = document.getElementById('pannableImage');

        function openImageViewerModal(title, url) {
            document.getElementById('imageModalTitle').innerHTML = `<i class="fa-solid fa-image"></i> ${title}`;
            pannableImg.src = url;
            document.getElementById('downloadImageLink').href = url;
            resetImageTransform();
            document.getElementById('imageViewerModal').classList.add('active');
        }

        function closeImageViewerModal() {
            document.getElementById('imageViewerModal').classList.remove('active');
            pannableImg.src = '';
        }

        function resetImageTransform() {
            imgScale = 1;
            imgPosX = 0;
            imgPosY = 0;
            updateImageTransform();
        }

        function zoomImage(delta) {
            imgScale = Math.max(0.2, Math.min(5, imgScale + delta));
            updateImageTransform();
        }

        function updateImageTransform() {
            pannableImg.style.transform = `translate(${imgPosX}px, ${imgPosY}px) scale(${imgScale})`;
        }

        // Mouse Drag / Pan Event Listeners
        imgContainer.addEventListener('mousedown', (e) => {
            isDraggingImage = true;
            startX = e.clientX - imgPosX;
            startY = e.clientY - imgPosY;
            imgContainer.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDraggingImage) return;
            imgPosX = e.clientX - startX;
            imgPosY = e.clientY - startY;
            updateImageTransform();
        });

        window.addEventListener('mouseup', () => {
            isDraggingImage = false;
            imgContainer.style.cursor = 'grab';
        });

        // Mouse Wheel Zoom
        imgContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomIntensity = 0.1;
            if (e.deltaY < 0) {
                imgScale = Math.min(5, imgScale + zoomIntensity);
            } else {
                imgScale = Math.max(0.2, imgScale - zoomIntensity);
            }
            updateImageTransform();
        }, { passive: false });

        function toggleImageFullScreen() {
            const card = document.querySelector('#imageViewerModal .modal-card');
            if (!document.fullscreenElement) {
                if (card.requestFullscreen) {
                    card.requestFullscreen();
                } else if (card.webkitRequestFullscreen) {
                    card.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": 'F&#039;' }; // Safe fallback
            return text.toString().replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>