<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "User Account Management - AMSC InnDaw";

// Ensure $userID is safely available from session
$userID = $userID ?? ($user['ID'] ?? 0);

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'admin';

// Handle Form POST Requests (Add Staff Account, Update Credentials/Status, Delete, Reset Password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $tabTarget = trim($_POST['tab_target'] ?? 'admin');

    try {
        if ($action === 'save-user') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = trim($_POST['status'] ?? 'Active');
            $isVerified = (trim($_POST['verified'] ?? 'true') === 'true') ? 1 : 0;
            $roleID = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT) ?? 1;

            if ($id) {
                // Update Existing Account (Staff can update username/email/status/role; Students restricted to Status & Verification)
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if (!empty($username) && !empty($email)) {
                    $sql = "UPDATE users SET Username = ?, Email = ?, Status = ?, IsVerified = ?, RoleID = ? WHERE ID = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
                    mysqli_stmt_bind_param($stmt, "sssiii", $username, $email, $status, $isVerified, $roleID, $id);
                } else {
                    // Student update restriction safeguard (status/verification only)
                    $sql = "UPDATE users SET Status = ?, IsVerified = ? WHERE ID = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
                    mysqli_stmt_bind_param($stmt, "sii", $status, $isVerified, $id);
                }

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Execution failed: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Account Update Successful', "Updated account profile and status for User ID: {$username}.");
                redirectWithMessage('success', 'Account details updated successfully.', "users?");
            } else {
                // Create New Staff Account from Employee Pool with Auto-composed Username/Email & Default Password
                $staffID = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if (!$staffID || empty($username) || empty($email)) {
                    throw new Exception("Please select an employee and provide both username and email address.");
                }

                // Default password requirement: "qwertyuiop2026"
                $defaultPassword = password_hash('qwertyuiop2026', PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (Username, Email, Password, RoleID, IsVerified, Status) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
                $defaultVerified = 1;
                $defaultStatus = 'Active';
                mysqli_stmt_bind_param($stmt, "sssiss", $username, $email, $defaultPassword, $roleID, $defaultVerified, $defaultStatus);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Execution failed: " . mysqli_stmt_error($stmt));
                }
                $newUserID = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Link UserID back to staff_members
                $linkStmt = mysqli_prepare($conn, "UPDATE staff_members SET UserID = ? WHERE StaffID = ?");
                mysqli_stmt_bind_param($linkStmt, "ii", $newUserID, $staffID);
                mysqli_stmt_execute($linkStmt);
                mysqli_stmt_close($linkStmt);

                logActivity($conn, $userID, 'Account Creation Successful', "Created staff account for username: {$username}.");
                redirectWithMessage('success', 'Staff account successfully created with default password (qwertyuiop2026).', "users?tab=admin?");
            }
        } elseif ($action === 'delete-user') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception("Invalid user ID specified for deletion.");

            $fetchStmt = mysqli_prepare($conn, "SELECT Username FROM users WHERE ID = ?");
            mysqli_stmt_bind_param($fetchStmt, "i", $id);
            mysqli_stmt_execute($fetchStmt);
            $res = mysqli_stmt_get_result($fetchStmt);
            $targetUser = mysqli_fetch_assoc($res);
            mysqli_stmt_close($fetchStmt);

            $delStmt = mysqli_prepare($conn, "DELETE FROM users WHERE ID = ?");
            if (!$delStmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
            mysqli_stmt_bind_param($delStmt, "i", $id);
            if (!mysqli_stmt_execute($delStmt)) {
                throw new Exception("Execution failed: " . mysqli_stmt_error($delStmt));
            }
            mysqli_stmt_close($delStmt);

            $targetName = $targetUser['Username'] ?? "ID #$id";
            logActivity($conn, $userID, 'User Account Deleted', "Permanently deleted user account: {$targetName}.");
            redirectWithMessage('success', 'User account permanently deleted.', "users?tab={$tabTarget}?");

        } elseif ($action === 'reset-password') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception("Invalid user ID specified for password reset.");

            // Reset password to default: "qwertyuiop2026"
            $defaultPassword = password_hash('qwertyuiop2026', PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET Password = ? WHERE ID = ?");
            if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "si", $defaultPassword, $id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Execution failed: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Password Reset Successful', "Reset password securely to default for user: {$id}.");
            redirectWithMessage('success', 'User password successfully reset to default (qwertyuiop2026).', "users?tab={$tabTarget}?");
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logActivity($conn, $userID, 'User Action Failed', $errorMsg);
        redirectWithMessage('error', $errorMsg, "users?tab={$tabTarget}?");
    }
}

