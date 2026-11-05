<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Manage Applicants - AMSC InDaw";

// Get active academic term
$aySql = "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1";
$ayRes = mysqli_query($conn, $aySql);
$currentTerm = mysqli_fetch_assoc($ayRes);

$activeTerm = 'AY ' . ($currentTerm['YearCode'] ?? '2026-2027') . ', ' . ($currentTerm['Semester'] ?? '2nd Semester');
$enrollmentStatus = $currentTerm['EnrollmentStatus'] ?? 'Closed';
$currentYearCode = explode('-', $currentTerm['YearCode'] ?? '2026')[0];

// -------------------------------------------------------------------------
// POST / ACTION HANDLERS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $applicantID = filter_input(INPUT_POST, 'applicant_id', FILTER_VALIDATE_INT);

    try {
        // 1. Delete Applicant (Allowed at any stage for security/nuisance/duplication)
        if ($action === 'delete_applicant') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");

            $appQuery = mysqli_query($conn, "SELECT FirstName, LastName, ApplicationNumber FROM applicants WHERE ApplicantID = $applicantID LIMIT 1");
            $appData = mysqli_fetch_assoc($appQuery);
            $appName = $appData ? "{$appData['FirstName']} {$appData['LastName']} ({$appData['ApplicationNumber']})" : "#{$applicantID}";

            $stmt = mysqli_prepare($conn, "DELETE FROM applicants WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Applicant Deleted', "Deleted applicant record: {$appName}.");
            redirectWithMessage('success', 'Applicant record securely removed.', 'applicants?');
        }

        // 2. Individual Document Verification (Only available when Under Review)
        elseif ($action === 'verify_document') {
            $docID = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
            $docStatus = trim($_POST['doc_status'] ?? 'Pending');
            $docRemarks = trim($_POST['doc_remarks'] ?? '');

            if (!$docID) throw new Exception("Invalid document reference.");

            $stmt = mysqli_prepare($conn, "UPDATE applicant_documents SET VerificationStatus = ?, Remarks = ? WHERE DocumentID = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $docStatus, $docRemarks, $docID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Document Verified', "Updated document ID #{$docID} to {$docStatus}.");
            echo json_encode(['status' => 'success']);
            exit;
        }

        // 3. Return Application Back to Stage 2 (Profile & Document Resubmission)
        elseif ($action === 'return_application') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Pending' WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Application Returned', "Returned applicant ID #{$applicantID} for profile revision and/or document re-submission.");
            redirectWithMessage('success', 'Application returned to applicant for revision/re-submission.', 'applicants?');
        }

        // 4. Approve Application & Automatically Generate Student Record
        elseif ($action === 'approve_application') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");
            $selectedProgramID = filter_input(INPUT_POST, 'selected_program_id', FILTER_VALIDATE_INT);

            $stmtApp = mysqli_prepare($conn, "
                SELECT a.*, p.ID AS ProgID FROM applicants a 
                LEFT JOIN programs p ON p.ID = COALESCE(?, a.PreferredProgramID_1)
                WHERE a.ApplicantID = ? LIMIT 1
            ");
            mysqli_stmt_bind_param($stmtApp, 'ii', $selectedProgramID, $applicantID);
            mysqli_stmt_execute($stmtApp);
            $appRes = mysqli_stmt_get_result($stmtApp);
            $appInfo = mysqli_fetch_assoc($appRes);
            mysqli_stmt_close($stmtApp);

            if (!$appInfo) throw new Exception("Applicant details not found.");

            $programID = $selectedProgramID ?: $appInfo['PreferredProgramID_1'];

            $currQuery = mysqli_query($conn, "SELECT ID FROM curriculum WHERE ProgramID = $programID AND IsActive = 1 LIMIT 1");
            $currRow = mysqli_fetch_assoc($currQuery);
            $curriculumID = $currRow['ID'] ?? 1;

            $updStmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Approved', UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($updStmt, 'i', $applicantID);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            $checkStudent = mysqli_query($conn, "SELECT StudentID FROM students WHERE ApplicantID = $applicantID LIMIT 1");
            if (mysqli_num_rows($checkStudent) == 0) {
                $insStudent = mysqli_prepare($conn, "INSERT INTO students (ApplicantID, StudentNumber, ProgramID, CurriculumID, EnrollmentStatus, DateEnrolled) VALUES (?, 'TEMP', ?, ?, NULL, NULL)");
                mysqli_stmt_bind_param($insStudent, 'iii', $applicantID, $programID, $curriculumID);
                mysqli_stmt_execute($insStudent);
                $studentPrimaryKey = mysqli_insert_id($conn);
                mysqli_stmt_close($insStudent);

                $generatedStudentNumber = ($currentYearCode * 10000) + $studentPrimaryKey;

                $updStudentNum = mysqli_prepare($conn, "UPDATE students SET StudentNumber = ? WHERE StudentID = ?");
                mysqli_stmt_bind_param($updStudentNum, 'si', $generatedStudentNumber, $studentPrimaryKey);
                mysqli_stmt_execute($updStudentNum);
                mysqli_stmt_close($updStudentNum);
            }

            logActivity($conn, $userID, 'Application Approved', "Approved applicant ID #{$applicantID} and generated Student ID.");
            redirectWithMessage('success', 'Application approved and student profile successfully generated!', 'applicants?');
        }

        // 5. Undo Approval (Revert to Under Review & Delete Student Record)
        elseif ($action === 'undo_approval') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");

            // Update applicant status back to Under Review
            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Under Review', UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Delete the student record associated with this applicant
            $delStudentStmt = mysqli_prepare($conn, "DELETE FROM students WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($delStudentStmt, 'i', $applicantID);
            mysqli_stmt_execute($delStudentStmt);
            mysqli_stmt_close($delStudentStmt);

            logActivity($conn, $userID, 'Approval Undone', "Reverted approved applicant ID #{$applicantID} back to Under Review and removed student record.");
            redirectWithMessage('success', 'Approval successfully undone. Application reverted to Under Review.', 'applicants?');
        }

        // 6. Reject Application
        elseif ($action === 'reject_application') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Rejected', UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Application Rejected', "Rejected applicant ID #{$applicantID}.");
            redirectWithMessage('success', 'Application marked as Rejected.', 'applicants?');
        }

        // 7. Undo Rejection
        elseif ($action === 'undo_status') {
            if (!$applicantID) throw new Exception("Invalid applicant reference.");
            $targetStatus = trim($_POST['target_status'] ?? 'Under Review');

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = ?, UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'si', $targetStatus, $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Application Status Undone', "Reverted applicant ID #{$applicantID} status to {$targetStatus}.");
            redirectWithMessage('success', "Application status successfully reverted to '{$targetStatus}'.", 'applicants?');
        }
    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), 'applicants?');
    }
}

