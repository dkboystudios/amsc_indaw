<?php
// Start output buffering immediately to prevent stray whitespace or warnings from breaking execution
ob_start();
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$programId = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Fetch Program
$progSql = "SELECT p.*, o.* FROM programs p INNER JOIN offices o ON p.CollegeID = o.ID WHERE p.ID = ? LIMIT 1";
$progStmt = mysqli_prepare($conn, $progSql);
mysqli_stmt_bind_param($progStmt, 'i', $programId);
mysqli_stmt_execute($progStmt);
$progRes = mysqli_stmt_get_result($progStmt);
$program = mysqli_fetch_assoc($progRes);
mysqli_stmt_close($progStmt);

if (!$program) {
    redirectWithMessage('error', 'Program not found.', 'academics?tab=program&');
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $action = $_POST['action'] ?? '';

    try {
        // EDIT & DELETE PROGRAM LOGIC
        if ($action === 'edit_program') {
            $programCode = trim($_POST['program_code'] ?? '');
            $programName = trim($_POST['program_title'] ?? '');
            $collegeID = filter_input(INPUT_POST, 'college', FILTER_VALIDATE_INT);
            $isActive = $_POST['is_active'];

            if (empty($programCode) || empty($programName) || (empty($collegeID) || $collegeID === 0)) {
                throw new Exception("Incomplete program information for Program #{$programId}.");
            }

            $sql = "UPDATE programs SET ProgramCode = ?, ProgramName = ?, CollegeID = ?, IsActive = ? WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssiii', $programCode, $programName, $collegeID, $isActive, $programId);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Program Updated', 'Updated program: ' . $programName);
            redirectWithMessage('success', 'Program successfully updated.', "programs?id=$programId&");
        } else if ($action === 'delete_program') {
            $sql = "DELETE FROM programs WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $programId);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Program Deleted', "Deleted program ID {$programId}.");
            redirectWithMessage('success', 'Program deleted successfully.', 'academics?tab=program&');
        }
        
        // CRUD LOGIC FOR CURRICULUM
        else if ($action === 'add_curriculum') {
            $curriculumName = trim($_POST['curriculum_title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $effectiveYear = trim($_POST['effective_year'] ?? '');
            $isActive = $_POST['is_active'] ?? 1;

            if (empty($curriculumName)) {
                throw new Exception("Curriculum name missing (" . $program['ProgramName'] . ")");
            }

            $sql = "INSERT INTO curriculum (ProgramID, CurriculumName, Description, EffectiveYear, IsActive) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssi", $programId, $curriculumName, $description, $effectiveYear, $isActive);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'New Curriculum Added', "Curriculum added successfully ({$curriculumName}).");
            redirectWithMessage('success', 'New curriculum successfully added.', "programs?id=$programId&");
        } else if ($action === 'edit_curriculum') {
            $curriculumID = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            $curriculumName = trim($_POST['curriculum_title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $effectiveYear = trim($_POST['effective_year'] ?? '');
            $isActive = $_POST['is_active'] ?? 1;

            if (empty($curriculumName)) {
                throw new Exception("Curriculum name missing (" . $program['ProgramName'] . ")");
            }

            $sql = "UPDATE curriculum SET CurriculumName = ?, Description = ?, EffectiveYear = ?, IsActive = ? WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssii", $curriculumName, $description, $effectiveYear, $isActive, $curriculumID);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Curriculum Updated', "Curriculum updated successfully: ({$curriculumName}).");
            redirectWithMessage('success', 'Curriculum successfully updated.', "programs?id=$programId&");
        } else if ($action === 'delete_curriculum') {
            $curriculumID = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);

            $sql = "DELETE FROM curriculum WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $curriculumID);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Curriculum Deleted', "Curriculum deleted successfully (ID: {$curriculumID}).");
            redirectWithMessage('success', 'Curriculum successfully deleted.', "programs?id={$programId}&");
        }
        
        // BLOCK SECTION & STUDENT ASSIGNMENT ACTIONS
        else if ($action === 'create_section_pool') {
            $sectionName = trim($_POST['section_name'] ?? '');
            $academicYearID = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);
            $maxCapacity = filter_input(INPUT_POST, 'max_capacity', FILTER_VALIDATE_INT) ?: 40;

            if (empty($sectionName) || empty($academicYearID)) {
                throw new Exception("Section name and academic year are required.");
            }

            $sql = "INSERT INTO sections (AcademicYearID, SectionCode, MaxCapacity, CurrentEnrolled) VALUES (?, ?, ?, 0)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isi', $academicYearID, $sectionName, $maxCapacity);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Section Pool Created', "Created new section pool: {$sectionName}");
            redirectWithMessage('success', 'Section pool successfully created.', "programs?id=$programId&");
        } else if ($action === 'remove_block_section') {
            $sectionID = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
            $sql = "DELETE FROM sections WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $sectionID);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Section Removed', "Removed section ID {$sectionID}.");
            redirectWithMessage('success', 'Section removed successfully.', "programs?id=$programId&");
        } else if ($action === 'remove_program_student') {
            $studentID = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            $sql = "DELETE FROM students WHERE StudentID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $studentID);
            mysqli_stmt_execute($stmt);

            logActivity($conn, $userID, 'Student Removed from Program', "Removed student ID {$studentID} from program.");
            redirectWithMessage('success', 'Student successfully removed from program.', "programs?id=$programId&");
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        logActivity($conn, $userID, "Program Action Failed", $msg);
        redirectWithMessage('error', $msg, "programs?id={$programId}&");
    }
}