// List of roles
$roleList = array();
$roleSql = "SELECT * FROM roles ORDER BY RoleName";
$roleResult = mysqli_query($conn, $roleSql);
if ($roleResult) {
    while ($roleRow = mysqli_fetch_assoc($roleResult)) {
        $roleList[] = $roleRow;
    }
}


// Fetch staff/admin users from database
$adminList = array();
$adminSql = "SELECT u.*, r.Module, r.RoleName FROM users u INNER JOIN roles r ON u.RoleID = r.ID WHERE r.Module = 'admin' ORDER BY Username ASC";
$adminResult = mysqli_query($conn, $adminSql);
if ($adminResult) {
    while ($adminRow = mysqli_fetch_assoc($adminResult)) {
        $adminList[] = $adminRow;
    }
}
$adminCount = count($adminList);

// Fetch student/applicant users from database
$studentList = array();
$studentSql = "SELECT u.*, r.Module, r.RoleName FROM users u INNER JOIN roles r ON u.RoleID = r.ID WHERE r.Module = 'applicant' ORDER BY Username ASC";
$studentResult = mysqli_query($conn, $studentSql);
if ($studentResult) {
    while ($studentRow = mysqli_fetch_assoc($studentResult)) {
        $studentList[] = $studentRow;
    }
}
$studentCount = count($studentList);

