<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

// Retrieve curriculum ID from query string, default to 1 if not set
$curriculumId = isset($_GET['id']) ? intval($_GET['id']) : 1;
$userID = $userID ?? ($user['ID'] ?? 0);

// -------------------------------------------------------------------------
// HANDLE FORM POST ACTIONS (CRUD)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        // 1. EDIT CURRICULUM DETAILS
        if ($action === 'save-curriculum') {
            $name = trim($_POST['curriculum_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $effectivity = trim($_POST['effective_year'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                throw new Exception("Curriculum name is required.");
            }

            $sql = "UPDATE curriculum SET CurriculumName = ?, Description = ?, EffectiveYear = ?, IsActive = ? WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssii', $name, $desc, $effectivity, $isActive, $curriculumId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Curriculum Updated', "Updated curriculum ID {$curriculumId}: {$name}.");
            redirectWithMessage('success', "Curriculum details updated successfully.", "curriculum.php?id={$curriculumId}");
        }

        // 2. DELETE CURRICULUM
        elseif ($action === 'delete-curriculum') {
            $delStmt = mysqli_prepare($conn, "DELETE FROM curriculum WHERE ID = ?");
            mysqli_stmt_bind_param($delStmt, 'i', $curriculumId);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);

            logActivity($conn, $userID, 'Curriculum Deleted', "Deleted curriculum ID {$curriculumId}.");
            redirectWithMessage('success', "Curriculum permanently deleted.", "academics.php?tab=curriculum");
        }

        // 3. ADD OR EDIT PROSPECTUS SUBJECT / PLACEHOLDER MAPPING
        elseif ($action === 'save-prospectus-subject') {
            $mapID = filter_input(INPUT_POST, 'mapping_id', FILTER_VALIDATE_INT);
            $isPlaceholder = isset($_POST['is_placeholder']) ? 1 : 0;
            
            $subjectID = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT) ?: null;
            $placeholderTitle = trim($_POST['placeholder_title'] ?? '');
            $placeholderCode = trim($_POST['placeholder_code'] ?? '');

            $yearLevel = trim($_POST['year_level'] ?? null);
            $semester = trim($_POST['semester'] ?? null);
            $countTotal = isset($_POST['count_towards_total']) ? 1 : 0;
            $isElective = isset($_POST['is_elective']) ? 1 : 0;
            $prereqLogic = trim($_POST['prerequisite_logic'] ?? 'ALL');
            $prerequisites = $_POST['prerequisites'] ?? []; 
            $noOfUnits = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_FLOAT) ?: 3.0;

            if (!$isPlaceholder && !$subjectID) {
                throw new Exception("Please select a subject from catalog or enable Placeholder mode.");
            }

            if ($mapID) {
                $sql = "UPDATE curriculum_subjects 
                        SET SubjectID = ?, YearLevel = ?, Semester = ?, CountTowardsTotal = ?, IsElective = ?, PrerequisiteLogic = ?, IsPlaceholder = ?, PlaceholderTitle = ?, PlaceholderCode = ?, Units = ? 
                        WHERE ID = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'issiisissdi', $subjectID, $yearLevel, $semester, $countTotal, $isElective, $prereqLogic, $isPlaceholder, $placeholderTitle, $placeholderCode, $noOfUnits, $mapID);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Prospectus Subject Updated', "Updated subject mapping ID {$mapID} in curriculum #{$curriculumId}.");
            } else {
                $sql = "INSERT INTO curriculum_subjects (CurriculumID, SubjectID, YearLevel, Semester, CountTowardsTotal, IsElective, PrerequisiteLogic, IsPlaceholder, PlaceholderTitle, PlaceholderCode, Units) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'iissiisissd', $curriculumId, $subjectID, $yearLevel, $semester, $countTotal, $isElective, $prereqLogic, $isPlaceholder, $placeholderTitle, $placeholderCode, $noOfUnits);
                mysqli_stmt_execute($stmt);
                $mapID = mysqli_insert_id($conn); // Capture newly created mapping ID
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Prospectus Subject Added', "Added mapping to curriculum #{$curriculumId}.");
            }

            // Save / Refresh Prerequisites Mapping linked to mapping ID ($mapID)
            if ($mapID) {
                $delPr = mysqli_prepare($conn, "DELETE FROM subject_prerequisites WHERE SubjectID = ?");
                mysqli_stmt_bind_param($delPr, 'i', $mapID);
                mysqli_stmt_execute($delPr);
                mysqli_stmt_close($delPr);

                if (!empty($prerequisites)) {
                    $prStmt = mysqli_prepare($conn, "INSERT INTO subject_prerequisites (SubjectID, PrerequisiteSubjectID) VALUES (?, ?)");
                    foreach ($prerequisites as $prID) {
                        $prIDVal = intval($prID);
                        if ($prIDVal > 0) {
                            mysqli_stmt_bind_param($prStmt, 'ii', $mapID, $prIDVal);
                            mysqli_stmt_execute($prStmt);
                        }
                    }
                    mysqli_stmt_close($prStmt);
                }
            }

            redirectWithMessage('success', "Prospectus item saved successfully.", "curriculum.php?id={$curriculumId}");
        }

        // 4. REMOVE PROSPECTUS SUBJECT MAPPING
        elseif ($action === 'delete-prospectus-subject') {
            $mapID = filter_input(INPUT_POST, 'mapping_id', FILTER_VALIDATE_INT);
            if ($mapID) {
                $delPr = mysqli_prepare($conn, "DELETE FROM subject_prerequisites WHERE SubjectID = ?");
                mysqli_stmt_bind_param($delPr, 'i', $mapID);
                mysqli_stmt_execute($delPr);
                mysqli_stmt_close($delPr);

                $delStmt = mysqli_prepare($conn, "DELETE FROM curriculum_subjects WHERE ID = ?");
                mysqli_stmt_bind_param($delStmt, 'i', $mapID);
                mysqli_stmt_execute($delStmt);
                mysqli_stmt_close($delStmt);

                logActivity($conn, $userID, 'Prospectus Subject Removed', "Removed mapping ID {$mapID} from curriculum #{$curriculumId}.");
                redirectWithMessage('success', "Subject removed from prospectus.", "curriculum.php?id={$curriculumId}");
            }
        }
    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), "curriculum.php?id={$curriculumId}");
    }
}

