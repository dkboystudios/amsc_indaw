<?php
// Start output buffering immediately to prevent stray whitespace or warnings from breaking execution
ob_start();
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Applicant's Dashboard";

// Get active term
$aySql = "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1";
$ayRes = mysqli_query($conn, $aySql);
$currentTerm = mysqli_fetch_assoc($ayRes);

$activeTermID = $currentTerm['ID'] ?? 1;
$activeTerm = 'AY' . ($currentTerm['YearCode'] ?? '') . ', ' . ($currentTerm['Semester'] ?? '');
$enrollmentStatus = $currentTerm['EnrollmentStatus'] ?? 'Closed';

function getDegreeCollege($conn, $programID) {
    if (!$programID) return null;
    $sql = "SELECT o.* FROM programs p INNER JOIN offices o ON p.CollegeID = o.ID WHERE p.ID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $programID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result) ?? null;
}

function getProgramName($conn, $programID) {
    if (!$programID) return 'None';
    $sql = "SELECT ProgramName FROM programs WHERE ID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $programID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result)['ProgramName'] ?? 'None';
}

// Fetch current applicant ID and documents for remarks lookup
$applicantID = $userInfo['ApplicantID'] ?? 0;
$docRemarksList = [];
if ($applicantID) {
    $docRes = mysqli_query($conn, "SELECT DocumentType, VerificationStatus, Remarks FROM applicant_documents WHERE ApplicantID = $applicantID");
    while ($dRow = mysqli_fetch_assoc($docRes)) {
        $docRemarksList[] = $dRow;
    }
}

// Check if student record exists for this user / applicant
$studentInfo = null;
$enrolledUnits = 0;
$totalCurriculumUnits = 0;
$computedYearLevel = 'N/A';

if ($applicantID) {
    $stSql = "SELECT s.*, p.ProgramCode, p.ProgramName, p.ID as ProgID 
              FROM students s 
              LEFT JOIN programs p ON s.ProgramID = p.ID 
              WHERE s.ApplicantID = ? LIMIT 1";
    $stStmt = mysqli_prepare($conn, $stSql);
    mysqli_stmt_bind_param($stStmt, 'i', $applicantID);
    mysqli_stmt_execute($stStmt);
    $stRes = mysqli_stmt_get_result($stStmt);
    $studentInfo = mysqli_fetch_assoc($stRes);
    mysqli_stmt_close($stStmt);
}

if ($studentInfo && isset($studentInfo['StudentID'])) {
    $studentID = $studentInfo['StudentID'];
    $programID = $studentInfo['ProgramID'];

    // 1. Calculate total enrolled units for active term
    $unitSql = "SELECT COALESCE(SUM(cs.Units), 0) as TotalUnits 
                FROM enrollments e 
                INNER JOIN classes c ON e.ClassID = c.ID 
                INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID 
                WHERE e.StudentID = ? AND c.AcademicYearID = ? AND e.Status = 'Enrolled'";
    $unitStmt = mysqli_prepare($conn, $unitSql);
    mysqli_stmt_bind_param($unitStmt, 'ii', $studentID, $activeTermID);
    mysqli_stmt_execute($unitStmt);
    $unitRes = mysqli_fetch_assoc(mysqli_stmt_get_result($unitStmt));
    $enrolledUnits = floatval($unitRes['TotalUnits'] ?? 0);
    mysqli_stmt_close($unitStmt);

    // 2. Fetch total units required in curriculum or estimate year level based on typical units per year (e.g. ~30-36 units per year level block)
    // Let's check curriculum subjects total for the program if available, or compute based on standard block bounds
    // Assuming standard year level mapping: 1st Year (1-40 units), 2nd Year (41-80 units), 3rd Year (81-120 units), 4th Year (121+ units) or standard cumulative earned units.
    // Let's query total passed/enrolled cumulative units or curriculum total:
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

    // Compute year level based on cumulative or current load baseline
    if ($cumulativeUnits <= 40) {
        $computedYearLevel = '1st Year';
    } elseif ($cumulativeUnits <= 80) {
        $computedYearLevel = '2nd Year';
    } elseif ($cumulativeUnits <= 120) {
        $computedYearLevel = '3rd Year';
    } else {
        $computedYearLevel = '4th Year / Senior';
    }
}