// Fetch Curricula
$curriculaSql = "SELECT * FROM curriculum WHERE ProgramID = $programId ORDER BY EffectiveYear DESC, CurriculumName ASC";
$curriculaResult = mysqli_query($conn, $curriculaSql);
$curriculaList = [];
while ($r = mysqli_fetch_assoc($curriculaResult)) { $curriculaList[] = $r; }

$pageTitle = htmlspecialchars($program['ProgramName']) . " - AMSC InnDaw";

// Fetch list of colleges/offices
$collegeSql = "SELECT * FROM offices WHERE Type = 'Academic' AND IsActive = 1 ORDER BY OfficeName";
$collegeRes = mysqli_query($conn, $collegeSql);
$colleges = [];
while ($row = mysqli_fetch_assoc($collegeRes)) { $colleges[] = $row; }

// Fetch Academic Years for section creation
$aySql = "SELECT * FROM academic_years ORDER BY YearCode DESC, Semester DESC";
$ayRes = mysqli_query($conn, $aySql);
$academicYears = [];
while ($row = mysqli_fetch_assoc($ayRes)) { $academicYears[] = $row; }

// Fetch sections (for display & assignment)
$sectionsSql = "SELECT s.*, ay.YearCode, ay.Semester FROM sections s INNER JOIN academic_years ay ON s.AcademicYearID = ay.ID ORDER BY s.ID DESC";
$sectionsRes = mysqli_query($conn, $sectionsSql);
$sectionsList = [];
while ($row = mysqli_fetch_assoc($sectionsRes)) { $sectionsList[] = $row; }

// Fetch students enrolled in this program
$studentsSql = "SELECT st.*, a.FirstName, a.LastName, a.MiddleName, a.ExtName, c.CurriculumName 
                FROM students st 
                INNER JOIN applicants a ON st.ApplicantID = a.ApplicantID 
                INNER JOIN curriculum c ON st.CurriculumID = c.ID 
                WHERE st.ProgramID = ?";
$stuStmt = mysqli_prepare($conn, $studentsSql);
mysqli_stmt_bind_param($stuStmt, 'i', $programId);
mysqli_stmt_execute($stuStmt);
$stuRes = mysqli_stmt_get_result($stuStmt);
$programStudents = [];
while ($row = mysqli_fetch_assoc($stuRes)) { $programStudents[] = $row; }
mysqli_stmt_close($stuStmt);

