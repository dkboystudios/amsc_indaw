<?php
// Start output buffering immediately to prevent stray whitespace or warnings from breaking execution
ob_start();
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "$userFullName's Profile - AMSC InnDaw";

$maxDate = date('Y-m-d');

// Upload directory configuration
$uploadDir = '../assets/uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle Update/Insert Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'profile-update') {
        $firstName = trim($_POST['first-name'] ?? '');
        $middleName = trim($_POST['middle-name'] ?? '');
        $lastName = trim($_POST['last-name'] ?? '');
        $extName = trim($_POST['ext-name'] ?? null);

        $gender = trim($_POST['gender'] ?? null);
        $dateOfBirth = trim($_POST['date-of-birth'] ?? null);
        $contactNumber = trim($_POST['contact-number'] ?? '');

        $address = trim($_POST['address'] ?? '');

        $employeeNumber = trim($_POST['employee-number'] ?? '');
        $employmentStatus = trim($_POST['employment-status'] ?? null);

        try {
            if (!isset($userInfo)) {
                // Process Photo Upload
                $uploadError = '';
                $photoNewFileName = handleImageUpload($conn, $userID, $userName, 'profile-picture', $uploadError);

                // Insert Staff Member
                $sql = "INSERT INTO staff_members (UserID, EmployeeNumber, FirstName, MiddleName, LastName, ExtName, ContactNumber, Gender, EmploymentStatus, DateOfBirth, HomeAddress, ProfilePicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = mysqli_prepare($conn, $sql);

                if (!$insertStmt) {
                    throw new Exception("Prepared statement error (Profile creation): " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param($insertStmt, "isssssssssss", $userID, $employeeNumber, $firstName, $middleName, $lastName, $extName, $contactNumber, $gender, $employmentStatus, $dateOfBirth, $address, $photoNewFileName);

                if (!mysqli_stmt_execute($insertStmt)) {
                    throw new Exception("Statement execution (Profile creation): " . mysqli_stmt_error($insertStmt));
                }
                mysqli_stmt_close($insertStmt);

                logActivity($conn, $userID, 'Profile Creation Successful', 'Creates profile information (new staff member).');
                redirectWithMessage('success', 'Successfully created a staff member profile', 'profile?');
            } else {
                // If user info exists, do updates instead
                $staffID = filter_input(INPUT_POST, 'staff-id', FILTER_VALIDATE_INT) ?? 0;

                // Process Photo Upload
                $uploadError = '';
                $photoNewFileName = handleImageUpload($conn, $userID, $userName, 'profile-picture', $uploadError);

                $updateStmt = null;
                $successMsg = '';

                if ($photoNewFileName) {
                    // Delete previous profile picture file if it exists
                    $oldImgSql = "SELECT ProfilePicture FROM staff_members WHERE UserID = ?";
                    $oldStmt = mysqli_prepare($conn, $oldImgSql);
                    mysqli_stmt_bind_param($oldStmt, 'i', $userID);
                    mysqli_stmt_execute($oldStmt);
                    $resOld = mysqli_stmt_get_result($oldStmt);
                    if ($oldRow = mysqli_fetch_assoc($resOld)) {
                        $oldPictureFilePath = $uploadDir . $oldRow['ProfilePicture'];
                        if (!empty($oldRow['ProfilePicture']) && file_exists($oldPictureFilePath)) {
                            unlink($oldPictureFilePath);
                        }
                    }
                    mysqli_stmt_close($oldStmt);

                    $updateSql = "UPDATE staff_members SET EmployeeNumber = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, ContactNumber = ?, Gender = ?, EmploymentStatus = ?, DateOfBirth = ?, HomeAddress = ?, ProfilePicture = ? WHERE UserID = ?";
                    $updateStmt = mysqli_prepare($conn, $updateSql);
                    mysqli_stmt_bind_param($updateStmt, 'sssssssssssi', $employeeNumber, $firstName, $middleName, $lastName, $extName, $contactNumber, $gender, $employmentStatus, $dateOfBirth, $address, $photoNewFileName, $userID);
                    $successMsg = "Profile details and picture updated successfully.";
                } else {
                    $updateSql = "UPDATE staff_members SET EmployeeNumber = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, ContactNumber = ?, Gender = ?, EmploymentStatus = ?, DateOfBirth = ?, HomeAddress = ? WHERE StaffID = ?";
                    $updateStmt = mysqli_prepare($conn, $updateSql);
                    mysqli_stmt_bind_param($updateStmt, 'ssssssssssi', $employeeNumber, $firstName, $middleName, $lastName, $extName, $contactNumber, $gender, $employmentStatus, $dateOfBirth, $address, $staffID);
                    $successMsg = "Profile details updated successfully.";
                }

                if (!mysqli_stmt_execute($updateStmt)) {
                    throw new Exception("Statement execution error (Profile updating): " . mysqli_stmt_error($updateStmt));
                }
                mysqli_stmt_close($updateStmt);
                logActivity($conn, $userID, 'Profile Update Successful.', $successMsg);
                redirectWithMessage('success', $successMsg, 'profile?');
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            logActivity($conn, $userID, 'Profile Update Failed.', $errorMessage);
            redirectWithMessage('error', $errorMessage, 'profile?');
        }  
    } else if ($action === 'account-update') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        try {
            $sql = "UPDATE users SET Email = ?, Username = ? WHERE ID = ?";
            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception("Prepared statement error (User account update): " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "ssi", $email, $username, $userID);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Statement execution error (User account update): " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Account Update Successful', 'Updates account credentials successfully.');
            redirectWithMessage('success', 'Account details updated successfully.', 'profile?');
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            logActivity($conn, $userID, 'Account Update Failed', $errorMessage);
            redirectWithMessage('error', $errorMessage, 'profile?');
        }
    } else if ($action === 'password-update') {
        $oldPassword = trim($_POST['old-password'] ?? '');
        $newPassword = trim($_POST['new-password'] ?? '');
        $confirmPassword = trim($_POST['confirm-password'] ?? '');

        try {            
            // VALIDATE PASSWORD
            if (!password_verify($oldPassword, $user['Password']) && $oldPassword !== $user['Password']) {
                throw new Exception("Incorrect current password!");
            }

            if (strlen($newPassword) < 4) {
                throw new Exception("Passwords must have at least four (4) characters.");
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception("Passwords mismatched.");
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $passSql = "UPDATE users SET Password = ? WHERE ID = ?";
            $passStmt = mysqli_prepare($conn, $passSql);
            
            if (!$passStmt) {
                throw new Exception("Prepared statement error (Password update): " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($passStmt, "si", $hashedPassword, $userID);

            if (!mysqli_stmt_execute($passStmt)) {
                throw new Exception("Statement execution error (Password update): " . mysqli_stmt_error($passStmt));
            }
            mysqli_stmt_close($passStmt);

            logActivity($conn, $userID, 'Password Update Successful', 'Updates account password successfully.');
            redirectWithMessage('success', 'Password updated successfully.', 'profile?');
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            logActivity($conn, $userID, 'Password Update Failed', $errorMessage);
            redirectWithMessage('error', $errorMessage, 'profile?');
        }
    } else {
        logActivity($conn, $userID, 'Profile Action Stopped', 'Unsupported action prevented.');
        redirectWithMessage('error', 'Profile Action Stopped. Unsupported action prevented.', 'profile?');
    }
}

// Secure Helper to handle File Uploads with dependencies passed safely
function handleImageUpload($conn, $userID, $userName, $fileInputName, &$errorMessage = '') {
    // Relative upload directory inside assets/uploads/profiles/
    $targetDir = '../assets/uploads/profiles/';

    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fileInputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload error code: ' . $file['error'];
        logActivity($conn, $userID, 'Profile Picture Update Failed.', $errorMessage);
        redirectWithMessage('error', $errorMessage, 'profile?');
        return false;
    }

    // 1. Check file size limit (Max 5 MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errorMessage = 'Uploaded file exceeds the maximum allowed size (5 MB).';
        logActivity($conn, $userID, 'Profile Picture Update Failed.', $errorMessage);
        redirectWithMessage('error', $errorMessage, 'profile?');
        return false;
    }

    // 2. Extension validation
    $fileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        $errorMessage = 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP files are allowed.';
        logActivity($conn, $userID, 'Profile Picture Update Failed.', $errorMessage);
        redirectWithMessage('error', $errorMessage, 'profile?');
        return false;
    }

    // 3. MIME type validation using Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $errorMessage = 'Invalid image MIME type detected.';
        logActivity($conn, $userID, 'Profile Picture Update Failed.', $errorMessage);
        redirectWithMessage('error', $errorMessage, 'profile?');
        return false;
    }

    // 4. Secure cryptographic random filename generation
    $newFileName = $userName . '-admin-' . date('YmdHis') . '.' . $fileExtension;
    $destPath = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        logActivity($conn, $userID, 'Profile Picture Update Successful', 'Updates new profile picture successfully.');
        return $newFileName;
    }

    $errorMessage = 'Failed to save uploaded image on the server.';
    logActivity($conn, $userID, 'Profile Picture Update Failed.', $errorMessage);
    redirectWithMessage('error', $errorMessage, 'profile?');
    return false;
}