// Check if today is the applicant's birthday
$isBirthday = false;
if (!empty($userInfo['BirthDate'])) {
    $today = date('m-d');
    $bday = date('m-d', strtotime($userInfo['BirthDate']));
    if ($today === $bday) {
        $isBirthday = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>

    <style>
        .enrollment-alert-banner { padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .enrollment-alert-banner.open { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .enrollment-alert-banner.closed { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Hero Section Styling */
        .applicant-hero-card {
            background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 24px 30px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .hero-avatar {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            border: 3px solid #ffffff;
            object-fit: cover;
            background-color: #e2e8f0;
            flex-shrink: 0;
        }
        .hero-info h2 {
            margin: 0 0 5px 0;
            font-size: 1.5rem;
            color: #ffffff;
        }
        .hero-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* Birthday Banner */
        .birthday-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            color: #b45309;
            padding: 16px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .birthday-banner i {
            font-size: 1.8rem;
            color: #d97706;
        }

        /* Remarks Box Styles */
        .admin-remarks-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        .admin-remarks-box h5 {
            margin: 0 0 8px 0;
            color: var(--primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .current-program-highlight {
            background: #eff6ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dashboard-content">
            
            <!-- Hero Section Showing Name and Picture -->
            <?php 
                $profilePic = !empty($userInfo['ProfilePicture']) ? '../assets/uploads/profiles/' . htmlspecialchars($userInfo['ProfilePicture']) : '../assets/images/default-avatar.png';
                $fullName = trim(($userInfo['FirstName'] ?? '') . ' ' . ($userInfo['LastName'] ?? '')) ?: 'Valued Applicant';
            ?>
            <div class="applicant-hero-card">
                <img src="<?= $profilePic ?>" alt="Profile Picture" class="hero-avatar">
                <div class="hero-info">
                    <h2>Welcome back, <?= htmlspecialchars($fullName) ?>!</h2>
                    <p>Track your admission milestones, review document verification status, and check program preferences below.</p>
                </div>
            </div>

            <!-- Birthday Greeting Section (Shows only on birthday) -->
            <?php if ($isBirthday): ?>
                <div class="birthday-banner">
                    <i class="fa-solid fa-cake-candles"></i>
                    <div>
                        <span>Happy Birthday, <?= htmlspecialchars($userInfo['FirstName'] ?? 'Applicant') ?>! 🥳🎂</span>
                        <p style="margin: 2px 0 0 0; font-weight: normal; font-size: 0.88rem;">The entire Adiong Memorial State College community wishes you success and happiness on your special day!</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="content-header" style="margin-top: 0;">
                <div>
                    <h2>Application & Student Overview</h2>
                    <p class="text-muted">Track your admission status, current degree enrollment, and academic progress.</p>
                </div>
                <div class="application-id-badge">
                    <span>App No:</span> <strong><?= htmlspecialchars($userInfo['ApplicationNumber'] ?? 'N/A') ?></strong>
                    <?php if (!empty($studentInfo['StudentNumber'])): ?>
                        | <span>Student No:</span> <strong><?= htmlspecialchars($studentInfo['StudentNumber']) ?></strong>
                    <?php endif; ?>
                </div>
            </div>

            <!-- HIGHLIGHTED CURRENT PROGRAM & YEAR LEVEL COMPUTATION CARD -->
            <?php if ($studentInfo && !empty($studentInfo['ProgramName'])): ?>
                <div class="current-program-highlight">
                    <div>
                        <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; font-weight: bold; color: #1d4ed8;">Active Enrolled Program</span>
                        <h3 style="margin: 4px 0 2px 0; color: #1e3a8a; font-size: 1.25rem;">
                            <i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($studentInfo['ProgramName']) ?> (<?= htmlspecialchars($studentInfo['ProgramCode']) ?>)
                        </h3>
                        <p style="margin: 0; font-size: 0.9rem; color: #475569;">
                            Computed Year Level: <strong><?= htmlspecialchars($computedYearLevel) ?></strong> | Enrolled Units This Term: <strong><?= $enrolledUnits ?> Units</strong>
                        </p>
                    </div>
                    <div>
                        <span class="badge badge-success" style="padding: 8px 14px; font-size: 0.9rem; background: #2563eb; color: #fff; border-radius: 6px;">
                            <?= htmlspecialchars($studentInfo['EnrollmentStatus'] ?? 'Enrolled') ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

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

            <?php
                $userStatus = isset($studentInfo) ? $studentInfo['EnrollmentStatus'] : ($userInfo['ApplicationStatus'] ?? 'Pending');

                $statusBadge = 'fa-clock';
                $statusDescription = 'Complete your profile and submit your credentials.';

                switch ($userStatus) {
                    case 'Pending':
                        $statusBadge = "fa-clock";
                        $statusDescription = "Complete your profile and submit your credentials.";
                        break;
                    
                    case 'Under Review':
                        $statusBadge = "fa-magnifying-glass";
                        $statusDescription = "Your documents have been submitted and are currently being verified by the Admissions Office.";
                        break;

                    case 'Rejected':
                        $statusBadge = "fa-triangle-exclamation";
                        $statusDescription = "Your application has been rejected. Fix and re-upload your credentials and re-try.";
                        break;

                    case 'Approved':
                        $statusBadge = "fa-check";
                        $statusDescription = "Your application has been approved. Welcome to Adiong Memorial State College.";
                        break;

                    case 'Pre-Enrolled':
                        $statusBadge = "fa-circle";
                        $statusDescription = "Enroll in subjects to secure slots.";
                        break;

                    case 'Enrolled':
                        $statusBadge = 'fa-check';
                        $statusDescription = "You're now officially enrolled.";
                        break;

                    case 'Dropped':
                        $statusBadge = 'fa-ban';
                        $statusDescription = "You are dropped from your degree program.";
                        break;

                    case 'Graduated':
                        $statusBadge = 'fa-graduation-cap';
                        $statusDescription = "Congratulations! You're now ready for your next chapter in life.";
                        break;
                }
            ?>

            <!-- Status Banner -->
            <div class="card status-banner status-under-review" style="margin-bottom: 25px;">
                <div class="status-icon">
                    <i class="fa-solid <?= $statusBadge ?>"></i>
                </div>
                <div class="status-info">
                    <span class="status-label">Application Status</span>
                    <h3 class="status-title"><?= htmlspecialchars($userStatus) ?></h3>
                    <p><?= htmlspecialchars($statusDescription) ?></p>
                </div>
            </div>

            <!-- Steps & Progress Tracking -->
            <?php
                $hasProfile = !empty($userInfo['FirstName']);
                $rawStatus = $userInfo['ApplicationStatus'] ?? 'Pending';

                $s1State = 'completed';
                $s2State = $hasProfile ? 'completed' : ($rawStatus === 'Pending' ? 'active' : 'pending');
                $s3State = ($rawStatus === 'Under Review') ? 'active' : (($rawStatus === 'Approved' || $rawStatus === 'Rejected') ? 'completed' : 'pending');
                $s4State = ($rawStatus === 'Approved') ? 'completed' : (($rawStatus === 'Rejected') ? 'rejected' : 'pending');
            ?>
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h3><i class="fa-solid fa-list-check"></i> Admission Progress & Admin Remarks</h3>
                </div>
                <div class="card-body">
                    <div class="progress-tracker">
                        <div class="tracker-step <?= $s1State ?>">
                            <div class="step-marker"><i class="fa-solid fa-check"></i></div>
                            <div class="step-details">
                                <h4>Account Created</h4>
                                <p>Verified email & credentials</p>
                            </div>
                        </div>

                        <div class="tracker-step <?= $s2State ?>">
                            <div class="step-marker"><?= $s2State === 'completed' ? '<i class="fa-solid fa-check"></i>' : '2' ?></div>
                            <div class="step-details">
                                <h4>Profile & Choices</h4>
                                <p>Personal details & preferred programs</p>
                            </div>
                        </div>

                        <div class="tracker-step <?= $s3State ?>">
                            <div class="step-marker"><?= $s3State === 'completed' ? '<i class="fa-solid fa-check"></i>' : '3' ?></div>
                            <div class="step-details">
                                <h4>Document Verification</h4>
                                <p>Review by registrar & admissions office</p>
                            </div>
                        </div>

                        <div class="tracker-step <?= $s4State ?>">
                            <div class="step-marker"><?= $s4State === 'completed' ? '<i class="fa-solid fa-check"></i>' : '4' ?></div>
                            <div class="step-details">
                                <h4>Approval & Student ID</h4>
                                <p>Official Student Number assignment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Remarks Section -->
                    <div class="admin-remarks-box">
                        <h5><i class="fa-solid fa-comment-dots"></i> Latest Administrator Remarks & Feedback</h5>
                        <?php if (!empty($userInfo['Remarks'])): ?>
                            <p style="margin: 0 0 10px 0;"><strong>Overall Application Note:</strong> <?= htmlspecialchars($userInfo['Remarks']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($docRemarksList)): ?>
                            <div style="font-size: 0.88rem;">
                                <strong>Document Feedback:</strong>
                                <ul style="margin: 4px 0 0 20px; padding: 0;">
                                    <?php foreach ($docRemarksList as $d): ?>
                                        <li>
                                            <em><?= htmlspecialchars($d['DocumentType']) ?></em> [Status: <strong><?= htmlspecialchars($d['VerificationStatus']) ?></strong>] 
                                            <?= !empty($d['Remarks']) ? '- "' . htmlspecialchars($d['Remarks']) . '"' : '- No specific remarks.' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php elseif (empty($userInfo['Remarks'])): ?>
                            <p style="margin: 0; color: var(--text-muted);">No remarks or feedback recorded from the admissions office yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            $program1 = $userInfo['PreferredProgramID_1'] ?? 0;
            $program2 = $userInfo['PreferredProgramID_2'] ?? 0;
            $program3 = $userInfo['PreferredProgramID_3'] ?? 0;

            $college1 = getDegreeCollege($conn, $program1);
            $college2 = getDegreeCollege($conn, $program2);
            $college3 = getDegreeCollege($conn, $program3);

            $chosenProgram = $studentInfo['ProgramID'] ?? 0;
            ?>

            <!-- Preferred Programs Summary -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-graduation-cap"></i> Preferred Degree Programs</h3>
                    <a href="profile" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                </div>
                <div class="card-body">
                    <div class="program-choices-list">
                        <div class="choice-item <?= $program1 === $chosenProgram ? 'choice-primary' : '' ?>">
                            <span class="choice-badge"><?= $program1 === $chosenProgram ? 'Chosen' : '1st Choice' ?></span>
                            <div class="choice-details">
                                <h4><?= htmlspecialchars(getProgramName($conn, $program1)) ?></h4>
                                <p><?= htmlspecialchars($college1['OfficeName'] ?? 'Unspecified College') ?> (<?= htmlspecialchars($college1['OfficeCode'] ?? 'N/A') ?>)</p>
                            </div>
                        </div>

                        <div class="choice-item <?= $program2 === $chosenProgram ? 'choice-primary' : '' ?>">
                            <span class="choice-badge"><?= $program2 === $chosenProgram ? 'Chosen' : '2nd Choice' ?></span>
                            <div class="choice-details">
                                <h4><?= htmlspecialchars(getProgramName($conn, $program2)) ?></h4>
                                <p><?= htmlspecialchars($college2['OfficeName'] ?? 'Unspecified College') ?> (<?= htmlspecialchars($college2['OfficeCode'] ?? 'N/A') ?>)</p>
                            </div>
                        </div>

                        <div class="choice-item <?= $program3 === $chosenProgram ? 'choice-primary' : '' ?>">
                            <span class="choice-badge"><?= $program3 === $chosenProgram ? 'Chosen' : '3rd Choice' ?></span>
                            <div class="choice-details">
                                <h4><?= htmlspecialchars(getProgramName($conn, $program3)) ?></h4>
                                <p><?= htmlspecialchars($college3['OfficeName'] ?? 'Unspecified College') ?> (<?= htmlspecialchars($college3['OfficeCode'] ?? 'N/A') ?>)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../includes/snackbar.php'; ?>

    <script src="../assets/js/main.js"></script>
</body>
</html>