<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Class Management & Scheduling - AMSC InnDaw";

// -------------------------------------------------------------------------
// POST / AJAX ACTION HANDLERS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        // 1. Create or Update Class Record
        if ($action === 'save_class') {
            $classID        = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $className      = trim($_POST['class_name'] ?? '');
            $currSubID      = filter_input(INPUT_POST, 'curriculum_subject_id', FILTER_VALIDATE_INT);
            $instructorID   = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
            $academicYearID = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);
            $maxCapacity    = filter_input(INPUT_POST, 'max_capacity', FILTER_VALIDATE_INT) ?: 40;
            $isPetition     = isset($_POST['is_petition']) ? 1 : 0;
            $isEnrollOpen   = isset($_POST['is_enrollment_open']) ? 1 : 0;

            if (empty($className) || !$currSubID || !$academicYearID) {
                throw new Exception("Please fill in all required class fields.");
            }

            if ($instructorID === false) {
                $instructorID = NULL;
            }

            if ($classID) {
                // Update
                $stmt = mysqli_prepare($conn, "UPDATE classes SET ClassName = ?, CurriculumSubjectID = ?, InstructorID = ?, AcademicYearID = ?, MaxCapacity = ?, IsEnrollmentOpen = ?, IsPetition = ?, UpdatedAt = NOW() WHERE ID = ?");
                mysqli_stmt_bind_param($stmt, 'siiiiiii', $className, $currSubID, $instructorID, $academicYearID, $maxCapacity, $isEnrollOpen, $isPetition, $classID);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID ?? 0, 'Class Updated', "Updated class record: {$className}.");
                redirectWithMessage('success', 'Class record updated successfully.', 'classes?');
            } else {
                // Insert
                $stmt = mysqli_prepare($conn, "INSERT INTO classes (ClassName, CurriculumSubjectID, InstructorID, AcademicYearID, MaxCapacity, IsEnrollmentOpen, IsPetition) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'siiiiii', $className, $currSubID, $instructorID, $academicYearID, $maxCapacity, $isEnrollOpen, $isPetition);
                mysqli_stmt_execute($stmt);
                $newClassID = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Handle Schedule Inputs if submitted
                if (!empty($_POST['schedules']) && is_array($_POST['schedules'])) {
                    foreach ($_POST['schedules'] as $sched) {
                        $desc  = trim($sched['description'] ?? 'Lecture');
                        $days  = trim($sched['days'] ?? '');
                        $start = trim($sched['start_time'] ?? '');
                        $end   = trim($sched['end_time'] ?? '');
                        $room  = trim($sched['room'] ?? 'TBA');

                        if (!empty($days) && !empty($start) && !empty($end)) {
                            $schedStmt = mysqli_prepare($conn, "INSERT INTO class_schedules (ClassID, Description, DayOfWeek, StartTime, EndTime, RoomAssignment) VALUES (?, ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($schedStmt, 'isssss', $newClassID, $desc, $days, $start, $end, $room);
                            mysqli_stmt_execute($schedStmt);
                            mysqli_stmt_close($schedStmt);
                        }
                    }
                }

                logActivity($conn, $userID ?? 0, 'Class Created', "Created new class: {$className}.");
                redirectWithMessage('success', 'New class created successfully.', 'classes?');
            }
        }

        // 2. Delete Class Record
        elseif ($action === 'delete_class') {
            $classID = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            if (!$classID) throw new Exception("Invalid class ID.");

            $stmt = mysqli_prepare($conn, "DELETE FROM classes WHERE ID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $classID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Class Deleted', "Deleted class ID #{$classID}.");
            redirectWithMessage('success', 'Class deleted successfully.', 'classes?');
        }

        // 3. Quick Toggle Enrollment or Petition Status (AJAX / POST)
        elseif ($action === 'toggle_status') {
            $classID = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $field   = trim($_POST['field'] ?? ''); 
            $val     = filter_input(INPUT_POST, 'val', FILTER_VALIDATE_INT);

            if ($classID && ($field === 'IsEnrollmentOpen' || $field === 'IsPetition')) {
                $stmt = mysqli_prepare($conn, "UPDATE classes SET {$field} = ? WHERE ID = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $val, $classID);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $action = 'opened';

                if ($field === 'IsEnrollmentOpen') {
                    $action = $val === 1 ? 'opened' : 'locked';
                    logActivity($conn, $userID, "Class {$action}", "Enrollment for class ID #{$classID} is {$action}.");
                } else if ($field === 'IsPetition') {
                    $action = $val === 1 ? "Class ID #{$classID} is made a petition class." : "Class ID #{$classID} is removed as a petition class.";
                    logActivity($conn, $userID, "Class Petition Status Changed", $action);
                }
                echo json_encode(['status' => 'success']);
                exit;
            }
            throw new Exception("Invalid toggle parameters.");
        }
    } catch (Exception $e) {
        if (!empty($_POST['ajax'])) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
        redirectWithMessage('error', $e->getMessage(), 'classes?');
    }
}

// -------------------------------------------------------------------------
// FETCH DATA FOR FILTERS, DROPDOWNS & TABLE
// -------------------------------------------------------------------------

// 1. Academic Years Pool
$aySql = "SELECT ID, YearCode, Semester, IsActive, EnrollmentStatus, CONCAT('AY ', YearCode, ', ', Semester) AS Term FROM academic_years ORDER BY CreatedAt DESC";
$ayRes = mysqli_query($conn, $aySql);
$acadYears = [];
$defaultActiveAyID = 1;
while ($ayRow = mysqli_fetch_assoc($ayRes)) {
    $acadYears[] = $ayRow;
    if ($ayRow['IsActive'] == 1) {
        $defaultActiveAyID = $ayRow['ID'];
    }
}

$selectedAyID = filter_input(INPUT_GET, 'ayid', FILTER_VALIDATE_INT) ?: $defaultActiveAyID;

// Get active term
$aySql = "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1";
$ayRes = mysqli_query($conn, $aySql);
$currentTerm = mysqli_fetch_assoc($ayRes);

$activeTerm = 'AY' . $currentTerm['YearCode'] . ', ' . $currentTerm['Semester'];
$enrollmentStatus = $currentTerm['EnrollmentStatus'];

// 2. Subject Pool from curriculum_subjects joined with subjects
$subjectPoolQuery = "
    SELECT cs.ID AS CurriculumSubjectID, s.SubjectCode, s.SubjectTitle, cs.Units
    FROM curriculum_subjects cs
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    WHERE s.ID IS NOT NULL
    ORDER BY s.SubjectCode ASC
";
$subjectPoolRes = mysqli_query($conn, $subjectPoolQuery);
$subjectPool = [];
while ($sub = mysqli_fetch_assoc($subjectPoolRes)) {
    $subjectPool[] = $sub;
}

// 3. Instructor Pool: staff_members who have staff_designations with AppointmentType = 'Academic'
$instructorPoolQuery = "
    SELECT DISTINCT sm.StaffID, sm.EmployeeNumber, sm.FirstName, sm.LastName, sm.ProfilePicture, sm.PositionTitle, sm.ContactNumber, sm.DateOfBirth, sm.HomeAddress
    FROM staff_members sm
    JOIN staff_designations sd ON sm.StaffID = sd.StaffID
    WHERE sd.AppointmentType = 'Academic' AND sm.IsActive = 1
    ORDER BY sm.LastName ASC
";
$instructorRes = mysqli_query($conn, $instructorPoolQuery);
$instructorPool = [];
while ($inst = mysqli_fetch_assoc($instructorRes)) {
    $instructorPool[] = $inst;
}

// 4. Pagination, Searching, Sorting, Filtering for Classes Table
$searchQuery = trim($_GET['q'] ?? '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClause = "WHERE c.AcademicYearID = $selectedAyID";
if (!empty($searchQuery)) {
    $escapedQuery = mysqli_real_escape_string($conn, $searchQuery);
    $whereClause .= " AND (c.ClassName LIKE '%{$escapedQuery}%' OR s.SubjectCode LIKE '%{$escapedQuery}%' OR s.SubjectTitle LIKE '%{$escapedQuery}%' OR CONCAT(sm.FirstName, ' ', sm.LastName) LIKE '%{$escapedQuery}%')";
}

$countSql = "
    SELECT COUNT(*) AS total 
    FROM classes c
    JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    LEFT JOIN staff_members sm ON c.InstructorID = sm.StaffID
    {$whereClause}
";
$countRes = mysqli_fetch_assoc(mysqli_query($conn, $countSql));
$totalRecords = $countRes['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit) ?: 1;

$classesQuery = "
    SELECT c.*, 
           s.SubjectCode, s.SubjectTitle, 
           sm.StaffID, sm.FirstName AS InstFirst, sm.LastName AS InstLast, sm.ProfilePicture AS InstPhoto, sm.EmployeeNumber, sm.PositionTitle, sm.ContactNumber, sm.DateOfBirth, sm.HomeAddress,
           (SELECT COUNT(*) FROM enrollments e WHERE e.ClassID = c.ID AND e.Status = 'Enrolled') AS EnrolledCount
    FROM classes c
    JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    LEFT JOIN staff_members sm ON c.InstructorID = sm.StaffID
    {$whereClause}
    ORDER BY c.ID DESC
    LIMIT $limit OFFSET $offset
";
$classesRes = mysqli_query($conn, $classesQuery);
$classesList = [];
while ($row = mysqli_fetch_assoc($classesRes)) {
    $classID = $row['ID'];
    $schedQuery = "SELECT * FROM class_schedules WHERE ClassID = $classID";
    $schedRes = mysqli_query($conn, $schedQuery);
    $schedules = [];
    while ($sc = mysqli_fetch_assoc($schedRes)) {
        $schedules[] = $sc;
    }
    $row['Schedules'] = $schedules;
    $classesList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .classes-container { display: flex; flex-direction: column; gap: 20px; }
        .filter-toolbar { background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .classes-table-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; }
        .classes-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem; }
        .classes-table th, .classes-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .classes-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .classes-table tr:hover { background-color: #f8fafc; }
        
        .class-link { font-weight: 700; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .class-link:hover { text-decoration: underline; color: var(--primary-hover); }

        .instructor-cell { display: flex; align-items: center; gap: 10px; }
        .instructor-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
        .instructor-name-link { font-weight: 600; color: var(--primary); text-decoration: none; cursor: pointer; }
        .instructor-name-link:hover { text-decoration: underline; }

        .capacity-badge { background-color: #f1f5f9; color: var(--text-color); font-weight: 600; font-size: 0.78rem; padding: 4px 10px; border-radius: 12px; display: inline-block; }
        
        /* Toggle Switch Styles */
        .switch { position: relative; display: inline-block; width: 42px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 22px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(20px); }

        .toggle-container { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }

        /* Day Selector Toggle Buttons Styles */
        .day-selector { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
        .day-btn { width: 36px; height: 36px; border-radius: 6px; border: 1px solid var(--border-color); background: #f8fafc; color: var(--text-color); font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .day-btn:hover { border-color: var(--primary); background: #e0f2fe; color: var(--primary); }
        .day-btn.active { background: var(--primary); color: #ffffff; border-color: var(--primary); box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25); }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 92%; max-width: 650px; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #f8fafc; }
        .modal-footer { padding: 14px 20px; background: #ffffff; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }

        .enrollment-alert-banner { padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .enrollment-alert-banner.open { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .enrollment-alert-banner.closed { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media(max-width: 768px) { .form-grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Class Management & Scheduling</h2>
                    <p class="text-muted">Manage academic classes, assign academic instructors, configure schedules, and open sections for enrollment or petitions.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openClassModal()">
                        <i class="fa-solid fa-plus"></i> Create New Class
                    </button>
                </div>
            </div>

            <!-- Enrollment Status Alert Box -->
            <?php 
                $statusLower = strtolower(trim($enrollmentStatus));
                $alertClass = ($statusLower === 'open') ? 'open' : 'closed';
                $statusIcon = ($statusLower === 'open') ? 'fa-circle-check' : 'fa-circle-xmark';
            ?>
            <div class="enrollment-alert-banner <?= $alertClass ?>">
                <i class="fa-solid <?= $statusIcon ?>"></i>
                <span>Enrollment Status for <strong><?= htmlspecialchars($activeTerm) ?></strong> is currently: <strong><?= htmlspecialchars($enrollmentStatus) ?></strong></span>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="classes-container">

                <!-- Filter Toolbar -->
                <div class="filter-toolbar">
                    <form method="GET" action="classes" class="filter-group" style="flex: 1; max-width: 600px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">Academic Term:</label>
                        <select name="ayid" class="form-control" style="width: 280px; padding: 6px 10px; font-size: 0.85rem;" onchange="this.form.submit()">
                            <?php foreach ($acadYears as $ay): ?>
                                <option value="<?= $ay['ID'] ?>" <?= ($ay['ID'] == $selectedAyID) ? 'selected' : '' ?>><?= $ay['Term'] ?><?= $ay['IsActive'] == 1 ? ' (Active)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search class, subject, teacher..." class="form-control" style="width: 220px; padding: 6px 12px; font-size: 0.85rem;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Total Classes: <strong><?= $totalRecords ?></strong>
                    </div>
                </div>

                <!-- Classes Table Card -->
                <div class="classes-table-card">
                    <div style="overflow-x: auto;">
                        <table class="classes-table">
                            <thead>
                                <tr>
                                    <th>Class Name / Subject</th>
                                    <th>Instructor</th>
                                    <th>Schedule & Room</th>
                                    <th>Enrolled / Capacity</th>
                                    <th>Enrollment Open</th>
                                    <th>Petition Class</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($classesList)): ?>
                                    <?php foreach ($classesList as $cls): ?>
                                        <?php 
                                            $schedStrings = [];
                                            if (!empty($cls['Schedules'])) {
                                                foreach ($cls['Schedules'] as $sc) {
                                                    $days = htmlspecialchars($sc['DayOfWeek']);
                                                    $start = date('g:i a', strtotime($sc['StartTime']));
                                                    $end = date('g:i a', strtotime($sc['EndTime']));
                                                    $room = htmlspecialchars($sc['RoomAssignment'] ?? 'TBA');
                                                    $schedStrings[] = "{$days} {$start} - {$end} ({$room})";
                                                }
                                            }
                                            $formattedSchedule = !empty($schedStrings) ? implode(' <br> ', $schedStrings) : '<span class="text-muted">No schedule assigned</span>';

                                            $instPhoto = !empty($cls['InstPhoto']) ? '../assets/uploads/profiles/' . htmlspecialchars($cls['InstPhoto']) : '../assets/images/default-avatar.png';
                                            $instName = !empty($cls['InstLast']) ? htmlspecialchars("{$cls['InstFirst']} {$cls['InstLast']}") : 'Unassigned Instructor';
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="class_view?id=<?= $cls['ID'] ?>" class="class-link">
                                                    <i class="fa-solid fa-chalkboard-user"></i> <?= htmlspecialchars($cls['ClassName']) ?>
                                                </a>
                                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                                    <?= htmlspecialchars($cls['SubjectCode']) ?> - <?= htmlspecialchars_decode($cls['SubjectTitle']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($cls['StaffID'])): ?>
                                                    <div class="instructor-cell">
                                                        <img src="<?= $instPhoto ?>" alt="Instructor" class="instructor-avatar">
                                                        <div>
                                                            <a class="instructor-name-link" onclick='openInstructorModal(<?= json_encode($cls) ?>)'><?= $instName ?></a>
                                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($cls['PositionTitle'] ?? 'Academic Staff') ?></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-style: italic; font-size: 0.82rem;">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><div style="font-size: 0.85rem; line-height: 1.4;"><?= $formattedSchedule ?></div></td>
                                            <td>
                                                <span class="capacity-badge"><?= $cls['EnrolledCount'] ?> / <?= $cls['MaxCapacity'] ?> Students</span>
                                            </td>
                                            <td>
                                                <label class="switch" title="Toggle Enrollment">
                                                    <input type="checkbox" <?= $cls['IsEnrollmentOpen'] == 1 ? 'checked' : '' ?> onchange="toggleStatus(<?= $cls['ID'] ?>, 'IsEnrollmentOpen', this)">
                                                    <span class="slider"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <label class="switch" title="Toggle Petition Status">
                                                    <input type="checkbox" <?= $cls['IsPetition'] == 1 ? 'checked' : '' ?> onchange="toggleStatus(<?= $cls['ID'] ?>, 'IsPetition', this)">
                                                    <span class="slider"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openClassModal(<?= json_encode($cls) ?>)' title="Edit Class">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <form action="classes" method="POST" onsubmit="return confirm('Are you sure you want to delete this class?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_class">
                                                        <input type="hidden" name="class_id" value="<?= $cls['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">No classes found for the selected academic term.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                            <span class="text-muted">Showing page <?= $page ?> of <?= $totalPages ?></span>
                            <div style="display: flex; gap: 5px;">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="classes?ayid=<?= $selectedAyID ?>&page=<?= $i ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-sm <?= ($i == $page) ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- CREATE / EDIT CLASS MODAL -->
    <div id="classModal" class="modal-overlay">
        <div class="modal-card" style="overflow: auto;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-chalkboard"></i> Create New Class</h3>
                <button type="button" class="modal-close-btn" onclick="closeClassModal()">&times;</button>
            </div>
            <form id="classForm" action="classes" method="POST">
                <input type="hidden" name="action" value="save_class">
                <input type="hidden" name="class_id" id="classID">
                <input type="hidden" name="academic_year_id" value="<?= $selectedAyID ?>">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Class Name / Section *</label>
                        <input type="text" name="class_name" id="className" class="form-control" placeholder="e.g. IT 111 - Section A" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Subject Pool (Curriculum Subjects) *</label>
                        <select name="curriculum_subject_id" id="currSubID" class="form-control" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($subjectPool as $sub): ?>
                                <option value="<?= $sub['CurriculumSubjectID'] ?>"><?= htmlspecialchars_decode($sub['SubjectCode'] . ' - ' . $sub['SubjectTitle'] . ' (' . $sub['Units'] . ' units)') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Assign Instructor (Academic Appointment)</label>
                        <select name="instructor_id" id="instructorID" class="form-control">
                            <option value="">-- Unassigned (Can be assigned later) --</option>
                            <?php foreach ($instructorPool as $inst): ?>
                                <option value="<?= $inst['StaffID'] ?>"><?= htmlspecialchars_decode("{$inst['LastName']}, {$inst['FirstName']} ({$inst['EmployeeNumber']})") ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Max Student Capacity *</label>
                            <input type="number" name="max_capacity" id="maxCapacity" class="form-control" value="40" min="5" max="150" required>
                        </div>
                        <div style="display: flex; flex-direction: row; gap: 10px; justify-content: center;">
                            <label class="toggle-container">
                                <input type="checkbox" name="is_enrollment_open" id="isEnrollOpen" value="1"> Open Enrollment
                            </label>
                            <label class="toggle-container">
                                <input type="checkbox" name="is_petition" id="isPetition" value="1"> Petition Class
                            </label>
                        </div>
                    </div>

                    <!-- Initial Schedule Configuration with Interactive Day Toggle Buttons -->
                    <div id="scheduleSection">
                        <hr style="border:0; border-top:1px solid var(--border-color); margin: 15px 0;">
                        <h4 style="font-size: 0.9rem; color: var(--primary); margin-bottom: 10px;"><i class="fa-solid fa-calendar-days"></i> Initial Schedule Entry</h4>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Day(s) of Week (Select multiple)</label>
                            <div class="day-selector">
                                <input type="hidden" name="schedules[0][days]" id="schedDaysInput" value="">
                                <button type="button" class="day-btn" data-day="A" title="Sunday" onclick="toggleDayButton(this, 'A')">A</button>
                                <button type="button" class="day-btn" data-day="M" title="Monday" onclick="toggleDayButton(this, 'M')">M</button>
                                <button type="button" class="day-btn" data-day="T" title="Tuesday" onclick="toggleDayButton(this, 'T')">T</button>
                                <button type="button" class="day-btn" data-day="W" title="Wednesday" onclick="toggleDayButton(this, 'W')">W</button>
                                <button type="button" class="day-btn" data-day="Th" title="Thursday" onclick="toggleDayButton(this, 'Th')">Th</button>
                                <button type="button" class="day-btn" data-day="F" title="Friday" onclick="toggleDayButton(this, 'F')">F</button>
                                <button type="button" class="day-btn" data-day="S" title="Saturday" onclick="toggleDayButton(this, 'S')">S</button>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-bottom: 10px;">
                            <div class="form-group">
                                <label style="font-size: 0.8rem; font-weight: 600;">Description</label>
                                <input type="text" name="schedules[0][description]" class="form-control" value="Lecture">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.8rem; font-weight: 600;">Room Assignment</label>
                                <input type="text" name="schedules[0][room]" class="form-control" placeholder="Room 301 or Lab" value="TBA">
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.8rem; font-weight: 600;">Start & End Time</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="time" name="schedules[0][start_time]" class="form-control">
                                <input type="time" name="schedules[0][end_time]" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeClassModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Class</button>
                </div>
            </form>
        </div>
    </div>

    <!-- INSTRUCTOR PROFILE & TEACHING LOAD MODAL -->
    <div id="instructorModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-tie"></i> Instructor Profile & Teaching Load</h3>
                <button type="button" class="modal-close-btn" onclick="closeInstructorModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; gap: 20px; align-items: center; background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                    <img id="instModalPhoto" src="<?= $userProfilePhoto ?>" alt="Instructor Photo" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary);">
                    <div>
                        <h4 id="instModalName" style="margin: 0 0 4px 0; color: var(--primary);">Instructor Name</h4>
                        <p class="text-muted" style="margin: 0 0 4px 0; font-size: 0.88rem;">Employee No: <strong id="instModalEmpNo">AMSC-EMP-0000</strong></p>
                        <p class="text-muted" style="margin: 0; font-size: 0.85rem;">Position: <span id="instModalPosition" style="font-weight: 600; color: var(--text-color);">Academic Instructor</span></p>
                    </div>
                </div>

                <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                    <h5 style="margin-top: 0; color: var(--primary); font-size: 0.9rem; margin-bottom: 10px;">Employee Information</h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem;">
                        <div><strong>Contact Number:</strong> <span id="instModalContact">N/A</span></div>
                        <div><strong>Date of Birth:</strong> <span id="instModalDob">N/A</span></div>
                        <div style="grid-column: span 2;"><strong>Home Address:</strong> <span id="instModalAddress">N/A</span></div>
                    </div>
                </div>

                <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5 style="margin-top: 0; color: var(--primary); font-size: 0.9rem; margin-bottom: 10px;">Assigned Teaching Load (Current Term)</h5>
                    <div id="instModalTeachingLoad">
                        <p class="text-muted" style="font-size: 0.85rem; text-align: center; margin: 10px 0;">Loading teaching load...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeInstructorModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        const classModal = document.getElementById('classModal');
        const instructorModal = document.getElementById('instructorModal');

        function openClassModal(cls = null) {
            const form = document.getElementById('classForm');
            
            if (cls) {
                document.getElementById('modalTitle').innerHTML = `<i class="fa-solid fa-pen"></i> Edit Class Record`;
                document.getElementById('classID').value = cls.ID;
                document.getElementById('className').value = cls.ClassName || '';
                document.getElementById('currSubID').value = cls.CurriculumSubjectID || '';
                document.getElementById('instructorID').value = cls.StaffID || '';
                document.getElementById('maxCapacity').value = cls.MaxCapacity || 40;
                document.getElementById('isEnrollOpen').checked = (cls.IsEnrollmentOpen == 1);
                document.getElementById('isPetition').checked = (cls.IsPetition == 1);
                
                document.getElementById('scheduleSection').style.display = 'none';
            } else {
                document.getElementById('modalTitle').innerHTML = `<i class="fa-solid fa-chalkboard"></i> Create New Class`;
                
                form.reset();
                document.getElementById('classID').value = '';
                document.getElementById('isEnrollOpen').checked = false;
                document.getElementById('isPetition').checked = false;
                document.getElementById('maxCapacity').value = 40;
                document.getElementById('schedDaysInput').value = '';
                
                // Clear active states on day toggle buttons
                document.querySelectorAll('.day-btn').forEach(btn => btn.classList.remove('active'));

                document.getElementById('scheduleSection').style.display = 'block';
            }
            classModal.classList.add('active');
        }

        function closeClassModal() {
            classModal.classList.remove('active');
        }

        function toggleDayButton(btn, dayLetter) {
            btn.classList.toggle('active');
            const selectorGroup = btn.closest('.day-selector');
            const order = ['A', 'M', 'T', 'W', 'Th', 'F', 'S'];
            let selected = [];
            order.forEach(d => {
                const b = selectorGroup.querySelector(`.day-btn[data-day="${d}"]`);
                if (b && b.classList.contains('active')) {
                    selected.push(d);
                }
            });
            selectorGroup.querySelector('#schedDaysInput').value = selected.join('');
        }

        function openInstructorModal(cls) {
            const birthday = cls.DateOfBirth ?? 'N/A';

            if (birthday !== 'N/A' || birthday !== '0000-00-00' || birthday !== null || birthday !== '') {
                const [year, month, day] = birthday.split('-').map(Number);
                const dateObj = new Date(year, month - 1, day);

                const formattedBirthday = new Intl.DateTimeFormat('en-US', {
                    month: 'long',
                    day: '2-digit',
                    year: 'numeric'
                }).format(dateObj);
            }

            document.getElementById('instModalName').innerText = `${cls.InstFirst || ''} ${cls.InstLast || ''}`.trim();
            document.getElementById('instModalEmpNo').innerText = cls.EmployeeNumber || 'N/A';
            document.getElementById('instModalPosition').innerText = cls.PositionTitle || 'Academic Instructor';
            document.getElementById('instModalContact').innerText = cls.ContactNumber || 'N/A';
            document.getElementById('instModalDob').innerText = birthday;
            document.getElementById('instModalAddress').innerText = cls.HomeAddress || 'N/A';

            if (cls.InstPhoto) {
                document.getElementById('instModalPhoto').src = `../assets/uploads/profiles/${cls.InstPhoto}`;
            } else {
                document.getElementById('instModalPhoto').src = '../assets/images/default-avatar.png';
            }

            const loadContainer = document.getElementById('instModalTeachingLoad');
            loadContainer.innerHTML = `<p style="font-size: 0.85rem;">Currently assigned to class: <strong>${cls.ClassName}</strong> (${cls.SubjectCode} - ${cls.SubjectTitle})</p>`;

            instructorModal.classList.add('active');
        }

        function closeInstructorModal() {
            instructorModal.classList.remove('active');
        }

        function toggleStatus(classID, field, checkboxElement) {
            const val = checkboxElement.checked ? 1 : 0;
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('ajax', '1');
            formData.append('class_id', classID);
            formData.append('field', field);
            formData.append('val', val);

            fetch('classes', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert("Failed to update status.");
                    checkboxElement.checked = !checkboxElement.checked;
                }
            })
            .catch(err => {
                console.error(err);
                checkboxElement.checked = !checkboxElement.checked;
            });
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>