// -------------------------------------------------------------------------
// FETCH DATA FOR DISPLAY
// -------------------------------------------------------------------------
$currSql = "SELECT c.*, p.ProgramName FROM curriculum c INNER JOIN programs p ON c.ProgramID = p.ID WHERE c.ID = ? LIMIT 1";
$currStmt = mysqli_prepare($conn, $currSql);
mysqli_stmt_bind_param($currStmt, 'i', $curriculumId);
mysqli_stmt_execute($currStmt);
$currRes = mysqli_stmt_get_result($currStmt);
$curriculum = mysqli_fetch_assoc($currRes);
mysqli_stmt_close($currStmt);

if (!$curriculum) {
    die("Curriculum not found.");
}

$pageTitle = $curriculum['CurriculumName'] . " - Prospectus - AMSC InnDAW";

// Fetch Master Subjects Catalog for Modals
$catalogRes = mysqli_query($conn, "SELECT * FROM subjects ORDER BY SubjectCode ASC");
$masterSubjects = [];
while ($subRow = mysqli_fetch_assoc($catalogRes)) {
    $masterSubjects[] = $subRow;
}

// Fetch Prospectus Subjects Mapped to this Curriculum
$prospectusSql = "
    SELECT cs.ID as MappingID, cs.YearLevel, cs.Semester, cs.CountTowardsTotal, cs.IsElective, cs.PrerequisiteLogic, 
           cs.IsPlaceholder, cs.PlaceholderTitle, cs.PlaceholderCode,
           s.ID as SubjectID, s.SubjectCode, s.SubjectTitle, cs.Units 
    FROM curriculum_subjects cs
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    WHERE cs.CurriculumID = ? AND cs.IsElective = 0
    ORDER BY 
        FIELD(cs.YearLevel, '1st Year', '2nd Year', '3rd Year', '4th Year', 'First Year', 'Second Year', 'Third Year', 'Fourth Year'),
        FIELD(cs.Semester, '1st Semester', '2nd Semester', 'Summer', 'First Semester', 'Second Semester'),
        COALESCE(s.SubjectCode, cs.PlaceholderCode) ASC
