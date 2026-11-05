<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Academic & Curriculum Management - AMSC InnDaw";

// Determine active tab from URL parameter
$currentTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'term';
if (!in_array($currentTab, ['term', 'program', 'curriculum', 'subject'])) {
    $currentTab = 'term';
}

function validateAcademicYear($academicYear) {
    // 1. Strict pattern check for YYYY-YYYY (e.g., 2026-2027)
    if (!preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $matches)) {
        return "Invalid format. The academic year must strictly follow the pattern YYYY-YYYY (e.g., 2026-2027).";
    }

    $year1 = (int)$matches[1];
    $year2 = (int)$matches[2];

    // 2. First year must not be equal or greater than the second year (Year1 < Year2)
    if ($year1 >= $year2) {
        return "The first year cannot be equal to or greater than the second year.";
    }

    // 3. Ensure they are succeeding/consecutive years (exactly one year apart)
    if ($year2 - $year1 !== 1) {
        return "The academic years must be consecutive and exactly one year apart (e.g., 2026-2027).";
    }

    return true; // Valid
}

// Get active term
$aySql = "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1";
$ayRes = mysqli_query($conn, $aySql);
$currentTerm = mysqli_fetch_assoc($ayRes);

$activeTerm = 'AY' . $currentTerm['YearCode'] . ', ' . $currentTerm['Semester'];
$enrollmentStatus = $currentTerm['EnrollmentStatus'];

