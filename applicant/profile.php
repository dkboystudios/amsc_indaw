<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Applicant's Profile";
$maxDate = date('Y-m-d');

// Fetch Active Programs
$programs = array();
$progSql = "SELECT * FROM programs WHERE IsActive = 1 ORDER BY ProgramName";
$progRes = mysqli_query($conn, $progSql);
while ($pRow = mysqli_fetch_assoc($progRes)) {
    $programs[] = $pRow;
}

// Fetch Current User Account Details & IsVerified status
$userAccount = [];
$uSql = "SELECT Username, Email, IsVerified FROM users WHERE ID = ?";
$uStmt = mysqli_prepare($conn, $uSql);
mysqli_stmt_bind_param($uStmt, 'i', $userID);
mysqli_stmt_execute($uStmt);
$uRes = mysqli_stmt_get_result($uStmt);
if ($row = mysqli_fetch_assoc($uRes)) {
    $userAccount = $row;
}
mysqli_stmt_close($uStmt);

// Fetch User's Audit Logs
$auditLogs = [];
$lSql = "SELECT Title, Description, LoggedAt FROM activity_logs WHERE UserID = ? ORDER BY LoggedAt DESC";
$lStmt = mysqli_prepare($conn, $lSql);
mysqli_stmt_bind_param($lStmt, 'i', $userID);
mysqli_stmt_execute($lStmt);
$lRes = mysqli_stmt_get_result($lStmt);
while ($lRow = mysqli_fetch_assoc($lRes)) {
    $auditLogs[] = $lRow;
}
mysqli_stmt_close($lStmt);

$appStatus = $userInfo['ApplicationStatus'] ?? 'Pending';
$isEditable = ($appStatus === 'Pending');