";
$pStmt = mysqli_prepare($conn, $prospectusSql);
mysqli_stmt_bind_param($pStmt, 'i', $curriculumId);
mysqli_stmt_execute($pStmt);
$pRes = mysqli_stmt_get_result($pStmt);

$yearLevelsMap = [
    'First Year' => '1st Year', 'Second Year' => '2nd Year', 'Third Year' => '3rd Year', 'Fourth Year' => '4th Year',
    '1st Year' => '1st Year', '2nd Year' => '2nd Year', '3rd Year' => '3rd Year', '4th Year' => '4th Year'
];

$semesterMap = [
    'First Semester' => '1st Semester', 'Second Semester' => '2nd Semester', 'Summer' => 'Summer',
    '1st Semester' => '1st Semester', '2nd Semester' => '2nd Semester'
];

$prospectusData = [
    '1st Year' => ['1st Semester' => ['subjects' => []], '2nd Semester' => ['subjects' => []]],
    '2nd Year' => ['1st Semester' => ['subjects' => []], '2nd Semester' => ['subjects' => []]],
    '3rd Year' => ['1st Semester' => ['subjects' => []], '2nd Semester' => ['subjects' => []]],
    '4th Year' => ['1st Semester' => ['subjects' => []], '2nd Semester' => ['subjects' => []]]
];

$totalCreditedUnits = 0;
$totalUnitsIncludingNonCredited = 0;

while ($row = mysqli_fetch_assoc($pRes)) {
    $prereqs = [];
    // Fetch prerequisites for this mapping ID (works for both subjects and placeholders)
    if (!empty($row['MappingID'])) {
        $prereqSqlClean = "
            SELECT sub.ID as SubjectID, sub.SubjectCode, sub.SubjectTitle 
            FROM subject_prerequisites p 
            INNER JOIN subjects sub ON p.PrerequisiteSubjectID = sub.ID 
            WHERE p.SubjectID = ?
        ";
        $prStmt = mysqli_prepare($conn, $prereqSqlClean);
        mysqli_stmt_bind_param($prStmt, 'i', $row['MappingID']);
        mysqli_stmt_execute($prStmt);
        $prRes = mysqli_stmt_get_result($prStmt);
        while ($prRow = mysqli_fetch_assoc($prRes)) {
            $prereqs[] = $prRow;
        }
        mysqli_stmt_close($prStmt);
    }

    $row['Prerequisites'] = $prereqs;
    
    // Evaluate units: Placeholders default to 3.0 units[cite: 13]
    $units = intval($row['IsPlaceholder']) === 1 ? 3.0 : floatval($row['Units'] ?? 3.0);
    $row['EvaluatedUnits'] = $units;

    $yNorm = $yearLevelsMap[$row['YearLevel']] ?? '1st Year';
    $sNorm = $semesterMap[$row['Semester']] ?? '1st Semester';
    
    if (isset($prospectusData[$yNorm][$sNorm])) {
        $prospectusData[$yNorm][$sNorm]['subjects'][] = $row;
    }

    $totalUnitsIncludingNonCredited += $units;
    if (intval($row['CountTowardsTotal']) === 1) {
        $totalCreditedUnits += $units;
    }
}
mysqli_stmt_close($pStmt);

// Compute Per-Semester Sub-totals & Non-Credited Units
foreach ($prospectusData as $yKey => $semesters) {
    foreach ($semesters as $sKey => $sData) {
        $semCredited = 0;
        $semTotal = 0;
        foreach ($sData['subjects'] as $subj) {
            $u = $subj['EvaluatedUnits'];
            $semTotal += $u;
            if (intval($subj['CountTowardsTotal']) === 1) {
                $semCredited += $u;
            }
        }
        $prospectusData[$yKey][$sKey]['credited_units'] = $semCredited;
        $prospectusData[$yKey][$sKey]['total_units'] = $semTotal;
        $prospectusData[$yKey][$sKey]['non_credited_units'] = $semTotal - $semCredited;
    }
}

