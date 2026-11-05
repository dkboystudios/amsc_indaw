<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Subject Enrollment & Schedule - AMSC InDaw";

// 1. Fetch Active Academic Term & Enrollment Status
$termQuery = mysqli_query($conn, "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1");
$currentTerm = mysqli_fetch_assoc($termQuery);

$activeTermName = $currentTerm ? ('AY ' . $currentTerm['YearCode'] . ', ' . $currentTerm['Semester']) : 'No Active Term';
$activeSemester = $currentTerm['Semester'] ?? '';
$activeYearID = $currentTerm['ID'] ?? 0;
$enrollmentStatus = $currentTerm['EnrollmentStatus'] ?? 'Closed';
$isEnrollmentGloballyOpen = ($currentTerm && strtolower(trim($enrollmentStatus)) === 'open');

// 2. Fetch Student Information Fallback Check
$studentInfo = null;
if (isset($userInfo['ApplicantID'])) {
    $sQuery = mysqli_prepare($conn, "SELECT s.*, p.ProgramName, c.CurriculumName FROM students s JOIN programs p ON s.ProgramID = p.ID JOIN curriculum c ON s.CurriculumID = c.ID WHERE s.ApplicantID = ? LIMIT 1");
    mysqli_stmt_bind_param($sQuery, 'i', $userInfo['ApplicantID']);
    mysqli_stmt_execute($sQuery);
    $sRes = mysqli_stmt_get_result($sQuery);
    if ($sRow = mysqli_fetch_assoc($sRes)) {
        $studentInfo = $sRow;
    }
    mysqli_stmt_close($sQuery);
}

// -------------------------------------------------------------------------
// HANDLE FORM SUBMISSIONS (SAVE DRAFT / CONFIRM ENROLLMENT)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $selectedClasses = $_POST['classes'] ?? [];

    try {
        if (!$currentTerm || !$isEnrollmentGloballyOpen) {
            throw new Exception("Enrollment for the current term is currently closed. No enrollment is allowed.");
        }

        if (!$studentInfo) {
            throw new Exception("Student record not found. Please contact the registrar.");
        }

        if (empty($selectedClasses)) {
            throw new Exception("Please select at least one class to enroll.");
        }

        $studentID = $studentInfo['StudentID'];
        $enrollStatus = ($action === 'confirm_enrollment') ? 'Enrolled' : 'Draft';
        $studentEnrollmentStatus = ($action === 'confirm_enrollment') ? 'Enrolled' : 'Pre-Enrolled';

        // Validate Units and Capacity
        $totalUnits = 0;
        foreach ($selectedClasses as $classID) {
            $cStmt = mysqli_prepare($conn, "
                SELECT cl.*, cs.Units, s.SubjectCode, s.SubjectTitle,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.ClassID = cl.ID AND e.Status IN ('Draft', 'Enrolled')) as EnrolledCount
                FROM classes cl
                JOIN curriculum_subjects cs ON cl.CurriculumSubjectID = cs.ID
                JOIN subjects s ON cs.SubjectID = s.ID
                WHERE cl.ID = ? AND cl.AcademicYearID = ?
            ");
            mysqli_stmt_bind_param($cStmt, 'ii', $classID, $activeYearID);
            mysqli_stmt_execute($cStmt);
            $cRes = mysqli_stmt_get_result($cStmt);
            $classRow = mysqli_fetch_assoc($cRes);
            mysqli_stmt_close($cStmt);

            if (!$classRow) {
                throw new Exception("Invalid class selection.");
            }

            if ($classRow['IsEnrollmentOpen'] == 0) {
                throw new Exception("Class {$classRow['ClassName']} is locked and cannot be enrolled.");
            }

            if ($classRow['EnrolledCount'] >= $classRow['MaxCapacity']) {
                throw new Exception("Class {$classRow['ClassName']} has reached its maximum capacity.");
            }

            $totalUnits += floatval($classRow['Units']);
        }

        if ($totalUnits > 27) {
            throw new Exception("Total units ({$totalUnits}) exceed the maximum allowable limit of 27 units.");
        }

        // Fetch all schedules for selected classes to perform overlap conflict checks
        $schedulesToCheck = [];
        foreach ($selectedClasses as $classID) {
            $schStmt = mysqli_prepare($conn, "SELECT cs.*, cl.ClassName FROM class_schedules cs JOIN classes cl ON cs.ClassID = cl.ID WHERE cs.ClassID = ?");
            mysqli_stmt_bind_param($schStmt, 'i', $classID);
            mysqli_stmt_execute($schStmt);
            $schRes = mysqli_stmt_get_result($schStmt);
            while ($sch = mysqli_fetch_assoc($schRes)) {
                $schedulesToCheck[] = $sch;
            }
            mysqli_stmt_close($schStmt);
        }

        // Helper function to expand day tokens
        function expandDays($dayStr) {
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

        // Improved Conflict Check Algorithm (Time Overlap Check)
        for ($i = 0; $i < count($schedulesToCheck); $i++) {
            for ($j = $i + 1; $j < count($schedulesToCheck); $j++) {
                $s1 = $schedulesToCheck[$i];
                $s2 = $schedulesToCheck[$j];

                $days1 = expandDays($s1['DayOfWeek']);
                $days2 = expandDays($s2['DayOfWeek']);
                $commonDays = array_intersect($days1, $days2);

                if (!empty($commonDays)) {
                    if ($s1['StartTime'] < $s2['EndTime'] && $s2['StartTime'] < $s1['EndTime']) {
                        throw new Exception("Schedule conflict detected between '{$s1['ClassName']}' and '{$s2['ClassName']}' on overlapping days and time intervals.");
                    }
                }
            }
        }

        // Save to Database
        mysqli_query($conn, "DELETE FROM enrollments WHERE StudentID = $studentID AND ClassID IN (SELECT ID FROM classes WHERE AcademicYearID = $activeYearID)");

        foreach ($selectedClasses as $classID) {
            $insStmt = mysqli_prepare($conn, "INSERT INTO enrollments (StudentID, ClassID, Status, EnrolledOn) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insStmt, 'iis', $studentID, $classID, $enrollStatus);
            mysqli_stmt_execute($insStmt);
            mysqli_stmt_close($insStmt);
        }

        $updStudent = mysqli_prepare($conn, "UPDATE students SET EnrollmentStatus = ? WHERE StudentID = ?");
        mysqli_stmt_bind_param($updStudent, 'si', $studentEnrollmentStatus, $studentID);
        mysqli_stmt_execute($updStudent);
        mysqli_stmt_close($updStudent);

        $logTitle = ($action === 'confirm_enrollment') ? 'Enrollment Confirmed' : 'Enrollment Draft Saved';
        logActivity($conn, $userID, $logTitle, "Student successfully updated enrollment status to {$studentEnrollmentStatus}.");
        redirectWithMessage('success', "Enrollment successfully saved as {$studentEnrollmentStatus}!", 'enrollment?');

    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), 'enrollment?');
    }
}

