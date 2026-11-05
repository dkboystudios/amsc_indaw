<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Admin Dashboard - AMSC InnDaw";

// --- HANDLE POST REQUESTS FOR MODAL ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $targetApplicantID = filter_input(INPUT_POST, 'applicant_id', FILTER_VALIDATE_INT);

    try {
        // 1. Update Overall Application Status (with Undo capability)
        if ($action === 'update_overall_status' && $targetApplicantID) {
            $newStatus = trim($_POST['overall_status'] ?? '');
            $adminRemarks = trim($_POST['overall_remarks'] ?? '');

            $allowedStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected'];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                throw new Exception("Invalid application status selection.");
            }

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = ?, AdminRemarks = ?, UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $newStatus, $adminRemarks, $targetApplicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Application Status Updated', "Updated applicant ID #{$targetApplicantID} status to {$newStatus}.");
            redirectWithMessage('success', "Application status updated to '{$newStatus}'.", 'dashboard');
        }

        // 2. Individual Document Verification with Remarks
        elseif ($action === 'verify_document') {
            $docID = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
            $docStatus = trim($_POST['doc_status'] ?? '');
            $docRemarks = trim($_POST['doc_remarks'] ?? '');

            if (!$docID || !$targetApplicantID) {
                throw new Exception("Invalid document reference.");
            }

            $stmt = mysqli_prepare($conn, "UPDATE applicant_documents SET VerificationStatus = ?, Remarks = ?, VerifiedBy = ?, VerifiedAt = NOW() WHERE ID = ? AND ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'ssiii', $docStatus, $docRemarks, $userID, $docID, $targetApplicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID ?? 0, 'Document Verification', "Updated document ID #{$docID} status to {$docStatus}.");
            redirectWithMessage('success', "Document status updated to '{$docStatus}'.", 'dashboard');
        }
    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), 'dashboard');
    }
}

// --- SET OR FETCH ACADEMIC YEAR SELECTION ---
$targetAyID = $_SESSION['selected_ay_id'] ?? null;
if (!$targetAyID) {
    $activeQuery = mysqli_query($conn, "SELECT ID FROM academic_years WHERE IsActive = 1 LIMIT 1");
    if ($activeRow = mysqli_fetch_assoc($activeQuery)) {
        $targetAyID = $activeRow['ID'];
    } else {
        $fallbackQuery = mysqli_query($conn, "SELECT ID FROM academic_years ORDER BY CreatedAt DESC LIMIT 1");
        if ($fallbackRow = mysqli_fetch_assoc($fallbackQuery)) {
            $targetAyID = $fallbackRow['ID'];
        } else {
            $targetAyID = 1;
        }
    }
}

$currentAySql = "SELECT ID, YearCode, Semester, EnrollmentStatus, CONCAT('AY ', YearCode, ', ', Semester) AS Term FROM academic_years WHERE ID = $targetAyID LIMIT 1";
$currentAyRes = mysqli_query($conn, $currentAySql);
$currentAyData = mysqli_fetch_assoc($currentAyRes);

$activeTerm = $currentAyData['Term'] ?? 'Unknown Term';
$enrollmentStatus = $currentAyData['EnrollmentStatus'] ?? 'Closed';
$_SESSION['active_academic_term'] = $activeTerm;

$aySql = "SELECT ID, CONCAT('AY ', YearCode, ', ', Semester) AS Term, IsActive, EnrollmentStatus FROM academic_years ORDER BY CreatedAt DESC";
$ayRes = mysqli_query($conn, $aySql);
$acadYears = array();
while ($ayRow = mysqli_fetch_assoc($ayRes)) {
    $acadYears[] = $ayRow;
}

// 1. Fetch Dynamic Statistics Counts (Applicants)
$totalApplicants = 0;
$pendingReviews = 0;
$enrolledStudents = 0;
$rejectedApplications = 0;

$resTotal = mysqli_query($conn, "SELECT COUNT(*) AS count FROM applicants");
if ($row = mysqli_fetch_assoc($resTotal)) { $totalApplicants = $row['count']; }