// Check if today is the user's birthday
$isBirthday = false;
if (!empty($userInfo['DateOfBirth'])) {
    $today = date('m-d');
    $bday = date('m-d', strtotime($userInfo['DateOfBirth']));
    if ($today === $bday) {
        $isBirthday = true;
    }
}

// Fetch Audit Logs for the current user with optional date and search filters
$logSearch = trim($_GET['log_search'] ?? '');
$logDate = trim($_GET['log_date'] ?? '');

$logQuery = "SELECT * FROM activity_logs WHERE UserID = ?";
$logParams = [$userID];
$logTypes = "i";

if (!empty($logSearch)) {
    $logQuery .= " AND (Title LIKE ? OR Description LIKE ?)";
    $searchTerm = "%{$logSearch}%";
    array_push($logParams, $searchTerm, $searchTerm);
    $logTypes .= "ss";
}

if (!empty($logDate)) {
    $logQuery .= " AND DATE(LoggedAt) = ?";
    array_push($logParams, $logDate);
    $logTypes .= "s";
}

$logQuery .= " ORDER BY LoggedAt DESC LIMIT 50";

$logStmt = mysqli_prepare($conn, $logQuery);
if ($logStmt) {
    mysqli_stmt_bind_param($logStmt, $logTypes, ...$logParams);
    mysqli_stmt_execute($logStmt);
    $logResult = mysqli_stmt_get_result($logStmt);
    $userLogs = [];
    while ($row = mysqli_fetch_assoc($logResult)) {
        $userLogs[] = $row;
    }
    mysqli_stmt_close($logStmt);
} else {
    $userLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        /* --- Profile Page Styles & Improved UI --- */
        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        .profile-sidebar-card, .profile-form-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.02);
            overflow: hidden;
            margin-bottom: 24px;
            transition: box-shadow 0.3s ease;
        }

        .profile-sidebar-card:hover, .profile-form-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }

        .profile-header-bg {
            background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%);
            padding: 35px 20px;
            text-align: center;
            color: #ffffff;
        }

        .profile-avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 12px auto;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.8);
            background-color: #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .profile-meta-list {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .meta-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .meta-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-value {
            font-weight: 600;
            color: var(--primary);
            text-align: right;
        }

        /* Status Badges */
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status.active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-status.inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-verified {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-verified.true {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-verified.false {
            background-color: #f1f5f9;
            color: #64748b;
        }

        /* Verification Action Box Inside Sidebar */
        .verification-action-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 16px;
            margin: 0 20px 20px 20px;
            text-align: center;
        }

        /* Hero Birthday Greeting Banner */
        .hero-birthday-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 24px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            animation: fadeIn 0.5s ease-in-out;
        }

        .hero-birthday-icon {
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .hero-birthday-content h3 {
            margin: 0 0 4px 0;
            font-size: 1.4rem;
            color: #ffffff;
        }

        .hero-birthday-content p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        /* Form Card Styles */
        .form-section-title {
            font-size: 1.05rem;
            color: var(--primary);
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-form-body {
            padding: 28px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        .form-grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3, .form-grid-4 {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 10px 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Audit Logs Table Styles */
        .audit-logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
        }

        .audit-logs-table th, .audit-logs-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .audit-logs-table th {
            background-color: #f8fafc;
            color: var(--primary);
            font-weight: 700;
        }

        .audit-logs-table tr:hover {
            background-color: #f8fafc;
        }

        .audit-toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .audit-toolbar input, .audit-toolbar select {
            padding: 9px 14px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
        }

        .audit-toolbar input[type="text"] {
            flex: 1;
            min-width: 220px;
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
                    <h2><?= htmlspecialchars($userFirstName ?? 'User') ?>'s Profile Settings</h2>
                    <p class="text-muted">Manage your personal credentials, contact details, and account security settings.</p>
                </div>
            </div>

            <!-- Hero-Sized Birthday Greeting (Displays only on the user's birthday) -->
            <?php if ($isBirthday): ?>
                <div class="hero-birthday-banner">
                    <div class="hero-birthday-icon">
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>
                    <div class="hero-birthday-content">
                        <h3>Happy Birthday, <?= htmlspecialchars($userFirstName ?? 'Valued User') ?>! 🥳🎂</h3>
                        <p>Wishing you a fantastic day filled with joy, success, and wonderful moments. Thank you for your continued dedication to Adiong Memorial State College!</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="profile-container">

                <!-- SIDEBAR: PROFILE OVERVIEW CARD -->
                <div class="profile-sidebar-card">
                    <div class="profile-header-bg">
                        <div class="profile-avatar-container">
                            <img src="<?= htmlspecialchars($userProfilePicture ?? '../assets/images/default-avatar.png') ?>" alt="Profile Picture" class="profile-avatar" id="sidebarAvatarPreview">
                        </div>
                        <h3 style="margin: 0 0 4px 0; font-size: 1.15rem;" id="sidebarFullName"><?= isset($userInfo) ? htmlspecialchars($userFullName) : "Employee's Name" ?></h3>
                        <p style="margin: 0; font-size: 0.85rem; opacity: 0.85;" id="sidebarUsername">@<?= htmlspecialchars($userName) ?></p>
                    </div>

                    <div class="profile-meta-list">
                        <div class="meta-item">
                            <span class="meta-label">User ID</span>
                            <span class="meta-value"><?= htmlspecialchars($userID) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Role ID</span>
                            <span class="meta-value"><?= htmlspecialchars($userRole['RoleName'] ?? 'N/A') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Employment Status</span>
                            <span class="meta-value"><?= htmlspecialchars($userInfo['EmploymentStatus'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Account Status</span>
                            <span class="meta-value"><span class="badge-status <?= isset($user) && $user['Status'] === 'Active' ? 'active' : 'inactive' ?>"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($user['Status'] ?? 'Active') ?></span></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Verification</span>
                            <span class="meta-value"><span class="badge-verified <?= isset($user) && $user['IsVerified'] ? 'true' : 'false' ?>"><?= isset($user) && $user['IsVerified'] ? 'Verified' : 'Unverified' ?></span></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Account Created</span>
                            <span class="meta-value"><?= isset($user['CreatedAt']) ? date('F d, Y', strtotime($user['CreatedAt'])) : 'N/A' ?></span>
                        </div>
                    </div>

                    <!-- Verification Button Box (Shows if user is NOT verified) -->
                    <?php if (isset($user) && empty($user['IsVerified'])): ?>
                        <div class="verification-action-box">
                            <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #b45309; font-weight: 600;">Your account is currently unverified.</p>
                            <a href="../email_verification?" class="btn btn-sm btn-primary" style="width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-shield-halved"></i> Verify Account Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- MAIN: EDITABLE PROFILE & SECURITY FORM -->
                <div>
                    <div class="profile-form-card">
                        <div style="padding: 18px 28px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary);"><i class="fa-solid fa-user-pen"></i> Edit Profile Details</h3>
                        </div>

                        <div class="profile-form-body">
                            <!-- FIXED: Added enctype="multipart/form-data" to support image uploads -->
                            <form id="profileUpdateForm" action="profile?" method="post" enctype="multipart/form-data">

                                <input type="hidden" name="staff-id" value="<?= $userInfo['StaffID'] ?? '' ?>">
                                <input type="hidden" name="action" value="profile-update">
                                
                                <!-- Personal Information -->
                                <h4 class="form-section-title"><i class="fa-solid fa-address-card"></i> Personal Information</h4>

                                <div class="form-group">
                                    <label>Profile Picture Upload</label>
                                    <input type="file" name="profile-picture" id="inputAvatarFile" class="form-control" accept="image/*" onchange="previewAvatar(event)">
                                </div>

                                <div class="form-grid-4">
                                    <div class="form-group">
                                        <label>First Name *</label>
                                        <input type="text" name="first-name" id="inputFirstName" class="form-control" value="<?= htmlspecialchars($userInfo['FirstName'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Middle Name</label>
                                        <input type="text" name="middle-name" id="inputMiddleName" class="form-control" value="<?= htmlspecialchars($userInfo['MiddleName'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name *</label>
                                        <input type="text" name="last-name" id="inputLastName" class="form-control" value="<?= htmlspecialchars($userInfo['LastName'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Suffix</label>
                                        <select name="ext-name" id="inputExtName" class="form-control">
                                            <option value="" <?= isset($userInfo) && empty($userInfo['ExtName']) ? 'selected' : '' ?>>None</option>
                                            <option value="Jr." <?= isset($userInfo) && $userInfo['ExtName'] === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                            <option value="Sr." <?= isset($userInfo) && $userInfo['ExtName'] === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                            <option value="II" <?= isset($userInfo) && $userInfo['ExtName'] === 'II' ? 'selected' : '' ?>>II</option>
                                            <option value="III" <?= isset($userInfo) && $userInfo['ExtName'] === 'III' ? 'selected' : '' ?>>III</option>
                                            <option value="IV" <?= isset($userInfo) && $userInfo['ExtName'] === 'IV' ? 'selected' : '' ?>>IV</option>
                                            <option value="V" <?= isset($userInfo) && $userInfo['ExtName'] === 'V' ? 'selected' : '' ?>>V</option>
                                            <option value="VI" <?= isset($userInfo) && $userInfo['ExtName'] === 'VI' ? 'selected' : '' ?>>VI</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Sex *</label>
                                        <select name="gender" id="selectGender" class="form-control" required>
                                            <option value="" <?= isset($userInfo) && empty($userInfo['Gender']) ? 'selected' : '' ?>>--No Sex Selected--</option>
                                            <option value="Male" <?= isset($userInfo) && $userInfo['Gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= isset($userInfo) && $userInfo['Gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Date of Birth</label>
                                        <input type="date" name="date-of-birth" id="inputDob" class="form-control" max="<?= $maxDate ?>" value="<?= htmlspecialchars($userInfo['DateOfBirth'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="tel" name="contact-number" id="inputContact" class="form-control" placeholder="e.g., 09xx xxx xxxx" value="<?= htmlspecialchars($userInfo['ContactNumber'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Home Residential Address</label>
                                    <input type="text" name="address" id="inputAddress" class="form-control" value="<?= htmlspecialchars($userInfo['HomeAddress'] ?? '') ?>">
                                </div>

                                <!-- Employment Information -->
                                <h4 class="form-section-title" style="margin-top: 30px;"><i class="fa-solid fa-briefcase"></i> Employment Information</h4>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Employee ID Number</label>
                                        <input type="text" name="employee-number" id="inputEmployeeNumber" class="form-control" placeholder="e.g., AMSC-EMP-2026001" value="<?= htmlspecialchars($userInfo['EmployeeNumber'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Employment Status</label>
                                        <select name="employment-status" id="selectEmploymentStatus" class="form-control">
                                            <option value="" <?= isset($userInfo) && empty($userInfo['EmploymentStatus']) ? 'selected' : '' ?>>--No Employee Status Selected--</option>
                                            <option value="Full-Time" <?= isset($userInfo) && $userInfo['EmploymentStatus'] === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                                            <option value="Part-Time" <?= isset($userInfo) && $userInfo['EmploymentStatus'] === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                                            <option value="Contractual" <?= isset($userInfo) && $userInfo['EmploymentStatus'] === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                </div>

                            </form>
                        </div>
                    </div>

                    <div class="profile-form-card">
                        <div style="padding: 18px 28px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary);"><i class="fa-solid fa-user-pen"></i> Edit Security Credentials</h3>
                        </div>

                        <div class="profile-form-body">
                            <form action="profile?" method="post" id="accountUpdateForm">

                                <input type="hidden" name="action" value="account-update">

                                <!-- Account Information -->
                                <h4 class="form-section-title"><i class="fa-solid fa-address-card"></i> Account Information</h4>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Username *</label>
                                        <input type="text" name="username" id="inputUsername" class="form-control" value="<?= htmlspecialchars($userName) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email Address *</label>
                                        <input type="email" name="email" id="inputEmail" class="form-control" value="<?= htmlspecialchars($user['Email'] ?? '') ?>" placeholder="mail@example.com" required>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                </div>
                            </form>

                            <form action="profile?" method="post" id="passwordUpdateForm" style="margin-top: 35px;">

                                <input type="hidden" name="action" value="password-update">

                                <!-- Password Update Section -->
                                <h4 class="form-section-title" style="margin-top: 24px;"><i class="fa-solid fa-key"></i> Security & Password Update</h4>
                                <p class="text-muted" style="font-size: 0.85rem; margin-top: -12px; margin-bottom: 18px;">Leave password fields blank if you do not wish to change your current password.</p>

                                <div class="form-grid-3">
                                    <div class="form-group">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <label style="margin: 0;">Current Password</label>
                                        </div>
                                        <input type="password" id="inputOldPassword" class="form-control" name="old-password" placeholder="Enter current password" required>
                                    </div>
                                    <div class="form-group">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <label style="margin: 0;">New Password</label>
                                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="generateProfilePassword()">
                                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                                            </button>
                                        </div>
                                        <input type="password" id="inputNewPassword" class="form-control" name="new-password" placeholder="Enter new password" required>
                                    </div>
                                    <div class="form-group">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <label style="margin: 0;">Confirm New Password</label>
                                            <button type="button" id="toggle-password-btn" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="togglePassword()">
                                                <i class="fa-solid fa-eye" id="toggleIcon"></i>
                                            </button>
                                        </div>
                                        <input type="password" id="inputConfirmPassword" class="form-control" name="confirm-password" placeholder="Confirm new password" required>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save New Password</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- USER AUDIT LOGS CARD -->
                    <div class="profile-form-card">
                        <div style="padding: 18px 28px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary);"><i class="fa-solid fa-clock-rotate-left"></i> My Activity & Audit Logs</h3>
                            <span class="text-muted" style="font-size: 0.85rem;">Showing latest activities</span>
                        </div>

                        <div class="profile-form-body">
                            <!-- Search & Date Filter Toolbar -->
                            <form method="GET" action="profile?" class="audit-toolbar">
                                <input type="text" name="log_search" value="<?= htmlspecialchars($logSearch) ?>" placeholder="Search action or details...">
                                <input type="date" name="log_date" value="<?= htmlspecialchars($logDate) ?>" class="form-control" style="width: auto;">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                                <?php if (!empty($logSearch) || !empty($logDate)): ?>
                                    <a href="profile?" class="btn btn-secondary btn-sm" style="text-decoration: none;">Reset</a>
                                <?php endif; ?>
                            </form>

                            <div style="overflow-x: auto;">
                                <table class="audit-logs-table">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($userLogs)): ?>
                                            <?php foreach ($userLogs as $log): ?>
                                                <tr>
                                                    <td style="white-space: nowrap; font-size: 0.85rem; color: var(--text-muted);">
                                                        <?= htmlspecialchars($log['LoggedAt']) ?>
                                                    </td>
                                                    <td>
                                                        <strong style="color: var(--primary);"><?= htmlspecialchars($log['Title']) ?></strong>
                                                    </td>
                                                    <td style="font-size: 0.85rem; color: var(--text-color);">
                                                        <?= htmlspecialchars($log['Description'] ?? 'No details recorded') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; padding: 25px; color: var(--text-muted);">No audit logs found matching your criteria.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../includes/snackbar.php'; ?>

    <!-- Vanilla JS Interactivity Script -->
    <script>
        // Secure Random Password Generator Function
        function generateSecurePassword(length = 12) {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*";
            let password = "";
            for (let i = 0; i < length; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return password;
        }

        function generateProfilePassword() {
            const pwd = generateSecurePassword(12);
            document.getElementById('inputNewPassword').value = pwd;
            document.getElementById('inputConfirmPassword').value = pwd;
        }

        function togglePassword() {
            let oldPassInput = document.getElementById('inputOldPassword');
            let newPassInput = document.getElementById('inputNewPassword');
            let confirmPassInput = document.getElementById('inputConfirmPassword');
            let toggleIcon = document.getElementById('toggleIcon');

            if (newPassInput.type === 'password') {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                oldPassInput.type = 'text';
                newPassInput.type = 'text';
                confirmPassInput.type = 'text';
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                oldPassInput.type = 'password';
                newPassInput.type = 'password';
                confirmPassInput.type = 'password';
            }
        }

        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('sidebarAvatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>