// -------------------------------------------------------------------------
// Handle Form Submissions (Create, Update, Delete across all tabs)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = trim($_POST['action'] ?? '');
    $category = trim($_POST['category'] ?? '');

    try {
        // --- 1. ACADEMIC TERMS CRUD ---
        if ($category === 'term') {
            if ($action === 'save-term') {
                $termID = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
                $yearCode = trim($_POST['academic_year'] ?? '');
                $semester = trim($_POST['semester'] ?? '');
                $enrollmentStatus = trim($_POST['enrollment_status'] ?? 'Closed');
                $isSystemActive = isset($_POST['is_system_active']) ? 1 : 0;

                if (empty($yearCode) || empty($semester)) {
                    throw new Exception("Academic Year and Semester are required.");
                }

                $validYearCode = validateAcademicYear($yearCode);

                if ($validYearCode !== true) {
                    throw new Exception($validYearCode);
                }

                // If marked active system term, set all other terms to IsSystemActive = 0
                if ($isSystemActive === 1) {
                    mysqli_query($conn, "UPDATE academic_years SET IsActive = 0");
                }

                if ($termID) {
                    $sql = "UPDATE academic_years SET YearCode = ?, Semester = ?, EnrollmentStatus = ?, IsActive = ? WHERE ID = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "sssii", $yearCode, $semester, $enrollmentStatus, $isSystemActive, $termID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Academic Term Updated', "Updated term: {$yearCode} {$semester}.");
                    redirectWithMessage('success', "Academic term successfully updated.", "academics?tab=term&&");
                } else {
                    $sql = "INSERT INTO academic_years (YearCode, Semester, EnrollmentStatus, IsActive) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "sssi", $yearCode, $semester, $enrollmentStatus, $isSystemActive);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Academic Term Created', "Created new term: {$yearCode} {$semester}.");
                    redirectWithMessage('success', "New academic term successfully added.", "academics?tab=term&");
                }
            } elseif ($action === 'delete-term') {
                $termID = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
                if ($termID) {
                    $stmt = mysqli_prepare($conn, "DELETE FROM academic_years WHERE ID = ?");
                    mysqli_stmt_bind_param($stmt, "i", $termID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Academic Term Deleted', "Deleted academic term ID {$termID}.");
                    redirectWithMessage('success', "Academic term deleted successfully.", "academics?tab=term&");
                }
            }
        }

        // --- 2. DEGREE PROGRAMS CRUD ---
        elseif ($category === 'program') {
            if ($action === 'save-program') {
                $progID = filter_input(INPUT_POST, 'program_id', FILTER_VALIDATE_INT);
                $progCode = trim($_POST['program_code'] ?? '');
                $progTitle = trim($_POST['program_title'] ?? '');
                $officeID = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT) ?: null;
                $isActive = trim($_POST['is_active'] ?? '1');

                if (empty($progCode) || empty($progTitle)) {
                    throw new Exception("Program Code and Descriptive Title are required.");
                }

                if ($progID) {
                    $sql = "UPDATE programs SET ProgramCode = ?, ProgramName = ?, CollegeID = ?, IsActive = ? WHERE ID = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssisi", $progCode, $progTitle, $officeID, $isActive, $progID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Degree Program Updated', "Updated degree program: {$progCode}.");
                    redirectWithMessage('success', "Degree program successfully updated.", "academics?tab=program&");
                } else {
                    $sql = "INSERT INTO programs (ProgramCode, ProgramName, CollegeID, IsActive) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssis", $progCode, $progTitle, $officeID, $isActive);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Degree Program Created', "Created degree program: {$progCode}.");
                    redirectWithMessage('success', "New degree program successfully added.", "academics?tab=program&");
                }
            } elseif ($action === 'delete-program') {
                $progID = filter_input(INPUT_POST, 'program_id', FILTER_VALIDATE_INT);
                if ($progID) {
                    $stmt = mysqli_prepare($conn, "DELETE FROM programs WHERE ID = ?");
                    mysqli_stmt_bind_param($stmt, "i", $progID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Degree Program Deleted', "Deleted degree program ID {$progID}.");
                    redirectWithMessage('success', "Degree program deleted successfully.", "academics?tab=program&");
                }
            }
        }

        // --- 3. CURRICULA CRUD ---
        elseif ($category === 'curriculum') {
            if ($action === 'save-curriculum') {
                $currID = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
                $title = trim($_POST['curriculum_title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $progID = filter_input(INPUT_POST, 'program_id', FILTER_VALIDATE_INT);
                $effectiveYear = trim($_POST['effective_year'] ?? date('Y'));
                $isActive = trim($_POST['is_active'] ?? '1');

                if (empty($title) || !$progID) {
                    throw new Exception("Curriculum Title and Associated Program are required.");
                }

                if ($currID) {
                    $sql = "UPDATE curriculum SET CurriculumName = ?, Description = ?, ProgramID = ?, EffectiveYear = ?, IsActive = ? WHERE ID = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssissi", $title, $description, $progID, $effectiveYear, $isActive, $currID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Curriculum Updated', "Updated curriculum: {$title}.");
                    redirectWithMessage('success', "Curriculum successfully updated.", "academics?tab=curriculum&");
                } else {
                    $sql = "INSERT INTO curriculum (CurriculumName, Description, ProgramID, EffectiveYear, IsActive) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssiss", $title, $description, $progID, $effectiveYear, $isActive);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Curriculum Created', "Created curriculum: {$title}.");
                    redirectWithMessage('success', "New curriculum successfully added.", "academics?tab=curriculum&");
                }
            } elseif ($action === 'delete-curriculum') {
                $currID = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
                if ($currID) {
                    $stmt = mysqli_prepare($conn, "DELETE FROM curriculum WHERE ID = ?");
                    mysqli_stmt_bind_param($stmt, "i", $currID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Curriculum Deleted', "Deleted curriculum ID {$currID}.");
                    redirectWithMessage('success', "Curriculum deleted successfully.", "academics?tab=curriculum&");
                }
            }
        }

        // --- 4. SUBJECT CATALOG CRUD ---
        elseif ($category === 'subject') {
            if ($action === 'save-subject') {
                $subjectCode = trim($_POST['subject_code'] ?? '');
                $oldSubjectCode = trim($_POST['old_subject_code'] ?? '');
                $subjectTitle = htmlspecialchars($_POST['subject_title'], ENT_QUOTES);

                if (empty($subjectCode) || empty($subjectTitle)) {
                    throw new Exception("Course Code and Descriptive Title are required.");
                }

                if (!empty($oldSubjectCode)) {
                    $sql = "UPDATE subjects SET SubjectCode = ?, SubjectTitle = ? WHERE SubjectCode = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "sss", $subjectCode, $subjectTitle, $oldSubjectCode);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Subject Updated', "Updated subject: {$subjectCode}.");
                    redirectWithMessage('success', "Subject details updated successfully.", "academics?tab=subject&");
                } else {
                    $sql = "INSERT INTO subjects (SubjectCode, SubjectTitle) VALUES (?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ss", $subjectCode, $subjectTitle);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Subject Created', "Added subject: {$subjectCode}.");
                    redirectWithMessage('success', "New subject successfully added to catalog.", "academics?tab=subject&");
                }
            } elseif ($action === 'delete-subject') {
                $subjectCode = trim($_POST['subject_code'] ?? '');
                if (!empty($subjectCode)) {
                    $stmt = mysqli_prepare($conn, "DELETE FROM subjects WHERE SubjectCode = ?");
                    mysqli_stmt_bind_param($stmt, "s", $subjectCode);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    logActivity($conn, $userID, 'Subject Deleted', "Deleted subject: {$subjectCode}.");
                    redirectWithMessage('success', "Subject deleted successfully.", "academics?tab=subject&");
                }
            }
        }
    } catch (Exception $e) {
        logActivity($conn, $userID, 'Academic Action Error', $e->getMessage());
        redirectWithMessage('error', $e->getMessage(), "academics?tab={$category}");
    }
}