$resPending = mysqli_query($conn, "SELECT COUNT(*) AS count FROM applicants WHERE ApplicationStatus = 'Pending' OR ApplicationStatus = 'Under Review'");
if ($row = mysqli_fetch_assoc($resPending)) { $pendingReviews = $row['count']; }

$resEnrolled = mysqli_query($conn, "SELECT COUNT(*) AS count FROM applicants WHERE ApplicationStatus = 'Enrolled' OR ApplicationStatus = 'Approved'");
if ($row = mysqli_fetch_assoc($resEnrolled)) { $enrolledStudents = $row['count']; }

$resRejected = mysqli_query($conn, "SELECT COUNT(*) AS count FROM applicants WHERE ApplicationStatus = 'Rejected'");
if ($row = mysqli_fetch_assoc($resRejected)) { $rejectedApplications = $row['count']; }

// 2. Fetch Recent Applicant Submissions with Detailed Information & Documents JSON for Modal Loading
$recentApplicantsQuery = "
    SELECT a.*, 
           p1.ProgramName AS Prog1, 
           p2.ProgramName AS Prog2, 
           p3.ProgramName AS Prog3,
           u.Email AS UserEmail
    FROM applicants a 
    LEFT JOIN programs p1 ON a.PreferredProgramID_1 = p1.ID 
    LEFT JOIN programs p2 ON a.PreferredProgramID_2 = p2.ID 
    LEFT JOIN programs p3 ON a.PreferredProgramID_3 = p3.ID 
    LEFT JOIN users u ON a.UserID = u.ID
    ORDER BY a.ApplicantID DESC 
    LIMIT 8
";

// Fetch all applicants with their documents mapped for instant modal access
$applicantsMap = [];
$allApplicantsRes = mysqli_query($conn, $recentApplicantsQuery);
while ($appRow = mysqli_fetch_assoc($allApplicantsRes)) {
    $appID = $appRow['ApplicantID'];
    // Fetch documents for this applicant
    $docRes = mysqli_query($conn, "SELECT * FROM applicant_documents WHERE ApplicantID = $appID");
    $docs = [];
    while ($dRow = mysqli_fetch_assoc($docRes)) {
        $docs[] = $dRow;
    }
    $appRow['Documents'] = $docs;
    $applicantsMap[$appID] = $appRow;
}

// 3. Fetch Demography Data: Applicants by Gender
$genderQuery = "SELECT Gender, COUNT(*) as count FROM applicants GROUP BY Gender";
$genderResult = mysqli_query($conn, $genderQuery);
$genderData = ['Male' => 0, 'Female' => 0, 'Other' => 0];
while ($gRow = mysqli_fetch_assoc($genderResult)) {
    $sex = ucfirst(strtolower(trim($gRow['Gender'] ?? 'Other')));
    if (isset($genderData[$sex])) {
        $genderData[$sex] = intval($gRow['count']);
    } else {
        $genderData['Other'] += intval($gRow['count']);
    }
}

// 4. Fetch Demography Data: Applicants by Degree Program Preference
$progDemogQuery = "
    SELECT p.ProgramName, COUNT(a.ApplicantID) as count 
    FROM programs p 
    LEFT JOIN applicants a ON p.ID = a.PreferredProgramID_1 
    GROUP BY p.ID, p.ProgramName 
    ORDER BY count DESC
";
$progDemogResult = mysqli_query($conn, $progDemogQuery);
$programLabels = [];
$programCounts = [];
while ($pRow = mysqli_fetch_assoc($progDemogResult)) {
    $programLabels[] = $pRow['ProgramName'];
    $programCounts[] = intval($pRow['count']);
}