// -------------------------------------------------------------------------
// PAGINATION & FETCH APPLICANTS LOGIC
// -------------------------------------------------------------------------
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $limit;

$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM applicants");
$countRow = mysqli_fetch_assoc($countResult);
$totalRecords = $countRow['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit) ?: 1;

$applicantsQuery = "
    SELECT a.*, 
           p1.ProgramName AS Prog1Name, p1.ProgramCode AS Prog1Code,
           p2.ProgramName AS Prog2Name, p2.ProgramCode AS Prog2Code,
           p3.ProgramName AS Prog3Name, p3.ProgramCode AS Prog3Code,
           u.Email AS UserEmail,
           s.StudentNumber, s.EnrollmentStatus
    FROM applicants a
    LEFT JOIN programs p1 ON a.PreferredProgramID_1 = p1.ID
    LEFT JOIN programs p2 ON a.PreferredProgramID_2 = p2.ID
    LEFT JOIN programs p3 ON a.PreferredProgramID_3 = p3.ID
    LEFT JOIN users u ON a.UserID = u.ID
    LEFT JOIN students s ON a.ApplicantID = s.ApplicantID
    ORDER BY a.ApplicantID DESC
    LIMIT $limit OFFSET $offset
";
$applicantsRes = mysqli_query($conn, $applicantsQuery);
$applicantsList = [];
while ($row = mysqli_fetch_assoc($applicantsRes)) {
    $appID = $row['ApplicantID'];
    $docRes = mysqli_query($conn, "SELECT * FROM applicant_documents WHERE ApplicantID = $appID");
    $docs = [];
    while ($dRow = mysqli_fetch_assoc($docRes)) {
        $docs[] = $dRow;
    }
    $row['Documents'] = $docs;
    $applicantsList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .applicants-container { display: flex; flex-direction: column; gap: 20px; }
        .filter-toolbar { display: flex; gap: 15px; background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); align-items: center; flex-wrap: wrap; }
        .filter-toolbar input, .filter-toolbar select { padding: 9px 14px; font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; }
        .filter-toolbar input { flex: 1; min-width: 250px; }

        .applicants-table-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .applicants-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .applicants-table th, .applicants-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .applicants-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .applicants-table tr:hover { background-color: #f8fafc; }

        .applicant-profile-cell { display: flex; align-items: center; gap: 12px; }
        .applicant-thumb { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); background-color: #e2e8f0; flex-shrink: 0; }
        .applicant-name-btn { font-weight: 600; color: var(--primary); background: none; border: none; padding: 0; cursor: pointer; text-align: left; }
        .applicant-name-btn:hover { text-decoration: underline; }
        .app-number { font-size: 0.75rem; color: var(--text-muted); display: block; }

        /* Progress Pipeline Tracker with Step Status Styling */
        .progress-pipeline { display: flex; align-items: center; gap: 6px; }
        .pipeline-step { width: 24px; height: 24px; border-radius: 50%; background-color: #cbd5e1; color: #475569; font-size: 0.72rem; font-weight: bold; display: flex; align-items: center; justify-content: center; cursor: help; position: relative; transition: all 0.2s ease; }
        .pipeline-step.completed { background-color: #10b981; color: #ffffff; }
        .pipeline-step.active { background-color: #d97706; color: #ffffff; box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2); }
        .pipeline-step.rejected { background-color: #ef4444; color: #ffffff; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
        .pipeline-labels { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-weight: 600; }

        /* Pagination Styling */
        .pagination-container { padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; flex-wrap: wrap; gap: 10px; }
        .pagination-links { display: flex; gap: 5px; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 90%; max-width: 950px; height: 88vh; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column; overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }

        .modal-profile-banner { display: flex; align-items: center; gap: 20px; background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; }
        .modal-avatar-large { width: 84px; height: 84px; border-radius: 50%; border: 3px solid var(--primary); object-fit: cover; background-color: #e2e8f0; flex-shrink: 0; }

        .modal-tabs { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); padding: 0 20px; }
        .modal-tab-btn { padding: 12px 20px; background: transparent; border: none; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; }
        .modal-tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; }

        .modal-body { flex: 1; padding: 24px; overflow-y: auto; background: #f8fafc; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .doc-review-card { background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 12px; }
        .doc-review-header { display: flex; justify-content: space-between; align-items: center; }
        .doc-review-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .doc-review-actions input { flex: 1; min-width: 200px; padding: 6px 10px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; }

        .modal-footer { padding: 16px 20px; background: #ffffff; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 12px; }
        .admin-action-row { display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }

        .doc-viewer-card { background: #ffffff; width: 90%; max-width: 900px; height: 85vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; z-index: 2500; }
        .doc-viewer-content { flex: 1; background: #1e293b; display: flex; align-items: center; justify-content: center; position: relative; overflow: auto; }
        .doc-viewer-content img { max-width: 100%; max-height: 100%; transition: transform 0.2s ease; cursor: grab; }
        .doc-viewer-content iframe { width: 100%; height: 100%; border: none; }

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
                    <h2>Applicant Directory & Review</h2>
                    <p class="text-muted">Review applicant profiles, verify individual documents, and approve or reject submissions.</p>
                </div>
                <div class="application-id-badge">
                    <span>Total Records:</span> <strong><?= $totalRecords ?></strong>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="applicants-container">

                <!-- Filter Toolbar -->
                <div class="filter-toolbar">
                    <input type="text" id="searchInput" placeholder="Search by name, app number, or program...">
                    
                    <select id="statusFilter">
                        <option value="">Filter by Status</option>
                        <option value="Pending">Pending / Step 1 & 2</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>

                    <select id="sortSelect">
                        <option value="name">Sort by Name (A-Z)</option>
                        <option value="date-desc">Sort by Date (Newest)</option>
                        <option value="date-asc">Sort by Date (Oldest)</option>
                    </select>
                </div>
                
                <!-- Enrollment Status Alert Banner -->
                <?php 
                    $statusLower = strtolower(trim($enrollmentStatus));
                    $alertClass = ($statusLower === 'open') ? 'open' : 'closed';
                    $statusIcon = ($statusLower === 'open') ? 'fa-circle-check' : 'fa-circle-xmark';
                ?>
                <div class="enrollment-alert-banner <?= $alertClass ?>">
                    <i class="fa-solid <?= $statusIcon ?>"></i>
                    <span>Enrollment Status for <strong><?= htmlspecialchars($activeTerm) ?></strong> is currently: <strong><?= htmlspecialchars($enrollmentStatus) ?></strong></span>
                </div>

                <!-- Applicants Table Card -->
                <div class="applicants-table-card">
                    <div style="overflow-x: auto;">
                        <table class="applicants-table" id="applicantsTable">
                            <thead>
                                <tr>
                                    <th>Applicant Name & ID</th>
                                    <th>Program Preference</th>
                                    <th>Contact Info</th>
                                    <th>Admission Pipeline Stage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($applicantsList)): ?>
                                    <?php foreach ($applicantsList as $app): ?>
                                        <?php 
                                            $rawStatus = $app['ApplicationStatus'] ?? 'Pending';
                                            
                                            // Step 1: Account Creation
                                            $s1Class = "completed";
                                            $s1Tip   = "Step 1 (Account Creation): Completed - Applicant account has been registered.";

                                            // Step 2: Profile & Document Submission (Pending status)
                                            if ($rawStatus === 'Pending') {
                                                $s2Class = "active";
                                                $s2Tip   = "Step 2 (Application Pending): Current Step - Applicant has not yet completed submission.";
                                            } else {
                                                $s2Class = "completed";
                                                $s2Tip   = "Step 2 (Profile Submission): Completed - Profile and required documents submitted.";
                                            }

                                            // Step 3: Under Review
                                            if ($rawStatus === 'Under Review') {
                                                $s3Class = "active";
                                                $s3Tip   = "Step 3 (Under Review): Current Step - Application is being reviewed and verified by admin.";
                                            } elseif ($rawStatus === 'Approved' || $rawStatus === 'Rejected') {
                                                $s3Class = "completed";
                                                $s3Tip   = "Step 3 (Under Review): Completed - Review stage completed.";
                                            } else {
                                                $s3Class = "pending";
                                                $s3Tip   = "Step 3 (Under Review): Not yet done.";
                                            }

                                            // Step 4: Approval (Green) or Rejection (Red)
                                            if ($rawStatus === 'Approved') {
                                                $s4Class = "completed";
                                                $s4Tip   = "Step 4 (Approved): Completed - Application approved & Student ID ({$app['StudentNumber']}) assigned.";
                                            } elseif ($rawStatus === 'Rejected') {
                                                $s4Class = "rejected";
                                                $s4Tip   = "Step 4 (Rejected): Application marked as rejected (Can be undone).";
                                            } else {
                                                $s4Class = "pending";
                                                $s4Tip   = "Step 4 (Approval / Rejection): Not yet done.";
                                            }

                                            // Pipeline Label
                                            if ($rawStatus === 'Pending') $stageLabel = "Stage 2: Pending Submission";
                                            elseif ($rawStatus === 'Under Review') $stageLabel = "Stage 3: Under Review";
                                            elseif ($rawStatus === 'Approved') $stageLabel = "Stage 4: Approved (ID: {$app['StudentNumber']})";
                                            elseif ($rawStatus === 'Rejected') $stageLabel = "Stage 4: Rejected";
                                            else $stageLabel = "Stage 1: Account Created";
                                        ?>
                                        <tr data-name="<?= strtolower(htmlspecialchars("{$app['FirstName']} {$app['LastName']}")) ?>" data-status="<?= htmlspecialchars($rawStatus) ?>" data-date="<?= htmlspecialchars($app['SubmittedAt']) ?>">
                                            <td>
                                                <div class="applicant-profile-cell">
                                                    <?php 
                                                        $avatar = !empty($app['ProfilePicture']) ? '../assets/uploads/profiles/' . htmlspecialchars($app['ProfilePicture']) : '../assets/images/default-avatar.png';
                                                    ?>
                                                    <img src="<?= $avatar ?>" alt="Profile" class="applicant-thumb">
                                                    <div>
                                                        <button type="button" class="applicant-name-btn" onclick='openApplicantModal(<?= json_encode($app) ?>)'><?= htmlspecialchars("{$app['FirstName']} {$app['LastName']}") ?></button>
                                                        <span class="app-number"><?= htmlspecialchars($app['ApplicationNumber']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($app['Prog1Name'] ?? 'Unspecified Program') ?></td>
                                            <td><?= htmlspecialchars($app['ContactNumber'] ?? 'N/A') ?><br><small class="text-muted"><?= htmlspecialchars($app['UserEmail']) ?></small></td>
                                            <td>
                                                <div class="progress-pipeline">
                                                    <div class="pipeline-step <?= $s1Class ?>" title="<?= htmlspecialchars($s1Tip) ?>">1</div>
                                                    <div class="pipeline-step <?= $s2Class ?>" title="<?= htmlspecialchars($s2Tip) ?>">2</div>
                                                    <div class="pipeline-step <?= $s3Class ?>" title="<?= htmlspecialchars($s3Tip) ?>">3</div>
                                                    <div class="pipeline-step <?= $s4Class ?>" title="<?= htmlspecialchars($s4Tip) ?>">4</div>
                                                </div>
                                                <div class="pipeline-labels"><?= $stageLabel ?></div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 6px; align-items: center;">
                                                    <?php if ($rawStatus === 'Pending'): ?>
                                                        <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">Awaiting Review</span>
                                                        <form action="applicants" method="POST" onsubmit="return confirm('Securely delete this applicant record?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_applicant">
                                                            <input type="hidden" name="applicant_id" value="<?= $app['ApplicantID'] ?>">
                                                            <button type="submit" class="btn btn-sm" style="background: #fee2e2; color: #dc2626;" title="Delete Nuisance/Duplicate">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-primary btn-sm" onclick='openApplicantModal(<?= json_encode($app) ?>)'>
                                                            <i class="fa-solid fa-eye"></i> Review
                                                        </button>
                                                        <form action="applicants" method="POST" onsubmit="return confirm('Securely delete this applicant record?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_applicant">
                                                            <input type="hidden" name="applicant_id" value="<?= $app['ApplicantID'] ?>">
                                                            <button type="submit" class="btn btn-sm" style="background: #fee2e2; color: #dc2626;" title="Delete Record">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">No applicant records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <span class="text-muted">Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (Total: <?= $totalRecords ?> applicants)</span>
                            <div class="pagination-links">
                                <?php if ($page > 1): ?>
                                    <a href="applicants?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Previous</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="applicants?page=<?= $i ?>" class="btn btn-sm <?= ($i == $page) ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="applicants?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- APPLICANT REVIEW MODAL -->
    <div id="applicantModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalApplicantTitle"><i class="fa-solid fa-user-graduate"></i> Applicant Review</h3>
                <button type="button" class="modal-close-btn" onclick="closeApplicantModal()">&times;</button>
            </div>

            <div class="modal-tabs">
                <button type="button" class="modal-tab-btn active" onclick="switchTab('profile')">Profile Details & Status</button>
                <button type="button" class="modal-tab-btn" onclick="switchTab('documents')">Documents Uploaded & Verification</button>
            </div>

            <div class="modal-body">
                
                <div class="modal-profile-banner">
                    <img id="detAvatar" src="../assets/images/default-avatar.png" alt="Applicant Photo" class="modal-avatar-large">
                    <div>
                        <h3 id="detName" style="margin: 0 0 4px 0; color: var(--primary);">Candidate Name</h3>
                        <p class="text-muted" style="margin: 0; font-size: 0.9rem;">Application ID: <strong id="detAppNo">APP-2026-0000</strong></p>
                        <p class="text-muted" style="margin: 2px 0 0 0; font-size: 0.85rem;">Status: <span id="detStatusBadge" style="font-weight: 600;">Pending</span></p>
                    </div>
                </div>

                <!-- Tab 1: Profile Details -->
                <div id="tabProfile" class="tab-pane active">
                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                        <h4 style="margin-top: 0; color: var(--primary);">Personal Information Record</h4>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.95rem;">
                            <div><strong>Sex:</strong> <span id="detGender">Male</span></div>
                            <div><strong>Birth Date:</strong> <span id="detBirthDate">N/A</span></div>
                            <div><strong>Contact Number:</strong> <span id="detContact">N/A</span></div>
                            <div><strong>Email Address:</strong> <span id="detEmail">N/A</span></div>
                            <div style="grid-column: span 2;"><strong>Residential Address:</strong> <span id="detAddress">N/A</span></div>
                        </div>
                    </div>

                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; color: var(--primary);">Preferred Degree Programs</h4>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 15px;">
                        <ul style="margin: 0; padding-left: 20px; font-size: 0.95rem; display: flex; flex-direction: column; gap: 8px;">
                            <li><strong>1st Preference:</strong> <span id="detProg1">N/A</span></li>
                            <li><strong>2nd Preference:</strong> <span id="detProg2">N/A</span></li>
                            <li><strong>3rd Preference:</strong> <span id="detProg3">N/A</span></li>
                        </ul>
                    </div>
                </div>

                <!-- Tab 2: Documents Uploaded -->
                <div id="tabDocuments" class="tab-pane">
                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; color: var(--primary);">Document Verification & Remarks</h4>
                        <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 15px;">Review each credential using the secure viewer modal, and assign individual verification status with remarks.</p>

                        <div id="modalDocsContainer">
                            <!-- Injected dynamically via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" id="modalFooterActions">
                <!-- Action buttons injected dynamically -->
            </div>
        </div>
    </div>

    <!-- DOCUMENT VIEWER MODAL -->
    <div id="docViewerModal" class="modal-overlay" style="z-index: 2500;">
        <div class="doc-viewer-card">
            <div class="modal-header">
                <h3 id="viewerModalTitle"><i class="fa-solid fa-file"></i> Document Viewer</h3>
                <button type="button" class="modal-close-btn" onclick="closeDocViewerModal()">&times;</button>
            </div>
            <div class="doc-viewer-content" id="viewerContentContainer">
                <!-- Injected iframe for PDF or img for image viewer -->
            </div>
            <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span class="text-muted" style="font-size: 0.85rem;">AMSC InDaw Secure Admin Preview (Zoom & Fullscreen supported)</span>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="zoomInDoc()">
                        <i class="fa-solid fa-magnifying-glass-plus"></i> Zoom In
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="zoomOutDoc()">
                        <i class="fa-solid fa-magnifying-glass-minus"></i> Zoom Out
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleFullscreenDoc()">
                        <i class="fa-solid fa-expand"></i> Fullscreen
                    </button>
                    <a id="downloadDocLink" href="#" download class="btn btn-primary btn-sm" style="text-decoration: none;">
                        <i class="fa-solid fa-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        let currentZoom = 1;

        function openApplicantModal(app) {
            document.getElementById('modalApplicantTitle').innerHTML = `<i class="fa-solid fa-user-graduate"></i> Reviewing: ${app.FirstName} ${app.LastName} (${app.ApplicationNumber})`;
            document.getElementById('detAppNo').textContent = app.ApplicationNumber;
            document.getElementById('detName').textContent = `${app.FirstName || ''} ${app.MiddleName || ''} ${app.LastName || ''} ${app.ExtName || ''}`.trim();
            document.getElementById('detStatusBadge').textContent = app.ApplicationStatus || 'Pending';
            document.getElementById('detGender').textContent = app.Gender || 'N/A';
            document.getElementById('detBirthDate').textContent = app.BirthDate || 'N/A';
            document.getElementById('detContact').textContent = app.ContactNumber || 'N/A';
            document.getElementById('detEmail').textContent = app.UserEmail || 'N/A';
            document.getElementById('detAddress').textContent = app.Address || 'N/A';
            
            document.getElementById('detProg1').textContent = app.Prog1Name || 'Unspecified';
            document.getElementById('detProg2').textContent = app.Prog2Name || 'None';
            document.getElementById('detProg3').textContent = app.Prog3Name || 'None';

            if (app.ProfilePicture) {
                document.getElementById('detAvatar').src = `../assets/uploads/profiles/${app.ProfilePicture}`;
            } else {
                document.getElementById('detAvatar').src = '../assets/images/default-avatar.png';
            }

            const docsContainer = document.getElementById('modalDocsContainer');
            docsContainer.innerHTML = '';
            
            if (app.Documents && app.Documents.length > 0) {
                app.Documents.forEach(doc => {
                    const isPdf = doc.FilePath.toLowerCase().endsWith('.pdf');
                    const fileUrl = `../assets/uploads/documents/${doc.FilePath}`;
                    const docCard = document.createElement('div');
                    docCard.className = 'doc-review-card';
                    docCard.innerHTML = `
                        <div class="doc-review-header">
                            <div>
                                <strong>${doc.DocumentType}</strong>
                                <span class="text-muted" style="display: block; font-size: 0.8rem;">
                                    <i class="fa-solid ${isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary'}"></i> ${doc.FilePath}
                                </span>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openDocViewerModal('${doc.DocumentType}', '${fileUrl}', ${isPdf})">
                                <i class="fa-solid fa-eye"></i> View ${isPdf ? 'PDF' : 'Image'}
                            </button>
                        </div>
                        <div class="doc-review-actions">
                            <input type="text" id="docRemarks_${doc.DocumentID}" value="${doc.Remarks || ''}" placeholder="Enter remarks for this document...">
                            <select id="docStatus_${doc.DocumentID}" class="form-control" style="width: 140px; padding: 6px;" onchange="updateDocumentStatus(${doc.ApplicantID}, ${doc.DocumentID})">
                                <option value="Pending" ${doc.VerificationStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Approved" ${doc.VerificationStatus === 'Approved' ? 'selected' : ''}>Approved</option>
                                <option value="Rejected" ${doc.VerificationStatus === 'Rejected' ? 'selected' : ''}>Rejected</option>
                            </select>
                        </div>
                    `;
                    docsContainer.appendChild(docCard);
                });
            } else {
                docsContainer.innerHTML = '<p class="text-muted" style="text-align: center; padding: 20px;">No documents uploaded yet by this applicant.</p>';
            }

            const footerContainer = document.getElementById('modalFooterActions');
            footerContainer.innerHTML = '';

            if (app.ApplicationStatus === 'Pending') {
                footerContainer.innerHTML = `
                    <div style="text-align: center; padding: 10px; color: var(--text-muted); font-size: 0.9rem;">
                        <i class="fa-solid fa-circle-info"></i> Applicant is still completing submission. Action buttons available once status changes to <strong>Under Review</strong>.
                    </div>
                `;
            } else if (app.ApplicationStatus === 'Under Review') {
                footerContainer.innerHTML = `
                    <div class="admin-action-row">
                        <form action="applicants" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="return_application">
                            <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Return application for re-submission?');">
                                <i class="fa-solid fa-rotate-left"></i> Return to Application
                            </button>
                        </form>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <form action="applicants" method="POST" style="display:inline; display: flex; gap: 5px; align-items: center;">
                                <input type="hidden" name="action" value="approve_application">
                                <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                                <select name="selected_program_id" class="form-control" style="font-size: 0.85rem; padding: 5px;">
                                    <option value="${app.PreferredProgramID_1}">1st: ${app.Prog1Name}</option>
                                    ${app.PreferredProgramID_2 ? `<option value="${app.PreferredProgramID_2}">2nd: ${app.Prog2Name}</option>` : ''}
                                    ${app.PreferredProgramID_3 ? `<option value="${app.PreferredProgramID_3}">3rd: ${app.Prog3Name}</option>` : ''}
                                </select>
                                <button type="submit" class="btn btn-sm" style="background: #d1fae5; color: #065f46;" onclick="return confirm('Approve applicant and generate Student ID?');">
                                    <i class="fa-solid fa-check"></i> Approve & Enroll
                                </button>
                            </form>
                            
                            <form action="applicants" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="reject_application">
                                <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                                <button type="submit" class="btn" style="background: #fee2e2; color: #991b1b;" onclick="return confirm('Reject this application?');">
                                    <i class="fa-solid fa-xmark"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            } else if (app.ApplicationStatus === 'Rejected') {
                footerContainer.innerHTML = `
                    <div class="admin-action-row" style="justify-content: space-between;">
                        <span class="text-danger" style="font-weight: 600;"><i class="fa-solid fa-triangle-exclamation"></i> Application currently marked as REJECTED.</span>
                        <form action="applicants" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="undo_status">
                            <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                            <input type="hidden" name="target_status" value="Under Review">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-undo"></i> Undo Rejection (Restore to Review)
                            </button>
                        </form>
                    </div>
                `;
            } else if (app.ApplicationStatus === 'Approved') {
                footerContainer.innerHTML = `
                    <div class="admin-action-row" style="justify-content: space-between; align-items: center;">
                        <span style="color: #065f46; font-size: 0.9rem; font-weight: 600;">
                            <i class="fa-solid fa-circle-check"></i> Approved (Student ID: <strong>${app.StudentNumber || 'Assigned'}</strong>)
                        </span>
                        <form action="applicants" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="undo_approval">
                            <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                            <button type="submit" class="btn btn-sm" style="background: #fef3c7; color: #b45309; border: 1px solid #f59e0b;" onclick="return confirm('Are you sure you want to undo this approval? This will revert the application status to Under Review and delete the student record.');">
                                <i class="fa-solid fa-rotate-left"></i> Undo Approval
                            </button>
                        </form>
                    </div>
                `;
            }

            switchTab('profile');
            document.getElementById('applicantModal').classList.add('active');
        }

        function closeApplicantModal() {
            document.getElementById('applicantModal').classList.remove('active');
        }

        function switchTab(tabName) {
            document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            if (tabName === 'profile') {
                document.querySelectorAll('.modal-tab-btn')[0].classList.add('active');
                document.getElementById('tabProfile').classList.add('active');
            } else if (tabName === 'documents') {
                document.querySelectorAll('.modal-tab-btn')[1].classList.add('active');
                document.getElementById('tabDocuments').classList.add('active');
            }
        }

        function updateDocumentStatus(applicantID, documentID) {
            const status = document.getElementById(`docStatus_${documentID}`).value;
            const remarks = document.getElementById(`docRemarks_${documentID}`).value;

            const formData = new FormData();
            formData.append('action', 'verify_document');
            formData.append('applicant_id', applicantID);
            formData.append('document_id', documentID);
            formData.append('doc_status', status);
            formData.append('doc_remarks', remarks);

            fetch('applicants', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if(data.status !== 'success') {
                    alert('Failed to update document verification status.');
                }
            }).catch(err => console.error(err));
        }

        function openDocViewerModal(title, url, isPdf) {
            document.getElementById('viewerModalTitle').innerHTML = `<i class="fa-solid ${isPdf ? 'fa-file-pdf' : 'fa-file-image'}"></i> ${title}`;
            document.getElementById('downloadDocLink').href = url;
            currentZoom = 1;

            const container = document.getElementById('viewerContentContainer');
            container.innerHTML = '';

            if (isPdf) {
                const iframe = document.createElement('iframe');
                iframe.src = url;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                container.appendChild(iframe);
            } else {
                const img = document.createElement('img');
                img.id = 'zoomableImage';
                img.src = url;
                img.alt = title;
                container.appendChild(img);
            }

            document.getElementById('docViewerModal').classList.add('active');
        }

        function closeDocViewerModal() {
            document.getElementById('docViewerModal').classList.remove('active');
            document.getElementById('viewerContentContainer').innerHTML = '';
        }

        function zoomInDoc() {
            currentZoom += 0.25;
            const img = document.getElementById('zoomableImage');
            if (img) img.style.transform = `scale(${currentZoom})`;
        }

        function zoomOutDoc() {
            if (currentZoom > 0.5) currentZoom -= 0.25;
            const img = document.getElementById('zoomableImage');
            if (img) img.style.transform = `scale(${currentZoom})`;
        }

        function toggleFullscreenDoc() {
            const card = document.querySelector('.doc-viewer-card');
            if (!document.fullscreenElement) {
                card.requestFullscreen().catch(err => alert(`Error attempting to enable fullscreen: ${err.message}`));
            } else {
                document.exitFullscreen();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const sortSelect = document.getElementById('sortSelect');
            const tableBody = document.querySelector('#applicantsTable tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));

            function filterAndSortTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = statusFilter.value;
                const sortBy = sortSelect.value;

                const filteredRows = rows.filter(row => {
                    const text = row.textContent.toLowerCase();
                    const status = row.getAttribute('data-status');
                    return text.includes(query) && (!selectedStatus || status === selectedStatus);
                });

                filteredRows.sort((a, b) => {
                    const nameA = a.getAttribute('data-name');
                    const nameB = b.getAttribute('data-name');
                    const dateA = new Date(a.getAttribute('data-date'));
                    const dateB = new Date(b.getAttribute('data-date'));

                    if (sortBy === 'name') return nameA.localeCompare(nameB);
                    if (sortBy === 'date-desc') return dateB - dateA;
                    if (sortBy === 'date-asc') return dateA - dateB;
                });

                tableBody.innerHTML = '';
                if (filteredRows.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">No matching applicants found.</td></tr>`;
                } else {
                    filteredRows.forEach(row => tableBody.appendChild(row));
                }
            }

            if(searchInput) searchInput.addEventListener('input', filterAndSortTable);
            if(statusFilter) statusFilter.addEventListener('change', filterAndSortTable);
            if(sortSelect) sortSelect.addEventListener('change', filterAndSortTable);
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>