// -------------------------------------------------------------------------
// Fetch Data from Database for Active Views
// -------------------------------------------------------------------------

// Active System Term for Header Badge
$activeTermRes = mysqli_query($conn, "SELECT YearCode, Semester FROM academic_years WHERE IsActive = 1 LIMIT 1");
$activeTermRow = mysqli_fetch_assoc($activeTermRes);
$activeTermText = $activeTermRow ? "{$activeTermRow['YearCode']} : {$activeTermRow['Semester']}" : "None Set";

// 1. Fetch Academic Years
$termsResult = mysqli_query($conn, "SELECT * FROM academic_years ORDER BY YearCode DESC, Semester ASC");
$termsList = [];
while ($r = mysqli_fetch_assoc($termsResult)) { $termsList[] = $r; }

// 2. Fetch Degree Programs (with joined Office/College Name)
$programsSql = "
    SELECT dp.*, o.OfficeCode, o.OfficeName 
    FROM programs dp 
    LEFT JOIN offices o ON dp.CollegeID = o.ID 
    ORDER BY dp.ProgramCode ASC
";
$programsResult = mysqli_query($conn, $programsSql);
$programsList = [];
while ($r = mysqli_fetch_assoc($programsResult)) { $programsList[] = $r; }

// 3. Fetch Curricula (with joined Program Code)
$curriculaSql = "
    SELECT c.*, dp.ProgramCode, dp.ProgramName, o.OfficeName, o.OfficeCode 
    FROM curriculum c 
    LEFT JOIN programs dp ON c.ProgramID = dp.ID 
    LEFT JOIN offices o ON dp.CollegeID = o.ID 
    ORDER BY c.EffectiveYear DESC, c.CurriculumName ASC
";
$curriculaResult = mysqli_query($conn, $curriculaSql);
$curriculaList = [];
echo mysqli_error($conn);
while ($r = mysqli_fetch_assoc($curriculaResult)) { $curriculaList[] = $r; }

// 4. Fetch Subject Catalog
$subjectsResult = mysqli_query($conn, "SELECT * FROM subjects ORDER BY SubjectCode ASC");
$subjectsList = [];
while ($r = mysqli_fetch_assoc($subjectsResult)) { $subjectsList[] = $r; }