// 5. Fetch Staff Statistics & Demography by Gender
$totalStaff = 0;
$staffGenderData = ['Male' => 0, 'Female' => 0];
$staffTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'staff_members'");
if ($staffTableCheck && mysqli_num_rows($staffTableCheck) > 0) {
    $staffResTotal = mysqli_query($conn, "SELECT COUNT(*) AS count FROM staff_members");
    if ($row = mysqli_fetch_assoc($staffResTotal)) { $totalStaff = $row['count']; }

    $staffGenderRes = mysqli_query($conn, "SELECT Gender, COUNT(*) as count FROM staff_members GROUP BY Gender");
    while ($sgRow = mysqli_fetch_assoc($staffGenderRes)) {
        $sSex = ucfirst(strtolower(trim($sgRow['Gender'] ?? '')));
        if (isset($staffGenderData[$sSex])) {
            $staffGenderData[$sSex] = intval($sgRow['count']);
        }
    }
} else {
    $totalStaff = 14; 
    $staffGenderData = ['Male' => 8, 'Female' => 6];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); padding: 20px; display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-icon { width: 54px; height: 54px; border-radius: 12px; background-color: var(--green-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .stat-icon.accent { background-color: #fef3c7; color: #d97706; }
        .stat-icon.danger { background-color: #fee2e2; color: #dc2626; }
        .stat-icon.info { background-color: #e0f2fe; color: #0284c7; }
        .stat-info h4 { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin: 0 0 4px 0; }
        .stat-info span { font-size: 1.6rem; font-weight: 700; color: var(--primary); }
        
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 992px) { .charts-row { grid-template-columns: 1fr; } }
        .chart-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; }
        .chart-card h3 { font-size: 1.05rem; color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .chart-container { position: relative; width: 100%; height: 260px; }

        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .admin-table th, .admin-table td { padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
        .admin-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .admin-table tr:hover { background-color: #f8fafc; }

        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status.pending { background-color: #fef3c7; color: #b45309; }
        .badge-status.approved { background-color: #d1fae5; color: #065f46; }
        .badge-status.rejected { background-color: #fee2e2; color: #991b1b; }
        .badge-status.re-upload { background-color: #e0f2fe; color: #0369a1; }

        .action-btns { display: flex; gap: 8px; }
        .term-config-bar { background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }

        .enrollment-alert-banner { padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .enrollment-alert-banner.open { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .enrollment-alert-banner.closed { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* --- MODAL STYLING & TABS --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 92%; max-width: 750px; border-radius: 10px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }

        .modal-tabs { display: flex; background: #f1f5f9; border-bottom: 1px solid var(--border-color); }
        .modal-tab-btn { flex: 1; padding: 12px; background: transparent; border: none; font-weight: 600; font-size: 0.9rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s ease; text-align: center; }
        .modal-tab-btn.active { background: #ffffff; color: var(--primary); border-bottom: 3px solid var(--primary); }

        .modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #f8fafc; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .modal-profile-banner { display: flex; gap: 20px; align-items: center; background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; }
        .modal-avatar-large { width: 85px; height: 85px; border-radius: 50%; border: 3px solid var(--primary); object-fit: cover; }

        .doc-review-card { background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 12px; }
        .doc-review-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .doc-review-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border-top: 1px dashed var(--border-color); padding-top: 12px; }

        /* 3-Step Progress Tracker */
        .stepper-wrapper { display: flex; justify-content: space-between; position: relative; margin: 15px 0 25px 0; padding: 0 10px; }
        .stepper-wrapper::before { content: ''; position: absolute; top: 50%; left: 15%; right: 15%; height: 4px; background: #e2e8f0; z-index: 1; transform: translateY(-50%); }
        .stepper-progress { position: absolute; top: 50%; left: 15%; height: 4px; background: var(--primary, #0284c7); z-index: 1; transform: translateY(-50%); transition: width 0.3s ease; }
        .step-item { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; text-align: center; }
        .step-circle { width: 38px; height: 38px; border-radius: 50%; background: #ffffff; border: 3px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.85rem; color: #64748b; }
        .step-label { font-size: 0.78rem; font-weight: 600; color: #64748b; }
        .step-item.active .step-circle { border-color: var(--primary); background: #e0f2fe; color: var(--primary); }
        .step-item.completed .step-circle { border-color: #10b981; background: #10b981; color: #ffffff; }
        .step-item.completed .step-label { color: #065f46; }
        .step-item.rejected .step-circle { border-color: #ef4444; background: #ef4444; color: #ffffff; }
        .step-item.rejected .step-label { color: #991b1b; }

        .modal-footer { padding: 14px 20px; background: #ffffff; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

        .pdf-modal-card { background: #ffffff; width: 95%; max-width: 900px; height: 85vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Registrar / Admin Overview</h2>
                    <p class="text-muted">Monitor admission pipelines, verify documents, and manage institutional records.</p>
                </div>
                <div class="application-id-badge" style="background: var(--primary); color: #fff; padding: 6px 14px; border-radius: 6px;">
                    <span>Active Term:</span> <strong><?= htmlspecialchars($activeTerm) ?></strong>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <!-- Active Academic Year Configuration Bar -->
            <div class="term-config-bar">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-calendar-days" style="color: var(--primary); font-size: 1.2rem;"></i>
                    <span style="font-size: 0.95rem; font-weight: 600;">Academic Year & Term Configuration</span>
                </div>
                <form method="POST" action="dashboard" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="action" value="update_academic_term">
                    <select id="ayFilter" name="active_term" class="form-control" style="width: 300px; padding: 6px 10px; font-size: 0.85rem;">
                        <?php foreach ($acadYears as $ay): ?>
                            <option value="<?= $ay['ID'] ?>" <?= ($ay['ID'] == $targetAyID) ? 'selected' : '' ?>>
                                <?= $ay['Term'] ?> <?= ($ay['IsActive'] == 1 ? ' (Active)' : '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Set Active Term</button>
                </form>
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

            <!-- Metrics Statistics Grid -->
            <div class="admin-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <h4>Total Applicants</h4>
                        <span><?= number_format($totalApplicants) ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon accent"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="stat-info">
                        <h4>Pending Reviews</h4>
                        <span><?= number_format($pendingReviews) ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #d1fae5; color: #065f46;"><i class="fa-solid fa-user-check"></i></div>
                    <div class="stat-info">
                        <h4>Enrolled Students</h4>
                        <span><?= number_format($enrolledStudents) ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="stat-info">
                        <h4>Active Staff Members</h4>
                        <span><?= number_format($totalStaff) ?></span>
                    </div>
                </div>
            </div>

            <!-- DEMOGRAPHY CHARTS SECTION -->
            <div class="charts-row">
                <div class="chart-card">
                    <h3><i class="fa-solid fa-chart-bar" style="color: var(--primary);"></i> Applicants by Degree Program Preference</h3>
                    <div class="chart-container">
                        <canvas id="programBarChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3><i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i> Applicant Gender Distribution</h3>
                    <div class="chart-container">
                        <canvas id="genderPieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- RECENT APPLICANT SUBMISSIONS TABLE -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fa-solid fa-user-clock" style="color: var(--primary);"></i> Recent Applicant Submissions</h3>
                    <a href="applicants" class="btn btn-outline btn-sm" style="color: var(--primary); background: transparent; border: 1px solid var(--primary); text-decoration: none;">View All Applicants</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>App No.</th>
                                    <th>Applicant Name</th>
                                    <th>Contact Details</th>
                                    <th>1st Program Preference</th>
                                    <th>Submission Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($applicantsMap)): ?>
                                    <?php foreach ($applicantsMap as $applicant): ?>
                                        <?php 
                                            $statusClass = 'pending';
                                            $statusIcon = 'fa-clock';
                                            $currentStatus = $applicant['ApplicationStatus'] ?? 'Pending';

                                            if ($currentStatus === 'Approved' || $currentStatus === 'Enrolled') {
                                                $statusClass = 'approved';
                                                $statusIcon = 'fa-check';
                                            } elseif ($currentStatus === 'Under Review') {
                                                $statusClass = 'pending';
                                                $statusIcon = 'fa-magnifying-glass';
                                            } elseif ($currentStatus === 'Rejected' || $currentStatus === 'Re-upload') {
                                                $statusClass = 'rejected';
                                                $statusIcon = 'fa-triangle-exclamation';
                                            }
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($applicant['ApplicationNumber'] ?? 'APP-2026-0000') ?></strong></td>
                                            <td>
                                                <div style="font-weight: 600;"><?= htmlspecialchars(($applicant['FirstName'] ?? '') . ' ' . ($applicant['LastName'] ?? '')) ?></div>
                                                <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($applicant['Gender'] ?? '') ?></div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem;"><?= htmlspecialchars($applicant['ContactNumber'] ?? 'N/A') ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($applicant['Prog1'] ?? 'Unspecified') ?></td>
                                            <td><?= isset($applicant['SubmittedAt']) ? date('M d, Y', strtotime($applicant['SubmittedAt'])) : 'N/A' ?></td>
                                            <td>
                                                <span class="badge-status <?= $statusClass ?>">
                                                    <i class="fa-solid <?= $statusIcon ?>"></i> <?= htmlspecialchars($currentStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openApplicantModal(<?= json_encode($applicant) ?>)'>
                                                        <i class="fa-solid fa-eye"></i> Review
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: var(--text-muted);">No recent applicant records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- STAFF MEMBERS OVERVIEW PANEL & DEMOGRAPHY -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fa-solid fa-id-badge" style="color: var(--primary);"></i> Staff Members & Institutional Personnel Demographics</h3>
                    <a href="employees" class="btn btn-outline btn-sm" style="color: var(--primary); background: transparent; border: 1px solid var(--primary); text-decoration: none;">Manage Staff Directory</a>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.9rem; line-height: 1.6;">
                                Overview of administrative personnel, faculty instructors, and technical support teams assigned across departments for term <strong><?= htmlspecialchars($activeTerm) ?></strong>.
                            </p>
                            <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 8px 12px; border-radius: 6px; font-size: 0.88rem;">
                                    <span>Total Registered Staff:</span>
                                    <strong><?= $totalStaff ?> Personnel</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 8px 12px; border-radius: 6px; font-size: 0.88rem;">
                                    <span>Male Personnel:</span>
                                    <strong><?= $staffGenderData['Male'] ?> (<?= ($totalStaff > 0) ? round(($staffGenderData['Male'] / $totalStaff) * 100, 1) : 0 ?>%)</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 8px 12px; border-radius: 6px; font-size: 0.88rem;">
                                    <span>Female Personnel:</span>
                                    <strong><?= $staffGenderData['Female'] ?> (<?= ($totalStaff > 0) ? round(($staffGenderData['Female'] / $totalStaff) * 100, 1) : 0 ?>%)</strong>
                                </div>
                            </div>
                        </div>
                        <div style="width: 100%; max-width: 280px; margin: 0 auto; height: 220px; position: relative;">
                            <canvas id="staffGenderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- APPLICANT REVIEW MODAL -->
    <div id="applicantModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalApplicantTitle"><i class="fa-solid fa-user-graduate"></i> Applicant Review & Verification</h3>
                <button type="button" class="modal-close-btn" onclick="closeApplicantModal()">&times;</button>
            </div>

            <!-- Modal Tabs -->
            <div class="modal-tabs">
                <button type="button" id="tabBtnProfile" class="modal-tab-btn active" onclick="switchTab('profile')">Profile Details & Status</button>
                <button type="button" id="tabBtnDocs" class="modal-tab-btn" onclick="switchTab('documents')">Documents & Verification</button>
            </div>

            <div class="modal-body">
                <!-- Prominent Profile Picture Banner -->
                <div class="modal-profile-banner">
                    <img id="detAvatar" src="../assets/images/default-avatar.png" alt="Applicant Photo" class="modal-avatar-large">
                    <div>
                        <h3 id="detName" style="margin: 0 0 4px 0; color: var(--primary);">Candidate Name</h3>
                        <p class="text-muted" style="margin: 0; font-size: 0.9rem;">Application ID: <strong id="detAppNo">APP-2026-0000</strong></p>
                        <p class="text-muted" style="margin: 2px 0 0 0; font-size: 0.85rem;">Preferred Program: <span id="detProgram" style="font-weight: 600; color: var(--text-color);">BS Information Technology</span></p>
                    </div>
                </div>

                <!-- Tab 1: Profile Details & 3-Step Progress Tracker -->
                <div id="tabProfile" class="tab-pane active">
                    <!-- 3-Step Progress Tracker -->
                    <div style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                        <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--primary);">Application Pipeline Progress</h4>
                        <div class="stepper-wrapper">
                            <div id="stepperProgressBar" class="stepper-progress" style="width: 50%;"></div>
                            <div id="step1" class="step-item completed">
                                <div class="step-circle"><i class="fa-solid fa-paper-plane"></i></div>
                                <div class="step-label">Pending</div>
                            </div>
                            <div id="step2" class="step-item active">
                                <div class="step-circle"><i class="fa-solid fa-magnifying-glass"></i></div>
                                <div class="step-label">Under Review</div>
                            </div>
                            <div id="step3" class="step-item">
                                <div class="step-circle"><i class="fa-solid fa-check"></i></div>
                                <div class="step-label" id="step3Label">Approval</div>
                            </div>
                        </div>

                        <!-- Overall Status Update Form inside Tab 1 -->
                        <form action="dashboard" method="POST" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                            <input type="hidden" name="action" value="update_overall_status">
                            <input type="hidden" name="applicant_id" id="formApplicantID">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
                                <div class="form-group">
                                    <label style="font-size: 0.8rem; font-weight: 600;">Update Overall Status (Undo / Modify)</label>
                                    <select name="overall_status" id="modalStatusSelect" class="form-control" required style="padding: 6px;">
                                        <option value="Pending">Step 1: Pending (Reset)</option>
                                        <option value="Under Review">Step 2: Under Review</option>
                                        <option value="Approved">Step 3: Approve Application</option>
                                        <option value="Rejected">Step 3: Reject Application</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.8rem; font-weight: 600;">Admin Notes / Remarks</label>
                                    <input type="text" name="overall_remarks" id="modalRemarksInput" class="form-control" placeholder="Enter remarks..." style="padding: 6px 12px; font-size: 0.85rem;">
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
                            </div>
                        </form>
                    </div>

                    <!-- Personal Info Grid -->
                    <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--primary);">Personal Information Record</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.88rem; margin-top: 10px;">
                            <div><strong>Sex:</strong> <span id="detGender">Male</span></div>
                            <div><strong>Birth Date:</strong> <span id="detBirthDate">N/A</span></div>
                            <div><strong>Contact Number:</strong> <span id="detContact">N/A</span></div>
                            <div><strong>Email Address:</strong> <span id="detEmail">N/A</span></div>
                            <div style="grid-column: span 2;"><strong>Residential Address:</strong> <span id="detAddress">N/A</span></div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Documents Uploaded & Individual Verification -->
                <div id="tabDocuments" class="tab-pane">
                    <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--primary);">Document Verification & Remarks</h4>
                        <p class="text-muted" style="font-size: 0.82rem; margin-bottom: 15px;">Review each candidate requirement and assign individual verification statuses with remarks.</p>

                        <div id="modalDocsContainer">
                            <!-- Dynamically injected via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <span class="text-muted" style="font-size: 0.8rem;">AMSC InDaw Registrar Portal</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeApplicantModal()">Close Window</button>
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
            <div style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 0.82rem;">AMSC InDaw Secure Admin Preview</span>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="printPdfDocument()">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <a id="downloadPdfLink" href="#" download class="btn btn-primary btn-sm" style="text-decoration: none;">
                        <i class="fa-solid fa-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/snackbar.php'; ?>

    <script src="../assets/js/main.js"></script>

    <!-- Chart.js & Review Modal Script Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Program Preference Bar Chart
            const progCanvas = document.getElementById('programBarChart');
            if (progCanvas) {
                new Chart(progCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($programLabels) ?>,
                        datasets: [{
                            label: 'Applicants Count',
                            data: <?= json_encode($programCounts) ?>,
                            backgroundColor: 'rgba(2, 132, 199, 0.75)',
                            borderColor: 'rgba(2, 132, 199, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            // 2. Applicant Gender Pie Chart
            const genderCanvas = document.getElementById('genderPieChart');
            if (genderCanvas) {
                new Chart(genderCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Male', 'Female', 'Other'],
                        datasets: [{
                            data: [<?= $genderData['Male'] ?>, <?= $genderData['Female'] ?>, <?= $genderData['Other'] ?>],
                            backgroundColor: ['#3b82f6', '#ec4899', '#94a3b8']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
                    }
                });
            }

            // 3. Staff Gender Breakdown Chart
            const staffCanvas = document.getElementById('staffGenderChart');
            if (staffCanvas) {
                new Chart(staffCanvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Male Staff', 'Female Staff'],
                        datasets: [{
                            data: [<?= $staffGenderData['Male'] ?>, <?= $staffGenderData['Female'] ?>],
                            backgroundColor: ['#0284c7', '#10b981']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
                    }
                });
            }
        });

        // --- REVIEW MODAL & TAB LOGIC ---
        function openApplicantModal(app) {
            let formattedBirthday = 'N/A';
            if (app.BirthDate) {
                const birthday = app.BirthDate;
                const [year, month, day] = birthday.split('-').map(Number);
                if (year && month && day) {
                    const dateObj = new Date(year, month - 1, day);
                    formattedBirthday = new Intl.DateTimeFormat('en-US', {
                        month: 'long',
                        day: '2-digit',
                        year: 'numeric'
                    }).format(dateObj);
                }
            }

            document.getElementById('formApplicantID').value = app.ApplicantID;
            document.getElementById('detName').innerText = `${app.FirstName || ''} ${app.MiddleName || ''} ${app.LastName || ''} ${app.ExtName || ''}`.trim();
            document.getElementById('detAppNo').innerText = app.ApplicationNumber || 'N/A';
            document.getElementById('detProgram').innerText = app.Prog1 || 'Unspecified Program';
            document.getElementById('detGender').innerText = app.Gender || 'N/A';
            document.getElementById('detBirthDate').innerText = formattedBirthday;
            document.getElementById('detContact').innerText = app.ContactNumber || 'N/A';
            document.getElementById('detEmail').innerText = app.UserEmail || 'N/A';
            document.getElementById('detAddress').innerText = app.Address || 'N/A';
            
            if (app.ProfilePicture) {
                document.getElementById('detAvatar').src = `../assets/uploads/profiles/${app.ProfilePicture}`;
            } else {
                document.getElementById('detAvatar').src = '../assets/images/default-avatar.png';
            }

            document.getElementById('modalStatusSelect').value = app.ApplicationStatus || 'Pending';
            document.getElementById('modalRemarksInput').value = app.AdminRemarks || '';

            // Update Stepper Progress UI
            updateStepperUI(app.ApplicationStatus);

            // Populate Documents
            const docsContainer = document.getElementById('modalDocsContainer');
            docsContainer.innerHTML = '';
            
            if (app.Documents && app.Documents.length > 0) {
                app.Documents.forEach(doc => {
                    const docCard = document.createElement('div');
                    docCard.className = 'doc-review-card';
                    docCard.innerHTML = `
                        <div class="doc-review-header">
                            <div>
                                <strong style="font-size: 0.9rem; color: var(--primary);">${escapeHtml(doc.DocumentType || 'Requirement')}</strong>
                                <span class="text-muted" style="display: block; font-size: 0.78rem;">${escapeHtml(doc.FileName || 'File')}</span>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openPdfModal('${escapeHtml(doc.DocumentType || 'Document')}', '../assets/uploads/documents/${doc.FilePath}')">
                                <i class="fa-solid fa-eye"></i> View PDF
                            </button>
                        </div>
                        <form action="dashboard" method="POST" class="doc-review-actions">
                            <input type="hidden" name="action" value="verify_document">
                            <input type="hidden" name="applicant_id" value="${app.ApplicantID}">
                            <input type="hidden" name="doc_id" value="${doc.ID}">
                            
                            <input type="text" name="doc_remarks" value="${escapeHtml(doc.Remarks || '')}" placeholder="Enter remarks..." class="form-control" style="flex: 1; font-size: 0.82rem; padding: 5px 10px;">
                            
                            <select name="doc_status" class="form-control" style="width: 130px; font-size: 0.82rem; padding: 5px;" required>
                                <option value="Pending" ${doc.VerificationStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Approved" ${doc.VerificationStatus === 'Approved' ? 'selected' : ''}>Approve</option>
                                <option value="Re-upload" ${doc.VerificationStatus === 'Re-upload' ? 'selected' : ''}>Re-upload</option>
                                <option value="Rejected" ${doc.VerificationStatus === 'Rejected' ? 'selected' : ''}>Reject</option>
                            </select>

                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                        </form>
                    `;
                    docsContainer.appendChild(docCard);
                });
            } else {
                docsContainer.innerHTML = '<p class="text-muted" style="text-align: center; padding: 15px;">No uploaded documents found for this candidate.</p>';
            }

            switchTab('profile');
            document.getElementById('applicantModal').classList.add('active');
        }

        function closeApplicantModal() {
            document.getElementById('applicantModal').classList.remove('active');
        }

        function switchTab(tabName) {
            const btnProfile = document.getElementById('tabBtnProfile');
            const btnDocs = document.getElementById('tabBtnDocs');
            const paneProfile = document.getElementById('tabProfile');
            const paneDocs = document.getElementById('tabDocuments');

            if (tabName === 'profile') {
                btnProfile.classList.add('active');
                btnDocs.classList.remove('active');
                paneProfile.classList.add('active');
                paneDocs.classList.remove('active');
            } else {
                btnDocs.classList.add('active');
                btnProfile.classList.remove('active');
                paneDocs.classList.add('active');
                paneProfile.classList.remove('active');
            }
        }

        function updateStepperUI(status) {
            const progressBar = document.getElementById('stepperProgressBar');
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const step3Label = document.getElementById('step3Label');

            step1.className = 'step-item completed';
            step2.className = 'step-item';
            step3.className = 'step-item';

            if (status === 'Pending') {
                step1.className = 'step-item active';
                progressBar.style.width = '0%';
                step3Label.innerText = 'Approval';
                step3.querySelector('.step-circle').innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (status === 'Under Review') {
                step2.className = 'step-item active';
                progressBar.style.width = '50%';
                step3Label.innerText = 'Approval';
                step3.querySelector('.step-circle').innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (status === 'Approved') {
                step2.className = 'step-item completed';
                step3.className = 'step-item completed';
                progressBar.style.width = '100%';
                step3Label.innerText = 'Approved';
                step3.querySelector('.step-circle').innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (status === 'Rejected') {
                step2.className = 'step-item completed';
                step3.className = 'step-item rejected';
                progressBar.style.width = '100%';
                step3Label.innerText = 'Rejected';
                step3.querySelector('.step-circle').innerHTML = '<i class="fa-solid fa-xmark"></i>';
            }
        }

        function openPdfModal(title, filePath) {
            document.getElementById('pdfModalTitle').innerHTML = `<i class="fa-solid fa-file-pdf"></i> ${title}`;
            document.getElementById('pdfViewerIframe').src = filePath;
            document.getElementById('downloadPdfLink').href = filePath;
            document.getElementById('pdfViewerModal').classList.add('active');
        }

        function closePdfModal() {
            document.getElementById('pdfViewerModal').classList.remove('active');
            document.getElementById('pdfViewerIframe').src = '';
        }

        function printPdfDocument() {
            const iframe = document.getElementById('pdfViewerIframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString().replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }
    </script>
</body>
</html>