// 3. Fetch Offered Classes for Current Semester (Restricted to No Prerequisites for New Students)
$offeredClasses = [];
$schedulesByClass = [];
if ($studentInfo && $currentTerm) {
    $currID = $studentInfo['CurriculumID'];
    $classSql = "
        SELECT cl.*, cs.YearLevel, cs.Semester, cs.Units, s.SubjectCode, s.SubjectTitle,
               st.StaffID, st.FirstName as InstFirst, st.LastName as InstLast, st.ProfilePicture as InstPic, st.PositionTitle as InstPos,
               (SELECT COUNT(*) FROM enrollments e WHERE e.ClassID = cl.ID AND e.Status IN ('Draft', 'Enrolled')) as EnrolledCount
        FROM classes cl
        JOIN curriculum_subjects cs ON cl.CurriculumSubjectID = cs.ID
        JOIN subjects s ON cs.SubjectID = s.ID
        LEFT JOIN staff_members st ON cl.InstructorID = st.StaffID
        WHERE cl.AcademicYearID = ? 
          AND cs.CurriculumID = ? 
          AND (cs.Semester = ? OR cl.IsPetition = 1)
          AND cs.ID NOT IN (SELECT SubjectID FROM subject_prerequisites)
    ";
    $stmt = mysqli_prepare($conn, $classSql);
    mysqli_stmt_bind_param($stmt, 'iis', $activeYearID, $currID, $activeSemester);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $offeredClasses[] = $row;
    }
    mysqli_stmt_close($stmt);

    if (!empty($offeredClasses)) {
        $classIDs = array_column($offeredClasses, 'ID');
        $idsIn = implode(',', $classIDs);
        $schedRes = mysqli_query($conn, "SELECT * FROM class_schedules WHERE ClassID IN ($idsIn)");
        while ($sRow = mysqli_fetch_assoc($schedRes)) {
            $schedulesByClass[$sRow['ClassID']][] = $sRow;
        }
    }
}