// Fetch active employees/staff members who don't have accounts yet for the creation pool
$employeesPool = array();
$empSql = "SELECT StaffID, FirstName, LastName, EmployeeNumber FROM staff_members WHERE UserID IS NULL ORDER BY LastName ASC";
$empResult = mysqli_query($conn, $empSql);
if ($empResult) {
    while ($empRow = mysqli_fetch_assoc($empResult)) {
        $employeesPool[] = $empRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        /* --- User Management Layout Styles --- */
        .users-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Tabs Styling */
        .user-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 5px;
        }

        .tab-btn {
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #ffffff;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-top: 1px solid var(--border-color);
        }

        .filter-toolbar {
            display: flex;
            gap: 15px;
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .toolbar-left {
            display: flex;
            gap: 12px;
            flex: 1;
            flex-wrap: wrap;
        }

        .filter-toolbar input, .filter-toolbar select {
            padding: 9px 14px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            outline: none;
        }

        .filter-toolbar input {
            min-width: 240px;
            flex: 1;
        }

        /* Users Table Card */
        .users-table-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .users-table th, .users-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .users-table th {
            background-color: #f8fafc;
            color: var(--primary);
            font-weight: 700;
        }

        .users-table tr:hover {
            background-color: #f8fafc;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            background-color: #e2e8f0;
            flex-shrink: 0;
        }

        .username {
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 2px 0;
        }

        .user-subtext {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Status & Role Badges */
        .badge-role {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            display: inline-block;
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

        .badge-status.suspended {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-status.locked {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-verified {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-verified.true {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-verified.false {
            background-color: #f1f5f9;
            color: #64748b;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }

        /* --- FIXED MODAL STYLES & LAYOUT --- */
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
            max-width: 650px;
            border-radius: 10px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
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
            flex-shrink: 0;
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
            padding: 20px 24px;
            max-height: calc(75vh - 120px);
            overflow-y: auto;
            flex-grow: 1;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--primary);
        }

        .modal-footer {
            padding: 14px 24px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }

        /* Switch Toggle Component */
        .switch-container {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-top: 4px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
            flex-shrink: 0;
        }

        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        /* Activity Log List Styling */
        .activity-log-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }

        .activity-log-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
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
                    <h2>User Account Management</h2>
                    <p class="text-muted">Manage staff & student credentials, verification status, account states, and roles.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($tab === 'admin'): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openUserModal('add')">
                            <i class="fa-solid fa-user-plus"></i> Create Staff Account
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="users-container">

                <!-- Tab Navigation -->
                <div class="user-tabs">
                    <button type="button" class="tab-btn <?= (isset($tab) && $tab === 'admin') || empty($tab) ? 'active' : '' ?>" id="tabStaffBtn" onclick="window.location.href='users?tab=admin'">
                        <i class="fa-solid fa-user-shield"></i> Staff Accounts (<?= $adminCount ?>)
                    </button>
                    <button type="button" class="tab-btn <?= isset($tab) && $tab === 'student' ? 'active' : '' ?>" id="tabStudentBtn" onclick="window.location.href='users?tab=student'">
                        <i class="fa-solid fa-user-graduate"></i> Student / Applicant Accounts (<?= $studentCount ?>)
                    </button>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="filter-toolbar">
                    <div class="toolbar-left">
                        <input type="text" id="searchInput" placeholder="Search by username, email, or name...">
                        
                        <select id="statusFilter">
                            <option value="">Filter Account Status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="locked">Locked</option>
                        </select>
                    </div>
                </div>

                <!-- Users Table Card -->
                <div class="users-table-card">
                    <div style="overflow-x: auto;">
                        <table class="users-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>User Profile</th>
                                    <th>Username & Email</th>
                                    <th id="roleHeader">Role ID</th>
                                    <th>Verification</th>
                                    <th>Account Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php $usersList = ($tab === 'student') ? $studentList : $adminList; ?>
                                <?php if (!empty($usersList)): ?>
                                    <?php foreach ($usersList as $usr): 
                                        $usrID = $usr['ID'];
                                        $statusStr = $usr['Status'] ?? 'Active';
                                        $statusClass = strtolower($statusStr);
                                        $isVerifiedBool = !empty($usr['IsVerified']) && $usr['IsVerified'] != 0;
                                        $isStudentTab = ($tab === 'student');
                                        $isLoggedIn = $usr['Email'] === $user['Email'];

                                        $tblName = $isStudentTab ? 'applicants' : 'staff_members';

                                        $usrInfo = array();
                                        $infoSql = "SELECT ProfilePicture, FirstName, LastName, ExtName FROM $tblName WHERE UserID = ?";
                                        $infoStmt = mysqli_prepare($conn, $infoSql);
                                        mysqli_stmt_bind_param($infoStmt, 'i', $usrID);
                                        mysqli_stmt_execute($infoStmt);
                                        $infoResult = mysqli_stmt_get_result($infoStmt);
                                        $usrInfo = mysqli_fetch_assoc($infoResult);
                                    ?>
                                        <tr data-category="<?= $isStudentTab ? 'student' : 'staff' ?>" data-name="<?= htmlspecialchars(strtolower($usr['Username'] ?? '')) ?>" data-email="<?= htmlspecialchars(strtolower($usr['Email'] ?? '')) ?>" data-status="<?= $statusClass ?>">
                                            <td>
                                                <div class="user-info-cell">
                                                    <img src="<?= isset($usrInfo['ProfilePicture']) ? '/amsc_indaw/assets/uploads/profiles/' . $usrInfo['ProfilePicture'] : $defaultProfilePhoto ?>" alt="Avatar" class="user-avatar">
                                                    <div>
                                                        <h4 class="username"><?= htmlspecialchars(isset($usrInfo) ? $usrInfo['LastName'] . ', ' . $usrInfo['FirstName'] . ' ' . $usrInfo['ExtName']  : $usr['Username']) ?></h4>
                                                        <span class="user-subtext">ID: <?= $tab === 'student' ? 'STD' : 'EMP' ?>-<?= date('Y', strtotime($usr['CreatedAt'])) * 10000 + $usr['ID'] ?? 0 ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($usr['Username'] ?? '') ?></strong>
                                                <div class="user-subtext"><?= htmlspecialchars($usr['Email'] ?? '') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge-role"><?= htmlspecialchars($usr['RoleName'] ?? '') ?></span>
                                            </td>
                                            <td>
                                                <span class="badge-verified <?= $isVerifiedBool ? 'true' : 'false' ?>">
                                                    <i class="fa-solid <?= $isVerifiedBool ? 'fa-check' : 'fa-xmark' ?>"></i> <?= $isVerifiedBool ? 'Verified' : 'Unverified' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status <?= $statusClass ?>">
                                                    <i class="fa-solid <?= $statusClass === 'active' ? 'fa-circle-check' : ($statusClass === 'suspended' ? 'fa-triangle-exclamation' : 'fa-lock') ?>"></i> 
                                                    <?= ucfirst($statusStr) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openViewLogsModal(<?= $usr['ID'] ?? 0 ?>, '<?= htmlspecialchars($usr['Username'] ?? '') ?>')" title="View Audit Logs"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                                    <?php if (!$isLoggedIn) { ?>
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openUserModal('edit', <?= $usr['ID'] ?? 0 ?>, '<?= htmlspecialchars($usr['Username'] ?? '') ?>', '<?= htmlspecialchars($usr['Email'] ?? '') ?>', '<?= $usr['RoleID'] ?? 3 ?>', '<?= $isVerifiedBool ? 'true' : 'false' ?>', '<?= $statusClass ?>', <?= $isStudentTab ? 'true' : 'false' ?>)" title="Edit Account Status"><i class="fa-solid fa-pen"></i></button>
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openResetPasswordModal(<?= $usr['ID'] ?? 0 ?>, '<?= htmlspecialchars($usr['Email'] ?? '') ?>')" title="Reset Password (qwertyuiop2026)"><i class="fa-solid fa-key"></i></button>
                                                        <form action="users?tab=<?= $tab ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user account?');">
                                                            <input type="hidden" name="action" value="delete-user">
                                                            <input type="hidden" name="id" value="<?= $usr['ID'] ?? 0 ?>">
                                                            <input type="hidden" name="tab_target" value="<?= $tab ?>">
                                                            <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                        </form>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px; color: var(--text-muted);">No user accounts found in this category.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- CREATE / EDIT USER MODAL -->
    <div id="userModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="userModalTitle"><i class="fa-solid fa-user-plus"></i> Create Staff Account</h3>
                <button type="button" class="modal-close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userForm" action="users?tab=<?= $tab ?>" method="POST">
                    <input type="hidden" name="action" value="save-user">
                    <input type="hidden" name="id" id="userIdField" value="">
                    <input type="hidden" name="tab_target" value="<?= $tab ?>">

                    <!-- Staff Selection Pool (Only visible on Add mode) -->
                    <div class="form-group" id="employeePoolGroup">
                        <label>Select Active Employee *</label>
                        <select name="staff_id" id="staffIdSelect" class="form-control" onchange="autoComposeStaffCredentials(this)">
                            <option value="">-- Choose Employee from Pool --</option>
                            <?php foreach ($employeesPool as $emp): ?>
                                <option value="<?= $emp['StaffID'] ?>" data-firstname="<?= htmlspecialchars($emp['FirstName']) ?>" data-lastname="<?= htmlspecialchars($emp['LastName']) ?>">
                                    <?= htmlspecialchars($emp['LastName'] . ', ' . $emp['FirstName'] . ' (' . ($emp['EmployeeNumber'] ?? 'No ID') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">Default password will be automatically set to <strong>qwertyuiop2026</strong>.</small>
                    </div>

                    <!-- Username & Email Fields -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" id="userUsername" class="form-control" required placeholder="Auto-composed or editable">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" id="userEmail" class="form-control" required placeholder="user@amsc.edu.ph">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group" id="roleGroup">
                            <label>Role ID *</label>
                            <select name="role_id" id="userRole" class="form-control">
                                <?php foreach ($roleList as $role): ?>
                                    <?php 
                                        $module = $tab === 'student' ? 'applicant' : 'admin';
                                        if ($module === $role['Module']) {
                                     ?>
                                        <option value="<?= $role['ID'] ?>"><?= $role['RoleName'] ?></option>
                                    <?php } ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Verified Toggle Switch -->
                        <div class="form-group">
                            <label>Verification Status</label>
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" name="verified_checkbox" id="userVerifiedCheckbox" value="true" onchange="document.getElementById('userVerifiedInput').value = this.checked ? 'true' : 'false';">
                                    <span class="slider"></span>
                                </label>
                                <span id="verifiedLabelText" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">Verified (True)</span>
                            </div>
                            <input type="hidden" name="verified" id="userVerifiedInput" value="true">
                        </div>
                    </div>

                    <!-- Account Status Select -->
                    <div class="form-group">
                        <label>Account Status *</label>
                        <select name="status" id="userStatus" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                            <option value="Locked">Locked</option>
                        </select>
                    </div>

                    <div class="modal-footer" style="margin: 20px -24px -20px -24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RESET PASSWORD MODAL (Default Password: qwertyuiop2026) -->
    <div id="resetPasswordModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-key"></i> Reset User Password</h3>
                <button type="button" class="modal-close-btn" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="resetPassForm" action="users?tab=<?= $tab ?>" method="POST">
                    <input type="hidden" name="action" value="reset-password">
                    <input type="hidden" name="id" id="resetUserIdField" value="">
                    <input type="hidden" name="tab_target" value="<?= $tab ?>">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Target Email</label>
                        <input type="text" id="resetTargetEmail" class="form-control" readonly style="background-color: #f1f5f9;">
                    </div>
                    <p class="text-muted" style="font-size: 0.85rem;">
                        Clicking update will automatically reset this account's password to the default standard: <strong>qwertyuiop2026</strong>.
                    </p>

                    <div class="modal-footer" style="margin: 20px -24px -20px -24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset to Default Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW AUDIT ACTIVITY LOGS MODAL -->
    <div id="logsModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Account Audit Activity Logs</h3>
                <button type="button" class="modal-close-btn" onclick="closeLogsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-top: 0; font-size: 0.9rem; color: var(--primary);">Displaying activity history for: <strong id="logUserName">User Account</strong></p>
                <div id="activityLogsContainer" style="max-height: 300px; overflow-y: auto;">
                    <div style="text-align: center; color: var(--text-muted); padding: 20px;">Loading logs...</div>
                </div>
            </div>
            <div class="modal-footer" style="margin: 20px -24px -20px -24px;">
                <button type="button" class="btn btn-secondary" onclick="closeLogsModal()" style="margin-right: 20px; margin-bottom: 20px;">Close</button>
            </div>
        </div>
    </div>

    <?php include '../includes/snackbar.php'; ?>

    <!-- Vanilla JS Interactivity Script -->
    <script>
        const userModal = document.getElementById('userModal');
        const resetPasswordModal = document.getElementById('resetPasswordModal');
        const logsModal = document.getElementById('logsModal');
        const tableBody = document.getElementById('usersTableBody');

        function autoComposeStaffCredentials(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (!selectedOption.value) {
                document.getElementById('userUsername').value = '';
                document.getElementById('userEmail').value = '';
                return;
            }
            const firstName = selectedOption.getAttribute('data-firstname') || '';
            const lastName = selectedOption.getAttribute('data-lastname') || '';
            
            if (firstName && lastName) {
                const cleanFirst = firstName.toLowerCase().replace(/[^a-z0-9]/g, '');
                const cleanLast = lastName.toLowerCase().replace(/[^a-z0-9]/g, '');
                const generatedUsername = cleanFirst.charAt(0) + cleanLast;
                const generatedEmail = `${cleanFirst}.${cleanLast}@amsc.edu.ph`;

                document.getElementById('userUsername').value = generatedUsername;
                document.getElementById('userEmail').value = generatedEmail;
            }
        }

        function openUserModal(mode, id = '', username = '', email = '', roleId = 1, verified = true, status = 'Active', isStudent = false) {
            const isVerifiedBool = (verified === 'true' || verified === true || verified === '1' || verified === 1);
            
            // Set verified switch & hidden input
            document.getElementById('userVerifiedCheckbox').checked = isVerifiedBool;
            document.getElementById('userVerifiedInput').value = isVerifiedBool ? 'true' : 'false';
            document.getElementById('verifiedLabelText').textContent = isVerifiedBool ? 'Verified (True)' : 'Unverified (False)';

            if (mode === 'add') {
                document.getElementById('userModalTitle').innerHTML = `<i class="fa-solid fa-user-plus"></i> Create Staff Account`;
                document.getElementById('userForm').reset();
                document.getElementById('userIdField').value = '';
                document.getElementById('employeePoolGroup').style.display = 'block';
                document.getElementById('staffIdSelect').setAttribute('required', 'required');
                document.getElementById('userUsername').removeAttribute('readonly');
                document.getElementById('userEmail').removeAttribute('readonly');
                document.getElementById('roleGroup').style.display = 'block';
                document.getElementById('userVerifiedCheckbox').checked = true;
                document.getElementById('userVerifiedInput').value = 'true';
                document.getElementById('verifiedLabelText').textContent = 'Verified (True)';
            } else {
                document.getElementById('userModalTitle').innerHTML = `<i class="fa-solid fa-user-pen"></i> Edit Account (${username})`;
                document.getElementById('userIdField').value = id;
                document.getElementById('employeePoolGroup').style.display = 'none';
                document.getElementById('staffIdSelect').removeAttribute('required');
                
                document.getElementById('userUsername').value = username;
                document.getElementById('userEmail').value = email;
                document.getElementById('userStatus').value = status.charAt(0).toUpperCase() + status.slice(1);
                
                document.getElementById('userRole').value = roleId;

                if (isStudent) {
                    // Restrict student credentials editing (username/email/role locked)
                    document.getElementById('userUsername').setAttribute('readonly', 'readonly');
                    document.getElementById('userEmail').setAttribute('readonly', 'readonly');
                } else {
                    document.getElementById('userUsername').removeAttribute('readonly');
                    document.getElementById('userEmail').removeAttribute('readonly');
                }
            }
            userModal.classList.add('active');
        }

        function closeUserModal() {
            userModal.classList.remove('active');
        }

        // Reset Password Modal
        function openResetPasswordModal(id, email) {
            document.getElementById('resetUserIdField').value = id;
            document.getElementById('resetTargetEmail').value = email;
            resetPasswordModal.classList.add('active');
        }

        function closeResetPasswordModal() {
            resetPasswordModal.classList.remove('active');
        }

        // Audit Logs Modal with AJAX / DB Fetch
        function openViewLogsModal(userId, userName) {
            document.getElementById('logUserName').textContent = userName;
            const container = document.getElementById('activityLogsContainer');
            container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading activity logs from database...</div>';
            logsModal.classList.add('active');

            // Fetch activity logs asynchronously from `activity_logs` table
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `../api/get_activity_logs?user_id=${userId}`, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.logs.length > 0) {
                            let html = '';
                            response.logs.forEach(log => {
                                html += `
                                    <div class="activity-log-item">
                                        <div class="activity-icon"><i class="fa-solid fa-circle-info"></i></div>
                                        <div>
                                            <strong>${escapeHtml(log.Title)}</strong>
                                            <div class="text-muted">${escapeHtml(log.Description)}</div>
                                            <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;"><i class="fa-regular fa-clock"></i> ${log.LoggedAt}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = html;
                        } else {
                            container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">No audit activity logs recorded for this user yet.</div>';
                        }
                    } catch (e) {
                        container.innerHTML = '<div style="text-align: center; color: #dc2626; padding: 20px;">Failed to parse activity logs response.</div>';
                    }
                } else {
                    container.innerHTML = '<div style="text-align: center; color: #dc2626; padding: 20px;">Error fetching audit logs from server.</div>';
                }
            };
            xhr.send();
        }

        function closeLogsModal() {
            logsModal.classList.remove('active');
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function filterTable() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const selectedStatus = document.getElementById('statusFilter').value.toLowerCase();
            const rows = Array.from(tableBody.querySelectorAll('tr'));

            rows.forEach(row => {
                if (!row.getAttribute('data-category')) return;
                const text = row.textContent.toLowerCase();
                const statusAttr = row.getAttribute('data-status').toLowerCase();

                const matchesQuery = text.includes(query);
                const matchesStatus = !selectedStatus || statusAttr === selectedStatus;

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            if (searchInput) searchInput.addEventListener('input', filterTable);
            if (statusFilter) statusFilter.addEventListener('change', filterTable);
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>