// 5. Fetch Active Academic Offices for Dropdowns
$officesResult = mysqli_query($conn, "SELECT ID, OfficeCode, OfficeName FROM offices WHERE Type = 'Academic' AND IsActive = 1 ORDER BY OfficeName ASC");
$academicOfficesList = [];
while ($r = mysqli_fetch_assoc($officesResult)) { $academicOfficesList[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .academics-container { display: flex; flex-direction: column; gap: 20px; }
        .academics-tabs-header { display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px; flex-wrap: wrap; }
        .section-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; display: none; }
        .section-card.active { display: block; }
        .section-header { padding: 16px 20px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .section-header h3 { font-size: 1.05rem; color: var(--primary); margin: 0; display: flex; align-items: center; gap: 8px; }
        .acad-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .acad-table th, .acad-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .acad-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .acad-table tr:hover { background-color: #f8fafc; }
        .curriculum-link, .program-link { font-weight: 700; color: var(--primary); text-decoration: none; }
        .curriculum-link:hover, .program-link:hover { text-decoration: underline; color: var(--primary-hover); }
        .badge-active-term { background-color: #d1fae5; color: #065f46; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .unit-badge { background-color: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 10px; }
        .badge-ge { background-color: #fef3c7; color: #b45309; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; border: 1px solid #fde68a; display: inline-block; margin-left: 5px; }
        .badge-maj { background-color: #c7fed0; color: #09b41d; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; border: 1px solid #fde68a; display: inline-block; margin-left: 5px; }
        .action-btns { display: flex; gap: 6px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 90%; max-width: 600px; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 24px 20px; max-height: 75vh; overflow-y: auto; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        abbr { text-decoration: none; }
        @media (max-width: 600px) { .form-grid-3 { grid-template-columns: 1fr; } }
        .modal-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }

        .enrollment-alert-banner { padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .enrollment-alert-banner.open { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .enrollment-alert-banner.closed { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Academic & Curriculum Management</h2>
                    <p class="text-muted">Configure academic terms, degree programs, curricula structures, and subject catalogs.</p>
                </div>
                <div class="application-id-badge">
                    <span>Active Term:</span> <strong><?= htmlspecialchars($activeTermText) ?></strong>
                </div>
            </div>

            <div class="academics-container">

                <!-- Tabs Navigation Header -->
                <div class="academics-tabs-header">
                    <button type="button" id="tabTermBtn" class="btn <?= $currentTab === 'term' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" onclick="switchAcadTab('term')">
                        <i class="fa-solid fa-calendar-days"></i> Academic Year & Semester
                    </button>
                    <button type="button" id="tabProgramBtn" class="btn <?= $currentTab === 'program' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" onclick="switchAcadTab('program')">
                        <i class="fa-solid fa-graduation-cap"></i> Degree Programs
                    </button>
                    <button type="button" id="tabCurriculumBtn" class="btn <?= $currentTab === 'curriculum' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" onclick="switchAcadTab('curriculum')">
                        <i class="fa-solid fa-book-bookmark"></i> Curricula
                    </button>
                    <button type="button" id="tabSubjectBtn" class="btn <?= $currentTab === 'subject' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" onclick="switchAcadTab('subject')">
                        <i class="fa-solid fa-book-open"></i> Subject Catalog
                    </button>
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

                <!-- 1. ACADEMIC YEAR & SEMESTER SECTION -->
                <div id="sectionTerm" class="section-card <?= $currentTab === 'term' ? 'active' : '' ?>">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-calendar-days" style="color: var(--primary-accent);"></i> Academic Calendar Terms</h3>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAcadModal('term', 'add')">
                            <i class="fa-solid fa-plus"></i> Add Academic Term
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="acad-table">
                            <thead>
                                <tr>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Enrollment Status</th>
                                    <th>System State</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($termsList)): ?>
                                    <?php foreach ($termsList as $t): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($t['YearCode']) ?></strong></td>
                                            <td><?= htmlspecialchars($t['Semester']) ?></td>
                                            <td>
                                                <span style="color: <?= $t['EnrollmentStatus'] === 'Open' ? '#065f46' : '#991b1b' ?>; font-weight: bold;">
                                                    <?= htmlspecialchars($t['EnrollmentStatus']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($t['IsActive'] == 1): ?>
                                                    <span class="badge-active-term"><i class="fa-solid fa-circle-check"></i> Active Term</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Archived / Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openAcadModal('term', 'edit', <?= htmlspecialchars(json_encode($t)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    <form action="academics?tab=term&" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this term?');">
                                                        <input type="hidden" name="category" value="term">
                                                        <input type="hidden" name="action" value="delete-term">
                                                        <input type="hidden" name="term_id" value="<?= $t['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No academic terms found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. DEGREE PROGRAMS SECTION -->
                <div id="sectionProgram" class="section-card <?= $currentTab === 'program' ? 'active' : '' ?>">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-graduation-cap" style="color: var(--primary-accent);"></i> Degree Programs</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAcadModal('program', 'add')">
                            <i class="fa-solid fa-plus"></i> Add Degree Program
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="acad-table">
                            <thead>
                                <tr>
                                    <th>Program Code</th>
                                    <th>Program Descriptive Title</th>
                                    <th>Department / College</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($programsList)): ?>
                                    <?php foreach ($programsList as $p): 
                                        $deptLabel = !empty($p['OfficeName']) ? "{$p['OfficeName']} ({$p['OfficeCode']})" : 'Unassigned';
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="programs?id=<?= $p['ID'] ?>" class="program-link"><?= htmlspecialchars($p['ProgramCode']) ?></a>
                                            </td>
                                            <td>
                                                <a href="programs?id=<?= $p['ID'] ?>" class="program-link"><?= htmlspecialchars($p['ProgramName']) ?></a>
                                            </td>
                                            <td><?= htmlspecialchars($deptLabel) ?></td>
                                            <td><span style="color: <?= $p['IsActive'] ? '#065f46' : '#991b1b' ?>; font-weight: bold;"><?= htmlspecialchars($p['IsActive'] ? 'Active' : 'Defunct') ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openAcadModal('program', 'edit', <?= htmlspecialchars(json_encode($p)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    <form action="academics?tab=program" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this program?');">
                                                        <input type="hidden" name="category" value="program">
                                                        <input type="hidden" name="action" value="delete-program">
                                                        <input type="hidden" name="program_id" value="<?= $p['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No degree programs recorded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. CURRICULA SECTION -->
                <div id="sectionCurriculum" class="section-card <?= $currentTab === 'curriculum' ? 'active' : '' ?>">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-book-bookmark" style="color: var(--primary-accent);"></i> Program Curricula</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openAcadModal('curriculum', 'add')">
                            <i class="fa-solid fa-plus"></i> Add Curriculum
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="acad-table">
                            <thead>
                                <tr>
                                    <th>Curriculum Title</th>
                                    <th>Description</th>
                                    <th>Associated Program</th>
                                    <th>Effective Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($curriculaList)): ?>
                                    <?php foreach ($curriculaList as $c): ?>
                                        <?php
                                        $cSql = "SELECT SUM(Units) AS TotalUnits FROM curriculum_subjects WHERE CountTowardsTotal = 1 AND CurriculumID = ?";
                                        $cStmt = mysqli_prepare($conn, $cSql);
                                        mysqli_stmt_bind_param($cStmt, 'i', $c['ID']);
                                        mysqli_stmt_execute($cStmt);
                                        $cResult = mysqli_stmt_get_result($cStmt);
                                        $countUnits = mysqli_fetch_assoc($cResult)['TotalUnits'] ?? 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="curriculum?id=<?= $c['ID'] ?>" class="curriculum-link">
                                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i> <?= htmlspecialchars($c['CurriculumName']) ?>
                                                </a>
                                                <!-- <p style="font-size: 12px;">No. of total units: <?= $countUnits ?></p> -->
                                            </td>
                                            <td><?= htmlspecialchars($c['Description'] ?? '') ?></td>
                                            <td>
                                                <a href="programs?id=<?= $c['ProgramID'] ?>" class="program-link">
                                                    <abbr title="<?= $c['ProgramName'] ?>"><?= htmlspecialchars($c['ProgramCode'] ?? 'N/A') ?></abbr> (<abbr title="<?= $c['OfficeName'] ?>"><?= htmlspecialchars($c['OfficeCode'] ?? 'Unit') ?></abbr>)
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($c['EffectiveYear']) ?></td>
                                            <td><?= htmlspecialchars($c['IsActive'] == 1 ? 'Active' : 'Inactive') ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openAcadModal('curriculum', 'edit', <?= htmlspecialchars(json_encode($c)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    <form action="academics?tab=curriculum" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this curriculum?');">
                                                        <input type="hidden" name="category" value="curriculum">
                                                        <input type="hidden" name="action" value="delete-curriculum">
                                                        <input type="hidden" name="curriculum_id" value="<?= $c['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No curricula records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. SUBJECT CATALOG SECTION -->
                <div id="sectionSubject" class="section-card <?= $currentTab === 'subject' ? 'active' : '' ?>">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-book-open" style="color: var(--primary-accent);"></i> Subject Catalog</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="subjectSearch" class="form-control" placeholder="Search subject code or title..." style="padding: 6px 12px; font-size: 0.85rem; width: 220px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAcadModal('subject', 'add')">
                                <i class="fa-solid fa-plus"></i> Add Subject
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="acad-table" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Descriptive Title</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="subjectsTableBody">
                                <?php if (!empty($subjectsList)): ?>
                                    <?php foreach ($subjectsList as $s): 
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($s['SubjectCode']) ?></strong></td>
                                            <td><?= htmlspecialchars_decode($s['SubjectTitle']) ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openAcadModal('subject', 'edit', <?= htmlspecialchars(json_encode($s)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    <form action="academics?tab=subject" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                                        <input type="hidden" name="category" value="subject">
                                                        <input type="hidden" name="action" value="delete-subject">
                                                        <input type="hidden" name="subject_code" value="<?= htmlspecialchars($s['SubjectCode']) ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 25px; color: var(--text-muted);">No subjects found in catalog.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- GENERAL DYNAMIC MODAL -->
    <div id="acadModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-gear"></i> Management Form</h3>
                <button type="button" class="modal-close-btn" onclick="closeAcadModal()">&times;</button>
            </div>
            <form id="acadForm" action="academics" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save-record">
                    <input type="hidden" name="category" id="modalCategory" value="">
                    
                    <!-- Dynamic form fields populated via JS -->
                    <div id="modalFormFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAcadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php include "../includes/snackbar.php"; ?>

    <script>
        const academicOffices = <?= json_encode($academicOfficesList) ?>;
        const degreePrograms = <?= json_encode($programsList) ?>;

        const acadModal = document.getElementById('acadModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalCategory = document.getElementById('modalCategory');
        const modalFormFields = document.getElementById('modalFormFields');
        const subjectSearch = document.getElementById('subjectSearch');

        // Switch Tabs using URL query parameters for persistent backend state
        function switchAcadTab(tab) {
            window.location.href = `academics?tab=${tab}`;
        }

        // Open Dynamic Modal with form generation per section
        function openAcadModal(category, mode, data = null) {
            modalCategory.value = category;
            modalFormFields.innerHTML = '';
            document.querySelector('#acadForm input[name="action"]').value = `save-${category}`;

            if (category === 'term') {
                modalTitle.innerHTML = `<i class="fa-solid fa-calendar-days"></i> ${mode === 'add' ? 'Add Academic Term' : 'Edit Academic Term'}`;
                const termID = data ? data.ID : '';
                const yearCode = data ? data.YearCode : '2026-2027';
                const semester = data ? data.Semester : '1st Semester';
                const status = data ? data.EnrollmentStatus : 'Open';
                const isSystemActive = data ? (data.IsActive == 1) : false;

                modalFormFields.innerHTML = `
                    <input type="hidden" name="term_id" value="${termID}">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Academic Year *</label>
                        <input type="text" name="academic_year" class="form-control" value="${escapeHtml(yearCode)}" placeholder="e.g. AY 2026-2027" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Semester *</label>
                        <select name="semester" class="form-control" required>
                            <option value="1st Semester" ${semester === '1st Semester' ? 'selected' : ''}>1st Semester</option>
                            <option value="2nd Semester" ${semester === '2nd Semester' ? 'selected' : ''}>2nd Semester</option>
                            <option value="Summer" ${semester === 'Summer' ? 'selected' : ''}>Summer</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Enrollment Status *</label>
                        <select name="enrollment_status" class="form-control" required>
                            <option value="Open" ${status === 'Open' ? 'selected' : ''}>Open</option>
                            <option value="Closed" ${status === 'Closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-bottom: 0;">
                        <input type="checkbox" name="is_system_active" id="chkActiveTerm" value="1" style="width: 18px; height: 18px;" ${isSystemActive ? 'checked' : ''}>
                        <label for="chkActiveTerm" style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">Set as Active System Term</label>
                    </div>
                `;
            } else if (category === 'program') {
                modalTitle.innerHTML = `<i class="fa-solid fa-graduation-cap"></i> ${mode === 'add' ? 'Add Degree Program' : 'Edit Degree Program'}`;
                const progID = data ? data.ID : '';
                const code = data ? data.ProgramCode : '';
                const title = data ? data.ProgramName : '';
                const officeID = data ? data.CollegeID : '';
                const status = data ? data.IsActive : 1;

                let officeOptions = `<option value="">-- Unassigned --</option>`;
                academicOffices.forEach(o => {
                    officeOptions += `<option value="${o.ID}" ${officeID == o.ID ? 'selected' : ''}>${escapeHtml(o.OfficeName)} (${escapeHtml(o.OfficeCode)})</option>`;
                });

                modalFormFields.innerHTML = `
                    <input type="hidden" name="program_id" value="${progID}">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Program Code *</label>
                        <input type="text" name="program_code" class="form-control" value="${escapeHtml(code)}" placeholder="e.g. BSIT" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Descriptive Title *</label>
                        <input type="text" name="program_title" class="form-control" value="${escapeHtml(title)}" placeholder="e.g. Bachelor of Science in Information Technology" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Department / College Unit</label>
                        <select name="office_id" class="form-control">${officeOptions}</select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Status *</label>
                        <select name="is_active" class="form-control" required>
                            <option value="1" ${status === 1 ? 'selected' : ''}>Active</option>
                            <option value="0" ${status === 0 ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                `;
            } else if (category === 'curriculum') {
                modalTitle.innerHTML = `<i class="fa-solid fa-book-bookmark"></i> ${mode === 'add' ? 'Add Curriculum' : 'Edit Curriculum'}`;
                const currID = data ? data.ID : '';
                const title = data ? data.CurriculumName : '';
                const desc = data ? data.Description : '';
                const progID = data ? data.ProgramID : '';
                const year = data ? data.EffectiveYear : '2026';
                const status = data ? data.IsActive : 1;

                let progOptions = `<option value="">-- Select Degree Program --</option>`;
                degreePrograms.forEach(p => {
                    progOptions += `<option value="${p.ID}" ${progID == p.ID ? 'selected' : ''}>${escapeHtml(p.ProgramCode)} - ${escapeHtml(p.ProgramName)}</option>`;
                });

                modalFormFields.innerHTML = `
                    <input type="hidden" name="curriculum_id" value="${currID}">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Curriculum Title *</label>
                        <input type="text" name="curriculum_title" class="form-control" value="${escapeHtml(title)}" placeholder="e.g. BSIT Curriculum 2026" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Overview or CMO reference details...">${escapeHtml(desc)}</textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Associated Degree Program *</label>
                        <select name="program_id" class="form-control" required>${progOptions}</select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Effective Year *</label>
                        <input type="text" name="effective_year" class="form-control" value="${escapeHtml(year)}" placeholder="e.g. 2026" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Status *</label>
                        <select name="is_active" class="form-control" required>
                            <option value="1" ${status == 1 ? 'selected' : ''}>Active</option>
                            <option value="0" ${status == 0 ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                `;
            } else if (category === 'subject') {
                modalTitle.innerHTML = `<i class="fa-solid fa-book-open"></i> ${mode === 'add' ? 'Add Subject' : 'Edit Subject'}`;
                const code = data ? data.SubjectCode : '';
                const title = data ? data.SubjectTitle : '';

                modalFormFields.innerHTML = `
                    <input type="hidden" name="old_subject_code" value="${escapeHtml(code)}">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Descriptive Title *</label>
                        <input type="text" name="subject_title" class="form-control" value="${escapeHtml(title)}" placeholder="e.g. Computer Programming 1" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Course Code *</label>
                        <input type="text" name="subject_code" class="form-control" value="${escapeHtml(code)}" placeholder="e.g. IT 111" required>
                    </div>
                `;
            }

            acadModal.classList.add('active');
        }

        function closeAcadModal() {
            acadModal.classList.remove('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Subject Catalog Client-side Search
        document.addEventListener('DOMContentLoaded', () => {
            if (subjectSearch) {
                subjectSearch.addEventListener('input', (e) => {
                    const query = e.target.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#subjectsTableBody tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(query) ? '' : 'none';
                    });
                });
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>