// -------------------------------------------------------------------------
// HANDLE FORM SUBMISSIONS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        // -- HANDLE SUBMIT APPLICATION --
        if ($action === 'submit_application') {
            $chkDocs = mysqli_query($conn, "SELECT COUNT(*) AS total FROM applicant_documents WHERE ApplicantID = " . ($userInfo['ApplicantID'] ?? 0));
            $docRow = mysqli_fetch_assoc($chkDocs);
            if (($docRow['total'] ?? 0) == 0) {
                throw new Exception("Please upload at least one requirement before submitting your application.");
            }

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Under Review', UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $userInfo['ApplicantID']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Application Submitted', 'Applicant submitted their application for review.');
            redirectWithMessage('success', 'Application successfully submitted for review!', 'profile?');
        }

        // -- HANDLE SEND VERIFICATION EMAIL --
        elseif ($action === 'send_verification') {
            $to = $userAccount['Email'];
            $subject = "Verify Your Email Address - AMSC InDaw";
            $verificationLink = "localhost/amsc_indaw/verify?userid={$userID}&verified=1";
            
            logActivity($conn, $userID, 'Verification Email Sent', 'Verification link dispatched to email.');
            redirectWithMessage('success', 'Verification link has been sent to your email address.', '../email_verification.php?');
        }

        // -- HANDLE PROFILE DETAILS UPDATE --
        elseif (isset($_POST['update_profile'])) {
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($isEditable) {
                $preferredProgram1 = filter_input(INPUT_POST, 'preferred_program_1', FILTER_VALIDATE_INT);
                $preferredProgram2 = filter_input(INPUT_POST, 'preferred_program_2', FILTER_VALIDATE_INT);
                $preferredProgram3 = filter_input(INPUT_POST, 'preferred_program_3', FILTER_VALIDATE_INT);

                $firstName = trim($_POST['first_name'] ?? '');
                $middleName = trim($_POST['middle_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $extName = trim($_POST['ext_name'] ?? '');

                $gender = trim($_POST['gender'] ?? '');
                $dob = trim($_POST['birth_date'] ?? null);

                if (empty($preferredProgram1) || empty($firstName) || empty($lastName) || empty($gender) || empty($dob) || empty($contactNumber) || empty($address)) {
                    throw new Exception("Incomplete information. Please fill in all required fields.");
                }

                if (($preferredProgram1 && $preferredProgram2 && $preferredProgram1 === $preferredProgram2) ||
                    ($preferredProgram1 && $preferredProgram3 && $preferredProgram1 === $preferredProgram3) ||
                    ($preferredProgram2 && $preferredProgram3 && $preferredProgram2 === $preferredProgram3)) {
                    throw new Exception("Selecting the same preferred program is not allowed.");
                }

                $uploadError = '';
                $fileUsername = strtoupper(str_replace(' ', '_', $firstName . '_' . $lastName));
                $newPhoto = handleImageUpload($conn, $userID, $fileUsername, 'profile_picture', $uploadError);
                $targetDir = '../assets/uploads/profiles/';

                if ($userInfo) {
                    $applicationNumber = trim($_POST['app_number'] ?? '');
                    if (empty($applicationNumber)) throw new Exception("Unknown application number.");

                    if ($newPhoto) {
                        if (!empty($userInfo['ProfilePicture']) && file_exists($targetDir . $userInfo['ProfilePicture'])) {
                            @unlink($targetDir . $userInfo['ProfilePicture']);
                        }
                        $updateSql = "UPDATE applicants SET PreferredProgramID_1 = ?, PreferredProgramID_2 = ?, PreferredProgramID_3 = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, Gender = ?, BirthDate = ?, ContactNumber = ?, Address = ?, ProfilePicture = ? WHERE ApplicationNumber = ?";
                        $updateStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($updateStmt, 'iiissssssssss', $preferredProgram1, $preferredProgram2, $preferredProgram3, $firstName, $middleName, $lastName, $extName, $gender, $dob, $contactNumber, $address, $newPhoto, $applicationNumber);
                    } else {
                        $updateSql = "UPDATE applicants SET PreferredProgramID_1 = ?, PreferredProgramID_2 = ?, PreferredProgramID_3 = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, Gender = ?, BirthDate = ?, ContactNumber = ?, Address = ? WHERE ApplicationNumber = ?";
                        $updateStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($updateStmt, 'iiisssssssss', $preferredProgram1, $preferredProgram2, $preferredProgram3, $firstName, $middleName, $lastName, $extName, $gender, $dob, $contactNumber, $address, $applicationNumber);
                    }
                    mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                } else {
                    $applicationNumber = 'APP-' . (date('Y') * 10000 + $userID); 
                    $insertSql = "INSERT INTO applicants (UserID, ApplicationNumber, FirstName, MiddleName, LastName, ExtName, Gender, BirthDate, ContactNumber, Address, PreferredProgramID_1, PreferredProgramID_2, PreferredProgramID_3, ProfilePicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = mysqli_prepare($conn, $insertSql);
                    $defaultAvatarName = $newPhoto ?: null;
                    mysqli_stmt_bind_param($insertStmt, 'isssssssssiiis', $userID, $applicationNumber, $firstName, $middleName, $lastName, $extName, $gender, $dob, $contactNumber, $address, $preferredProgram1, $preferredProgram2, $preferredProgram3, $defaultAvatarName);
                    mysqli_stmt_execute($insertStmt);
                    mysqli_stmt_close($insertStmt);
                }
            } else {
                if (empty($contactNumber) || empty($address)) {
                    throw new Exception("Contact Number and Address cannot be blank.");
                }
                $updStmt = mysqli_prepare($conn, "UPDATE applicants SET ContactNumber = ?, Address = ? WHERE UserID = ?");
                mysqli_stmt_bind_param($updStmt, 'ssi', $contactNumber, $address, $userID);
                mysqli_stmt_execute($updStmt);
                mysqli_stmt_close($updStmt);
            }

            logActivity($conn, $userID, 'Applicant Profile Updated', 'Applicant profile details updated successfully.');
            redirectWithMessage('success', 'Profile updated successfully.', 'profile?');
        }
        
        // -- HANDLE ACCOUNT CREDENTIALS & PASSWORD UPDATE --
        elseif (isset($_POST['update_account'])) {
            $newUsername = trim($_POST['username'] ?? '');
            $newEmail = trim($_POST['email'] ?? '');
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newUsername) || empty($newEmail)) {
                throw new Exception("Username and Email are required.");
            }

            $chkSql = "SELECT ID FROM users WHERE (Username = ? OR Email = ?) AND ID != ?";
            $chkStmt = mysqli_prepare($conn, $chkSql);
            mysqli_stmt_bind_param($chkStmt, 'ssi', $newUsername, $newEmail, $userID);
            mysqli_stmt_execute($chkStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
                throw new Exception("Username or Email is already taken by another user.");
            }
            mysqli_stmt_close($chkStmt);

            if (!empty($newPassword) || !empty($oldPassword) || !empty($confirmPassword)) {
                if (empty($oldPassword)) throw new Exception("Please enter your current (old) password.");
                if (empty($newPassword) || empty($confirmPassword)) throw new Exception("Fill out both new password fields.");
                if (strlen($newPassword) < 6) throw new Exception("New password must be at least 6 characters.");
                if ($newPassword !== $confirmPassword) throw new Exception("New passwords do not match.");

                $passStmt = mysqli_prepare($conn, "SELECT Password FROM users WHERE ID = ? LIMIT 1");
                mysqli_stmt_bind_param($passStmt, 'i', $userID);
                mysqli_stmt_execute($passStmt);
                $userPassRow = mysqli_fetch_assoc(mysqli_stmt_get_result($passStmt));
                mysqli_stmt_close($passStmt);

                if (!$userPassRow || !password_verify($oldPassword, $userPassRow['Password'])) {
                    throw new Exception("Incorrect current password.");
                }

                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updStmt = mysqli_prepare($conn, "UPDATE users SET Username = ?, Email = ?, Password = ? WHERE ID = ?");
                mysqli_stmt_bind_param($updStmt, 'sssi', $newUsername, $newEmail, $hashedPassword, $userID);
            } else {
                $updStmt = mysqli_prepare($conn, "UPDATE users SET Username = ?, Email = ? WHERE ID = ?");
                mysqli_stmt_bind_param($updStmt, 'ssi', $newUsername, $newEmail, $userID);
            }

            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            logActivity($conn, $userID, 'Account Settings Updated', 'User successfully updated account credentials.');
            redirectWithMessage('success', 'Account credentials updated successfully.', 'profile?');
        }
    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), 'profile?');
    }
}