$enrolledClassIDs = [];
if ($studentInfo) {
    $enRes = mysqli_query($conn, "SELECT ClassID FROM enrollments WHERE StudentID = " . $studentInfo['StudentID'] . " AND Status IN ('Draft', 'Enrolled')");
    while ($enRow = mysqli_fetch_assoc($enRes)) {
        $enrolledClassIDs[] = intval($enRow['ClassID']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .enrollment-container { display: grid; grid-template-columns: 1.4fr 1fr; gap: 24px; margin-bottom: 30px; }
        @media (max-width: 992px) { .enrollment-container { grid-template-columns: 1fr; } }
        .panel-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; height: 620px; }
        .panel-header { padding: 16px 20px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h3 { font-size: 1.05rem; color: var(--primary); margin: 0; display: flex; align-items: center; gap: 8px; }
        .panel-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 15px; }
        .toolbar input, .toolbar select { padding: 8px 12px; font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; flex: 1; }
        .subject-item { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; transition: all 0.15s ease; }
        .subject-item.locked { background: #f1f5f9; opacity: 0.8; border-color: #cbd5e1; }
        .subject-info h4 { font-size: 0.95rem; color: var(--primary); margin: 0 0 4px 0; }
        .subject-info p { font-size: 0.8rem; color: var(--text-muted); margin: 2px 0; }
        .instructor-tag { display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; font-size: 0.8rem; background: #e2e8f0; padding: 2px 8px; border-radius: 12px; }
        .instructor-tag img { width: 18px; height: 18px; border-radius: 50%; object-fit: cover; }
        .subject-meta { text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
        .unit-badge { background-color: #d1fae5; color: #065f46; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
        .locked-badge { background-color: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
        .slots-badge { background-color: #e0f2fe; color: #0369a1; font-weight: 600; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
        .add-btn { background: var(--primary); color: #fff; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        .add-btn:hover { background: var(--primary-hover); }
        .empty-cart { text-align: center; color: var(--text-muted); padding: 40px 20px; font-size: 0.9rem; }
        
        /* Enrolled Cart Items */
        .enrolled-item { background: #ffffff; border: 1px solid #cbd5e1; border-left: 4px solid #1b4332; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease; }
        .enrolled-item.conflict { background-color: #fff5f5; border-color: #fecaca; border-left-color: #dc2626; }
        .conflict-text { color: #dc2626; font-size: 0.75rem; font-weight: bold; margin-top: 3px; display: flex; align-items: center; gap: 4px; }

        .remove-btn { background: #fee2e2; color: #dc2626; border: none; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        .panel-footer { padding: 16px 20px; background-color: #f8fafc; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .units-summary { font-size: 0.9rem; font-weight: 600; color: var(--text-color); }
        .units-summary span { color: var(--primary); font-weight: 700; }
        
        /* Schedule Matrix Styles */
        .schedule-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 40px; overflow: hidden; }
        .schedule-table-wrapper { overflow-x: auto; }
        .schedule-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 0.8rem; min-width: 1200px; }
        .schedule-table th, .schedule-table td { border: 1px solid var(--border-color); padding: 8px 4px; height: 50px; }
        .schedule-table th { background-color: var(--primary); color: #ffffff; font-weight: 600; }
        .schedule-table td.day-col { background-color: #f8fafc; font-weight: 700; color: var(--primary); width: 100px; text-align: center; }
        .sched-block { background-color: #d1fae5; border-left: 3px solid var(--primary); padding: 6px; border-radius: 4px; text-align: left; font-size: 0.75rem; height: 100%; box-sizing: border-box; }
        .sched-block.conflict { background-color: #fee2e2; border-left-color: #dc2626; }
        .sched-block strong { color: var(--primary); display: block; }
        .fallback-banner { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: center; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Subject Enrollment & Schedule</h2>
                    <p class="text-muted">Active Term: <strong><?= htmlspecialchars($activeTermName) ?></strong> (New Student Catalog: Prerequisite-Free Subjects) | Status: <span class="badge <?= $isEnrollmentGloballyOpen ? 'badge-success' : 'badge-danger' ?>" style="<?= $isEnrollmentGloballyOpen ? 'color: white; background-color: #75c79f;' : 'color: white; background-color: red' ?>"><?= htmlspecialchars($enrollmentStatus) ?></span></p>
                </div>
                <div class="application-id-badge">
                    <span>Student No:</span> <strong><?= htmlspecialchars($studentInfo['StudentNumber'] ?? 'NOT YET REGISTERED') ?></strong>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <?php if (!$studentInfo): ?>
                <div class="fallback-banner">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    <h3>Student Record Not Found</h3>
                    <p>Your student profile has not been initialized or approved for enrollment yet. Please complete your registration and document submission with the registrar.</p>
                </div>
            <?php elseif (!$currentTerm || !$isEnrollmentGloballyOpen): ?>
                <div class="fallback-banner">
                    <i class="fa-solid fa-lock" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    <h3>Enrollment is Currently Closed</h3>
                    <p>There is no active enrollment period for the current term (<?= htmlspecialchars($activeTermName) ?>). Enrollment actions are disabled.</p>
                </div>
            <?php endif; ?>

            <form id="enrollmentForm" method="POST" action="enrollment">
                <input type="hidden" name="action" id="formAction" value="save_draft">

                <div class="enrollment-container">
                    <!-- LEFT PANEL: Offered Classes Catalog -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3><i class="fa-solid fa-book-open" style="color: var(--primary-accent);"></i> Offered Classes Catalog</h3>
                            <span class="text-muted" style="font-size: 0.8rem;">Click [+] to add class</span>
                        </div>
                        <div class="panel-body">
                            <div class="toolbar">
                                <input type="text" id="searchInput" placeholder="Search by class name, course code or title...">
                                <select id="sortSelect">
                                    <option value="code">Sort by Code</option>
                                    <option value="units">Sort by Units</option>
                                </select>
                            </div>

                            <div id="availableClassesList">
                                <?php if (empty($offeredClasses)): ?>
                                    <p class="text-muted" style="text-align: center; padding: 30px;">No prerequisite-free offered classes found for your curriculum and semester.</p>
                                <?php else: ?>
                                    <?php foreach ($offeredClasses as $cls): 
                                        $classID = $cls['ID'];
                                        $isLocked = ($cls['IsEnrollmentOpen'] == 0 || $cls['EnrolledCount'] >= $cls['MaxCapacity'] || !$isEnrollmentGloballyOpen);
                                        $remainingSlots = $cls['MaxCapacity'] - $cls['EnrolledCount'];
                                        $classSchedules = $schedulesByClass[$classID] ?? [];
                                        
                                        $schedTextArr = [];
                                        foreach ($classSchedules as $cs) {
                                            $schedTextArr[] = $cs['DayOfWeek'] . ' ' . date('h:i A', strtotime($cs['StartTime'])) . '-' . date('h:i A', strtotime($cs['EndTime'])) . ' (' . ($cs['RoomAssignment'] ?: 'TBA') . ')';
                                        }
                                        $schedString = !empty($schedTextArr) ? implode(' | ', $schedTextArr) : 'Schedule TBA';
                                    ?>
                                        <div class="subject-item <?= $isLocked ? 'locked' : '' ?>" 
                                             data-id="<?= $classID ?>" 
                                             data-code="<?= htmlspecialchars($cls['SubjectCode']) ?>" 
                                             data-title="<?= htmlspecialchars_decode($cls['SubjectTitle']) ?>" 
                                             data-units="<?= $cls['Units'] ?>" 
                                             data-classname="<?= htmlspecialchars($cls['ClassName']) ?>"
                                             data-schedule="<?= htmlspecialchars($schedString) ?>"
                                             data-instructor="<?= htmlspecialchars(($cls['InstFirst'] ? $cls['InstFirst'] . ' ' . $cls['InstLast'] : 'TBA')) ?>"
                                             data-schedules='<?= json_encode($classSchedules) ?>'>
                                            <div class="subject-info">
                                                <h4><?= htmlspecialchars($cls['ClassName']) ?> (<?= htmlspecialchars($cls['SubjectCode']) ?>)</h4>
                                                <p><?= htmlspecialchars_decode($cls['SubjectTitle']) ?></p>
                                                <p><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($schedString) ?></p>
                                                
                                                <?php if (!empty($cls['InstFirst'])): ?>
                                                    <div class="instructor-tag" title="<?= htmlspecialchars($cls['InstPos'] ?? 'Instructor') ?>">
                                                        <?php 
                                                            $instPic = !empty($cls['InstPic']) ? '../assets/uploads/profiles/' . $cls['InstPic'] : $defaultProfilePhoto;
                                                        ?>
                                                        <img src="<?= htmlspecialchars($instPic) ?>" alt="Instructor">
                                                        <span><?= htmlspecialchars($cls['InstFirst'] . ' ' . $cls['InstLast']) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <p><i class="fa-solid fa-chalkboard-user"></i> Instructor: <em>TBA</em></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="subject-meta">
                                                <div style="display: flex; gap: 4px;">
                                                    <span class="unit-badge"><?= $cls['Units'] ?> Units</span>
                                                    <span class="slots-badge"><?= $remainingSlots ?> slots left</span>
                                                </div>
                                                <?php if ($cls['IsEnrollmentOpen'] == 0): ?>
                                                    <span class="locked-badge">Locked</span>
                                                <?php elseif ($cls['EnrolledCount'] >= $cls['MaxCapacity']): ?>
                                                    <span class="locked-badge">Full</span>
                                                <?php elseif ($isEnrollmentGloballyOpen): ?>
                                                    <button type="button" class="add-btn" onclick="addClassToCart(<?= $classID ?>)" title="Add Class"><i class="fa-solid fa-plus"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT PANEL: Selected Classes / Cart -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3><i class="fa-solid fa-cart-shopping" style="color: var(--primary-accent);"></i> Selected Enrolled Classes</h3>
                            <span class="unit-badge" id="headerUnitBadge">0 Units</span>
                        </div>
                        <div class="panel-body" id="dropZone" style="background-color: #fafafa;">
                            <div id="emptyCartMessage" class="empty-cart">
                                <i class="fa-solid fa-hand-pointer" style="font-size: 2rem; color: #cbd5e1;  margin: auto; margin-bottom: 10px;display: block;"></i>
                                Click [+] from the catalog to select classes for your semester enrollment.
                            </div>
                            <div id="enrolledClassesList"></div>
                        </div>
                        <div class="panel-footer">
                            <div class="units-summary">
                                Total Units: <span id="totalUnitsCount">0</span> / 27 max
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" id="saveDraftBtn" class="btn btn-accent" onclick="document.getElementById('formAction').value='save_draft';">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Draft
                                </button>
                                <button type="submit" id="confirmEnrollmentBtn" class="btn btn-primary" onclick="document.getElementById('formAction').value='confirm_enrollment';">
                                    <i class="fa-solid fa-check-to-slot"></i> Confirm Enrollment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- SCHEDULE VIEWER SECTION (Matrix) -->
            <div class="schedule-card">
                <div class="panel-header">
                    <h3><i class="fa-solid fa-calendar-days" style="color: var(--primary-accent);"></i> Interactive Weekly Schedule Matrix & Conflict Checker</h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <div class="schedule-table-wrapper">
                        <table class="schedule-table" id="scheduleMatrixTable">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Day / Time</th>
                                    <?php 
                                        $timeCols = [];
                                        for ($h = 7; $h <= 18; $h++) {
                                            $timeCols[] = sprintf('%02d:00:00', $h);
                                            if ($h < 18) {
                                                $timeCols[] = sprintf('%02d:30:00', $h);
                                            }
                                        }
                                        foreach ($timeCols as $tCol):
                                            $colLabel = date('h:i A', strtotime($tCol));
                                    ?>
                                        <th style="font-size: 0.7rem; min-width: 65px;"><?= $colLabel ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="scheduleMatrixBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Handling Cart, Cart Conflicts, Matrix Rendering & Colspan Overlaps -->
    <script>
        let selectedClassIDs = <?= json_encode($enrolledClassIDs) ?>;
        const MAX_UNITS = 27;

        const timeColumns = [
            "07:00:00", "07:30:00", "08:00:00", "08:30:00", "09:00:00", "09:30:00",
            "10:00:00", "10:30:00", "11:00:00", "11:30:00", "12:00:00", "12:30:00",
            "13:00:00", "13:30:00", "14:00:00", "14:30:00", "15:00:00", "15:30:00",
            "16:00:00", "16:30:00", "17:00:00", "17:30:00", "18:00:00"
        ];

        const daysList = [
            { key: 'MON', label: 'Monday' },
            { key: 'TUE', label: 'Tuesday' },
            { key: 'WED', label: 'Wednesday' },
            { key: 'THU', label: 'Thursday' },
            { key: 'FRI', label: 'Friday' },
            { key: 'SAT', label: 'Saturday' },
            { key: 'SUN', label: 'Sunday'}
        ];

        document.addEventListener('DOMContentLoaded', () => {
            renderCart();
            updateScheduleGrid();

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    document.querySelectorAll('.subject-item').forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(query) ? 'flex' : 'none';
                    });
                });
            }
        });

        function addClassToCart(id) {
            if (selectedClassIDs.includes(id)) return;

            const item = document.querySelector(`.subject-item[data-id="${id}"]`);
            if (!item) return;

            const units = parseFloat(item.dataset.units);
            const currentTotal = getCurrentTotalUnits();

            if (currentTotal + units > MAX_UNITS) {
                alert(`Cannot add class. Maximum limit of ${MAX_UNITS} units will be exceeded.`);
                return;
            }

            selectedClassIDs.push(id);
            renderCart();
            updateScheduleGrid();
        }

        function removeClassFromCart(id) {
            const index = selectedClassIDs.indexOf(id);
            if (index > -1) {
                selectedClassIDs.splice(index, 1);
            }
            renderCart();
            updateScheduleGrid();
        }

        function getCurrentTotalUnits() {
            let total = 0;
            selectedClassIDs.forEach(id => {
                const item = document.querySelector(`.subject-item[data-id="${id}"]`);
                if (item) total += parseFloat(item.dataset.units);
            });
            return total;
        }

        function parseDays(dayStr) {
            dayStr = (dayStr || '').toUpperCase();
            let matched = [];
            if (dayStr.includes('MON') || (dayStr.includes('M') && !dayStr.includes('TH'))) matched.push('MON');
            if (dayStr.includes('TUE') || (dayStr.includes('T') && !dayStr.includes('TH'))) matched.push('TUE');
            if (dayStr.includes('WED') || dayStr.includes('W')) matched.push('WED');
            if (dayStr.includes('THU') || dayStr.includes('TH')) matched.push('THU');
            if (dayStr.includes('FRI') || dayStr.includes('F')) matched.push('FRI');
            if (dayStr.includes('SAT') || dayStr.includes('S')) matched.push('SAT');
            if (dayStr.includes('SUN') || dayStr.includes('A')) matched.push('SUN');
            return matched;
        }

        // Check if two classes have overlapping schedules
        function checkClassConflict(schedulesA, schedulesB) {
            for (let sa of schedulesA) {
                let daysA = parseDays(sa.DayOfWeek);
                for (let sb of schedulesB) {
                    let daysB = parseDays(sb.DayOfWeek);
                    let commonDays = daysA.filter(d => daysB.includes(d));
                    if (commonDays.length > 0) {
                        if (sa.StartTime < sb.EndTime && sb.StartTime < sa.EndTime) {
                            return true; // Overlap found
                        }
                    }
                }
            }
            return false;
        }

        function renderCart() {
            const enrolledList = document.getElementById('enrolledClassesList');
            const emptyCartMessage = document.getElementById('emptyCartMessage');
            const totalUnitsCount = document.getElementById('totalUnitsCount');
            const headerUnitBadge = document.getElementById('headerUnitBadge');

            enrolledList.innerHTML = '';
            if (selectedClassIDs.length === 0) {
                emptyCartMessage.style.display = 'block';
                totalUnitsCount.textContent = '0';
                headerUnitBadge.textContent = '0 Units';
                return;
            }

            emptyCartMessage.style.display = 'none';
            let totalUnits = 0;

            // Pre-parse schedules for all selected classes to check conflicts for cart items
            let classSchedulesMap = {};
            selectedClassIDs.forEach(id => {
                const item = document.querySelector(`.subject-item[data-id="${id}"]`);
                if (item) {
                    try {
                        classSchedulesMap[id] = JSON.parse(item.dataset.schedules || '[]');
                    } catch(e) {
                        classSchedulesMap[id] = [];
                    }
                }
            });

            selectedClassIDs.forEach(id => {
                const item = document.querySelector(`.subject-item[data-id="${id}"]`);
                if (!item) return;

                const code = item.dataset.code;
                const title = item.dataset.title;
                const units = parseFloat(item.dataset.units);
                const classname = item.dataset.classname;
                const instructor = item.dataset.instructor;

                totalUnits += units;

                // Determine if this specific class has a schedule conflict with any other selected class
                let hasConflictWithOther = false;
                selectedClassIDs.forEach(otherID => {
                    if (id !== otherID) {
                        if (checkClassConflict(classSchedulesMap[id], classSchedulesMap[otherID])) {
                            hasConflictWithOther = true;
                        }
                    }
                });

                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'classes[]';
                hiddenInput.value = id;
                enrolledList.appendChild(hiddenInput);

                const enrolledDiv = document.createElement('div');
                enrolledDiv.className = `enrolled-item ${hasConflictWithOther ? 'conflict' : ''}`;
                enrolledDiv.innerHTML = `
                    <div class="subject-info">
                        <h4>${classname} (${code})</h4>
                        <p>${title}</p>
                        <p><i class="fa-solid fa-chalkboard-user"></i> ${instructor}</p>
                        ${hasConflictWithOther ? '<div class="conflict-text"><i class="fa-solid fa-triangle-exclamation"></i> Schedule Conflict!</div>' : ''}
                    </div>
                    <div class="subject-meta">
                        <span class="unit-badge">${units} Units</span>
                        <button type="button" class="remove-btn" onclick="removeClassFromCart(${id})" title="Remove Class"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                `;
                enrolledList.appendChild(enrolledDiv);
            });

            totalUnitsCount.textContent = totalUnits;
            headerUnitBadge.textContent = `${totalUnits} Units`;
        }

        function updateScheduleGrid() {
            const tbody = document.getElementById('scheduleMatrixBody');
            tbody.innerHTML = '';

            let allSchedules = [];
            selectedClassIDs.forEach(id => {
                const item = document.querySelector(`.subject-item[data-id="${id}"]`);
                if (!item) return;

                const code = item.dataset.code;
                const classname = item.dataset.classname;
                let schedules = [];
                try {
                    schedules = JSON.parse(item.dataset.schedules || '[]');
                } catch(e) {}

                schedules.forEach(sch => {
                    const days = parseDays(sch.DayOfWeek);
                    days.forEach(d => {
                        allSchedules.push({
                            day: d,
                            start: sch.StartTime,
                            end: sch.EndTime,
                            classname: classname,
                            code: code,
                            room: sch.RoomAssignment || 'TBA'
                        });
                    });
                });
            });

            daysList.forEach(dayObj => {
                const tr = document.createElement('tr');
                
                const tdDay = document.createElement('td');
                tdDay.className = 'day-col';
                tdDay.textContent = dayObj.label;
                tr.appendChild(tdDay);

                const daySchedules = allSchedules.filter(s => s.day === dayObj.key);

                let skipSlots = 0;
                for (let i = 0; i < timeColumns.length; i++) {
                    if (skipSlots > 0) {
                        skipSlots--;
                        continue;
                    }

                    const slotTime = timeColumns[i];
                    const matchingScheds = daySchedules.filter(s => s.start === slotTime);

                    if (matchingScheds.length > 0) {
                        const sched = matchingScheds[0];
                        
                        let startIndex = timeColumns.indexOf(sched.start);
                        let endIndex = timeColumns.indexOf(sched.end);
                        if (endIndex === -1) endIndex = timeColumns.length;
                        
                        let colSpan = endIndex - startIndex;
                        if (colSpan < 1) colSpan = 1;

                        skipSlots = colSpan - 1;

                        const td = document.createElement('td');
                        td.colSpan = colSpan;

                        let hasConflict = daySchedules.some(other => {
                            if (other === sched) return false;
                            return (sched.start < other.end && other.start < sched.end);
                        });

                        td.innerHTML = `
                            <div class="sched-block ${hasConflict ? 'conflict' : ''}">
                                <strong>${sched.classname}</strong>
                                <span style="font-size: 0.7rem; display:block;">${sched.room}</span>
                                ${hasConflict ? '<span style="color:red; font-weight:bold; font-size:0.65rem;">[CONFLICT!]</span>' : ''}
                            </div>
                        `;
                        tr.appendChild(td);
                    } else {
                        const td = document.createElement('td');
                        let covered = daySchedules.some(s => {
                            return (slotTime >= s.start && slotTime < s.end);
                        });

                        if (!covered) {
                            tr.appendChild(td);
                        }
                    }
                }

                tbody.appendChild(tr);
            });
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>