// Fetch Dynamic Elective Offerings mapped in curriculum_subjects (IsElective = 1)
$electivesQuery = "
    SELECT DISTINCT COALESCE(s.SubjectCode, cs.PlaceholderCode) as SubjectCode, 
           COALESCE(s.SubjectTitle, cs.PlaceholderTitle) as SubjectTitle, 
           cs.Units
    FROM curriculum_subjects cs
    LEFT JOIN subjects s ON cs.SubjectID = s.ID
    WHERE cs.CurriculumID = ? AND cs.IsElective = 1
    ORDER BY SubjectCode ASC
";
$eStmt = mysqli_prepare($conn, $electivesQuery);
mysqli_stmt_bind_param($eStmt, 'i', $curriculumId);
mysqli_stmt_execute($eStmt);
$eRes = mysqli_stmt_get_result($eStmt);
$electiveOfferings = [];
while ($eRow = mysqli_fetch_assoc($eRes)) {
    $electiveOfferings[] = $eRow;
}
mysqli_stmt_close($eStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .curriculum-container { display: flex; flex-direction: column; gap: 20px; }
        .curriculum-info-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .curriculum-info-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status.active { background-color: #d1fae5; color: #065f46; }
        .badge-status.inactive { background-color: #fee2e2; color: #991b1b; }
        
        /* 2-Column Side-by-Side Prospectus Layout per Year Level */
        .year-level-block { background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .year-level-title { background: var(--primary); color: #ffffff; padding: 12px 20px; font-weight: 700; font-size: 1.05rem; display: flex; justify-content: space-between; align-items: center; }
        .year-semesters-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        @media (max-width: 992px) { .year-semesters-grid { grid-template-columns: 1fr; } }
        .semester-col { border-right: 1px solid var(--border-color); }
        .semester-col:last-child { border-right: none; }
        .semester-subheading { padding: 10px 15px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); font-size: 0.88rem; font-weight: bold; color: var(--primary); display: flex; justify-content: space-between; align-items: center; }
        
        .prospectus-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        .prospectus-table th, .prospectus-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .prospectus-table th { background-color: #f1f5f9; color: var(--primary); font-weight: 700; font-size: 0.8rem; }
        .prospectus-table tr:hover { background-color: #f8fafc; }
        
        .unit-badge { background-color: #d1fae5; color: #065f46; font-weight: 700; font-size: 0.72rem; padding: 2px 7px; border-radius: 10px; }
        .unit-badge.non-credited { background-color: #f1f5f9; color: #64748b; }
        .prereq-badge { background-color: #fef3c7; color: #b45309; font-size: 0.72rem; font-weight: 600; padding: 2px 6px; border-radius: 6px; border: 1px solid #fde68a; display: inline-block; margin-right: 2px; }
        .placeholder-badge { background-color: #e0f2fe; color: #0369a1; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; border: 1px solid #bae6fd; display: inline-block; }
        .action-btns { display: flex; gap: 4px; }

        .curriculum-link a { color: var(--primary); font-weight: bold; }
        .curriculum-link a:hover { background-color: var(--primary); color: white; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 90%; max-width: 600px; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 24px 20px; max-height: 75vh; overflow-y: auto; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Curriculum Prospectus & Mapping</h2>
                    <p class="text-muted">Manage curriculum settings, prospectus subjects, year levels, and prerequisites.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="academics.php?tab=curriculum" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left"></i> Back to Curricula
                    </a>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="curriculum-container">

                <!-- CURRICULUM DETAILS & SUMMARY CARD -->
                <div class="curriculum-info-card">
                    <div class="curriculum-info-header">
                        <div>
                            <span class="badge-status <?= intval($curriculum['IsActive']) === 1 ? 'active' : 'inactive' ?>">
                                <i class="fa-solid fa-circle-check"></i> <?= intval($curriculum['IsActive']) === 1 ? 'Active' : 'Inactive' ?> Curriculum
                            </span>
                            <h3 style="color: var(--primary); margin: 6px 0 2px 0; font-size: 1.3rem;"><?= htmlspecialchars($curriculum['CurriculumName']) ?></h3>
                            <p class="text-muted" style="margin: 0; font-size: 0.9rem;"><?= htmlspecialchars($curriculum['Description']) ?></p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openEditCurriculumModal()">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Curriculum
                            </button>
                            <form action="curriculum.php?id=<?= $curriculumId ?>" method="POST" onsubmit="return confirm('Delete entire curriculum?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete-curriculum">
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.88rem;">
                        <div><strong>Program:</strong><br><span><?= htmlspecialchars($curriculum['ProgramName']) ?></span> <span class="curriculum-link"><a href="programs.php?id=<?= $curriculum['ProgramID'] ?>" title="Visit the <?= $curriculum['ProgramName'] ?> page."><i class="fa-solid fa-arrow-up-right-from-square"></i></a></span></div>
                        <div><strong>Effectivity Year:</strong><br><span><?= htmlspecialchars($curriculum['EffectiveYear']) ?></span></div>
                        <div><strong>Official Credited Units:</strong><br><span><strong><?= $totalCreditedUnits ?> Units</strong></span></div>
                        <div><strong>Total Units (Inc. Non-Credited):</strong><br><span><strong><?= $totalUnitsIncludingNonCredited ?> Units</strong></span></div>
                    </div>
                </div>

                <!-- PROSPECTUS HEADER -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <h3 style="color: var(--primary); margin: 0;"><i class="fa-solid fa-book-bookmark"></i> Prospectus Course Mapping</h3>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openProspectusModal('add')">
                        <i class="fa-solid fa-plus"></i> Add Subject / Placeholder
                    </button>
                </div>

                <!-- PROSPECTUS TABLES (2-COLUMN GRID PER YEAR LEVEL) -->
                <?php foreach ($prospectusData as $yearLevel => $semesters): ?>
                    <div class="year-level-block">
                        <div class="year-level-title">
                            <span><?= htmlspecialchars($yearLevel) ?> Prospectus</span>
                        </div>
                        <div class="year-semesters-grid">
                            
                            <!-- First Semester Column -->
                            <?php $s1 = $semesters['1st Semester']; ?>
                            <div class="semester-col">
                                <div class="semester-subheading">
                                    <span>First Semester</span>
                                    <span style="font-size: 0.78rem; font-weight: normal; color: var(--text-muted);">
                                        Subtotal: <strong><?= $s1['credited_units'] ?? 0 ?> Units</strong> <?= ($s1['non_credited_units'] > 0) ? '(' . $s1['total_units'] . ')' : '' ?>
                                    </span>
                                </div>
                                <table class="prospectus-table">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Descriptive Title</th>
                                            <th>Units</th>
                                            <th>Prerequisites</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($s1['subjects'])): ?>
                                            <?php foreach ($s1['subjects'] as $subj): 
                                                $isCredited = intval($subj['CountTowardsTotal']) === 1;
                                                $code = intval($subj['IsPlaceholder']) === 1 ? $subj['PlaceholderCode'] : $subj['SubjectCode'];
                                                $title = intval($subj['IsPlaceholder']) === 1 ? $subj['PlaceholderTitle'] : $subj['SubjectTitle'];
                                                $unitDisplay = $isCredited ? $subj['EvaluatedUnits'] : '(' . $subj['EvaluatedUnits'] . ')'; 
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($code) ?></strong>
                                                        <?php if (intval($subj['IsPlaceholder']) === 1): ?>
                                                            <span class="placeholder-badge">Placeholder</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars_decode($title) ?></td>
                                                    <td><span class="unit-badge <?= $isCredited ? '' : 'non-credited' ?>"><?= $unitDisplay ?></span></td>
                                                    <td>
                                                        <?php if (!empty($subj['Prerequisites'])): ?>
                                                            <?php foreach ($subj['Prerequisites'] as $pr): ?>
                                                                <span class="prereq-badge"><?= htmlspecialchars($pr['SubjectCode']) ?></span>                                       
                                                                <?php $concat = $pr['SubjectCode']; ?>
                                                            <?php endforeach; ?>
                                                                <small class="text-muted" style="font-size:0.7rem;">(<?= htmlspecialchars($subj['PrerequisiteLogic'] ?? 'ALL') ?>)</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick='openProspectusModal("edit", <?= json_encode($subj) ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                            <form action="curriculum.php?id=<?= $curriculumId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Remove subject?');">
                                                                <input type="hidden" name="action" value="delete-prospectus-subject">
                                                                <input type="hidden" name="mapping_id" value="<?= $subj['MappingID'] ?>">
                                                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" style="text-align:center; padding:15px; color:var(--text-muted);">No courses mapped.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Second Semester Column -->
                            <?php $s2 = $semesters['2nd Semester']; ?>
                            <div class="semester-col">
                                <div class="semester-subheading">
                                    <span>Second Semester</span>
                                    <span style="font-size: 0.78rem; font-weight: normal; color: var(--text-muted);">
                                        Subtotal: <strong><?= $s2['credited_units'] ?? 0 ?> Units</strong> <?= ($s2['non_credited_units'] > 0) ? '(' . $s2['total_units'] . ')' : '' ?>
                                    </span>
                                </div>
                                <table class="prospectus-table">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Descriptive Title</th>
                                            <th>Units</th>
                                            <th>Prerequisites</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($s2['subjects'])): ?>
                                            <?php foreach ($s2['subjects'] as $subj): 
                                                $isCredited = intval($subj['CountTowardsTotal']) === 1;
                                                $code = intval($subj['IsPlaceholder']) === 1 ? $subj['PlaceholderCode'] : $subj['SubjectCode'];
                                                $title = intval($subj['IsPlaceholder']) === 1 ? $subj['PlaceholderTitle'] : $subj['SubjectTitle'];
                                                $unitDisplay = $isCredited ? $subj['EvaluatedUnits'] : '(' . $subj['EvaluatedUnits'] . ')'; 
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($code) ?></strong>
                                                        <?php if (intval($subj['IsPlaceholder']) === 1): ?>
                                                            <span class="placeholder-badge">Placeholder</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars_decode($title) ?></td>
                                                    <td><span class="unit-badge <?= $isCredited ? '' : 'non-credited' ?>"><?= $unitDisplay ?></span></td>
                                                    <td>
                                                        <?php if (!empty($subj['Prerequisites'])): ?>
                                                            <?php foreach ($subj['Prerequisites'] as $pr): ?>
                                                                <span class="prereq-badge"><?= htmlspecialchars($pr['SubjectCode']) ?></span>
                                                            <?php endforeach; ?>
                                                            <small class="text-muted" style="font-size:0.7rem;">(<?= htmlspecialchars($subj['PrerequisiteLogic'] ?? 'ALL') ?>)</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick='openProspectusModal("edit", <?= json_encode($subj) ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                            <form action="curriculum.php?id=<?= $curriculumId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Remove subject?');">
                                                                <input type="hidden" name="action" value="delete-prospectus-subject">
                                                                <input type="hidden" name="mapping_id" value="<?= $subj['MappingID'] ?>">
                                                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" style="text-align:center; padding:15px; color:var(--text-muted);">No courses mapped.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- TECHNICAL ELECTIVES OFFERINGS TABLE -->
                <?php if (!empty($electiveOfferings)): ?>
                    <div class="year-level-block" style="margin-top: 20px;">
                        <div class="year-level-title" style="background-color: var(--primary, #0284c7);">
                            <span><i class="fa-solid fa-layer-group"></i> TECHNICAL ELECTIVES</span>
                            <span style="font-size: 0.8rem; font-weight: normal;">Optional courses subject to prerequisites</span>
                        </div>
                        <div style="padding: 15px;">
                            <p class="text-muted" style="font-size: 0.82rem; margin-top: 0; margin-bottom: 12px; font-style: italic;">
                                Note: These are not counted to the total official units as these are options for students, subject to prerequisites.
                            </p>
                            <table class="prospectus-table">
                               <thead>
                                   <tr>
                                       <th style="width: 25%;">CourseCode</th>
                                       <th style="width: 60%;">Descriptive Title</th>
                                       <th style="width: 15%;">Units</th>
                                   </tr>
                               </thead>
                               <tbody>
                                   <?php foreach ($electiveOfferings as $eo): ?>
                                       <tr>
                                           <td><strong><?= htmlspecialchars($eo['SubjectCode']) ?></strong></td>
                                           <td><?= htmlspecialchars($eo['SubjectTitle']) ?></td>
                                           <td><span class="unit-badge"><?= htmlspecialchars($eo['Units']) ?></span></td>
                                       </tr>
                                   <?php endforeach; ?>
                               </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- EDIT CURRICULUM DETAILS MODAL -->
    <div id="editCurriculumModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Curriculum Details</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditCurriculumModal()">&times;</button>
            </div>
            <form action="curriculum.php?id=<?= $curriculumId ?>" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save-curriculum">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Curriculum Name *</label>
                        <input type="text" name="curriculum_name" class="form-control" value="<?= htmlspecialchars($curriculum['CurriculumName']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Effectivity Year *</label>
                        <input type="text" name="effective_year" class="form-control" value="<?= htmlspecialchars($curriculum['EffectiveYear']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Description *</label>
                        <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($curriculum['Description']) ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Curriculum Status *</label>
                        <select name="is_active" class="form-control" required>
                            <option value="1" <?= intval($curriculum['IsActive']) === 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= intval($curriculum['IsActive']) === 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditCurriculumModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD / EDIT PROSPECTUS MAPPING & PLACEHOLDER MODAL -->
    <div id="prospectusModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="prospectusModalTitle"><i class="fa-solid fa-book"></i> Add Prospectus Subject</h3>
                <button type="button" class="modal-close-btn" onclick="closeProspectusModal()">&times;</button>
            </div>
            <form action="curriculum.php?id=<?= $curriculumId ?>" method="POST" id="prospectusForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save-prospectus-subject">
                    <input type="hidden" name="mapping_id" id="mappingIdField" value="">

                    <!-- Placeholder Toggle -->
                    <div class="form-group" style="margin-bottom: 15px; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color);">
                        <label style="font-size: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="is_placeholder" id="isPlaceholderCheckbox" value="1" onchange="togglePlaceholderMode()" style="width: 17px; height: 17px;">
                            Set as Generic Placeholder Slot (e.g., Tech Elect 1) - Defaults to 3 Units
                        </label>
                    </div>

                    <!-- Subject Selection Block -->
                    <div id="subjectSelectBlock" class="form-grid-2" style="margin-bottom: 12px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-size: 0.85rem; font-weight: 600;">Select Subject from Master Catalog *</label>
                            <select name="subject_id" id="subjectIdSelect" class="form-control">
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($masterSubjects as $ms): ?>
                                    <option value="<?= $ms['ID'] ?>"><?= htmlspecialchars($ms['SubjectCode']) ?> - <?= htmlspecialchars_decode($ms['SubjectTitle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Placeholder Details Block -->
                    <div id="placeholderFieldsBlock" class="form-grid-2" style="margin-bottom: 12px; display: none;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Placeholder Code *</label>
                            <input type="text" name="placeholder_code" id="placeholderCodeInput" class="form-control" placeholder="e.g. *Tech Elect 1">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Placeholder Descriptive Title *</label>
                            <input type="text" name="placeholder_title" id="placeholderTitleInput" class="form-control" placeholder="e.g. Technical Elective 1">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">No. of Units *</label>
                        <input type="number" step="0.5" name="units" id="subjectUnits" class="form-control" value="3.0" min="0" max="20" required>
                    </div>

                    <!-- Year Level and Semester -->
                    <div class="form-grid-2" style="margin-bottom: 12px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Year Level *</label>
                            <select name="year_level" id="yearLevelSelect" class="form-control">
                                <option value="">-- No Assigned Year Level --</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Semester *</label>
                            <select name="semester" id="semesterSelect" class="form-control">
                                <option value="">-- No Assigned Semester --</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Prerequisite Subject(s)</label>
                        <select name="prerequisites[]" id="prereqSelect" class="form-control" multiple style="height: 90px;">
                            <?php foreach ($masterSubjects as $ms): ?>
                                <option value="<?= $ms['ID'] ?>"><?= htmlspecialchars($ms['SubjectCode']) ?> - <?= htmlspecialchars_decode($ms['SubjectTitle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 12px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Prerequisite Logic Rule</label>
                            <select name="prerequisite_logic" id="prereqLogicSelect" class="form-control">
                                <option value="ALL">ALL (Must pass all selected prereqs)</option>
                                <option value="ANY">ANY (Must pass at least one)</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; gap: 8px;">
                            <label style="font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="is_elective" id="isElectiveCheckbox" value="1" style="width: 16px; height: 16px;"> Is Elective Offering
                            </label>
                            <label style="font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="count_towards_total" id="countTotalCheckbox" value="1" checked style="width: 16px; height: 16px;"> Count Units to Official Total
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeProspectusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Prospectus Mapping</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS Interactivity -->
    <script>
        const editCurriculumModal = document.getElementById('editCurriculumModal');
        const prospectusModal = document.getElementById('prospectusModal');
        const prospectusModalTitle = document.getElementById('prospectusModalTitle');

        function openEditCurriculumModal() {
            editCurriculumModal.classList.add('active');
        }

        function closeEditCurriculumModal() {
            editCurriculumModal.classList.remove('active');
        }

        function togglePlaceholderMode() {
            const isChecked = document.getElementById('isPlaceholderCheckbox').checked;
            document.getElementById('subjectSelectBlock').style.display = isChecked ? 'none' : 'block';
            document.getElementById('placeholderFieldsBlock').style.display = isChecked ? 'grid' : 'none';
        }

        function openProspectusModal(mode, data = null) {
            const form = document.getElementById('prospectusForm');
            form.reset();
            
            if (mode === 'add') {
                prospectusModalTitle.innerHTML = `<i class="fa-solid fa-plus"></i> Add Prospectus Subject / Placeholder`;
                document.getElementById('mappingIdField').value = '';
                document.getElementById('countTotalCheckbox').checked = true;
                document.getElementById('isPlaceholderCheckbox').checked = false;
                document.getElementById('subjectUnits').value = '3.0';
            } else if (data) {
                prospectusModalTitle.innerHTML = `<i class="fa-solid fa-pen"></i> Edit Prospectus Subject`;
                document.getElementById('mappingIdField').value = data.MappingID;
                document.getElementById('yearLevelSelect').value = data.YearLevel || '';
                document.getElementById('semesterSelect').value = data.Semester || '';
                document.getElementById('subjectUnits').value = data.Units || 3.0;

                document.getElementById('prereqLogicSelect').value = data.PrerequisiteLogic || 'ALL';
                document.getElementById('isElectiveCheckbox').checked = (data.IsElective == 1);
                document.getElementById('countTotalCheckbox').checked = (data.CountTowardsTotal == 1);

                const isPh = (data.IsPlaceholder == 1);
                document.getElementById('isPlaceholderCheckbox').checked = isPh;
                if (isPh) {
                    document.getElementById('placeholderCodeInput').value = data.PlaceholderCode || '';
                    document.getElementById('placeholderTitleInput').value = data.PlaceholderTitle || '';
                } else {
                    document.getElementById('subjectIdSelect').value = data.SubjectID || '';
                }

                // Populate multi-select prerequisites cleanly
                const prereqSelect = document.getElementById('prereqSelect');
                for (let option of prereqSelect.options) {
                    option.selected = false;
                }
                if (data.Prerequisites && data.Prerequisites.length > 0) {
                    const prereqIDs = data.Prerequisites.map(p => parseInt(p.SubjectID));
                    for (let option of prereqSelect.options) {
                        if (prereqIDs.includes(parseInt(option.value))) {
                            option.selected = true;
                        }
                    }
                }
            }
            togglePlaceholderMode();
            prospectusModal.classList.add('active');
        }

        function closeProspectusModal() {
            prospectusModal.classList.remove('active');
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