function handleImageUpload($conn, $userID, $userName, $fileInputName, &$errorMessage = '') {
    $targetDir = '../assets/uploads/profiles/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;

    $fileExtension = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) return false;

    $newFileName = $userName . '-student-' . date('YmdHis') . '.' . $fileExtension;
    if (move_uploaded_file($file['tmp_name'], $targetDir . $newFileName)) return $newFileName;
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .profile-container { display: flex; flex-direction: column; gap: 28px; max-width: 950px; margin: 0 auto; padding-bottom: 40px; }
        .profile-section-card { background: #ffffff; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; }
        .profile-section-header { padding: 18px 24px; background: linear-gradient(to right, #f8fafc, #ffffff); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .profile-section-header h3 { font-size: 1.1rem; color: var(--primary); margin: 0; font-weight: 600; }
        .profile-section-body { padding: 26px 24px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 20px; }
        .form-row.single-col { grid-template-columns: 1fr; }
        .form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
        .form-row.four-col { grid-template-columns: 1fr 1fr 1fr 1fr; }
        @media (max-width: 768px) { .form-row, .form-row.three-col, .form-row.four-col { grid-template-columns: 1fr; gap: 16px; } }
        .profile-upload-wrapper { display: flex; align-items: center; gap: 30px; flex-wrap: wrap; }
        .profile-preview-container { width: 160px; height: 160px; border-radius: 50%; border: 4px solid #ffffff; box-shadow: 0 4px 10px rgba(0,0,0,0.15); overflow: hidden; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; }
        .profile-preview-container img { width: 100%; height: 100%; object-fit: cover; }
        .verified-badge { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .unverified-badge { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 100%; max-width: 450px; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }

        /* Activity Log Cards Styling */
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .activity-cards-grid { display: flex; flex-direction: column; gap: 12px; }
        .activity-card { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px 20px; display: flex; flex-direction: column; gap: 6px; transition: all 0.2s ease; }
        .activity-card:hover { border-color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .activity-card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .activity-card-title { font-weight: 600; color: var(--primary); font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
        .activity-card-time { font-size: 0.8rem; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 12px; }
        .activity-card-desc { font-size: 0.88rem; color: #475569; margin: 0; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Settings & Profile</h2>
                    <p class="text-muted">Manage your personal profile, account credentials, and submit applications.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="application-id-badge">
                        <span>App No:</span> <strong><?= htmlspecialchars($userInfo['ApplicationNumber'] ?? 'N/A') ?></strong>
                    </div>
                    <?php if ($appStatus === 'Pending'): ?>
                        <form action="profile" method="POST" onsubmit="return confirm('Are you sure you want to submit your application?');">
                            <input type="hidden" name="action" value="submit_application">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Submit Application</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="profile-container">

                <!-- 1. ACCOUNT & PASSWORD SETTINGS -->
                <form id="accountForm" method="post" action="profile" novalidate>
                    <input type="hidden" name="update_account" value="1">
                    <div class="profile-section-card">
                        <div class="profile-section-header">
                            <h3><i class="fa-solid fa-user-lock" style="color: var(--primary);"></i> Account Credentials & Password</h3>
                            <div>
                                <?php if (!empty($userAccount['IsVerified'])): ?>
                                    <span class="verified-badge"><i class="fa-solid fa-circle-check"></i> Verified Email</span>
                                <?php else: ?>
                                    <span class="unverified-badge"><i class="fa-solid fa-triangle-exclamation"></i> Unverified Email</span>
                                    <button type="button" class="btn btn-sm btn-primary" style="margin-left: 8px; padding: 2px 8px; font-size: 0.75rem;" onclick="redirect('../email_verification?status=info&message=Verification+link+has+been+sent+to+your+email+address.')">Verify Email Address</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-section-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username <span class="required">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($userAccount['Username'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($userAccount['Email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                            <h4 style="font-size: 0.95rem; color: var(--primary); margin-bottom: 15px;"><i class="fa-solid fa-key"></i> Change Password (Optional)</h4>
                            
                            <div class="form-row three-col">
                                <div class="form-group">
                                    <label for="old_password">Current Password</label>
                                    <input type="password" id="old_password" name="old_password" class="form-control" placeholder="Required for password change">
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="At least 6 characters">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type password">
                                </div>
                            </div>

                            <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 15px;">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-shield-halved"></i> Update Account Credentials</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- 2. PERSONAL PROFILE FORM -->
                <form id="profileForm" class="profile-form" method="post" action="profile" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="app_number" value="<?= htmlspecialchars($userInfo['ApplicationNumber'] ?? '') ?>">
                    
                    <div class="profile-section-card">
                        <div class="profile-section-header">
                            <h3><i class="fa-solid fa-camera-retro" style="color: var(--primary);"></i> Applicant Photo</h3>
                        </div>
                        <div class="profile-section-body">
                            <div class="profile-upload-wrapper">
                                <div class="profile-preview-container">
                                    <?php 
                                        $displayPhoto = (!empty($userInfo['ProfilePicture'])) 
                                            ? '../assets/uploads/profiles/' . htmlspecialchars($userInfo['ProfilePicture']) 
                                            : '../assets/images/default-avatar.png';
                                    ?>
                                    <img id="profilePreviewImg" src="<?= $displayPhoto ?>" alt="Profile Preview">
                                </div>
                                <div class="upload-actions">
                                    <h4 style="margin:0 0 5px 0;">Upload Formal ID Photo</h4>
                                    <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 12px;">Allowed: JPG, PNG, WEBP (Max 5MB).</p>
                                    <?php if ($isEditable): ?>
                                        <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" class="form-control">
                                    <?php else: ?>
                                        <small class="text-muted">Photo changes are locked since application is under review/processed.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section-card">
                        <div class="profile-section-header">
                            <h3><i class="fa-solid fa-graduation-cap" style="color: var(--primary);"></i> Preferred Degree Programs</h3>
                        </div>
                        <div class="profile-section-body">
                            <div class="form-row single-col">
                                <div class="form-group">
                                    <label for="preferred_program_1">1st Preference <span class="required">*</span></label>
                                    <select id="preferred_program_1" name="preferred_program_1" class="form-control" <?= !$isEditable ? 'disabled' : 'required' ?>>
                                        <option value="">-- Select 1st Choice --</option>
                                        <?php foreach($programs as $p): ?>
                                            <option value="<?= $p['ID'] ?>" <?= (isset($userInfo) && $p['ID'] == $userInfo['PreferredProgramID_1']) ? 'selected' : '' ?>><?= htmlspecialchars($p['ProgramName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="preferred_program_2">2nd Preference</label>
                                    <select id="preferred_program_2" name="preferred_program_2" class="form-control" <?= !$isEditable ? 'disabled' : '' ?>>
                                        <option value="">-- Optional --</option>
                                        <?php foreach($programs as $p): ?>
                                            <option value="<?= $p['ID'] ?>" <?= (isset($userInfo) && $p['ID'] == $userInfo['PreferredProgramID_2']) ? 'selected' : '' ?>><?= htmlspecialchars($p['ProgramName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="preferred_program_3">3rd Preference</label>
                                    <select id="preferred_program_3" name="preferred_program_3" class="form-control" <?= !$isEditable ? 'disabled' : '' ?>>
                                        <option value="">-- Optional --</option>
                                        <?php foreach($programs as $p): ?>
                                            <option value="<?= $p['ID'] ?>" <?= (isset($userInfo) && $p['ID'] == $userInfo['PreferredProgramID_3']) ? 'selected' : '' ?>><?= htmlspecialchars($p['ProgramName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section-card">
                        <div class="profile-section-header">
                            <h3><i class="fa-solid fa-address-card" style="color: var(--primary);"></i> Personal Information</h3>
                            <?php if (!$isEditable): ?>
                                <span class="text-muted" style="font-size: 0.8rem;"><i class="fa-solid fa-lock"></i> Restricted fields locked</span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-section-body">
                            <div class="form-row four-col">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($userInfo['FirstName'] ?? '') ?>" <?= !$isEditable ? 'readonly style="background:#f1f5f9;"' : 'required' ?>>
                                </div>
                                <div class="form-group">
                                    <label>Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($userInfo['MiddleName'] ?? '') ?>" <?= !$isEditable ? 'readonly style="background:#f1f5f9;"' : '' ?>>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($userInfo['LastName'] ?? '') ?>" <?= !$isEditable ? 'readonly style="background:#f1f5f9;"' : 'required' ?>>
                                </div>
                                <div class="form-group">
                                    <label>Suffix</label>
                                    <?php $currentExt = $userInfo['ExtName'] ?? ''; ?>
                                    <?php if ($isEditable): ?>
                                        <select name="ext_name" id="ext_name" class="form-control">
                                            <option value="" <?= ($currentExt === '') ? 'selected' : '' ?>>None</option>
                                            <option value="Jr." <?= ($currentExt === 'Jr.') ? 'selected' : '' ?>>Jr.</option>
                                            <option value="Sr." <?= ($currentExt === 'Sr.') ? 'selected' : '' ?>>Sr.</option>
                                            <option value="II" <?= ($currentExt === 'II') ? 'selected' : '' ?>>II</option>
                                            <option value="III" <?= ($currentExt === 'III') ? 'selected' : '' ?>>III</option>
                                            <option value="IV" <?= ($currentExt === 'IV') ? 'selected' : '' ?>>IV</option>
                                            <option value="V" <?= ($currentExt === 'V') ? 'selected' : '' ?>>V</option>
                                            <option value="VI" <?= ($currentExt === 'VI') ? 'selected' : '' ?>>VI</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($currentExt ?: 'None') ?>" readonly style="background:#f1f5f9;">
                                        <input type="hidden" name="ext_name" value="<?= htmlspecialchars($currentExt) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-row three-col">
                                <div class="form-group">
                                    <label>Sex</label>
                                    <?php $currentGender = $userInfo['Gender'] ?? ''; ?>
                                    <?php if (!$isEditable): ?>
                                        <input type="text" name="gender" class="form-control" value="<?= htmlspecialchars($userInfo['Gender'] ?? '') ?>" readonly style="background:#f1f5f9;" ?>>
                                    <?php else: ?>
                                        <select name="gender" id="gender" class="form-control">
                                            <option value="" <?= $currentGender === '' ? 'selected' : '' ?>>--Select Gender--</option>
                                            <option value="Male" <?= $currentGender === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= $currentGender === 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Birth Date</label>
                                    <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($userInfo['BirthDate'] ?? '') ?>" <?= $isEditable ? '' : 'readonly style="background:#f1f5f9;"' ?>>
                                </div>                                
                                <div class="form-group">
                                    <label>Contact Number <span class="required">*</span></label>
                                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($userInfo['ContactNumber'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-row single-col">
                                <div class="form-group">
                                    <label>Complete Residential Address <span class="required">*</span></label>
                                    <textarea name="address" rows="3" class="form-control" required><?= htmlspecialchars($userInfo['Address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions" style="display: flex; justify-content: flex-end; margin-top: 15px;">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Profile Details</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- 3. ACTIVITY LOGS CARDS SECTION -->
                <div class="profile-section-card">
                    <div class="profile-section-header">
                        <h3><i class="fa-solid fa-clipboard-list" style="color: var(--primary);"></i> Recent Activity Logs</h3>
                    </div>
                    <div class="profile-section-body">
                        <div class="filter-bar">
                            <span class="text-muted" style="font-size: 0.9rem;">Monitor your recent account actions.</span>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label for="logDateFilter" style="font-size: 0.9rem; font-weight: 600;"><i class="fa-regular fa-calendar"></i> Filter by Date:</label>
                                <input type="date" id="logDateFilter" class="form-control" max="<?= $maxDate ?>" style="width: auto; padding: 6px 12px;">
                            </div>
                        </div>

                        <div class="activity-cards-grid" id="activityCardsContainer">
                            <?php if (!empty($auditLogs)): ?>
                                <?php foreach ($auditLogs as $log): 
                                    $formattedDate = date('(l) M d, Y h:i A', strtotime($log['LoggedAt']));
                                    $rawDate = date('Y-m-d', strtotime($log['LoggedAt']));
                                ?>
                                    <div class="activity-card" data-date="<?= $rawDate ?>">
                                        <div class="activity-card-header">
                                            <span class="activity-card-title"><i class="fa-solid fa-circle-dot" style="font-size: 0.6rem; color: var(--primary);"></i> <?= htmlspecialchars($log['Title']) ?></span>
                                            <span class="activity-card-time"><?= htmlspecialchars($formattedDate) ?></span>
                                        </div>
                                        <p class="activity-card-desc"><?= htmlspecialchars($log['Description']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted" style="text-align: center; padding: 20px; margin: 0;">No recent activity found.</p>
                            <?php endif; ?>
                            
                            <div id="noLogsMessage" style="display: none; text-align: center; color: var(--text-muted); padding: 30px;">
                                No activity logs found for the selected date.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- VERIFICATION SENT MODAL -->
    <div id="verificationModal" class="modal-overlay">
        <div class="modal-card">
            <div style="padding:20px; background:var(--primary); color:#fff; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.1rem; color:#fff;">Email Verification Sent</h3>
                <button onclick="closeVerificationModal()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:24px; text-align:center;">
                <i class="fa-solid fa-paper-plane" style="font-size:3rem; color:var(--primary); margin-bottom:15px;"></i>
                <p style="font-size:0.95rem; color:#334155;">A verification link has been sent to your email address from <strong>sir.dkboy@gmail.com</strong>. Please check your inbox or spam folder.</p>
                <button class="btn btn-primary" onclick="closeVerificationModal()" style="margin-top:15px;">Got it</button>
            </div>
        </div>
    </div>

    <script>
        function openVerificationModal() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'profile';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'send_verification';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
        function closeVerificationModal() {
            document.getElementById('verificationModal').classList.remove('active');
        }
        <?php if(isset($_GET['msg']) && strpos($_GET['msg'], 'sent') !== false): ?>
            document.addEventListener('DOMContentLoaded', () => { document.getElementById('verificationModal').classList.add('active'); });
        <?php endif; ?>

        // Profile Picture Preview Logic
        document.addEventListener('DOMContentLoaded', () => {
            const profilePictureInput = document.getElementById('profilePictureInput');
            const profilePreviewImg = document.getElementById('profilePreviewImg');

            if (profilePictureInput && profilePreviewImg) {
                profilePictureInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profilePreviewImg.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Activity Logs Filter Logic for Cards
            const logDateFilter = document.getElementById('logDateFilter');
            const activityCards = document.querySelectorAll('.activity-card');
            const noLogsMessage = document.getElementById('noLogsMessage');

            if (logDateFilter) {
                logDateFilter.addEventListener('change', function() {
                    const selectedDate = this.value; // Format: YYYY-MM-DD
                    let hasVisibleCards = false;

                    activityCards.forEach(card => {
                        const cardDate = card.getAttribute('data-date');
                        if (!selectedDate || cardDate === selectedDate) {
                            card.style.display = '';
                            hasVisibleCards = true;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    if (!hasVisibleCards && activityCards.length > 0) {
                        noLogsMessage.style.display = 'block';
                    } else {
                        noLogsMessage.style.display = 'none';
                    }
                });
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>