// Fetch all unassigned or available students for roster addition
$allStudentsSql = "SELECT st.StudentID, st.StudentNumber, a.FirstName, a.LastName FROM students st INNER JOIN applicants a ON st.ApplicantID = a.ApplicantID ORDER BY a.LastName";
$allStuRes = mysqli_query($conn, $allStudentsSql);
$allStudentsList = [];
while ($row = mysqli_fetch_assoc($allStuRes)) { $allStudentsList[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        /* --- Program & Section Management Layout Styles --- */
        .program-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Top Program Info & Action Card */
        .program-info-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .program-info-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-status.active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-status.inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Block Section Cards & Tables */
        .section-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .section-card-header {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-card-header h3 {
            font-size: 1.05rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .program-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .program-table th, .program-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .program-table th {
            background-color: #f8fafc;
            color: var(--primary);
            font-weight: 700;
        }

        .program-table tr:hover {
            background-color: #f8fafc;
        }

        .enrolled-badge {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-block;
        }

        .capacity-badge {
            background-color: #f1f5f9;
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-block;
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            width: 90%;
            max-width: 700px;
            border-radius: 10px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            padding: 16px 20px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            margin: 0;
            color: #ffffff;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 24px 20px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .curriculum-link, .program-link { font-weight: 700; color: var(--primary); text-decoration: none; }
        .curriculum-link:hover, .program-link:hover { text-decoration: underline; color: var(--primary-hover); }

        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .student-roster-add-box {
            display: flex;
            gap: 10px;
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
            align-items: center;
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <!-- Admin Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Degree Program & Block Section Management</h2>
                    <p class="text-muted">Manage degree program details, college affiliation, and assigned student block sections.</p>
                </div>
                <div>
                    <a href="academics?tab=program&" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left"></i> Back to Programs
                    </a>
                </div>
            </div>

            <div class="program-container">

                <!-- PROGRAM OVERVIEW & EDIT CARD -->
                <div class="program-info-card">
                    <div class="program-info-header">
                        <div>
                            <span class="badge-status <?= $program['IsActive'] == 1 ? 'active' : 'inactive' ?>" id="programStatusBadge"><i class="fa-solid fa-circle-check"></i> <?= $program['IsActive'] == 1 ? 'Active' : 'Inactive' ?> Program</span>
                            <h3 style="color: var(--primary); margin: 6px 0 2px 0; font-size: 1.3rem;">
                                <span id="displayProgramCode"><?= htmlspecialchars($program['ProgramCode'] ?? '') ?></span> - <span id="displayProgramTitle"><?= htmlspecialchars($program['ProgramName'] ?? 'N/A') ?></span>
                            </h3>
                            <p class="text-muted" id="displayDepartment" style="margin: 0; font-size: 0.9rem;"><?= htmlspecialchars($program['OfficeName'] ?? 'N/A') ?> (<?= htmlspecialchars($program['OfficeCode']) ?>)</p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openProgramModal()">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Program
                            </button>
                            <form action="programs?id=<?= $programId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this program?');">
                                <input type="hidden" name="action" value="delete_program">
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.9rem;">
                        <div><strong>Program ID:</strong> <span>PRG-<?= date('Y', strtotime($program['CreatedAt'])) ?>-<?= $program['ID']; ?></span></div>
                        <div><strong>Date created:</strong> <span id="displayStatusCode"><?= date('F d, Y', strtotime($program['CreatedAt'])) ?></span></div>
                        <div><strong>Total Block Sections:</strong> <span><?= count($sectionsList) ?> Sections</span></div>
                    </div>
                </div>

                <!-- CURRICULUM SECTION -->
                <div class="section-card">
                    <div class="section-card-header">
                        <h3><i class="fa-solid fa-layer-group" style="color: var(--primary-accent);"></i> Curricula Sections</h3>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCurrModal('add')">
                                <i class="fa-solid fa-plus"></i> Add Curriculum
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="program-table">
                            <thead>
                                <tr>
                                    <th>Curriculum Title</th>
                                    <th>Description</th>
                                    <th>Effective Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="curriculaTableBody">
                               <?php if (!empty($curriculaList)): ?>
                                    <?php foreach ($curriculaList as $c): ?>
                                        <tr>
                                            <td>
                                                <a href="curriculum?id=<?= $c['ID'] ?>&" class="curriculum-link">
                                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i> <?= htmlspecialchars($c['CurriculumName']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($c['Description'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($c['EffectiveYear']) ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openCurrModal('edit', <?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    <form action="programs?id=<?= $programId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this curriculum?');">
                                                        <input type="hidden" name="action" value="delete_curriculum">
                                                        <input type="hidden" name="curriculum_id" value="<?= $c['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 25px; color: var(--text-muted);">No curricula records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- BLOCK SECTIONS SECTION -->
                <div class="section-card">
                    <div class="section-card-header">
                        <h3><i class="fa-solid fa-layer-group" style="color: var(--primary-accent);"></i> Program Block Sections</h3>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateSectionModal()">
                                <i class="fa-solid fa-plus"></i> Create New Section Pool
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="program-table">
                            <thead>
                                <tr>
                                    <th>Section Name</th>
                                    <th>Academic Year</th>
                                    <th>Max Capacity</th>
                                    <th>Current Enrolled</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="blockSectionsTableBody">
                                <?php if (!empty($sectionsList)): ?>
                                    <?php foreach ($sectionsList as $sec): ?>
                                        <tr id="sectionRow_<?= $sec['ID'] ?>">
                                            <td><strong><?= htmlspecialchars($sec['SectionCode']) ?></strong></td>
                                            <td><?= htmlspecialchars($sec['YearCode']) ?> (<?= htmlspecialchars($sec['Semester']) ?>)</td>
                                            <td><span class="capacity-badge"><?= $sec['MaxCapacity'] ?> Students</span></td>
                                            <td><span class="enrolled-badge" id="enrolledCountBadge_<?= $sec['ID'] ?>"><i class="fa-solid fa-user-group"></i> <span class="enrolled-num"><?= $sec['CurrentEnrolled'] ?></span> Enrolled</span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openSectionRosterModal(<?= $sec['ID'] ?>, '<?= htmlspecialchars($sec['SectionCode'], ENT_QUOTES) ?>', <?= $sec['MaxCapacity'] ?>)" title="View & Manage Enrolled Students">
                                                        <i class="fa-solid fa-users"></i> Manage Students
                                                    </button>
                                                    <form action="programs?id=<?= $programId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this section?');">
                                                        <input type="hidden" name="action" value="remove_block_section">
                                                        <input type="hidden" name="section_id" value="<?= $sec['ID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No sections created or assigned yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PROGRAM ENROLLED STUDENTS SECTION -->
                <div class="section-card">
                    <div class="section-card-header">
                        <h3><i class="fa-solid fa-user-graduate" style="color: var(--primary-accent);"></i> Students Enrolled in <?= htmlspecialchars($program['ProgramName']) ?></h3>
                        <div class="application-id-badge">
                            <span>Total Enrolled:</span> <strong><?= count($programStudents) ?> Students</strong>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="program-table" id="programStudentsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Curriculum</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="programStudentsTableBody">
                                <?php if (!empty($programStudents)): ?>
                                    <?php foreach ($programStudents as $stu): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($stu['FirstName'] . ' ' . $stu['LastName']) ?></strong></td>
                                            <td><span style="color: var(--primary); font-weight: bold;"><?= htmlspecialchars($stu['StudentNumber']) ?></span></td>
                                            <td><?= htmlspecialchars($stu['CurriculumName']) ?></td>
                                            <td><span class="badge-status active"><?= htmlspecialchars($stu['EnrollmentStatus']) ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <form action="programs?id=<?= $programId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Remove student from program?');">
                                                        <input type="hidden" name="action" value="remove_program_student">
                                                        <input type="hidden" name="student_id" value="<?= $stu['StudentID'] ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove from program">
                                                            <i class="fa-solid fa-user-minus"></i> Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 25px; color: var(--text-muted);">No students enrolled in this program yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- STUDENT ROSTER & SECTION MANAGEMENT MODAL -->
    <div id="sectionRosterModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="rosterModalTitle"><i class="fa-solid fa-users"></i> Section Student Roster</h3>
                <button type="button" class="modal-close-btn" onclick="closeSectionRosterModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Add Student Form (Handled via AJAX) -->
                <div class="student-roster-add-box">
                    <input type="hidden" id="modalSectionIDInput" value="">
                    <select id="modalStudentSelect" class="form-control" style="flex: 1;" required>
                        <option value="">-- Choose Student to Add to Block Section --</option>
                        <?php foreach ($allStudentsList as $ast): ?>
                            <option value="<?= $ast['StudentID'] ?>"><?= htmlspecialchars($ast['FirstName'] . ' ' . $ast['LastName'] . ' (' . $ast['StudentNumber'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addStudentToSectionAJAX()">
                        <i class="fa-solid fa-user-plus"></i> Add Student
                    </button>
                </div>

                <!-- Enrolled Roster Table -->
                <table class="program-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalRosterTableBody">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSectionRosterModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- EDIT PROGRAM MODAL -->
    <div id="programModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-graduation-cap"></i> Edit Degree Program</h3>
                <button type="button" class="modal-close-btn" onclick="closeProgramModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="programs?id=<?= $programId ?>">
                    <input type="hidden" name="action" value="edit_program">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Program Code *</label>
                        <input type="text" name="program_code" id="editProgramCode" class="form-control" value="<?= htmlspecialchars($program['ProgramCode']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Descriptive Title *</label>
                        <input type="text" name="program_title" id="editProgramTitle" class="form-control" value="<?= htmlspecialchars($program['ProgramName']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Department / College *</label>
                        <select name="college" id="editDepartment" class="form-control" required>
                            <?php foreach($colleges as $col): ?>
                                <option value="<?= $col['ID'] ?>" <?= $program['CollegeID'] == $col['ID'] ? 'selected' : '' ?>><?= htmlspecialchars($col['OfficeName']) ?> (<?= htmlspecialchars($col['OfficeCode']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Program Status *</label>
                        <select name="is_active" id="editProgramStatus" class="form-control" required>
                            <option value="1" <?= $program['IsActive'] == 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $program['IsActive'] == 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="modal-footer" style="margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeProgramModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- STANDALONE CREATE SECTION POOL MODAL -->
    <div id="createSectionModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-layer-group"></i> Create New Section Pool</h3>
                <button type="button" class="modal-close-btn" onclick="closeCreateSectionModal()">&times;</button>
            </div>
            <form action="programs?id=<?= $programId ?>" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_section_pool">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Section Name *</label>
                        <input type="text" name="section_name" class="form-control" placeholder="e.g. BSIT 1-A" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Academic Year *</label>
                        <select name="academic_year_id" class="form-control" required>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?= $ay['ID'] ?>"><?= htmlspecialchars($ay['YearCode'] . ' - ' . $ay['Semester']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Max Capacity *</label>
                        <input type="number" name="max_capacity" class="form-control" value="40" min="10" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateSectionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Section</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CREATE/EDIT CURRICULUM MODAL -->
    <div id="currModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-gear"></i> Management Form</h3>
                <button type="button" class="modal-close-btn" onclick="closeCurrModal()">&times;</button>
            </div>
            <form id="currForm" action="programs?id=<?= $programId ?>" method="POST">
                <div class="modal-body">
                    <div id="modalFormFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCurrModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php include "../includes/snackbar.php"; ?>

    <!-- Vanilla JS Interactivity & AJAX Roster Management -->
    <script>
        const programModal = document.getElementById('programModal');
        const createSectionModal = document.getElementById('createSectionModal');
        const sectionRosterModal = document.getElementById('sectionRosterModal');
        const currModal = document.getElementById('currModal');

        let currentActiveSectionID = null;

        function openSectionRosterModal(sectionID, sectionName, capacity) {
            currentActiveSectionID = sectionID;
            document.getElementById('rosterModalTitle').innerHTML = `<i class="fa-solid fa-users"></i> Managing Students: ${sectionName} (Cap: ${capacity})`;
            document.getElementById('modalSectionIDInput').value = sectionID;
            
            loadSectionRoster(sectionID);
            sectionRosterModal.classList.add('active');
        }

        function loadSectionRoster(sectionID) {
            const rosterBody = document.getElementById('modalRosterTableBody');
            rosterBody.innerHTML = `<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Loading roster...</td></tr>`;

            fetch(`../api/get_section_students?section_id=${sectionID}`)
                .then(res => res.json())
                .then(data => {
                    renderRosterTable(data);
                    updateSectionEnrolledCountBadge(sectionID, data.length);
                })
                .catch(err => {
                    rosterBody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 20px; color: var(--text-muted);">Failed to load roster.</td></tr>`;
                });
        }

        function renderRosterTable(data) {
            const rosterBody = document.getElementById('modalRosterTableBody');
            if (data && data.length > 0) {
                rosterBody.innerHTML = '';
                data.forEach(stu => {
                    rosterBody.innerHTML += `
                        <tr id="rosterRow_${stu.MappingID}">
                            <td><strong>${escapeHtml(stu.FirstName)} ${escapeHtml(stu.LastName)}</strong></td>
                            <td><span style="color: var(--primary); font-weight: bold;">${escapeHtml(stu.StudentNumber)}</span></td>
                            <td>
                                <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="removeStudentFromSectionAJAX(${stu.MappingID})">
                                    <i class="fa-solid fa-user-minus"></i> Remove
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                rosterBody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 20px; color: var(--text-muted);">No students enrolled in this block section yet.</td></tr>`;
            }
        }

        function addStudentToSectionAJAX() {
            const sectionID = document.getElementById('modalSectionIDInput').value;
            const studentID = document.getElementById('modalStudentSelect').value;

            if (!studentID) {
                alert("Please select a student to add.");
                return;
            }

            const formData = new FormData();
            formData.append('section_id', sectionID);
            formData.append('student_id', studentID);

            fetch('../api/add_student_to_section', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    document.getElementById('modalStudentSelect').value = "";
                    loadSectionRoster(sectionID);
                } else {
                    alert(response.error || "Error adding student to section.");
                }
            })
            .catch(err => {
                alert("Error adding student to section.");
            });
        }

        function removeStudentFromSectionAJAX(mappingID) {
            if (!confirm("Are you sure you want to remove this student from the section?")) return;

            const sectionID = currentActiveSectionID;
            const formData = new FormData();
            formData.append('mapping_id', mappingID);
            formData.append('section_id', sectionID);

            fetch('../api/remove_student_from_section', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    loadSectionRoster(sectionID);
                } else {
                    alert(response.error || "Error removing student from section.");
                }
            })
            .catch(err => {
                alert("Error removing student from section.");
            });
        }

        function updateSectionEnrolledCountBadge(sectionID, count) {
            const badgeNum = document.querySelector(`#enrolledCountBadge_${sectionID} .enrolled-num`);
            if (badgeNum) {
                badgeNum.textContent = count;
            }
        }

        function closeSectionRosterModal() {
            sectionRosterModal.classList.remove('active');
        }

        // Program Details Modal
        function openProgramModal() {
            programModal.classList.add('active');
        }

        function closeProgramModal() {
            programModal.classList.remove('active');
        }

        // Standalone Create Section Modal
        function openCreateSectionModal() {
            createSectionModal.classList.add('active');
        }

        function closeCreateSectionModal() {
            createSectionModal.classList.remove('active');
        }

        function openCurrModal(mode, data = null) {
            const modalFormFields = document.getElementById('modalFormFields');
            document.getElementById('modalTitle').innerHTML = `<i class="fa-solid fa-book-bookmark"></i> ${mode === 'add' ? 'Add Curriculum' : 'Edit Curriculum'}`;
            
            const currID = data ? data.ID : '';
            const title = data ? data.CurriculumName : '';
            const desc = data ? data.Description : '';
            const year = data ? data.EffectiveYear : '2026';
            const action = mode === 'add' ? 'add_curriculum' : 'edit_curriculum';

            modalFormFields.innerHTML = `
                <input type="hidden" name="curriculum_id" value="${currID}">
                <input type="hidden" name="action" value="${action}">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Curriculum Title *</label>
                    <input type="text" name="curriculum_title" class="form-control" value="${escapeHtml(title)}" placeholder="e.g. BSIT Curriculum 2026" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Overview or CMO reference details...">${escapeHtml(desc)}</textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Effective Year *</label>
                    <input type="text" name="effective_year" class="form-control" value="${escapeHtml(year)}" placeholder="e.g. 2026" required>
                </div>
            `;
            currModal.classList.add('active');
        }

        function closeCurrModal() {
            currModal.classList.remove('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>